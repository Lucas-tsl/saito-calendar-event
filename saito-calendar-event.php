<?php
/**
 * Plugin Name: Saito Calendar Event
 * Plugin URI: https://github.com/Lucas-tsl/saito-calendar-event
 * Description: Calendriers à cases déblocables par date (calendrier de l'Avent, compte à rebours d'un événement…) : chaque case révèle un texte, une image, un produit et, avec WooCommerce, un code promo ajoutable au panier.
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Troteseil Lucas
 * Author URI: https://github.com/Lucas-tsl
 * Text Domain: saito-calendar-event
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAITO_CE_VERSION', '1.1.0' );
define( 'SAITO_CE_FILE', __FILE__ );
define( 'SAITO_CE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SAITO_CE_URL', plugin_dir_url( __FILE__ ) );
define( 'SAITO_CE_POST_TYPE', 'saito_calendar_event' );
define( 'SAITO_CE_CASES_META', '_saito_ce_cases' );
define( 'SAITO_CE_STYLE_META', '_saito_ce_style' );
define( 'SAITO_CE_COLORS_META', '_saito_ce_colors' );
define( 'SAITO_CE_LOCKED_MESSAGE_META', '_saito_ce_locked_message' );
define( 'SAITO_CE_TEXTS_META', '_saito_ce_texts' );
define( 'SAITO_CE_OPEN_COUNT_META', '_saito_ce_open_count' );
define( 'SAITO_CE_SCHEDULE_META', '_saito_ce_schedule' );
define( 'SAITO_CE_HEADER_META', '_saito_ce_show_header' );
define( 'SAITO_CE_SUBTITLE_META', '_saito_ce_subtitle' );

/**
 * Enregistre le post type "Calendrier" : un post = un template de calendrier
 * (ex. "Calendrier de l'Avent 2026"), configuré une fois puis affiché via
 * shortcode ou bloc partout où on veut.
 */
function saito_ce_register_post_type() {
	register_post_type(
		SAITO_CE_POST_TYPE,
		array(
			'labels'              => array(
				'name'               => __( 'Calendriers', 'saito-calendar-event' ),
				'singular_name'      => __( 'Calendrier', 'saito-calendar-event' ),
				'add_new_item'       => __( 'Ajouter un calendrier', 'saito-calendar-event' ),
				'edit_item'          => __( 'Modifier le calendrier', 'saito-calendar-event' ),
				'all_items'          => __( 'Calendriers', 'saito-calendar-event' ),
				'not_found'          => __( 'Aucun calendrier trouvé.', 'saito-calendar-event' ),
			),
			'menu_icon'            => 'dashicons-calendar-alt',
			'public'               => false,
			'publicly_queryable'   => false,
			'show_ui'              => true,
			'show_in_menu'         => saito_hub_menu_slug(),
			'show_in_admin_bar'    => false,
			'show_in_rest'         => false,
			'exclude_from_search'  => true,
			'supports'             => array( 'title' ),
			'capability_type'      => 'post',
			'map_meta_cap'         => true,
		)
	);
}
add_action( 'init', 'saito_ce_register_post_type' );

/**
 * WooCommerce est optionnel : sans lui, les cases révèlent leur texte et
 * leur image, mais ne proposent ni code promo au panier ni fiche produit.
 */
function saito_ce_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Rappel affiché uniquement sur les écrans des calendriers (pas sur tout
 * l'admin) quand WooCommerce est absent.
 */
function saito_ce_admin_notice_missing_woocommerce() {
	if ( saito_ce_is_woocommerce_active() ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || SAITO_CE_POST_TYPE !== $screen->post_type ) {
		return;
	}
	echo '<div class="notice notice-info"><p>' .
		esc_html__( 'WooCommerce n’est pas actif : les cases révèlent leur texte et leur image, mais les codes promo et les produits liés ne sont pas disponibles.', 'saito-calendar-event' ) .
		'</p></div>';
}
add_action( 'admin_notices', 'saito_ce_admin_notice_missing_woocommerce' );

// Menu « Saito » commun aux plugins Saito (voir includes/saito-hub/hub.php) :
// les calendriers y apparaissent en sous-menu.
require_once SAITO_CE_PATH . 'includes/saito-hub/load.php';
add_filter( 'saito_hub_plugins', 'saito_ce_register_in_saito_hub' );
function saito_ce_register_in_saito_hub( $plugins ) {
	$plugins['calendar-event'] = array(
		'version'      => SAITO_CE_VERSION,
		'settings_url' => admin_url( 'edit.php?post_type=' . SAITO_CE_POST_TYPE ),
		'menu_slugs'   => array( 'edit.php?post_type=' . SAITO_CE_POST_TYPE ),
	);
	return $plugins;
}

require_once SAITO_CE_PATH . 'includes/class-saito-ce-admin.php';
require_once SAITO_CE_PATH . 'includes/class-saito-ce-public.php';

Saito_CE_Admin::init();
Saito_CE_Public::init();

function saito_ce_activate() {
	saito_ce_register_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'saito_ce_activate' );

function saito_ce_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'saito_ce_deactivate' );

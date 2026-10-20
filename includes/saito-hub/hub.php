<?php
/**
 * Saito Hub — menu « Saito » commun aux plugins Saito.
 *
 * - Saito > Tableau de bord : les plugins Saito, installés ou non.
 * - Un sous-menu par plugin, ajouté par le plugin lui-même sous
 *   saito_hub_menu_slug() (sur admin_menu, priorité par défaut).
 * - Saito > Outils : CSS et JavaScript additionnels du site.
 * - Saito > Documentation : dépôts GitHub, documentations, pages WordPress.org.
 *
 * Un plugin se déclare via le filtre saito_hub_plugins :
 *
 *   add_filter( 'saito_hub_plugins', function ( $plugins ) {
 *       $plugins['navi'] = array(
 *           'version'      => '0.8.0',
 *           'settings_url' => admin_url( 'admin.php?page=navi-main' ),
 *           'menu_slugs'   => array( 'navi-main' ), // Ses sous-menus, pour l'ordre du menu.
 *       );
 *       return $plugins;
 *   } );
 *
 * Nom, description et liens viennent du catalogue saito_hub_catalog() : un
 * plugin absent du site y figure quand même (tableau de bord, documentation).
 *
 * @package SaitoHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAITO_HUB_MENU', 'saito' );
define( 'SAITO_HUB_TOOLS_PAGE', 'saito-tools' );
define( 'SAITO_HUB_DOCS_PAGE', 'saito-docs' );
define( 'SAITO_HUB_CSS_OPTION', 'saito_hub_custom_css' );
define( 'SAITO_HUB_JS_OPTION', 'saito_hub_custom_js' );

/**
 * Slug du menu parent « Saito », pour add_submenu_page() et show_in_menu.
 *
 * @return string
 */
function saito_hub_menu_slug() {
	return SAITO_HUB_MENU;
}

/**
 * URL d'un fichier du hub.
 *
 * @param string $path Chemin relatif au dossier du hub.
 * @return string
 */
function saito_hub_url( $path ) {
	return plugins_url( $path, SAITO_HUB_DIR . 'hub.php' );
}

/**
 * Tous les plugins Saito connus, dans l'ordre d'affichage.
 *
 * @return array<string,array>
 */
function saito_hub_catalog() {
	$catalog = array(
		'navi'           => array(
			'name'        => 'Saito Navi',
			'description' => __( 'Bouton flottant : consentement cookies (Google Consent Mode V2), panier flottant, accessibilité et stories produit.', 'saito-calendar-event' ),
			'github'      => 'https://github.com/Lucas-tsl/navi-wordpress',
			'docs'        => 'https://lucas-tsl.github.io/navi-wordpress/',
			'notion'      => 'https://www.notion.so/3ff1a86510698123ae10fa7ebb485a6f',
			'wporg'       => 'https://wordpress.org/plugins/saito-navi/',
		),
		'faq'            => array(
			'name'        => 'Saito FAQ',
			'description' => __( 'Questions/réponses sur les articles, pages, produits et catégories de produits, avec schéma FAQPage.', 'saito-calendar-event' ),
			'github'      => 'https://github.com/Lucas-tsl/navi-faq',
			'docs'        => 'https://lucas-tsl.github.io/navi-faq/',
			'notion'      => 'https://rectangular-tiara-ce3.notion.site/Navi-FAQ-Documentation-3c61e90fa69a81df9caad08c42242bf7',
			'wporg'       => 'https://wordpress.org/plugins/saito-faq/',
		),
		'calendar-event' => array(
			'name'        => 'Saito Calendar Event',
			'description' => __( 'Calendrier de l\'Avent (ou tout calendrier à cases) relié aux produits et codes promo WooCommerce.', 'saito-calendar-event' ),
			'github'      => 'https://github.com/Lucas-tsl/saito-calendar-event',
			'docs'        => '',
			'notion'      => 'https://www.notion.so/3ff1a8651069815eadd6ec0d4d518d1d',
			'wporg'       => 'https://wordpress.org/plugins/saito-calendar-event/',
		),
		'store-locator'  => array(
			'name'        => 'Saito Store Locator',
			'description' => __( 'Carte des points de vente avec recherche, fiches magasin indexables, import CSV et synchronisation Google Sheets.', 'saito-calendar-event' ),
			'github'      => 'https://github.com/Lucas-tsl/saito-store-locator',
			'docs'        => '',
			'notion'      => 'https://www.notion.so/3ff1a86510698181bb3ec3c787c94fb2',
			'wporg'       => '',
		),
	);

	$active = apply_filters( 'saito_hub_plugins', array() );
	foreach ( (array) $active as $id => $info ) {
		$base           = isset( $catalog[ $id ] ) ? $catalog[ $id ] : array( 'name' => $id, 'description' => '', 'github' => '', 'docs' => '', 'notion' => '', 'wporg' => '' );
		$catalog[ $id ] = array_merge( $base, (array) $info, array( 'active' => true ) );
	}
	return $catalog;
}

/* -------------------------------------------------------------------------
 * Menu
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'saito_hub_register_menu', 9 );
/**
 * Menu « Saito » et son tableau de bord, avant les sous-menus des plugins.
 */
function saito_hub_register_menu() {
	add_menu_page( 'Saito', 'Saito', 'manage_options', SAITO_HUB_MENU, 'saito_hub_render_dashboard', saito_hub_url( 'assets/menu-icon.png' ), 58 );
	add_submenu_page( SAITO_HUB_MENU, __( 'Tableau de bord', 'saito-calendar-event' ), __( 'Tableau de bord', 'saito-calendar-event' ), 'manage_options', SAITO_HUB_MENU, 'saito_hub_render_dashboard' );
}

add_action( 'admin_menu', 'saito_hub_register_menu_tail', 99 );
/**
 * Outils et Documentation, après les sous-menus des plugins.
 */
function saito_hub_register_menu_tail() {
	add_submenu_page( SAITO_HUB_MENU, __( 'Outils', 'saito-calendar-event' ), __( 'Outils', 'saito-calendar-event' ), 'manage_options', SAITO_HUB_TOOLS_PAGE, 'saito_hub_render_tools' );
	add_submenu_page( SAITO_HUB_MENU, __( 'Documentation', 'saito-calendar-event' ), __( 'Documentation', 'saito-calendar-event' ), 'manage_options', SAITO_HUB_DOCS_PAGE, 'saito_hub_render_docs' );
}

add_action( 'admin_menu', 'saito_hub_sort_submenu', 100 );
/**
 * Ordre du menu Saito : tableau de bord, plugins dans l'ordre du catalogue,
 * puis Outils et Documentation. Sans cela, les types de contenu (ex.
 * Calendriers) passent avant les pages, ajoutées plus tard par WordPress.
 */
function saito_hub_sort_submenu() {
	global $submenu;
	if ( empty( $submenu[ SAITO_HUB_MENU ] ) ) {
		return;
	}
	$weights = array( SAITO_HUB_MENU => 0 );
	$weight  = 1;
	foreach ( saito_hub_catalog() as $plugin ) {
		foreach ( isset( $plugin['menu_slugs'] ) ? (array) $plugin['menu_slugs'] : array() as $slug ) {
			$weights[ $slug ] = $weight++;
		}
	}
	$weights[ SAITO_HUB_TOOLS_PAGE ] = 1000;
	$weights[ SAITO_HUB_DOCS_PAGE ]  = 1001;

	$items = $submenu[ SAITO_HUB_MENU ];
	$keys  = array_keys( $items );
	usort(
		$keys,
		function ( $a, $b ) use ( $items, $weights ) {
			$wa = isset( $weights[ $items[ $a ][2] ] ) ? $weights[ $items[ $a ][2] ] : 999;
			$wb = isset( $weights[ $items[ $b ][2] ] ) ? $weights[ $items[ $b ][2] ] : 999;
			return $wa === $wb ? $a - $b : $wa - $wb;
		}
	);
	$sorted = array();
	foreach ( $keys as $key ) {
		$sorted[] = $items[ $key ];
	}
	$submenu[ SAITO_HUB_MENU ] = $sorted; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- réordonne seulement le sous-menu Saito.
}

add_action( 'admin_enqueue_scripts', 'saito_hub_admin_assets' );
/**
 * Styles du hub (icône du menu partout, pages du hub uniquement pour le reste).
 *
 * @param string $hook Écran courant.
 */
function saito_hub_admin_assets( $hook ) {
	wp_add_inline_style( 'common', '#toplevel_page_saito .wp-menu-image img { padding: 6px 0 0; width: 20px; height: 20px; }' );

	$pages = array( 'toplevel_page_' . SAITO_HUB_MENU, 'saito_page_' . SAITO_HUB_TOOLS_PAGE, 'saito_page_' . SAITO_HUB_DOCS_PAGE );
	if ( ! in_array( $hook, $pages, true ) ) {
		return;
	}
	wp_enqueue_style( 'saito-hub', saito_hub_url( 'assets/hub.css' ), array(), SAITO_HUB_VERSION );

	if ( 'saito_page_' . SAITO_HUB_TOOLS_PAGE === $hook ) {
		$editors = array( 'saito-hub-css' => 'text/css' );
		if ( current_user_can( 'unfiltered_html' ) ) {
			$editors['saito-hub-js'] = 'text/javascript';
		}
		foreach ( $editors as $id => $type ) {
			$settings = wp_enqueue_code_editor( array( 'type' => $type ) );
			if ( false !== $settings ) {
				wp_add_inline_script( 'code-editor', sprintf( 'jQuery(function(){ wp.codeEditor.initialize(%s, %s); });', wp_json_encode( $id ), wp_json_encode( $settings ) ) );
			}
		}
	}
}

/**
 * En-tête commun des pages du hub.
 *
 * @param string $title Titre de la page.
 */
function saito_hub_page_header( $title ) {
	?>
	<div class="saito-hub-header">
		<img src="<?php echo esc_url( saito_hub_url( 'assets/logo.png' ) ); ?>" alt="" width="40" height="40">
		<h1><?php echo esc_html( $title ); ?></h1>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Tableau de bord
 * ---------------------------------------------------------------------- */

/**
 * Saito > Tableau de bord.
 */
function saito_hub_render_dashboard() {
	?>
	<div class="wrap saito-hub">
		<?php saito_hub_page_header( 'Saito' ); ?>
		<p class="saito-hub-intro"><?php esc_html_e( 'Les plugins Saito installés sur ce site, et ceux qui peuvent l\'être.', 'saito-calendar-event' ); ?></p>
		<div class="saito-hub-cards">
			<?php foreach ( saito_hub_catalog() as $plugin ) : ?>
				<?php $active = ! empty( $plugin['active'] ); ?>
				<div class="saito-hub-card<?php echo $active ? ' is-active' : ''; ?>">
					<h2><?php echo esc_html( $plugin['name'] ); ?></h2>
					<p class="saito-hub-status">
						<?php
						if ( $active ) {
							/* translators: %s: numéro de version. */
							echo esc_html( sprintf( __( 'Actif — version %s', 'saito-calendar-event' ), isset( $plugin['version'] ) ? $plugin['version'] : '?' ) );
						} else {
							esc_html_e( 'Non installé ou inactif', 'saito-calendar-event' );
						}
						?>
					</p>
					<p><?php echo esc_html( $plugin['description'] ); ?></p>
					<p class="saito-hub-actions">
						<?php if ( $active && ! empty( $plugin['settings_url'] ) ) : ?>
							<a class="button button-primary" href="<?php echo esc_url( $plugin['settings_url'] ); ?>"><?php esc_html_e( 'Réglages', 'saito-calendar-event' ); ?></a>
						<?php elseif ( ! $active && ! empty( $plugin['wporg'] ) ) : ?>
							<a class="button" href="<?php echo esc_url( $plugin['wporg'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Voir sur WordPress.org', 'saito-calendar-event' ); ?></a>
						<?php endif; ?>
						<a class="button button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SAITO_HUB_DOCS_PAGE ) ); ?>"><?php esc_html_e( 'Documentation', 'saito-calendar-event' ); ?></a>
					</p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Outils : CSS et JavaScript additionnels
 * ---------------------------------------------------------------------- */

add_action( 'admin_init', 'saito_hub_register_settings' );
/**
 * Réglages de Saito > Outils.
 */
function saito_hub_register_settings() {
	register_setting( 'saito_hub_tools', SAITO_HUB_CSS_OPTION, array( 'type' => 'string', 'sanitize_callback' => 'saito_hub_sanitize_css', 'default' => '' ) );
	register_setting( 'saito_hub_tools', SAITO_HUB_JS_OPTION, array( 'type' => 'string', 'sanitize_callback' => 'saito_hub_sanitize_js', 'default' => '' ) );
}

/**
 * CSS additionnel : texte libre, sans balise de fermeture qui sortirait du <style>.
 *
 * @param mixed $value Valeur soumise.
 * @return string
 */
function saito_hub_sanitize_css( $value ) {
	return trim( str_ireplace( '</style', '', (string) $value ) );
}

/**
 * JavaScript additionnel : réservé aux utilisateurs autorisés à publier du code
 * (unfiltered_html, retiré par défaut aux administrateurs en multisite).
 *
 * @param mixed $value Valeur soumise.
 * @return string
 */
function saito_hub_sanitize_js( $value ) {
	if ( ! current_user_can( 'unfiltered_html' ) ) {
		return (string) get_option( SAITO_HUB_JS_OPTION, '' );
	}
	return trim( str_ireplace( '</script', '', (string) $value ) );
}

/**
 * Saito > Outils.
 */
function saito_hub_render_tools() {
	$can_js = current_user_can( 'unfiltered_html' );
	?>
	<div class="wrap saito-hub">
		<?php saito_hub_page_header( __( 'Outils', 'saito-calendar-event' ) ); ?>
		<?php settings_errors(); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'saito_hub_tools' ); ?>

			<h2><label for="saito-hub-css"><?php esc_html_e( 'CSS additionnel', 'saito-calendar-event' ); ?></label></h2>
			<p class="description"><?php esc_html_e( 'Ajouté sur toutes les pages du site, après les styles du thème et des plugins.', 'saito-calendar-event' ); ?></p>
			<textarea id="saito-hub-css" name="<?php echo esc_attr( SAITO_HUB_CSS_OPTION ); ?>" rows="14" class="large-text code"><?php echo esc_textarea( (string) get_option( SAITO_HUB_CSS_OPTION, '' ) ); ?></textarea>

			<h2><label for="saito-hub-js"><?php esc_html_e( 'JavaScript additionnel', 'saito-calendar-event' ); ?></label></h2>
			<p class="description"><?php esc_html_e( 'Ajouté en bas de toutes les pages du site. Saisissez le code seul, sans balise <script>.', 'saito-calendar-event' ); ?></p>
			<?php if ( ! $can_js ) : ?>
				<p class="notice notice-warning inline"><?php esc_html_e( 'Votre compte n\'est pas autorisé à publier du code JavaScript sur ce site : ce champ est en lecture seule.', 'saito-calendar-event' ); ?></p>
			<?php endif; ?>
			<textarea id="saito-hub-js" name="<?php echo esc_attr( SAITO_HUB_JS_OPTION ); ?>" rows="14" class="large-text code"<?php disabled( ! $can_js ); ?>><?php echo esc_textarea( (string) get_option( SAITO_HUB_JS_OPTION, '' ) ); ?></textarea>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

add_action( 'wp_enqueue_scripts', 'saito_hub_enqueue_custom_code', 100 );
/**
 * Sortie du CSS et du JavaScript additionnels sur le site.
 */
function saito_hub_enqueue_custom_code() {
	$css = (string) get_option( SAITO_HUB_CSS_OPTION, '' );
	if ( '' !== $css ) {
		wp_register_style( 'saito-hub-custom', false, array(), SAITO_HUB_VERSION );
		wp_enqueue_style( 'saito-hub-custom' );
		wp_add_inline_style( 'saito-hub-custom', $css );
	}

	$js = (string) get_option( SAITO_HUB_JS_OPTION, '' );
	if ( '' !== $js ) {
		wp_register_script( 'saito-hub-custom', false, array(), SAITO_HUB_VERSION, true );
		wp_enqueue_script( 'saito-hub-custom' );
		wp_add_inline_script( 'saito-hub-custom', $js );
	}
}

/* -------------------------------------------------------------------------
 * Documentation
 * ---------------------------------------------------------------------- */

/**
 * Lien externe de la page Documentation, ou tiret s'il n'existe pas encore.
 *
 * @param string $url   Adresse.
 * @param string $label Texte du lien.
 */
function saito_hub_doc_link( $url, $label ) {
	if ( '' === $url ) {
		echo '<span class="saito-hub-muted">—</span>';
		return;
	}
	printf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', esc_url( $url ), esc_html( $label ) );
}

/**
 * Saito > Documentation.
 */
function saito_hub_render_docs() {
	?>
	<div class="wrap saito-hub">
		<?php saito_hub_page_header( __( 'Documentation', 'saito-calendar-event' ) ); ?>
		<table class="widefat striped saito-hub-docs">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Plugin', 'saito-calendar-event' ); ?></th>
					<th><?php esc_html_e( 'Code source', 'saito-calendar-event' ); ?></th>
					<th><?php esc_html_e( 'Documentation', 'saito-calendar-event' ); ?></th>
					<th><?php esc_html_e( 'Notion', 'saito-calendar-event' ); ?></th>
					<th><?php esc_html_e( 'WordPress.org', 'saito-calendar-event' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( saito_hub_catalog() as $plugin ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $plugin['name'] ); ?></strong><br><span class="saito-hub-muted"><?php echo esc_html( ! empty( $plugin['active'] ) ? __( 'Actif sur ce site', 'saito-calendar-event' ) : __( 'Non installé', 'saito-calendar-event' ) ); ?></span></td>
						<td><?php saito_hub_doc_link( $plugin['github'], 'GitHub' ); ?></td>
						<td><?php saito_hub_doc_link( $plugin['docs'], __( 'Site de documentation', 'saito-calendar-event' ) ); ?></td>
						<td><?php saito_hub_doc_link( $plugin['notion'], 'Notion' ); ?></td>
						<td><?php saito_hub_doc_link( $plugin['wporg'], __( 'Page du plugin', 'saito-calendar-event' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

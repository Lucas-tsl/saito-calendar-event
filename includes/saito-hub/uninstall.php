<?php
/**
 * Saito Hub — nettoyage à la désinstallation d'un plugin Saito.
 *
 * Appelé par le uninstall.php de chaque plugin (require de ce fichier). Les
 * réglages de Saito > Outils sont partagés : ils ne sont supprimés qu'avec le
 * dernier plugin Saito encore présent sur le site.
 *
 * @package SaitoHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$saito_hub_leaving = dirname( WP_UNINSTALL_PLUGIN );
$saito_hub_others  = array();
foreach ( (array) glob( WP_PLUGIN_DIR . '/*/includes/saito-hub/load.php' ) as $saito_hub_file ) {
	$saito_hub_slug = basename( dirname( dirname( dirname( $saito_hub_file ) ) ) );
	if ( $saito_hub_slug !== $saito_hub_leaving ) {
		$saito_hub_others[] = $saito_hub_slug;
	}
}

if ( empty( $saito_hub_others ) ) {
	delete_option( 'saito_hub_custom_css' );
	delete_option( 'saito_hub_custom_js' );
}

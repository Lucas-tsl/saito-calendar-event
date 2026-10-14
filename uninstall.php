<?php
/**
 * Désinstallation : supprime les calendriers et leurs réglages. Appelé par
 * WordPress uniquement à la suppression du plugin (pas à sa désactivation).
 *
 * @package SaitoCalendarEvent
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$saito_ce_calendars = get_posts(
	array(
		'post_type'      => 'saito_calendar_event',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

// wp_delete_post() supprime aussi toutes les métas du calendrier (_saito_ce_*).
foreach ( $saito_ce_calendars as $saito_ce_calendar_id ) {
	wp_delete_post( $saito_ce_calendar_id, true );
}

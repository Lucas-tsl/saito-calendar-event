<?php
/**
 * Test manuel, sans dépendance (pas de PHPUnit ni de WordPress) : vérifie
 * Saito_CE_Public::get_schedule_state(), qui décide si un calendrier est
 * visible des visiteurs selon sa période d'affichage (onglet
 * Programmation). Les dates de début et de fin sont incluses. Exécuté par
 * la CI via `php tests/test-schedule-state.php`.
 *
 * On stub uniquement wp_timezone(), seule fonction WordPress utilisée par la
 * méthode testée.
 */

define( 'ABSPATH', __DIR__ . '/' );

function wp_timezone() {
	return new DateTimeZone( 'Europe/Paris' );
}

require __DIR__ . '/../includes/class-saito-ce-public.php';

$failures = 0;

function saito_ce_assert( $label, $expected, $actual ) {
	global $failures;
	if ( $expected !== $actual ) {
		$failures++;
		fwrite( STDERR, sprintf( "FAIL: %s (attendu %s, obtenu %s)\n", $label, var_export( $expected, true ), var_export( $actual, true ) ) );
		return;
	}
	echo "OK: {$label}\n";
}

function saito_ce_state( $start, $end ) {
	return Saito_CE_Public::get_schedule_state( array( 'start' => $start, 'end' => $end ) );
}

$tz        = new DateTimeZone( 'Europe/Paris' );
$today     = ( new DateTime( 'now', $tz ) )->format( 'Y-m-d' );
$yesterday = ( new DateTime( 'now', $tz ) )->modify( '-1 day' )->format( 'Y-m-d' );
$tomorrow  = ( new DateTime( 'now', $tz ) )->modify( '+1 day' )->format( 'Y-m-d' );

saito_ce_assert( 'aucune date => visible', 'live', saito_ce_state( '', '' ) );
saito_ce_assert( 'réglage absent (ancien calendrier) => visible', 'live', Saito_CE_Public::get_schedule_state( '' ) );
saito_ce_assert( 'début demain => pas encore visible', 'before', saito_ce_state( $tomorrow, '' ) );
saito_ce_assert( "début aujourd'hui => visible (date de début incluse)", 'live', saito_ce_state( $today, '' ) );
saito_ce_assert( 'début hier => visible', 'live', saito_ce_state( $yesterday, '' ) );
saito_ce_assert( "fin aujourd'hui => encore visible (date de fin incluse)", 'live', saito_ce_state( '', $today ) );
saito_ce_assert( 'fin hier => plus visible', 'after', saito_ce_state( '', $yesterday ) );
saito_ce_assert( 'période hier → demain => visible', 'live', saito_ce_state( $yesterday, $tomorrow ) );
saito_ce_assert( 'date invalide ignorée => visible', 'live', saito_ce_state( 'pas-une-date', '' ) );

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} échec(s).\n" );
	exit( 1 );
}

echo "\nTous les tests sont passés.\n";

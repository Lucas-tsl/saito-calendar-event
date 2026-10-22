<?php
/**
 * Test manuel, sans dépendance (pas de PHPUnit ni de WordPress) : vérifie
 * les modèles de calendrier (Saito_CE_Templates) : dates de la Black Week
 * (du lundi précédant le Black Friday au Cyber Monday) pour plusieurs
 * années, cases et programmation des modèles Black Week et Avent.
 * Exécuté par la CI via `php tests/test-templates.php`.
 */

define( 'ABSPATH', __DIR__ . '/' );

function __( $text ) {
	return $text;
}

function apply_filters( $hook, $value ) {
	return $value;
}

require __DIR__ . '/../includes/class-saito-ce-templates.php';

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

// Black Friday : lendemain du 4e jeudi de novembre.
$expected_black_friday = array(
	2024 => '2024-11-29',
	2025 => '2025-11-28',
	2026 => '2026-11-27',
	2027 => '2027-11-26',
	2028 => '2028-11-24',
);
foreach ( $expected_black_friday as $year => $date ) {
	saito_ce_assert( "Black Friday {$year}", $date, Saito_CE_Templates::black_friday( $year )->format( 'Y-m-d' ) );
	saito_ce_assert( "Black Friday {$year} est un vendredi", '5', Saito_CE_Templates::black_friday( $year )->format( 'N' ) );
}

// Black Week 2026 : lundi 23 → lundi 30 novembre.
$week  = Saito_CE_Templates::build( 'black-week', 2026 );
$dates = array_column( $week['cases'], 'unlock_date' );
saito_ce_assert( 'Black Week 2026 : 8 cases', 8, count( $week['cases'] ) );
saito_ce_assert( 'Black Week 2026 : jours 1 à 8', range( 1, 8 ), array_column( $week['cases'], 'day' ) );
saito_ce_assert( 'Black Week 2026 : commence le lundi 23 novembre', '2026-11-23', $dates[0] );
saito_ce_assert( 'Black Week 2026 : case 5 le vendredi 27 (Black Friday)', '2026-11-27', $dates[4] );
saito_ce_assert( 'Black Week 2026 : finit le lundi 30 (Cyber Monday)', '2026-11-30', $dates[7] );
saito_ce_assert( 'Black Week 2026 : programmation du 23 au 30', array( '2026-11-23', '2026-11-30' ), array( $week['schedule']['start'], $week['schedule']['end'] ) );
saito_ce_assert( 'Black Week : habillage', 'black-week', $week['style'] );
saito_ce_assert( 'Black Week : titre', 'Black Week 2026', $week['title'] );
saito_ce_assert( 'Black Week : case 5 titrée Black Friday', 'Black Friday', $week['cases'][4]['title'] );
saito_ce_assert( 'Black Week : case 8 titrée Cyber Monday', 'Cyber Monday', $week['cases'][7]['title'] );

// Année suivante : dates recalculées.
$week_2027 = Saito_CE_Templates::build( 'black-week', 2027 );
saito_ce_assert( 'Black Week 2027 : du lundi 22 au lundi 29 novembre', array( '2027-11-22', '2027-11-29' ), array( $week_2027['cases'][0]['unlock_date'], $week_2027['cases'][7]['unlock_date'] ) );

// Avent.
$advent = Saito_CE_Templates::build( 'advent', 2026 );
saito_ce_assert( 'Avent : 24 cases', 24, count( $advent['cases'] ) );
saito_ce_assert( 'Avent : du 1er au 24 décembre', array( '2026-12-01', '2026-12-24' ), array( $advent['cases'][0]['unlock_date'], $advent['cases'][23]['unlock_date'] ) );
saito_ce_assert( 'Avent : habillage Noël', 'noel', $advent['style'] );

// Modèle inconnu.
saito_ce_assert( 'modèle inconnu : null', null, Saito_CE_Templates::build( 'inconnu', 2026 ) );

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} échec(s).\n" );
	exit( 1 );
}

echo "\nTous les tests sont passés.\n";

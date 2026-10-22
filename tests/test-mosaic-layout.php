<?php
/**
 * Test manuel, sans dépendance (pas de PHPUnit ni de WordPress) : vérifie
 * la disposition des habillages mosaïque (Saito_CE_Public::MOSAIC_LAYOUT et
 * mosaic_arrangement()) : les 24 portes couvrent exactement la grille 6×6
 * sans chevauchement, le 1 et le 24 sont les grandes portes, l'ordre de
 * rendu suit l'ordre visuel (ligne par ligne), et un calendrier qui n'a pas
 * exactement les jours 1→24 retombe sur le motif répété. Exécuté par la CI
 * via `php tests/test-mosaic-layout.php`.
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

function saito_ce_shapes( $cells ) {
	return array_values(
		array_map(
			function ( $cell ) {
				return $cell['shape'];
			},
			$cells
		)
	);
}

// Couverture de la grille 6×6.
$grid    = array();
$overlap = false;
foreach ( Saito_CE_Public::MOSAIC_LAYOUT as $day => $cell ) {
	list( $row, $col, $rows, $cols ) = $cell;
	for ( $r = $row; $r < $row + $rows; $r++ ) {
		for ( $c = $col; $c < $col + $cols; $c++ ) {
			if ( $r > 6 || $c > 6 || isset( $grid[ $r ][ $c ] ) ) {
				$overlap = true;
			}
			$grid[ $r ][ $c ] = $day;
		}
	}
}
$cells = 0;
foreach ( $grid as $row_cells ) {
	$cells += count( $row_cells );
}
$days = array_keys( Saito_CE_Public::MOSAIC_LAYOUT );
sort( $days );
saito_ce_assert( 'les jours 1 à 24 ont chacun une place', range( 1, 24 ), $days );
saito_ce_assert( 'aucun chevauchement ni débordement de la grille 6×6', false, $overlap );
saito_ce_assert( 'les 36 cellules sont couvertes', 36, $cells );

// Disposition fixe d'un calendrier 1→24 (jours fournis dans le désordre).
$full = Saito_CE_Public::mosaic_arrangement( range( 24, 1 ) );
saito_ce_assert( 'le 1 est une grande porte', 'big', $full[1]['shape'] );
saito_ce_assert( 'le 24 est une grande porte', 'big', $full[24]['shape'] );
saito_ce_assert( 'deux grandes portes en tout', 2, count( array_keys( saito_ce_shapes( $full ), 'big', true ) ) );
saito_ce_assert( 'zone CSS du 24 (centre de la grille)', '3 / 3 / span 2 / span 2', $full[24]['area'] );
$order = array_keys( $full );
saito_ce_assert( 'ordre visuel : le 1 (en haut à gauche) en premier', 1, $order[0] );
saito_ce_assert( 'ordre visuel : le 16 (en bas à droite) en dernier', 16, $order[23] );

// Repli : autre nombre de cases.
$five = Saito_CE_Public::mosaic_arrangement( array( 1, 2, 3, 4, 5 ) );
saito_ce_assert( 'repli 5 cases : grande, normale, normale, haute, grande (la dernière)', array( 'big', '', '', 'tall', 'big' ), saito_ce_shapes( $five ) );
saito_ce_assert( 'repli : pas de place fixe', '', $five[1]['area'] );
saito_ce_assert( 'jours vides ou en double ignorés', array( 1, 2 ), array_keys( Saito_CE_Public::mosaic_arrangement( array( 2, 0, 1, 2 ) ) ) );

// Semaine de 8 cases (WEEK_LAYOUT) : grille 4×4 couverte sans chevauchement.
$grid    = array();
$overlap = false;
foreach ( Saito_CE_Public::WEEK_LAYOUT as $day => $cell ) {
	list( $row, $col, $rows, $cols ) = $cell;
	for ( $r = $row; $r < $row + $rows; $r++ ) {
		for ( $c = $col; $c < $col + $cols; $c++ ) {
			if ( $r > 4 || $c > 4 || isset( $grid[ $r ][ $c ] ) ) {
				$overlap = true;
			}
			$grid[ $r ][ $c ] = $day;
		}
	}
}
$cells = 0;
foreach ( $grid as $row_cells ) {
	$cells += count( $row_cells );
}
saito_ce_assert( 'semaine : aucun chevauchement ni débordement de la grille 4×4', false, $overlap );
saito_ce_assert( 'semaine : les 16 cellules sont couvertes', 16, $cells );
saito_ce_assert( 'semaine : 4 colonnes', 4, Saito_CE_Public::layout_columns( Saito_CE_Public::WEEK_LAYOUT ) );
saito_ce_assert( '24 cases : 6 colonnes', 6, Saito_CE_Public::layout_columns( Saito_CE_Public::MOSAIC_LAYOUT ) );

$week = Saito_CE_Public::mosaic_arrangement( range( 8, 1 ) );
saito_ce_assert( 'semaine : le 5 (Black Friday) est une grande porte', 'big', $week[5]['shape'] );
saito_ce_assert( 'semaine : le 8 (Cyber Monday) est une grande porte', 'big', $week[8]['shape'] );
saito_ce_assert( 'semaine : place fixe du 5', '2 / 1 / span 2 / span 2', $week[5]['area'] );
saito_ce_assert( 'semaine : ordre visuel', array( 1, 2, 3, 4, 5, 8, 6, 7 ), array_keys( $week ) );

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} échec(s).\n" );
	exit( 1 );
}

echo "\nTous les tests sont passés.\n";

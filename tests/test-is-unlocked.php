<?php
/**
 * Test manuel, sans dépendance (pas de PHPUnit ni de WordPress) : vérifie
 * la logique de déblocage par date de Saito_CE_Public::is_unlocked(), qui a
 * déjà été source d'un bug corrigé une fois (comparaison de chaînes au lieu
 * de dates). Exécuté par la CI via `php tests/test-is-unlocked.php`.
 *
 * On stub uniquement wp_timezone(), seule fonction WordPress utilisée par
 * la méthode testée ; is_unlocked() étant privée, on l'invoque par
 * réflexion plutôt que de changer sa visibilité pour les besoins du test.
 */

define( 'ABSPATH', __DIR__ . '/' );

function wp_timezone() {
	return new DateTimeZone( 'Europe/Paris' );
}

require __DIR__ . '/../includes/class-saito-ce-public.php';

function saito_ce_test_invoke_is_unlocked( $unlock_date ) {
	$method = new ReflectionMethod( 'Saito_CE_Public', 'is_unlocked' );
	$method->setAccessible( true );
	return $method->invoke( null, array( 'unlock_date' => $unlock_date ) );
}

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

$tz        = new DateTimeZone( 'Europe/Paris' );
$today     = new DateTime( 'now', $tz );
$yesterday = ( clone $today )->modify( '-1 day' );
$tomorrow  = ( clone $today )->modify( '+1 day' );

saito_ce_assert( 'date vide => jamais déblocable', false, saito_ce_test_invoke_is_unlocked( '' ) );
saito_ce_assert( "date d'hier (Y-m-d) => débloquée", true, saito_ce_test_invoke_is_unlocked( $yesterday->format( 'Y-m-d' ) ) );
saito_ce_assert( "date d'aujourd'hui (Y-m-d) => débloquée", true, saito_ce_test_invoke_is_unlocked( $today->format( 'Y-m-d' ) ) );
saito_ce_assert( 'date de demain (Y-m-d) => verrouillée', false, saito_ce_test_invoke_is_unlocked( $tomorrow->format( 'Y-m-d' ) ) );
saito_ce_assert(
	// createFromFormat('Y-m-d', ...) échoue sur ce format ("trailing data"),
	// ce qui doit déclencher le repli strtotime() plutôt que de verrouiller
	// la case à tort.
	'format non strictement Y-m-d mais valide (ISO 8601 avec heure) => débloquée',
	true,
	saito_ce_test_invoke_is_unlocked( $yesterday->format( DateTime::ATOM ) )
);
saito_ce_assert( 'date invalide => verrouillée (repli sécurisé)', false, saito_ce_test_invoke_is_unlocked( 'pas-une-date' ) );

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} échec(s).\n" );
	exit( 1 );
}

echo "\nTous les tests sont passés.\n";

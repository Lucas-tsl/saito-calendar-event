<?php
/**
 * Test manuel, sans dépendance (pas de PHPUnit ni de WordPress) : vérifie
 * la logique de Saito_CE_Public::is_case_expired(), en particulier qu'une
 * case du jour même ("aujourd'hui") n'est pas considérée expirée — seul le
 * jour d'après doit l'être (une case n'étant ouvrable que le jour exact de
 * son déblocage, ni avant ni après). Exécuté par la CI via
 * `php tests/test-case-expired.php`.
 *
 * On stub uniquement wp_timezone(), seule fonction WordPress utilisée par la
 * méthode testée ; is_case_expired() étant privée, on l'invoque par
 * réflexion plutôt que de changer sa visibilité pour les besoins du test.
 */

define( 'ABSPATH', __DIR__ . '/' );

function wp_timezone() {
	return new DateTimeZone( 'Europe/Paris' );
}

require __DIR__ . '/../includes/class-saito-ce-public.php';

function saito_ce_test_invoke_is_case_expired( $unlock_date ) {
	$method = new ReflectionMethod( 'Saito_CE_Public', 'is_case_expired' );
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

saito_ce_assert( 'date vide => jamais expirée (jamais déblocable non plus)', false, saito_ce_test_invoke_is_case_expired( '' ) );
saito_ce_assert( "date d'hier => case expirée (plus ouvrable du tout)", true, saito_ce_test_invoke_is_case_expired( $yesterday->format( 'Y-m-d' ) ) );
saito_ce_assert( "date d'aujourd'hui => case encore ouvrable (pas expirée)", false, saito_ce_test_invoke_is_case_expired( $today->format( 'Y-m-d' ) ) );
saito_ce_assert( 'date de demain (case encore verrouillée) => pas expirée', false, saito_ce_test_invoke_is_case_expired( $tomorrow->format( 'Y-m-d' ) ) );
saito_ce_assert( 'date invalide => pas expirée (repli sécurisé, cohérent avec is_unlocked)', false, saito_ce_test_invoke_is_case_expired( 'pas-une-date' ) );

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} échec(s).\n" );
	exit( 1 );
}

echo "\nTous les tests sont passés.\n";

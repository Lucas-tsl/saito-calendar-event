<?php
/**
 * Test manuel, sans dépendance : vérifie Saito_CE_Public::get_locked_message(),
 * en particulier qu'un message personnalisé par l'admin contenant un "%"
 * isolé (ex. "-10% de réduction") ne fait pas planter le rendu du calendrier.
 * Une première version utilisait sprintf(), qui lève une ValueError sur ce
 * genre de texte libre en PHP 8+ ; le remplacement par str_replace() corrige
 * ce risque, ce test l'empêche de revenir silencieusement.
 *
 * Exécuté par la CI via `php tests/test-locked-message.php`.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SAITO_CE_LOCKED_MESSAGE_META', '_saito_ce_locked_message' );

$GLOBALS['saito_ce_test_locked_message_meta'] = '';

function get_post_meta( $post_id, $key, $single = false ) {
	return $GLOBALS['saito_ce_test_locked_message_meta'];
}

function get_option( $name ) {
	return 'Y-m-d';
}

function date_i18n( $format, $timestamp ) {
	return gmdate( $format, $timestamp );
}

function wp_timezone() {
	return new DateTimeZone( 'Europe/Paris' );
}

function wp_date( $format, $timestamp, $timezone ) {
	$date = new DateTime( '@' . $timestamp );
	$date->setTimezone( $timezone );
	return $date->format( $format );
}

function __( $text, $domain = 'default' ) {
	return $text;
}

require __DIR__ . '/../includes/class-saito-ce-public.php';

function saito_ce_test_invoke_get_locked_message( $message_meta, $unlock_date ) {
	$GLOBALS['saito_ce_test_locked_message_meta'] = $message_meta;
	$method = new ReflectionMethod( 'Saito_CE_Public', 'get_locked_message' );
	$method->setAccessible( true );
	return $method->invoke( null, 1, $unlock_date );
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

/**
 * @return mixed|null Le résultat de $fn(), ou null si elle a levé une exception.
 */
function saito_ce_assert_no_throw( $label, callable $fn ) {
	global $failures;
	try {
		$result = $fn();
		echo "OK: {$label}\n";
		return $result;
	} catch ( Throwable $e ) {
		$failures++;
		fwrite( STDERR, sprintf( "FAIL: %s (exception : %s)\n", $label, $e->getMessage() ) );
		return null;
	}
}

$default_message = saito_ce_test_invoke_get_locked_message( '', '2026-12-24' );
saito_ce_assert( 'message vide => repli sur le message par défaut', true, str_contains( $default_message, 'Cette case n’est pas encore ouvrable' ) );
saito_ce_assert( 'message par défaut => %s remplacé par la date', true, str_contains( $default_message, '2026-12-24' ) );

$no_placeholder = saito_ce_test_invoke_get_locked_message( 'Reviens bientôt !', '2026-12-24' );

$with_percent = saito_ce_assert_no_throw(
	'message custom avec un "%" isolé (ex. "-10%") ne plante pas',
	function () {
		return saito_ce_test_invoke_get_locked_message( 'Reviens vite, -10% de réduction t’attend le %s !', '2026-12-24' );
	}
);
if ( null !== $with_percent ) {
	saito_ce_assert( 'le "%s" a bien été remplacé par la date', true, str_contains( $with_percent, '2026-12-24' ) );
	saito_ce_assert( 'le littéral "%s" ne subsiste pas', false, str_contains( $with_percent, '%s' ) );
	saito_ce_assert( 'le "%" isolé du texte custom est conservé tel quel', true, str_contains( $with_percent, '-10%' ) );
}

saito_ce_assert( 'message custom sans %s => la date est ajoutée à la fin', 'Reviens bientôt ! 2026-12-24', $no_placeholder );

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} échec(s).\n" );
	exit( 1 );
}

echo "\nTous les tests sont passés.\n";

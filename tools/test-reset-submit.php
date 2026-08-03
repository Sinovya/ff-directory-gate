<?php
/**
 * End-to-end test of the Bricks password-reset submit.
 *
 * Rendering the form is not proof it works - the action has to receive the
 * hidden key/login and the correctly-named password field. This mints a real
 * reset key, POSTs the form the way the browser does, then verifies the new
 * password actually authenticates and the old one no longer does.
 *
 * Run: wp eval-file test-reset-submit.php <user_login>
 */

$login = isset( $args[0] ) ? $args[0] : 'dirtest';

$user = get_user_by( 'login', $login );

if ( ! $user ) {
	WP_CLI::error( "no such user: $login" );
}

$PAGE     = 28256;
$FORM     = '41a558';
$NEW_ID   = 'ac9bf7';
$CONF_ID  = 'csokkl';
$new_pass = 'FfDirTest!' . wp_generate_password( 8, false );

// A known-good starting password so we can prove the old one stops working.
$old_pass = 'OldPass!' . wp_generate_password( 8, false );
wp_set_password( $old_pass, $user->ID );

WP_CLI::log( 'user      : ' . $user->user_login . ' (#' . $user->ID . ')' );
WP_CLI::log( 'old pass  : set for the test' );
WP_CLI::log( 'new pass  : ' . $new_pass );

$key = get_password_reset_key( $user );

if ( is_wp_error( $key ) ) {
	WP_CLI::error( 'could not mint a reset key: ' . $key->get_error_message() );
}

WP_CLI::log( 'reset key : minted' );

$body = array(
	'action'                    => 'bricks_form_submit',
	'formId'                    => $FORM,
	'postId'                    => $PAGE,
	'nonce'                     => wp_create_nonce( 'bricks-nonce-form' ),
	'form-field-key'            => $key,
	'form-field-login'          => $user->user_login,
	'form-field-' . $NEW_ID     => $new_pass,
	'form-field-' . $CONF_ID    => $new_pass,
	'referrer'                  => get_permalink( $PAGE ),
);

$response = wp_remote_post( admin_url( 'admin-ajax.php' ), array(
	'timeout' => 30,
	'body'    => $body,
) );

if ( is_wp_error( $response ) ) {
	WP_CLI::error( 'request failed: ' . $response->get_error_message() );
}

$code = wp_remote_retrieve_response_code( $response );
$raw  = wp_remote_retrieve_body( $response );

WP_CLI::log( '' );
WP_CLI::log( 'HTTP ' . $code );
WP_CLI::log( 'response: ' . mb_substr( $raw, 0, 300 ) );

$json = json_decode( $raw, true );

WP_CLI::log( '' );

if ( is_array( $json ) && ! empty( $json['success'] ) ) {
	WP_CLI::log( '  form reported SUCCESS' );
} else {
	WP_CLI::warning( '  form did NOT report success' );
}

/*
 * The password was changed by a SEPARATE http request. This CLI process still
 * holds the user object it cached before that, so wp_authenticate() would
 * compare against a stale hash and report a false failure. Drop the cache
 * first - otherwise the test says "reset did not take effect" when it did.
 */
clean_user_cache( $user->ID );
wp_cache_delete( $user->ID, 'users' );
wp_cache_delete( $user->user_login, 'userlogins' );

// The real proof: does the password actually work now?
$auth_new = wp_authenticate( $user->user_login, $new_pass );
$auth_old = wp_authenticate( $user->user_login, $old_pass );

WP_CLI::log( '  new password authenticates: ' . ( is_wp_error( $auth_new ) ? 'NO - ' . $auth_new->get_error_code() : 'YES' ) );
WP_CLI::log( '  old password rejected:      ' . ( is_wp_error( $auth_old ) ? 'YES' : 'NO - STILL ACCEPTED' ) );

if ( ! is_wp_error( $auth_new ) && is_wp_error( $auth_old ) ) {
	WP_CLI::success( 'password reset works end to end.' );
} else {
	WP_CLI::error( 'password reset did NOT take effect.' );
}

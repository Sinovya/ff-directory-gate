<?php
/**
 * Trace wp_new_user_notification() for a given user: is the filter reached,
 * what does Bricksforge return, and is wp_mail() actually invoked?
 *
 * Run: wp eval-file probe-new-user-email.php <user_login>
 */

$login = isset( $args[0] ) ? $args[0] : '';

if ( ! $login ) {
	WP_CLI::error( 'usage: wp eval-file probe-new-user-email.php <user_login>' );
}

$user = get_user_by( 'login', $login );

if ( ! $user ) {
	WP_CLI::error( "no such user: $login" );
}

$GLOBALS['probe'] = array( 'filter' => false, 'mail' => false );

// What does the filter chain hand back?
add_filter( 'wp_new_user_notification_email', function ( $email, $u, $blogname ) {

	$GLOBALS['probe']['filter'] = true;

	WP_CLI::log( '  filter reached. returned array:' );
	WP_CLI::log( '    to:      ' . ( isset( $email['to'] ) ? ( is_array( $email['to'] ) ? implode( ',', $email['to'] ) : $email['to'] ) : '(MISSING)' ) );
	WP_CLI::log( '    subject: ' . ( isset( $email['subject'] ) ? $email['subject'] : '(MISSING)' ) );
	WP_CLI::log( '    message: ' . ( isset( $email['message'] ) ? strlen( $email['message'] ) . ' bytes' : '(MISSING)' ) );
	WP_CLI::log( '    headers: ' . ( isset( $email['headers'] ) ? ( is_array( $email['headers'] ) ? implode( ' | ', $email['headers'] ) : $email['headers'] ) : '(none)' ) );

	if ( isset( $email['message'] ) && preg_match( '#https?://[^\s"\'<>]*action=rp[^\s"\'<>]*#i', $email['message'], $m ) ) {
		WP_CLI::log( '    set-password link found:' );
		WP_CLI::log( '      ' . html_entity_decode( $m[0] ) );
	} else {
		WP_CLI::warning( '    NO action=rp link inside the message' );
	}

	return $email;
}, 99999, 3 );

// Is wp_mail actually called?
add_filter( 'wp_mail', function ( $atts ) {

	$GLOBALS['probe']['mail'] = true;

	$to = isset( $atts['to'] ) ? $atts['to'] : '';
	WP_CLI::log( '  wp_mail() invoked -> to: ' . ( is_array( $to ) ? implode( ',', $to ) : $to ) );

	return $atts;
}, 99999 );

WP_CLI::log( 'firing wp_new_user_notification(' . $user->ID . ", null, 'user') ..." );

wp_new_user_notification( $user->ID, null, 'user' );

WP_CLI::log( '' );
WP_CLI::log( '  filter reached: ' . ( $GLOBALS['probe']['filter'] ? 'YES' : 'NO' ) );
WP_CLI::log( '  wp_mail called: ' . ( $GLOBALS['probe']['mail'] ? 'YES' : 'NO' ) );

if ( ! $GLOBALS['probe']['filter'] ) {
	WP_CLI::warning( 'core returned before building the user email - check get_password_reset_key() / $notify handling' );
}

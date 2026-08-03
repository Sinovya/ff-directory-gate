<?php
/**
 * Inspect the most recent FluentSMTP log row: was the branded Bricksforge
 * template applied, and does the set-password link point at the branded
 * reset page with a real key?
 *
 * Run: wp eval-file inspect-last-email.php
 */

global $wpdb;

$row = $wpdb->get_row( 'SELECT id, `to`, `from`, subject, status, body, created_at FROM dlrh_fsmpt_email_logs ORDER BY id DESC LIMIT 1', ARRAY_A );

if ( ! $row ) {
	WP_CLI::error( 'no rows in the mail log' );
}

$to = $row['to'];

// FluentSMTP stores the recipient serialised.
$maybe = @unserialize( $to );

if ( is_array( $maybe ) ) {
	$flat = array();
	array_walk_recursive( $maybe, function ( $v, $k ) use ( &$flat ) {
		if ( 'email' === $k ) {
			$flat[] = $v;
		}
	} );
	$to = $flat ? implode( ', ', $flat ) : $to;
}

WP_CLI::log( '  id:        ' . $row['id'] );
WP_CLI::log( '  to:        ' . $to );
WP_CLI::log( '  subject:   ' . $row['subject'] );
WP_CLI::log( '  status:    ' . $row['status'] );
WP_CLI::log( '  sent at:   ' . $row['created_at'] );

$body = (string) $row['body'];

WP_CLI::log( '' );
WP_CLI::log( '  body length:            ' . strlen( $body ) );
WP_CLI::log( '  contains <img> (logo):  ' . ( false !== stripos( $body, '<img' ) ? 'yes' : 'no' ) );
WP_CLI::log( '  contains "Click Here":  ' . ( false !== stripos( $body, 'Click Here' ) ? 'yes' : 'no' ) );
WP_CLI::log( '  contains your copy:     ' . ( false !== stripos( $body, 'account is active' ) ? 'yes' : 'no' ) );

// Any unreplaced Bricksforge variables left behind?
if ( preg_match_all( '/\{\{\s*[a-z_0-9]+\s*\}\}/i', $body, $vars ) ) {
	WP_CLI::warning( '  UNREPLACED variables still in the body: ' . implode( ', ', array_unique( $vars[0] ) ) );
} else {
	WP_CLI::log( '  unreplaced {{vars}}:     none' );
}

// The set-password link.
if ( preg_match( '#href=["\']([^"\']*action=rp[^"\']*)["\']#i', $body, $m ) ) {

	$url = html_entity_decode( $m[1] );

	WP_CLI::log( '' );
	WP_CLI::log( '  SET-PASSWORD LINK:' );
	WP_CLI::log( '    ' . $url );

	$parts = wp_parse_url( $url );
	parse_str( isset( $parts['query'] ) ? $parts['query'] : '', $q );

	WP_CLI::log( '    path:  ' . ( isset( $parts['path'] ) ? $parts['path'] : '(none)' ) );
	WP_CLI::log( '    login: ' . ( isset( $q['login'] ) ? $q['login'] : '(missing)' ) );
	WP_CLI::log( '    key:   ' . ( isset( $q['key'] ) && '' !== $q['key'] ? 'present (' . strlen( $q['key'] ) . ' chars)' : 'MISSING' ) );

	$branded = ( false !== strpos( (string) ( isset( $parts['path'] ) ? $parts['path'] : '' ), 'car-dir-password-reset' ) );
	WP_CLI::log( '    branded reset page: ' . ( $branded ? 'YES' : 'NO - points at wp-login.php' ) );

	// Is the key actually valid right now?
	if ( isset( $q['key'], $q['login'] ) ) {
		$check = check_password_reset_key( $q['key'], $q['login'] );
		WP_CLI::log( '    key validates now:  ' . ( is_wp_error( $check ) ? 'NO - ' . $check->get_error_code() : 'YES (user ' . $check->ID . ')' ) );
	}
} else {
	WP_CLI::warning( '  no action=rp link found in the body' );
}

<?php
/**
 * Dump the From header and the greeting markup of recent emails, to diagnose
 * (a) a truncated sender name and (b) stray styling around a merge variable.
 *
 * Run: wp eval-file inspect-email-details.php [how_many]
 */

global $wpdb;

$limit = isset( $args[0] ) ? (int) $args[0] : 3;

$rows = $wpdb->get_results(
	$wpdb->prepare( 'SELECT id, `from`, `to`, subject, headers, body, created_at FROM dlrh_fsmpt_email_logs ORDER BY id DESC LIMIT %d', $limit ),
	ARRAY_A
);

foreach ( $rows as $r ) {

	WP_CLI::log( '--------------------------------------------------' );
	WP_CLI::log( 'id ' . $r['id'] . '   ' . $r['created_at'] );
	WP_CLI::log( '  subject: ' . $r['subject'] );

	// from / headers may be serialised
	foreach ( array( 'from', 'headers' ) as $f ) {
		$v = $r[ $f ];
		$u = @unserialize( $v );
		WP_CLI::log( '  ' . str_pad( $f . ':', 10 ) . ( false !== $u || 'b:0;' === $v ? wp_json_encode( $u ) : $v ) );
	}

	$body = (string) $r['body'];

	// The greeting line - show raw markup so any styling is visible.
	if ( preg_match( '#(<p[^>]*>.{0,400}?Hi[^<]{0,40}.{0,400}?</p>)#is', $body, $m ) ) {
		WP_CLI::log( '  greeting markup:' );
		WP_CLI::log( '    ' . preg_replace( '/\s+/', ' ', $m[1] ) );
	} elseif ( preg_match( '#.{0,220}\bHi\b.{0,260}#is', $body, $m ) ) {
		WP_CLI::log( '  greeting context:' );
		WP_CLI::log( '    ' . preg_replace( '/\s+/', ' ', $m[0] ) );
	}

	// Any inline styles that look like an editor "variable chip".
	if ( preg_match_all( '#<span[^>]*style="[^"]*(background|color)[^"]*"[^>]*>[^<]{0,40}</span>#i', $body, $sp ) ) {
		WP_CLI::log( '  styled spans found: ' . count( $sp[0] ) );
		foreach ( array_slice( $sp[0], 0, 3 ) as $s ) {
			WP_CLI::log( '    ' . preg_replace( '/\s+/', ' ', $s ) );
		}
	} else {
		WP_CLI::log( '  styled spans found: none' );
	}
}

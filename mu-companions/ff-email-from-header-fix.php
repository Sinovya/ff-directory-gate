<?php
/**
 * Plugin Name: FF Email From-Header Fix
 * Description: Repairs malformed "From: Name<email>" headers (missing the space before the angle bracket) that would otherwise lose the last character of the sender name.
 * Version: 1.0.0
 * Author: FreightForge
 *
 * WHY
 * ---
 * bricksforge/includes/email-designer/EmailDesigner.php::get_from() builds:
 *
 *     return 'From: ' . $fromName . '<' . $fromEmail . '>';
 *
 * with NO space before the '<'. That yields:
 *
 *     From: FreightForge<onboarding@freightforge.com>
 *
 * RFC 5322 expects `Display Name <addr>`. Parsers that assume the space is
 * there strip one character in its place, so the sender name arrives as
 * "FreightForg" - the brand name with its last letter missing, on every
 * customer-facing email.
 *
 * This runs on wp_mail at priority 10000, i.e. AFTER Bricksforge's own
 * EmailDesigner::finish (9999), and inserts the missing space. Vendor files are
 * untouched, so it survives plugin updates.
 *
 * Idempotent: a header that is already well-formed is left exactly as it is.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'wp_mail', 'ff_email_fix_from_header', 10000 );

function ff_email_fix_from_header( $atts ) {

	if ( ! is_array( $atts ) || empty( $atts['headers'] ) ) {
		return $atts;
	}

	$headers = $atts['headers'];

	if ( is_array( $headers ) ) {
		foreach ( $headers as $i => $line ) {
			if ( is_string( $line ) ) {
				$headers[ $i ] = ff_email_repair_from_line( $line );
			}
		}
	} elseif ( is_string( $headers ) ) {
		// Multi-line string form.
		$lines = preg_split( '/\r\n|\r|\n/', $headers );

		foreach ( $lines as $i => $line ) {
			$lines[ $i ] = ff_email_repair_from_line( $line );
		}

		$headers = implode( "\r\n", $lines );
	}

	$atts['headers'] = $headers;

	return $atts;
}

/**
 * Insert the missing space in a From: header. Anything else is returned as-is.
 *
 * Handles quoted display names too: "Acme Co"<a@b.com> -> "Acme Co" <a@b.com>
 */
function ff_email_repair_from_line( $line ) {

	if ( ! is_string( $line ) || 0 !== stripos( ltrim( $line ), 'from:' ) ) {
		return $line;
	}

	// (From:)(name, ending in a non-space)(<addr>)
	return preg_replace(
		'/^(\s*from:\s*)(.*[^\s<])(<[^>]+>\s*)$/i',
		'$1$2 $3',
		$line
	);
}

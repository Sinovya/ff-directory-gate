<?php
/**
 * Render the carrier archive loop as an ANONYMOUS NON-MEMBER would see it,
 * with the gate forced to enforce via the ff_dir_gate_mode filter seam - so a
 * live site can be QA'd without ever enforcing on real visitors.
 *
 * Run: wp eval-file test-teaser-render.php <template_id>
 */

$tpl = isset( $args[0] ) ? (int) $args[0] : 65;

WP_CLI::log( 'gate version: ' . ( defined( 'FF_DIR_GATE_VERSION' ) ? FF_DIR_GATE_VERSION : 'NOT LOADED' ) );
WP_CLI::log( 'stored mode : ' . get_option( 'ff_dir_gate_mode', 'off' ) . '   (unchanged by this test)' );

// Anonymous, and force enforcement for this process only.
wp_set_current_user( 0 );
add_filter( 'ff_dir_gate_mode', function () {
	return 'enforce';
} );

WP_CLI::log( 'forced mode : ' . ff_dir_gate_mode() . '  teaser_active=' . ( ff_dir_teaser_active() ? 'YES' : 'no' ) );

$carriers = get_posts( array(
	'post_type'      => 'carriers',
	'posts_per_page' => 8,
	'post_status'    => 'publish',
	'fields'         => 'ids',
) );

if ( ! $carriers ) {
	WP_CLI::error( 'no carriers found' );
}

$elements = get_post_meta( $tpl, '_bricks_page_content_2', true );

if ( ! is_array( $elements ) ) {
	WP_CLI::error( "template $tpl has no bricks content" );
}

// Render the loop card once per carrier by faking the loop post context.
$html = '';

foreach ( $carriers as $cid ) {
	$GLOBALS['post'] = get_post( $cid );
	setup_postdata( $GLOBALS['post'] );
	$html .= \Bricks\Frontend::render_data( $elements );
}

wp_reset_postdata();

WP_CLI::log( 'rendered bytes: ' . strlen( $html ) );
WP_CLI::log( '' );

// ---- leak checks -------------------------------------------------------
$names = array();
foreach ( $carriers as $cid ) {
	$t = get_the_title( $cid );
	if ( $t && false !== strpos( $html, $t ) ) {
		$names[] = $t;
	}
}

$checks = array(
	'carrier names in HTML' => count( $names ),
	'USDOT numbers'         => preg_match_all( '/USDOT\s*<?[^>]*>?\s*\d{4,}/i', $html ),
	'MC numbers'            => preg_match_all( '/MC\s*<?[^>]*>?\s*\d{4,}/i', $html ),
	'email addresses'       => preg_match_all( '/[A-Za-z0-9._%-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/i', $html ),
	'phone numbers'         => preg_match_all( '/\(\d{3}\)\s*\d{3}-\d{4}/', $html ),
	'name-derived slugs'    => preg_match_all( '#href="[^"]*/carriers/[a-z0-9-]+/?"#i', $html ),
	'LOGO <img> tags'       => preg_match_all( '/<img[^>]+/i', $html ),
	'wp-content uploads'    => preg_match_all( '#/wp-content/uploads/#', $html ),
);

WP_CLI::log( 'LEAK CHECKS (all should be 0):' );
foreach ( $checks as $label => $n ) {
	WP_CLI::log( sprintf( '  %-24s %s%s', $label, $n, ( $n > 0 ? '   <-- LEAK' : '' ) ) );
}

if ( $names ) {
	WP_CLI::log( '  leaked names: ' . implode( ' | ', array_slice( $names, 0, 5 ) ) );
}

WP_CLI::log( '' );
WP_CLI::log( 'PUBLIC FIELDS (should be present):' );
foreach ( array( 'Zone', 'Equipment', 'Power Units', 'Carrier Operation' ) as $p ) {
	WP_CLI::log( sprintf( '  %-20s %s', $p, ( false !== stripos( $html, $p ) ? 'present' : 'missing' ) ) );
}

if ( preg_match_all( '/Power Units:.{0,60}/s', $html, $m ) ) {
	foreach ( array_slice( $m[0], 0, 3 ) as $s ) {
		WP_CLI::log( '    ' . trim( preg_replace( '/\s+/', ' ', strip_tags( $s ) ) ) );
	}
}

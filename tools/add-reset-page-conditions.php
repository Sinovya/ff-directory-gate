<?php
/**
 * Gate the password-reset page (28256) so the form and the "expired" message
 * are mutually exclusive.
 *
 * Right now NEITHER has a condition, so a visitor with a perfectly valid link
 * sees the reset form AND "This link is invalid or has expired" at the same
 * time. Same shape as dir-sandbox page 348 (no conditions at all) rather than
 * the classic rp_key bug - see [[reference-bricks-password-reset-conditions]].
 *
 * The reset link carries ?action=rp&key=...&login=... and the parameter is
 * **key** (NOT rp_key - that is the recurring clone bug).
 * ff-reset-to-login-bricks-safe rewrites the URL to ?key= (empty) when
 * check_password_reset_key() fails, so "key is empty" is exactly the failure
 * state.
 *
 *   form   #41a558  -> show when {url_parameter:key} is NOT empty
 *   text   #zboqyd  -> show when {url_parameter:key} IS empty   (expired msg)
 *   link   #7f8f67  -> show when {url_parameter:key} IS empty   (Lost Password)
 *
 * Run: wp eval-file add-reset-page-conditions.php [dry]
 */

global $wpdb;

$PAGE = 28256;
$KEY  = '_bricks_page_content_2';
$DRY  = in_array( 'dry', (array) ( $args ?? array() ), true );

// element id => array( compare, condition id )
$RULES = array(
	'41a558' => array( 'empty_not', 'ffrp01' ), // the password form
	'zboqyd' => array( 'empty',     'ffrp02' ), // "invalid or has expired"
	'7f8f67' => array( 'empty',     'ffrp03' ), // "Lost Password" link
);

$content = get_post_meta( $PAGE, $KEY, true );

if ( ! is_array( $content ) || empty( $content ) ) {
	WP_CLI::error( "page $PAGE has no $KEY" );
}

$bak = $KEY . '_bak_resetconditions';

if ( ! get_post_meta( $PAGE, $bak, true ) ) {
	update_post_meta( $PAGE, $bak, $content );
	WP_CLI::log( "backup -> postmeta $bak" );
} else {
	WP_CLI::log( "backup already exists at $bak" );
}

$ids = array();
foreach ( $content as $el ) {
	$ids[] = $el['id'];
}

$missing = array_diff( array_keys( $RULES ), $ids );

if ( $missing ) {
	WP_CLI::error( 'elements not found: ' . implode( ', ', $missing ) . ' - page structure changed, aborting.' );
}

$applied = array();

foreach ( $content as $i => $el ) {

	if ( ! isset( $RULES[ $el['id'] ] ) ) {
		continue;
	}

	list( $compare, $cid ) = $RULES[ $el['id'] ];

	$content[ $i ]['settings']['_conditions'] = array(
		array(
			array(
				'id'           => $cid,
				'key'          => 'dynamic_data',
				'dynamic_data' => '{url_parameter:key}',
				'compare'      => $compare,
			),
		),
	);

	$applied[] = sprintf( '%s (%s) -> show when key is %s', $el['id'], $el['name'], 'empty_not' === $compare ? 'PRESENT' : 'EMPTY' );
}

foreach ( $applied as $a ) {
	WP_CLI::log( '  ' . $a );
}

if ( $DRY ) {
	WP_CLI::success( 'DRY RUN - nothing written.' );
	return;
}

// update_post_meta is blocked on this key by Bricks' meta guard - use $wpdb.
$ok = $wpdb->update(
	$wpdb->postmeta,
	array( 'meta_value' => maybe_serialize( $content ) ),
	array( 'post_id' => $PAGE, 'meta_key' => $KEY )
);

if ( false === $ok ) {
	WP_CLI::error( 'DB write failed: ' . $wpdb->last_error );
}

clean_post_cache( $PAGE );
wp_cache_delete( $PAGE, 'post_meta' );

$check    = get_post_meta( $PAGE, $KEY, true );
$verified = 0;

foreach ( (array) $check as $el ) {
	if ( isset( $RULES[ $el['id'] ] ) && ! empty( $el['settings']['_conditions'] ) ) {
		$verified++;
	}
}

WP_CLI::success( "written. verified $verified/" . count( $RULES ) . ' on re-read.' );

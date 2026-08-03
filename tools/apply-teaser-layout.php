<?php
/**
 * Phase 2b - teaser loop layout on the carrier archive (Bricks tpl 65).
 *
 * Native conditions drive LAYOUT only. The data is already redacted in PHP by
 * ff-directory-gate, so a condition failing open cannot leak a value.
 *
 * Run: wp eval-file apply-teaser-layout.php [dry]
 */

global $wpdb;

$TPL      = 65;
$META_KEY = '_bricks_page_content_2';
$DRY      = in_array( 'dry', (array) ( $args ?? array() ), true );

$content = get_post_meta( $TPL, $META_KEY, true );

if ( ! is_array( $content ) || empty( $content ) ) {
	WP_CLI::error( 'Template ' . $TPL . ' has no ' . $META_KEY );
}

// ---------------------------------------------------------------- backup
$backup_key = '_bricks_page_content_2_bak_dirgate';

if ( ! get_post_meta( $TPL, $backup_key, true ) ) {
	update_post_meta( $TPL, $backup_key, $content );
	WP_CLI::log( 'Backup written to postmeta ' . $backup_key );
} else {
	WP_CLI::log( 'Backup already exists at ' . $backup_key . ' (left untouched)' );
}

// ------------------------------------------------------- condition helpers
function ffdg_cond( $compare, $cid ) {
	return array(
		array(
			array(
				'id'           => $cid,
				'key'          => 'dynamic_data',
				'dynamic_data' => '{ff_dir_can_search}',
				'compare'      => $compare,
			),
		),
	);
}

// Elements that carry identity or are member-only actions.
$member_only = array(
	'hspwxn' => 'ffdg01', // block: carrier name heading + Save to My Network
	'dqxmxj' => 'ffdg02', // city, state, zip, country
	'kfluid' => 'ffdg03', // MC | USDOT
	'rhgvro' => 'ffdg04', // View Listing button
);

$CARD       = 'tdskyj';
$AFTER      = 'bsixfk'; // insert the locked line after the operation/units/equipment block
$LOCKED_ID  = 'ffdglk';

$changed = array();
$found   = array();

foreach ( $content as $i => $el ) {

	$id = $el['id'] ?? '';

	if ( isset( $member_only[ $id ] ) ) {
		$content[ $i ]['settings']['_conditions'] = ffdg_cond( 'empty_not', $member_only[ $id ] );
		$changed[] = $id . ' -> member only';
		$found[]   = $id;
	}

	if ( $CARD === $id ) {
		$found[] = $CARD;
	}
}

$missing = array_diff( array_merge( array_keys( $member_only ), array( $CARD ) ), $found );

if ( ! empty( $missing ) ) {
	WP_CLI::error( 'Expected elements not found: ' . implode( ', ', $missing ) . ' - aborting, template structure changed.' );
}

// -------------------------------------------------- insert the locked line
$already = false;

foreach ( $content as $el ) {
	if ( ( $el['id'] ?? '' ) === $LOCKED_ID ) {
		$already = true;
	}
}

if ( $already ) {
	WP_CLI::log( 'Locked-line element ' . $LOCKED_ID . ' already present - not re-inserting.' );
} else {

	$locked = array(
		'id'       => $LOCKED_ID,
		'name'     => 'shortcode',
		'parent'   => $CARD,
		'children' => array(),
		'settings' => array(
			'shortcode'   => '[ff_dir_locked feature="search"]',
			'_conditions' => ffdg_cond( 'empty', 'ffdg05' ),
		),
	);

	$content[] = $locked;

	// Register it as a child of the card, immediately after $AFTER.
	foreach ( $content as $i => $el ) {

		if ( ( $el['id'] ?? '' ) !== $CARD ) {
			continue;
		}

		$kids = $el['children'] ?? array();
		$pos  = array_search( $AFTER, $kids, true );

		if ( false === $pos ) {
			$kids[] = $LOCKED_ID;
		} else {
			array_splice( $kids, $pos + 1, 0, array( $LOCKED_ID ) );
		}

		$content[ $i ]['children'] = $kids;
		$changed[] = $LOCKED_ID . ' -> inserted into ' . $CARD . ' after ' . $AFTER;
	}
}

WP_CLI::log( 'Planned changes:' );
foreach ( $changed as $c ) {
	WP_CLI::log( '  - ' . $c );
}

if ( $DRY ) {
	WP_CLI::success( 'DRY RUN - nothing written.' );
	return;
}

// --------------------------------------------------------------- write back
// update_post_meta is blocked on this key on FF sites - write via $wpdb.
$serialized = maybe_serialize( $content );

$ok = $wpdb->update(
	$wpdb->postmeta,
	array( 'meta_value' => $serialized ),
	array( 'post_id' => $TPL, 'meta_key' => $META_KEY )
);

if ( false === $ok ) {
	WP_CLI::error( 'DB write failed: ' . $wpdb->last_error );
}

clean_post_cache( $TPL );
wp_cache_delete( $TPL, 'post_meta' );

// Verify by re-reading.
$check = get_post_meta( $TPL, $META_KEY, true );
$verified = 0;

foreach ( $check as $el ) {
	$id = $el['id'] ?? '';
	if ( isset( $member_only[ $id ] ) && ! empty( $el['settings']['_conditions'] ) ) {
		$verified++;
	}
	if ( $id === $LOCKED_ID ) {
		$verified++;
	}
}

WP_CLI::success( 'Written. Verified ' . $verified . ' / ' . ( count( $member_only ) + 1 ) . ' elements on re-read.' );

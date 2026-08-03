<?php
/**
 * Put [ff_dir_account_bar] in the Directory header.
 *
 * Header template 28236 stores its tree in **_bricks_page_header_2**, not
 * _bricks_page_content_2 (that is the Bricks convention for header templates).
 *
 *   section #337673
 *     container #5d81f5
 *       logo #994e89
 *       nav-menu #b9296c
 *       block #df0bdb   <- the two CTA buttons
 *
 * Placed as a child of the CONTAINER, not of #df0bdb: that block is
 * display:none below 991px, so anything inside it would leave a member with no
 * way to log out on a phone.
 *
 * Run: wp eval-file add-account-bar-to-header.php [dry]
 */

global $wpdb;

$TPL    = 28236;
$KEY    = '_bricks_page_header_2';
$PARENT = '5d81f5';
$AFTER  = 'df0bdb';
$ID     = 'ffdgab';
$DRY    = in_array( 'dry', (array) ( $args ?? array() ), true );

$content = get_post_meta( $TPL, $KEY, true );

if ( ! is_array( $content ) || empty( $content ) ) {
	WP_CLI::error( "template $TPL has no $KEY" );
}

$bak = $KEY . '_bak_accountbar';

if ( ! get_post_meta( $TPL, $bak, true ) ) {
	update_post_meta( $TPL, $bak, $content );
	WP_CLI::log( "backup -> postmeta $bak" );
} else {
	WP_CLI::log( "backup already exists at $bak" );
}

$ids = array();
foreach ( $content as $el ) {
	$ids[] = $el['id'];
}

if ( ! in_array( $PARENT, $ids, true ) ) {
	WP_CLI::error( "parent $PARENT not found - header structure changed, aborting." );
}

// Idempotent: drop any previous run.
$content = array_values( array_filter( $content, function ( $el ) use ( $ID ) {
	return $el['id'] !== $ID;
} ) );

foreach ( $content as $i => $el ) {

	if ( $el['id'] !== $PARENT ) {
		continue;
	}

	$kids = array_values( array_filter( (array) $el['children'], function ( $k ) use ( $ID ) {
		return $k !== $ID;
	} ) );

	$pos = array_search( $AFTER, $kids, true );

	if ( false === $pos ) {
		$kids[] = $ID;
	} else {
		array_splice( $kids, $pos + 1, 0, array( $ID ) );
	}

	$content[ $i ]['children'] = $kids;
	WP_CLI::log( "  $ID inserted into $PARENT after $AFTER" );
}

$content[] = array(
	'id'       => $ID,
	'name'     => 'shortcode',
	'parent'   => $PARENT,
	'children' => array(),
	'settings' => array(
		'shortcode'   => '[ff_dir_account_bar]',
		'_typography' => array( 'font-size' => 'var(--text-s)' ),
	),
);

// Integrity.
$all = array();
foreach ( $content as $el ) {
	$all[] = $el['id'];
}

if ( count( $all ) !== count( array_unique( $all ) ) ) {
	WP_CLI::error( 'duplicate ids after insert' );
}

foreach ( $content as $el ) {
	foreach ( (array) $el['children'] as $k ) {
		if ( ! in_array( $k, $all, true ) ) {
			WP_CLI::error( 'dangling child ' . $el['id'] . ' -> ' . $k );
		}
	}
}

WP_CLI::log( '  elements: ' . count( $content ) );

if ( $DRY ) {
	WP_CLI::success( 'DRY RUN - nothing written.' );
	return;
}

$ok = $wpdb->update(
	$wpdb->postmeta,
	array( 'meta_value' => maybe_serialize( $content ) ),
	array( 'post_id' => $TPL, 'meta_key' => $KEY )
);

if ( false === $ok ) {
	WP_CLI::error( 'DB write failed: ' . $wpdb->last_error );
}

clean_post_cache( $TPL );
wp_cache_delete( $TPL, 'post_meta' );

$check = get_post_meta( $TPL, $KEY, true );
$found = false;

foreach ( (array) $check as $el ) {
	if ( $el['id'] === $ID ) {
		$found = true;
	}
}

WP_CLI::success( $found ? 'written and verified on re-read.' : 'WRITTEN BUT NOT FOUND ON RE-READ' );

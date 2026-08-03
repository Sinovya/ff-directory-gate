<?php
/**
 * Put [ff_dir_account_notice] above the carrier results on prod tpl 65.
 *
 *   container #ocpzym
 *     kgzjio          (filters)
 *     block #xhnrzs   <- results column
 *       yrkuon        (the loop)
 *
 * Inserted as the FIRST child of xhnrzs, so a customer in grace sees the
 * warning before the results rather than after scrolling past 50 cards.
 *
 * Carries a Bricks condition on {ff_dir_access_state} (grace OR expired) so the
 * element is not rendered at all for everyone else - the shortcode already
 * self-hides, but an always-present empty wrapper would still occupy layout.
 *
 * Run: wp eval-file place-account-notice.php [dry]
 */

global $wpdb;

$TPL    = 65;
$KEY    = '_bricks_page_content_2';
$PARENT = 'xhnrzs';
$ID     = 'ffdgan';
$DRY    = in_array( 'dry', (array) ( $args ?? array() ), true );

$content = get_post_meta( $TPL, $KEY, true );

if ( ! is_array( $content ) || empty( $content ) ) {
	WP_CLI::error( "template $TPL has no $KEY" );
}

$bak = $KEY . '_bak_accountnotice';

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
	WP_CLI::error( "parent $PARENT not found - template structure changed, aborting." );
}

// Idempotent.
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

	array_unshift( $kids, $ID ); // first child = above the results

	$content[ $i ]['children'] = $kids;
	WP_CLI::log( "  $ID inserted as first child of $PARENT" );
}

// Two condition GROUPS = OR (grace OR expired).
$content[] = array(
	'id'       => $ID,
	'name'     => 'shortcode',
	'parent'   => $PARENT,
	'children' => array(),
	'settings' => array(
		'shortcode'   => '[ff_dir_account_notice]',
		'_conditions' => array(
			array(
				array(
					'id'           => 'ffdgn1',
					'key'          => 'dynamic_data',
					'dynamic_data' => '{ff_dir_access_state}',
					'compare'      => '==',
					'value'        => 'grace',
				),
			),
			array(
				array(
					'id'           => 'ffdgn2',
					'key'          => 'dynamic_data',
					'dynamic_data' => '{ff_dir_access_state}',
					'compare'      => '==',
					'value'        => 'expired',
				),
			),
		),
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

if ( $DRY ) {
	WP_CLI::success( 'DRY RUN - nothing written. elements would be ' . count( $content ) );
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

WP_CLI::success( $found ? 'written and verified.' : 'WRITTEN BUT NOT FOUND ON RE-READ' );

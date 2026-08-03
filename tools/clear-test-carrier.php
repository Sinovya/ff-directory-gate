<?php
/**
 * Retire a test carrier record from production.
 *
 * Post 24110 "Arthur J Kenyon" is a test row that reached the live directory:
 * USDOT 1234567 (sequential filler), FreightForge's own phone number, and a
 * personal email address. It is published, counted in the carrier total, and
 * indexed for search, so a paying customer could find it and treat it as real.
 *
 * Backs everything up to a JSON file FIRST, then moves the post to trash
 * (reversible) and clears its search-index rows.
 *
 * Run: wp eval-file clear-test-carrier.php <post_id> [dry]
 */

global $wpdb;

$post_id = isset( $args[0] ) && is_numeric( $args[0] ) ? (int) $args[0] : 0;
$DRY     = in_array( 'dry', (array) ( $args ?? array() ), true );

if ( ! $post_id ) {
	WP_CLI::error( 'usage: wp eval-file clear-test-carrier.php <post_id> [dry]' );
}

$post = get_post( $post_id );

if ( ! $post || 'carriers' !== $post->post_type ) {
	WP_CLI::error( "post $post_id is not a carrier" );
}

WP_CLI::log( 'carrier : ' . $post->post_title . ' (#' . $post_id . ', ' . $post->post_status . ')' );

// ------------------------------------------------------------- gather
$backup = array(
	'exported_at' => gmdate( 'c' ),
	'post'        => $post->to_array(),
	'postmeta'    => get_post_meta( $post_id ),
	'terms'       => array(),
	'tables'      => array(),
);

foreach ( get_object_taxonomies( 'carriers' ) as $tax ) {
	$terms = wp_get_post_terms( $post_id, $tax, array( 'fields' => 'names' ) );
	if ( ! is_wp_error( $terms ) && $terms ) {
		$backup['terms'][ $tax ] = $terms;
	}
}

foreach ( array( 'dlrh_carrier_information', 'dlrh_carrier_equipment', 'dlrh_carrier_cargo' ) as $t ) {
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE ID = %d", $post_id ), ARRAY_A );
	if ( $row ) {
		$backup['tables'][ $t ] = $row;
	}
}

$index_rows = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM dlrh_facetwp_index WHERE post_id = %d', $post_id ) );

WP_CLI::log( '  custom-table rows : ' . implode( ', ', array_keys( $backup['tables'] ) ) );
WP_CLI::log( '  taxonomies        : ' . count( $backup['terms'] ) );
WP_CLI::log( '  search index rows : ' . $index_rows );

/*
 * NOT wp-content/uploads - that is served over the web, and this backup holds
 * the carrier's contact details. Write above the web root instead.
 */
$dir = dirname( ABSPATH ) . '/private-backups';

if ( ! is_dir( $dir ) ) {
	wp_mkdir_p( $dir );
}

$file = $dir . '/ff-test-carrier-' . $post_id . '-backup-' . gmdate( 'Ymd-His' ) . '.json';

if ( $DRY ) {
	WP_CLI::log( '' );
	WP_CLI::log( '  would write backup to: ' . $file );
	WP_CLI::log( '  would then: trash the post, delete its search-index rows' );
	WP_CLI::success( 'DRY RUN - nothing written.' );
	return;
}

$json = wp_json_encode( $backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

if ( false === file_put_contents( $file, $json ) ) {
	WP_CLI::error( 'could not write the backup - aborting before any change' );
}

WP_CLI::log( '' );
WP_CLI::log( '  backup written: ' . $file . ' (' . strlen( $json ) . ' bytes)' );

// ------------------------------------------------------------- retire
$trashed = wp_trash_post( $post_id );

if ( ! $trashed ) {
	WP_CLI::error( 'wp_trash_post failed - nothing else was changed' );
}

// FacetWP normally re-indexes on status change, but clear explicitly so the
// record cannot linger in search results.
$deleted = $wpdb->delete( 'dlrh_facetwp_index', array( 'post_id' => $post_id ) );

clean_post_cache( $post_id );

WP_CLI::log( '  post status now : ' . get_post_status( $post_id ) );
WP_CLI::log( '  index rows removed: ' . (int) $deleted );
WP_CLI::log( '  index rows left   : ' . (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM dlrh_facetwp_index WHERE post_id = %d', $post_id ) ) );

$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='carriers' AND post_status='publish'" );
WP_CLI::log( '  published carriers now: ' . number_format_i18n( $total ) );

WP_CLI::success( 'retired. Restore from Trash in wp-admin, or from the JSON backup above.' );

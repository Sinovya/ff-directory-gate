<?php
/**
 * Correct FF Provisioning (29737) to the version that is actually deployed.
 *
 * Run:      wp eval-file fix-cpt-provisioning-version.php
 * Dry run:  FF_CPT_DRY=1 wp eval-file fix-cpt-provisioning-version.php
 *
 * The entry recorded v0.3.0, which exists nowhere: the onboarding clone template
 * and demo - the only two sites with the plugin - both run 0.2.0, and the
 * repository is already at 0.4.0.
 *
 * last_updated stays 09/07/2026: every version from 0.1.0 to 0.4.0 was committed
 * that day, so the date is right regardless of which version is recorded.
 *
 * A deployment note is appended because the prose describes behaviour that is
 * NOT live - writing the customer name and address into the forms arrived in
 * 0.3.0 - and an entry that documents undeployed behaviour without saying so is
 * worse than one with a stale version number.
 */

global $wpdb;

$DRY = (bool) getenv( 'FF_CPT_DRY' );

$id      = 29737;
$slug    = 'ff-provisioning';
$version = 'v0.2.0';
$marker  = 'Deployed state, 2026-09-07';

$append = "\n" . '<p><strong>&#9888; Deployed state, 2026-09-07.</strong> The onboarding clone template and demo run <strong>0.2.0</strong> &mdash; they are the only two sites that have this plugin. The repository is already at <strong>0.4.0</strong>. Two things described above are therefore <strong>not live yet</strong>: writing the customer&rsquo;s name and address into the forms arrived in <strong>0.3.0</strong>, and being able to change or clear a value after provisioning arrived in <strong>0.4.0</strong>. This entry records the version that is deployed, as every entry does &mdash; not the newest one that exists.</p>';

$post = get_post( $id );

if ( ! $post || 'plugin' !== $post->post_type || $post->post_name !== $slug ) {
	WP_CLI::error( sprintf( '#%d is not "%s" - nothing written.', $id, $slug ) );
}

$now = $wpdb->get_row(
	$wpdb->prepare( 'SELECT plugin_version, last_updated, how_it_works_example_s FROM plugins WHERE ID = %d', $id ),
	ARRAY_A
);

WP_CLI::log( sprintf( '#%d  %s%s', $id, $slug, $DRY ? '   [DRY RUN]' : '' ) );

$set = array();

if ( $version !== $now['plugin_version'] ) {
	$set['plugin_version'] = $version;
	WP_CLI::log( sprintf( '  plugin_version   %s  ->  %s   (deployed on clone + demo; repo is at 0.4.0)', $now['plugin_version'], $version ) );
}

WP_CLI::log( sprintf( '  last_updated     %s  (unchanged - 0.1.0 through 0.4.0 were all committed that day)', $now['last_updated'] ) );

if ( false === strpos( (string) $now['how_it_works_example_s'], $marker ) ) {
	$set['how_it_works_example_s'] = $now['how_it_works_example_s'] . $append;
	WP_CLI::log( sprintf( '  prose            %d B  ->  %d B   (deployment note appended)', strlen( $now['how_it_works_example_s'] ), strlen( $set['how_it_works_example_s'] ) ) );
} else {
	WP_CLI::log( '  prose            deployment note already present' );
}

if ( ! $set ) {
	WP_CLI::success( 'already correct - nothing to do.' );
	return;
}

if ( isset( $set['how_it_works_example_s'] ) ) {
	$v      = $set['how_it_works_example_s'];
	$faults = array();
	if ( substr_count( $v, '&amp;' ) > 0 ) {
		$faults[] = 'double-encoded ampersands';
	}
	if ( substr_count( $v, chr( 92 ) . 'n' ) > 0 ) {
		$faults[] = 'literal backslash-n';
	}
	foreach ( array( 'p', 'ul', 'ol', 'li' ) as $tag ) {
		if ( substr_count( $v, '<' . $tag . '>' ) !== substr_count( $v, '</' . $tag . '>' ) ) {
			$faults[] = $tag . ' tags unbalanced';
		}
	}
	if ( $faults ) {
		WP_CLI::error( implode( '; ', $faults ) . ' - nothing written.' );
	}
}

if ( $DRY ) {
	WP_CLI::success( 'dry run clean.' );
	return;
}

if ( false === $wpdb->update( 'plugins', $set, array( 'ID' => $id ) ) ) {
	WP_CLI::error( 'write failed: ' . $wpdb->last_error );
}

clean_post_cache( $id );
wp_cache_flush();

$fresh = $wpdb->get_row(
	$wpdb->prepare( 'SELECT plugin_version, last_updated, how_it_works_example_s FROM plugins WHERE ID = %d', $id ),
	ARRAY_A
);

foreach ( $set as $col => $val ) {
	if ( (string) $fresh[ $col ] !== (string) $val ) {
		WP_CLI::error( 'VERIFY FAILED on ' . $col );
	}
}

WP_CLI::log( sprintf( '  verified: %s / %s', $fresh['plugin_version'], $fresh['last_updated'] ) );
WP_CLI::success( 'done.' );

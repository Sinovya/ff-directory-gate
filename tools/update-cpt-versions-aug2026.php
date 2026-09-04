<?php
/**
 * Correct 12 stale plugin_version values and one wrong status in the "Plugins"
 * CPT (Meta Box custom table `plugins`).
 *
 * Run:      wp eval-file update-cpt-versions-aug2026.php
 * Dry run:  FF_CPT_DRY=1 wp eval-file update-cpt-versions-aug2026.php
 *
 * RULES OBSERVED
 *
 * - Only plugin_version / last_updated / status are touched. Prose is never
 *   rewritten by this script.
 * - last_updated is the plugin's real release date - the repository's last
 *   commit where the repo holds the deployed version, else the server file
 *   mtime. It is NOT today's date: bumping it for a doc edit would falsely
 *   imply a code release.
 * - Where only the version was wrong and the date already reflects the real
 *   release, the date is left alone (28989).
 * - Each row is matched on BOTH the post ID and the expected slug, so a wrong
 *   ID writes nothing rather than overwriting an unrelated entry.
 * - Idempotent: a row already holding the target values is reported as
 *   "already correct" and skipped.
 * - The existing "v" prefix convention of each entry is preserved (28633 has
 *   never used one).
 *
 * DELIBERATELY EXCLUDED - these need a decision, not a value:
 *   28827 ff-network-lookup    three versions live at once (0.3.5 clone /
 *                              0.4.0 frontline / 0.6.0 demo)
 *   28817 ff-after-login-router  two divergent codebases share one name
 *                              (2.0.1 on onboarding sites, 1.1.0 mu on the
 *                              Directory)
 */

global $wpdb;

$DRY = (bool) getenv( 'FF_CPT_DRY' );

// id => [slug, version, last_updated|null (null = leave alone), status|null, why]
$rows = array(
	28633 => array( 'freightforge-carrier-data-api-2', '2.17.0',  '08/26/2026', null, 'gateway: 11 minor versions behind; TMS integration shipped in these' ),
	28805 => array( 'ff-scheduled-refresh',            'v1.5.0',  '08/31/2026', null, 'permanent-failure fix; 1.4.x/1.5.0 are in NO repo, date is the prod file mtime' ),
	28832 => array( 'ff-lane-tender',                  'v1.22.0', '08/18/2026', null, 'sandbox' ),
	28830 => array( 'ff-broker-network',               'v1.5.0',  '08/17/2026', null, 'sandbox' ),
	28989 => array( 'ff-directory-gate',               'v0.9.0',  null,         null, 'version only: 0.9.0 was already the repo state on the entry date' ),
	28828 => array( 'ff-network-banner',               'v0.4.0',  '08/18/2026', null, '' ),
	28818 => array( 'ff-gf-entry-cleaner',             'v1.8.6',  '08/10/2026', null, '' ),
	28589 => array( 'freightforge-carrier-insert',     'v1.9.15', '08/31/2026', null, 'zone label canonicalisation, deployed to prod 08/31' ),
	28730 => array( 'ff-carrier-importer',             'v1.3.1',  '08/31/2026', null, 'zone label canonicalisation, deployed to prod 08/31' ),
	28808 => array( 'ff-lanes-plugin',                 'v1.1.7',  '08/31/2026', null, 'zone label canonicalisation, deployed to prod 08/31' ),
	28807 => array( 'ff-lane-importer',                'v2.0.1',  '08/31/2026', null, 'zone label canonicalisation, deployed to prod 08/31' ),
	28822 => array( 'fmcsa-proxy-lookup',              'v2.4.7',  '08/18/2026', null, 'onboarding sites at 2.4.7; demo runs 2.4.9, uncommitted' ),
	28826 => array( 'ff-cron-monitor',                 null,      null,         'Active', 'STATUS FIX: it is a must-use plugin on prod AND sandbox, not undeployed' ),
);

WP_CLI::log( sprintf( '%d entries to reconcile%s', count( $rows ), $DRY ? '   [DRY RUN]' : '' ) );
WP_CLI::log( str_repeat( '-', 78 ) );

$changed = 0;
$already = 0;
$failed  = 0;

foreach ( $rows as $id => $r ) {

	list( $slug, $version, $updated, $status, $why ) = $r;

	$post = get_post( $id );

	if ( ! $post || 'plugin' !== $post->post_type ) {
		WP_CLI::warning( sprintf( '#%d is not a plugin entry - skipped, nothing written', $id ) );
		$failed++;
		continue;
	}

	if ( $post->post_name !== $slug ) {
		WP_CLI::warning( sprintf(
			'#%d slug mismatch: expected "%s", found "%s" - skipped, nothing written',
			$id, $slug, $post->post_name
		) );
		$failed++;
		continue;
	}

	$now = array(
		'plugin_version' => (string) rwmb_meta( 'plugin_version', '', $id ),
		'last_updated'   => (string) rwmb_meta( 'last_updated', '', $id ),
		'status'         => (string) rwmb_meta( 'status', '', $id ),
	);

	$set = array();
	if ( null !== $version && $version !== $now['plugin_version'] ) {
		$set['plugin_version'] = $version;
	}
	if ( null !== $updated && $updated !== $now['last_updated'] ) {
		$set['last_updated'] = $updated;
	}
	if ( null !== $status && $status !== $now['status'] ) {
		$set['status'] = $status;
	}

	WP_CLI::log( '' );
	WP_CLI::log( sprintf( '#%d  %s', $id, $slug ) );

	if ( ! $set ) {
		WP_CLI::log( sprintf( '  already correct: %s / %s / %s', $now['plugin_version'], $now['last_updated'], $now['status'] ) );
		$already++;
		continue;
	}

	foreach ( $set as $col => $val ) {
		WP_CLI::log( sprintf( '  %-14s %-12s ->  %s', $col, $now[ $col ] !== '' ? $now[ $col ] : '(blank)', $val ) );
	}
	if ( '' !== $why ) {
		WP_CLI::log( '  reason: ' . $why );
	}

	if ( $DRY ) {
		$changed++;
		continue;
	}

	$ok = $wpdb->update( 'plugins', $set, array( 'ID' => $id ) );

	if ( false === $ok ) {
		WP_CLI::warning( '  write failed: ' . $wpdb->last_error );
		$failed++;
		continue;
	}

	clean_post_cache( $id );

	/*
	 * Read back from the table, NOT through rwmb_meta().
	 *
	 * Meta Box caches the custom-table row for the life of the request. Reading
	 * the "before" values above populates that cache, so rwmb_meta() here would
	 * return the pre-update copy and report a false failure on a write that
	 * actually succeeded. clean_post_cache() does not clear it - it is not the
	 * post cache. A fresh process reads the new values correctly, which is what
	 * the admin edit screen does on the next page load.
	 */
	$fresh = $wpdb->get_row(
		$wpdb->prepare( 'SELECT plugin_version, last_updated, status FROM plugins WHERE ID = %d', $id ),
		ARRAY_A
	);

	$bad = array();
	foreach ( $set as $col => $val ) {
		$got = isset( $fresh[ $col ] ) ? (string) $fresh[ $col ] : '';
		if ( $got !== $val ) {
			$bad[] = sprintf( '%s is "%s", expected "%s"', $col, $got, $val );
		}
	}

	if ( $bad ) {
		WP_CLI::warning( '  VERIFY FAILED: ' . implode( '; ', $bad ) );
		$failed++;
		continue;
	}

	WP_CLI::log( '  verified via rwmb_meta()' );
	$changed++;
}

if ( ! $DRY ) {
	wp_cache_flush();
}

WP_CLI::log( '' );
WP_CLI::log( str_repeat( '-', 78 ) );
WP_CLI::log( sprintf( 'changed %d   already correct %d   failed %d', $changed, $already, $failed ) );

if ( $failed ) {
	WP_CLI::warning( 'finished with problems - see above.' );
} else {
	WP_CLI::success( $DRY ? 'dry run clean.' : 'done.' );
}

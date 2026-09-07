<?php
/**
 * Attach the FF FacetWP Browser Index Guard repository to its CPT entry, and
 * (once the GitHub rename has been done) correct the FF FacetWP Refresh Sync
 * entry, whose repository no longer has a mismatched name.
 *
 * Run:      wp eval-file link-cpt-guard-repo.php
 * Dry run:  FF_CPT_DRY=1 wp eval-file link-cpt-guard-repo.php
 *
 * 29642 is gated on FF_CPT_RENAMED=1 so this can be run safely BEFORE the
 * rename: without that variable the refresh-sync entry is reported and left
 * alone, because pointing it at a repository that does not exist yet would be
 * worse than leaving the honest mismatch warning in place.
 *
 * GitHub keeps a redirect from the old repository name, so an un-updated remote
 * keeps working - which is exactly why a stale link here would go unnoticed.
 */

global $wpdb;

$DRY     = (bool) getenv( 'FF_CPT_DRY' );
$RENAMED = (bool) getenv( 'FF_CPT_RENAMED' );

$rows = array(

	// Append a repository bullet - this entry never claimed to have none.
	29643 => array(
		'slug'    => 'ff-facetwp-browser-index-guard',
		'url'     => 'https://github.com/Sinovya/ff-facetwp-browser-index-guard',
		'needle'  => '<li>Installed as a must-use plugin on production and the Directory sandbox.</li>',
		'replace' => '<li>Installed as a must-use plugin on production and the Directory sandbox, byte-identical on both (verified 2026-09-07).</li>'
			. "\n" . '<li>Repository: <code>Sinovya/ff-facetwp-browser-index-guard</code>, committed 2026-09-07 from production and byte-identical to the deployed file.</li>',
		'gated'   => false,
	),

	// The mismatch warning becomes false the moment the repo is renamed.
	29642 => array(
		'slug'    => 'ff-facetwp-refresh-sync',
		'url'     => 'https://github.com/Sinovya/ff-facetwp-refresh-sync',
		'needle'  => '<li>Repository is <code>Sinovya/ff-facet-refresh-sync</code>, which does not match the plugin folder name <code>ff-facetwp-refresh-sync</code>. Expect it to look like a missing repository in any name-matching audit.</li>',
		'replace' => '<li>Repository: <code>Sinovya/ff-facetwp-refresh-sync</code>. It was originally created as <code>ff-facet-refresh-sync</code> (no &ldquo;wp&rdquo;) and renamed on 2026-09-07 to match the plugin folder, so it no longer reads as a missing repository in a name-matching audit. GitHub still redirects the old name, so an old clone keeps working.</li>',
		'gated'   => true,
	),
);

WP_CLI::log( sprintf(
	'%d entries%s%s',
	count( $rows ),
	$DRY ? '   [DRY RUN]' : '',
	$RENAMED ? '   [rename confirmed]' : '   [rename NOT confirmed - 29642 will be skipped]'
) );
WP_CLI::log( str_repeat( '-', 78 ) );

$changed = 0;
$already = 0;
$skipped = 0;
$failed  = 0;

foreach ( $rows as $id => $r ) {

	$post = get_post( $id );

	if ( ! $post || 'plugin' !== $post->post_type || $post->post_name !== $r['slug'] ) {
		WP_CLI::warning( sprintf( '#%d did not match slug "%s" - nothing written', $id, $r['slug'] ) );
		$failed++;
		continue;
	}

	WP_CLI::log( '' );
	WP_CLI::log( sprintf( '#%d  %s', $id, $r['slug'] ) );

	if ( $r['gated'] && ! $RENAMED ) {
		WP_CLI::log( '  SKIPPED - set FF_CPT_RENAMED=1 once the GitHub rename is done.' );
		$skipped++;
		continue;
	}

	$now = $wpdb->get_row(
		$wpdb->prepare( 'SELECT github_link, how_it_works_example_s FROM plugins WHERE ID = %d', $id ),
		ARRAY_A
	);

	$set = array();

	$want_link = serialize( array(
		'url'     => $r['url'],
		'title'   => $r['url'],
		'target'  => '',
		'post_id' => 0,
	) );

	if ( (string) $now['github_link'] !== $want_link ) {
		$set['github_link'] = $want_link;
		WP_CLI::log( '  github_link   ' . ( '' === (string) $now['github_link'] ? '(none)' : 'changing' ) . '  ->  ' . $r['url'] );
	}

	$how = (string) $now['how_it_works_example_s'];

	if ( false !== strpos( $how, $r['replace'] ) ) {
		WP_CLI::log( '  prose         already updated' );
	} elseif ( false === strpos( $how, $r['needle'] ) ) {
		WP_CLI::warning( '  prose needle NOT FOUND - entry skipped so nothing is half-written.' );
		$failed++;
		continue;
	} else {
		$new = str_replace( $r['needle'], $r['replace'], $how );
		$set['how_it_works_example_s'] = $new;
		WP_CLI::log( sprintf( '  prose         %d B  ->  %d B', strlen( $how ), strlen( $new ) ) );
	}

	if ( ! $set ) {
		WP_CLI::log( '  already correct' );
		$already++;
		continue;
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
			WP_CLI::warning( '  ' . implode( '; ', $faults ) . ' - entry skipped.' );
			$failed++;
			continue;
		}
	}

	if ( $DRY ) {
		WP_CLI::log( '  checks ok' );
		$changed++;
		continue;
	}

	if ( false === $wpdb->update( 'plugins', $set, array( 'ID' => $id ) ) ) {
		WP_CLI::warning( '  write failed: ' . $wpdb->last_error );
		$failed++;
		continue;
	}

	clean_post_cache( $id );

	$fresh = $wpdb->get_row(
		$wpdb->prepare( 'SELECT github_link, how_it_works_example_s FROM plugins WHERE ID = %d', $id ),
		ARRAY_A
	);

	$bad = array();
	foreach ( $set as $col => $val ) {
		if ( (string) $fresh[ $col ] !== (string) $val ) {
			$bad[] = $col;
		}
	}

	if ( $bad ) {
		WP_CLI::warning( '  VERIFY FAILED on: ' . implode( ', ', $bad ) );
		$failed++;
		continue;
	}

	WP_CLI::log( '  verified against the table' );
	$changed++;
}

if ( ! $DRY ) {
	wp_cache_flush();
}

$norepo = $wpdb->get_col(
	"SELECT p.post_name FROM {$wpdb->posts} p
	 JOIN plugins t ON t.ID = p.ID
	 WHERE p.post_type = 'plugin' AND p.post_status = 'publish'
	   AND ( t.github_link = '' OR t.github_link IS NULL )
	 ORDER BY p.post_name"
);

WP_CLI::log( '' );
WP_CLI::log( str_repeat( '-', 78 ) );
WP_CLI::log( sprintf( 'changed %d   already correct %d   skipped %d   failed %d', $changed, $already, $skipped, $failed ) );
WP_CLI::log( 'entries still with no repository (' . count( $norepo ) . '): ' . ( $norepo ? implode( ', ', $norepo ) : 'none' ) );

if ( $failed ) {
	WP_CLI::warning( 'finished with problems - see above.' );
} else {
	WP_CLI::success( $DRY ? 'dry run clean.' : 'done.' );
}

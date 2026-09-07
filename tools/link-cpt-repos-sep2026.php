<?php
/**
 * Attach the newly created repositories to their Plugins CPT entries.
 *
 * Run:      wp eval-file link-cpt-repos-sep2026.php
 * Dry run:  FF_CPT_DRY=1 wp eval-file link-cpt-repos-sep2026.php
 *
 * Five plugins that were server-only now have repositories, and a sixth (the
 * Directory build of the after-login router) lives on a branch of an existing
 * one. Two things follow for each entry:
 *
 *   1. github_link is set (serialized Meta Box URL field).
 *   2. The bullet that says "No repository ... server only" is replaced, because
 *      leaving it would make the entry contradict its own link.
 *
 * The prose replacement is an exact str_replace. If the needle is not found the
 * entry is REPORTED AND SKIPPED rather than written blindly - a silent no-op
 * here would leave a false statement in a document people trust.
 *
 * ff-search-enhancements (29649) is deliberately untouched: it still has no
 * repository, and its entry says so correctly.
 */

global $wpdb;

$DRY = (bool) getenv( 'FF_CPT_DRY' );

// id => [slug, repo url, needle|null, replacement|null]
$rows = array(

	29731 => array(
		'ff-carrier-context',
		'https://github.com/Sinovya/ff-carrier-context',
		'<li>No repository &mdash; the plugin exists on the server only.</li>',
		'<li>Repository: <code>Sinovya/ff-carrier-context</code>, committed 2026-09-07 from the demo server and byte-identical to the deployed file.</li>',
	),

	29732 => array(
		'ff-workflow-inbox-guard',
		'https://github.com/Sinovya/ff-workflow-inbox-guard',
		'<li>No repository &mdash; a server-only must-use plugin on freightforge.com.</li>',
		'<li>Repository: <code>Sinovya/ff-workflow-inbox-guard</code>, committed 2026-09-07 from freightforge.com and byte-identical to the deployed file.</li>',
	),

	29733 => array(
		'ff-silence-mcp-notices',
		'https://github.com/Sinovya/ff-silence-mcp-notices',
		'<li>No repository &mdash; a server-only must-use plugin on freightforge.com.</li>',
		'<li>Repository: <code>Sinovya/ff-silence-mcp-notices</code>, committed 2026-09-07 from freightforge.com and byte-identical to the deployed file.</li>',
	),

	29648 => array(
		'ff-carrier-csv-export',
		'https://github.com/Sinovya/ff-carrier-csv-export',
		'<li>No repository. The plugin exists on the server only, which is a gap worth closing before it is next edited.</li>',
		'<li>Repository: <code>Sinovya/ff-carrier-csv-export</code>, committed 2026-09-07 from the Frontline server and byte-identical to the deployed file.</li>',
	),

	// This entry made no claim about a repository, so a bullet is appended after
	// the last one rather than replacing anything.
	29645 => array(
		'ff-proximity-index-fix',
		'https://github.com/Sinovya/ff-proximity-index-fix',
		'<li>Related: the proximity facet itself was converted from a stray checkbox facet to a true radius facet on 2026-06-09 (radius options 10/25/50/100/250 miles, default 100).</li>',
		'<li>Related: the proximity facet itself was converted from a stray checkbox facet to a true radius facet on 2026-06-09 (radius options 10/25/50/100/250 miles, default 100).</li>'
		. "\n" . '<li>Repository: <code>Sinovya/ff-proximity-index-fix</code>, committed 2026-09-07 from production and byte-identical to the deployed file.</li>',
	),

	29734 => array(
		'ff-after-login-router-directory',
		'https://github.com/Sinovya/ff-after-login-router/tree/directory',
		'<li>No separate repository. The repository <code>Sinovya/ff-after-login-router</code> holds the <strong>2.0.1 onboarding build</strong>, not this one, so this code exists on the servers only.</li>',
		'<li><strong>Repository: the <code>directory</code> branch of <code>Sinovya/ff-after-login-router</code></strong>. The <code>main</code> branch holds the 2.0.1 onboarding build.</li>'
		. "\n" . '<li>🚨 <strong>The branch was originally cut from the Directory <em>sandbox</em> copy, which is missing the <code>directory_member</code> route</strong> &mdash; the one thing that makes this build different. Corrected on 2026-09-07; the branch now matches <strong>production</strong> byte for byte. <strong>The sandbox file is still behind.</strong> Both report version 1.1.0, so the difference is invisible to a version check and only a checksum finds it.</li>',
	),
);

WP_CLI::log( sprintf( '%d entries to link%s', count( $rows ), $DRY ? '   [DRY RUN]' : '' ) );
WP_CLI::log( str_repeat( '-', 78 ) );

$changed = 0;
$already = 0;
$failed  = 0;

foreach ( $rows as $id => $r ) {

	list( $slug, $url, $needle, $replacement ) = $r;

	$post = get_post( $id );

	if ( ! $post || 'plugin' !== $post->post_type || $post->post_name !== $slug ) {
		WP_CLI::warning( sprintf( '#%d did not match slug "%s" - skipped, nothing written', $id, $slug ) );
		$failed++;
		continue;
	}

	$now = $wpdb->get_row(
		$wpdb->prepare( 'SELECT github_link, how_it_works_example_s FROM plugins WHERE ID = %d', $id ),
		ARRAY_A
	);

	WP_CLI::log( '' );
	WP_CLI::log( sprintf( '#%d  %s', $id, $slug ) );

	$set = array();

	// --- github_link -----------------------------------------------------
	$want_link = serialize( array(
		'url'     => $url,
		'title'   => $url,
		'target'  => '',
		'post_id' => 0,
	) );

	if ( (string) $now['github_link'] !== $want_link ) {
		$set['github_link'] = $want_link;
		WP_CLI::log( '  github_link   ' . ( '' === (string) $now['github_link'] ? '(none)' : 'changing' ) . '  ->  ' . $url );
	}

	// --- prose -----------------------------------------------------------
	$how = (string) $now['how_it_works_example_s'];

	if ( null !== $needle ) {
		if ( false !== strpos( $how, $replacement ) ) {
			WP_CLI::log( '  prose         already updated' );
		} elseif ( false === strpos( $how, $needle ) ) {
			WP_CLI::warning( '  prose needle NOT FOUND - entry skipped entirely so nothing is half-written.' );
			WP_CLI::log( '  needle: ' . substr( $needle, 0, 90 ) . '...' );
			$failed++;
			continue;
		} else {
			$new = str_replace( $needle, $replacement, $how );
			$set['how_it_works_example_s'] = $new;
			WP_CLI::log( sprintf( '  prose         %d B  ->  %d B', strlen( $how ), strlen( $new ) ) );
		}
	}

	if ( ! $set ) {
		WP_CLI::log( '  already correct' );
		$already++;
		continue;
	}

	// Prose sanity checks, same house rules as the other CPT scripts.
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

WP_CLI::log( '' );
WP_CLI::log( str_repeat( '-', 78 ) );
WP_CLI::log( sprintf( 'changed %d   already correct %d   failed %d', $changed, $already, $failed ) );

// How many entries still carry no repository at all?
$norepo = $wpdb->get_col(
	"SELECT p.post_name FROM {$wpdb->posts} p
	 JOIN plugins t ON t.ID = p.ID
	 WHERE p.post_type = 'plugin' AND p.post_status = 'publish'
	   AND ( t.github_link = '' OR t.github_link IS NULL )
	 ORDER BY p.post_name"
);

WP_CLI::log( '' );
WP_CLI::log( 'entries still with no repository (' . count( $norepo ) . '): ' . ( $norepo ? implode( ', ', $norepo ) : 'none' ) );

if ( $failed ) {
	WP_CLI::warning( 'finished with problems - see above.' );
} else {
	WP_CLI::success( $DRY ? 'dry run clean.' : 'done.' );
}

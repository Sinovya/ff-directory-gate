<?php
/**
 * Link the two long-unversioned plugins to their new repositories.
 *
 * Run:      wp eval-file link-cpt-legacy-repos.php
 * Dry run:  FF_CPT_DRY=1 wp eval-file link-cpt-legacy-repos.php
 *
 *   28806 freightforge-carrier-cargo-automation
 *   28814 freightforge-search-ui
 *
 * Both were edited in place on production for months with no repository; their
 * history was reconstructed from the .bak files beside the live files on
 * 2026-09-07. Each entry gains a repository bullet plus the drift fact that
 * matters for that plugin, appended before the closing </ul>.
 *
 * After this, ff-search-enhancements is the only entry with no repository, by
 * design.
 */

global $wpdb;

$DRY = (bool) getenv( 'FF_CPT_DRY' );

$rows = array(

	28806 => array(
		'slug'   => 'freightforge-carrier-cargo-automation',
		'url'    => 'https://github.com/Sinovya/freightforge-carrier-cargo-automation',
		'needle' => '<li><strong>Location:</strong> Directory (carrier profiles).</li>',
		'add'    => '<li><strong>Location:</strong> Directory (carrier profiles).</li>'
			. "\n" . '<li>Repository: <code>Sinovya/freightforge-carrier-cargo-automation</code>, created 2026-09-07. It had been edited in place on production with no version control; the history was reconstructed from the single <code>.bak</code> file beside the live file, which was the only record that ever existed.</li>'
			. "\n" . '<li>🔴 <strong>The Directory sandbox is running v2.6 and is three months behind</strong> &mdash; its file is byte-identical to the first commit. <strong>Sandbox therefore has no Socrata census fallback</strong>, so a carrier whose cargo is missing from FMCSA Mobile QC shows empty cargo there but resolves correctly on production. A cargo fault that reproduces only on sandbox is very likely this, not a real defect.</li>',
	),

	28814 => array(
		'slug'   => 'freightforge-search-ui',
		'url'    => 'https://github.com/Sinovya/freightforge-search-ui',
		'needle' => '<li><strong>Sits on top of:</strong> the enterprise search index (facets) and the CSV export plugin.</li>',
		'add'    => '<li><strong>Sits on top of:</strong> the enterprise search index (facets) and the CSV export plugin.</li>'
			. "\n" . '<li>Repository: <code>Sinovya/freightforge-search-ui</code>, created 2026-09-07. It had been edited in place on production for months; the history was reconstructed from the three <code>.bak</code> files beside the live file, which were the only record that ever existed.</li>'
			. "\n" . '<li>🚨 <strong>Every one of those four builds reports <code>Version: 1.0.4</code>.</strong> The version string was never advanced, so it cannot identify which build a site is running &mdash; <strong>only a checksum can</strong>. Production is <code>59c11ab8</code>.</li>'
			. "\n" . '<li>⚠️ <strong>The Directory sandbox is a separate lineage, not simply behind</strong> (<code>6bc65f26</code>, kept on the repository&rsquo;s <code>sandbox</code> branch). It never received the State/Province chip relabel, because its facet is not named <code>state_1</code> and the change would be a no-op there.</li>'
			. "\n" . '<li>⚠️ <strong>The files carry mixed line endings</strong> and the repository pins <code>* -text</code> to preserve them. Do not normalise them, and do not strip carriage returns when deploying this plugin &mdash; that is the correct move for the LF-native FF plugins but would corrupt this one.</li>',
	),
);

WP_CLI::log( sprintf( '%d entries to link%s', count( $rows ), $DRY ? '   [DRY RUN]' : '' ) );
WP_CLI::log( str_repeat( '-', 78 ) );

$changed = 0;
$already = 0;
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

	if ( false !== strpos( $how, $r['add'] ) ) {
		WP_CLI::log( '  prose         already updated' );
	} elseif ( false === strpos( $how, $r['needle'] ) ) {
		WP_CLI::warning( '  prose needle NOT FOUND - entry skipped so nothing is half-written.' );
		$failed++;
		continue;
	} else {
		$new = str_replace( $r['needle'], $r['add'], $how );
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
WP_CLI::log( sprintf( 'changed %d   already correct %d   failed %d', $changed, $already, $failed ) );
WP_CLI::log( 'entries still with no repository (' . count( $norepo ) . '): ' . ( $norepo ? implode( ', ', $norepo ) : 'none' ) );

if ( $failed ) {
	WP_CLI::warning( 'finished with problems - see above.' );
} else {
	WP_CLI::success( $DRY ? 'dry run clean.' : 'done.' );
}

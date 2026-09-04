<?php
/**
 * 2026-09-04 sweep: reconcile the CPT entries that moved.
 *
 * Run:      wp eval-file update-cpt-sep2026.php
 * Dry run:  FF_CPT_DRY=1 wp eval-file update-cpt-sep2026.php
 *
 *   28835 ff-selfserve-lanes    v0.6.0 -> v0.7.0 (07/10/2026)
 *   28833 ff-carrier-portal     date only: the plugin changed on 08/10 but the
 *                               version string never advanced
 *   28827 ff-network-lookup     v0.3.5 -> v0.8.0 + full prose refresh: it has
 *                               grown a second role (TMS document packet) that
 *                               the May entry does not mention at all
 *   28817 ff-after-login-router appends a cross-reference to the new Directory
 *                               entry. Version and date unchanged - the plugin
 *                               did not change, only our description of it.
 *
 * Every row is matched on BOTH id and slug. Idempotent throughout.
 * Verification reads the table directly: Meta Box caches its custom-table row
 * for the life of the request, so rwmb_meta() would report a stale value.
 */

global $wpdb;

$DRY = (bool) getenv( 'FF_CPT_DRY' );

/* -------------------------------------------------------------------------
 * ff-network-lookup: replacement prose
 * ---------------------------------------------------------------------- */

$nl_summary = <<<'HTML'
<p>FF Network Lookup runs on the broker&rsquo;s own site and is the broker-side end of two FreightForge services. First, it answers one question fast: is this carrier already in the FreightForge network? Given a USDOT number it asks the Directory through the API Gateway, caches the answer, and for a carrier we already hold it issues the tokens that send them down the express onboarding path instead of full re-registration. Second, since August 2026 it is the broker site&rsquo;s <strong>TMS entry point</strong>: it reports each carrier&rsquo;s workflow transitions back to the Directory, and it collects, stores and serves the documents that carrier supplied &mdash; including, since 0.8.0, the executed agreement itself as a typed deliverable rather than a file somebody has to go and find. It also owns the master switch for both, so this is where the paid add-on is turned on and off for each broker site.</p>
HTML;

$nl_how = <<<'HTML'
<p>Two jobs, one plugin, one switch. The lookup came first; the TMS role was added through 0.4.0 to 0.8.0 and now shares the same on/off control.</p>
<ol>
<li><strong>Check the Directory.</strong> It queries the Directory API Gateway for the USDOT and caches the result, so repeat checks are instant.</li>
<li><strong>Report network status.</strong> It returns whether the carrier is already in the network.</li>
<li><strong>Issue express tokens.</strong> For a carrier we already hold, it manages the tokens that let the broker use the express path rather than collecting everything again.</li>
<li><strong>Report workflow transitions.</strong> As a carrier moves through the broker&rsquo;s onboarding workflow, each transition is reported back to the Directory, so a partner TMS can see where a carrier has reached without polling.</li>
<li><strong>Collect the documents the carrier supplied</strong> &mdash; insurance certificates, authority paperwork and the rest &mdash; and serve them to an authorised partner on demand, each one verified byte-for-byte before it is handed over.</li>
<li><strong>Include the executed agreement itself.</strong> Once signed, the agreement is rendered once, stored, and offered alongside the uploads as a properly typed document rather than a filename somebody has to interpret.</li>
</ol>
<p><strong>Example:</strong></p>
<p>A broker enters a USDOT. The lookup finds the carrier already in the Directory and issues an express token, so the broker is sent down the short onboarding path instead of collecting everything from scratch. That carrier then signs their agreement, and the broker&rsquo;s partner TMS requests the carrier&rsquo;s document packet: it receives the three files the carrier uploaded plus the signed agreement, each one integrity-checked, with the agreement labelled as an executed contract rather than guessed at from its filename.</p>
<p><strong>Turning the add-on on and off &mdash; this plugin owns the switch</strong></p>
<p>Express carrier matching is an optional paid add-on, and this plugin is where it is enabled or disabled for a given broker site. FF Express Registration only consumes the tokens issued here, so there is no separate switch on that side. <strong>Since 0.7.0 the same switch also governs the TMS path,</strong> so a site that has not bought the add-on exposes neither the lookup nor the document packet.</p>
<ul>
<li><strong>Where:</strong> the broker site&rsquo;s WordPress admin &rarr; <strong>Settings &rarr; Network Lookup</strong> &rarr; the checkbox <em>&ldquo;Enable express carrier matching on this site&rdquo;</em> (administrators only).</li>
<li><strong>Default is off.</strong> Sites cloned from the onboarding template start dormant, so the add-on has to be switched on deliberately when a broker buys it.</li>
<li><strong>What off means:</strong> the check runs before any Directory API call, so nothing is queried, no express token is issued, and no express link appears in the broker&rsquo;s notification. Carriers follow the normal full registration path and nothing downstream fires.</li>
<li><strong>Before switching on,</strong> the site needs its Directory base URL, API key and API secret configured, along with the broker name and the express form&rsquo;s field mapping. Without them the lookup fails and carriers quietly fall back to full registration.</li>
</ul>
<p><strong>Technical reference</strong></p>
<ul>
<li>🚨 <strong>Three different versions are live at once.</strong> Onboarding clone template <strong>0.3.5</strong>, Frontline <strong>0.7.0</strong>, demo <strong>0.8.0</strong>. The version field above records the newest. Check the site before assuming a feature is present &mdash; the TMS document packet does not exist at all on the clone template.</li>
<li>⚠️ <strong>The signed-agreement deliverable is on demo only, not on Frontline</strong> &mdash; and Frontline is the site in the partner TMS integration.</li>
<li><strong>Master switch:</strong> the <code>ff_network_matching_enabled</code> option, default false, checked before any Directory API call and, since 0.7.0, before the TMS path too.</li>
<li><strong>Lookup path:</strong> the Gravity Forms hook calls the lookup in-process for speed. The REST route <code>GET /wp-json/ff/v1/network-status/{usdot}</code> is an administrator-only inspection endpoint for manual testing, not the path the form uses.</li>
<li><strong>Testing before go-live:</strong> that endpoint deliberately ignores the master switch, so a broker&rsquo;s credentials can be verified before the service is switched on.</li>
<li><strong>Credentials are issued per broker and per Directory install.</strong> <code>ff_network_lookup_directory_url</code>, <code>ff_network_lookup_api_key</code> and <code>ff_network_lookup_api_secret</code> must always be changed together. A key issued on the sandbox Directory does not exist on production, so repointing the URL on its own makes every lookup fail authentication &mdash; silently, with carriers dropping to full registration.</li>
<li><strong>Per-broker isolation:</strong> each broker&rsquo;s API Gateway client carries a carrier-source filter, which is what keeps one broker&rsquo;s lookups from returning another broker&rsquo;s carriers.</li>
<li><strong>The onboarding clone template stays pointed at the sandbox Directory</strong> and holds no production credentials, so a cloned site can never inherit another broker&rsquo;s API client.</li>
<li><strong>Documents are stored as bytes in a database table, not in the uploads folder.</strong> Three reasons: a signed contract under uploads is reachable by guessing a web address and these sites run nginx, where the usual folder-level deny file is silently ignored; the upload cleanup job deletes old uploads and would quietly remove executed agreements; and a database row cannot be half-removed by a file sync.</li>
<li><strong>The agreement is rendered once and stored, never re-rendered.</strong> The renderer is not byte-stable and the gateway re-checks the document&rsquo;s fingerprint before serving it, so regenerating on demand would fail our own integrity check. Measured at about two seconds for the first render and nothing thereafter.</li>
<li>⚠️ <strong>The signature add-on&rsquo;s renderer differs between its 1.9.x and 2.x lines.</strong> Both are supported, but <strong>the 1.9.x branch has never actually run</strong> &mdash; demo is on 2.x. It is exercised the moment this reaches Frontline, which is on 1.9.x.</li>
<li>Document collection is wrapped so that a rendering failure costs the agreement only, never the rest of the packet.</li>
<li>⚠️ <strong>The workflow step identifier that locates a signed agreement differs per site</strong> and is discovered at run time rather than hard-coded &mdash; a number taken from one site returns nothing on another.</li>
<li>⚠️ Uploaded documents are still reported as unclassified; only the agreement is typed. A partner files unknown types as &ldquo;other&rdquo;.</li>
<li><strong>Also:</strong> manages the express registration tokens that FF Express Registration consumes.</li>
</ul>
HTML;

$nl_faq = <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: What does this plugin actually do now?</em></p>
<p>A: Two things. It tells a broker whether a carrier is already in the FreightForge network, and it is the broker site&rsquo;s connection to a partner transport management system &mdash; reporting workflow progress and handing over the carrier&rsquo;s documents.</p>
<p><em>Q: Which version is a given broker site running?</em></p>
<p>A: Check the site. Three versions are live at once, and the document features do not exist on the oldest.</p>
<p><em>Q: Is the signed agreement available to partners today?</em></p>
<p>A: On the demo site, yes, and it is proven end to end. Not yet on Frontline, which is the site in the live partner integration.</p>
<p><em>Q: Why are the documents kept in the database rather than as files?</em></p>
<p>A: A signed contract sitting in the uploads folder is reachable by guessing a web address, and our routine upload cleanup would eventually delete it. Neither is acceptable for an executed agreement.</p>
<p><em>Q: Why not generate the agreement PDF when it is asked for?</em></p>
<p>A: Because the generated file is not byte-identical each time, and the gateway checks the document&rsquo;s fingerprint before serving it. Regenerating would fail our own integrity check.</p>
<p><em>Q: How is the add-on switched on for a broker?</em></p>
<p>A: One checkbox in the broker site&rsquo;s admin, under Settings then Network Lookup. It is off by default, and since 0.7.0 it governs the TMS path as well.</p>
<p><em>Q: What happens if it is off?</em></p>
<p>A: Nothing is queried and nothing is exposed. Carriers simply go through full registration as they always did.</p>
<p>&nbsp;</p>
<p><strong>Marketing:</strong></p>
<p>This is the piece that makes the network worth being in. A carrier already known to FreightForge does not fill in the same forms again for the next broker, and the broker gets a verified carrier faster than they could collect one. The document packet extends the same idea to the partner systems brokers already run: rather than chasing a carrier for an insurance certificate, the TMS asks for the packet and receives it &mdash; with the executed agreement in it, labelled as exactly what it is. Every carrier we already hold makes the next broker&rsquo;s onboarding cheaper, which is the whole argument for joining.</p>
HTML;

/* -------------------------------------------------------------------------
 * ff-after-login-router: appended cross-reference
 * ---------------------------------------------------------------------- */

$alr_marker = 'A second, different plugin shares this name';

$alr_append = <<<'HTML'

<p><strong>&#9888; A second, different plugin shares this name</strong></p>
<p>The Directory and the Directory sandbox run a <strong>different</strong> after-login router under this same name, numbered <strong>1.1.0</strong> and documented separately as <em>FF After-Login Router (Directory)</em>. This entry describes the <strong>onboarding-site</strong> build only. The two version numbers are not comparable: 1.1.0 on the Directory is newer in behaviour than 2.0.1 here, because it carries the paying-member routing that the onboarding sites have no use for. <strong>Never copy either file over the other</strong> &mdash; doing so removes routing that the receiving site depends on.</p>
HTML;

/* -------------------------------------------------------------------------
 * Rows
 * ---------------------------------------------------------------------- */

// id => [slug, version|null, last_updated|null, prose array|null, why]
$rows = array(
	28835 => array( 'ff-selfserve-lanes', 'v0.7.0', '07/10/2026', null,
		'v0.7.0 hooks the Gravity Flow outgoing-webhook step; live on freightforge.com' ),

	28833 => array( 'ff-carrier-portal', null, '08/10/2026', null,
		'date only - the plugin file changed on 08/10 but the version string never advanced' ),

	28827 => array( 'ff-network-lookup', 'v0.8.0', '09/04/2026',
		array(
			'summary'                => $nl_summary,
			'how_it_works_example_s' => $nl_how,
			'faq_marketing'          => $nl_faq,
		),
		'0.3.5 -> 0.8.0: gained the whole TMS document-packet role the entry never mentioned' ),

	28817 => array( 'ff-after-login-router', null, null, 'APPEND',
		'cross-reference to the new Directory entry; plugin unchanged so version and date stay' ),
);

WP_CLI::log( sprintf( '%d entries to reconcile%s', count( $rows ), $DRY ? '   [DRY RUN]' : '' ) );
WP_CLI::log( str_repeat( '-', 78 ) );

$changed = 0;
$already = 0;
$failed  = 0;

foreach ( $rows as $id => $r ) {

	list( $slug, $version, $updated, $prose, $why ) = $r;

	$post = get_post( $id );

	if ( ! $post || 'plugin' !== $post->post_type || $post->post_name !== $slug ) {
		WP_CLI::warning( sprintf( '#%d did not match slug "%s" - skipped, nothing written', $id, $slug ) );
		$failed++;
		continue;
	}

	$now = $wpdb->get_row(
		$wpdb->prepare( 'SELECT plugin_version, last_updated, summary, how_it_works_example_s, faq_marketing FROM plugins WHERE ID = %d', $id ),
		ARRAY_A
	);

	$set = array();

	if ( null !== $version && $version !== $now['plugin_version'] ) {
		$set['plugin_version'] = $version;
	}
	if ( null !== $updated && $updated !== $now['last_updated'] ) {
		$set['last_updated'] = $updated;
	}

	if ( 'APPEND' === $prose ) {
		if ( false === strpos( (string) $now['how_it_works_example_s'], $alr_marker ) ) {
			$set['how_it_works_example_s'] = $now['how_it_works_example_s'] . $alr_append;
		}
	} elseif ( is_array( $prose ) ) {
		// Only write a prose column that actually differs, so a re-run reports
		// "already correct" rather than a no-op write of identical bytes.
		foreach ( $prose as $col => $val ) {
			if ( (string) ( $now[ $col ] ?? '' ) !== (string) $val ) {
				$set[ $col ] = $val;
			}
		}
	}

	WP_CLI::log( '' );
	WP_CLI::log( sprintf( '#%d  %s', $id, $slug ) );

	if ( ! $set ) {
		WP_CLI::log( sprintf( '  already correct: %s / %s', $now['plugin_version'], $now['last_updated'] ) );
		$already++;
		continue;
	}

	foreach ( $set as $col => $val ) {
		if ( in_array( $col, array( 'summary', 'how_it_works_example_s', 'faq_marketing' ), true ) ) {
			WP_CLI::log( sprintf( '  %-24s %d B  ->  %d B', $col, strlen( (string) ( $now[ $col ] ?? '' ) ), strlen( $val ) ) );
		} else {
			WP_CLI::log( sprintf( '  %-24s %-12s ->  %s', $col, $now[ $col ], $val ) );
		}
	}
	WP_CLI::log( '  reason: ' . $why );

	// Prose sanity checks, same house rules as the create scripts.
	$faults = array();
	foreach ( $set as $col => $val ) {
		if ( ! in_array( $col, array( 'summary', 'how_it_works_example_s', 'faq_marketing' ), true ) ) {
			continue;
		}
		if ( substr_count( $val, '&amp;' ) > 0 ) {
			$faults[] = $col . ': double-encoded ampersands';
		}
		if ( substr_count( $val, chr( 92 ) . 'n' ) > 0 ) {
			$faults[] = $col . ': literal backslash-n';
		}
		foreach ( array( 'p', 'ul', 'ol', 'li' ) as $tag ) {
			if ( substr_count( $val, '<' . $tag . '>' ) !== substr_count( $val, '</' . $tag . '>' ) ) {
				$faults[] = sprintf( '%s: %s tags unbalanced', $col, $tag );
			}
		}
	}

	if ( $faults ) {
		WP_CLI::warning( '  ' . implode( '; ', $faults ) );
		WP_CLI::log( '  SKIPPED - nothing written for this entry.' );
		$failed++;
		continue;
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
		$wpdb->prepare( 'SELECT plugin_version, last_updated, summary, how_it_works_example_s, faq_marketing FROM plugins WHERE ID = %d', $id ),
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

if ( $failed ) {
	WP_CLI::warning( 'finished with problems - see above.' );
} else {
	WP_CLI::success( $DRY ? 'dry run clean.' : 'done.' );
}

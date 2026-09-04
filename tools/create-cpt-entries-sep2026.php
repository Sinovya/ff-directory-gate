<?php
/**
 * Create the four missing "Plugins" CPT entries found by the 2026-09-04 sweep.
 *
 * Run:      wp eval-file create-cpt-entries-sep2026.php
 * Dry run:  FF_CPT_DRY=1 wp eval-file create-cpt-entries-sep2026.php
 *
 * Three were found only by listing must-use plugins on every server; none of
 * the four has a repository, so a repo-based audit cannot see them at all.
 *
 * The fourth (ff-after-login-router-directory) is a deliberate SPLIT: the
 * Directory runs a genuinely different codebase under the same plugin name as
 * the onboarding sites, and one version field cannot describe both.
 */

global $wpdb;

$DRY = (bool) getenv( 'FF_CPT_DRY' );

$entries = array();

/* =========================================================================
 * 1. FF Carrier Context   (demo only, in development)
 * ====================================================================== */

$entries[] = array(
	'slug'     => 'ff-carrier-context',
	'title'    => 'FF Carrier Context',
	'version'  => 'v1.0.0',
	'updated'  => '09/04/2026',
	'status'   => 'In Development',
	'location' => 'Onboarding Sites',
	'repo'     => '',
	'summary'  => <<<'HTML'
<p>When a carrier finishes signing their agreement, the signature service sends them on to a thank-you page and everything about who they are is lost at the boundary. FF Passthrough Bridge already carries the carrier and their lane across that redirect; this is what lets a page built in Bricks actually show them &mdash; &ldquo;Thank you, Acme Transport, your Chicago to Dallas lane is confirmed&rdquo; &mdash; without a page cache ever serving one carrier&rsquo;s details to the next one. It renders empty placeholders on the server and fills them in the visitor&rsquo;s own browser, which is what makes it safe behind a cache. It was built for the demo site as the template for every future broker onboarding site, where the page has to carry the broker&rsquo;s own brand colours rather than the form plugin&rsquo;s styling.</p>
HTML
	,
	'how'      => <<<'HTML'
<p>The values never appear in the HTML the server sends. That is the whole design, and it is what makes the page reusable on a cached site without any per-site cache configuration.</p>
<ol>
<li><strong>The bridge carries the values.</strong> FF Passthrough Bridge stores the carrier and lane as the visitor crosses the signature redirect.</li>
<li><strong>Render a placeholder, not a value.</strong> Each shortcode outputs an empty tagged span plus a fallback word to show if nothing was carried.</li>
<li><strong>Fill it in the browser.</strong> A small script reads the carried values after the page loads and fills every placeholder on it.</li>
<li><strong>Fail visibly to an editor, quietly to a carrier.</strong> A mistyped key shows a red error to someone who can edit the page, and just the plain fallback to the carrier.</li>
</ol>
<p><strong>Example:</strong></p>
<p>A carrier signs their agreement and lands on the broker&rsquo;s branded confirmation page, which reads &ldquo;Thank you, Acme Transport &mdash; your Chicago, IL to Dallas, TX lane is confirmed.&rdquo; The next carrier through sees their own company and their own lane, even though both were served the same cached page.</p>
<p><strong>Technical reference</strong></p>
<ul>
<li><strong>Shortcodes:</strong> <code>[ff_carrier key="company_name" fallback="your company"]</code>, <code>[ff_carrier_name]</code>, <code>[ff_lane]</code>.</li>
<li><strong>Known keys</strong> (an allow-list, filterable via <code>ff_cc_known_keys</code>): company_name, company_dba_name, usdot, mc_number, o_city, o_state, d_city, d_state, equipment, carrier_id, broker_id. A key outside the list is refused rather than rendered empty.</li>
<li><strong>Why the value is not read in PHP:</strong> the onboarding sites run an nginx page cache, so echoing a value server-side would bake the first carrier&rsquo;s company name into the cached HTML and serve it to everyone after them. Filling in the browser is immune to that and needs no cache rules.</li>
<li>The script is printed on <code>wp_footer</code>, and only when a shortcode actually ran on the page.</li>
<li>🚨 <strong>Gravity Forms merge tags do not work for this &mdash; verified, do not retry.</strong> An HTML field containing <code>{Origin City:5}</code> renders the literal tag text to the carrier; prepopulated values are not substituted. Form 28 field 15 was already built that way and would have shipped raw tag syntax to carriers.</li>
<li>⚠️ <strong>Active on the demo site only.</strong> Not on the clone template, Frontline, Immense or WeTruckWithYou.</li>
<li>No repository &mdash; the plugin exists on the server only.</li>
</ul>
HTML
	,
	'faq'      => <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: Why not just print the carrier&rsquo;s name into the page?</em></p>
<p>A: These pages are cached. The first carrier&rsquo;s name would be stored in the cached copy and shown to every carrier after them.</p>
<p><em>Q: Why a Bricks page instead of the form&rsquo;s own confirmation?</em></p>
<p>A: The page has to carry the broker&rsquo;s brand colours, and it is the template every future broker site will reuse.</p>
<p><em>Q: Can we use form merge tags for this?</em></p>
<p>A: No. It was tried and verified: the tag renders as literal text to the carrier. That is the reason this plugin exists.</p>
<p><em>Q: What shows if nothing was carried across?</em></p>
<p>A: The fallback wording chosen on each shortcode, so the sentence still reads properly.</p>
<p><em>Q: Where is it running?</em></p>
<p>A: The demo site only, while the page template is being settled.</p>
<p>&nbsp;</p>
<p><strong>Operational value:</strong></p>
<p>The moment a carrier finishes signing is the one moment they are paying full attention, and a generic thank-you page wastes it. Naming the carrier and the lane they just committed to confirms the right thing happened, on a page that carries the broker&rsquo;s brand rather than ours. Solving it cache-safely once means every broker site we stand up gets it for free.</p>
HTML
	,
);

/* =========================================================================
 * 2. FF Workflow Inbox Guard   (freightforge.com)
 * ====================================================================== */

$entries[] = array(
	'slug'     => 'ff-workflow-inbox-guard',
	'title'    => 'FF Workflow Inbox Guard',
	'version'  => 'v1.1.0',
	'updated'  => '07/08/2026',
	'status'   => 'Active',
	'location' => 'Main Site',
	'repo'     => '',
	'summary'  => <<<'HTML'
<p>The Workflow Inbox on freightforge.com is where our two administrators review each self-serve carrier registration before it is allowed through to the Directory. The page was reachable by anyone who knew its address, signed in or not. This turns it into a genuine &ldquo;page not found&rdquo; for everyone who is not entitled to it, and keeps it out of search engines even for the people who are. It is one isolated file that touches no onboarding or plugin code.</p>
HTML
	,
	'how'      => <<<'HTML'
<p>It is a page-level gate placed in front of a review queue. Gravity Flow still decides what each reviewer may do once they are inside; this only decides whether they get to see the page at all.</p>
<ol>
<li><strong>Act on that one page and nothing else.</strong></li>
<li><strong>Let an authorised reviewer through,</strong> after which the workflow applies its own per-entry rules as normal.</li>
<li><strong>Give everyone else a real 404</strong> rather than a redirect or a login prompt, so an unauthorised visitor learns nothing about whether the page exists.</li>
<li><strong>Render the site&rsquo;s own 404 page,</strong> so it looks like any other missing page rather than an error.</li>
<li><strong>Keep it out of search results</strong> even when a permitted reviewer is looking at it.</li>
</ol>
<p><strong>Example:</strong></p>
<p>A logged-out visitor who has been forwarded the inbox link sees the site&rsquo;s ordinary &ldquo;page not found&rdquo;. An administrator visiting the same address sees the review queue exactly as before.</p>
<p><strong>Technical reference</strong></p>
<ul>
<li>Guards page <strong>44023</strong> (<code>/workflow-inbox</code>) on <code>template_redirect</code> at priority 0.</li>
<li>🚨 <strong>Capability trap, and the whole reason for v1.1.0:</strong> the administrator role on FF sites has <strong>no <code>gravityflow_*</code> capabilities at all</strong> &mdash; verified on the main site and on a working onboarding site. Inbox access actually runs through <strong><code>gform_full_access</code></strong>. Version 1.0.0 gated on <code>gravityflow_workflow</code> and locked out both real administrators, who saw a blank page. <strong>When gating any Gravity Flow inbox page, test <code>gform_full_access</code>, not the gravityflow capabilities.</strong></li>
<li>Sets a 404 status, marks the query as a 404 and disables caching, with <strong>no <code>exit</code></strong> &mdash; so WordPress&rsquo; template loader picks the theme&rsquo;s 404 template naturally.</li>
<li>Adds noindex and nofollow through <code>wp_robots</code>.</li>
<li><strong>Two-layer model:</strong> this is the page-level gate, Gravity Flow remains the per-entry gate. Neither replaces the other.</li>
<li>The inbox itself is a JavaScript grid, so purge the object cache and the page cache after any change or the old behaviour appears to persist.</li>
<li>Verified: both administrators allowed, logged-out visitors receive a 404.</li>
<li>No repository &mdash; a server-only must-use plugin on freightforge.com.</li>
</ul>
HTML
	,
	'faq'      => <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: What was exposed?</em></p>
<p>A: The page listing self-serve carrier registrations awaiting review was reachable by address, including by logged-out visitors.</p>
<p><em>Q: Why a 404 rather than a login prompt?</em></p>
<p>A: A login prompt confirms the page exists. A 404 tells an unauthorised visitor nothing at all.</p>
<p><em>Q: Does it change who can approve a registration?</em></p>
<p>A: No. Gravity Flow still governs what each reviewer may act on. This only controls who can load the page.</p>
<p><em>Q: Why did version 1.0.0 lock our own admins out?</em></p>
<p>A: It checked a Gravity Flow capability that the administrator role on our sites does not actually have. Access runs through a Gravity Forms capability instead.</p>
<p><em>Q: Is it in version control?</em></p>
<p>A: No. It exists on the server only, which is worth fixing before it is next edited.</p>
<p>&nbsp;</p>
<p><strong>Operational value:</strong></p>
<p>The review queue is the control point between a stranger&rsquo;s form submission and a record in the carrier Directory. Leaving it addressable made that control decorative. One isolated file closes it without touching a line of the onboarding stack, and the capability lesson it recorded applies to every workflow page we gate from here on.</p>
HTML
	,
);

/* =========================================================================
 * 3. FF Silence Novamira mcp-adapter Notices   (freightforge.com)
 * ====================================================================== */

$entries[] = array(
	'slug'     => 'ff-silence-mcp-notices',
	'title'    => 'FF Silence Novamira mcp-adapter Notices',
	'version'  => '',
	'updated'  => '04/26/2026',
	'status'   => 'Active',
	'location' => 'Main Site',
	'repo'     => '',
	'summary'  => <<<'HTML'
<p>Every single request to freightforge.com was writing the same three developer warnings into the error log, on every page load, caused by a third-party plugin registering the same thing twice inside itself. Nothing was broken and no visitor ever saw anything, but the log filled with thousands of identical lines a day, which is how a real error goes unnoticed. This silences exactly those three warnings and lets every other one through untouched. It is a workaround for a vendor bug reported on 26 April 2026, and it is meant to be deleted when the vendor fixes it.</p>
HTML
	,
	'how'      => <<<'HTML'
<p>It is deliberately the narrowest possible suppression: a warning has to match both the function that raised it and the subject of the message before it is dropped.</p>
<ol>
<li><strong>Match on two things at once,</strong> the specific registry function and a mention of the vendor component in the message text.</li>
<li><strong>Drop only those,</strong> and only from that component.</li>
<li><strong>Let everything else through,</strong> so a genuine warning from anywhere else in the site still reaches the log.</li>
<li><strong>Stay trivially removable</strong> &mdash; a single file, deleted in one step when the vendor ships a fix.</li>
</ol>
<p><strong>Example:</strong></p>
<p>Before, a single scheduled task added three identical warnings to the log, and an ordinary browsing session added hundreds. Now it adds none, while a real warning raised by any other plugin appears exactly as it would have.</p>
<p><strong>Technical reference</strong></p>
<ul>
<li>Filters <code>doing_it_wrong_trigger_error</code>, returning false only when the calling function is one of three ability-registry functions <strong>and</strong> the message mentions the vendor&rsquo;s <code>mcp-adapter</code> component.</li>
<li>The three suppressed warnings: an ability category already registered; an ability not found when unregistering; that same ability already registered.</li>
<li><strong>Cause is inside the third-party plugin</strong> &mdash; an explicit category registration that duplicates what its own bundled library already does, plus an unregister-then-register sequence that warns on every request. Not our code, and not fixable from our side without editing the vendor.</li>
<li>Verified by emptying the log and running a scheduled task: three warnings before, none after.</li>
<li><strong>Remove this file once the vendor ships a fix.</strong> Reported to them on 2026-04-26.</li>
<li>Carries no version header, so the version field is deliberately blank &mdash; the same as FF Gravity Flow Status Columns.</li>
<li>No repository &mdash; a server-only must-use plugin on freightforge.com.</li>
</ul>
HTML
	,
	'faq'      => <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: Was anything actually broken?</em></p>
<p>A: No. The warnings were noise, not failures. Nothing a visitor could see was affected.</p>
<p><em>Q: Then why bother?</em></p>
<p>A: Because a log full of thousands of identical harmless lines is where a real error hides. Silencing known noise is what keeps the log worth reading.</p>
<p><em>Q: Does it hide other problems?</em></p>
<p>A: No. It matches on both the specific function and the specific component. Any other warning is untouched.</p>
<p><em>Q: Is this permanent?</em></p>
<p>A: It should not be. It is a workaround for a reported vendor bug and should be deleted when they fix it.</p>
<p><em>Q: Why is the version blank?</em></p>
<p>A: The file has no version header. The field reflects that rather than inventing a number.</p>
<p>&nbsp;</p>
<p><strong>Operational value:</strong></p>
<p>An error log is only useful if somebody will read it, and nobody reads a log that gains thousands of identical lines a day. This restores the log as a place where a real problem stands out &mdash; which is the difference between finding an issue ourselves and hearing about it from a customer.</p>
HTML
	,
);

/* =========================================================================
 * 4. FF After-Login Router (Directory)   -- deliberate split from 28817
 * ====================================================================== */

$entries[] = array(
	'slug'     => 'ff-after-login-router-directory',
	'title'    => 'FF After-Login Router (Directory)',
	'version'  => 'v1.1.0',
	'updated'  => '07/31/2026',
	'status'   => 'Active',
	'location' => 'Directory',
	'repo'     => '',
	'summary'  => <<<'HTML'
<p>The Directory runs its own build of the after-login router, and it is <strong>not</strong> the one the onboarding sites run. Both decide where a user lands after signing in and both define functions of the same name, but they are different code with different behaviour &mdash; and their version numbers run in the opposite direction to their features. The Directory build is numbered 1.1.0 and the onboarding build 2.0.1, yet the Directory one is the newer of the two in what it does: it is the build that knows about paying Directory members, which is exactly why it had to diverge. This entry exists so that nobody &ldquo;updates&rdquo; one site with the other site&rsquo;s file.</p>
HTML
	,
	'how'      => <<<'HTML'
<p>Bricks sends every successful sign-in to one shared landing address. This decides, on the server, where that person actually belongs.</p>
<ol>
<li><strong>Catch the visitor at the shared landing address</strong> that the login form sends everyone to.</li>
<li><strong>Take the first role that matches,</strong> in a deliberate priority order, so a user holding two roles gets a predictable destination.</li>
<li><strong>Send a paying Directory member to the carrier search,</strong> not the home page. This is the part that only exists in this build, and it is not cosmetic: the front page is the old login page, so a member falling through to the default would be bounced straight back to a login form immediately after signing in.</li>
<li><strong>Accept any form of destination</strong> &mdash; a page ID, a slug, a path or a full address &mdash; so a destination can be changed without touching code.</li>
<li><strong>Stay extensible,</strong> with the whole role map exposed for another plugin to adjust.</li>
</ol>
<p><strong>Example:</strong></p>
<p>A broker signs in and lands on their dashboard. An administrator signing in lands in the WordPress admin. A paying Directory member signs in and lands on the carrier search, ready to work &mdash; where, without this build, they would have been returned to a login form and reasonably concluded their password had failed.</p>
<p><strong>Technical reference</strong></p>
<ul>
<li>Must-use plugin on <strong>production and the Directory sandbox</strong>, at <strong>1.1.0</strong>. Landing slug <code>after-login</code>.</li>
<li>Role map, in priority order: administrator to the WordPress admin; broker to page 11391 (<code>/car-admin/</code>); <strong><code>directory_member</code> to <code>/carriers/</code></strong>; anything else to the fallback.</li>
<li>The whole map is filterable through <code>ff_after_login_role_map</code>.</li>
<li>🚨 <strong>Two different plugins share this name.</strong> The onboarding-site build is documented separately at 2.0.1; it creates the <code>broker</code> role and knows nothing about <code>directory_member</code>. <strong>A higher version number here means the other codebase, not newer code.</strong> Copying either file over the other breaks the site it lands on.</li>
<li>The <code>directory_member</code> route is what makes the paid Directory usable at all &mdash; without it, a paying member is returned to a login form after every sign-in.</li>
<li>No separate repository. The repository <code>Sinovya/ff-after-login-router</code> holds the <strong>2.0.1 onboarding build</strong>, not this one, so this code exists on the servers only.</li>
</ul>
HTML
	,
	'faq'      => <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: Why are there two plugins with the same name?</em></p>
<p>A: They started as one and diverged. The Directory needed to route paying members to the carrier search; the onboarding sites needed to create and route the broker role. Neither change belonged on the other site.</p>
<p><em>Q: Which one is newer?</em></p>
<p>A: This one, despite its lower version number. The numbers were assigned independently on each side and are not comparable.</p>
<p><em>Q: Can I copy the 2.0.1 file onto the Directory to bring it up to date?</em></p>
<p>A: No. That removes the member routing and sends every paying customer back to a login form.</p>
<p><em>Q: Why does a member need special routing?</em></p>
<p>A: The Directory front page is the old login page. Without a member destination they land back on a login form immediately after signing in and assume it failed.</p>
<p><em>Q: Should these be merged one day?</em></p>
<p>A: Possibly, but only deliberately, with one file covering both role sets. Until then two entries is the honest description.</p>
<p>&nbsp;</p>
<p><strong>Operational value:</strong></p>
<p>Two plugins sharing a name, with version numbers pointing the wrong way, is exactly the shape of a mistake somebody makes once and cannot easily diagnose &mdash; the symptom would be paying customers unable to sign in, on the site where that costs the most. Writing the split down converts a trap into a known fact.</p>
HTML
	,
);

/* =========================================================================
 * Write
 * ====================================================================== */

WP_CLI::log( sprintf( '%d entries to process%s', count( $entries ), $DRY ? '   [DRY RUN]' : '' ) );
WP_CLI::log( str_repeat( '-', 78 ) );

$created = 0;
$updated = 0;
$problem = 0;

foreach ( $entries as $e ) {

	$existing = get_page_by_path( $e['slug'], OBJECT, 'plugin' );

	WP_CLI::log( '' );
	WP_CLI::log( ( $existing ? 'UPDATE #' . $existing->ID . '  ' : 'CREATE       ' ) . $e['slug'] );
	WP_CLI::log( sprintf(
		'  %s   %s   %s / %s',
		$e['version'] !== '' ? $e['version'] : '(blank version)',
		$e['updated'],
		$e['status'],
		$e['location']
	) );

	$all    = $e['summary'] . $e['how'] . $e['faq'];
	$faults = array();

	if ( substr_count( $all, '&amp;' ) > 0 ) {
		$faults[] = 'double-encoded ampersands';
	}
	if ( substr_count( $all, chr( 92 ) . 'n' ) > 0 ) {
		$faults[] = 'literal backslash-n instead of real newlines';
	}
	foreach ( array( 'p', 'ul', 'ol', 'li' ) as $tag ) {
		$open  = substr_count( $all, '<' . $tag . '>' );
		$close = substr_count( $all, '</' . $tag . '>' );
		if ( $open !== $close ) {
			$faults[] = sprintf( '%s tags unbalanced (%d open, %d close)', $tag, $open, $close );
		}
	}

	if ( $faults ) {
		$problem++;
		WP_CLI::warning( '  ' . implode( '; ', $faults ) );
		WP_CLI::log( '  SKIPPED - fix the prose and re-run.' );
		continue;
	}

	WP_CLI::log( sprintf(
		'  summary %d B   how_it_works %d B   faq %d B   checks ok',
		strlen( $e['summary'] ), strlen( $e['how'] ), strlen( $e['faq'] )
	) );

	if ( $DRY ) {
		continue;
	}

	if ( $existing ) {
		$post_id = $existing->ID;
		$updated++;
	} else {
		$post_id = wp_insert_post( array(
			'post_type'   => 'plugin',
			'post_status' => 'publish',
			'post_title'  => $e['title'],
			'post_name'   => $e['slug'],
			'post_author' => 1,
		), true );

		if ( is_wp_error( $post_id ) ) {
			WP_CLI::warning( '  insert failed: ' . $post_id->get_error_message() );
			$problem++;
			continue;
		}
		$created++;
	}

	$wpdb->replace( 'plugins', array(
		'ID'                     => $post_id,
		'plugin_version'         => $e['version'],
		'last_updated'           => $e['updated'],
		'status'                 => $e['status'],
		'location'               => $e['location'],
		'summary'                => $e['summary'],
		'how_it_works_example_s' => $e['how'],
		'faq_marketing'          => $e['faq'],
		'github_link'            => $e['repo'] === '' ? '' : serialize( array(
			'url'     => $e['repo'],
			'title'   => $e['repo'],
			'target'  => '',
			'post_id' => 0,
		) ),
	) );

	clean_post_cache( $post_id );

	// Verify from the table, not rwmb_meta() - Meta Box caches the row for the
	// life of the request, so a read-back through it can report a stale value.
	$fresh = $wpdb->get_row(
		$wpdb->prepare( 'SELECT plugin_version, summary FROM plugins WHERE ID = %d', $id = $post_id ),
		ARRAY_A
	);

	WP_CLI::log( sprintf(
		'  verified: version "%s", summary %d B  -  %s',
		$fresh['plugin_version'],
		strlen( (string) $fresh['summary'] ),
		admin_url( 'post.php?post=' . $post_id . '&action=edit' )
	) );
}

if ( ! $DRY ) {
	wp_cache_flush();
}

$total = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'plugin' AND post_status = 'publish'"
);

WP_CLI::log( '' );
WP_CLI::log( str_repeat( '-', 78 ) );
WP_CLI::log( sprintf( 'created %d   updated %d   skipped %d', $created, $updated, $problem ) );
WP_CLI::log( 'published entries in the CPT now: ' . $total );

if ( $problem ) {
	WP_CLI::warning( 'finished with problems - see above.' );
} else {
	WP_CLI::success( $DRY ? 'dry run clean.' : 'done.' );
}

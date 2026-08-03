<?php
/**
 * Create/update the "Plugins" CPT documentation entry for FF Directory Gate.
 *
 * Storage is the Meta Box custom table `plugins` (no dlrh_ prefix), keyed by
 * post ID. Idempotent via get_page_by_path().
 *
 * Run: wp eval-file create-plugins-cpt-entry.php [dry]
 */

global $wpdb;

$DRY   = in_array( 'dry', (array) ( $args ?? array() ), true );
$SLUG  = 'ff-directory-gate';
$TITLE = 'FF Directory Gate';
$REPO  = 'https://github.com/Sinovya/ff-directory-gate';

$summary = <<<'HTML'
<p>FF Directory Gate turns the carrier directory into a paid product. Visitors who have not bought access still see a useful <strong>teaser</strong> &mdash; how many carriers match a lane, which zones and equipment they cover, their authority status and a banded fleet size &mdash; so a prospect can prove to themselves that the coverage exists on their corridor before they ever talk to sales. What they cannot see is who those carriers are: names, USDOT and MC numbers, addresses, contact details, logos and profile links are stripped out of the page entirely, not merely hidden with styling. Paying customers sign in and see the whole record. The plugin also knows the difference between a stranger and a lapsed customer, so someone whose subscription ran out is offered a way to renew rather than being asked to buy something they already had.</p>
HTML;

$how = <<<'HTML'
<p>Every gated surface asks the same question of one function, so there is a single place where access is decided and no way for one page to disagree with another. A master switch turns the entire gate on or off without touching code, which is what made it safe to deploy to a live site.</p>
<ol>
<li><strong>Work out who the visitor is.</strong> Four outcomes: a member in good standing, a customer inside their grace period after a missed payment, a lapsed customer, or someone who never bought anything. Administrators always count as members so the team is never locked out of its own data.</li>
<li><strong>Remove the identifying data before the page is built.</strong> Redaction happens where the values are resolved, so the sensitive fields never reach the HTML. It works from an <strong>allow-list</strong> &mdash; only zone, equipment, carrier operation, authority status and a banded power-unit range are public; everything else is withheld by default.</li>
<li><strong>Close every door, not just the obvious one.</strong> The search results, individual carrier and lane pages, the REST API, the RSS feeds, the search filters that would reveal a USDOT number, and the links whose web addresses are built from carrier names.</li>
<li><strong>Say the right thing to the right person.</strong> A stranger is invited to request access. A customer in grace keeps working but is warned how many days remain. A lapsed customer is offered a renewal. After a year without renewing they are treated as a new prospect again.</li>
<li><strong>Provision and renew.</strong> Each customer has a paid-through date, a grace period chosen per customer, and a billing cycle recorded for reference. These are edited on the customer&rsquo;s user profile or from the command line.</li>
</ol>
<p><strong>Example:</strong></p>
<p>A freight broker searches for reefer capacity out of zone 5 without signing in. They see that 34 carriers match, what equipment those carriers run and roughly how big each fleet is &mdash; but no company names, no USDOT numbers and no phone numbers, and each result carries a &ldquo;Request access&rdquo; button. They buy a subscription, are given a login, and the same search now shows every carrier in full. Months later a payment fails: for the next 14 days they keep working normally but see a banner saying access continues for a limited time. If it lapses, the results return to the teaser, but the message now reads &ldquo;Your access ended on&hellip;&rdquo; with a renewal link rather than a sales pitch.</p>
<p><strong>Technical reference</strong></p>
<ul>
<li><strong>Master switch:</strong> option <code>ff_dir_gate_mode</code> (<code>off</code> by default, or <code>enforce</code>). Setting it back to <code>off</code> instantly restores the fully public site, including the teaser layout &mdash; it is the kill switch.</li>
<li><strong>Single decision function:</strong> <code>ff_dir_user_can( $feature )</code> for the doors <code>search</code>, <code>contacts</code> and <code>export</code>. Filter <code>ff_dir_user_can</code> is the seam for a future billing-driven entitlement record.</li>
<li><strong>States:</strong> <code>ff_dir_access_state()</code> returns <code>full</code>, <code>grace</code>, <code>expired</code> or <code>none</code>. Hard cutoff is <code>ff_dir_paid_through</code> plus <code>ff_dir_grace_days</code>; grace deliberately does not count as expired.</li>
<li><strong>Redaction:</strong> hooks <code>bricks/dynamic_data/format_value</code> for Meta Box and core fields, plus a record-then-blank pair on <code>bricks/dynamic_data/render_tag</code> for tags owned by other plugins &mdash; the carrier logo is rendered by FF Carrier Logos and would otherwise have leaked.</li>
<li><strong>Gated surfaces:</strong> archive loop, carrier/lane singles (302, never 301), WP REST via <code>rest_endpoints</code> and <code>permission_callback</code>, feeds, identity facets via <code>facetwp_facets</code>, and <code>post_type_link</code>.</li>
<li><strong>Shortcodes:</strong> <code>[ff_dir_account_bar]</code>, <code>[ff_dir_account_notice]</code>, <code>[ff_dir_locked]</code>, <code>[ff_dir_gate]</code>, <code>[ff_dir_member_only]</code>, <code>[ff_dir_guest_only]</code>, <code>[ff_dir_units]</code>.</li>
<li><strong>Bricks tags:</strong> <code>{ff_dir_access_state}</code>, <code>{ff_dir_can_search}</code>, <code>{ff_dir_is_grace}</code>, <code>{ff_dir_grace_days_left}</code>, <code>{ff_dir_expiry_date}</code>, <code>{ff_dir_locked_message}</code>, <code>{ff_dir_cta_label}</code>, <code>{ff_dir_cta_url}</code> and others.</li>
<li><strong>Admin:</strong> Users &rarr; Edit User &rarr; &ldquo;Directory access&rdquo; (state, paid-through, grace dropdown, billing cycle).</li>
<li><strong>WP-CLI:</strong> <code>wp ff-dir grant|check|revoke</code>. Revoke is for genuine offboarding only &mdash; it removes the entitlement, so a non-paying customer would be treated as a stranger instead of being offered a renewal.</li>
<li><strong>Companion MU-plugins</strong> in the same repository: Directory entry routing, a guard for an unauthenticated Bricksforge test-mail endpoint, and a repair for a malformed <code>From:</code> header that truncated the sender name.</li>
</ul>
HTML;

$faq = <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: What does it do?</em></p>
<p>A: It makes the carrier directory a paid product &mdash; a teaser for everyone else, the full record for customers.</p>
<p><em>Q: What can someone see without paying?</em></p>
<p>A: Result counts, origin and destination zones, equipment types, carrier operation, authority status and a banded fleet size. Not the carrier&rsquo;s name, USDOT or MC number, address, contacts, logo or profile link.</p>
<p><em>Q: Is the hidden data really gone, or just hidden?</em></p>
<p>A: Really gone. The values are removed before the page is assembled, so they are not in the source and cannot be recovered from the browser.</p>
<p><em>Q: Do carriers pay?</em></p>
<p>A: No. Carriers list free, permanently. Only the people searching for carriers pay.</p>
<p><em>Q: What happens when a customer misses a payment?</em></p>
<p>A: They keep full access for a grace period set per customer, with a banner showing how many days remain. After that the data locks and they are offered a renewal. A year later they are treated as a new prospect again.</p>
<p><em>Q: How do we turn it off in a hurry?</em></p>
<p>A: One setting. Switching the mode back to off restores the fully public site immediately, with no code change or deployment.</p>
<p><em>Q: Does it lock our own team out?</em></p>
<p>A: No. Administrators always see the full data.</p>
<p>&nbsp;</p>
<p><strong>Marketing:</strong></p>
<p>The teaser is the sales asset. A broker can confirm that real capacity exists on the corridor they care about &mdash; how many carriers, what equipment, roughly what size &mdash; before speaking to anyone, which is a far stronger argument than a claim on a marketing page. What they cannot do is act on it without an account, and that is the whole product: the value is not the count, it is knowing which carriers those are and how to reach them.</p>
HTML;

$data = array(
	'plugin_version'         => 'v0.8.1',
	'last_updated'           => gmdate( 'm/d/Y' ),
	'status'                 => 'Active',
	'location'               => 'Directory',
	'summary'                => $summary,
	'how_it_works_example_s' => $how,
	'faq_marketing'          => $faq,
	'github_link'            => serialize( array(
		'url'     => $REPO,
		'title'   => $REPO,
		'target'  => '',
		'post_id' => 0,
	) ),
);

$existing = get_page_by_path( $SLUG, OBJECT, 'plugin' );

WP_CLI::log( $existing ? "updating existing entry #{$existing->ID}" : 'creating a new entry' );
WP_CLI::log( '  version: ' . $data['plugin_version'] . '   status: ' . $data['status'] . '   location: ' . $data['location'] );
WP_CLI::log( '  summary: ' . strlen( $summary ) . ' bytes, how_it_works: ' . strlen( $how ) . ', faq: ' . strlen( $faq ) );

if ( $DRY ) {
	WP_CLI::success( 'DRY RUN - nothing written.' );
	return;
}

if ( $existing ) {
	$post_id = $existing->ID;
} else {
	$post_id = wp_insert_post( array(
		'post_type'   => 'plugin',
		'post_status' => 'publish',
		'post_title'  => $TITLE,
		'post_name'   => $SLUG,
		'post_author' => 1,
	), true );

	if ( is_wp_error( $post_id ) ) {
		WP_CLI::error( 'insert failed: ' . $post_id->get_error_message() );
	}
}

$data['ID'] = $post_id;

$wpdb->replace( 'plugins', $data );

clean_post_cache( $post_id );
wp_cache_flush();

// Verify through the same path the admin screen reads.
$check = array();
foreach ( array( 'plugin_version', 'status', 'location', 'summary', 'github_link' ) as $f ) {
	$v          = rwmb_meta( $f, '', $post_id );
	$check[ $f ] = is_array( $v ) ? wp_json_encode( $v ) : mb_substr( (string) $v, 0, 60 );
}

WP_CLI::log( '' );
WP_CLI::log( 'verified via rwmb_meta():' );
foreach ( $check as $k => $v ) {
	WP_CLI::log( '  ' . str_pad( $k, 16 ) . $v );
}

WP_CLI::success( 'entry #' . $post_id . ' ready: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );

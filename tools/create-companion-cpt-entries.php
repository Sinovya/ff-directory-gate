<?php
/**
 * Create/update Plugins CPT entries for the three companion MU-plugins that
 * ship in the ff-directory-gate repository under mu-companions/.
 *
 * Idempotent via get_page_by_path(). Run: wp eval-file create-companion-cpt-entries.php [dry]
 */

global $wpdb;

$DRY  = in_array( 'dry', (array) ( $args ?? array() ), true );
$REPO = 'https://github.com/Sinovya/ff-directory-gate';

$entries = array();

/* ------------------------------------------------------------------ 1 */
$entries['ff-directory-entry-routing'] = array(
	'title'   => 'FF Directory Entry Routing',
	'version' => 'v1.1.0',
	'summary' => <<<'HTML'
<p>FF Directory Entry Routing decides where someone lands when they arrive at the Directory without a specific destination. Typing the bare address now takes a visitor straight to carrier search rather than to a page that no longer served a purpose, and the three superseded sign-in pages redirect to their current replacements so old bookmarks, saved links and password-reset emails already sitting in inboxes all still work. It is a small plugin whose whole job is that nobody reaches a dead end.</p>
HTML,
	'how'     => <<<'HTML'
<p>The Directory was rebuilt around new sign-in, lost-password and password-reset pages, which left the old ones stranded and the front page pointing at a login form that had been replaced. This plugin closes both gaps in one place.</p>
<ol>
<li><strong>Send the front door somewhere useful.</strong> A visit to the bare web address is forwarded to carrier search. A customer sees their search; everyone else sees the teaser, which is the sales pitch &mdash; so one destination serves both audiences.</li>
<li><strong>Forward the retired pages.</strong> The three superseded sign-in pages redirect permanently to their current equivalents.</li>
<li><strong>Keep the query string intact.</strong> Password-reset links carry a one-time key. Dropping it would turn every reset email already in an inbox into an error, so the redirect carries it across.</li>
<li><strong>Stay on its own site.</strong> Several FreightForge sites share plugin file names, so this one checks which site it is running on and does nothing anywhere except the Directory.</li>
</ol>
<p><strong>Example:</strong></p>
<p>A customer bookmarked the old sign-in page months ago. They click it, land on the current sign-in page, and never learn that anything moved. Separately, a colleague types just the domain name into their browser and arrives directly at carrier search instead of a stale login screen.</p>
<p><strong>Technical reference</strong></p>
<ul>
<li>Runs on <code>template_redirect</code> at priority 0, ahead of WordPress&rsquo;s own canonical redirect &mdash; which is what makes it possible to intercept <code>/login/</code> at all, since that page is currently the site&rsquo;s front page.</li>
<li>Front door is a <strong>302</strong>, not a 301: the destination is still an open question and a permanent redirect would be cached in browsers with nothing to clear. The legacy page redirects are 301.</li>
<li>Only the true root is redirected. <code>is_front_page()</code> is also true for the old <code>/login/</code> path, so the front-door rule is gated on an empty request path or it would swallow that redirect too.</li>
<li>Host-guarded to <code>directory.freightforge.com</code>; a copy on another site is a no-op.</li>
<li>Filters: <code>ff_dir_front_door_target</code>, <code>ff_legacy_auth_redirect_map</code>.</li>
</ul>
HTML,
	'faq'     => <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: What does it do?</em></p>
<p>A: Sends the bare web address to carrier search, and forwards the three retired sign-in pages to their current replacements.</p>
<p><em>Q: Will old password-reset emails still work?</em></p>
<p>A: Yes. The one-time key in the link is carried through the redirect.</p>
<p><em>Q: Could it affect the other FreightForge sites?</em></p>
<p>A: No. It checks which site it is on and does nothing anywhere but the Directory.</p>
<p><em>Q: How do we change where the front door points?</em></p>
<p>A: One filter, no page edits.</p>
<p>&nbsp;</p>
<p><strong>Operational value:</strong></p>
<p>Every dead end costs a support email or a lost sign-in. This removes the two most likely ones &mdash; someone typing the domain from memory, and someone clicking a link they saved before the rebuild.</p>
HTML,
);

/* ------------------------------------------------------------------ 2 */
$entries['ff-bricksforge-ajax-guard'] = array(
	'title'   => 'FF Bricksforge AJAX Guard',
	'version' => 'v1.0.0',
	'summary' => <<<'HTML'
<p>FF Bricksforge AJAX Guard closes a hole in a third-party plugin. Bricksforge&rsquo;s email designer exposes a &ldquo;send a test email&rdquo; action that anyone on the internet can trigger, with the recipient address supplied by the caller and no sign-in, permission or security-token check of any kind. The message body is fixed, so it is not an open spam relay &mdash; but it is mail leaving the FreightForge domain at a stranger&rsquo;s request, which is a sender-reputation problem and trivially automated. This plugin requires an authenticated administrator instead, without touching the vendor&rsquo;s files.</p>
HTML,
	'how'     => <<<'HTML'
<p>Bricksforge registers its test-mail action twice &mdash; once for signed-in users and once for the general public &mdash; and the handler performs no permission or token check before sending. The guard intercepts the same action first.</p>
<ol>
<li><strong>Intercept before the vendor handler.</strong> It attaches to the same action at an earlier priority, so it runs first.</li>
<li><strong>Require an administrator.</strong> Anyone else is refused with a &ldquo;not allowed&rdquo; response and the attempt is written to the error log with the requesting address.</li>
<li><strong>Let real admins through untouched.</strong> The Send Test button in the email designer continues to work exactly as before.</li>
<li><strong>Leave everything else alone.</strong> Bricksforge&rsquo;s other public actions were checked individually and are properly protected, so they are deliberately not touched.</li>
</ol>
<p><strong>Example:</strong></p>
<p>An automated script posts to the site asking it to send a test email to an address of the attacker&rsquo;s choosing. Before: the site sent it. Now: the request is refused and logged, while an administrator clicking Send Test in the designer sees no difference.</p>
<p><strong>Technical reference</strong></p>
<ul>
<li>Guards <code>brf_email_designer_test_mail</code> on both the authenticated and unauthenticated AJAX hooks, at priority 1 &mdash; ahead of Bricksforge&rsquo;s own handler at the default priority.</li>
<li>Requires a logged-in user with <code>manage_options</code>; otherwise returns a 403 JSON error.</li>
<li><strong>Deliberately does not require a security token.</strong> Bricksforge&rsquo;s own admin interface does not send one, so demanding it would break the feature for legitimate administrators. An authenticated-administrator check is the strongest test that keeps the button working.</li>
<li>Verified: an anonymous request is refused with no mail sent and a log entry written; an administrator passes through.</li>
<li>Checked and intentionally left alone: <code>bricksforge_update_option</code> and <code>bricksforge_delete_option</code> verify both a token and capabilities, and <code>bricksforge_send_mail</code> requires <code>edit_posts</code>. Guarding those would risk breaking front-end forms for no benefit.</li>
<li>No vendor files are modified, so it survives Bricksforge updates.</li>
</ul>
HTML,
	'faq'     => <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: What was the risk?</em></p>
<p>A: Anyone could make the site send an email to an address of their choosing, without signing in. The content was fixed, so it is a sender-reputation problem rather than a data breach.</p>
<p><em>Q: Was anything actually exploited?</em></p>
<p>A: No evidence of it. The guard was added as soon as the gap was found.</p>
<p><em>Q: Does it break the email designer?</em></p>
<p>A: No. Administrators use Send Test exactly as before.</p>
<p><em>Q: Will a Bricksforge update undo it?</em></p>
<p>A: No. It sits alongside the plugin rather than editing it.</p>
<p><em>Q: Why not require a security token as well?</em></p>
<p>A: Bricksforge&rsquo;s own interface does not send one, so requiring it would break the button for real administrators too.</p>
<p>&nbsp;</p>
<p><strong>Operational value:</strong></p>
<p>Mail sent from our domain at a stranger&rsquo;s request damages deliverability for every message we send &mdash; including customer welcome and password-reset emails. Closing it protects the sending reputation the rest of the platform depends on.</p>
HTML,
);

/* ------------------------------------------------------------------ 3 */
$entries['ff-email-from-header-fix'] = array(
	'title'   => 'FF Email From-Header Fix',
	'version' => 'v1.0.0',
	'summary' => <<<'HTML'
<p>FF Email From-Header Fix repairs a formatting bug in a third-party plugin that was quietly clipping the company name off every branded email. Bricksforge assembled the sender line without a space before the address, so mail software removed one character where the space should have been and customers received messages from &ldquo;FreightForg&rdquo;. The setting was correct all along; the fault was in how the header was written. This plugin repairs the header on its way out.</p>
HTML,
	'how'     => <<<'HTML'
<p>A sender line is conventionally written as a display name, a space, then the address in angle brackets. Bricksforge omitted the space, and software that assumes it is there removes a character in its place &mdash; which is the last letter of the company name.</p>
<ol>
<li><strong>Inspect outbound mail.</strong> It examines each message&rsquo;s headers as it is sent, after Bricksforge has finished assembling them.</li>
<li><strong>Repair only the sender line.</strong> Where the name runs straight into the address, the missing space is inserted. Other headers are untouched.</li>
<li><strong>Leave correct headers alone.</strong> A properly formed sender line passes through unchanged, so the fix is safe to leave in place permanently.</li>
</ol>
<p><strong>Example:</strong></p>
<p>The welcome email a new customer receives previously arrived from &ldquo;FreightForg&rdquo;. It now arrives from &ldquo;FreightForge&rdquo;. Nothing else about the message changed.</p>
<p><strong>Technical reference</strong></p>
<ul>
<li>Filters <code>wp_mail</code> at priority 10000 &mdash; after Bricksforge&rsquo;s own final pass, so the repair is applied to the finished header.</li>
<li>Only lines beginning <code>From:</code> are considered. Handles both the array and multi-line string header formats, and quoted display names.</li>
<li>Idempotent: a well-formed header is returned unchanged.</li>
<li>Root cause is in the vendor&rsquo;s <code>get_from()</code>, which concatenates the name and address with no separator. Reply-To was unaffected because it is built separately &mdash; which is why the two disagreed.</li>
<li>No vendor files are modified, so it survives Bricksforge updates.</li>
</ul>
HTML,
	'faq'     => <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: What was wrong?</em></p>
<p>A: Customer emails arrived from &ldquo;FreightForg&rdquo; &mdash; the company name with its last letter missing.</p>
<p><em>Q: Was it a setting we had entered incorrectly?</em></p>
<p>A: No. The configured name was correct; the plugin assembled the sender line incorrectly.</p>
<p><em>Q: Which emails were affected?</em></p>
<p>A: Any sent through a Bricksforge email template, including the customer welcome and password-reset messages.</p>
<p><em>Q: Will a Bricksforge update undo it?</em></p>
<p>A: No, and if they fix it upstream this becomes harmless &mdash; a correct header is left untouched.</p>
<p>&nbsp;</p>
<p><strong>Operational value:</strong></p>
<p>The sender name is the first thing a customer reads, before the subject line. A misspelled company name in that position undermines trust in exactly the messages that matter most &mdash; the welcome and the password reset &mdash; and looks like a phishing attempt rather than a typo.</p>
HTML,
);

/* ------------------------------------------------------------------ run */
foreach ( $entries as $slug => $e ) {

	$existing = get_page_by_path( $slug, OBJECT, 'plugin' );

	WP_CLI::log( ( $existing ? 'update #' . $existing->ID : 'create' ) . '  ' . $e['title'] . '  ' . $e['version'] );

	if ( $DRY ) {
		continue;
	}

	if ( $existing ) {
		$post_id = $existing->ID;
	} else {
		$post_id = wp_insert_post( array(
			'post_type'   => 'plugin',
			'post_status' => 'publish',
			'post_title'  => $e['title'],
			'post_name'   => $slug,
			'post_author' => 1,
		), true );

		if ( is_wp_error( $post_id ) ) {
			WP_CLI::error( 'insert failed for ' . $slug . ': ' . $post_id->get_error_message() );
		}
	}

	$wpdb->replace( 'plugins', array(
		'ID'                     => $post_id,
		'plugin_version'         => $e['version'],
		'last_updated'           => gmdate( 'm/d/Y' ),
		'status'                 => 'Active',
		'location'               => 'Directory',
		'summary'                => $e['summary'],
		'how_it_works_example_s' => $e['how'],
		'faq_marketing'          => $e['faq'],
		'github_link'            => serialize( array(
			'url'     => $REPO,
			'title'   => $REPO,
			'target'  => '',
			'post_id' => 0,
		) ),
	) );

	clean_post_cache( $post_id );

	WP_CLI::log( '   -> #' . $post_id . '  ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
}

if ( $DRY ) {
	WP_CLI::success( 'DRY RUN - nothing written.' );
	return;
}

wp_cache_flush();

// Verify through the same path the admin screen reads.
WP_CLI::log( '' );
foreach ( array_keys( $entries ) as $slug ) {
	$p = get_page_by_path( $slug, OBJECT, 'plugin' );
	WP_CLI::log( sprintf(
		'  %-32s v=%-8s status=%-7s summary=%d bytes',
		$slug,
		rwmb_meta( 'plugin_version', '', $p->ID ),
		rwmb_meta( 'status', '', $p->ID ),
		strlen( (string) rwmb_meta( 'summary', '', $p->ID ) )
	) );
}

WP_CLI::success( 'companion entries ready.' );

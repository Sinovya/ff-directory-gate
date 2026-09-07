<?php
/**
 * Plugins CPT sweep for 2026-09-07: one new entry, one version update.
 *
 * Run:      wp eval-file cpt-sep07-2026.php
 * Dry run:  FF_CPT_DRY=1 wp eval-file cpt-sep07-2026.php
 *
 * NEW  ff-provisioning   the setup screen that stands a new customer up
 * EDIT 28821 ff-ip-map   v1.4.2 -> v1.5.0, capture on more than one form
 *
 * Follows the September sweep's shape: idempotent, dry run gated on an env
 * var because wp eval-file rejects unknown flags, prose sanity-checked before
 * anything is written, and verified by reading the table directly rather than
 * through rwmb_meta(), which caches the row for the life of the request and
 * would report a stale value on a write that actually succeeded.
 */

global $wpdb;

$DRY = (bool) getenv( 'FF_CPT_DRY' );

$entries = array();

/* =========================================================================
 * 1. FF Provisioning   (NEW)
 * ====================================================================== */

$entries[] = array(
	'slug'     => 'ff-provisioning',
	'title'    => 'FF Provisioning',
	'version'  => 'v0.3.0',
	'updated'  => '09/07/2026',
	'status'   => 'Active',
	'location' => 'Onboarding Sites',
	'repo'     => 'https://github.com/Sinovya/ff-provisioning',
	'summary'  => <<<'HTML'
<p>Standing up a new carrier onboarding customer used to mean setting ten options across three plugins and two admin screens, activating a fourth, editing thirteen email notifications across four forms, and remembering that the Directory address, key and secret only work as a matched set. Nothing checked any of it, and every way of getting it wrong failed silently &mdash; a mismatched key sends carriers quietly through the long registration form; a missing form number means the short-form invitation is never sent; a notification left unedited emails carriers as &ldquo;(Broker Name)&rdquo;. This turns all of it into one screen: name the customer, say how they came to us, tick what they have bought, and press Provision.</p>
<p>The checkbox is the smaller half. The screen then verifies its own work and reports pass or fail on each item, because the failures worth catching are the ones that leave no trace until a carrier says they never received an email.</p>
HTML
	,
	'how'      => <<<'HTML'
<p>Everything is on one page, in the order you would naturally fill it in.</p>
<ol>
<li><strong>The customer.</strong> Their name and their email address. The name replaces the &ldquo;(Broker Name)&rdquo; placeholder everywhere it appears &mdash; form titles, email subjects, the sender name a carrier sees. The email becomes the address completed registrations are sent to, and is added to the copy line on anything chasing an insurance certificate.</li>
<li><strong>How they were sourced.</strong> Direct, referred by a TMS partner, or arriving through a TMS integration. This sets what is included: an integration customer gets Express Registration and IP Geolocation as part of the package, while the other two channels buy them as add-ons. Ticking a tier ticks what it includes, but never locks it &mdash; a customer can always have something switched off.</li>
<li><strong>Services.</strong> Express Registration, IP Geolocation, and the TMS inbound connection.</li>
<li><strong>Directory.</strong> Sandbox or production, with the key and secret issued for this customer.</li>
<li><strong>Forms.</strong> Which form is the contract request, which is the express registration, which forms capture location, and which report their approvals back to a TMS partner.</li>
</ol>
<p><strong>Example.</strong> A broker arrives through a TMS partnership. You type their name and address, choose &ldquo;TMS integration partner&rdquo;, and all three services tick themselves. You paste the credentials, pick the forms, and press Provision. The screen reports what it changed &mdash; ten options, one plugin activated, four forms edited &mdash; then runs nine checks and confirms, among other things, that it successfully authenticated against the Directory with the credentials you just pasted.</p>
<p>FreightForge stays where it belongs. Chris continues to receive the admin notification when a contract request is raised, copied to David, and neither is moved onto the customer. The shared sending address is left alone.</p>
HTML
	,
	'faq'      => <<<'HTML'
<p><strong>Does it create the customer&rsquo;s API credentials?</strong> No, and deliberately. Creating an API client on the Directory from a broker site would mean every one of those sites held a credential capable of creating Directory clients &mdash; on sites the customer has administrator access to. The client and broker record are still created centrally, and the credentials pasted in here.</p>
<p><strong>Is it safe to run twice?</strong> Yes. A second run reports every value as unchanged and writes nothing. Values that were already correct are reported as such rather than counted as work, so a re-run cannot look busier than it was.</p>
<p><strong>What if a form is missing something?</strong> It says so instead of proceeding quietly. A form without the location-capture fields cannot be ticked for IP Geolocation, and the reason is shown next to it.</p>
<p><strong>Why does it verify rather than just confirm it saved?</strong> Because saving is not the thing that goes wrong. The credential check makes a real signed call to the Directory and reports who it authenticated as &mdash; reading the setting back would only prove the setting could be written.</p>
HTML
	,
);

/* =========================================================================
 * 2. FF IP Map   (UPDATE 28821 - v1.4.2 -> v1.5.0)
 *
 * Version reflects the clone template, which is what every new site
 * inherits. The existing live sites are still on 1.4.2, and the prose says
 * so - the same one-entry-with-the-split-spelled-out approach used for
 * ff-network-lookup rather than a second entry.
 * ====================================================================== */

$entries[] = array(
	'slug'     => 'ff-ip-map',
	'title'    => 'FF IP Map',
	'version'  => 'v1.5.0',
	'updated'  => '09/07/2026',
	'status'   => 'Active',
	'location' => 'Onboarding Sites',
	'repo'     => 'https://github.com/Sinovya/ff-ip-map',
	'summary'  => <<<'HTML'
<p>Records where a carrier actually was when they filled in their registration &mdash; their network address, the city and country it resolves to, and, if they allow it, their device&rsquo;s own location. A broker reviewing a submission can see whether the company claiming to be a Chicago carrier filled the form in from Chicago. It is fraud tooling: carrier identity theft works by impersonating a real, well-established carrier, so the evidence matters most on the records that look most legitimate.</p>
<p>It is a paid add-on, charged per customer site, and included as standard for customers arriving through a TMS integration. Carriers are told what is being collected, in a notice above the submit button.</p>
HTML
	,
	'how'      => <<<'HTML'
<p><strong>New in v1.5.0: it can watch more than one form.</strong> Capture used to be tied to a single registration form. That was fine while every site had one, but Express Registration is a different form with its own internal numbering, so a customer with both got evidence from one and nothing from the other &mdash; and the express form is the quickest route into a broker&rsquo;s network, which makes it the one most worth watching.</p>
<ol>
<li>Each form it watches carries its own set of hidden fields, because the same field has a different number on every form. The plugin now stores one mapping per form rather than one for the site.</li>
<li>Adding a form to the list is enough: FF Provisioning finds the right fields on it by their labels, so there is no numbering to copy by hand.</li>
<li>A form that does not carry the capture fields cannot be added, and says why.</li>
</ol>
<p><strong>Example.</strong> A customer buys the add-on and also sells express onboarding. Both their registration forms are ticked. A carrier who takes the short route is now recorded exactly as one who takes the long route, and a broker comparing the two sees the same evidence on both.</p>
<p><strong>Switching it off.</strong> Removing every form switches capture off completely &mdash; no notice on any form, no lookup on any submission. That has been reliably true since v1.4.2.</p>
HTML
	,
	'faq'      => <<<'HTML'
<p><strong>Which sites are on v1.5.0?</strong> The clone template and the demo site, so every site created from now on has it. Existing customer sites continue on v1.4.2, which behaves identically for a single form; the newer version was written so that an existing configuration keeps working untouched.</p>
<p><strong>Does adding a second form change the first?</strong> No. The original form keeps its own settings exactly as they were.</p>
<p><strong>What is the carrier told?</strong> A notice above the submit button states that the broker collects their network address and approximate location for verification and fraud prevention. Device location is only ever collected if the carrier&rsquo;s browser asks them and they agree.</p>
HTML
	,
);

/* ---------------------------------------------------------------------- */

$created = 0;
$updated = 0;
$problem = 0;

foreach ( $entries as $e ) {

	$existing = get_page_by_path( $e['slug'], OBJECT, 'plugin' );

	WP_CLI::log( '' );
	WP_CLI::log( ( $existing ? 'UPDATE #' . $existing->ID . '  ' : 'CREATE       ' ) . $e['slug'] );
	WP_CLI::log( sprintf( '  %s   %s   %s / %s', $e['version'], $e['updated'], $e['status'], $e['location'] ) );

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
		WP_CLI::warning( '  skipped: ' . implode( '; ', $faults ) );
		$problem++;
		continue;
	}

	if ( $DRY ) {
		WP_CLI::log( '  dry run - nothing written' );
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

	// From the table, not rwmb_meta(): Meta Box caches the row for the life of
	// the request and would report the pre-update value on a write that
	// succeeded.
	$fresh = $wpdb->get_row(
		$wpdb->prepare( 'SELECT plugin_version, last_updated, summary FROM plugins WHERE ID = %d', $post_id ),
		ARRAY_A
	);

	WP_CLI::log( sprintf(
		'  verified: %s  %s  summary %d B  -  %s',
		$fresh['plugin_version'],
		$fresh['last_updated'],
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

<?php
/**
 * Create the eight missing "Plugins" CPT documentation entries found by the
 * 2026-08-31 gap check.
 *
 * Storage is the Meta Box custom table `plugins` (no dlrh_ prefix), keyed by
 * post ID. Idempotent via get_page_by_path() - an entry that already exists is
 * updated in place, never duplicated.
 *
 * Run:      wp eval-file create-cpt-entries-aug2026.php
 * Dry run:  FF_CPT_DRY=1 wp eval-file create-cpt-entries-aug2026.php
 *
 * The dry run is gated on an environment variable, not a flag: wp eval-file
 * rejects unknown flags, and a bare positional argument does not reliably
 * reach $args.
 *
 * last_updated is the plugin's git last-commit date where a repository exists,
 * else the server file mtime. It is NOT today's date - bumping it would falsely
 * imply a code release.
 */

global $wpdb;

$DRY = (bool) getenv( 'FF_CPT_DRY' );

$entries = array();

/* =========================================================================
 * 1. FF FacetWP Refresh Sync
 * ====================================================================== */

$entries[] = array(
	'slug'     => 'ff-facetwp-refresh-sync',
	'title'    => 'FF FacetWP Refresh Sync',
	'version'  => 'v1.0.0',
	'updated'  => '08/19/2026',
	'status'   => 'Active',
	'location' => 'Directory',
	// Note: the repository name differs from the plugin folder name.
	'repo'     => 'https://github.com/Sinovya/ff-facet-refresh-sync',
	'summary'  => <<<'HTML'
<p>The Directory&rsquo;s search results do not come from the carrier database directly &mdash; they come from a separate search index, and something has to keep the two in step. Nothing did. The scheduled refresh updated carrier records and stopped there, so from the moment a refresh changed a carrier&rsquo;s authority status the search index went on showing the old one. On 19 August 2026 the index had not been rebuilt since 24 June: 209 carriers whose USDOT authority had lapsed still appeared as Active, and 197 active carriers were indexed as Inactive and were effectively invisible to search &mdash; 406 wrong records, accumulating at roughly 200 a month, and it surfaced only because somebody happened to look at one carrier. This plugin re-indexes a carrier the moment a refresh changes a field that search can filter on, and reconciles the whole index weekly to catch drift that arrives by any other route.</p>
HTML
	,
	'how'      => <<<'HTML'
<p>FF Scheduled Refresh announces every field it changes. This listens for those announcements, decides which ones the search index actually cares about, and re-indexes the affected carriers once per run rather than once per field.</p>
<ol>
<li><strong>Watch what the refresh changes.</strong> Every changed field is reported as it happens, with the carrier it belongs to.</li>
<li><strong>Ignore changes search cannot see.</strong> Only seven carrier fields are things a visitor can filter or sort by. A change to anything else is a real change to carrier data that has no bearing on what a search returns, and re-indexing for it would be work with no visible effect.</li>
<li><strong>Index once per carrier, not once per change.</strong> The carriers are collected during the run and indexed together at the end, so a carrier with eight changed fields is indexed once rather than eight times.</li>
<li><strong>Reconcile weekly.</strong> Watching new changes cannot fix drift that accumulated before the plugin existed, or drift caused by something that does not announce itself. A weekly pass compares the indexed status of every carrier against the database, finds carriers missing from the index entirely, and repairs both. It is deliberately silent when there is nothing to do &mdash; two months of silence is exactly how the original problem went unnoticed, so the weekly pass is the thing that would now break that silence.</li>
<li><strong>Stay switchable.</strong> A single setting turns it off without deactivating the plugin, and it is an ordinary plugin rather than a must-use one precisely so it can be switched off from the admin screen if it ever misbehaves.</li>
</ol>
<p><strong>Example:</strong></p>
<p>A carrier&rsquo;s USDOT authority lapses. The overnight refresh picks it up and writes the new status to the carrier record. Before this plugin, a broker searching for active carriers on that lane kept seeing the carrier as Active &mdash; until the next manual re-index, which in the worst observed case was two months later. Now the carrier drops out of the active results the same night, and the weekly reconciliation confirms nothing else has slipped.</p>
<p><strong>Technical reference</strong></p>
<ul>
<li>Listens on <code>ff_refresh_change_logged</code> (fired by FF Scheduled Refresh), collects carrier IDs, and flushes on <code>shutdown</code> at priority 99 &mdash; so it behaves identically whether the refresh ran from cron, WP-CLI or the admin, without having to find every exit path.</li>
<li>Indexed fields watched: <code>usdot_status</code>, <code>allowed_to_operate</code>, <code>number_of_power_units</code>, <code>state_province</code>, <code>cargo_carried</code>, <code>latitude</code>, <code>longitude</code>. Filterable via <code>ff_fwp_sync_indexed_fields</code>. The taxonomy facets (carrier operation, hazmat, country, equipment type, zone, states covered) are not written by the field refresh, and the lane facets come from a different table entirely, so neither is watched.</li>
<li><strong>Kill switch:</strong> option <code>ff_fwp_sync_mode</code> set to <code>off</code>.</li>
<li>Weekly reconciliation runs on <code>ff_fwp_sync_reconcile_cron</code>. <code>ff_fwp_sync_reconcile( true )</code> reports what it would repair without touching the index.</li>
<li>Measured cost: re-indexing 405 carriers took 9.6 seconds, so doing this properly is negligible against the refresh itself.</li>
<li><strong>This plugin owns re-index-on-refresh.</strong> FF Scheduled Refresh 1.4.0 later added its own re-index wiring, which duplicates what is already running here. That duplicate is to be stripped from the refresh plugin, not extended &mdash; the ownership was never written down, which is how it came to be built twice.</li>
<li>Deliberately a separate plugin rather than an edit to FF Scheduled Refresh: search-index freshness is a distinct concern from data collection, and at the time production and sandbox were running different refresh versions, so editing it would have deepened that drift.</li>
<li>Repository is <code>Sinovya/ff-facet-refresh-sync</code>, which does not match the plugin folder name <code>ff-facetwp-refresh-sync</code>. Expect it to look like a missing repository in any name-matching audit.</li>
<li>Installed and active on production and the Directory sandbox.</li>
</ul>
HTML
	,
	'faq'      => <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: What was actually wrong?</em></p>
<p>A: Search results described carriers using a status they no longer held. A carrier whose authority had lapsed still came back as Active, and an active carrier could be missing from the results altogether.</p>
<p><em>Q: How many carriers were affected?</em></p>
<p>A: 406 at the point it was found, accumulating at roughly 200 a month since the last manual re-index.</p>
<p><em>Q: How long had it been happening?</em></p>
<p>A: Since 24 June 2026, found on 19 August. Nothing reported it; it came to light because one carrier was looked at by hand.</p>
<p><em>Q: Does it slow the nightly refresh down?</em></p>
<p>A: Not meaningfully. Re-indexing 405 carriers measured under ten seconds.</p>
<p><em>Q: What happens if it misbehaves?</em></p>
<p>A: One setting switches it off without deactivating anything, and it can also simply be deactivated from the plugins screen.</p>
<p><em>Q: Why is this not part of the refresh plugin?</em></p>
<p>A: Keeping the search index fresh is a different job from collecting carrier data, and production and sandbox were running different versions of the refresh plugin at the time.</p>
<p>&nbsp;</p>
<p><strong>Operational value:</strong></p>
<p>The Directory&rsquo;s core promise is that a search tells the truth about who is authorised to operate right now. For two months it did not, for 406 carriers, and there was no mechanism that would ever have said so. This closes the gap for new changes and adds the weekly check that would surface it next time &mdash; the check matters as much as the fix, because the original failure was not that the index went stale but that nothing noticed.</p>
HTML
	,
);

/* =========================================================================
 * 2. FF FacetWP Browser Index Guard
 * ====================================================================== */

$entries[] = array(
	'slug'     => 'ff-facetwp-browser-index-guard',
	'title'    => 'FF FacetWP Browser Index Guard',
	'version'  => 'v1.0.0',
	'updated'  => '08/22/2026',
	'status'   => 'Active',
	'location' => 'Directory',
	'repo'     => '',
	'summary'  => <<<'HTML'
<p>Rebuilding the Directory&rsquo;s search index from the FacetWP settings screen looks like a normal admin button, and on a site of this size it is a trap. The rebuild runs inside the browser tab that started it; on 20,000 carriers it dies partway through, and there is no server-side process to carry on. What is left behind is a progress bar frozen at some random percentage, an orphaned temporary table, and &mdash; if the run was cancelled &mdash; a stored flag that silently kills the <em>next</em> attempt as well, so the obvious response of trying again does not work either. This plugin blocks that button and tells whoever pressed it the one command that does the job properly in about six minutes.</p>
HTML
	,
	'how'      => <<<'HTML'
<p>The block is enforced on the server, not in the browser. The visual changes exist only so that nobody presses a button that was going to be refused anyway.</p>
<ol>
<li><strong>Intercept the request before FacetWP sees it.</strong> FacetWP registers its whole admin request switchboard at one point during start-up; this runs immediately before that point, so a rebuild request is caught before FacetWP ever reads it.</li>
<li><strong>Answer with the command to run instead.</strong> The refusal is not a bare error &mdash; it names the exact SSH command, so the person who pressed the button knows what to do next.</li>
<li><strong>Record who tried.</strong> Every blocked attempt is written to the error log with the user and the address it came from.</li>
<li><strong>Make the button look inert.</strong> The Re-index button is greyed out and unclickable on the FacetWP screens, and a notice above it explains why before anyone reaches for it.</li>
<li><strong>Leave the command-line path alone.</strong> It deliberately does not block resuming an index. A run started over SSH must stay resumable, and blocking that would break FacetWP&rsquo;s own retry behaviour.</li>
</ol>
<p><strong>Example:</strong></p>
<p>Someone opens Settings, FacetWP and presses Re-index because search results look stale. Before: the tab churns for twenty minutes, freezes at 61%, and leaves the index half-built and a temporary table behind. Now: the button is greyed out, a notice above it explains the problem, and the request is refused with the message &ldquo;Browser re-indexing is disabled on this site. Run <code>sh reindex.sh</code> over SSH instead.&rdquo;</p>
<p><strong>Technical reference</strong></p>
<ul>
<li>Hooks <code>init</code> at priority <strong>999</strong>, ahead of FacetWP&rsquo;s AJAX switchboard at <code>init</code>:1000. Intercepts the POST action <code>facetwp_rebuild_index</code> and replies with a JSON error naming the command.</li>
<li><strong>Kill switch:</strong> <code>wp option update ff_facetwp_guard_mode off</code>.</li>
<li><strong>Deliberately does not touch <code>facetwp_resume_index</code></strong> &mdash; a CLI-started index must stay resumable.</li>
<li>The CSS (<code>pointer-events: none</code> on the rebuild button) is cosmetic only. The button is a Vue component, so unbinding its click handler from outside is unreliable; the server-side interception is the real guard.</li>
<li><strong>Correct procedure:</strong> <code>ssh directory-new sh reindex.sh</code> (sandbox: <code>ssh sandbox sh reindex.sh</code>). Progress: <code>ssh directory-new sh reindex-status.sh</code>. Both scripts are staged in the home directory of production and sandbox.</li>
<li>A cancelled browser run leaves <code>facetwp_indexing_cancelled</code> set to <code>yes</code>, which silently aborts the following run. There is no cron fallback on this install.</li>
<li>Installed as a must-use plugin on production and the Directory sandbox.</li>
</ul>
HTML
	,
	'faq'      => <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: Why not just fix the re-index so it works from the browser?</em></p>
<p>A: The limit is the browser tab, not our code. FacetWP drives the rebuild from the page that is open; on 20,000 carriers it does not survive the run, and there is no server-side fallback to hand it to.</p>
<p><em>Q: What do I do when search looks stale?</em></p>
<p>A: Run <code>sh reindex.sh</code> over SSH. It takes about six minutes and keeps going after you close the terminal.</p>
<p><em>Q: Does this stop the index being rebuilt at all?</em></p>
<p>A: No. It stops one route that does not work. The SSH route is unaffected, and so is FacetWP&rsquo;s ability to resume a run started that way.</p>
<p><em>Q: Can it be turned off?</em></p>
<p>A: Yes, with one option. It should be turned off only with a reason, because the button it hides genuinely does not work here.</p>
<p><em>Q: Would search have needed re-indexing this often anyway?</em></p>
<p>A: Less often now. FF FacetWP Refresh Sync keeps the index in step automatically; a full rebuild is for recovery, not routine.</p>
<p>&nbsp;</p>
<p><strong>Operational value:</strong></p>
<p>A half-finished index is worse than a stale one: it leaves carriers missing from search with no visible sign that anything is wrong, and the natural next step of pressing the button again is silently ignored. Removing the button removes an entire category of self-inflicted outage, and replaces a twenty-minute failure with a six-minute command that works.</p>
HTML
	,
);

/* =========================================================================
 * 3. FF National Averages
 * ====================================================================== */

$entries[] = array(
	'slug'     => 'ff-national-averages',
	'title'    => 'FF National Averages',
	'version'  => 'v1.0.0',
	'updated'  => '08/26/2026',
	'status'   => 'Active',
	'location' => 'Directory',
	'repo'     => 'https://github.com/Sinovya/ff-national-averages',
	'summary'  => <<<'HTML'
<p>FMCSA publishes the out-of-service national averages in two places, and the two disagree. QCMobile, the interface the Directory refreshes carrier data from, serves a baseline stamped 2009-2010 and has not moved it since &mdash; every carrier in the country is compared against the same three numbers: 5.51, 20.72 and 4.50. SAFER recomputes the same averages every month over the most recent 24 months of inspection data and prints the date it did so; as of 31 July 2026 those three numbers are 6.67, 22.26 and 4.44. The gap runs one way. A driver comparator of 5.51 against a real 6.67 is 17% low, so every carrier looks worse against it than against the truth &mdash; which is the wrong direction for a figure we publish to brokers. This plugin keeps the current SAFER averages, together with the date they were computed, so any comparison the Directory shows is against a number that is both current and dated.</p>
HTML
	,
	'how'      => <<<'HTML'
<p>Once a month it reads a single SAFER company snapshot, takes the three national averages and the date they were computed from it, checks them, and stores them. Three national numbers are stored once, not copied onto twenty thousand carrier records.</p>
<ol>
<li><strong>Read one snapshot.</strong> The averages are national, so any carrier&rsquo;s page returns them; a single long-established USDOT number is used as the probe.</li>
<li><strong>Prove it is looking at the right table before trusting a number.</strong> SAFER is a web page, not a data feed, so the parse anchors on the printed &ldquo;national average&rdquo; label rather than picking up the first thing on the page that looks like a percentage.</li>
<li><strong>Range check everything, and reject the set if any part is wrong.</strong> All three figures and the date must be present and plausible or nothing is written.</li>
<li><strong>Fail closed.</strong> A rejected fetch leaves the previous values exactly as they were and logs loudly. A stale comparator we know about is better than a wrong one we do not.</li>
<li><strong>Keep the carrier records in step.</strong> When the averages change, the per-carrier comparator columns are updated to match, so anything still reading them does not disagree with anything reading the new values.</li>
<li><strong>Never show a comparison undated.</strong> The period is published alongside the figures as &ldquo;24 months to&rdquo; the date SAFER computed them, falling back to the old 2009-2010 label if no successful fetch has happened yet.</li>
</ol>
<p><strong>Example:</strong></p>
<p>A carrier&rsquo;s driver out-of-service rate is 6.10%. Against the QCMobile baseline of 5.51% that carrier reads as worse than average. Against the actual figure for the last 24 months, 6.67%, the same carrier is better than average. Nothing about the carrier changed &mdash; only which of FMCSA&rsquo;s two published averages we compared it to.</p>
<p><strong>Technical reference</strong></p>
<ul>
<li>Values live in the option <code>ff_national_averages</code> as vehicle, driver, hazmat, the SAFER as-of date and the time we fetched it. Failures are recorded in <code>ff_national_averages_last_error</code>.</li>
<li>Monthly cron event <code>ff_na_refresh</code> on a custom <code>monthly_ff_na</code> schedule, first run 03:00 on the first of the month.</li>
<li>Source: the SAFER carrier snapshot at <code>safer.fmcsa.dot.gov/query.asp</code>. Probe USDOT <strong>4031930</strong>.</li>
<li>Parse guards: the response must exceed 5,000 bytes, contain the national-average label, yield exactly three percentages after it, and carry an as-of date. Any failure returns an error and writes nothing.</li>
<li><code>ff_na_period()</code> returns the label to display beside any comparison &mdash; &ldquo;24 months to YYYY-MM-DD&rdquo;, or the QCMobile vintage <code>2009-2010</code> before the first successful fetch. This is what replaces the previously hardcoded comparator period.</li>
<li>A successful change fires <code>ff_na_changed</code>, which triggers the carrier-table sync.</li>
<li><strong>WP-CLI:</strong> <code>wp ff-na show</code> (stored values plus the last error), <code>refresh</code> (fetch, validate and store), <code>fetch</code> (parse and print without writing &mdash; the safe way to test a SAFER layout change), <code>sync</code> (push current values to the carrier table).</li>
<li>Consumed by FF OOS Highlight, which prefers the live SAFER figure over a carrier&rsquo;s stored column.</li>
<li>Installed and active on production and the Directory sandbox.</li>
</ul>
HTML
	,
	'faq'      => <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: Why are there two different national averages at all?</em></p>
<p>A: FMCSA publishes both. QCMobile serves a fixed 2009-2010 baseline; SAFER recomputes monthly over the most recent 24 months. We were using the first without realising it had not moved in fifteen years.</p>
<p><em>Q: Which way did the old number distort things?</em></p>
<p>A: Consistently against the carrier. The stale driver average is 17% below the real one, so carriers looked worse than they are.</p>
<p><em>Q: SAFER is a web page. What happens when they change its layout?</em></p>
<p>A: The parse refuses to guess. If the label is missing or the numbers do not add up, nothing is written, the previous values stay in place and the failure is logged.</p>
<p><em>Q: How often does it update?</em></p>
<p>A: Monthly, matching how often SAFER recomputes. It can also be run on demand from the command line.</p>
<p><em>Q: Do the averages get written onto every carrier?</em></p>
<p>A: Three numbers are stored once. The per-carrier comparator columns are kept in step so nothing reading the old location disagrees.</p>
<p><em>Q: Is the period shown to users?</em></p>
<p>A: Yes, and deliberately. A comparison against an undated average is not a comparison anyone can check.</p>
<p>&nbsp;</p>
<p><strong>Operational value:</strong></p>
<p>Every out-of-service figure the Directory publishes is a comparison, and a comparison is only as defensible as its comparator. Publishing a carrier&rsquo;s rate against a fifteen-year-old baseline, undated, is the kind of detail that is very hard to defend after the fact and trivial to fix beforehand. This makes the comparator current, dated, checkable, and safe to fail.</p>
HTML
	,
);

/* =========================================================================
 * 4. FF Proximity Index Fix
 * ====================================================================== */

$entries[] = array(
	'slug'     => 'ff-proximity-index-fix',
	'title'    => 'FF Proximity Index Fix',
	'version'  => 'v1.0.0',
	'updated'  => '06/09/2026',
	'status'   => 'Active',
	'location' => 'Directory',
	'repo'     => '',
	'summary'  => <<<'HTML'
<p>Radius search &mdash; &ldquo;carriers within 100 miles of this city&rdquo; &mdash; returned nothing usable on production, while working correctly on the sandbox from identical settings. The cause was in the search index rather than the data: when FacetWP indexed a carrier&rsquo;s location it stored the latitude in both the latitude and the longitude position, so every distance calculation was made from a point that does not exist. This plugin reads both coordinates straight from the carrier table as each carrier is indexed, and leaves a carrier out of the radius search altogether rather than indexing it at a false position.</p>
HTML
	,
	'how'      => <<<'HTML'
<p>FacetWP indexes the latitude from the carrier record and then makes a second, separate query for the longitude. On this install that second query does not reach the carrier data, so the longitude comes back empty and the latitude is stored twice. Rather than patch the vendor, this fills in both values at the moment the row is written.</p>
<ol>
<li><strong>Step in as the location facet is indexed</strong> and ignore every other facet.</li>
<li><strong>Read both coordinates from the source of record</strong> &mdash; the carrier table, by carrier ID, in one query.</li>
<li><strong>Check they are real.</strong> Both must be numeric, not the zero point off the coast of Africa, and within valid latitude and longitude ranges.</li>
<li><strong>Skip rather than guess.</strong> A carrier without usable coordinates is left out of the radius facet entirely. Indexing it at a made-up position would put it in results it does not belong in.</li>
</ol>
<p><strong>Example:</strong></p>
<p>A broker searches for carriers within 100 miles of Minneapolis. Before: no meaningful results, because every carrier was indexed at a position whose longitude was a copy of its latitude &mdash; no carrier in North America has a positive longitude, so the distance maths could not work. After: 1,206 carriers, verified against a hand-calculated great-circle distance.</p>
<p><strong>Technical reference</strong></p>
<ul>
<li>Filters <code>facetwp_index_row</code> at priority 20, acting only on the <code>proximity</code> facet.</li>
<li>Reads <code>latitude</code> and <code>longitude</code> from <code>dlrh_carrier_information</code> by post ID, and sets <code>facet_value</code> to the latitude and <code>facet_display_value</code> to the longitude &mdash; the pair FacetWP&rsquo;s distance calculation expects.</li>
<li>Rejects a coordinate pair that is non-numeric, exactly 0/0, or outside 90 degrees of latitude or 180 of longitude; those carriers are excluded from the facet.</li>
<li><strong>Root cause:</strong> FacetWP&rsquo;s <code>index_latlng</code> resolves the longitude through a re-entrant <code>get_row_data()</code> call that does not trigger the Meta Box FacetWP integrator, so <code>facet_display_value</code> stayed equal to the latitude. No vendor file is modified, so it survives FacetWP updates.</li>
<li><strong>Production only, and deliberately so.</strong> The Directory sandbox indexes the proximity facet correctly without it, on the same FacetWP and Meta Box versions and an identical facet configuration. This is a workaround for a failure that only production exhibits &mdash; it is not a deployment gap, and adding it to the sandbox is not required.</li>
<li><strong>Health check:</strong> <code>SELECT SUM(facet_display_value REGEXP '^-') FROM dlrh_facetwp_index WHERE facet_name = 'proximity'</code> should be close to the carrier count, since every North American longitude is negative. After the fix: 19,794 rows, all negative.</li>
<li>Related: the proximity facet itself was converted from a stray checkbox facet to a true radius facet on 2026-06-09 (radius options 10/25/50/100/250 miles, default 100).</li>
</ul>
HTML
	,
	'faq'      => <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: What did the failure look like?</em></p>
<p>A: Radius search returned nothing, or nothing sensible, even though the carriers had correct coordinates in the database.</p>
<p><em>Q: Was the carrier data wrong?</em></p>
<p>A: No. The coordinates were correct throughout; only the copy held in the search index was wrong.</p>
<p><em>Q: Why does the sandbox not need this?</em></p>
<p>A: The sandbox indexes the same facet correctly from the same configuration and the same plugin versions. The failure is specific to production, which is why the fix is too.</p>
<p><em>Q: Does it change FacetWP itself?</em></p>
<p>A: No. It supplies the correct values as the index row is written, so FacetWP updates cannot undo it.</p>
<p><em>Q: What happens to a carrier with no coordinates?</em></p>
<p>A: It is left out of radius search rather than placed somewhere invented. Being absent is correct; being wrongly present is not.</p>
<p><em>Q: How do I check it is still working?</em></p>
<p>A: Count the indexed longitudes that are negative. Every North American carrier should have one, so that count should track the carrier count.</p>
<p>&nbsp;</p>
<p><strong>Operational value:</strong></p>
<p>Search by lane and by radius is the Directory&rsquo;s primary way in. A radius search that silently returns nothing does not read as a fault to whoever ran it &mdash; it reads as no carriers in that market, which is the most damaging possible wrong answer for a coverage product. Twenty lines of index-time repair restore it, and the health check makes the failure loud if it ever returns.</p>
HTML
	,
);

/* =========================================================================
 * 5. FF OOS Highlight
 * ====================================================================== */

$entries[] = array(
	'slug'     => 'ff-oos-highlight',
	'title'    => 'FF OOS Highlight',
	'version'  => 'v1.1.0',
	'updated'  => '08/26/2026',
	'status'   => 'Active',
	'location' => 'Directory',
	'repo'     => 'https://github.com/Sinovya/ff-oos-highlight',
	'summary'  => <<<'HTML'
<p>A carrier page already shows the carrier&rsquo;s out-of-service rate beside the national average &mdash; 8.05% next to 6.67% &mdash; and says nothing at all about the relationship between them. A broker has to spot it. This marks the rate when it sits above the average. That is a statement about two figures already on the page, not a verdict about the carrier: the comparator sits in the next column, the number of inspections the rate is drawn from sits two columns to the left, nothing is aggregated into a single score, and a caption says in plain words that an unmarked rate is not an endorsement and that a rate drawn from few inspections says little either way. Those conditions are exactly what separates it from the risk badge that was removed on 26 August 2026.</p>
HTML
	,
	'how'      => <<<'HTML'
<p>The comparison happens in PHP because it has to. Bricks conditions compare a dynamic value against a fixed one and cannot compare two dynamic fields against each other, so a rate cannot be tested against an average in the page builder at all.</p>
<ol>
<li><strong>Compare two figures that are both already on the page.</strong> Nothing new is introduced, nothing is combined, and nothing is stored.</li>
<li><strong>Say nothing when there is nothing to say.</strong> Below three inspections no comparison is drawn at all &mdash; at a single inspection the only possible rates are 0% and 100%, and neither means anything.</li>
<li><strong>Prefer the live average.</strong> The current SAFER figure is used where it is available, falling back to the carrier&rsquo;s own stored comparator column. If neither can be established the figure renders plain, because an unknown comparator must not produce a mark in either direction.</li>
<li><strong>Caption the table.</strong> The caption names the period the average covers and states what an absent mark does and does not mean.</li>
<li><strong>Render inside the existing table.</strong> The same output is exposed both as a shortcode and as a Bricks dynamic tag, because the carrier table is built from elements that resolve dynamic data but never run shortcodes.</li>
</ol>
<p><strong>Example:</strong></p>
<p>A carrier has a driver out-of-service rate of 8.05% from 62 inspections, against a national average of 6.67%. The rate is marked, the average is visible in the next column and the inspection count two columns to its left, and the caption below reads that rates are shown against the FMCSA national average for the 24 months to that date, that a rate above the average is marked, and that an unmarked rate is not an endorsement. A second carrier with 11.1% from two inspections is not marked at all &mdash; there is not enough behind the number to compare.</p>
<p><strong>Technical reference</strong></p>
<ul>
<li><strong>Shortcodes:</strong> <code>[ff_oos_pct type="driver"]</code> (also <code>vehicle</code>, <code>hazmat</code>) and <code>[ff_oos_note]</code> for the caption.</li>
<li><strong>Bricks dynamic tags:</strong> <code>{ff_oos_pct_driver}</code>, <code>{ff_oos_pct_vehicle}</code>, <code>{ff_oos_pct_hazmat}</code>, <code>{ff_oos_note}</code>. These exist because Bricks&rsquo; text-basic element resolves dynamic data but never calls <code>do_shortcode</code>, and the carrier table is built from text-basic cells &mdash; a shortcode placed in one renders as literal text.</li>
<li>Minimum three inspections before any comparison is made (<code>FF_OOSH_MIN_INSPECTIONS</code>).</li>
<li>Reads <code>&lt;type&gt;_oos_percentage</code> and <code>&lt;type&gt;_inspections</code> from the carrier custom table. The average comes from FF National Averages, falling back to <code>&lt;type&gt;_national_average</code> on the carrier row.</li>
<li>An empty rate renders as an empty cell, never as zero &mdash; a carrier that was never inspected has no rate, rather than a rate of none.</li>
<li>Stores nothing and writes nothing. Removing the plugin removes the marks and leaves every underlying figure untouched.</li>
<li>Satisfies the presentation conditions in section 11 of the partner specification: comparator displayed, denominator displayed, no aggregation, no verdict, absence carries no meaning.</li>
<li>Installed and active on production and the Directory sandbox.</li>
</ul>
HTML
	,
	'faq'      => <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: Is this a risk score or a rating?</em></p>
<p>A: No, and the distinction is deliberate. It marks one published figure as higher than another published figure sitting beside it. Nothing is combined, weighted or scored, and no carrier is labelled.</p>
<p><em>Q: Does an unmarked rate mean the carrier is fine?</em></p>
<p>A: No, and the caption on every table says so. It means the rate is not above the national average, or that there were too few inspections to compare.</p>
<p><em>Q: Why ignore carriers with very few inspections?</em></p>
<p>A: Because a rate from one or two inspections is not a rate. At one inspection the only possible answers are 0% and 100%, and marking either would be misleading.</p>
<p><em>Q: Which national average is used?</em></p>
<p>A: The current SAFER figure, recomputed monthly, with the period shown in the caption. If that is unavailable the carrier&rsquo;s own stored comparator is used, and if neither can be established nothing is marked.</p>
<p><em>Q: Can a carrier dispute it?</em></p>
<p>A: There is nothing of ours to dispute. Both numbers are FMCSA&rsquo;s and both are on the page; a carrier who disagrees with the rate is disagreeing with FMCSA.</p>
<p><em>Q: Why not do this with Bricks conditions?</em></p>
<p>A: Bricks can compare a dynamic value against a fixed one, but not two dynamic values against each other. This comparison cannot be expressed in the builder.</p>
<p>&nbsp;</p>
<p><strong>Marketing:</strong></p>
<p>Brokers do not want a number, they want to know whether the number is good. Showing a rate next to an average and leaving the reader to do the arithmetic is data; marking the rate that exceeds it is the beginning of an answer. Doing it this way &mdash; the comparator visible, the sample size visible, nothing aggregated, and a caption that refuses to overclaim &mdash; is what lets us make the point at all, because the vetting record we can defend is the one where every figure shown is traceable to its source and its limits are stated on the page.</p>
HTML
	,
);

/* =========================================================================
 * 6. FF API Header Hygiene
 * ====================================================================== */

$entries[] = array(
	'slug'     => 'ff-api-header-hygiene',
	'title'    => 'FF API Header Hygiene',
	'version'  => 'v1.0.0',
	'updated'  => '08/14/2026',
	'status'   => 'Active',
	'location' => 'Directory',
	'repo'     => 'https://github.com/Sinovya/ff-api-header-hygiene',
	'summary'  => <<<'HTML'
<p>Every response the partner API returned carried headers that described the platform underneath it rather than the product a partner is integrating with. One of them, WordPress&rsquo; own discovery header, also leaked the Directory&rsquo;s internal hostname, because it is built from the site address rather than the public API address. The cross-origin headers had the same problem in reverse: they advertised WordPress&rsquo; own authentication and pagination headers, which this gateway does not use, and said nothing about the headers it does use. This removes the first and corrects the second, so a partner&rsquo;s browser-based client can read its own rate-limit state and quote a request identifier when it raises a support ticket.</p>
HTML
	,
	'how'      => <<<'HTML'
<p>It acts on the partner API namespace only, and only on the way out. Nothing about authentication, routing or response bodies is touched.</p>
<ol>
<li><strong>Recognise a partner request by its API route, not its hostname.</strong> That means it behaves identically whether the request arrived at the public API address, the sandbox equivalent, or the internal route on the Directory itself.</li>
<li><strong>Remove the platform discovery header.</strong> A single value cannot be removed from a header that may legitimately carry several, so all of them are cleared and any that did not come from the platform are put back.</li>
<li><strong>Advertise the headers the gateway really accepts</strong> &mdash; the FreightForge authentication set, rather than the platform&rsquo;s.</li>
<li><strong>Expose the headers a browser client actually needs to read</strong> &mdash; the four rate-limit counters, the reset time and the request identifier.</li>
<li><strong>Change nothing else.</strong> It runs after the response headers are queued and before anything is sent, and does nothing at all if output has already begun.</li>
</ol>
<p><strong>Example:</strong></p>
<p>A partner calls the carrier lookup endpoint from a browser application. Before: the response advertised the platform by name, disclosed the Directory&rsquo;s internal hostname, offered headers the gateway does not accept, and hid the rate-limit counters from the client&rsquo;s own JavaScript &mdash; so the integration could not tell how much of its quota was left until it ran out. Now the response names only the FreightForge contract, and the client can read its remaining hourly and daily allowance directly.</p>
<p><strong>Technical reference</strong></p>
<ul>
<li>Filters <code>rest_pre_serve_request</code> at priority 10, scoped to the <code>freightforge/v1</code> namespace (<code>FF_API_HYGIENE_NAMESPACE</code>).</li>
<li>Strips the core <code>Link: rel="https://api.w.org/"</code> discovery header, preserving any other Link values.</li>
<li>Sets <code>Access-Control-Allow-Headers</code> to Authorization, Content-Type, X-FF-Api-Key, X-FF-Timestamp, X-FF-Signature.</li>
<li>Sets <code>Access-Control-Expose-Headers</code> to X-RateLimit-Limit-Hour, X-RateLimit-Remaining-Hour, X-RateLimit-Limit-Day, X-RateLimit-Remaining-Day, X-RateLimit-Reset, X-Request-ID.</li>
<li>The platform&rsquo;s X-WP-Nonce and X-WP-Total are deliberately not advertised: this gateway authenticates with the X-FF headers and paginates in the response body, not in headers.</li>
<li>No-ops safely if headers have already been sent.</li>
<li>Installed as a must-use plugin on production and the Directory sandbox. It is a companion to FF Carrier API Gateway and changes none of that plugin&rsquo;s behaviour.</li>
</ul>
HTML
	,
	'faq'      => <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: Was anything exposed that should not have been?</em></p>
<p>A: The internal hostname of the Directory, and the fact that the API is built on WordPress. Neither is a credential, but neither belongs in a partner-facing response.</p>
<p><em>Q: Does this change how partners authenticate?</em></p>
<p>A: No. It corrects what the response says about authentication so that it matches what the gateway has always required.</p>
<p><em>Q: Why does exposing rate-limit headers matter?</em></p>
<p>A: A browser client cannot read a response header unless the server says it may. Without that, a partner could not see their remaining quota until a request was refused.</p>
<p><em>Q: Could it affect anything other than the partner API?</em></p>
<p>A: No. It acts only on the partner namespace, so the Directory&rsquo;s own API routes are untouched.</p>
<p><em>Q: Is it environment-specific?</em></p>
<p>A: No, and that is intentional. It keys on the API route rather than the hostname, so production, sandbox and internal calls all behave the same.</p>
<p>&nbsp;</p>
<p><strong>Operational value:</strong></p>
<p>A partner integrating against our API reads the response headers before they read the documentation. Headers that name someone else&rsquo;s platform, disclose an internal hostname and describe an authentication scheme we do not use make the product look improvised at exactly the moment a partner is deciding whether to build against it. This is a small change with a disproportionate effect on how the gateway presents itself.</p>
HTML
	,
);

/* =========================================================================
 * 7. FreightForge Carrier CSV Export
 * ====================================================================== */

$entries[] = array(
	'slug'     => 'ff-carrier-csv-export',
	'title'    => 'FreightForge Carrier CSV Export',
	'version'  => 'v1.0.9',
	'updated'  => '04/24/2026',
	'status'   => 'Active',
	'location' => 'Onboarding Sites',
	'repo'     => '',
	'summary'  => <<<'HTML'
<p>Frontline Logistics loads approved carriers into their transport management system from a spreadsheet. When a carrier registration reaches the approval step of the onboarding workflow, this builds that spreadsheet from the completed form and emails it to Frontline as an attachment, in the exact columns and the exact order their import expects. It is the file-based route into a customer&rsquo;s TMS, and it predates the API integration &mdash; one customer, one recipient address, one fixed layout that must not move.</p>
HTML
	,
	'how'      => <<<'HTML'
<p>It waits for a decision, not a submission. A carrier who fills the form in but is never approved produces no file.</p>
<ol>
<li><strong>Trigger on approval.</strong> It watches for one named step in the onboarding workflow to complete, on the registration forms it has been configured for.</li>
<li><strong>Map the entry onto the agreed columns.</strong> Thirty form fields become thirty named columns: company identity and address, billing address, dispatch contact, equipment, insurance and the rest.</li>
<li><strong>Normalise the awkward ones.</strong> Combined address fields, multiple-choice equipment lists and dates are reshaped into the single-value form the import expects.</li>
<li><strong>Email it as an attachment</strong> to the recipient on the settings screen, with a formatted covering message.</li>
<li><strong>Take a second form without changing the layout.</strong> A duplicate registration form can be added by configuring it alongside the first, but it must be a duplicate: the column layout is shared by every configured form and changing it would break the customer&rsquo;s import.</li>
</ol>
<p><strong>Example:</strong></p>
<p>A carrier completes the Frontline registration form and uploads their insurance certificate. A reviewer approves it. Within seconds a CSV containing that carrier&rsquo;s thirty mapped fields arrives in Frontline&rsquo;s inbox, ready to be dropped into their import, without anybody re-keying an address or a policy number.</p>
<p><strong>Technical reference</strong></p>
<ul>
<li>Hooks <code>gravityflow_step_complete</code> and <code>gravityflow_post_process_workflow</code>.</li>
<li><strong>Configured forms:</strong> form 21 &ldquo;Frontline Logistics Carrier Registration&rdquo; at trigger step 92, and form 49 &ldquo;Frontline Logistics Carrier Registration (No Load-Updated)&rdquo; at trigger step 131. The step is the one named &ldquo;Carrier Registration Data to Carrier Directory&rdquo; on each workflow.</li>
<li><strong>Thirty mapped field IDs, shared by both forms.</strong> A new form must be a duplicate of form 21 or the mapping breaks; fields present on the new form but not in the mapping are silently ignored.</li>
<li>Recipient is stored in the option <code>ff_csv_export_settings</code>, defaulting to Frontline&rsquo;s admin address. An admin submenu offers a settings screen, a test send and an email preview.</li>
<li>Builds the file with a header row and one data row, and sends it with the site&rsquo;s normal mail path.</li>
<li><strong>Installed on the Frontline site only</strong> &mdash; not on the clone template, demo, immense or wetruckwithyou.</li>
<li><strong>Independent of the API integration.</strong> It does not use FF Carrier API Gateway and knows nothing about broker rows or network lookup. When Frontline is provisioned on the gateway the two will run in parallel until somebody decides to retire this one; that decision belongs with the TMS cutover, not with this plugin.</li>
<li>No repository. The plugin exists on the server only, which is a gap worth closing before it is next edited.</li>
</ul>
HTML
	,
	'faq'      => <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: When does the file get sent?</em></p>
<p>A: When a registration is approved, not when it is submitted. Nothing leaves the site for a carrier who is never approved.</p>
<p><em>Q: Can we add another registration form?</em></p>
<p>A: Yes, but it has to be a duplicate of the existing one. The columns are shared, and the customer&rsquo;s import depends on them staying identical.</p>
<p><em>Q: Can the recipient be changed?</em></p>
<p>A: Yes, on the plugin&rsquo;s settings screen, which also has a test send and a preview of the email.</p>
<p><em>Q: Does this run on our other onboarding sites?</em></p>
<p>A: No. It is installed on the Frontline site only, because the layout is specific to their import.</p>
<p><em>Q: Is this replaced by the API integration?</em></p>
<p>A: Not yet. The two are independent, and whether this is retired is part of the TMS cutover decision rather than something this plugin settles.</p>
<p>&nbsp;</p>
<p><strong>Operational value:</strong></p>
<p>Getting an approved carrier into a customer&rsquo;s TMS is the point at which onboarding either delivers or creates work. Thirty fields re-keyed by hand is where errors enter a customer&rsquo;s system and where the value of our approval workflow leaks away. This closes the last step of the handoff for the customer who needed it first, and it is the reference point for what the API integration has to match before anyone proposes switching it off.</p>
HTML
	,
);

/* =========================================================================
 * 8. FreightForge Search Enhancements  (inactive - documented deliberately)
 * ====================================================================== */

$entries[] = array(
	'slug'     => 'ff-search-enhancements',
	'title'    => 'FreightForge Search Enhancements',
	'version'  => 'v1.2.0',
	'updated'  => '04/07/2026',
	'status'   => 'Inactive',
	'location' => 'Directory',
	'repo'     => '',
	'summary'  => <<<'HTML'
<p>An earlier front end for the carrier search results &mdash; a result count, pagination controls and a set of active-filter chips &mdash; added to the page as JavaScript from the footer, because the Bricks templates of the time could not produce them. It is installed but switched off on both production and the Directory sandbox, and the surfaces it used to provide are now produced elsewhere. It is documented here for one reason: so that nobody finds it, recognises the feature names, and switches it back on expecting it to be the live implementation.</p>
HTML
	,
	'how'      => <<<'HTML'
<p>Everything it did happened in the visitor&rsquo;s browser after the page had loaded. Nothing was stored and no server-side behaviour was changed.</p>
<ol>
<li><strong>Injected a script block into the footer</strong> of the search pages.</li>
<li><strong>Rewrote the result count and pagination</strong> each time the search filters changed.</li>
<li><strong>Rendered a set of active-filter chips</strong> showing which filters were applied, with a way to clear them.</li>
<li><strong>Offered a settings screen</strong> in the admin for the above.</li>
</ol>
<p><strong>Example:</strong></p>
<p>Somebody goes looking for where the active-filter chips on the carrier search come from, finds a plugin called Search Enhancements that plainly renders active-filter chips, and activates it. They now have two sets of chips fighting each other, because the live ones come from somewhere else. This entry exists so that the search stops at this paragraph.</p>
<p><strong>Technical reference</strong></p>
<ul>
<li>Hooks <code>wp_footer</code>; all behaviour is client-side JavaScript. Adds an admin settings page.</li>
<li><strong>Inactive on production and on the Directory sandbox.</strong> It has been inactive on the sandbox for as long as the current search stack has existed.</li>
<li><strong>The live active-filter chips are not this plugin.</strong> They are rendered by the <code>[ff_active_filters]</code> shortcode in FreightForge Search UI, which is where the proximity-chip consolidation and the brand styling live. Pagination and result counts come from FacetWP and the Bricks templates.</li>
<li>Because the live chips are placed by a shortcode stored in Bricks post meta, searching post content for the shortcode name finds nothing &mdash; search the post meta table instead. This is the usual reason someone ends up at this plugin by mistake.</li>
<li>No repository and no local copy; the file exists on the server only.</li>
<li>Retained rather than deleted so the original JavaScript stays available for reference. Deleting it is a safe change whenever nobody wants that reference any more.</li>
</ul>
HTML
	,
	'faq'      => <<<'HTML'
<p><strong>FAQ&rsquo;s:</strong></p>
<p><em>Q: Is this running?</em></p>
<p>A: No. It is inactive on production and on the Directory sandbox.</p>
<p><em>Q: Then where do the active-filter chips come from?</em></p>
<p>A: FreightForge Search UI, through the <code>[ff_active_filters]</code> shortcode placed in the Bricks archive template.</p>
<p><em>Q: Should I activate it if search results look wrong?</em></p>
<p>A: No. It would duplicate what the live templates already render. Stale-looking results are an indexing question, not this.</p>
<p><em>Q: Why keep it installed at all?</em></p>
<p>A: Only so the original JavaScript stays readable. There is no repository copy, so deleting it deletes it for good.</p>
<p><em>Q: Can it be deleted?</em></p>
<p>A: Yes, safely, once nobody wants the reference. Nothing depends on it.</p>
<p>&nbsp;</p>
<p><strong>Operational value:</strong></p>
<p>An inactive plugin whose name matches a live feature is a trap for whoever next goes looking. Documenting the ones that are switched off, and saying plainly what replaced them, costs one entry and saves the afternoon somebody would otherwise spend debugging the wrong file.</p>
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
		$e['version'],
		$e['updated'],
		$e['status'],
		$e['location']
	) );
	WP_CLI::log( sprintf(
		'  summary %d B   how_it_works %d B   faq %d B   repo: %s',
		strlen( $e['summary'] ),
		strlen( $e['how'] ),
		strlen( $e['faq'] ),
		$e['repo'] !== '' ? $e['repo'] : '(none)'
	) );

	// Sanity checks on the prose, per the house rules for this table.
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

	WP_CLI::log( '  checks: entities ok, newlines ok, tags balanced' );

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

	// Verify through the same path the admin edit screen reads.
	$ver = rwmb_meta( 'plugin_version', '', $post_id );
	$sum = (string) rwmb_meta( 'summary', '', $post_id );
	WP_CLI::log( sprintf(
		'  verified: version %s, summary %d B  -  %s',
		$ver,
		strlen( $sum ),
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

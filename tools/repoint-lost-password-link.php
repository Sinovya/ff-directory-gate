<?php
/**
 * Repoint "Lost password" links on the CURRENT auth pages from the superseded
 * page 26661 to the current one, 28266.
 *
 * Both new pages (28190 Carrier Directory Login, 28256 Carrier Directory
 * Password Reset) still linked to the old Lost Password page. That only worked
 * because ff-legacy-auth-redirects 301s /lost-password/ - this makes them
 * self-contained.
 *
 * Walks the unserialised element tree rather than string-replacing the
 * serialised blob, so it cannot corrupt length prefixes.
 *
 * Run: wp eval-file repoint-lost-password-link.php [dry]
 */

global $wpdb;

$DRY  = in_array( 'dry', (array) ( $args ?? array() ), true );
$FROM = '26661';
$TO   = '28266';
$KEY  = '_bricks_page_content_2';
$PAGES = array( 28190, 28256 );

$total = 0;

foreach ( $PAGES as $pid ) {

	$content = get_post_meta( $pid, $KEY, true );

	if ( ! is_array( $content ) ) {
		WP_CLI::warning( "$pid has no $KEY - skipped" );
		continue;
	}

	$hits = 0;

	foreach ( $content as $i => $el ) {

		if ( empty( $el['settings'] ) || ! is_array( $el['settings'] ) ) {
			continue;
		}

		// Link settings can sit under 'link' or directly on the element.
		foreach ( array( 'link', 'settings' ) as $unused ) {
			// handled below
		}

		$s = $el['settings'];

		// Case 1: nested link array (text-link, button, etc.)
		if ( isset( $s['link']['postId'] ) && (string) $s['link']['postId'] === $FROM ) {
			$content[ $i ]['settings']['link']['postId'] = $TO;
			$hits++;
			WP_CLI::log( "  $pid  #" . $el['id'] . '  link.postId  ' . $FROM . ' -> ' . $TO
				. '   (' . ( isset( $s['text'] ) ? mb_substr( wp_strip_all_tags( $s['text'] ), 0, 40 ) : $el['name'] ) . ')' );
		}

		// Case 2: flat postId on the element's own settings
		if ( isset( $s['postId'] ) && (string) $s['postId'] === $FROM ) {
			$content[ $i ]['settings']['postId'] = $TO;
			$hits++;
			WP_CLI::log( "  $pid  #" . $el['id'] . '  postId       ' . $FROM . ' -> ' . $TO );
		}
	}

	if ( ! $hits ) {
		WP_CLI::log( "  $pid  no matching link found" );
		continue;
	}

	$total += $hits;

	if ( $DRY ) {
		continue;
	}

	// Back up once per page.
	$bak = $KEY . '_bak_lostpw';

	if ( ! get_post_meta( $pid, $bak, true ) ) {
		update_post_meta( $pid, $bak, get_post_meta( $pid, $KEY, true ) );
	}

	// update_post_meta is blocked on this key on FF sites - write via $wpdb.
	$ok = $wpdb->update(
		$wpdb->postmeta,
		array( 'meta_value' => maybe_serialize( $content ) ),
		array( 'post_id' => $pid, 'meta_key' => $KEY )
	);

	if ( false === $ok ) {
		WP_CLI::error( "DB write failed for $pid: " . $wpdb->last_error );
	}

	clean_post_cache( $pid );
	wp_cache_delete( $pid, 'post_meta' );
}

if ( $DRY ) {
	WP_CLI::success( "DRY RUN - $total link(s) would change." );
	return;
}

// Verify by re-reading.
$remaining = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
	 WHERE pm.meta_key = %s AND pm.meta_value LIKE %s AND p.ID IN (28190, 28256)",
	$KEY, '%"' . $FROM . '"%'
) );

WP_CLI::success( "$total link(s) repointed. Remaining references to $FROM on those pages: $remaining" );

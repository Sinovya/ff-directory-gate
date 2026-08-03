<?php
/**
 * Make the teaser card's copy and CTA state-aware on production tpl 65.
 *
 * The card was built with static text and a fixed "Request access" button, so a
 * LAPSED customer - who already bought access - was told to request it. Swaps
 * the literals for the state-aware dynamic tags, which keeps the existing
 * styling and needs no second set of elements or conditions.
 *
 *   ffdgt1 (text)   -> {ff_dir_locked_message}
 *   ffdgb1 (button) -> text {ff_dir_cta_label}, link {ff_dir_cta_url}
 *
 * Run: wp eval-file make-teaser-cta-stateful.php [dry]
 */

global $wpdb;

$TPL  = 65;
$KEY  = '_bricks_page_content_2';
$TEXT = 'ffdgt1';
$BTN  = 'ffdgb1';
$DRY  = in_array( 'dry', (array) ( $args ?? array() ), true );

$content = get_post_meta( $TPL, $KEY, true );

if ( ! is_array( $content ) || empty( $content ) ) {
	WP_CLI::error( "template $TPL has no $KEY" );
}

$bak = $KEY . '_bak_statefulcta';

if ( ! get_post_meta( $TPL, $bak, true ) ) {
	update_post_meta( $TPL, $bak, $content );
	WP_CLI::log( "backup -> postmeta $bak" );
} else {
	WP_CLI::log( "backup already exists at $bak" );
}

$ids = array();
foreach ( $content as $el ) {
	$ids[] = $el['id'];
}

$missing = array_diff( array( $TEXT, $BTN ), $ids );

if ( $missing ) {
	WP_CLI::error( 'elements not found: ' . implode( ', ', $missing ) );
}

foreach ( $content as $i => $el ) {

	if ( $el['id'] === $TEXT ) {
		WP_CLI::log( '  ' . $TEXT . ' text: "' . ( $el['settings']['text'] ?? '' ) . '" -> {ff_dir_locked_message}' );
		$content[ $i ]['settings']['text'] = '{ff_dir_locked_message}';
	}

	if ( $el['id'] === $BTN ) {
		WP_CLI::log( '  ' . $BTN . ' text: "' . ( $el['settings']['text'] ?? '' ) . '" -> {ff_dir_cta_label}' );
		$content[ $i ]['settings']['text'] = '{ff_dir_cta_label}';

		$old_link = isset( $el['settings']['link']['useDynamicData'] ) ? $el['settings']['link']['useDynamicData'] : '(none)';
		WP_CLI::log( '  ' . $BTN . ' link: ' . $old_link . ' -> {ff_dir_cta_url}' );

		$content[ $i ]['settings']['link'] = array(
			'type'           => 'meta',
			'useDynamicData' => '{ff_dir_cta_url}',
		);
	}
}

if ( $DRY ) {
	WP_CLI::success( 'DRY RUN - nothing written.' );
	return;
}

$ok = $wpdb->update(
	$wpdb->postmeta,
	array( 'meta_value' => maybe_serialize( $content ) ),
	array( 'post_id' => $TPL, 'meta_key' => $KEY )
);

if ( false === $ok ) {
	WP_CLI::error( 'DB write failed: ' . $wpdb->last_error );
}

clean_post_cache( $TPL );
wp_cache_delete( $TPL, 'post_meta' );

WP_CLI::success( 'written.' );

<?php
/**
 * Phase 2b styling - turn the teaser locked line into native Bricks elements.
 *
 * Replaces the raw [ff_dir_locked] shortcode element in the tpl 65 loop card
 * with a styled block + text + button, so the teaser card has visual parity
 * with the member card and the user can restyle it in the builder.
 *
 * The shortcode itself STAYS in the plugin - it is still the right tool for
 * one-off placements (the profile locked state later). It is just not the
 * right tool for the card, where the layout should be real elements.
 *
 * Run: wp eval-file apply-teaser-styling.php [dry]
 */

global $wpdb;

$TPL      = 65;
$META_KEY = '_bricks_page_content_2';
$DRY      = in_array( 'dry', (array) ( $args ?? array() ), true );

$CARD     = 'tdskyj';
$AFTER    = 'bsixfk';
$OLD_SC   = 'ffdglk'; // shortcode element added by apply-teaser-layout.php
$WRAP     = 'ffdgtz';
$TEXT     = 'ffdgt1';
$BTN      = 'ffdgb1';

// ------------------------------------------------------- 1. global classes
$classes = get_option( 'bricks_global_classes', array() );
$existing = wp_list_pluck( $classes, 'name' );

$new_classes = array(
	array(
		'id'       => 'ffdgcl',
		'name'     => 'carrier-card__locked',
		'settings' => array(
			'_display'        => 'flex',
			'_direction'      => 'column',
			'_alignItems'     => 'flex-start',
			'_rowGap'         => 'var(--space-xs)',
			'_margin'         => array( 'top' => 'var(--space-xs)' ),
			'_padding'        => array( 'top' => 'var(--space-xs)' ),
			'_border'         => array(
				'width' => array( 'top' => '1' ),
				'style' => 'solid',
				'color' => array( 'raw' => 'var(--primary-trans-10)' ),
			),
			'_widthMobilePortrait' => '100%',
		),
	),
	array(
		'id'       => 'ffdgct',
		'name'     => 'carrier-card__locked-text',
		'settings' => array(
			'_typography' => array(
				'font-size'   => 'var(--text-s)',
				'font-weight' => '500',
				'color'       => array( 'raw' => 'var(--text-dark-muted)' ),
				'line-height' => '1.4',
			),
		),
	),
);

$added_classes = array();

foreach ( $new_classes as $nc ) {
	if ( ! in_array( $nc['name'], $existing, true ) ) {
		$classes[]       = $nc;
		$added_classes[] = $nc['name'];
	}
}

// --------------------------------------------------------- 2. the elements
$content = get_post_meta( $TPL, $META_KEY, true );

if ( ! is_array( $content ) || empty( $content ) ) {
	WP_CLI::error( 'Template ' . $TPL . ' has no ' . $META_KEY );
}

$cond_teaser = array(
	array(
		array(
			'id'           => 'ffdg05',
			'key'          => 'dynamic_data',
			'dynamic_data' => '{ff_dir_can_search}',
			'compare'      => 'empty',
		),
	),
);

// Drop the old shortcode element and any previous run of this script.
$content = array_values( array_filter( $content, function ( $el ) use ( $OLD_SC, $WRAP, $TEXT, $BTN ) {
	return ! in_array( $el['id'] ?? '', array( $OLD_SC, $WRAP, $TEXT, $BTN ), true );
} ) );

$card_found = false;

foreach ( $content as $i => $el ) {

	if ( ( $el['id'] ?? '' ) !== $CARD ) {
		continue;
	}

	$card_found = true;

	$kids = array_values( array_filter( $el['children'] ?? array(), function ( $c ) use ( $OLD_SC, $WRAP ) {
		return ! in_array( $c, array( $OLD_SC, $WRAP ), true );
	} ) );

	$pos = array_search( $AFTER, $kids, true );

	if ( false === $pos ) {
		$kids[] = $WRAP;
	} else {
		array_splice( $kids, $pos + 1, 0, array( $WRAP ) );
	}

	$content[ $i ]['children'] = $kids;
}

if ( ! $card_found ) {
	WP_CLI::error( 'Card element ' . $CARD . ' not found - template structure changed, aborting.' );
}

$content[] = array(
	'id'       => $WRAP,
	'name'     => 'block',
	'parent'   => $CARD,
	'children' => array( $TEXT, $BTN ),
	'settings' => array(
		'_cssGlobalClasses' => array( 'ffdgcl' ),
		'_conditions'       => $cond_teaser,
	),
);

$content[] = array(
	'id'       => $TEXT,
	'name'     => 'text-basic',
	'parent'   => $WRAP,
	'children' => array(),
	'settings' => array(
		'tag'               => 'p',
		'text'              => 'Carrier names, USDOT and contacts are member-only',
		'_cssGlobalClasses' => array( 'ffdgct' ),
	),
);

$content[] = array(
	'id'       => $BTN,
	'name'     => 'button',
	'parent'   => $WRAP,
	'children' => array(),
	'settings' => array(
		'text'              => 'Request access',
		'style'             => 'primary',
		'_cssGlobalClasses' => array( 'kkkzbk' ), // ff-btn - parity with View Listing
		'link'              => array(
			'type'           => 'meta',
			'useDynamicData' => '{ff_dir_request_access_url}',
		),
	),
);

WP_CLI::log( 'Global classes to add: ' . ( $added_classes ? implode( ', ', $added_classes ) : '(none - already present)' ) );
WP_CLI::log( 'Elements: ' . $WRAP . ' (block, teaser-only) > ' . $TEXT . ' (text) + ' . $BTN . ' (button, ff-btn)' );
WP_CLI::log( 'Inserted into ' . $CARD . ' after ' . $AFTER );

if ( $DRY ) {
	WP_CLI::success( 'DRY RUN - nothing written.' );
	return;
}

// ------------------------------------------------------------- 3. persist
if ( $added_classes ) {
	update_option( 'bricks_global_classes', $classes );
}

// update_post_meta is blocked on this key on FF sites - write via $wpdb.
$ok = $wpdb->update(
	$wpdb->postmeta,
	array( 'meta_value' => maybe_serialize( $content ) ),
	array( 'post_id' => $TPL, 'meta_key' => $META_KEY )
);

if ( false === $ok ) {
	WP_CLI::error( 'DB write failed: ' . $wpdb->last_error );
}

clean_post_cache( $TPL );
wp_cache_delete( $TPL, 'post_meta' );

// ------------------------------------------------------------- 4. verify
$check   = get_post_meta( $TPL, $META_KEY, true );
$seen    = array();
$card_ok = false;

foreach ( $check as $el ) {
	$id = $el['id'] ?? '';
	if ( in_array( $id, array( $WRAP, $TEXT, $BTN ), true ) ) {
		$seen[] = $id;
	}
	if ( $id === $CARD && in_array( $WRAP, $el['children'] ?? array(), true ) ) {
		$card_ok = true;
	}
	if ( $id === $OLD_SC ) {
		WP_CLI::warning( 'Old shortcode element ' . $OLD_SC . ' still present!' );
	}
}

$gc_now = wp_list_pluck( get_option( 'bricks_global_classes', array() ), 'name' );

WP_CLI::success( sprintf(
	'Written. elements=%d/3 (%s) card_child=%s classes_present=%s',
	count( $seen ),
	implode( ',', $seen ),
	$card_ok ? 'yes' : 'NO',
	( in_array( 'carrier-card__locked', $gc_now, true ) && in_array( 'carrier-card__locked-text', $gc_now, true ) ) ? 'yes' : 'NO'
) );

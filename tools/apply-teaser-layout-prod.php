<?php
/**
 * Teaser loop layout for PRODUCTION tpl 65 (directory.freightforge.com).
 *
 * Prod's card drifted a long way from sandbox (133 elements vs 65), so this is
 * a separate script - do NOT run the sandbox apply-teaser-*.php files here.
 *
 * Prod card structure:
 *   tdskyj  (.carrier-card .facetwp-template)
 *     hspwxn (.carrier-info)
 *       sfqcle   block, flex row   <- LOGO + NAME  (member only)
 *         yxjqpf   image  {ff_logos_approved_id}
 *         nsnqjz   heading {post_title}
 *       zusqqd   risk badges + "FMCSA Verified"   (left public: the risk tags
 *                are redacted to empty for non-members so their own Bricks
 *                conditions hide them, and the FMCSA badge is simply true)
 *     doolui
 *       dqxmxj   address            (member only)
 *       kfluid   MC | USDOT         (member only)
 *       lkiwqw   DAT zone           (public)
 *       bsixfk   operation / units / equipment (public, units banded)
 *     rhgvro  View Listing button   (member only)
 *
 * Adds: ffdgtz teaser block (.carrier-card__locked) with locked copy + a
 * "Request access" button, shown only when {ff_dir_can_search} is empty.
 *
 * Run: wp eval-file apply-teaser-layout-prod.php [dry]
 */

global $wpdb;

$TPL   = 65;
$KEY   = '_bricks_page_content_2';
$DRY   = in_array( 'dry', (array) ( $args ?? array() ), true );

$CARD  = 'tdskyj';
$AFTER = 'doolui';           // insert the teaser block after this child
$BTN   = 'kkkzbk';           // .ff-btn - parity with the View Listing button

$MEMBER_ONLY = array(
	'sfqcle' => 'ffdgp1', // logo + carrier name
	'dqxmxj' => 'ffdgp2', // city / state / zip / country
	'kfluid' => 'ffdgp3', // MC | USDOT
	'rhgvro' => 'ffdgp4', // View Listing
);

$WRAP = 'ffdgtz';
$TEXT = 'ffdgt1';
$BUTN = 'ffdgb1';

$content = get_post_meta( $TPL, $KEY, true );

if ( ! is_array( $content ) || empty( $content ) ) {
	WP_CLI::error( "template $TPL has no $KEY" );
}

// ------------------------------------------------------------------ backup
$bak = $KEY . '_bak_dirgate';

if ( ! get_post_meta( $TPL, $bak, true ) ) {
	update_post_meta( $TPL, $bak, $content );
	WP_CLI::log( "backup -> postmeta $bak" );
} else {
	WP_CLI::log( "backup already exists at $bak (untouched)" );
}

// -------------------------------------------------------- global classes
$classes  = get_option( 'bricks_global_classes', array() );
$existing = array();

foreach ( $classes as $c ) {
	if ( isset( $c['name'] ) ) {
		$existing[] = $c['name'];
	}
}

$want_classes = array(
	array(
		'id'       => 'ffdgcl',
		'name'     => 'carrier-card__locked',
		'settings' => array(
			'_display'    => 'flex',
			'_direction'  => 'column',
			'_alignItems' => 'flex-start',
			'_rowGap'     => 'var(--space-xs)',
			'_margin'     => array( 'top' => 'var(--space-xs)' ),
			'_padding'    => array( 'top' => 'var(--space-xs)' ),
			'_border'     => array(
				'width' => array( 'top' => '1' ),
				'style' => 'solid',
				'color' => array( 'raw' => 'var(--primary-trans-10)' ),
			),
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

$added = array();

foreach ( $want_classes as $wc ) {
	if ( ! in_array( $wc['name'], $existing, true ) ) {
		$classes[] = $wc;
		$added[]   = $wc['name'];
	}
}

// ------------------------------------------------------------- conditions
function ffp_cond( $compare, $cid ) {
	return array(
		array(
			array(
				'id'           => $cid,
				'key'          => 'dynamic_data',
				'dynamic_data' => '{ff_dir_can_search}',
				'compare'      => $compare,
			),
		),
	);
}

// --------------------------------------------------- verify structure first
$ids = array();
foreach ( $content as $el ) {
	$ids[] = $el['id'];
}

$required = array_merge( array_keys( $MEMBER_ONLY ), array( $CARD, $AFTER ) );
$missing  = array_diff( $required, $ids );

if ( $missing ) {
	WP_CLI::error( 'expected prod elements not found: ' . implode( ', ', $missing )
		. ' - template structure changed, aborting.' );
}

foreach ( array( $WRAP, $TEXT, $BUTN ) as $n ) {
	if ( in_array( $n, $ids, true ) ) {
		WP_CLI::warning( "$n already present - it will be replaced." );
	}
}

// Drop any previous run.
$content = array_values( array_filter( $content, function ( $el ) use ( $WRAP, $TEXT, $BUTN ) {
	return ! in_array( $el['id'], array( $WRAP, $TEXT, $BUTN ), true );
} ) );

// ------------------------------------------------------------ apply
$changed = array();

foreach ( $content as $i => $el ) {

	$id = $el['id'];

	if ( isset( $MEMBER_ONLY[ $id ] ) ) {
		$content[ $i ]['settings']['_conditions'] = ffp_cond( 'empty_not', $MEMBER_ONLY[ $id ] );
		$changed[] = "$id -> member only";
	}

	if ( $CARD === $id ) {

		$kids = array_values( array_filter( (array) $el['children'], function ( $k ) use ( $WRAP ) {
			return $k !== $WRAP;
		} ) );

		$pos = array_search( $AFTER, $kids, true );

		if ( false === $pos ) {
			$kids[] = $WRAP;
		} else {
			array_splice( $kids, $pos + 1, 0, array( $WRAP ) );
		}

		$content[ $i ]['children'] = $kids;
		$changed[] = "$WRAP inserted into $CARD after $AFTER";
	}
}

$content[] = array(
	'id'       => $WRAP,
	'name'     => 'block',
	'parent'   => $CARD,
	'children' => array( $TEXT, $BUTN ),
	'settings' => array(
		'_cssGlobalClasses' => array( 'ffdgcl' ),
		'_conditions'       => ffp_cond( 'empty', 'ffdgp5' ),
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
	'id'       => $BUTN,
	'name'     => 'button',
	'parent'   => $WRAP,
	'children' => array(),
	'settings' => array(
		'text'              => 'Request access',
		'style'             => 'primary',
		'_cssGlobalClasses' => array( $BTN ),
		'link'              => array(
			'type'           => 'meta',
			'useDynamicData' => '{ff_dir_request_access_url}',
		),
	),
);

// ------------------------------------------------------- integrity check
$all = array();
foreach ( $content as $el ) {
	$all[] = $el['id'];
}

$dupes = array_values( array_diff_assoc( $all, array_unique( $all ) ) );

if ( $dupes ) {
	WP_CLI::error( 'duplicate ids: ' . implode( ', ', $dupes ) );
}

foreach ( $content as $el ) {
	foreach ( (array) $el['children'] as $k ) {
		if ( ! in_array( $k, $all, true ) ) {
			WP_CLI::error( 'dangling child ' . $el['id'] . ' -> ' . $k );
		}
	}
}

WP_CLI::log( '' );
WP_CLI::log( 'global classes to add: ' . ( $added ? implode( ', ', $added ) : '(already present)' ) );
foreach ( $changed as $c ) {
	WP_CLI::log( '  - ' . $c );
}
WP_CLI::log( 'elements: ' . count( $content ) );

if ( $DRY ) {
	WP_CLI::success( 'DRY RUN - nothing written.' );
	return;
}

if ( $added ) {
	update_option( 'bricks_global_classes', $classes );
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

$check = get_post_meta( $TPL, $KEY, true );
$seen  = 0;

foreach ( (array) $check as $el ) {
	if ( isset( $MEMBER_ONLY[ $el['id'] ] ) && ! empty( $el['settings']['_conditions'] ) ) {
		$seen++;
	}
	if ( $el['id'] === $WRAP ) {
		$seen++;
	}
}

WP_CLI::success( 'written. verified ' . $seen . '/' . ( count( $MEMBER_ONLY ) + 1 ) . ' on re-read.' );

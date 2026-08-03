<?php
/**
 * Plugin Name: FF Directory Gate
 * Description: Entitlement seam and access gate for paid Directory access. Registers the directory_member role, provides ff_dir_user_can() - the single function every gate consults - and gates the carrier surfaces.
 * Version: 0.9.0
 * Author: FreightForge
 *
 * PHASE 1 (0.1.0): role + entitlement function + WP-CLI provisioning. Inert.
 * PHASE 2a (0.2.0): carrier/lane single redirect + WP REST gate.
 * PHASE 2b (0.3.0): archive teaser - field redaction, power-unit banding,
 *                   identity facets, permalink masking, feed gate, Bricks kit.
 *          (0.3.1): Bricks dynamic tags so native conditions can drive the
 *                   teaser vs member loop layout off the same decision function.
 *          (0.3.2): {ff_dir_request_access_url} so the teaser CTA links to the
 *                   overview page without hardcoding it into the template.
 *          (0.4.0): ff_dir_gate_mode master switch, default OFF, so the plugin
 *                   can ship to a live site inert. See MASTER SWITCH below.
 *          (0.5.0): FOREIGN identity tags - the production archive card renders
 *                   a carrier LOGO via ff-carrier-logos' {ff_logos_approved_id},
 *                   which the Meta Box allow-list never saw. Plus a filter seam
 *                   on ff_dir_gate_mode so the teaser can be QA'd on a live site
 *                   without enforcing on the public.
 *          (0.6.0): member chrome - hide the WP admin bar from customers and
 *                   add [ff_dir_account_bar] (sign in / signed-in + log out).
 *          (0.6.1): account bar suppressed on the login/lost-password/reset
 *                   pages, where it named the wrong identity.
 *          (0.7.0): expired-member state - a lapsed customer is no longer told
 *                   to "request access" for something they already bought.
 *
 * Design notes:
 * - Does NOT depend on the Members plugin. Members is active on dir-sandbox but is
 *   NOT installed on production, so the role is registered with add_role().
 * - ff_dir_user_can() is the ONLY function allowed to make an access decision.
 * - REST is gated via the rest_endpoints filter + permission_callback, NOT
 *   rest_pre_dispatch - other plugins on this stack swallow WP_Error from that hook.
 * - Redirect target is the option ff_dir_gate_redirect so the Directory overview
 *   page can be pointed at without a code change.
 */

defined( 'ABSPATH' ) || exit;

define( 'FF_DIR_GATE_VERSION', '0.9.0' );
define( 'FF_DIR_ROLE', 'directory_member' );

/**
 * ============================================================
 * MASTER SWITCH - ff_dir_gate_mode
 *
 *   off      (default) nothing is gated. The site behaves exactly as it did
 *            before this plugin existed. Safe to deploy on a live site.
 *   enforce  the gates fire.
 *
 *   wp option update ff_dir_gate_mode enforce   # turn on
 *   wp option update ff_dir_gate_mode off       # kill switch
 *
 * Same shape as ff_internal_routes_mode, which this codebase already uses.
 *
 * IMPORTANT distinction, do not blur these two:
 *
 *   ff_dir_user_can()      "is this user ENTITLED?"  - never affected by mode.
 *   ff_dir_gate_enabled()  "are we ENFORCING?"       - the mode switch.
 *
 * Mode off must never OPEN a door that was already closed, so anything that
 * reveals member-only content (ff_dir_user_can, the shortcodes) ignores the
 * mode entirely. Only the gates and the LAYOUT tags respect it - turning the
 * gate off has to give back exactly the public site, teaser layout included.
 * ============================================================
 */
function ff_dir_gate_mode() {

	$mode = get_option( 'ff_dir_gate_mode', 'off' );
	$mode = is_string( $mode ) ? strtolower( trim( $mode ) ) : 'off';
	$mode = in_array( $mode, array( 'off', 'enforce' ), true ) ? $mode : 'off';

	/**
	 * Filter seam so the teaser can be rendered and QA'd on a LIVE site without
	 * touching the stored option - i.e. without briefly enforcing on the public:
	 *
	 *   add_filter( 'ff_dir_gate_mode', function () { return 'enforce'; } );
	 */
	$mode = apply_filters( 'ff_dir_gate_mode', $mode );

	return in_array( $mode, array( 'off', 'enforce' ), true ) ? $mode : 'off';
}

function ff_dir_gate_enabled() {
	return 'enforce' === ff_dir_gate_mode();
}

/**
 * "Will this visitor see the real data?" - the question the LAYOUT asks.
 * True whenever the gate is off, because then the site is simply public.
 */
function ff_dir_shows_full_data( $feature = 'search' ) {
	return ! ff_dir_gate_enabled() || ff_dir_user_can( $feature );
}

/**
 * The doors. Anything not in this list is closed by definition.
 */
function ff_dir_features() {
	return array( 'search', 'contacts', 'export' );
}

/**
 * Post types that carry carrier data and must never render for a non-member.
 */
function ff_dir_gated_post_types() {
	return apply_filters( 'ff_dir_gated_post_types', array( 'carriers', 'lane' ) );
}

/**
 * Register the membership role. Idempotent and cheap - get_role() reads the
 * already-loaded wp_user_roles option, so this is not a query per request.
 */
add_action( 'init', 'ff_dir_register_role', 1 );
function ff_dir_register_role() {
	if ( get_role( FF_DIR_ROLE ) ) {
		return;
	}
	add_role( FF_DIR_ROLE, 'Directory Member', array( 'read' => true ) );
}

/**
 * THE decision function.
 *
 * Order matters: cheapest checks first, and each failure is final.
 *   1. unknown feature      -> false (closed by default)
 *   2. not logged in        -> false
 *   3. administrator        -> true  (never lock your own team out of your own data)
 *   4. wrong role           -> false
 *   5. access expired       -> false
 *   6. per-feature toggle
 *
 * @param string   $feature One of ff_dir_features().
 * @param int|null $user_id Defaults to the current user.
 * @return bool
 */
function ff_dir_user_can( $feature, $user_id = null ) {

	$feature = is_string( $feature ) ? strtolower( trim( $feature ) ) : '';
	$allowed = false;

	if ( ! in_array( $feature, ff_dir_features(), true ) ) {
		return false;
	}

	$user = $user_id ? get_user_by( 'id', (int) $user_id ) : wp_get_current_user();

	if ( ! $user || ! $user->ID ) {
		return false;
	}

	if ( user_can( $user, 'manage_options' ) ) {
		$allowed = true;
	} elseif ( in_array( FF_DIR_ROLE, (array) $user->roles, true ) && ! ff_dir_access_expired( $user->ID ) ) {

		$features = get_user_meta( $user->ID, 'ff_dir_features', true );

		if ( is_array( $features ) ) {
			// Supports both a map ( search => 1 ) and a plain list ( 'search', 'export' ).
			$allowed = isset( $features[ $feature ] )
				? (bool) $features[ $feature ]
				: in_array( $feature, $features, true );
		}
	}

	/**
	 * Extension seam. When the entitlement matrix generalises the API gateway's
	 * feature_overrides, it hooks here rather than replacing this function.
	 */
	return (bool) apply_filters( 'ff_dir_user_can', $allowed, $feature, $user->ID );
}

/**
 * ============================================================
 * BILLING WINDOW
 *
 *   ff_dir_paid_through   end of the period the customer has PAID for
 *   ff_dir_grace_days     how long access continues past that (per customer)
 *   ff_dir_billing_cycle  monthly | quarterly | semiannual | annual
 *
 * Access is driven ONLY by the dates. The cycle is bookkeeping - it says what
 * to advance paid_through to on the next payment; the gate never reads it.
 *
 * Back-compat: older grants stored a single hard date in ff_dir_access_expires.
 * That is still honoured as the paid-through date, and grace defaults to 0, so
 * existing members behave exactly as before.
 * ============================================================
 */
function ff_dir_billing_cycles() {
	return apply_filters( 'ff_dir_billing_cycles', array(
		'monthly'    => 'Monthly',
		'quarterly'  => 'Quarterly',
		'semiannual' => 'Semi-annually',
		'annual'     => 'Annually',
	) );
}

function ff_dir_grace_options() {
	return apply_filters( 'ff_dir_grace_options', array( 0, 7, 14, 30 ) );
}

/** How long a lapsed customer stays a renewable ex-customer before reverting to a stranger. */
function ff_dir_ex_customer_days() {
	return (int) apply_filters( 'ff_dir_ex_customer_days', 365 );
}

function ff_dir_paid_through( $user_id ) {

	$date = get_user_meta( $user_id, 'ff_dir_paid_through', true );

	if ( empty( $date ) ) {
		$date = get_user_meta( $user_id, 'ff_dir_access_expires', true ); // legacy grants
	}

	return is_string( $date ) ? trim( $date ) : '';
}

function ff_dir_grace_days( $user_id ) {

	$days = get_user_meta( $user_id, 'ff_dir_grace_days', true );

	return ( '' === $days || null === $days ) ? 0 : max( 0, (int) $days );
}

/**
 * The moment access actually stops: end of the paid period plus grace.
 * Null means "no expiry configured", i.e. indefinite access.
 */
function ff_dir_hard_cutoff( $user_id ) {

	$paid = ff_dir_paid_through( $user_id );

	if ( '' === $paid ) {
		return null;
	}

	$stamp = strtotime( $paid . ' 23:59:59' );

	if ( ! $stamp ) {
		return null; // Unparseable date is a provisioning error, not a lockout.
	}

	return $stamp + ( ff_dir_grace_days( $user_id ) * DAY_IN_SECONDS );
}

/**
 * Has access actually stopped? Grace counts as NOT expired - a customer inside
 * grace keeps working, which is the whole point of a grace period.
 */
function ff_dir_access_expired( $user_id ) {

	$cutoff = ff_dir_hard_cutoff( $user_id );

	if ( null === $cutoff ) {
		return false;
	}

	return $cutoff < current_time( 'timestamp' );
}

/** Is the customer past their paid period but still inside grace? */
function ff_dir_in_grace( $user_id ) {

	$paid = ff_dir_paid_through( $user_id );

	if ( '' === $paid || ff_dir_access_expired( $user_id ) ) {
		return false;
	}

	$paid_end = strtotime( $paid . ' 23:59:59' );

	return $paid_end && $paid_end < current_time( 'timestamp' );
}

/** Whole days of grace left, rounded up. 0 if not in grace. */
function ff_dir_grace_days_remaining( $user_id ) {

	if ( ! ff_dir_in_grace( $user_id ) ) {
		return 0;
	}

	$cutoff = ff_dir_hard_cutoff( $user_id );

	return max( 0, (int) ceil( ( $cutoff - current_time( 'timestamp' ) ) / DAY_IN_SECONDS ) );
}

/**
 * A customer who lapsed long ago stops being a renewable ex-customer and is
 * treated as someone who never bought - they get "Request access" again.
 */
function ff_dir_is_former_customer( $user_id ) {

	$cutoff = ff_dir_hard_cutoff( $user_id );

	if ( null === $cutoff ) {
		return false;
	}

	return ( $cutoff + ( ff_dir_ex_customer_days() * DAY_IN_SECONDS ) ) < current_time( 'timestamp' );
}

/**
 * ============================================================
 * ACCESS STATE - three outcomes, not two.
 *
 * v1 deliberately deferred the "signed in but no access" case, on the reasoning
 * that accounts are only created when access is granted so it could not occur.
 * EXPIRY breaks that assumption: every grant carries --expires, and the day
 * after, the customer still has an account and can still log in.
 *
 * Without this they land on the teaser being told to "Request access" - asked
 * to request something they already bought, with no explanation and no route to
 * renewing. That is the worst state to leave a lapsed customer in, because it
 * looks like the product forgot them.
 *
 *   full     sees real data (member in good standing, or an admin, or gate off)
 *   expired  has an account, but access is not currently active
 *   none     not signed in at all
 * ============================================================
 */
function ff_dir_access_state( $user_id = null ) {

	if ( ! ff_dir_gate_enabled() ) {
		return 'full';
	}

	$user = $user_id ? get_user_by( 'id', (int) $user_id ) : wp_get_current_user();

	if ( ! $user || ! $user->ID ) {
		return 'none';
	}

	if ( user_can( $user, 'manage_options' ) ) {
		return 'full';
	}

	// Someone with no membership at all is a stranger, not a lapsed customer.
	if ( ! in_array( FF_DIR_ROLE, (array) $user->roles, true ) ) {
		return 'none';
	}

	// Lapsed so long ago that the relationship has gone cold - back to stranger.
	if ( ff_dir_is_former_customer( $user->ID ) ) {
		return 'none';
	}

	if ( ff_dir_access_expired( $user->ID ) ) {
		return 'expired';
	}

	// Past the paid period but inside grace: still has access, but must be told.
	if ( ff_dir_in_grace( $user->ID ) && ff_dir_user_can( 'search', $user->ID ) ) {
		return 'grace';
	}

	// Has the role and is in date, but every door is shut - treat as lapsed
	// rather than as a stranger, because they still have a relationship.
	return ff_dir_user_can( 'search', $user->ID ) ? 'full' : 'expired';
}

/**
 * The date access actually ends (paid period PLUS grace), formatted for humans.
 * This is the date to show a customer - quoting the paid-through date while
 * they still have grace left would understate what they have.
 */
function ff_dir_access_expiry_date( $user_id = null ) {

	$user = $user_id ? get_user_by( 'id', (int) $user_id ) : wp_get_current_user();

	if ( ! $user || ! $user->ID ) {
		return '';
	}

	$cutoff = ff_dir_hard_cutoff( $user->ID );

	return $cutoff ? date_i18n( get_option( 'date_format' ), $cutoff ) : '';
}

/**
 * Where a lapsed customer goes to renew. Separate seam from the "request
 * access" URL a stranger gets - renewing is a different conversation.
 */
function ff_dir_renew_url() {
	return apply_filters( 'ff_dir_renew_url', ff_dir_redirect_url( array( 'from' => 'expired' ) ) );
}

/**
 * Convenience wrapper - "is this a live member at all", regardless of doors.
 */
function ff_dir_is_member( $user_id = null ) {

	$user = $user_id ? get_user_by( 'id', (int) $user_id ) : wp_get_current_user();

	if ( ! $user || ! $user->ID ) {
		return false;
	}

	if ( user_can( $user, 'manage_options' ) ) {
		return true;
	}

	return in_array( FF_DIR_ROLE, (array) $user->roles, true ) && ! ff_dir_access_expired( $user->ID );
}

/**
 * Where a blocked visitor is sent.
 *
 * Stored as an option so the Directory overview page can be pointed at without
 * touching code:
 *     wp option update ff_dir_gate_redirect 'https://freightforge.com/directory/'
 *
 * @param array $args Query args to append, e.g. array( 'from' => 'carrier' ).
 * @return string
 */
function ff_dir_redirect_url( $args = array() ) {

	$base = get_option( 'ff_dir_gate_redirect', 'https://freightforge.com/directory/' );
	$base = trim( (string) $base );

	if ( '' === $base ) {
		$base = home_url( '/' );
	}

	if ( ! empty( $args ) ) {
		$base = add_query_arg( array_map( 'rawurlencode', $args ), $base );
	}

	return apply_filters( 'ff_dir_redirect_url', $base, $args );
}

/**
 * ============================================================
 * GATE 1 - carrier and lane singles
 *
 * Priority 0 on template_redirect, before Bricks renders anything.
 * 302 and never 301: a 301 is cached by the browser permanently, so opening
 * access later would still bounce returning visitors with nothing to clear.
 * ============================================================
 */
add_action( 'template_redirect', 'ff_dir_gate_singles', 0 );
function ff_dir_gate_singles() {

	if ( ! ff_dir_gate_enabled() ) {
		return;
	}

	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	if ( ! is_singular( ff_dir_gated_post_types() ) ) {
		return;
	}

	if ( ff_dir_user_can( 'search' ) ) {
		return;
	}

	$post = get_queried_object();

	$url = ff_dir_redirect_url( array(
		'from' => is_singular( 'lane' ) ? 'lane' : 'carrier',
	) );

	nocache_headers();
	wp_redirect( $url, 302 );
	exit;
}

/**
 * ============================================================
 * GATE 2 - WP REST
 *
 * carriers and lane are registered with show_in_rest = true, so
 * /wp-json/wp/v2/carriers serves the whole dataset to anyone. Gated by
 * wrapping each route's permission_callback rather than via
 * rest_pre_dispatch, which other plugins on this stack swallow.
 * ============================================================
 */
add_filter( 'rest_endpoints', 'ff_dir_gate_rest_endpoints', 99 );
function ff_dir_gate_rest_endpoints( $endpoints ) {

	if ( ! ff_dir_gate_enabled() ) {
		return $endpoints;
	}

	foreach ( ff_dir_gated_post_types() as $type ) {

		$object = get_post_type_object( $type );

		if ( ! $object || empty( $object->show_in_rest ) ) {
			continue;
		}

		$namespace = ! empty( $object->rest_namespace ) ? $object->rest_namespace : 'wp/v2';
		$base      = ! empty( $object->rest_base ) ? $object->rest_base : $type;

		$routes = array(
			'/' . $namespace . '/' . $base,
			'/' . $namespace . '/' . $base . '/(?P<id>[\d]+)',
		);

		foreach ( $routes as $route ) {

			if ( ! isset( $endpoints[ $route ] ) ) {
				continue;
			}

			foreach ( $endpoints[ $route ] as $index => $handler ) {

				// A route array also carries scalar keys such as 'namespace' alongside
				// its numeric handler entries - only real handlers have a callback.
				if ( ! is_array( $handler ) || ! isset( $handler['callback'] ) ) {
					continue;
				}

				$original = isset( $handler['permission_callback'] ) ? $handler['permission_callback'] : null;

				$endpoints[ $route ][ $index ]['permission_callback'] = function ( $request ) use ( $original ) {

					if ( ! ff_dir_user_can( 'search' ) ) {
						return new WP_Error(
							'ff_dir_access_required',
							'Directory access required.',
							array( 'status' => is_user_logged_in() ? 403 : 401 )
						);
					}

					return is_callable( $original ) ? call_user_func( $original, $request ) : true;
				};
			}
		}
	}

	return $endpoints;
}

/**
 * ============================================================
 * GATE 3 - the archive teaser
 *
 * A non-member keeps every filter and every count. What is stripped is the
 * identity of the rows: name, USDOT, MC, DBA, address, contacts. Zone,
 * equipment, authority status and a power-unit BAND stay public, because the
 * teaser is the sales asset - a prospect has to be able to prove the coverage
 * exists on their corridor before they will pay for the names.
 *
 * Everything below hangs off ONE Bricks hook: bricks/dynamic_data/format_value
 * ( $value, $tag, $post_id, $filters, $context ), fired from
 * Base::format_value_for_context(). Verified on Bricks 2.3.9 to carry the clean
 * tag name for BOTH the Meta Box provider and the core WP provider, so a single
 * filter covers tpl 65 (archive loop), tpl 91 (live single), tpl 2161 (draft
 * redesign) and any page built later.
 *
 * The FacetWP refresh response needs no separate work: the loop renders back
 * through these same filters, so the AJAX HTML is redacted by construction.
 * Facet OPTIONS are the separate job - see ff_dir_strip_identity_facets().
 * ============================================================
 */

/**
 * Carrier fields a non-member may see. This is an ALLOW-LIST on purpose.
 *
 * dlrh_carrier_information carries 160+ columns - tax_id_number, duns_number,
 * insurance_policy_number, the factoring company's full address, two sets of
 * government contact names and numbers. A deny-list would be correct only until
 * the next field lands in a template. Anything not named here is redacted.
 */
function ff_dir_public_carrier_fields() {
	return apply_filters( 'ff_dir_public_carrier_fields', array(
		'company_location_zone',
		'equipment_type',
		'carrier_operation',
		'usdot_status',
		'operating_authority_status',
		'allowed_to_operate',
		'number_of_power_units', // Never raw - banded by ff_dir_band_power_units().
	) );
}

/**
 * Core WP tags that leak identity when the post is a carrier: the title IS the
 * carrier name, and the slug is derived from it, so an href alone gives the
 * name away even when the anchor text has been redacted.
 */
function ff_dir_identity_wp_tags() {
	return apply_filters( 'ff_dir_identity_wp_tags', array(
		'post_title', 'post_name', 'post_slug', 'post_excerpt',
		'post_content', 'post_url', 'post_link', 'permalink',
	) );
}

/**
 * Is the current viewer being shown the teaser rather than the real data?
 *
 * Cached per user for the request - format_value fires once per tag per row,
 * so this is called a few hundred times on a 25-row archive.
 */
function ff_dir_teaser_active() {

	static $cache = array();

	if ( ! ff_dir_gate_enabled() ) {
		return false;
	}

	$uid = get_current_user_id();

	if ( ! isset( $cache[ $uid ] ) ) {
		$cache[ $uid ] = ! ff_dir_user_can( 'search' );
	}

	return $cache[ $uid ];
}

/**
 * Power units as a band, never an exact count.
 *
 * An exact fleet size plus a zone plus an equipment type starts to identify a
 * small carrier - three trucks running reefer out of zone 5 is a short list.
 *
 * Labels use an ASCII hyphen, not an en dash: this copy passes through Bricks
 * dynamic data, where non-ASCII breaks the @fallback filter.
 */
function ff_dir_band_power_units( $value ) {

	$raw = is_array( $value ) ? '' : (string) $value;
	$n   = (int) preg_replace( '/[^0-9]/', '', $raw );

	if ( $n < 1 ) {
		return apply_filters( 'ff_dir_power_unit_band', 'not listed', $n, $value );
	}

	$bands = array(
		array( 10, '1-10' ),
		array( 25, '11-25' ),
		array( 50, '26-50' ),
		array( 100, '51-100' ),
		array( 250, '101-250' ),
		array( 500, '251-500' ),
	);

	$label = '501+';

	foreach ( $bands as $band ) {
		if ( $n <= $band[0] ) {
			$label = $band[1];
			break;
		}
	}

	return apply_filters( 'ff_dir_power_unit_band', $label, $n, $value );
}

/**
 * What a redacted field renders as. Empty by default.
 *
 * Deliberately empty rather than a "member only" string: the locked-state copy
 * belongs to the Bricks layout, which can position it once per card instead of
 * repeating it on every stripped field. An empty value is also the safe failure
 * mode if the teaser layout has not been built yet - the card looks unfinished,
 * but it does not leak.
 */
function ff_dir_redacted_value( $value, $tag, $post_id, $context ) {

	$replacement = is_array( $value ) ? array() : '';

	return apply_filters( 'ff_dir_redacted_value', $replacement, $tag, $post_id, $context, $value );
}

add_filter( 'bricks/dynamic_data/format_value', 'ff_dir_redact_dynamic_value', 999, 5 );
function ff_dir_redact_dynamic_value( $value, $tag, $post_id, $filters = array(), $context = 'text' ) {

	if ( ! is_string( $tag ) || '' === $tag ) {
		return $value;
	}

	if ( ! ff_dir_teaser_active() ) {
		return $value;
	}

	// Meta Box carrier fields arrive as mb_carriers_<field>.
	if ( 0 === strpos( $tag, 'mb_carriers_' ) ) {

		$field = substr( $tag, strlen( 'mb_carriers_' ) );

		if ( 'number_of_power_units' === $field ) {
			return ff_dir_band_power_units( $value );
		}

		if ( in_array( $field, ff_dir_public_carrier_fields(), true ) ) {
			return $value;
		}

		return ff_dir_redacted_value( $value, $tag, $post_id, $context );
	}

	$post_type = $post_id ? get_post_type( $post_id ) : '';

	if ( ! in_array( $post_type, ff_dir_gated_post_types(), true ) ) {
		return $value;
	}

	if ( ! in_array( $tag, ff_dir_identity_wp_tags(), true ) ) {
		return $value;
	}

	// Link-shaped tags become the overview page rather than an empty href, so
	// the archive's "View Listing" button turns into a route to the sales page
	// instead of a dead link. Bricks writes the tag value straight into href.
	$link_tags = apply_filters(
		'ff_dir_identity_link_tags',
		array( 'post_url', 'post_link', 'permalink', 'post_slug', 'post_name' )
	);

	if ( in_array( $tag, $link_tags, true ) ) {
		return ff_dir_redirect_url( array( 'from' => 'lane' === $post_type ? 'lane' : 'carrier' ) );
	}

	return ff_dir_redacted_value( $value, $tag, $post_id, $context );
}

/**
 * ============================================================
 * FOREIGN identity tags - dynamic tags owned by OTHER plugins.
 *
 * A carrier's LOGO identifies it as surely as its name does. On production the
 * archive card renders one through {ff_logos_approved_id}, which belongs to
 * ff-carrier-logos, not to Meta Box - so the allow-list above never sees it.
 *
 * ff-carrier-logos resolves its tags on `bricks/dynamic_data/render_tag` at
 * priority 99. That hook's signature is ( $value, $post, $context ) with NO tag
 * argument, so a late filter cannot tell which tag produced a value. Hence the
 * two-step: record the raw tag at priority 1 (before any provider touches it),
 * then blank the resolved value at priority 200.
 *
 * ⚠️ Image context must be emptied with an ARRAY, not ''. Bricks' Image element
 * reads $value[0]; on a bare string PHP would index it character-by-character.
 * ============================================================
 */
function ff_dir_foreign_identity_tag_prefixes() {
	return apply_filters( 'ff_dir_foreign_identity_tag_prefixes', array(
		'ff_logos_approved_', // ff-carrier-logos: approved logo id / url / thumbnail / medium
	) );
}

function ff_dir_is_foreign_identity_tag( $tag ) {

	$tag = is_string( $tag ) ? trim( $tag, '{}' ) : '';

	if ( '' === $tag ) {
		return false;
	}

	foreach ( ff_dir_foreign_identity_tag_prefixes() as $prefix ) {
		if ( 0 === strpos( $tag, $prefix ) ) {
			return true;
		}
	}

	return false;
}

add_filter( 'bricks/dynamic_data/render_tag', 'ff_dir_record_raw_tag', 1, 3 );
function ff_dir_record_raw_tag( $tag, $post = null, $context = 'text' ) {

	$GLOBALS['ff_dir_raw_tag'] = is_string( $tag ) ? $tag : '';

	return $tag;
}

add_filter( 'bricks/dynamic_data/render_tag', 'ff_dir_blank_foreign_identity', 200, 3 );
function ff_dir_blank_foreign_identity( $value, $post = null, $context = 'text' ) {

	if ( ! ff_dir_teaser_active() ) {
		return $value;
	}

	$raw = isset( $GLOBALS['ff_dir_raw_tag'] ) ? $GLOBALS['ff_dir_raw_tag'] : '';

	if ( ! ff_dir_is_foreign_identity_tag( $raw ) ) {
		return $value;
	}

	// Emptying the tag also switches off the element: the archive card's image
	// carries a native Bricks condition "{ff_logos_approved_id} is not empty",
	// so a blank value hides the whole figure rather than leaving a broken img.
	return ( 'image' === $context ) ? array() : '';
}

/**
 * Same leak by the shortcode route - ff-carrier-logos also ships
 * [ff_carrier_logo], which emits an <img> straight into content.
 */
add_filter( 'do_shortcode_tag', 'ff_dir_gate_logo_shortcode', 10, 2 );
function ff_dir_gate_logo_shortcode( $output, $tag ) {

	if ( 'ff_carrier_logo' === $tag && ff_dir_teaser_active() ) {
		return '';
	}

	return $output;
}

/**
 * And the third route: {ff_logos_*} tokens embedded inside a text element.
 * Priority 1 so the tokens are gone before ff-carrier-logos expands them.
 */
add_filter( 'bricks/dynamic_data/render_content', 'ff_dir_strip_foreign_identity_tokens', 1, 3 );
function ff_dir_strip_foreign_identity_tokens( $content, $post = null, $context = 'text' ) {

	if ( ! is_string( $content ) || '' === $content || ! ff_dir_teaser_active() ) {
		return $content;
	}

	foreach ( ff_dir_foreign_identity_tag_prefixes() as $prefix ) {

		if ( false === strpos( $content, $prefix ) ) {
			continue;
		}

		$content = preg_replace(
			'/\{+' . preg_quote( $prefix, '/' ) . '[a-z0-9_]*(?::[^}]*)?\}+/i',
			'',
			$content
		);
	}

	return $content;
}

/**
 * Permalink masking - the same leak as {post_slug}, by the other route.
 *
 * Carrier slugs are name-derived (c-e-s-trucking-llc), so any real permalink
 * handed to a non-member gives up the name regardless of the anchor text.
 */
add_filter( 'post_type_link', 'ff_dir_mask_permalink', 10, 2 );
function ff_dir_mask_permalink( $url, $post ) {

	if ( ! $post || ! in_array( get_post_type( $post ), ff_dir_gated_post_types(), true ) ) {
		return $url;
	}

	if ( ! ff_dir_teaser_active() ) {
		return $url;
	}

	return ff_dir_redirect_url( array(
		'from' => 'lane' === get_post_type( $post ) ? 'lane' : 'carrier',
	) );
}

/**
 * Identity facets. usdot_number is an autocomplete facet whose AJAX endpoint
 * will serve USDOT numbers on request, which is the whole identity of a row.
 * Removed from the facet registry for non-members so neither the markup nor
 * the endpoint knows the facet exists.
 */
function ff_dir_identity_facets() {
	return apply_filters( 'ff_dir_identity_facets', array(
		'usdot_number', 'mc_number', 'carrier_name', 'carrier_legal_name',
	) );
}

add_filter( 'facetwp_facets', 'ff_dir_strip_identity_facets' );
function ff_dir_strip_identity_facets( $facets ) {

	if ( ! ff_dir_teaser_active() ) {
		return $facets;
	}

	$strip = ff_dir_identity_facets();

	foreach ( $facets as $index => $facet ) {
		if ( isset( $facet['name'] ) && in_array( $facet['name'], $strip, true ) ) {
			unset( $facets[ $index ] );
		}
	}

	return array_values( $facets );
}

/**
 * Feeds. /carriers/feed/ serves carrier names as <item><title> with no template
 * involved at all, so none of the filters above touch it. Closed outright for
 * non-members - the Directory is data, not content anyone should be syndicating.
 */
add_action( 'template_redirect', 'ff_dir_gate_feeds', 0 );
function ff_dir_gate_feeds() {

	if ( ! ff_dir_gate_enabled() ) {
		return;
	}

	if ( ! is_feed() ) {
		return;
	}

	if ( ff_dir_user_can( 'search' ) ) {
		return;
	}

	$types = array_filter( (array) get_query_var( 'post_type' ) );

	if ( is_singular( ff_dir_gated_post_types() ) ) {
		$types[] = get_post_type( get_queried_object_id() );
	}

	if ( ! array_intersect( $types, ff_dir_gated_post_types() ) ) {
		return;
	}

	nocache_headers();
	wp_redirect( ff_dir_redirect_url( array( 'from' => 'feed' ) ), 302 );
	exit;
}

/**
 * ============================================================
 * Bricks build kit
 *
 * Native Bricks conditions handle LAYOUT - which loop variant renders, whether
 * a block is visible. These shortcodes handle DATA and per-feature copy. A
 * condition must never be the only thing between anonymous and a phone number;
 * the redaction filter above is what actually keeps the value out of the HTML.
 *
 *   [ff_dir_gate feature="contacts"]...[/ff_dir_gate]   member-side content
 *   [ff_dir_locked feature="contacts"]                  locked-state copy + link
 *   [ff_dir_member_only]...[/ff_dir_member_only]
 *   [ff_dir_guest_only]...[/ff_dir_guest_only]
 *   [ff_dir_units]                                      exact or banded
 *
 * No [ff_dir_count]: freightforge-search-ui already ships [ff_results], and the
 * count is identical for members and non-members by design.
 * ============================================================
 */

add_shortcode( 'ff_dir_gate', 'ff_dir_sc_gate' );
function ff_dir_sc_gate( $atts, $content = '' ) {

	$atts = shortcode_atts( array( 'feature' => 'search' ), $atts, 'ff_dir_gate' );

	if ( ! ff_dir_user_can( $atts['feature'] ) ) {
		return '';
	}

	return do_shortcode( $content );
}

add_shortcode( 'ff_dir_locked', 'ff_dir_sc_locked' );
function ff_dir_sc_locked( $atts ) {

	$atts = shortcode_atts( array(
		'feature'      => 'search',
		'text'         => 'Carrier names, USDOT and contacts are member-only',
		'link'         => 'Request access',
		'expired_text' => 'Your Directory access has ended',
		'expired_link' => 'Renew access',
	), $atts, 'ff_dir_locked' );

	if ( ff_dir_user_can( $atts['feature'] ) ) {
		return '';
	}

	// A lapsed customer must never be told to "request access" - they already
	// bought it. Different copy, different destination.
	$expired = ( 'expired' === ff_dir_access_state() );

	if ( $expired ) {
		$date  = ff_dir_access_expiry_date();
		$text  = $atts['expired_text'] . ( $date ? ' on ' . $date : '' );
		$label = $atts['expired_link'];
		$href  = ff_dir_renew_url();
		$class = 'ff-dir-locked ff-dir-locked--expired';
	} else {
		$text  = $atts['text'];
		$label = $atts['link'];
		$href  = ff_dir_redirect_url( array( 'from' => 'locked' ) );
		$class = 'ff-dir-locked';
	}

	$html = '<span class="' . esc_attr( $class ) . '">' . esc_html( $text ) . '</span>';

	if ( '' !== $label ) {
		$html .= ' <a class="ff-dir-locked__link" href="' . esc_url( $href ) . '">'
			. esc_html( $label ) . '</a>';
	}

	return $html;
}

/**
 * A page-level banner for a lapsed customer, so the explanation appears once at
 * the top rather than only as a repeated line inside every card.
 *
 *   [ff_dir_expired_notice]
 */
add_shortcode( 'ff_dir_account_notice', 'ff_dir_sc_account_notice' );
add_shortcode( 'ff_dir_expired_notice', 'ff_dir_sc_account_notice' ); // earlier name, kept working
function ff_dir_sc_account_notice( $atts ) {

	$atts = shortcode_atts( array(
		'link' => 'Contact us to renew',
	), $atts, 'ff_dir_account_notice' );

	$state = ff_dir_access_state();

	if ( 'grace' !== $state && 'expired' !== $state ) {
		return '';
	}

	$user_id = get_current_user_id();

	if ( 'grace' === $state ) {

		$left = ff_dir_grace_days_remaining( $user_id );
		$end  = ff_dir_access_expiry_date(); // the hard cutoff, grace included

		$message = sprintf(
			'Your payment is overdue. Your Directory access continues for %s more %s%s.',
			number_format_i18n( $left ),
			1 === $left ? 'day' : 'days',
			$end ? ' (until ' . $end . ')' : ''
		);

		$class = 'ff-dir-account-notice ff-dir-account-notice--grace';

	} else {

		$date = ff_dir_access_expiry_date();

		$message = ( $date ? sprintf( 'Your Directory access ended on %s.', $date ) : 'Your Directory access is no longer active.' )
			. ' You are still signed in, but carrier names, contacts and export are paused until it is renewed.';

		$class = 'ff-dir-account-notice ff-dir-account-notice--expired';
	}

	return '<div class="' . esc_attr( $class ) . '">'
		. '<span class="ff-dir-account-notice__text">' . esc_html( $message ) . '</span> '
		. '<a class="ff-dir-account-notice__link" href="' . esc_url( ff_dir_renew_url() ) . '">'
		. esc_html( $atts['link'] ) . '</a>'
		. '</div>';
}

add_shortcode( 'ff_dir_member_only', 'ff_dir_sc_member_only' );
function ff_dir_sc_member_only( $atts, $content = '' ) {
	return ff_dir_is_member() ? do_shortcode( $content ) : '';
}

add_shortcode( 'ff_dir_guest_only', 'ff_dir_sc_guest_only' );
function ff_dir_sc_guest_only( $atts, $content = '' ) {
	return ff_dir_is_member() ? '' : do_shortcode( $content );
}

/**
 * Hide the WordPress admin bar from customers.
 *
 * A paying Directory member was seeing the WP admin bar, including a
 * "Dashboard" link that only dead-ends at wp-login.php (wp-admin is already
 * blocked for them). It is also the ONLY logout affordance, which is why
 * [ff_dir_account_bar] has to land at the same time.
 *
 * Done in code rather than per-user in wp-admin: the "Show Toolbar when viewing
 * site" checkbox is per account, so it has to be remembered for every customer
 * ever provisioned, and a missed one shows a customer the WordPress chrome.
 */
add_filter( 'show_admin_bar', 'ff_dir_hide_admin_bar_for_customers', 99 );
function ff_dir_hide_admin_bar_for_customers( $show ) {

	if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) ) {
		return $show;
	}

	return apply_filters( 'ff_dir_show_admin_bar_for_customers', false );
}

/**
 * Account bar for the site header.
 *
 * Two jobs, both currently missing from the Directory:
 *   - a signed-in member has NO front-end way to log out. The only logout link
 *     lives in the WordPress admin bar, which should not be shown to customers
 *     at all - so hiding the bar without this would strand them.
 *   - an anonymous visitor on the teaser has no visible way to SIGN IN. A
 *     returning customer had to know the URL.
 *
 * Must be a shortcode, not a Bricks link: wp_logout_url() carries a per-request
 * nonce, so the href cannot be baked into a template.
 *
 * Login destination follows the Bricks "login page" setting, so it stays
 * correct if that page is ever swapped again.
 */
add_shortcode( 'ff_dir_account_bar', 'ff_dir_sc_account_bar' );
function ff_dir_sc_account_bar( $atts ) {

	$atts = shortcode_atts( array(
		'signed_in_label' => 'Signed in as',
		'logout_label'    => 'Log out',
		'login_label'     => 'Sign in',
	), $atts, 'ff_dir_account_bar' );

	// Never on the auth pages themselves. On the reset page it is actively
	// misleading: the header would say "Signed in as <whoever is logged in>"
	// while the form sets the password for whoever owns the key in the URL -
	// two different identities on one screen. On login/lost-password it is
	// just noise, and "Sign in" on the login page is circular.
	if ( ff_dir_is_auth_page() ) {
		return '';
	}

	$login_url = ff_dir_login_url();

	if ( ! is_user_logged_in() ) {
		return '<span class="ff-dir-account ff-dir-account--guest">'
			. '<a class="ff-dir-account__login" href="' . esc_url( $login_url ) . '">'
			. esc_html( $atts['login_label'] ) . '</a></span>';
	}

	$user = wp_get_current_user();
	$name = $user->display_name ? $user->display_name : $user->user_login;

	// Say so in the chrome too, otherwise "Signed in as ..." reads as a promise
	// of access the lapsed customer no longer has.
	$expired  = ( 'expired' === ff_dir_access_state() );
	$modifier = $expired ? ' ff-dir-account--expired' : '';
	$suffix   = $expired ? ' <span class="ff-dir-account__flag">(access expired)</span>' : '';

	return '<span class="ff-dir-account ff-dir-account--member' . $modifier . '">'
		. '<span class="ff-dir-account__who">' . esc_html( $atts['signed_in_label'] ) . ' '
		. '<strong>' . esc_html( $name ) . '</strong></span>' . $suffix . ' '
		. '<a class="ff-dir-account__logout" href="' . esc_url( wp_logout_url( $login_url ) ) . '">'
		. esc_html( $atts['logout_label'] ) . '</a></span>';
}

/**
 * Is the current request one of the Bricks auth pages (login / lost password /
 * reset password)? Read from the Bricks settings rather than hardcoded, so
 * swapping any of those pages needs no code change.
 */
function ff_dir_is_auth_page() {

	if ( is_admin() ) {
		return false;
	}

	$settings = get_option( 'bricks_global_settings', array() );

	$ids = array_filter( array_map( 'intval', array(
		isset( $settings['login_page'] ) ? $settings['login_page'] : 0,
		isset( $settings['lost_password_page'] ) ? $settings['lost_password_page'] : 0,
		isset( $settings['reset_password_page'] ) ? $settings['reset_password_page'] : 0,
	) ) );

	$ids = apply_filters( 'ff_dir_auth_page_ids', $ids );

	if ( empty( $ids ) ) {
		return false;
	}

	$current = (int) get_queried_object_id();

	return $current && in_array( $current, $ids, true );
}

/**
 * The site's real login page, read from the Bricks setting rather than
 * hardcoded. Falls back to wp_login_url() if Bricks has none configured.
 */
function ff_dir_login_url() {

	$settings = get_option( 'bricks_global_settings', array() );
	$page_id  = isset( $settings['login_page'] ) ? (int) $settings['login_page'] : 0;

	if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
		$url = get_permalink( $page_id );

		if ( $url ) {
			return apply_filters( 'ff_dir_login_url', $url );
		}
	}

	return apply_filters( 'ff_dir_login_url', wp_login_url() );
}

/**
 * Power units for the current row - exact for a member, banded for everyone
 * else. Reads through rwmb_meta(): number_of_power_units is a Meta Box field
 * but is NOT a column on dlrh_carrier_information, so get_post_meta() misses it.
 */
add_shortcode( 'ff_dir_units', 'ff_dir_sc_units' );
function ff_dir_sc_units( $atts ) {

	$atts = shortcode_atts( array( 'id' => 0, 'suffix' => '' ), $atts, 'ff_dir_units' );

	$post_id = (int) $atts['id'] ? (int) $atts['id'] : get_the_ID();

	if ( ! $post_id || ! function_exists( 'rwmb_meta' ) ) {
		return '';
	}

	$value = rwmb_meta( 'number_of_power_units', array(), $post_id );
	$value = is_array( $value ) ? '' : (string) $value;

	if ( '' === trim( $value ) ) {
		return '';
	}

	$out = ff_dir_shows_full_data( 'search' ) ? $value : ff_dir_band_power_units( $value );

	return esc_html( '' !== $atts['suffix'] ? $out . ' ' . $atts['suffix'] : $out );
}

/**
 * ============================================================
 * Bricks dynamic tags - for native CONDITIONS
 *
 * Bricks conditions cannot call a PHP function directly, so the gate exposes
 * itself as dynamic tags and the condition is a plain empty / empty_not test:
 *
 *     show the member layout    -> {ff_dir_can_search}  compare: empty_not
 *     show the teaser layout    -> {ff_dir_can_search}  compare: empty
 *
 * Same mechanism ff-broker-network already uses on this site for
 * {ff_is_single_network}, so the pattern is the site's own idiom.
 *
 * Layout follows the SAME decision function as the data, so the two can never
 * disagree - an expired member gets the teaser layout AND the teaser data,
 * rather than a member layout full of blanked fields.
 * ============================================================
 */

function ff_dir_bricks_tags() {
	return array(
		'ff_dir_can_search'        => 'FF Dir: Can Search',
		'ff_dir_is_member'         => 'FF Dir: Is Member',
		// One tag that returns full|grace|expired|none, so a single Bricks
		// condition ("equals grace") can drive a whole block, rather than
		// juggling several boolean tags.
		'ff_dir_access_state'      => 'FF Dir: Access State (full|grace|expired|none)',
		'ff_dir_is_expired'        => 'FF Dir: Access Expired',
		'ff_dir_is_grace'          => 'FF Dir: In Grace Period',
		'ff_dir_grace_days_left'   => 'FF Dir: Grace Days Remaining',
		'ff_dir_expiry_date'       => 'FF Dir: Access Expiry Date',
		'ff_dir_units'             => 'FF Dir: Power Units (exact or banded)',
		'ff_dir_request_access_url' => 'FF Dir: Request Access URL',
		'ff_dir_renew_url'         => 'FF Dir: Renew Access URL',
		// State-aware CTA, so ONE set of template elements serves both a
		// stranger and a lapsed customer. Without these the teaser card's copy
		// is static and tells an expired member to "request access".
		'ff_dir_locked_message'    => 'FF Dir: Locked Message (state-aware)',
		'ff_dir_cta_label'         => 'FF Dir: CTA Label (state-aware)',
		'ff_dir_cta_url'           => 'FF Dir: CTA URL (state-aware)',
	);
}

/**
 * Returns null for anything that is not one of ours, so the caller can pass
 * the tag through untouched.
 */
function ff_dir_resolve_bricks_tag( $tag_key ) {

	// These drive LAYOUT, so they answer "what will this visitor actually see".
	// With the gate off that is the full public site, teaser layout included -
	// which is what makes the template edits safe to apply BEFORE going live.
	if ( 'ff_dir_can_search' === $tag_key ) {
		return ff_dir_shows_full_data( 'search' ) ? '1' : '';
	}

	if ( 'ff_dir_is_member' === $tag_key ) {
		return ( ! ff_dir_gate_enabled() || ff_dir_is_member() ) ? '1' : '';
	}

	if ( 'ff_dir_units' === $tag_key ) {
		return ff_dir_sc_units( array() );
	}

	// Lets the teaser CTA point at the overview page WITHOUT hardcoding it into
	// the template - the destination stays a one-line wp option update.
	if ( 'ff_dir_request_access_url' === $tag_key ) {
		return ff_dir_redirect_url( array( 'from' => 'carrier' ) );
	}

	if ( 'ff_dir_is_expired' === $tag_key ) {
		return ( 'expired' === ff_dir_access_state() ) ? '1' : '';
	}

	if ( 'ff_dir_access_state' === $tag_key ) {
		return ff_dir_access_state();
	}

	if ( 'ff_dir_is_grace' === $tag_key ) {
		return ( 'grace' === ff_dir_access_state() ) ? '1' : '';
	}

	if ( 'ff_dir_grace_days_left' === $tag_key ) {
		$left = ff_dir_grace_days_remaining( get_current_user_id() );
		return $left ? (string) $left : '';
	}

	if ( 'ff_dir_expiry_date' === $tag_key ) {
		return ff_dir_access_expiry_date();
	}

	if ( 'ff_dir_renew_url' === $tag_key ) {
		return ff_dir_renew_url();
	}

	if ( 'ff_dir_locked_message' === $tag_key ) {

		if ( 'expired' === ff_dir_access_state() ) {
			$date = ff_dir_access_expiry_date();
			return $date
				? sprintf( 'Your access ended on %s', $date )
				: 'Your access is no longer active';
		}

		return 'Carrier names, USDOT and contacts are member-only';
	}

	if ( 'ff_dir_cta_label' === $tag_key ) {
		return ( 'expired' === ff_dir_access_state() ) ? 'Renew access' : 'Request access';
	}

	if ( 'ff_dir_cta_url' === $tag_key ) {
		return ( 'expired' === ff_dir_access_state() )
			? ff_dir_renew_url()
			: ff_dir_redirect_url( array( 'from' => 'carrier' ) );
	}

	return null;
}

add_filter( 'bricks/dynamic_tags_list', 'ff_dir_register_bricks_tags' );
function ff_dir_register_bricks_tags( $tags ) {

	foreach ( ff_dir_bricks_tags() as $key => $label ) {
		$tags[] = array(
			'name'  => '{' . $key . '}',
			'label' => $label,
			'group' => 'FreightForge Directory Gate',
		);
	}

	return $tags;
}

add_filter( 'bricks/dynamic_data/render_tag', 'ff_dir_bricks_render_tag', 10, 3 );
function ff_dir_bricks_render_tag( $tag, $post = null, $context = 'text' ) {

	$resolved = is_string( $tag ) ? ff_dir_resolve_bricks_tag( $tag ) : null;

	return ( null === $resolved ) ? $tag : $resolved;
}

add_filter( 'bricks/dynamic_data/render_content', 'ff_dir_bricks_render_content', 10, 3 );
function ff_dir_bricks_render_content( $content, $post = null, $context = 'text' ) {

	if ( ! is_string( $content ) || false === strpos( $content, '{ff_dir_' ) ) {
		return $content;
	}

	foreach ( array_keys( ff_dir_bricks_tags() ) as $key ) {

		$token = '{' . $key . '}';

		if ( false !== strpos( $content, $token ) ) {
			$content = str_replace( $token, (string) ff_dir_resolve_bricks_tag( $key ), $content );
		}
	}

	return $content;
}

/**
 * ============================================================
 * GRACE REMINDER
 *
 * A customer whose payment has lapsed keeps working, so they have no reason to
 * visit the site and discover the banner. Without an email the first thing they
 * notice is the day access stops - which is the worst possible moment to find
 * out. This sends one reminder per billing period.
 *
 * Deliberately date-driven and independent of any billing system: it fires off
 * paid_through, the same field everything else uses. When a billing engine is
 * chosen this can be replaced by its own dunning email without touching the gate.
 *
 * Sends ONCE per period. The marker stores the paid-through date it was sent
 * for, so renewing (which moves that date) automatically re-arms it.
 * ============================================================
 */
add_action( 'init', 'ff_dir_schedule_grace_reminder' );
function ff_dir_schedule_grace_reminder() {

	if ( ! wp_next_scheduled( 'ff_dir_grace_reminder' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ff_dir_grace_reminder' );
	}
}

add_action( 'ff_dir_grace_reminder', 'ff_dir_send_grace_reminders' );
function ff_dir_send_grace_reminders() {

	if ( ! ff_dir_gate_enabled() ) {
		return 0;
	}

	$users = get_users( array(
		'role'   => FF_DIR_ROLE,
		'fields' => array( 'ID', 'user_email', 'display_name' ),
	) );

	$sent = 0;

	foreach ( $users as $user ) {

		if ( 'grace' !== ff_dir_access_state( $user->ID ) ) {
			continue;
		}

		$period = ff_dir_paid_through( $user->ID );

		// Already told them about THIS period.
		if ( get_user_meta( $user->ID, 'ff_dir_grace_notified', true ) === $period ) {
			continue;
		}

		if ( ff_dir_send_grace_email( $user ) ) {
			update_user_meta( $user->ID, 'ff_dir_grace_notified', $period );
			$sent++;
		}
	}

	return $sent;
}

function ff_dir_send_grace_email( $user ) {

	$left = ff_dir_grace_days_remaining( $user->ID );
	$ends = ff_dir_access_expiry_date( $user->ID );
	$name = $user->display_name ? $user->display_name : $user->user_email;

	$subject = apply_filters(
		'ff_dir_grace_email_subject',
		'Your FreightForge Directory access needs renewing',
		$user
	);

	$body = sprintf(
		"Hi %s,\n\n"
		. "We haven't received your latest payment for the FreightForge Directory.\n\n"
		. "Your access is still working - you have %d %s remaining, until %s. "
		. "After that, carrier names, contact details and export will pause until the account is brought up to date.\n\n"
		. "To renew, reply to this email or call (612) 804-0064.\n\n"
		. "Sign in: %s\n\n"
		. "- The FreightForge team",
		$name,
		$left,
		1 === $left ? 'day' : 'days',
		$ends,
		ff_dir_login_url()
	);

	$body = apply_filters( 'ff_dir_grace_email_body', $body, $user, $left, $ends );

	return wp_mail( $user->user_email, $subject, $body );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'ff-dir grace-reminders', function () {
		$sent = ff_dir_send_grace_reminders();
		WP_CLI::success( 'grace reminders sent: ' . (int) $sent );
	} );
}

/**
 * ============================================================
 * ADMIN - customer billing fields on the user profile.
 *
 * Users > Edit User > "Directory access". Gives the per-customer grace-period
 * dropdown, the billing cycle and the paid-through date without needing WP-CLI,
 * so a non-technical admin can onboard and renew.
 * ============================================================
 */
add_action( 'show_user_profile', 'ff_dir_render_profile_fields' );
add_action( 'edit_user_profile', 'ff_dir_render_profile_fields' );
function ff_dir_render_profile_fields( $user ) {

	if ( ! current_user_can( 'edit_users' ) ) {
		return;
	}

	$paid   = ff_dir_paid_through( $user->ID );
	$grace  = ff_dir_grace_days( $user->ID );
	$cycle  = get_user_meta( $user->ID, 'ff_dir_billing_cycle', true );
	$state  = ff_dir_access_state( $user->ID );
	$cutoff = ff_dir_hard_cutoff( $user->ID );

	$labels = array(
		'full'    => 'Active',
		'grace'   => 'In grace period',
		'expired' => 'Lapsed - sees "Renew access"',
		'none'    => 'Not a member - sees "Request access"',
	);

	echo '<h2>Directory access</h2>';
	echo '<table class="form-table" role="presentation"><tbody>';

	echo '<tr><th>Current state</th><td><strong>' . esc_html( $labels[ $state ] ?? $state ) . '</strong>';

	if ( $cutoff ) {
		echo '<p class="description">Access ends ' . esc_html( date_i18n( get_option( 'date_format' ), $cutoff ) ) . '.';
		if ( ff_dir_in_grace( $user->ID ) ) {
			echo ' <strong>' . (int) ff_dir_grace_days_remaining( $user->ID ) . ' day(s) of grace remaining.</strong>';
		}
		echo '</p>';
	}

	echo '</td></tr>';

	echo '<tr><th><label for="ff_dir_paid_through">Paid through</label></th><td>'
		. '<input type="date" name="ff_dir_paid_through" id="ff_dir_paid_through" value="' . esc_attr( $paid ) . '" />'
		. '<p class="description">End of the period the customer has paid for. Access continues past this by the grace period below.</p></td></tr>';

	echo '<tr><th><label for="ff_dir_grace_days">Grace period</label></th><td><select name="ff_dir_grace_days" id="ff_dir_grace_days">';
	foreach ( ff_dir_grace_options() as $opt ) {
		printf(
			'<option value="%1$d"%2$s>%3$s</option>',
			(int) $opt,
			selected( $grace, $opt, false ),
			0 === (int) $opt ? 'None' : esc_html( $opt . ' days' )
		);
	}
	echo '</select><p class="description">How long access continues after a missed payment.</p></td></tr>';

	echo '<tr><th><label for="ff_dir_billing_cycle">Billing cycle</label></th><td><select name="ff_dir_billing_cycle" id="ff_dir_billing_cycle">';
	echo '<option value="">&mdash;</option>';
	foreach ( ff_dir_billing_cycles() as $key => $label ) {
		printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $key ), selected( $cycle, $key, false ), esc_html( $label ) );
	}
	echo '</select><p class="description">Bookkeeping only - it does not affect access. It says what to advance "paid through" to on the next payment.</p></td></tr>';

	echo '</tbody></table>';

	wp_nonce_field( 'ff_dir_profile_save', 'ff_dir_profile_nonce' );
}

add_action( 'personal_options_update', 'ff_dir_save_profile_fields' );
add_action( 'edit_user_profile_update', 'ff_dir_save_profile_fields' );
function ff_dir_save_profile_fields( $user_id ) {

	if ( ! current_user_can( 'edit_users' ) ) {
		return;
	}

	if ( ! isset( $_POST['ff_dir_profile_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ff_dir_profile_nonce'] ) ), 'ff_dir_profile_save' ) ) {
		return;
	}

	if ( isset( $_POST['ff_dir_paid_through'] ) ) {
		$paid = sanitize_text_field( wp_unslash( $_POST['ff_dir_paid_through'] ) );
		$paid = ( '' === $paid || strtotime( $paid ) ) ? $paid : '';
		update_user_meta( $user_id, 'ff_dir_paid_through', $paid );
		update_user_meta( $user_id, 'ff_dir_access_expires', $paid ); // keep legacy key in step
	}

	if ( isset( $_POST['ff_dir_grace_days'] ) ) {
		update_user_meta( $user_id, 'ff_dir_grace_days', max( 0, (int) $_POST['ff_dir_grace_days'] ) );
	}

	if ( isset( $_POST['ff_dir_billing_cycle'] ) ) {
		$cycle = sanitize_text_field( wp_unslash( $_POST['ff_dir_billing_cycle'] ) );
		update_user_meta( $user_id, 'ff_dir_billing_cycle', array_key_exists( $cycle, ff_dir_billing_cycles() ) ? $cycle : '' );
	}
}

/**
 * ============================================================
 * WP-CLI provisioning
 *
 *   wp ff-dir grant <user> [--features=search,contacts,export] [--expires=2027-07-28]
 *   wp ff-dir revoke <user>
 *   wp ff-dir check <user> [--feature=contacts]
 * ============================================================
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {

	class FF_Dir_CLI {

		/**
		 * Grant directory membership.
		 *
		 * <user>
		 * : User ID, login or email.
		 *
		 * [--features=<list>]
		 * : Comma-separated doors. Default: search,contacts,export
		 *
		 * [--expires=<date>]
		 * : YYYY-MM-DD. Default: one year from today.
		 */
		public function grant( $args, $assoc ) {

			$user = $this->resolve( $args[0] );

			$features = isset( $assoc['features'] )
				? array_map( 'trim', explode( ',', $assoc['features'] ) )
				: ff_dir_features();

			$features = array_values( array_intersect( $features, ff_dir_features() ) );

			if ( empty( $features ) ) {
				WP_CLI::error( 'No valid features. Valid: ' . implode( ', ', ff_dir_features() ) );
			}

			// --paid-through is the new name; --expires still accepted.
			$paid = isset( $assoc['paid-through'] ) ? $assoc['paid-through']
				: ( isset( $assoc['expires'] ) ? $assoc['expires'] : gmdate( 'Y-m-d', strtotime( '+1 year' ) ) );

			if ( ! strtotime( $paid ) ) {
				WP_CLI::error( 'Could not parse the date. Use YYYY-MM-DD.' );
			}

			$grace = isset( $assoc['grace'] ) ? max( 0, (int) $assoc['grace'] ) : ff_dir_grace_days( $user->ID );
			$cycle = isset( $assoc['cycle'] ) ? strtolower( trim( $assoc['cycle'] ) ) : '';

			if ( $cycle && ! array_key_exists( $cycle, ff_dir_billing_cycles() ) ) {
				WP_CLI::error( 'Unknown --cycle. Valid: ' . implode( ', ', array_keys( ff_dir_billing_cycles() ) ) );
			}

			$user->add_role( FF_DIR_ROLE );

			$map = array();
			foreach ( ff_dir_features() as $f ) {
				$map[ $f ] = in_array( $f, $features, true ) ? 1 : 0;
			}

			update_user_meta( $user->ID, 'ff_dir_features', $map );
			update_user_meta( $user->ID, 'ff_dir_paid_through', $paid );
			update_user_meta( $user->ID, 'ff_dir_grace_days', $grace );

			if ( $cycle ) {
				update_user_meta( $user->ID, 'ff_dir_billing_cycle', $cycle );
			}

			// Keep the legacy key in step so anything still reading it agrees.
			update_user_meta( $user->ID, 'ff_dir_access_expires', $paid );

			WP_CLI::success( sprintf(
				'%s (#%d) granted [%s] paid through %s, grace %d day(s) -> access ends %s%s',
				$user->user_login,
				$user->ID,
				implode( ', ', $features ),
				$paid,
				$grace,
				date_i18n( 'Y-m-d', ff_dir_hard_cutoff( $user->ID ) ),
				$cycle ? ', cycle ' . $cycle : ''
			) );
		}

		/**
		 * Remove directory membership.
		 *
		 * <user>
		 * : User ID, login or email.
		 */
		public function revoke( $args ) {

			$user = $this->resolve( $args[0] );

			$user->remove_role( FF_DIR_ROLE );
			delete_user_meta( $user->ID, 'ff_dir_features' );
			delete_user_meta( $user->ID, 'ff_dir_access_expires' );

			WP_CLI::success( sprintf( '%s (#%d) revoked', $user->user_login, $user->ID ) );
		}

		/**
		 * Report what a user can do.
		 *
		 * <user>
		 * : User ID, login or email.
		 *
		 * [--feature=<feature>]
		 * : Check one door instead of all.
		 */
		public function check( $args, $assoc ) {

			$user     = $this->resolve( $args[0] );
			$features = isset( $assoc['feature'] ) ? array( $assoc['feature'] ) : ff_dir_features();
			$expires  = get_user_meta( $user->ID, 'ff_dir_access_expires', true );

			WP_CLI::log( sprintf(
				'gate mode: %s%s',
				ff_dir_gate_mode(),
				ff_dir_gate_enabled() ? '' : '  (nothing is gated - entitlements below are advisory)'
			) );

			$cutoff = ff_dir_hard_cutoff( $user->ID );
			$cycle  = get_user_meta( $user->ID, 'ff_dir_billing_cycle', true );

			WP_CLI::log( sprintf(
				'%s (#%d) roles=[%s]',
				$user->user_login,
				$user->ID,
				implode( ',', (array) $user->roles )
			) );

			WP_CLI::log( sprintf(
				'  state=%s  paid through=%s  grace=%d day(s)  access ends=%s%s',
				ff_dir_access_state( $user->ID ),
				ff_dir_paid_through( $user->ID ) ?: 'none',
				ff_dir_grace_days( $user->ID ),
				$cutoff ? date_i18n( 'Y-m-d', $cutoff ) : 'never',
				$cycle ? '  cycle=' . $cycle : ''
			) );

			if ( ff_dir_in_grace( $user->ID ) ) {
				WP_CLI::warning( sprintf( '  IN GRACE - %d day(s) remaining', ff_dir_grace_days_remaining( $user->ID ) ) );
			}

			foreach ( $features as $f ) {
				WP_CLI::log( sprintf( '  %-9s %s', $f, ff_dir_user_can( $f, $user->ID ) ? 'YES' : 'no' ) );
			}
		}

		private function resolve( $who ) {

			$user = is_numeric( $who )
				? get_user_by( 'id', (int) $who )
				: ( get_user_by( 'login', $who ) ?: get_user_by( 'email', $who ) );

			if ( ! $user ) {
				WP_CLI::error( 'User not found: ' . $who );
			}

			return $user;
		}
	}

	WP_CLI::add_command( 'ff-dir', 'FF_Dir_CLI' );
}

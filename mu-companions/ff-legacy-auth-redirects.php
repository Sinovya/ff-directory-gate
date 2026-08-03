<?php
/**
 * Plugin Name: FF Directory Entry Routing
 * Description: Front door for the Directory. Sends "/" to the carrier search, and 301s the superseded auth pages to the current Carrier Directory ones so old bookmarks and emailed links keep working. Directory site only.
 * Version: 1.1.0
 * Author: FreightForge
 *
 * The live auth pages are car-dir-login / car-dir-lost-password /
 * car-dir-password-reset (Bricks > Settings > General points at them since
 * 2026-07-31). The superseded Login / Lost Password / Password Reset pages are
 * still published, so anyone holding an old URL would otherwise land nowhere
 * useful.
 *
 * ⚠️ SCOPE GUARD: these FF sites each have their own mu-plugins directory and
 * several share filenames, so this file refuses to act anywhere except the
 * Directory. Copying it to another site is a no-op rather than a surprise
 * redirect on someone else's login page.
 *
 * ⚠️ /login/ is a special case: page 26657 "Login" is currently the site FRONT
 * PAGE, so WordPress canonically 301s /login/ to /. This runs at
 * template_redirect priority 0, ahead of redirect_canonical, so /login/ reaches
 * the real login page. It deliberately does NOT touch "/" itself — what the
 * front page should be is a separate decision.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Only ever run on the Directory. Host match, not a slug, because the map below
 * is specific to this site's pages.
 */
function ff_legacy_auth_is_directory_site() {

	$host = wp_parse_url( home_url(), PHP_URL_HOST );

	return is_string( $host ) && false !== strpos( $host, 'directory.freightforge.com' );
}

function ff_legacy_auth_map() {

	return apply_filters( 'ff_legacy_auth_redirect_map', array(
		'login'          => '/car-dir-login/',
		'lost-password'  => '/car-dir-lost-password/',
		'password-reset' => '/car-dir-password-reset/',
	) );
}

/**
 * Front door: "/" goes to the carrier search.
 *
 * The front page is currently page 26657 "Login" - the SUPERSEDED login page -
 * so anyone typing the bare domain landed on a dead-end form, and the
 * after-login router's fallback (home_url('/')) returned members to a login
 * screen. Sending "/" to the archive serves both audiences off one listing:
 * non-members get the teaser (the sales asset, with Request access CTAs),
 * members get their search.
 *
 * 302, deliberately not 301: where the front page should point is still an open
 * decision, and a 301 would be cached in browsers permanently with nothing to
 * clear. Same reasoning as the gate's carrier redirect.
 */
function ff_dir_front_door_target() {
	return apply_filters( 'ff_dir_front_door_target', '/carriers/' );
}

add_action( 'template_redirect', 'ff_dir_front_door_redirect', 0 );
function ff_dir_front_door_redirect() {

	if ( is_admin() || wp_doing_ajax() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	if ( ! ff_legacy_auth_is_directory_site() || ! is_front_page() ) {
		return;
	}

	$target = ff_dir_front_door_target();

	if ( '' === $target ) {
		return; // filtered off
	}

	$uri   = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	$path  = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );

	/*
	 * ONLY the true root. is_front_page() is also true for /login/, because the
	 * front page is page 26657 "Login" - so without this the front door would
	 * swallow /login/ and send someone with an old bookmark to the teaser
	 * instead of the login form, pre-empting the legacy rule below.
	 */
	if ( '' !== $path ) {
		return;
	}

	// Never redirect the target onto itself.
	if ( $path === trim( $target, '/' ) ) {
		return;
	}

	$dest  = home_url( $target );
	$query = wp_parse_url( $uri, PHP_URL_QUERY );

	if ( $query ) {
		$dest .= '?' . $query;
	}

	nocache_headers();
	wp_safe_redirect( $dest, 302 );
	exit;
}

add_action( 'template_redirect', 'ff_legacy_auth_redirect', 0 );
function ff_legacy_auth_redirect() {

	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	if ( ! ff_legacy_auth_is_directory_site() ) {
		return;
	}

	$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

	if ( '' === $uri ) {
		return;
	}

	$path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
	$map  = ff_legacy_auth_map();

	// Never touch the front page itself - only the explicit legacy slugs.
	if ( '' === $path || ! isset( $map[ $path ] ) ) {
		return;
	}

	$target = home_url( $map[ $path ] );

	// Do not redirect a page onto itself.
	if ( untrailingslashit( $target ) === untrailingslashit( home_url( '/' . $path . '/' ) ) ) {
		return;
	}

	// Preserve the query string: password-reset links carry ?key= and ?login=.
	$query = wp_parse_url( $uri, PHP_URL_QUERY );

	if ( $query ) {
		$target .= '?' . $query;
	}

	nocache_headers();
	wp_safe_redirect( $target, 301 );
	exit;
}

<?php
/**
 * Make the password-reset form on page 28256 actually reset a password.
 *
 * It was configured as a LOGIN form (actions ["login","redirect"]), so
 * submitting fed the two new-password fields to wp_authenticate() and returned
 * "Username or password is incorrect."
 *
 * Bricks' Reset_Password::run() needs THREE things, and the form had none:
 *   1. action 'reset-password'  - also what makes Bricks inject the hidden
 *      form-field-key / form-field-login inputs from the URL. Without the
 *      action those hidden fields are never rendered at all.
 *   2. setting 'resetPasswordNew' = the FIELD ID of the new-password input;
 *      the action reads $form_fields["form-field-{$id}"].
 *   3. the password inputs must POST as form-field-<id>. Ours carried custom
 *      name attributes (new_password / confirm_password), so they posted under
 *      those names and the lookup above would have missed them.
 *
 * Run: wp eval-file fix-reset-form-action.php [dry]
 */

global $wpdb;

$PAGE     = 28256;
$META     = '_bricks_page_content_2';
$FORM     = '41a558';
$NEW_ID   = 'ac9bf7'; // "Enter a New password"
$CONF_ID  = 'csokkl'; // "Confirm New Password"
$REDIRECT = 'https://directory.freightforge.com/car-dir-login/?reset=success';
$DRY      = in_array( 'dry', (array) ( $args ?? array() ), true );

$content = get_post_meta( $PAGE, $META, true );

if ( ! is_array( $content ) || empty( $content ) ) {
	WP_CLI::error( "page $PAGE has no $META" );
}

$bak = $META . '_bak_resetformaction';

if ( ! get_post_meta( $PAGE, $bak, true ) ) {
	update_post_meta( $PAGE, $bak, $content );
	WP_CLI::log( "backup -> postmeta $bak" );
} else {
	WP_CLI::log( "backup already exists at $bak" );
}

$found = false;

foreach ( $content as $i => $el ) {

	if ( $el['id'] !== $FORM ) {
		continue;
	}

	$found = true;
	$s     = $el['settings'];

	WP_CLI::log( '  BEFORE actions: ' . wp_json_encode( isset( $s['actions'] ) ? $s['actions'] : null ) );

	// 1. the action
	$s['actions'] = array( 'reset-password', 'redirect' );

	// 2. tell Bricks which field holds the new password
	$s['resetPasswordNew'] = $NEW_ID;

	// 3. drop custom names so the inputs post as form-field-<id>
	foreach ( (array) ( isset( $s['fields'] ) ? $s['fields'] : array() ) as $fi => $f ) {

		if ( ! isset( $f['id'] ) || ! in_array( $f['id'], array( $NEW_ID, $CONF_ID ), true ) ) {
			continue;
		}

		if ( isset( $f['name'] ) ) {
			WP_CLI::log( '  field ' . $f['id'] . ': dropping custom name "' . $f['name'] . '"' );
			unset( $s['fields'][ $fi ]['name'] );
		}
	}

	// 4. sensible post-reset behaviour, matching the page's own copy
	$s['redirect']       = $REDIRECT;
	$s['successMessage'] = 'Password updated. Redirecting you to sign in...';

	$content[ $i ]['settings'] = $s;

	WP_CLI::log( '  AFTER  actions: ' . wp_json_encode( $s['actions'] ) );
	WP_CLI::log( '  resetPasswordNew: ' . $s['resetPasswordNew'] );
	WP_CLI::log( '  redirect: ' . $s['redirect'] );
}

if ( ! $found ) {
	WP_CLI::error( "form $FORM not found on page $PAGE" );
}

if ( $DRY ) {
	WP_CLI::success( 'DRY RUN - nothing written.' );
	return;
}

$ok = $wpdb->update(
	$wpdb->postmeta,
	array( 'meta_value' => maybe_serialize( $content ) ),
	array( 'post_id' => $PAGE, 'meta_key' => $META )
);

if ( false === $ok ) {
	WP_CLI::error( 'DB write failed: ' . $wpdb->last_error );
}

clean_post_cache( $PAGE );
wp_cache_delete( $PAGE, 'post_meta' );

$check = get_post_meta( $PAGE, $META, true );

foreach ( (array) $check as $el ) {
	if ( $el['id'] === $FORM ) {
		WP_CLI::success( 'written. actions now ' . wp_json_encode( $el['settings']['actions'] )
			. ', resetPasswordNew=' . ( $el['settings']['resetPasswordNew'] ?? '(missing)' ) );
	}
}

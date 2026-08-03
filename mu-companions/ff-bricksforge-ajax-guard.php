<?php
/**
 * Plugin Name: FF Bricksforge AJAX Guard
 * Description: Requires an authenticated administrator for Bricksforge's email-designer test-mail AJAX action, which the plugin itself registers for wp_ajax_nopriv_ with no nonce and no capability check.
 * Version: 1.0.0
 * Author: FreightForge
 *
 * WHY
 * ---
 * bricksforge/includes/email-designer/EmailDesigner.php registers:
 *
 *     add_action('wp_ajax_brf_email_designer_test_mail',        [$this, 'send_test_mail']);
 *     add_action('wp_ajax_nopriv_brf_email_designer_test_mail', [$this, 'send_test_mail']);
 *
 * send_test_mail() performs NO nonce check and NO capability check, and takes
 * the recipient straight from $_POST['to']. Anyone on the internet can
 * therefore make the site send mail to an address of their choosing. The body
 * is a fixed template, so this is not an open spam relay, but it is outbound
 * mail from our domain at an attacker's request - which is a sender-reputation
 * problem, and it is trivially scriptable.
 *
 * SCOPE
 * -----
 * Deliberately narrow. Bricksforge's OTHER nopriv actions were checked and are
 * properly guarded - bricksforge_update_option and bricksforge_delete_option
 * verify a nonce AND capabilities, and bricksforge_send_mail requires
 * edit_posts. Guarding those too would risk breaking front-end forms for no
 * benefit, so only the one broken action is touched.
 *
 * NOT A NONCE CHECK
 * -----------------
 * We cannot require a nonce here: Bricksforge's own admin UI does not send one,
 * so demanding one would break the Send Test button for legitimate admins too.
 * An authenticated-administrator requirement is the strongest check that keeps
 * the feature working.
 *
 * Runs at priority 1, ahead of Bricksforge's own handler (default 10).
 * Survives plugin updates because it does not modify vendor files.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_nopriv_brf_email_designer_test_mail', 'ff_brf_guard_email_test', 1 );
add_action( 'wp_ajax_brf_email_designer_test_mail', 'ff_brf_guard_email_test', 1 );

function ff_brf_guard_email_test() {

	if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		return; // let Bricksforge handle it
	}

	if ( function_exists( 'error_log' ) ) {
		error_log( sprintf(
			'FF Bricksforge guard: blocked unauthorised email-designer test mail from %s',
			isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'
		) );
	}

	wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
}

<?php
/**
 * Exercise all four billing states against real users.
 *
 *   active   paid through the future
 *   grace    paid-through has passed, still inside the grace window
 *   lapsed   grace exhausted, under a year
 *   stranger lapsed more than a year (reverts to "never a customer")
 *
 * Creates its own throwaway users and deletes them again.
 *
 * Run: wp eval-file test-billing-states.php
 */

$cases = array(
	'active'   => array( 'paid' => '+30 days', 'grace' => 14 ),
	'grace'    => array( 'paid' => '-3 days',  'grace' => 14 ),
	'lapsed'   => array( 'paid' => '-40 days', 'grace' => 14 ),
	'stranger' => array( 'paid' => '-500 days', 'grace' => 14 ),
);

$made = array();

foreach ( $cases as $name => $cfg ) {

	$login = 'bstate_' . $name;
	$user  = get_user_by( 'login', $login );

	if ( ! $user ) {
		$id   = wp_insert_user( array(
			'user_login' => $login,
			'user_email' => $login . '@example.com',
			'user_pass'  => wp_generate_password( 16 ),
			'role'       => 'subscriber',
		) );
		$user = get_user_by( 'id', $id );
	}

	$made[] = $user->ID;

	$user->add_role( FF_DIR_ROLE );

	update_user_meta( $user->ID, 'ff_dir_features', array( 'search' => 1, 'contacts' => 1, 'export' => 1 ) );
	update_user_meta( $user->ID, 'ff_dir_paid_through', gmdate( 'Y-m-d', strtotime( $cfg['paid'] ) ) );
	update_user_meta( $user->ID, 'ff_dir_grace_days', $cfg['grace'] );
	update_user_meta( $user->ID, 'ff_dir_billing_cycle', 'monthly' );

	clean_user_cache( $user->ID );
	wp_set_current_user( $user->ID );

	$state = ff_dir_access_state();
	$left  = ff_dir_grace_days_remaining( $user->ID );

	WP_CLI::log( sprintf(
		'  %-9s paid_through=%s grace=%dd -> state=%-8s can_search=%-3s %s',
		$name,
		ff_dir_paid_through( $user->ID ),
		ff_dir_grace_days( $user->ID ),
		$state,
		ff_dir_user_can( 'search' ) ? 'YES' : 'no',
		$left ? "({$left} grace days left)" : ''
	) );

	// What the card would say.
	WP_CLI::log( '            message: "' . ff_dir_resolve_bricks_tag( 'ff_dir_locked_message' ) . '"'
		. '  CTA: "' . ff_dir_resolve_bricks_tag( 'ff_dir_cta_label' ) . '"' );

	$notice = trim( wp_strip_all_tags( do_shortcode( '[ff_dir_account_notice]' ) ) );
	WP_CLI::log( '            notice : ' . ( $notice ? '"' . preg_replace( '/\s+/', ' ', $notice ) . '"' : '(none)' ) );
	WP_CLI::log( '' );
}

wp_set_current_user( 0 );

foreach ( $made as $id ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $id );
}

WP_CLI::success( 'all states exercised; throwaway users removed.' );

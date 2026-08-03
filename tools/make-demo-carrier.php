<?php
/**
 * Create a wholly INVENTED carrier for marketing screenshots.
 *
 * The Directory overview page is public, so a screenshot of a real carrier
 * profile would publish that carrier's live phone number and email address.
 * This builds a fake one instead.
 *
 * Method: clone a real carrier's rows across all three Meta Box tables so every
 * field the template expects exists, then overwrite ALL identity, contact and
 * sensitive columns with fabricated values.
 *
 * Fabrication rules used:
 *   USDOT/MC 9999001  - above the real FMCSA issued range (~4.1M)
 *   (555) 010-xxxx    - the 555-01xx block reserved for fiction
 *   example.com       - RFC 2606 reserved domain
 *   street address    - invented; city/state are real so the map renders
 *
 * SANDBOX ONLY. Run: wp eval-file make-demo-carrier.php [dry]
 *
 * @package ff-directory-gate
 */

global $wpdb;

$DRY  = in_array( 'dry', (array) ( $args ?? array() ), true );
$SLUG = 'cedar-ridge-transport-llc-demo';
$NAME = 'Cedar Ridge Transport LLC';

if ( false === strpos( home_url(), 'dir-sandbox' ) ) {
	WP_CLI::error( 'refusing to run outside dir-sandbox (home_url=' . home_url() . ')' );
}

// --------------------------------------------------------------- source row
$source = $wpdb->get_var(
	"SELECT p.ID FROM {$wpdb->posts} p
	 JOIN dlrh_carrier_information ci ON ci.ID = p.ID
	 JOIN dlrh_carrier_equipment  ce ON ce.ID = p.ID
	 WHERE p.post_type='carriers' AND p.post_status='publish'
	 ORDER BY p.ID ASC LIMIT 1"
);

if ( ! $source ) {
	WP_CLI::error( 'no source carrier with rows in both tables' );
}

WP_CLI::log( 'source carrier: ' . $source . ' (' . get_the_title( $source ) . ')' );

// ------------------------------------------------------- remove previous run
$existing = get_page_by_path( $SLUG, OBJECT, 'carriers' );

if ( $existing ) {
	WP_CLI::log( 'existing demo carrier #' . $existing->ID . ' will be replaced' );
}

if ( $DRY ) {
	WP_CLI::success( 'DRY RUN - would clone ' . $source . ' into a new invented carrier "' . $NAME . '"' );
	return;
}

if ( $existing ) {
	foreach ( array( 'dlrh_carrier_information', 'dlrh_carrier_equipment', 'dlrh_carrier_cargo' ) as $t ) {
		$wpdb->delete( $t, array( 'ID' => $existing->ID ) );
	}
	wp_delete_post( $existing->ID, true );
}

// ------------------------------------------------------------- create post
$new_id = wp_insert_post( array(
	'post_type'   => 'carriers',
	'post_status' => 'publish',
	'post_title'  => $NAME,
	'post_name'   => $SLUG,
), true );

if ( is_wp_error( $new_id ) ) {
	WP_CLI::error( 'insert failed: ' . $new_id->get_error_message() );
}

WP_CLI::log( 'created post ' . $new_id );

// ------------------------------------------------- clone the three MB rows
foreach ( array( 'dlrh_carrier_information', 'dlrh_carrier_equipment', 'dlrh_carrier_cargo' ) as $table ) {

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE ID = %d", $source ), ARRAY_A );

	if ( ! $row ) {
		WP_CLI::warning( "no source row in $table - skipped" );
		continue;
	}

	$row['ID'] = $new_id;
	$wpdb->insert( $table, $row );
	WP_CLI::log( "cloned $table" );
}

// --------------------------------------------- overwrite identity + contact
$invented = array(
	'carrier_legal_name'  => $NAME,
	'carrier_dba_name'    => 'Cedar Ridge Logistics',
	'usdot_number'        => '9999001',
	'mc_number'           => '9999001',
	'street_address'      => '4200 Sample Ridge Road',
	'city'                => 'Des Moines',
	'state_province'      => 'IA',
	'zip_postal_code'     => '50301',
	'country'             => 'United States',
	'first_name'          => 'Dana',
	'last_name'           => 'Whitfield',
	'title'               => 'Dispatch Manager',
	'telephone'           => '(555) 010-0142',
	'email'               => 'dispatch@example.com',
	'website_url'         => 'https://example.com',
	'latitude'            => '41.5868',
	'longitude'           => '-93.6250',
	'usdot_status'        => 'Active',
	'allowed_to_operate'  => 'Yes',
	'carrier_operation'   => 'Interstate',
	'safety_rating'       => 'Satisfactory',
);

// Anything that could carry a real person, account or policy - blanked.
$blank = array(
	'tax_id_number', 'duns_number', 'svi_number',
	'insurance_policy_number', 'insurance_policy_number_carrier', 'insurance_amount_on_file',
	'insurance_policy_company_name', 'insurance_company_name',
	'insurance_agenct_company_name', 'insurance_agent_company_name',
	'insurance_agent_telephone', 'insurance_agent_email',
	'motus_insurer_name', 'motus_insurance_policy_no',
	'factoring_company', 'factoring_company_name', 'factoring_company_street_address',
	'factoring_company_city', 'factoring_company_state_province', 'factoring_company_zip_postal_code',
	'factoring_company_country', 'factoring_company_contact_first_name',
	'factoring_company_contact_last_name', 'factoring_company_contact_telephone_number',
	'factoring_company_telephone_number', 'factoring_company_contact_email',
	'cell_number', 'fax_number', 'database_email', 'secondary_name_group',
	'company_logo_url', 'logo_pending_attachment_id', 'logo_approved_attachment_id',
	'first_name_gov1', 'last_name_gov1', 'title_gov1', 'email_gov1', 'telephone_gov1', 'cell_number_gov1',
	'first_name_gov2', 'last_name_gov2', 'title_gov2', 'email_gov2', 'telephone_gov2', 'cell_number_gov2',
	'carrier_risk_badge', 'carrier_risk_score', 'carrier_risk_level',
);

$cols = $wpdb->get_col( 'SHOW COLUMNS FROM dlrh_carrier_information' );
$data = array();

foreach ( $invented as $k => $v ) {
	if ( in_array( $k, $cols, true ) ) {
		$data[ $k ] = $v;
	}
}

foreach ( $blank as $k ) {
	if ( in_array( $k, $cols, true ) ) {
		$data[ $k ] = '';
	}
}

$wpdb->update( 'dlrh_carrier_information', $data, array( 'ID' => $new_id ) );
WP_CLI::log( 'overwrote ' . count( $data ) . ' columns (' . count( $invented ) . ' invented, ' . count( $blank ) . ' blanked)' );

// Fleet size that reads as a real mid-size carrier and lands in a useful band.
$wpdb->update( 'dlrh_carrier_equipment', array( 'number_of_power_units' => '18' ), array( 'ID' => $new_id ) );

// ------------------------------------------------------------- taxonomies
foreach ( array( 'equipment-type', 'company-location-zone', 'country', 'carrier-operation', 'states-covered' ) as $tax ) {

	if ( ! taxonomy_exists( $tax ) ) {
		continue;
	}

	$terms = wp_get_post_terms( $source, $tax, array( 'fields' => 'ids' ) );

	if ( ! is_wp_error( $terms ) && $terms ) {
		wp_set_post_terms( $new_id, $terms, $tax );
	}
}

// ------------------------------------------------------------------ verify
$check = $wpdb->get_row( $wpdb->prepare(
	'SELECT carrier_legal_name, usdot_number, mc_number, city, state_province, telephone, email
	 FROM dlrh_carrier_information WHERE ID = %d', $new_id ), ARRAY_A );

WP_CLI::log( '' );
foreach ( (array) $check as $k => $v ) {
	WP_CLI::log( '  ' . str_pad( $k, 20 ) . $v );
}

WP_CLI::log( '  ' . str_pad( 'power units', 20 ) . $wpdb->get_var( $wpdb->prepare( 'SELECT number_of_power_units FROM dlrh_carrier_equipment WHERE ID = %d', $new_id ) ) );
WP_CLI::success( 'invented carrier ready: ' . get_permalink( $new_id ) );

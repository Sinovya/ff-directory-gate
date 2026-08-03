<?php
/**
 * Remove the editor's "variable chip" <code> wrappers from the Bricksforge
 * email templates.
 *
 * Inserting a merge variable in the Email Designer wraps it in
 *   <code style="color: rgb(146,64,14); background-color: rgba(180,83,9,0.08);">
 * and that styling is SAVED into the template, so it ships in real customer
 * email - the recipient sees their own name highlighted in amber.
 *
 * Strips only the <code> tags, keeping everything inside them (the variable AND
 * any punctuation the chip swallowed, e.g. the trailing comma).
 *
 * The option is a serialised PHP array; the HTML sits at
 * canvas.elements[n].settings.text.value, so this walks the whole structure
 * rather than guessing the path.
 *
 * Run: wp eval-file strip-code-wrappers.php [dry]
 */

$OPTION = 'brf_email_designer_data';
$BACKUP = 'brf_email_designer_data_bak_codestrip';
$DRY    = in_array( 'dry', (array) ( $args ?? array() ), true );

$data = get_option( $OPTION );

if ( empty( $data ) ) {
	WP_CLI::error( "option $OPTION is empty" );
}

$GLOBALS['ff_code_hits'] = array();

/**
 * Recursively strip <code ...>...</code> from every string in the structure.
 */
function ff_strip_code_tags( $value ) {

	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			$value[ $k ] = ff_strip_code_tags( $v );
		}
		return $value;
	}

	if ( ! is_string( $value ) || false === stripos( $value, '<code' ) ) {
		return $value;
	}

	$before = $value;

	// Keep the inner content, drop the tags.
	$after = preg_replace( '#<code\b[^>]*>(.*?)</code>#is', '$1', $value );

	if ( null === $after ) {
		return $value; // regex failed - leave untouched
	}

	if ( $after !== $before ) {
		$GLOBALS['ff_code_hits'][] = array(
			'before' => mb_substr( preg_replace( '/\s+/', ' ', $before ), 0, 160 ),
			'after'  => mb_substr( preg_replace( '/\s+/', ' ', $after ), 0, 160 ),
		);
	}

	return $after;
}

$updated = ff_strip_code_tags( $data );
$hits    = $GLOBALS['ff_code_hits'];

if ( ! $hits ) {
	WP_CLI::success( 'no <code> wrappers found - nothing to do.' );
	return;
}

WP_CLI::log( 'Found ' . count( $hits ) . ' string(s) containing <code> wrappers:' );

foreach ( $hits as $i => $h ) {
	WP_CLI::log( '' );
	WP_CLI::log( '  [' . $i . '] BEFORE: ' . $h['before'] );
	WP_CLI::log( '      AFTER : ' . $h['after'] );
}

if ( $DRY ) {
	WP_CLI::log( '' );
	WP_CLI::success( 'DRY RUN - nothing written.' );
	return;
}

if ( ! get_option( $BACKUP ) ) {
	update_option( $BACKUP, $data, false );
	WP_CLI::log( '' );
	WP_CLI::log( "backup saved to option $BACKUP" );
}

update_option( $OPTION, $updated );

// Verify by re-reading.
$check = get_option( $OPTION );
$raw   = maybe_serialize( $check );

WP_CLI::log( 'remaining "<code" occurrences after write: ' . substr_count( strtolower( $raw ), '<code' ) );
WP_CLI::success( 'written.' );

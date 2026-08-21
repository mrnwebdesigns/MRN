<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness for contextual ACF motion targets.
/**
 * Regression coverage for contextual builder motion-target choices.
 *
 * Run with:
 * php stack/themes/mrn-base-stack/tests/php/motion-target-choices.php
 *
 * @package mrn-base-stack
 */

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

require dirname( __DIR__, 2 ) . '/inc/builder/helpers.php';

function mrn_motion_target_test_assert_same( array $expected, array $actual, $message ) {
	if ( $expected === $actual ) {
		return;
	}

	fwrite(
		STDERR,
		sprintf(
			"FAIL: %s\nExpected: %s\nActual: %s\n",
			$message,
			implode( ', ', $expected ),
			implode( ', ', $actual )
		)
	);
	exit( 1 );
}

mrn_motion_target_test_assert_same(
	array( 'row', 'surface', 'content', 'header', 'items' ),
	array_keys( mrn_base_stack_get_motion_target_choices_for_field( 'field_mrn_card_motion_settings' ) ),
	'Card layouts expose only their supported regions.'
);

mrn_motion_target_test_assert_same(
	array( 'row', 'surface', 'content', 'media', 'header' ),
	array_keys( mrn_base_stack_get_motion_target_choices_for_field( 'field_mrn_nested_image_content_motion_settings' ) ),
	'Nested image-content layouts retain their media target.'
);

mrn_motion_target_test_assert_same(
	array( 'row', 'surface', 'content', 'header', 'left-column', 'right-column' ),
	array_keys( mrn_base_stack_get_motion_target_choices_for_field( 'field_mrn_two_column_split_motion_settings' ) ),
	'Only two-column layouts expose left and right sub-layout targets.'
);

mrn_motion_target_test_assert_same(
	array_keys( mrn_base_stack_get_motion_target_choices() ),
	array_keys( mrn_base_stack_get_motion_target_choices_for_field( 'field_custom_motion_settings' ) ),
	'Unknown extension fields retain the complete compatibility catalog.'
);

echo "Contextual motion target choice tests passed.\n";

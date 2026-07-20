<?php
// phpcs:ignoreFile -- Standalone WP/ACF stub harness for row-spacing helper regression.
/**
 * Focused runtime check for flexible-content row-spacing meta hydration.
 *
 * Run with:
 * php stack/themes/mrn-base-stack/tests/php/row-spacing-meta-index.php
 *
 * @package mrn-base-stack
 */

$GLOBALS['mrn_row_spacing_meta_test'] = array(
	'post_id'          => 123,
	'row_index'        => 2,
	'row_index_offset' => 1,
	'layout'           => 'content_row',
	'raw_rows'         => array(
		'page_builder_fields' => array(
			0 => array(
				'acf_fc_layout' => 'hero_row',
			),
			2 => array(
				'acf_fc_layout' => 'content_row',
			),
		),
	),
	'meta'             => array(
		'page_builder_fields_1_row_spacing_preset'            => 'Wrong Disabled Row',
		'page_builder_fields_2_row_spacing_preset'            => 'Correct Visible Row',
		'page_builder_fields_2_row_spacing_margin_top_preset' => 'Large',
	),
	'updated'          => array(),
);

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		$key = preg_replace( '/[^a-z0-9_\-]/', '', $key );

		return is_string( $key ) ? $key : '';
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

function get_row_index() {
	return $GLOBALS['mrn_row_spacing_meta_test']['row_index'];
}

function acf_get_setting( $setting ) {
	return 'row_index_offset' === $setting ? $GLOBALS['mrn_row_spacing_meta_test']['row_index_offset'] : null;
}

function get_row_layout() {
	return $GLOBALS['mrn_row_spacing_meta_test']['layout'];
}

function get_the_ID() {
	return $GLOBALS['mrn_row_spacing_meta_test']['post_id'];
}

function get_queried_object_id() {
	return $GLOBALS['mrn_row_spacing_meta_test']['post_id'];
}

function get_field( $field, $post_id = 0, $format_value = true ) {
	unset( $post_id, $format_value );

	return isset( $GLOBALS['mrn_row_spacing_meta_test']['raw_rows'][ $field ] )
		? $GLOBALS['mrn_row_spacing_meta_test']['raw_rows'][ $field ]
		: null;
}

function get_post_meta( $post_id, $key, $single = false ) {
	unset( $post_id, $single );

	return isset( $GLOBALS['mrn_row_spacing_meta_test']['meta'][ $key ] )
		? $GLOBALS['mrn_row_spacing_meta_test']['meta'][ $key ]
		: '';
}

function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $hook_name, $callback, $priority, $accepted_args );
}
function current_user_can( $capability, ...$args ) {
	unset( $capability, $args );

	return true;
}
function wp_is_post_revision( $post ) {
	unset( $post );

	return false;
}
function wp_is_post_autosave( $post ) {
	unset( $post );

	return false;
}
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function update_post_meta( $post_id, $key, $value ) {
	unset( $post_id );
	$GLOBALS['mrn_row_spacing_meta_test']['updated'][ $key ] = $value;
}
function delete_post_meta( $post_id, $key ) {
	unset( $post_id );
	unset( $GLOBALS['mrn_row_spacing_meta_test']['updated'][ $key ] );
}
function acf_get_field( $field_key ) {
	if ( 'field_builder' !== $field_key ) {
		return null;
	}
	return array(
		'key'     => 'field_builder',
		'name'    => 'generic_builder',
		'type'    => 'flexible_content',
		'layouts' => array(
			array(
				'key'        => 'layout_content',
				'name'       => 'content_row',
				'sub_fields' => array(
					array( 'key' => 'field_spacing', 'name' => 'row_spacing_preset' ),
				),
			),
		),
	);
}

require_once __DIR__ . '/../../inc/row-spacing-meta.php';

function mrn_base_stack_get_row_spacing_contract( array $row = array() ) {
	$preset = isset( $row['row_spacing_preset'] ) && is_scalar( $row['row_spacing_preset'] )
		? trim( (string) $row['row_spacing_preset'] )
		: '';

	if ( '' === $preset ) {
		return array(
			'classes'    => array(),
			'attributes' => array(),
		);
	}

	return array(
		'classes'    => array(),
		'attributes' => array(
			'data-selected' => $preset,
			'style'         => '--preset: ' . $preset,
		),
	);
}

function mrn_row_spacing_meta_test_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

$resolved_index = mrn_base_stack_get_current_flex_row_meta_index( 'page_builder_fields', 123 );
mrn_row_spacing_meta_test_assert( 2 === $resolved_index, 'visible row after a skipped raw row resolves to raw meta index 2' );

$GLOBALS['mrn_row_spacing_meta_test']['raw_rows']['sequential_builder'] = array(
	0 => array( 'acf_fc_layout' => 'hero_row' ),
	1 => array( 'acf_fc_layout' => 'content_row' ),
);
mrn_row_spacing_meta_test_assert(
	1 === mrn_base_stack_resolve_flexible_row_meta_index( 123, 'sequential_builder', 1, 'content_row' ),
	'normal sequential rows retain their existing zero-based mapping'
);

$GLOBALS['mrn_row_spacing_meta_test']['raw_rows']['long_builder'] = array();
for ( $index = 0; $index <= 6; ++$index ) {
	$GLOBALS['mrn_row_spacing_meta_test']['raw_rows']['long_builder'][ $index ] = array( 'acf_fc_layout' => 'content_row' );
}
$GLOBALS['mrn_row_spacing_meta_test']['raw_rows']['long_builder'][8] = array( 'acf_fc_layout' => 'target_row' );
mrn_row_spacing_meta_test_assert(
	8 === mrn_base_stack_resolve_flexible_row_meta_index( 123, 'long_builder', 7, 'target_row' ),
	'visible row 8 maps to raw saved row 9 when one earlier row is skipped'
);
mrn_row_spacing_meta_test_assert(
	-1 === mrn_base_stack_resolve_flexible_row_meta_index( 123, 'long_builder', 7, 'wrong_row' ),
	'a candidate with the wrong acf_fc_layout is rejected'
);

$hydrated = mrn_base_stack_hydrate_row_spacing_selectors_from_meta(
	array(
		'acf_fc_layout' => 'content_row',
	),
	'page_builder_fields',
	123
);

mrn_row_spacing_meta_test_assert(
	isset( $hydrated['row_spacing_preset'] ) && 'Correct Visible Row' === $hydrated['row_spacing_preset'],
	'hydration reads the visible row spacing preset'
);

$GLOBALS['mrn_row_spacing_meta_test']['raw_rows']['generic_builder'] = array(
	0 => array( 'acf_fc_layout' => 'hero_row' ),
	2 => array( 'acf_fc_layout' => 'content_row' ),
);
$_POST['acf'] = array(
	'field_builder' => array(
		array( 'acf_fc_layout' => 'layout_hero' ),
		array( 'acf_fc_layout' => 'layout_content', 'field_spacing' => 'Extra Large' ),
	),
);
mrn_base_stack_save_dynamic_row_spacing_values( 123 );
mrn_row_spacing_meta_test_assert(
	'Extra Large' === ( $GLOBALS['mrn_row_spacing_meta_test']['updated']['generic_builder_2_row_spacing_preset'] ?? '' ),
	'dynamic row-spacing values save to the resolved raw row'
);
mrn_row_spacing_meta_test_assert(
	! isset( $GLOBALS['mrn_row_spacing_meta_test']['updated']['generic_builder_1_row_spacing_preset'] ),
	'dynamic row-spacing values do not save to the skipped neighboring row'
);
mrn_row_spacing_meta_test_assert(
	isset( $hydrated['row_spacing_margin_top_preset'] ) && 'Large' === $hydrated['row_spacing_margin_top_preset'],
	'hydration reads side selector fields from the same visible row'
);
mrn_row_spacing_meta_test_assert(
	'Wrong Disabled Row' !== $hydrated['row_spacing_preset'],
	'hydration does not drift to disabled/previous row meta'
);

$preserved = mrn_base_stack_hydrate_row_spacing_selectors_from_meta(
	array(
		'acf_fc_layout'       => 'content_row',
		'row_spacing_preset' => 'Already Complete',
	),
	'page_builder_fields',
	123,
	2
);
mrn_row_spacing_meta_test_assert(
	'Already Complete' === $preserved['row_spacing_preset'],
	'complete row data is preserved when selector fields are already present'
);

$GLOBALS['mrn_row_spacing_meta_test']['row_index'] = false;
$builder_hydrated                                 = mrn_base_stack_hydrate_row_spacing_selectors_from_meta(
	array(
		'__mrn_builder_row_index' => 1,
		'acf_fc_layout'           => 'content_row',
	),
	'page_builder_fields',
	123
);
mrn_row_spacing_meta_test_assert(
	isset( $builder_hydrated['row_spacing_preset'] ) && 'Correct Visible Row' === $builder_hydrated['row_spacing_preset'],
	'builder row context maps visible array position through sparse raw keys'
);
$GLOBALS['mrn_row_spacing_meta_test']['row_index'] = 2;

$attribute_html = mrn_base_stack_get_row_spacing_attr_html_for_current_row(
	array(
		'acf_fc_layout' => 'content_row',
	),
	'page_builder_fields',
	123
);
mrn_row_spacing_meta_test_assert(
	false !== strpos( $attribute_html, 'data-selected="Correct Visible Row"' ),
	'attribute wrapper calls the contract with hydrated row spacing data'
);
mrn_row_spacing_meta_test_assert(
	false === strpos( $attribute_html, 'Wrong Disabled Row' ),
	'attribute wrapper output excludes disabled/previous row values'
);

echo "OK: row-spacing meta index hydration handles skipped flexible-content rows.\n";

<?php
/**
 * Consumer-based ACF editor assets.
 *
 * @package mrn-base-stack
 */

/**
 * Match a repeater, including empty repeater templates.
 *
 * @param array $field Field definition.
 * @return bool
 */
function mrn_base_stack_admin_is_repeater_field( $field ) {
	return 'repeater' === ( $field['type'] ?? '' );
}

/**
 * Match the same wrapper marker used by admin-icon-choosers.js.
 *
 * @param array $field Field definition.
 * @return bool
 */
function mrn_base_stack_admin_is_icon_source_field( $field ) {
	$classes = preg_split( '/\s+/', trim( $field['wrapper']['class'] ?? '' ) );
	return in_array( 'mrn-icon-chooser-field--source', $classes, true );
}

/**
 * Match a rendered field or a possible field in the current post editor.
 *
 * @param array|null $field Rendered field; null during early enqueue.
 * @param callable   $predicate Consumer's field matcher.
 * @return bool
 */
function mrn_base_stack_admin_acf_field_needs_assets( $field, $predicate ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! is_admin() || ! mrn_base_stack_admin_is_safe_acf_editor_helper_screen( $screen ) ) {
		return false;
	}
	if ( is_array( $field ) ) {
		return $predicate( $field );
	}
	if ( function_exists( 'mrn_shared_assets_acf_editor_has_field' ) ) {
		return mrn_shared_assets_acf_editor_has_field( $predicate );
	}
	// Compatibility with older shared assets: retain dynamic post editor support.
	return in_array( $screen->base, array( 'post', 'post-new' ), true );
}

/**
 * Enqueue shared ACF repeater admin controls anywhere repeaters render.
 *
 * @param array|null $field Rendered field, or null during early enqueue.
 * @return void
 */
function mrn_base_stack_enqueue_shared_repeater_admin_assets( $field = null ) {
	if ( ! mrn_base_stack_admin_acf_field_needs_assets( $field, 'mrn_base_stack_admin_is_repeater_field' ) || wp_script_is( 'mrn-base-stack-admin-repeater-controls', 'enqueued' ) ) {
		return;
	}
	$repeater_controls_path = get_template_directory() . '/js/admin-repeater-controls.js';
	$repeater_controls_ver  = file_exists( $repeater_controls_path ) ? (string) filemtime( $repeater_controls_path ) : _S_VERSION;
	$repeater_styles_path   = get_template_directory() . '/css/admin-repeater-controls.css';
	$repeater_styles_ver    = file_exists( $repeater_styles_path ) ? (string) filemtime( $repeater_styles_path ) : _S_VERSION;

	wp_enqueue_style(
		'mrn-base-stack-admin-repeater-controls',
		get_template_directory_uri() . '/css/admin-repeater-controls.css',
		array(),
		$repeater_styles_ver
	);

	wp_enqueue_script(
		'mrn-base-stack-admin-repeater-controls',
		get_template_directory_uri() . '/js/admin-repeater-controls.js',
		array( 'jquery', 'acf-input' ),
		$repeater_controls_ver,
		true
	);
}
add_action( 'acf/render_field/type=repeater', 'mrn_base_stack_enqueue_shared_repeater_admin_assets' );
add_action( 'acf/input/admin_enqueue_scripts', 'mrn_base_stack_enqueue_shared_repeater_admin_assets' );

/**
 * Enqueue icon helpers only for an MRN icon source field.
 *
 * @param array|null $field Rendered field, or null during early enqueue.
 * @return void
 */
function mrn_base_stack_enqueue_acf_icon_admin_assets( $field = null ) {
	if ( ! mrn_base_stack_admin_acf_field_needs_assets( $field, 'mrn_base_stack_admin_is_icon_source_field' ) || wp_script_is( 'mrn-base-stack-admin-icon-choosers', 'enqueued' ) ) {
		return;
	}
	$icon_choosers_script_path = get_template_directory() . '/js/admin-icon-choosers.js';
	$icon_choosers_script_ver  = file_exists( $icon_choosers_script_path ) ? (string) filemtime( $icon_choosers_script_path ) : _S_VERSION;
	$icon_choosers_style_path  = get_template_directory() . '/css/admin-icon-choosers.css';
	$icon_choosers_style_ver   = file_exists( $icon_choosers_style_path ) ? (string) filemtime( $icon_choosers_style_path ) : _S_VERSION;

	if ( function_exists( 'mrn_shared_assets_enqueue_admin_icon_chooser' ) ) {
		mrn_shared_assets_enqueue_admin_icon_chooser( 'mrn-shared-icon-chooser', 'mrn-shared-icon-chooser' );
	}

	wp_enqueue_script(
		'mrn-base-stack-admin-icon-choosers',
		get_template_directory_uri() . '/js/admin-icon-choosers.js',
		array( 'jquery', 'acf-input', 'mrn-shared-icon-chooser' ),
		$icon_choosers_script_ver,
		true
	);

	wp_enqueue_style(
		'mrn-base-stack-admin-icon-choosers',
		get_template_directory_uri() . '/css/admin-icon-choosers.css',
		array( 'mrn-shared-icon-chooser' ),
		$icon_choosers_style_ver
	);
}
add_action( 'acf/render_field', 'mrn_base_stack_enqueue_acf_icon_admin_assets' );
add_action( 'acf/input/admin_enqueue_scripts', 'mrn_base_stack_enqueue_acf_icon_admin_assets' );

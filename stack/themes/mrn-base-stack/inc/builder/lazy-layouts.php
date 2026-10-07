<?php
/**
 * Render unused builder layout templates only when the editor requests them.
 *
 * Saved rows and ACF's native add/duplicate/save mechanisms remain unchanged.
 *
 * @package mrn-base-stack
 */

/**
 * Enqueue the adapter for Classic Editor posts and the owned 404 options screen.
 *
 * @param string $hook_suffix Current admin page.
 * @return void
 */
function mrn_base_stack_enqueue_lazy_builder_layouts( $hook_suffix ) {
	$options_editor = str_ends_with( $hook_suffix, '_page_mrn-404-page' ) && current_user_can( 'edit_theme_options' );
	if ( ( ! $options_editor && ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) || ! class_exists( '\ACF\Pro\Fields\FlexibleContent\Layout' ) || ! function_exists( 'mrn_base_stack_admin_is_safe_acf_editor_helper_screen' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $options_editor && ! mrn_base_stack_admin_is_safe_acf_editor_helper_screen( $screen ) ) {
		return;
	}

	// Deferred image/WYSIWYG fields cannot enqueue their assets in the initial render.
	if ( function_exists( 'acf_enqueue_uploader' ) ) {
		acf_enqueue_uploader();
	}

	$path = '/js/admin-lazy-layouts.js';
	wp_enqueue_script( 'mrn-base-stack-lazy-layouts', get_template_directory_uri() . $path, array( 'acf-input', 'acf-pro-input' ), (string) filemtime( get_template_directory() . $path ), true );
	wp_localize_script(
		'mrn-base-stack-lazy-layouts',
		'mrnBuilderLayouts',
		array(
			'loading' => __( 'Loading layout fields…', 'mrn-base-stack' ),
			'failed'  => __( 'Layout fields could not be loaded. Try adding the row again.', 'mrn-base-stack' ),
			'pending' => __( 'Wait for the layout fields to finish loading before saving.', 'mrn-base-stack' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'mrn_base_stack_enqueue_lazy_builder_layouts', 30 );

/**
 * Identify Stack-owned flexible-content fields, including derived field keys.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return bool
 */
function mrn_base_stack_is_lazy_builder_field( array $field ) {
	return 'flexible_content' === ( $field['type'] ?? '' ) && false !== strpos( (string) ( $field['key'] ?? '' ), 'field_mrn_' );
}

/**
 * Remove only the direct children of an unused flexible layout template.
 *
 * Prefixes distinguish templates from saved rows and repeater clone rows.
 * The requested AJAX template is exempt; its nested flexible templates remain
 * deferred. No field definitions, defaults, values, or catalog entries change.
 *
 * @param array<string, mixed>|false $field Prepared ACF field.
 * @return array<string, mixed>|false
 */
function mrn_base_stack_prepare_lazy_builder_layout( $field ) {
	if ( ! is_array( $field ) || ( empty( $GLOBALS['mrn_builder_layout_request'] ) && ! wp_script_is( 'mrn-base-stack-lazy-layouts', 'enqueued' ) ) ) {
		return $field;
	}

	$prefixes = $GLOBALS['mrn_builder_template_prefixes'] ?? array();
	$prefix   = (string) ( $field['prefix'] ?? '' );
	if ( isset( $prefixes[ $prefix ] ) && ( $GLOBALS['mrn_builder_requested_template_prefix'] ?? '' ) !== $prefix ) {
		return false;
	}

	if ( mrn_base_stack_is_lazy_builder_field( $field ) ) {
		$GLOBALS['mrn_builder_template_prefixes'][ $field['name'] . '[acfcloneindex]' ] = true;
		$field['wrapper']['class'] = trim( ( $field['wrapper']['class'] ?? '' ) . ' mrn-acf-lazy-layouts' );
	}

	return $field;
}
add_filter( 'acf/prepare_field', 'mrn_base_stack_prepare_lazy_builder_layout', -10 );

/**
 * Render one empty layout using ACF's native renderer after authorization.
 *
 * @return void
 */
function mrn_base_stack_ajax_builder_layout() {
	$field_key = isset( $_POST['field_key'] ) && is_string( $_POST['field_key'] ) ? sanitize_key( wp_unslash( $_POST['field_key'] ) ) : '';
	$target    = isset( $_POST['post_id'] ) && is_scalar( $_POST['post_id'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['post_id'] ) ) : '';
	$post_id   = 'options' === $target ? 'options' : ( ctype_digit( $target ) ? absint( $target ) : 0 );
	$nonce     = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	$can_edit  = 'options' === $post_id ? current_user_can( 'edit_theme_options' ) : ( $post_id && current_user_can( 'edit_post', $post_id ) );
	if ( ! wp_verify_nonce( $nonce, 'acf_field_flexible_content_' . $field_key ) || ! $can_edit ) {
		wp_send_json_error( array( 'message' => __( 'You cannot load this layout.', 'mrn-base-stack' ) ), 403 );
	}
	if ( ! function_exists( 'acf_set_form_data' ) || ! function_exists( 'acf_get_store' ) || ! function_exists( 'acf_get_field' ) || ! function_exists( 'acf_prepare_field' ) ) {
		wp_send_json_error( array( 'message' => __( 'The layout editor is unavailable.', 'mrn-base-stack' ) ), 400 );
		return;
	}

	$input_name  = isset( $_POST['input_name'] ) && is_string( $_POST['input_name'] ) ? sanitize_text_field( wp_unslash( $_POST['input_name'] ) ) : '';
	$layout_name = isset( $_POST['layout'] ) && is_string( $_POST['layout'] ) ? sanitize_key( wp_unslash( $_POST['layout'] ) ) : '';
	if ( strlen( $input_name ) > 2048 || ! preg_match( '/^acf(?:\[[a-zA-Z0-9_-]+\])+$/D', $input_name ) || ! str_ends_with( $input_name, '[' . $field_key . ']' ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid layout field.', 'mrn-base-stack' ) ), 400 );
	}
	if ( 'options' === $post_id && ( ! preg_match( '/^acf\[field_mrn_404_content_rows\](?:\[|$)/D', $input_name ) || ( 'field_mrn_404_content_rows' !== $field_key && ! str_starts_with( $field_key, 'not_found_' ) ) ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid options layout field.', 'mrn-base-stack' ) ), 403 );
	}

	acf_set_form_data( 'post_id', $post_id );
	// An early startup read must not cache another post's layout allowlist.
	acf_get_store( 'fields' )->remove( $field_key );
	mrn_base_stack_register_cloned_acf_field_key( $field_key );
	$field = acf_get_field( $field_key );
	if ( ! is_array( $field ) || ! mrn_base_stack_is_lazy_builder_field( $field ) || ! class_exists( '\ACF\Pro\Fields\FlexibleContent\Layout' ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid layout field.', 'mrn-base-stack' ) ), 400 );
	}

	$field['prefix'] = substr( $input_name, 0, -strlen( '[' . $field_key . ']' ) );
	$field           = acf_prepare_field( $field );
	if ( ! is_array( $field ) ) {
		wp_send_json_error( array( 'message' => __( 'This layout is unavailable.', 'mrn-base-stack' ) ), 400 );
	}

	$layout = null;
	foreach ( $field['layouts'] as $candidate ) {
		if ( $layout_name === $candidate['name'] && (int) ( $candidate['max'] ?? 0 ) >= 0 ) {
			$layout = $candidate;
			break;
		}
	}
	if ( null === $layout ) {
		wp_send_json_error( array( 'message' => __( 'This layout is unavailable.', 'mrn-base-stack' ) ), 400 );
	}

	$field['name']                                    = $input_name;
	$GLOBALS['mrn_builder_layout_request']            = true;
	$GLOBALS['mrn_builder_requested_template_prefix'] = $input_name . '[acfcloneindex]';
	ob_start();
	try {
		$renderer = new \ACF\Pro\Fields\FlexibleContent\Layout( $field, $layout, 'acfcloneindex', array() );
		$renderer->render();
		$html = ob_get_contents();
	} finally {
		ob_end_clean();
		unset( $GLOBALS['mrn_builder_layout_request'], $GLOBALS['mrn_builder_requested_template_prefix'] );
	}

	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_mrn_base_stack_builder_layout', 'mrn_base_stack_ajax_builder_layout' );

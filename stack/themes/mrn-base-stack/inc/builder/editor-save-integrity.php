<?php
/**
 * Reject truncated native Classic Editor submissions before WordPress saves.
 *
 * @package mrn-base-stack
 */

/**
 * Mark the beginning of supported editor forms, before the ACF field payload.
 *
 * @param WP_Post $post Edited post.
 * @return void
 */
function mrn_base_stack_editor_save_guard_start( $post ) {
	if ( ! $post instanceof WP_Post || ! function_exists( 'acf' )
		|| ! mrn_base_stack_admin_is_safe_acf_editor_helper_screen( get_current_screen() )
		|| ! in_array( $post->post_type, mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		return;
	}
	wp_nonce_field( 'mrn-builder-save-' . $post->ID, '_mrn_builder_save_guard', false );
}
add_action( 'edit_form_top', 'mrn_base_stack_editor_save_guard_start' );

/**
 * Add the completion marker after all editor controls, associated with #post.
 *
 * The native form attribute works without JavaScript and keeps this last in
 * browser submission order even when ACF adds fields inside the editor form.
 *
 * @return void
 */
function mrn_base_stack_editor_save_guard_end() {
	global $post;
	if ( ! $post instanceof WP_Post || ! function_exists( 'acf' )
		|| ! mrn_base_stack_admin_is_safe_acf_editor_helper_screen( get_current_screen() )
		|| ! in_array( $post->post_type, mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		return;
	}
	printf( '<input type="hidden" form="post" name="_mrn_builder_save_complete" value="%s">', esc_attr( wp_create_nonce( 'mrn-builder-save-' . $post->ID ) ) );
}
add_action( 'admin_footer-post.php', 'mrn_base_stack_editor_save_guard_end', PHP_INT_MAX );
add_action( 'admin_footer-post-new.php', 'mrn_base_stack_editor_save_guard_end', PHP_INT_MAX );

/**
 * Fail before post.php dispatches its write when the form tail is missing.
 *
 * Autosave, REST, WP-CLI and other forms do not carry this native form marker.
 * WordPress remains responsible for their ordinary authorization and saves.
 *
 * @return void
 */
function mrn_base_stack_reject_incomplete_editor_save() {
	if ( ! isset( $_POST['_mrn_builder_save_guard'], $_POST['post_ID'], $_POST['action'] )
		|| ! is_string( $_POST['action'] ) || 'editpost' !== $_POST['action'] ) {
		return;
	}
	$post_id = absint( $_POST['post_ID'] );
	$guard   = is_string( $_POST['_mrn_builder_save_guard'] ) ? sanitize_text_field( wp_unslash( $_POST['_mrn_builder_save_guard'] ) ) : '';
	if ( ! current_user_can( 'edit_post', $post_id ) || ! wp_verify_nonce( $guard, 'mrn-builder-save-' . $post_id ) ) {
		return;
	}
	$complete = isset( $_POST['_mrn_builder_save_complete'] ) && is_string( $_POST['_mrn_builder_save_complete'] )
		? sanitize_text_field( wp_unslash( $_POST['_mrn_builder_save_complete'] ) ) : '';
	if ( ! wp_verify_nonce( $complete, 'mrn-builder-save-' . $post_id ) ) {
		wp_die(
			esc_html__( 'This page was not saved because the server received an incomplete editor submission. Your saved content is unchanged. Go back to recover your edits and ask your administrator to check the PHP input limits before saving again.', 'mrn-base-stack' ),
			esc_html__( 'Incomplete editor submission', 'mrn-base-stack' ),
			array( 'response' => 400, 'back_link' => true )
		);
	}
}
add_action( 'admin_init', 'mrn_base_stack_reject_incomplete_editor_save', 0 );

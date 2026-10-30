<?php
/** Local Option B presentation scope. Native screens and AME retain ownership. */
defined( 'ABSPATH' ) || exit;

/** Identify the deliberately supported native/MRN screens; never style app internals. */
function mrn_admin_ui_modern_screen() {
	if ( ! function_exists( 'mrn_editor_experience_enabled' ) || ! mrn_editor_experience_enabled() ) {
		return '';
	}
	$screen = get_current_screen();
	if ( ! $screen ) {
		return '';
	}
	if ( 'dashboard' === $screen->id ) {
		return 'dashboard';
	}
	$types = function_exists( 'mrn_base_stack_get_singular_shell_post_types' ) ? mrn_base_stack_get_singular_shell_post_types() : array( 'post', 'page' );
	// Application-owned tables remain excluded even if a site opts them into the MRN shell.
	$types = array_diff( $types, array( 'product', 'product_variation', 'shop_order', 'shop_coupon', 'courses', 'lesson', 'tutor_quiz', 'tutor_assignments', 'wpforms', 'acf-field-group' ) );
	if ( 'edit' === $screen->base && in_array( $screen->post_type, $types, true ) ) {
		return 'list';
	}
	if ( in_array( $screen->id, array( 'options-general', 'options-writing', 'options-reading', 'options-discussion', 'options-media', 'options-permalink', 'profile' ), true ) ) {
		return 'settings';
	}
	// Exact MRN pages only. Prefix matching would accidentally claim third-party apps.
	$pages = array( 'mrn-config-helper', 'mrn-notifications-center', 'mrn-reusable-block-library', 'mrn-tokens', 'mrn-theme-header-footer', 'mrn-header-utility-message', 'mrn-business-information', 'mrn-404-page', 'mrn-public-security-hardening' );
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	return in_array( $page, $pages, true ) ? 'settings' : '';
}

add_filter( 'admin_body_class', function ( $classes ) {
	$scope = mrn_admin_ui_modern_screen();
	return $scope ? $classes . ' mrn-modern-admin mrn-admin-' . $scope : $classes;
} );

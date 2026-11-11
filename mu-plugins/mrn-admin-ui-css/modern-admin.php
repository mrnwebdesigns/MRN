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
	if ( 'nav-menus' === $screen->id && 'nav-menus' === $screen->base ) {
		return 'menus';
	}
	if ( in_array( $screen->id, array( 'users', 'plugins' ), true ) ) {
		return 'list';
	}
	$types = function_exists( 'mrn_base_stack_get_singular_shell_post_types' ) ? mrn_base_stack_get_singular_shell_post_types() : array( 'post', 'page' );
	// Application-owned tables remain excluded even if a site opts them into the MRN shell.
	$types = array_diff( $types, array( 'product', 'product_variation', 'shop_order', 'shop_coupon', 'courses', 'lesson', 'tutor_quiz', 'tutor_assignments', 'wpforms', 'acf-field-group' ) );
	if ( 'edit' === $screen->base ) {
		// List presentation is independent of the singular/editor shell. Reuse
		// native public CPTs and MRN's existing content/data registry so private
		// resources, locations and reusable blocks receive the same list header.
		// Row-action rendering asks again for every record; resolve this once.
		static $list_types = null;
		if ( null === $list_types ) {
			$data_types = function_exists( 'mrn_admin_data_post_types_get_config' ) ? array_keys( mrn_admin_data_post_types_get_config() ) : array();
			$list_types = array_intersect(
				array_merge( $types, get_post_types( array( 'public' => true, 'show_ui' => true ) ), $data_types ),
				get_post_types( array( 'show_ui' => true ) )
			);
		}
		// Even an administrator selecting one as data must not claim an app's UI.
		$app_type = in_array( $screen->post_type, array( 'product', 'product_variation', 'shop_order', 'shop_coupon', 'courses', 'lesson', 'tutor_quiz', 'tutor_assignments', 'wpforms' ), true )
			|| preg_match( '/^(?:acf[-_]|seopress_|wpforms_|shop_|product_|tutor_)/', $screen->post_type );
		if ( ! $app_type && in_array( $screen->post_type, $list_types, true ) ) {
			return 'list';
		}
	}
	if ( in_array( $screen->base, array( 'edit-tags', 'term' ), true ) && in_array( $screen->taxonomy, get_object_taxonomies( $types ), true ) ) {
		return 'settings';
	}
	if ( in_array( $screen->id, array( 'options-general', 'options-writing', 'options-reading', 'options-discussion', 'options-media', 'options-permalink', 'options-privacy', 'profile', 'user', 'user-edit', 'user-new', 'tools', 'import', 'export', 'update-core' ), true ) ) {
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

/** Paint a usable native disclosure before the shared popover enhancement loads. */
function mrn_admin_ui_post_actions( $actions, $post ) {
	$screen = get_current_screen();
	if ( ! $screen || 'edit' !== $screen->base || 'list' !== mrn_admin_ui_modern_screen() || ! $actions ) {
		return $actions;
	}
	// Leave application-owned disclosures with their owner. Original action HTML
	// already contains its owner's capability checks, URLs, nonces and handlers.
	foreach ( $actions as $action ) {
		if ( ! is_string( $action ) || false !== stripos( $action, '<details' ) || false !== stripos( $action, 'popover=' ) ) {
			return $actions;
		}
	}
	$title = wp_strip_all_tags( get_the_title( $post ) );
	$name = $title ? sprintf( __( 'Actions for %s', 'mrn-admin-ui-css' ), $title ) : __( 'Actions', 'mrn-admin-ui-css' );
	$html = '<details class="mrn-row-actions-native"><summary class="button" aria-label="' . esc_attr( $name ) . '">' . esc_html__( 'Actions', 'mrn-admin-ui-css' ) . '</summary><div class="mrn-row-actions-native__content">';
	foreach ( $actions as $key => $action ) {
		// Retain class lists such as core's "inline hide-if-no-js" verbatim.
		$html .= '<span class="' . esc_attr( $key ) . '">' . $action . '</span>';
	}
	$html .= '</div></details>';
	return array( 'mrn_actions' => $html );
}
add_filter( 'post_row_actions', 'mrn_admin_ui_post_actions', PHP_INT_MAX, 2 );
add_filter( 'page_row_actions', 'mrn_admin_ui_post_actions', PHP_INT_MAX, 2 );

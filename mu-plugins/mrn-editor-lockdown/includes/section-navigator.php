<?php
/**
 * Opt-in Local Hub section navigation. Native ACF remains the content owner.
 *
 * @package MRNEditorLockdown
 */

defined( 'ABSPATH' ) || exit;

/**
 * A single local-only switch also serves as the emergency disable mechanism.
 *
 * @return bool
 */
function mrn_editor_experience_enabled() {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	return defined( 'MRN_EDITOR_EXPERIENCE_ENABLED' ) && true === MRN_EDITOR_EXPERIENCE_ENABLED
		&& str_ends_with( $host, '.localhost' );
}

/**
 * Restrict the pilot to authorized native Classic Editor forms.
 *
 * @return bool
 */
function mrn_editor_experience_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! mrn_editor_experience_enabled() || ! mrn_editor_lockdown_is_classic_post_screen( $screen ) ) {
		return false;
	}
	if ( ! function_exists( 'acf' ) || ! function_exists( 'mrn_base_stack_get_singular_shell_post_types' )
		|| ! in_array( $screen->post_type, mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		return false;
	}
	$post_id = get_the_ID();
	$type    = get_post_type_object( $screen->post_type );
	return $post_id ? current_user_can( 'edit_post', $post_id ) : ( $type && current_user_can( $type->cap->edit_posts ) );
}

/** Enqueue behavior separately from the cosmetic component. */
function mrn_editor_experience_enqueue() {
	if ( ! mrn_editor_experience_screen() ) {
		return;
	}
	$file = dirname( __DIR__ ) . '/assets/section-navigator.js';
	wp_enqueue_script( 'mrn-section-navigator', content_url( 'mu-plugins/mrn-editor-lockdown/assets/section-navigator.js' ), array( 'acf-input', 'acf-pro-input' ), (string) filemtime( $file ), true );
	wp_localize_script(
		'mrn-section-navigator',
		'mrnSectionNavigator',
		array(
			'title'    => __( 'Page sections', 'mrn-editor-lockdown' ),
			'search'   => __( 'Find a section', 'mrn-editor-lockdown' ),
			'close'    => __( 'Close sections', 'mrn-editor-lockdown' ),
			'return'   => __( 'Return to sections', 'mrn-editor-lockdown' ),
			'empty'    => __( 'No matching sections. Your content is unchanged.', 'mrn-editor-lockdown' ),
			'errors'   => __( 'Needs attention', 'mrn-editor-lockdown' ),
			'all'      => __( 'Show all sections', 'mrn-editor-lockdown' ),
			'untitled' => __( 'Untitled section', 'mrn-editor-lockdown' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'mrn_editor_experience_enqueue', 40 );

/** Keep a native toolbar entry point available while scrolling. */
function mrn_editor_experience_toolbar( $bar ) {
	if ( mrn_editor_experience_screen() ) {
		$bar->add_node( array( 'id' => 'mrn-page-sections', 'title' => __( 'Sections', 'mrn-editor-lockdown' ), 'href' => '#mrn-section-outline' ) );
	}
}
add_action( 'admin_bar_menu', 'mrn_editor_experience_toolbar', 100 );

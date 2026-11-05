<?php
/**
 * Opt-in pilot section navigation. Native ACF remains the content owner.
 *
 * @package MRNEditorLockdown
 */

defined( 'ABSPATH' ) || exit;

/**
 * One switch disables the pilot on Local Hub and its exact approved review host.
 *
 * @return bool
 */
function mrn_editor_experience_enabled() {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	return defined( 'MRN_EDITOR_EXPERIENCE_ENABLED' ) && true === MRN_EDITOR_EXPERIENCE_ENABLED
		&& ( str_ends_with( $host, '.localhost' ) || 'admin-editor-pilot.mrndev.io' === $host );
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

/**
 * Reuse native post-type support and ACF's existing group placement.
 * Authored fields belong to Content even in a sidebar or a mixed group.
 * Only sidebar groups made entirely of switches/presentation are settings.
 * Unknown/custom field types default to Content rather than hiding authoring.
 * This describes existing controls, never renders or relocates field inputs.
 *
 * @param string $post_type Registered post type.
 * @return array
 */
function mrn_editor_experience_content_panels( $post_type ) {
	$core = array();
	foreach ( array( 'title' => 'titlediv', 'editor' => 'postdivrich', 'excerpt' => 'postexcerpt', 'thumbnail' => 'postimagediv' ) as $feature => $id ) {
		if ( post_type_supports( $post_type, $feature ) ) {
			$core[] = $id;
		}
	}
	$settings = array();
	foreach ( mrn_editor_lockdown_get_acf_field_group_metaboxes( $post_type ) as $group ) {
		if ( 'side' === $group['position'] ) {
			$fields = acf_get_fields( $group['field_group'] );
			$content = array_filter( $fields ?: array(), static function ( $field ) {
				return ! in_array( $field['type'] ?? '', array( 'true_false', 'message', 'tab', 'accordion' ), true );
			} );
			if ( $fields && ! $content ) {
				$settings[] = $group['id'];
			}
		}
	}
	return array( 'core' => $core, 'acfSettings' => $settings );
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
			'panels'   => mrn_editor_experience_content_panels( get_current_screen()->post_type ),
			'contentFields' => __( 'Content fields', 'mrn-editor-lockdown' ),
			'bodyContent' => __( 'Body content', 'mrn-editor-lockdown' ),
			'title'    => __( 'Page structure', 'mrn-editor-lockdown' ),
			'search'   => __( 'Search sections and content', 'mrn-editor-lockdown' ),
			'collapseRows' => __( 'Collapse items', 'mrn-editor-lockdown' ),
			'expandRows' => __( 'Expand items', 'mrn-editor-lockdown' ),
			'component' => __( 'Item', 'mrn-editor-lockdown' ),
			'close'    => __( 'Close sections', 'mrn-editor-lockdown' ),
			'return'   => __( 'Return to sections', 'mrn-editor-lockdown' ),
			'empty'    => __( 'No matching sections. Your content is unchanged.', 'mrn-editor-lockdown' ),
			'start'    => __( 'No sections yet. Add a section to get started.', 'mrn-editor-lockdown' ),
			'startHint' => __( 'Add your first section using a region below or the page outline.', 'mrn-editor-lockdown' ),
			'errors'   => __( 'Needs attention', 'mrn-editor-lockdown' ),
			'all'      => __( 'Show all sections', 'mrn-editor-lockdown' ),
			'untitled' => __( 'Untitled section', 'mrn-editor-lockdown' ),
			'content'  => __( 'Content', 'mrn-editor-lockdown' ),
			'settings' => __( 'Page settings', 'mrn-editor-lockdown' ),
			'focus'    => __( 'Edit one section', 'mrn-editor-lockdown' ),
			'overview' => __( 'Arrange sections', 'mrn-editor-lockdown' ),
			'previous' => __( 'Previous section', 'mrn-editor-lockdown' ),
			'next'     => __( 'Next section', 'mrn-editor-lockdown' ),
			'add'      => __( 'Add section', 'mrn-editor-lockdown' ),
			'menu'     => __( 'Site menu', 'mrn-editor-lockdown' ),
			'back'     => __( 'Back to list', 'mrn-editor-lockdown' ),
			'backUrl'  => admin_url( 'edit.php?post_type=' . get_current_screen()->post_type ),
			'alerts'   => __( 'Page alerts', 'mrn-editor-lockdown' ),
			'pageName' => __( 'Page title', 'mrn-editor-lockdown' ),
			'editSelected' => __( 'Edit selected section', 'mrn-editor-lockdown' ),
			'hint'     => __( 'Choose a section to edit. Arrange sections to see the full page order.', 'mrn-editor-lockdown' ),
			'moveUp' => __( 'Move section up', 'mrn-editor-lockdown' ),
			'moveDown' => __( 'Move section down', 'mrn-editor-lockdown' ),
			'moved' => __( 'Section moved to position', 'mrn-editor-lockdown' ),
			'arrangeHint' => __( 'Drag the native section handles, or use Move section up / down for the selected section. Select a section in the outline to return to focused editing.', 'mrn-editor-lockdown' ),
			'settingsHint' => __( 'Publishing, page attributes and other page options. Edit content in the Content tab.', 'mrn-editor-lockdown' ),
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

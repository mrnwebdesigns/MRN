<?php
/**
 * Builder admin behavior.
 *
 * @package mrn-base-stack
 */

/**
 * Check whether stack ACF admin helpers should run on the current screen.
 *
 * @param mixed $screen Current admin screen.
 * @return bool
 */
function mrn_base_stack_admin_is_safe_acf_editor_helper_screen( $screen ) {
	if ( ! $screen instanceof WP_Screen ) {
		return false;
	}

	if ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
		return false;
	}

	$post_type = sanitize_key( (string) $screen->post_type );
	$screen_id = sanitize_key( (string) $screen->id );
	$base      = sanitize_key( (string) $screen->base );
	$excluded  = array(
		'acf-field',
		'acf-field-group',
	);

	if ( in_array( $post_type, $excluded, true ) || in_array( $screen_id, $excluded, true ) || in_array( $base, $excluded, true ) ) {
		return false;
	}

	return true;
}

/**
 * Determine whether automatic ACF row-level collapsing may run.
 *
 * Automatic flexible-content/repeater row collapsing is enabled for stack
 * singular editors, but the JavaScript queues work after editor idle time and
 * aborts once the editor starts interacting with fields.
 *
 * @param string $row_type  ACF row type: flexible_content or repeater.
 * @param string $post_type Current post type slug.
 * @return bool
 */
function mrn_base_stack_admin_is_initial_acf_row_collapse_enabled( $row_type, $post_type = '' ) {
	$row_type  = sanitize_key( (string) $row_type );
	$post_type = sanitize_key( (string) $post_type );

	/**
	 * Global switch for automatic ACF row collapse on editor load.
	 *
	 * Defaults to true because the current implementation is idle-scheduled and
	 * interaction-aware. Return false to keep all rows open by default.
	 *
	 * @param bool   $enabled   Whether automatic ACF row collapsing is enabled.
	 * @param string $row_type  ACF row type: flexible_content or repeater.
	 * @param string $post_type Current post type slug.
	 */
	$enabled = (bool) apply_filters( 'mrn_base_stack_admin_initial_collapse_enabled', true, $row_type, $post_type );

	/**
	 * Shared opt-in for automatic ACF row-level collapsing.
	 *
	 * @param bool   $enabled   Whether automatic ACF row collapsing is enabled.
	 * @param string $row_type  ACF row type: flexible_content or repeater.
	 * @param string $post_type Current post type slug.
	 */
	$enabled = (bool) apply_filters( 'mrn_base_stack_admin_acf_row_collapse_enabled', $enabled, $row_type, $post_type );

	if ( 'flexible_content' === $row_type ) {
		/**
		 * Opt in to automatic flexible-content row collapsing.
		 *
		 * @param bool   $enabled   Whether automatic flexible row collapsing is enabled.
		 * @param string $post_type Current post type slug.
		 */
		return (bool) apply_filters( 'mrn_base_stack_admin_initial_flexible_row_collapse_enabled', $enabled, $post_type );
	}

	if ( 'repeater' === $row_type ) {
		/**
		 * Opt in to automatic repeater row collapsing.
		 *
		 * @param bool   $enabled   Whether automatic repeater row collapsing is enabled.
		 * @param string $post_type Current post type slug.
		 */
		return (bool) apply_filters( 'mrn_base_stack_admin_initial_repeater_row_collapse_enabled', $enabled, $post_type );
	}

	return $enabled;
}

/**
 * Enqueue builder admin assets for supported classic editor screens.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 * @return void
 */
function mrn_base_stack_admin_enqueue_builder_assets( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen ) {
		return;
	}

	if ( ! mrn_base_stack_admin_is_safe_acf_editor_helper_screen( $screen ) ) {
		return;
	}

	$post_type = sanitize_key( (string) $screen->post_type );
	if ( '' === $post_type && isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
		$post_type = sanitize_key( (string) wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
	}

	if ( '' === $post_type && 'post-new.php' === $hook_suffix ) {
		$post_type = 'post';
	}

	if ( '' === $post_type || ! in_array( $post_type, mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		return;
	}

	if ( function_exists( 'wp_enqueue_editor' ) ) {
		wp_enqueue_editor();
	}

	$content_builder_admin_js_path = get_template_directory() . '/js/content-builder-admin.js';

	wp_enqueue_script(
		'mrn-base-stack-content-builder-admin',
		get_template_directory_uri() . '/js/content-builder-admin.js',
		array( 'jquery' ),
		file_exists( $content_builder_admin_js_path ) ? (string) filemtime( $content_builder_admin_js_path ) : _S_VERSION,
		true
	);

	wp_enqueue_script(
		'mrn-base-stack-row-flex-layout-admin',
		get_template_directory_uri() . '/js/admin-row-flex-layout.js',
		array( 'jquery', 'mrn-base-stack-content-builder-admin' ),
		_S_VERSION,
		true
	);

	$post_id = 0;
	if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin context lookup.
		$post_id = absint( wp_unslash( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin context lookup.
	} elseif ( isset( $_POST['post_ID'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only admin context lookup.
		$post_id = absint( wp_unslash( $_POST['post_ID'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only admin context lookup.
	}

	$row_flex_settings         = ( $post_id > 0 && function_exists( 'mrn_base_stack_get_builder_row_flex_payload' ) ) ? mrn_base_stack_get_builder_row_flex_payload( $post_id ) : array();
	$row_flex_supported_fields = function_exists( 'mrn_base_stack_get_builder_row_flex_supported_fields' )
		? mrn_base_stack_get_builder_row_flex_supported_fields()
		: array( 'page_content_rows', 'page_after_content_rows', 'page_hero_rows', 'page_sidebar_rows' );

	$initial_flexible_collapse_enabled = mrn_base_stack_admin_is_initial_acf_row_collapse_enabled( 'flexible_content', $post_type );
	$initial_repeater_collapse_enabled = mrn_base_stack_admin_is_initial_acf_row_collapse_enabled( 'repeater', $post_type );
	$initial_collapse_delay_ms         = absint(
		/**
		 * Delay before initial ACF row collapse begins.
		 *
		 * The browser idle callback still gates execution after this delay when
		 * available, so this value is only the earliest possible start.
		 *
		 * @param int    $delay_ms  Delay in milliseconds.
		 * @param string $post_type Current post type slug.
		 */
		apply_filters( 'mrn_base_stack_admin_initial_acf_row_collapse_delay_ms', 900, $post_type )
	);
	$initial_flexible_collapse_max_rows = absint(
		/**
		 * Maximum flexible-content rows to collapse during initial editor cleanup.
		 *
		 * @param int    $max_rows  Maximum row count.
		 * @param string $post_type Current post type slug.
		 */
		apply_filters( 'mrn_base_stack_admin_initial_flexible_row_collapse_max_rows', 120, $post_type )
	);
	$initial_repeater_collapse_max_rows = absint(
		/**
		 * Maximum repeater rows to collapse during initial editor cleanup.
		 *
		 * @param int    $max_rows  Maximum row count.
		 * @param string $post_type Current post type slug.
		 */
		apply_filters( 'mrn_base_stack_admin_initial_repeater_row_collapse_max_rows', 160, $post_type )
	);

	wp_localize_script(
		'mrn-base-stack-content-builder-admin',
		'mrnBaseStackBuilderAdmin',
		array(
			'ajaxUrl'                        => admin_url( 'admin-ajax.php' ),
			'nonce'                          => wp_create_nonce( 'mrn-base-stack-convert-reusable-block' ),
			'action'                         => 'mrn_base_stack_prepare_page_specific_block',
			'actionTitle'                    => 'Convert to page-specific',
			'confirmTitle'                   => 'Replace With Page-Specific Copy',
			'confirmText'                    => 'This will replace the reusable block reference in this row with a page-only copy you can edit here. The original reusable block will stay in the library unchanged.',
			'confirmButton'                  => 'Convert to Page-Specific',
			'cancelButton'                   => 'Cancel',
			'emptySelectionText'             => 'Choose a reusable block first.',
			'editBlockUrlPattern'            => admin_url( 'post.php?post=__MRN_BLOCK_ID__&action=edit' ),
			'editBlockText'                  => 'Edit selected reusable block',
			'editBlockTitle'                 => 'Edit this reusable block in a new tab',
			'loadingText'                    => 'Converting block...',
			'successText'                    => 'This row is now a page-specific block.',
			'errorText'                      => 'The block could not be converted.',
			'contentListTaxonomies'          => function_exists( 'mrn_base_stack_get_content_list_post_type_taxonomy_map' ) ? mrn_base_stack_get_content_list_post_type_taxonomy_map() : array(),
			'contentListDisplayModes'        => function_exists( 'mrn_base_stack_get_content_list_display_mode_choice_map' ) ? mrn_base_stack_get_content_list_display_mode_choice_map() : array(),
			'contentListDisplayStyles'       => function_exists( 'mrn_base_stack_get_content_list_display_style_choice_map' ) ? mrn_base_stack_get_content_list_display_style_choice_map() : array(),
			'initialCollapseEnabled'         => $initial_flexible_collapse_enabled || $initial_repeater_collapse_enabled,
			'initialFlexibleCollapseEnabled' => $initial_flexible_collapse_enabled,
			'initialRepeaterCollapseEnabled' => $initial_repeater_collapse_enabled,
			'initialCollapseDelayMs'         => $initial_collapse_delay_ms,
			'initialFlexibleCollapseMaxRows' => $initial_flexible_collapse_max_rows,
			'initialRepeaterCollapseMaxRows' => $initial_repeater_collapse_max_rows,
			'rowFlex'                        => array(
				'nonce'           => wp_create_nonce( 'mrn-base-stack-row-flex-layout' ),
				'nonceField'      => 'mrn_base_stack_row_flex_nonce',
				'payloadField'    => 'mrn_base_stack_row_flex_payload',
				'supportedFields' => $row_flex_supported_fields,
				'savedSettings'   => $row_flex_settings,
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'mrn_base_stack_admin_enqueue_builder_assets' );

/**
 * Normalize ACF layout picker metadata.
 *
 * @param array<string, mixed> $metadata Raw metadata.
 * @return array<string, mixed>
 */
function mrn_base_stack_normalize_acf_layout_picker_metadata( array $metadata ) {
	$keywords = array();
	if ( isset( $metadata['keywords'] ) ) {
		$raw_keywords = is_array( $metadata['keywords'] ) ? $metadata['keywords'] : explode( ',', (string) $metadata['keywords'] );
		$keywords     = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $keyword ) {
							return sanitize_text_field( wp_strip_all_tags( (string) $keyword ) );
						},
						$raw_keywords
					)
				)
			)
		);
	}

	return array(
		'description'           => isset( $metadata['description'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $metadata['description'] ) ) : '',
		'icon'                  => isset( $metadata['icon'] ) ? mrn_base_stack_normalize_acf_layout_picker_dashicon( (string) $metadata['icon'] ) : '',
		'preview_thumbnail_url' => isset( $metadata['preview_thumbnail_url'] ) ? esc_url_raw( (string) $metadata['preview_thumbnail_url'] ) : '',
		'preview_alt_text'      => isset( $metadata['preview_alt_text'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $metadata['preview_alt_text'] ) ) : '',
		'category'              => isset( $metadata['category'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $metadata['category'] ) ) : '',
		'keywords'              => $keywords,
	);
}

/**
 * Normalize a Dashicons class for the ACF layout picker.
 *
 * @param string $dashicon Dashicon class.
 * @return string
 */
function mrn_base_stack_normalize_acf_layout_picker_dashicon( $dashicon ) {
	$dashicon = strtolower( trim( (string) $dashicon ) );
	if ( '' === $dashicon || 'dashicons' === $dashicon ) {
		return '';
	}

	if ( preg_match( '/dashicons-[a-z0-9-]+/', $dashicon, $matches ) ) {
		return sanitize_html_class( $matches[0] );
	}

	$dashicon = preg_replace( '/[^a-z0-9-]/', '', $dashicon );
	if ( '' === $dashicon ) {
		return '';
	}

	if ( 0 !== strpos( $dashicon, 'dashicons-' ) ) {
		$dashicon = 'dashicons-' . $dashicon;
	}

	return 'dashicons-dashicons' === $dashicon ? '' : sanitize_html_class( $dashicon );
}

/**
 * Get metadata for one ACF flexible-content layout in the admin picker.
 *
 * @param string               $layout_name Layout name/slug.
 * @param array<string, mixed> $layout      ACF layout definition.
 * @param array<string, mixed> $field       ACF flexible-content field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_acf_layout_picker_metadata( $layout_name, $layout = array(), $field = array() ) {
	$layout_name = sanitize_key( (string) $layout_name );
	$layout      = is_array( $layout ) ? $layout : array();
	$field       = is_array( $field ) ? $field : array();

	$metadata = array(
		'description'           => '',
		'icon'                  => '',
		'preview_thumbnail_url' => '',
		'preview_alt_text'      => '',
		'category'              => '',
		'keywords'              => array(),
	);

	foreach ( array( 'description', 'instructions', 'picker_description', 'layout_picker_description' ) as $description_key ) {
		if ( isset( $layout[ $description_key ] ) && '' !== trim( (string) $layout[ $description_key ] ) ) {
			$metadata['description'] = (string) $layout[ $description_key ];
			break;
		}
	}

	foreach ( array( 'icon', 'dashicon', 'picker_icon', 'layout_picker_icon' ) as $icon_key ) {
		if ( isset( $layout[ $icon_key ] ) && '' !== trim( (string) $layout[ $icon_key ] ) ) {
			$metadata['icon'] = (string) $layout[ $icon_key ];
			break;
		}
	}

	foreach ( array( 'preview_thumbnail_url', 'preview_url', 'picker_preview_url', 'layout_picker_preview_url' ) as $preview_key ) {
		if ( isset( $layout[ $preview_key ] ) && '' !== trim( (string) $layout[ $preview_key ] ) ) {
			$metadata['preview_thumbnail_url'] = (string) $layout[ $preview_key ];
			break;
		}
	}

	foreach ( array( 'preview_alt_text', 'preview_alt', 'picker_preview_alt', 'layout_picker_preview_alt' ) as $alt_key ) {
		if ( isset( $layout[ $alt_key ] ) && '' !== trim( (string) $layout[ $alt_key ] ) ) {
			$metadata['preview_alt_text'] = (string) $layout[ $alt_key ];
			break;
		}
	}

	foreach ( array( 'category', 'group', 'picker_category', 'layout_picker_category' ) as $category_key ) {
		if ( isset( $layout[ $category_key ] ) && '' !== trim( (string) $layout[ $category_key ] ) ) {
			$metadata['category'] = (string) $layout[ $category_key ];
			break;
		}
	}

	foreach ( array( 'keywords', 'picker_keywords', 'layout_picker_keywords' ) as $keywords_key ) {
		if ( isset( $layout[ $keywords_key ] ) ) {
			$metadata['keywords'] = $layout[ $keywords_key ];
			break;
		}
	}

	/**
	 * Filter metadata shown in the stack ACF flexible-content layout picker.
	 *
	 * @param array<string, mixed> $metadata    Picker metadata.
	 * @param string               $layout_name Layout name/slug.
	 * @param array<string, mixed> $layout      ACF layout definition.
	 * @param array<string, mixed> $field       ACF flexible-content field definition.
	 */
	$metadata = apply_filters(
		'mrn_base_stack_acf_layout_picker_metadata',
		$metadata,
		$layout_name,
		$layout,
		$field
	);

	return mrn_base_stack_normalize_acf_layout_picker_metadata( is_array( $metadata ) ? $metadata : array() );
}

/**
 * Build a lightweight metadata map for the admin layout picker.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_acf_layout_picker_metadata_map() {
	$preloaded_map = array();
	if ( function_exists( 'mrn_config_helper_get_acf_layout_picker_metadata_map' ) ) {
		$preloaded_map = mrn_config_helper_get_acf_layout_picker_metadata_map();
	} else {
		$preloaded_map = function_exists( 'mrn_config_helper_get_builder_layout_picker_metadata' )
			? mrn_config_helper_get_builder_layout_picker_metadata()
			: array();
		$preloaded_map = apply_filters( 'mrn_base_stack_acf_layout_picker_metadata_map', is_array( $preloaded_map ) ? $preloaded_map : array() );
	}
	if ( is_array( $preloaded_map ) && ! empty( $preloaded_map ) ) {
		$map = array();
		foreach ( $preloaded_map as $layout_name => $metadata ) {
			$layout_name = sanitize_key( (string) $layout_name );
			if ( '' === $layout_name || ! is_array( $metadata ) ) {
				continue;
			}

			$map[ $layout_name ] = mrn_base_stack_normalize_acf_layout_picker_metadata( $metadata );
		}

		return $map;
	}

	if ( ! has_filter( 'mrn_base_stack_acf_layout_picker_metadata' ) ) {
		return array();
	}

	$field_names = array( 'page_hero_rows', 'page_content_rows', 'page_after_content_rows', 'page_sidebar_rows' );
	$map         = array();

	foreach ( $field_names as $field_name ) {
		$field_name = sanitize_key( $field_name );
		$field      = array(
			'name' => $field_name,
		);
		$catalog    = function_exists( 'mrn_base_stack_get_builder_layout_allowlist_catalog' )
			? mrn_base_stack_get_builder_layout_allowlist_catalog( $field_name )
			: array();

		if ( empty( $catalog ) || ! is_array( $catalog ) ) {
			continue;
		}

		foreach ( $catalog as $layout_name => $layout ) {
			if ( ! is_array( $layout ) ) {
				continue;
			}

			$layout_name = sanitize_key( (string) $layout_name );
			if ( '' === $layout_name ) {
				continue;
			}

			$metadata = mrn_base_stack_get_acf_layout_picker_metadata( $layout_name, $layout, $field );
			if ( '' === $metadata['description'] && '' === $metadata['icon'] && '' === $metadata['preview_thumbnail_url'] && '' === $metadata['category'] && empty( $metadata['keywords'] ) ) {
				continue;
			}

			$map[ $layout_name ] = $metadata;
		}
	}

	return $map;
}

/**
 * Enqueue the stack ACF flexible-content layout picker.
 *
 * @return void
 */
function mrn_base_stack_enqueue_acf_layout_picker_assets() {
	if ( ! is_admin() ) {
		return;
	}

	if ( function_exists( 'mrn_config_helper_has_acf_layout_picker' ) && mrn_config_helper_has_acf_layout_picker() ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen || ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
		return;
	}

	$post_type = sanitize_key( (string) $screen->post_type );
	if ( '' === $post_type || ! in_array( $post_type, mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		return;
	}

	$css_path = get_template_directory() . '/css/acf-layout-picker.css';
	$js_path  = get_template_directory() . '/js/acf-layout-picker.js';

	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'mrn-base-stack-acf-layout-picker',
			get_template_directory_uri() . '/css/acf-layout-picker.css',
			array(),
			(string) filemtime( $css_path )
		);
	}

	if ( file_exists( $js_path ) ) {
		wp_enqueue_script(
			'mrn-base-stack-acf-layout-picker',
			get_template_directory_uri() . '/js/acf-layout-picker.js',
			array( 'acf-input' ),
			(string) filemtime( $js_path ),
			true
		);

		wp_localize_script(
			'mrn-base-stack-acf-layout-picker',
			'mrnBaseStackAcfLayoutPicker',
			array(
				'title'       => 'Add a Section',
				'subtitle'    => 'Pick a layout to insert into this page.',
				'searchLabel' => 'Search sections',
				'emptyText'   => 'No sections match that search.',
				'metadata'    => mrn_base_stack_get_acf_layout_picker_metadata_map(),
			)
		);
	}
}
add_action( 'acf/input/admin_enqueue_scripts', 'mrn_base_stack_enqueue_acf_layout_picker_assets' );

/**
 * Add lightweight admin CSS for custom content-builder row actions.
 *
 * @return void
 */
function mrn_base_stack_admin_builder_action_styles() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen || ! in_array( sanitize_key( (string) $screen->post_type ), mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		return;
	}
	?>
	<style id="mrn-base-stack-builder-actions">
		.acf-fc-layout-controls .mrn-convert-reusable-block-action,
		.acf-fc-layout-actions .mrn-convert-reusable-block-action,
		.acf-fc-layout-controlls .mrn-convert-reusable-block-action {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 28px;
			height: 28px;
			color: inherit;
			text-decoration: none;
			border: 0;
			background: transparent;
			box-shadow: none;
			opacity: 0;
			pointer-events: none;
			transition: opacity 0.15s ease;
		}

		.layout:hover .mrn-convert-reusable-block-action,
		.layout:focus-within .mrn-convert-reusable-block-action,
		.layout.active-layout .mrn-convert-reusable-block-action,
		.layout.-hover .mrn-convert-reusable-block-action {
			color: #fff;
			opacity: 0.9;
			pointer-events: auto;
		}

		.acf-fc-layout-controls .mrn-convert-reusable-block-action:hover,
		.acf-fc-layout-actions .mrn-convert-reusable-block-action:hover,
		.acf-fc-layout-controlls .mrn-convert-reusable-block-action:hover,
		.acf-fc-layout-controls .mrn-convert-reusable-block-action:focus,
		.acf-fc-layout-actions .mrn-convert-reusable-block-action:focus,
		.acf-fc-layout-controlls .mrn-convert-reusable-block-action:focus {
			opacity: 1;
			outline: none;
			box-shadow: none;
		}

		.mrn-convert-reusable-block-action .dashicons {
			font-size: 20px;
			width: 20px;
			height: 20px;
			line-height: 20px;
		}

		.layout[data-layout="reusable_block"] .mrn-reusable-block-edit-link {
			margin: 8px 0 0;
		}

		.layout[data-layout="reusable_block"] .mrn-reusable-block-edit-link a {
			display: inline-flex;
			align-items: center;
			gap: 4px;
			text-decoration: none;
		}

		.layout[data-layout="reusable_block"] .mrn-reusable-block-edit-link a:hover,
		.layout[data-layout="reusable_block"] .mrn-reusable-block-edit-link a:focus {
			text-decoration: underline;
		}

		.layout[data-layout="reusable_block"] .mrn-reusable-block-edit-link .dashicons {
			font-size: 16px;
			width: 16px;
			height: 16px;
			line-height: 16px;
		}

		.layout[data-layout="content_lists"] > .acf-fields {
			position: relative;
		}

		.layout[data-layout="content_lists"].mrn-content-list-is-syncing > .acf-fields::before {
			content: "";
			position: absolute;
			inset: 0;
			background: rgba(255, 255, 255, 0.55);
			pointer-events: none;
			z-index: 2;
		}

		.layout[data-layout="content_lists"].mrn-content-list-is-syncing > .acf-fields::after {
			content: "";
			position: absolute;
			top: 18px;
			right: 18px;
			width: 18px;
			height: 18px;
			border: 2px solid #8c8f94;
			border-right-color: transparent;
			border-radius: 50%;
			animation: mrn-content-list-admin-spin 0.75s linear infinite;
			pointer-events: none;
			z-index: 3;
		}

		.layout[data-layout="content_lists"] .acf-field.mrn-content-list-legacy-field-disabled {
			opacity: 0.5;
			position: relative;
		}

		.layout[data-layout="content_lists"] .acf-field.mrn-content-list-legacy-field-disabled .acf-input {
			position: relative;
		}

		.layout[data-layout="content_lists"] .acf-field.mrn-content-list-legacy-field-disabled .acf-input::after {
			content: "";
			position: absolute;
			inset: 0;
			background: rgba(255, 255, 255, 0.01);
			cursor: not-allowed;
			z-index: 2;
		}

			.layout[data-layout="content_lists"] .acf-field.mrn-content-list-legacy-field-disabled .acf-label label::after {
				content: " (Handled by Display Mode)";
				font-weight: 400;
				color: #646970;
			}

			.layout .acf-tab-group li.mrn-row-flex-tab a {
				font-weight: 600;
			}

			.layout .acf-field.mrn-row-flex-panel {
				display: none;
				border-top: 1px solid #dcdcde;
				padding: 16px;
				background: #fff;
			}

			.layout.mrn-row-flex-tab-active > .acf-fields > .acf-field:not(.mrn-row-flex-panel) {
				display: none !important;
			}

			.layout.mrn-row-flex-tab-active > .acf-fields > .acf-field.mrn-row-flex-panel {
				display: block !important;
			}

			.layout .mrn-row-flex-panel__description {
				margin: 0 0 14px;
				color: #50575e;
			}

			.layout .mrn-row-flex-panel__grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
				gap: 12px;
			}

			.layout .mrn-row-flex-panel__control {
				display: grid;
				gap: 6px;
			}

			.layout .mrn-row-flex-panel__control-label {
				font-weight: 600;
				color: #1d2327;
			}

			.layout .mrn-row-flex-panel__checkbox {
				display: inline-flex;
				align-items: center;
				gap: 8px;
				font-weight: 600;
				color: #1d2327;
			}

			.layout .mrn-row-flex-panel__control select,
			.layout .mrn-row-flex-panel__control input[type="number"] {
				width: 100%;
				max-width: 100%;
			}

			@keyframes mrn-content-list-admin-spin {
				from {
					transform: rotate(0deg);
				}

				to {
					transform: rotate(360deg);
				}
			}
	</style>
	<?php
}
add_action( 'admin_head', 'mrn_base_stack_admin_builder_action_styles' );

/**
 * Save non-ACF row-level flex controls for builder rows.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post Current post object.
 * @return void
 */
function mrn_base_stack_save_builder_row_flex_layout_meta( $post_id, $post ) {
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	$post_type = sanitize_key( (string) $post->post_type );
	if ( '' === $post_type || ! in_array( $post_type, mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$nonce_field = isset( $_POST['mrn_base_stack_row_flex_nonce'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verification handled inline.
		? sanitize_text_field( wp_unslash( $_POST['mrn_base_stack_row_flex_nonce'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verification handled inline.
		: '';

	if ( '' === $nonce_field || ! wp_verify_nonce( $nonce_field, 'mrn-base-stack-row-flex-layout' ) ) {
		return;
	}

	$raw_payload = isset( $_POST['mrn_base_stack_row_flex_payload'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verification handled inline.
		? sanitize_textarea_field( wp_unslash( (string) $_POST['mrn_base_stack_row_flex_payload'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verification handled inline.
		: '';

	$decoded_payload = array();
	if ( is_string( $raw_payload ) && '' !== trim( $raw_payload ) ) {
		$decoded = json_decode( $raw_payload, true );
		if ( is_array( $decoded ) ) {
			$decoded_payload = $decoded;
		}
	}

	$sanitized = function_exists( 'mrn_base_stack_sanitize_builder_row_flex_payload' )
		? mrn_base_stack_sanitize_builder_row_flex_payload( $decoded_payload )
		: array();

	$meta_key = function_exists( 'mrn_base_stack_get_builder_row_flex_meta_key' )
		? mrn_base_stack_get_builder_row_flex_meta_key()
		: '_mrn_builder_row_flex_settings';

	if ( empty( $sanitized ) ) {
		delete_post_meta( $post_id, $meta_key );
		return;
	}

	update_post_meta( $post_id, $meta_key, $sanitized );
}
add_action( 'save_post', 'mrn_base_stack_save_builder_row_flex_layout_meta', 20, 2 );

/**
 * Get post types where the native WordPress editor should be hidden.
 *
 * Defaults to no post types so the Classic Editor body field remains available
 * unless a site explicitly opts into hiding it.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_native_editor_hidden_post_types() {
	$post_types = array();

	/**
	 * Filter post types where the native editor should be hidden.
	 *
	 * @param array<int, string> $post_types Post type slugs.
	 */
	$post_types = apply_filters( 'mrn_base_stack_native_editor_hidden_post_types', $post_types );

	if ( ! is_array( $post_types ) ) {
		return array();
	}

	return array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $post_types )
			)
		)
	);
}

/**
 * Hide the native WordPress content editor on configured singular screens while
 * preserving screen compatibility for plugins that expect the classic editor context.
 */
function mrn_base_stack_hide_native_editor_metabox() {
	foreach ( mrn_base_stack_get_native_editor_hidden_post_types() as $post_type ) {
		remove_meta_box( 'postdivrich', $post_type, 'normal' );
	}
}
add_action( 'add_meta_boxes', 'mrn_base_stack_hide_native_editor_metabox', 20 );

/**
 * Add a final CSS-level guard so the native content editor stays hidden even if
 * another plugin re-adds it after the initial metabox pass.
 */
function mrn_base_stack_hide_native_editor_css() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen ) {
		return;
	}

	if ( ! in_array( sanitize_key( (string) $screen->post_type ), mrn_base_stack_get_native_editor_hidden_post_types(), true ) ) {
		return;
	}
	?>
	<style id="mrn-base-stack-hide-native-editor">
		#postdivrich {
			display: none !important;
		}
	</style>
	<?php
}
add_action( 'admin_head', 'mrn_base_stack_hide_native_editor_css' );

/**
 * Reorganize supported editorial CPT edit screens so key fields land in stable,
 * intentional positions regardless of other plugin metaboxes.
 *
 * @param string  $post_type Post type slug.
 * @param WP_Post $post      Current post object.
 * @return void
 */
function mrn_base_stack_customize_editorial_cpt_edit_screen( $post_type, $post ) {
	$post_type = sanitize_key( (string) $post_type );

	if ( ! in_array( $post_type, array( 'gallery', 'case_study' ), true ) || ! $post instanceof WP_Post ) {
		return;
	}

	remove_meta_box( 'postexcerpt', $post_type, 'normal' );
	remove_meta_box( 'postexcerpt', $post_type, 'advanced' );
	remove_meta_box( 'postexcerpt', $post_type, 'side' );
}
add_action( 'add_meta_boxes', 'mrn_base_stack_customize_editorial_cpt_edit_screen', 100, 2 );

/**
 * Render the custom excerpt field directly after the title field.
 *
 * @param WP_Post $post Current post object.
 * @return void
 */
function mrn_base_stack_render_editorial_cpt_excerpt_after_title( $post ) {
	if ( ! $post instanceof WP_Post || 'gallery' !== $post->post_type ) {
		return;
	}

	$title       = __( 'Gallery Excerpt', 'mrn-base-stack' );
	$description = __( 'Write the short summary that should appear directly under the Gallery title and in listings that use the excerpt.', 'mrn-base-stack' );

	?>
	<div class="mrn-blog-excerpt-panel">
		<div class="mrn-blog-excerpt-panel__header">
			<h2 class="mrn-blog-excerpt-panel__title"><?php echo esc_html( $title ); ?></h2>
		</div>
		<div class="mrn-blog-excerpt-panel__body">
			<p><?php echo esc_html( $description ); ?></p>
			<textarea id="excerpt" name="excerpt" rows="4" class="widefat"><?php echo esc_textarea( $post->post_excerpt ); ?></textarea>
		</div>
	</div>
	<?php
}
add_action( 'edit_form_after_title', 'mrn_base_stack_render_editorial_cpt_excerpt_after_title' );

/**
 * Tidy the custom editorial excerpt panel so it reads like part of the native edit
 * flow instead of a generic postbox dropped into the content column.
 *
 * @return void
 */
function mrn_base_stack_editorial_cpt_edit_screen_styles() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen || 'gallery' !== sanitize_key( (string) $screen->post_type ) ) {
		return;
	}
	?>
	<style id="mrn-base-stack-blog-edit-screen">
		.mrn-blog-excerpt-panel {
			margin: 16px 0 20px;
			background: #fff;
			border-color: #dcdcde;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
		}

		.mrn-blog-excerpt-panel__header {
			border-bottom: 1px solid #dcdcde;
			padding: 0 16px;
		}

		.mrn-blog-excerpt-panel__title {
			margin: 0;
			padding: 12px 0;
			font-size: 14px;
			line-height: 1.4;
		}

		.mrn-blog-excerpt-panel__body {
			padding: 16px;
		}

		.mrn-blog-excerpt-panel p {
			margin-top: 0;
			color: #50575e;
		}

		.mrn-blog-excerpt-panel textarea {
			min-height: 110px;
			resize: vertical;
		}
	</style>
	<?php
}
add_action( 'admin_head', 'mrn_base_stack_editorial_cpt_edit_screen_styles' );

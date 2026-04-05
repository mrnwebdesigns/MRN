<?php
/**
 * Builder admin behavior.
 *
 * @package mrn-base-stack
 */

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
	if ( ! $screen instanceof WP_Screen || ! in_array( sanitize_key( (string) $screen->post_type ), mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		return;
	}

	if ( function_exists( 'wp_enqueue_editor' ) ) {
		wp_enqueue_editor();
	}

	wp_enqueue_script(
		'mrn-base-stack-content-builder-admin',
		get_template_directory_uri() . '/js/content-builder-admin.js',
		array( 'jquery' ),
		_S_VERSION,
		true
	);

	wp_localize_script(
		'mrn-base-stack-content-builder-admin',
		'mrnBaseStackBuilderAdmin',
		array(
			'ajaxUrl'                 => admin_url( 'admin-ajax.php' ),
			'nonce'                   => wp_create_nonce( 'mrn-base-stack-convert-reusable-block' ),
			'action'                  => 'mrn_base_stack_prepare_page_specific_block',
			'actionTitle'             => 'Convert to page-specific',
			'confirmTitle'            => 'Replace With Page-Specific Copy',
			'confirmText'             => 'This will replace the reusable block reference in this row with a page-only copy you can edit here. The original reusable block will stay in the library unchanged.',
			'confirmButton'           => 'Convert to Page-Specific',
			'cancelButton'            => 'Cancel',
			'emptySelectionText'      => 'Choose a reusable block first.',
			'loadingText'             => 'Converting block...',
			'successText'             => 'This row is now a page-specific block.',
			'errorText'               => 'The block could not be converted.',
			'builderLayouts'          => mrn_base_stack_get_builder_add_row_layout_menu_items(),
			'disabledLayouts'         => function_exists( 'mrn_base_stack_get_hidden_builder_layouts' ) ? mrn_base_stack_get_hidden_builder_layouts() : array(),
			'contentListTaxonomies'   => function_exists( 'mrn_base_stack_get_content_list_post_type_taxonomy_map' ) ? mrn_base_stack_get_content_list_post_type_taxonomy_map() : array(),
			'contentListDisplayModes' => function_exists( 'mrn_base_stack_get_content_list_display_mode_choice_map' ) ? mrn_base_stack_get_content_list_display_mode_choice_map() : array(),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'mrn_base_stack_admin_enqueue_builder_assets' );

/**
 * Build live Add Row menu metadata from the registered page builder layouts.
 *
 * This keeps editor menu behavior aligned with the actual flexible-content
 * layouts instead of relying on parallel hardcoded lists in admin JavaScript.
 *
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_builder_add_row_layout_menu_items() {
	if ( ! function_exists( 'acf_get_field' ) ) {
		return array();
	}

	$field = acf_get_field( 'field_mrn_page_content_rows' );
	if ( ! is_array( $field ) || empty( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
		return array();
	}

	$items = array();

	foreach ( $field['layouts'] as $layout ) {
		if ( ! is_array( $layout ) ) {
			continue;
		}

		$name  = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
		$label = isset( $layout['label'] ) ? trim( wp_strip_all_tags( (string) $layout['label'] ) ) : '';

		if ( '' === $name ) {
			continue;
		}

		if ( '' === $label ) {
			$label = ucfirst( str_replace( array( '_', '-' ), ' ', $name ) );
		}

		$is_page_only = false !== stripos( $label, '(Page Only)' );
		$is_reusable  = false !== stripos( $label, 'reusable' ) || false !== stripos( $label, 'shared' );

		$items[] = array(
			'name'        => $name,
			'label'       => $label,
			'isPageOnly'  => $is_page_only,
			'isReusable'  => $is_reusable,
		);
	}

	return $items;
}

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

		li.mrn-builder-menu-header {
			position: relative;
			margin-top: 14px;
			padding-top: 16px;
			padding-left: 12px;
			padding-right: 12px;
			font-size: 11px;
			font-weight: 700;
			letter-spacing: 0.04em;
			text-transform: uppercase;
			color: #2c3338;
			cursor: default;
			pointer-events: none;
		}

		li.mrn-builder-menu-header::before {
			content: "";
			position: absolute;
			top: 0;
			left: 12px;
			right: 12px;
			border-top: 1px solid #dcdcde;
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
 * Hide the native WordPress content editor on posts and pages while preserving
 * screen compatibility for plugins that expect the classic editor context.
 */
function mrn_base_stack_hide_native_editor_metabox() {
	remove_meta_box( 'postdivrich', 'post', 'normal' );
	remove_meta_box( 'postdivrich', 'page', 'normal' );
	remove_meta_box( 'postdivrich', 'blog', 'normal' );
	remove_meta_box( 'postdivrich', 'gallery', 'normal' );
	remove_meta_box( 'postdivrich', 'testimonial', 'normal' );
	remove_meta_box( 'postdivrich', 'case_study', 'normal' );
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

	if ( ! in_array( sanitize_key( (string) $screen->post_type ), mrn_base_stack_get_singular_shell_post_types(), true ) ) {
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

	if ( ! in_array( $post_type, array( 'blog', 'gallery', 'case_study' ), true ) || ! $post instanceof WP_Post ) {
		return;
	}

	if ( 'blog' === $post_type ) {
		remove_meta_box( 'authordiv', $post_type, 'normal' );
		remove_meta_box( 'authordiv', $post_type, 'advanced' );
		add_meta_box( 'authordiv', __( 'Author', 'mrn-base-stack' ), 'post_author_meta_box', $post_type, 'side', 'high' );
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
	if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'blog', 'gallery' ), true ) ) {
		return;
	}

	$title       = 'blog' === $post->post_type ? __( 'Blog Excerpt', 'mrn-base-stack' ) : __( 'Gallery Excerpt', 'mrn-base-stack' );
	$description = 'blog' === $post->post_type
		? __( 'Write the short summary that should appear directly under the Blog title and in listings that use the excerpt.', 'mrn-base-stack' )
		: __( 'Write the short summary that should appear directly under the Gallery title and in listings that use the excerpt.', 'mrn-base-stack' );

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
	if ( ! $screen instanceof WP_Screen || ! in_array( sanitize_key( (string) $screen->post_type ), array( 'blog', 'gallery' ), true ) ) {
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

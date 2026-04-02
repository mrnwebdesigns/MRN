<?php

/**

 * Builder admin behavior.

 *

 * @package mrn-base-stack

 */

function mrn_base_stack_admin_enqueue_builder_assets( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen || ! mrn_base_stack_is_builder_supported_post_type( $screen->post_type ) ) {
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
			'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
			'nonce'              => wp_create_nonce( 'mrn-base-stack-convert-reusable-block' ),
			'action'             => 'mrn_base_stack_prepare_page_specific_block',
			'actionTitle'        => 'Convert to page-specific',
			'confirmTitle'       => 'Replace With Page-Specific Copy',
			'confirmText'        => 'This will replace the reusable block reference in this row with a page-only copy you can edit here. The original reusable block will stay in the library unchanged.',
			'confirmButton'      => 'Convert to Page-Specific',
			'cancelButton'       => 'Cancel',
			'emptySelectionText' => 'Choose a reusable block first.',
			'loadingText'        => 'Converting block...',
			'successText'        => 'This row is now a page-specific block.',
			'errorText'          => 'The block could not be converted.',
			'hiddenLayouts'      => array(
				'basic_block',
				'content_grid',
				'cta_block',
				'faq_block',
			),
			'contentListTaxonomies' => function_exists( 'mrn_base_stack_get_content_list_post_type_taxonomy_map' ) ? mrn_base_stack_get_content_list_post_type_taxonomy_map() : array(),
			'menuDecorations'    => array(
				array(
					'beforeLayout'    => 'reusable_block',
					'className'       => 'mrn-builder-menu-divider',
					'label'           => 'Reusable / Shared',
					'styleIdentifier' => 'reusable-shared',
				),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'mrn_base_stack_admin_enqueue_builder_assets' );

/**
 * Add lightweight admin CSS for custom content-builder row actions.
 *
 * @return void
 */
function mrn_base_stack_admin_builder_action_styles() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen || ! mrn_base_stack_is_builder_supported_post_type( $screen->post_type ) ) {
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

	if ( ! mrn_base_stack_is_builder_supported_post_type( $screen->post_type ) ) {
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

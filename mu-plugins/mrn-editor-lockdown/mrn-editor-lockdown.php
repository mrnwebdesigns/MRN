<?php
/**
 * Plugin Name: MRN Editor Lockdown (MU)
 * Description: Enforces MRN classic editor metabox ordering for posts, pages, and reusable block library screens across the stack.
 * Version: 1.0.8
 *
 * @package MRNEditorLockdown
 */

defined( 'ABSPATH' ) || exit;

/**
 * SEO Helper ACF metabox ID.
 *
 * @return string
 */
function mrn_editor_lockdown_get_seo_helper_metabox_id() {
	return 'acf-group_69a1c0f3a1b01';
}

/**
 * Legacy SmartCrawl SEO metabox ID.
 *
 * @return string
 */
function mrn_editor_lockdown_get_legacy_seo_metabox_id() {
	return 'wds-wds-meta-box';
}

/**
 * Ensure the SEO Helper metabox stays at the top of the locked sidebar order.
 *
 * @param string $side_order Comma-delimited sidebar metabox order.
 * @return string
 */
function mrn_editor_lockdown_prepend_seo_helper_to_side_order( $side_order ) {
	$metabox_id = mrn_editor_lockdown_get_seo_helper_metabox_id();
	$order      = array_filter( array_map( 'trim', explode( ',', (string) $side_order ) ) );
	$order      = array_values(
		array_filter(
			$order,
			static function ( $item ) use ( $metabox_id ) {
				return $item !== $metabox_id;
			}
		)
	);

	array_unshift( $order, $metabox_id );

	return implode( ',', array_unique( $order ) );
}

/**
 * Ensure the SEO Helper metabox is never hidden on locked classic editor screens.
 *
 * @param mixed $hidden Existing hidden metabox IDs.
 * @return string[]
 */
function mrn_editor_lockdown_get_visible_hidden_metaboxes( $hidden ) {
	$metabox_id = mrn_editor_lockdown_get_seo_helper_metabox_id();
	$hidden     = is_array( $hidden ) ? $hidden : array();

	return array_values(
		array_filter(
			array_map( 'sanitize_key', $hidden ),
			static function ( $item ) use ( $metabox_id ) {
				return $item !== $metabox_id;
			}
		)
	);
}

/**
 * Get enforced metabox layout settings for supported post types.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_editor_lockdown_get_layouts() {
	static $layouts = null;

	if ( null !== $layouts ) {
		return $layouts;
	}

	$layouts = array(
		'post' => array(
			'screen_layout' => 2,
			'meta_box_order' => array(
				'normal'   => 'postexcerpt,slugdiv,authordiv',
				'side'     => 'acf-group_69a1c0f3a1b01,categorydiv,tagsdiv-post_tag,submitdiv',
				'advanced' => 'ame-cpe-content-permissions',
			),
			'closed' => array(
				'ame-cpe-content-permissions',
			),
		),
		'page' => array(
			'screen_layout' => 2,
			'meta_box_order' => array(
				'normal'   => 'slugdiv,authordiv,revisionsdiv',
				'side'     => 'acf-group_69a1c0f3a1b01,submitdiv,pageparentdiv',
				'advanced' => 'ame-cpe-content-permissions',
			),
			'closed' => array(
				'pageparentdiv',
				'ame-cpe-content-permissions',
			),
		),
		'blog' => array(
			'screen_layout' => 2,
			'meta_box_order' => array(
				'normal'   => 'slugdiv,revisionsdiv',
				'side'     => 'authordiv,submitdiv',
				'advanced' => 'ame-cpe-content-permissions',
			),
			'closed' => array(
				'ame-cpe-content-permissions',
			),
		),
		'gallery' => array(
			'screen_layout' => 2,
			'meta_box_order' => array(
				'normal'   => 'slugdiv,revisionsdiv',
				'side'     => 'submitdiv,gallery_categorydiv,postimagediv',
				'advanced' => 'ame-cpe-content-permissions',
			),
			'closed' => array(
				'ame-cpe-content-permissions',
			),
		),
	);

	foreach ( $layouts as $post_type => $settings ) {
		if ( empty( $settings['meta_box_order']['side'] ) ) {
			continue;
		}

		$layouts[ $post_type ]['meta_box_order']['side'] = mrn_editor_lockdown_prepend_seo_helper_to_side_order( $settings['meta_box_order']['side'] );
	}

	return $layouts;
}

/**
 * Get the shared reusable-block editor layout.
 *
 * @return array<string, mixed>
 */
function mrn_editor_lockdown_get_reusable_layout() {
	static $layout = null;

	if ( null !== $layout ) {
		return $layout;
	}

	$layout = array(
		'screen_layout' => 2,
		'meta_box_order' => array(
			'normal'   => 'slugdiv,revisionsdiv',
			'side'     => mrn_editor_lockdown_prepend_seo_helper_to_side_order( 'submitdiv' ),
			'advanced' => '',
		),
		'closed' => array(),
	);

	return $layout;
}

/**
 * Check whether a post type is one of the reusable block library CPTs.
 *
 * @param string $post_type Post type slug.
 * @return bool
 */
function mrn_editor_lockdown_is_reusable_post_type( $post_type ) {
	return is_string( $post_type ) && 0 === strpos( $post_type, 'mrn_reusable_' );
}

/**
 * Get classic-editor post types that should inherit the generic locked layout.
 *
 * @return string[]
 */
function mrn_editor_lockdown_get_dynamic_post_types() {
	static $locked = null;

	if ( null !== $locked ) {
		return $locked;
	}

	$post_types = get_post_types(
		array(
			'show_ui' => true,
		),
		'names'
	);

	if ( ! is_array( $post_types ) ) {
		return array();
	}

	$excluded = array(
		'attachment',
		'acf-field-group',
		'acf-field',
	);

	$locked = array();

	foreach ( $post_types as $post_type ) {
		$post_type = sanitize_key( (string) $post_type );

		if ( '' === $post_type || in_array( $post_type, $excluded, true ) || mrn_editor_lockdown_is_reusable_post_type( $post_type ) ) {
			continue;
		}

		$locked[] = $post_type;
	}

	$locked = array_values( array_unique( $locked ) );

	return $locked;
}

/**
 * Get the fallback locked layout for dynamically discovered classic-editor screens.
 *
 * @return array<string, mixed>
 */
function mrn_editor_lockdown_get_dynamic_layout() {
	static $layout = null;

	if ( null !== $layout ) {
		return $layout;
	}

	$layout = array(
		'screen_layout' => 2,
		'meta_box_order' => array(
			'normal'   => 'slugdiv,revisionsdiv',
			'side'     => mrn_editor_lockdown_prepend_seo_helper_to_side_order( 'submitdiv,authordiv,pageparentdiv,categorydiv,tagsdiv-post_tag,postimagediv' ),
			'advanced' => 'ame-cpe-content-permissions',
		),
		'closed' => array(
			'ame-cpe-content-permissions',
		),
	);

	return $layout;
}

/**
 * Get the layout settings for a specific post type.
 *
 * @param string $post_type Post type slug.
 * @return array<string, mixed>|null
 */
function mrn_editor_lockdown_get_layout_for_post_type( $post_type ) {
	static $cache = array();

	$post_type = sanitize_key( (string) $post_type );

	if ( array_key_exists( $post_type, $cache ) ) {
		return $cache[ $post_type ];
	}

	$layouts = mrn_editor_lockdown_get_layouts();

	if ( isset( $layouts[ $post_type ] ) ) {
		$cache[ $post_type ] = $layouts[ $post_type ];
		return $cache[ $post_type ];
	}

	if ( mrn_editor_lockdown_is_reusable_post_type( $post_type ) ) {
		$cache[ $post_type ] = mrn_editor_lockdown_get_reusable_layout();
		return $cache[ $post_type ];
	}

	if ( in_array( $post_type, mrn_editor_lockdown_get_dynamic_post_types(), true ) ) {
		$cache[ $post_type ] = mrn_editor_lockdown_get_dynamic_layout();
		return $cache[ $post_type ];
	}

	$cache[ $post_type ] = null;

	return $cache[ $post_type ];
}

/**
 * Get all post types that should receive screen-option lock filters.
 *
 * @return string[]
 */
function mrn_editor_lockdown_get_supported_post_types() {
	static $post_types = null;

	if ( null !== $post_types ) {
		return $post_types;
	}

	$post_types = array_merge(
		array_keys( mrn_editor_lockdown_get_layouts() ),
		mrn_editor_lockdown_get_dynamic_post_types()
	);

	if ( function_exists( 'mrn_rbl_get_post_types' ) ) {
		$reusable_post_types = mrn_rbl_get_post_types();
		if ( is_array( $reusable_post_types ) ) {
			foreach ( $reusable_post_types as $reusable_post_type ) {
				if ( is_string( $reusable_post_type ) && mrn_editor_lockdown_is_reusable_post_type( $reusable_post_type ) ) {
					$post_types[] = $reusable_post_type;
				}
			}
		}
	}

	$post_types = array_values( array_unique( array_filter( $post_types, 'is_string' ) ) );

	return $post_types;
}

/**
 * Check whether the current screen is a classic post editor screen.
 *
 * @param mixed $screen Current screen object.
 * @return bool
 */
function mrn_editor_lockdown_is_classic_post_screen( $screen ) {
	if ( ! $screen instanceof WP_Screen ) {
		return false;
	}

	if ( 'post' !== $screen->base ) {
		return false;
	}

	if ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
		return false;
	}

	return true;
}

/**
 * Check whether the current screen is a supported classic post editor screen.
 *
 * @param mixed $screen Current screen object.
 * @return bool
 */
function mrn_editor_lockdown_is_supported_screen( $screen ) {
	if ( ! mrn_editor_lockdown_is_classic_post_screen( $screen ) ) {
		return false;
	}

	return null !== mrn_editor_lockdown_get_layout_for_post_type( $screen->post_type );
}

/**
 * Enforce saved metabox layout user preferences for the current editor screen.
 *
 * @param WP_Screen $screen Current admin screen.
 * @return void
 */
function mrn_editor_lockdown_apply_layout( $screen ) {
	if ( ! mrn_editor_lockdown_is_supported_screen( $screen ) ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		return;
	}

	$user_id = get_current_user_id();
	if ( $user_id < 1 ) {
		return;
	}

	$settings  = mrn_editor_lockdown_get_layout_for_post_type( $screen->post_type );
	$post_type = $screen->post_type;

	if ( null === $settings ) {
		return;
	}

	$screen_layout_key = 'screen_layout_' . $post_type;
	$meta_box_order_key = 'meta-box-order_' . $post_type;
	$closed_postboxes_key = 'closedpostboxes_' . $post_type;
	$hidden_metaboxes_key = 'metaboxhidden_' . $post_type;
	$screen_layout = (int) $settings['screen_layout'];
	$current_screen_layout = (int) get_user_meta( $user_id, $screen_layout_key, true );
	$current_meta_box_order = get_user_meta( $user_id, $meta_box_order_key, true );
	$current_closed_postboxes = get_user_meta( $user_id, $closed_postboxes_key, true );
	$current_hidden_metaboxes = get_user_meta( $user_id, $hidden_metaboxes_key, true );

	if ( $current_screen_layout !== $screen_layout ) {
		update_user_meta( $user_id, $screen_layout_key, $screen_layout );
	}

	if ( ! is_array( $current_meta_box_order ) || $current_meta_box_order !== $settings['meta_box_order'] ) {
		update_user_meta( $user_id, $meta_box_order_key, $settings['meta_box_order'] );
	}

	if ( ! is_array( $current_closed_postboxes ) || $current_closed_postboxes !== $settings['closed'] ) {
		update_user_meta( $user_id, $closed_postboxes_key, $settings['closed'] );
	}

	$visible_hidden_metaboxes = mrn_editor_lockdown_get_visible_hidden_metaboxes( $current_hidden_metaboxes );

	if ( ! is_array( $current_hidden_metaboxes ) || $current_hidden_metaboxes !== $visible_hidden_metaboxes ) {
		update_user_meta( $user_id, $hidden_metaboxes_key, $visible_hidden_metaboxes );
	}
}
add_action( 'current_screen', 'mrn_editor_lockdown_apply_layout' );

/**
 * Remove the heavyweight SmartCrawl metabox from supported classic editor screens.
 *
 * The lightweight SEO helper remains available in the sidebar, so editors keep
 * the intended SEO surface without booting the full SmartCrawl analysis UI.
 *
 * @param string $post_type Current post type slug.
 * @return void
 */
function mrn_editor_lockdown_remove_legacy_seo_metabox( $post_type ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! mrn_editor_lockdown_is_supported_screen( $screen ) ) {
		return;
	}

	$post_type = sanitize_key( (string) $post_type );

	if ( '' === $post_type || $post_type !== $screen->post_type ) {
		return;
	}

	$metabox_id = mrn_editor_lockdown_get_legacy_seo_metabox_id();

	remove_meta_box( $metabox_id, $post_type, 'normal' );
	remove_meta_box( $metabox_id, $post_type, 'advanced' );
	remove_meta_box( $metabox_id, $post_type, 'side' );
}
add_action( 'add_meta_boxes', 'mrn_editor_lockdown_remove_legacy_seo_metabox', 100 );

/**
 * Filter screen layout user options for locked post types.
 *
 * @param mixed  $result Existing user option value.
 * @param string $option Option name.
 * @param int    $user   User ID.
 * @return mixed
 */
function mrn_editor_lockdown_filter_screen_layout_option( $result, $option, $user ) {
	unset( $user );

	if ( 0 === strpos( $option, 'screen_layout_' ) ) {
		$post_type = substr( $option, strlen( 'screen_layout_' ) );
		$layout    = mrn_editor_lockdown_get_layout_for_post_type( $post_type );

		if ( null !== $layout ) {
			return (int) $layout['screen_layout'];
		}
	}

	return $result;
}
/**
 * Filter metabox ordering user options for locked post types.
 *
 * @param mixed  $result Existing user option value.
 * @param string $option Option name.
 * @param int    $user   User ID.
 * @return mixed
 */
function mrn_editor_lockdown_filter_metabox_order_option( $result, $option, $user ) {
	unset( $user );

	if ( 0 === strpos( $option, 'meta-box-order_' ) ) {
		$post_type = substr( $option, strlen( 'meta-box-order_' ) );
		$layout    = mrn_editor_lockdown_get_layout_for_post_type( $post_type );

		if ( null !== $layout ) {
			return $layout['meta_box_order'];
		}
	}

	return $result;
}
/**
 * Force the same closed metaboxes at runtime.
 *
 * @param array $hidden Existing hidden/closed metabox IDs.
 * @param mixed $screen Current screen object.
 * @return array
 */
function mrn_editor_lockdown_filter_closed_metaboxes( $hidden, $screen ) {
	if ( ! mrn_editor_lockdown_is_supported_screen( $screen ) ) {
		return $hidden;
	}

	$settings = mrn_editor_lockdown_get_layout_for_post_type( $screen->post_type );

	if ( null === $settings ) {
		return $hidden;
	}
	$hidden   = is_array( $hidden ) ? $hidden : array();

	return array_values( array_unique( array_merge( $hidden, $settings['closed'] ) ) );
}

/**
 * Force the SEO Helper metabox to remain visible at runtime.
 *
 * @param mixed  $hidden Existing hidden metabox IDs.
 * @param string $option Current user option name.
 * @param int    $user   User ID.
 * @return string[]
 */
function mrn_editor_lockdown_filter_hidden_metaboxes( $hidden, $option, $user ) {
	unset( $user );

	if ( 0 === strpos( $option, 'metaboxhidden_' ) ) {
		$post_type = substr( $option, strlen( 'metaboxhidden_' ) );
		$layout    = mrn_editor_lockdown_get_layout_for_post_type( $post_type );

		if ( null !== $layout ) {
			return mrn_editor_lockdown_get_visible_hidden_metaboxes( $hidden );
		}
	}

	return is_array( $hidden ) ? $hidden : array();
}
/**
 * Register dynamic user-option filters for supported post types.
 *
 * @return void
 */
function mrn_editor_lockdown_register_option_filters() {
	foreach ( mrn_editor_lockdown_get_supported_post_types() as $post_type ) {
		add_filter( 'get_user_option_screen_layout_' . $post_type, 'mrn_editor_lockdown_filter_screen_layout_option', 10, 3 );
		add_filter( 'get_user_option_meta-box-order_' . $post_type, 'mrn_editor_lockdown_filter_metabox_order_option', 10, 3 );
		add_filter( 'get_user_option_closedpostboxes_' . $post_type, 'mrn_editor_lockdown_filter_closed_metaboxes', 10, 2 );
		add_filter( 'get_user_option_metaboxhidden_' . $post_type, 'mrn_editor_lockdown_filter_hidden_metaboxes', 10, 3 );
	}
}
add_action( 'init', 'mrn_editor_lockdown_register_option_filters', 20 );

/**
 * Output light admin CSS to remove easy layout customization paths.
 *
 * @return void
 */
function mrn_editor_lockdown_admin_css() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! mrn_editor_lockdown_is_classic_post_screen( $screen ) ) {
		return;
	}
	?>
	<style id="mrn-editor-lockdown">
		body.post-php:not(.mrn-editor-page-ready),
		body.post-new-php:not(.mrn-editor-page-ready) {
			overflow: hidden;
		}

		body.post-php:not(.mrn-editor-page-ready)::before,
		body.post-new-php:not(.mrn-editor-page-ready)::before {
			content: '';
			position: fixed;
			inset: 0;
			background: rgba(17, 20, 24, 0.86);
			z-index: 100000;
		}

		body.post-php:not(.mrn-editor-page-ready)::after,
		body.post-new-php:not(.mrn-editor-page-ready)::after {
			content: '';
			position: fixed;
			top: 50%;
			left: 50%;
			width: 48px;
			height: 48px;
			margin: -40px 0 0 -24px;
			border-radius: 50%;
			border: 4px solid rgba(255, 255, 255, 0.35);
			border-top-color: #ffffff;
			animation: mrnEditorPageLoaderSpin 0.9s linear infinite;
			z-index: 100001;
		}

		.mrn-editor-loading-message {
			position: fixed;
			top: 50%;
			left: 50%;
			width: min(88vw, 520px);
			margin-top: 24px;
			transform: translateX(-50%);
			text-align: center;
			color: #f4f7fb;
			font-size: 14px;
			font-weight: 600;
			letter-spacing: 0.02em;
			line-height: 1.4;
			text-wrap: balance;
			z-index: 100002;
			pointer-events: none;
			text-shadow: 0 1px 2px rgba(0, 0, 0, 0.45);
		}

		body.post-php:not(.mrn-editor-page-ready):not(.mrn-editor-loading-message-live) #wpwrap::before,
		body.post-new-php:not(.mrn-editor-page-ready):not(.mrn-editor-loading-message-live) #wpwrap::before {
			content: 'Summoning your editing desk...';
			position: fixed;
			top: 50%;
			left: 50%;
			width: min(88vw, 520px);
			margin-top: 24px;
			transform: translateX(-50%);
			text-align: center;
			color: #f4f7fb;
			font-size: 14px;
			font-weight: 600;
			letter-spacing: 0.02em;
			line-height: 1.4;
			text-wrap: balance;
			z-index: 100002;
			pointer-events: none;
			text-shadow: 0 1px 2px rgba(0, 0, 0, 0.45);
		}

		@keyframes mrnEditorPageLoaderSpin {
			to {
				transform: rotate(360deg);
			}
		}

		.mrn-editor-sidebar-toggle {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 4px;
			flex: 0 0 110px;
			width: 110px;
			min-width: 110px;
			max-width: 110px;
			min-height: 30px;
			padding: 0 12px;
			border: 1px solid #c3c4c7;
			border-top: 0;
			border-radius: 0 0 4px 4px;
			background: #f6f7f7;
			box-shadow: none;
			color: #1d2327;
			cursor: pointer;
			transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease, opacity 0.15s ease;
		}

		.mrn-editor-sidebar-toggle--icon {
			flex: 0 0 30px;
			width: 30px;
			min-width: 30px;
			max-width: 30px;
			padding: 0;
		}

		.mrn-editor-sidebar-toggle--icon .dashicons {
			margin: 0;
		}

		.mrn-editor-sidebar-toggle:hover,
		.mrn-editor-sidebar-toggle:focus-visible {
			border-color: #2271b1;
			background: #fff;
			color: #0a4b78;
		}

		.mrn-editor-sidebar-toggle:focus-visible {
			outline: 2px solid #2271b1;
			outline-offset: 2px;
		}

		.mrn-editor-sidebar-toggle .dashicons {
			width: 16px;
			height: 16px;
			font-size: 16px;
			line-height: 16px;
		}

		.mrn-editor-sidebar-toggle__label {
			display: inline-block;
			font-size: 13px;
			line-height: 1;
			text-align: center;
			flex: 1 1 auto;
			white-space: nowrap;
		}

		body.mrn-editor-sidebar-collapsible #screen-meta-links {
			display: flex;
			align-items: flex-start;
			gap: 6px;
		}

		body.mrn-editor-sidebar-collapsible #post-body.columns-2 #postbox-container-1 {
			position: relative;
		}

		body.mrn-editor-sidebar-collapsible #postbox-container-1 > *:not(.mrn-editor-sidebar-toggle) {
			transition: none;
		}

		body.mrn-editor-sidebar-collapsible.mrn-editor-sidebar-animate #post-body.columns-2 #postbox-container-1 {
			transition: width 0.22s ease, margin-right 0.22s ease, opacity 0.22s ease;
		}

		body.mrn-editor-sidebar-collapsible.mrn-editor-sidebar-animate #postbox-container-1 > *:not(.mrn-editor-sidebar-toggle) {
			transition: opacity 0.18s ease, transform 0.22s ease, visibility 0.22s ease;
		}

		body.mrn-editor-sidebar-collapsible .mrn-editor-sidebar-toggle {
			opacity: 0;
			pointer-events: none;
		}

		body.mrn-editor-sidebar-collapsible.mrn-editor-sidebar-ready .mrn-editor-sidebar-toggle {
			opacity: 1;
			pointer-events: auto;
		}

		body.mrn-editor-sidebar-collapsible.mrn-editor-sidebar-collapsed #post-body.columns-2 #postbox-container-1 {
			width: 0 !important;
			min-width: 0 !important;
			margin-right: 0 !important;
			overflow: visible;
		}

		body.mrn-editor-sidebar-collapsible.mrn-editor-sidebar-collapsed #poststuff #post-body.columns-2 {
			margin-right: 0 !important;
		}

		body.mrn-editor-sidebar-collapsible.mrn-editor-sidebar-collapsed #post-body.columns-2 #post-body-content {
			width: 100% !important;
			max-width: 100% !important;
			min-width: 0 !important;
			margin-right: 0 !important;
		}

		body.mrn-editor-sidebar-collapsible.mrn-editor-sidebar-collapsed #postbox-container-1 > * {
			opacity: 0 !important;
			transform: translateX(18px);
			pointer-events: none !important;
			visibility: hidden !important;
		}

		@media (max-width: 850px) {
			body.mrn-editor-sidebar-collapsible.mrn-editor-sidebar-ready .mrn-editor-sidebar-toggle {
				opacity: 0;
				pointer-events: none;
			}

			body.mrn-editor-sidebar-collapsible.mrn-editor-sidebar-collapsed #post-body.columns-2 #postbox-container-1,
			body.mrn-editor-sidebar-collapsible.mrn-editor-sidebar-collapsed #post-body.columns-2 #post-body-content {
				width: auto;
				margin-right: 0;
			}

			body.mrn-editor-sidebar-collapsible.mrn-editor-sidebar-collapsed #postbox-container-1 > * {
				opacity: 1 !important;
				transform: none !important;
				pointer-events: auto !important;
				visibility: visible !important;
			}
		}

		@media (prefers-reduced-motion: reduce) {
			body.mrn-editor-sidebar-collapsible.mrn-editor-sidebar-animate #post-body.columns-2 #postbox-container-1,
			body.mrn-editor-sidebar-collapsible.mrn-editor-sidebar-animate #postbox-container-1 > *:not(.mrn-editor-sidebar-toggle),
			.mrn-editor-sidebar-toggle {
				transition: none !important;
			}

			body.post-php:not(.mrn-editor-page-ready)::after,
			body.post-new-php:not(.mrn-editor-page-ready)::after {
				animation: none;
			}
		}

	<?php if ( mrn_editor_lockdown_is_supported_screen( $screen ) ) : ?>
			.postbox .handle-order-higher,
		.postbox .handle-order-lower {
			display: none !important;
		}
	<?php endif; ?>
	</style>
	<?php
}
add_action( 'admin_head', 'mrn_editor_lockdown_admin_css' );

/**
 * Disable jQuery UI sortable for metaboxes on locked screens.
 *
 * @return void
 */
function mrn_editor_lockdown_admin_js() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! mrn_editor_lockdown_is_classic_post_screen( $screen ) ) {
		return;
	}
	?>
	<script id="mrn-editor-lockdown-js">
		jQuery(function($) {
			var body = document.body;
			var postType = <?php echo wp_json_encode( sanitize_key( (string) $screen->post_type ) ); ?>;
			var legacyStorageKey = 'mrnEditorSidebarCollapsed:' + postType;
			// Version the storage key so existing collapsed states do not keep
			// the locked sidebar hidden after the editor performance rollout.
			var storageKey = 'mrnEditorSidebarState:v2:' + postType;
			var migrationKey = 'mrnEditorSidebarStateMigration:v1:' + postType;
			var sidebar = document.getElementById('postbox-container-1');
			var postBody = document.getElementById('post-body');
			var postBodyContent = document.getElementById('post-body-content');
			var screenMetaLinks = document.getElementById('screen-meta-links');
			var toggle;
			var toggleIcon;
			var toggleText;
			var distractionFreeToggle;
			var distractionFreeIcon;
			var adminMenuCollapseButton;
			var restoreTimer;
			var loadingFallbackTimer;
			var loadingReadyTimer;
			var loadingMessageTimer;
			var loadingMessageEl;
			var loadingMessageIndex = 0;
			var loadingMessageStartStorageKey = 'mrnEditorLoadingMessageStart:v1:' + postType;
			var loadingDelayMs = 1000;
			var loadingMessageIcons = ['🚀', '💣', '🧨', '⚡', '🛰️', '🛠️', '🎯', '🧪', '🔥', '✨'];
			var loadingMessageStartPhrases = [
				'Aligning your metaboxes',
				'Bribing the sidebar gremlins',
				'Polishing the publish button',
				'Checking every tiny click target',
				'Sharpening your headline pencils',
				'Warming up the permalink engine',
				'Untangling classic editor cables',
				'Tuning the SEO helper radar',
				'Buffing up content controls',
				'Loading keyboard shortcut fuel',
				'Calibrating preview thrusters',
				'Dusting off the formatting toolbox',
				'Rehearsing your save-draft backup plan',
				'Smoothing out admin panel corners',
				'Syncing title fields and slug magic',
				'Packing extra speed into this screen',
				'Running one last quality checkpoint',
				'Prepping your content launchpad',
				'Teaching buttons to behave politely',
				'Deploying tiny UX elves'
			];
			var loadingMessageEndPhrases = [
				'for liftoff',
				'before the big reveal',
				'so everything feels snappy',
				'without waking the bugs',
				'with cinematic confidence'
			];
			var loadingMessages = [];
			var loadingStartIndex;
			var loadingEndIndex;

			for (loadingStartIndex = 0; loadingStartIndex < loadingMessageStartPhrases.length; loadingStartIndex += 1) {
				for (loadingEndIndex = 0; loadingEndIndex < loadingMessageEndPhrases.length; loadingEndIndex += 1) {
					loadingMessages.push(loadingMessageStartPhrases[loadingStartIndex] + ' ' + loadingMessageEndPhrases[loadingEndIndex]);
				}
			}

			function setLoadingMessage(index) {
				if (!loadingMessageEl || !loadingMessages.length) {
					return;
				}

				loadingMessageEl.textContent = loadingMessages[index % loadingMessages.length] + ' ' + loadingMessageIcons[index % loadingMessageIcons.length];
			}

			function getRandomLoadingMessageStartIndex() {
				if (!loadingMessages.length) {
					return 0;
				}

				var nextIndex = Math.floor(Math.random() * loadingMessages.length);
				if (loadingMessages.length < 2) {
					return nextIndex;
				}

				try {
					var previousRaw = window.sessionStorage.getItem(loadingMessageStartStorageKey);
					var previousIndex = parseInt(previousRaw, 10);

					if (!Number.isNaN(previousIndex) && previousIndex >= 0 && previousIndex < loadingMessages.length && previousIndex === nextIndex) {
						nextIndex = (nextIndex + 1 + Math.floor(Math.random() * (loadingMessages.length - 1))) % loadingMessages.length;
					}

					window.sessionStorage.setItem(loadingMessageStartStorageKey, String(nextIndex));
				} catch (storageError) {
					return nextIndex;
				}

				return nextIndex;
			}

			function startLoadingMessageCycle() {
				if (!body || loadingMessageEl) {
					return;
				}

				body.classList.add('mrn-editor-loading-message-live');
				loadingMessageEl = document.createElement('div');
				loadingMessageEl.className = 'mrn-editor-loading-message';
				loadingMessageEl.setAttribute('role', 'status');
				loadingMessageEl.setAttribute('aria-live', 'polite');
				setLoadingMessage(loadingMessageIndex);
				body.appendChild(loadingMessageEl);

				loadingMessageTimer = window.setInterval(function() {
					loadingMessageIndex = (loadingMessageIndex + 1) % loadingMessages.length;
					setLoadingMessage(loadingMessageIndex);
				}, 650);
			}

			function stopLoadingMessageCycle() {
				if (loadingMessageTimer) {
					window.clearInterval(loadingMessageTimer);
					loadingMessageTimer = null;
				}

				if (loadingMessageEl && loadingMessageEl.parentNode) {
					loadingMessageEl.parentNode.removeChild(loadingMessageEl);
				}

				loadingMessageEl = null;
				if (body) {
					body.classList.remove('mrn-editor-loading-message-live');
				}
			}

			function markEditorPageReady() {
				if (!body) {
					return;
				}

				body.classList.add('mrn-editor-page-ready');
				stopLoadingMessageCycle();

				if (loadingFallbackTimer) {
					window.clearTimeout(loadingFallbackTimer);
					loadingFallbackTimer = null;
				}

				if (loadingReadyTimer) {
					window.clearTimeout(loadingReadyTimer);
					loadingReadyTimer = null;
				}
			}

			function scheduleEditorPageReady() {
				if (loadingReadyTimer) {
					window.clearTimeout(loadingReadyTimer);
				}

				loadingReadyTimer = window.setTimeout(markEditorPageReady, loadingDelayMs);
			}

			function initEditorLoadingMask() {
				if (!body) {
					return;
				}

				loadingMessageIndex = getRandomLoadingMessageStartIndex();
				startLoadingMessageCycle();

				if ('complete' === document.readyState) {
					scheduleEditorPageReady();
					return;
				}

				window.addEventListener('load', scheduleEditorPageReady, { once: true });
				loadingFallbackTimer = window.setTimeout(markEditorPageReady, 7000);
			}

			function isAdminMenuCollapsed() {
				return !!body && body.classList.contains('folded');
			}

			function getAdminMenuCollapseButton() {
				if (adminMenuCollapseButton && adminMenuCollapseButton.isConnected) {
					return adminMenuCollapseButton;
				}

				adminMenuCollapseButton = document.getElementById('collapse-button') || document.querySelector('#collapse-menu button');

				return adminMenuCollapseButton;
			}

			function setAdminMenuCollapsed(collapsed) {
				if (!body || isAdminMenuCollapsed() === collapsed) {
					return;
				}

				adminMenuCollapseButton = getAdminMenuCollapseButton();
				if (adminMenuCollapseButton && 'function' === typeof adminMenuCollapseButton.click) {
					adminMenuCollapseButton.click();
					return;
				}

				body.classList.toggle('folded', collapsed);
			}

			function updateDistractionFreeToggleState() {
				if (!distractionFreeToggle) {
					return;
				}

				var distractionFreeActive = body && body.classList.contains('mrn-editor-sidebar-collapsed') && isAdminMenuCollapsed();
				var toggleLabel = distractionFreeActive
					? 'Exit distraction-free mode (show sidebar and expand admin menu)'
					: 'Enter distraction-free mode (hide sidebar and collapse admin menu)';

				distractionFreeToggle.setAttribute('aria-pressed', distractionFreeActive ? 'true' : 'false');
				distractionFreeToggle.setAttribute('aria-label', toggleLabel);
				distractionFreeToggle.setAttribute('title', toggleLabel);

				if (distractionFreeIcon) {
					distractionFreeIcon.className = distractionFreeActive ? 'dashicons dashicons-editor-expand' : 'dashicons dashicons-editor-contract';
				}
			}

			function setSidebarCollapsed(collapsed) {
				if (!body) {
					return;
				}

				body.classList.toggle('mrn-editor-sidebar-collapsed', collapsed);

				if (postBody) {
					postBody.style.marginRight = collapsed ? '0' : '';
				}

				if (postBodyContent) {
					postBodyContent.style.marginRight = collapsed ? '0' : '';
					postBodyContent.style.width = collapsed ? '100%' : '';
					postBodyContent.style.maxWidth = collapsed ? '100%' : '';
					postBodyContent.style.minWidth = collapsed ? '0' : '';
				}

				if (sidebar) {
					sidebar.style.width = collapsed ? '0' : '';
					sidebar.style.minWidth = collapsed ? '0' : '';
					sidebar.style.marginRight = collapsed ? '0' : '';
				}

				if (toggle) {
					toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
					toggle.setAttribute('aria-label', collapsed ? 'Show sidebar settings and SEO fields' : 'Hide sidebar settings and SEO fields');
					toggle.setAttribute('title', collapsed ? 'Show sidebar settings and SEO fields' : 'Hide sidebar settings and SEO fields');
				}

				if (toggleIcon) {
					toggleIcon.className = collapsed ? 'dashicons dashicons-arrow-left-alt2' : 'dashicons dashicons-arrow-right-alt2';
				}

				if (toggleText) {
					toggleText.textContent = collapsed ? 'Show Sidebar' : 'Hide Sidebar';
				}

				updateDistractionFreeToggleState();
			}

			function getStoredSidebarState() {
				try {
					return 'collapsed' === window.localStorage.getItem(storageKey);
				} catch (error) {
					return false;
				}
			}

			function migrateSidebarStateIfNeeded() {
				try {
					if ('done' === window.localStorage.getItem(migrationKey)) {
						return;
					}

					window.localStorage.removeItem(legacyStorageKey);
					window.localStorage.removeItem(storageKey);
					window.localStorage.setItem(migrationKey, 'done');
				} catch (error) {}
			}

			function restoreSidebarState() {
				setSidebarCollapsed(getStoredSidebarState());
			}

			function scheduleSidebarStateRestore() {
				if (restoreTimer) {
					window.clearTimeout(restoreTimer);
				}

				restoreTimer = window.setTimeout(function() {
					restoreSidebarState();
				}, 280);
			}

			function initSidebarToggle() {
				if (!body || !sidebar || !postBody || !postBody.classList.contains('columns-2') || !screenMetaLinks) {
					return;
				}

				migrateSidebarStateIfNeeded();

				body.classList.add('mrn-editor-sidebar-collapsible');
				adminMenuCollapseButton = getAdminMenuCollapseButton();
				if (adminMenuCollapseButton) {
					adminMenuCollapseButton.addEventListener('click', function() {
						window.setTimeout(updateDistractionFreeToggleState, 0);
					});
				}

				toggle = document.createElement('button');
				toggleIcon = document.createElement('span');
				toggleText = document.createElement('span');
				toggle.type = 'button';
				toggle.className = 'mrn-editor-sidebar-toggle';
				toggle.setAttribute('aria-expanded', 'true');
				toggleIcon.className = 'dashicons dashicons-arrow-right-alt2';
				toggleIcon.setAttribute('aria-hidden', 'true');
				toggleText.className = 'mrn-editor-sidebar-toggle__label';
				toggleText.textContent = 'Hide Sidebar';
				toggle.appendChild(toggleIcon);
				toggle.appendChild(toggleText);
				toggle.addEventListener('click', function() {
					var collapsed = !body.classList.contains('mrn-editor-sidebar-collapsed');
					setSidebarCollapsed(collapsed);

					try {
						window.localStorage.setItem(storageKey, collapsed ? 'collapsed' : 'expanded');
					} catch (error) {}
				});

				distractionFreeToggle = document.createElement('button');
				distractionFreeIcon = document.createElement('span');
				distractionFreeToggle.type = 'button';
				distractionFreeToggle.className = 'mrn-editor-sidebar-toggle mrn-editor-sidebar-toggle--icon mrn-editor-sidebar-toggle--distraction';
				distractionFreeToggle.setAttribute('aria-pressed', 'false');
				distractionFreeToggle.setAttribute('aria-label', 'Enter distraction-free mode (hide sidebar and collapse admin menu)');
				distractionFreeToggle.setAttribute('title', 'Enter distraction-free mode (hide sidebar and collapse admin menu)');
				distractionFreeIcon.className = 'dashicons dashicons-editor-contract';
				distractionFreeIcon.setAttribute('aria-hidden', 'true');
				distractionFreeToggle.appendChild(distractionFreeIcon);
				distractionFreeToggle.addEventListener('click', function() {
					var nextCollapsedState = distractionFreeToggle.getAttribute('aria-pressed') !== 'true';
					setSidebarCollapsed(nextCollapsedState);
					setAdminMenuCollapsed(nextCollapsedState);
					window.setTimeout(updateDistractionFreeToggleState, 0);

					try {
						window.localStorage.setItem(storageKey, nextCollapsedState ? 'collapsed' : 'expanded');
					} catch (error) {}
				});

				screenMetaLinks.appendChild(toggle);
				screenMetaLinks.appendChild(distractionFreeToggle);
				body.classList.add('mrn-editor-sidebar-ready');
				restoreSidebarState();
				updateDistractionFreeToggleState();

				window.requestAnimationFrame(function() {
					window.requestAnimationFrame(function() {
						body.classList.add('mrn-editor-sidebar-animate');
					});
				});
			}

			function lockMetaboxSorting() {
				if (!$.fn.sortable) {
					return;
				}

				$('.meta-box-sortables').each(function() {
					var $sortable = $(this);

					if (!$sortable.data('ui-sortable')) {
						return;
					}

					try {
						$sortable.sortable('option', 'handle', '.mrn-disabled-metabox-drag-handle');
						$sortable.sortable('refresh');
					} catch (e) {}
				});

				$('.meta-box-sortables .hndle, .meta-box-sortables .handlediv').css('cursor', 'default');
			}

			initEditorLoadingMask();
			initSidebarToggle();
			if (<?php echo wp_json_encode( mrn_editor_lockdown_is_supported_screen( $screen ) ); ?>) {
				lockMetaboxSorting();
				setTimeout(lockMetaboxSorting, 250);
				$(document).on('postbox-toggled columnschange', function() {
					lockMetaboxSorting();
					scheduleSidebarStateRestore();
				});
			}
		});
	</script>
	<?php
}
add_action( 'admin_print_footer_scripts', 'mrn_editor_lockdown_admin_js' );

<?php
/**
 * Plugin Name: MRN Editor Lockdown (MU)
 * Description: Enforces MRN classic editor metabox ordering for posts, pages, and reusable block library screens across the stack.
 * Version: 1.0.7
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
 * Get enforced metabox layout settings for supported post types.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_editor_lockdown_get_layouts() {
	$layouts = array(
		'post' => array(
			'screen_layout' => 2,
			'meta_box_order' => array(
				'normal'   => 'postexcerpt,slugdiv,authordiv',
				'side'     => 'acf-group_69a1c0f3a1b01,categorydiv,tagsdiv-post_tag,submitdiv',
				'advanced' => 'wds-wds-meta-box,ame-cpe-content-permissions',
			),
			'closed' => array(
				'wds-wds-meta-box',
				'ame-cpe-content-permissions',
			),
		),
		'page' => array(
			'screen_layout' => 2,
			'meta_box_order' => array(
				'normal'   => 'wds-wds-meta-box,slugdiv,authordiv,revisionsdiv',
				'side'     => 'acf-group_69a1c0f3a1b01,submitdiv,pageparentdiv',
				'advanced' => 'ame-cpe-content-permissions',
			),
			'closed' => array(
				'pageparentdiv',
				'wds-wds-meta-box',
				'ame-cpe-content-permissions',
			),
		),
		'blog' => array(
			'screen_layout' => 2,
			'meta_box_order' => array(
				'normal'   => 'wds-wds-meta-box,slugdiv,revisionsdiv',
				'side'     => 'authordiv,submitdiv',
				'advanced' => 'ame-cpe-content-permissions',
			),
			'closed' => array(
				'wds-wds-meta-box',
				'ame-cpe-content-permissions',
			),
		),
		'gallery' => array(
			'screen_layout' => 2,
			'meta_box_order' => array(
				'normal'   => 'wds-wds-meta-box,slugdiv,revisionsdiv',
				'side'     => 'submitdiv,gallery_categorydiv,postimagediv',
				'advanced' => 'ame-cpe-content-permissions',
			),
			'closed' => array(
				'wds-wds-meta-box',
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
	return array(
		'screen_layout' => 2,
		'meta_box_order' => array(
			'normal'   => 'slugdiv,revisionsdiv',
			'side'     => mrn_editor_lockdown_prepend_seo_helper_to_side_order( 'submitdiv' ),
			'advanced' => '',
		),
		'closed' => array(),
	);
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

	return array_values( array_unique( $locked ) );
}

/**
 * Get the fallback locked layout for dynamically discovered classic-editor screens.
 *
 * @return array<string, mixed>
 */
function mrn_editor_lockdown_get_dynamic_layout() {
	return array(
		'screen_layout' => 2,
		'meta_box_order' => array(
			'normal'   => 'wds-wds-meta-box,slugdiv,revisionsdiv',
			'side'     => mrn_editor_lockdown_prepend_seo_helper_to_side_order( 'submitdiv,authordiv,pageparentdiv,categorydiv,tagsdiv-post_tag,postimagediv' ),
			'advanced' => 'ame-cpe-content-permissions',
		),
		'closed' => array(
			'wds-wds-meta-box',
			'ame-cpe-content-permissions',
		),
	);
}

/**
 * Get the layout settings for a specific post type.
 *
 * @param string $post_type Post type slug.
 * @return array<string, mixed>|null
 */
function mrn_editor_lockdown_get_layout_for_post_type( $post_type ) {
	$layouts = mrn_editor_lockdown_get_layouts();

	if ( isset( $layouts[ $post_type ] ) ) {
		return $layouts[ $post_type ];
	}

	if ( mrn_editor_lockdown_is_reusable_post_type( $post_type ) ) {
		return mrn_editor_lockdown_get_reusable_layout();
	}

	if ( in_array( $post_type, mrn_editor_lockdown_get_dynamic_post_types(), true ) ) {
		return mrn_editor_lockdown_get_dynamic_layout();
	}

	return null;
}

/**
 * Get all post types that should receive screen-option lock filters.
 *
 * @return string[]
 */
function mrn_editor_lockdown_get_supported_post_types() {
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

	return array_values( array_unique( array_filter( $post_types, 'is_string' ) ) );
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

	update_user_meta( $user_id, 'screen_layout_' . $post_type, (int) $settings['screen_layout'] );
	update_user_meta( $user_id, 'meta-box-order_' . $post_type, $settings['meta_box_order'] );
	update_user_meta( $user_id, 'closedpostboxes_' . $post_type, $settings['closed'] );
}
add_action( 'current_screen', 'mrn_editor_lockdown_apply_layout' );

/**
 * Filter screen layout user options for locked post types.
 *
 * @param mixed $result Existing user option value.
 * @param string $option Option name.
 * @param int $user User ID.
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
 * @param mixed $result Existing user option value.
 * @param string $option Option name.
 * @param int $user User ID.
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
 * Register dynamic user-option filters for supported post types.
 *
 * @return void
 */
function mrn_editor_lockdown_register_option_filters() {
	foreach ( mrn_editor_lockdown_get_supported_post_types() as $post_type ) {
		add_filter( 'get_user_option_screen_layout_' . $post_type, 'mrn_editor_lockdown_filter_screen_layout_option', 10, 3 );
		add_filter( 'get_user_option_meta-box-order_' . $post_type, 'mrn_editor_lockdown_filter_metabox_order_option', 10, 3 );
		add_filter( 'get_user_option_closedpostboxes_' . $post_type, 'mrn_editor_lockdown_filter_closed_metaboxes', 10, 2 );
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
		.mrn-editor-sidebar-toggle {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 4px;
			width: 110px;
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
			transition: width 0.22s ease, margin-right 0.22s ease, opacity 0.22s ease;
		}

		body.mrn-editor-sidebar-collapsible #postbox-container-1 > *:not(.mrn-editor-sidebar-toggle) {
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
			var storageKey = 'mrnEditorSidebarCollapsed:' + postType;
			var sidebar = document.getElementById('postbox-container-1');
			var postBody = document.getElementById('post-body');
			var postBodyContent = document.getElementById('post-body-content');
			var screenMetaLinks = document.getElementById('screen-meta-links');
			var toggle;
			var toggleIcon;
			var toggleText;
			var restoreTimer;

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
					toggle.setAttribute('aria-label', collapsed ? 'Show sidebar settings' : 'Hide sidebar settings');
					toggle.setAttribute('title', collapsed ? 'Show sidebar settings' : 'Hide sidebar settings');
				}

				if (toggleIcon) {
					toggleIcon.className = collapsed ? 'dashicons dashicons-arrow-left-alt2' : 'dashicons dashicons-arrow-right-alt2';
				}

				if (toggleText) {
					toggleText.textContent = collapsed ? 'Show Sidebar' : 'Hide Sidebar';
				}
			}

			function getStoredSidebarState() {
				try {
					return 'collapsed' === window.localStorage.getItem(storageKey);
				} catch (error) {
					return false;
				}
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

				body.classList.add('mrn-editor-sidebar-collapsible');

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

				screenMetaLinks.appendChild(toggle);
				body.classList.add('mrn-editor-sidebar-ready');
				restoreSidebarState();
				scheduleSidebarStateRestore();
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

			initSidebarToggle();
			window.addEventListener('load', function() {
				scheduleSidebarStateRestore();
			});

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

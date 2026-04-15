<?php
/**
 * Builder admin behavior.
 *
 * @package mrn-base-stack
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MRN_BASE_STACK_LAYOUT_CHOOSER_META_KEY' ) ) {
	define( 'MRN_BASE_STACK_LAYOUT_CHOOSER_META_KEY', '_mrn_enabled_builder_layouts' );
}

if ( ! defined( 'MRN_BASE_STACK_LAYOUT_CHOOSER_NONCE_ACTION' ) ) {
	define( 'MRN_BASE_STACK_LAYOUT_CHOOSER_NONCE_ACTION', 'mrn-base-stack-layout-chooser' );
}

/**
 * Resolve the current post ID for classic post editor requests.
 *
 * @return int
 */
function mrn_base_stack_get_builder_layout_selector_post_id() {
	static $post_id = null;

	if ( null !== $post_id ) {
		return $post_id;
	}

	$post_id = 0;

	if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only post context lookup.
		$post_id = absint( wp_unslash( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint sanitizes scalar post IDs.
	}

	if ( ! $post_id && isset( $_POST['post_ID'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only post context lookup.
		$post_id = absint( wp_unslash( $_POST['post_ID'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint sanitizes scalar post IDs.
	}

	if ( ! $post_id && function_exists( 'acf_get_form_data' ) ) {
		$acf_post_id = acf_get_form_data( 'post_id' );

		if ( is_numeric( $acf_post_id ) ) {
			$post_id = absint( $acf_post_id );
		} elseif ( is_string( $acf_post_id ) && preg_match( '/^post_(\d+)$/', $acf_post_id, $matches ) ) {
			$post_id = absint( $matches[1] );
		}
	}

	if ( ! $post_id && isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post ) {
		$post_id = (int) $GLOBALS['post']->ID;
	}

	if ( ! $post_id && isset( $GLOBALS['post_ID'] ) ) {
		$post_id = absint( $GLOBALS['post_ID'] );
	}

	return $post_id;
}

/**
 * Get the current post type for classic post editor requests.
 *
 * @param int $post_id Current post ID.
 * @return string
 */
function mrn_base_stack_get_builder_layout_selector_post_type( $post_id = 0 ) {
	$post_type = '';
	$post_id   = absint( $post_id );

	if ( $post_id ) {
		$post_type = get_post_type( $post_id );
	}

	if ( ! is_string( $post_type ) || '' === $post_type ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen instanceof WP_Screen ) {
			$post_type = (string) $screen->post_type;
		}
	}

	return sanitize_key( (string) $post_type );
}

/**
 * Get the saved builder layout allow-list for a post.
 *
 * @param int $post_id Post ID.
 * @return array<int, string>
 */
function mrn_base_stack_get_saved_builder_layout_selection( $post_id ) {
	static $cache = array();

	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return array();
	}

	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$raw = get_post_meta( $post_id, MRN_BASE_STACK_LAYOUT_CHOOSER_META_KEY, true );
	if ( ! is_array( $raw ) ) {
		$cache[ $post_id ] = array();
		return $cache[ $post_id ];
	}

	$cache[ $post_id ] = array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $raw )
			)
		)
	);

	return $cache[ $post_id ];
}

/**
 * Get chooser-managed flexible-content field key map by field name.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_layout_chooser_field_key_map() {
	return array(
		'page_hero_rows'         => 'field_mrn_page_hero_rows',
		'page_content_rows'      => 'field_mrn_page_content_rows',
		'page_after_content_rows' => 'field_mrn_page_after_content_rows',
	);
}

/**
 * Get chooser-managed layout metadata keyed by flexible-content field name.
 *
 * @return array<string, array<int, array<string, mixed>>>
 */
function mrn_base_stack_get_layout_chooser_layouts_by_field() {
	$map     = mrn_base_stack_get_layout_chooser_field_key_map();
	$layouts = array();

	foreach ( $map as $field_name => $field_key ) {
		$field_name = sanitize_key( (string) $field_name );
		$field_key  = sanitize_key( (string) $field_key );

		if ( '' === $field_name || '' === $field_key ) {
			continue;
		}

		$layouts[ $field_name ] = mrn_base_stack_get_builder_add_row_layout_menu_items( $field_key );
	}

	return $layouts;
}

/**
 * Get all selectable top-level layout names for the layout chooser.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_selectable_builder_layout_names() {
	$hidden_layouts = function_exists( 'mrn_base_stack_get_hidden_builder_layouts' ) ? mrn_base_stack_get_hidden_builder_layouts() : array();
	$layout_names   = array();

	foreach ( mrn_base_stack_get_layout_chooser_layouts_by_field() as $field_layouts ) {
		if ( ! is_array( $field_layouts ) ) {
			continue;
		}

		foreach ( $field_layouts as $layout ) {
			if ( ! is_array( $layout ) ) {
				continue;
			}

			$name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}

			$is_page_only = ! empty( $layout['isPageOnly'] );
			if ( $is_page_only || in_array( $name, $hidden_layouts, true ) ) {
				continue;
			}

			$layout_names[] = $name;
		}
	}

	return array_values( array_unique( $layout_names ) );
}

/**
 * Get builder meta-key prefixes that can contain flexible-layout names.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_usage_source_prefixes() {
	return array(
		'page_hero_rows',
		'page_content_rows',
		'page_after_content_rows',
	);
}

/**
 * Get currently-used layout names from all builder buckets on a post.
 *
 * @param int $post_id Post ID.
 * @return array<int, string>
 */
function mrn_base_stack_get_used_builder_layout_names( $post_id ) {
	static $cache = array();

	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return array();
	}

	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$names = array();
	$meta  = get_post_meta( $post_id );

	if ( ! is_array( $meta ) || empty( $meta ) ) {
		$cache[ $post_id ] = array();
		return $cache[ $post_id ];
	}

	$prefixes = mrn_base_stack_get_builder_layout_usage_source_prefixes();
	$prefixes = array_values(
		array_filter(
			array_map( 'sanitize_key', $prefixes )
		)
	);

	foreach ( $meta as $meta_key => $values ) {
		$meta_key = (string) $meta_key;

		if ( '_acf_fc_layout' !== substr( $meta_key, -14 ) ) {
			continue;
		}

		$matches_prefix = false;

		foreach ( $prefixes as $prefix ) {
			if ( 0 === strpos( $meta_key, $prefix . '_' ) ) {
				$matches_prefix = true;
				break;
			}
		}

		if ( ! $matches_prefix || ! is_array( $values ) ) {
			continue;
		}

		foreach ( $values as $value ) {
			$layout_name = sanitize_key( (string) $value );
			if ( '' === $layout_name ) {
				continue;
			}

			$names[] = $layout_name;
		}
	}

	$cache[ $post_id ] = array_values( array_unique( $names ) );

	return $cache[ $post_id ];
}

/**
 * Get the layout allow-list for the current post.
 *
 * @param int $post_id Post ID.
 * @return array<int, string>
 */
function mrn_base_stack_get_allowed_builder_layout_names( $post_id ) {
	static $cache = array();

	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return array();
	}

	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$cache[ $post_id ] = array_values(
		array_unique(
			array_merge(
				mrn_base_stack_get_saved_builder_layout_selection( $post_id ),
				mrn_base_stack_get_used_builder_layout_names( $post_id )
			)
		)
	);

	return $cache[ $post_id ];
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
	if ( ! $screen instanceof WP_Screen || ! in_array( sanitize_key( (string) $screen->post_type ), mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		return;
	}

	$post_id                  = mrn_base_stack_get_builder_layout_selector_post_id();
	$saved_layout_selection   = $post_id ? mrn_base_stack_get_saved_builder_layout_selection( $post_id ) : array();
	$allowed_layout_selection = $post_id ? mrn_base_stack_get_allowed_builder_layout_names( $post_id ) : array();
	$initial_layout_selection = ! empty( $saved_layout_selection ) ? $saved_layout_selection : $allowed_layout_selection;
	$has_initial_layout_set   = ! empty( $initial_layout_selection );
	$layout_chooser_path      = get_template_directory() . '/js/admin-layout-chooser.js';
	$layout_chooser_ver       = file_exists( $layout_chooser_path ) ? (string) filemtime( $layout_chooser_path ) : _S_VERSION;

	if ( function_exists( 'wp_enqueue_editor' ) ) {
		wp_enqueue_editor();
	}

	$content_builder_admin_path = get_template_directory() . '/js/content-builder-admin.js';
	$content_builder_admin_ver  = file_exists( $content_builder_admin_path ) ? (string) filemtime( $content_builder_admin_path ) : _S_VERSION;

	wp_enqueue_script(
		'mrn-base-stack-content-builder-admin',
		get_template_directory_uri() . '/js/content-builder-admin.js',
		array( 'jquery' ),
		$content_builder_admin_ver,
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
				'enableDetachment'        => false,
				'builderLayouts'          => mrn_base_stack_get_builder_add_row_layout_menu_items(),
				'disabledLayouts'         => function_exists( 'mrn_base_stack_get_hidden_builder_layouts' ) ? mrn_base_stack_get_hidden_builder_layouts() : array(),
				'contentListTaxonomies'   => function_exists( 'mrn_base_stack_get_content_list_post_type_taxonomy_map' ) ? mrn_base_stack_get_content_list_post_type_taxonomy_map() : array(),
				'contentListDisplayModes' => function_exists( 'mrn_base_stack_get_content_list_display_mode_choice_map' ) ? mrn_base_stack_get_content_list_display_mode_choice_map() : array(),
				'layoutChooser'           => array(
					'postId'                    => $post_id,
					'selectedLayouts'           => $initial_layout_selection,
					'hasSavedSelection'         => $has_initial_layout_set,
					'canPersistSelection'       => $post_id > 0,
					'managedFieldNames'         => array_keys( mrn_base_stack_get_layout_chooser_field_key_map() ),
					'layoutsByField'            => mrn_base_stack_get_layout_chooser_layouts_by_field(),
					'saveAction'                => 'mrn_base_stack_save_builder_layout_selection',
					'nonce'                     => wp_create_nonce( MRN_BASE_STACK_LAYOUT_CHOOSER_NONCE_ACTION ),
					'launchButton'              => 'Choose Layouts',
					'insertButton'              => 'Insert Layout(s)',
					'updateButton'              => 'Save Selection',
					'showNotice'                => false,
					'savingButton'              => 'Saving...',
					'dialogTitle'               => 'Choose Allowed Layouts',
					'dialogDescription'         => 'Choose which layouts this entry can add. You can reopen this chooser later to allow more layouts.',
					'emptySelectionError'       => 'Choose at least one layout to continue.',
					'missingSelectionNotice'    => 'Pick your starting layout set before building this page.',
					'readyNotice'               => 'Layout chooser is available when you need to allow more layouts.',
					'saveFailedNotice'          => 'Could not save the layout selection.',
					'saveSuccessNotice'         => 'Layouts saved.',
					'cannotResolvePostIdNotice' => 'Save this draft once, then choose layouts.',
				),
			)
		);

	wp_enqueue_script(
		'mrn-base-stack-admin-layout-chooser',
		get_template_directory_uri() . '/js/admin-layout-chooser.js',
		array( 'jquery', 'mrn-base-stack-content-builder-admin' ),
		$layout_chooser_ver,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'mrn_base_stack_admin_enqueue_builder_assets' );

/**
 * Pre-hide heavy builder row bodies before first paint so the editor does not
 * visibly collapse large ACF structures after the page is already on screen.
 *
 * The admin scripts clear these markers once initial collapse/detach work is
 * complete, and a timeout fallback prevents the screen from staying hidden if
 * one of those scripts fails unexpectedly.
 *
 * @return void
 */
function mrn_base_stack_precollapse_builder_admin_rows() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen || 'post' !== $screen->base ) {
		return;
	}

	if ( ! in_array( sanitize_key( (string) $screen->post_type ), mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		return;
	}

	/**
	 * Toggle pre-collapse rendering behavior for builder admin screens.
	 *
	 * Disabled by default because hiding/revealing large ACF trees can create
	 * visible layout jumps on some stacks.
	 *
	 * @param bool $enabled Whether pre-collapse should run.
	 */
	if ( ! apply_filters( 'mrn_base_stack_enable_builder_admin_precollapse', false ) ) {
		return;
	}
	?>
	<script id="mrn-base-stack-precollapse-admin-script">
		(function() {
			var root = document.documentElement;

			if ( ! root ) {
				return;
			}

			root.classList.add( 'mrn-base-stack-admin-precollapse' );
			root.setAttribute( 'data-mrn-builder-precollapse', 'pending' );
			root.setAttribute( 'data-mrn-repeater-precollapse', 'pending' );

			window.setTimeout( function() {
				root.classList.remove( 'mrn-base-stack-admin-precollapse' );
				root.removeAttribute( 'data-mrn-builder-precollapse' );
				root.removeAttribute( 'data-mrn-repeater-precollapse' );
			}, 4000 );
		}());
	</script>
	<style id="mrn-base-stack-precollapse-admin-style">
		.mrn-base-stack-admin-precollapse .acf-field-flexible-content .layout:not(.acf-clone) > .acf-fc-layout-actions-wrap {
			border-bottom-width: 0 !important;
		}

		.mrn-base-stack-admin-precollapse .acf-field-flexible-content .layout:not(.acf-clone) > .acf-fields,
		.mrn-base-stack-admin-precollapse .acf-field-flexible-content .layout:not(.acf-clone) > .acf-fields.-left,
		.mrn-base-stack-admin-precollapse .acf-field-flexible-content .layout:not(.acf-clone) > .acf-table,
		.mrn-base-stack-admin-precollapse .acf-field[data-type="repeater"] > .acf-input > .acf-repeater > .acf-table > tbody > .acf-row:not(.acf-clone) > .acf-fields,
		.mrn-base-stack-admin-precollapse .acf-field[data-type="repeater"] > .acf-input > .acf-repeater > .acf-table > .acf-tbody > .acf-row:not(.acf-clone) > .acf-fields,
		.mrn-base-stack-admin-precollapse .acf-field[data-type="repeater"] > .acf-input > .acf-repeater > table > tbody > .acf-row:not(.acf-clone) > .acf-fields,
		.mrn-base-stack-admin-precollapse .acf-field[data-type="repeater"] > .acf-input > .acf-repeater > .values > .acf-row:not(.acf-clone) > .acf-fields,
		.mrn-base-stack-admin-precollapse .acf-field[data-type="repeater"] > .acf-input > .acf-repeater > .acf-table > tbody > .acf-row:not(.acf-clone) > td:not(.acf-row-handle):not(.acf-row-handle.order),
		.mrn-base-stack-admin-precollapse .acf-field[data-type="repeater"] > .acf-input > .acf-repeater > .acf-table > .acf-tbody > .acf-row:not(.acf-clone) > td:not(.acf-row-handle):not(.acf-row-handle.order),
		.mrn-base-stack-admin-precollapse .acf-field[data-type="repeater"] > .acf-input > .acf-repeater > table > tbody > .acf-row:not(.acf-clone) > td:not(.acf-row-handle):not(.acf-row-handle.order),
		.mrn-base-stack-admin-precollapse .acf-field[data-type="repeater"] > .acf-input > .acf-repeater > .values > .acf-row:not(.acf-clone) > td:not(.acf-row-handle):not(.acf-row-handle.order) {
			display: none !important;
		}
	</style>
	<?php
}
add_action( 'admin_head', 'mrn_base_stack_precollapse_builder_admin_rows', 1 );

/**
 * Delay ACF WYSIWYG initialization on heavy classic editor screens.
 *
 * This keeps TinyMCE instances from booting on initial page load for builder-
 * style post types, allowing editors to initialize only when a field is used.
 *
 * @param array<string, mixed>|false $field ACF field configuration.
 * @return array<string, mixed>|false
 */
function mrn_base_stack_delay_builder_wysiwyg_initialization( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen || 'post' !== $screen->base ) {
		return $field;
	}

	if ( ! in_array( sanitize_key( (string) $screen->post_type ), mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		return $field;
	}

	if ( ! empty( $field['delay'] ) ) {
		return $field;
	}

	$field['delay'] = 1;

	return $field;
}
add_filter( 'acf/prepare_field/type=wysiwyg', 'mrn_base_stack_delay_builder_wysiwyg_initialization', 20 );

/**
 * Build live Add Row menu metadata from the registered page builder layouts.
 *
 * This keeps editor menu behavior aligned with the actual flexible-content
 * layouts instead of relying on parallel hardcoded lists in admin JavaScript.
 *
 * @param string $field_key ACF flexible-content field key.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_builder_add_row_layout_menu_items( $field_key = 'field_mrn_page_content_rows' ) {
	if ( ! function_exists( 'acf_get_field' ) ) {
		return array();
	}

	$field_key = sanitize_key( (string) $field_key );
	$field     = acf_get_field( $field_key );
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
 * Filter builder flexible-content layouts to the per-post allow-list.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_filter_editor_flexible_layouts_by_selection( $field ) {
	if ( ! is_array( $field ) || empty( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
		return $field;
	}

	$post_id = mrn_base_stack_get_builder_layout_selector_post_id();
	if ( ! $post_id ) {
		return $field;
	}

	$post_type = mrn_base_stack_get_builder_layout_selector_post_type( $post_id );
	if ( '' === $post_type || ! in_array( $post_type, mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		return $field;
	}

	$allowed_layout_names = mrn_base_stack_get_allowed_builder_layout_names( $post_id );
	if ( empty( $allowed_layout_names ) ) {
		return $field;
	}

	$filtered_layouts = array();

	foreach ( $field['layouts'] as $layout_key => $layout ) {
		if ( ! is_array( $layout ) ) {
			continue;
		}

		$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
		if ( '' === $layout_name || ! in_array( $layout_name, $allowed_layout_names, true ) ) {
			continue;
		}

		$filtered_layouts[ $layout_key ] = $layout;
	}

	if ( empty( $filtered_layouts ) ) {
		return $field;
	}

	$field['layouts'] = $filtered_layouts;

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_page_hero_rows', 'mrn_base_stack_filter_editor_flexible_layouts_by_selection', 40 );
add_filter( 'acf/load_field/key=field_mrn_page_content_rows', 'mrn_base_stack_filter_editor_flexible_layouts_by_selection', 40 );
add_filter( 'acf/load_field/key=field_mrn_page_after_content_rows', 'mrn_base_stack_filter_editor_flexible_layouts_by_selection', 40 );
add_filter( 'acf/prepare_field/key=field_mrn_page_hero_rows', 'mrn_base_stack_filter_editor_flexible_layouts_by_selection', 40 );
add_filter( 'acf/prepare_field/key=field_mrn_page_content_rows', 'mrn_base_stack_filter_editor_flexible_layouts_by_selection', 40 );
add_filter( 'acf/prepare_field/key=field_mrn_page_after_content_rows', 'mrn_base_stack_filter_editor_flexible_layouts_by_selection', 40 );

/**
 * Persist builder layout chooser selections for a specific post.
 *
 * @return void
 */
function mrn_base_stack_save_builder_layout_selection() {
	if ( ! check_ajax_referer( MRN_BASE_STACK_LAYOUT_CHOOSER_NONCE_ACTION, 'nonce', false ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Security check failed. Refresh and try again.', 'mrn-base-stack' ),
			),
			403
		);
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint sanitizes scalar post IDs.
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'You do not have permission to update layouts for this entry.', 'mrn-base-stack' ),
			),
			403
		);
	}

	$post_type = sanitize_key( (string) get_post_type( $post_id ) );
	if ( '' === $post_type || ! in_array( $post_type, mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Layouts can only be managed for supported builder entries.', 'mrn-base-stack' ),
			),
			400
		);
	}

	$raw_layouts = isset( $_POST['layouts'] ) ? (array) wp_unslash( $_POST['layouts'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
	$layouts     = array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $raw_layouts )
			)
		)
	);

	$available_layouts = mrn_base_stack_get_selectable_builder_layout_names();
	if ( ! empty( $available_layouts ) ) {
		$layouts = array_values( array_intersect( $layouts, $available_layouts ) );
	}

	if ( empty( $layouts ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Choose at least one layout.', 'mrn-base-stack' ),
			),
			400
		);
	}

	update_post_meta( $post_id, MRN_BASE_STACK_LAYOUT_CHOOSER_META_KEY, $layouts );

	wp_send_json_success(
		array(
			'postId'          => $post_id,
			'selectedLayouts' => $layouts,
		)
	);
}
add_action( 'wp_ajax_mrn_base_stack_save_builder_layout_selection', 'mrn_base_stack_save_builder_layout_selection' );

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
			opacity: 0.8;
			pointer-events: auto;
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

		/* Hide heavy ACF clone-template bodies before admin JS runs so the
			edit screen does not visibly assemble template field groups on load. */
		.acf-field-flexible-content > .acf-input > .acf-flexible-content > .clones > .layout.acf-clone > .acf-fields,
		.acf-field-flexible-content > .acf-input > .clones > .layout.acf-clone > .acf-fields,
		.acf-field[data-type="repeater"] > .acf-input > .acf-repeater > .acf-table > tbody > .acf-row.acf-clone > td:not(.acf-row-handle):not(.acf-row-handle.order),
		.acf-field[data-type="repeater"] > .acf-input > .acf-repeater > .acf-table > .acf-tbody > .acf-row.acf-clone > td:not(.acf-row-handle):not(.acf-row-handle.order),
		.acf-field[data-type="repeater"] > .acf-input > .acf-repeater > table > tbody > .acf-row.acf-clone > td:not(.acf-row-handle):not(.acf-row-handle.order),
		.acf-field[data-type="repeater"] > .acf-input > .acf-repeater > .values > .acf-row.acf-clone > .acf-fields {
			display: none !important;
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

<?php
/**
 * Plugin Name: MRN Editor Lockdown
 * Description: Enforces MRN classic editor metabox ordering for posts, pages, and reusable block library screens across the stack.
 * Version: 1.0.32
 *
 * @package MRNEditorLockdown
 */

defined( 'ABSPATH' ) || exit;

/**
 * Deny the Theme File Editor capability for every user, including admins.
 *
 * This blocks direct access to theme-editor.php without removing broader
 * theme-management capabilities such as installing, activating, or updating
 * themes.
 *
 * @param array $allcaps Effective capabilities for the current user.
 * @return array
 */
function mrn_editor_lockdown_disable_theme_file_editor_capability( $allcaps ) {
	if ( ! is_array( $allcaps ) ) {
		$allcaps = array();
	}

	$disabled = function_exists( 'mrn_config_helper_is_theme_file_editor_disabled' )
		? mrn_config_helper_is_theme_file_editor_disabled()
		: true;

	if ( $disabled ) {
		$allcaps['edit_themes'] = false;
	}

	return $allcaps;
}
add_filter( 'user_has_cap', 'mrn_editor_lockdown_disable_theme_file_editor_capability', PHP_INT_MAX );

/**
 * Remove the Theme File Editor entry from Appearance as defense in depth.
 *
 * @return void
 */
function mrn_editor_lockdown_remove_theme_file_editor_menu() {
	$disabled = function_exists( 'mrn_config_helper_is_theme_file_editor_disabled' )
		? mrn_config_helper_is_theme_file_editor_disabled()
		: true;

	if ( $disabled ) {
		remove_submenu_page( 'themes.php', 'theme-editor.php' );
	}
}
add_action( 'admin_menu', 'mrn_editor_lockdown_remove_theme_file_editor_menu', PHP_INT_MAX );

/**
 * Determine whether the heavyweight editor loading mask should run.
 *
 * @return bool
 */
function mrn_editor_lockdown_is_loading_mask_enabled() {
	return (bool) apply_filters( 'mrn_editor_lockdown_loading_mask_enabled', false );
}

/**
 * Determine whether lightweight non-blocking editor loading feedback should run.
 *
 * @return bool
 */
function mrn_editor_lockdown_is_loading_indicator_enabled() {
	return (bool) apply_filters( 'mrn_editor_lockdown_loading_indicator_enabled', true );
}

/**
 * SEO Helper ACF metabox ID.
 *
 * @return string
 */
function mrn_editor_lockdown_get_seo_helper_metabox_id() {
	return 'acf-group_69a1c0f3a1b01';
}

/**
 * Schema Bridge controls metabox ID.
 *
 * @return string
 */
function mrn_editor_lockdown_get_schema_bridge_metabox_id() {
	return 'mrn-schema-bridge-controls';
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
 * Determine whether the legacy SmartCrawl metabox should be removed.
 *
 * Defaults to disabled so SmartCrawl stays visible on edit screens.
 *
 * @return bool
 */
function mrn_editor_lockdown_should_remove_legacy_seo_metabox() {
	return (bool) apply_filters( 'mrn_editor_lockdown_remove_legacy_seo_metabox', false );
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
 * Ensure the SEO Helper and matching ACF metaboxes are never hidden on locked
 * classic editor screens.
 *
 * @param mixed   $hidden         Existing hidden metabox IDs.
 * @param string[] $acf_metabox_ids Matching ACF metabox IDs.
 * @return string[]
 */
function mrn_editor_lockdown_get_visible_hidden_metaboxes( $hidden, $acf_metabox_ids = array() ) {
	$metabox_ids = array_merge(
		array( mrn_editor_lockdown_get_seo_helper_metabox_id() ),
		is_array( $acf_metabox_ids ) ? $acf_metabox_ids : array()
	);
	$metabox_ids = array_values( array_filter( array_map( 'sanitize_key', $metabox_ids ) ) );
	$hidden      = is_array( $hidden ) ? $hidden : array();

	return array_values(
		array_filter(
			array_map( 'sanitize_key', $hidden ),
			static function ( $item ) use ( $metabox_ids ) {
				return ! in_array( $item, $metabox_ids, true );
			}
		)
	);
}

/**
 * Get the matching ACF field-group metaboxes for a post type.
 *
 * The return value preserves ACF's own group order and allows callers to
 * reorder or remove entries with the
 * `mrn_editor_lockdown_acf_field_groups` filter.
 *
 * @param string $post_type Post type slug.
 * @return array<int, array<string, mixed>>
 */
function mrn_editor_lockdown_get_acf_field_group_metaboxes( $post_type ) {
	static $cache = array();

	$post_type = sanitize_key( (string) $post_type );

	if ( array_key_exists( $post_type, $cache ) ) {
		return $cache[ $post_type ];
	}

	if ( '' === $post_type || ! function_exists( 'acf_get_field_groups' ) ) {
		$cache[ $post_type ] = array();
		return $cache[ $post_type ];
	}

	$field_groups = acf_get_field_groups(
		array(
			'post_type' => $post_type,
		)
	);

	if ( ! is_array( $field_groups ) || empty( $field_groups ) ) {
		$cache[ $post_type ] = array();
		return $cache[ $post_type ];
	}

	$allowed_positions = array( 'normal', 'side', 'advanced', 'acf_after_title' );
	$metaboxes         = array();

	foreach ( $field_groups as $field_group ) {
		if ( ! is_array( $field_group ) || empty( $field_group['key'] ) ) {
			continue;
		}

		$metabox_id = 'acf-' . sanitize_key( (string) $field_group['key'] );
		if ( '' === $metabox_id ) {
			continue;
		}

		$position = isset( $field_group['position'] ) ? sanitize_key( (string) $field_group['position'] ) : 'normal';
		if ( ! in_array( $position, $allowed_positions, true ) ) {
			$position = 'normal';
		}

		$metaboxes[] = array(
			'id'         => $metabox_id,
			'key'        => sanitize_key( (string) $field_group['key'] ),
			'position'   => $position,
			'menu_order' => isset( $field_group['menu_order'] ) ? (int) $field_group['menu_order'] : 0,
			'title'      => isset( $field_group['title'] ) ? sanitize_text_field( (string) $field_group['title'] ) : '',
			'field_group' => $field_group,
		);
	}

	/**
	 * Filter the matching ACF field-group metabox definitions for a post type.
	 *
	 * Return a modified list to exclude a group or reposition it by changing
	 * its `position` entry.
	 *
	 * @param array<int, array<string, mixed>> $metaboxes   Normalized metabox records.
	 * @param string                           $post_type   Post type slug.
	 * @param array<int, array<string, mixed>> $field_groups Raw ACF field groups.
	 */
	$metaboxes = apply_filters( 'mrn_editor_lockdown_acf_field_groups', $metaboxes, $post_type, $field_groups );

	if ( ! is_array( $metaboxes ) ) {
		$cache[ $post_type ] = array();
		return $cache[ $post_type ];
	}

	$normalized_metaboxes = array();

	foreach ( $metaboxes as $metabox ) {
		if ( ! is_array( $metabox ) || empty( $metabox['id'] ) ) {
			continue;
		}

		$metabox_id = sanitize_key( (string) $metabox['id'] );
		if ( '' === $metabox_id ) {
			continue;
		}

		$position = isset( $metabox['position'] ) ? sanitize_key( (string) $metabox['position'] ) : 'normal';
		if ( ! in_array( $position, $allowed_positions, true ) ) {
			$position = 'normal';
		}

		$normalized_metaboxes[ $metabox_id ] = array(
			'id'          => $metabox_id,
			'key'         => isset( $metabox['key'] ) ? sanitize_key( (string) $metabox['key'] ) : $metabox_id,
			'position'    => $position,
			'menu_order'  => isset( $metabox['menu_order'] ) ? (int) $metabox['menu_order'] : 0,
			'title'       => isset( $metabox['title'] ) ? sanitize_text_field( (string) $metabox['title'] ) : '',
			'field_group' => isset( $metabox['field_group'] ) && is_array( $metabox['field_group'] ) ? $metabox['field_group'] : array(),
		);
	}

	$normalized_metaboxes = array_values( $normalized_metaboxes );

	usort(
		$normalized_metaboxes,
		static function ( $left, $right ) {
			if ( $left['position'] !== $right['position'] ) {
				return strcmp( (string) $left['position'], (string) $right['position'] );
			}

			if ( $left['menu_order'] !== $right['menu_order'] ) {
				return (int) $left['menu_order'] <=> (int) $right['menu_order'];
			}

			if ( $left['title'] !== $right['title'] ) {
				return strcmp( (string) $left['title'], (string) $right['title'] );
			}

			return strcmp( (string) $left['id'], (string) $right['id'] );
		}
	);

	$cache[ $post_type ] = $normalized_metaboxes;
	return $cache[ $post_type ];
}

/**
 * Get the matching ACF metabox IDs for a post type.
 *
 * @param string $post_type Post type slug.
 * @return string[]
 */
function mrn_editor_lockdown_get_acf_field_group_metabox_ids( $post_type ) {
	$metaboxes = mrn_editor_lockdown_get_acf_field_group_metaboxes( $post_type );

	return array_values(
		array_filter(
			array_map(
				static function ( $metabox ) {
					return is_array( $metabox ) && ! empty( $metabox['id'] ) ? sanitize_key( (string) $metabox['id'] ) : '';
				},
				$metaboxes
			)
		)
	);
}

/**
 * Append metabox IDs to an order string without disturbing existing entries.
 *
 * @param string   $order      Existing comma-delimited metabox order.
 * @param string[] $metabox_ids Metabox IDs to append.
 * @return string
 */
function mrn_editor_lockdown_append_missing_metabox_ids_to_order( $order, $metabox_ids ) {
	$order_items   = array_values( array_filter( array_map( 'trim', explode( ',', (string) $order ) ) ) );
	$order_lookup  = array_fill_keys( $order_items, true );
	$metabox_ids   = is_array( $metabox_ids ) ? $metabox_ids : array();
	$metabox_ids   = array_values( array_filter( array_map( 'sanitize_key', $metabox_ids ) ) );
	$missing_items  = array();

	foreach ( $metabox_ids as $metabox_id ) {
		if ( isset( $order_lookup[ $metabox_id ] ) ) {
			continue;
		}

		$missing_items[] = $metabox_id;
	}

	if ( empty( $missing_items ) ) {
		return implode( ',', array_values( array_unique( $order_items ) ) );
	}

	$order_items = array_merge( $order_items, $missing_items );

	return implode( ',', array_values( array_unique( $order_items ) ) );
}

/**
 * Insert metabox IDs before a specific anchor without disturbing existing ones.
 *
 * @param string   $order      Existing comma-delimited metabox order.
 * @param string[] $metabox_ids Metabox IDs to insert.
 * @param string   $anchor     Anchor metabox ID.
 * @return string
 */
function mrn_editor_lockdown_insert_missing_metabox_ids_before_anchor( $order, $metabox_ids, $anchor ) {
	$order_items  = array_values( array_filter( array_map( 'trim', explode( ',', (string) $order ) ) ) );
	$order_lookup = array_fill_keys( $order_items, true );
	$metabox_ids  = is_array( $metabox_ids ) ? $metabox_ids : array();
	$metabox_ids  = array_values( array_filter( array_map( 'sanitize_key', $metabox_ids ) ) );
	$missing_items = array();

	foreach ( $metabox_ids as $metabox_id ) {
		if ( isset( $order_lookup[ $metabox_id ] ) ) {
			continue;
		}

		$missing_items[] = $metabox_id;
	}

	if ( empty( $missing_items ) ) {
		return implode( ',', array_values( array_unique( $order_items ) ) );
	}

	$anchor     = sanitize_key( (string) $anchor );
	$anchor_at  = false;

	if ( '' !== $anchor ) {
		$anchor_at = array_search( $anchor, $order_items, true );
	}

	if ( false === $anchor_at ) {
		$anchor_at = count( $order_items );
	}

	array_splice( $order_items, (int) $anchor_at, 0, $missing_items );

	return implode( ',', array_values( array_unique( $order_items ) ) );
}

/**
 * Merge discovered ACF field-group metaboxes into a locked editor layout.
 *
 * @param array<string, mixed> $layout    Locked editor layout.
 * @param string               $post_type Post type slug.
 * @return array<string, mixed>
 */
function mrn_editor_lockdown_merge_acf_field_groups_into_layout( $layout, $post_type ) {
	if ( ! is_array( $layout ) ) {
		return $layout;
	}

	$metaboxes = mrn_editor_lockdown_get_acf_field_group_metaboxes( $post_type );
	if ( empty( $metaboxes ) ) {
		return $layout;
	}

	$grouped_metaboxes = array(
		'normal'         => array(),
		'side'           => array(),
		'advanced'       => array(),
		'acf_after_title' => array(),
	);

	foreach ( $metaboxes as $metabox ) {
		if ( ! is_array( $metabox ) || empty( $metabox['id'] ) ) {
			continue;
		}

		$position = isset( $metabox['position'] ) ? sanitize_key( (string) $metabox['position'] ) : 'normal';
		if ( ! isset( $grouped_metaboxes[ $position ] ) ) {
			$position = 'normal';
		}

		$grouped_metaboxes[ $position ][] = sanitize_key( (string) $metabox['id'] );
	}

	if ( ! isset( $layout['meta_box_order'] ) || ! is_array( $layout['meta_box_order'] ) ) {
		$layout['meta_box_order'] = array();
	}

	foreach ( array( 'normal', 'advanced' ) as $context ) {
		if ( empty( $grouped_metaboxes[ $context ] ) ) {
			continue;
		}

		$layout['meta_box_order'][ $context ] = mrn_editor_lockdown_append_missing_metabox_ids_to_order(
			isset( $layout['meta_box_order'][ $context ] ) ? $layout['meta_box_order'][ $context ] : '',
			$grouped_metaboxes[ $context ]
		);
	}

	if ( ! empty( $grouped_metaboxes['side'] ) ) {
		$layout['meta_box_order']['side'] = mrn_editor_lockdown_insert_missing_metabox_ids_before_anchor(
			isset( $layout['meta_box_order']['side'] ) ? $layout['meta_box_order']['side'] : '',
			$grouped_metaboxes['side'],
			'mrn-builder-layout-allowlist'
		);
	}

	if ( ! empty( $grouped_metaboxes['acf_after_title'] ) ) {
		$layout['acf_after_title'] = $grouped_metaboxes['acf_after_title'];
	}

	return $layout;
}

/**
 * Build the universal locked-editor sidebar order.
 *
 * Screens only render boxes they actually register, so one canonical order can
 * safely cover Posts, Pages, reusable blocks, and custom post types.
 *
 * @param string[] $taxonomy_metaboxes Taxonomy metabox IDs for the post type.
 * @return string
 */
function mrn_editor_lockdown_get_standard_side_order( $taxonomy_metaboxes = array() ) {
	$taxonomy_metaboxes = is_array( $taxonomy_metaboxes )
		? array_values( array_filter( array_map( 'sanitize_key', $taxonomy_metaboxes ) ) )
		: array();

	$order = array_merge(
		array(
			mrn_editor_lockdown_get_seo_helper_metabox_id(),
			'postimagediv',
			'submitdiv',
		),
		$taxonomy_metaboxes,
		array(
			'pageparentdiv',
			'mrn-builder-layout-allowlist',
			'mrn-config-helper-breadcrumbs',
			mrn_editor_lockdown_get_schema_bridge_metabox_id(),
			'authordiv',
		)
	);

	return implode( ',', array_values( array_unique( $order ) ) );
}

/**
 * Get utility metaboxes that remain collapsed across locked editor screens.
 *
 * @param string[] $additional Additional collapsed metabox IDs.
 * @return string[]
 */
function mrn_editor_lockdown_get_standard_closed_metaboxes( $additional = array() ) {
	$additional = is_array( $additional ) ? $additional : array();

	return array_values(
		array_unique(
			array_merge(
				array(
					'mrn-builder-layout-allowlist',
					'mrn-config-helper-breadcrumbs',
					mrn_editor_lockdown_get_schema_bridge_metabox_id(),
				),
				$additional
			)
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
				'normal'   => 'postexcerpt,slugdiv',
				'side'     => mrn_editor_lockdown_get_standard_side_order( array( 'categorydiv', 'tagsdiv-post_tag' ) ),
				'advanced' => 'ame-cpe-content-permissions',
			),
			'closed' => mrn_editor_lockdown_get_standard_closed_metaboxes( array( 'ame-cpe-content-permissions' ) ),
		),
		'page' => array(
			'screen_layout' => 2,
			'meta_box_order' => array(
				'normal'   => 'slugdiv,revisionsdiv',
				'side'     => mrn_editor_lockdown_get_standard_side_order( array( 'categorydiv', 'tagsdiv-post_tag' ) ),
				'advanced' => 'ame-cpe-content-permissions',
			),
			'closed' => mrn_editor_lockdown_get_standard_closed_metaboxes( array( 'ame-cpe-content-permissions' ) ),
		),
		'gallery' => array(
			'screen_layout' => 2,
			'meta_box_order' => array(
				'normal'   => 'slugdiv,revisionsdiv',
				'side'     => mrn_editor_lockdown_get_standard_side_order( array( 'gallery_categorydiv', 'gallery_tagdiv' ) ),
				'advanced' => 'ame-cpe-content-permissions',
			),
			'closed' => mrn_editor_lockdown_get_standard_closed_metaboxes( array( 'ame-cpe-content-permissions' ) ),
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
			'side'     => mrn_editor_lockdown_get_standard_side_order(),
			'advanced' => '',
		),
		'closed' => mrn_editor_lockdown_get_standard_closed_metaboxes(),
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
 * @param string $post_type Post type slug.
 * @return array<string, mixed>
 */
function mrn_editor_lockdown_get_dynamic_layout( $post_type = '' ) {
	static $base_layout = null;

	if ( null === $base_layout ) {
		$base_layout = array(
			'screen_layout' => 2,
			'meta_box_order' => array(
				'normal'   => 'slugdiv,revisionsdiv',
				'side'     => mrn_editor_lockdown_get_standard_side_order( array( 'categorydiv', 'tagsdiv-post_tag' ) ),
				'advanced' => 'ame-cpe-content-permissions',
			),
			'closed' => mrn_editor_lockdown_get_standard_closed_metaboxes( array( 'ame-cpe-content-permissions' ) ),
		);
	}

	$post_type = sanitize_key( (string) $post_type );
	$layout    = $base_layout;

	/**
	 * Filter the locked fallback layout for one dynamically discovered CPT.
	 *
	 * Stack-owned CPT registries can use this to place their specific metaboxes
	 * without replacing the shared two-column, SEO-first layout contract.
	 *
	 * @param array<string, mixed> $layout    Locked layout settings.
	 * @param string               $post_type Post type slug.
	 */
	$filtered = apply_filters( 'mrn_editor_lockdown_dynamic_layout', $layout, $post_type );

	return is_array( $filtered ) ? $filtered : $layout;
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

	$layout = null;
	$layouts = mrn_editor_lockdown_get_layouts();

	if ( isset( $layouts[ $post_type ] ) ) {
		$layout = $layouts[ $post_type ];
	} elseif ( mrn_editor_lockdown_is_reusable_post_type( $post_type ) ) {
		$layout = mrn_editor_lockdown_get_reusable_layout();
	} elseif ( in_array( $post_type, mrn_editor_lockdown_get_dynamic_post_types(), true ) ) {
		$layout = mrn_editor_lockdown_get_dynamic_layout( $post_type );
	}

	if ( is_array( $layout ) ) {
		$layout = mrn_editor_lockdown_merge_acf_field_groups_into_layout( $layout, $post_type );
	}

	$cache[ $post_type ] = is_array( $layout ) ? $layout : null;

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

	if ( in_array( sanitize_key( (string) $screen->post_type ), array( 'acf-field', 'acf-field-group' ), true ) ) {
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
 * Determine whether classic-editor enforcement should apply to a post type.
 *
 * @param string $post_type Post type slug.
 * @return bool
 */
function mrn_editor_lockdown_should_force_classic_editor_for_post_type( $post_type ) {
	$post_type = sanitize_key( (string) $post_type );
	if ( '' === $post_type ) {
		return false;
	}

	return in_array( $post_type, mrn_editor_lockdown_get_supported_post_types(), true );
}

/**
 * Force supported post types to use Classic Editor.
 *
 * @param bool   $use_block_editor Current block-editor decision.
 * @param string $post_type        Post type slug.
 * @return bool
 */
function mrn_editor_lockdown_force_classic_editor_for_post_type( $use_block_editor, $post_type ) {
	if ( mrn_editor_lockdown_should_force_classic_editor_for_post_type( $post_type ) ) {
		return false;
	}

	return (bool) $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'mrn_editor_lockdown_force_classic_editor_for_post_type', 100, 2 );
add_filter( 'gutenberg_can_edit_post_type', 'mrn_editor_lockdown_force_classic_editor_for_post_type', 100, 2 );

/**
 * Force supported posts to use Classic Editor.
 *
 * @param bool    $use_block_editor Current block-editor decision.
 * @param WP_Post $post             Current post object.
 * @return bool
 */
function mrn_editor_lockdown_force_classic_editor_for_post( $use_block_editor, $post ) {
	if ( $post instanceof WP_Post && mrn_editor_lockdown_should_force_classic_editor_for_post_type( $post->post_type ) ) {
		return false;
	}

	return (bool) $use_block_editor;
}
add_filter( 'use_block_editor_for_post', 'mrn_editor_lockdown_force_classic_editor_for_post', 100, 2 );
add_filter( 'gutenberg_can_edit_post', 'mrn_editor_lockdown_force_classic_editor_for_post', 100, 2 );

/**
 * Ensure TinyMCE/Quicktags runtime is available for classic edit screens.
 *
 * @param string $hook_suffix Current admin hook suffix.
 * @return void
 */
function mrn_editor_lockdown_enqueue_editor_runtime( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	if ( ! function_exists( 'wp_enqueue_editor' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen ) {
		return;
	}

	$post_type = sanitize_key( (string) $screen->post_type );
	if ( '' === $post_type && 'post-new.php' === $hook_suffix && isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
		$post_type = sanitize_key( (string) wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
	}

	if ( '' === $post_type && 'post-new.php' === $hook_suffix ) {
		$post_type = 'post';
	}

	if ( ! mrn_editor_lockdown_should_force_classic_editor_for_post_type( $post_type ) ) {
		return;
	}

	wp_enqueue_editor();
}
add_action( 'admin_enqueue_scripts', 'mrn_editor_lockdown_enqueue_editor_runtime', 20 );

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

	$visible_hidden_metaboxes = mrn_editor_lockdown_get_visible_hidden_metaboxes(
		$current_hidden_metaboxes,
		mrn_editor_lockdown_get_acf_field_group_metabox_ids( $post_type )
	);

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
	if ( ! mrn_editor_lockdown_should_remove_legacy_seo_metabox() ) {
		return;
	}

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
 * Force the SEO Helper and matching ACF metaboxes to remain visible at runtime.
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
			return mrn_editor_lockdown_get_visible_hidden_metaboxes(
				$hidden,
				mrn_editor_lockdown_get_acf_field_group_metabox_ids( $post_type )
			);
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
 * Keep Admin Menu Editor from removing the required SEO Helper metabox.
 *
 * Imported AME screen configurations can retain `isPresent=false` for a box
 * that was unavailable when the export was created. SEO Helper is part of the
 * locked editor contract, so normalize existing screen entries back to present.
 *
 * @return void
 */
function mrn_editor_lockdown_repair_ame_seo_metabox_config() {
	$option_name = 'ws_ame_meta_boxes';
	$raw_config  = get_option( $option_name, '' );

	if ( ! is_string( $raw_config ) || '' === $raw_config ) {
		return;
	}

	$config = json_decode( $raw_config, true );

	if ( ! is_array( $config ) || empty( $config['screens'] ) || ! is_array( $config['screens'] ) ) {
		return;
	}

	$metabox_id = mrn_editor_lockdown_get_seo_helper_metabox_id();
	$changed    = false;

	foreach ( $config['screens'] as &$screen ) {
		if ( ! is_array( $screen ) ) {
			continue;
		}

		foreach ( array( 'metaBoxes:', 'metaBoxes' ) as $collection_key ) {
			if ( empty( $screen[ $collection_key ][ $metabox_id ] ) || ! is_array( $screen[ $collection_key ][ $metabox_id ] ) ) {
				continue;
			}

			$box = &$screen[ $collection_key ][ $metabox_id ];

			if ( empty( $box['isPresent'] ) || empty( $box['wasPresent'] ) ) {
				$box['isPresent']  = true;
				$box['wasPresent'] = true;
				$changed           = true;
			}

			unset( $box );
		}
	}
	unset( $screen );

	if ( $changed ) {
		update_option( $option_name, wp_json_encode( $config ), false );
	}
}
add_action( 'admin_init', 'mrn_editor_lockdown_repair_ame_seo_metabox_config', 0 );

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
	$loading_mask_enabled      = mrn_editor_lockdown_is_loading_mask_enabled();
	$loading_indicator_enabled = mrn_editor_lockdown_is_loading_indicator_enabled();
	?>
	<style id="mrn-editor-lockdown">
	<?php if ( $loading_mask_enabled ) : ?>
		body.post-php:not(.mrn-editor-page-ready),
		body.post-new-php:not(.mrn-editor-page-ready) {
			overflow: hidden;
		}

		body.post-php:not(.mrn-editor-page-ready)::before,
		body.post-new-php:not(.mrn-editor-page-ready)::before {
			content: '';
			position: fixed;
			inset: 0;
			background: rgba(255, 255, 255, 0.9);
			backdrop-filter: blur(3px);
			z-index: 100000;
		}

		body.post-php:not(.mrn-editor-page-ready)::after,
		body.post-new-php:not(.mrn-editor-page-ready)::after {
			content: '';
			position: fixed;
			top: 50%;
			left: 50%;
			width: 42px;
			height: 42px;
			margin: -38px 0 0 -21px;
			border-radius: 50%;
			background: conic-gradient(from 90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.36) 36%, #ffffff 72%, rgba(255, 255, 255, 0));
			-webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 4px), #000 calc(100% - 3px));
			mask: radial-gradient(farthest-side, transparent calc(100% - 4px), #000 calc(100% - 3px));
			animation: mrnEditorPageLoaderSpin 0.82s linear infinite;
			z-index: 100001;
		}

		.mrn-editor-loading-message {
			position: fixed;
			top: 50%;
			left: 50%;
			width: min(88vw, 520px);
			margin-top: 24px;
			padding: 14px 18px;
			transform: translateX(-50%);
			text-align: center;
			color: #ffffff;
			background: #111111;
			border: 1px solid rgba(255, 255, 255, 0.16);
			border-radius: 8px;
			box-shadow: 0 18px 46px rgba(17, 17, 17, 0.24);
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			font-size: 14px;
			font-weight: 600;
			letter-spacing: 0;
			line-height: 1.4;
			text-wrap: balance;
			z-index: 100002;
			pointer-events: none;
		}

		body.post-php:not(.mrn-editor-page-ready):not(.mrn-editor-loading-message-live) #wpwrap::before,
		body.post-new-php:not(.mrn-editor-page-ready):not(.mrn-editor-loading-message-live) #wpwrap::before {
			content: 'Preparing the editor workspace...';
			position: fixed;
			top: 50%;
			left: 50%;
			width: min(88vw, 520px);
			margin-top: 24px;
			padding: 14px 18px;
			transform: translateX(-50%);
			text-align: center;
			color: #ffffff;
			background: #111111;
			border: 1px solid rgba(255, 255, 255, 0.16);
			border-radius: 8px;
			box-shadow: 0 18px 46px rgba(17, 17, 17, 0.24);
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			font-size: 14px;
			font-weight: 600;
			letter-spacing: 0;
			line-height: 1.4;
			text-wrap: balance;
			z-index: 100002;
			pointer-events: none;
		}

	<?php endif; ?>

	<?php if ( $loading_indicator_enabled ) : ?>
		html.mrn-editor-loading-indicator-live::before {
			content: 'Preparing editor controls...';
			position: fixed;
			top: 50%;
			left: 50%;
			width: min(88vw, 360px);
			min-height: 34px;
			padding: 20px 28px 20px 82px;
			box-sizing: border-box;
			transform: translate(-50%, -50%);
			border: 1px solid rgba(255, 255, 255, 0.16);
			border-radius: 8px;
			background: #111111;
			box-shadow: 0 18px 46px rgba(17, 17, 17, 0.24);
			color: #ffffff;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			font-size: 14px;
			font-weight: 700;
			letter-spacing: 0;
			line-height: 1.35;
			text-align: left;
			pointer-events: none;
			z-index: 100003;
		}

		html.mrn-editor-loading-indicator-live::after {
			content: '';
			position: fixed;
			top: 50%;
			left: calc(50% - min(44vw, 180px) + 42px);
			width: 34px;
			height: 34px;
			margin: -17px 0 0 -17px;
			border-radius: 50%;
			background: conic-gradient(from 90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.36) 36%, #ffffff 72%, rgba(255, 255, 255, 0));
			-webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 4px), #000 calc(100% - 3px));
			mask: radial-gradient(farthest-side, transparent calc(100% - 4px), #000 calc(100% - 3px));
			pointer-events: none;
			z-index: 100004;
			animation: mrnEditorPageLoaderSpin 0.78s linear infinite;
		}

		@media (max-width: 782px) {
			html.mrn-editor-loading-indicator-live::before {
				width: min(90vw, 320px);
				padding: 18px 22px 18px 74px;
				font-size: 13px;
			}

			html.mrn-editor-loading-indicator-live::after {
				left: calc(50% - min(45vw, 160px) + 38px);
				width: 30px;
				height: 30px;
				margin: -15px 0 0 -15px;
			}
		}
	<?php endif; ?>

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
			max-width: 0 !important;
			overflow: hidden;
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

			html.mrn-editor-loading-indicator-live::before {
				animation: none;
			}

			html.mrn-editor-loading-indicator-live::after {
				animation: none;
				background: conic-gradient(from 90deg, rgba(255, 255, 255, 0.18), #ffffff 70%, rgba(255, 255, 255, 0.18));
			}
		}

	<?php if ( mrn_editor_lockdown_is_supported_screen( $screen ) ) : ?>
			.postbox .handle-order-higher,
		.postbox .handle-order-lower {
			display: none !important;
		}
	<?php endif; ?>
	</style>
	<?php if ( $loading_indicator_enabled && ! $loading_mask_enabled ) : ?>
		<script id="mrn-editor-lockdown-loading-indicator-bootstrap">
			(function() {
				var docEl = document.documentElement;
				if (!docEl) {
					return;
				}

				docEl.classList.add('mrn-editor-loading-indicator-live');
			})();
		</script>
	<?php endif; ?>
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
			var loadingMaskEnabled = <?php echo wp_json_encode( mrn_editor_lockdown_is_loading_mask_enabled() ); ?>;
			var loadingIndicatorEnabled = <?php echo wp_json_encode( mrn_editor_lockdown_is_loading_indicator_enabled() ); ?>;
			var loadingMessageStartPhrases = [
				'Preparing editor controls',
				'Loading content fields',
				'Organizing sidebar panels',
				'Checking editor state',
				'Loading publishing tools',
				'Preparing reusable content',
				'Syncing editor settings',
				'Finalizing admin controls'
			];
			var loadingMessageEndPhrases = [
				'for this page',
				'for a smoother edit',
				'before the screen is ready',
				'with the current layout'
			];
			var loadingMessages = [];
			var loadingStartIndex;
			var loadingEndIndex;
			var loadingMaskReadyDelayMs = 1000;
			var loadingIndicatorReadyDelayMs = 120;

			for (loadingStartIndex = 0; loadingStartIndex < loadingMessageStartPhrases.length; loadingStartIndex += 1) {
				for (loadingEndIndex = 0; loadingEndIndex < loadingMessageEndPhrases.length; loadingEndIndex += 1) {
					loadingMessages.push(loadingMessageStartPhrases[loadingStartIndex] + ' ' + loadingMessageEndPhrases[loadingEndIndex]);
				}
			}

			function setLoadingMessage(index) {
				if (!loadingMessageEl || !loadingMessages.length) {
					return;
				}

				loadingMessageEl.textContent = loadingMessages[index % loadingMessages.length];
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

			function startLoadingIndicator() {
				if (!loadingIndicatorEnabled || loadingMaskEnabled) {
					return;
				}

				if (document.documentElement) {
					document.documentElement.classList.add('mrn-editor-loading-indicator-live');
				}
			}

			function stopLoadingIndicator() {
				if (document.documentElement) {
					document.documentElement.classList.remove('mrn-editor-loading-indicator-live');
				}
			}

			function markEditorPageReady() {
				if (!body) {
					return;
				}

				body.classList.add('mrn-editor-page-ready');
				stopLoadingMessageCycle();
				stopLoadingIndicator();

				if (loadingFallbackTimer) {
					window.clearTimeout(loadingFallbackTimer);
					loadingFallbackTimer = null;
				}

				if (loadingReadyTimer) {
					window.clearTimeout(loadingReadyTimer);
					loadingReadyTimer = null;
				}
			}

			function scheduleEditorPageReady(delayMs) {
				if (loadingReadyTimer) {
					window.clearTimeout(loadingReadyTimer);
				}

				loadingReadyTimer = window.setTimeout(markEditorPageReady, delayMs);
			}

			function initEditorLoadingMask() {
				if (!body) {
					return;
				}

				if (!loadingMaskEnabled) {
					if (!loadingIndicatorEnabled) {
						markEditorPageReady();
						return;
					}

					startLoadingIndicator();

					if ('complete' === document.readyState) {
						scheduleEditorPageReady(loadingIndicatorReadyDelayMs);
						return;
					}

					window.addEventListener('load', function() {
						scheduleEditorPageReady(loadingIndicatorReadyDelayMs);
					}, { once: true });
					loadingFallbackTimer = window.setTimeout(markEditorPageReady, 7000);
					return;
				}

				loadingMessageIndex = getRandomLoadingMessageStartIndex();
				startLoadingMessageCycle();

				if ('complete' === document.readyState) {
					scheduleEditorPageReady(loadingMaskReadyDelayMs);
					return;
				}

				window.addEventListener('load', function() {
					scheduleEditorPageReady(loadingMaskReadyDelayMs);
				}, { once: true });
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

/**
 * Normalize a saved Admin Menu Editor item when it represents a core update surface.
 *
 * @param array<string, mixed> $item        AME menu item.
 * @param string               $menu_title  Stable menu title without dynamic badge markup.
 * @param string               $capability  Required capability.
 * @param string               $file        WordPress admin file.
 * @return bool True when the item changed.
 */
function mrn_editor_lockdown_normalize_ame_item( &$item, $menu_title, $capability, $file ) {
	if ( ! is_array( $item ) ) {
		return false;
	}

	$changed = false;

	if ( ! empty( $item['hidden'] ) ) {
		$item['hidden'] = false;
		$changed        = true;
	}

	if ( isset( $item['hidden_from_actor']['role:administrator'] ) ) {
		unset( $item['hidden_from_actor']['role:administrator'] );
		$changed = true;
	}

	if ( empty( $item['grant_access'] ) || ! is_array( $item['grant_access'] ) ) {
		$item['grant_access'] = array();
	}

	if ( ! isset( $item['grant_access']['role:administrator'] ) || true !== $item['grant_access']['role:administrator'] ) {
		$item['grant_access']['role:administrator'] = true;
		$changed                                    = true;
	}

	if ( empty( $item['defaults'] ) || ! is_array( $item['defaults'] ) ) {
		$item['defaults'] = array();
	}

	$defaults = array(
		'menu_title'   => $menu_title,
		'access_level' => $capability,
		'file'         => $file,
	);

	foreach ( $defaults as $key => $value ) {
		if ( ! isset( $item['defaults'][ $key ] ) || $value !== $item['defaults'][ $key ] ) {
			$item['defaults'][ $key ] = $value;
			$changed                  = true;
		}
	}

	return $changed;
}

/**
 * Repair AME configs that imported stale update badges or restricted update screens.
 *
 * @return bool True when an option changed.
 */
function mrn_editor_lockdown_repair_admin_update_menu_config() {
	$changed = false;
	$config  = get_option( 'ws_menu_editor_pro' );

	if ( is_array( $config ) && isset( $config['custom_menu']['tree'] ) && is_array( $config['custom_menu']['tree'] ) ) {
		$tree = &$config['custom_menu']['tree'];

		if ( isset( $tree['index.php']['items'] ) && is_array( $tree['index.php']['items'] ) ) {
			foreach ( $tree['index.php']['items'] as &$item ) {
				$file        = isset( $item['defaults']['file'] ) ? (string) $item['defaults']['file'] : '';
				$template_id = isset( $item['template_id'] ) ? (string) $item['template_id'] : '';

				if ( 'update-core.php' === $file || 'index.php>update-core.php' === $template_id ) {
					$changed = mrn_editor_lockdown_normalize_ame_item( $item, 'Updates', 'update_core', 'update-core.php' ) || $changed;
				}
			}
			unset( $item );
		}

		if ( isset( $tree['plugins.php'] ) && is_array( $tree['plugins.php'] ) ) {
			$changed = mrn_editor_lockdown_normalize_ame_item( $tree['plugins.php'], 'Plugins', 'activate_plugins', 'plugins.php' ) || $changed;
		}

		if ( isset( $tree['themes.php'] ) && is_array( $tree['themes.php'] ) && isset( $tree['themes.php']['items'] ) && is_array( $tree['themes.php']['items'] ) ) {
			foreach ( $tree['themes.php']['items'] as &$item ) {
				$file        = isset( $item['defaults']['file'] ) ? (string) $item['defaults']['file'] : '';
				$template_id = isset( $item['template_id'] ) ? (string) $item['template_id'] : '';

				if ( 'themes.php' === $file || 'themes.php>themes.php' === $template_id ) {
					$changed = mrn_editor_lockdown_normalize_ame_item( $item, 'Themes', 'switch_themes', 'themes.php' ) || $changed;
				}
			}
			unset( $item );
		}

		if ( $changed ) {
			update_option( 'ws_menu_editor_pro', $config, false );
		}
	}

	$toolbar_changed = false;
	$toolbar_config  = get_option( 'ws_abe_admin_bar_settings' );

	if ( is_array( $toolbar_config ) && isset( $toolbar_config['nodes']['updates'] ) ) {
		unset( $toolbar_config['nodes']['updates'] );
		$toolbar_changed = true;
	}

	if ( $toolbar_changed ) {
		update_option( 'ws_abe_admin_bar_settings', $toolbar_config, false );
	}

	return $changed || $toolbar_changed;
}

/**
 * Keep core update screens available even when a stale AME export is imported.
 *
 * @return void
 */
function mrn_editor_lockdown_repair_admin_update_menu_config_on_admin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	mrn_editor_lockdown_repair_admin_update_menu_config();
}
add_action( 'admin_init', 'mrn_editor_lockdown_repair_admin_update_menu_config_on_admin', 1 );

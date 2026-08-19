<?php

namespace MRN_Mega_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	public const VERSION = '0.16.17';
	public const POST_TYPE = 'mrn_mega_menu';
	public const META_LAYOUT = '_mrn_mega_menu_layout';
	public const OPTION_ASSIGNMENTS = 'mrn_mega_menu_assignments';
	public const MENU_META_ENABLED = '_mrn_mega_menu_enabled';
	public const MENU_META_LAYOUT_ID = '_mrn_mega_menu_layout_id';
	public const MENU_META_PARENT_CLICK = '_mrn_mega_menu_parent_click';
	public const ITEM_META_ICON = '_mrn_mega_menu_item_icon';
	public const ITEM_META_ARROW_ICON = '_mrn_mega_menu_arrow_icon';

	/**
	 * Resolve a plugin-relative asset URL without global bootstrap constants.
	 *
	 * @param string $relative_path Path relative to the plugin root.
	 */
	public static function asset_url( $relative_path ) {
		return plugins_url( ltrim( (string) $relative_path, '/' ), dirname( __DIR__ ) . '/mrn-mega-menu.php' );
	}

	/**
	 * Resolve a plugin-relative filesystem path.
	 *
	 * @param string $relative_path Path relative to the plugin root.
	 */
	public static function path( $relative_path = '' ) {
		return trailingslashit( dirname( __DIR__ ) ) . ltrim( (string) $relative_path, '/' );
	}

	/**
	 * Read WordPress' runtime menu-parent property without assuming it exists on
	 * every WP_Post instance supplied by integrations or tests.
	 *
	 * @param mixed $item Potential nav-menu item object.
	 */
	public static function get_menu_item_parent_id( $item ) {
		$properties = is_object( $item ) ? get_object_vars( $item ) : array();

		return absint( $properties['menu_item_parent'] ?? 0 );
	}

	public static function init() {
		Stack_Integration::init();
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_woocommerce_notice' ) );
		add_filter( 'mrn_universal_sticky_bar_post_types', array( __CLASS__, 'add_to_universal_sticky_bar' ) );
		Admin::init();
		Menu_Admin::init();
		Renderer::init();
	}

	/**
	 * Opt the Mega Menu editor into the Universal Sticky Bar when it is available.
	 *
	 * @param array<int, string> $post_types Supported post types.
	 * @return array<int, string>
	 */
	public static function add_to_universal_sticky_bar( $post_types ) {
		if ( ! is_array( $post_types ) ) {
			$post_types = array();
		}

		$post_types[] = self::POST_TYPE;

		return array_values( array_unique( array_filter( $post_types, 'is_string' ) ) );
	}

	public static function activate() {
		self::register_post_type();
	}

	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Mega Layouts', 'mrn-mega-menu' ),
			'singular_name'      => __( 'Mega Layout', 'mrn-mega-menu' ),
			'add_new_item'       => __( 'Build a Mega Layout', 'mrn-mega-menu' ),
			'edit_item'          => __( 'Edit Mega Layout', 'mrn-mega-menu' ),
			'new_item'           => __( 'New Mega Layout', 'mrn-mega-menu' ),
			'search_items'       => __( 'Search Mega Layouts', 'mrn-mega-menu' ),
			'not_found'          => __( 'No mega layouts found.', 'mrn-mega-menu' ),
			'not_found_in_trash' => __( 'No mega layouts found in Trash.', 'mrn-mega-menu' ),
			'menu_name'          => __( 'Mega Layouts', 'mrn-mega-menu' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-screenoptions',
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	public static function render_woocommerce_notice() {
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type || class_exists( 'WooCommerce' ) ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'WooCommerce is not active. Link and promotion blocks will work normally; product and category blocks will appear once WooCommerce is available.', 'mrn-mega-menu' ); ?></p>
		</div>
		<?php
	}

	public static function get_assignments() {
		$value = get_option( self::OPTION_ASSIGNMENTS, array() );
		$assignments = array();
		if ( is_array( $value ) ) {
			foreach ( $value as $item_id => $panel_id ) {
				$item_id  = absint( $item_id );
				$panel_id = absint( $panel_id );
				if ( $item_id && $panel_id ) {
					$assignments[ $item_id ] = $panel_id;
				}
			}
		}

		foreach ( self::get_enabled_menus() as $menu ) {
			$layout_id = self::get_menu_layout_id( $menu->term_id );
			if ( $layout_id && ! self::layout_has_blocks( $layout_id ) ) {
				continue;
			}
			$layout_parents = $layout_id ? self::get_layout_parent_ids( $layout_id, $menu->term_id ) : array();
			$items     = wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
			if ( empty( $items ) || is_wp_error( $items ) ) {
				continue;
			}

			$parents = array();
			foreach ( $items as $item ) {
				$parent_id = self::get_menu_item_parent_id( $item );
				if ( $parent_id ) {
					$parents[ $parent_id ] = true;
				}
			}
			foreach ( $items as $item ) {
				$item_id = absint( $item->ID );
				$has_native_children = isset( $parents[ $item_id ] );
				$has_layout_panel    = $layout_id && in_array( $item_id, $layout_parents, true );
				if ( 0 === self::get_menu_item_parent_id( $item ) && ( $has_native_children || $has_layout_panel ) && ( ! $layout_id || $has_layout_panel ) ) {
					$assignments[ $item_id ] = $layout_id;
				}
			}
		}

		return $assignments;
	}

	/**
	 * Return nav menus that have mega behavior enabled.
	 *
	 * @return array<int, \WP_Term>
	 */
	public static function get_enabled_menus() {
		$menus = get_terms(
			array(
				'taxonomy'   => 'nav_menu',
				'hide_empty' => false,
				'meta_key'   => self::MENU_META_ENABLED,
				'meta_value' => '1',
			)
		);

		return is_array( $menus ) && ! is_wp_error( $menus ) ? $menus : array();
	}

	public static function is_menu_mega( $menu_id ) {
		return '1' === (string) get_term_meta( absint( $menu_id ), self::MENU_META_ENABLED, true );
	}

	public static function get_menu_layout_id( $menu_id ) {
		$layout_id = absint( get_term_meta( absint( $menu_id ), self::MENU_META_LAYOUT_ID, true ) );
		return self::POST_TYPE === get_post_type( $layout_id ) && 'publish' === get_post_status( $layout_id ) ? $layout_id : 0;
	}

	/**
	 * Sanitize a menu-level parent click behavior.
	 *
	 * Missing and unknown values intentionally resolve to toggle so existing
	 * menus keep the pre-0.16.13 behavior.
	 *
	 * @param mixed $value Raw setting value.
	 * @return string Either toggle or link.
	 */
	public static function sanitize_parent_click( $value ) {
		return 'link' === sanitize_key( is_scalar( $value ) ? (string) $value : '' ) ? 'link' : 'toggle';
	}

	/**
	 * Sanitize a panel-level parent click override.
	 *
	 * @param mixed $value Raw setting value.
	 * @return string Either inherit, toggle, or link.
	 */
	public static function sanitize_parent_click_override( $value ) {
		$value = sanitize_key( is_scalar( $value ) ? (string) $value : '' );
		return in_array( $value, array( 'toggle', 'link' ), true ) ? $value : 'inherit';
	}

	/**
	 * Sanitize a panel-level display mode.
	 *
	 * @param mixed $value Raw setting value.
	 * @return string Either mega or dropdown.
	 */
	public static function sanitize_display_mode( $value ) {
		$value = sanitize_key( is_scalar( $value ) ? (string) $value : '' );
		return 'dropdown' === $value ? 'dropdown' : 'mega';
	}

	/**
	 * Return the configured default for a WordPress menu.
	 *
	 * @param int $menu_id WordPress nav menu term ID.
	 * @return string Either toggle or link.
	 */
	public static function get_menu_parent_click( $menu_id ) {
		return self::sanitize_parent_click( get_term_meta( absint( $menu_id ), self::MENU_META_PARENT_CLICK, true ) );
	}

	/**
	 * Resolve one assigned top-level item's parent click behavior.
	 *
	 * @param int                  $item_id Menu item post ID.
	 * @param array<string, mixed> $panel   Resolved panel configuration.
	 * @return string Either toggle or link.
	 */
	public static function resolve_parent_click( $item_id, $panel ) {
		$override = self::sanitize_parent_click_override( $panel['parent_click'] ?? 'inherit' );
		if ( 'inherit' !== $override ) {
			return $override;
		}

		$menu_ids = wp_get_object_terms( absint( $item_id ), 'nav_menu', array( 'fields' => 'ids' ) );
		$menu_id  = ! is_wp_error( $menu_ids ) && ! empty( $menu_ids ) ? absint( reset( $menu_ids ) ) : 0;
		return $menu_id ? self::get_menu_parent_click( $menu_id ) : 'toggle';
	}

	/**
	 * Whether a native menu item URL is a usable navigation destination.
	 *
	 * @param mixed $url Native WordPress menu item URL.
	 * @return bool
	 */
	public static function has_usable_parent_url( $url ) {
		$url = is_scalar( $url ) ? trim( (string) $url ) : '';
		return '' !== $url && '#' !== $url;
	}

	/**
	 * Sanitize a shared-icon-chooser value for storage and front-end output.
	 *
	 * @param mixed $icon Raw icon value.
	 * @return array{type:string,value:string}
	 */
	public static function sanitize_icon( $icon ) {
		if ( is_string( $icon ) ) {
			$decoded = json_decode( $icon, true );
			$icon    = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $icon ) ) {
			return array( 'type' => '', 'value' => '' );
		}

		$type  = isset( $icon['type'] ) ? sanitize_key( $icon['type'] ) : '';
		$value = isset( $icon['value'] ) && is_scalar( $icon['value'] ) ? trim( (string) $icon['value'] ) : '';
		if ( 'media' === $type ) {
			$value = esc_url_raw( $value );
		} elseif ( in_array( $type, array( 'dashicons', 'fontawesome' ), true ) ) {
			$tokens = preg_split( '/\s+/', strtolower( $value ) );
			$tokens = array_filter(
				array_map(
					static function ( $token ) {
						return sanitize_html_class( $token );
					},
					is_array( $tokens ) ? $tokens : array()
				)
			);
			$value = implode( ' ', array_unique( $tokens ) );
		} else {
			$type  = '';
			$value = '';
		}

		return array( 'type' => $value ? $type : '', 'value' => $value );
	}

	/**
	 * Get one menu item's configured icon.
	 *
	 * @param int    $item_id Menu item post ID.
	 * @param string $role    Either item or arrow.
	 * @return array{type:string,value:string}
	 */
	public static function get_menu_item_icon( $item_id, $role = 'item' ) {
		$key = 'arrow' === $role ? self::ITEM_META_ARROW_ICON : self::ITEM_META_ICON;
		return self::sanitize_icon( get_post_meta( absint( $item_id ), $key, true ) );
	}

	public static function get_layout( $post_id ) {
		if ( ! absint( $post_id ) ) {
			return self::normalize_layout(
				array(
				'width'   => 'full',
				'columns' => array(
					array(
						'blocks' => array(
							array(
								'type'        => 'menu',
								'title'       => '',
								'source'      => 'assigned_children',
								'branch_mode' => 'children_only',
							),
						),
					),
				),
				)
			);
		}
		$layout = get_post_meta( absint( $post_id ), self::META_LAYOUT, true );
		return self::normalize_layout( is_array( $layout ) ? $layout : Admin::default_layout() );
	}

	/**
	 * Normalize legacy flat columns into parent-item mega-menu panels.
	 *
	 * Older layouts treated each top-level array entry as both the menu-item
	 * panel and its only visual column. The normalized shape separates those
	 * concepts without changing or discarding any blocks.
	 *
	 * @param array<string, mixed> $layout Saved layout data.
	 * @return array<string, mixed>
	 */
	public static function normalize_layout( $layout ) {
		$normalized = array(
			'width'  => isset( $layout['width'] ) && 'full' === $layout['width'] ? 'full' : 'content',
			'panels' => array(),
		);

		if ( isset( $layout['panels'] ) && is_array( $layout['panels'] ) ) {
			foreach ( $layout['panels'] as $panel ) {
				$columns = isset( $panel['columns'] ) && is_array( $panel['columns'] ) ? $panel['columns'] : array();
				$normalized['panels'][] = array(
					'menu_item_id'  => absint( $panel['menu_item_id'] ?? 0 ),
					'label_override' => sanitize_text_field( $panel['label_override'] ?? '' ),
					'display_mode'  => self::sanitize_display_mode( $panel['display_mode'] ?? 'mega' ),
					'parent_click'  => self::sanitize_parent_click_override( $panel['parent_click'] ?? 'inherit' ),
					'item_icon'     => self::sanitize_icon( $panel['item_icon'] ?? array() ),
					'arrow_icon'    => self::sanitize_icon( $panel['arrow_icon'] ?? array() ),
					'child_arrow_icon' => self::sanitize_icon( $panel['child_arrow_icon'] ?? ( $panel['child_icon'] ?? array() ) ),
					'columns'       => $columns ? $columns : array( array( 'blocks' => array() ) ),
				);
			}
		} else {
			$legacy_columns = isset( $layout['columns'] ) && is_array( $layout['columns'] ) ? $layout['columns'] : array();
			foreach ( $legacy_columns as $column ) {
				$blocks       = isset( $column['blocks'] ) && is_array( $column['blocks'] ) ? $column['blocks'] : array();
				$menu_item_id = 0;
				foreach ( $blocks as $block ) {
					if ( 'menu' === ( $block['type'] ?? '' ) && 'selected' === ( $block['source'] ?? '' ) && absint( $block['root_item_id'] ?? 0 ) ) {
						$menu_item_id = absint( $block['root_item_id'] );
						break;
					}
				}
				$normalized['panels'][] = array(
					'menu_item_id'  => $menu_item_id,
					'label_override' => '',
					'display_mode'  => 'mega',
					'parent_click'  => 'inherit',
					'item_icon'     => array( 'type' => '', 'value' => '' ),
					'arrow_icon'    => array( 'type' => '', 'value' => '' ),
					'child_arrow_icon' => array( 'type' => '', 'value' => '' ),
					'columns'       => array( array( 'blocks' => $blocks ) ),
				);
			}
		}

		if ( empty( $normalized['panels'] ) ) {
			$normalized['panels'][] = array(
				'menu_item_id'  => 0,
				'label_override' => '',
				'display_mode'  => 'mega',
				'parent_click'  => 'inherit',
				'item_icon'     => array( 'type' => '', 'value' => '' ),
				'arrow_icon'    => array( 'type' => '', 'value' => '' ),
				'child_arrow_icon' => array( 'type' => '', 'value' => '' ),
				'columns'       => array( array( 'blocks' => array() ) ),
			);
		}

		return $normalized;
	}

	/**
	 * Determine whether a saved reusable layout contains renderable content.
	 *
	 * Automatic mode uses layout ID 0 and is intentionally handled separately.
	 * An empty reusable layout must not take ownership of native submenu children.
	 *
	 * @param int $post_id Mega Layout post ID.
	 * @return bool
	 */
	public static function layout_has_blocks( $post_id ) {
		$layout = self::get_layout( $post_id );
		foreach ( $layout['panels'] as $panel ) {
			foreach ( isset( $panel['columns'] ) && is_array( $panel['columns'] ) ? $panel['columns'] : array() as $column ) {
				if ( ! empty( $column['blocks'] ) && is_array( $column['blocks'] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Return the ordered top-level menu branches represented by layout columns.
	 *
	 * @param int $layout_id Mega Layout post ID.
	 * @param int $menu_id   WordPress nav menu ID.
	 * @return array<int, int>
	 */
	public static function get_layout_parent_ids( $layout_id, $menu_id ) {
		$parent_ids = array();
		$layout     = self::get_layout( $layout_id );
		foreach ( $layout['panels'] as $panel ) {
			$panel_parent_id = absint( $panel['menu_item_id'] ?? 0 );
			if ( $panel_parent_id && ! in_array( $panel_parent_id, $parent_ids, true ) ) {
				$parent_ids[] = $panel_parent_id;
			}
			foreach ( isset( $panel['columns'] ) && is_array( $panel['columns'] ) ? $panel['columns'] : array() as $column ) {
				foreach ( isset( $column['blocks'] ) && is_array( $column['blocks'] ) ? $column['blocks'] : array() as $block ) {
				if ( 'menu' !== ( $block['type'] ?? '' ) || 'selected' !== ( $block['source'] ?? '' ) || absint( $block['menu_id'] ?? 0 ) !== absint( $menu_id ) ) {
					continue;
				}
				$parent_id = absint( $block['root_item_id'] ?? 0 );
				if ( $parent_id && ! in_array( $parent_id, $parent_ids, true ) ) {
					$parent_ids[] = $parent_id;
				}
				}
			}
		}
		return $parent_ids;
	}
}

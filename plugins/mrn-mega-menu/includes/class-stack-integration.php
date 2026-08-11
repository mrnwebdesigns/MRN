<?php

namespace MRN_Mega_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps optional MRN stack behavior behind one portable integration boundary.
 */
final class Stack_Integration {
	/**
	 * Register integration hooks without requiring any stack component.
	 */
	public static function init() {
		add_filter( 'mrn_admin_data_post_types', array( __CLASS__, 'register_admin_data_post_type' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
		add_filter( 'acf/load_field/key=field_mrn_theme_header_primary_menu_id', array( __CLASS__, 'label_header_menu_choices' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_stack_styles' ), 20 );
	}

	/**
	 * Label mega-enabled WordPress menus in the stack header selector.
	 *
	 * @param array $field ACF field definition.
	 * @return array
	 */
	public static function label_header_menu_choices( $field ) {
		if ( empty( $field['choices'] ) || ! is_array( $field['choices'] ) ) {
			return $field;
		}

		foreach ( $field['choices'] as $menu_id => $label ) {
			if ( absint( $menu_id ) && Plugin::is_menu_mega( $menu_id ) && false === strpos( (string) $label, '— Mega menu' ) ) {
				$field['choices'][ $menu_id ] = sprintf( __( '%s — Mega menu', 'mrn-mega-menu' ), $label );
			}
		}
		$field['instructions'] = __( 'Choose any WordPress menu. Menus labeled “Mega menu” open their parent items as mega panels and work anywhere wp_nav_menu() is used.', 'mrn-mega-menu' );

		return $field;
	}

	/**
	 * Detect the stack through public runtime contracts, not paths or site names.
	 */
	public static function is_stack_available() {
		$available = function_exists( 'mrn_site_colors_get_all' )
			|| function_exists( 'mrn_site_styles_get_graphic_elements' )
			|| function_exists( 'mrn_base_stack_get_singular_shell_post_types' )
			|| function_exists( 'mrn_admin_data_post_types_get_config' );

		/**
		 * Override stack detection for custom distributions and test environments.
		 *
		 * @param bool $available Whether an MRN stack contract was detected.
		 */
		return (bool) apply_filters( 'mrn_mega_menu_stack_detected', $available );
	}

	/**
	 * Return individually detected contracts so features never assume one another.
	 */
	public static function get_capabilities() {
		$capabilities = array(
			'admin_data_post_types' => function_exists( 'mrn_admin_data_post_types_get_config' ),
			'admin_ui_contract'      => function_exists( 'mrn_admin_ui_contract_version' ) && function_exists( 'mrn_admin_ui_contract_get' ),
			'site_colors'           => function_exists( 'mrn_site_colors_get_all' ) && function_exists( 'mrn_site_colors_get_css_var' ),
			'shell_widths'          => function_exists( 'mrn_site_styles_get_row_width_values' ) || function_exists( 'mrn_base_stack_get_singular_shell_post_types' ),
			'tokens'                => function_exists( 'mrn_tokens_get' ),
			'shared_assets'         => function_exists( 'mrn_shared_assets_get_fontawesome_icons' ),
			'admin_layout_builder'  => function_exists( 'mrn_shared_assets_enqueue_admin_layout_builder' ),
			'business_information'  => function_exists( 'mrn_base_stack_get_business_information' ),
			'reusable_blocks'       => function_exists( 'mrn_rbl_get_post_types' ) && function_exists( 'mrn_rbl_get_render_context' ) && function_exists( 'mrn_rbl_render_context' ),
		);

		/**
		 * Filter the available stack contracts for custom stack distributions.
		 *
		 * @param array<string, bool> $capabilities Detected capability map.
		 */
		$capabilities = apply_filters( 'mrn_mega_menu_stack_capabilities', $capabilities );

		return is_array( $capabilities ) ? array_map( 'boolval', $capabilities ) : array();
	}

	/**
	 * Provide a stable status payload for diagnostics and editor UI.
	 */
	public static function get_status() {
		$stack        = self::is_stack_available();
		$capabilities = self::get_capabilities();

		return array(
			'mode'         => $stack ? 'stack' : 'standalone',
			'connected'    => $stack,
			'capabilities' => $capabilities,
		);
	}

	/**
	 * Adopt the stack's non-public content-record behavior when it exists.
	 *
	 * @param array $post_types Existing post-type requests.
	 * @return array
	 */
	public static function register_admin_data_post_type( $post_types ) {
		$post_types = is_array( $post_types ) ? $post_types : array();
		if ( ! self::is_stack_available() ) {
			return $post_types;
		}

		$post_types[ Plugin::POST_TYPE ] = array(
			'show_ui'       => true,
			'show_in_menu'  => false,
			'admin_cleanup' => true,
		);

		return $post_types;
	}

	/**
	 * Expose runtime mode to themes without changing standalone markup structure.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public static function body_classes( $classes ) {
		if ( self::is_stack_available() ) {
			$classes[] = 'mrn-mega-menu-stack-connected';
		}
		return $classes;
	}

	/**
	 * Resolve explicit MRN Token placeholders in human-readable menu copy.
	 *
	 * Syntax: {token:phone}, {token:site_name}, or any registered token key.
	 * Unavailable and empty tokens remain visible so configuration errors are not
	 * silently converted into blank menu labels.
	 *
	 * @param string $text Stored menu copy.
	 * @return string
	 */
	public static function resolve_text( $text ) {
		$text = (string) $text;
		if ( ! function_exists( 'mrn_tokens_get' ) || false === strpos( $text, '{token:' ) ) {
			return $text;
		}

		$resolved = preg_replace_callback(
			'/\{token:([a-z0-9_-]+)\}/i',
			static function ( $matches ) {
				$token = mrn_tokens_get( sanitize_key( $matches[1] ) );
				if ( ! is_array( $token ) || ! isset( $token['value'] ) || ! is_scalar( $token['value'] ) ) {
					return $matches[0];
				}
				$value = sanitize_text_field( (string) $token['value'] );
				return '' !== $value ? $value : $matches[0];
			},
			$text
		);

		/**
		 * Filter resolved menu copy after optional MRN Token replacement.
		 *
		 * @param string $resolved Resolved copy.
		 * @param string $text     Original stored copy.
		 */
		return (string) apply_filters( 'mrn_mega_menu_resolve_text', is_string( $resolved ) ? $resolved : $text, $text );
	}

	/**
	 * Return published blocks from the optional Reusable Block Library.
	 *
	 * @return array<int, \WP_Post>
	 */
	public static function get_reusable_blocks() {
		static $blocks = null;
		if ( is_array( $blocks ) ) {
			return $blocks;
		}

		$capabilities = self::get_capabilities();
		if ( empty( $capabilities['reusable_blocks'] ) ) {
			$blocks = array();
			return $blocks;
		}

		$post_types = array_filter( array_map( 'sanitize_key', (array) mrn_rbl_get_post_types() ) );
		if ( empty( $post_types ) ) {
			$blocks = array();
			return $blocks;
		}

		$blocks = get_posts(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return $blocks;
	}

	/**
	 * Build an editor label that distinguishes similarly named block types.
	 *
	 * @param \WP_Post $post Reusable block post.
	 * @return string
	 */
	public static function get_reusable_block_label( $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$title     = '' !== trim( (string) $post->post_title ) ? $post->post_title : sprintf( __( 'Reusable block #%d', 'mrn-mega-menu' ), $post->ID );
		$type      = get_post_type_object( $post->post_type );
		$type_name = $type && isset( $type->labels->singular_name ) ? $type->labels->singular_name : '';

		return $type_name ? sprintf( __( '%1$s — %2$s', 'mrn-mega-menu' ), $title, $type_name ) : $title;
	}

	/**
	 * Render one published library block directly inside a mega-menu panel.
	 *
	 * @param int $post_id Reusable block post ID.
	 * @return string
	 */
	public static function render_reusable_block( $post_id ) {
		$capabilities = self::get_capabilities();
		$post         = get_post( absint( $post_id ) );
		if ( empty( $capabilities['reusable_blocks'] ) || ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
			return '';
		}

		$post_types = array_map( 'sanitize_key', (array) mrn_rbl_get_post_types() );
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return '';
		}

		static $render_index = 0;
		++$render_index;
		$context = mrn_rbl_get_render_context(
			$post,
			array(
				'host_post_id'   => get_queried_object_id(),
				'host_row_index' => $render_index,
				'suppress_anchor' => true,
			)
		);

		return mrn_rbl_render_context( $context );
	}

	/**
	 * Layer stack design tokens over portable plugin defaults.
	 */
	public static function enqueue_stack_styles() {
		if ( ! self::is_stack_available() || ! wp_style_is( 'mrn-mega-menu', 'enqueued' ) ) {
			return;
		}

		$css = '.mrn-mega-menu--stack.mrn-mega-menu--content{width:min(calc(100vw - (2 * var(--mrn-shell-gutter,16px))),var(--mrn-shell-wide-width,1200px));}'
			. '.mrn-mega-menu--stack.mrn-mega-menu--full{width:100vw;max-width:none;}'
			. '.mrn-mega-menu--stack.mrn-mega-menu--full .mrn-mega-menu__columns{max-width:none;}'
			. '.mrn-mega-menu--stack .mrn-mega-menu__surface{background:var(--site-color-white,#fff);color:var(--site-color-black,#172033);}'
			. '.mrn-mega-menu--stack .mrn-mega-menu__heading{color:var(--site-color-black,#172033);}'
			. '.mrn-mega-menu--stack .mrn-mega-menu__promo-link{color:var(--site-color-primary,var(--site-color-accent,#075985))!important;}';

		wp_add_inline_style( 'mrn-mega-menu', $css );
	}
}

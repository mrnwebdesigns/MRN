<?php
/**
 * MRN Base Stack functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package mrn-base-stack
 */

if ( ! defined( '_S_VERSION' ) ) {
	// Replace the version number of the theme on each release.
	define( '_S_VERSION', '1.1.35' );
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function mrn_base_stack_setup() {
	/*
		* Make theme available for translation.
		* Translations can be filed in the /languages/ directory.
		* If you're building a theme based on mrn-base-stack, use a find and replace
		* to change 'mrn-base-stack' to the name of your theme in all the template files.
		*/
	load_theme_textdomain( 'mrn-base-stack', get_template_directory() . '/languages' );

	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	/*
		* Let WordPress manage the document title.
		* By adding theme support, we declare that this theme does not use a
		* hard-coded <title> tag in the document head, and expect WordPress to
		* provide it for us.
		*/
	add_theme_support( 'title-tag' );

	/*
		* Enable support for Post Thumbnails on posts and pages.
		*
		* @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		*/
	add_theme_support( 'post-thumbnails' );

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus(
		array(
			'menu-1'           => esc_html__( 'Primary', 'mrn-base-stack' ),
			'menu-2'           => esc_html__( 'Utility (Legacy)', 'mrn-base-stack' ),
			'menu-3'           => esc_html__( 'Footer Primary', 'mrn-base-stack' ),
			'menu-4'           => esc_html__( 'Legal (Legacy)', 'mrn-base-stack' ),
			'header-secondary' => esc_html__( 'Header Secondary', 'mrn-base-stack' ),
			'header-tertiary'  => esc_html__( 'Header Tertiary', 'mrn-base-stack' ),
			'footer-secondary' => esc_html__( 'Footer Secondary', 'mrn-base-stack' ),
			'footer-tertiary'  => esc_html__( 'Footer Tertiary', 'mrn-base-stack' ),
			'social-media'     => esc_html__( 'Social Media', 'mrn-base-stack' ),
		)
	);

	/*
		* Switch default core markup for search form, comment form, and comments
		* to output valid HTML5.
		*/
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	// Set up the WordPress core custom background feature.
	add_theme_support(
		'custom-background',
		apply_filters(
			'mrn_base_stack_custom_background_args',
			array(
				'default-color' => 'ffffff',
				'default-image' => '',
			)
		)
	);

	// Add theme support for selective refresh for widgets.
	add_theme_support( 'customize-selective-refresh-widgets' );

	/**
	 * Add support for core custom logo.
	 *
	 * @link https://codex.wordpress.org/Theme_Logo
	 */
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 250,
			'width'       => 250,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);
}
add_action( 'after_setup_theme', 'mrn_base_stack_setup' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function mrn_base_stack_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'mrn_base_stack_content_width', 640 );
}
add_action( 'after_setup_theme', 'mrn_base_stack_content_width', 0 );

/**
 * Get the theme-owned post types hidden from the back-end UI.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_hidden_admin_cpts() {
	$post_types = function_exists( 'mrn_config_helper_get_hidden_admin_cpts' ) ? mrn_config_helper_get_hidden_admin_cpts() : array();

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
 * Determine whether a theme-owned post type should appear in the WordPress admin UI.
 *
 * @param string $post_type Post type slug.
 * @return bool
 */
function mrn_base_stack_is_admin_cpt_visible( $post_type ) {
	return ! in_array( sanitize_key( (string) $post_type ), mrn_base_stack_get_hidden_admin_cpts(), true );
}

/**
 * Get theme-owned editorial custom post types.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_editorial_cpts() {
	$post_types = array( 'gallery', 'testimonial', 'case_study' );

	/**
	 * Filter the theme-owned editorial CPT slugs.
	 *
	 * @param array<int, string> $post_types Supported CPTs.
	 */
	$post_types = apply_filters( 'mrn_base_stack_editorial_cpts', $post_types );

	if ( ! is_array( $post_types ) ) {
		return array( 'gallery', 'testimonial', 'case_study' );
	}

	$post_types = array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $post_types )
			)
		)
	);

	return ! empty( $post_types ) ? $post_types : array( 'gallery', 'testimonial', 'case_study' );
}

/**
 * Get builder layouts hidden from the editor add-row menus.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_hidden_builder_layouts() {
	$layouts = function_exists( 'mrn_config_helper_get_hidden_builder_layouts' ) ? mrn_config_helper_get_hidden_builder_layouts() : array();

	if ( ! is_array( $layouts ) ) {
		return array();
	}

	return array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $layouts )
			)
		)
	);
}

/**
 * Get the singular post types that use the theme's builder-style shell.
 *
 * @param array<int, string> $post_types Post type slugs.
 * @return array<int, string>
 */
function mrn_base_stack_build_post_type_location_rules( array $post_types ) {
	$locations = array();

	foreach ( $post_types as $post_type ) {
		$post_type = sanitize_key( (string) $post_type );
		if ( '' === $post_type ) {
			continue;
		}

		$locations[] = array(
			array(
				'param'    => 'post_type',
				'operator' => '==',
				'value'    => $post_type,
			),
		);
	}

	return $locations;
}

/**
 * Get the singular post types that use the theme's generic content builder.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_supported_post_types() {
	$post_types = array( 'page', 'post' );

	/**
	 * Filter the post types that should receive the theme builder experience.
	 *
	 * @param array<int, string> $post_types Supported post types.
	 */
	$post_types = apply_filters( 'mrn_base_stack_builder_supported_post_types', $post_types );

	if ( ! is_array( $post_types ) ) {
		return array( 'page', 'post' );
	}

	$post_types = array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $post_types )
			)
		)
	);

	return ! empty( $post_types ) ? $post_types : array( 'page', 'post' );
}

/**
 * Get the post types that should expose the shared hero field group.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_hero_supported_post_types() {
	$post_types = array_merge( mrn_base_stack_get_builder_supported_post_types(), mrn_base_stack_get_editorial_cpts() );

	/**
	 * Filter the post types that should receive the theme hero experience.
	 *
	 * @param array<int, string> $post_types Supported post types.
	 */
	$post_types = apply_filters( 'mrn_base_stack_hero_supported_post_types', $post_types );

	if ( ! is_array( $post_types ) ) {
		return array_merge( array( 'page', 'post' ), mrn_base_stack_get_editorial_cpts() );
	}

	$post_types = array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $post_types )
			)
		)
	);

	return ! empty( $post_types ) ? $post_types : array_merge( array( 'page', 'post' ), mrn_base_stack_get_editorial_cpts() );
}

/**
 * Get the post types that should expose the shared after-content field group.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_after_content_supported_post_types() {
	$post_types = array_merge( mrn_base_stack_get_builder_supported_post_types(), mrn_base_stack_get_editorial_cpts() );

	/**
	 * Filter the post types that should receive the after-content builder.
	 *
	 * @param array<int, string> $post_types Supported post types.
	 */
	$post_types = apply_filters( 'mrn_base_stack_after_content_supported_post_types', $post_types );

	if ( ! is_array( $post_types ) ) {
		return array_merge( array( 'page', 'post' ), mrn_base_stack_get_editorial_cpts() );
	}

	$post_types = array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $post_types )
			)
		)
	);

	return ! empty( $post_types ) ? $post_types : array_merge( array( 'page', 'post' ), mrn_base_stack_get_editorial_cpts() );
}

/**
 * Get the singular post types that should load shared shell assets and admin helpers.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_singular_shell_post_types() {
	$post_types = array_merge(
		mrn_base_stack_get_builder_supported_post_types(),
		mrn_base_stack_get_hero_supported_post_types(),
		mrn_base_stack_get_after_content_supported_post_types(),
		mrn_base_stack_get_editorial_cpts()
	);

	/**
	 * Filter the singular post types that should load shared shell assets.
	 *
	 * @param array<int, string> $post_types Supported post types.
	 */
	$post_types = apply_filters( 'mrn_base_stack_singular_shell_post_types', $post_types );

	if ( ! is_array( $post_types ) ) {
		return array_merge( array( 'page', 'post' ), mrn_base_stack_get_editorial_cpts() );
	}

	$post_types = array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $post_types )
			)
		)
	);

	return ! empty( $post_types ) ? $post_types : array_merge( array( 'page', 'post' ), mrn_base_stack_get_editorial_cpts() );
}

/**
 * Determine whether a post type uses the theme's builder-style singular shell.
 *
 * @param string $post_type Post type slug.
 * @return bool
 */
function mrn_base_stack_is_builder_supported_post_type( $post_type ) {
	return in_array( sanitize_key( (string) $post_type ), mrn_base_stack_get_builder_supported_post_types(), true );
}

/**
 * Build ACF location rules for builder-supported post types.
 *
 * @return array<int, array<int, array<string, string>>>
 */
function mrn_base_stack_get_builder_location_rules() {
	return mrn_base_stack_build_post_type_location_rules( mrn_base_stack_get_builder_supported_post_types() );
}

/**
 * Build ACF location rules for hero-supported post types.
 *
 * @return array<int, array<int, array<string, string>>>
 */
function mrn_base_stack_get_hero_location_rules() {
	return mrn_base_stack_build_post_type_location_rules( mrn_base_stack_get_hero_supported_post_types() );
}

/**
 * Build ACF location rules for after-content-supported post types.
 *
 * @return array<int, array<int, array<string, string>>>
 */
function mrn_base_stack_get_after_content_location_rules() {
	return mrn_base_stack_build_post_type_location_rules( mrn_base_stack_get_after_content_supported_post_types() );
}

/**
 * Determine whether the layout-builder runtime should load.
 *
 * This rollback track keeps layout-builder functionality fully disabled.
 *
 * @return bool
 */
function mrn_base_stack_is_layout_builder_enabled() {
	return false;
}

/**
 * Opt theme-owned editorial CPTs into the universal sticky bar plugin.
 *
 * @param array<int, string> $post_types Supported sticky-bar post types.
 * @return array<int, string>
 */
function mrn_base_stack_add_editorial_cpts_to_universal_sticky_bar( $post_types ) {
	if ( ! is_array( $post_types ) ) {
		$post_types = array();
	}

	$post_types = array_merge( $post_types, mrn_base_stack_get_editorial_cpts() );

	return array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $post_types )
			)
		)
	);
}
add_filter( 'mrn_universal_sticky_bar_post_types', 'mrn_base_stack_add_editorial_cpts_to_universal_sticky_bar' );

/**
 * Enqueue shared ACF repeater admin controls anywhere repeaters render.
 *
 * @return void
 */
function mrn_base_stack_enqueue_shared_repeater_admin_assets() {
	$repeater_controls_path = get_template_directory() . '/js/admin-repeater-controls.js';
	$repeater_controls_ver  = file_exists( $repeater_controls_path ) ? (string) filemtime( $repeater_controls_path ) : _S_VERSION;
	$repeater_styles_path   = get_template_directory() . '/css/admin-repeater-controls.css';
	$repeater_styles_ver    = file_exists( $repeater_styles_path ) ? (string) filemtime( $repeater_styles_path ) : _S_VERSION;

	wp_enqueue_style(
		'mrn-base-stack-admin-repeater-controls',
		get_template_directory_uri() . '/css/admin-repeater-controls.css',
		array(),
		$repeater_styles_ver
	);

	wp_enqueue_script(
		'mrn-base-stack-admin-repeater-controls',
		get_template_directory_uri() . '/js/admin-repeater-controls.js',
		array( 'jquery', 'acf-input' ),
		$repeater_controls_ver,
		true
	);

	if ( function_exists( 'mrn_shared_assets_enqueue_admin_icon_chooser' ) ) {
		mrn_shared_assets_enqueue_admin_icon_chooser( 'mrn-shared-icon-chooser', 'mrn-shared-icon-chooser' );
	}

	wp_enqueue_script(
		'mrn-base-stack-admin-icon-choosers',
		get_template_directory_uri() . '/js/admin-icon-choosers.js',
		array( 'jquery', 'acf-input', 'mrn-shared-icon-chooser' ),
		_S_VERSION,
		true
	);

	wp_enqueue_style(
		'mrn-base-stack-admin-icon-choosers',
		get_template_directory_uri() . '/css/admin-icon-choosers.css',
		array( 'mrn-shared-icon-chooser' ),
		_S_VERSION
	);
}
add_action( 'acf/input/admin_enqueue_scripts', 'mrn_base_stack_enqueue_shared_repeater_admin_assets' );

/**
 * Enqueue gallery-specific editor behavior on gallery edit screens.
 *
 * @param string $hook_suffix Current admin page hook.
 * @return void
 */
function mrn_base_stack_enqueue_gallery_admin_assets( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'gallery' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_script(
		'mrn-base-stack-gallery-admin',
		get_template_directory_uri() . '/js/admin-gallery.js',
		array( 'jquery', 'acf-input' ),
		_S_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'mrn_base_stack_enqueue_gallery_admin_assets' );

/**
 * Enqueue Motion inView assets for front-end effects.
 */
function mrn_base_stack_enqueue_motion_assets() {
	wp_enqueue_script(
		'mrn-base-stack-motion',
		get_template_directory_uri() . '/js/vendor/motion.js',
		array(),
		'12.38.0',
		true
	);

	wp_enqueue_script(
		'mrn-base-stack-front-end-effects',
		get_template_directory_uri() . '/js/front-end-effects.js',
		array( 'mrn-base-stack-motion' ),
		_S_VERSION,
		true
	);
}

/**
 * Enqueue scripts and styles.
 */
function mrn_base_stack_scripts() {
	wp_enqueue_style( 'mrn-base-stack-style', get_stylesheet_uri(), array(), _S_VERSION );
	wp_style_add_data( 'mrn-base-stack-style', 'rtl', 'replace' );

	$layout_builder_enabled = mrn_base_stack_is_layout_builder_enabled();
	$header_options         = function_exists( 'mrn_base_stack_get_theme_header_footer_options' ) ? mrn_base_stack_get_theme_header_footer_options() : array();
	$needs_fontawesome      = false;
	$needs_dashicons        = false;
	$uses_icon_search       = ! empty( $header_options['header_show_search'] ) && isset( $header_options['header_search_style'] ) && 'icon_only' === $header_options['header_search_style'];
	$search_icon_source     = isset( $header_options['header_search_icon_source'] ) ? (string) $header_options['header_search_icon_source'] : 'dashicons';

	if ( 'fontawesome' === $search_icon_source && $uses_icon_search ) {
		$needs_fontawesome = true;
	}

	if ( ( 'dashicons' === $search_icon_source || 'standard' === $search_icon_source ) && $uses_icon_search ) {
		$needs_dashicons = true;
	}

	if ( function_exists( 'mrn_config_helper_get_social_links' ) ) {
		$social_links = mrn_config_helper_get_social_links();

		if ( is_array( $social_links ) ) {
			foreach ( $social_links as $social_link ) {
				if ( ! is_array( $social_link ) || ! isset( $social_link['icon_type'] ) ) {
					continue;
				}

				if ( 'fontawesome' === $social_link['icon_type'] ) {
					$needs_fontawesome = true;
				}

				if ( 'dashicons' === $social_link['icon_type'] ) {
					$needs_dashicons = true;
				}

				if ( $needs_fontawesome && $needs_dashicons ) {
					break;
				}
			}
		}
	}

	if ( function_exists( 'mrn_config_helper_get_breadcrumb_settings' ) ) {
		$breadcrumb_settings = mrn_config_helper_get_breadcrumb_settings();

		if (
			is_array( $breadcrumb_settings )
			&& ! empty( $breadcrumb_settings['enabled'] )
			&& isset( $breadcrumb_settings['separator_type'] )
			&& 'fontawesome' === sanitize_key( (string) $breadcrumb_settings['separator_type'] )
		) {
			$breadcrumb_context = function_exists( 'mrn_breadcrumbs_detect_context' ) ? mrn_breadcrumbs_detect_context() : '';
			$context_enabled    = function_exists( 'mrn_breadcrumbs_context_enabled' ) ? mrn_breadcrumbs_context_enabled( $breadcrumb_context, $breadcrumb_settings ) : true;

			if ( $context_enabled ) {
				$needs_fontawesome = true;
			}
		}
	}

	if ( $layout_builder_enabled && is_singular( mrn_base_stack_get_singular_shell_post_types() ) && function_exists( 'get_field' ) && function_exists( 'mrn_base_stack_collect_builder_link_icon_asset_needs' ) ) {
		$post_id      = get_queried_object_id();
		$builder_sets = array();

		if ( $post_id ) {
			$builder_sets = array(
				get_field( 'page_hero_rows', $post_id ),
				get_field( 'page_content_rows', $post_id ),
				get_field( 'page_after_content_rows', $post_id ),
			);
		}

		foreach ( $builder_sets as $builder_rows ) {
			mrn_base_stack_collect_builder_link_icon_asset_needs( $builder_rows, $needs_fontawesome, $needs_dashicons );

			if ( $needs_fontawesome && $needs_dashicons ) {
				break;
			}
		}
	}

	if ( $layout_builder_enabled && function_exists( 'mrn_base_stack_collect_builder_link_icon_asset_needs' ) && function_exists( 'mrn_rbl_get_post_types' ) && is_singular( mrn_rbl_get_post_types() ) && function_exists( 'get_fields' ) ) {
		$reusable_post_id = get_queried_object_id();

		if ( $reusable_post_id ) {
			$reusable_fields = get_fields( $reusable_post_id );

			if ( is_array( $reusable_fields ) ) {
				mrn_base_stack_collect_builder_link_icon_asset_needs( $reusable_fields, $needs_fontawesome, $needs_dashicons );
			}
		}
	}

	if ( $needs_fontawesome && function_exists( 'mrn_shared_assets_enqueue_fontawesome' ) ) {
		mrn_shared_assets_enqueue_fontawesome( 'mrn-base-stack-fontawesome' );
	}

	if ( $needs_dashicons ) {
		wp_enqueue_style( 'dashicons' );
	}

	wp_enqueue_script( 'mrn-base-stack-navigation', get_template_directory_uri() . '/js/navigation.js', array(), _S_VERSION, true );

	if ( $uses_icon_search ) {
		wp_enqueue_script(
			'mrn-base-stack-header-search',
			get_template_directory_uri() . '/js/header-search.js',
			array(),
			_S_VERSION,
			true
		);
	}

	if ( $layout_builder_enabled && is_singular( mrn_base_stack_get_singular_shell_post_types() ) ) {
		mrn_base_stack_enqueue_motion_assets();

		wp_enqueue_style(
			'mrn-base-stack-splide',
			get_template_directory_uri() . '/css/vendor/splide.min.css',
			array(),
			'4.1.4'
		);

		wp_enqueue_script(
			'mrn-base-stack-splide',
			get_template_directory_uri() . '/js/vendor/splide.min.js',
			array(),
			'4.1.4',
			true
		);

		wp_enqueue_script(
			'mrn-base-stack-front-end-slider',
			get_template_directory_uri() . '/js/front-end-slider.js',
			array( 'mrn-base-stack-splide' ),
			_S_VERSION,
			true
		);

		wp_enqueue_script(
			'mrn-base-stack-front-end-tabs',
			get_template_directory_uri() . '/js/front-end-tabs.js',
			array( 'mrn-base-stack-splide' ),
			_S_VERSION,
			true
		);

	}

	if ( is_singular( 'gallery' ) ) {
		wp_enqueue_style(
			'mrn-base-stack-glightbox',
			get_template_directory_uri() . '/css/vendor/glightbox.min.css',
			array(),
			'3.3.1'
		);

		wp_enqueue_script(
			'mrn-base-stack-glightbox',
			get_template_directory_uri() . '/js/vendor/glightbox.min.js',
			array(),
			'3.3.1',
			true
		);

		wp_enqueue_script(
			'mrn-base-stack-front-end-gallery',
			get_template_directory_uri() . '/js/front-end-gallery.js',
			array( 'mrn-base-stack-glightbox' ),
			_S_VERSION,
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'mrn_base_stack_scripts' );

/**
 * Load builder modules.
 */
if ( mrn_base_stack_is_layout_builder_enabled() ) {
	require_once get_template_directory() . '/inc/builder/boot.php';
} else {
	/**
	 * Detect whether the current request is editing ACF field definitions.
	 *
	 * Spacing contracts should apply to content editors, not to ACF field/group
	 * management screens where field definitions are authored.
	 *
	 * @return bool
	 */
	function mrn_base_stack_is_disabled_builder_row_spacing_field_editor_request() {
		$acf_editor_post_types = array( 'acf-field-group', 'acf-field' );

		if ( function_exists( 'get_current_screen' ) ) {
			$current_screen = get_current_screen();
			if ( $current_screen && isset( $current_screen->post_type ) ) {
				$screen_post_type = sanitize_key( (string) $current_screen->post_type );
				if ( in_array( $screen_post_type, $acf_editor_post_types, true ) ) {
					return true;
				}
			}

			if ( $current_screen && isset( $current_screen->id ) ) {
				$screen_id = sanitize_key( (string) $current_screen->id );
				if ( false !== strpos( $screen_id, 'acf-field-group' ) || false !== strpos( $screen_id, 'acf-field' ) ) {
					return true;
				}
			}
		}

		$request_post_type = '';
		if ( isset( $_REQUEST['post_type'] ) && is_scalar( $_REQUEST['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only scope detection.
			$request_post_type = sanitize_key( wp_unslash( (string) $_REQUEST['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only scope detection.
		}

		if ( '' !== $request_post_type && in_array( $request_post_type, $acf_editor_post_types, true ) ) {
			return true;
		}

		$request_post_id = '';
		if ( isset( $_REQUEST['post_id'] ) && is_scalar( $_REQUEST['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only scope detection.
			$request_post_id = sanitize_text_field( wp_unslash( (string) $_REQUEST['post_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only scope detection.
		}

		if ( '' !== $request_post_id ) {
			if ( 0 === strpos( $request_post_id, 'acf-field-group_' ) || 0 === strpos( $request_post_id, 'acf-field_' ) ) {
				return true;
			}

			if ( preg_match( '/^post_(\d+)$/', $request_post_id, $post_match ) ) {
				$post_id_from_request = absint( $post_match[1] );
				if ( $post_id_from_request > 0 ) {
					$request_post_type = sanitize_key( (string) get_post_type( $post_id_from_request ) );
					if ( in_array( $request_post_type, $acf_editor_post_types, true ) ) {
						return true;
					}
				}
			}
		}

		$request_post = 0;
		if ( isset( $_REQUEST['post'] ) && is_scalar( $_REQUEST['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only scope detection.
			$request_post = absint( $_REQUEST['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only scope detection.
		}

		if ( $request_post > 0 ) {
			$request_post_type = sanitize_key( (string) get_post_type( $request_post ) );
			if ( in_array( $request_post_type, $acf_editor_post_types, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether editor-only row-spacing contracts should load while the
	 * builder runtime remains disabled.
	 *
	 * @return bool
	 */
	function mrn_base_stack_should_apply_disabled_builder_row_spacing_contract() {
		if ( mrn_base_stack_is_disabled_builder_row_spacing_field_editor_request() ) {
			return false;
		}

		if ( is_admin() ) {
			return true;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return true;
		}

		return false;
	}

	/**
	 * Normalize a disabled-builder row-spacing selector scope key.
	 *
	 * @param mixed $scope Raw scope value.
	 * @return string
	 */
	function mrn_base_stack_normalize_disabled_builder_row_spacing_scope( $scope ) {
		$scope = is_scalar( $scope ) ? strtolower( trim( (string) $scope ) ) : '';

		if ( in_array( $scope, array( 'margin', 'padding' ), true ) ) {
			return $scope;
		}

		if ( preg_match( '/^(margin|padding)\-(top|right|bottom|left)$/', $scope ) ) {
			return $scope;
		}

		return '';
	}

	/**
	 * Expand a disabled-builder row-spacing property into side-level keys.
	 *
	 * @param mixed $property Raw property key.
	 * @return array<int, string>
	 */
	function mrn_base_stack_expand_disabled_builder_row_spacing_property_to_keys( $property ) {
		$property = is_scalar( $property ) ? strtolower( trim( (string) $property ) ) : '';
		if ( '' === $property ) {
			return array();
		}

		if ( 'margin' === $property ) {
			return array(
				'margin-top',
				'margin-right',
				'margin-bottom',
				'margin-left',
			);
		}

		if ( 'padding' === $property ) {
			return array(
				'padding-top',
				'padding-right',
				'padding-bottom',
				'padding-left',
			);
		}

		if ( preg_match( '/^(margin|padding)\-(top|right|bottom|left)$/', $property ) ) {
			return array( $property );
		}

		return array();
	}

	/**
	 * Get side selector definitions for disabled-builder row spacing controls.
	 *
	 * @return array<int, array<string, string>>
	 */
	function mrn_base_stack_get_disabled_builder_row_spacing_side_selector_definitions() {
		return array(
			array(
				'name'  => 'row_spacing_margin_top_preset',
				'label' => 'Margin Top',
				'scope' => 'margin-top',
			),
			array(
				'name'  => 'row_spacing_margin_right_preset',
				'label' => 'Margin Right',
				'scope' => 'margin-right',
			),
			array(
				'name'  => 'row_spacing_margin_bottom_preset',
				'label' => 'Margin Bottom',
				'scope' => 'margin-bottom',
			),
			array(
				'name'  => 'row_spacing_margin_left_preset',
				'label' => 'Margin Left',
				'scope' => 'margin-left',
			),
			array(
				'name'  => 'row_spacing_padding_top_preset',
				'label' => 'Padding Top',
				'scope' => 'padding-top',
			),
			array(
				'name'  => 'row_spacing_padding_right_preset',
				'label' => 'Padding Right',
				'scope' => 'padding-right',
			),
			array(
				'name'  => 'row_spacing_padding_bottom_preset',
				'label' => 'Padding Bottom',
				'scope' => 'padding-bottom',
			),
			array(
				'name'  => 'row_spacing_padding_left_preset',
				'label' => 'Padding Left',
				'scope' => 'padding-left',
			),
		);
	}

	/**
	 * Check whether the field name is a row-spacing selector.
	 *
	 * @param mixed $field_name Raw field name.
	 * @return bool
	 */
	function mrn_base_stack_is_disabled_builder_row_spacing_selector_field_name( $field_name ) {
		$field_name = is_scalar( $field_name ) ? sanitize_key( (string) $field_name ) : '';
		if ( '' === $field_name ) {
			return false;
		}

		if ( in_array( $field_name, array( 'row_spacing_preset', 'row_spacing_margin_preset', 'row_spacing_padding_preset' ), true ) ) {
			return true;
		}

		foreach ( mrn_base_stack_get_disabled_builder_row_spacing_side_selector_definitions() as $definition ) {
			$selector_name = isset( $definition['name'] ) ? sanitize_key( (string) $definition['name'] ) : '';
			if ( '' !== $selector_name && $selector_name === $field_name ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Map a row-spacing selector field name to its scope.
	 *
	 * @param mixed $field_name Raw field name.
	 * @return string
	 */
	function mrn_base_stack_get_disabled_builder_row_spacing_scope_from_field_name( $field_name ) {
		$field_name = is_scalar( $field_name ) ? sanitize_key( (string) $field_name ) : '';
		if ( 'row_spacing_margin_preset' === $field_name ) {
			return 'margin';
		}

		if ( 'row_spacing_padding_preset' === $field_name ) {
			return 'padding';
		}

		foreach ( mrn_base_stack_get_disabled_builder_row_spacing_side_selector_definitions() as $definition ) {
			$selector_name = isset( $definition['name'] ) ? sanitize_key( (string) $definition['name'] ) : '';
			$scope         = isset( $definition['scope'] ) ? mrn_base_stack_normalize_disabled_builder_row_spacing_scope( $definition['scope'] ) : '';
			if ( '' !== $selector_name && '' !== $scope && $selector_name === $field_name ) {
				return $scope;
			}
		}

		if ( 'row_spacing_preset' === $field_name ) {
			return '';
		}

		return '';
	}

	/**
	 * Check whether a row-spacing property belongs to a disabled-builder selector scope.
	 *
	 * @param mixed  $property Raw property key.
	 * @param string $scope Selector scope (`margin`, `padding`, or empty for all).
	 * @return bool
	 */
	function mrn_base_stack_disabled_builder_row_spacing_property_matches_scope( $property, $scope = '' ) {
		$scope = mrn_base_stack_normalize_disabled_builder_row_spacing_scope( $scope );
		if ( '' === $scope ) {
			return true;
		}

		$target_properties = mrn_base_stack_expand_disabled_builder_row_spacing_property_to_keys( $property );
		if ( empty( $target_properties ) ) {
			return false;
		}

		if ( in_array( $scope, array( 'margin', 'padding' ), true ) ) {
			foreach ( $target_properties as $target_property ) {
				if ( 0 === strpos( $target_property, $scope . '-' ) ) {
					return true;
				}
			}

			return false;
		}

		return in_array( $scope, $target_properties, true );
	}

	/**
	 * Build row-spacing preset choices from site styles for disabled-builder mode.
	 *
	 * @param string $scope Optional selector scope (`margin`, `padding`, or empty for all).
	 * @return array<string, string>
	 */
	function mrn_base_stack_get_disabled_builder_row_spacing_choices( $scope = '' ) {
		$scope   = mrn_base_stack_normalize_disabled_builder_row_spacing_scope( $scope );
		$choices = array(
			'' => 'Site Default',
		);

		if ( ! function_exists( 'mrn_site_styles_get_row_spacing_presets_resolved' ) ) {
			return $choices;
		}

		$rows = mrn_site_styles_get_row_spacing_presets_resolved();
		if ( ! is_array( $rows ) ) {
			return $choices;
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			if ( ! mrn_base_stack_disabled_builder_row_spacing_property_matches_scope( $row['property'] ?? '', $scope ) ) {
				continue;
			}

			$name = isset( $row['name'] ) && is_scalar( $row['name'] ) ? trim( (string) $row['name'] ) : '';
			if ( '' === $name || isset( $choices[ $name ] ) ) {
				continue;
			}

			$choices[ $name ] = $name;
		}

		return $choices;
	}

	/**
	 * Build the editor field definition for row spacing overrides.
	 *
	 * @param string $key_seed Field key seed.
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @param string $scope Optional selector scope (`margin`, `padding`, or empty for all).
	 * @param string $instructions Optional custom field instructions.
	 * @param string $wrapper_width Wrapper width percentage.
	 * @return array<string, mixed>
	 */
	function mrn_base_stack_get_disabled_builder_row_spacing_field( $key_seed, $name = 'row_spacing_preset', $label = 'Row Spacing', $scope = '', $instructions = '', $wrapper_width = '50' ) {
		$key_seed = sanitize_key( (string) $key_seed );
		$scope    = mrn_base_stack_normalize_disabled_builder_row_spacing_scope( $scope );
		if ( '' === $key_seed ) {
			$key_seed = 'field_mrn_disabled_builder_row_spacing';
		}
		if ( '' === $instructions && 'row_spacing_preset' === $name ) {
			$instructions = 'Overrides site spacing defaults for this row only.';
		}

		return array(
			'key'               => $key_seed . '_' . sanitize_key( (string) $name ),
			'label'             => $label,
			'name'              => $name,
			'aria-label'        => '',
			'type'              => 'select',
			'instructions'      => $instructions,
			'required'          => 0,
			'conditional_logic' => 0,
			'wrapper'           => array(
				'width' => (string) $wrapper_width,
			),
			'choices'           => mrn_base_stack_get_disabled_builder_row_spacing_choices( $scope ),
			'default_value'     => '',
			'allow_null'        => 1,
			'multiple'          => 0,
			'ui'                => 1,
			'ajax'              => 0,
			'return_format'     => 'value',
		);
	}

	/**
	 * Ensure a layout has row-level Content/Spacing tabs and a spacing preset control.
	 *
	 * @param array<int, mixed> $fields   Layout sub fields.
	 * @param string            $key_seed Field key seed.
	 * @return array<int, mixed>
	 */
	function mrn_base_stack_apply_disabled_builder_row_spacing_sub_fields( array $fields, $key_seed = '' ) {
		$seed              = sanitize_key( (string) $key_seed );
		$content_tab_index = null;
		$spacing_tab_index = null;
		$effects_tab_index = null;

		foreach ( $fields as $index => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field_name  = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
			$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
			$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';
			$field_key   = isset( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';

			if ( '' === $seed && '' !== $field_key ) {
				$seed = $field_key;
			}

			if ( mrn_base_stack_is_disabled_builder_row_spacing_selector_field_name( $field_name ) && 'select' === $field_type ) {
				unset( $fields[ $index ] );
				continue;
			}

			if ( 'tab' !== $field_type ) {
				continue;
			}

			if ( null === $content_tab_index && 'content' === $field_label ) {
				$content_tab_index = $index;
			}

			if ( null === $spacing_tab_index && 'spacing' === $field_label ) {
				$spacing_tab_index = $index;
			}

			if ( null === $effects_tab_index && 'effects' === $field_label ) {
				$effects_tab_index = $index;
			}
		}

		$fields = array_values( $fields );

		if ( '' === $seed ) {
			$seed = 'field_mrn_disabled_builder_row_spacing';
		}

		$row_spacing_selector_fields = array();
		foreach ( mrn_base_stack_get_disabled_builder_row_spacing_side_selector_definitions() as $definition ) {
			$selector_name  = isset( $definition['name'] ) ? sanitize_key( (string) $definition['name'] ) : '';
			$selector_label = isset( $definition['label'] ) ? sanitize_text_field( (string) $definition['label'] ) : '';
			$scope          = isset( $definition['scope'] ) ? mrn_base_stack_normalize_disabled_builder_row_spacing_scope( $definition['scope'] ) : '';

			if ( '' === $selector_name || '' === $selector_label || '' === $scope ) {
				continue;
			}

			$row_spacing_selector_fields[] = mrn_base_stack_get_disabled_builder_row_spacing_field(
				$seed . '_' . $selector_name,
				$selector_name,
				$selector_label,
				$scope,
				'',
				'25'
			);
		}

		$content_tab_index = null;
		$spacing_tab_index = null;
		$effects_tab_index = null;

		foreach ( $fields as $index => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
			$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';
			if ( 'tab' !== $field_type ) {
				continue;
			}

			if ( null === $content_tab_index && 'content' === $field_label ) {
				$content_tab_index = $index;
			}

			if ( null === $spacing_tab_index && 'spacing' === $field_label ) {
				$spacing_tab_index = $index;
			}

			if ( null === $effects_tab_index && 'effects' === $field_label ) {
				$effects_tab_index = $index;
			}
		}

		if ( null === $content_tab_index ) {
			array_unshift(
				$fields,
				array(
					'key'        => $seed . '_content_tab_contract',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				)
			);
		}

		$spacing_tab = array(
			'key'        => $seed . '_spacing_tab_contract',
			'label'      => 'Spacing',
			'name'       => '',
			'aria-label' => '',
			'type'       => 'tab',
			'placement'  => 'top',
			'endpoint'   => 0,
		);

		$spacing_tab_index = null;
		$effects_tab_index = null;

		foreach ( $fields as $index => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
			$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';
			if ( 'tab' !== $field_type ) {
				continue;
			}

			if ( null === $spacing_tab_index && 'spacing' === $field_label ) {
				$spacing_tab_index = $index;
			}

			if ( null === $effects_tab_index && 'effects' === $field_label ) {
				$effects_tab_index = $index;
			}
		}

		if ( null === $spacing_tab_index ) {
			$insert_index = null !== $effects_tab_index ? $effects_tab_index : count( $fields );
			array_splice( $fields, $insert_index, 0, array( $spacing_tab ) );
			$spacing_tab_index = $insert_index;
		}

		foreach ( $fields as $index => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
			$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
			if ( mrn_base_stack_is_disabled_builder_row_spacing_selector_field_name( $field_name ) && 'select' === $field_type ) {
				unset( $fields[ $index ] );
			}
		}

		$fields = array_values( $fields );

		$spacing_tab_index = null;
		foreach ( $fields as $index => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
			$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';
			if ( 'tab' === $field_type && 'spacing' === $field_label ) {
				$spacing_tab_index = $index;
				break;
			}
		}

		if ( null === $spacing_tab_index ) {
			$spacing_tab_index = count( $fields ) - 1;
		}

		if ( ! empty( $row_spacing_selector_fields ) ) {
			array_splice(
				$fields,
				$spacing_tab_index + 1,
				0,
				$row_spacing_selector_fields
			);
		}

		return array_values( $fields );
	}

	/**
	 * Apply row-spacing editor contracts to flexible-content fields.
	 *
	 * @param array<string, mixed>|mixed $field ACF field definition.
	 * @return array<string, mixed>|mixed
	 */
	function mrn_base_stack_apply_disabled_builder_row_spacing_contract_to_flexible_field( $field ) {
		if ( ! mrn_base_stack_should_apply_disabled_builder_row_spacing_contract() ) {
			return $field;
		}

		if ( ! is_array( $field ) ) {
			return $field;
		}

		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		if ( 'flexible_content' !== $field_type ) {
			return $field;
		}

		if ( ! isset( $field['layouts'] ) || ! is_array( $field['layouts'] ) || empty( $field['layouts'] ) ) {
			return $field;
		}

		foreach ( $field['layouts'] as $layout_key => $layout ) {
			if ( ! is_array( $layout ) || ! isset( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
				continue;
			}

			$key_seed                        = isset( $layout['key'] ) ? (string) $layout['key'] : ( isset( $field['key'] ) ? (string) $field['key'] : '' );
			$layout['sub_fields']            = mrn_base_stack_apply_disabled_builder_row_spacing_sub_fields( $layout['sub_fields'], $key_seed );
			$field['layouts'][ $layout_key ] = $layout;
		}

		return $field;
	}

	/**
	 * Ensure row-spacing preset fields always expose up-to-date site-style choices.
	 *
	 * @param array<string, mixed>|mixed $field ACF field definition.
	 * @return array<string, mixed>|mixed
	 */
	function mrn_base_stack_apply_disabled_builder_row_spacing_choices( $field ) {
		if ( ! is_array( $field ) ) {
			return $field;
		}

		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		if ( ! mrn_base_stack_is_disabled_builder_row_spacing_selector_field_name( $field_name ) || 'select' !== $field_type ) {
			return $field;
		}

		$scope = mrn_base_stack_get_disabled_builder_row_spacing_scope_from_field_name( $field_name );

		$choices       = mrn_base_stack_get_disabled_builder_row_spacing_choices( $scope );
		$current_value = isset( $field['value'] ) && is_scalar( $field['value'] ) ? trim( (string) $field['value'] ) : '';
		if ( '' !== $current_value && ! isset( $choices[ $current_value ] ) ) {
			$choices[ $current_value ] = $current_value . ' (Missing preset)';
		}

		$field['choices']       = $choices;
		$field['default_value'] = '';
		$field['allow_null']    = 0;
		$field['ui']            = 1;
		return $field;
	}

	add_filter( 'acf/load_field/type=flexible_content', 'mrn_base_stack_apply_disabled_builder_row_spacing_contract_to_flexible_field', 35 );
	add_filter( 'acf/prepare_field/type=flexible_content', 'mrn_base_stack_apply_disabled_builder_row_spacing_contract_to_flexible_field', 35 );
	add_filter( 'acf/get_field', 'mrn_base_stack_apply_disabled_builder_row_spacing_contract_to_flexible_field', 35 );
	add_filter( 'acf/load_field/name=row_spacing_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/prepare_field/name=row_spacing_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/load_field/name=row_spacing_margin_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/prepare_field/name=row_spacing_margin_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/load_field/name=row_spacing_padding_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/prepare_field/name=row_spacing_padding_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/load_field/name=row_spacing_margin_top_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/prepare_field/name=row_spacing_margin_top_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/load_field/name=row_spacing_margin_right_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/prepare_field/name=row_spacing_margin_right_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/load_field/name=row_spacing_margin_bottom_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/prepare_field/name=row_spacing_margin_bottom_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/load_field/name=row_spacing_margin_left_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/prepare_field/name=row_spacing_margin_left_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/load_field/name=row_spacing_padding_top_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/prepare_field/name=row_spacing_padding_top_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/load_field/name=row_spacing_padding_right_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/prepare_field/name=row_spacing_padding_right_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/load_field/name=row_spacing_padding_bottom_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/prepare_field/name=row_spacing_padding_bottom_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/load_field/name=row_spacing_padding_left_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );
	add_filter( 'acf/prepare_field/name=row_spacing_padding_left_preset', 'mrn_base_stack_apply_disabled_builder_row_spacing_choices', 35 );

	/**
	 * Fallback hero renderer when layout-builder runtime is disabled.
	 *
	 * @param int|null $post_id Optional post ID.
	 * @return bool
	 */
	function mrn_base_stack_render_hero_builder( $post_id = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Keep signature parity with builder-enabled runtime.
		return false;
	}

	/**
	 * Fallback main-content renderer when layout-builder runtime is disabled.
	 *
	 * @param int|null $post_id Optional post ID.
	 * @return void
	 */
	function mrn_base_stack_render_content_builder( $post_id = null ) {
		$post_id = null !== $post_id ? absint( $post_id ) : get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$content = get_post_field( 'post_content', $post_id );
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filtered through the standard post-content pipeline.
		echo apply_filters( 'the_content', $content );
	}

	/**
	 * Fallback after-content renderer when layout-builder runtime is disabled.
	 *
	 * @param int|null $post_id Optional post ID.
	 * @return void
	 */
	function mrn_base_stack_render_after_content_builder( $post_id = null ) {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Keep signature parity with builder-enabled runtime.

	/**
	 * Shared HTML tag choices for heading-style text fields.
	 *
	 * @return array<string, string>
	 */
	function mrn_base_stack_get_text_tag_choices() {
		return array(
			'h1'   => 'H1',
			'h2'   => 'H2',
			'h3'   => 'H3',
			'h4'   => 'H4',
			'h5'   => 'H5',
			'h6'   => 'H6',
			'p'    => 'Paragraph',
			'span' => 'Span',
			'div'  => 'Div',
		);
	}

	/**
	 * Normalize a requested HTML tag to the supported text-tag set.
	 *
	 * @param mixed  $value Raw tag value.
	 * @param string $default_tag Default tag value.
	 * @return string
	 */
	function mrn_base_stack_normalize_text_tag( $value, $default_tag = 'p' ) {
		$tag          = is_string( $value ) ? sanitize_key( $value ) : '';
		$default_tag  = is_string( $default_tag ) ? sanitize_key( $default_tag ) : 'p';
		$allowed_tags = array_keys( mrn_base_stack_get_text_tag_choices() );

		if ( ! in_array( $default_tag, $allowed_tags, true ) ) {
			$default_tag = 'p';
		}

		if ( ! in_array( $tag, $allowed_tags, true ) ) {
			$tag = $default_tag;
		}

		return $tag;
	}

	/**
	 * Build a standard inline-HTML-enabled text field definition.
	 *
	 * @param string $key Unique ACF field key.
	 * @param string $label Field label.
	 * @param string $name Field name.
	 * @param string $instructions Field instructions.
	 * @param string $width Wrapper width percentage.
	 * @return array<string, mixed>
	 */
	function mrn_base_stack_get_inline_text_field( $key, $label, $name, $instructions = 'Limited inline HTML allowed: span, strong, em, br.', $width = '75' ) {
		return array(
			'key'           => $key,
			'label'         => $label,
			'name'          => $name,
			'aria-label'    => '',
			'type'          => 'text',
			'instructions'  => $instructions,
			'wrapper'       => array(
				'width' => $width,
			),
		);
	}

	/**
	 * Build a standard label-tag ACF field definition.
	 *
	 * @param string $key Unique ACF field key.
	 * @param string $name Field name.
	 * @param string $default_tag Default tag choice.
	 * @param string $label Field label.
	 * @return array<string, mixed>
	 */
	function mrn_base_stack_get_label_tag_field( $key, $name = 'label_tag', $default_tag = 'p', $label = 'Tag' ) {
		unset( $label );

		return array(
			'key'               => $key,
			'label'             => 'Tag',
			'name'              => $name,
			'aria-label'        => '',
			'type'              => 'select',
			'choices'           => mrn_base_stack_get_text_tag_choices(),
			'default_value'     => mrn_base_stack_normalize_text_tag( $default_tag, 'p' ),
			'multiple'          => 0,
			'return_format'     => 'value',
			'ui'                => 0,
			'wrapper'           => array(
				'width' => '25',
			),
		);
	}

	/**
	 * Build a standard heading/subheading tag ACF field definition.
	 *
	 * @param string $key Unique ACF field key.
	 * @param string $name Field name.
	 * @param string $default_tag Default tag choice.
	 * @param string $label Field label.
	 * @return array<string, mixed>
	 */
	function mrn_base_stack_get_text_tag_field( $key, $name = 'heading_tag', $default_tag = 'h2', $label = 'Tag' ) {
		unset( $label );

		return array(
			'key'               => $key,
			'label'             => 'Tag',
			'name'              => $name,
			'aria-label'        => '',
			'type'              => 'select',
			'choices'           => mrn_base_stack_get_text_tag_choices(),
			'default_value'     => mrn_base_stack_normalize_text_tag( $default_tag, 'h2' ),
			'multiple'          => 0,
			'return_format'     => 'value',
			'ui'                => 0,
			'wrapper'           => array(
				'width' => '25',
			),
		);
	}
}

/**
 * Get a stable row-spacing contract for row wrappers across builder modes.
 *
 * When the full layout-builder runtime is available this delegates to the
 * builder helper contract. When disabled, it resolves spacing from Site Styles
 * defaults/presets so child-theme templates can still apply the shared spacing
 * variables without duplicating stack logic.
 *
 * @param array<string, mixed> $row Row-like payload containing spacing selectors.
 * @return array{classes:array<int,string>,attributes:array<string,string>}
 */
if ( ! function_exists( 'mrn_base_stack_get_row_spacing_contract' ) ) {
	/**
	 * Get row-spacing classes/attributes for one row payload.
	 *
	 * @param array<string, mixed> $row Row-like payload containing spacing selectors.
	 * @return array{classes:array<int,string>,attributes:array<string,string>}
	 */
	function mrn_base_stack_get_row_spacing_contract( array $row = array() ) {
		if ( function_exists( 'mrn_base_stack_get_builder_row_spacing_contract' ) ) {
			return mrn_base_stack_get_builder_row_spacing_contract( $row );
		}

		$property_keys = array(
			'margin-top',
			'margin-right',
			'margin-bottom',
			'margin-left',
			'padding-top',
			'padding-right',
			'padding-bottom',
			'padding-left',
		);

		$sanitize_dimension = static function ( $value ) {
			if ( function_exists( 'mrn_site_styles_sanitize_spacing_dimension' ) ) {
				return mrn_site_styles_sanitize_spacing_dimension( (string) $value );
			}

			$sanitized = preg_replace( '/[^a-zA-Z0-9.%(),\-+*\/\s]/', '', (string) $value );
			$sanitized = is_string( $sanitized ) ? trim( preg_replace( '/\s+/', ' ', $sanitized ) ) : '';
			if ( '' === $sanitized ) {
				return '';
			}

			return substr( $sanitized, 0, 64 );
		};

		$expand_property_to_keys = static function ( $property ) {
			if ( function_exists( 'mrn_base_stack_expand_row_spacing_property_to_keys' ) ) {
				return mrn_base_stack_expand_row_spacing_property_to_keys( $property );
			}

			if ( function_exists( 'mrn_base_stack_expand_disabled_builder_row_spacing_property_to_keys' ) ) {
				return mrn_base_stack_expand_disabled_builder_row_spacing_property_to_keys( $property );
			}

			$property = is_scalar( $property ) ? strtolower( trim( (string) $property ) ) : '';
			if ( 'margin' === $property ) {
				return array( 'margin-top', 'margin-right', 'margin-bottom', 'margin-left' );
			}

			if ( 'padding' === $property ) {
				return array( 'padding-top', 'padding-right', 'padding-bottom', 'padding-left' );
			}

			if ( preg_match( '/^(margin|padding)\-(top|right|bottom|left)$/', $property ) ) {
				return array( $property );
			}

			return array();
		};

		$normalize_scope = static function ( $scope ) {
			if ( function_exists( 'mrn_base_stack_normalize_row_spacing_preset_scope' ) ) {
				return mrn_base_stack_normalize_row_spacing_preset_scope( $scope );
			}

			if ( function_exists( 'mrn_base_stack_normalize_disabled_builder_row_spacing_scope' ) ) {
				return mrn_base_stack_normalize_disabled_builder_row_spacing_scope( $scope );
			}

			$scope = is_scalar( $scope ) ? strtolower( trim( (string) $scope ) ) : '';
			if ( in_array( $scope, array( 'margin', 'padding' ), true ) ) {
				return $scope;
			}

			if ( preg_match( '/^(margin|padding)\-(top|right|bottom|left)$/', $scope ) ) {
				return $scope;
			}

			return '';
		};

		$property_matches_scope = static function ( $property, $scope = '' ) use ( $expand_property_to_keys, $normalize_scope ) {
			if ( function_exists( 'mrn_base_stack_row_spacing_property_matches_scope' ) ) {
				return mrn_base_stack_row_spacing_property_matches_scope( $property, $scope );
			}

			if ( function_exists( 'mrn_base_stack_disabled_builder_row_spacing_property_matches_scope' ) ) {
				return mrn_base_stack_disabled_builder_row_spacing_property_matches_scope( $property, $scope );
			}

			$scope = $normalize_scope( $scope );
			if ( '' === $scope ) {
				return true;
			}

			$target_properties = $expand_property_to_keys( $property );
			if ( empty( $target_properties ) ) {
				return false;
			}

			if ( in_array( $scope, array( 'margin', 'padding' ), true ) ) {
				foreach ( $target_properties as $target_property ) {
					if ( 0 === strpos( $target_property, $scope . '-' ) ) {
						return true;
					}
				}

				return false;
			}

			return in_array( $scope, $target_properties, true );
		};

		$side_selector_definitions = array(
			array(
				'name'  => 'row_spacing_margin_top_preset',
				'scope' => 'margin-top',
			),
			array(
				'name'  => 'row_spacing_margin_right_preset',
				'scope' => 'margin-right',
			),
			array(
				'name'  => 'row_spacing_margin_bottom_preset',
				'scope' => 'margin-bottom',
			),
			array(
				'name'  => 'row_spacing_margin_left_preset',
				'scope' => 'margin-left',
			),
			array(
				'name'  => 'row_spacing_padding_top_preset',
				'scope' => 'padding-top',
			),
			array(
				'name'  => 'row_spacing_padding_right_preset',
				'scope' => 'padding-right',
			),
			array(
				'name'  => 'row_spacing_padding_bottom_preset',
				'scope' => 'padding-bottom',
			),
			array(
				'name'  => 'row_spacing_padding_left_preset',
				'scope' => 'padding-left',
			),
		);

		if ( function_exists( 'mrn_base_stack_get_row_spacing_side_selector_definitions' ) ) {
			$definitions = mrn_base_stack_get_row_spacing_side_selector_definitions();
			if ( is_array( $definitions ) && ! empty( $definitions ) ) {
				$side_selector_definitions = $definitions;
			}
		} elseif ( function_exists( 'mrn_base_stack_get_disabled_builder_row_spacing_side_selector_definitions' ) ) {
			$definitions = mrn_base_stack_get_disabled_builder_row_spacing_side_selector_definitions();
			if ( is_array( $definitions ) && ! empty( $definitions ) ) {
				$side_selector_definitions = $definitions;
			}
		}

		$normalize_preset_name = static function ( $value ) {
			$name = is_scalar( $value ) ? trim( (string) $value ) : '';
			$name = preg_replace( '/\s+/', ' ', $name );
			$name = is_string( $name ) ? trim( $name ) : '';

			return strtolower( $name );
		};

		$resolved_values = array(
			'desktop' => array_fill_keys( $property_keys, '' ),
			'mobile'  => array_fill_keys( $property_keys, '' ),
		);

		if ( function_exists( 'mrn_site_styles_get_row_spacing_defaults_resolved' ) ) {
			$defaults = mrn_site_styles_get_row_spacing_defaults_resolved();
			if ( is_array( $defaults ) ) {
				foreach ( $defaults as $property => $values ) {
					if ( ! is_array( $values ) ) {
						continue;
					}

					$targets = $expand_property_to_keys( $property );
					if ( empty( $targets ) ) {
						continue;
					}

					$desktop = $sanitize_dimension( $values['desktop'] ?? '' );
					$mobile  = $sanitize_dimension( $values['mobile'] ?? '' );

					foreach ( $targets as $target ) {
						if ( '' !== $desktop ) {
							$resolved_values['desktop'][ $target ] = $desktop;
						}

						if ( '' !== $mobile ) {
							$resolved_values['mobile'][ $target ] = $mobile;
						}
					}
				}
			}
		}

		$preset_rows = function_exists( 'mrn_site_styles_get_row_spacing_presets_resolved' )
			? mrn_site_styles_get_row_spacing_presets_resolved()
			: array();
		$preset_rows = is_array( $preset_rows ) ? $preset_rows : array();

		$resolve_overrides_for_preset = static function ( $preset_name, $scope = '' ) use ( $normalize_scope, $normalize_preset_name, $property_matches_scope, $expand_property_to_keys, $sanitize_dimension, $preset_rows ) {
			$normalized_name = $normalize_preset_name( $preset_name );
			$scope           = $normalize_scope( $scope );
			$scope_is_side   = (bool) preg_match( '/^(margin|padding)\-(top|right|bottom|left)$/', $scope );
			$overrides       = array(
				'desktop' => array(),
				'mobile'  => array(),
			);

			if ( '' === $normalized_name ) {
				return $overrides;
			}

			foreach ( $preset_rows as $preset_row ) {
				if ( ! is_array( $preset_row ) ) {
					continue;
				}

				$row_name = $normalize_preset_name( $preset_row['name'] ?? '' );
				if ( '' === $row_name || $row_name !== $normalized_name ) {
					continue;
				}

				if ( ! $property_matches_scope( $preset_row['property'] ?? '', $scope ) ) {
					continue;
				}

				$target_properties = $expand_property_to_keys( $preset_row['property'] ?? '' );
				if ( $scope_is_side ) {
					$target_properties = in_array( $scope, $target_properties, true ) ? array( $scope ) : array();
				}
				if ( empty( $target_properties ) ) {
					continue;
				}

				$desktop = $sanitize_dimension( $preset_row['desktop'] ?? '' );
				$mobile  = $sanitize_dimension( $preset_row['mobile'] ?? '' );

				foreach ( $target_properties as $target_property ) {
					if ( '' !== $desktop ) {
						$overrides['desktop'][ $target_property ] = $desktop;
					}

					if ( '' !== $mobile ) {
						$overrides['mobile'][ $target_property ] = $mobile;
					}
				}
			}

			return $overrides;
		};

		$apply_overrides = static function ( array $overrides, array $target ) use ( $sanitize_dimension ) {
			foreach ( array( 'desktop', 'mobile' ) as $device_key ) {
				if ( ! isset( $overrides[ $device_key ] ) || ! is_array( $overrides[ $device_key ] ) ) {
					continue;
				}

				foreach ( $overrides[ $device_key ] as $property => $value ) {
					$property = sanitize_key( (string) $property );
					$value    = $sanitize_dimension( $value );
					if ( '' === $property || '' === $value ) {
						continue;
					}

					$target[ $device_key ][ $property ] = $value;
				}
			}

			return $target;
		};

		$preset_name = isset( $row['row_spacing_preset'] ) && is_scalar( $row['row_spacing_preset'] )
			? trim( (string) $row['row_spacing_preset'] )
			: '';
		if ( '' !== $preset_name ) {
			$resolved_values = $apply_overrides( $resolve_overrides_for_preset( $preset_name, '' ), $resolved_values );
		}

		$margin_preset = isset( $row['row_spacing_margin_preset'] ) && is_scalar( $row['row_spacing_margin_preset'] )
			? trim( (string) $row['row_spacing_margin_preset'] )
			: '';
		if ( '' !== $margin_preset ) {
			$resolved_values = $apply_overrides( $resolve_overrides_for_preset( $margin_preset, 'margin' ), $resolved_values );
		}

		$padding_preset = isset( $row['row_spacing_padding_preset'] ) && is_scalar( $row['row_spacing_padding_preset'] )
			? trim( (string) $row['row_spacing_padding_preset'] )
			: '';
		if ( '' !== $padding_preset ) {
			$resolved_values = $apply_overrides( $resolve_overrides_for_preset( $padding_preset, 'padding' ), $resolved_values );
		}

		$side_preset_names = array();
		foreach ( $side_selector_definitions as $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			$selector_name = isset( $definition['name'] ) ? sanitize_key( (string) $definition['name'] ) : '';
			$scope         = isset( $definition['scope'] ) ? $normalize_scope( $definition['scope'] ) : '';
			if ( '' === $selector_name || '' === $scope ) {
				continue;
			}

				$selected_name = isset( $row[ $selector_name ] ) && is_scalar( $row[ $selector_name ] )
					? trim( (string) $row[ $selector_name ] )
					: '';

				$side_preset_names[ $scope ] = $selected_name;

			if ( '' === $selected_name ) {
				continue;
			}

			$resolved_values = $apply_overrides( $resolve_overrides_for_preset( $selected_name, $scope ), $resolved_values );
		}

		$active_selector_label = $preset_name;
		if ( '' === $active_selector_label ) {
			$active_selector_parts = array();
			if ( '' !== $margin_preset ) {
				$active_selector_parts[] = 'margin-' . sanitize_title( $margin_preset );
			}
			if ( '' !== $padding_preset ) {
				$active_selector_parts[] = 'padding-' . sanitize_title( $padding_preset );
			}
			foreach ( $side_preset_names as $scope => $side_name ) {
				if ( '' === $side_name ) {
					continue;
				}

				$active_selector_parts[] = sanitize_title( (string) $scope ) . '-' . sanitize_title( $side_name );
			}
			$active_selector_label = implode( '_', $active_selector_parts );
		}

		$styles      = array();
		$has_spacing = false;

		foreach ( $property_keys as $property ) {
			$desktop = isset( $resolved_values['desktop'][ $property ] ) ? $sanitize_dimension( $resolved_values['desktop'][ $property ] ) : '';
			$mobile  = isset( $resolved_values['mobile'][ $property ] ) ? $sanitize_dimension( $resolved_values['mobile'][ $property ] ) : '';

			if ( '' !== $desktop ) {
				$styles[]    = '--mrn-row-' . $property . '-desktop: ' . $desktop;
				$has_spacing = true;
			}

			if ( '' !== $mobile ) {
				$styles[]    = '--mrn-row-' . $property . '-mobile: ' . $mobile;
				$has_spacing = true;
			}
		}

		if ( ! $has_spacing ) {
			return array(
				'classes'    => array(),
				'attributes' => array(),
			);
		}

		$style = function_exists( 'mrn_base_stack_get_inline_style_attribute' )
			? mrn_base_stack_get_inline_style_attribute( $styles )
			: implode( '; ', array_values( array_filter( array_map( 'trim', $styles ), 'strlen' ) ) );
		if ( '' === $style ) {
			return array(
				'classes'    => array(),
				'attributes' => array(),
			);
		}

		return array(
			'classes'    => array(),
			'attributes' => array(
				'data-mrn-row-spacing' => '' !== $active_selector_label ? sanitize_title( $active_selector_label ) : 'defaults',
				'style'                => $style,
			),
		);
	}
}

/**
 * Load theme options modules.
 */
require_once get_template_directory() . '/inc/theme-options.php';

/**
 * Load menu link attribute modules.
 */
require_once get_template_directory() . '/inc/menu-link-attributes.php';

/**
 * Load singular sidebar modules.
 */
require_once get_template_directory() . '/inc/singular-sidebar.php';

/**
 * Load gallery modules.
 */
require_once get_template_directory() . '/inc/gallery.php';

/**
 * Load testimonial modules.
 */
require_once get_template_directory() . '/inc/testimonial.php';

/**
 * Load case-study modules.
 */
require_once get_template_directory() . '/inc/case-study.php';

/**
 * Implement the Custom Header feature.
 */
require get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Functions which enhance the theme by hooking into WordPress.
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
if ( defined( 'JETPACK__VERSION' ) ) {
	require get_template_directory() . '/inc/jetpack.php';
}

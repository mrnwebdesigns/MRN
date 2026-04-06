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
	define( '_S_VERSION', '1.1.11' );
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
			'menu-2' => esc_html__( 'Utility', 'mrn-base-stack' ),
			'menu-1' => esc_html__( 'Primary', 'mrn-base-stack' ),
			'menu-3' => esc_html__( 'Footer', 'mrn-base-stack' ),
			'menu-4' => esc_html__( 'Legal', 'mrn-base-stack' ),
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
	$post_types = array( 'blog', 'gallery', 'testimonial', 'case_study' );

	/**
	 * Filter the theme-owned editorial CPT slugs.
	 *
	 * @param array<int, string> $post_types Supported CPTs.
	 */
	$post_types = apply_filters( 'mrn_base_stack_editorial_cpts', $post_types );

	if ( ! is_array( $post_types ) ) {
		return array( 'blog', 'gallery', 'testimonial', 'case_study' );
	}

	$post_types = array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $post_types )
			)
		)
	);

	return ! empty( $post_types ) ? $post_types : array( 'blog', 'gallery', 'testimonial', 'case_study' );
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
	$post_types = array( 'page', 'post', 'blog' );

	/**
	 * Filter the post types that should receive the theme builder experience.
	 *
	 * @param array<int, string> $post_types Supported post types.
	 */
	$post_types = apply_filters( 'mrn_base_stack_builder_supported_post_types', $post_types );

	if ( ! is_array( $post_types ) ) {
		return array( 'page', 'post', 'blog' );
	}

	$post_types = array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $post_types )
			)
		)
	);

	return ! empty( $post_types ) ? $post_types : array( 'page', 'post', 'blog' );
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
 * Register the theme-owned Blog custom post type.
 *
 * @return void
 */
function mrn_base_stack_register_blog_post_type() {
	$show_ui = mrn_base_stack_is_admin_cpt_visible( 'blog' );

	$labels = array(
		'name'                  => __( 'Blogs', 'mrn-base-stack' ),
		'singular_name'         => __( 'Blog', 'mrn-base-stack' ),
		'menu_name'             => __( 'Blogs', 'mrn-base-stack' ),
		'name_admin_bar'        => __( 'Blog', 'mrn-base-stack' ),
		'add_new'               => __( 'Add New', 'mrn-base-stack' ),
		'add_new_item'          => __( 'Add New Blog', 'mrn-base-stack' ),
		'new_item'              => __( 'New Blog', 'mrn-base-stack' ),
		'edit_item'             => __( 'Edit Blog', 'mrn-base-stack' ),
		'view_item'             => __( 'View Blog', 'mrn-base-stack' ),
		'view_items'            => __( 'View Blogs', 'mrn-base-stack' ),
		'all_items'             => __( 'All Blogs', 'mrn-base-stack' ),
		'search_items'          => __( 'Search Blogs', 'mrn-base-stack' ),
		'parent_item_colon'     => __( 'Parent Blogs:', 'mrn-base-stack' ),
		'not_found'             => __( 'No blogs found.', 'mrn-base-stack' ),
		'not_found_in_trash'    => __( 'No blogs found in Trash.', 'mrn-base-stack' ),
		'archives'              => __( 'Blog Archives', 'mrn-base-stack' ),
		'attributes'            => __( 'Blog Attributes', 'mrn-base-stack' ),
		'insert_into_item'      => __( 'Insert into blog', 'mrn-base-stack' ),
		'uploaded_to_this_item' => __( 'Uploaded to this blog', 'mrn-base-stack' ),
		'featured_image'        => __( 'Featured image', 'mrn-base-stack' ),
		'set_featured_image'    => __( 'Set featured image', 'mrn-base-stack' ),
		'remove_featured_image' => __( 'Remove featured image', 'mrn-base-stack' ),
		'use_featured_image'    => __( 'Use as featured image', 'mrn-base-stack' ),
		'filter_items_list'     => __( 'Filter blogs list', 'mrn-base-stack' ),
		'items_list_navigation' => __( 'Blogs list navigation', 'mrn-base-stack' ),
		'items_list'            => __( 'Blogs list', 'mrn-base-stack' ),
		'item_published'        => __( 'Blog published.', 'mrn-base-stack' ),
		'item_updated'          => __( 'Blog updated.', 'mrn-base-stack' ),
	);

	register_post_type(
		'blog',
		array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => $show_ui,
			'show_in_menu'        => $show_ui,
			'show_in_rest'        => true,
			'has_archive'         => true,
			'rewrite'             => array(
				'slug'       => 'blog',
				'with_front' => false,
			),
			'menu_position'       => 6,
			'menu_icon'           => 'dashicons-admin-post',
			'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
			'taxonomies'          => array( 'category', 'post_tag' ),
			'publicly_queryable'  => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => $show_ui,
			'exclude_from_search' => false,
			'hierarchical'        => false,
			'query_var'           => true,
		)
	);
}
add_action( 'init', 'mrn_base_stack_register_blog_post_type' );

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
	wp_enqueue_script(
		'mrn-base-stack-admin-repeater-controls',
		get_template_directory_uri() . '/js/admin-repeater-controls.js',
		array( 'jquery', 'acf-input' ),
		_S_VERSION,
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

	$header_options     = function_exists( 'mrn_base_stack_get_theme_header_footer_options' ) ? mrn_base_stack_get_theme_header_footer_options() : array();
	$needs_fontawesome  = false;
	$needs_dashicons    = false;
	$uses_icon_search   = ! empty( $header_options['header_show_search'] ) && isset( $header_options['header_search_style'] ) && 'icon_only' === $header_options['header_search_style'];
	$search_icon_source = isset( $header_options['header_search_icon_source'] ) ? (string) $header_options['header_search_icon_source'] : 'dashicons';

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

	if ( is_singular( mrn_base_stack_get_singular_shell_post_types() ) ) {
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
require_once get_template_directory() . '/inc/builder/boot.php';

/**
 * Load theme options modules.
 */
require_once get_template_directory() . '/inc/theme-options.php';

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

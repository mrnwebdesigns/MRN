<?php
/**
 * mrn-base-stack functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package mrn-base-stack
 */

if ( ! defined( '_S_VERSION' ) ) {
	// Replace the version number of the theme on each release.
	define( '_S_VERSION', '1.0.3' );
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
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function mrn_base_stack_widgets_init() {
	register_sidebar(
		array(
			'name'          => esc_html__( 'Sidebar', 'mrn-base-stack' ),
			'id'            => 'sidebar-1',
			'description'   => esc_html__( 'Add widgets here.', 'mrn-base-stack' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'mrn_base_stack_widgets_init' );

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

	if ( function_exists( 'mrn_config_helper_get_social_links' ) && function_exists( 'mrn_shared_assets_enqueue_fontawesome' ) ) {
		$social_links = mrn_config_helper_get_social_links();

		if ( is_array( $social_links ) ) {
			foreach ( $social_links as $social_link ) {
				if ( is_array( $social_link ) && isset( $social_link['icon_type'] ) && 'fontawesome' === $social_link['icon_type'] ) {
					mrn_shared_assets_enqueue_fontawesome( 'mrn-base-stack-fontawesome' );
					break;
				}
			}
		}
	}

	wp_enqueue_script( 'mrn-base-stack-navigation', get_template_directory_uri() . '/js/navigation.js', array(), _S_VERSION, true );

	if ( is_singular( array( 'post', 'page' ) ) ) {
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

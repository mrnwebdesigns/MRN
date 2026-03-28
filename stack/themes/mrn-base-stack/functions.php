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
	define( '_S_VERSION', '1.0.0' );
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
			'menu-1' => esc_html__( 'Primary', 'mrn-base-stack' ),
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
 * Enqueue scripts and styles.
 */
function mrn_base_stack_scripts() {
	wp_enqueue_style( 'mrn-base-stack-style', get_stylesheet_uri(), array(), _S_VERSION );
	wp_style_add_data( 'mrn-base-stack-style', 'rtl', 'replace' );

	wp_enqueue_script( 'mrn-base-stack-navigation', get_template_directory_uri() . '/js/navigation.js', array(), _S_VERSION, true );

	if ( is_singular( array( 'post', 'page' ) ) ) {
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
 * Enqueue content-builder admin assets on post and page edit screens.
 *
 * @param string $hook_suffix Current admin screen hook.
 * @return void
 */
function mrn_base_stack_admin_enqueue_builder_assets( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
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
	if ( ! $screen instanceof WP_Screen || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
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

	if ( ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
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
 * Build the context array passed into a builder template part.
 *
 * @param array<string, mixed> $row Flexible Content row.
 * @param int                  $post_id Current post ID.
 * @param int                  $index Zero-based row index.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_builder_row_context( array $row, $post_id, $index ) {
	return array(
		'row'     => $row,
		'post_id' => (int) $post_id,
		'index'   => (int) $index,
	);
}

/**
 * Allow a small, intentional inline HTML subset for heading-style fields.
 *
 * @param string $value Raw heading text value.
 * @return string
 */
function mrn_base_stack_format_heading_inline_html( $value ) {
	$allowed_tags = array(
		'span'   => array(
			'class' => true,
		),
		'strong' => array(),
		'em'     => array(),
		'br'     => array(),
	);

	return wp_kses( (string) $value, $allowed_tags );
}

/**
 * Nested layouts available inside the Two Column Split builder row.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_two_column_nested_layouts() {
	return array(
		'layout_mrn_nested_body_text'      => array(
			'key'        => 'layout_mrn_nested_body_text',
			'name'       => 'body_text',
			'label'      => 'Body Text',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'          => 'field_mrn_nested_body_text_content',
					'label'        => 'Body Text',
					'name'         => 'body_text',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'           => 'field_mrn_nested_body_text_bottom_accent',
					'label'         => 'Bottom Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_body_text_bottom_accent_style',
					'label'         => 'Bottom Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
			),
		),
		'layout_mrn_nested_basic'          => array(
			'key'        => 'layout_mrn_nested_basic',
			'name'       => 'basic',
			'label'      => 'Basic - label|title|text with editor|image|link',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_basic_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'           => 'field_mrn_nested_basic_label',
					'label'         => 'Label',
					'name'          => 'label',
					'aria-label'    => '',
					'type'          => 'text',
					'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
				),
				array(
					'key'           => 'field_mrn_nested_basic_heading',
					'label'         => 'Title field',
					'name'          => 'text_field',
					'aria-label'    => '',
					'type'          => 'text',
					'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_heading_tag',
					'label'         => 'HTML Tag for Text Field',
					'name'          => 'text_field_tag',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'h1'   => 'H1',
						'h2'   => 'H2',
						'h3'   => 'H3',
						'h4'   => 'H4',
						'h5'   => 'H5',
						'h6'   => 'H6',
						'p'    => 'Paragraph',
						'span' => 'Span',
						'div'  => 'Div',
					),
					'default_value' => 'h2',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'          => 'field_mrn_nested_basic_content',
					'label'        => 'Text area with editor',
					'name'         => 'content',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'           => 'field_mrn_nested_basic_image',
					'label'         => 'Image',
					'name'          => 'image',
					'aria-label'    => '',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_link',
					'label'         => 'Link',
					'name'          => 'link',
					'aria-label'    => '',
					'type'          => 'link',
					'return_format' => 'array',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'        => 'field_mrn_nested_basic_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_basic_link_style',
					'label'         => 'Link style',
					'name'          => 'link_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_link_style_choices' )
						? mrn_rbl_get_link_style_choices()
						: array(
							'link'   => 'Link',
							'button' => 'Button',
						),
					'default_value' => 'link',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_link_color',
					'label'         => 'Link color',
					'name'          => 'link_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_image_placement',
					'label'         => 'Image placement',
					'name'          => 'image_placement',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'left'  => 'Left',
						'right' => 'Right',
					),
					'default_value' => 'left',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
				),
				array(
					'key'           => 'field_mrn_nested_basic_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
			),
		),
		'layout_mrn_nested_card'           => array(
			'key'        => 'layout_mrn_nested_card',
			'name'       => 'card',
			'label'      => 'Card - image|text|link',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_card_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'           => 'field_mrn_nested_card_heading',
					'label'         => 'Text Field',
					'name'          => 'text_field',
					'aria-label'    => '',
					'type'          => 'text',
					'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_card_heading_tag',
					'label'         => 'HTML Tag for Text Field',
					'name'          => 'text_field_tag',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'h1'   => 'H1',
						'h2'   => 'H2',
						'h3'   => 'H3',
						'h4'   => 'H4',
						'h5'   => 'H5',
						'h6'   => 'H6',
						'p'    => 'Paragraph',
						'span' => 'Span',
						'div'  => 'Div',
					),
					'default_value' => 'h2',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'          => 'field_mrn_nested_card_items',
					'label'        => 'Cards',
					'name'         => 'card_items',
					'aria-label'   => '',
					'type'         => 'repeater',
					'layout'       => 'row',
					'collapsed'    => 'field_mrn_nested_card_item_text',
					'button_label' => 'Add Card',
					'min'          => 1,
					'sub_fields'   => array(
						array(
							'key'           => 'field_mrn_nested_card_item_image',
							'label'         => 'Image',
							'name'          => 'image',
							'aria-label'    => '',
							'type'          => 'image',
							'return_format' => 'array',
							'preview_size'  => 'medium',
							'library'       => 'all',
							'wrapper'       => array(
								'width' => '33',
							),
						),
						array(
							'key'          => 'field_mrn_nested_card_item_text',
							'label'        => 'Text',
							'name'         => 'text',
							'aria-label'   => '',
							'type'         => 'wysiwyg',
							'tabs'         => 'all',
							'toolbar'      => 'full',
							'media_upload' => 1,
							'delay'        => 0,
							'wrapper'      => array(
								'width' => '34',
							),
						),
						array(
							'key'           => 'field_mrn_nested_card_item_link',
							'label'         => 'Link',
							'name'          => 'link',
							'aria-label'    => '',
							'type'          => 'link',
							'return_format' => 'array',
							'wrapper'       => array(
								'width' => '33',
							),
						),
					),
				),
				array(
					'key'           => 'field_mrn_nested_card_link',
					'label'         => 'Link',
					'name'          => 'link',
					'aria-label'    => '',
					'type'          => 'link',
					'return_format' => 'array',
				),
				array(
					'key'        => 'field_mrn_nested_card_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'           => 'field_mrn_nested_card_background_color',
					'label'         => 'Background Color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
				),
				array(
					'key'           => 'field_mrn_nested_card_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_card_bottom_accent_style',
					'label'         => 'Bottom Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
			),
		),
		'layout_mrn_nested_reusable_block' => array(
			'key'        => 'layout_mrn_nested_reusable_block',
			'name'       => 'reusable_block',
			'label'      => 'Reusable Block',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'           => 'field_mrn_nested_reusable_block_post',
					'label'         => 'Block',
					'name'          => 'block',
					'aria-label'    => '',
					'type'          => 'post_object',
					'post_type'     => function_exists( 'mrn_rbl_get_post_types' ) ? mrn_rbl_get_post_types() : array(),
					'return_format' => 'object',
					'ui'            => 1,
					'allow_null'    => 0,
					'multiple'      => 0,
					'instructions'  => 'Choose a reusable block from the library. Editing that block updates it everywhere it is used.',
				),
			),
		),
	);
}

/**
 * Render a single hero row.
 *
 * @param array<string, mixed> $row Hero flexible content row.
 * @param int                  $post_id Current post ID.
 * @param int                  $index Zero-based row index.
 * @return bool True when a known hero row type was rendered.
 */
function mrn_base_stack_render_hero_row( array $row, $post_id, $index ) {
	if ( empty( $row['acf_fc_layout'] ) ) {
		return false;
	}

	$layout  = (string) $row['acf_fc_layout'];
	$context = mrn_base_stack_get_builder_row_context( $row, $post_id, $index );

	if ( 'hero' === $layout ) {
		get_template_part( 'template-parts/builder/hero', null, $context );
		return true;
	}

	return false;
}

/**
 * Render the ACF hero rows for posts and pages.
 *
 * @param int|null $post_id Post ID to render. Defaults to current post.
 * @return bool True when at least one hero row was rendered.
 */
function mrn_base_stack_render_hero_builder( $post_id = null ) {
	if ( ! function_exists( 'get_field' ) ) {
		return false;
	}

	$post_id = $post_id ? (int) $post_id : get_the_ID();
	if ( ! $post_id ) {
		return false;
	}

	$rows = get_field( 'page_hero_rows', $post_id );
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return false;
	}

	$rendered = false;

	foreach ( $rows as $index => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		if ( mrn_base_stack_render_hero_row( $row, $post_id, $index ) ) {
			$rendered = true;
		}
	}

	return $rendered;
}

/**
 * Render a single builder row.
 *
 * @param array<string, mixed> $row Flexible Content row.
 * @param int                  $post_id Current post ID.
 * @param int                  $index Zero-based row index.
 * @return bool True when a known row type was rendered.
 */
function mrn_base_stack_render_builder_row( array $row, $post_id, $index ) {
	if ( empty( $row['acf_fc_layout'] ) ) {
		return false;
	}

	$layout  = (string) $row['acf_fc_layout'];
	$context = mrn_base_stack_get_builder_row_context( $row, $post_id, $index );

	if ( 'body_text' === $layout ) {
		get_template_part( 'template-parts/builder/body-text', null, $context );
		return true;
	}

	if ( 'hero' === $layout ) {
		get_template_part( 'template-parts/builder/hero', null, $context );
		return true;
	}

	if ( 'basic' === $layout ) {
		get_template_part( 'template-parts/builder/basic', null, $context );
		return true;
	}

	if ( 'cta' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			echo mrn_rbl_render_fields_as_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'mrn_reusable_cta',
				$row,
				array(
					'post_id'    => (int) $post_id,
					'post_name'  => 'page-cta',
					'block_name' => 'Page CTA',
				)
			);
			return true;
		}

		return false;
	}

	if ( 'grid' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			echo mrn_rbl_render_fields_as_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'mrn_reusable_grid',
				$row,
				array(
					'post_id'    => (int) $post_id,
					'post_name'  => 'page-grid',
					'block_name' => 'Page Grid',
				)
			);
			return true;
		}

		return false;
	}

	if ( 'image_content' === $layout ) {
		get_template_part( 'template-parts/builder/image-content', null, $context );
		return true;
	}

	if ( 'slider' === $layout ) {
		get_template_part( 'template-parts/builder/slider', null, $context );
		return true;
	}

	if ( 'external_widget' === $layout ) {
		get_template_part( 'template-parts/builder/external-widget', null, $context );
		return true;
	}

	if ( 'card' === $layout ) {
		get_template_part( 'template-parts/builder/card', null, $context );
		return true;
	}

	if ( 'two_column_split' === $layout ) {
		get_template_part( 'template-parts/builder/two-column-split', null, $context );
		return true;
	}

	if ( 'reusable_block' === $layout ) {
		get_template_part( 'template-parts/builder/reusable-block', null, $context );
		return true;
	}

	if ( 'basic_block' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			echo mrn_rbl_render_fields_as_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'mrn_reusable_basic',
				$row,
				array(
					'post_id'    => (int) $post_id,
					'post_name'  => 'page-basic-block',
					'block_name' => 'Page Basic Block',
				)
			);
			return true;
		}

		return false;
	}

	if ( 'content_grid' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			echo mrn_rbl_render_fields_as_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'mrn_reusable_grid',
				$row,
				array(
					'post_id'    => (int) $post_id,
					'post_name'  => 'page-content-grid',
					'block_name' => 'Page Content Grid',
				)
			);
			return true;
		}

		return false;
	}

	if ( 'cta_block' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			echo mrn_rbl_render_fields_as_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'mrn_reusable_cta',
				$row,
				array(
					'post_id'    => (int) $post_id,
					'post_name'  => 'page-cta-block',
					'block_name' => 'Page CTA Block',
				)
			);
			return true;
		}

		return false;
	}

	if ( 'faq_block' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			echo mrn_rbl_render_fields_as_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'mrn_reusable_faq',
				$row,
				array(
					'post_id'    => (int) $post_id,
					'post_name'  => 'page-faq-block',
					'block_name' => 'Page FAQ Block',
				)
			);
			return true;
		}

		return false;
	}

	return false;
}

/**
 * Map reusable block post types to their page-specific builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_page_specific_layout_map() {
	return array(
		'mrn_reusable_cta'   => 'cta_block',
		'mrn_reusable_basic' => 'basic_block',
		'mrn_reusable_grid'  => 'content_grid',
		'mrn_reusable_faq'   => 'faq_block',
	);
}

/**
 * Map reusable block post types to their page-specific builder layout keys.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_page_specific_layout_key_map() {
	return array(
		'mrn_reusable_cta'   => 'layout_mrn_cta_block',
		'mrn_reusable_basic' => 'layout_mrn_basic_block',
		'mrn_reusable_grid'  => 'layout_mrn_content_grid',
		'mrn_reusable_faq'   => 'layout_mrn_faq_block',
	);
}

/**
 * Normalize block field data for use in AJAX responses.
 *
 * @param mixed $value Field value.
 * @return mixed
 */
function mrn_base_stack_normalize_page_specific_payload_value( $value ) {
	if ( $value instanceof WP_Post ) {
		return (int) $value->ID;
	}

	if ( is_array( $value ) ) {
		if ( isset( $value['ID'] ) && is_numeric( $value['ID'] ) && ( isset( $value['url'] ) || isset( $value['filename'] ) || isset( $value['sizes'] ) ) ) {
			return (int) $value['ID'];
		}

		foreach ( $value as $key => $item ) {
			$value[ $key ] = mrn_base_stack_normalize_page_specific_payload_value( $item );
		}
	}

	return $value;
}

/**
 * Build the conversion payload for a reusable block.
 *
 * @param int $block_id Reusable block post ID.
 * @return array<string, mixed>|WP_Error
 */
function mrn_base_stack_get_page_specific_payload_for_block( $block_id ) {
	if ( ! function_exists( 'get_fields' ) ) {
		return new WP_Error( 'acf_missing', 'ACF is required to convert reusable blocks.' );
	}

	$block = get_post( $block_id );
	if ( ! ( $block instanceof WP_Post ) ) {
		return new WP_Error( 'invalid_block', 'The selected reusable block could not be found.' );
	}

	$layout_map = mrn_base_stack_get_page_specific_layout_map();
	$layout_key_map = mrn_base_stack_get_page_specific_layout_key_map();
	$target_layout  = $layout_map[ $block->post_type ] ?? '';
	$target_key     = $layout_key_map[ $block->post_type ] ?? '';

	if ( '' === $target_layout || '' === $target_key ) {
		return new WP_Error( 'unsupported_block_type', 'This reusable block type does not have a page-specific version yet.' );
	}

	$block_fields = get_fields( $block->ID );
	if ( ! is_array( $block_fields ) || empty( $block_fields ) ) {
		return new WP_Error( 'empty_block', 'The selected reusable block does not have field data to copy yet.' );
	}

	return array(
		'layout'     => $target_layout,
		'layoutKey'  => $target_key,
		'fields'     => mrn_base_stack_normalize_page_specific_payload_value( $block_fields ),
		'blockId'    => (int) $block->ID,
		'blockTitle' => get_the_title( $block ),
	);
}

/**
 * AJAX: prepare a reusable block for page-specific conversion.
 *
 * @return void
 */
function mrn_base_stack_ajax_prepare_page_specific_block() {
	check_ajax_referer( 'mrn-base-stack-convert-reusable-block', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
		wp_send_json_error(
			array(
				'message' => 'You do not have permission to convert reusable blocks.',
			),
			403
		);
	}

	$block_id = isset( $_POST['block_id'] ) ? absint( wp_unslash( $_POST['block_id'] ) ) : 0;
	if ( $block_id < 1 ) {
		wp_send_json_error(
			array(
				'message' => 'Choose a reusable block before converting it.',
			),
			400
		);
	}

	$payload = mrn_base_stack_get_page_specific_payload_for_block( $block_id );
	if ( is_wp_error( $payload ) ) {
		wp_send_json_error(
			array(
				'message' => $payload->get_error_message(),
			),
			400
		);
	}

	wp_send_json_success( $payload );
}
add_action( 'wp_ajax_mrn_base_stack_prepare_page_specific_block', 'mrn_base_stack_ajax_prepare_page_specific_block' );

/**
 * Keep draft reusable blocks out of the page/post builder picker.
 *
 * @param array<string, mixed> $args WP_Query args for the post object field.
 * @return array<string, mixed>
 */
function mrn_base_stack_filter_reusable_block_picker_query( $args ) {
	$args['post_status'] = array( 'publish' );

	return $args;
}
add_filter( 'acf/fields/post_object/query/key=field_mrn_reusable_block_post', 'mrn_base_stack_filter_reusable_block_picker_query' );
add_filter( 'acf/fields/post_object/query/key=field_mrn_nested_reusable_block_post', 'mrn_base_stack_filter_reusable_block_picker_query' );

/**
 * Improve flexible content row titles in the builder using ACF's native layout title filter.
 *
 * @param string               $title  Current layout title HTML.
 * @param array<string, mixed> $field  Flexible content field settings.
 * @param array<string, mixed> $layout Current layout settings.
 * @param int|string           $i      Row index.
 * @return string
 */
function mrn_base_stack_filter_builder_layout_title( $title, $field, $layout, $i ) {
	unset( $field, $i );

	if ( ! is_array( $layout ) ) {
		return $title;
	}

	if ( ! function_exists( 'get_sub_field' ) ) {
		return $title;
	}

	$layout_name = isset( $layout['name'] ) ? (string) $layout['name'] : '';

	if ( 'reusable_block' === $layout_name ) {
		$block = get_sub_field( 'block' );
		if ( $block instanceof WP_Post ) {
			$block_title = get_the_title( $block );
		} elseif ( is_numeric( $block ) ) {
			$block_title = get_the_title( (int) $block );
		} else {
			$block_title = '';
		}

		$block_title = is_string( $block_title ) ? trim( $block_title ) : '';

		if ( '' === $block_title ) {
			return $title;
		}

		return 'Reusable Block: ' . esc_html( $block_title );
	}

	if ( 'basic' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'text_field' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Basic: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'cta' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'text_field' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'CTA: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'grid' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'text_field' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Grid: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'slider' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'text_field' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Slider: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'image_content' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'text_field' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Image: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'body_text' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'title_field' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Text: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'card' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'text_field' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Card: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	return $title;
}
add_filter( 'acf/fields/flexible_content/layout_title/name=page_content_rows', 'mrn_base_stack_filter_builder_layout_title', 10, 4 );

/**
 * Render the ACF content builder rows for posts and pages.
 *
 * @param int|null $post_id Post ID to render. Defaults to current post.
 * @return bool True when at least one builder row was rendered.
 */
function mrn_base_stack_render_content_builder( $post_id = null ) {
	if ( ! function_exists( 'get_field' ) ) {
		return false;
	}

	$post_id = $post_id ? (int) $post_id : get_the_ID();
	if ( ! $post_id ) {
		return false;
	}

	$rows = get_field( 'page_content_rows', $post_id );
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return false;
	}

	echo '<div class="mrn-content-builder">';

	foreach ( $rows as $index => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		if ( mrn_base_stack_render_builder_row( $row, $post_id, $index ) ) {
			continue;
		}
	}

	echo '</div>';

	return true;
}

/**
 * Get the rendered builder markup for a post without echoing it.
 *
 * @param int|null $post_id Post ID to render. Defaults to current post.
 * @return string Rendered builder markup, or an empty string when unavailable.
 */
function mrn_base_stack_get_content_builder_markup( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	if ( ! $post_id ) {
		return '';
	}

	ob_start();
	$rendered = mrn_base_stack_render_content_builder( $post_id );
	$markup   = ob_get_clean();

	if ( ! $rendered || ! is_string( $markup ) ) {
		return '';
	}

	return trim( $markup );
}

/**
 * Build markup for SmartCrawl content analysis when the builder is in use.
 *
 * SmartCrawl's recommended "Content" mode only inspects `the_content()` output.
 * Our starter theme renders the ACF builder directly, so we provide equivalent
 * singular markup here when builder rows exist.
 *
 * @param int $post_id Post ID being analyzed.
 * @return string Markup string for SmartCrawl, or an empty string to fall back.
 */
function mrn_base_stack_get_smartcrawl_markup( $post_id ) {
	$post = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) ) {
		return '';
	}

	$builder_markup = mrn_base_stack_get_content_builder_markup( $post->ID );
	if ( '' === $builder_markup ) {
		return '';
	}

	$title_markup = sprintf(
		'<h1 class="entry-title">%s</h1>',
		esc_html( get_the_title( $post ) )
	);

	return $title_markup . "\n" . $builder_markup;
}

/**
 * Feed builder-rendered markup into SmartCrawl endpoint analysis.
 *
 * @param mixed        $subject Existing subject from earlier filters.
 * @param string|array $keywords Focus keyword(s), unused here.
 * @param bool         $is_primary Whether SmartCrawl is running the primary check set.
 * @return mixed Markup string when builder content exists, otherwise the original subject.
 */
function mrn_base_stack_filter_smartcrawl_subject_endpoint( $subject, $keywords, $is_primary ) {
	unset( $keywords, $is_primary );

	if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
		return $subject;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->base ) {
		return $subject;
	}

	$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
	if ( ! $post_id && isset( $_POST['post_ID'] ) ) {
		$post_id = absint( wp_unslash( $_POST['post_ID'] ) );
	}

	if ( ! $post_id ) {
		return $subject;
	}

	$markup = mrn_base_stack_get_smartcrawl_markup( $post_id );

	return '' !== $markup ? $markup : $subject;
}
add_filter( 'wds-checks-subject-endpoint', 'mrn_base_stack_filter_smartcrawl_subject_endpoint', 10, 3 );

/**
 * Register local ACF field groups used by the starter theme.
 */
function mrn_base_stack_register_acf_field_groups() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_hero_builder',
			'title'                 => 'Hero',
			'menu_order'            => -10,
			'fields'                => array(
				array(
					'key'               => 'field_mrn_page_hero_rows',
					'label'             => 'Hero',
					'name'              => 'page_hero_rows',
					'aria-label'        => '',
					'type'              => 'flexible_content',
					'max'               => 1,
					'button_label'      => 'Add Hero Row',
					'layouts'           => array(
						'layout_mrn_hero' => array(
							'key'        => 'layout_mrn_hero',
							'name'       => 'hero',
							'label'      => 'Simple',
							'display'    => 'block',
							'sub_fields' => array(
								array(
									'key'           => 'field_mrn_hero_content_tab',
									'label'         => 'Content',
									'name'          => '',
									'aria-label'    => '',
									'type'          => 'tab',
									'placement'     => 'top',
								),
								array(
									'key'           => 'field_mrn_hero_label',
									'label'         => 'Label',
									'name'          => 'label',
									'aria-label'    => '',
									'type'          => 'text',
									'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
								),
								array(
									'key'           => 'field_mrn_hero_heading',
									'label'         => 'Heading Override',
									'name'          => 'heading',
									'aria-label'    => '',
									'type'          => 'text',
									'instructions'  => 'Leave blank to use the page or post title. Limited inline HTML allowed: span, strong, em, br.',
								),
								array(
									'key'           => 'field_mrn_hero_heading_tag',
									'label'         => 'Heading Tag',
									'name'          => 'heading_tag',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => array(
										'h1'   => 'H1',
										'h2'   => 'H2',
										'h3'   => 'H3',
										'h4'   => 'H4',
										'h5'   => 'H5',
										'h6'   => 'H6',
										'p'    => 'Paragraph',
										'span' => 'Span',
										'div'  => 'Div',
									),
									'default_value' => 'h1',
									'ui'            => 1,
								),
								array(
									'key'           => 'field_mrn_hero_content',
									'label'         => 'Text Area with Editor',
									'name'          => 'content',
									'aria-label'    => '',
									'type'          => 'wysiwyg',
									'tabs'          => 'all',
									'toolbar'       => 'full',
									'media_upload'  => 1,
									'delay'         => 0,
								),
								array(
									'key'           => 'field_mrn_hero_link',
									'label'         => 'Link',
									'name'          => 'link',
									'aria-label'    => '',
									'type'          => 'link',
									'return_format' => 'array',
								),
								array(
									'key'           => 'field_mrn_hero_image',
									'label'         => 'Image',
									'name'          => 'image',
									'aria-label'    => '',
									'type'          => 'image',
									'return_format' => 'array',
									'preview_size'  => 'medium',
									'library'       => 'all',
								),
								array(
									'key'           => 'field_mrn_hero_config_tab',
									'label'         => 'Configs',
									'name'          => '',
									'aria-label'    => '',
									'type'          => 'tab',
									'placement'     => 'top',
									'endpoint'      => 0,
								),
								array(
									'key'           => 'field_mrn_hero_background_color',
									'label'         => 'Background Color',
									'name'          => 'background_color',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Select from Site Styles colors when available.',
								),
								array(
									'key'           => 'field_mrn_hero_bottom_accent',
									'label'         => 'Bottom Accent',
									'name'          => 'bottom_accent',
									'aria-label'    => '',
									'type'          => 'true_false',
									'ui'            => 1,
									'default_value' => 0,
									'ui_on_text'    => 'On',
									'ui_off_text'   => 'Off',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_hero_bottom_accent_style',
									'label'         => 'Bottom Accent Style',
									'name'          => 'bottom_accent_style',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
									'default_value' => '',
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Choose a saved graphic element from Site Styles.',
									'wrapper'       => array(
										'width' => '50',
									),
								),
							),
						),
					),
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'page',
					),
				),
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			),
			'position'              => 'acf_after_title',
			'menu_order'            => -10,
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
		)
	);

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_content_builder',
			'title'                 => 'Content',
			'menu_order'            => 10,
			'fields'                => array(
				array(
					'key'               => 'field_mrn_page_content_rows',
					'label'             => 'Content',
					'name'              => 'page_content_rows',
					'aria-label'        => '',
					'type'              => 'flexible_content',
					'button_label'      => 'Add Content Row',
					'layouts'           => array(
						'layout_mrn_body_text'      => array(
							'key'        => 'layout_mrn_body_text',
							'name'       => 'body_text',
							'label'      => 'Text - label|title|text with editor',
							'display'    => 'block',
							'sub_fields' => array(
								array(
									'key'           => 'field_mrn_body_text_label',
									'label'         => 'Label',
									'name'          => 'label',
									'aria-label'    => '',
									'type'          => 'text',
									'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
								),
								array(
									'key'           => 'field_mrn_body_text_title',
									'label'         => 'Title field',
									'name'          => 'title_field',
									'aria-label'    => '',
									'type'          => 'text',
									'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_body_text_title_tag',
									'label'         => 'HTML tag for text field',
									'name'          => 'title_field_tag',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => array(
										'h1'   => 'H1',
										'h2'   => 'H2',
										'h3'   => 'H3',
										'h4'   => 'H4',
										'h5'   => 'H5',
										'h6'   => 'H6',
										'p'    => 'Paragraph',
										'span' => 'Span',
										'div'  => 'Div',
									),
									'default_value' => 'h2',
									'ui'            => 1,
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_body_text_content',
									'label'         => 'Text area with editor',
									'name'          => 'body_text',
									'aria-label'    => '',
									'type'          => 'wysiwyg',
									'tabs'          => 'all',
									'toolbar'       => 'full',
									'media_upload'  => 1,
									'delay'         => 0,
								),
								array(
									'key'           => 'field_mrn_body_text_background_color',
									'label'         => 'Background color',
									'name'          => 'background_color',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Select from Site Colors when available.',
								),
								array(
									'key'           => 'field_mrn_body_text_bottom_accent',
									'label'         => 'Bottom Accent',
									'name'          => 'bottom_accent',
									'aria-label'    => '',
									'type'          => 'true_false',
									'ui'            => 1,
									'default_value' => 0,
									'ui_on_text'    => 'On',
									'ui_off_text'   => 'Off',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_body_text_bottom_accent_style',
									'label'         => 'Bottom Accent Style',
									'name'          => 'bottom_accent_style',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
									'default_value' => '',
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Choose a saved graphic element from Site Styles.',
									'wrapper'       => array(
										'width' => '50',
									),
								),
							),
						),
						'layout_mrn_basic'          => array(
							'key'        => 'layout_mrn_basic',
							'name'       => 'basic',
							'label'      => 'Basic - label|title|text with editor|image|link',
							'display'    => 'block',
							'sub_fields' => array(
								array(
									'key'        => 'field_mrn_basic_content_tab',
									'label'      => 'Content',
									'name'       => '',
									'aria-label' => '',
									'type'       => 'tab',
									'placement'  => 'top',
								),
								array(
									'key'           => 'field_mrn_basic_label',
									'label'         => 'Label',
									'name'          => 'label',
									'aria-label'    => '',
									'type'          => 'text',
									'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
								),
								array(
									'key'           => 'field_mrn_basic_heading',
									'label'         => 'Title field',
									'name'          => 'text_field',
									'aria-label'    => '',
									'type'          => 'text',
									'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_basic_heading_tag',
									'label'         => 'HTML Tag for Text Field',
									'name'          => 'text_field_tag',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => array(
										'h1'   => 'H1',
										'h2'   => 'H2',
										'h3'   => 'H3',
										'h4'   => 'H4',
										'h5'   => 'H5',
										'h6'   => 'H6',
										'p'    => 'Paragraph',
										'span' => 'Span',
										'div'  => 'Div',
									),
									'default_value' => 'h2',
									'ui'            => 1,
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_basic_content',
									'label'         => 'Text area with editor',
									'name'          => 'content',
									'aria-label'    => '',
									'type'          => 'wysiwyg',
									'tabs'          => 'all',
									'toolbar'       => 'full',
									'media_upload'  => 1,
									'delay'         => 0,
								),
								array(
									'key'           => 'field_mrn_basic_image',
									'label'         => 'Image',
									'name'          => 'image',
									'aria-label'    => '',
									'type'          => 'image',
									'return_format' => 'array',
									'preview_size'  => 'medium',
									'library'       => 'all',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_basic_link',
									'label'         => 'Link',
									'name'          => 'link',
									'aria-label'    => '',
									'type'          => 'link',
									'return_format' => 'array',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'        => 'field_mrn_basic_config_tab',
									'label'      => 'Configs',
									'name'       => '',
									'aria-label' => '',
									'type'       => 'tab',
									'placement'  => 'top',
									'endpoint'   => 0,
								),
								array(
									'key'           => 'field_mrn_basic_link_style',
									'label'         => 'Link style',
									'name'          => 'link_style',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_rbl_get_link_style_choices' )
										? mrn_rbl_get_link_style_choices()
										: array(
											'link'   => 'Link',
											'button' => 'Button',
										),
									'default_value' => 'link',
									'ui'            => 1,
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_basic_link_color',
									'label'         => 'Link color',
									'name'          => 'link_color',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Select from Site Colors when available.',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_basic_image_placement',
									'label'         => 'Image placement',
									'name'          => 'image_placement',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => array(
										'left'  => 'Left',
										'right' => 'Right',
									),
									'default_value' => 'left',
									'ui'            => 1,
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_basic_background_color',
									'label'         => 'Background color',
									'name'          => 'background_color',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Select from Site Colors when available.',
								),
								array(
									'key'           => 'field_mrn_basic_bottom_accent',
									'label'         => 'Accent',
									'name'          => 'bottom_accent',
									'aria-label'    => '',
									'type'          => 'true_false',
									'ui'            => 1,
									'default_value' => 0,
									'ui_on_text'    => 'On',
									'ui_off_text'   => 'Off',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_basic_bottom_accent_style',
									'label'         => 'Accent Style',
									'name'          => 'bottom_accent_style',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
									'default_value' => '',
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Choose a saved graphic element from Site Styles.',
									'wrapper'       => array(
										'width' => '50',
									),
								),
							),
						),
						'layout_mrn_cta'            => array(
							'key'        => 'layout_mrn_cta',
							'name'       => 'cta',
							'label'      => 'CTA - label|title|text with editor|link',
							'display'    => 'block',
							'sub_fields' => array(
								array(
									'key'          => 'field_mrn_page_cta_fields',
									'label'        => 'CTA',
									'name'         => '',
									'aria-label'   => '',
									'type'         => 'clone',
									'clone'        => array( 'group_mrn_reusable_cta' ),
									'display'      => 'seamless',
									'layout'       => 'block',
									'prefix_label' => 0,
									'prefix_name'  => 0,
								),
							),
						),
						'layout_mrn_grid'           => array(
							'key'        => 'layout_mrn_grid',
							'name'       => 'grid',
							'label'      => 'Grid - label|title|repeater',
							'display'    => 'block',
							'sub_fields' => array(
								array(
									'key'          => 'field_mrn_page_grid_fields',
									'label'        => 'Grid',
									'name'         => '',
									'aria-label'   => '',
									'type'         => 'clone',
									'clone'        => array( 'group_mrn_reusable_content_grid' ),
									'display'      => 'seamless',
									'layout'       => 'block',
									'prefix_label' => 0,
									'prefix_name'  => 0,
								),
							),
						),
						'layout_mrn_slider'         => array(
							'key'        => 'layout_mrn_slider',
							'name'       => 'slider',
							'label'      => 'Slider - repeater',
							'display'    => 'block',
							'sub_fields' => array(
								array(
									'key'        => 'field_mrn_slider_content_tab',
									'label'      => 'Content',
									'name'       => '',
									'aria-label' => '',
									'type'       => 'tab',
									'placement'  => 'top',
								),
								array(
									'key'           => 'field_mrn_slider_label',
									'label'         => 'Label',
									'name'          => 'label',
									'aria-label'    => '',
									'type'          => 'text',
									'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
								),
								array(
									'key'           => 'field_mrn_slider_heading',
									'label'         => 'Title field',
									'name'          => 'text_field',
									'aria-label'    => '',
									'type'          => 'text',
									'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_slider_heading_tag',
									'label'         => 'HTML Tag for Text Field',
									'name'          => 'text_field_tag',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => array(
										'h1'   => 'H1',
										'h2'   => 'H2',
										'h3'   => 'H3',
										'h4'   => 'H4',
										'h5'   => 'H5',
										'h6'   => 'H6',
										'p'    => 'Paragraph',
										'span' => 'Span',
										'div'  => 'Div',
									),
									'default_value' => 'h2',
									'ui'            => 1,
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'          => 'field_mrn_slider_items',
									'label'        => 'Slides',
									'name'         => 'slider_items',
									'aria-label'   => '',
									'type'         => 'repeater',
									'layout'       => 'row',
									'collapsed'    => 'field_mrn_slider_item_heading',
									'button_label' => 'Add Slide',
									'min'          => 1,
									'sub_fields'   => array(
										array(
											'key'           => 'field_mrn_slider_item_image',
											'label'         => 'Image',
											'name'          => 'image',
											'aria-label'    => '',
											'type'          => 'image',
											'return_format' => 'array',
											'preview_size'  => 'medium',
											'library'       => 'all',
										),
										array(
											'key'           => 'field_mrn_slider_item_label',
											'label'         => 'Label',
											'name'          => 'label',
											'aria-label'    => '',
											'type'          => 'text',
											'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
										),
										array(
											'key'           => 'field_mrn_slider_item_heading',
											'label'         => 'Title field',
											'name'          => 'title',
											'aria-label'    => '',
											'type'          => 'text',
											'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
											'wrapper'       => array(
												'width' => '50',
											),
										),
										array(
											'key'           => 'field_mrn_slider_item_heading_tag',
											'label'         => 'HTML Tag for Title Field',
											'name'          => 'title_tag',
											'aria-label'    => '',
											'type'          => 'select',
											'choices'       => array(
												'h1'   => 'H1',
												'h2'   => 'H2',
												'h3'   => 'H3',
												'h4'   => 'H4',
												'h5'   => 'H5',
												'h6'   => 'H6',
												'p'    => 'Paragraph',
												'span' => 'Span',
												'div'  => 'Div',
											),
											'default_value' => 'h3',
											'ui'            => 1,
											'wrapper'       => array(
												'width' => '50',
											),
										),
										array(
											'key'          => 'field_mrn_slider_item_content',
											'label'        => 'Text area with editor',
											'name'         => 'content',
											'aria-label'   => '',
											'type'         => 'wysiwyg',
											'tabs'         => 'all',
											'toolbar'      => 'full',
											'media_upload' => 1,
											'delay'        => 0,
										),
										array(
											'key'           => 'field_mrn_slider_item_link',
											'label'         => 'Link',
											'name'          => 'link',
											'aria-label'    => '',
											'type'          => 'link',
											'return_format' => 'array',
										),
									),
								),
								array(
									'key'        => 'field_mrn_slider_config_tab',
									'label'      => 'Configs',
									'name'       => '',
									'aria-label' => '',
									'type'       => 'tab',
									'placement'  => 'top',
									'endpoint'   => 0,
								),
								array(
									'key'           => 'field_mrn_slider_link_style',
									'label'         => 'Link style',
									'name'          => 'link_style',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_rbl_get_link_style_choices' )
										? mrn_rbl_get_link_style_choices()
										: array(
											'link'   => 'Link',
											'button' => 'Button',
										),
									'default_value' => 'link',
									'ui'            => 1,
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_slider_link_color',
									'label'         => 'Link color',
									'name'          => 'link_color',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Select from Site Colors when available.',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_slider_background_color',
									'label'         => 'Background color',
									'name'          => 'background_color',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Select from Site Colors when available.',
								),
								array(
									'key'           => 'field_mrn_slider_bottom_accent',
									'label'         => 'Accent',
									'name'          => 'bottom_accent',
									'aria-label'    => '',
									'type'          => 'true_false',
									'ui'            => 1,
									'default_value' => 0,
									'ui_on_text'    => 'On',
									'ui_off_text'   => 'Off',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_slider_bottom_accent_style',
									'label'         => 'Accent Style',
									'name'          => 'bottom_accent_style',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
									'default_value' => '',
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Choose a saved graphic element from Site Styles.',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_slider_per_page',
									'label'         => 'Slides per view',
									'name'          => 'per_page',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => array(
										'1' => '1',
										'2' => '2',
										'3' => '3',
									),
									'default_value' => '1',
									'ui'            => 1,
									'wrapper'       => array(
										'width' => '25',
									),
								),
								array(
									'key'           => 'field_mrn_slider_show_arrows',
									'label'         => 'Show arrows',
									'name'          => 'show_arrows',
									'aria-label'    => '',
									'type'          => 'true_false',
									'ui'            => 1,
									'default_value' => 1,
									'ui_on_text'    => 'On',
									'ui_off_text'   => 'Off',
									'wrapper'       => array(
										'width' => '25',
									),
								),
								array(
									'key'           => 'field_mrn_slider_show_pagination',
									'label'         => 'Show pagination',
									'name'          => 'show_pagination',
									'aria-label'    => '',
									'type'          => 'true_false',
									'ui'            => 1,
									'default_value' => 1,
									'ui_on_text'    => 'On',
									'ui_off_text'   => 'Off',
									'wrapper'       => array(
										'width' => '25',
									),
								),
								array(
									'key'           => 'field_mrn_slider_pause_on_hover',
									'label'         => 'Pause on hover',
									'name'          => 'pause_on_hover',
									'aria-label'    => '',
									'type'          => 'true_false',
									'ui'            => 1,
									'default_value' => 1,
									'ui_on_text'    => 'On',
									'ui_off_text'   => 'Off',
									'wrapper'       => array(
										'width' => '25',
									),
								),
								array(
									'key'           => 'field_mrn_slider_autoplay',
									'label'         => 'Autoplay',
									'name'          => 'autoplay',
									'aria-label'    => '',
									'type'          => 'true_false',
									'ui'            => 1,
									'default_value' => 0,
									'ui_on_text'    => 'On',
									'ui_off_text'   => 'Off',
									'wrapper'       => array(
										'width' => '25',
									),
								),
								array(
									'key'           => 'field_mrn_slider_delay_start',
									'label'         => 'Delay start',
									'name'          => 'delay_start',
									'aria-label'    => '',
									'type'          => 'number',
									'default_value' => 0,
									'min'           => 0,
									'step'          => 0.5,
									'instructions'  => 'Seconds to wait before autoplay begins.',
									'wrapper'       => array(
										'width' => '33',
									),
								),
								array(
									'key'           => 'field_mrn_slider_delay_time',
									'label'         => 'Delay time',
									'name'          => 'delay_time',
									'aria-label'    => '',
									'type'          => 'number',
									'default_value' => 5,
									'min'           => 1,
									'step'          => 0.5,
									'instructions'  => 'Seconds each slide stays visible during autoplay.',
									'wrapper'       => array(
										'width' => '33',
									),
								),
								array(
									'key'           => 'field_mrn_slider_time_on_slide',
									'label'         => 'Time on slide',
									'name'          => 'time_on_slide',
									'aria-label'    => '',
									'type'          => 'number',
									'default_value' => 600,
									'min'           => 100,
									'step'          => 50,
									'instructions'  => 'Transition speed in milliseconds.',
									'wrapper'       => array(
										'width' => '34',
									),
								),
							),
						),
						'layout_mrn_image_content'  => array(
							'key'        => 'layout_mrn_image_content',
							'name'       => 'image_content',
							'label'      => 'Image - label|title|text with editor',
							'display'    => 'block',
							'sub_fields' => array(
								array(
									'key'        => 'field_mrn_image_content_tab',
									'label'      => 'Content',
									'name'       => '',
									'aria-label' => '',
									'type'       => 'tab',
									'placement'  => 'top',
								),
								array(
									'key'           => 'field_mrn_image_content_image',
									'label'         => 'Image',
									'name'          => 'image',
									'aria-label'    => '',
									'type'          => 'image',
									'return_format' => 'array',
									'preview_size'  => 'large',
									'library'       => 'all',
								),
								array(
									'key'           => 'field_mrn_image_content_label',
									'label'         => 'Label',
									'name'          => 'label',
									'aria-label'    => '',
									'type'          => 'text',
									'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
								),
								array(
									'key'           => 'field_mrn_image_content_heading',
									'label'         => 'Title field',
									'name'          => 'text_field',
									'aria-label'    => '',
									'type'          => 'text',
									'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_image_content_heading_tag',
									'label'         => 'HTML Tag for Text Field',
									'name'          => 'text_field_tag',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => array(
										'h1'   => 'H1',
										'h2'   => 'H2',
										'h3'   => 'H3',
										'h4'   => 'H4',
										'h5'   => 'H5',
										'h6'   => 'H6',
										'p'    => 'Paragraph',
										'span' => 'Span',
										'div'  => 'Div',
									),
									'default_value' => 'h2',
									'ui'            => 1,
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'          => 'field_mrn_image_content_text',
									'label'        => 'Text area with editor',
									'name'         => 'content',
									'aria-label'   => '',
									'type'         => 'wysiwyg',
									'tabs'         => 'all',
									'toolbar'      => 'full',
									'media_upload' => 1,
									'delay'        => 0,
								),
								array(
									'key'        => 'field_mrn_image_content_config_tab',
									'label'      => 'Configs',
									'name'       => '',
									'aria-label' => '',
									'type'       => 'tab',
									'placement'  => 'top',
									'endpoint'   => 0,
								),
								array(
									'key'           => 'field_mrn_image_content_background_color',
									'label'         => 'Background color',
									'name'          => 'background_color',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Select from Site Colors when available.',
								),
								array(
									'key'           => 'field_mrn_image_content_bottom_accent',
									'label'         => 'Accent',
									'name'          => 'bottom_accent',
									'aria-label'    => '',
									'type'          => 'true_false',
									'ui'            => 1,
									'default_value' => 0,
									'ui_on_text'    => 'On',
									'ui_off_text'   => 'Off',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_image_content_bottom_accent_style',
									'label'         => 'Accent Style',
									'name'          => 'bottom_accent_style',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
									'default_value' => '',
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Choose a saved graphic element from Site Styles.',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_image_content_full_width',
									'label'         => 'Full width',
									'name'          => 'full_width',
									'aria-label'    => '',
									'type'          => 'true_false',
									'ui'            => 1,
									'default_value' => 0,
									'ui_on_text'    => 'On',
									'ui_off_text'   => 'Off',
									'wrapper'       => array(
										'width' => '25',
									),
								),
								array(
									'key'           => 'field_mrn_image_content_position',
									'label'         => 'Image position',
									'name'          => 'image_position',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => array(
										'top'    => 'Top',
										'bottom' => 'Bottom',
									),
									'default_value' => 'top',
									'ui'            => 1,
									'wrapper'       => array(
										'width' => '25',
									),
								),
								array(
									'key'           => 'field_mrn_image_content_size',
									'label'         => 'Image size',
									'name'          => 'image_size',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => array(
										'contained' => 'Contained',
										'cover'     => 'Cover',
									),
									'default_value' => 'contained',
									'ui'            => 1,
									'wrapper'       => array(
										'width' => '25',
									),
								),
								array(
									'key'           => 'field_mrn_image_content_alignment',
									'label'         => 'Image alignment',
									'name'          => 'image_alignment',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => array(
										'left'   => 'Left',
										'center' => 'Center',
										'right'  => 'Right',
									),
									'default_value' => 'center',
									'ui'            => 1,
									'wrapper'       => array(
										'width' => '25',
									),
								),
							),
						),
						'layout_mrn_external_widget' => array(
							'key'        => 'layout_mrn_external_widget',
							'name'       => 'external_widget',
							'label'      => 'External - widget/iFrame',
							'display'    => 'block',
							'sub_fields' => array(
								array(
									'key'        => 'field_mrn_external_widget_content_tab',
									'label'      => 'Content',
									'name'       => '',
									'aria-label' => '',
									'type'       => 'tab',
									'placement'  => 'top',
								),
								array(
									'key'          => 'field_mrn_external_widget_code',
									'label'        => 'Snippet/Code',
									'name'         => 'embed_code',
									'aria-label'   => '',
									'type'         => 'textarea',
									'rows'         => 8,
									'new_lines'    => '',
									'instructions' => 'Paste trusted widget, iframe, or embed snippet markup.',
								),
								array(
									'key'        => 'field_mrn_external_widget_config_tab',
									'label'      => 'Configs',
									'name'       => '',
									'aria-label' => '',
									'type'       => 'tab',
									'placement'  => 'top',
									'endpoint'   => 0,
								),
								array(
									'key'           => 'field_mrn_external_widget_background_color',
									'label'         => 'Background color',
									'name'          => 'background_color',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Select from Site Colors when available.',
								),
								array(
									'key'           => 'field_mrn_external_widget_bottom_accent',
									'label'         => 'Accent',
									'name'          => 'bottom_accent',
									'aria-label'    => '',
									'type'          => 'true_false',
									'ui'            => 1,
									'default_value' => 0,
									'ui_on_text'    => 'On',
									'ui_off_text'   => 'Off',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_external_widget_bottom_accent_style',
									'label'         => 'Accent Style',
									'name'          => 'bottom_accent_style',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
									'default_value' => '',
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Choose a saved graphic element from Site Styles.',
									'wrapper'       => array(
										'width' => '50',
									),
								),
							),
						),
						'layout_mrn_card'           => array(
							'key'        => 'layout_mrn_card',
							'name'       => 'card',
							'label'      => 'Card - image|text|link',
							'display'    => 'block',
							'sub_fields' => array(
								array(
									'key'        => 'field_mrn_card_content_tab',
									'label'      => 'Content',
									'name'       => '',
									'aria-label' => '',
									'type'       => 'tab',
									'placement'  => 'top',
								),
								array(
									'key'        => 'field_mrn_card_heading',
									'label'      => 'Text Field',
									'name'       => 'text_field',
									'aria-label' => '',
									'type'       => 'text',
									'instructions'=> 'Limited inline HTML allowed: span, strong, em, br.',
									'wrapper'    => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_card_heading_tag',
									'label'         => 'HTML Tag for Text Field',
									'name'          => 'text_field_tag',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => array(
										'h1'   => 'H1',
										'h2'   => 'H2',
										'h3'   => 'H3',
										'h4'   => 'H4',
										'h5'   => 'H5',
										'h6'   => 'H6',
										'p'    => 'Paragraph',
										'span' => 'Span',
										'div'  => 'Div',
									),
									'default_value' => 'h2',
									'ui'            => 1,
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'          => 'field_mrn_card_items',
									'label'        => 'Cards',
									'name'         => 'card_items',
									'aria-label'   => '',
									'type'         => 'repeater',
									'layout'       => 'row',
									'collapsed'    => 'field_mrn_card_item_text',
									'button_label' => 'Add Card',
									'min'          => 1,
									'sub_fields'   => array(
										array(
											'key'           => 'field_mrn_card_item_image',
											'label'         => 'Image',
											'name'          => 'image',
											'aria-label'    => '',
											'type'          => 'image',
											'return_format' => 'array',
											'preview_size'  => 'medium',
											'library'       => 'all',
											'wrapper'       => array(
												'width' => '33',
											),
										),
										array(
											'key'          => 'field_mrn_card_item_text',
											'label'        => 'Text',
											'name'         => 'text',
											'aria-label'   => '',
											'type'         => 'wysiwyg',
											'tabs'         => 'all',
											'toolbar'      => 'full',
											'media_upload' => 1,
											'delay'        => 0,
											'wrapper'      => array(
												'width' => '34',
											),
										),
										array(
											'key'           => 'field_mrn_card_item_link',
											'label'         => 'Link',
											'name'          => 'link',
											'aria-label'    => '',
											'type'          => 'link',
											'return_format' => 'array',
											'wrapper'       => array(
												'width' => '33',
											),
										),
									),
								),
								array(
									'key'           => 'field_mrn_card_link',
									'label'         => 'Link',
									'name'          => 'link',
									'aria-label'    => '',
									'type'          => 'link',
									'return_format' => 'array',
								),
								array(
									'key'        => 'field_mrn_card_config_tab',
									'label'      => 'Configs',
									'name'       => '',
									'aria-label' => '',
									'type'       => 'tab',
									'placement'  => 'top',
								),
								array(
									'key'           => 'field_mrn_card_background_color',
									'label'         => 'Background Color',
									'name'          => 'background_color',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Select from Site Colors when available.',
								),
								array(
									'key'           => 'field_mrn_card_bottom_accent',
									'label'         => 'Accent',
									'name'          => 'bottom_accent',
									'aria-label'    => '',
									'type'          => 'true_false',
									'ui'            => 1,
									'default_value' => 0,
									'ui_on_text'    => 'On',
									'ui_off_text'   => 'Off',
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_card_bottom_accent_style',
									'label'         => 'Bottom Accent Style',
									'name'          => 'bottom_accent_style',
									'aria-label'    => '',
									'type'          => 'select',
									'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
									'default_value' => '',
									'ui'            => 1,
									'allow_null'    => 1,
									'instructions'  => 'Choose a saved graphic element from Site Styles.',
									'wrapper'       => array(
										'width' => '50',
									),
								),
							),
						),
						'layout_mrn_two_column_split' => array(
							'key'        => 'layout_mrn_two_column_split',
							'name'       => 'two_column_split',
							'label'      => 'Two Column Split',
							'display'    => 'block',
							'sub_fields' => array(
								array(
									'key'        => 'field_mrn_two_column_split_content_tab',
									'label'      => 'Content',
									'name'       => '',
									'aria-label' => '',
									'type'       => 'tab',
									'placement'  => 'top',
								),
								array(
									'key'           => 'field_mrn_two_column_split_left_column',
									'label'         => 'Left Column',
									'name'          => 'left_column_rows',
									'aria-label'    => '',
									'type'          => 'flexible_content',
									'button_label'  => 'Add Left Layout',
									'max'           => 1,
									'layouts'       => mrn_base_stack_get_two_column_nested_layouts(),
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'           => 'field_mrn_two_column_split_right_column',
									'label'         => 'Right Column',
									'name'          => 'right_column_rows',
									'aria-label'    => '',
									'type'          => 'flexible_content',
									'button_label'  => 'Add Right Layout',
									'max'           => 1,
									'layouts'       => mrn_base_stack_get_two_column_nested_layouts(),
									'wrapper'       => array(
										'width' => '50',
									),
								),
								array(
									'key'        => 'field_mrn_two_column_split_config_tab',
									'label'      => 'Configs',
									'name'       => '',
									'aria-label' => '',
									'type'       => 'tab',
									'placement'  => 'top',
								),
								array(
									'key'           => 'field_mrn_two_column_split_ratio',
									'label'         => 'Column Split',
									'name'          => 'column_ratio',
									'aria-label'    => '',
									'type'          => 'select',
									'default_value' => '50-50',
									'choices'       => array(
										'50-50' => '50 / 50',
										'60-40' => '60 / 40',
										'40-60' => '40 / 60',
										'67-33' => '67 / 33',
										'33-67' => '33 / 67',
									),
									'ui'            => 1,
								),
							),
						),
						'layout_mrn_reusable_block' => array(
							'key'        => 'layout_mrn_reusable_block',
							'name'       => 'reusable_block',
							'label'      => 'Reusable Block',
							'display'    => 'block',
							'sub_fields' => array(
								array(
									'key'               => 'field_mrn_reusable_block_post',
									'label'             => 'Block',
									'name'              => 'block',
									'aria-label'        => '',
									'type'              => 'post_object',
									'post_type'         => function_exists( 'mrn_rbl_get_post_types' ) ? mrn_rbl_get_post_types() : array(),
									'return_format'     => 'object',
									'ui'                => 1,
									'allow_null'        => 0,
									'multiple'          => 0,
									'instructions'      => 'Choose a reusable block from the library. Editing that block updates it everywhere it is used.',
								),
							),
						),
						'layout_mrn_cta_block'      => array(
							'key'        => 'layout_mrn_cta_block',
							'name'       => 'cta_block',
							'label'      => 'CTA (Page Only)',
							'display'    => 'block',
							'sub_fields' => array(
								array(
									'key'          => 'field_mrn_page_cta_block_fields',
									'label'        => 'CTA',
									'name'         => '',
									'aria-label'   => '',
									'type'         => 'clone',
									'clone'        => array( 'group_mrn_reusable_cta' ),
									'display'      => 'seamless',
									'layout'       => 'block',
									'prefix_label' => 0,
									'prefix_name'  => 0,
								),
							),
						),
						'layout_mrn_basic_block'    => array(
							'key'        => 'layout_mrn_basic_block',
							'name'       => 'basic_block',
							'label'      => 'Basic Block (Page Only)',
							'display'    => 'block',
							'sub_fields' => array(
								array(
									'key'          => 'field_mrn_page_basic_block_fields',
									'label'        => 'Basic Block',
									'name'         => '',
									'aria-label'   => '',
									'type'         => 'clone',
									'clone'        => array( 'group_mrn_reusable_basic_block' ),
									'display'      => 'seamless',
									'layout'       => 'block',
									'prefix_label' => 0,
									'prefix_name'  => 0,
								),
							),
						),
						'layout_mrn_content_grid'   => array(
							'key'        => 'layout_mrn_content_grid',
							'name'       => 'content_grid',
							'label'      => 'Content Grid (Page Only)',
							'display'    => 'block',
							'sub_fields' => array(
								array(
									'key'          => 'field_mrn_page_content_grid_fields',
									'label'        => 'Content Grid',
									'name'         => '',
									'aria-label'   => '',
									'type'         => 'clone',
									'clone'        => array( 'group_mrn_reusable_content_grid' ),
									'display'      => 'seamless',
									'layout'       => 'block',
									'prefix_label' => 0,
									'prefix_name'  => 0,
								),
							),
						),
						'layout_mrn_faq_block'      => array(
							'key'        => 'layout_mrn_faq_block',
							'name'       => 'faq_block',
							'label'      => 'FAQ (Page Only)',
							'display'    => 'block',
							'sub_fields' => array(
								array(
									'key'          => 'field_mrn_page_faq_block_fields',
									'label'        => 'FAQ',
									'name'         => '',
									'aria-label'   => '',
									'type'         => 'clone',
									'clone'        => array( 'group_mrn_reusable_faq' ),
									'display'      => 'seamless',
									'layout'       => 'block',
									'prefix_label' => 0,
									'prefix_name'  => 0,
								),
							),
						),
					),
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'page',
					),
				),
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			),
			'menu_order'            => 10,
			'position'              => 'acf_after_title',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => array(
				'the_content',
				'excerpt',
			),
			'active'                => true,
			'description'           => 'Universal starter content builder for posts and pages.',
			'show_in_rest'          => 1,
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_acf_field_groups' );

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

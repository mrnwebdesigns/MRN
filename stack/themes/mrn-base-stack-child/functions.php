<?php
/**
 * Child theme bootstrap for mrn-base-stack.
 *
 * @package mrn-base-stack-child
 */

if ( ! defined( 'MRN_BASE_STACK_CHILD_VERSION' ) ) {
	define( 'MRN_BASE_STACK_CHILD_VERSION', '1.0.0' );
}

if ( ! function_exists( 'mrn_base_stack_child_enqueue_styles' ) ) {
	/**
	 * Load parent and child styles in deterministic order.
	 *
	 * The parent theme currently registers `mrn-base-stack-style` using
	 * `get_stylesheet_uri()`. In a child-theme context that points to the child
	 * stylesheet, so this callback re-binds the parent handle to the parent
	 * stylesheet and then layers child overrides on top.
	 *
	 * @return void
	 */
	function mrn_base_stack_child_enqueue_styles() {
		$parent_theme = wp_get_theme( get_template() );
		$parent_ver   = $parent_theme instanceof WP_Theme ? $parent_theme->get( 'Version' ) : '';
		$parent_ver   = is_string( $parent_ver ) && '' !== $parent_ver ? $parent_ver : null;

		wp_dequeue_style( 'mrn-base-stack-style' );
		wp_deregister_style( 'mrn-base-stack-style' );

		wp_enqueue_style(
			'mrn-base-stack-style',
			get_template_directory_uri() . '/style.css',
			array(),
			$parent_ver
		);
		wp_style_add_data( 'mrn-base-stack-style', 'rtl', 'replace' );

		wp_enqueue_style(
			'mrn-base-stack-child-style',
			get_stylesheet_uri(),
			array( 'mrn-base-stack-style' ),
			MRN_BASE_STACK_CHILD_VERSION
		);
	}
}
add_action( 'wp_enqueue_scripts', 'mrn_base_stack_child_enqueue_styles', 20 );

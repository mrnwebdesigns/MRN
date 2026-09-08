<?php
/**
 * Child theme bootstrap for mrn-base-stack.
 *
 * @package mrn-base-stack-child
 */

if ( ! defined( 'MRN_BASE_STACK_CHILD_VERSION' ) ) {
	define( 'MRN_BASE_STACK_CHILD_VERSION', '1.1.0' );
}

if ( ! function_exists( 'mrn_base_stack_child_enqueue_styles' ) ) {
	/**
	 * Load parent and child styles in deterministic order.
	 *
	 * Re-bind the parent handle to the parent stylesheet and then layer child
	 * overrides on top. Run late so site-level overrides stay authoritative
	 * after parent layout assets and updates.
	 *
	 * @return void
	 */
	function mrn_base_stack_child_enqueue_styles() {
		$parent_theme      = wp_get_theme( get_template() );
		$parent_theme_ver  = $parent_theme instanceof WP_Theme ? $parent_theme->get( 'Version' ) : '';
		$parent_theme_ver  = is_string( $parent_theme_ver ) && '' !== $parent_theme_ver ? $parent_theme_ver : ( defined( '_S_VERSION' ) ? _S_VERSION : '1.0.0' );
		$parent_style_path = get_template_directory() . '/style.css';
		$child_style_path  = get_stylesheet_directory() . '/style.css';
		$parent_ver        = file_exists( $parent_style_path ) ? $parent_theme_ver . '-' . (string) filemtime( $parent_style_path ) : $parent_theme_ver;
		$child_ver         = file_exists( $child_style_path ) ? MRN_BASE_STACK_CHILD_VERSION . '-' . (string) filemtime( $child_style_path ) : MRN_BASE_STACK_CHILD_VERSION;

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
			$child_ver
		);
	}
}
add_action( 'wp_enqueue_scripts', 'mrn_base_stack_child_enqueue_styles', 999 );

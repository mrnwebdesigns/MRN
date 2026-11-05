<?php
/**
 * Functions which enhance the theme by hooking into WordPress
 *
 * @package mrn-base-stack
 */

/**
 * Adds custom classes to the array of body classes.
 *
 * @param array $classes Classes for the body element.
 * @return array
 */
function mrn_base_stack_body_classes( $classes ) {
	// Adds a class of hfeed to non-singular pages.
	if ( ! is_singular() ) {
		$classes[] = 'hfeed';
	}

	// Adds a class of no-sidebar when there is no sidebar present.
	if ( ! is_active_sidebar( 'sidebar-1' ) ) {
		$classes[] = 'no-sidebar';
	}

	return $classes;
}
add_filter( 'body_class', 'mrn_base_stack_body_classes' );

/**
 * Return the limited inline HTML subset allowed in stack text fields.
 *
 * @return array<string, array<string, bool>>
 */
function mrn_base_stack_get_limited_inline_html_allowed_tags() {
	return array(
		'span'   => array(
			'class' => true,
		),
		'strong' => array(),
		'em'     => array(),
		'br'     => array(),
	);
}

/**
 * Sanitize a value using the stack's limited inline HTML contract.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function mrn_base_stack_sanitize_limited_inline_html( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$value = (string) $value;
	$value = preg_replace( '#<(script|style|iframe|object|embed|template|noscript)\b[^>]*>.*?</\1>#is', '', $value );
	$value = is_string( $value ) ? $value : '';

	return trim( wp_kses( $value, mrn_base_stack_get_limited_inline_html_allowed_tags() ) );
}

/**
 * Preserve allowed inline HTML in the WordPress General Settings tagline field.
 *
 * Core sanitization can strip markup before the option-specific filter runs,
 * so prefer the original submitted value and apply the stack contract here.
 *
 * @param mixed      $value          Sanitized option value from WordPress.
 * @param string     $option         Option name.
 * @param mixed|null $original_value Original submitted value.
 * @return string
 */
function mrn_base_stack_sanitize_blogdescription_option( $value, $option = '', $original_value = null ) {
	unset( $option );

	$source = null !== $original_value ? $original_value : $value;

	return mrn_base_stack_sanitize_limited_inline_html( $source );
}
add_filter( 'sanitize_option_blogdescription', 'mrn_base_stack_sanitize_blogdescription_option', 10, 3 );

/**
 * Return the render-ready site tagline.
 *
 * @return string
 */
function mrn_base_stack_get_site_tagline() {
	return mrn_base_stack_sanitize_limited_inline_html( get_option( 'blogdescription', '' ) );
}

/**
 * Keep the site tagline out of the generated document title.
 *
 * @param array<string, string> $title Document title parts.
 * @return array<string, string>
 */
function mrn_base_stack_filter_document_title_parts( $title ) {
	$tagline_text = trim( wp_strip_all_tags( mrn_base_stack_get_site_tagline() ) );

	unset( $title['tagline'] );

	if ( isset( $title['title'] ) ) {
		$title_text = trim( wp_strip_all_tags( (string) $title['title'] ) );

		if ( '' !== $tagline_text && $tagline_text === $title_text ) {
			if ( is_singular() ) {
				$title['title'] = single_post_title( '', false );
			} elseif ( is_home() && ! is_front_page() ) {
				$posts_page_id  = (int) get_option( 'page_for_posts' );
				$title['title'] = $posts_page_id > 0 ? get_the_title( $posts_page_id ) : '';
			} elseif ( is_front_page() ) {
				$title['title'] = get_bloginfo( 'name', 'display' );
			}
		}

		$title['title'] = trim( wp_strip_all_tags( (string) $title['title'] ) );
	}

	if ( isset( $title['site'] ) ) {
		$title['site'] = trim( wp_strip_all_tags( (string) $title['site'] ) );
	}

	return $title;
}
add_filter( 'document_title_parts', 'mrn_base_stack_filter_document_title_parts', 20 );

/**
 * Render tagline guidance with core's existing description, before first paint.
 * Keep the native aria-describedby target and the localized core example.
 */
add_action( 'load-options-general.php', function () {
	add_filter( 'gettext_default', function ( $translation, $text ) {
		if ( 'In a few words, explain what this site is about. Example: &#8220;%s.&#8221;' !== $text ) {
			return $translation;
		}
		return esc_html__( 'Limited inline HTML allowed: span, strong, em, br. The tagline never controls the page title.', 'mrn-base-stack' ) . '<br>' . $translation;
	}, 10, 2 );
} );

/**
 * Add a pingback url auto-discovery header for single posts, pages, or attachments.
 */
function mrn_base_stack_pingback_header() {
	if ( is_singular() && pings_open() ) {
		printf( '<link rel="pingback" href="%s">', esc_url( get_bloginfo( 'pingback_url' ) ) );
	}
}
add_action( 'wp_head', 'mrn_base_stack_pingback_header' );

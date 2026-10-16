<?php
/**
 * Native SEOPress cookie preferences presentation.
 *
 * @package mrn-base-stack
 */

/**
 * Get the cookie icon through the Stack's shared icon renderer.
 *
 * @return string Escaped decorative markup, or an empty string without assets.
 */
function mrn_base_stack_get_cookie_preferences_icon_markup() {
	if ( ! function_exists( 'mrn_shared_assets_enqueue_fontawesome' ) || ! function_exists( 'mrn_base_stack_get_button_link_icon_markup' ) ) {
		return '';
	}

	return mrn_base_stack_get_button_link_icon_markup(
		array(
			'link_icon_source'   => 'fontawesome',
			'link_icon_fa_class' => 'fa-solid fa-cookie-bite',
		)
	);
}

/**
 * Load presentation only when SEOPress enables its native reopen control.
 *
 * @return bool Whether the shared Font Awesome bundle is needed.
 */
function mrn_base_stack_enqueue_cookie_preferences_style() {
	if ( ! function_exists( 'seopress_get_service' ) || ! function_exists( 'seopress_cookies_edit_choice_html' ) ) {
		return false;
	}

	$options = seopress_get_service( 'GoogleAnalyticsOption' );
	if ( '1' !== $options->getDisable() || '1' !== $options->getOptOutEditChoice() || '' === mrn_base_stack_get_cookie_preferences_icon_markup() ) {
		return false;
	}

	$path = get_template_directory() . '/css/cookie-preferences.css';
	wp_enqueue_style( 'mrn-base-stack-cookie-preferences', get_template_directory_uri() . '/css/cookie-preferences.css', array( 'mrn-base-stack-style' ), _S_VERSION . '-' . (string) filemtime( $path ) );

	return true;
}

/**
 * Keep SEOPress's native button, label and click handler; change presentation.
 *
 * @param string $html Native edit-choice markup.
 * @param string $label Configured, potentially translated label.
 * @return string
 */
function mrn_base_stack_cookie_preferences_icon( $html, $label ) {
	$icon = mrn_base_stack_get_cookie_preferences_icon_markup();
	if ( '' === $icon ) {
		return $html;
	}

	$native_button = '<button id="seopress-user-consent-edit" type="button">' . $label . '</button>';
	$icon_button   = '<button id="seopress-user-consent-edit" class="mrn-cookie-preferences-button" type="button">' . $icon . '<span class="screen-reader-text">' . esc_html( wp_strip_all_tags( $label ) ) . '</span></button>';

	// Leave third-party replacement controls untouched when the native markup differs.
	return str_replace( $native_button, $icon_button, $html );
}
add_filter( 'seopress_rgpd_edit_message', 'mrn_base_stack_cookie_preferences_icon', 10, 2 );

<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness for rendered social-link asset discovery.
/**
 * Focused regression coverage for rendered social-link icon asset needs.
 *
 * @package mrn-base-stack
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['mrn_social_link_test_rows'] = array(
	array(
		'url'       => 'https://linkedin.example.test',
		'name'      => 'LinkedIn',
		'icon_type' => 'dashicons',
		'dashicon'  => 'dashicons-linkedin',
	),
	array(
		'url'       => 'https://facebook.example.test',
		'name'      => 'Facebook',
		'icon_type' => 'fontawesome',
		'fa_class'  => 'fa-brands fa-facebook-f',
	),
	array(
		'url'       => 'https://text-only.example.test',
		'name'      => 'Text only',
		'icon_type' => 'dashicons',
		'dashicon'  => '',
	),
);

function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function esc_html__( $text, $domain = 'default' ) {
	return $text;
}

function esc_html_e( $text, $domain = 'default' ) {
	echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function esc_attr_e( $text, $domain = 'default' ) {
	echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function esc_attr_x( $text, $context = '', $domain = 'default' ) {
	return $text;
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_url( $url ) {
	return $url;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) );
}

function mrn_config_helper_get_social_links() {
	return $GLOBALS['mrn_social_link_test_rows'];
}

function mrn_rendered_social_test_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

require dirname( __DIR__, 2 ) . '/inc/frontend-assets.php';
require dirname( __DIR__, 2 ) . '/inc/template-tags.php';

$needs_fontawesome = false;
$needs_dashicons   = false;
mrn_base_stack_collect_rendered_social_link_asset_needs( false, $GLOBALS['mrn_social_link_test_rows'], $needs_fontawesome, $needs_dashicons );
mrn_rendered_social_test_assert( ! $needs_fontawesome && ! $needs_dashicons, 'disabled social menus do not request icon fonts.' );

$needs_fontawesome = false;
$needs_dashicons   = false;
mrn_base_stack_collect_rendered_social_link_asset_needs( true, $GLOBALS['mrn_social_link_test_rows'], $needs_fontawesome, $needs_dashicons );
mrn_rendered_social_test_assert( $needs_fontawesome, 'rendered Font Awesome social links request Font Awesome.' );
mrn_rendered_social_test_assert( $needs_dashicons, 'rendered Dashicon social links request Dashicons.' );

ob_start();
mrn_base_stack_render_social_links();
$markup = (string) ob_get_clean();

mrn_rendered_social_test_assert( false !== strpos( $markup, 'mrn-social-links' ), 'the social link list renders.' );
mrn_rendered_social_test_assert( false !== strpos( $markup, 'dashicons dashicons-linkedin' ), 'Dashicon social links render the expected Dashicons classes.' );
mrn_rendered_social_test_assert( false !== strpos( $markup, 'fa-brands fa-facebook-f' ), 'Font Awesome social links still render their class names.' );
mrn_rendered_social_test_assert( false === strpos( $markup, 'dashicons </span>' ), 'text-only social links do not force a Dashicons icon.' );

echo "PASS: Rendered social-link asset needs.\n";

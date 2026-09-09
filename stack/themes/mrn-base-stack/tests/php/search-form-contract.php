<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness for the stack search form contract.
/**
 * Focused regression coverage for the native-first stack search form.
 *
 * The form renders unconditionally (no search-plugin dependency) and is
 * routed through the native `get_search_form` filter so a relevance plugin
 * like Relevanssi can enhance it transparently when active, matching how
 * Relevanssi documents layering onto a theme's native form.
 *
 * Run with:
 * php stack/themes/mrn-base-stack/tests/php/search-form-contract.php
 *
 * @package mrn-base-stack
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['mrn_search_form_test_query'] = '';

function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function apply_filters( $hook_name, $value ) {
	if ( 'get_search_form' === $hook_name && isset( $GLOBALS['mrn_search_form_test_filter'] ) && is_callable( $GLOBALS['mrn_search_form_test_filter'] ) ) {
		return call_user_func( $GLOBALS['mrn_search_form_test_filter'], $value );
	}

	return $value;
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function esc_html__( $text, $domain = 'default' ) {
	return $text;
}

function esc_html_e( $text, $domain = 'default' ) {
	echo $text; // phpcs:ignore
}

function esc_attr_e( $text, $domain = 'default' ) {
	echo $text; // phpcs:ignore
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

function get_search_query() {
	return $GLOBALS['mrn_search_form_test_query'];
}

function home_url( $path = '/' ) {
	return 'https://example.test' . $path;
}

function wp_unique_id( $prefix = '' ) {
	return $prefix . '1';
}

function mrn_search_form_test_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

require dirname( __DIR__, 2 ) . '/inc/template-tags.php';

// Renders unconditionally: no plugin/config state gates it.
$markup = mrn_base_stack_get_search_form_markup();
mrn_search_form_test_assert( '' !== trim( $markup ), 'the search form renders unconditionally' );
mrn_search_form_test_assert( false !== strpos( $markup, 'name="s"' ), 'the form submits the native WordPress search query var' );
mrn_search_form_test_assert( false !== strpos( $markup, 'mrn-site-search__input' ), 'the input carries the class header-search.js targets' );

// The current query value is reflected (and escaped) back into the field.
$GLOBALS['mrn_search_form_test_query'] = 'budget "report" <script>';
$markup_with_query                     = mrn_base_stack_get_search_form_markup();
mrn_search_form_test_assert( false === strpos( $markup_with_query, '<script>' ), 'the reflected search query is escaped' );
mrn_search_form_test_assert( false !== strpos( $markup_with_query, 'value="' ), 'the reflected search query is present in the value attribute' );
$GLOBALS['mrn_search_form_test_query'] = '';

// A relevance plugin (Relevanssi and similar) enhances the native form via
// the same `get_search_form` filter WordPress core documents for this.
$GLOBALS['mrn_search_form_test_filter'] = static function ( $form ) {
	return $form . '<!-- enhanced -->';
};
$enhanced_markup = mrn_base_stack_get_search_form_markup();
mrn_search_form_test_assert( false !== strpos( $enhanced_markup, '<!-- enhanced -->' ), 'a get_search_form filter can enhance the native markup' );
unset( $GLOBALS['mrn_search_form_test_filter'] );

// The render wrapper echoes the same markup the getter returns.
ob_start();
mrn_base_stack_render_search_form_markup();
$rendered = (string) ob_get_clean();
mrn_search_form_test_assert( false !== strpos( $rendered, 'name="s"' ), 'the render wrapper echoes the same form markup' );

echo "PASS: Search form contract.\n";

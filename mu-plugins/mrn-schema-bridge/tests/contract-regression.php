<?php
/**
 * Focused contract checks for MRN Schema Bridge.
 *
 * Run: php tests/contract-regression.php
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['mrn_test_filters'] = array();
$GLOBALS['mrn_test_fields']  = array();
$GLOBALS['mrn_test_meta']    = array();

class WP_Post {
	public $ID = 0;
	public $post_type = 'page';
	public $post_name = 'sample';
	public $post_content = '';
}

function add_filter( $hook, $callback ) {
	$GLOBALS['mrn_test_filters'][ $hook ][] = $callback;
}

function add_action( $hook, $callback ) {
	add_filter( $hook, $callback );
}

function apply_filters( $hook, $value ) {
	return $value;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- WordPress stub.
}

function sanitize_email( $value ) {
	return filter_var( (string) $value, FILTER_SANITIZE_EMAIL );
}

function __( $value, $domain = '' ) {
	return (string) $value;
}

function absint( $value ) {
	return abs( (int) $value );
}

function esc_url_raw( $value ) {
	return (string) $value;
}

function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- WordPress stub.
}

function wp_json_encode( $value ) {
	return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- WordPress stub.
}

function get_field( $name ) {
	return array_key_exists( $name, $GLOBALS['mrn_test_fields'] ) ? $GLOBALS['mrn_test_fields'][ $name ] : false;
}

function get_field_object( $name ) {
	return array_key_exists( $name, $GLOBALS['mrn_test_fields'] ) ? array( 'name' => $name ) : false;
}

function get_bloginfo( $key ) {
	return 'name' === $key ? 'Example Organization' : 'UTF-8';
}

function home_url( $path = '/' ) {
	return 'https://example.com' . $path;
}

function trailingslashit( $value ) {
	return rtrim( (string) $value, '/' ) . '/';
}

function get_post( $post ) {
	return $post instanceof WP_Post ? $post : null;
}

function get_post_meta( $post_id, $key ) {
	return $GLOBALS['mrn_test_meta'][ $post_id ][ $key ] ?? '';
}

function get_option( $name, $default = false ) {
	return 'blog_public' === $name ? '1' : $default;
}

function is_admin() {
	return false;
}

require dirname( __DIR__ ) . '/mrn-schema-bridge.php';

function mrn_assert( $condition, $message ) {
	if ( ! $condition ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI-only test failure text, never rendered in WordPress.
		printf( "FAIL: %s\n", $message );
		exit( 1 );
	}
}

mrn_assert(
	in_array( 'case_study', mrn_schema_bridge_get_project_post_types(), true ),
	'Base-stack case_study must receive project schema.'
);

$GLOBALS['mrn_test_fields']['schema_author_policy'] = 'organization';
mrn_assert(
	mrn_schema_bridge_is_author_person_node(
		array(
			'@type' => 'Person',
			'@id'   => 'https://example.com/#schema-author',
			'name'  => 'Internal Editor',
		)
	),
	'Organization author policy must remove internal WordPress authors.'
);

$GLOBALS['mrn_test_fields']['schema_author_policy'] = 'public';
mrn_assert(
	! mrn_schema_bridge_is_author_person_node(
		array(
			'@type' => 'Person',
			'@id'   => 'https://example.com/#schema-author',
			'name'  => 'Public Expert',
		)
	),
	'Public author policy must preserve real authors.'
);

$GLOBALS['mrn_test_fields']['schema_ai_search_crawlers']   = true;
$GLOBALS['mrn_test_fields']['schema_ai_training_crawlers'] = false;
$robots = mrn_schema_bridge_filter_robots_txt( "User-agent: *\nAllow: /\n", true );
mrn_assert( false !== strpos( $robots, "User-agent: OAI-SearchBot\nAllow: /" ), 'AI search crawler must be allowed by default.' );
mrn_assert( false !== strpos( $robots, "User-agent: GPTBot\nDisallow: /" ), 'AI training crawler must be blocked by default.' );

$post            = new WP_Post();
$post->ID        = 42;
$post->post_type = 'page';
$GLOBALS['mrn_test_meta'][42][ MRN_SCHEMA_BRIDGE_SCHEMA_MODE_META_KEY ] = 'override';
$GLOBALS['mrn_test_meta'][42][ MRN_SCHEMA_BRIDGE_PAGE_INTENT_META_KEY ] = 'service';
mrn_assert( 'service' === mrn_schema_bridge_get_post_page_intent( $post ), 'Custom page intent must be returned in override mode.' );

$testimonial            = new WP_Post();
$testimonial->ID        = 77;
$testimonial->post_type = 'testimonial';
$testimonial_node       = mrn_schema_bridge_build_testimonial_schema_node(
	$testimonial,
	array(
		'name'        => 'Jane Customer',
		'company'     => 'Example Client',
		'position'    => 'Executive Director',
		'website_url' => 'https://client.example/',
		'content'     => '<p>The team delivered an accessible, fast website.</p>',
	),
	'https://example.com/services/'
);

mrn_assert( 'Quotation' === $testimonial_node['@type'], 'Visible testimonials must map to Quotation schema.' );
mrn_assert( 'The team delivered an accessible, fast website.' === $testimonial_node['text'], 'Testimonial schema text must be plain visible content.' );
mrn_assert( 'Jane Customer' === $testimonial_node['spokenByCharacter']['name'], 'Testimonial schema must preserve speaker attribution.' );
mrn_assert( 'Example Client' === $testimonial_node['spokenByCharacter']['worksFor']['name'], 'Testimonial schema must preserve the attributed company.' );
mrn_assert( false === strpos( (string) wp_json_encode( $testimonial_node ), 'Review' ), 'Testimonials must not emit Review or rating schema.' );

mrn_schema_bridge_rendered_testimonial_registry( array( 'node' => $testimonial_node ) );
mrn_schema_bridge_rendered_testimonial_registry( array( 'node' => $testimonial_node ) );
mrn_assert( 1 === count( mrn_schema_bridge_rendered_testimonial_registry() ), 'Repeated testimonial output must be deduplicated by schema ID.' );

echo "MRN Schema Bridge contract regression checks passed.\n";

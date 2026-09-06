<?php
/**
 * Focused contract checks for MRN Schema Bridge.
 *
 * Run: php tests/contract-regression.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SMARTCRAWL_VERSION', '3.13.3' );
define( 'SEOPRESS_VERSION', '10.2.0' );

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

function apply_filters( $hook, $value, ...$args ) {
	if ( empty( $GLOBALS['mrn_test_filters'][ $hook ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['mrn_test_filters'][ $hook ] as $callback ) {
		$value = $callback( $value, ...$args );
	}

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

function is_singular() {
	return false;
}

function get_queried_object() {
	return null;
}

function mrn_base_stack_get_business_schema_data() {
	return array(
		'description'             => 'Canonical business description',
		'logo'                    => array(
			'@type' => 'ImageObject',
			'url'   => 'https://example.com/assets/logo.png',
			'width' => 1200,
			'height' => 630,
		),
		'address'                 => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => '123 Main St',
			'addressLocality' => 'Raleigh',
			'addressRegion'   => 'NC',
			'postalCode'      => '27601',
			'addressCountry'  => 'US',
		),
		'telephone'               => '+1-919-555-0100',
		'openingHoursSpecification' => array(
			'@type'     => 'OpeningHoursSpecification',
			'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ),
		),
	);
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
	'seopress' === mrn_schema_bridge_get_active_schema_provider(),
	'SEOPress must be authoritative when SEOPress and SmartCrawl are both loaded during migration.'
);
mrn_assert(
	! mrn_schema_bridge_legacy_smartcrawl_compatibility_enabled(),
	'Legacy SmartCrawl mutations must be disabled after SEOPress becomes authoritative.'
);

$inactive_smartcrawl_options = array( 'existing' => 'preserved' );
mrn_assert(
	$inactive_smartcrawl_options === mrn_schema_bridge_filter_smartcrawl_social_options( $inactive_smartcrawl_options ),
	'Inactive SmartCrawl social options must pass through without mutation.'
);
mrn_assert(
	$inactive_smartcrawl_options === mrn_schema_bridge_filter_smartcrawl_schema_options( $inactive_smartcrawl_options ),
	'Inactive SmartCrawl schema options must pass through without mutation.'
);

add_filter(
	'mrn_schema_bridge_legacy_smartcrawl_compatibility_enabled',
	static function () {
		return true;
	}
);
mrn_assert(
	mrn_schema_bridge_legacy_smartcrawl_compatibility_enabled(),
	'Legacy SmartCrawl compatibility must remain explicitly re-enableable for rollback.'
);

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
		'content'     => '<p>The team delivered an accessible, fast website for the client&apos;s team.</p>',
	),
	'https://example.com/services/'
);

mrn_assert( 'Quotation' === $testimonial_node['@type'], 'Visible testimonials must map to Quotation schema.' );
mrn_assert( "The team delivered an accessible, fast website for the client's team." === $testimonial_node['text'], 'Testimonial schema text must be decoded plain visible content.' );
mrn_assert( 'Jane Customer' === $testimonial_node['spokenByCharacter']['name'], 'Testimonial schema must preserve speaker attribution.' );
mrn_assert( 'Example Client' === $testimonial_node['spokenByCharacter']['worksFor']['name'], 'Testimonial schema must preserve the attributed company.' );
mrn_assert( false === strpos( (string) wp_json_encode( $testimonial_node ), 'Review' ), 'Testimonials must not emit Review or rating schema.' );

mrn_schema_bridge_rendered_testimonial_registry( array( 'node' => $testimonial_node ) );
mrn_schema_bridge_rendered_testimonial_registry( array( 'node' => $testimonial_node ) );
mrn_assert( 1 === count( mrn_schema_bridge_rendered_testimonial_registry() ), 'Repeated testimonial output must be deduplicated by schema ID.' );

$GLOBALS['mrn_test_fields']['schema_organization_type'] = 'ProfessionalService';
$GLOBALS['mrn_test_fields']['schema_legal_name']       = 'Example Legal Entity LLC';
$GLOBALS['mrn_test_fields']['schema_alternate_name']   = 'Example Alt Name';
$GLOBALS['mrn_test_fields']['schema_email']            = 'info@example.com';
$GLOBALS['mrn_test_fields']['schema_area_served']      = 'North Carolina';
$GLOBALS['mrn_test_fields']['schema_latitude']         = '35.7796';
$GLOBALS['mrn_test_fields']['schema_longitude']        = '-78.6382';
$GLOBALS['mrn_test_fields']['schema_author_policy']     = 'organization';

$blog_posting = array(
	'@context'         => 'https://schema.org',
	'@type'            => 'BlogPosting',
	'headline'         => 'Blog headline',
	'description'      => 'Blog description',
	'datePublished'    => '2026-08-01T10:00:00+00:00',
	'dateModified'     => '2026-08-02T11:00:00+00:00',
	'image'            => array(
		'@type' => 'ImageObject',
		'url'   => 'https://example.com/uploads/blog-image.jpg',
	),
	'mainEntityOfPage' => array(
		'@type' => 'WebPage',
		'@id'   => 'https://example.com/blog/sample-post/',
	),
	'author'           => array(
		'@type' => 'Person',
		'@id'   => 'https://example.com/author/mrn-admin/#person',
		'name'  => 'mrn-admin',
		'url'   => 'https://https://example.com/author/mrn-admin/',
	),
	'publisher'        => array(
		'@type' => 'Organization',
		'name'  => 'SEOPress Publisher',
		'url'   => 'https://https://example.com',
		'logo'  => array(
			'@type' => 'ImageObject',
			'url'   => 'https://example.com/uploads/seopress-logo.png',
		),
	),
);

$news_article = array(
	'@context'         => 'https://schema.org',
	'@type'            => 'NewsArticle',
	'headline'         => 'News headline',
	'description'      => 'News description',
	'datePublished'    => '2026-08-03T12:00:00+00:00',
	'dateModified'     => '2026-08-04T13:00:00+00:00',
	'image'            => array(
		'@type' => 'ImageObject',
		'url'   => 'https://example.com/uploads/news-image.jpg',
	),
	'mainEntityOfPage' => array(
		'@type' => 'WebPage',
		'@id'   => 'https://example.com/news/sample-story/',
	),
	'author'           => array(
		'@type' => 'Person',
		'@id'   => 'https://example.com/author/public-reporter/#person',
		'name'  => 'Public Reporter',
		'url'   => 'https://https://example.com/author/public-reporter/',
	),
	'publisher'        => array(
		'@type' => 'Organization',
		'name'  => 'SEOPress Publisher',
		'url'   => 'https://example.com',
	),
);

$normalized_payload = mrn_schema_bridge_filter_seopress_json_schema_generator_get_jsons(
	array(
		'article'     => $blog_posting,
		'supplemental' => array(
			'@type' => 'Service',
			'name'  => 'Keep me',
		),
	)
);

mrn_assert( isset( $normalized_payload['article'] ), 'Assembled SEOPress payload must keep the article node.' );
mrn_assert(
	array( '@id' => 'https://example.com/#organization' ) === $normalized_payload['article']['author'],
	'Non-public BlogPosting authors must be replaced with the canonical organization reference.'
);
mrn_assert(
	'https://example.com/#organization' === $normalized_payload['article']['publisher']['@id'],
	'BlogPosting publisher must receive the canonical organization ID.'
);
mrn_assert(
	'Example Legal Entity LLC' === $normalized_payload['article']['publisher']['legalName'],
	'BlogPosting publisher must inherit canonical legal name data.'
);
mrn_assert(
	'Example Alt Name' === $normalized_payload['article']['publisher']['alternateName'],
	'BlogPosting publisher must inherit canonical alternate name data.'
);
mrn_assert(
	'info@example.com' === $normalized_payload['article']['publisher']['email'],
	'BlogPosting publisher must inherit canonical email data.'
);
mrn_assert(
	'North Carolina' === $normalized_payload['article']['publisher']['areaServed'],
	'BlogPosting publisher must inherit canonical area served data.'
);
mrn_assert(
	'Canonical business description' === $normalized_payload['article']['publisher']['description'],
	'BlogPosting publisher must inherit canonical business description data.'
);
mrn_assert(
	'https://example.com/uploads/blog-image.jpg' === $normalized_payload['article']['image']['url'],
	'BlogPosting image URL must be preserved.'
);
mrn_assert(
	'https://example.com/assets/logo.png' === $normalized_payload['article']['publisher']['logo']['url'],
	'BlogPosting publisher logo must be taken from canonical Business Information data.'
);
mrn_assert(
	'https://example.com/blog/sample-post/' === $normalized_payload['article']['mainEntityOfPage']['@id'],
	'BlogPosting mainEntityOfPage must be preserved.'
);
mrn_assert(
	'Blog headline' === $normalized_payload['article']['headline'],
	'BlogPosting headline must be preserved.'
);
mrn_assert(
	'Blog description' === $normalized_payload['article']['description'],
	'BlogPosting description must be preserved.'
);
mrn_assert(
	'2026-08-01T10:00:00+00:00' === $normalized_payload['article']['datePublished'],
	'BlogPosting datePublished must be preserved.'
);
mrn_assert(
	'2026-08-02T11:00:00+00:00' === $normalized_payload['article']['dateModified'],
	'BlogPosting dateModified must be preserved.'
);
mrn_assert(
	isset( $normalized_payload['supplemental'] ) && 'Keep me' === $normalized_payload['supplemental']['name'],
	'SEOPress generator normalization must preserve unrelated supplemental schema entries.'
);

$GLOBALS['mrn_test_fields']['schema_author_policy'] = 'public';

$news_payload = mrn_schema_bridge_filter_seopress_json_schema_generator_get_jsons(
	array(
		'article' => $news_article,
	)
);

mrn_assert(
	'Public Reporter' === $news_payload['article']['author']['name'],
	'NewsArticle authors should remain when the author policy allows public authors.'
);
mrn_assert(
	'https://example.com/author/public-reporter/' === $news_payload['article']['author']['url'],
	'Malformed NewsArticle author URLs must be normalized.'
);
mrn_assert(
	'https://example.com/#organization' === $news_payload['article']['publisher']['@id'],
	'NewsArticle publisher must also receive the canonical organization ID.'
);

$GLOBALS['mrn_test_fields']['schema_author_policy'] = 'organization';

$smartcrawl_graph = array(
	'@context' => 'https://schema.org',
	'@graph'   => array(
		array(
			'@type' => 'Organization',
			'@id'   => 'https://example.com/#schema-publishing-organization',
			'name'  => 'SmartCrawl Publisher',
		),
		array(
			'@type' => 'Person',
			'@id'   => 'https://example.com/#schema-author',
			'name'  => 'mrn-admin',
			'url'   => 'https://example.com/author/mrn-admin/',
		),
		array(
			'@type'   => 'Article',
			'headline' => 'SmartCrawl Headline',
			'author'  => array(
				'@type' => 'Person',
				'@id'   => 'https://example.com/#schema-author',
				'name'  => 'mrn-admin',
				'url'   => 'https://example.com/author/mrn-admin/',
			),
		),
	),
);

$normalized_smartcrawl = mrn_schema_bridge_filter_provider_schema_graph( $smartcrawl_graph, false );
mrn_assert(
	2 === count( $normalized_smartcrawl['@graph'] ),
	'SmartCrawl graph normalization must still strip internal author nodes without changing the graph shape beyond that removal.'
);
mrn_assert(
	'https://example.com/#schema-publishing-organization' === $normalized_smartcrawl['@graph'][0]['@id'],
	'SmartCrawl organization nodes must keep their canonical identifier.'
);
mrn_assert(
	'Example Legal Entity LLC' === $normalized_smartcrawl['@graph'][0]['legalName'],
	'SmartCrawl organization nodes must still receive canonical business data.'
);
mrn_assert(
	array(
		'@id' => 'https://example.com/#schema-publishing-organization',
	) === $normalized_smartcrawl['@graph'][1]['author'],
	'SmartCrawl article author references must still be replaced with the organization reference.'
);

echo "MRN Schema Bridge contract regression checks passed.\n";

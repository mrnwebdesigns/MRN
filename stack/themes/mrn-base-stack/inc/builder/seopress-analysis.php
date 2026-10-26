<?php
/**
 * Supported SEOPress analysis adapters for the Stack's saved ACF builder.
 *
 * @package mrn-base-stack
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-mrn-base-stack-seopress-main-extractor.php';
require_once __DIR__ . '/class-mrn-base-stack-seopress-incoming-extractor.php';

/**
 * Detect saved Stack content outside post_content, without rendering or writes.
 *
 * @param bool    $builder Existing provider detection.
 * @param WP_Post $post Analysed post.
 * @return bool
 */
function mrn_base_stack_seopress_builder_driven( $builder, $post ) {
	if ( $builder || ! $post instanceof WP_Post || ! function_exists( 'get_field' ) || ! mrn_base_stack_is_layout_builder_enabled() ) {
		return $builder;
	}
	foreach ( array( 'page_content_rows', 'page_after_content_rows', 'page_hero_rows' ) as $field ) {
		if ( get_post_meta( $post->ID, $field, true ) ) {
			return true;
		}
	}
	if ( 'job_posting' === $post->post_type ) {
		foreach ( array( 'summary', 'responsibilities', 'qualifications', 'benefits' ) as $field ) {
			if ( '' !== trim( wp_strip_all_tags( (string) get_post_meta( $post->ID, 'job_posting_' . $field, true ) ) ) ) {
				return true;
			}
		}
	}
	return 'team_member' === $post->post_type && '' !== trim( wp_strip_all_tags( (string) get_post_meta( $post->ID, 'team_member_bio', true ) ) );
}
add_filter( 'seopress_content_analysis_builder_driven', 'mrn_base_stack_seopress_builder_driven', 20, 2 );

/**
 * Adapt only the provider's native descriptors; retain other integrations.
 *
 * @param array $data Extractor descriptors, before their execution.
 * @return array
 */
function mrn_base_stack_seopress_analysis_extractors( $data ) {
	if ( isset( $_GET['tax_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve native taxonomy analysis; presence check only.
		return $data;
	}
	$id = absint( $data['internal_links']['options']['id'] ?? 0 );
	if ( ! $id ) {
		return $data;
	}
	$post = get_post( $id );
	if ( ! $post instanceof WP_Post ) {
		return $data;
	}
	$prefix = '\\SEOPress\\Services\\ContentAnalysis\\GetContent\\';
	foreach ( array(
		'h1'                => 'Hn',
		'h2'                => 'Hn',
		'h3'                => 'Hn',
		'images'            => 'Image',
		'links_no_follow'   => 'LinkNoFollow',
		'outbound_links'    => 'OutboundLinks',
		'content_structure' => 'ContentStructure',
	) as $key => $class ) {
		if ( ( $data[ $key ]['class'] ?? '' ) !== $prefix . $class ) {
			continue;
		}
		$data[ $key ]['options']['mrn_original_class'] = $prefix . $class;
		$data[ $key ]['class']                         = MRN_Base_Stack_SEOPress_Main_Extractor::class;
	}
	if ( ( $data['internal_links']['class'] ?? '' ) === $prefix . 'InternalLinks' ) {
		$data['internal_links']['class'] = MRN_Base_Stack_SEOPress_Incoming_Extractor::class;
	}
	return $data;
}
add_filter( 'seopress_get_data_dom_filter_content', 'mrn_base_stack_seopress_analysis_extractors', 20 );

/**
 * Extract only public main content, retaining the original document for head/schema.
 *
 * @param DOMXPath $xpath Fetched document.
 * @return DOMXPath
 */
function mrn_base_stack_seopress_main_xpath( DOMXPath $xpath ) {
	$items = $xpath->query( '//main[not(ancestor::main)]|//*[@role="main" and not(ancestor::main)]' );
	if ( ! $items || 0 === $items->length ) {
		// Headless fragments/custom markup have no known Stack main contract.
		return $xpath;
	}
	$dom  = new DOMDocument();
	$root = $dom->appendChild( $dom->createElement( 'main' ) );
	foreach ( $items as $item ) {
		$root->appendChild( $dom->importNode( $item, true ) );
	}
	$scoped = new DOMXPath( $dom );
	$remove = $scoped->query( '//script|//style|//noscript|//template|//nav|//footer|//aside|//*[@hidden or @aria-hidden="true"]|//*[@id="wpadminbar" or @id="query-monitor-main"]|//*[contains(concat(" ",normalize-space(@class)," ")," mrn-singular-shell__sidebar ")]' );
	foreach ( iterator_to_array( $remove ) as $node ) {
		$node->parentNode->removeChild( $node ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
	}
	return $scoped;
}

/**
 * Public incoming sources must still have an enabled, accessible destination.
 *
 * @param WP_Post|null $post Candidate.
 * @return bool
 */
function mrn_base_stack_seopress_public_source( $post ) {
	return $post instanceof WP_Post && is_post_publicly_viewable( $post ) && '' === $post->post_password
		&& ( ! function_exists( 'mrn_admin_data_post_types_get_state' ) || ! in_array( mrn_admin_data_post_types_get_state( $post->post_type ), array( 'disabled', 'content_only' ), true ) )
		&& ( 'team_member' !== $post->post_type || mrn_base_stack_team_member_has_public_profile( $post ) );
}

/**
 * Render saved Stack fields with their existing renderers for link verification.
 * Never replace post_content or add this markup to a public response.
 *
 * @param WP_Post $post Candidate source.
 * @return string
 */
function mrn_base_stack_seopress_builder_markup( WP_Post $post ) {
	$markup = '';
	if ( ! in_array( $post->post_type, array( 'gallery', 'case_study', 'testimonial' ), true ) ) {
		$markup .= mrn_base_stack_get_content_builder_markup( $post->ID );
	}
	$markup .= mrn_base_stack_get_after_content_builder_markup( $post->ID );
	ob_start();
	mrn_base_stack_render_hero_builder( $post->ID );
	$markup .= (string) ob_get_clean();
	if ( 'job_posting' === $post->post_type ) {
		$job = mrn_base_stack_get_job_posting_data( $post->ID );
		foreach ( array( 'summary', 'responsibilities', 'qualifications', 'benefits' ) as $key ) {
			$markup .= wp_kses_post( $job[ $key ] ?? '' );
		}
	}
	/** Custom public templates can supply their rendered ACF body here. */
	return (string) apply_filters( 'mrn_base_stack_seopress_builder_markup', $markup, $post );
}

/**
 * Find saved ACF URL references and their reusable-block hosts, not every post.
 * Rendered href verification discards unused fields and plain text mentions.
 *
 * @param string $path Destination path.
 * @return int[]
 */
function mrn_base_stack_seopress_link_candidates( $path ) {
	$types = array();
	foreach ( get_post_types( array(), 'objects' ) as $name => $type ) {
		if ( ! empty( $type->public ) || 0 === strpos( $name, 'mrn_reusable_' ) ) {
			$types[] = $name;
		}
	}
	$args = array(
		'post_type'      => $types,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	);
	// Analysis-only candidate lookup, followed by actual rendered href verification.
	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	$args['meta_query'] = array(
		array(
			'value'   => $path,
			'compare' => 'LIKE',
		),
	);
	$ids                = ( new WP_Query( $args ) )->posts;
	$seen               = array();
	for ( $depth = 0; $depth < 10 && $ids; ++$depth ) {
		$next = array();
		foreach ( $ids as $id ) {
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			if ( 0 === strpos( (string) get_post_type( $id ), 'mrn_reusable_' ) ) {
				// Follow explicit native reusable-block references only.
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				$args['meta_query'] = array(
					array(
						'key'         => '(^|_)block$',
						'compare_key' => 'REGEXP',
						'value'       => (string) $id,
						'compare'     => '=',
					),
				);
				$next               = array_merge( $next, ( new WP_Query( $args ) )->posts );
			}
		}
		$ids = $next;
	}
	return array_map( 'intval', array_keys( $seen ) );
}

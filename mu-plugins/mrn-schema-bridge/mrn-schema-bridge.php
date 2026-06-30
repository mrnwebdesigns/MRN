<?php
/**
 * Plugin Name: MRN Schema Bridge
 * Description: Shared structured data normalization for MRN sites.
 * Version: 0.3.3
 * Author: MRN
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MRN_SCHEMA_BRIDGE_VERSION' ) ) {
	define( 'MRN_SCHEMA_BRIDGE_VERSION', '0.3.3' );
}

if ( ! defined( 'MRN_SCHEMA_BRIDGE_SCHEMA_HEALTH_OPTION' ) ) {
	define( 'MRN_SCHEMA_BRIDGE_SCHEMA_HEALTH_OPTION', 'mrn_schema_bridge_schema_health_last_report' );
}

if ( ! defined( 'MRN_SCHEMA_BRIDGE_SERVICE_PAGE_IDS_OPTION' ) ) {
	define( 'MRN_SCHEMA_BRIDGE_SERVICE_PAGE_IDS_OPTION', 'mrn_schema_bridge_service_page_ids' );
}

if ( ! defined( 'MRN_SCHEMA_BRIDGE_SERVICE_AREA_SERVED_OPTION' ) ) {
	define( 'MRN_SCHEMA_BRIDGE_SERVICE_AREA_SERVED_OPTION', 'mrn_schema_bridge_service_area_served' );
}

if ( ! defined( 'MRN_SCHEMA_BRIDGE_CONTACT_PAGE_IDS_OPTION' ) ) {
	define( 'MRN_SCHEMA_BRIDGE_CONTACT_PAGE_IDS_OPTION', 'mrn_schema_bridge_contact_page_ids' );
}

if ( ! defined( 'MRN_SCHEMA_BRIDGE_CONTACT_POINTS_OPTION' ) ) {
	define( 'MRN_SCHEMA_BRIDGE_CONTACT_POINTS_OPTION', 'mrn_schema_bridge_contact_points' );
}

if ( ! defined( 'MRN_SCHEMA_BRIDGE_POST_SCHEMA_DESCRIPTIONS_OPTION' ) ) {
	define( 'MRN_SCHEMA_BRIDGE_POST_SCHEMA_DESCRIPTIONS_OPTION', 'mrn_schema_bridge_post_schema_descriptions' );
}

if ( ! defined( 'MRN_SCHEMA_BRIDGE_POST_SCHEMA_DESCRIPTION_META_KEY' ) ) {
	define( 'MRN_SCHEMA_BRIDGE_POST_SCHEMA_DESCRIPTION_META_KEY', '_mrn_schema_bridge_schema_description' );
}

/**
 * Check whether schema graph normalization is enabled.
 *
 * @return bool
 */
function mrn_schema_bridge_enabled() {
	return (bool) apply_filters( 'mrn_schema_bridge_enabled', true );
}

/**
 * Normalize a human-readable name for comparisons.
 *
 * @param mixed $name Candidate name.
 * @return string
 */
function mrn_schema_bridge_normalize_name( $name ) {
	if ( ! is_scalar( $name ) ) {
		return '';
	}

	return strtolower( trim( wp_strip_all_tags( (string) $name ) ) );
}

/**
 * Get public author names that should remain in author Person schema.
 *
 * @return array<int, string>
 */
function mrn_schema_bridge_get_public_author_names() {
	$names = apply_filters( 'mrn_schema_bridge_public_author_names', array() );

	if ( ! is_array( $names ) ) {
		return array();
	}

	$normalized = array();

	foreach ( $names as $name ) {
		$name = mrn_schema_bridge_normalize_name( $name );

		if ( '' !== $name ) {
			$normalized[] = $name;
		}
	}

	return array_values( array_unique( $normalized ) );
}

/**
 * Get known internal author names that should never be exposed as public authors.
 *
 * @return array<int, string>
 */
function mrn_schema_bridge_get_blocked_author_names() {
	$names = apply_filters(
		'mrn_schema_bridge_blocked_author_names',
		array(
			'nethues',
		)
	);

	if ( ! is_array( $names ) ) {
		return array();
	}

	$normalized = array();

	foreach ( $names as $name ) {
		$name = mrn_schema_bridge_normalize_name( $name );

		if ( '' !== $name ) {
			$normalized[] = $name;
		}
	}

	return array_values( array_unique( $normalized ) );
}

/**
 * Check whether non-public author Person nodes should be removed.
 *
 * @return bool
 */
function mrn_schema_bridge_strip_non_public_author_person_nodes_enabled() {
	return (bool) apply_filters( 'mrn_schema_bridge_strip_non_public_author_person_nodes_enabled', true );
}

/**
 * Get schema item types as a normalized array.
 *
 * @param array<string, mixed> $item Schema graph item.
 * @return array<int, string>
 */
function mrn_schema_bridge_get_item_types( $item ) {
	if ( ! is_array( $item ) || empty( $item['@type'] ) ) {
		return array();
	}

	$types = is_array( $item['@type'] ) ? $item['@type'] : array( $item['@type'] );

	return array_values(
		array_filter(
			array_map(
				static function ( $type ) {
					return is_scalar( $type ) ? (string) $type : '';
				},
				$types
			)
		)
	);
}

/**
 * Check whether a schema item has a type.
 *
 * @param array<string, mixed> $item Schema graph item.
 * @param string               $type Schema type.
 * @return bool
 */
function mrn_schema_bridge_item_has_type( $item, $type ) {
	return in_array( $type, mrn_schema_bridge_get_item_types( $item ), true );
}

/**
 * Check whether a schema item looks like a WordPress author Person node.
 *
 * @param array<string, mixed> $item Schema graph item.
 * @return bool
 */
function mrn_schema_bridge_is_author_person_node( $item ) {
	if ( ! is_array( $item ) || ! mrn_schema_bridge_item_has_type( $item, 'Person' ) ) {
		return false;
	}

	$id   = isset( $item['@id'] ) && is_scalar( $item['@id'] ) ? (string) $item['@id'] : '';
	$url  = isset( $item['url'] ) && is_scalar( $item['url'] ) ? (string) $item['url'] : '';
	$name = isset( $item['name'] ) ? mrn_schema_bridge_normalize_name( $item['name'] ) : '';

	$looks_like_author = false !== strpos( $id, 'schema-author' ) || false !== strpos( $url, '/author/' );

	if ( in_array( $name, mrn_schema_bridge_get_blocked_author_names(), true ) ) {
		return true;
	}

	if ( ! $looks_like_author ) {
		return false;
	}

	if ( ! mrn_schema_bridge_strip_non_public_author_person_nodes_enabled() ) {
		return false;
	}

	return ! in_array( $name, mrn_schema_bridge_get_public_author_names(), true );
}

/**
 * Find the preferred organization reference for replacing removed author refs.
 *
 * @param array<int, array<string, mixed>> $graph Schema graph.
 * @return array<string, string>
 */
function mrn_schema_bridge_get_organization_reference( $graph ) {
	$preferred = null;
	$fallback  = null;

	foreach ( $graph as $item ) {
		if ( ! is_array( $item ) || empty( $item['@id'] ) || ! is_scalar( $item['@id'] ) ) {
			continue;
		}

		$is_org = mrn_schema_bridge_item_has_type( $item, 'Organization' )
			|| mrn_schema_bridge_item_has_type( $item, 'ProfessionalService' )
			|| mrn_schema_bridge_item_has_type( $item, 'LocalBusiness' );

		if ( ! $is_org ) {
			continue;
		}

		if ( false !== strpos( (string) $item['@id'], '#schema-publishing-organization' ) ) {
			$preferred = array( '@id' => (string) $item['@id'] );
			break;
		}

		if ( null === $fallback ) {
			$fallback = array( '@id' => (string) $item['@id'] );
		}
	}

	$reference = $preferred ? $preferred : $fallback;

	if ( ! $reference ) {
		$reference = array(
			'@type' => 'Organization',
			'name'  => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : '',
			'url'   => function_exists( 'home_url' ) ? home_url( '/' ) : '',
		);
	}

	return (array) apply_filters( 'mrn_schema_bridge_organization_reference', $reference, $graph );
}

/**
 * Get or set whether the active schema provider already emitted ContactPage schema.
 *
 * @param bool|null $detected Optional detected state.
 * @return bool
 */
function mrn_schema_bridge_provider_contact_page_schema_detected( $detected = null ) {
	if ( null !== $detected ) {
		$GLOBALS['mrn_schema_bridge_provider_contact_page_schema_detected'] = (bool) $detected;
	}

	return ! empty( $GLOBALS['mrn_schema_bridge_provider_contact_page_schema_detected'] );
}

/**
 * Get the current queried contact page post when configured.
 *
 * @return WP_Post|null
 */
function mrn_schema_bridge_get_current_contact_page_post() {
	if ( ! is_singular() ) {
		return null;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return null;
	}

	if ( ! in_array( absint( $post->ID ), mrn_schema_bridge_get_contact_page_ids(), true ) ) {
		return null;
	}

	return $post;
}

/**
 * Build an organization main entity value with configured contact points.
 *
 * @return array<string,mixed>
 */
function mrn_schema_bridge_get_contact_page_main_entity() {
	$main_entity    = mrn_schema_bridge_get_default_organization_reference();
	$contact_points = mrn_schema_bridge_get_contact_points();

	if ( ! empty( $contact_points ) ) {
		$main_entity['@type']        = 'Organization';
		$main_entity['contactPoint'] = 1 === count( $contact_points ) ? $contact_points[0] : $contact_points;
	}

	return $main_entity;
}

/**
 * Enrich provider ContactPage nodes so supplemental output does not duplicate them.
 *
 * @param array<int, array<string, mixed>> $graph Schema graph.
 * @return array<int, array<string, mixed>>
 */
function mrn_schema_bridge_enrich_contact_page_schema_graph( $graph ) {
	$post = mrn_schema_bridge_get_current_contact_page_post();

	if ( ! $post instanceof WP_Post ) {
		return $graph;
	}

	$description            = mrn_schema_bridge_get_post_schema_description( $post );
	$organization_reference = mrn_schema_bridge_get_default_organization_reference();
	$main_entity            = mrn_schema_bridge_get_contact_page_main_entity();

	foreach ( $graph as $index => $item ) {
		if ( ! is_array( $item ) || ! mrn_schema_bridge_item_has_type( $item, 'ContactPage' ) ) {
			continue;
		}

		mrn_schema_bridge_provider_contact_page_schema_detected( true );

		if ( empty( $graph[ $index ]['publisher'] ) ) {
			$graph[ $index ]['publisher'] = $organization_reference;
		}

		if ( empty( $graph[ $index ]['mainEntity'] ) ) {
			$graph[ $index ]['mainEntity'] = $main_entity;
		} elseif (
			is_array( $graph[ $index ]['mainEntity'] )
			&& empty( $graph[ $index ]['mainEntity']['contactPoint'] )
			&& ! empty( $main_entity['contactPoint'] )
		) {
			$graph[ $index ]['mainEntity']['contactPoint'] = $main_entity['contactPoint'];
		}

		if ( '' !== $description && empty( $graph[ $index ]['description'] ) ) {
			$graph[ $index ]['description'] = $description;
		}
	}

	return $graph;
}

/**
 * Check whether a schema reference points to a removed author node.
 *
 * @param mixed              $value       Candidate schema value.
 * @param array<int, string> $removed_ids Removed schema IDs.
 * @param array<int, string> $removed_names Removed schema names.
 * @return bool
 */
function mrn_schema_bridge_value_references_removed_author( $value, $removed_ids, $removed_names ) {
	if ( is_string( $value ) ) {
		return in_array( $value, $removed_ids, true );
	}

	if ( ! is_array( $value ) ) {
		return false;
	}

	if ( isset( $value['@id'] ) && is_scalar( $value['@id'] ) && in_array( (string) $value['@id'], $removed_ids, true ) ) {
		return true;
	}

	if ( isset( $value['name'] ) && in_array( mrn_schema_bridge_normalize_name( $value['name'] ), $removed_names, true ) ) {
		return true;
	}

	return false;
}

/**
 * Remove internal/non-public author Person nodes from a schema graph.
 *
 * @param array<int, array<string, mixed>> $graph Schema graph.
 * @return array<int, array<string, mixed>>
 */
function mrn_schema_bridge_strip_author_person_nodes( $graph ) {
	$removed_ids   = array();
	$removed_names = array();
	$kept          = array();

	foreach ( $graph as $item ) {
		if ( ! is_array( $item ) || ! mrn_schema_bridge_is_author_person_node( $item ) ) {
			$kept[] = $item;
			continue;
		}

		if ( ! empty( $item['@id'] ) && is_scalar( $item['@id'] ) ) {
			$removed_ids[] = (string) $item['@id'];
		}

		if ( ! empty( $item['name'] ) ) {
			$removed_names[] = mrn_schema_bridge_normalize_name( $item['name'] );
		}
	}

	$removed_ids   = array_values( array_unique( array_filter( $removed_ids ) ) );
	$removed_names = array_values( array_unique( array_filter( $removed_names ) ) );

	if ( empty( $removed_ids ) && empty( $removed_names ) ) {
		return $graph;
	}

	$organization_reference = mrn_schema_bridge_get_organization_reference( $kept );

	foreach ( $kept as $index => $item ) {
		if ( ! is_array( $item ) || empty( $item['author'] ) ) {
			continue;
		}

		if ( mrn_schema_bridge_value_references_removed_author( $item['author'], $removed_ids, $removed_names ) ) {
			$kept[ $index ]['author'] = $organization_reference;
		}
	}

	return array_values( $kept );
}

/**
 * Normalize schema graphs produced by supported SEO plugins.
 *
 * @param array $data Schema graph data.
 * @return array
 */
function mrn_schema_bridge_filter_schema_graph( $data ) {
	if ( ! mrn_schema_bridge_enabled() || ! is_array( $data ) ) {
		return $data;
	}

	$data = mrn_schema_bridge_strip_author_person_nodes( $data );
	$data = mrn_schema_bridge_enrich_contact_page_schema_graph( $data );

	return (array) apply_filters( 'mrn_schema_bridge_schema_graph', $data );
}
add_filter( 'wds_schema_printer_schema_data', 'mrn_schema_bridge_filter_schema_graph', 20 );

/**
 * Check whether supplemental schema output is enabled.
 *
 * @return bool
 */
function mrn_schema_bridge_supplemental_schema_enabled() {
	return (bool) apply_filters( 'mrn_schema_bridge_supplemental_schema_enabled', true );
}

/**
 * Build a stable schema ID from a URL and fragment.
 *
 * @param string $url Page URL.
 * @param string $fragment Schema fragment without the hash.
 * @return string
 */
function mrn_schema_bridge_build_schema_id( $url, $fragment ) {
	$url      = is_string( $url ) ? $url : '';
	$fragment = sanitize_key( (string) $fragment );

	if ( '' === $url || '' === $fragment ) {
		return '';
	}

	return trailingslashit( $url ) . '#' . $fragment;
}

/**
 * Get the default organization node reference.
 *
 * @return array<string,string>
 */
function mrn_schema_bridge_get_default_organization_reference() {
	$reference = array(
		'@id' => mrn_schema_bridge_build_schema_id( home_url( '/' ), 'schema-publishing-organization' ),
	);

	return (array) apply_filters( 'mrn_schema_bridge_default_organization_reference', $reference );
}

/**
 * Get a webpage node reference for a URL.
 *
 * @param string $url Page URL.
 * @return array<string,string>
 */
function mrn_schema_bridge_get_webpage_reference( $url ) {
	$reference = array(
		'@id' => mrn_schema_bridge_build_schema_id( $url, 'schema-webpage' ),
	);

	return (array) apply_filters( 'mrn_schema_bridge_webpage_reference', $reference, $url );
}

/**
 * Get configured service page IDs.
 *
 * @return array<int,int>
 */
function mrn_schema_bridge_get_service_page_ids() {
	$raw = get_option( MRN_SCHEMA_BRIDGE_SERVICE_PAGE_IDS_OPTION, array() );
	$ids = array();

	if ( is_string( $raw ) ) {
		$raw = preg_split( '/[\s,]+/', $raw );
	}

	if ( is_array( $raw ) ) {
		foreach ( $raw as $id ) {
			$id = absint( $id );

			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
	}

	$ids = apply_filters( 'mrn_schema_bridge_service_page_ids', $ids );

	if ( ! is_array( $ids ) ) {
		return array();
	}

	return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
}

/**
 * Get configured service area text.
 *
 * @return string
 */
function mrn_schema_bridge_get_service_area_served() {
	$area = get_option( MRN_SCHEMA_BRIDGE_SERVICE_AREA_SERVED_OPTION, '' );
	$area = is_scalar( $area ) ? sanitize_text_field( (string) $area ) : '';
	$area = apply_filters( 'mrn_schema_bridge_service_area_served', $area );

	return is_scalar( $area ) ? sanitize_text_field( (string) $area ) : '';
}

/**
 * Get configured contact page IDs.
 *
 * @return array<int,int>
 */
function mrn_schema_bridge_get_contact_page_ids() {
	$raw = get_option( MRN_SCHEMA_BRIDGE_CONTACT_PAGE_IDS_OPTION, array() );
	$ids = array();

	if ( is_string( $raw ) ) {
		$raw = preg_split( '/[\s,]+/', $raw );
	}

	if ( is_array( $raw ) ) {
		foreach ( $raw as $id ) {
			$id = absint( $id );

			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
	}

	$ids = apply_filters( 'mrn_schema_bridge_contact_page_ids', $ids );

	if ( ! is_array( $ids ) ) {
		return array();
	}

	return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
}

/**
 * Normalize scalar or list schema text values.
 *
 * @param mixed $value Candidate schema text value.
 * @return string|array<int,string>
 */
function mrn_schema_bridge_normalize_schema_text_value( $value ) {
	if ( is_scalar( $value ) ) {
		return sanitize_text_field( (string) $value );
	}

	if ( ! is_array( $value ) ) {
		return '';
	}

	$normalized = array();

	foreach ( $value as $item ) {
		if ( ! is_scalar( $item ) ) {
			continue;
		}

		$item = sanitize_text_field( (string) $item );

		if ( '' !== $item ) {
			$normalized[] = $item;
		}
	}

	return array_values( array_unique( $normalized ) );
}

/**
 * Check whether an array looks like one contact point definition.
 *
 * @param array $value Candidate array.
 * @return bool
 */
function mrn_schema_bridge_is_contact_point_shape( $value ) {
	$known_keys = array(
		'areaServed',
		'area_served',
		'availableLanguage',
		'available_language',
		'contactType',
		'contact_type',
		'email',
		'phone',
		'telephone',
		'url',
	);

	foreach ( $known_keys as $key ) {
		if ( array_key_exists( $key, $value ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Normalize a ContactPoint node.
 *
 * @param mixed $point Candidate contact point.
 * @return array<string,mixed>
 */
function mrn_schema_bridge_normalize_contact_point( $point ) {
	if ( ! is_array( $point ) ) {
		return array();
	}

	$node = array(
		'@type' => 'ContactPoint',
	);

	if ( isset( $point['contactType'] ) || isset( $point['contact_type'] ) ) {
		$contact_type = isset( $point['contactType'] ) ? $point['contactType'] : $point['contact_type'];
		$contact_type = is_scalar( $contact_type ) ? sanitize_text_field( (string) $contact_type ) : '';

		if ( '' !== $contact_type ) {
			$node['contactType'] = $contact_type;
		}
	}

	if ( isset( $point['email'] ) && is_scalar( $point['email'] ) ) {
		$email = sanitize_email( (string) $point['email'] );

		if ( '' !== $email && is_email( $email ) ) {
			$node['email'] = $email;
		}
	}

	if ( isset( $point['telephone'] ) || isset( $point['phone'] ) ) {
		$telephone = isset( $point['telephone'] ) ? $point['telephone'] : $point['phone'];
		$telephone = is_scalar( $telephone ) ? sanitize_text_field( (string) $telephone ) : '';

		if ( '' !== $telephone ) {
			$node['telephone'] = $telephone;
		}
	}

	if ( isset( $point['url'] ) && is_scalar( $point['url'] ) ) {
		$url = esc_url_raw( (string) $point['url'] );

		if ( '' !== $url ) {
			$node['url'] = $url;
		}
	}

	if ( isset( $point['areaServed'] ) || isset( $point['area_served'] ) ) {
		$area_served = isset( $point['areaServed'] ) ? $point['areaServed'] : $point['area_served'];
		$area_served = mrn_schema_bridge_normalize_schema_text_value( $area_served );

		if ( ! empty( $area_served ) ) {
			$node['areaServed'] = $area_served;
		}
	}

	if ( isset( $point['availableLanguage'] ) || isset( $point['available_language'] ) ) {
		$language = isset( $point['availableLanguage'] ) ? $point['availableLanguage'] : $point['available_language'];
		$language = mrn_schema_bridge_normalize_schema_text_value( $language );

		if ( ! empty( $language ) ) {
			$node['availableLanguage'] = $language;
		}
	}

	return count( $node ) > 1 ? $node : array();
}

/**
 * Get configured organization contact points.
 *
 * @return array<int,array<string,mixed>>
 */
function mrn_schema_bridge_get_contact_points() {
	$raw = get_option( MRN_SCHEMA_BRIDGE_CONTACT_POINTS_OPTION, array() );

	if ( is_string( $raw ) ) {
		$decoded = json_decode( $raw, true );

		if ( is_array( $decoded ) ) {
			$raw = $decoded;
		}
	}

	$raw = apply_filters( 'mrn_schema_bridge_contact_points', $raw );

	if ( ! is_array( $raw ) ) {
		return array();
	}

	if ( mrn_schema_bridge_is_contact_point_shape( $raw ) ) {
		$raw = array( $raw );
	}

	$points = array();

	foreach ( $raw as $point ) {
		$point = mrn_schema_bridge_normalize_contact_point( $point );

		if ( ! empty( $point ) ) {
			$points[] = $point;
		}
	}

	return $points;
}

/**
 * Get the post meta key used for schema-only descriptions.
 *
 * @return string
 */
function mrn_schema_bridge_get_post_schema_description_meta_key() {
	$meta_key = apply_filters( 'mrn_schema_bridge_post_schema_description_meta_key', MRN_SCHEMA_BRIDGE_POST_SCHEMA_DESCRIPTION_META_KEY );

	return is_scalar( $meta_key ) ? sanitize_key( (string) $meta_key ) : '';
}

/**
 * Normalize a post schema description map from an option or filter.
 *
 * @param mixed $raw Raw description map.
 * @return array<string,string>
 */
function mrn_schema_bridge_normalize_post_schema_description_map( $raw ) {
	if ( is_string( $raw ) ) {
		$decoded = json_decode( $raw, true );

		if ( is_array( $decoded ) ) {
			$raw = $decoded;
		}
	}

	if ( ! is_array( $raw ) ) {
		return array();
	}

	$map = array();

	foreach ( $raw as $key => $value ) {
		if ( ! is_scalar( $key ) || ! is_scalar( $value ) ) {
			continue;
		}

		$key         = sanitize_key( (string) $key );
		$description = sanitize_text_field( (string) $value );

		if ( '' !== $key && '' !== $description ) {
			$map[ $key ] = $description;
		}
	}

	return $map;
}

/**
 * Get a schema-only description override for a post.
 *
 * @param WP_Post $post Post object.
 * @return string
 */
function mrn_schema_bridge_get_post_schema_description_override( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$meta_key = mrn_schema_bridge_get_post_schema_description_meta_key();

	if ( '' !== $meta_key ) {
		$meta_description = get_post_meta( $post->ID, $meta_key, true );

		if ( is_scalar( $meta_description ) && '' !== trim( (string) $meta_description ) ) {
			return sanitize_text_field( (string) $meta_description );
		}
	}

	$raw_descriptions = get_option( MRN_SCHEMA_BRIDGE_POST_SCHEMA_DESCRIPTIONS_OPTION, array() );
	$raw_descriptions = apply_filters( 'mrn_schema_bridge_post_schema_descriptions', $raw_descriptions, $post );
	$descriptions     = mrn_schema_bridge_normalize_post_schema_description_map( $raw_descriptions );

	$post_keys = array(
		sanitize_key( (string) $post->ID ),
		sanitize_key( (string) $post->post_name ),
	);

	foreach ( array_unique( $post_keys ) as $post_key ) {
		if ( isset( $descriptions[ $post_key ] ) ) {
			return $descriptions[ $post_key ];
		}
	}

	return '';
}

/**
 * Get post description suitable for schema.
 *
 * @param WP_Post $post Post object.
 * @return string
 */
function mrn_schema_bridge_get_post_schema_description( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$description = mrn_schema_bridge_get_post_schema_description_override( $post );

	if ( '' === trim( (string) $description ) ) {
		$description = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
	}

	if ( '' === trim( (string) $description ) ) {
		$content     = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		$description = wp_trim_words( $content, 40, '' );
	}

	$description = sanitize_text_field( (string) $description );

	return (string) apply_filters( 'mrn_schema_bridge_post_schema_description', $description, $post );
}

/**
 * Get featured image URL for schema when available.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function mrn_schema_bridge_get_post_schema_image_url( $post_id ) {
	$image_url = get_the_post_thumbnail_url( absint( $post_id ), 'full' );
	$image_url = is_string( $image_url ) ? esc_url_raw( $image_url ) : '';

	return (string) apply_filters( 'mrn_schema_bridge_post_schema_image_url', $image_url, $post_id );
}

/**
 * Build Service schema for a configured service page.
 *
 * @param WP_Post $post Page object.
 * @return array<string,mixed>
 */
function mrn_schema_bridge_build_service_schema_node( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return array();
	}

	$url = get_permalink( $post );

	if ( ! is_string( $url ) || '' === $url ) {
		return array();
	}

	$title       = get_the_title( $post );
	$description = mrn_schema_bridge_get_post_schema_description( $post );
	$image_url   = mrn_schema_bridge_get_post_schema_image_url( $post->ID );
	$area_served = mrn_schema_bridge_get_service_area_served();

	$node = array(
		'@type'            => 'Service',
		'@id'              => mrn_schema_bridge_build_schema_id( $url, 'schema-service' ),
		'name'             => sanitize_text_field( (string) $title ),
		'serviceType'      => sanitize_text_field( (string) $title ),
		'url'              => esc_url_raw( $url ),
		'provider'         => mrn_schema_bridge_get_default_organization_reference(),
		'mainEntityOfPage' => esc_url_raw( $url ),
	);

	if ( '' !== $description ) {
		$node['description'] = $description;
	}

	if ( '' !== $image_url ) {
		$node['image'] = $image_url;
	}

	if ( '' !== $area_served ) {
		$node['areaServed'] = $area_served;
	}

	return (array) apply_filters( 'mrn_schema_bridge_service_schema_node', $node, $post );
}

/**
 * Get post types that should receive project/case-study schema.
 *
 * @return array<int,string>
 */
function mrn_schema_bridge_get_project_post_types() {
	$post_types = apply_filters( 'mrn_schema_bridge_project_post_types', array( 'mrn_case_study' ) );

	if ( ! is_array( $post_types ) ) {
		return array();
	}

	return array_values(
		array_unique(
			array_filter(
				array_map(
					static function ( $post_type ) {
						return is_scalar( $post_type ) ? sanitize_key( (string) $post_type ) : '';
					},
					$post_types
				)
			)
		)
	);
}

/**
 * Build CreativeWork schema for a project/case-study entry.
 *
 * @param WP_Post $post Post object.
 * @return array<string,mixed>
 */
function mrn_schema_bridge_build_project_schema_node( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return array();
	}

	$url = get_permalink( $post );

	if ( ! is_string( $url ) || '' === $url ) {
		return array();
	}

	$title       = get_the_title( $post );
	$description = mrn_schema_bridge_get_post_schema_description( $post );
	$image_url   = mrn_schema_bridge_get_post_schema_image_url( $post->ID );
	$organization_reference = mrn_schema_bridge_get_default_organization_reference();

	$node = array(
		'@type'            => 'CreativeWork',
		'@id'              => mrn_schema_bridge_build_schema_id( $url, 'schema-project-work' ),
		'name'             => sanitize_text_field( (string) $title ),
		'url'              => esc_url_raw( $url ),
		'creator'          => $organization_reference,
		'publisher'        => $organization_reference,
		'mainEntityOfPage' => esc_url_raw( $url ),
		'about'            => array(
			'@type' => 'Project',
			'name'  => sanitize_text_field( (string) $title ),
		),
	);

	if ( '' !== $description ) {
		$node['description'] = $description;
	}

	if ( '' !== $image_url ) {
		$node['image'] = $image_url;
	}

	return (array) apply_filters( 'mrn_schema_bridge_project_schema_node', $node, $post );
}

/**
 * Build ContactPage schema for a configured contact page.
 *
 * @param WP_Post $post Page object.
 * @return array<string,mixed>
 */
function mrn_schema_bridge_build_contact_page_schema_node( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return array();
	}

	$url = get_permalink( $post );

	if ( ! is_string( $url ) || '' === $url ) {
		return array();
	}

	$title                  = get_the_title( $post );
	$description            = mrn_schema_bridge_get_post_schema_description( $post );
	$image_url              = mrn_schema_bridge_get_post_schema_image_url( $post->ID );
	$organization_reference = mrn_schema_bridge_get_default_organization_reference();

	$node = array(
		'@type'            => 'ContactPage',
		'@id'              => mrn_schema_bridge_build_schema_id( $url, 'schema-contact-page' ),
		'name'             => sanitize_text_field( (string) $title ),
		'url'              => esc_url_raw( $url ),
		'publisher'        => $organization_reference,
		'mainEntity'       => mrn_schema_bridge_get_contact_page_main_entity(),
		'mainEntityOfPage' => esc_url_raw( $url ),
	);

	if ( '' !== $description ) {
		$node['description'] = $description;
	}

	if ( '' !== $image_url ) {
		$node['image'] = $image_url;
	}

	return (array) apply_filters( 'mrn_schema_bridge_contact_page_schema_node', $node, $post );
}

/**
 * Get supplemental schema nodes for the current frontend request.
 *
 * @return array<int,array<string,mixed>>
 */
function mrn_schema_bridge_get_supplemental_schema_nodes() {
	$nodes = array();

	if ( ! is_singular() ) {
		return (array) apply_filters( 'mrn_schema_bridge_supplemental_schema_nodes', $nodes, null );
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return (array) apply_filters( 'mrn_schema_bridge_supplemental_schema_nodes', $nodes, null );
	}

	if ( 'page' === $post->post_type && in_array( absint( $post->ID ), mrn_schema_bridge_get_service_page_ids(), true ) ) {
		$service_node = mrn_schema_bridge_build_service_schema_node( $post );

		if ( ! empty( $service_node ) ) {
			$nodes[] = $service_node;
		}
	}

	if (
		'page' === $post->post_type
		&& in_array( absint( $post->ID ), mrn_schema_bridge_get_contact_page_ids(), true )
		&& ! mrn_schema_bridge_provider_contact_page_schema_detected()
	) {
		$contact_page_node = mrn_schema_bridge_build_contact_page_schema_node( $post );

		if ( ! empty( $contact_page_node ) ) {
			$nodes[] = $contact_page_node;
		}
	}

	if ( in_array( sanitize_key( (string) $post->post_type ), mrn_schema_bridge_get_project_post_types(), true ) ) {
		$project_node = mrn_schema_bridge_build_project_schema_node( $post );

		if ( ! empty( $project_node ) ) {
			$nodes[] = $project_node;
		}
	}

	$nodes = apply_filters( 'mrn_schema_bridge_supplemental_schema_nodes', $nodes, $post );

	return is_array( $nodes ) ? array_values( array_filter( $nodes, 'is_array' ) ) : array();
}

/**
 * Print supplemental JSON-LD.
 *
 * @return void
 */
function mrn_schema_bridge_print_supplemental_schema() {
	if ( ! mrn_schema_bridge_enabled() || ! mrn_schema_bridge_supplemental_schema_enabled() ) {
		return;
	}

	$nodes = mrn_schema_bridge_get_supplemental_schema_nodes();

	if ( empty( $nodes ) ) {
		return;
	}

	$schema = array(
		'@context' => 'https://schema.org',
		'@graph'   => $nodes,
	);

	echo '<script type="application/ld+json" id="mrn-schema-bridge-supplemental">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'mrn_schema_bridge_print_supplemental_schema', 41 );

/**
 * Check whether the supported schema provider is loaded.
 *
 * @return bool
 */
function mrn_schema_bridge_supported_schema_provider_loaded() {
	$loaded = defined( 'SMARTCRAWL_VERSION' )
		|| defined( 'SMARTCRAWL_PLUGIN_DIR' )
		|| defined( 'SMARTCRAWL_PLUGIN_BASENAME' );

	return (bool) apply_filters( 'mrn_schema_bridge_supported_schema_provider_loaded', $loaded );
}

/**
 * Check whether legacy theme business schema output should be suppressed.
 *
 * @return bool
 */
function mrn_schema_bridge_suppress_legacy_business_schema_enabled() {
	return (bool) apply_filters( 'mrn_schema_bridge_suppress_legacy_business_schema_enabled', true );
}

/**
 * Remove older base-stack business JSON-LD when a supported schema provider is active.
 *
 * @return void
 */
function mrn_schema_bridge_suppress_legacy_business_schema() {
	if ( ! mrn_schema_bridge_enabled() || ! mrn_schema_bridge_suppress_legacy_business_schema_enabled() ) {
		return;
	}

	if ( ! mrn_schema_bridge_supported_schema_provider_loaded() ) {
		return;
	}

	remove_action( 'wp_head', 'mrn_base_stack_print_business_schema', 40 );
}
add_action( 'wp', 'mrn_schema_bridge_suppress_legacy_business_schema', 1 );

/**
 * Get the capability required to access schema health tools.
 *
 * @return string
 */
function mrn_schema_bridge_schema_health_capability() {
	$capability = apply_filters( 'mrn_schema_bridge_schema_health_capability', 'manage_options' );

	return is_string( $capability ) && '' !== $capability ? $capability : 'manage_options';
}

/**
 * Get the Schema Health admin page URL.
 *
 * @return string
 */
function mrn_schema_bridge_get_schema_health_page_url() {
	return add_query_arg(
		array(
			'page' => 'mrn-schema-health',
		),
		admin_url( 'tools.php' )
	);
}

/**
 * Get the default sitemap URL for a scan.
 *
 * @return string
 */
function mrn_schema_bridge_get_default_sitemap_url() {
	$url = apply_filters( 'mrn_schema_bridge_schema_health_default_sitemap_url', home_url( '/sitemap.xml' ) );

	return is_string( $url ) && '' !== $url ? $url : home_url( '/sitemap.xml' );
}

/**
 * Get the maximum number of URLs an admin-triggered scan may request.
 *
 * @return int
 */
function mrn_schema_bridge_schema_health_max_url_limit() {
	$limit = absint( apply_filters( 'mrn_schema_bridge_schema_health_max_url_limit', 100 ) );

	return max( 1, $limit );
}

/**
 * Get the default number of URLs to scan.
 *
 * @return int
 */
function mrn_schema_bridge_schema_health_default_url_limit() {
	$default = absint( apply_filters( 'mrn_schema_bridge_schema_health_default_url_limit', 50 ) );
	$max     = mrn_schema_bridge_schema_health_max_url_limit();

	return max( 1, min( $default, $max ) );
}

/**
 * Get the maximum number of sitemap files a scan may read.
 *
 * @return int
 */
function mrn_schema_bridge_schema_health_max_sitemap_limit() {
	$limit = absint( apply_filters( 'mrn_schema_bridge_schema_health_max_sitemap_limit', 10 ) );

	return max( 1, $limit );
}

/**
 * Get the maximum allowed response body size.
 *
 * @return int
 */
function mrn_schema_bridge_schema_health_max_body_bytes() {
	$bytes = absint( apply_filters( 'mrn_schema_bridge_schema_health_max_body_bytes', 5242880 ) );

	return max( 1024, $bytes );
}

/**
 * Sanitize a URL for same-site scanning.
 *
 * @param mixed $url Candidate URL.
 * @return string
 */
function mrn_schema_bridge_sanitize_scan_url( $url ) {
	if ( ! is_scalar( $url ) ) {
		return '';
	}

	$url = esc_url_raw( trim( (string) $url ) );

	if ( '' === $url ) {
		return '';
	}

	$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

	if ( ! is_string( $scheme ) || ! in_array( strtolower( $scheme ), array( 'http', 'https' ), true ) ) {
		return '';
	}

	return $url;
}

/**
 * Normalize a URL host for comparisons.
 *
 * @param mixed $host Candidate host.
 * @return string
 */
function mrn_schema_bridge_normalize_host( $host ) {
	if ( ! is_scalar( $host ) ) {
		return '';
	}

	$normalized = preg_replace( '/^www\./', '', strtolower( trim( (string) $host ) ) );

	return is_string( $normalized ) ? $normalized : '';
}

/**
 * Check whether a URL should be allowed in the admin scan.
 *
 * @param string $url Candidate URL.
 * @return bool
 */
function mrn_schema_bridge_is_allowed_scan_url( $url ) {
	$url = mrn_schema_bridge_sanitize_scan_url( $url );

	if ( '' === $url ) {
		return false;
	}

	$site_host = mrn_schema_bridge_normalize_host( wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
	$url_host  = mrn_schema_bridge_normalize_host( wp_parse_url( $url, PHP_URL_HOST ) );
	$allowed   = '' !== $site_host && '' !== $url_host && $site_host === $url_host;

	return (bool) apply_filters( 'mrn_schema_bridge_schema_health_allowed_scan_url', $allowed, $url, $site_host );
}

/**
 * Fetch a same-site URL for schema health scanning.
 *
 * @param string $url Target URL.
 * @param string $accept Accept header value.
 * @return array{code:int,body:string}|WP_Error
 */
function mrn_schema_bridge_schema_health_fetch_url( $url, $accept = 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8' ) {
	if ( ! mrn_schema_bridge_is_allowed_scan_url( $url ) ) {
		return new WP_Error( 'mrn_schema_bridge_scan_url_disallowed', __( 'The scan can only fetch same-site HTTP or HTTPS URLs.', 'mrn-schema-bridge' ) );
	}

	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => absint( apply_filters( 'mrn_schema_bridge_schema_health_request_timeout', 10 ) ),
			'redirection' => 3,
			'user-agent'  => 'MRN Schema Bridge/' . MRN_SCHEMA_BRIDGE_VERSION . '; ' . home_url( '/' ),
			'headers'     => array(
				'Accept' => $accept,
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$body = (string) wp_remote_retrieve_body( $response );

	if ( strlen( $body ) > mrn_schema_bridge_schema_health_max_body_bytes() ) {
		return new WP_Error( 'mrn_schema_bridge_scan_body_too_large', __( 'The response body was too large to scan safely.', 'mrn-schema-bridge' ) );
	}

	return array(
		'code' => absint( wp_remote_retrieve_response_code( $response ) ),
		'body' => $body,
	);
}

/**
 * Parse a sitemap XML body into sitemap or page URLs.
 *
 * @param string $body Sitemap body.
 * @return array{type:string,locs:array<int,string>}|WP_Error
 */
function mrn_schema_bridge_schema_health_parse_sitemap( $body ) {
	if ( '' === trim( $body ) ) {
		return new WP_Error( 'mrn_schema_bridge_empty_sitemap', __( 'The sitemap response was empty.', 'mrn-schema-bridge' ) );
	}

	if ( ! function_exists( 'simplexml_load_string' ) ) {
		return new WP_Error( 'mrn_schema_bridge_simplexml_missing', __( 'The SimpleXML PHP extension is required to parse sitemaps.', 'mrn-schema-bridge' ) );
	}

	$previous_errors = libxml_use_internal_errors( true );
	libxml_clear_errors();

	$xml    = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
	$errors = libxml_get_errors();
	libxml_clear_errors();
	libxml_use_internal_errors( $previous_errors );

	if ( false === $xml ) {
		$error_message = __( 'The sitemap XML could not be parsed.', 'mrn-schema-bridge' );

		if ( ! empty( $errors[0] ) && isset( $errors[0]->message ) ) {
			$error_message = trim( (string) $errors[0]->message );
		}

		return new WP_Error( 'mrn_schema_bridge_invalid_sitemap_xml', $error_message );
	}

	$root_name = strtolower( (string) $xml->getName() );
	$type      = 'unknown';
	$xpath     = '//*[local-name()="loc"]';

	if ( 'sitemapindex' === $root_name ) {
		$type  = 'sitemap';
		$xpath = '//*[local-name()="sitemap"]/*[local-name()="loc"]';
	} elseif ( 'urlset' === $root_name ) {
		$type  = 'url';
		$xpath = '//*[local-name()="url"]/*[local-name()="loc"]';
	}

	$nodes = $xml->xpath( $xpath );
	$locs  = array();

	if ( is_array( $nodes ) ) {
		foreach ( $nodes as $node ) {
			$url = mrn_schema_bridge_sanitize_scan_url( (string) $node );

			if ( '' !== $url ) {
				$locs[] = $url;
			}
		}
	}

	return array(
		'type' => $type,
		'locs' => array_values( array_unique( $locs ) ),
	);
}

/**
 * Add a sitemap collection issue.
 *
 * @param array<int, array<string, string>> $issues Issue list.
 * @param string                            $url URL.
 * @param string                            $message Issue message.
 * @return void
 */
function mrn_schema_bridge_schema_health_add_sitemap_issue( &$issues, $url, $message ) {
	$issues[] = array(
		'url'     => $url,
		'message' => $message,
	);
}

/**
 * Collect page URLs from a sitemap or sitemap index.
 *
 * @param string $sitemap_url Sitemap URL.
 * @param int    $limit URL limit.
 * @return array{urls:array<int,string>,issues:array<int,array<string,string>>,sitemaps_scanned:int}
 */
function mrn_schema_bridge_schema_health_collect_urls( $sitemap_url, $limit ) {
	$queue           = array( $sitemap_url );
	$visited         = array();
	$page_urls       = array();
	$issues          = array();
	$max_sitemaps    = mrn_schema_bridge_schema_health_max_sitemap_limit();
	$requested_limit = max( 1, absint( $limit ) );

	while ( ! empty( $queue ) && count( $page_urls ) < $requested_limit && count( $visited ) < $max_sitemaps ) {
		$current = array_shift( $queue );
		$current = is_string( $current ) ? $current : '';

		if ( '' === $current || isset( $visited[ $current ] ) ) {
			continue;
		}

		$visited[ $current ] = true;

		if ( ! mrn_schema_bridge_is_allowed_scan_url( $current ) ) {
			mrn_schema_bridge_schema_health_add_sitemap_issue( $issues, $current, __( 'Skipped because it is outside the current site.', 'mrn-schema-bridge' ) );
			continue;
		}

		$response = mrn_schema_bridge_schema_health_fetch_url( $current, 'application/xml,text/xml,*/*;q=0.8' );

		if ( is_wp_error( $response ) ) {
			mrn_schema_bridge_schema_health_add_sitemap_issue( $issues, $current, $response->get_error_message() );
			continue;
		}

		if ( $response['code'] < 200 || $response['code'] >= 300 ) {
			mrn_schema_bridge_schema_health_add_sitemap_issue(
				$issues,
				$current,
				sprintf(
					/* translators: %d: HTTP response status code. */
					__( 'Sitemap returned HTTP %d.', 'mrn-schema-bridge' ),
					$response['code']
				)
			);
			continue;
		}

		$parsed = mrn_schema_bridge_schema_health_parse_sitemap( $response['body'] );

		if ( is_wp_error( $parsed ) ) {
			mrn_schema_bridge_schema_health_add_sitemap_issue( $issues, $current, $parsed->get_error_message() );
			continue;
		}

		foreach ( $parsed['locs'] as $loc ) {
			if ( 'sitemap' === $parsed['type'] ) {
				if ( count( $visited ) + count( $queue ) < $max_sitemaps && mrn_schema_bridge_is_allowed_scan_url( $loc ) ) {
					$queue[] = $loc;
				}

				continue;
			}

			if ( ! mrn_schema_bridge_is_allowed_scan_url( $loc ) ) {
				continue;
			}

			$page_urls[ $loc ] = $loc;

			if ( count( $page_urls ) >= $requested_limit ) {
				break;
			}
		}
	}

	return array(
		'urls'             => array_values( $page_urls ),
		'issues'           => $issues,
		'sitemaps_scanned' => count( $visited ),
	);
}

/**
 * Extract JSON-LD script bodies from HTML.
 *
 * @param string $html HTML body.
 * @return array<int, string>
 */
function mrn_schema_bridge_schema_health_extract_json_ld_blocks( $html ) {
	$blocks = array();

	if ( '' === trim( $html ) ) {
		return $blocks;
	}

	if ( preg_match_all( '#<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches ) ) {
		$charset = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'charset' ) : 'UTF-8';
		$charset = is_string( $charset ) && '' !== $charset ? $charset : 'UTF-8';

		foreach ( $matches[1] as $block ) {
			$decoded = trim( html_entity_decode( (string) $block, ENT_QUOTES | ENT_HTML5, $charset ) );

			if ( '' !== $decoded ) {
				$blocks[] = $decoded;
			}
		}
	}

	return $blocks;
}

/**
 * Check whether an array is a list.
 *
 * @param array<mixed> $value Candidate array.
 * @return bool
 */
function mrn_schema_bridge_schema_health_is_list( $value ) {
	if ( array() === $value ) {
		return true;
	}

	return array_keys( $value ) === range( 0, count( $value ) - 1 );
}

/**
 * Extract top-level schema nodes from decoded JSON-LD.
 *
 * @param mixed                           $value Decoded JSON-LD value.
 * @param array<int, array<string,mixed>> $nodes Collected nodes.
 * @return void
 */
function mrn_schema_bridge_schema_health_extract_nodes( $value, &$nodes ) {
	if ( ! is_array( $value ) ) {
		return;
	}

	if ( mrn_schema_bridge_schema_health_is_list( $value ) ) {
		foreach ( $value as $item ) {
			mrn_schema_bridge_schema_health_extract_nodes( $item, $nodes );
		}

		return;
	}

	if ( isset( $value['@type'] ) || isset( $value['@id'] ) ) {
		/** @var array<string,mixed> $schema_node */
		$schema_node = $value;
		$nodes[]     = $schema_node;
	}

	if ( isset( $value['@graph'] ) && is_array( $value['@graph'] ) ) {
		foreach ( $value['@graph'] as $item ) {
			mrn_schema_bridge_schema_health_extract_nodes( $item, $nodes );
		}
	}
}

/**
 * Check whether a schema node is an organization-like entity.
 *
 * @param array<string,mixed> $node Schema node.
 * @return bool
 */
function mrn_schema_bridge_schema_health_is_organization_node( $node ) {
	return mrn_schema_bridge_item_has_type( $node, 'Organization' )
		|| mrn_schema_bridge_item_has_type( $node, 'ProfessionalService' )
		|| mrn_schema_bridge_item_has_type( $node, 'LocalBusiness' );
}

/**
 * Check whether a schema node has logo or image data.
 *
 * @param array<string,mixed> $node Schema node.
 * @return bool
 */
function mrn_schema_bridge_schema_health_node_has_image( $node ) {
	return ! empty( $node['logo'] ) || ! empty( $node['image'] );
}

/**
 * Add a page-level schema warning.
 *
 * @param array<string,mixed> $row Page report row.
 * @param string              $code Warning code.
 * @param string              $message Warning message.
 * @param string              $recommendation Recommended action.
 * @return void
 */
function mrn_schema_bridge_schema_health_add_warning( &$row, $code, $message, $recommendation = '' ) {
	$row['warnings'][] = array(
		'code'           => $code,
		'message'        => $message,
		'recommendation' => $recommendation,
	);
}

/**
 * Scan one URL for schema health signals.
 *
 * @param string $url Page URL.
 * @return array<string,mixed>
 */
function mrn_schema_bridge_schema_health_scan_url( $url ) {
	$row = array(
		'url'          => $url,
		'status'       => 0,
		'schema_types' => array(),
		'warnings'     => array(),
	);

	$response = mrn_schema_bridge_schema_health_fetch_url( $url );

	if ( is_wp_error( $response ) ) {
		mrn_schema_bridge_schema_health_add_warning(
			$row,
			'fetch_failed',
			$response->get_error_message(),
			__( 'Confirm the URL is public, same-site, and not blocked by the server.', 'mrn-schema-bridge' )
		);

		return $row;
	}

	$row['status'] = $response['code'];

	if ( $response['code'] < 200 || $response['code'] >= 300 ) {
		mrn_schema_bridge_schema_health_add_warning(
			$row,
			'http_status',
			sprintf(
				/* translators: %d: HTTP response status code. */
				__( 'URL returned HTTP %d.', 'mrn-schema-bridge' ),
				$response['code']
			),
			__( 'Review redirects, noindex pages, and sitemap inclusion for this URL.', 'mrn-schema-bridge' )
		);
	}

	$blocks = mrn_schema_bridge_schema_health_extract_json_ld_blocks( $response['body'] );
	$nodes  = array();

	foreach ( $blocks as $block ) {
		$decoded = json_decode( $block, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			mrn_schema_bridge_schema_health_add_warning(
				$row,
				'invalid_json_ld',
				json_last_error_msg(),
				__( 'Fix the malformed JSON-LD block before relying on schema output.', 'mrn-schema-bridge' )
			);
			continue;
		}

		mrn_schema_bridge_schema_health_extract_nodes( $decoded, $nodes );
	}

	if ( empty( $blocks ) || empty( $nodes ) ) {
		mrn_schema_bridge_schema_health_add_warning(
			$row,
			'missing_json_ld',
			__( 'No JSON-LD schema nodes were detected.', 'mrn-schema-bridge' ),
			__( 'Confirm the SEO/schema provider is enabled for this URL.', 'mrn-schema-bridge' )
		);

		return $row;
	}

	$organization_count       = 0;
	$organization_image_count = 0;
	$internal_author_count    = 0;
	$types                    = array();

	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}

		foreach ( mrn_schema_bridge_get_item_types( $node ) as $type ) {
			$types[] = $type;
		}

		if ( mrn_schema_bridge_schema_health_is_organization_node( $node ) ) {
			++$organization_count;

			if ( mrn_schema_bridge_schema_health_node_has_image( $node ) ) {
				++$organization_image_count;
			}
		}

		if ( mrn_schema_bridge_is_author_person_node( $node ) ) {
			++$internal_author_count;
		}
	}

	$row['schema_types'] = array_values( array_unique( array_filter( $types ) ) );

	if ( $organization_count > 1 ) {
		mrn_schema_bridge_schema_health_add_warning(
			$row,
			'duplicate_organization',
			sprintf(
				/* translators: %d: number of organization-like schema nodes. */
				__( 'Detected %d organization-like schema nodes.', 'mrn-schema-bridge' ),
				$organization_count
			),
			__( 'Choose one source of Organization schema or add a site-specific bridge rule to suppress duplicates.', 'mrn-schema-bridge' )
		);
	}

	if ( $organization_count > 0 && 0 === $organization_image_count ) {
		mrn_schema_bridge_schema_health_add_warning(
			$row,
			'missing_organization_image',
			__( 'Organization schema is missing logo/image data.', 'mrn-schema-bridge' ),
			__( 'Add a schema-ready logo image in the active schema provider.', 'mrn-schema-bridge' )
		);
	}

	if ( $internal_author_count > 0 ) {
		mrn_schema_bridge_schema_health_add_warning(
			$row,
			'internal_author_person',
			sprintf(
				/* translators: %d: number of internal author person nodes. */
				__( 'Detected %d internal author Person schema node(s).', 'mrn-schema-bridge' ),
				$internal_author_count
			),
			__( 'Schema normalization should remove supported internal author nodes; inspect unsupported schema sources if this remains.', 'mrn-schema-bridge' )
		);
	}

	return $row;
}

/**
 * Run a schema health scan from a sitemap URL.
 *
 * @param string $sitemap_url Sitemap URL.
 * @param int    $limit URL limit.
 * @return array<string,mixed>|WP_Error
 */
function mrn_schema_bridge_run_schema_health_scan( $sitemap_url, $limit ) {
	$sitemap_url = mrn_schema_bridge_sanitize_scan_url( $sitemap_url );

	if ( '' === $sitemap_url || ! mrn_schema_bridge_is_allowed_scan_url( $sitemap_url ) ) {
		return new WP_Error( 'mrn_schema_bridge_invalid_sitemap_url', __( 'Enter a same-site HTTP or HTTPS sitemap URL.', 'mrn-schema-bridge' ) );
	}

	$limit      = max( 1, min( absint( $limit ), mrn_schema_bridge_schema_health_max_url_limit() ) );
	$collected  = mrn_schema_bridge_schema_health_collect_urls( $sitemap_url, $limit );
	$rows       = array();
	$warning_ct = 0;

	foreach ( $collected['urls'] as $url ) {
		$row         = mrn_schema_bridge_schema_health_scan_url( $url );
		$warning_ct += isset( $row['warnings'] ) && is_array( $row['warnings'] ) ? count( $row['warnings'] ) : 0;
		$rows[]      = $row;
	}

	$report = array(
		'version'          => MRN_SCHEMA_BRIDGE_VERSION,
		'scanned_at'       => current_time( 'mysql' ),
		'site_url'         => home_url( '/' ),
		'sitemap_url'      => $sitemap_url,
		'requested_limit'  => $limit,
		'urls_found'       => count( $collected['urls'] ),
		'urls_scanned'     => count( $rows ),
		'warnings_count'   => $warning_ct,
		'sitemaps_scanned' => $collected['sitemaps_scanned'],
		'sitemap_issues'   => $collected['issues'],
		'rows'             => $rows,
	);

	update_option( MRN_SCHEMA_BRIDGE_SCHEMA_HEALTH_OPTION, $report, false );

	return $report;
}

/**
 * Get the last stored schema health report.
 *
 * @return array<string,mixed>
 */
function mrn_schema_bridge_get_schema_health_last_report() {
	$report = get_option( MRN_SCHEMA_BRIDGE_SCHEMA_HEALTH_OPTION, array() );

	return is_array( $report ) ? $report : array();
}

/**
 * Handle an admin scan form submission.
 *
 * @return array<string,mixed>|WP_Error
 */
function mrn_schema_bridge_handle_schema_health_scan_request() {
	if ( ! current_user_can( mrn_schema_bridge_schema_health_capability() ) ) {
		return new WP_Error( 'mrn_schema_bridge_forbidden', __( 'You are not allowed to run schema health scans.', 'mrn-schema-bridge' ) );
	}

	check_admin_referer( 'mrn_schema_bridge_schema_health_scan', 'mrn_schema_bridge_schema_health_nonce' );

	$sitemap_url = isset( $_POST['mrn_schema_bridge_sitemap_url'] )
		? mrn_schema_bridge_sanitize_scan_url( esc_url_raw( wp_unslash( $_POST['mrn_schema_bridge_sitemap_url'] ) ) )
		: mrn_schema_bridge_get_default_sitemap_url();

	$limit = isset( $_POST['mrn_schema_bridge_url_limit'] )
		? absint( wp_unslash( $_POST['mrn_schema_bridge_url_limit'] ) )
		: mrn_schema_bridge_schema_health_default_url_limit();

	return mrn_schema_bridge_run_schema_health_scan( $sitemap_url, $limit );
}

/**
 * Render a list of schema health warnings.
 *
 * @param array<int,array<string,string>> $warnings Warning rows.
 * @return void
 */
function mrn_schema_bridge_render_schema_health_warnings( $warnings ) {
	if ( empty( $warnings ) ) {
		echo '<span style="color:#008a20;">' . esc_html__( 'OK', 'mrn-schema-bridge' ) . '</span>';
		return;
	}

	echo '<ul style="margin:0 0 0 18px;">';
	foreach ( $warnings as $warning ) {
		$message        = isset( $warning['message'] ) ? (string) $warning['message'] : '';
		$recommendation = isset( $warning['recommendation'] ) ? (string) $warning['recommendation'] : '';
		echo '<li>';
		echo esc_html( $message );

		if ( '' !== $recommendation ) {
			echo '<br><span style="color:#646970;">' . esc_html( $recommendation ) . '</span>';
		}

		echo '</li>';
	}
	echo '</ul>';
}

/**
 * Render sitemap issues.
 *
 * @param array<int,array<string,string>> $issues Sitemap issue rows.
 * @return void
 */
function mrn_schema_bridge_render_schema_health_sitemap_issues( $issues ) {
	if ( empty( $issues ) ) {
		return;
	}

	echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'Sitemap notes', 'mrn-schema-bridge' ) . '</strong></p><ul style="margin-left:18px;">';
	foreach ( $issues as $issue ) {
		$url     = isset( $issue['url'] ) ? (string) $issue['url'] : '';
		$message = isset( $issue['message'] ) ? (string) $issue['message'] : '';
		echo '<li><code>' . esc_html( $url ) . '</code>: ' . esc_html( $message ) . '</li>';
	}
	echo '</ul></div>';
}

/**
 * Render the Schema Health admin page.
 *
 * @return void
 */
function mrn_schema_bridge_render_schema_health_page() {
	if ( ! current_user_can( mrn_schema_bridge_schema_health_capability() ) ) {
		wp_die( esc_html__( 'You are not allowed to access schema health tools.', 'mrn-schema-bridge' ) );
	}

	$scan_result = null;
	$method      = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';

	if ( 'POST' === strtoupper( $method ) ) {
		$scan_result = mrn_schema_bridge_handle_schema_health_scan_request();
	}

	$report      = is_array( $scan_result ) ? $scan_result : mrn_schema_bridge_get_schema_health_last_report();
	$sitemap_url = isset( $report['sitemap_url'] ) && is_string( $report['sitemap_url'] )
		? $report['sitemap_url']
		: mrn_schema_bridge_get_default_sitemap_url();
	$url_limit   = isset( $report['requested_limit'] ) ? absint( $report['requested_limit'] ) : mrn_schema_bridge_schema_health_default_url_limit();
	$rows        = isset( $report['rows'] ) && is_array( $report['rows'] ) ? $report['rows'] : array();
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Schema Health', 'mrn-schema-bridge' ); ?></h1>

		<?php if ( is_wp_error( $scan_result ) ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( $scan_result->get_error_message() ); ?></p></div>
		<?php elseif ( is_array( $scan_result ) ) : ?>
			<div class="notice notice-success inline"><p><?php echo esc_html__( 'Schema scan complete.', 'mrn-schema-bridge' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( mrn_schema_bridge_get_schema_health_page_url() ); ?>" style="max-width:860px;margin:18px 0 24px;">
			<?php wp_nonce_field( 'mrn_schema_bridge_schema_health_scan', 'mrn_schema_bridge_schema_health_nonce' ); ?>
			<input type="hidden" name="mrn_schema_bridge_schema_health_action" value="scan" />

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="mrn-schema-bridge-sitemap-url"><?php echo esc_html__( 'Sitemap URL', 'mrn-schema-bridge' ); ?></label>
						</th>
						<td>
							<input
								type="url"
								class="regular-text code"
								id="mrn-schema-bridge-sitemap-url"
								name="mrn_schema_bridge_sitemap_url"
								value="<?php echo esc_attr( $sitemap_url ); ?>"
								required
							/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="mrn-schema-bridge-url-limit"><?php echo esc_html__( 'URL limit', 'mrn-schema-bridge' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								class="small-text"
								id="mrn-schema-bridge-url-limit"
								name="mrn_schema_bridge_url_limit"
								min="1"
								max="<?php echo esc_attr( (string) mrn_schema_bridge_schema_health_max_url_limit() ); ?>"
								value="<?php echo esc_attr( (string) $url_limit ); ?>"
							/>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'Run Schema Scan', 'mrn-schema-bridge' ) ); ?>
		</form>

		<?php if ( ! empty( $report ) ) : ?>
			<h2><?php echo esc_html__( 'Last Report', 'mrn-schema-bridge' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: scan date, 2: scanned URL count, 3: warning count. */
						__( '%1$s: scanned %2$d URL(s), found %3$d warning(s).', 'mrn-schema-bridge' ),
						isset( $report['scanned_at'] ) ? (string) $report['scanned_at'] : __( 'Last scan', 'mrn-schema-bridge' ),
						isset( $report['urls_scanned'] ) ? absint( $report['urls_scanned'] ) : 0,
						isset( $report['warnings_count'] ) ? absint( $report['warnings_count'] ) : 0
					)
				);
				?>
			</p>

			<?php
			$sitemap_issues = isset( $report['sitemap_issues'] ) && is_array( $report['sitemap_issues'] ) ? $report['sitemap_issues'] : array();
			mrn_schema_bridge_render_schema_health_sitemap_issues( $sitemap_issues );
			?>

			<table class="widefat striped" style="max-width:1200px;">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'URL', 'mrn-schema-bridge' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'HTTP', 'mrn-schema-bridge' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Schema Types', 'mrn-schema-bridge' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Findings', 'mrn-schema-bridge' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr>
							<td colspan="4"><?php echo esc_html__( 'No URLs were scanned.', 'mrn-schema-bridge' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$url      = isset( $row['url'] ) ? (string) $row['url'] : '';
							$status   = isset( $row['status'] ) ? absint( $row['status'] ) : 0;
							$types    = isset( $row['schema_types'] ) && is_array( $row['schema_types'] ) ? array_map( 'strval', $row['schema_types'] ) : array();
							$warnings = isset( $row['warnings'] ) && is_array( $row['warnings'] ) ? $row['warnings'] : array();
							?>
							<tr>
								<td><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a></td>
								<td><?php echo esc_html( (string) $status ); ?></td>
								<td><?php echo esc_html( empty( $types ) ? '-' : implode( ', ', $types ) ); ?></td>
								<td><?php mrn_schema_bridge_render_schema_health_warnings( $warnings ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Register Schema Health admin tools.
 *
 * @return void
 */
function mrn_schema_bridge_register_schema_health_page() {
	add_management_page(
		__( 'Schema Health', 'mrn-schema-bridge' ),
		__( 'Schema Health', 'mrn-schema-bridge' ),
		mrn_schema_bridge_schema_health_capability(),
		'mrn-schema-health',
		'mrn_schema_bridge_render_schema_health_page'
	);
}

if ( is_admin() ) {
	add_action( 'admin_menu', 'mrn_schema_bridge_register_schema_health_page' );
}

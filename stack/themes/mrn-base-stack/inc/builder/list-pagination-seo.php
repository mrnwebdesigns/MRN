<?php
/**
 * Public URL identity for the parent's rendered content lists.
 *
 * @package mrn-base-stack
 */

/**
 * Collect list placements using the same indexes as nested/reusable renderers.
 *
 * @param array $rows Rendered builder rows.
 * @param int   $post_id Owning page.
 * @param int   $depth Recursion limit for malformed reusable data.
 * @return array
 */
function mrn_base_stack_collect_paginated_lists( array $rows, $post_id, $depth = 0 ) {
	if ( $depth > 10 ) {
		return array();
	}
	$lists = array();
	foreach ( $rows as $index => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$layout   = $row['acf_fc_layout'] ?? '';
		$children = array();
		if ( 'content_lists' === $layout && mrn_base_stack_content_list_can_paginate( $row ) ) {
			$lists[] = array(
				'row'   => $row,
				'index' => (int) $index,
			);
		} elseif ( 'reusable_block' === $layout ) {
			$block = mrn_base_stack_get_reusable_block_post_from_row( $row );
			if ( $block instanceof WP_Post ) {
				$children[ $index ] = mrn_base_stack_get_reusable_block_builder_row( $block, $row );
			}
		} elseif ( 'two_column_split' === $layout ) {
			$stack = 'stack' === ( $row['column_rows_mode'] ?? '' );
			foreach ( array( 'left_column_rows', 'right_column_rows' ) as $column => $field ) {
				foreach ( array_slice( (array) ( $row[ $field ] ?? array() ), 0, $stack ? 10 : 1 ) as $child_index => $child ) {
					$key                = $stack ? mrn_base_stack_get_nested_builder_row_index( $index, $column, $child_index ) : 0;
					$children[ $key ][] = $child;
				}
			}
			// Legacy columns can share index zero: preserve both placements.
			foreach ( $children as $key => $placements ) {
				foreach ( $placements as $child ) {
					$lists = array_merge( $lists, mrn_base_stack_collect_paginated_lists( array( $key => $child ), $post_id, $depth + 1 ) );
				}
			}
			continue;
		} elseif ( in_array( $layout, array( 'card', 'tabbed_layout' ), true ) ) {
			$is_tabs = 'tabbed_layout' === $layout;
			$items   = (array) ( $row[ $is_tabs ? 'tabs' : 'card_items' ] ?? array() );
			foreach ( $items as $item_index => $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$timeline = ! $is_tabs && 'timeline' === ( $row['card_presentation'] ?? '' );
				$nested   = (array) ( $item[ $is_tabs ? 'panel_rows' : 'card_rows' ] ?? array() );
				foreach ( $timeline ? $nested : array_slice( $nested, 0, 1 ) as $child ) {
					$key   = $timeline ? $item_index : mrn_base_stack_get_nested_builder_row_index( $index, $item_index, 0 );
					$lists = array_merge( $lists, mrn_base_stack_collect_paginated_lists( array( $key => $child ), $post_id, $depth + 1 ) );
				}
			}
		}
		if ( $children ) {
			$lists = array_merge( $lists, mrn_base_stack_collect_paginated_lists( $children, $post_id, $depth + 1 ) );
		}
	}
	return $lists;
}

/**
 * Prepare one public request's list ownership before SEO output or templates.
 *
 * @return array Owning post, canonical URL and range validation outcome.
 */
function mrn_base_stack_get_list_pagination_request() {
	static $state = null;
	if ( null !== $state ) {
		return $state;
	}
	$empty = array(
		'post_id'      => 0,
		'url'          => '',
		'out_of_range' => false,
	);
	if ( is_admin() || ! is_singular() || is_preview() || is_paged() || get_query_var( 'page' ) > 1 || ! function_exists( 'get_field' ) ) {
		return $empty;
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post || ! is_post_publicly_viewable( $post ) || post_password_required( $post ) ) {
		return $empty;
	}
	// Ordinary requests need neither builder hydration nor range queries.
	$prefix    = 'mrn_list_page_' . $post->ID . '_';
	$requested = false;
	foreach ( array_keys( $_GET ) as $key ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL key ownership, never used as output.
		if ( is_string( $key ) && 0 === strpos( $key, $prefix ) ) {
			$requested = true;
			break;
		}
	}
	if ( ! $requested ) {
		return $empty;
	}
	$state  = $empty;
	$fields = array( 'page_after_content_rows' );
	if ( in_array( $post->post_type, array( 'page', 'service' ), true ) ) {
		array_unshift( $fields, 'page_content_rows' );
	}
	if ( function_exists( 'mrn_base_stack_get_singular_sidebar_settings' ) && 'none' !== ( mrn_base_stack_get_singular_sidebar_settings( $post->ID )['layout'] ?? 'none' ) ) {
		if ( ! in_array( 'page_content_rows', $fields, true ) ) {
			array_unshift( $fields, 'page_content_rows' );
		}
		$fields[] = 'page_sidebar_rows';
	}
	$lists = array();
	foreach ( $fields as $field ) {
		$rows = get_field( $field, $post->ID );
		if ( is_array( $rows ) ) {
			$lists = array_merge( $lists, mrn_base_stack_collect_paginated_lists( $rows, $post->ID ) );
		}
	}
	// Only the two-column hero actually renders nested builder content.
	$hero = get_field( 'page_hero_rows', $post->ID );
	if ( is_array( $hero ) ) {
		$hero  = array_filter(
			$hero,
			static function ( $row ) {
				return is_array( $row ) && 'two_column_split' === ( $row['acf_fc_layout'] ?? '' );
			}
		);
		$lists = array_merge( $lists, mrn_base_stack_collect_paginated_lists( $hero, $post->ID ) );
	}
	/** Allow a custom template to declare its actual rendered list placements. */
	$lists = apply_filters( 'mrn_base_stack_page_paginated_lists', $lists, $post );
	if ( ! $lists ) {
		return $state;
	}
	$base             = get_permalink( $post );
	$state['post_id'] = $post->ID;
	$params           = array();
	foreach ( $lists as $list ) {
		$page = mrn_base_stack_get_content_list_current_page( $post->ID, $list['index'] );
		if ( $page < 2 ) {
			continue;
		}
		$first = mrn_base_stack_get_cached_content_list_query( mrn_base_stack_get_content_list_query_args( $list['row'], $post->ID ) );
		if ( $page > (int) $first->max_num_pages ) {
			$state['out_of_range'] = true;
			return $state;
		}
		$params[ mrn_base_stack_get_content_list_pagination_query_arg( $post->ID, $list['index'] ) ] = $page;
	}
	ksort( $params );
	$custom = get_post_meta( $post->ID, '_seopress_robots_canonical', true );
	// Preserve an explicit editorial canonical pointing at a different resource.
	if ( function_exists( 'seopress_get_service' ) && is_string( $custom ) && '' !== $custom && $custom !== $base ) {
		return $state;
	}
	$state['url'] = add_query_arg( $params, $base );
	return $state;
}

/**
 * Reject nonexistent list pages without redirecting other archive families.
 *
 * @return void
 */
function mrn_base_stack_guard_list_pagination_range() {
	$state = mrn_base_stack_get_list_pagination_request();
	if ( $state['out_of_range'] ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		remove_action( 'template_redirect', 'redirect_canonical' );
	}
}
add_action( 'template_redirect', 'mrn_base_stack_guard_list_pagination_range', 0 );

/**
 * Resolve a page URL without changing global permalink/navigation behavior.
 *
 * @param string       $url Provider's original URL.
 * @param WP_Post|null $post Optional canonical subject.
 * @return string
 */
function mrn_base_stack_filter_list_pagination_url( $url, $post = null ) {
	$state = mrn_base_stack_get_list_pagination_request();
	if ( $post instanceof WP_Post && $post->ID !== $state['post_id'] ) {
		return $url;
	}
	return ! is_404() && $state['url'] ? $state['url'] : $url;
}
add_filter( 'get_canonical_url', 'mrn_base_stack_filter_list_pagination_url', 100, 2 );

/**
 * SEOPress filters the complete canonical element, not just its URL.
 *
 * @param string $html Canonical markup.
 * @return string
 */
function mrn_base_stack_filter_list_pagination_canonical( $html ) {
	$url = mrn_base_stack_filter_list_pagination_url( '' );
	return $url ? '<link rel="canonical" href="' . esc_url( $url ) . '">' : $html;
}
add_filter( 'seopress_titles_canonical', 'mrn_base_stack_filter_list_pagination_canonical', 100 );

/**
 * Keep social sharing identity consistent with the canonical.
 *
 * @param string $html Open Graph markup.
 * @return string
 */
function mrn_base_stack_filter_list_pagination_og_url( $html ) {
	$url = mrn_base_stack_filter_list_pagination_url( '' );
	return $url ? '<meta property="og:url" content="' . esc_url( $url ) . '">' : $html;
}
add_filter( 'seopress_social_og_url', 'mrn_base_stack_filter_list_pagination_og_url', 100 );

/**
 * Resolve supported SEOPress post-URL variables used by generated JSON-LD.
 * Literal editorial schema URLs and other posts' identities remain untouched.
 *
 * @param string $url Provider variable value.
 * @return string
 */
function mrn_base_stack_filter_list_pagination_post_url( $url ) {
	$state = mrn_base_stack_get_list_pagination_request();
	if ( ! $state['url'] || ! $state['post_id'] || is_404() || esc_url( get_permalink( $state['post_id'] ) ) !== $url ) {
		return $url;
	}
	return esc_url( $state['url'] );
}
add_filter( 'seopress_titles_post_url', 'mrn_base_stack_filter_list_pagination_post_url', 100 );
add_filter( 'seopress_get_tag_post_url_value', 'mrn_base_stack_filter_list_pagination_post_url', 100 );

/**
 * Keep bridge page references on the same document without rewriting ancestors.
 *
 * @param array  $reference Existing page reference.
 * @param string $url Referenced URL.
 * @return array
 */
function mrn_base_stack_filter_list_pagination_page_reference( $reference, $url ) {
	$state = mrn_base_stack_get_list_pagination_request();
	if ( $state['url'] && $state['post_id'] && get_permalink( $state['post_id'] ) === $url && ! is_404() ) {
		$reference['@id'] = $state['url'] . '#schema-webpage';
	}
	return $reference;
}
add_filter( 'mrn_schema_bridge_webpage_reference', 'mrn_base_stack_filter_list_pagination_page_reference', 100, 2 );

/**
 * Align bridge-generated page nodes, retaining site and ancestor identities.
 *
 * @param array $nodes Generated supplemental nodes.
 * @return array
 */
function mrn_base_stack_filter_list_pagination_schema_nodes( $nodes ) {
	$state = mrn_base_stack_get_list_pagination_request();
	if ( ! $state['url'] || ! $state['post_id'] || is_404() ) {
		return $nodes;
	}
	$base = get_permalink( $state['post_id'] );
	foreach ( $nodes as &$node ) {
		if ( ! is_array( $node ) || ! array_intersect( (array) ( $node['@type'] ?? array() ), array( 'WebPage', 'CollectionPage', 'ContactPage', 'ProfilePage', 'AboutPage' ) ) ) {
			continue;
		}
		if ( ( $node['url'] ?? '' ) === $base ) {
			$node['url'] = $state['url'];
		}
		$id = $node['@id'] ?? '';
		if ( is_string( $id ) && ( 0 === strpos( $id, $base . '#' ) || 0 === strpos( $id, $base . '/#' ) ) ) {
			$node['@id'] = $state['url'] . '#' . wp_parse_url( $id, PHP_URL_FRAGMENT );
		}
	}
	unset( $node );
	return $nodes;
}
add_filter( 'mrn_schema_bridge_supplemental_schema_nodes', 'mrn_base_stack_filter_list_pagination_schema_nodes', 100 );

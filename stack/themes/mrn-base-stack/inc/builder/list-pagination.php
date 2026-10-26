<?php
/**
 * Shared content-list queries and public pagination identity.
 *
 * @package mrn-base-stack
 */

/**
 * Resolve the renderer's source type, including its historical fallback.
 *
 * @param array $row Builder row.
 * @return string
 */
function mrn_base_stack_get_content_list_source_type( array $row ) {
	$choices = mrn_base_stack_get_content_list_post_type_choices();
	$type    = sanitize_key( (string) ( $row['list_post_type'] ?? 'post' ) );
	// Disabled sources are absent from the picker; retain their saved data source.
	if ( post_type_exists( $type ) && function_exists( 'mrn_admin_data_post_types_get_state' ) && 'disabled' === mrn_admin_data_post_types_get_state( $type ) ) {
		return $type;
	}
	return isset( $choices[ $type ] ) ? $type : ( isset( $choices['post'] ) ? 'post' : (string) array_key_first( $choices ) );
}

/**
 * Disabled data may remain embedded, without creating paginated destinations.
 *
 * @param array $row Builder row.
 * @return bool
 */
function mrn_base_stack_content_list_can_paginate( array $row ) {
	$type = mrn_base_stack_get_content_list_source_type( $row );
	return ! empty( $row['enable_pagination'] ) && ( ! function_exists( 'mrn_admin_data_post_types_get_state' ) || 'disabled' !== mrn_admin_data_post_types_get_state( $type ) );
}

/**
 * Build the same list query for rendering and pagination validation.
 *
 * @param array $row Builder row.
 * @param int   $post_id Owning page.
 * @param int   $page Positive page number.
 * @return array
 */
function mrn_base_stack_get_content_list_query_args( array $row, $post_id, $page = 1 ) {
	$type       = mrn_base_stack_get_content_list_source_type( $row );
	$orderby    = sanitize_key( (string) ( $row['orderby'] ?? 'date' ) );
	$choices    = mrn_base_stack_get_content_list_orderby_choices();
	$orderby    = isset( $choices[ $orderby ] ) ? $orderby : 'date';
	$order      = strtoupper( sanitize_key( (string) ( $row['order'] ?? 'DESC' ) ) );
	$order      = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';
	$style      = mrn_base_stack_normalize_content_list_display_style( $row['display_style'] ?? '', $type );
	$styles     = mrn_base_stack_get_content_list_display_styles_for_post_type( $type );
	$pagination = mrn_base_stack_content_list_can_paginate( $row );
	if ( 'grid' === $style && isset( $styles[ $style ] ) ) {
		$orderby = 'menu_order';
		$order   = 'ASC';
	}
	$args = array(
		'post_type'           => $type,
		'post_status'         => 'publish',
		'posts_per_page'      => max( 1, absint( $row['posts_per_page'] ?? 10 ) ),
		'orderby'             => $orderby,
		'order'               => $order,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => ! $pagination,
	);
	if ( 'manual_posts' === sanitize_key( (string) ( $row['filter_source'] ?? 'none' ) ) ) {
		$ids              = mrn_base_stack_get_content_list_manual_post_ids( $row, $type );
		$args['post__in'] = $ids ? $ids : array( 0 );
		$args['orderby']  = 'post__in';
		$args['order']    = 'ASC';
	}
	if ( $pagination ) {
		$args['paged'] = max( 1, (int) $page );
	} elseif ( empty( $row['enable_pagination'] ) && ! empty( $row['offset'] ) ) {
		$args['offset'] = absint( $row['offset'] );
	}
	$tax_query = mrn_base_stack_get_content_list_tax_query( $row, $post_id, $type );
	if ( $tax_query ) {
		// Preserve the renderer's existing taxonomy constraints.
		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}
	return $args;
}

/**
 * Reuse identical read-only query results within this public request.
 *
 * @param array $args Query arguments.
 * @return WP_Query
 */
function mrn_base_stack_get_cached_content_list_query( array $args ) {
	static $queries = array();
	$key            = md5( wp_json_encode( $args ) );
	if ( ! isset( $queries[ $key ] ) ) {
		$queries[ $key ] = new WP_Query( $args );
	}
	$queries[ $key ]->rewind_posts();
	return $queries[ $key ];
}

/**
 * Execute a bounded list query, without ever sending an unchecked SQL offset.
 *
 * @param array $row Builder row.
 * @param int   $post_id Owning page.
 * @param int   $index Renderer row index.
 * @return WP_Query
 */
function mrn_base_stack_query_content_list( array $row, $post_id, $index ) {
	$page = mrn_base_stack_content_list_can_paginate( $row ) ? mrn_base_stack_get_content_list_current_page( $post_id, $index ) : 1;
	$args = mrn_base_stack_get_content_list_query_args( $row, $post_id );
	if ( $page > 1 ) {
		$first = mrn_base_stack_get_cached_content_list_query( $args );
		if ( $page > (int) $first->max_num_pages ) {
			// Public owned requests are rejected before templates; bound previews too.
			$args['post__in'] = array( 0 );
		} else {
			$args['paged'] = $page;
		}
	}
	return mrn_base_stack_get_cached_content_list_query( $args );
}

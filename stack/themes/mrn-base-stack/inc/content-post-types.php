<?php
/**
 * Theme-owned content post type registrations.
 *
 * Dedicated ACF field groups and front-end presentation can be added by each
 * post type without changing these stable registration keys.
 *
 * @package mrn-base-stack
 */

/**
 * Get the content post types owned by the base stack.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_content_post_type_definitions() {
	return array(
		'team_member' => array(
			'singular'       => __( 'Team Member', 'mrn-base-stack' ),
			'plural'         => __( 'Team Members', 'mrn-base-stack' ),
			'rewrite_slug'   => 'team',
			'menu_icon'      => 'dashicons-groups',
			'menu_position'  => 10,
			'side_metaboxes' => array( 'acf-group_mrn_team_member_settings' ),
		),
		'location'    => array(
			'singular'      => __( 'Location', 'mrn-base-stack' ),
			'plural'        => __( 'Locations', 'mrn-base-stack' ),
			'rewrite_slug'  => 'locations',
			'menu_icon'     => 'dashicons-location-alt',
			'menu_position' => 11,
		),
	);
}

/**
 * Place stack-owned CPT metaboxes inside the shared locked editor layout.
 *
 * Every CPT in this registry inherits the Editor Lockdown fallback contract.
 * CPT-specific sidebar boxes can be declared with `side_metaboxes`; they are
 * placed after the SEO Helper and before the remaining standard sidebar boxes.
 *
 * @param array  $layout    Dynamic Editor Lockdown layout.
 * @param string $post_type Post type slug.
 * @return array
 */
function mrn_base_stack_filter_content_post_type_metabox_layout( $layout, $post_type ) {
	$definitions = mrn_base_stack_get_content_post_type_definitions();
	$post_type   = sanitize_key( (string) $post_type );

	if ( ! isset( $definitions[ $post_type ] ) || ! is_array( $layout ) ) {
		return $layout;
	}

	$side_metaboxes = isset( $definitions[ $post_type ]['side_metaboxes'] ) && is_array( $definitions[ $post_type ]['side_metaboxes'] )
		? array_values( array_filter( array_map( 'sanitize_key', $definitions[ $post_type ]['side_metaboxes'] ) ) )
		: array();

	if ( empty( $side_metaboxes ) || empty( $layout['meta_box_order']['side'] ) ) {
		return $layout;
	}

	$side_order = array_values( array_filter( array_map( 'trim', explode( ',', (string) $layout['meta_box_order']['side'] ) ) ) );
	$side_order = array_values( array_diff( $side_order, $side_metaboxes ) );
	$position   = array_search( 'mrn-builder-layout-allowlist', $side_order, true );
	$position   = false === $position ? count( $side_order ) : $position;

	array_splice( $side_order, $position, 0, $side_metaboxes );
	$layout['meta_box_order']['side'] = implode( ',', $side_order );

	return $layout;
}
add_filter( 'mrn_editor_lockdown_dynamic_layout', 'mrn_base_stack_filter_content_post_type_metabox_layout', 10, 2 );

/**
 * Treat locations as reusable data rather than public destinations.
 *
 * @param array $post_types Admin/data-only CPT configuration.
 * @return array
 */
function mrn_base_stack_register_location_as_admin_data( $post_types ) {
	$show_ui = function_exists( 'mrn_base_stack_is_admin_cpt_visible' ) ? mrn_base_stack_is_admin_cpt_visible( 'location' ) : true;

	$post_types['location'] = array(
		'show_ui'       => $show_ui,
		'show_in_menu'  => $show_ui,
		'admin_cleanup' => true,
	);

	return $post_types;
}
add_filter( 'mrn_admin_data_post_types', 'mrn_base_stack_register_location_as_admin_data' );

/**
 * Register the base stack's field-ready content post types.
 *
 * @return void
 */
function mrn_base_stack_register_content_post_types() {
	foreach ( mrn_base_stack_get_content_post_type_definitions() as $post_type => $definition ) {
		$show_ui  = function_exists( 'mrn_base_stack_is_admin_cpt_visible' ) ? mrn_base_stack_is_admin_cpt_visible( $post_type ) : true;
		$singular = $definition['singular'];
		$plural   = $definition['plural'];

		register_post_type(
			$post_type,
			array(
				'labels'              => array(
					'name'                  => $plural,
					'singular_name'         => $singular,
					'menu_name'             => $plural,
					'name_admin_bar'        => $singular,
					'add_new'               => __( 'Add New', 'mrn-base-stack' ),
					/* translators: %s: singular post type label. */
					'add_new_item'          => sprintf( __( 'Add New %s', 'mrn-base-stack' ), $singular ),
					/* translators: %s: singular post type label. */
					'new_item'              => sprintf( __( 'New %s', 'mrn-base-stack' ), $singular ),
					/* translators: %s: singular post type label. */
					'edit_item'             => sprintf( __( 'Edit %s', 'mrn-base-stack' ), $singular ),
					/* translators: %s: singular post type label. */
					'view_item'             => sprintf( __( 'View %s', 'mrn-base-stack' ), $singular ),
					/* translators: %s: plural post type label. */
					'view_items'            => sprintf( __( 'View %s', 'mrn-base-stack' ), $plural ),
					/* translators: %s: plural post type label. */
					'all_items'             => sprintf( __( 'All %s', 'mrn-base-stack' ), $plural ),
					/* translators: %s: plural post type label. */
					'search_items'          => sprintf( __( 'Search %s', 'mrn-base-stack' ), $plural ),
					'not_found'             => __( 'No items found.', 'mrn-base-stack' ),
					'not_found_in_trash'    => __( 'No items found in Trash.', 'mrn-base-stack' ),
					/* translators: %s: singular post type label. */
					'archives'              => sprintf( __( '%s Archives', 'mrn-base-stack' ), $singular ),
					/* translators: %s: singular post type label. */
					'attributes'            => sprintf( __( '%s Attributes', 'mrn-base-stack' ), $singular ),
					'featured_image'        => __( 'Featured image', 'mrn-base-stack' ),
					'set_featured_image'    => __( 'Set featured image', 'mrn-base-stack' ),
					'remove_featured_image' => __( 'Remove featured image', 'mrn-base-stack' ),
					'use_featured_image'    => __( 'Use as featured image', 'mrn-base-stack' ),
					/* translators: %s: plural post type label. */
					'items_list_navigation' => sprintf( __( '%s list navigation', 'mrn-base-stack' ), $plural ),
					/* translators: %s: plural post type label. */
					'items_list'            => sprintf( __( '%s list', 'mrn-base-stack' ), $plural ),
				),
				'public'              => true,
				'show_ui'             => $show_ui,
				'show_in_menu'        => $show_ui,
				'show_in_rest'        => true,
				'has_archive'         => true,
				'rewrite'             => array(
					'slug'       => $definition['rewrite_slug'],
					'with_front' => false,
				),
				'menu_position'       => $definition['menu_position'],
				'menu_icon'           => $definition['menu_icon'],
				'supports'            => array( 'title', 'thumbnail', 'revisions' ),
				'publicly_queryable'  => true,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => $show_ui,
				'exclude_from_search' => false,
				'hierarchical'        => false,
				'query_var'           => true,
			)
		);
	}
}
add_action( 'init', 'mrn_base_stack_register_content_post_types' );

/**
 * Register the per-member public profile control.
 *
 * @return void
 */
function mrn_base_stack_register_team_member_profile_field() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_mrn_team_member_settings',
			'title'    => __( 'Team Member Settings', 'mrn-base-stack' ),
			'fields'   => array(
				array(
					'key'           => 'field_mrn_team_member_public_profile',
					'label'         => __( 'Public Profile Page', 'mrn-base-stack' ),
					'name'          => 'team_member_public_profile',
					'type'          => 'true_false',
					'instructions'  => __( 'Turn this off to keep the team member available to grids and other components without giving them a standalone profile page.', 'mrn-base-stack' ),
					'default_value' => 1,
					'ui'            => 1,
					'ui_on_text'    => __( 'Enabled', 'mrn-base-stack' ),
					'ui_off_text'   => __( 'Disabled', 'mrn-base-stack' ),
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'team_member',
					),
				),
			),
			'position' => 'side',
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_team_member_profile_field' );

/**
 * Disable the per-member public-profile toggle when Team Member is forced
 * Content Only site-wide (Site Configurations -> Admin -> Content Types).
 *
 * The whole CPT has no public pages in that state, so the per-post setting
 * has no effect regardless of its value — leaving it live and "Enabled"
 * would misleadingly imply that member still has a profile page.
 *
 * @param array $field ACF field array.
 * @return array
 */
function mrn_base_stack_maybe_disable_team_member_profile_field( $field ) {
	if ( ! function_exists( 'mrn_admin_data_post_types_get_post_type_config' ) || null === mrn_admin_data_post_types_get_post_type_config( 'team_member' ) ) {
		return $field;
	}

	$field['disabled']     = true;
	$field['instructions'] = __( 'Team Member is currently set to Content Only in Site Configurations, so every team member already has no public profile page — this setting has no effect right now.', 'mrn-base-stack' );

	return $field;
}
add_filter( 'acf/prepare_field/key=field_mrn_team_member_public_profile', 'mrn_base_stack_maybe_disable_team_member_profile_field' );

/**
 * Determine whether a team member has a public profile destination.
 *
 * Records without saved toggle metadata remain public for backward safety.
 *
 * @param int|WP_Post|null $post Team member post or ID.
 * @return bool
 */
function mrn_base_stack_team_member_has_public_profile( $post = null ) {
	$post = get_post( $post );

	if ( ! $post || 'team_member' !== $post->post_type ) {
		return true;
	}

	return '0' !== (string) get_post_meta( $post->ID, 'team_member_public_profile', true );
}

/**
 * Remove public admin actions for team members without profile pages.
 *
 * @param array   $actions Post row actions.
 * @param WP_Post $post    Current post.
 * @return array
 */
function mrn_base_stack_filter_team_member_row_actions( $actions, $post ) {
	if ( ! mrn_base_stack_team_member_has_public_profile( $post ) ) {
		unset( $actions['view'], $actions['preview'] );
	}

	return $actions;
}
add_filter( 'post_row_actions', 'mrn_base_stack_filter_team_member_row_actions', 100, 2 );

/**
 * Disable preview links for team members without profile pages.
 *
 * @param string  $preview_link Preview URL.
 * @param WP_Post $post         Current post.
 * @return string
 */
function mrn_base_stack_filter_team_member_preview_link( $preview_link, $post ) {
	return mrn_base_stack_team_member_has_public_profile( $post ) ? $preview_link : '';
}
add_filter( 'preview_post_link', 'mrn_base_stack_filter_team_member_preview_link', 100, 2 );

/**
 * Hide permalink controls for team members without profile pages.
 *
 * @param string $html    Sample permalink HTML.
 * @param int    $post_id Post ID.
 * @return string
 */
function mrn_base_stack_filter_team_member_sample_permalink( $html, $post_id ) {
	$post = get_post( $post_id );

	return $post && ! mrn_base_stack_team_member_has_public_profile( $post ) ? '' : $html;
}
add_filter( 'get_sample_permalink_html', 'mrn_base_stack_filter_team_member_sample_permalink', 100, 2 );

/**
 * Return a 404 for disabled team member profile requests.
 *
 * @return void
 */
function mrn_base_stack_disable_team_member_profile_request() {
	if ( ! is_singular( 'team_member' ) || mrn_base_stack_team_member_has_public_profile( get_queried_object() ) ) {
		return;
	}

	global $wp_query;

	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
add_action( 'template_redirect', 'mrn_base_stack_disable_team_member_profile_request', 1 );

/**
 * Keep disabled team member destinations out of front-end search results.
 *
 * @param WP_Query $query Current query.
 * @return void
 */
function mrn_base_stack_exclude_disabled_team_member_profiles_from_search( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}

	$meta_query   = $query->get( 'meta_query' );
	$meta_query   = is_array( $meta_query ) ? $meta_query : array();
	$meta_query[] = array(
		'relation' => 'OR',
		array(
			'key'     => 'team_member_public_profile',
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'     => 'team_member_public_profile',
			'value'   => '0',
			'compare' => '!=',
		),
	);

	$query->set( 'meta_query', $meta_query );
}
add_action( 'pre_get_posts', 'mrn_base_stack_exclude_disabled_team_member_profiles_from_search' );

/**
 * Exclude disabled team member profiles from the core posts sitemap.
 *
 * @param array  $args      WP_Query arguments.
 * @param string $post_type Sitemap post type.
 * @return array
 */
function mrn_base_stack_filter_team_member_sitemap_query_args( $args, $post_type ) {
	if ( 'team_member' !== $post_type ) {
		return $args;
	}

	if ( ! isset( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Sitemap exclusion uses the stack's public-profile meta flag.
		$args['meta_query'] = array();
	}

	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Sitemap exclusion uses the stack's public-profile meta flag.
	$args['meta_query'][] = array(
		'relation' => 'OR',
		array(
			'key'     => 'team_member_public_profile',
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'     => 'team_member_public_profile',
			'value'   => '0',
			'compare' => '!=',
		),
	);

	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'mrn_base_stack_filter_team_member_sitemap_query_args', 10, 2 );

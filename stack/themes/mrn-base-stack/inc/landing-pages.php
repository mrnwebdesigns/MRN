<?php
/**
 * Landing Pages use the shared builder and content-type exposure controls.
 *
 * @package mrn-base-stack
 */

/**
 * Add Landing Pages to the existing builder and editorial contracts.
 *
 * @param string[] $post_types Existing post type keys.
 * @return string[]
 */
function mrn_base_stack_add_landing_page_post_type( $post_types ) {
	$post_types[] = 'landing_page';

	return array_values( array_unique( $post_types ) );
}
add_filter( 'mrn_base_stack_builder_supported_post_types', 'mrn_base_stack_add_landing_page_post_type' );
add_filter( 'mrn_base_stack_editorial_cpts', 'mrn_base_stack_add_landing_page_post_type' );

/**
 * Whether public Landing Page archives have been explicitly enabled.
 *
 * @return bool
 */
function mrn_base_stack_landing_page_archives_enabled() {
	return (bool) get_option( 'mrn_landing_page_archives_enabled', false );
}

/**
 * Register Landing Pages and their dedicated categories and tags.
 *
 * The shared MU contract applies Content Only/Disabled to register_post_type.
 * Read its resulting object so the taxonomies inherit the same exposure.
 *
 * @return void
 */
function mrn_base_stack_register_landing_page_post_type() {
	$show_ui = function_exists( 'mrn_base_stack_is_admin_cpt_visible' ) ? mrn_base_stack_is_admin_cpt_visible( 'landing_page' ) : true;

	register_post_type(
		'landing_page',
		array(
			'labels'              => array(
				'name'                  => __( 'Landing Pages', 'mrn-base-stack' ),
				'singular_name'         => __( 'Landing Page', 'mrn-base-stack' ),
				'menu_name'             => __( 'Landing Pages', 'mrn-base-stack' ),
				'name_admin_bar'        => __( 'Landing Page', 'mrn-base-stack' ),
				'add_new_item'          => __( 'Add New Landing Page', 'mrn-base-stack' ),
				'edit_item'             => __( 'Edit Landing Page', 'mrn-base-stack' ),
				'new_item'              => __( 'New Landing Page', 'mrn-base-stack' ),
				'view_item'             => __( 'View Landing Page', 'mrn-base-stack' ),
				'view_items'            => __( 'View Landing Pages', 'mrn-base-stack' ),
				'search_items'          => __( 'Search Landing Pages', 'mrn-base-stack' ),
				'all_items'             => __( 'All Landing Pages', 'mrn-base-stack' ),
				'not_found'             => __( 'No landing pages found.', 'mrn-base-stack' ),
				'not_found_in_trash'    => __( 'No landing pages found in Trash.', 'mrn-base-stack' ),
				'archives'              => __( 'Landing Page Archives', 'mrn-base-stack' ),
				'insert_into_item'      => __( 'Insert into landing page', 'mrn-base-stack' ),
				'uploaded_to_this_item' => __( 'Uploaded to this landing page', 'mrn-base-stack' ),
			),
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => $show_ui,
			'show_in_menu'        => $show_ui,
			'show_in_admin_bar'   => $show_ui,
			'show_in_nav_menus'   => true,
			'show_in_rest'        => true,
			'exclude_from_search' => false,
			'has_archive'         => mrn_base_stack_landing_page_archives_enabled() ? 'landing-pages' : false,
			'rewrite'             => array(
				'slug'       => 'landing-pages',
				'with_front' => false,
			),
			'menu_icon'           => 'dashicons-welcome-widgets-menus',
			'menu_position'       => 12,
			'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			'taxonomies'          => array( 'landing_page_category', 'landing_page_tag' ),
		)
	);

	$post_type = get_post_type_object( 'landing_page' );
	if ( ! $post_type ) {
		return;
	}

	$public_archives = $post_type->publicly_queryable && mrn_base_stack_landing_page_archives_enabled();
	foreach ( array( 'category', 'tag' ) as $kind ) {
		$is_category = 'category' === $kind;
		register_taxonomy(
			'landing_page_' . $kind,
			array( 'landing_page' ),
			array(
				'labels'             => array(
					'name'          => $is_category ? __( 'Landing Page Categories', 'mrn-base-stack' ) : __( 'Landing Page Tags', 'mrn-base-stack' ),
					'singular_name' => $is_category ? __( 'Landing Page Category', 'mrn-base-stack' ) : __( 'Landing Page Tag', 'mrn-base-stack' ),
					'menu_name'     => $is_category ? __( 'Categories', 'mrn-base-stack' ) : __( 'Tags', 'mrn-base-stack' ),
				),
				'hierarchical'       => $is_category,
				'public'             => $public_archives,
				'publicly_queryable' => $public_archives,
				'show_ui'            => $post_type->show_ui,
				'show_admin_column'  => $post_type->show_ui,
				'show_in_nav_menus'  => $public_archives,
				'show_in_rest'       => $post_type->show_in_rest,
				'query_var'          => $public_archives,
				'rewrite'            => $public_archives ? array(
					'slug'       => 'landing-page-' . $kind,
					'with_front' => false,
				) : false,
			)
		);
	}
}
add_action( 'init', 'mrn_base_stack_register_landing_page_post_type' );

/**
 * Show archive controls alongside the shared Content Only/Disabled controls.
 *
 * @return void
 */
function mrn_base_stack_render_landing_page_archive_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div id="mrn-landing-page-archive-settings" class="mrn-config-helper-card">
		<h2><?php esc_html_e( 'Landing Page Archives', 'mrn-base-stack' ); ?></h2>
		<?php wp_nonce_field( 'mrn_landing_page_archives_save', 'mrn_landing_page_archives_nonce' ); ?>
		<input type="hidden" name="mrn_landing_page_archives_present" value="1" />
		<label>
			<input type="checkbox" name="mrn_landing_page_archives_enabled" value="1" <?php checked( mrn_base_stack_landing_page_archives_enabled() ); ?> aria-describedby="mrn-landing-page-archives-help" />
			<?php esc_html_e( 'Enable Landing Page, category and tag archives', 'mrn-base-stack' ); ?>
		</label>
		<p id="mrn-landing-page-archives-help" class="description"><?php esc_html_e( 'Standalone Landing Pages remain available when archives are off. Content Only removes standalone pages and archives; Disabled also removes the editing interface. Those settings above take priority. Existing content and terms are retained.', 'mrn-base-stack' ); ?></p>
	</div>
	<?php
}
add_action( 'mrn_config_helper_render_admin_content_type_settings', 'mrn_base_stack_render_landing_page_archive_settings' );

/**
 * Save only explicitly submitted archive controls from an administrator.
 *
 * @return void
 */
function mrn_base_stack_save_landing_page_archive_settings() {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['mrn_landing_page_archives_present'], $_POST['mrn_landing_page_archives_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mrn_landing_page_archives_nonce'] ) ), 'mrn_landing_page_archives_save' ) ) {
		return;
	}
	$enabled = isset( $_POST['mrn_landing_page_archives_enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['mrn_landing_page_archives_enabled'] ) );
	if ( mrn_base_stack_landing_page_archives_enabled() !== $enabled ) {
		update_option( 'mrn_landing_page_archives_enabled', $enabled, false );
		update_option( 'mrn_landing_page_rewrite_pending', true, false );
	}
}
add_action( 'admin_init', 'mrn_base_stack_save_landing_page_archive_settings', 20 );

/**
 * Rebuild Landing Page routes when the shared Content Only selection changes.
 *
 * @param mixed $old Previous selection.
 * @param mixed $selection New selection.
 * @return void
 */
function mrn_base_stack_queue_landing_page_state_rewrites( $old, $selection ) {
	$was_selected = is_array( $old ) && in_array( 'landing_page', $old, true );
	$is_selected  = is_array( $selection ) && in_array( 'landing_page', $selection, true );
	if ( $was_selected !== $is_selected ) {
		update_option( 'mrn_landing_page_rewrite_pending', true, false );
	}
}
add_action( 'update_option_mrn_admin_data_post_types_manual', 'mrn_base_stack_queue_landing_page_state_rewrites', 10, 2 );

/**
 * Include the first saved Content Only selection in rewrite invalidation.
 *
 * @param string $option Added option name.
 * @param mixed  $selection Initial selection.
 * @return void
 */
function mrn_base_stack_add_landing_page_state_selection( $option, $selection ) {
	mrn_base_stack_queue_landing_page_state_rewrites( array(), $selection );
}
add_action( 'add_option_mrn_admin_data_post_types_manual', 'mrn_base_stack_add_landing_page_state_selection', 10, 2 );

/**
 * Rebuild rewrites once on the next request, after the new state registers.
 *
 * @return void
 */
function mrn_base_stack_flush_landing_page_rewrites() {
	if ( get_option( 'mrn_landing_page_rewrite_pending' ) ) {
		flush_rewrite_rules( false );
		delete_option( 'mrn_landing_page_rewrite_pending' );
	}
}
add_action( 'wp_loaded', 'mrn_base_stack_flush_landing_page_rewrites', 100 );

/**
 * Prevent query-string and stale-rewrite requests bypassing the archive switch.
 *
 * @return void
 */
function mrn_base_stack_guard_landing_page_archives() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only public route identification; no state changes.
	$requested_type    = isset( $_GET['post_type'] ) && is_string( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
	$requested_tax     = isset( $_GET['taxonomy'] ) && is_string( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
	$is_landing_single = is_singular( 'landing_page' ) || isset( $_GET['landing_page'] );
	$is_landing_tax    = is_tax( array( 'landing_page_category', 'landing_page_tag' ) )
		|| in_array( $requested_tax, array( 'landing_page_category', 'landing_page_tag' ), true )
		|| isset( $_GET['landing_page_category'] ) || isset( $_GET['landing_page_tag'] );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$is_landing_archive = is_post_type_archive( 'landing_page' )
		|| ( ! is_singular() && ! is_search() && ( 'landing_page' === get_query_var( 'post_type' ) || 'landing_page' === $requested_type ) );
	if ( is_admin() || ( ! $is_landing_archive && ! $is_landing_tax && ! $is_landing_single ) ) {
		return;
	}
	$post_type = get_post_type_object( 'landing_page' );
	if ( $post_type && $post_type->publicly_queryable && ( ( ! $is_landing_archive && ! $is_landing_tax ) || mrn_base_stack_landing_page_archives_enabled() ) ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	remove_action( 'template_redirect', 'redirect_canonical' );
}
add_action( 'template_redirect', 'mrn_base_stack_guard_landing_page_archives', 0 );

/**
 * Keep unavailable archive URLs from being guessed into unrelated single URLs.
 *
 * @param string|false $redirect_url Proposed canonical redirect.
 * @param string       $requested_url Original request.
 * @return string|false
 */
function mrn_base_stack_filter_landing_page_archive_redirect( $redirect_url, $requested_url ) {
	$post_type = get_post_type_object( 'landing_page' );
	if ( $post_type && $post_type->publicly_queryable && mrn_base_stack_landing_page_archives_enabled() ) {
		return $redirect_url;
	}
	$path = trailingslashit( (string) wp_parse_url( $requested_url, PHP_URL_PATH ) );
	foreach ( array( 'landing-page-category', 'landing-page-tag' ) as $slug ) {
		$prefix = (string) wp_parse_url( home_url( '/' . $slug . '/' ), PHP_URL_PATH );
		if ( 0 === strpos( $path, $prefix ) ) {
			return false;
		}
	}
	$prefix = (string) wp_parse_url( home_url( '/landing-pages/' ), PHP_URL_PATH );
	if ( $path === $prefix || preg_match( '#^' . preg_quote( $prefix, '#' ) . 'page/[0-9]+/$#', $path ) ) {
		return false;
	}
	if ( ( ! $post_type || ! $post_type->publicly_queryable ) && 0 === strpos( $path, $prefix ) ) {
		return false;
	}

	return $redirect_url;
}
add_filter( 'redirect_canonical', 'mrn_base_stack_filter_landing_page_archive_redirect', 10, 2 );

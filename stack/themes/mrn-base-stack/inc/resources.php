<?php
/**
 * Resource CPT registration and rendering helpers.
 *
 * A Resource is a downloadable file (any type) with basic metadata, organized
 * with the shared category/tag taxonomies. It has no public single URL or
 * archive (see mrn-admin-data-post-types) and is meant to be listed on a page
 * via the Reference Content builder row. Downloads are served through
 * mrn_base_stack_handle_resource_download() rather than linking the raw
 * upload URL, so the response can carry X-Robots-Tag: noindex (keep search
 * engines from indexing the raw file directly) and force a real download.
 *
 * @package mrn-base-stack
 */

/**
 * Treat resources as component data rather than public destinations.
 *
 * @param array $post_types Admin/data-only CPT configuration.
 * @return array
 */
function mrn_base_stack_register_resource_as_admin_data( $post_types ) {
	$show_ui = function_exists( 'mrn_base_stack_is_admin_cpt_visible' ) ? mrn_base_stack_is_admin_cpt_visible( 'resource' ) : true;

	$post_types['resource'] = array(
		'show_ui'       => $show_ui,
		'show_in_menu'  => $show_ui,
		'admin_cleanup' => true,
	);

	return $post_types;
}
add_filter( 'mrn_admin_data_post_types', 'mrn_base_stack_register_resource_as_admin_data' );

/**
 * Register the theme-owned Resource custom post type.
 *
 * Baseline args below look "public" on purpose — the mrn-admin-data-post-types
 * MU-plugin's register_post_type_args filter (already loaded before theme
 * init) forces public/publicly_queryable/has_archive/rewrite/query_var to
 * false at registration time. Do not hand-set those to false here.
 *
 * @return void
 */
function mrn_base_stack_register_resource_post_type() {
	$show_ui = function_exists( 'mrn_base_stack_is_admin_cpt_visible' ) ? mrn_base_stack_is_admin_cpt_visible( 'resource' ) : true;

	$labels = array(
		'name'                  => __( 'Resources', 'mrn-base-stack' ),
		'singular_name'         => __( 'Resource', 'mrn-base-stack' ),
		'menu_name'             => __( 'Resources', 'mrn-base-stack' ),
		'name_admin_bar'        => __( 'Resource', 'mrn-base-stack' ),
		'add_new'               => __( 'Add New', 'mrn-base-stack' ),
		'add_new_item'          => __( 'Add New Resource', 'mrn-base-stack' ),
		'new_item'              => __( 'New Resource', 'mrn-base-stack' ),
		'edit_item'             => __( 'Edit Resource', 'mrn-base-stack' ),
		'view_item'             => __( 'View Resource', 'mrn-base-stack' ),
		'view_items'            => __( 'View Resources', 'mrn-base-stack' ),
		'all_items'             => __( 'All Resources', 'mrn-base-stack' ),
		'search_items'          => __( 'Search Resources', 'mrn-base-stack' ),
		'parent_item_colon'     => __( 'Parent Resources:', 'mrn-base-stack' ),
		'not_found'             => __( 'No resources found.', 'mrn-base-stack' ),
		'not_found_in_trash'    => __( 'No resources found in Trash.', 'mrn-base-stack' ),
		'archives'              => __( 'Resource Archives', 'mrn-base-stack' ),
		'attributes'            => __( 'Resource Attributes', 'mrn-base-stack' ),
		'insert_into_item'      => __( 'Insert into resource', 'mrn-base-stack' ),
		'uploaded_to_this_item' => __( 'Uploaded to this resource', 'mrn-base-stack' ),
		'featured_image'        => __( 'Featured image', 'mrn-base-stack' ),
		'set_featured_image'    => __( 'Set featured image', 'mrn-base-stack' ),
		'remove_featured_image' => __( 'Remove featured image', 'mrn-base-stack' ),
		'use_featured_image'    => __( 'Use as featured image', 'mrn-base-stack' ),
		'filter_items_list'     => __( 'Filter resources list', 'mrn-base-stack' ),
		'items_list_navigation' => __( 'Resources list navigation', 'mrn-base-stack' ),
		'items_list'            => __( 'Resources list', 'mrn-base-stack' ),
		'item_published'        => __( 'Resource published.', 'mrn-base-stack' ),
		'item_updated'          => __( 'Resource updated.', 'mrn-base-stack' ),
	);

	register_post_type(
		'resource',
		array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => $show_ui,
			'show_in_menu'        => $show_ui,
			'show_in_rest'        => true,
			'has_archive'         => true,
			'rewrite'             => array(
				'slug'       => 'resources',
				'with_front' => false,
			),
			'menu_position'       => 12,
			'menu_icon'           => 'dashicons-media-document',
			'supports'            => array( 'title', 'excerpt', 'revisions' ),
			'taxonomies'          => array( 'category', 'post_tag' ),
			'publicly_queryable'  => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => $show_ui,
			'exclude_from_search' => false,
			'hierarchical'        => false,
			'query_var'           => true,
		)
	);
}
add_action( 'init', 'mrn_base_stack_register_resource_post_type' );

/**
 * Register resource-specific ACF fields.
 *
 * @return void
 */
function mrn_base_stack_register_resource_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_resource',
			'title'                 => 'Resource',
			'menu_order'            => 10,
			'fields'                => array(
				array(
					'key'           => 'field_mrn_resource_file',
					'label'         => 'File',
					'name'          => 'resource_file',
					'aria-label'    => '',
					'type'          => 'file',
					'return_format' => 'array',
					'library'       => 'all',
					'mime_types'    => 'pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,rtf,odt,ods,odp,jpg,jpeg,png,gif,webp,mp4,mov,webm,mp3,wav,zip',
					'required'      => 1,
					'instructions'  => 'Upload the downloadable file. Only safe file types are accepted: documents (PDF, Word, Excel, PowerPoint, RTF, OpenDocument), images (JPG, PNG, GIF, WebP), video (MP4, MOV, WebM), audio (MP3, WAV), and ZIP archives.',
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'resource',
					),
				),
			),
			'position'              => 'acf_after_title',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'description'           => 'Theme-owned resource fields.',
			'show_in_rest'          => 1,
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_resource_field_group' );

/**
 * Get a resource's uploaded file field.
 *
 * @param int|null $post_id Post ID. Defaults to the current post.
 * @return array<string, mixed>|null ACF file array, or null if unset.
 */
function mrn_base_stack_get_resource_file( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	if ( ! $post_id || ! function_exists( 'get_field' ) ) {
		return null;
	}

	$file = get_field( 'resource_file', $post_id );

	return ( is_array( $file ) && ! empty( $file['url'] ) ) ? $file : null;
}

/**
 * Get a resource's uploaded file URL.
 *
 * @param int|null $post_id Post ID. Defaults to the current post.
 * @return string Absolute file URL, or '' if unset.
 */
function mrn_base_stack_get_resource_file_url( $post_id = null ) {
	$file = mrn_base_stack_get_resource_file( $post_id );

	return ( $file && ! empty( $file['url'] ) ) ? esc_url_raw( (string) $file['url'] ) : '';
}

/**
 * Build the noindex'd download-proxy URL for a resource.
 *
 * @param int $post_id Resource post ID.
 * @return string
 */
function mrn_base_stack_get_resource_download_url( $post_id ) {
	return add_query_arg( 'mrn_resource_download', absint( $post_id ), home_url( '/' ) );
}

if ( ! function_exists( 'mrn_base_stack_register_resource_download_query_var' ) ) :
	/**
	 * Register the resource-download query var.
	 *
	 * No custom rewrite rule is registered on purpose — a plain query-string
	 * endpoint needs no permalink flush on any site this ships to.
	 *
	 * @param array $vars Public query vars.
	 * @return array
	 */
	function mrn_base_stack_register_resource_download_query_var( $vars ) {
		$vars[] = 'mrn_resource_download';

		return $vars;
	}
endif;
add_filter( 'query_vars', 'mrn_base_stack_register_resource_download_query_var' );

/**
 * Stream a resource's uploaded file instead of exposing its raw upload URL.
 *
 * Only ever serves the file already configured on a published `resource`
 * post — the query var identifies the resource, not an attachment, so there
 * is no arbitrary-file-disclosure path here.
 *
 * @return void
 */
function mrn_base_stack_handle_resource_download() {
	$post_id = absint( get_query_var( 'mrn_resource_download' ) );

	if ( $post_id < 1 ) {
		return;
	}

	if ( 'resource' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
		status_header( 404 );
		nocache_headers();
		exit;
	}

	$file = mrn_base_stack_get_resource_file( $post_id );

	if ( ! $file || empty( $file['id'] ) ) {
		status_header( 404 );
		nocache_headers();
		exit;
	}

	$path = get_attached_file( absint( $file['id'] ) );

	if ( ! $path || ! file_exists( $path ) ) {
		status_header( 404 );
		nocache_headers();
		exit;
	}

	$mime_type = ! empty( $file['mime_type'] ) ? (string) $file['mime_type'] : 'application/octet-stream';
	$filename  = ! empty( $file['filename'] ) ? (string) $file['filename'] : basename( $path );

	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'Content-Type: ' . $mime_type );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a validated media-library file to the browser, not reading arbitrary user input.
	exit;
}
add_action( 'template_redirect', 'mrn_base_stack_handle_resource_download' );

/**
 * File-extension -> Font Awesome icon class map for resource files.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_resource_file_extension_icon_map() {
	return array(
		'pdf'     => 'fa-solid fa-file-pdf',
		'doc'     => 'fa-solid fa-file-word',
		'docx'    => 'fa-solid fa-file-word',
		'xls'     => 'fa-solid fa-file-excel',
		'xlsx'    => 'fa-solid fa-file-excel',
		'csv'     => 'fa-solid fa-file-csv',
		'ppt'     => 'fa-solid fa-file-powerpoint',
		'pptx'    => 'fa-solid fa-file-powerpoint',
		'zip'     => 'fa-solid fa-file-zipper',
		'rar'     => 'fa-solid fa-file-zipper',
		'7z'      => 'fa-solid fa-file-zipper',
		'jpg'     => 'fa-solid fa-file-image',
		'jpeg'    => 'fa-solid fa-file-image',
		'png'     => 'fa-solid fa-file-image',
		'gif'     => 'fa-solid fa-file-image',
		'svg'     => 'fa-solid fa-file-image',
		'mp4'     => 'fa-solid fa-file-video',
		'mov'     => 'fa-solid fa-file-video',
		'webm'    => 'fa-solid fa-file-video',
		'mp3'     => 'fa-solid fa-file-audio',
		'wav'     => 'fa-solid fa-file-audio',
		'txt'     => 'fa-solid fa-file-lines',
		'json'    => 'fa-solid fa-file-lines',
		'xml'     => 'fa-solid fa-file-lines',
		'default' => 'fa-solid fa-file',
	);
}

/**
 * Resolve a mime type string to a Font Awesome icon class.
 *
 * Builds a one-time extension<=>mime lookup from WordPress core's own
 * wp_get_mime_types(), so there is no hand-maintained mime-type table to
 * drift out of sync with WP core.
 *
 * @param string $mime_type e.g. 'application/pdf'.
 * @return string Full FA class string, e.g. 'fa-solid fa-file-pdf'.
 */
function mrn_base_stack_get_resource_file_icon_class( $mime_type ) {
	static $mime_to_ext = null;

	$mime_type = is_string( $mime_type ) ? strtolower( trim( $mime_type ) ) : '';
	$icon_map  = mrn_base_stack_get_resource_file_extension_icon_map();

	if ( '' === $mime_type ) {
		return $icon_map['default'];
	}

	if ( null === $mime_to_ext ) {
		$mime_to_ext = array();

		foreach ( wp_get_mime_types() as $extension_group => $mime ) {
			$mime = strtolower( (string) $mime );

			if ( ! isset( $mime_to_ext[ $mime ] ) ) {
				$extensions           = explode( '|', $extension_group );
				$mime_to_ext[ $mime ] = strtolower( (string) reset( $extensions ) );
			}
		}
	}

	$extension = isset( $mime_to_ext[ $mime_type ] ) ? $mime_to_ext[ $mime_type ] : '';

	return isset( $icon_map[ $extension ] ) ? $icon_map[ $extension ] : $icon_map['default'];
}

/**
 * Build the file-type icon markup for a resource.
 *
 * @param int|null $post_id Post ID. Defaults to the current post.
 * @return string '' if the resource has no file.
 */
function mrn_base_stack_get_resource_file_icon_html( $post_id = null ) {
	$file = mrn_base_stack_get_resource_file( $post_id );

	if ( ! $file ) {
		return '';
	}

	$fa_class = mrn_base_stack_get_resource_file_icon_class( isset( $file['mime_type'] ) ? (string) $file['mime_type'] : '' );

	if ( function_exists( 'mrn_fapm_icon_is_allowed' ) && ! mrn_fapm_icon_is_allowed( $fa_class ) ) {
		return '';
	}

	return '<i class="' . esc_attr( $fa_class ) . '" aria-hidden="true"></i>';
}

/**
 * Point a resource's Reference Content list-item link at its file instead of
 * a non-existent single-post URL.
 *
 * @param string  $permalink Resolved permalink.
 * @param WP_Post $item_post Listed post.
 * @return string
 */
function mrn_base_stack_filter_resource_content_list_permalink( $permalink, $item_post ) {
	if ( ! ( $item_post instanceof WP_Post ) || 'resource' !== $item_post->post_type ) {
		return $permalink;
	}

	$file = mrn_base_stack_get_resource_file( $item_post->ID );

	return $file ? mrn_base_stack_get_resource_download_url( $item_post->ID ) : $permalink;
}
add_filter( 'mrn_base_stack_content_list_item_permalink', 'mrn_base_stack_filter_resource_content_list_permalink', 10, 2 );

/**
 * Prefix a resource's Reference Content list-item title with its file-type icon.
 *
 * @param string  $icon_html Icon markup so far.
 * @param WP_Post $item_post Listed post.
 * @return string
 */
function mrn_base_stack_filter_resource_content_list_title_icon( $icon_html, $item_post ) {
	if ( ! ( $item_post instanceof WP_Post ) || 'resource' !== $item_post->post_type ) {
		return $icon_html;
	}

	return mrn_base_stack_get_resource_file_icon_html( $item_post->ID );
}
add_filter( 'mrn_base_stack_content_list_item_title_icon_html', 'mrn_base_stack_filter_resource_content_list_title_icon', 10, 2 );

/**
 * Whether a page's raw post meta contains a Reference Content row configured
 * with list_post_type = 'resource', so Font Awesome only enqueues where
 * actually needed.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function mrn_base_stack_resource_content_list_needs_fontawesome_from_post_meta( $post_id ) {
	$post_id = absint( $post_id );

	if ( $post_id < 1 ) {
		return false;
	}

	$post_meta = get_post_meta( $post_id, '', false );

	if ( ! is_array( $post_meta ) ) {
		return false;
	}

	foreach ( $post_meta as $meta_key => $meta_values ) {
		if ( ! is_string( $meta_key ) || ! preg_match( '/list_post_type$/', $meta_key ) || ! is_array( $meta_values ) ) {
			continue;
		}

		$value = reset( $meta_values );

		if ( is_scalar( $value ) && 'resource' === sanitize_key( (string) $value ) ) {
			return true;
		}
	}

	return false;
}

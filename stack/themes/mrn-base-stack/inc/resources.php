<?php
/**
 * Resource CPT registration and rendering helpers.
 *
 * A Resource is a downloadable file (any type) with basic metadata, organized
 * with the shared category/tag taxonomies. It has no public single URL or
 * archive (see mrn-admin-data-post-types) and is meant to be listed on a page
 * via the Reference Content builder row. Item links point to the uploaded
 * file; PDFs open in a new tab. The separate legacy download endpoint remains
 * available for existing links and sends a noindex, forced-download response.
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
			'supports'            => array( 'title', 'excerpt', 'thumbnail', 'revisions', 'mrn-content-list-links' ),
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
					'key'           => 'field_mrn_resource_source',
					'label'         => 'Resource Source',
					'name'          => 'resource_source',
					'type'          => 'radio',
					'choices'       => array(
						'file'         => 'Uploaded file or video',
						'remote_video' => 'Remote video',
					),
					'default_value' => 'file',
					'layout'        => 'horizontal',
					'instructions'  => 'Video resources open in the Stack video modal when Enable Item Links is on. Use the featured image as the video poster.',
				),
				array(
					'key'               => 'field_mrn_resource_file',
					'label'             => 'File',
					'name'              => 'resource_file',
					'aria-label'        => '',
					'type'              => 'file',
					'return_format'     => 'array',
					'library'           => 'all',
					'mime_types'        => 'pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,rtf,odt,ods,odp,jpg,jpeg,png,gif,webp,mp4,mov,webm,mp3,wav,zip',
					'required'          => 1,
					'instructions'      => 'This resource is Content Only: it has no standalone page or archive. Add it to a Reference Content (Content List) row and turn Enable Item Links on. Uploaded videos (MP4, MOV, WebM) open in the video modal; PDFs open in a new tab. Turning links off affects that row only. Also accepts documents, images, audio, and ZIP archives.',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_mrn_resource_source',
								'operator' => '!=',
								'value'    => 'remote_video',
							),
						),
					),
				),
				array(
					'key'               => 'field_mrn_resource_video_remote',
					'label'             => 'Remote Video URL',
					'name'              => 'resource_video_remote',
					'type'              => 'url',
					'required'          => 1,
					'instructions'      => 'Enter a YouTube, Vimeo, Google Drive video link, or a direct MP4, MOV, or WebM URL. The provider must allow playback or embedding for visitors.',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_mrn_resource_source',
								'operator' => '==',
								'value'    => 'remote_video',
							),
						),
					),
				),
				array(
					'key'               => 'field_mrn_resource_use_provider_poster',
					'label'             => 'Use Provider Poster',
					'name'              => 'resource_use_provider_poster',
					'type'              => 'true_false',
					'ui'                => 0,
					'message'           => 'Enabled',
					'default_value'     => 0,
					'instructions'      => 'On save, use the YouTube or Vimeo poster as the featured image when available. Turn off to restore or choose a manual featured image. Google Drive and direct video files use the manual image.',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_mrn_resource_source',
								'operator' => '==',
								'value'    => 'remote_video',
							),
						),
					),
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
 * Import a provider's poster through WordPress media, only during an editor save.
 *
 * Discovery is disabled and thumbnail hosts are restricted to the providers'
 * image CDNs. WordPress validates HTTP destinations and uploaded image bytes.
 *
 * @param string $url     Supported remote video URL.
 * @param int    $post_id Resource ID.
 * @return int Attachment ID, or zero when no public provider poster is available.
 */
function mrn_base_stack_import_resource_provider_poster( $url, $post_id ) {
	$video    = mrn_base_stack_get_resource_remote_video( $url );
	$provider = $video['provider'] ?? '';
	$hosts    = array(
		'youtube' => array( 'i.ytimg.com', 'img.youtube.com' ),
		'vimeo'   => array( 'i.vimeocdn.com' ),
	);
	if ( empty( $hosts[ $provider ] ) || ! current_user_can( 'upload_files' ) ) {
		return 0;
	}

	// WordPress's YouTube oEmbed provider does not match /embed/ URLs.
	$provider_url = 'youtube' === $provider ? 'https://www.youtube.com/watch?v=' . rawurlencode( basename( (string) wp_parse_url( $video['url'], PHP_URL_PATH ) ) ) : $url;
	$data         = _wp_oembed_get_object()->get_data( $provider_url, array( 'discover' => false, 'width' => 1280 ) );

	$thumbnail = is_object( $data ) && isset( $data->thumbnail_url ) && is_string( $data->thumbnail_url ) ? esc_url_raw( $data->thumbnail_url, array( 'https' ) ) : '';
	$parts     = wp_parse_url( $thumbnail );
	if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || ! in_array( strtolower( $parts['host'] ?? '' ), $hosts[ $provider ], true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// Bound both the save delay and download size; do not follow CDN redirects.
	$limit_download = static function ( $args, $request_url ) use ( $thumbnail ) {
		if ( $thumbnail === $request_url ) {
			$args['limit_response_size'] = 5 * MB_IN_BYTES;
			$args['redirection']         = 0;
		}
		return $args;
	};
	add_filter( 'http_request_args', $limit_download, 10, 2 );
	$temporary = download_url( $thumbnail, 8 );
	remove_filter( 'http_request_args', $limit_download, 10 );
	if ( is_wp_error( $temporary ) ) {
		return 0;
	}
	if ( filesize( $temporary ) >= 5 * MB_IN_BYTES ) {
		wp_delete_file( $temporary );
		return 0;
	}

	$extensions = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif' );
	$mime       = wp_get_image_mime( $temporary );
	if ( ! isset( $extensions[ $mime ] ) ) {
		wp_delete_file( $temporary );
		return 0;
	}
	$title = sanitize_text_field( wp_strip_all_tags( get_the_title( $post_id ) ) );
	$image = media_handle_sideload(
		array(
			'name'     => 'resource-' . $post_id . '-video-poster.' . $extensions[ $mime ],
			'tmp_name' => $temporary,
		),
		$post_id,
		$title
	);
	if ( is_wp_error( $image ) ) {
		wp_delete_file( $temporary );
		return 0;
	}
	update_post_meta( $image, '_source_url', $thumbnail );
	update_post_meta( $image, '_wp_attachment_image_alt', $title );
	return (int) $image;
}

/**
 * Apply the optional provider image after ACF saves, preserving manual selection.
 *
 * ACF owns request nonce validation. This hook reads saved fields, never request
 * input, and also requires the current user's post-edit capability.
 *
 * @param int|string $post_id ACF post ID.
 * @return void
 */
function mrn_base_stack_sync_resource_provider_poster( $post_id ) {
	$post_id = is_numeric( $post_id ) ? (int) $post_id : 0;
	if ( $post_id <= 0 || 'resource' !== get_post_type( $post_id ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! function_exists( 'get_field' ) ) {
		return;
	}

	$cached  = get_post_meta( $post_id, '_mrn_resource_provider_poster', true );
	$cached  = is_array( $cached ) ? $cached : array();
	$auto_id = absint( $cached['attachment_id'] ?? 0 );
	$active  = (bool) get_post_meta( $post_id, '_mrn_resource_provider_poster_active', true );
	$current = (int) get_post_thumbnail_id( $post_id );
	$enabled = 'remote_video' === get_field( 'resource_source', $post_id ) && get_field( 'resource_use_provider_poster', $post_id );
	$poster  = 0;
	if ( $enabled ) {
		// Remember a newly selected manual image before applying the automatic one.
		if ( ! $active || $current !== $auto_id || ! metadata_exists( 'post', $post_id, '_mrn_resource_manual_thumbnail' ) ) {
			update_post_meta( $post_id, '_mrn_resource_manual_thumbnail', $current );
		}
		$url    = get_field( 'resource_video_remote', $post_id );
		$url    = is_string( $url ) ? esc_url_raw( trim( $url ), array( 'http', 'https' ) ) : '';
		$source = hash( 'sha256', $url );
		if ( $source === ( $cached['source'] ?? '' ) && 'trash' !== get_post_status( $auto_id ) && wp_attachment_is_image( $auto_id ) ) {
			$poster = $auto_id;
		} else {
			$poster = mrn_base_stack_import_resource_provider_poster( $url, $post_id );
			if ( $poster ) {
				update_post_meta( $post_id, '_mrn_resource_provider_poster', array( 'source' => $source, 'attachment_id' => $poster ) );
			}
		}
	}
	if ( $poster ) {
		set_post_thumbnail( $post_id, $poster );
		update_post_meta( $post_id, '_mrn_resource_provider_poster_active', 1 );
	} elseif ( $active && $auto_id && $current === $auto_id ) {
		// An unavailable or changed provider must never retain the wrong poster.
		$manual = absint( get_post_meta( $post_id, '_mrn_resource_manual_thumbnail', true ) );
		if ( $manual && 'trash' !== get_post_status( $manual ) && wp_attachment_is_image( $manual ) ) {
			set_post_thumbnail( $post_id, $manual );
		} else {
			delete_post_thumbnail( $post_id );
		}
	}
	if ( ! $poster ) {
		delete_post_meta( $post_id, '_mrn_resource_provider_poster_active' );
	}
}
add_action( 'acf/save_post', 'mrn_base_stack_sync_resource_provider_poster', 20 );

/**
 * Run after WordPress REST authentication and ACF additional fields are saved.
 *
 * @param WP_Post $post Updated Resource.
 * @return void
 */
function mrn_base_stack_sync_resource_provider_poster_after_rest_save( $post ) {
	mrn_base_stack_sync_resource_provider_poster( $post->ID );
}
add_action( 'rest_after_insert_resource', 'mrn_base_stack_sync_resource_provider_poster_after_rest_save', 20 );

/**
 * Resolve a supported remote video using the existing Stack embed contract.
 *
 * @param mixed $url Video URL.
 * @return array<string, string> Modal URL/type, or an empty array.
 */
function mrn_base_stack_get_resource_remote_video( $url ) {
	$url    = is_string( $url ) ? esc_url_raw( trim( $url ), array( 'http', 'https' ) ) : '';
	$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
	if ( '' === $host || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		return array();
	}

	$hosts = array( 'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com', 'drive.google.com' );
	if ( in_array( $host, $hosts, true ) && function_exists( 'mrn_base_stack_get_video_embed' ) ) {
		$embed = mrn_base_stack_get_video_embed( $url );
		if ( ! empty( $embed['embed_url'] ) ) {
			return array(
				'url'      => $embed['embed_url'],
				'type'     => 'google_drive' === $embed['provider'] ? 'external' : 'video',
				'provider' => $embed['provider'],
			);
		}
	}

	$extension = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	return in_array( $extension, array( 'mp4', 'mov', 'webm' ), true ) ? array(
		'url'  => $url,
		'type' => 'video',
	) : array();
}

/**
 * Validate the remote URL without fetching third-party content.
 *
 * @param bool|string $valid Existing validation result.
 * @param mixed       $value Submitted URL.
 * @return bool|string
 */
function mrn_base_stack_validate_resource_remote_video( $valid, $value ) {
	if ( true !== $valid || empty( $value ) ) {
		return $valid;
	}
	return mrn_base_stack_get_resource_remote_video( $value ) ? true : __( 'Enter a supported YouTube, Vimeo, Google Drive, MP4, MOV, or WebM video URL.', 'mrn-base-stack' );
}
add_filter( 'acf/validate_value/key=field_mrn_resource_video_remote', 'mrn_base_stack_validate_resource_remote_video', 10, 2 );

/**
 * Resolve a Resource video. Unset source fields preserve existing file records.
 *
 * @param int $post_id Resource ID.
 * @return array<string, string> Modal URL/type, or an empty array.
 */
function mrn_base_stack_get_resource_video( $post_id ) {
	if ( ! function_exists( 'get_field' ) ) {
		return array();
	}
	if ( 'remote_video' === get_field( 'resource_source', $post_id ) ) {
		return mrn_base_stack_get_resource_remote_video( get_field( 'resource_video_remote', $post_id ) );
	}
	$file = mrn_base_stack_get_resource_file( $post_id );
	if ( $file && in_array( $file['mime_type'] ?? '', array( 'video/mp4', 'video/quicktime', 'video/webm' ), true ) ) {
		return array(
			'url'  => esc_url_raw( $file['url'], array( 'http', 'https' ) ),
			'type' => 'video',
		);
	}
	return array();
}

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
	if ( 'remote_video' === get_field( 'resource_source', $post_id ) ) {
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

	$file  = mrn_base_stack_get_resource_file( $item_post->ID );
	$video = mrn_base_stack_get_resource_video( $item_post->ID );

	return $video ? $video['url'] : ( $file ? mrn_base_stack_get_resource_file_url( $item_post->ID ) : '' );
}
add_filter( 'mrn_base_stack_content_list_item_permalink', 'mrn_base_stack_filter_resource_content_list_permalink', 10, 2 );

/**
 * Open PDF resource links in a new tab.
 *
 * @param array<string, string> $attributes Link attributes.
 * @param WP_Post               $item_post Listed post.
 * @return array<string, string>
 */
function mrn_base_stack_filter_resource_content_list_link_attributes( $attributes, $item_post ) {
	if ( ! is_array( $attributes ) || ! ( $item_post instanceof WP_Post ) || 'resource' !== $item_post->post_type ) {
		return $attributes;
	}

	$file  = mrn_base_stack_get_resource_file( $item_post->ID );
	$video = mrn_base_stack_get_resource_video( $item_post->ID );
	if ( $video ) {
		$attributes['data-mrn-video-modal'] = '1';
		$attributes['data-type']            = $video['type'];
		$attributes['aria-haspopup']        = 'dialog';
		$attributes['aria-label']           = sprintf( /* translators: %s: Resource title. */ __( 'Play video: %s', 'mrn-base-stack' ), get_the_title( $item_post ) );
		unset( $attributes['target'], $attributes['rel'] );
		return $attributes;
	}

	if ( ! is_array( $file ) || 'application/pdf' !== strtolower( (string) ( $file['mime_type'] ?? '' ) ) ) {
		return $attributes;
	}

	$attributes['target'] = '_blank';
	$attributes['rel']    = 'noopener';

	return $attributes;
}
add_filter( 'mrn_base_stack_content_list_item_link_attributes', 'mrn_base_stack_filter_resource_content_list_link_attributes', 10, 2 );

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

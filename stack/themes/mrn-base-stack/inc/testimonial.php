<?php
/**
 * Testimonial CPT registration and rendering helpers.
 *
 * @package mrn-base-stack
 */

/**
 * Register the theme-owned Testimonial custom post type.
 *
 * @return void
 */
function mrn_base_stack_register_testimonial_post_type() {
	$show_ui = function_exists( 'mrn_base_stack_is_admin_cpt_visible' ) ? mrn_base_stack_is_admin_cpt_visible( 'testimonial' ) : true;

	$labels = array(
		'name'                  => __( 'Testimonials', 'mrn-base-stack' ),
		'singular_name'         => __( 'Testimonial', 'mrn-base-stack' ),
		'menu_name'             => __( 'Testimonials', 'mrn-base-stack' ),
		'name_admin_bar'        => __( 'Testimonial', 'mrn-base-stack' ),
		'add_new'               => __( 'Add New', 'mrn-base-stack' ),
		'add_new_item'          => __( 'Add New Testimonial', 'mrn-base-stack' ),
		'new_item'              => __( 'New Testimonial', 'mrn-base-stack' ),
		'edit_item'             => __( 'Edit Testimonial', 'mrn-base-stack' ),
		'view_item'             => __( 'View Testimonial', 'mrn-base-stack' ),
		'view_items'            => __( 'View Testimonials', 'mrn-base-stack' ),
		'all_items'             => __( 'All Testimonials', 'mrn-base-stack' ),
		'search_items'          => __( 'Search Testimonials', 'mrn-base-stack' ),
		'parent_item_colon'     => __( 'Parent Testimonials:', 'mrn-base-stack' ),
		'not_found'             => __( 'No testimonials found.', 'mrn-base-stack' ),
		'not_found_in_trash'    => __( 'No testimonials found in Trash.', 'mrn-base-stack' ),
		'archives'              => __( 'Testimonial Archives', 'mrn-base-stack' ),
		'attributes'            => __( 'Testimonial Attributes', 'mrn-base-stack' ),
		'insert_into_item'      => __( 'Insert into testimonial', 'mrn-base-stack' ),
		'uploaded_to_this_item' => __( 'Uploaded to this testimonial', 'mrn-base-stack' ),
		'featured_image'        => __( 'Featured image', 'mrn-base-stack' ),
		'set_featured_image'    => __( 'Set featured image', 'mrn-base-stack' ),
		'remove_featured_image' => __( 'Remove featured image', 'mrn-base-stack' ),
		'use_featured_image'    => __( 'Use as featured image', 'mrn-base-stack' ),
		'filter_items_list'     => __( 'Filter testimonials list', 'mrn-base-stack' ),
		'items_list_navigation' => __( 'Testimonials list navigation', 'mrn-base-stack' ),
		'items_list'            => __( 'Testimonials list', 'mrn-base-stack' ),
		'item_published'        => __( 'Testimonial published.', 'mrn-base-stack' ),
		'item_updated'          => __( 'Testimonial updated.', 'mrn-base-stack' ),
	);

	register_post_type(
		'testimonial',
		array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => $show_ui,
			'show_in_menu'        => $show_ui,
			'show_in_rest'        => true,
			'has_archive'         => true,
			'rewrite'             => array(
				'slug'       => 'testimonials',
				'with_front' => false,
			),
			'menu_position'       => 8,
			'menu_icon'           => 'dashicons-format-quote',
			'supports'            => array( 'title', 'revisions' ),
			'publicly_queryable'  => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => $show_ui,
			'exclude_from_search' => false,
			'hierarchical'        => false,
			'query_var'           => true,
		)
	);
}
add_action( 'init', 'mrn_base_stack_register_testimonial_post_type' );

/**
 * Register testimonial-specific ACF fields.
 *
 * @return void
 */
function mrn_base_stack_register_testimonial_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_testimonial',
			'title'                 => 'Testimonial',
			'menu_order'            => 10,
			'fields'                => array(
				mrn_base_stack_get_inline_text_field( 'field_mrn_testimonial_label', 'Label', 'testimonial_label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_testimonial_label_tag', 'testimonial_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_testimonial_heading', 'Heading', 'testimonial_heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_testimonial_heading_tag', 'testimonial_heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_testimonial_subheading', 'Subheading', 'testimonial_subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_testimonial_subheading_tag', 'testimonial_subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'          => 'field_mrn_testimonial_name',
					'label'        => 'Name',
					'name'         => 'testimonial_name',
					'aria-label'   => '',
					'type'         => 'text',
					'instructions' => 'First/last name',
					'required'     => 1,
					'wrapper'      => array(
						'width' => '50',
					),
				),
				array(
					'key'        => 'field_mrn_testimonial_company',
					'label'      => 'Company',
					'name'       => 'testimonial_company',
					'aria-label' => '',
					'type'       => 'text',
					'wrapper'    => array(
						'width' => '50',
					),
				),
				array(
					'key'        => 'field_mrn_testimonial_position',
					'label'      => 'Position',
					'name'       => 'testimonial_position',
					'aria-label' => '',
					'type'       => 'text',
					'wrapper'    => array(
						'width' => '50',
					),
				),
				array(
					'key'        => 'field_mrn_testimonial_website_url',
					'label'      => 'Website URL',
					'name'       => 'testimonial_website_url',
					'aria-label' => '',
					'type'       => 'url',
					'wrapper'    => array(
						'width' => '50',
					),
				),
				array(
					'key'          => 'field_mrn_testimonial_content',
					'label'        => 'Testimonial',
					'name'         => 'testimonial_content',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
					'wrapper'      => array(
						'width' => '100',
					),
				),
				array(
					'key'           => 'field_mrn_testimonial_image_logo',
					'label'         => 'Image/Logo',
					'name'          => 'testimonial_image_logo',
					'aria-label'    => '',
					'type'          => 'image',
					'return_format' => 'id',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'mime_types'    => 'jpg,jpeg,png,gif,webp,svg',
				),
				array(
					'key'           => 'field_mrn_testimonial_display_style',
					'label'         => 'Display Style',
					'name'          => 'testimonial_display_style',
					'aria-label'    => '',
					'type'          => 'select',
					'instructions'  => 'Choose the presentation style this testimonial should use.',
					'required'      => 1,
					'choices'       => function_exists( 'mrn_base_stack_get_display_style_choices_for_entity' ) ? mrn_base_stack_get_display_style_choices_for_entity( 'post_type', 'testimonial' ) : array(),
					'default_value' => 'story',
					'allow_null'    => 0,
					'multiple'      => 0,
					'ui'            => 1,
					'return_format' => 'value',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'          => 'field_mrn_testimonial_video_remote',
					'label'        => 'External Video URL',
					'name'         => 'testimonial_video_remote',
					'aria-label'   => '',
					'type'         => 'url',
					'instructions' => 'Paste a YouTube, Vimeo, or direct MP4/WebM/MOV video URL.',
					'wrapper'      => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_testimonial_video_upload',
					'label'         => 'Video Upload',
					'name'          => 'testimonial_video_upload',
					'aria-label'    => '',
					'type'          => 'file',
					'return_format' => 'array',
					'library'       => 'all',
					'mime_types'    => 'mp4,webm,mov',
					'instructions'  => 'Optional media-library video. When both upload and external URL are set, the upload is used first.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'testimonial',
					),
				),
			),
			'position'              => 'acf_after_title',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'description'           => 'Theme-owned testimonial fields.',
			'show_in_rest'          => 1,
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_testimonial_field_group' );

/**
 * Load live display-style choices into the testimonial display-style field.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_load_testimonial_display_style_field_choices( $field ) {
	if ( ! is_array( $field ) || ! function_exists( 'mrn_base_stack_get_display_style_choices_for_entity' ) ) {
		return $field;
	}

	$field['choices']       = mrn_base_stack_get_display_style_choices_for_entity( 'post_type', 'testimonial' );
	$field['default_value'] = function_exists( 'mrn_base_stack_normalize_display_style' )
		? mrn_base_stack_normalize_display_style( $field['default_value'] ?? 'story', 'post_type', 'testimonial', 'story' )
		: 'story';

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_testimonial_display_style', 'mrn_base_stack_load_testimonial_display_style_field_choices' );
add_filter( 'acf/prepare_field/key=field_mrn_testimonial_display_style', 'mrn_base_stack_load_testimonial_display_style_field_choices' );

/**
 * Get the normalized testimonial display style.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return string
 */
function mrn_base_stack_get_testimonial_display_style( $post_id = null ) {
	$post_id = $post_id ? absint( $post_id ) : get_the_ID();
	$style   = '';

	if ( $post_id && function_exists( 'get_field' ) ) {
		$field_value = get_field( 'testimonial_display_style', $post_id );
		if ( is_string( $field_value ) ) {
			$style = $field_value;
		}
	}

	if ( '' === trim( (string) $style ) && $post_id ) {
		$style = (string) get_post_meta( $post_id, 'testimonial_display_style', true );
	}

	return function_exists( 'mrn_base_stack_normalize_display_style' )
		? mrn_base_stack_normalize_display_style( $style, 'post_type', 'testimonial', 'story' )
		: sanitize_key( $style );
}

/**
 * Get the public testimonial data for a post.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_testimonial_data( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	$data = array(
		'label'         => '',
		'heading'       => '',
		'subheading'    => '',
		'name'          => get_the_title( $post_id ),
		'company'       => '',
		'position'      => '',
		'website_url'   => '',
		'content'       => '',
		'image_logo'    => null,
		'display_style' => 'story',
		'video_remote'  => '',
		'video_upload'  => null,
		'video_url'     => '',
		'video_kind'    => '',
		'video_mime'    => '',
	);

	if ( ! $post_id || ! function_exists( 'get_field' ) ) {
		return $data;
	}

	$label = get_field( 'testimonial_label', $post_id );
	if ( is_string( $label ) ) {
		$data['label'] = trim( $label );
	}

	$heading = get_field( 'testimonial_heading', $post_id );
	if ( is_string( $heading ) ) {
		$data['heading'] = trim( $heading );
	}

	$subheading = get_field( 'testimonial_subheading', $post_id );
	if ( is_string( $subheading ) ) {
		$data['subheading'] = trim( $subheading );
	}

	$name = get_field( 'testimonial_name', $post_id );
	if ( is_string( $name ) && '' !== trim( $name ) ) {
		$data['name'] = trim( $name );
	}

	$company = get_field( 'testimonial_company', $post_id );
	if ( is_string( $company ) ) {
		$data['company'] = trim( $company );
	}

	$position = get_field( 'testimonial_position', $post_id );
	if ( is_string( $position ) ) {
		$data['position'] = trim( $position );
	}

	$website_url = get_field( 'testimonial_website_url', $post_id );
	if ( is_string( $website_url ) ) {
		$data['website_url'] = trim( $website_url );
	}

	$content = get_field( 'testimonial_content', $post_id );
	if ( is_string( $content ) ) {
		$data['content'] = $content;
	}

	$image_logo = get_field( 'testimonial_image_logo', $post_id );
	if ( function_exists( 'mrn_base_stack_image_has_content' ) && mrn_base_stack_image_has_content( $image_logo ) ) {
		$data['image_logo'] = $image_logo;
	}

	$data['display_style'] = mrn_base_stack_get_testimonial_display_style( $post_id );

	$video_remote = get_field( 'testimonial_video_remote', $post_id );
	if ( is_string( $video_remote ) ) {
		$data['video_remote'] = trim( $video_remote );
	}

	$video_upload = get_field( 'testimonial_video_upload', $post_id );
	if ( is_array( $video_upload ) ) {
		$data['video_upload'] = $video_upload;
	}

	$upload_video_url  = '';
	$upload_video_mime = '';
	if ( is_array( $video_upload ) ) {
		if ( isset( $video_upload['url'] ) && is_string( $video_upload['url'] ) ) {
			$upload_video_url = trim( $video_upload['url'] );
		} elseif ( ! empty( $video_upload['ID'] ) ) {
			$attachment_url   = wp_get_attachment_url( (int) $video_upload['ID'] );
			$upload_video_url = is_string( $attachment_url ) ? $attachment_url : '';
		}

		if ( isset( $video_upload['mime_type'] ) && is_string( $video_upload['mime_type'] ) ) {
			$upload_video_mime = trim( $video_upload['mime_type'] );
		} elseif ( ! empty( $video_upload['ID'] ) ) {
			$attachment_mime   = get_post_mime_type( (int) $video_upload['ID'] );
			$upload_video_mime = is_string( $attachment_mime ) ? $attachment_mime : '';
		}
	}

	if ( '' !== $upload_video_url ) {
		$data['video_url']  = $upload_video_url;
		$data['video_kind'] = 'local';
		$data['video_mime'] = $upload_video_mime;
	} elseif ( '' !== $data['video_remote'] ) {
		$remote_video = mrn_base_stack_get_testimonial_remote_video_embed(
			$data['video_remote'],
			array(
				'autoplay'   => false,
				'muted'      => false,
				'loop'       => false,
				'controls'   => true,
				'background' => false,
			)
		);

		if ( '' !== $remote_video['embed_url'] ) {
			$data['video_url']  = $remote_video['embed_url'];
			$data['video_kind'] = $remote_video['kind'];
			$data['video_mime'] = $remote_video['mime'];
		}
	}

	return $data;
}

/**
 * Determine whether a URL points directly to a playable video file.
 *
 * @param string $url URL to inspect.
 * @return bool
 */
function mrn_base_stack_is_testimonial_direct_video_url( $url ) {
	return is_string( $url ) && 1 === preg_match( '~\.(mp4|webm|mov)(?:$|[?#])~i', $url );
}

/**
 * Resolve a direct-video URL extension to a MIME type.
 *
 * @param string $url URL to inspect.
 * @return string
 */
function mrn_base_stack_get_testimonial_video_mime_from_url( $url ) {
	$path      = wp_parse_url( $url, PHP_URL_PATH );
	$extension = is_string( $path ) ? strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';

	if ( 'mp4' === $extension ) {
		return 'video/mp4';
	}

	if ( 'webm' === $extension ) {
		return 'video/webm';
	}

	if ( 'mov' === $extension ) {
		return 'video/quicktime';
	}

	return '';
}

/**
 * Normalize an external testimonial video URL into the deferred-media contract.
 *
 * @param string               $url     External video URL.
 * @param array<string, mixed> $options Embed behavior options.
 * @return array{kind:string,provider:string,embed_url:string,mime:string}
 */
function mrn_base_stack_get_testimonial_remote_video_embed( $url, array $options = array() ) {
	$raw_url = is_string( $url ) ? trim( $url ) : '';
	if ( '' === $raw_url ) {
		return array(
			'kind'      => '',
			'provider'  => '',
			'embed_url' => '',
			'mime'      => '',
		);
	}

	$sanitized_url = esc_url_raw( $raw_url );
	if ( '' === $sanitized_url ) {
		return array(
			'kind'      => '',
			'provider'  => '',
			'embed_url' => '',
			'mime'      => '',
		);
	}

	if ( mrn_base_stack_is_testimonial_direct_video_url( $sanitized_url ) ) {
		return array(
			'kind'      => 'local',
			'provider'  => 'direct',
			'embed_url' => $sanitized_url,
			'mime'      => mrn_base_stack_get_testimonial_video_mime_from_url( $sanitized_url ),
		);
	}

	if ( function_exists( 'mrn_base_stack_get_video_embed' ) ) {
		$embed = mrn_base_stack_get_video_embed( $sanitized_url, $options );
		if ( is_array( $embed ) && ! empty( $embed['embed_url'] ) && is_string( $embed['embed_url'] ) ) {
			return array(
				'kind'      => 'remote',
				'provider'  => isset( $embed['provider'] ) && is_string( $embed['provider'] ) ? $embed['provider'] : '',
				'embed_url' => $embed['embed_url'],
				'mime'      => '',
			);
		}
	}

	$options = wp_parse_args(
		$options,
		array(
			'autoplay'   => false,
			'muted'      => false,
			'loop'       => false,
			'controls'   => true,
			'background' => false,
		)
	);

	if ( preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $sanitized_url, $matches ) ) {
		$video_id = $matches[1];
		$query    = array(
			'autoplay'       => ! empty( $options['autoplay'] ) ? '1' : '0',
			'mute'           => ! empty( $options['muted'] ) ? '1' : '0',
			'controls'       => ! empty( $options['controls'] ) ? '1' : '0',
			'loop'           => ! empty( $options['loop'] ) ? '1' : '0',
			'playlist'       => ! empty( $options['loop'] ) ? $video_id : '',
			'playsinline'    => '1',
			'rel'            => '0',
			'modestbranding' => '1',
		);

		return array(
			'kind'      => 'remote',
			'provider'  => 'youtube',
			'embed_url' => sprintf( 'https://www.youtube.com/embed/%s?%s', rawurlencode( $video_id ), http_build_query( array_filter( $query, 'strlen' ), '', '&', PHP_QUERY_RFC3986 ) ),
			'mime'      => '',
		);
	}

	if ( preg_match( '~(?:vimeo\.com/(?:video/)?|player\.vimeo\.com/video/)([0-9]+)~', $sanitized_url, $matches ) ) {
		$video_id = $matches[1];
		$query    = array(
			'autoplay'   => ! empty( $options['autoplay'] ) ? '1' : '0',
			'muted'      => ! empty( $options['muted'] ) ? '1' : '0',
			'loop'       => ! empty( $options['loop'] ) ? '1' : '0',
			'background' => ! empty( $options['background'] ) ? '1' : '0',
			'autopause'  => ! empty( $options['background'] ) ? '0' : '1',
			'controls'   => ! empty( $options['controls'] ) ? '1' : '0',
			'byline'     => '0',
			'title'      => '0',
		);

		return array(
			'kind'      => 'remote',
			'provider'  => 'vimeo',
			'embed_url' => sprintf( 'https://player.vimeo.com/video/%s?%s', rawurlencode( $video_id ), http_build_query( array_filter( $query, 'strlen' ), '', '&', PHP_QUERY_RFC3986 ) ),
			'mime'      => '',
		);
	}

	return array(
		'kind'      => '',
		'provider'  => '',
		'embed_url' => '',
		'mime'      => '',
	);
}

/**
 * Determine whether a testimonial needs deferred front-end media runtime assets.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return bool
 */
function mrn_base_stack_testimonial_requires_front_end_runtime( $post_id = null ) {
	$post_id = $post_id ? absint( $post_id ) : get_queried_object_id();
	if ( ! $post_id ) {
		return false;
	}

	$remote_video = get_post_meta( $post_id, 'testimonial_video_remote', true );
	if ( is_string( $remote_video ) && '' !== trim( $remote_video ) ) {
		return true;
	}

	$video_upload = get_post_meta( $post_id, 'testimonial_video_upload', true );
	if ( is_scalar( $video_upload ) && '' !== trim( (string) $video_upload ) ) {
		return true;
	}

	return false;
}

/**
 * Build a short plain-text excerpt from the testimonial body field.
 *
 * @param int|null $post_id Post ID to inspect.
 * @param int      $length  Excerpt length in words.
 * @return string
 */
function mrn_base_stack_get_testimonial_excerpt( $post_id = null, $length = 28 ) {
	$data     = mrn_base_stack_get_testimonial_data( $post_id );
	$segments = array(
		isset( $data['subheading'] ) && is_string( $data['subheading'] ) ? $data['subheading'] : '',
		isset( $data['content'] ) && is_string( $data['content'] ) ? $data['content'] : '',
	);
	$content  = wp_strip_all_tags( implode( ' ', array_filter( $segments ) ) );
	$content  = preg_replace( '/\s+/', ' ', $content );
	$content  = is_string( $content ) ? trim( $content ) : '';

	if ( '' === $content ) {
		return '';
	}

	return wp_trim_words( $content, $length );
}

/**
 * Add the display style to the testimonial admin list.
 *
 * @param array<string, string> $columns Admin columns.
 * @return array<string, string>
 */
function mrn_base_stack_testimonial_admin_columns( $columns ) {
	$updated_columns = array();

	foreach ( $columns as $key => $label ) {
		$updated_columns[ $key ] = $label;

		if ( 'title' === $key ) {
			$updated_columns['mrn_testimonial_display_style'] = __( 'Display Style', 'mrn-base-stack' );
		}
	}

	return $updated_columns;
}
add_filter( 'manage_testimonial_posts_columns', 'mrn_base_stack_testimonial_admin_columns' );

/**
 * Render the display style admin column.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function mrn_base_stack_testimonial_admin_column_content( $column, $post_id ) {
	if ( 'mrn_testimonial_display_style' !== $column ) {
		return;
	}

	$style   = mrn_base_stack_get_testimonial_display_style( $post_id );
	$choices = function_exists( 'mrn_base_stack_get_display_style_choices_for_entity' ) ? mrn_base_stack_get_display_style_choices_for_entity( 'post_type', 'testimonial' ) : array();

	echo esc_html( isset( $choices[ $style ] ) ? $choices[ $style ] : $style );
}
add_action( 'manage_testimonial_posts_custom_column', 'mrn_base_stack_testimonial_admin_column_content', 10, 2 );

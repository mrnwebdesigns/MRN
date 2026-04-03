<?php
/**
 * Gallery CPT registration, field groups, and rendering helpers.
 *
 * @package mrn-base-stack
 */

/**
 * Register the theme-owned Gallery custom post type.
 *
 * @return void
 */
function mrn_base_stack_register_gallery_post_type() {
	$show_ui = function_exists( 'mrn_base_stack_is_admin_cpt_visible' ) ? mrn_base_stack_is_admin_cpt_visible( 'gallery' ) : true;

	$labels = array(
		'name'                  => __( 'Galleries', 'mrn-base-stack' ),
		'singular_name'         => __( 'Gallery', 'mrn-base-stack' ),
		'menu_name'             => __( 'Galleries', 'mrn-base-stack' ),
		'name_admin_bar'        => __( 'Gallery', 'mrn-base-stack' ),
		'add_new'               => __( 'Add New', 'mrn-base-stack' ),
		'add_new_item'          => __( 'Add New Gallery', 'mrn-base-stack' ),
		'new_item'              => __( 'New Gallery', 'mrn-base-stack' ),
		'edit_item'             => __( 'Edit Gallery', 'mrn-base-stack' ),
		'view_item'             => __( 'View Gallery', 'mrn-base-stack' ),
		'view_items'            => __( 'View Galleries', 'mrn-base-stack' ),
		'all_items'             => __( 'All Galleries', 'mrn-base-stack' ),
		'search_items'          => __( 'Search Galleries', 'mrn-base-stack' ),
		'parent_item_colon'     => __( 'Parent Galleries:', 'mrn-base-stack' ),
		'not_found'             => __( 'No galleries found.', 'mrn-base-stack' ),
		'not_found_in_trash'    => __( 'No galleries found in Trash.', 'mrn-base-stack' ),
		'archives'              => __( 'Gallery Archives', 'mrn-base-stack' ),
		'attributes'            => __( 'Gallery Attributes', 'mrn-base-stack' ),
		'insert_into_item'      => __( 'Insert into gallery', 'mrn-base-stack' ),
		'uploaded_to_this_item' => __( 'Uploaded to this gallery', 'mrn-base-stack' ),
		'featured_image'        => __( 'Featured image', 'mrn-base-stack' ),
		'set_featured_image'    => __( 'Set featured image', 'mrn-base-stack' ),
		'remove_featured_image' => __( 'Remove featured image', 'mrn-base-stack' ),
		'use_featured_image'    => __( 'Use as featured image', 'mrn-base-stack' ),
		'filter_items_list'     => __( 'Filter galleries list', 'mrn-base-stack' ),
		'items_list_navigation' => __( 'Galleries list navigation', 'mrn-base-stack' ),
		'items_list'            => __( 'Galleries list', 'mrn-base-stack' ),
		'item_published'        => __( 'Gallery published.', 'mrn-base-stack' ),
		'item_updated'          => __( 'Gallery updated.', 'mrn-base-stack' ),
	);

	register_post_type(
		'gallery',
		array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => $show_ui,
			'show_in_menu'        => $show_ui,
			'show_in_rest'        => true,
			'has_archive'         => true,
			'rewrite'             => array(
				'slug'       => 'gallery',
				'with_front' => false,
			),
			'menu_position'       => 7,
			'menu_icon'           => 'dashicons-format-gallery',
			'supports'            => array( 'title', 'excerpt', 'thumbnail', 'revisions' ),
			'publicly_queryable'  => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => $show_ui,
			'exclude_from_search' => false,
			'hierarchical'        => false,
			'query_var'           => true,
		)
	);
}
add_action( 'init', 'mrn_base_stack_register_gallery_post_type' );

/**
 * Register Gallery taxonomies.
 *
 * @return void
 */
function mrn_base_stack_register_gallery_taxonomies() {
	$show_ui = function_exists( 'mrn_base_stack_is_admin_cpt_visible' ) ? mrn_base_stack_is_admin_cpt_visible( 'gallery' ) : true;

	register_taxonomy(
		'gallery_category',
		array( 'gallery' ),
		array(
			'labels'            => array(
				'name'          => __( 'Gallery Categories', 'mrn-base-stack' ),
				'singular_name' => __( 'Gallery Category', 'mrn-base-stack' ),
				'search_items'  => __( 'Search Gallery Categories', 'mrn-base-stack' ),
				'all_items'     => __( 'All Gallery Categories', 'mrn-base-stack' ),
				'edit_item'     => __( 'Edit Gallery Category', 'mrn-base-stack' ),
				'update_item'   => __( 'Update Gallery Category', 'mrn-base-stack' ),
				'add_new_item'  => __( 'Add New Gallery Category', 'mrn-base-stack' ),
				'new_item_name' => __( 'New Gallery Category', 'mrn-base-stack' ),
				'menu_name'     => __( 'Gallery Categories', 'mrn-base-stack' ),
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_ui'           => $show_ui,
			'show_admin_column' => $show_ui,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'       => 'gallery-category',
				'with_front' => false,
			),
		)
	);

	register_taxonomy(
		'gallery_media_category',
		array( 'attachment' ),
		array(
			'labels'            => array(
				'name'          => __( 'Media Categories', 'mrn-base-stack' ),
				'singular_name' => __( 'Media Category', 'mrn-base-stack' ),
				'search_items'  => __( 'Search Media Categories', 'mrn-base-stack' ),
				'all_items'     => __( 'All Media Categories', 'mrn-base-stack' ),
				'edit_item'     => __( 'Edit Media Category', 'mrn-base-stack' ),
				'update_item'   => __( 'Update Media Category', 'mrn-base-stack' ),
				'add_new_item'  => __( 'Add New Media Category', 'mrn-base-stack' ),
				'new_item_name' => __( 'New Media Category', 'mrn-base-stack' ),
				'menu_name'     => __( 'Media Categories', 'mrn-base-stack' ),
			),
			'public'            => false,
			'publicly_queryable'=> false,
			'hierarchical'      => true,
			'show_ui'           => $show_ui,
			'show_in_menu'      => false,
			'show_admin_column' => $show_ui,
			'show_in_rest'      => true,
			'show_tagcloud'     => false,
			'meta_box_cb'       => false,
			'rewrite'           => false,
		)
	);
}
add_action( 'init', 'mrn_base_stack_register_gallery_taxonomies' );

/**
 * Register gallery-specific ACF fields.
 *
 * @return void
 */
function mrn_base_stack_register_gallery_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_gallery_body',
			'title'                 => 'Gallery',
			'menu_order'            => 10,
			'fields'                => array(
				array(
					'key'        => 'field_mrn_gallery_items_tab',
					'label'      => 'Items',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'          => 'field_mrn_gallery_items',
					'label'        => 'Gallery Items',
					'name'         => 'gallery_items',
					'aria-label'   => '',
					'type'         => 'repeater',
					'layout'       => 'row',
					'button_label' => 'Add Gallery Item',
					'collapsed'    => 'field_mrn_gallery_item_heading',
					'sub_fields'   => array(
						array(
							'key'           => 'field_mrn_gallery_item_heading',
							'label'         => 'Heading',
							'name'          => 'heading',
							'aria-label'    => '',
							'type'          => 'text',
							'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
							'wrapper'       => array(
								'width' => '100',
							),
						),
						array(
							'key'           => 'field_mrn_gallery_item_media_type',
							'label'         => 'Media Type',
							'name'          => 'media_type',
							'aria-label'    => '',
							'type'          => 'button_group',
							'instructions'  => 'Clear existing media before switching this item to a different media type.',
							'choices'       => array(
								'image'    => 'Image',
								'video'    => 'Video',
								'external' => 'External Embed',
							),
							'default_value' => 'image',
							'layout'        => 'horizontal',
							'return_format' => 'value',
							'wrapper'       => array(
								'width' => '33',
							),
						),
						array(
							'key'           => 'field_mrn_gallery_item_image',
							'label'         => 'Image',
							'name'          => 'image',
							'aria-label'    => '',
							'type'          => 'image',
							'return_format' => 'array',
							'preview_size'  => 'medium',
							'library'       => 'all',
							'instructions'  => 'Required for image items.',
							'conditional_logic' => array(
								array(
									array(
										'field'    => 'field_mrn_gallery_item_media_type',
										'operator' => '==',
										'value'    => 'image',
									),
								),
							),
							'wrapper'       => array(
								'width' => '40',
							),
						),
						array(
							'key'               => 'field_mrn_gallery_item_preview_image',
							'label'             => 'Poster Image',
							'name'              => 'preview_image',
							'aria-label'        => '',
							'type'              => 'image',
							'return_format'     => 'array',
							'preview_size'      => 'medium',
							'library'           => 'all',
							'instructions'      => 'Optional poster image shown in the grid for video or external embed items.',
							'conditional_logic' => array(
								array(
									array(
										'field'    => 'field_mrn_gallery_item_media_type',
										'operator' => '!=',
										'value'    => 'image',
									),
								),
							),
							'wrapper'           => array(
								'width' => '40',
							),
						),
						array(
							'key'               => 'field_mrn_gallery_item_media_url',
							'label'             => 'Media URL',
							'name'              => 'media_url',
							'aria-label'        => '',
							'type'              => 'url',
							'instructions'      => 'For video items use a YouTube/Vimeo/self-hosted video URL. For external embeds use the target iframe/page URL.',
							'conditional_logic' => array(
								array(
									array(
										'field'    => 'field_mrn_gallery_item_media_type',
										'operator' => '!=',
										'value'    => 'image',
									),
								),
							),
							'wrapper'           => array(
								'width' => '27',
							),
						),
						array(
							'key'               => 'field_mrn_gallery_item_autoplay_thumbnail',
							'label'             => 'Autoplay Thumbnail',
							'name'              => 'autoplay_thumbnail',
							'aria-label'        => '',
							'type'              => 'true_false',
							'ui'                => 1,
							'default_value'     => 0,
							'ui_on_text'        => 'On',
							'ui_off_text'       => 'Off',
							'instructions'      => 'For direct video files, autoplay the video silently in the gallery tile.',
							'conditional_logic' => array(
								array(
									array(
										'field'    => 'field_mrn_gallery_item_media_type',
										'operator' => '==',
										'value'    => 'video',
									),
									array(
										'field'    => 'field_mrn_gallery_item_preview_image',
										'operator' => '==empty',
									),
								),
							),
							'wrapper'           => array(
								'width' => '33',
							),
						),
						array(
							'key'           => 'field_mrn_gallery_item_caption',
							'label'         => 'Caption',
							'name'          => 'caption',
							'aria-label'    => '',
							'type'          => 'textarea',
							'rows'          => 3,
							'new_lines'     => '',
							'wrapper'       => array(
								'width' => '60',
							),
						),
						array(
							'key'           => 'field_mrn_gallery_item_media_categories',
							'label'         => 'Media Categories',
							'name'          => 'media_categories',
							'aria-label'    => '',
							'type'          => 'taxonomy',
							'taxonomy'      => 'gallery_media_category',
							'field_type'    => 'multi_select',
							'ui'            => 1,
							'ajax'          => 1,
							'add_term'      => 1,
							'save_terms'    => 0,
							'load_terms'    => 0,
							'return_format' => 'id',
							'instructions'  => 'Search or choose categories for the selected media item. These power the category tabs on the gallery page.',
							'wrapper'       => array(
								'width' => '50',
							),
						),
						array(
							'key'           => 'field_mrn_gallery_item_link',
							'label'         => 'Optional Link',
							'name'          => 'link',
							'aria-label'    => '',
							'type'          => 'link',
							'return_format' => 'array',
							'wrapper'       => array(
								'width' => '50',
							),
						),
					),
				),
				array(
					'key'        => 'field_mrn_gallery_settings_tab',
					'label'      => 'Settings',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'           => 'field_mrn_gallery_columns',
					'label'         => 'Columns',
					'name'          => 'gallery_columns',
					'aria-label'    => '',
					'type'          => 'button_group',
					'choices'       => array(
						'2' => '2',
						'3' => '3',
						'4' => '4',
					),
					'default_value' => '3',
					'layout'        => 'horizontal',
					'return_format' => 'value',
					'wrapper'       => array(
						'width' => '25',
					),
				),
				array(
					'key'           => 'field_mrn_gallery_image_ratio',
					'label'         => 'Image Aspect',
					'name'          => 'gallery_image_ratio',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'landscape' => 'Landscape',
						'square'    => 'Square',
						'portrait'  => 'Portrait',
						'natural'   => 'Natural',
					),
					'default_value' => 'landscape',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '25',
					),
				),
				array(
					'key'           => 'field_mrn_gallery_enable_filters',
					'label'         => 'Enable Category Tabs',
					'name'          => 'gallery_enable_filters',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 1,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '25',
					),
				),
				array(
					'key'           => 'field_mrn_gallery_hover_animation',
					'label'         => 'Thumbnail Hover Effects',
					'name'          => 'gallery_hover_animation',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'none'               => 'None',
						'zoom'               => 'Zoom',
						'soft-zoom'          => 'Soft Zoom',
						'shift-left'         => 'Shift Left',
						'shift-right'        => 'Shift Right',
						'lift'               => 'Lift',
						'tilt'               => 'Tilt',
						'reveal-overlay'     => 'Reveal Overlay',
						'desaturate-color'   => 'Desaturate to Color',
						'blur-back'          => 'Blur Back',
						'parallax-drift'     => 'Parallax Drift',
					),
					'default_value' => 'zoom',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '25',
					),
				),
				array(
					'key'           => 'field_mrn_gallery_enable_lightbox',
					'label'         => 'Lightbox',
					'name'          => 'gallery_enable_lightbox',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 1,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '25',
					),
				),
				array(
					'key'           => 'field_mrn_gallery_empty_message',
					'label'         => 'Empty Message',
					'name'          => 'gallery_empty_message',
					'aria-label'    => '',
					'type'          => 'text',
					'default_value' => __( 'Gallery images coming soon.', 'mrn-base-stack' ),
					'instructions'  => 'Shown on the front end when the gallery has no images yet.',
				),
				array(
					'key'        => 'field_mrn_gallery_lightbox_tab',
					'label'      => 'Lightbox Settings',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'           => 'field_mrn_gallery_lightbox_show_captions',
					'label'         => 'Show Captions',
					'name'          => 'gallery_lightbox_show_captions',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 1,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '20',
					),
				),
				array(
					'key'           => 'field_mrn_gallery_lightbox_loop_items',
					'label'         => 'Loop Items',
					'name'          => 'gallery_lightbox_loop_items',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 1,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '20',
					),
				),
				array(
					'key'           => 'field_mrn_gallery_lightbox_autoplay_video',
					'label'         => 'Autoplay Video',
					'name'          => 'gallery_lightbox_autoplay_video',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 1,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '20',
					),
				),
				array(
					'key'           => 'field_mrn_gallery_lightbox_animation',
					'label'         => 'Animation Type',
					'name'          => 'gallery_lightbox_animation',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'zoom'  => 'Zoom',
						'fade'  => 'Fade',
						'none'  => 'None',
						'slide' => 'Slide',
					),
					'default_value' => 'zoom',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '20',
					),
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'gallery',
					),
				),
			),
			'position'              => 'acf_after_title',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'description'           => 'Primary image-grid body for gallery entries.',
			'show_in_rest'          => 1,
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_gallery_field_group' );

/**
 * Normalize gallery category term objects into front-end data.
 *
 * @param WP_Term[] $terms Raw term objects.
 * @return array<int, array{slug:string,label:string}>
 */
function mrn_base_stack_normalize_gallery_category_terms( array $terms ) {
	$categories = array();

	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term ) {
			continue;
		}

		$slug = sanitize_title( $term->slug );
		$label = trim( wp_strip_all_tags( $term->name ) );
		if ( '' === $slug || '' === $label ) {
			continue;
		}

		$categories[ $slug ] = array(
			'slug'  => $slug,
			'label' => $label,
		);
	}

	return array_values( $categories );
}

/**
 * Normalize legacy gallery item filter labels.
 *
 * @param string $raw_filters Raw comma-separated filter label string.
 * @return array<int, array{slug:string,label:string}>
 */
function mrn_base_stack_get_legacy_gallery_item_filters( $raw_filters ) {
	$raw_filters = is_string( $raw_filters ) ? $raw_filters : '';
	if ( '' === trim( $raw_filters ) ) {
		return array();
	}

	$legacy_filters = array();
	foreach ( preg_split( '/,/', $raw_filters ) as $raw_filter ) {
		$label = trim( wp_strip_all_tags( (string) $raw_filter ) );
		$slug  = sanitize_title( $label );

		if ( '' === $label || '' === $slug ) {
			continue;
		}

		$legacy_filters[ $slug ] = array(
			'slug'  => $slug,
			'label' => $label,
		);
	}

	return array_values( $legacy_filters );
}

/**
 * Get normalized gallery categories for a gallery item.
 *
 * @param int   $attachment_id Attachment ID.
 * @param int[] $selected_term_ids Selected term IDs from the gallery editor.
 * @param string $legacy_filters Legacy comma-separated filters string.
 * @return array<int, array{slug:string,label:string}>
 */
function mrn_base_stack_get_gallery_item_categories( $attachment_id, array $selected_term_ids = array(), $legacy_filters = '' ) {
	$attachment_id = (int) $attachment_id;
	$selected_term_ids = wp_parse_id_list( $selected_term_ids );

	if ( $attachment_id > 0 ) {
		$terms = get_the_terms( $attachment_id, 'gallery_media_category' );
		if ( is_array( $terms ) && ! empty( $terms ) ) {
			return mrn_base_stack_normalize_gallery_category_terms( $terms );
		}
	}

	if ( ! empty( $selected_term_ids ) ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'gallery_media_category',
				'include'    => $selected_term_ids,
				'hide_empty' => false,
				'orderby'    => 'include',
			)
		);
		if ( is_array( $terms ) && ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			return mrn_base_stack_normalize_gallery_category_terms( $terms );
		}
	}

	return mrn_base_stack_get_legacy_gallery_item_filters( $legacy_filters );
}

/**
 * Get or create gallery media category term IDs from legacy labels.
 *
 * @param string $legacy_filters Legacy comma-separated labels.
 * @return int[]
 */
function mrn_base_stack_get_gallery_media_category_ids_from_legacy_filters( $legacy_filters ) {
	$terms    = mrn_base_stack_get_legacy_gallery_item_filters( $legacy_filters );
	$term_ids = array();

	foreach ( $terms as $term ) {
		if ( empty( $term['label'] ) ) {
			continue;
		}

		$existing = term_exists( $term['label'], 'gallery_media_category' );
		if ( is_array( $existing ) && ! empty( $existing['term_id'] ) ) {
			$term_ids[] = (int) $existing['term_id'];
			continue;
		}

		if ( is_int( $existing ) ) {
			$term_ids[] = $existing;
			continue;
		}

		$created = wp_insert_term( $term['label'], 'gallery_media_category' );
		if ( is_wp_error( $created ) || empty( $created['term_id'] ) ) {
			continue;
		}

		$term_ids[] = (int) $created['term_id'];
	}

	return array_values( array_unique( array_filter( $term_ids ) ) );
}

/**
 * Normalize the gallery item media type.
 *
 * @param string $media_type Raw media type.
 * @return string
 */
function mrn_base_stack_normalize_gallery_media_type( $media_type ) {
	$media_type = sanitize_key( (string) $media_type );

	if ( ! in_array( $media_type, array( 'image', 'video', 'external' ), true ) ) {
		return 'image';
	}

	return $media_type;
}

/**
 * Determine whether a URL points to a directly playable video file.
 *
 * @param string $url Media URL.
 * @return bool
 */
function mrn_base_stack_is_direct_video_url( $url ) {
	$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
	if ( '' === $path ) {
		return false;
	}

	$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

	return in_array( $extension, array( 'mp4', 'm4v', 'webm', 'ogv', 'ogg', 'mov' ), true );
}

/**
 * Determine whether a URL is a hosted video provider URL.
 *
 * @param string $url Media URL.
 * @return bool
 */
function mrn_base_stack_is_hosted_video_url( $url ) {
	$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
	if ( '' === $host ) {
		return false;
	}

	$video_hosts = array(
		'youtube.com',
		'www.youtube.com',
		'youtu.be',
		'www.youtu.be',
		'vimeo.com',
		'www.vimeo.com',
		'player.vimeo.com',
	);

	return in_array( $host, $video_hosts, true );
}

/**
 * Resolve the effective media mode for a gallery item.
 *
 * @param string $media_type Selected gallery media type.
 * @param string $url Media URL.
 * @return string
 */
function mrn_base_stack_get_gallery_effective_media_type( $media_type, $url ) {
	$media_type = mrn_base_stack_normalize_gallery_media_type( $media_type );
	$url        = (string) $url;

	if ( 'image' === $media_type ) {
		return 'image';
	}

	if ( mrn_base_stack_is_direct_video_url( $url ) || mrn_base_stack_is_hosted_video_url( $url ) ) {
		return 'video';
	}

	return 'external';
}

/**
 * Resolve the GLightbox media type value for a gallery item.
 *
 * @param string $media_type Effective gallery media type.
 * @return string
 */
function mrn_base_stack_get_gallery_lightbox_media_type( $media_type ) {
	if ( 'external' === $media_type ) {
		return 'iframe';
	}

	return $media_type;
}

/**
 * Build a lightweight hosted-video embed URL for in-grid preview playback.
 *
 * @param string $url Hosted video URL.
 * @param bool   $autoplay Whether preview playback should autoplay.
 * @return string
 */
function mrn_base_stack_get_gallery_preview_embed_url( $url, $autoplay = false ) {
	$url  = (string) $url;
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$autoplay = (bool) $autoplay;

	if ( in_array( $host, array( 'youtube.com', 'www.youtube.com' ), true ) ) {
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( (string) $query, $params );
		$video_id = isset( $params['v'] ) ? preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $params['v'] ) : '';

		if ( '' === $video_id ) {
			return '';
		}

		return add_query_arg(
			array(
				'autoplay'   => $autoplay ? '1' : '0',
				'mute'       => '1',
				'controls'   => '0',
				'playsinline'=> '1',
				'rel'        => '0',
			),
			'https://www.youtube.com/embed/' . rawurlencode( $video_id )
		);
	}

	if ( in_array( $host, array( 'youtu.be', 'www.youtu.be' ), true ) ) {
		$video_id = trim( $path, '/' );
		$video_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', $video_id );

		if ( '' === $video_id ) {
			return '';
		}

		return add_query_arg(
			array(
				'autoplay'   => $autoplay ? '1' : '0',
				'mute'       => '1',
				'controls'   => '0',
				'playsinline'=> '1',
				'rel'        => '0',
			),
			'https://www.youtube.com/embed/' . rawurlencode( $video_id )
		);
	}

	if ( in_array( $host, array( 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com' ), true ) ) {
		$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
		$video_id = '';

		foreach ( array_reverse( $segments ) as $segment ) {
			if ( ctype_digit( $segment ) ) {
				$video_id = $segment;
				break;
			}
		}

		if ( '' === $video_id ) {
			return '';
		}

		return add_query_arg(
			array(
				'autoplay'   => $autoplay ? '1' : '0',
				'muted'      => '1',
				'background' => $autoplay ? '1' : '0',
				'title'      => '0',
				'byline'     => '0',
				'portrait'   => '0',
			),
			'https://player.vimeo.com/video/' . rawurlencode( $video_id )
		);
	}

	return '';
}

/**
 * Get normalized gallery lightbox settings.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_gallery_lightbox_settings( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	$settings = array(
		'show_captions'  => true,
		'loop_items'     => true,
		'autoplay_video' => true,
		'zoom_images'    => true,
		'animation'      => 'zoom',
	);

	if ( ! $post_id || ! function_exists( 'get_field' ) ) {
		return $settings;
	}

	$settings['show_captions']  = (bool) get_field( 'gallery_lightbox_show_captions', $post_id );
	$settings['loop_items']     = (bool) get_field( 'gallery_lightbox_loop_items', $post_id );
	$settings['autoplay_video'] = (bool) get_field( 'gallery_lightbox_autoplay_video', $post_id );

	$animation = sanitize_key( (string) get_field( 'gallery_lightbox_animation', $post_id ) );
	if ( ! in_array( $animation, array( 'zoom', 'fade', 'none', 'slide' ), true ) ) {
		$animation = 'zoom';
	}

	$settings['animation'] = $animation;

	return $settings;
}

/**
 * Get the gallery hover animation setting.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return string
 */
function mrn_base_stack_get_gallery_hover_animation( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$value   = $post_id && function_exists( 'get_field' ) ? sanitize_key( (string) get_field( 'gallery_hover_animation', $post_id ) ) : 'zoom';

	if ( ! in_array( $value, array( 'none', 'zoom', 'soft-zoom', 'shift-left', 'shift-right', 'lift', 'tilt', 'reveal-overlay', 'desaturate-color', 'blur-back', 'parallax-drift' ), true ) ) {
		$value = 'zoom';
	}

	/**
	 * Filter the gallery hover animation value before it becomes a theme class.
	 *
	 * @param string   $value Hover animation slug.
	 * @param int|null $post_id Post ID being rendered.
	 */
	return (string) apply_filters( 'mrn_base_stack_gallery_hover_animation', $value, $post_id );
}

/**
 * Get normalized gallery items.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_gallery_items( $post_id = null ) {
	if ( ! function_exists( 'get_field' ) ) {
		return array();
	}

	$post_id = $post_id ? (int) $post_id : get_the_ID();
	if ( ! $post_id ) {
		return array();
	}

	$rows  = get_field( 'gallery_items', $post_id );
	$items = array();

	if ( ! is_array( $rows ) ) {
		return $items;
	}

	foreach ( $rows as $index => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$media_type = mrn_base_stack_normalize_gallery_media_type( $row['media_type'] ?? 'image' );
		$image      = isset( $row['image'] ) && is_array( $row['image'] ) ? $row['image'] : array();
		$preview_image = isset( $row['preview_image'] ) && is_array( $row['preview_image'] ) ? $row['preview_image'] : array();
		$media_url  = isset( $row['media_url'] ) ? esc_url_raw( (string) $row['media_url'] ) : '';
		$effective_media_type = mrn_base_stack_get_gallery_effective_media_type( $media_type, $media_url );

		if ( 'image' === $media_type && empty( $image['ID'] ) && empty( $image['url'] ) ) {
			continue;
		}

		if ( 'image' !== $media_type && '' === $media_url ) {
			continue;
		}

		$display_image = 'image' === $media_type ? $image : $preview_image;
		$attachment_id = ! empty( $display_image['ID'] ) ? (int) $display_image['ID'] : 0;
		$thumb_url     = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'large' ) : ( $display_image['url'] ?? '' );
		$full_url      = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'full' ) : ( $display_image['url'] ?? '' );
		$alt           = $attachment_id ? get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) : ( $display_image['alt'] ?? '' );
		$caption       = isset( $row['caption'] ) ? trim( wp_strip_all_tags( (string) $row['caption'] ) ) : '';
		$link          = isset( $row['link'] ) && is_array( $row['link'] ) ? $row['link'] : array();
		$autoplay_thumbnail = ! empty( $row['autoplay_thumbnail'] );
		$selected_term_ids = isset( $row['media_categories'] ) ? wp_parse_id_list( $row['media_categories'] ) : array();
		$legacy_filters    = get_post_meta( $post_id, 'gallery_items_' . $index . '_filters', true );
		$filters           = mrn_base_stack_get_gallery_item_categories( $attachment_id, $selected_term_ids, $legacy_filters );

		if ( 'image' !== $media_type ) {
			$full_url = $media_url;
		}

		$items[] = array(
			'attachment_id' => $attachment_id,
			'media_type'    => $media_type,
			'effective_media_type' => $effective_media_type,
			'thumb_url'     => esc_url_raw( (string) $thumb_url ),
			'full_url'      => esc_url_raw( (string) $full_url ),
			'alt'           => is_string( $alt ) ? $alt : '',
			'caption'       => $caption,
			'autoplay_thumbnail' => $autoplay_thumbnail,
			'link'          => $link,
			'filters'       => $filters,
		);
	}

	return $items;
}

/**
 * Normalize gallery repeater row values before ACF saves them.
 *
 * @param mixed $value Submitted repeater value.
 * @param int|string $post_id Target post ID.
 * @param array<string, mixed> $field Field definition.
 * @return mixed
 */
function mrn_base_stack_normalize_gallery_items_value( $value, $post_id, $field ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}

	foreach ( $value as $index => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$media_type = mrn_base_stack_normalize_gallery_media_type( $row['field_mrn_gallery_item_media_type'] ?? 'image' );

		if ( 'image' === $media_type ) {
			$row['field_mrn_gallery_item_preview_image']      = '';
			$row['field_mrn_gallery_item_media_url']          = '';
			$row['field_mrn_gallery_item_autoplay_thumbnail'] = 0;
		} else {
			$row['field_mrn_gallery_item_image'] = '';
		}

		$value[ $index ] = $row;
	}

	return $value;
}
add_filter( 'acf/update_value/key=field_mrn_gallery_items', 'mrn_base_stack_normalize_gallery_items_value', 10, 3 );

/**
 * Normalize gallery repeater row values when loading into ACF.
 *
 * @param mixed $value Stored repeater value.
 * @param int|string $post_id Target post ID.
 * @param array<string, mixed> $field Field definition.
 * @return mixed
 */
function mrn_base_stack_normalize_loaded_gallery_items_value( $value, $post_id, $field ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}

	foreach ( $value as $index => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$media_type = mrn_base_stack_normalize_gallery_media_type(
			$row['field_mrn_gallery_item_media_type'] ?? $row['media_type'] ?? 'image'
		);

		if ( 'image' === $media_type ) {
			$row['field_mrn_gallery_item_preview_image']      = '';
			$row['field_mrn_gallery_item_media_url']          = '';
			$row['field_mrn_gallery_item_autoplay_thumbnail'] = 0;
		} else {
			$row['field_mrn_gallery_item_image'] = '';
		}

		$value[ $index ] = $row;
	}

	return $value;
}
add_filter( 'acf/load_value/key=field_mrn_gallery_items', 'mrn_base_stack_normalize_loaded_gallery_items_value', 10, 3 );

/**
 * Sync gallery row category selections onto the selected attachment items.
 *
 * @param mixed $post_id ACF save target.
 * @return void
 */
function mrn_base_stack_sync_gallery_attachment_categories( $post_id ) {
	$post_id = is_numeric( $post_id ) ? (int) $post_id : 0;
	if ( $post_id <= 0 || 'gallery' !== get_post_type( $post_id ) || ! function_exists( 'get_field' ) ) {
		return;
	}

	$rows = get_field( 'gallery_items', $post_id );
	if ( ! is_array( $rows ) ) {
		return;
	}

	$attachment_term_map = array();

	foreach ( $rows as $index => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$media_type = mrn_base_stack_normalize_gallery_media_type( $row['media_type'] ?? 'image' );
		$image = isset( $row['image'] ) && is_array( $row['image'] ) ? $row['image'] : array();
		$preview_image = isset( $row['preview_image'] ) && is_array( $row['preview_image'] ) ? $row['preview_image'] : array();
		$attachment_source = 'image' === $media_type ? $image : $preview_image;
		$attachment_id = ! empty( $attachment_source['ID'] ) ? (int) $attachment_source['ID'] : 0;

		if ( 'image' === $media_type ) {
			delete_post_meta( $post_id, 'gallery_items_' . $index . '_preview_image' );
			delete_post_meta( $post_id, 'gallery_items_' . $index . '_media_url' );
			delete_post_meta( $post_id, 'gallery_items_' . $index . '_autoplay_thumbnail' );
		} else {
			delete_post_meta( $post_id, 'gallery_items_' . $index . '_image' );
		}

		if ( $attachment_id <= 0 ) {
			continue;
		}

		$term_ids = isset( $row['media_categories'] ) ? wp_parse_id_list( $row['media_categories'] ) : array();
		if ( empty( $term_ids ) ) {
			$legacy_filters = get_post_meta( $post_id, 'gallery_items_' . $index . '_filters', true );
			$term_ids       = mrn_base_stack_get_gallery_media_category_ids_from_legacy_filters( $legacy_filters );
		}

		update_post_meta( $post_id, 'gallery_items_' . $index . '_media_categories', $term_ids );
		update_post_meta( $post_id, '_gallery_items_' . $index . '_media_categories', 'field_mrn_gallery_item_media_categories' );

		if ( ! isset( $attachment_term_map[ $attachment_id ] ) ) {
			$attachment_term_map[ $attachment_id ] = array();
		}

		$attachment_term_map[ $attachment_id ] = array_values(
			array_unique(
				array_merge(
					$attachment_term_map[ $attachment_id ],
					$term_ids
				)
			)
		);
	}

	foreach ( $attachment_term_map as $attachment_id => $term_ids ) {
		wp_set_object_terms( (int) $attachment_id, wp_parse_id_list( $term_ids ), 'gallery_media_category', false );
	}
}
add_action( 'acf/save_post', 'mrn_base_stack_sync_gallery_attachment_categories', 20 );

/**
 * Get unique gallery filters for a post.
 *
 * @param array<int, array<string, mixed>> $items Gallery items.
 * @return array<int, array{slug:string,label:string}>
 */
function mrn_base_stack_get_gallery_filters( array $items ) {
	$filters = array();

	foreach ( $items as $item ) {
		if ( empty( $item['filters'] ) || ! is_array( $item['filters'] ) ) {
			continue;
		}

		foreach ( $item['filters'] as $filter ) {
			if ( empty( $filter['slug'] ) || empty( $filter['label'] ) ) {
				continue;
			}

			$filters[ $filter['slug'] ] = array(
				'slug'  => (string) $filter['slug'],
				'label' => (string) $filter['label'],
			);
		}
	}

	return array_values( $filters );
}

/**
 * Get rendered gallery markup.
 *
 * @param int|null $post_id Post ID to render.
 * @return string
 */
function mrn_base_stack_get_gallery_markup( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	if ( ! $post_id ) {
		return '';
	}

	$items           = mrn_base_stack_get_gallery_items( $post_id );
	$columns         = function_exists( 'get_field' ) ? (string) get_field( 'gallery_columns', $post_id ) : '3';
	$image_ratio     = function_exists( 'get_field' ) ? sanitize_key( (string) get_field( 'gallery_image_ratio', $post_id ) ) : 'landscape';
	$enable_filters  = function_exists( 'get_field' ) ? (bool) get_field( 'gallery_enable_filters', $post_id ) : true;
	$enable_lightbox = function_exists( 'get_field' ) ? (bool) get_field( 'gallery_enable_lightbox', $post_id ) : true;
	$empty_message   = function_exists( 'get_field' ) ? trim( (string) get_field( 'gallery_empty_message', $post_id ) ) : '';
	$filters         = mrn_base_stack_get_gallery_filters( $items );
	$lightbox        = mrn_base_stack_get_gallery_lightbox_settings( $post_id );
	$hover_animation = mrn_base_stack_get_gallery_hover_animation( $post_id );

	if ( ! in_array( $columns, array( '2', '3', '4' ), true ) ) {
		$columns = '3';
	}

	if ( ! in_array( $image_ratio, array( 'landscape', 'square', 'portrait', 'natural' ), true ) ) {
		$image_ratio = 'landscape';
	}

	ob_start();
	?>
	<section
		class="mrn-gallery mrn-gallery--columns-<?php echo esc_attr( $columns ); ?> mrn-gallery--ratio-<?php echo esc_attr( $image_ratio ); ?> mrn-gallery--hover-<?php echo esc_attr( sanitize_html_class( $hover_animation ) ); ?>"
		data-gallery-hover="<?php echo esc_attr( $hover_animation ); ?>"
		data-gallery-root
		data-gallery-group="gallery-<?php echo esc_attr( (string) $post_id ); ?>"
		data-gallery-lightbox-show-captions="<?php echo esc_attr( $lightbox['show_captions'] ? 'true' : 'false' ); ?>"
		data-gallery-lightbox-loop="<?php echo esc_attr( $lightbox['loop_items'] ? 'true' : 'false' ); ?>"
		data-gallery-lightbox-autoplay-video="<?php echo esc_attr( $lightbox['autoplay_video'] ? 'true' : 'false' ); ?>"
		data-gallery-lightbox-animation="<?php echo esc_attr( (string) $lightbox['animation'] ); ?>"
	>
		<?php if ( $enable_filters && ! empty( $filters ) ) : ?>
			<div class="mrn-gallery__filters" role="toolbar" aria-label="<?php esc_attr_e( 'Gallery categories', 'mrn-base-stack' ); ?>">
				<button type="button" class="mrn-gallery__filter is-active" data-gallery-filter="all"><?php esc_html_e( 'All', 'mrn-base-stack' ); ?></button>
				<?php foreach ( $filters as $filter ) : ?>
					<button type="button" class="mrn-gallery__filter" data-gallery-filter="<?php echo esc_attr( $filter['slug'] ); ?>">
						<?php echo esc_html( $filter['label'] ); ?>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $items ) ) : ?>
			<div class="mrn-gallery__grid">
				<?php foreach ( $items as $index => $item ) : ?>
					<?php
					$item_filters = ! empty( $item['filters'] ) && is_array( $item['filters'] ) ? wp_list_pluck( $item['filters'], 'slug' ) : array();
					$link         = isset( $item['link'] ) && is_array( $item['link'] ) ? $item['link'] : array();
					$link_url     = isset( $link['url'] ) ? (string) $link['url'] : '';
					$link_target  = isset( $link['target'] ) ? (string) $link['target'] : '';
					$caption      = isset( $item['caption'] ) ? (string) $item['caption'] : '';
					$media_type   = isset( $item['effective_media_type'] ) ? (string) $item['effective_media_type'] : ( isset( $item['media_type'] ) ? mrn_base_stack_normalize_gallery_media_type( $item['media_type'] ) : 'image' );
					$thumb_url    = isset( $item['thumb_url'] ) ? (string) $item['thumb_url'] : '';
					$alt          = isset( $item['alt'] ) ? (string) $item['alt'] : '';
					$glightbox_data = array();
					$is_direct_video = 'video' === $media_type && mrn_base_stack_is_direct_video_url( $item['full_url'] );
					$autoplay_thumbnail = ! empty( $item['autoplay_thumbnail'] );
					$preview_embed_url = '';

					if ( 'video' === $media_type && ! $is_direct_video ) {
						$preview_embed_url = mrn_base_stack_get_gallery_preview_embed_url( $item['full_url'], $autoplay_thumbnail );
					}

					if ( 'image' !== $media_type ) {
						$glightbox_data[] = 'type: ' . mrn_base_stack_get_gallery_lightbox_media_type( $media_type );
						if ( 'external' === $media_type ) {
							$glightbox_data[] = 'width: 90vw';
							$glightbox_data[] = 'height: 90vh';
						}
					}

					if ( $lightbox['show_captions'] && '' !== $caption ) {
						$glightbox_data[] = 'description: ' . wp_strip_all_tags( $caption );
					}
					?>
					<figure class="mrn-gallery__item" data-gallery-item data-gallery-filters="<?php echo esc_attr( implode( ' ', $item_filters ) ); ?>">
						<?php if ( $enable_lightbox ) : ?>
							<a
								class="mrn-gallery__trigger glightbox"
								href="<?php echo esc_url( $item['full_url'] ); ?>"
								data-gallery="gallery-<?php echo esc_attr( (string) $post_id ); ?>"
								<?php if ( 'image' !== $media_type ) : ?>data-type="<?php echo esc_attr( mrn_base_stack_get_gallery_lightbox_media_type( $media_type ) ); ?>"<?php endif; ?>
								<?php if ( $lightbox['show_captions'] && '' !== $caption ) : ?>data-description="<?php echo esc_attr( wp_strip_all_tags( $caption ) ); ?>"<?php endif; ?>
								data-glightbox="<?php echo esc_attr( implode( '; ', $glightbox_data ) ); ?>"
							>
								<?php if ( '' !== $thumb_url ) : ?>
									<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $alt ); ?>">
								<?php elseif ( $is_direct_video ) : ?>
									<video
										class="mrn-gallery__video-preview"
										preload="metadata"
										muted
										playsinline
										loop
										data-gallery-thumbnail-video
										data-gallery-thumbnail-autoplay="<?php echo esc_attr( $autoplay_thumbnail ? 'true' : 'false' ); ?>"
										<?php if ( $autoplay_thumbnail ) : ?>autoplay<?php endif; ?>
									>
										<source src="<?php echo esc_url( $item['full_url'] ); ?>">
									</video>
								<?php elseif ( '' !== $preview_embed_url ) : ?>
									<iframe
										class="mrn-gallery__embed-preview"
										src="<?php echo esc_url( $preview_embed_url ); ?>"
										title="<?php echo esc_attr( $caption ? $caption : __( 'Video preview', 'mrn-base-stack' ) ); ?>"
										loading="lazy"
										allow="autoplay; encrypted-media; picture-in-picture"
										referrerpolicy="strict-origin-when-cross-origin"
										allowfullscreen
									></iframe>
								<?php else : ?>
									<span class="mrn-gallery__placeholder mrn-gallery__placeholder--<?php echo esc_attr( $media_type ); ?>">
										<span class="mrn-gallery__placeholder-label">
											<?php echo esc_html( 'video' === $media_type ? __( 'Video', 'mrn-base-stack' ) : __( 'Embed', 'mrn-base-stack' ) ); ?>
										</span>
									</span>
								<?php endif; ?>
								<?php if ( 'image' !== $media_type ) : ?>
									<span class="mrn-gallery__media-badge" aria-hidden="true">
										<span class="mrn-gallery__media-badge-icon"></span>
									</span>
								<?php endif; ?>
							</a>
						<?php elseif ( '' !== $link_url ) : ?>
							<a class="mrn-gallery__trigger" href="<?php echo esc_url( $link_url ); ?>"<?php if ( '' !== $link_target ) : ?> target="<?php echo esc_attr( $link_target ); ?>"<?php endif; ?><?php if ( '_blank' === $link_target ) : ?> rel="noopener noreferrer"<?php endif; ?>>
								<?php if ( '' !== $thumb_url ) : ?>
									<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $alt ); ?>">
								<?php elseif ( $is_direct_video ) : ?>
									<video
										class="mrn-gallery__video-preview"
										preload="metadata"
										muted
										playsinline
										loop
										data-gallery-thumbnail-video
										data-gallery-thumbnail-autoplay="<?php echo esc_attr( $autoplay_thumbnail ? 'true' : 'false' ); ?>"
										<?php if ( $autoplay_thumbnail ) : ?>autoplay<?php endif; ?>
									>
										<source src="<?php echo esc_url( $item['full_url'] ); ?>">
									</video>
								<?php elseif ( '' !== $preview_embed_url ) : ?>
									<iframe
										class="mrn-gallery__embed-preview"
										src="<?php echo esc_url( $preview_embed_url ); ?>"
										title="<?php echo esc_attr( $caption ? $caption : __( 'Video preview', 'mrn-base-stack' ) ); ?>"
										loading="lazy"
										allow="autoplay; encrypted-media; picture-in-picture"
										referrerpolicy="strict-origin-when-cross-origin"
										allowfullscreen
									></iframe>
								<?php else : ?>
									<span class="mrn-gallery__placeholder mrn-gallery__placeholder--<?php echo esc_attr( $media_type ); ?>">
										<span class="mrn-gallery__placeholder-label">
											<?php echo esc_html( 'video' === $media_type ? __( 'Video', 'mrn-base-stack' ) : __( 'Embed', 'mrn-base-stack' ) ); ?>
										</span>
									</span>
								<?php endif; ?>
								<?php if ( 'image' !== $media_type ) : ?>
									<span class="mrn-gallery__media-badge" aria-hidden="true">
										<span class="mrn-gallery__media-badge-icon"></span>
									</span>
								<?php endif; ?>
							</a>
						<?php else : ?>
							<div class="mrn-gallery__trigger">
								<?php if ( '' !== $thumb_url ) : ?>
									<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $alt ); ?>">
								<?php elseif ( $is_direct_video ) : ?>
									<video
										class="mrn-gallery__video-preview"
										preload="metadata"
										muted
										playsinline
										loop
										data-gallery-thumbnail-video
										data-gallery-thumbnail-autoplay="<?php echo esc_attr( $autoplay_thumbnail ? 'true' : 'false' ); ?>"
										<?php if ( $autoplay_thumbnail ) : ?>autoplay<?php endif; ?>
									>
										<source src="<?php echo esc_url( $item['full_url'] ); ?>">
									</video>
								<?php elseif ( '' !== $preview_embed_url ) : ?>
									<iframe
										class="mrn-gallery__embed-preview"
										src="<?php echo esc_url( $preview_embed_url ); ?>"
										title="<?php echo esc_attr( $caption ? $caption : __( 'Video preview', 'mrn-base-stack' ) ); ?>"
										loading="lazy"
										allow="autoplay; encrypted-media; picture-in-picture"
										referrerpolicy="strict-origin-when-cross-origin"
										allowfullscreen
									></iframe>
								<?php else : ?>
									<span class="mrn-gallery__placeholder mrn-gallery__placeholder--<?php echo esc_attr( $media_type ); ?>">
										<span class="mrn-gallery__placeholder-label">
											<?php echo esc_html( 'video' === $media_type ? __( 'Video', 'mrn-base-stack' ) : __( 'Embed', 'mrn-base-stack' ) ); ?>
										</span>
									</span>
								<?php endif; ?>
								<?php if ( 'image' !== $media_type ) : ?>
									<span class="mrn-gallery__media-badge" aria-hidden="true">
										<span class="mrn-gallery__media-badge-icon"></span>
									</span>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ( '' !== $caption ) : ?>
							<figcaption class="mrn-gallery__caption"><?php echo esc_html( $caption ); ?></figcaption>
						<?php endif; ?>
					</figure>
				<?php endforeach; ?>
			</div>

		<?php elseif ( '' !== $empty_message ) : ?>
			<p class="mrn-gallery__empty"><?php echo esc_html( $empty_message ); ?></p>
		<?php endif; ?>
	</section>
	<?php

	return trim( (string) ob_get_clean() );
}

/**
 * Echo rendered gallery markup.
 *
 * @param int|null $post_id Post ID to render.
 * @return bool
 */
function mrn_base_stack_render_gallery( $post_id = null ) {
	$markup = mrn_base_stack_get_gallery_markup( $post_id );

	if ( '' === $markup ) {
		return false;
	}

	echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return true;
}

/**
 * Get gallery markup adapted for SmartCrawl content analysis.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return string
 */
function mrn_base_stack_get_gallery_smartcrawl_markup( $post_id = null ) {
	$items = mrn_base_stack_get_gallery_items( $post_id );

	if ( empty( $items ) ) {
		return '';
	}

	$parts = array();
	foreach ( $items as $item ) {
		if ( ! empty( $item['caption'] ) ) {
			$parts[] = sprintf( '<p>%s</p>', esc_html( (string) $item['caption'] ) );
		}
	}

	return implode( "\n", $parts );
}

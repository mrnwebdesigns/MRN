<?php
/** Optional dynamic project cards, retaining the current source post model. */

function mrn_base_stack_is_project_list( array $row ) {
	return 'projects' === ( $row['record_presentation'] ?? '' );
}

function mrn_base_stack_get_project_frame_dimensions() {
	return explode( ' ', 'content_width inner_gutter inner_gutter_compact inner_gutter_mobile column_gap row_gap heading_size heading_line_height heading_space_after media_height media_height_mobile media_radius media_space_after media_space_after_mobile subtitle_size subtitle_line_height subtitle_space_after title_size title_line_height tags_space_before tags_gap tag_padding_inline tag_padding_block tag_radius tag_size tag_line_height actions_space_before action_size action_line_height action_padding_inline action_padding_block action_radius action_icon_width action_icon_height' );
}

function mrn_base_stack_get_project_list_fields( $seed ) {
	$make = static function ( $name, $type, array $extra = array() ) use ( $seed ) { return array_merge( array( 'key' => $seed . '_project_' . $name, 'name' => 'project_' . $name, 'label' => 'Project ' . ucwords( str_replace( '_', ' ', $name ) ), 'type' => $type, 'default_value' => '' ), $extra ); };
	$fields = array(
		$make( 'action_frame', 'true_false', array( 'label' => 'Project Action Button Frame', 'ui' => 1, 'default_value' => 0, 'instructions' => 'Use the selected native button typography, dimensions and Site Colors. Blank preserves existing link styles.' ) ),
		$make( 'selection', 'select', array( 'choices' => array( '' => 'All Selected Source Records', 'flagged' => 'Marked Records Only' ), 'instructions' => 'Source, item count, manual selection and ordering remain in the existing collection controls.' ) ),
		$make( 'flag_field', 'text', array( 'instructions' => 'Existing true/false field required to equal 1 when Marked Records Only is selected. An empty or invalid field returns no records.' ) ),
		$make( 'image_field', 'text', array( 'instructions' => 'Optional existing image field. A missing or invalid selection falls back to the featured image.' ) ),
		$make( 'subtitle_field', 'text', array( 'instructions' => 'Optional existing text field. Blank content uses the first term from the fallback taxonomy.' ) ),
		$make( 'subtitle_taxonomy', 'text', array( 'instructions' => 'Optional taxonomy registered for the source post type. Preserves its returned term order.' ) ),
		$make( 'feature_taxonomy', 'text', array( 'instructions' => 'Optional taxonomy registered for the source post type. Terms remain ordered and are not assigned to the builder page.' ) ),
		$make( 'video_field', 'text', array( 'default_value' => 'hover_video', 'instructions' => 'Optional existing video attachment or HTTP(S) URL. Preview starts only on hover or keyboard focus and respects reduced motion.' ) ),
		$make( 'image_size', 'select', array( 'choices' => array( 'medium_large' => 'Medium Large', 'large' => 'Large', 'full' => 'Full' ), 'default_value' => 'large' ) ),
		$make( 'heading_tag', 'select', array( 'choices' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6' ), 'default_value' => 'h3' ) ),
		$make( 'grid_breakpoint', 'select', array( 'choices' => array( '576' => 'One Column at 576px', '767' => 'One Column at 767px', '991' => 'One Column at 991px' ), 'default_value' => '767' ) ),
		$make( 'columns', 'select', array( 'choices' => array( '1' => 'One', '2' => 'Two', '3' => 'Three', '4' => 'Four' ), 'default_value' => '3' ) ),
	);
	foreach ( mrn_base_stack_get_project_frame_dimensions() as $name ) { $fields[] = $make( $name, 'text', array( 'instructions' => 'Optional bounded dimension or Site Text size/line token. Outer section spacing uses native row presets.', 'wrapper' => array( 'width' => '50' ) ) ); }
	foreach ( array( 'heading', 'title', 'subtitle', 'tag', 'action' ) as $part ) {
		$fields[] = $make( $part . '_weight', 'select', array( 'choices' => array( '' => 'Use Existing Weight' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ) ) );
	}
	foreach ( array( 'heading', 'title', 'title_hover', 'subtitle', 'tag', 'tag_surface', 'action_text', 'action_background', 'action_hover_background' ) as $part ) { $field = mrn_base_stack_get_background_color_field( $seed . '_project_' . $part . '_color' ); $field['name'] = 'project_' . $part . '_color'; $field['label'] = 'Project ' . ucwords( str_replace( '_', ' ', $part ) ) . ' Color'; $fields[] = $field; }
	$fields = array_merge( $fields, mrn_rbl_get_content_link_fields( $seed . '_project_links', 'Project Actions', 'project_links', 1 ) );
	foreach ( $fields as &$field ) { $field['conditional_logic'] = array( array( array( 'field' => $seed . '_record_presentation', 'operator' => '==', 'value' => 'projects' ) ) ); } unset( $field );
	return $fields;
}

/** Closed flag semantics preserve other constraints; malformed marked queries fail closed. */
function mrn_base_stack_get_project_collection_query( array $row, array $query ) {
	if ( ! mrn_base_stack_is_project_list( $row ) || 'flagged' !== ( $row['project_selection'] ?? '' ) ) { return $query; }
	$field = $row['project_flag_field'] ?? '';
	if ( ! is_string( $field ) || ! preg_match( '/^[a-zA-Z0-9_]+$/D', $field ) ) { $query['post__in'] = array( 0 ); return $query; }
	$flag = array( 'key' => $field, 'value' => '1', 'compare' => '=' );
	$query['meta_query'] = ! empty( $query['meta_query'] ) ? array( 'relation' => 'AND', $query['meta_query'], $flag ) : array( $flag );
	return $query;
}

function mrn_base_stack_get_project_list_contract( array $row ) {
	$out = array( 'classes' => array(), 'attributes' => array() );
	if ( ! mrn_base_stack_is_project_list( $row ) ) { return $out; }
	mrn_base_stack_enqueue_presentation(); $out['classes'][] = 'has-project-collection';
	if ( in_array( $row['project_action_frame'] ?? 0, array( true, 1, '1' ), true ) ) { $out['classes'][] = 'has-project-action-frame'; }
	$breakpoint = in_array( $row['project_grid_breakpoint'] ?? '', array( '576', '767', '991' ), true ) ? $row['project_grid_breakpoint'] : '767'; $out['classes'][] = 'project-grid-at-' . $breakpoint;
	$styles = array();
	foreach ( mrn_base_stack_get_project_frame_dimensions() as $name ) { $value = mrn_base_stack_get_content_frame_dimension( $row[ 'project_' . $name ] ?? '', $name ); if ( '' !== $value ) { $styles[] = '--mrn-project-' . str_replace( '_', '-', $name ) . ':' . $value; } }
	$columns = $row['project_columns'] ?? '3'; if ( in_array( $columns, array( 1, 2, 3, 4, '1', '2', '3', '4' ), true ) ) { $styles[] = '--mrn-project-columns:' . $columns; }
	foreach ( array( 'heading', 'title', 'subtitle', 'tag', 'action' ) as $part ) { $value = $row[ 'project_' . $part . '_weight' ] ?? ''; if ( is_scalar( $value ) && in_array( (string) $value, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-project-' . $part . '-weight:' . $value; } }
	foreach ( array( 'heading', 'title', 'title_hover', 'subtitle', 'tag', 'tag_surface', 'action_text', 'action_background', 'action_hover_background' ) as $part ) { $value = mrn_base_stack_get_frame_palette_color( $row[ 'project_' . $part . '_color' ] ?? '' ); if ( '' !== $value ) { $styles[] = '--mrn-project-' . str_replace( '_', '-', $part ) . '-color:' . $value; } }
	$out['attributes']['style'] = implode( ';', $styles ); return $out;
}

/** Resolve current record data at render time without copying it into the page. */
function mrn_base_stack_get_project_card_data( WP_Post $post, array $row ) {
	$get = static function ( $part ) use ( $post, $row ) { $field = $row[ 'project_' . $part . '_field' ] ?? ''; if ( ! is_string( $field ) || ! preg_match( '/^[a-zA-Z0-9_]+$/D', $field ) ) { return ''; } $value = function_exists( 'get_field' ) ? get_field( $field, $post->ID ) : null; return null === $value || false === $value ? get_post_meta( $post->ID, $field, true ) : $value; };
	$terms = static function ( $part ) use ( $post, $row ) { $tax = $row[ 'project_' . $part . '_taxonomy' ] ?? ''; if ( ! is_string( $tax ) || ! taxonomy_exists( $tax ) || ! is_object_in_taxonomy( $post->post_type, $tax ) ) { return array(); } $terms = get_the_terms( $post->ID, $tax ); return is_array( $terms ) ? $terms : array(); };
	$image = mrn_base_stack_get_image_attachment_id( $get( 'image' ) ); if ( ! $image || ! wp_attachment_is_image( $image ) ) { $image = get_post_thumbnail_id( $post ); }
	$subtitle = $get( 'subtitle' ); $subtitle = is_string( $subtitle ) ? trim( $subtitle ) : ''; if ( '' === $subtitle ) { $fallback = $terms( 'subtitle' ); $subtitle = $fallback ? $fallback[0]->name : ''; }
	$video = $get( 'video' ); $video = is_array( $video ) ? ( $video['url'] ?? '' ) : ( is_numeric( $video ) ? wp_get_attachment_url( (int) $video ) : $video );
	return array( 'id' => $post->ID, 'title' => get_the_title( $post ), 'url' => get_permalink( $post ), 'image' => $image, 'subtitle' => $subtitle, 'features' => $terms( 'feature' ), 'video' => is_string( $video ) ? esc_url_raw( $video, array( 'http', 'https' ) ) : '' );
}

function mrn_base_stack_render_project_list_item( WP_Post $post, array $args ) {
	if ( 'publish' !== $post->post_status ) { return ''; }
	$row = is_array( $args['project_settings'] ?? null ) ? $args['project_settings'] : array(); $data = mrn_base_stack_get_project_card_data( $post, $row );
	$tag = in_array( $row['project_heading_tag'] ?? '', array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $row['project_heading_tag'] : 'h3';
	$size = in_array( $row['project_image_size'] ?? '', array( 'medium_large', 'large', 'full' ), true ) ? $row['project_image_size'] : 'large';
	$image = $data['image'] ? mrn_base_stack_get_attachment_image( $data['image'], $size, array( 'alt' => $data['title'], 'loading' => 'lazy' ) ) : '';
	$linked = ! empty( $args['link_items'] ) && '' !== esc_url( $data['url'] );
	$html = '<li class="mrn-content-list-row__item mrn-record mrn-project" data-record-id="' . esc_attr( (string) $post->ID ) . '"><article>';
	if ( '' !== $image ) {
		$media = '<div class="mrn-record__media mrn-project__media">' . $image;
		if ( '' !== $data['video'] ) { $media .= '<video data-mrn-hover-video data-src="' . esc_url( $data['video'], array( 'http', 'https' ) ) . '" muted loop playsinline preload="none" aria-hidden="true" tabindex="-1"></video>'; }
		$media .= '</div>';
		$html .= $linked ? '<a class="mrn-project__image-link" href="' . esc_url( $data['url'] ) . '" aria-label="' . esc_attr( sprintf( __( 'View %s case study', 'mrn-base-stack' ), $data['title'] ) ) . '">' . $media . '</a>' : $media;
	}
	if ( '' !== $data['subtitle'] ) { $html .= '<p class="mrn-project__subtitle">' . esc_html( $data['subtitle'] ) . '</p>'; }
	$title = esc_html( $data['title'] ); $arrow = '<svg class="mrn-project__arrow" width="20" height="20" viewBox="0 0 12 10" aria-hidden="true" focusable="false"><path d="M4.608 9.952L9.584 4.96 4.624 0H6.4L11.36 4.976 6.384 9.952ZM0 4.32H10.56V5.632H0Z" fill="currentColor"/></svg>';
	$html .= '<' . $tag . ' class="mrn-project__title">' . ( $linked ? '<a href="' . esc_url( $data['url'] ) . '">' . $title . $arrow . '</a>' : $title ) . '</' . $tag . '>';
	if ( $data['features'] ) { $html .= '<ul class="mrn-project__tags" aria-label="' . esc_attr__( 'Project features', 'mrn-base-stack' ) . '">'; foreach ( $data['features'] as $term ) { $html .= '<li>' . esc_html( $term->name ) . '</li>'; } $html .= '</ul>'; }
	return $html . '</article></li>';
}

/** Reuse canonical link normalization, styles, attributes and icon helpers. */
function mrn_base_stack_get_project_actions( array $row ) {
	if ( ! mrn_base_stack_is_project_list( $row ) ) { return ''; }
	$links = mrn_rbl_get_content_links( array( 'links' => $row['project_links'] ?? array() ), array( 'max' => 4 ) ); if ( ! $links ) { return ''; }
	$html = '<div class="mrn-project__actions">';
	foreach ( $links as $link ) {
		$tag = mrn_rbl_get_content_link_tag_name( $link ); $attributes = mrn_rbl_get_content_link_html_attributes( $link ); $classes = 'mrn-ui__link ' . ( 'button' === ( $link['link_style'] ?? '' ) ? 'mrn-ui__link--button' : 'mrn-ui__link--text' ) . ' ' . mrn_rbl_get_content_link_custom_class_names( $link );
		$label = $link['text'] ?? $link['url'] ?? ''; $icon = mrn_base_stack_get_button_link_icon_markup( $link ); $position = mrn_base_stack_get_button_link_icon_position( $link );
		$html .= '<' . esc_html( $tag ) . ' class="' . esc_attr( trim( $classes ) ) . '" ' . $attributes . '>' . wp_kses_post( mrn_base_stack_get_compact_link_label_markup( $label, $icon, $position ) ) . '</' . esc_html( $tag ) . '>';
	}
	return $html . '</div>';
}

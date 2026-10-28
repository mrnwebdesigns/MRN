<?php
/** Optional dynamic Careers presentation for the native Content Lists row. */

/** Fields use the existing source, query, row-spacing and brand-token contracts. */
function mrn_base_stack_get_career_list_fields( $seed ) {
    $fields = array( array( 'key' => $seed . '_list_count_mode', 'name' => 'list_count_mode', 'label' => 'Collection Size', 'type' => 'select', 'choices' => array( 'limited' => 'Use Item Count', 'all' => 'All Published Items' ), 'default_value' => 'limited', 'instructions' => 'All Published Items disables pagination and offset. Existing lists retain their item count.' ) );
    $career = array();
    foreach ( array( 'apply' => 'apply_link', 'tags' => 'tags', 'tag_label' => 'label' ) as $part => $default ) { $career[] = array( 'key' => $seed . '_career_' . $part . '_field', 'name' => 'career_' . $part . '_field', 'label' => 'Career ' . ucwords( str_replace( '_', ' ', $part ) ) . ' Field', 'type' => 'text', 'default_value' => $default, 'instructions' => 'Existing ACF field name. Apply accepts a link or URL; Tags is an ordered repeater. Empty destinations use the record permalink.' ); }
    $career[] = array( 'key' => $seed . '_career_heading_tag', 'name' => 'career_heading_tag', 'label' => 'Career Title Heading Level', 'type' => 'select', 'choices' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6' ), 'default_value' => 'h3' );
    foreach ( array( 'content_width','inner_gutter','inner_gutter_compact','inner_gutter_mobile','heading_size','heading_line_height','heading_gap','intro_width','intro_size','intro_line_height','intro_gap','intro_gap_mobile','item_padding','item_padding_mobile','title_size','title_line_height','title_size_mobile','title_line_height_mobile','tag_size','tag_size_mobile','tag_padding_inline','tag_padding_inline_mobile','tag_padding_block','tag_padding_block_mobile','cta_size','cta_line_height' ) as $part ) { $career[] = array( 'key' => $seed . '_career_' . $part, 'name' => 'career_' . $part, 'label' => 'Career ' . ucwords( str_replace( '_', ' ', $part ) ), 'type' => 'text', 'instructions' => 'Optional bounded length or Site Text size/line token. Compact gutter: 1199px; small screen: 767px. Outer section spacing uses row presets.' ); }
    foreach ( array( 'title','text','action','divider','tag_background' ) as $part ) { $field = mrn_base_stack_get_background_color_field( $seed . '_career_' . $part . '_color' ); $field['name'] = 'career_' . $part . '_color'; $field['label'] = 'Career ' . ucwords( str_replace( '_', ' ', $part ) ) . ' Color'; $career[] = $field; }
    foreach ( $career as &$field ) { $field['conditional_logic'] = array( array( array( 'field' => $seed . '_record_presentation', 'operator' => '==', 'value' => 'careers' ) ) ); } unset( $field );
    return array_merge( $fields, $career );
}

/** Preserve limited-list defaults; explicit all mode has no offset or pagination. */
function mrn_base_stack_get_content_list_collection_query( array $row, array $query ) {
    if ( 'menu_order_date' === ( $row['orderby'] ?? '' ) && 'post__in' !== ( $query['orderby'] ?? '' ) ) { $query['orderby'] = array( 'menu_order' => 'ASC', 'date' => 'DESC' ); }
    if ( 'all' === ( $row['list_count_mode'] ?? '' ) ) { $query['posts_per_page'] = -1; $query['no_found_rows'] = true; unset( $query['offset'], $query['paged'] ); }
    if ( 'careers' === ( $row['record_presentation'] ?? '' ) ) { $query['has_password'] = false; }
    return $query;
}

/** Optional bounded presentation styles; unselected rows gain no overrides. */
function mrn_base_stack_get_career_list_contract( array $row ) {
    $out = array( 'classes' => array(), 'attributes' => array() );
    if ( 'careers' !== ( $row['record_presentation'] ?? '' ) ) { return $out; }
    mrn_base_stack_enqueue_presentation(); $out['classes'][] = 'has-careers-frame'; $styles = array();
    foreach ( mrn_base_stack_get_career_list_fields( 'field_mrn_career_style' ) as $field ) {
        $name = $field['name']; if ( ! str_starts_with( $name, 'career_' ) || str_ends_with( $name, '_field' ) || 'career_heading_tag' === $name ) { continue; }
        $part = substr( $name, 7 ); $raw = $row[ $name ] ?? '';
        $value = str_ends_with( $name, '_color' ) ? mrn_base_stack_get_frame_palette_color( $raw ) : mrn_base_stack_get_content_frame_dimension( $raw, $part );
        if ( '' !== $value ) { $styles[] = '--mrn-career-' . str_replace( '_', '-', $part ) . ':' . $value; }
    }
    $out['attributes']['style'] = implode( ';', $styles ); return $out;
}

/** Read current public records at render time, without duplicating their content. */
function mrn_base_stack_render_career_list_item( WP_Post $post, array $args ) {
    if ( 'publish' !== $post->post_status || '' !== $post->post_password ) { return ''; }
    $row = is_array( $args['career_settings'] ?? null ) ? $args['career_settings'] : array();
    $get = static function ( $part, $default ) use ( $post, $row ) { $name = $row[ 'career_' . $part . '_field' ] ?? $default; $name = is_string( $name ) ? sanitize_key( $name ) : ''; return '' !== $name && function_exists( 'get_field' ) ? get_field( $name, $post->ID ) : ''; };
    $apply = $get( 'apply', 'apply_link' ); $url = is_array( $apply ) ? ( $apply['url'] ?? '' ) : $apply; $url = is_string( $url ) ? esc_url( $url ) : '';
    if ( '' === $url || '#' === $url ) { $url = esc_url( get_permalink( $post ) ); }
    $target = is_array( $apply ) && in_array( $apply['target'] ?? '', array( '_blank','_self','_parent','_top' ), true ) ? $apply['target'] : '';
    $tag_field = is_string( $row['career_tag_label_field'] ?? null ) ? sanitize_key( $row['career_tag_label_field'] ) : 'label';
    $tags = $get( 'tags', 'tags' ); $tag_html = '';
    foreach ( is_array( $tags ) ? $tags : array() as $tag ) { $label = is_array( $tag ) ? ( $tag[ $tag_field ] ?? '' ) : ''; if ( is_string( $label ) && '' !== trim( $label ) ) { $tag_html .= '<li class="mrn-career__tag">' . esc_html( $label ) . '</li>'; } }
    $level = in_array( $row['career_heading_tag'] ?? '', array( 'h2','h3','h4','h5','h6' ), true ) ? $row['career_heading_tag'] : 'h3';
    $linked = ! empty( $args['link_items'] ); $title = esc_html( get_the_title( $post ) );
    $arrow = '<svg class="mrn-career__arrow" width="14" height="14" viewBox="0 0 12 10" aria-hidden="true" focusable="false"><path d="M4.608 9.952L9.584 4.96 4.624 0H6.4L11.36 4.976 6.384 9.952ZM0 4.32H10.56V5.632H0Z" fill="currentColor"/></svg>';
    if ( $linked ) { $title = '<a class="mrn-career__link" href="' . $url . '"' . ( '' !== $target ? ' target="' . esc_attr( $target ) . '" rel="noopener noreferrer"' : '' ) . '>' . $title . $arrow . '</a>'; }
    $cta = $linked && ! empty( $args['show_read_more'] ) && is_string( $args['read_more_label'] ?? null ) && '' !== trim( $args['read_more_label'] ) ? '<span class="mrn-career__cta" aria-hidden="true">' . esc_html( $args['read_more_label'] ) . $arrow . '</span>' : '';
    return '<li class="mrn-content-list-row__item mrn-career" data-record-id="' . absint( $post->ID ) . '"><' . $level . ' class="mrn-career__title">' . $title . '</' . $level . '>' . ( '' !== $tag_html ? '<ul class="mrn-career__tags">' . $tag_html . '</ul>' : '' ) . $cta . '</li>';
}

<?php
/** Optional native article collections. Existing Content Lists keep their contracts. */

function mrn_base_stack_is_article_list( array $row ) {
	return 'articles' === ( $row['record_presentation'] ?? '' ) && 'post' === ( $row['list_post_type'] ?? 'post' );
}

function mrn_base_stack_get_article_frame_dimensions() {
	return explode( ' ', 'content_width inner_gutter inner_gutter_compact inner_gutter_mobile inner_gutter_small row_gap column_gap row_gap_compact column_gap_compact row_gap_mobile filters_gap filters_gap_mobile filters_space_after filters_space_after_mobile filters_padding_top filters_padding_bottom filters_padding_top_mobile filters_padding_bottom_mobile filter_height filter_padding_inline filter_padding_block filter_size filter_line_height filter_height_mobile filter_padding_inline_mobile filter_padding_block_mobile filter_size_mobile filter_line_height_mobile media_space_after media_radius metadata_size metadata_line_height metadata_space_after metadata_gap title_size title_line_height title_size_mobile title_line_height_mobile title_space_after title_gap tags_gap tags_space_after tag_min_height tag_padding_inline tag_padding_block tag_size tag_line_height excerpt_size excerpt_line_height load_space_before load_space_before_mobile load_height load_padding_inline load_size load_line_height load_min_width load_radius' );
}

function mrn_base_stack_get_article_list_fields( $seed ) {
	$make = static function ( $name, $type, array $extra = array() ) use ( $seed ) { return array_merge( array( 'key' => $seed . '_article_' . $name, 'name' => 'article_' . $name, 'label' => 'Article ' . ucwords( str_replace( '_', ' ', $name ) ), 'type' => $type, 'default_value' => '' ), $extra ); };
	$fields = array(
		$make( 'filters', 'select', array( 'choices' => array( '' => 'No Category Controls', 'client-tabs' => 'Category Filter Buttons' ), 'instructions' => 'Filters operate on this collection only. Collection Size controls how many published posts are queried; every queried post remains available without JavaScript.' ) ),
		$make( 'load_more', 'true_false', array( 'ui' => 1, 'default_value' => 0, 'instructions' => 'Reveal batches from this collection without a page reload. Standard numbered pagination is disabled for Articles.' ) ),
		$make( 'load_batch_size', 'number', array( 'min' => 1, 'max' => 1000, 'step' => 1, 'default_value' => 9 ) ),
		$make( 'empty_categories', 'true_false', array( 'ui' => 1, 'default_value' => 0 ) ),
		$make( 'category_order', 'text', array( 'instructions' => 'Optional category slugs, separated by commas. Remaining categories follow name order.' ) ),
		$make( 'excluded_terms', 'taxonomy', array( 'taxonomy' => 'category', 'field_type' => 'multi_select', 'return_format' => 'id', 'add_term' => 0, 'save_terms' => 0, 'load_terms' => 0 ) ),
		$make( 'exclude_default_category', 'true_false', array( 'ui' => 1, 'default_value' => 0 ) ),
		$make( 'ignore_sticky_posts', 'true_false', array( 'ui' => 1, 'default_value' => 1 ) ),
		$make( 'read_time', 'true_false', array( 'ui' => 1, 'default_value' => 0 ) ),
		$make( 'words_per_minute', 'number', array( 'min' => 1, 'max' => 1000, 'step' => 1, 'default_value' => 225 ) ),
		$make( 'date_format', 'text', array( 'default_value' => 'F j, Y' ) ),
		$make( 'excerpt_mode', 'select', array( 'choices' => array( '' => 'WordPress Excerpt', 'no-headings' => 'Generate without Headings' ), 'instructions' => 'An authored excerpt keeps WordPress excerpt filters. Without an authored excerpt, Generate without Headings removes complete heading blocks before stripping shortcodes and markup.' ) ),
		$make( 'excerpt_lines', 'number', array( 'min' => 0, 'max' => 10, 'step' => 1, 'default_value' => 3, 'instructions' => 'Visible line limit; the complete excerpt remains in the document. Zero shows every line.' ) ),
		$make( 'heading_tag', 'select', array( 'choices' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6' ), 'default_value' => 'h3' ) ),
		$make( 'all_label', 'text', array( 'default_value' => 'All' ) ),
		$make( 'filter_label', 'text', array( 'default_value' => 'Filter posts by topic' ) ),
		$make( 'load_more_label', 'text', array( 'default_value' => 'Load More' ) ),
		$make( 'frame', 'select', array( 'choices' => array( '' => 'Use Existing Frame', 'editorial' => 'Editorial Article Grid' ), 'instructions' => 'Optional responsive article grid. All outer section spacing remains in row presets.' ) ),
		$make( 'media_ratio', 'text', array( 'instructions' => 'Optional positive width/height ratio, for example 360/288.' ) ),
		$make( 'row_spacing_mobile_breakpoint', 'select', array( 'choices' => array( '' => 'Use Existing (782px)', '767' => '767px' ), 'instructions' => 'Optional breakpoint for existing row margin/padding presets.' ) ),
		$make( 'padding_mobile_breakpoint', 'select', array( 'choices' => array( '' => 'Use Row Preset Breakpoint', '900' => 'Use Mobile Padding at 900px' ), 'instructions' => 'Switch only row-padding preset values earlier; margin presets keep their own breakpoint.' ) ),
		$make( 'gutter_mobile_breakpoint', 'select', array( 'choices' => array( '767' => '767px', '900' => '900px' ), 'default_value' => '767' ) ),
	);
	foreach ( mrn_base_stack_get_article_frame_dimensions() as $name ) { $fields[] = $make( $name, 'text', array( 'instructions' => 'Optional validated length or Site Text token. Compact grid gaps: 1100px; two columns: 960px; mobile type/grid: 640px. Compact gutter: 1199px; mobile gutter uses the selected breakpoint. Outer spacing stays in row presets.', 'wrapper' => array( 'width' => '50' ) ) ); }
	foreach ( array( 'text', 'title', 'metadata', 'excerpt', 'surface', 'active_surface', 'active_text', 'hover', 'border', 'load_hover_surface', 'load_hover_text', 'load_hover_border' ) as $part ) { $fields[] = $make( $part . '_color', 'load_hover_text' === $part ? 'color_picker' : 'select', array( 'choices' => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(), 'allow_null' => 1, 'ui' => 1 ) ); }
	foreach ( array( 'title', 'metadata', 'excerpt', 'filter', 'tag', 'load' ) as $part ) { $fields[] = $make( $part . '_font', 'select', array( 'choices' => array( '' => 'Use Existing Font', 'body' => 'Site Body Font', 'heading' => 'Site Heading Font' ) ) ); $fields[] = $make( $part . '_weight', 'select', array( 'choices' => array( '' => 'Use Existing Weight' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ) ) ); }
	foreach ( $fields as &$field ) { $field['conditional_logic'] = array( array( array( 'field' => $seed . '_record_presentation', 'operator' => '==', 'value' => 'articles' ) ) ); } unset( $field );
	return $fields;
}

/** Explicit article settings never change other collection queries. */
function mrn_base_stack_get_article_collection_query( array $row, array $query ) {
	if ( ! mrn_base_stack_is_article_list( $row ) ) { return $query; }
	$query['ignore_sticky_posts'] = ! array_key_exists( 'article_ignore_sticky_posts', $row ) || ! empty( $row['article_ignore_sticky_posts'] );
	$query['no_found_rows'] = true;
	unset( $query['paged'] );
	return $query;
}

function mrn_base_stack_get_article_excluded_terms( array $row ) {
	$raw = $row['article_excluded_terms'] ?? array();
	$ids = is_array( $raw ) ? array_values( array_unique( array_filter( array_map( 'absint', array_filter( $raw, 'is_scalar' ) ) ) ) ) : array();
	if ( ! empty( $row['article_exclude_default_category'] ) ) { $ids[] = absint( get_option( 'default_category' ) ); }
	return array_values( array_unique( $ids ) );
}

function mrn_base_stack_get_article_categories( array $row, $post_id = 0 ) {
	$terms = $post_id ? get_the_category( $post_id ) : get_terms( array( 'taxonomy' => 'category', 'hide_empty' => empty( $row['article_empty_categories'] ), 'orderby' => 'name', 'order' => 'ASC' ) );
	if ( ! is_array( $terms ) || is_wp_error( $terms ) ) { return array(); }
	$excluded = mrn_base_stack_get_article_excluded_terms( $row );
	$terms = array_values( array_filter( $terms, static fn( $term ) => $term instanceof WP_Term && ! in_array( (int) $term->term_id, $excluded, true ) ) );
	if ( ! $post_id ) {
		$raw = $row['article_category_order'] ?? ''; $slugs = is_string( $raw ) ? preg_split( '/[\s,]+/', trim( $raw ), -1, PREG_SPLIT_NO_EMPTY ) : array();
		$order = array_values( array_unique( array_map( 'sanitize_title', $slugs ) ) );
		usort( $terms, static function ( $a, $b ) use ( $order ) { $x = array_search( $a->slug, $order, true ); $y = array_search( $b->slug, $order, true ); $x = false === $x ? PHP_INT_MAX : $x; $y = false === $y ? PHP_INT_MAX : $y; return $x === $y ? strcasecmp( $a->name, $b->name ) : $x <=> $y; } );
	}
	return $terms;
}

/** Read current public article data; password-protected body copy is never exposed. */
function mrn_base_stack_get_article_card_data( WP_Post $post, array $row ) {
	$excerpt = ''; $minutes = 0;
	if ( '' === $post->post_password ) {
		if ( has_excerpt( $post->ID ) || 'no-headings' !== ( $row['article_excerpt_mode'] ?? '' ) ) { $raw = get_the_excerpt( $post->ID ); } else { $raw = preg_replace( '/<h[1-6][^>]*>.*?<\/h[1-6]>/is', ' ', $post->post_content ); $raw = wp_strip_all_tags( strip_shortcodes( $raw ) ); }
		$excerpt = wp_trim_words( trim( preg_replace( '/\s+/', ' ', (string) $raw ) ), max( 5, min( 1000, absint( $row['excerpt_length'] ?? 24 ) ) ), '...' );
		if ( ! empty( $row['article_read_time'] ) ) { $words = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) ) ); $minutes = max( 1, (int) ceil( str_word_count( $words ) / max( 1, min( 1000, absint( $row['article_words_per_minute'] ?? 225 ) ) ) ) ); }
	}
	$format = is_string( $row['article_date_format'] ?? null ) && '' !== trim( $row['article_date_format'] ) ? $row['article_date_format'] : 'F j, Y';
	return array( 'id' => $post->ID, 'title' => get_the_title( $post ), 'url' => get_permalink( $post ), 'excerpt' => $excerpt, 'minutes' => $minutes, 'date' => get_the_date( $format, $post->ID ), 'datetime' => get_the_date( 'c', $post->ID ), 'categories' => mrn_base_stack_get_article_categories( $row, $post->ID ) );
}

function mrn_base_stack_render_article_list_item( WP_Post $post, array $args ) {
	if ( 'publish' !== $post->post_status || 'post' !== $post->post_type ) { return ''; }
	$row = is_array( $args['article_settings'] ?? null ) ? $args['article_settings'] : array(); $data = mrn_base_stack_get_article_card_data( $post, $row );
	$tag = in_array( $row['article_heading_tag'] ?? '', array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $row['article_heading_tag'] : 'h3';
	$media = ! empty( $args['show_featured_image'] ) ? get_the_post_thumbnail( $post, 'medium_large', array( 'alt' => $data['title'], 'width' => 360, 'height' => 288, 'loading' => 'lazy' ) ) : '';
	$html = '<li class="mrn-article" data-article-card data-record-id="' . esc_attr( (string) $post->ID ) . '" data-categories="' . esc_attr( implode( ' ', array_map( static fn( $term ) => sanitize_html_class( $term->slug ), $data['categories'] ) ) ) . '"><article>';
	$link = ! empty( $args['link_items'] ) && '' !== esc_url( $data['url'] ); $html .= $link ? '<a class="mrn-article__link" href="' . esc_url( $data['url'] ) . '">' : '<div class="mrn-article__link">';
	if ( ! empty( $args['show_featured_image'] ) ) { $html .= '<div class="mrn-article__media">' . ( $media ? $media : '<span class="mrn-article__placeholder" aria-hidden="true"></span>' ) . '</div>'; }
	$metadata = ! empty( $args['show_publish_date'] ) ? '<time datetime="' . esc_attr( $data['datetime'] ) . '">' . esc_html( $data['date'] ) . '</time>' : '';
	if ( $data['minutes'] ) { $metadata .= ( $metadata ? ' <span aria-hidden="true">&bull;</span> ' : '' ) . '<span>' . esc_html( sprintf( _n( '%d min read', '%d min read', $data['minutes'], 'mrn-base-stack' ), $data['minutes'] ) ) . '</span>'; }
	if ( $metadata ) { $html .= '<p class="mrn-article__meta">' . $metadata . '</p>'; }
	$html .= '<' . $tag . ' class="mrn-article__title"><span>' . esc_html( $data['title'] ) . '</span>' . ( $link ? '<span class="mrn-article__arrow" aria-hidden="true">&rarr;</span>' : '' ) . '</' . $tag . '>';
	if ( $data['categories'] ) { $html .= '<ul class="mrn-article__tags" aria-label="' . esc_attr__( 'Post categories', 'mrn-base-stack' ) . '">'; foreach ( $data['categories'] as $term ) { $html .= '<li>' . esc_html( $term->name ) . '</li>'; } $html .= '</ul>'; }
	if ( ! empty( $args['show_excerpt'] ) && '' !== $data['excerpt'] ) { $html .= '<p class="mrn-article__excerpt">' . esc_html( $data['excerpt'] ) . '</p>'; }
	return $html . ( $link ? '</a>' : '</div>' ) . '</article></li>';
}

/** Hidden until progressive enhancement is ready, with every card visible without JS. */
function mrn_base_stack_get_article_controls( array $row, $after = false ) {
	if ( ! mrn_base_stack_is_article_list( $row ) ) { return ''; }
	if ( $after ) {
		$label = is_string( $row['article_load_more_label'] ?? null ) && '' !== trim( $row['article_load_more_label'] ) ? $row['article_load_more_label'] : __( 'Load More', 'mrn-base-stack' );
		return ( ! empty( $row['article_load_more'] ) ? '<div class="mrn-article__more" data-article-controls hidden><button type="button" data-article-more>' . esc_html( $label ) . ' <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 18 18"><path d="M9 11.55L4.5 7.05L5.55 6L9 9.45L12.45 6L13.5 7.05L9 11.55Z" fill="currentColor"/></svg></button></div>' : '' ) . '<p class="screen-reader-text" role="status" aria-live="polite" data-article-status></p>';
	}
	if ( 'client-tabs' !== ( $row['article_filters'] ?? '' ) ) { return ''; }
	$all = is_string( $row['article_all_label'] ?? null ) && '' !== trim( $row['article_all_label'] ) ? $row['article_all_label'] : __( 'All', 'mrn-base-stack' );
	$label = is_string( $row['article_filter_label'] ?? null ) && '' !== trim( $row['article_filter_label'] ) ? $row['article_filter_label'] : __( 'Filter posts by topic', 'mrn-base-stack' );
	$html = '<div class="mrn-article__filters" data-article-controls hidden role="group" aria-label="' . esc_attr( $label ) . '"><button type="button" data-article-filter="*" aria-pressed="true">' . esc_html( $all ) . '</button>';
	foreach ( mrn_base_stack_get_article_categories( $row ) as $term ) { $html .= '<button type="button" data-article-filter="' . esc_attr( sanitize_html_class( $term->slug ) ) . '" aria-pressed="false">' . esc_html( $term->name ) . '</button>'; }
	return $html . '</div>';
}

function mrn_base_stack_get_article_list_contract( array $row ) {
	$out = array( 'classes' => array(), 'attributes' => array() );
	if ( ! mrn_base_stack_is_article_list( $row ) ) { return $out; }
	$out['classes'][] = 'has-article-collection'; $styles = array();
	if ( '767' === ( $row['article_row_spacing_mobile_breakpoint'] ?? '' ) ) { $out['classes'][] = 'article-row-spacing-at-767'; }
	if ( '900' === ( $row['article_padding_mobile_breakpoint'] ?? '' ) ) { $out['classes'][] = 'article-padding-at-900'; }
	if ( function_exists( 'wp_enqueue_style' ) ) { wp_enqueue_style( 'mrn-base-stack-articles' ); }
	$out['attributes']['data-article-status-message'] = __( 'Showing %1$d of %2$d posts', 'mrn-base-stack' );
	$out['attributes']['data-mrn-articles'] = (string) max( 1, min( 1000, absint( $row['article_load_batch_size'] ?? 9 ) ) );
	$out['attributes']['data-article-incremental'] = ! empty( $row['article_load_more'] ) ? '1' : '0';
	if ( 'client-tabs' === ( $row['article_filters'] ?? '' ) || ! empty( $row['article_load_more'] ) ) { $out['attributes']['data-article-interactive'] = 'true'; if ( function_exists( 'wp_enqueue_script' ) ) { wp_enqueue_script( 'mrn-base-stack-front-end-articles' ); } }
	if ( 'editorial' === ( $row['article_frame'] ?? '' ) ) {
		$out['classes'][] = 'has-article-frame'; $out['classes'][] = 'article-gutter-mobile-' . ( '900' === ( $row['article_gutter_mobile_breakpoint'] ?? '' ) ? '900' : '767' );
		foreach ( mrn_base_stack_get_article_frame_dimensions() as $name ) { $value = mrn_base_stack_get_content_frame_dimension( $row[ 'article_' . $name ] ?? '', $name ); if ( '' !== $value ) { $styles[] = '--mrn-article-' . str_replace( '_', '-', $name ) . ':' . $value; } }
		foreach ( array( 'text', 'title', 'metadata', 'excerpt', 'surface', 'active_surface', 'active_text', 'hover', 'border', 'load_hover_surface', 'load_hover_text', 'load_hover_border' ) as $part ) { $value = mrn_base_stack_get_frame_palette_color( $row[ 'article_' . $part . '_color' ] ?? '' ); if ( '' !== $value ) { $styles[] = '--mrn-article-' . str_replace( '_', '-', $part ) . '-color:' . $value; } }
		foreach ( array( 'title', 'metadata', 'excerpt', 'filter', 'tag', 'load' ) as $part ) { $font = $row[ 'article_' . $part . '_font' ] ?? ''; if ( in_array( $font, array( 'body', 'heading' ), true ) ) { $styles[] = '--mrn-article-' . $part . '-font:var(--site-font-' . $font . ')'; } $weight = $row[ 'article_' . $part . '_weight' ] ?? ''; if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-article-' . $part . '-weight:' . $weight; } }
		$ratio = $row['article_media_ratio'] ?? ''; if ( is_string( $ratio ) && preg_match( '/^([0-9]{1,4}(?:\.[0-9]{1,4})?)\s*\/\s*([0-9]{1,4}(?:\.[0-9]{1,4})?)$/D', $ratio, $parts ) && (float) $parts[1] > 0 && (float) $parts[2] > 0 ) { $styles[] = '--mrn-article-media-ratio:' . $parts[1] . '/' . $parts[2]; }
		$lines = max( 0, min( 10, absint( $row['article_excerpt_lines'] ?? 3 ) ) ); if ( ! $lines ) { $out['classes'][] = 'article-show-complete-excerpt'; } else { $styles[] = '--mrn-article-excerpt-lines:' . $lines; }
	}
	if ( $styles ) { $out['attributes']['style'] = implode( ';', $styles ); }
	return $out;
}

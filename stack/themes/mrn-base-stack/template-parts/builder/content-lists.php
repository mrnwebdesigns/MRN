<?php
/**
 * Builder row: Content Lists.
 *
 * @package mrn-base-stack
 */

$context = is_array( $args ?? null ) ? $args : array();
$row     = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$post_id = isset( $context['post_id'] ) ? (int) $context['post_id'] : 0;
$index   = isset( $context['index'] ) ? (int) $context['index'] : 0;

$label       = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$label_tag   = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['label_tag'] ?? '', 'p' ) : 'p';
$heading     = isset( $row['text_field'] ) ? trim( (string) $row['text_field'] ) : '';
$heading_tag = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['text_field_tag'] ?? '', 'h2' ) : 'h2';
$intro       = isset( $row['content'] ) ? (string) $row['content'] : '';

$post_type_choices = function_exists( 'mrn_base_stack_get_content_list_post_type_choices' ) ? mrn_base_stack_get_content_list_post_type_choices() : array( 'post' => 'Posts' );
$post_type         = isset( $row['list_post_type'] ) ? sanitize_key( (string) $row['list_post_type'] ) : 'post';
if ( ! isset( $post_type_choices[ $post_type ] ) ) {
	$post_type = isset( $post_type_choices['post'] ) ? 'post' : (string) array_key_first( $post_type_choices );
}

$list_style = isset( $row['list_style'] ) ? sanitize_key( (string) $row['list_style'] ) : 'unordered';
if ( ! in_array( $list_style, array( 'unordered', 'ordered' ), true ) ) {
	$list_style = 'unordered';
}

$orderby_choices = function_exists( 'mrn_base_stack_get_content_list_orderby_choices' ) ? mrn_base_stack_get_content_list_orderby_choices() : array( 'date' => 'Publish Date' );
$orderby         = isset( $row['orderby'] ) ? sanitize_key( (string) $row['orderby'] ) : 'date';
if ( ! isset( $orderby_choices[ $orderby ] ) ) {
	$orderby = 'date';
}

$order = isset( $row['order'] ) ? strtoupper( sanitize_key( (string) $row['order'] ) ) : 'DESC';
if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
	$order = 'DESC';
}

$posts_per_page     = max( 1, absint( $row['posts_per_page'] ?? 10 ) );
$offset             = absint( $row['offset'] ?? 0 );
$tax_query          = function_exists( 'mrn_base_stack_get_content_list_tax_query' )
	? mrn_base_stack_get_content_list_tax_query( $row, $post_id, $post_type )
	: array();
$show_pagination    = ! empty( $row['enable_pagination'] );
$show_featured_image = ! empty( $row['show_featured_image'] );
$show_publish_date  = ! empty( $row['show_publish_date'] );
$show_excerpt       = ! empty( $row['show_excerpt'] );
$excerpt_length     = max( 5, absint( $row['excerpt_length'] ?? 24 ) );
$show_read_more     = ! empty( $row['show_read_more'] );
$read_more_label    = isset( $row['read_more_label'] ) ? trim( (string) $row['read_more_label'] ) : 'Read More';
$empty_message      = isset( $row['empty_message'] ) ? trim( (string) $row['empty_message'] ) : 'No content found.';
$hide_when_empty    = ! empty( $row['hide_when_empty'] );
$background_color   = isset( $row['background_color'] ) ? trim( (string) $row['background_color'] ) : '';
$bottom_accent      = ! empty( $row['bottom_accent'] );
$accent_slug        = isset( $row['bottom_accent_style'] ) ? (string) $row['bottom_accent_style'] : '';

$width_layers = function_exists( 'mrn_base_stack_get_section_width_layers' )
	? mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', 'wide', 'wide' )
	: array(
		'width'           => 'wide',
		'section_class'   => 'mrn-layout-section--contained',
		'container_class' => 'mrn-layout-container--wide',
	);

$current_page = $show_pagination && function_exists( 'mrn_base_stack_get_content_list_current_page' )
	? mrn_base_stack_get_content_list_current_page( $post_id, $index )
	: 1;
$query_args   = array(
	'post_type'           => $post_type,
	'post_status'         => 'publish',
	'posts_per_page'      => $posts_per_page,
	'orderby'             => $orderby,
	'order'               => $order,
	'ignore_sticky_posts' => true,
	'no_found_rows'       => ! $show_pagination,
);

if ( $show_pagination ) {
	$query_args['paged'] = max( 1, $current_page );
} elseif ( $offset > 0 ) {
	$query_args['offset'] = $offset;
}

if ( ! empty( $tax_query ) ) {
	$query_args['tax_query'] = $tax_query;
}

$query = new WP_Query( $query_args );

$has_intro   = '' !== trim( wp_strip_all_tags( $intro ) );
$has_heading = '' !== $heading;
$has_label   = '' !== $label;
$has_posts   = $query->have_posts();

if ( ! $has_posts && $hide_when_empty ) {
	wp_reset_postdata();
	return;
}

if ( ! $has_label && ! $has_heading && ! $has_intro && ! $has_posts && '' === $empty_message ) {
	wp_reset_postdata();
	return;
}

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--content-lists',
	'mrn-content-builder__row--content-lists-' . $list_style,
);
$section_styles  = array();

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-content-list-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$accent_contract = function_exists( 'mrn_base_stack_get_builder_accent_contract' ) ? mrn_base_stack_get_builder_accent_contract( $bottom_accent, $accent_slug ) : array(
	'classes'    => $bottom_accent ? array( 'has-bottom-accent' ) : array(),
	'attributes' => array(),
);
$motion_contract = function_exists( 'mrn_base_stack_get_builder_motion_contract' ) ? mrn_base_stack_get_builder_motion_contract( $row ) : array(
	'classes'    => array(),
	'attributes' => array(),
);
$section_classes   = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $accent_contract ) : $section_classes;
$section_classes   = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $motion_contract ) : $section_classes;
$section_attrs     = isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ? $accent_contract['attributes'] : array();
$section_attrs     = function_exists( 'mrn_base_stack_merge_builder_attributes' ) ? mrn_base_stack_merge_builder_attributes( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() ) : array_merge( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() );
$section_attr_html = function_exists( 'mrn_base_stack_get_html_attributes' ) ? mrn_base_stack_get_html_attributes( $section_attrs ) : '';
$surface_style     = function_exists( 'mrn_base_stack_get_inline_style_attribute' ) ? mrn_base_stack_get_inline_style_attribute( $section_styles ) : implode( '; ', $section_styles );
$is_full_width     = 'full-width' === ( $width_layers['width'] ?? '' );
$list_tag          = 'ordered' === $list_style ? 'ol' : 'ul';
$pagination_html   = '';

if ( $show_pagination && $query->max_num_pages > 1 ) {
	$page_arg     = function_exists( 'mrn_base_stack_get_content_list_pagination_query_arg' ) ? mrn_base_stack_get_content_list_pagination_query_arg( $post_id, $index ) : 'mrn_list_page';
	$base_url     = $post_id ? get_permalink( $post_id ) : '';
	$current_args = array();

	if ( ! is_string( $base_url ) || '' === $base_url ) {
		$base_url = home_url( '/' );
	}

	foreach ( $_GET as $query_key => $query_value ) {
		$query_key = is_string( $query_key ) ? sanitize_key( $query_key ) : '';
		if ( '' === $query_key || $query_key === $page_arg ) {
			continue;
		}

		if ( is_scalar( $query_value ) ) {
			$current_args[ $query_key ] = sanitize_text_field( wp_unslash( (string) $query_value ) );
		}
	}

	$pagination_base = add_query_arg(
		array_merge(
			$current_args,
			array(
				$page_arg => '%#%',
			)
		),
		$base_url
	);
	$pagination_base = str_replace( '%25%23%25', '%#%', $pagination_base );
	$pagination_html = paginate_links(
		array(
			'base'      => $pagination_base,
			'format'    => '',
			'current'   => max( 1, $current_page ),
			'total'     => max( 1, (int) $query->max_num_pages ),
			'type'      => 'list',
			'prev_text' => 'Previous',
			'next_text' => 'Next',
		)
	);
}
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="mrn-layout-section mrn-layout-section--content-lists <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<div class="mrn-layout-grid mrn-layout-grid--content-lists">
				<?php if ( $has_label || $has_heading || $has_intro ) : ?>
					<div class="mrn-content-list-row__header">
						<?php if ( $has_label ) : ?>
							<<?php echo esc_html( $label_tag ); ?> class="mrn-shell-section__label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $label_tag ); ?>>
						<?php endif; ?>
						<?php if ( $has_heading ) : ?>
							<<?php echo esc_html( $heading_tag ); ?> class="mrn-shell-section__heading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
						<?php endif; ?>
						<?php if ( $has_intro ) : ?>
							<div class="mrn-shell-section__content mrn-content-list-row__intro">
								<?php echo apply_filters( 'the_content', $intro ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<<?php echo esc_html( $list_tag ); ?> class="mrn-content-list-row__items mrn-content-list-row__items--<?php echo esc_attr( $list_style ); ?>">
					<?php if ( $has_posts ) : ?>
						<?php while ( $query->have_posts() ) : ?>
							<?php
							$query->the_post();
							$item_post    = get_post();
							$permalink    = $item_post instanceof WP_Post ? get_permalink( $item_post ) : '';
							$item_title   = $item_post instanceof WP_Post ? get_the_title( $item_post ) : '';
							$item_excerpt = $show_excerpt && $item_post instanceof WP_Post && function_exists( 'mrn_base_stack_get_content_list_excerpt' ) ? mrn_base_stack_get_content_list_excerpt( $item_post, $excerpt_length ) : '';
							$item_classes = array( 'mrn-content-list-row__item' );

							if ( $show_featured_image && has_post_thumbnail() ) {
								$item_classes[] = 'mrn-content-list-row__item--has-image';
							}
							?>
							<li class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>">
								<article class="mrn-content-list-row__card">
									<?php if ( $show_featured_image && has_post_thumbnail() && '' !== $permalink ) : ?>
										<a class="mrn-content-list-row__media" href="<?php echo esc_url( $permalink ); ?>">
											<?php the_post_thumbnail( 'medium_large' ); ?>
										</a>
									<?php elseif ( $show_featured_image && has_post_thumbnail() ) : ?>
										<div class="mrn-content-list-row__media">
											<?php the_post_thumbnail( 'medium_large' ); ?>
										</div>
									<?php endif; ?>

									<div class="mrn-content-list-row__body">
										<?php if ( $show_publish_date && $item_post instanceof WP_Post ) : ?>
											<p class="mrn-content-list-row__meta"><?php echo esc_html( get_the_date( '', $item_post ) ); ?></p>
										<?php endif; ?>

										<?php if ( '' !== $item_title ) : ?>
											<h3 class="mrn-content-list-row__title">
												<?php if ( '' !== $permalink ) : ?>
													<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $item_title ); ?></a>
												<?php else : ?>
													<?php echo esc_html( $item_title ); ?>
												<?php endif; ?>
											</h3>
										<?php endif; ?>

										<?php if ( '' !== $item_excerpt ) : ?>
											<p class="mrn-content-list-row__excerpt"><?php echo esc_html( $item_excerpt ); ?></p>
										<?php endif; ?>

										<?php if ( $show_read_more && '' !== $permalink ) : ?>
											<p class="mrn-content-list-row__link">
												<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( '' !== $read_more_label ? $read_more_label : 'Read More' ); ?></a>
											</p>
										<?php endif; ?>
									</div>
								</article>
							</li>
						<?php endwhile; ?>
					<?php elseif ( '' !== $empty_message ) : ?>
						<li class="mrn-content-list-row__item mrn-content-list-row__item--empty">
							<p class="mrn-content-list-row__empty"><?php echo esc_html( $empty_message ); ?></p>
						</li>
					<?php endif; ?>
				</<?php echo esc_html( $list_tag ); ?>>

				<?php if ( '' !== $pagination_html ) : ?>
					<nav class="mrn-content-list-row__pagination" aria-label="Content list pagination">
						<?php echo wp_kses_post( $pagination_html ); ?>
					</nav>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
<?php
wp_reset_postdata();

<?php
/**
 * Content Lists reusable block template.
 *
 * @var array<string, mixed> $context
 */

$fields       = isset( $context['fields'] ) && is_array( $context['fields'] ) ? $context['fields'] : array();
$block_post   = isset( $context['post'] ) && $context['post'] instanceof WP_Post ? $context['post'] : null;
$block_id     = isset( $context['post_id'] ) ? (int) $context['post_id'] : ( $block_post instanceof WP_Post ? (int) $block_post->ID : 0 );
$block_slug   = isset( $context['post_name'] ) ? (string) $context['post_name'] : ( $block_post instanceof WP_Post ? (string) $block_post->post_name : '' );
$host_post_id = isset( $context['host_post_id'] ) ? (int) $context['host_post_id'] : 0;
$host_index   = isset( $context['host_row_index'] ) ? (int) $context['host_row_index'] : 0;

if ( $host_post_id < 1 ) {
	$host_post_id = get_queried_object_id();
}

$label       = isset( $fields['label'] ) ? trim( (string) $fields['label'] ) : '';
$label_tag   = function_exists( 'mrn_rbl_normalize_text_tag' ) ? mrn_rbl_normalize_text_tag( $fields['label_tag'] ?? '', 'p' ) : 'p';
$heading     = isset( $fields['text_field'] ) ? trim( (string) $fields['text_field'] ) : '';
$heading_tag = function_exists( 'mrn_rbl_normalize_text_tag' ) ? mrn_rbl_normalize_text_tag( $fields['text_field_tag'] ?? '', 'h2' ) : 'h2';
$intro       = isset( $fields['content'] ) ? (string) $fields['content'] : '';

$post_type_choices = function_exists( 'mrn_base_stack_get_content_list_post_type_choices' ) ? mrn_base_stack_get_content_list_post_type_choices() : array( 'post' => 'Posts' );
$post_type         = isset( $fields['list_post_type'] ) ? sanitize_key( (string) $fields['list_post_type'] ) : 'post';
if ( ! isset( $post_type_choices[ $post_type ] ) ) {
	$post_type = isset( $post_type_choices['post'] ) ? 'post' : (string) array_key_first( $post_type_choices );
}

$list_style = isset( $fields['list_style'] ) ? sanitize_key( (string) $fields['list_style'] ) : 'unordered';
if ( ! in_array( $list_style, array( 'unordered', 'ordered' ), true ) ) {
	$list_style = 'unordered';
}

$display_mode = function_exists( 'mrn_base_stack_normalize_content_list_display_mode' )
	? mrn_base_stack_normalize_content_list_display_mode( $fields['display_mode'] ?? 'standard' )
	: 'standard';

$orderby_choices = function_exists( 'mrn_base_stack_get_content_list_orderby_choices' ) ? mrn_base_stack_get_content_list_orderby_choices() : array( 'date' => 'Publish Date' );
$orderby         = isset( $fields['orderby'] ) ? sanitize_key( (string) $fields['orderby'] ) : 'date';
if ( ! isset( $orderby_choices[ $orderby ] ) ) {
	$orderby = 'date';
}

$order = isset( $fields['order'] ) ? strtoupper( sanitize_key( (string) $fields['order'] ) ) : 'DESC';
if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
	$order = 'DESC';
}

$posts_per_page      = max( 1, absint( $fields['posts_per_page'] ?? 10 ) );
$offset              = absint( $fields['offset'] ?? 0 );
$tax_query           = function_exists( 'mrn_base_stack_get_content_list_tax_query' )
	? mrn_base_stack_get_content_list_tax_query( $fields, $host_post_id, $post_type )
	: array();
$show_pagination     = ! empty( $fields['enable_pagination'] );
$show_featured_image = ! empty( $fields['show_featured_image'] );
$show_publish_date   = ! empty( $fields['show_publish_date'] );
$show_excerpt        = ! empty( $fields['show_excerpt'] );
$excerpt_length      = max( 5, absint( $fields['excerpt_length'] ?? 24 ) );
$show_read_more      = ! empty( $fields['show_read_more'] );
$read_more_label     = isset( $fields['read_more_label'] ) ? trim( (string) $fields['read_more_label'] ) : 'Read More';
$empty_message       = isset( $fields['empty_message'] ) ? trim( (string) $fields['empty_message'] ) : 'No content found.';
$hide_when_empty     = ! empty( $fields['hide_when_empty'] );
$background_color    = isset( $fields['background_color'] ) ? trim( (string) $fields['background_color'] ) : '';
$bottom_accent       = ! empty( $fields['bottom_accent'] );
$accent_slug         = isset( $fields['bottom_accent_style'] ) ? (string) $fields['bottom_accent_style'] : '';

$current_page = $show_pagination && function_exists( 'mrn_base_stack_get_content_list_current_page' )
	? mrn_base_stack_get_content_list_current_page( $block_id > 0 ? $block_id : $host_post_id, $host_index )
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

$classes = array(
	'mrn-reusable-block',
	'mrn-reusable-block--content-lists',
	'mrn-content-list-row--' . $list_style,
	'mrn-reusable-block--content-lists-display-' . $display_mode,
);

$accent_contract = function_exists( 'mrn_site_styles_get_bottom_accent_contract' )
	? mrn_site_styles_get_bottom_accent_contract( $bottom_accent, $accent_slug )
	: array(
		'classes'    => $bottom_accent ? array( 'has-bottom-accent' ) : array(),
		'attributes' => array(),
	);

if ( isset( $accent_contract['classes'] ) && is_array( $accent_contract['classes'] ) ) {
	$classes = array_merge( $classes, $accent_contract['classes'] );
}

$inline_styles = array();
if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$inline_styles[] = '--mrn-content-list-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$list_tag        = 'ordered' === $list_style ? 'ol' : 'ul';
$pagination_html = '';
$row_anchor_id   = 'mrn-content-list-row-' . absint( $block_id > 0 ? $block_id : $host_post_id ) . '-' . absint( $host_index );

if ( $show_pagination && $query->max_num_pages > 1 ) {
	$page_arg     = function_exists( 'mrn_base_stack_get_content_list_pagination_query_arg' ) ? mrn_base_stack_get_content_list_pagination_query_arg( $block_id > 0 ? $block_id : $host_post_id, $host_index ) : 'mrn_list_page';
	$base_url     = $host_post_id > 0 ? get_permalink( $host_post_id ) : '';
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
	$pagination_base .= '#' . $row_anchor_id;
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
<section
	id="<?php echo esc_attr( $row_anchor_id ); ?>"
	class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	data-block-id="<?php echo esc_attr( (string) $block_id ); ?>"
	data-block-slug="<?php echo esc_attr( $block_slug ); ?>"
	<?php if ( isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ) : ?>
		<?php foreach ( $accent_contract['attributes'] as $attribute_name => $attribute_value ) : ?>
			<?php if ( '' !== $attribute_name && '' !== $attribute_value ) : ?>
				<?php echo ' ' . esc_attr( $attribute_name ) . '="' . esc_attr( $attribute_value ) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		<?php endforeach; ?>
	<?php endif; ?>
	<?php if ( $inline_styles !== array() ) : ?>
		style="<?php echo esc_attr( implode( '; ', $inline_styles ) ); ?>"
	<?php endif; ?>
>
	<div class="mrn-reusable-block__inner mrn-reusable-block__inner--content-lists">
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
					$item_post = get_post();
					?>
					<?php
					if ( $item_post instanceof WP_Post && function_exists( 'mrn_base_stack_render_content_list_item' ) ) {
						echo mrn_base_stack_render_content_list_item(
							$item_post,
							array(
								'display_mode'        => $display_mode,
								'show_featured_image' => $show_featured_image,
								'show_publish_date'   => $show_publish_date,
								'show_excerpt'        => $show_excerpt,
								'excerpt_length'      => $excerpt_length,
								'show_read_more'      => $show_read_more,
								'read_more_label'     => $read_more_label,
							)
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
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
</section>
<?php
wp_reset_postdata();

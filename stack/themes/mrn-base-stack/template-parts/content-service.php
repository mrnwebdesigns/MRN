<?php
/**
 * Template part for displaying service entries.
 *
 * @package mrn-base-stack
 */

$mrn_post_id       = get_the_ID();
$mrn_is_singular   = is_singular( 'service' );
$mrn_service       = function_exists( 'mrn_base_stack_get_service_data' ) ? mrn_base_stack_get_service_data( $mrn_post_id ) : array();
$mrn_label         = isset( $mrn_service['label'] ) ? trim( (string) $mrn_service['label'] ) : '';
$mrn_heading       = isset( $mrn_service['heading'] ) ? trim( (string) $mrn_service['heading'] ) : '';
$mrn_subheading    = isset( $mrn_service['subheading'] ) ? trim( (string) $mrn_service['subheading'] ) : '';
$mrn_summary       = isset( $mrn_service['summary'] ) ? (string) $mrn_service['summary'] : '';
$mrn_categories    = get_the_term_list( $mrn_post_id, 'category', '', esc_html__( ', ', 'mrn-base-stack' ) );
$mrn_tags          = get_the_term_list( $mrn_post_id, 'post_tag', '', esc_html_x( ', ', 'list item separator', 'mrn-base-stack' ) );
$mrn_detail_labels = array(
	'area'         => __( 'Service Area', 'mrn-base-stack' ),
	'location'     => __( 'Location', 'mrn-base-stack' ),
	'pricing_note' => __( 'Pricing', 'mrn-base-stack' ),
);

if ( ! empty( $mrn_service['location_record'] ) ) {
	$mrn_location_title = get_the_title( (int) $mrn_service['location_record'] );
	if ( '' !== trim( (string) $mrn_location_title ) ) {
		$mrn_service['location'] = $mrn_location_title;
	}
}

$mrn_primary_link = isset( $mrn_service['primary_link'] ) && is_array( $mrn_service['primary_link'] )
	? $mrn_service['primary_link']
	: array(
		'url'    => '',
		'title'  => '',
		'target' => '',
	);
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'mrn-service' ); ?>>
	<?php if ( $mrn_is_singular ) : ?>
		<?php
		$mrn_has_hero         = function_exists( 'mrn_base_stack_render_hero_builder' ) ? mrn_base_stack_render_hero_builder( $mrn_post_id ) : false;
		$mrn_sidebar_settings = function_exists( 'mrn_base_stack_get_singular_sidebar_settings' ) ? mrn_base_stack_get_singular_sidebar_settings( $mrn_post_id ) : array( 'layout' => 'none' );
		$mrn_sidebar_markup   = function_exists( 'mrn_base_stack_get_singular_sidebar_markup' ) ? mrn_base_stack_get_singular_sidebar_markup( $mrn_post_id ) : '';
		$mrn_has_sidebar      = 'none' !== ( $mrn_sidebar_settings['layout'] ?? 'none' ) && '' !== $mrn_sidebar_markup;
		$mrn_shell_classes    = array(
			'mrn-singular-shell',
			'mrn-singular-shell--service',
		);

		if ( $mrn_has_sidebar ) {
			$mrn_shell_classes[] = 'mrn-singular-shell--has-sidebar';
			$mrn_shell_classes[] = 'mrn-singular-shell--sidebar-' . sanitize_html_class( $mrn_sidebar_settings['layout'] );
		}

		if ( function_exists( 'mrn_base_stack_render_singular_breadcrumbs' ) ) {
			mrn_base_stack_render_singular_breadcrumbs( $mrn_post_id );
		}
		?>

		<div class="<?php echo esc_attr( implode( ' ', $mrn_shell_classes ) ); ?>" data-mrn-layout-slot="content-shell">
			<div class="mrn-singular-shell__main">
				<?php if ( ! $mrn_has_hero ) : ?>
					<header class="entry-header">
						<?php if ( '' !== $mrn_label ) : ?>
							<p class="mrn-entry-label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $mrn_label ) : esc_html( $mrn_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<?php endif; ?>

						<?php if ( '' !== $mrn_heading ) : ?>
							<h1 class="entry-title"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $mrn_heading ) : esc_html( $mrn_heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h1>
						<?php else : ?>
							<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
						<?php endif; ?>

						<?php if ( '' !== $mrn_subheading ) : ?>
							<p class="entry-summary"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $mrn_subheading ) : esc_html( $mrn_subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<?php endif; ?>
					</header>
				<?php endif; ?>

				<?php if ( $mrn_categories || $mrn_tags ) : ?>
					<div class="entry-meta">
						<?php if ( $mrn_categories ) : ?>
							<span class="cat-links">
								<?php
								printf(
									/* translators: 1: list of categories. */
									esc_html__( 'Posted in %1$s', 'mrn-base-stack' ),
									$mrn_categories // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								);
								?>
							</span>
						<?php endif; ?>

						<?php if ( $mrn_tags ) : ?>
							<span class="tags-links">
								<?php
								printf(
									/* translators: 1: list of tags. */
									esc_html__( 'Tagged %1$s', 'mrn-base-stack' ),
									$mrn_tags // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								);
								?>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( has_post_thumbnail() ) : ?>
					<div class="post-thumbnail">
						<?php the_post_thumbnail( 'large' ); ?>
					</div>
				<?php endif; ?>

				<div class="entry-content entry-content--service">
					<?php if ( '' !== trim( wp_strip_all_tags( $mrn_summary ) ) ) : ?>
						<section class="mrn-service-section mrn-service-section--summary">
							<h2><?php esc_html_e( 'Service Overview', 'mrn-base-stack' ); ?></h2>
							<?php echo wp_kses_post( $mrn_summary ); ?>
						</section>
					<?php endif; ?>

					<?php if ( ! empty( array_filter( array_intersect_key( $mrn_service, $mrn_detail_labels ) ) ) ) : ?>
						<section class="mrn-service-section mrn-service-section--details" aria-labelledby="service-details-<?php echo esc_attr( (string) $mrn_post_id ); ?>">
							<h2 id="service-details-<?php echo esc_attr( (string) $mrn_post_id ); ?>">
								<?php esc_html_e( 'Service Details', 'mrn-base-stack' ); ?>
							</h2>
							<dl class="mrn-service-details">
								<?php foreach ( $mrn_detail_labels as $mrn_detail_key => $mrn_detail_label ) : ?>
									<?php
									$mrn_detail_value = isset( $mrn_service[ $mrn_detail_key ] ) ? trim( (string) $mrn_service[ $mrn_detail_key ] ) : '';

									if ( '' === $mrn_detail_value ) {
										continue;
									}
									?>
									<div class="mrn-service-details__item">
										<dt><?php echo esc_html( $mrn_detail_label ); ?></dt>
										<dd><?php echo esc_html( $mrn_detail_value ); ?></dd>
									</div>
								<?php endforeach; ?>
							</dl>
						</section>
					<?php endif; ?>

					<?php
					$mrn_service_sections = array(
						'audience' => __( 'Who This Is For', 'mrn-base-stack' ),
						'features' => __( 'What\'s Included', 'mrn-base-stack' ),
						'process'  => __( 'Process / Approach', 'mrn-base-stack' ),
						'benefits' => __( 'Benefits', 'mrn-base-stack' ),
					);
					?>

					<?php foreach ( $mrn_service_sections as $mrn_section_key => $mrn_section_heading ) : ?>
						<?php $mrn_section_content = isset( $mrn_service[ $mrn_section_key ] ) ? (string) $mrn_service[ $mrn_section_key ] : ''; ?>
						<?php if ( '' !== trim( wp_strip_all_tags( $mrn_section_content ) ) ) : ?>
							<section class="mrn-service-section mrn-service-section--<?php echo esc_attr( sanitize_html_class( $mrn_section_key ) ); ?>">
								<h2><?php echo esc_html( $mrn_section_heading ); ?></h2>
								<?php echo wp_kses_post( $mrn_section_content ); ?>
							</section>
						<?php endif; ?>
					<?php endforeach; ?>

					<?php if ( ! empty( $mrn_primary_link['url'] ) ) : ?>
						<p class="mrn-service-cta">
							<a class="mrn-ui__button" href="<?php echo esc_url( (string) $mrn_primary_link['url'] ); ?>"<?php echo ! empty( $mrn_primary_link['target'] ) ? ' target="' . esc_attr( (string) $mrn_primary_link['target'] ) . '" rel="noopener"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
								<?php echo esc_html( (string) $mrn_primary_link['title'] ); ?>
							</a>
						</p>
					<?php endif; ?>

					<?php
					if ( function_exists( 'mrn_base_stack_render_content_builder' ) ) {
						mrn_base_stack_render_content_builder( $mrn_post_id );
					}

					if ( function_exists( 'mrn_base_stack_render_after_content_builder' ) ) {
						mrn_base_stack_render_after_content_builder( $mrn_post_id );
					}
					?>
				</div>
			</div>

			<?php if ( $mrn_has_sidebar ) : ?>
				<div class="mrn-singular-shell__sidebar">
					<?php echo $mrn_sidebar_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="mrn-shell-container mrn-shell-container--content">
			<header class="entry-header">
				<?php if ( '' !== $mrn_label ) : ?>
					<p class="mrn-entry-label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $mrn_label ) : esc_html( $mrn_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php endif; ?>

				<?php if ( '' !== $mrn_heading ) : ?>
					<h2 class="entry-title">
						<a href="<?php echo esc_url( get_permalink() ); ?>" rel="bookmark">
							<?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $mrn_heading ) : esc_html( $mrn_heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</a>
					</h2>
				<?php else : ?>
					<?php the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' ); ?>
				<?php endif; ?>

				<?php if ( '' !== $mrn_subheading ) : ?>
					<p class="entry-summary"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $mrn_subheading ) : esc_html( $mrn_subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php endif; ?>
			</header>

			<?php
			$mrn_archive_details = array_filter(
				array(
					isset( $mrn_service['area'] ) ? trim( (string) $mrn_service['area'] ) : '',
					isset( $mrn_service['location'] ) ? trim( (string) $mrn_service['location'] ) : '',
					isset( $mrn_service['pricing_note'] ) ? trim( (string) $mrn_service['pricing_note'] ) : '',
				)
			);
			?>

			<?php if ( ! empty( $mrn_archive_details ) ) : ?>
				<p class="entry-meta"><?php echo esc_html( implode( ' | ', $mrn_archive_details ) ); ?></p>
			<?php endif; ?>

			<?php if ( has_post_thumbnail() ) : ?>
				<a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
					<?php the_post_thumbnail( 'large' ); ?>
				</a>
			<?php endif; ?>

			<?php
			$mrn_archive_text = function_exists( 'mrn_base_stack_get_service_excerpt' ) ? mrn_base_stack_get_service_excerpt( $mrn_post_id ) : '';
			if ( '' !== $mrn_archive_text ) :
				?>
				<div class="entry-summary">
					<p><?php echo esc_html( $mrn_archive_text ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</article>

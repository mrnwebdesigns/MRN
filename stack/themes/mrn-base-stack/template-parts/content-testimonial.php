<?php
/**
 * Template part for displaying testimonial entries.
 *
 * @package mrn-base-stack
 */

$mrn_post_id      = get_the_ID();
$mrn_is_singular  = is_singular( 'testimonial' );
$mrn_testimonial  = function_exists( 'mrn_base_stack_get_testimonial_data' ) ? mrn_base_stack_get_testimonial_data( $mrn_post_id ) : array();
$mrn_label        = isset( $mrn_testimonial['label'] ) ? trim( (string) $mrn_testimonial['label'] ) : '';
$mrn_heading      = isset( $mrn_testimonial['heading'] ) ? trim( (string) $mrn_testimonial['heading'] ) : '';
$mrn_subheading   = isset( $mrn_testimonial['subheading'] ) ? trim( (string) $mrn_testimonial['subheading'] ) : '';
$mrn_name         = isset( $mrn_testimonial['name'] ) ? (string) $mrn_testimonial['name'] : get_the_title();
$mrn_company      = isset( $mrn_testimonial['company'] ) ? trim( (string) $mrn_testimonial['company'] ) : '';
$mrn_position     = isset( $mrn_testimonial['position'] ) ? trim( (string) $mrn_testimonial['position'] ) : '';
$mrn_website_url  = isset( $mrn_testimonial['website_url'] ) ? trim( (string) $mrn_testimonial['website_url'] ) : '';
$mrn_content      = isset( $mrn_testimonial['content'] ) ? (string) $mrn_testimonial['content'] : '';
$mrn_image_logo   = isset( $mrn_testimonial['image_logo'] ) && is_array( $mrn_testimonial['image_logo'] ) ? $mrn_testimonial['image_logo'] : null;
$mrn_archive_text = function_exists( 'mrn_base_stack_get_testimonial_excerpt' ) ? mrn_base_stack_get_testimonial_excerpt( $mrn_post_id ) : '';
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php if ( $mrn_is_singular ) : ?>
		<?php
		$mrn_has_hero         = function_exists( 'mrn_base_stack_render_hero_builder' ) ? mrn_base_stack_render_hero_builder( $mrn_post_id ) : false;
		$mrn_sidebar_settings = function_exists( 'mrn_base_stack_get_singular_sidebar_settings' ) ? mrn_base_stack_get_singular_sidebar_settings( $mrn_post_id ) : array( 'layout' => 'none' );
		$mrn_sidebar_markup   = function_exists( 'mrn_base_stack_get_singular_sidebar_markup' ) ? mrn_base_stack_get_singular_sidebar_markup( $mrn_post_id ) : '';
		$mrn_has_sidebar      = 'none' !== ( $mrn_sidebar_settings['layout'] ?? 'none' ) && '' !== $mrn_sidebar_markup;
		$mrn_shell_classes    = array(
			'mrn-singular-shell',
			'mrn-singular-shell--testimonial',
		);

		if ( $mrn_has_sidebar ) {
			$mrn_shell_classes[] = 'mrn-singular-shell--has-sidebar';
			$mrn_shell_classes[] = 'mrn-singular-shell--sidebar-' . sanitize_html_class( $mrn_sidebar_settings['layout'] );
		}
		?>

		<div class="<?php echo esc_attr( implode( ' ', $mrn_shell_classes ) ); ?>">
			<div class="mrn-singular-shell__main">
				<?php if ( ! $mrn_has_hero ) : ?>
					<header class="entry-header">
						<?php if ( '' !== $mrn_label ) : ?>
							<p class="mrn-entry-label"><?php echo esc_html( $mrn_label ); ?></p>
						<?php endif; ?>

						<?php if ( '' !== $mrn_heading ) : ?>
							<h1 class="entry-title"><?php echo esc_html( $mrn_heading ); ?></h1>
						<?php else : ?>
							<h1 class="entry-title"><?php echo esc_html( $mrn_name ); ?></h1>
						<?php endif; ?>

						<?php if ( '' !== $mrn_subheading ) : ?>
							<p class="entry-summary"><?php echo esc_html( $mrn_subheading ); ?></p>
						<?php endif; ?>

						<?php if ( '' !== $mrn_position ) : ?>
							<p class="entry-meta"><?php echo esc_html( $mrn_position ); ?></p>
						<?php endif; ?>

						<?php if ( '' !== $mrn_company ) : ?>
							<p class="entry-meta"><?php echo esc_html( $mrn_company ); ?></p>
						<?php endif; ?>

						<?php if ( '' !== $mrn_website_url ) : ?>
							<p class="entry-meta">
								<a href="<?php echo esc_url( $mrn_website_url ); ?>">
									<?php echo esc_html( $mrn_website_url ); ?>
								</a>
							</p>
						<?php endif; ?>
					</header>
				<?php endif; ?>

				<div class="entry-content entry-content--builder">
					<?php if ( $mrn_image_logo && ! empty( $mrn_image_logo['ID'] ) ) : ?>
						<div class="post-thumbnail">
							<?php echo wp_get_attachment_image( (int) $mrn_image_logo['ID'], 'large' ); ?>
						</div>
					<?php endif; ?>

					<?php if ( '' !== trim( wp_strip_all_tags( $mrn_content ) ) ) : ?>
						<div class="mrn-testimonial-body">
							<?php echo wp_kses_post( $mrn_content ); ?>
						</div>
					<?php endif; ?>

					<?php
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
					<p class="mrn-entry-label"><?php echo esc_html( $mrn_label ); ?></p>
				<?php endif; ?>

				<?php if ( '' !== $mrn_heading ) : ?>
					<h2 class="entry-title">
						<a href="<?php echo esc_url( get_permalink() ); ?>" rel="bookmark">
							<?php echo esc_html( $mrn_heading ); ?>
						</a>
					</h2>
				<?php else : ?>
					<h2 class="entry-title">
						<a href="<?php echo esc_url( get_permalink() ); ?>" rel="bookmark">
							<?php echo esc_html( $mrn_name ); ?>
						</a>
					</h2>
				<?php endif; ?>

				<?php if ( '' !== $mrn_subheading ) : ?>
					<p class="entry-summary"><?php echo esc_html( $mrn_subheading ); ?></p>
				<?php endif; ?>

				<?php if ( '' !== $mrn_position ) : ?>
					<p class="entry-meta"><?php echo esc_html( $mrn_position ); ?></p>
				<?php endif; ?>

				<?php if ( '' !== $mrn_company ) : ?>
					<p class="entry-meta"><?php echo esc_html( $mrn_company ); ?></p>
				<?php endif; ?>

				<?php if ( '' !== $mrn_website_url ) : ?>
					<p class="entry-meta">
						<a href="<?php echo esc_url( $mrn_website_url ); ?>" rel="bookmark">
							<?php echo esc_html( $mrn_website_url ); ?>
						</a>
					</p>
				<?php endif; ?>
			</header>

			<?php if ( $mrn_image_logo && ! empty( $mrn_image_logo['ID'] ) ) : ?>
				<a class="post-thumbnail" href="<?php echo esc_url( get_permalink() ); ?>" aria-hidden="true" tabindex="-1">
					<?php echo wp_get_attachment_image( (int) $mrn_image_logo['ID'], 'medium' ); ?>
				</a>
			<?php endif; ?>

			<?php if ( '' !== $mrn_archive_text ) : ?>
				<div class="entry-summary">
					<p><?php echo esc_html( $mrn_archive_text ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</article>

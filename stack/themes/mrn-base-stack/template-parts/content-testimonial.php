<?php
/**
 * Template part for displaying testimonial entries.
 *
 * @package mrn-base-stack
 */

$mrn_post_id      = get_the_ID();
$mrn_is_singular  = is_singular( 'testimonial' );
$mrn_testimonial  = function_exists( 'mrn_base_stack_get_testimonial_data' ) ? mrn_base_stack_get_testimonial_data( $mrn_post_id ) : array();
$mrn_name         = isset( $mrn_testimonial['name'] ) ? (string) $mrn_testimonial['name'] : get_the_title();
$mrn_company      = isset( $mrn_testimonial['company'] ) ? trim( (string) $mrn_testimonial['company'] ) : '';
$mrn_position     = isset( $mrn_testimonial['position'] ) ? trim( (string) $mrn_testimonial['position'] ) : '';
$mrn_website_url  = isset( $mrn_testimonial['website_url'] ) ? trim( (string) $mrn_testimonial['website_url'] ) : '';
$mrn_content      = isset( $mrn_testimonial['content'] ) ? (string) $mrn_testimonial['content'] : '';
$mrn_image_logo   = isset( $mrn_testimonial['image_logo'] ) && is_array( $mrn_testimonial['image_logo'] ) ? $mrn_testimonial['image_logo'] : null;
$mrn_archive_text = function_exists( 'mrn_base_stack_get_testimonial_excerpt' ) ? mrn_base_stack_get_testimonial_excerpt( $mrn_post_id ) : '';
$mrn_meta_parts   = array_filter( array( $mrn_position, $mrn_company ) );
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<div class="mrn-shell-container mrn-shell-container--content">
		<?php if ( $mrn_is_singular ) : ?>
			<header class="entry-header">
				<h1 class="entry-title"><?php echo esc_html( $mrn_name ); ?></h1>

				<?php if ( ! empty( $mrn_meta_parts ) ) : ?>
					<p class="entry-meta">
						<?php echo esc_html( implode( ', ', $mrn_meta_parts ) ); ?>
					</p>
				<?php endif; ?>

				<?php if ( '' !== $mrn_website_url ) : ?>
					<p class="entry-meta">
						<a href="<?php echo esc_url( $mrn_website_url ); ?>">
							<?php echo esc_html( $mrn_website_url ); ?>
						</a>
					</p>
				<?php endif; ?>
			</header>

			<?php if ( $mrn_image_logo && ! empty( $mrn_image_logo['ID'] ) ) : ?>
				<div class="post-thumbnail">
					<?php echo wp_get_attachment_image( (int) $mrn_image_logo['ID'], 'large' ); ?>
				</div>
			<?php endif; ?>

			<div class="entry-content">
				<?php if ( '' !== trim( wp_strip_all_tags( $mrn_content ) ) ) : ?>
					<?php echo wp_kses_post( $mrn_content ); ?>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<header class="entry-header">
				<h2 class="entry-title">
					<a href="<?php echo esc_url( get_permalink() ); ?>" rel="bookmark">
						<?php echo esc_html( $mrn_name ); ?>
					</a>
				</h2>

				<?php if ( ! empty( $mrn_meta_parts ) ) : ?>
					<p class="entry-meta">
						<?php echo esc_html( implode( ', ', $mrn_meta_parts ) ); ?>
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
		<?php endif; ?>
	</div>
</article>

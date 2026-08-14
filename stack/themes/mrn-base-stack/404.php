<?php
/**
 * The template for displaying 404 pages (not found).
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package mrn-base-stack
 */

get_header();

$mrn_not_found_options = function_exists( 'mrn_base_stack_get_not_found_options' )
	? mrn_base_stack_get_not_found_options()
	: array();

$mrn_not_found_title      = trim( (string) ( $mrn_not_found_options['title'] ?? '' ) );
$mrn_not_found_eyebrow    = trim( (string) ( $mrn_not_found_options['eyebrow'] ?? '' ) );
$mrn_not_found_message    = (string) ( $mrn_not_found_options['message'] ?? '' );
$mrn_not_found_home_label = trim( (string) ( $mrn_not_found_options['home_label'] ?? '' ) );
$mrn_not_found_links      = is_array( $mrn_not_found_options['helpful_links'] ?? null ) ? $mrn_not_found_options['helpful_links'] : array();
$mrn_has_primary_content  = '' !== $mrn_not_found_title || '' !== $mrn_not_found_eyebrow || '' !== trim( wp_strip_all_tags( $mrn_not_found_message ) ) || '' !== $mrn_not_found_home_label;
$mrn_has_recovery_content = ! empty( $mrn_not_found_options['show_search'] ) || ! empty( $mrn_not_found_links );
$mrn_show_not_found_panel = $mrn_has_primary_content || $mrn_has_recovery_content;
?>

<main id="primary" class="site-main site-main--404">
	<section class="mrn-not-found"<?php echo '' !== $mrn_not_found_title ? ' aria-labelledby="mrn-not-found-title"' : ' aria-label="' . esc_attr__( 'Page not found', 'mrn-base-stack' ) . '"'; ?>>
		<?php if ( $mrn_show_not_found_panel ) : ?>
			<div class="mrn-not-found__inner">
			<?php if ( $mrn_has_primary_content ) : ?>
			<div class="mrn-not-found__visual" aria-hidden="true">
				<span class="mrn-not-found__code">404</span>
				<span class="mrn-not-found__orbit mrn-not-found__orbit--one"></span>
				<span class="mrn-not-found__orbit mrn-not-found__orbit--two"></span>
			</div>

			<div class="mrn-not-found__content">
				<?php if ( '' !== $mrn_not_found_eyebrow ) : ?>
					<p class="mrn-not-found__eyebrow"><?php echo esc_html( $mrn_not_found_eyebrow ); ?></p>
				<?php endif; ?>

				<?php if ( '' !== $mrn_not_found_title ) : ?>
					<h1 id="mrn-not-found-title" class="mrn-not-found__title"><?php echo esc_html( $mrn_not_found_title ); ?></h1>
				<?php endif; ?>

				<?php if ( '' !== trim( wp_strip_all_tags( $mrn_not_found_message ) ) ) : ?>
					<div class="mrn-not-found__message">
						<?php echo wp_kses_post( $mrn_not_found_message ); ?>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $mrn_not_found_home_label ) : ?>
					<a class="mrn-not-found__home mrn-ui__link--button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $mrn_not_found_home_label ); ?></a>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<?php if ( $mrn_has_recovery_content ) : ?>
				<div class="mrn-not-found__recovery">
					<?php if ( ! empty( $mrn_not_found_options['show_search'] ) ) : ?>
						<div class="mrn-not-found__search">
							<?php if ( ! empty( $mrn_not_found_options['search_heading'] ) ) : ?>
								<h2 class="mrn-not-found__recovery-title"><?php echo esc_html( $mrn_not_found_options['search_heading'] ); ?></h2>
							<?php endif; ?>
							<div class="mrn-not-found__search-form">
								<?php
								if ( function_exists( 'mrn_base_stack_render_search_form_markup' ) ) {
									mrn_base_stack_render_search_form_markup();
								}
								?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $mrn_not_found_links ) ) : ?>
						<nav class="mrn-not-found__links"<?php echo ! empty( $mrn_not_found_options['links_heading'] ) ? ' aria-labelledby="mrn-not-found-links-title"' : ' aria-label="' . esc_attr__( 'Helpful links', 'mrn-base-stack' ) . '"'; ?>>
							<?php if ( ! empty( $mrn_not_found_options['links_heading'] ) ) : ?>
								<h2 id="mrn-not-found-links-title" class="mrn-not-found__recovery-title"><?php echo esc_html( $mrn_not_found_options['links_heading'] ); ?></h2>
							<?php endif; ?>
							<ul class="mrn-not-found__link-list">
								<?php foreach ( $mrn_not_found_links as $mrn_helpful_link_row ) : ?>
									<?php
									$mrn_helpful_link = isset( $mrn_helpful_link_row['link'] ) && is_array( $mrn_helpful_link_row['link'] ) ? $mrn_helpful_link_row['link'] : array();
									$mrn_link_url     = isset( $mrn_helpful_link['url'] ) ? (string) $mrn_helpful_link['url'] : '';
									$mrn_link_title   = isset( $mrn_helpful_link['title'] ) ? (string) $mrn_helpful_link['title'] : '';
									$mrn_link_target  = isset( $mrn_helpful_link['target'] ) && '_blank' === $mrn_helpful_link['target'] ? '_blank' : '_self';

									if ( '' === $mrn_link_url || '' === $mrn_link_title ) {
										continue;
									}
									?>
									<li>
										<a href="<?php echo esc_url( $mrn_link_url ); ?>" target="<?php echo esc_attr( $mrn_link_target ); ?>"<?php echo '_blank' === $mrn_link_target ? ' rel="noopener noreferrer"' : ''; ?>>
											<?php echo esc_html( $mrn_link_title ); ?><span aria-hidden="true"> &rarr;</span>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</nav>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php
		if ( function_exists( 'mrn_base_stack_render_not_found_content' ) ) {
			mrn_base_stack_render_not_found_content( $mrn_not_found_options['content_rows'] ?? array() );
		}
		?>
	</section>
</main><!-- #main -->

<?php
get_footer();

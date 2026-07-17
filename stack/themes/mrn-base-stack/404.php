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
?>

<main id="primary" class="site-main site-main--404">
	<section class="mrn-not-found" aria-labelledby="mrn-not-found-title">
		<div class="mrn-not-found__inner">
			<div class="mrn-not-found__visual" aria-hidden="true">
				<span class="mrn-not-found__code">404</span>
				<span class="mrn-not-found__orbit mrn-not-found__orbit--one"></span>
				<span class="mrn-not-found__orbit mrn-not-found__orbit--two"></span>
			</div>

			<div class="mrn-not-found__content">
				<?php if ( ! empty( $mrn_not_found_options['eyebrow'] ) ) : ?>
					<p class="mrn-not-found__eyebrow"><?php echo esc_html( $mrn_not_found_options['eyebrow'] ); ?></p>
				<?php endif; ?>

				<h1 id="mrn-not-found-title" class="mrn-not-found__title">
					<?php echo esc_html( $mrn_not_found_options['title'] ?? __( 'This page wandered off.', 'mrn-base-stack' ) ); ?>
				</h1>

				<?php if ( ! empty( $mrn_not_found_options['message'] ) ) : ?>
					<div class="mrn-not-found__message">
						<?php echo wp_kses_post( $mrn_not_found_options['message'] ); ?>
					</div>
				<?php endif; ?>

				<a class="mrn-not-found__home mrn-ui__link--button" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php echo esc_html( $mrn_not_found_options['home_label'] ?? __( 'Take me home', 'mrn-base-stack' ) ); ?>
				</a>
			</div>

			<?php if ( ! empty( $mrn_not_found_options['show_search'] ) || ! empty( $mrn_not_found_options['helpful_links'] ) ) : ?>
				<div class="mrn-not-found__recovery">
					<?php if ( ! empty( $mrn_not_found_options['show_search'] ) ) : ?>
						<div class="mrn-not-found__search">
							<h2 class="mrn-not-found__recovery-title"><?php echo esc_html( $mrn_not_found_options['search_heading'] ?? __( 'Search for what you need', 'mrn-base-stack' ) ); ?></h2>
							<form role="search" method="get" class="mrn-not-found__search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
								<label class="screen-reader-text" for="mrn-404-search-input"><?php esc_html_e( 'Search this site', 'mrn-base-stack' ); ?></label>
								<input id="mrn-404-search-input" class="mrn-not-found__search-input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'What were you looking for?', 'mrn-base-stack' ); ?>">
								<button class="mrn-not-found__search-button" type="submit"><?php esc_html_e( 'Search', 'mrn-base-stack' ); ?></button>
							</form>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $mrn_not_found_options['helpful_links'] ) ) : ?>
						<nav class="mrn-not-found__links" aria-labelledby="mrn-not-found-links-title">
							<h2 id="mrn-not-found-links-title" class="mrn-not-found__recovery-title"><?php echo esc_html( $mrn_not_found_options['links_heading'] ?? __( 'Or try one of these', 'mrn-base-stack' ) ); ?></h2>
							<ul class="mrn-not-found__link-list">
								<?php foreach ( $mrn_not_found_options['helpful_links'] as $mrn_helpful_link_row ) : ?>
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

		<?php
		if ( function_exists( 'mrn_base_stack_render_not_found_content' ) ) {
			mrn_base_stack_render_not_found_content( $mrn_not_found_options['content_rows'] ?? array() );
		}
		?>
	</section>
</main><!-- #main -->

<?php
get_footer();

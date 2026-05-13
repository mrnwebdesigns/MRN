<?php
/**
 * Template Name: Rollout Starter (Child)
 * Template Post Type: page
 * Description: Child-theme starter template for site rollout. Preserves parent shell and builder contracts while providing a safe breadcrumb insertion point.
 *
 * @package mrn-base-stack-child
 */

get_header();
?>

<main id="primary" class="site-main">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : ?>
			<?php
			the_post();

			$mrn_post_id  = get_the_ID();
			$mrn_has_hero = function_exists( 'mrn_base_stack_render_hero_builder' ) ? mrn_base_stack_render_hero_builder( $mrn_post_id ) : false;
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<div class="mrn-singular-shell mrn-singular-shell--page mrn-singular-shell--rollout-starter">
					<div class="mrn-singular-shell__main">
						<?php if ( ! $mrn_has_hero ) : ?>
							<header class="entry-header">
								<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
							</header>
						<?php endif; ?>

						<?php
						if ( function_exists( 'mrn_render_breadcrumbs' ) ) {
							mrn_render_breadcrumbs(
								array(
									'placement' => 'singular_header',
								)
							);
						}
						?>

						<?php if ( function_exists( 'mrn_base_stack_post_thumbnail' ) ) : ?>
							<?php mrn_base_stack_post_thumbnail(); ?>
						<?php endif; ?>

						<div class="entry-content entry-content--builder">
							<?php
							if ( function_exists( 'mrn_base_stack_render_content_builder' ) ) {
								mrn_base_stack_render_content_builder( $mrn_post_id );
							} else {
								the_content();
							}

							if ( function_exists( 'mrn_base_stack_render_after_content_builder' ) ) {
								mrn_base_stack_render_after_content_builder( $mrn_post_id );
							}

							wp_link_pages(
								array(
									'before' => '<div class="mrn-shell-container mrn-shell-container--content"><div class="page-links">' . esc_html__( 'Pages:', 'mrn-base-stack' ),
									'after'  => '</div></div>',
								)
							);
							?>
						</div>
					</div>
				</div>
			</article>
		<?php endwhile; ?>
	<?php endif; ?>
</main>

<?php
get_footer();

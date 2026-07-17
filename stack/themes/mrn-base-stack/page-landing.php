<?php
/**
 * Template Name: Landing Page
 * Template Post Type: page
 *
 * The landing page currently follows the standard page rendering contract.
 * Keep this template as the dedicated extension point for future landing-page
 * layout and behavior.
 *
 * @package mrn-base-stack
 */

get_header();
?>

	<main id="primary" class="site-main site-main--landing-page">

		<?php
		while ( have_posts() ) :
			the_post();

			get_template_part( 'template-parts/content', 'page' );

		endwhile; // End of the loop.
		?>

	</main><!-- #main -->

<?php
get_footer();

<?php
/**
 * Template Name: Sidebar Right
 * Template Post Type: page
 *
 * @package mrn-base-stack
 */

get_header();
?>

	<main id="primary" class="site-main">

		<?php
		while ( have_posts() ) :
			the_post();

			get_template_part( 'template-parts/content', 'page_with_sidebars' );

		endwhile;
		?>

	</main><!-- #main -->

<?php
get_footer();

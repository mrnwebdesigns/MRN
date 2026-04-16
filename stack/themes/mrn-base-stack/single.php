<?php
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package mrn-base-stack
 */

get_header();
?>

	<main id="primary" class="site-main">

		<?php
		while ( have_posts() ) :
			the_post();
			$mrn_post_type     = sanitize_key( (string) get_post_type() );
			$mrn_template_slug = in_array( $mrn_post_type, array( 'blog', 'post_with_sidebars', 'page_with_sidebars' ), true ) ? 'page_with_sidebars' : $mrn_post_type;

			get_template_part( 'template-parts/content', $mrn_template_slug );

		endwhile; // End of the loop.
		?>

	</main><!-- #main -->

<?php
get_footer();

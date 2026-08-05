<?php
/**
 * Template Name: Sidebar Left
 * Template Post Type: post, page, gallery, testimonial, case_study
 *
 * @package mrn-base-stack
 */

get_header();
?>

	<main id="primary" class="site-main">

		<?php
		while ( have_posts() ) :
			the_post();

			$mrn_post_type = sanitize_key( (string) get_post_type() );

			if ( 'page' === $mrn_post_type ) {
				get_template_part( 'template-parts/content', 'page_with_sidebars' );
			} else {
				get_template_part( 'template-parts/content', $mrn_post_type );
			}

		endwhile;
		?>

	</main><!-- #main -->

<?php
get_footer();

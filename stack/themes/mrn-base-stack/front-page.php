<?php
/**
 * Front page template for the stack starter theme.
 *
 * @package mrn-base-stack
 */

get_header();
?>

<main id="primary" class="site-main site-main--front-page">
	<?php
	while ( have_posts() ) :
		the_post();
		get_template_part( 'template-parts/content', 'page' );
	endwhile;
	?>
</main><!-- #main -->

<?php
get_footer();

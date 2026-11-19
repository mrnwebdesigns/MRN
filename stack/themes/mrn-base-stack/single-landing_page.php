<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- WordPress matches the landing_page CPT key in single template filenames.
/**
 * Render Landing Pages with the shared page builder, without article metadata.
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
	endwhile;
	?>
</main>
<?php
get_footer();

<?php
/**
 * Template part for displaying posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package mrn-base-stack
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php
		$mrn_post_id       = get_the_ID();
		$mrn_is_singular   = is_singular();
		$mrn_has_hero      = $mrn_is_singular ? mrn_base_stack_render_hero_builder( $mrn_post_id ) : false;
		$mrn_shell_classes = array(
			'mrn-singular-shell',
			'mrn-singular-shell--post',
		);
		?>

	<div class="<?php echo esc_attr( implode( ' ', $mrn_shell_classes ) ); ?>">
		<div class="mrn-singular-shell__main">
			<?php if ( ! $mrn_has_hero ) : ?>
				<header class="entry-header">
					<?php
					if ( $mrn_is_singular ) :
						the_title( '<h1 class="entry-title">', '</h1>' );
					else :
						the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' );
					endif;
					?>
				</header><!-- .entry-header -->
			<?php endif; ?>

			<?php mrn_base_stack_post_thumbnail(); ?>

			<div class="entry-content entry-content--builder">
				<?php
				if ( $mrn_is_singular ) {
					mrn_base_stack_render_content_builder( $mrn_post_id );
					mrn_base_stack_render_after_content_builder( $mrn_post_id );
				} else {
					the_excerpt();
				}

				wp_link_pages(
					array(
						'before' => '<div class="mrn-shell-container mrn-shell-container--content"><div class="page-links">' . esc_html__( 'Pages:', 'mrn-base-stack' ),
						'after'  => '</div></div>',
					)
				);
				?>
			</div><!-- .entry-content -->
		</div><!-- .mrn-singular-shell__main -->
	</div><!-- .mrn-singular-shell -->
</article><!-- #post-<?php the_ID(); ?> -->

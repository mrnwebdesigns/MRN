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
	$mrn_post_id          = get_the_ID();
	$mrn_is_singular      = is_singular();
	$mrn_has_hero         = $mrn_is_singular ? mrn_base_stack_render_hero_builder( $mrn_post_id ) : false;
	$mrn_sidebar_settings = $mrn_is_singular && function_exists( 'mrn_base_stack_get_singular_sidebar_settings' ) ? mrn_base_stack_get_singular_sidebar_settings( $mrn_post_id ) : array( 'layout' => 'none' );
	$mrn_sidebar_markup   = $mrn_is_singular && function_exists( 'mrn_base_stack_get_singular_sidebar_markup' ) ? mrn_base_stack_get_singular_sidebar_markup( $mrn_post_id ) : '';
	$mrn_has_sidebar      = $mrn_is_singular && 'none' !== ( $mrn_sidebar_settings['layout'] ?? 'none' ) && '' !== $mrn_sidebar_markup;
	$mrn_shell_classes    = array(
		'mrn-singular-shell',
		'mrn-singular-shell--post',
	);

	if ( $mrn_has_sidebar ) {
		$mrn_shell_classes[] = 'mrn-singular-shell--has-sidebar';
		$mrn_shell_classes[] = 'mrn-singular-shell--sidebar-' . sanitize_html_class( $mrn_sidebar_settings['layout'] );
	}
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

		<?php if ( $mrn_has_sidebar ) : ?>
			<div class="mrn-singular-shell__sidebar">
				<?php echo $mrn_sidebar_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endif; ?>
	</div><!-- .mrn-singular-shell -->
</article><!-- #post-<?php the_ID(); ?> -->

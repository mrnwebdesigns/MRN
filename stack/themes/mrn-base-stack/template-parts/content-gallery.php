<?php
/**
 * Template part for displaying gallery entries.
 *
 * @package mrn-base-stack
 */

$mrn_post_id     = get_the_ID();
$mrn_is_singular = is_singular( 'gallery' );
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php if ( $mrn_is_singular ) : ?>
		<?php
		$mrn_has_hero         = function_exists( 'mrn_base_stack_render_hero_builder' ) ? mrn_base_stack_render_hero_builder( $mrn_post_id ) : false;
		$mrn_sidebar_settings = function_exists( 'mrn_base_stack_get_singular_sidebar_settings' ) ? mrn_base_stack_get_singular_sidebar_settings( $mrn_post_id ) : array( 'layout' => 'none' );
		$mrn_sidebar_markup   = function_exists( 'mrn_base_stack_get_singular_sidebar_markup' ) ? mrn_base_stack_get_singular_sidebar_markup( $mrn_post_id ) : '';
		$mrn_has_sidebar      = 'none' !== ( $mrn_sidebar_settings['layout'] ?? 'none' ) && '' !== $mrn_sidebar_markup;
		$mrn_shell_classes    = array(
			'mrn-singular-shell',
			'mrn-singular-shell--gallery',
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
						<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
						<?php if ( has_excerpt() ) : ?>
							<div class="entry-summary">
								<?php the_excerpt(); ?>
							</div>
						<?php endif; ?>
					</header>
				<?php endif; ?>

				<div class="entry-content entry-content--builder">
					<?php
					if ( function_exists( 'mrn_base_stack_render_gallery' ) ) {
						mrn_base_stack_render_gallery( $mrn_post_id );
					}

					if ( function_exists( 'mrn_base_stack_render_after_content_builder' ) ) {
						mrn_base_stack_render_after_content_builder( $mrn_post_id );
					}
					?>
				</div>
			</div>

			<?php if ( $mrn_has_sidebar ) : ?>
				<div class="mrn-singular-shell__sidebar">
					<?php echo $mrn_sidebar_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<header class="entry-header">
			<?php the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' ); ?>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
				<?php the_post_thumbnail( 'large' ); ?>
			</a>
		<?php endif; ?>

		<div class="entry-content">
			<?php the_excerpt(); ?>
		</div>
	<?php endif; ?>
</article>

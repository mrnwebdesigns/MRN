<?php
/**
 * Template part for displaying case study entries.
 *
 * @package mrn-base-stack
 */

$mrn_post_id         = get_the_ID();
$mrn_is_singular     = is_singular( 'case_study' );
$mrn_case_study      = function_exists( 'mrn_base_stack_get_case_study_data' ) ? mrn_base_stack_get_case_study_data( $mrn_post_id ) : array();
$mrn_label           = isset( $mrn_case_study['label'] ) ? trim( (string) $mrn_case_study['label'] ) : '';
$mrn_heading         = isset( $mrn_case_study['heading'] ) ? trim( (string) $mrn_case_study['heading'] ) : '';
$mrn_subheading      = isset( $mrn_case_study['subheading'] ) ? trim( (string) $mrn_case_study['subheading'] ) : '';
$mrn_client_overview = isset( $mrn_case_study['client_overview'] ) ? (string) $mrn_case_study['client_overview'] : '';
$mrn_challenge       = isset( $mrn_case_study['challenge'] ) ? (string) $mrn_case_study['challenge'] : '';
$mrn_services        = isset( $mrn_case_study['services'] ) && is_array( $mrn_case_study['services'] ) ? $mrn_case_study['services'] : array();
$mrn_strategy_text   = isset( $mrn_case_study['strategy_content'] ) ? (string) $mrn_case_study['strategy_content'] : '';
$mrn_strategy_image  = isset( $mrn_case_study['strategy_image'] ) && is_array( $mrn_case_study['strategy_image'] ) ? $mrn_case_study['strategy_image'] : null;
$mrn_strategy_side   = isset( $mrn_case_study['strategy_image_position'] ) ? (string) $mrn_case_study['strategy_image_position'] : 'right';
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
			'mrn-singular-shell--case-study',
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
						<?php if ( '' !== $mrn_label ) : ?>
							<p class="mrn-entry-label"><?php echo esc_html( $mrn_label ); ?></p>
						<?php endif; ?>

						<?php if ( '' !== $mrn_heading ) : ?>
							<h1 class="entry-title"><?php echo esc_html( $mrn_heading ); ?></h1>
						<?php else : ?>
							<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
						<?php endif; ?>

						<?php if ( '' !== $mrn_subheading ) : ?>
							<p class="entry-summary"><?php echo esc_html( $mrn_subheading ); ?></p>
						<?php endif; ?>
					</header>
				<?php endif; ?>

				<div class="entry-content entry-content--builder">
					<?php if ( '' !== trim( wp_strip_all_tags( $mrn_client_overview ) ) ) : ?>
						<section class="mrn-case-study-section">
							<h2><?php esc_html_e( 'Client Overview', 'mrn-base-stack' ); ?></h2>
							<?php echo wp_kses_post( $mrn_client_overview ); ?>
						</section>
					<?php endif; ?>

					<?php if ( '' !== trim( wp_strip_all_tags( $mrn_challenge ) ) ) : ?>
						<section class="mrn-case-study-section">
							<h2><?php esc_html_e( 'The Challenge', 'mrn-base-stack' ); ?></h2>
							<?php echo wp_kses_post( $mrn_challenge ); ?>
						</section>
					<?php endif; ?>

					<?php if ( ! empty( $mrn_services ) ) : ?>
						<section class="mrn-case-study-section">
							<h2><?php esc_html_e( 'Services We Provided', 'mrn-base-stack' ); ?></h2>
							<?php foreach ( $mrn_services as $mrn_service ) : ?>
								<?php
								if ( ! is_array( $mrn_service ) ) {
									continue;
								}

								$mrn_service_text  = isset( $mrn_service['text'] ) ? (string) $mrn_service['text'] : '';
								$mrn_service_image = isset( $mrn_service['image'] ) && is_array( $mrn_service['image'] ) ? $mrn_service['image'] : null;
								$mrn_service_side  = isset( $mrn_service['image_position'] ) ? (string) $mrn_service['image_position'] : 'right';
								$mrn_service_side  = in_array( $mrn_service_side, array( 'left', 'right' ), true ) ? $mrn_service_side : 'right';

								if ( '' === trim( wp_strip_all_tags( $mrn_service_text ) ) && ! $mrn_service_image ) {
									continue;
								}
								?>
								<div class="mrn-case-study-section mrn-case-study-section--media mrn-case-study-section--media-<?php echo esc_attr( $mrn_service_side ); ?>">
									<?php if ( 'left' === $mrn_service_side && $mrn_service_image && ! empty( $mrn_service_image['ID'] ) ) : ?>
										<div class="mrn-case-study-section__media">
											<?php echo wp_get_attachment_image( (int) $mrn_service_image['ID'], 'large' ); ?>
										</div>
									<?php endif; ?>

									<?php if ( '' !== trim( wp_strip_all_tags( $mrn_service_text ) ) ) : ?>
										<div class="mrn-case-study-section__content">
											<?php echo wp_kses_post( $mrn_service_text ); ?>
										</div>
									<?php endif; ?>

									<?php if ( 'right' === $mrn_service_side && $mrn_service_image && ! empty( $mrn_service_image['ID'] ) ) : ?>
										<div class="mrn-case-study-section__media">
											<?php echo wp_get_attachment_image( (int) $mrn_service_image['ID'], 'large' ); ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</section>
					<?php endif; ?>

					<?php if ( '' !== trim( wp_strip_all_tags( $mrn_strategy_text ) ) || ( $mrn_strategy_image && ! empty( $mrn_strategy_image['ID'] ) ) ) : ?>
						<section class="mrn-case-study-section">
							<h2><?php esc_html_e( 'Strategy and Approach', 'mrn-base-stack' ); ?></h2>
							<div class="mrn-case-study-section mrn-case-study-section--media mrn-case-study-section--media-<?php echo esc_attr( in_array( $mrn_strategy_side, array( 'left', 'right' ), true ) ? $mrn_strategy_side : 'right' ); ?>">
								<?php if ( 'left' === $mrn_strategy_side && $mrn_strategy_image && ! empty( $mrn_strategy_image['ID'] ) ) : ?>
									<div class="mrn-case-study-section__media">
										<?php echo wp_get_attachment_image( (int) $mrn_strategy_image['ID'], 'large' ); ?>
									</div>
								<?php endif; ?>

								<?php if ( '' !== trim( wp_strip_all_tags( $mrn_strategy_text ) ) ) : ?>
									<div class="mrn-case-study-section__content">
										<?php echo wp_kses_post( $mrn_strategy_text ); ?>
									</div>
								<?php endif; ?>

								<?php if ( 'right' === $mrn_strategy_side && $mrn_strategy_image && ! empty( $mrn_strategy_image['ID'] ) ) : ?>
									<div class="mrn-case-study-section__media">
										<?php echo wp_get_attachment_image( (int) $mrn_strategy_image['ID'], 'large' ); ?>
									</div>
								<?php endif; ?>
							</div>
						</section>
					<?php endif; ?>

					<?php
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
		<div class="mrn-shell-container mrn-shell-container--content">
			<header class="entry-header">
				<?php if ( '' !== $mrn_label ) : ?>
					<p class="mrn-entry-label"><?php echo esc_html( $mrn_label ); ?></p>
				<?php endif; ?>

				<?php if ( '' !== $mrn_heading ) : ?>
					<h2 class="entry-title"><a href="<?php echo esc_url( get_permalink() ); ?>" rel="bookmark"><?php echo esc_html( $mrn_heading ); ?></a></h2>
				<?php else : ?>
					<?php the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' ); ?>
				<?php endif; ?>

				<?php if ( '' !== $mrn_subheading ) : ?>
					<p class="entry-summary"><?php echo esc_html( $mrn_subheading ); ?></p>
				<?php endif; ?>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
					<?php the_post_thumbnail( 'large' ); ?>
				</a>
			<?php endif; ?>

			<?php
			$mrn_archive_text = function_exists( 'mrn_base_stack_get_case_study_excerpt' ) ? mrn_base_stack_get_case_study_excerpt( $mrn_post_id ) : '';
			if ( '' !== $mrn_archive_text ) :
				?>
				<div class="entry-summary">
					<p><?php echo esc_html( $mrn_archive_text ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</article>

<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package mrn-base-stack
 */

?>

	<?php
	$mrn_footer_options        = function_exists( 'mrn_base_stack_get_theme_header_footer_options' ) ? mrn_base_stack_get_theme_header_footer_options() : array();
	$mrn_business_information  = function_exists( 'mrn_base_stack_get_business_information' ) ? mrn_base_stack_get_business_information() : array();
	$mrn_footer_logo           = function_exists( 'mrn_base_stack_get_business_logo' ) ? mrn_base_stack_get_business_logo( 'footer' ) : null;
	$mrn_footer_address_lines  = function_exists( 'mrn_base_stack_get_business_address_lines' ) ? mrn_base_stack_get_business_address_lines() : array();
	$mrn_footer_hours_rows     = function_exists( 'mrn_base_stack_get_business_hours_display_rows' ) ? mrn_base_stack_get_business_hours_display_rows() : array();
	$mrn_show_footer_menu      = ! empty( $mrn_footer_options['footer_show_footer_menu'] ) && has_nav_menu( 'menu-3' );
	$mrn_show_legal_menu       = ! empty( $mrn_footer_options['footer_show_legal_menu'] ) && has_nav_menu( 'menu-4' );
	$mrn_show_business_profile = ! empty( $mrn_footer_options['footer_show_business_profile'] ) && ! empty( $mrn_business_information['business_profile'] );
	$mrn_show_business_phone   = ! empty( $mrn_footer_options['footer_show_business_phone'] ) && ! empty( $mrn_business_information['phone'] ) && ! empty( $mrn_business_information['phone_uri'] );
	$mrn_show_text_phone       = ! empty( $mrn_footer_options['footer_show_text_phone'] ) && ! empty( $mrn_business_information['text_phone'] ) && ! empty( $mrn_business_information['text_phone_uri'] );
	$mrn_show_address          = ! empty( $mrn_footer_options['footer_show_address'] ) && ! empty( $mrn_footer_address_lines );
	$mrn_show_business_hours   = ! empty( $mrn_footer_options['footer_show_business_hours'] ) && ! empty( $mrn_footer_hours_rows );
	$mrn_show_social_links     = ! empty( $mrn_footer_options['footer_show_social_links'] ) && function_exists( 'mrn_config_helper_get_social_links' ) && ! empty( mrn_config_helper_get_social_links() );
	$mrn_footer_legal_text     = ! empty( $mrn_footer_options['footer_legal_text'] ) ? (string) $mrn_footer_options['footer_legal_text'] : '';
	$mrn_has_footer_top        = ! empty( $mrn_footer_logo ) || $mrn_show_business_profile || $mrn_show_business_phone || $mrn_show_text_phone || $mrn_show_address || $mrn_show_business_hours || $mrn_show_social_links || $mrn_show_footer_menu;
	?>

	<footer id="colophon" class="site-footer">
		<?php if ( $mrn_has_footer_top ) : ?>
			<div class="mrn-site-footer__top">
				<div class="mrn-site-footer__brand">
					<?php if ( ! empty( $mrn_footer_logo['ID'] ) ) : ?>
						<a class="custom-logo-link mrn-site-logo-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
							<?php
							echo wp_get_attachment_image(
								(int) $mrn_footer_logo['ID'],
								'full',
								false,
								array(
									'class' => 'custom-logo mrn-site-logo',
									'alt'   => get_bloginfo( 'name' ),
								)
							);
							?>
						</a>
					<?php endif; ?>

					<?php if ( $mrn_show_business_profile ) : ?>
						<p class="mrn-site-footer__profile"><?php echo esc_html( $mrn_business_information['business_profile'] ); ?></p>
					<?php endif; ?>

					<?php if ( $mrn_show_social_links && function_exists( 'mrn_base_stack_render_social_links' ) ) : ?>
						<div class="mrn-site-footer__social">
							<?php mrn_base_stack_render_social_links(); ?>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $mrn_show_footer_menu ) : ?>
					<nav class="mrn-site-footer__nav" aria-label="<?php esc_attr_e( 'Footer menu', 'mrn-base-stack' ); ?>">
						<?php
						wp_nav_menu(
							array(
								'theme_location' => 'menu-3',
								'menu_id'        => 'footer-menu',
								'container'      => false,
							)
						);
						?>
					</nav>
				<?php endif; ?>

				<?php if ( $mrn_show_business_phone || $mrn_show_text_phone || $mrn_show_address || $mrn_show_business_hours ) : ?>
					<div class="mrn-site-footer__contact">
						<?php if ( $mrn_show_business_phone ) : ?>
							<p class="mrn-site-footer__contact-item">
								<strong><?php esc_html_e( 'Phone', 'mrn-base-stack' ); ?>:</strong>
								<a href="<?php echo esc_url( $mrn_business_information['phone_uri'] ); ?>"><?php echo esc_html( $mrn_business_information['phone'] ); ?></a>
							</p>
						<?php endif; ?>

						<?php if ( $mrn_show_text_phone ) : ?>
							<p class="mrn-site-footer__contact-item">
								<strong><?php esc_html_e( 'Text', 'mrn-base-stack' ); ?>:</strong>
								<a href="<?php echo esc_url( $mrn_business_information['text_phone_uri'] ); ?>"><?php echo esc_html( $mrn_business_information['text_phone'] ); ?></a>
							</p>
						<?php endif; ?>

						<?php if ( $mrn_show_address ) : ?>
							<address class="mrn-site-footer__address">
								<?php foreach ( $mrn_footer_address_lines as $mrn_address_line ) : ?>
									<div><?php echo esc_html( $mrn_address_line ); ?></div>
								<?php endforeach; ?>
							</address>
						<?php endif; ?>

						<?php if ( $mrn_show_business_hours ) : ?>
							<div class="mrn-site-footer__hours">
								<?php foreach ( $mrn_footer_hours_rows as $mrn_hours_row ) : ?>
									<div class="mrn-site-footer__hours-row">
										<span class="mrn-site-footer__hours-label"><?php echo esc_html( $mrn_hours_row['label'] ); ?></span>
										<span class="mrn-site-footer__hours-value"><?php echo esc_html( $mrn_hours_row['hours'] ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="site-info">
			<div class="mrn-site-footer__bottom">
				<div class="mrn-site-footer__copyright"><?php echo esc_html( mrn_base_stack_get_footer_copyright_text() ); ?></div>

				<?php if ( $mrn_show_legal_menu ) : ?>
					<nav class="mrn-site-footer__legal-nav" aria-label="<?php esc_attr_e( 'Legal menu', 'mrn-base-stack' ); ?>">
						<?php
						wp_nav_menu(
							array(
								'theme_location' => 'menu-4',
								'menu_id'        => 'legal-menu',
								'container'      => false,
							)
						);
						?>
					</nav>
				<?php endif; ?>
			</div>

			<?php if ( '' !== $mrn_footer_legal_text ) : ?>
				<div class="mrn-site-footer__legal-text"><?php echo wp_kses_post( nl2br( $mrn_footer_legal_text ) ); ?></div>
			<?php endif; ?>
		</div><!-- .site-info -->
	</footer><!-- #colophon -->
</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>

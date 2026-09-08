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
	$mrn_footer_options                 = function_exists( 'mrn_base_stack_get_theme_header_footer_options' ) ? mrn_base_stack_get_theme_header_footer_options() : array();
	$mrn_business_information           = function_exists( 'mrn_base_stack_get_business_information' ) ? mrn_base_stack_get_business_information() : array();
	$mrn_footer_logo                    = function_exists( 'mrn_base_stack_get_business_logo' ) ? mrn_base_stack_get_business_logo( 'footer', $mrn_business_information ) : null;
	$mrn_footer_description             = function_exists( 'mrn_base_stack_get_site_tagline' ) ? mrn_base_stack_get_site_tagline() : wp_kses_post( get_bloginfo( 'description', 'display' ) );
	$mrn_footer_address_lines           = function_exists( 'mrn_base_stack_get_business_address_lines' ) ? mrn_base_stack_get_business_address_lines() : array();
	$mrn_footer_hours_rows              = function_exists( 'mrn_base_stack_get_business_hours_display_rows' ) ? mrn_base_stack_get_business_hours_display_rows() : array();
	$mrn_footer_tertiary_location       = 'footer-tertiary';
	$mrn_footer_privacy_location        = has_nav_menu( 'privacy-center' ) ? 'privacy-center' : '';
	$mrn_show_social_menu               = ! empty( $mrn_footer_options['footer_show_social_menu'] ) && mrn_base_stack_has_configured_social_links();
	$mrn_show_tertiary_menu             = ! empty( $mrn_footer_options['footer_show_tertiary_menu'] ) && mrn_base_stack_nav_location_has_items( $mrn_footer_tertiary_location );
	$mrn_show_privacy_links             = ! empty( $mrn_footer_options['footer_show_privacy_center_links'] ) && '' !== $mrn_footer_privacy_location && mrn_base_stack_nav_location_has_items( $mrn_footer_privacy_location );
	$mrn_show_secondary_menu            = ! empty( $mrn_footer_options['footer_show_secondary_menu'] ) && mrn_base_stack_nav_location_has_items( 'footer-secondary' );
	$mrn_show_primary_menu              = ! empty( $mrn_footer_options['footer_show_primary_menu'] ) && mrn_base_stack_nav_location_has_items( 'menu-3' );
	$mrn_footer_social_icon_color       = mrn_base_stack_normalize_site_color_slug( isset( $mrn_footer_options['footer_social_icon_color'] ) ? $mrn_footer_options['footer_social_icon_color'] : '' );
	$mrn_footer_social_icon_hover_color = mrn_base_stack_normalize_site_color_slug( isset( $mrn_footer_options['footer_social_icon_hover_color'] ) ? $mrn_footer_options['footer_social_icon_hover_color'] : '' );
	$mrn_footer_social_icon_style       = '';
	if ( '' !== $mrn_footer_social_icon_color ) {
		$mrn_footer_social_icon_style = '--mrn-social-icon-color: ' . mrn_base_stack_get_site_color_css_value( $mrn_footer_social_icon_color ) . ';';
	}
	if ( '' !== $mrn_footer_social_icon_hover_color ) {
		$mrn_footer_social_icon_style .= ' --mrn-social-icon-hover-color: ' . mrn_base_stack_get_site_color_css_value( $mrn_footer_social_icon_hover_color ) . ';';
	}
	$mrn_footer_social_menu_class = 'mrn-social-links';
	$mrn_show_footer_tagline      = ! empty( $mrn_footer_options['footer_show_tagline'] ) && ( '' !== $mrn_footer_description || is_customize_preview() );
	$mrn_show_business_profile    = ! empty( $mrn_footer_options['footer_show_business_profile'] ) && ! empty( $mrn_business_information['business_profile'] );
	$mrn_show_business_phone      = ! empty( $mrn_footer_options['footer_show_business_phone'] ) && ! empty( $mrn_business_information['phone'] ) && ! empty( $mrn_business_information['phone_uri'] );
	$mrn_show_text_phone          = ! empty( $mrn_footer_options['footer_show_text_phone'] ) && ! empty( $mrn_business_information['text_phone'] ) && ! empty( $mrn_business_information['text_phone_uri'] );
	$mrn_show_address             = ! empty( $mrn_footer_options['footer_show_address'] ) && ! empty( $mrn_footer_address_lines );
	$mrn_show_business_hours      = ! empty( $mrn_footer_options['footer_show_business_hours'] ) && ! empty( $mrn_footer_hours_rows );
	$mrn_show_back_to_top         = ! empty( $mrn_footer_options['footer_show_back_to_top'] );
	$mrn_footer_legal_text        = ! empty( $mrn_footer_options['footer_legal_text'] ) ? (string) $mrn_footer_options['footer_legal_text'] : '';
	$mrn_footer_body_rows         = array(
		array(
			'show'       => $mrn_show_primary_menu,
			'modifier'   => 'primary',
			'location'   => 'menu-3',
			'menu_id'    => 'footer-primary-menu',
			'menu_class' => 'menu',
			'aria_label' => __( 'Footer primary menu', 'mrn-base-stack' ),
		),
		array(
			'show'       => $mrn_show_secondary_menu,
			'modifier'   => 'secondary',
			'location'   => 'footer-secondary',
			'menu_id'    => 'footer-secondary-menu',
			'menu_class' => 'menu',
			'aria_label' => __( 'Footer secondary menu', 'mrn-base-stack' ),
		),
	);
	$mrn_footer_rows              = array(
		array(
			'show'       => $mrn_show_tertiary_menu,
			'modifier'   => 'tertiary',
			'location'   => $mrn_footer_tertiary_location,
			'menu_id'    => 'footer-tertiary-menu',
			'menu_class' => 'menu',
			'aria_label' => __( 'Footer tertiary menu', 'mrn-base-stack' ),
		),
		array(
			'show'       => $mrn_show_social_menu,
			'modifier'   => 'social',
			'menu_class' => $mrn_footer_social_menu_class,
			'style'      => $mrn_footer_social_icon_style,
			'renderer'   => 'configured_social_links',
			'aria_label' => __( 'Footer social menu', 'mrn-base-stack' ),
		),
		array(
			'show'       => $mrn_show_privacy_links,
			'modifier'   => 'privacy-center',
			'location'   => $mrn_footer_privacy_location,
			'menu_id'    => 'privacy-center-menu',
			'menu_class' => 'menu',
			'aria_label' => __( 'Privacy center links', 'mrn-base-stack' ),
		),
	);
	$mrn_footer_layout_grid       = isset( $mrn_footer_options['footer_layout_grid'] ) && is_array( $mrn_footer_options['footer_layout_grid'] ) ? $mrn_footer_options['footer_layout_grid'] : ( function_exists( 'mrn_base_stack_get_theme_header_footer_layout_grid' ) ? mrn_base_stack_get_theme_header_footer_layout_grid( 'footer' ) : array() );
	$mrn_footer_attributes        = function_exists( 'mrn_base_stack_get_theme_header_footer_shell_attributes' ) ? mrn_base_stack_get_theme_header_footer_shell_attributes( 'footer', $mrn_footer_options, $mrn_footer_layout_grid ) : array();
	$mrn_footer_attribute_html    = function_exists( 'mrn_base_stack_get_theme_header_footer_html_attributes' ) ? mrn_base_stack_get_theme_header_footer_html_attributes( $mrn_footer_attributes ) : '';
	$mrn_footer_content_width     = isset( $mrn_footer_options['footer_content_width'] ) ? $mrn_footer_options['footer_content_width'] : 'wide';
	$mrn_footer_width_class       = function_exists( 'mrn_base_stack_get_theme_header_footer_content_width_class' ) ? mrn_base_stack_get_theme_header_footer_content_width_class( $mrn_footer_content_width, 'wide' ) : 'mrn-theme-hf-layout--width-wide';
	$mrn_footer_classes           = trim( 'site-footer mrn-theme-hf-layout-grid mrn-theme-hf-layout-grid--footer ' . $mrn_footer_width_class . ( $mrn_show_back_to_top ? ' mrn-site-footer--has-back-to-top' : '' ) );
	$mrn_footer_grid_item_style   = static function ( $item_key ) use ( $mrn_footer_layout_grid ) {
		return function_exists( 'mrn_base_stack_get_theme_header_footer_layout_grid_item_style' ) ? mrn_base_stack_get_theme_header_footer_layout_grid_item_style( $mrn_footer_layout_grid, $item_key ) : '';
	};
	/* translators: %s: Site name. */
	$mrn_footer_home_label = sprintf( __( '%s home', 'mrn-base-stack' ), get_bloginfo( 'name' ) );
	?>

		<footer
			id="colophon"
			class="<?php echo esc_attr( $mrn_footer_classes ); ?>"
			<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Header/footer attribute helper escapes attribute names and values.
				echo '' !== $mrn_footer_attribute_html ? ' ' . $mrn_footer_attribute_html : '';
			?>
		>
		<?php if ( function_exists( 'mrn_base_stack_image_has_content' ) && mrn_base_stack_image_has_content( $mrn_footer_logo ) ) : ?>
			<div class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--footer-brand mrn-site-footer__brand" data-mrn-layout-slot="footer" data-mrn-layout-item="footer_brand"<?php echo '' !== $mrn_footer_grid_item_style( 'footer_brand' ) ? ' style="' . esc_attr( $mrn_footer_grid_item_style( 'footer_brand' ) ) . '"' : ''; ?>>
				<a class="custom-logo-link mrn-site-logo-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( $mrn_footer_home_label ); ?>">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared image helper returns escaped wp_get_attachment_image markup.
					echo function_exists( 'mrn_base_stack_get_attachment_image' ) ? mrn_base_stack_get_attachment_image(
						$mrn_footer_logo,
						'mrn-logo',
						array(
							'class' => 'custom-logo mrn-site-logo',
							'alt'   => get_bloginfo( 'name' ),
						)
					) : '';
					?>
				</a>
			</div>
		<?php endif; ?>

		<?php if ( $mrn_show_footer_tagline ) : ?>
			<div class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--footer-tagline mrn-site-footer__tagline" data-mrn-layout-slot="footer-tagline" data-mrn-layout-item="footer_tagline"<?php echo '' !== $mrn_footer_grid_item_style( 'footer_tagline' ) ? ' style="' . esc_attr( $mrn_footer_grid_item_style( 'footer_tagline' ) ) . '"' : ''; ?>>
				<p class="site-description"><?php echo $mrn_footer_description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized by mrn_base_stack_get_site_tagline() or wp_kses_post(). ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $mrn_show_business_profile ) : ?>
			<div class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--business-profile mrn-site-footer__profile" data-mrn-layout-slot="business-profile" data-mrn-layout-item="business_profile"<?php echo '' !== $mrn_footer_grid_item_style( 'business_profile' ) ? ' style="' . esc_attr( $mrn_footer_grid_item_style( 'business_profile' ) ) . '"' : ''; ?>>
				<?php echo esc_html( $mrn_business_information['business_profile'] ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $mrn_show_business_phone ) : ?>
			<div class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--business-phone mrn-site-footer__contact-item" data-mrn-layout-slot="business-phone" data-mrn-layout-item="business_phone"<?php echo '' !== $mrn_footer_grid_item_style( 'business_phone' ) ? ' style="' . esc_attr( $mrn_footer_grid_item_style( 'business_phone' ) ) . '"' : ''; ?>>
				<strong><?php esc_html_e( 'Phone', 'mrn-base-stack' ); ?>:</strong>
				<a href="<?php echo esc_url( $mrn_business_information['phone_uri'] ); ?>"><?php echo esc_html( $mrn_business_information['phone'] ); ?></a>
			</div>
		<?php endif; ?>

		<?php if ( $mrn_show_text_phone ) : ?>
			<div class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--text-phone mrn-site-footer__contact-item" data-mrn-layout-slot="text-phone" data-mrn-layout-item="text_phone"<?php echo '' !== $mrn_footer_grid_item_style( 'text_phone' ) ? ' style="' . esc_attr( $mrn_footer_grid_item_style( 'text_phone' ) ) . '"' : ''; ?>>
				<strong><?php esc_html_e( 'Text', 'mrn-base-stack' ); ?>:</strong>
				<a href="<?php echo esc_url( $mrn_business_information['text_phone_uri'] ); ?>"><?php echo esc_html( $mrn_business_information['text_phone'] ); ?></a>
			</div>
		<?php endif; ?>

		<?php if ( $mrn_show_address ) : ?>
			<address class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--address mrn-site-footer__address" data-mrn-layout-slot="address" data-mrn-layout-item="address"<?php echo '' !== $mrn_footer_grid_item_style( 'address' ) ? ' style="' . esc_attr( $mrn_footer_grid_item_style( 'address' ) ) . '"' : ''; ?>>
				<?php foreach ( $mrn_footer_address_lines as $mrn_address_line ) : ?>
					<div><?php echo esc_html( $mrn_address_line ); ?></div>
				<?php endforeach; ?>
			</address>
		<?php endif; ?>

		<?php if ( $mrn_show_business_hours ) : ?>
			<div class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--business-hours mrn-site-footer__hours" data-mrn-layout-slot="business-hours" data-mrn-layout-item="business_hours"<?php echo '' !== $mrn_footer_grid_item_style( 'business_hours' ) ? ' style="' . esc_attr( $mrn_footer_grid_item_style( 'business_hours' ) ) . '"' : ''; ?>>
				<?php foreach ( $mrn_footer_hours_rows as $mrn_hours_row ) : ?>
					<div class="mrn-site-footer__hours-row">
						<span class="mrn-site-footer__hours-label"><?php echo esc_html( $mrn_hours_row['label'] ); ?></span>
						<span class="mrn-site-footer__hours-value"><?php echo esc_html( $mrn_hours_row['hours'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php foreach ( $mrn_footer_body_rows as $mrn_footer_body_row ) : ?>
			<?php if ( empty( $mrn_footer_body_row['show'] ) || empty( $mrn_footer_body_row['location'] ) ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<?php
			$mrn_footer_body_item_key = 'primary' === $mrn_footer_body_row['modifier'] ? 'footer_primary_menu' : 'footer_secondary_menu';
			?>
			<nav class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--<?php echo esc_attr( $mrn_footer_body_item_key ); ?> mrn-site-footer__nav mrn-site-footer__nav--<?php echo esc_attr( $mrn_footer_body_row['modifier'] ); ?>" data-mrn-layout-slot="<?php echo esc_attr( $mrn_footer_body_row['modifier'] ); ?>" data-mrn-layout-item="<?php echo esc_attr( $mrn_footer_body_item_key ); ?>" aria-label="<?php echo esc_attr( $mrn_footer_body_row['aria_label'] ); ?>"<?php echo '' !== $mrn_footer_grid_item_style( $mrn_footer_body_item_key ) ? ' style="' . esc_attr( $mrn_footer_grid_item_style( $mrn_footer_body_item_key ) ) . '"' : ''; ?>>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => $mrn_footer_body_row['location'],
						'menu_id'        => $mrn_footer_body_row['menu_id'],
						'container'      => false,
						'menu_class'     => isset( $mrn_footer_body_row['menu_class'] ) ? (string) $mrn_footer_body_row['menu_class'] : 'menu',
					)
				);
				?>
			</nav>
		<?php endforeach; ?>

		<?php foreach ( $mrn_footer_rows as $mrn_footer_row ) : ?>
			<?php if ( empty( $mrn_footer_row['show'] ) ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<?php if ( empty( $mrn_footer_row['renderer'] ) && empty( $mrn_footer_row['location'] ) ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<?php
			$mrn_footer_item_key  = 'tertiary' === $mrn_footer_row['modifier'] ? 'footer_tertiary_menu' : ( 'privacy-center' === $mrn_footer_row['modifier'] ? 'privacy_center_links' : 'social_media' );
			$mrn_footer_nav_class = 'mrn-site-footer__menu-nav mrn-site-footer__menu-nav--' . sanitize_html_class( (string) $mrn_footer_row['modifier'] );
			$mrn_footer_nav_style = ! empty( $mrn_footer_row['style'] ) ? (string) $mrn_footer_row['style'] : '';
			?>
			<nav class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--<?php echo esc_attr( $mrn_footer_item_key ); ?> <?php echo esc_attr( $mrn_footer_nav_class ); ?>" data-mrn-layout-slot="<?php echo esc_attr( $mrn_footer_row['modifier'] ); ?>" data-mrn-layout-item="<?php echo esc_attr( $mrn_footer_item_key ); ?>" aria-label="<?php echo esc_attr( $mrn_footer_row['aria_label'] ); ?>"<?php echo '' !== $mrn_footer_grid_item_style( $mrn_footer_item_key ) || '' !== $mrn_footer_nav_style ? ' style="' . esc_attr( trim( $mrn_footer_grid_item_style( $mrn_footer_item_key ) . ' ' . $mrn_footer_nav_style ) ) . '"' : ''; ?>>
				<?php
				if ( isset( $mrn_footer_row['renderer'] ) && 'configured_social_links' === $mrn_footer_row['renderer'] ) {
					mrn_base_stack_render_social_links(
						array(
							'class' => isset( $mrn_footer_row['menu_class'] ) ? (string) $mrn_footer_row['menu_class'] : '',
						)
					);
				} else {
					wp_nav_menu(
						array(
							'theme_location' => $mrn_footer_row['location'],
							'menu_id'        => $mrn_footer_row['menu_id'],
							'container'      => false,
							'menu_class'     => isset( $mrn_footer_row['menu_class'] ) ? (string) $mrn_footer_row['menu_class'] : 'menu',
						)
					);
				}
				?>
			</nav>
		<?php endforeach; ?>

		<div class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--copyright site-info" data-mrn-layout-slot="copyright" data-mrn-layout-item="copyright"<?php echo '' !== $mrn_footer_grid_item_style( 'copyright' ) ? ' style="' . esc_attr( $mrn_footer_grid_item_style( 'copyright' ) ) . '"' : ''; ?>>
			<?php if ( '' !== $mrn_footer_legal_text ) : ?>
				<div class="mrn-site-footer__legal-text"><?php echo wp_kses_post( nl2br( $mrn_footer_legal_text ) ); ?></div>
			<?php endif; ?>

			<div class="mrn-site-footer__bottom">
				<div class="mrn-site-footer__copyright"><?php echo esc_html( mrn_base_stack_get_footer_copyright_text() ); ?></div>
			</div>
		</div><!-- .site-info -->

		<?php if ( $mrn_show_back_to_top ) : ?>
			<a class="mrn-back-to-top" href="#page" aria-label="<?php esc_attr_e( 'Back to top', 'mrn-base-stack' ); ?>" data-mrn-back-to-top>
				<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Back to top', 'mrn-base-stack' ); ?></span>
			</a>
		<?php endif; ?>
	</footer><!-- #colophon -->
</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>

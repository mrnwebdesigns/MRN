<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package mrn-base-stack
 */

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">

	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'mrn-base-stack' ); ?></a>

	<?php
		$mrn_header_options                = function_exists( 'mrn_base_stack_get_theme_header_footer_options' ) ? mrn_base_stack_get_theme_header_footer_options() : array();
		$mrn_business_information          = function_exists( 'mrn_base_stack_get_business_information' ) ? mrn_base_stack_get_business_information() : array();
		$mrn_header_primary_location       = 'menu-1';
		$mrn_header_primary_menu_id        = isset( $mrn_header_options['header_primary_menu_id'] ) ? absint( $mrn_header_options['header_primary_menu_id'] ) : 0;
		$mrn_header_secondary_location     = 'header-secondary';
		$mrn_header_tertiary_location      = 'header-tertiary';
		$mrn_show_secondary_menu           = ! empty( $mrn_header_options['header_show_secondary_menu'] ) && has_nav_menu( $mrn_header_secondary_location );
		$mrn_show_tertiary_menu            = ! empty( $mrn_header_options['header_show_tertiary_menu'] ) && function_exists( 'mrn_base_stack_nav_location_has_items' ) && mrn_base_stack_nav_location_has_items( $mrn_header_tertiary_location );
		$mrn_show_primary_menu             = mrn_base_stack_nav_menu_selection_has_items( $mrn_header_primary_menu_id, $mrn_header_primary_location );
		$mrn_show_search                   = ! empty( $mrn_header_options['header_show_search'] ) && ! empty( $mrn_header_options['header_searchwp_form_id'] ) && function_exists( 'mrn_base_stack_has_action' ) && mrn_base_stack_has_action( 'mrn_base_stack_header_search' );
		$mrn_show_business_phone           = ! empty( $mrn_header_options['header_show_business_phone'] ) && ! empty( $mrn_business_information['phone'] ) && ! empty( $mrn_business_information['phone_uri'] );
		$mrn_show_business_profile         = ! empty( $mrn_header_options['header_show_business_profile'] ) && ! empty( $mrn_business_information['business_profile'] );
		$mrn_show_header_utility_message   = function_exists( 'mrn_base_stack_has_header_utility_message' ) && mrn_base_stack_has_header_utility_message();
		$mrn_has_header_menu_rows          = $mrn_show_secondary_menu;
		$mrn_business_logo                 = function_exists( 'mrn_base_stack_get_business_logo' ) ? mrn_base_stack_get_business_logo( 'header' ) : null;
		$mrn_has_custom_logo               = function_exists( 'has_custom_logo' ) && has_custom_logo();
		$mrn_header_layout_grid            = isset( $mrn_header_options['header_layout_grid'] ) && is_array( $mrn_header_options['header_layout_grid'] ) ? $mrn_header_options['header_layout_grid'] : ( function_exists( 'mrn_base_stack_get_theme_header_footer_layout_grid' ) ? mrn_base_stack_get_theme_header_footer_layout_grid( 'header' ) : array() );
		$mrn_header_use_layout_grid        = ! isset( $mrn_header_options['header_use_layout_grid'] ) || ! empty( $mrn_header_options['header_use_layout_grid'] );
		$mrn_header_default_layout_order   = function_exists( 'mrn_base_stack_get_default_theme_header_footer_layout_order' ) ? mrn_base_stack_get_default_theme_header_footer_layout_order( 'header' ) : array( 'secondary_menu', 'header_utility_message', 'header_brand', 'business_profile', 'business_phone', 'search', 'header_tertiary_menu' );
		$mrn_header_layout_order           = isset( $mrn_header_options['header_layout_order'] ) && is_array( $mrn_header_options['header_layout_order'] ) ? $mrn_header_options['header_layout_order'] : ( function_exists( 'mrn_base_stack_get_theme_header_footer_layout_order' ) ? mrn_base_stack_get_theme_header_footer_layout_order( 'header' ) : $mrn_header_default_layout_order );
		$mrn_header_layout_order           = ! empty( $mrn_header_layout_order ) ? $mrn_header_layout_order : $mrn_header_default_layout_order;
		$mrn_header_attributes             = function_exists( 'mrn_base_stack_get_theme_header_footer_shell_attributes' ) ? mrn_base_stack_get_theme_header_footer_shell_attributes( 'header', $mrn_header_options, $mrn_header_layout_grid, $mrn_header_use_layout_grid ) : array();
		$mrn_header_attribute_html         = function_exists( 'mrn_base_stack_get_theme_header_footer_html_attributes' ) ? mrn_base_stack_get_theme_header_footer_html_attributes( $mrn_header_attributes ) : '';
		$mrn_header_content_width          = isset( $mrn_header_options['header_content_width'] ) ? $mrn_header_options['header_content_width'] : 'wide';
		$mrn_header_width_class            = function_exists( 'mrn_base_stack_get_theme_header_footer_content_width_class' ) ? mrn_base_stack_get_theme_header_footer_content_width_class( $mrn_header_content_width, 'wide' ) : 'mrn-theme-hf-layout--width-wide';
		$mrn_header_primary_nav_inherits   = ! isset( $mrn_header_options['header_primary_nav_inherit_header_settings'] ) || ! empty( $mrn_header_options['header_primary_nav_inherit_header_settings'] );
		$mrn_header_primary_nav_width      = $mrn_header_primary_nav_inherits && function_exists( 'mrn_base_stack_normalize_theme_header_footer_content_width' ) ? mrn_base_stack_normalize_theme_header_footer_content_width( $mrn_header_content_width, 'wide' ) : '';
		$mrn_header_primary_nav_width_slug = 'full-width' === $mrn_header_primary_nav_width ? 'full' : $mrn_header_primary_nav_width;
		$mrn_header_primary_nav_style      = $mrn_header_primary_nav_inherits && function_exists( 'mrn_base_stack_get_theme_header_footer_appearance_style' ) ? mrn_base_stack_get_theme_header_footer_appearance_style( 'header', $mrn_header_options ) : '';
		$mrn_header_primary_nav_classes    = 'main-navigation mrn-site-primary-navigation';
		$mrn_mobile_navigation_options     = function_exists( 'mrn_base_stack_get_mobile_navigation_options' ) ? mrn_base_stack_get_mobile_navigation_options() : array( 'enabled' => false );
		$mrn_mobile_navigation_enabled     = ! empty( $mrn_mobile_navigation_options['enabled'] );
		$mrn_mobile_navigation_uses_header = ! isset( $mrn_mobile_navigation_options['use_site_header'] ) || ! empty( $mrn_mobile_navigation_options['use_site_header'] );
		$mrn_mobile_navigation_style       = $mrn_mobile_navigation_enabled && function_exists( 'mrn_base_stack_get_mobile_navigation_style' ) ? mrn_base_stack_get_mobile_navigation_style( $mrn_mobile_navigation_options ) : '';
		$mrn_mobile_navigation_bottom      = $mrn_mobile_navigation_enabled && function_exists( 'mrn_base_stack_get_mobile_navigation_bottom_markup' ) ? mrn_base_stack_get_mobile_navigation_bottom_markup( $mrn_mobile_navigation_options ) : '';
		$mrn_mobile_navigation_action      = $mrn_mobile_navigation_enabled && ! $mrn_mobile_navigation_uses_header && function_exists( 'mrn_base_stack_get_mobile_navigation_header_action_markup' ) ? mrn_base_stack_get_mobile_navigation_header_action_markup( $mrn_mobile_navigation_options ) : '';
		$mrn_mobile_navigation_logo        = ! empty( $mrn_mobile_navigation_options['mobile_logo_id'] ) ? absint( $mrn_mobile_navigation_options['mobile_logo_id'] ) : $mrn_business_logo;
		/* translators: %s: Menu item label. */
		$mrn_mobile_submenu_open_label = __( 'Open %s submenu', 'mrn-base-stack' );
		/* translators: %s: Menu item label. */
		$mrn_mobile_submenu_close_label  = __( 'Close %s submenu', 'mrn-base-stack' );
		$mrn_header_primary_nav_classes .= $mrn_mobile_navigation_enabled ? ' mrn-mobile-navigation' : '';
		$mrn_header_primary_nav_classes .= $mrn_mobile_navigation_enabled && ! $mrn_mobile_navigation_uses_header ? ' mrn-mobile-navigation--full-screen' : '';
		$mrn_header_primary_nav_classes .= '' !== $mrn_header_primary_nav_width_slug ? ' mrn-site-primary-navigation--width-' . sanitize_html_class( $mrn_header_primary_nav_width_slug ) : ' mrn-site-primary-navigation--independent';
		$mrn_header_classes              = trim( 'site-header ' . ( $mrn_header_use_layout_grid ? 'mrn-theme-hf-layout-grid mrn-theme-hf-layout-grid--header ' : 'mrn-theme-hf-layout-stack mrn-theme-hf-layout-stack--header ' ) . $mrn_header_width_class );
		$mrn_header_grid_item_style      = static function ( $item_key ) use ( $mrn_header_layout_grid ) {
			return function_exists( 'mrn_base_stack_get_theme_header_footer_layout_grid_item_style' ) ? mrn_base_stack_get_theme_header_footer_layout_grid_item_style( $mrn_header_layout_grid, $item_key ) : '';
		};
		$mrn_header_item_attributes      = static function ( $item_key, $slot, $classes = '' ) use ( $mrn_header_use_layout_grid, $mrn_header_grid_item_style ) {
			$item_key    = sanitize_key( (string) $item_key );
			$item_class  = sanitize_html_class( str_replace( '_', '-', $item_key ) );
			$base_class  = $mrn_header_use_layout_grid ? 'mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--' : 'mrn-theme-hf-layout-stack__item mrn-theme-hf-layout-stack__item--';
			$class_names = trim( $base_class . $item_class . ' ' . (string) $classes );
			$style       = $mrn_header_use_layout_grid ? $mrn_header_grid_item_style( $item_key ) : '';
			$attributes  = array(
				'class'                => $class_names,
				'data-mrn-layout-slot' => (string) $slot,
				'data-mrn-layout-item' => $item_key,
			);

			if ( '' !== $style ) {
				$attributes['style'] = $style;
			}

			return function_exists( 'mrn_base_stack_get_theme_header_footer_html_attributes' ) ? mrn_base_stack_get_theme_header_footer_html_attributes( $attributes ) : '';
		};
		$mrn_header_primary_menu_args    = mrn_base_stack_get_nav_menu_selection_args(
			$mrn_header_primary_menu_id,
			$mrn_header_primary_location,
			array(
				'menu_id'    => 'primary-menu',
				'container'  => false,
				'menu_class' => 'menu',
			)
		);
		$mrn_header_rows                 = array(
			array(
				'show'          => $mrn_show_secondary_menu,
				'modifier'      => 'secondary',
				'location'      => $mrn_header_secondary_location,
				'menu_id'       => 'header-secondary-menu',
				'menu_class'    => 'menu',
				'aria_label'    => __( 'Header secondary menu', 'mrn-base-stack' ),
			),
		);
		/* translators: %s: Site name. */
		$mrn_header_home_label  = sprintf( __( '%s home', 'mrn-base-stack' ), get_bloginfo( 'name' ) );
		$mrn_header_render_item = static function ( $mrn_header_item_key ) use ( $mrn_header_item_attributes, $mrn_has_header_menu_rows, $mrn_header_rows, $mrn_show_header_utility_message, $mrn_business_logo, $mrn_has_custom_logo, $mrn_header_home_label, $mrn_show_business_profile, $mrn_business_information, $mrn_show_business_phone, $mrn_show_search, $mrn_show_tertiary_menu, $mrn_header_tertiary_location ) {
			switch ( $mrn_header_item_key ) {
				case 'secondary_menu':
					if ( ! $mrn_has_header_menu_rows ) {
						return;
					}
					?>
					<div <?php echo $mrn_header_item_attributes( 'secondary_menu', 'secondary-menu', 'mrn-site-header__menu-rows' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute helper escapes attribute names and values. ?>>
						<?php foreach ( $mrn_header_rows as $mrn_header_row ) : ?>
							<?php if ( empty( $mrn_header_row['show'] ) || empty( $mrn_header_row['location'] ) ) : ?>
								<?php continue; ?>
							<?php endif; ?>
							<?php
							$mrn_header_nav_class = 'mrn-site-header__menu-nav mrn-site-header__menu-nav--' . sanitize_html_class( (string) $mrn_header_row['modifier'] );
							?>
							<div class="mrn-site-header__menu-row mrn-site-header__menu-row--<?php echo esc_attr( $mrn_header_row['modifier'] ); ?>">
								<nav class="<?php echo esc_attr( $mrn_header_nav_class ); ?>" aria-label="<?php echo esc_attr( $mrn_header_row['aria_label'] ); ?>">
									<?php
									wp_nav_menu(
										array(
											'theme_location' => $mrn_header_row['location'],
											'menu_id'    => $mrn_header_row['menu_id'],
											'container'  => false,
											'menu_class' => isset( $mrn_header_row['menu_class'] ) ? (string) $mrn_header_row['menu_class'] : 'menu',
										)
									);
									?>
								</nav>
							</div>
						<?php endforeach; ?>
					</div>
					<?php
					break;

				case 'header_utility_message':
					if ( ! $mrn_show_header_utility_message ) {
						return;
					}
					?>
					<div <?php echo $mrn_header_item_attributes( 'header_utility_message', 'header-utility-message', 'mrn-site-header__utility-message-item' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute helper escapes attribute names and values. ?>>
						<?php echo mrn_base_stack_get_header_utility_message_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper sanitizes text, URL, target, classes, and link title. ?>
					</div>
					<?php
					break;

				case 'header_brand':
					?>
					<div <?php echo $mrn_header_item_attributes( 'header_brand', 'header', 'mrn-site-header__main' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute helper escapes attribute names and values. ?>>
						<div class="site-branding">
							<?php
							if ( function_exists( 'mrn_base_stack_image_has_content' ) && mrn_base_stack_image_has_content( $mrn_business_logo ) ) :
								?>
								<a class="custom-logo-link mrn-site-logo-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( $mrn_header_home_label ); ?>">
									<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared image helper returns escaped wp_get_attachment_image markup.
									echo function_exists( 'mrn_base_stack_get_attachment_image' ) ? mrn_base_stack_get_attachment_image(
										$mrn_business_logo,
										'mrn-logo',
										array(
											'class' => 'custom-logo mrn-site-logo',
											'alt'   => get_bloginfo( 'name' ),
										)
									) : '';
									?>
								</a>
								<?php
							elseif ( $mrn_has_custom_logo ) :
								the_custom_logo();
							elseif ( is_front_page() && is_home() ) :
								?>
								<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
								<?php
							else :
								?>
								<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></p>
								<?php
							endif;

							$mrn_base_stack_description = function_exists( 'mrn_base_stack_get_site_tagline' ) ? mrn_base_stack_get_site_tagline() : get_bloginfo( 'description', 'display' );
							if ( $mrn_base_stack_description || is_customize_preview() ) :
								?>
								<p class="site-description"><?php echo $mrn_base_stack_description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized by mrn_base_stack_get_site_tagline(). ?></p>
							<?php endif; ?>
						</div><!-- .site-branding -->
					</div>
					<?php
					break;

				case 'business_profile':
					if ( ! $mrn_show_business_profile ) {
						return;
					}
					?>
					<div <?php echo $mrn_header_item_attributes( 'business_profile', 'business-profile', 'mrn-site-header__meta' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute helper escapes attribute names and values. ?>>
						<div class="mrn-site-header__business-text"><?php echo esc_html( $mrn_business_information['business_profile'] ); ?></div>
					</div>
					<?php
					break;

				case 'business_phone':
					if ( ! $mrn_show_business_phone ) {
						return;
					}
					?>
					<div <?php echo $mrn_header_item_attributes( 'business_phone', 'business-phone', 'mrn-site-header__meta' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute helper escapes attribute names and values. ?>>
						<a class="mrn-site-header__phone" href="<?php echo esc_url( $mrn_business_information['phone_uri'] ); ?>"><?php echo esc_html( $mrn_business_information['phone'] ); ?></a>
					</div>
					<?php
					break;

				case 'search':
					if ( ! $mrn_show_search || ! function_exists( 'mrn_base_stack_render_header_search' ) ) {
						return;
					}
					?>
					<div <?php echo $mrn_header_item_attributes( 'search', 'search', 'mrn-site-header__meta' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute helper escapes attribute names and values. ?>>
						<?php mrn_base_stack_render_header_search(); ?>
					</div>
					<?php
					break;

				case 'header_tertiary_menu':
					if ( ! $mrn_show_tertiary_menu ) {
						return;
					}
					?>
					<div <?php echo $mrn_header_item_attributes( 'header_tertiary_menu', 'header-tertiary', 'mrn-site-header__navigation mrn-site-header__menu-row mrn-site-header__menu-row--tertiary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute helper escapes attribute names and values. ?>>
						<nav class="mrn-site-header__menu-nav mrn-site-header__menu-nav--tertiary" aria-label="<?php esc_attr_e( 'Header tertiary menu', 'mrn-base-stack' ); ?>">
						<?php
						wp_nav_menu(
							array(
								'theme_location' => $mrn_header_tertiary_location,
								'menu_id'        => 'header-tertiary-menu',
								'container'      => false,
								'menu_class'     => 'menu',
							)
						);
						?>
						</nav>
					</div>
					<?php
					break;
			}
		};
		?>

		<header
			id="masthead"
			class="<?php echo esc_attr( $mrn_header_classes ); ?>"
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Header/footer attribute helper escapes attribute names and values.
			echo '' !== $mrn_header_attribute_html ? ' ' . $mrn_header_attribute_html : '';
			?>
		>
			<?php foreach ( $mrn_header_use_layout_grid ? $mrn_header_default_layout_order : $mrn_header_layout_order as $mrn_header_render_item_key ) : ?>
				<?php $mrn_header_render_item( $mrn_header_render_item_key ); ?>
			<?php endforeach; ?>
		</header><!-- #masthead -->

		<?php if ( $mrn_show_primary_menu ) : ?>
			<nav id="site-navigation" class="<?php echo esc_attr( $mrn_header_primary_nav_classes ); ?>" aria-label="<?php esc_attr_e( 'Primary menu', 'mrn-base-stack' ); ?>"<?php echo $mrn_mobile_navigation_enabled ? ' data-mrn-mobile-navigation data-submenu-open-label="' . esc_attr( $mrn_mobile_submenu_open_label ) . '" data-submenu-close-label="' . esc_attr( $mrn_mobile_submenu_close_label ) . '"' : ''; ?><?php echo '' !== trim( $mrn_header_primary_nav_style . ';' . $mrn_mobile_navigation_style, ';' ) ? ' style="' . esc_attr( trim( $mrn_header_primary_nav_style . ';' . $mrn_mobile_navigation_style, ';' ) ) . '"' : ''; ?>>
				<button class="menu-toggle" aria-controls="<?php echo $mrn_mobile_navigation_enabled ? 'mrn-mobile-navigation-panel' : 'primary-menu'; ?>" aria-expanded="false" aria-label="<?php esc_attr_e( 'Open navigation', 'mrn-base-stack' ); ?>"<?php echo $mrn_mobile_navigation_enabled ? ' data-close-label="' . esc_attr__( 'Close navigation', 'mrn-base-stack' ) . '"' : ''; ?>>
					<?php if ( $mrn_mobile_navigation_enabled ) : ?>
						<span class="mrn-mobile-navigation__toggle-bar" aria-hidden="true"></span>
						<span class="mrn-mobile-navigation__toggle-bar" aria-hidden="true"></span>
						<span class="mrn-mobile-navigation__toggle-bar" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'mrn-base-stack' ); ?></span>
					<?php else : ?>
						<?php esc_html_e( 'Primary Menu', 'mrn-base-stack' ); ?>
					<?php endif; ?>
				</button>
				<?php if ( $mrn_mobile_navigation_enabled ) : ?>
					<div id="mrn-mobile-navigation-panel" class="mrn-mobile-navigation__panel">
						<?php if ( ! $mrn_mobile_navigation_uses_header ) : ?>
							<div class="mrn-mobile-navigation__drawer-header">
								<div class="mrn-mobile-navigation__drawer-branding">
									<?php if ( function_exists( 'mrn_base_stack_image_has_content' ) && mrn_base_stack_image_has_content( $mrn_mobile_navigation_logo ) ) : ?>
										<a class="mrn-mobile-navigation__logo-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( $mrn_header_home_label ); ?>">
											<?php
											// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared image helper returns escaped attachment markup.
											echo function_exists( 'mrn_base_stack_get_attachment_image' ) ? mrn_base_stack_get_attachment_image(
												$mrn_mobile_navigation_logo,
												'mrn-logo',
												array(
													'class' => 'mrn-mobile-navigation__logo',
													'alt' => get_bloginfo( 'name' ),
												)
											) : '';
											?>
										</a>
									<?php elseif ( $mrn_has_custom_logo ) : ?>
										<?php the_custom_logo(); ?>
									<?php else : ?>
										<a class="mrn-mobile-navigation__site-title" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
									<?php endif; ?>
								</div>
								<?php if ( '' !== $mrn_mobile_navigation_action ) : ?>
									<div class="mrn-mobile-navigation__header-action">
										<?php echo $mrn_mobile_navigation_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Mobile-navigation helper escapes built-in output; extension filter owns added markup. ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>
				<?php endif; ?>
				<?php
				wp_nav_menu(
					$mrn_header_primary_menu_args
				);
				?>
				<?php if ( $mrn_mobile_navigation_enabled ) : ?>
						<?php if ( '' !== $mrn_mobile_navigation_bottom ) : ?>
							<div class="mrn-mobile-navigation__bottom">
								<?php echo $mrn_mobile_navigation_bottom; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Reusable block renderer and extension hooks own escaping. ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</nav><!-- #site-navigation -->
		<?php endif; ?>

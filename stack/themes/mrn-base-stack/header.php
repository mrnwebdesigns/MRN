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
		$mrn_header_secondary_location     = 'header-secondary';
		$mrn_header_tertiary_location      = 'header-tertiary';
		$mrn_show_secondary_menu           = ! empty( $mrn_header_options['header_show_secondary_menu'] ) && has_nav_menu( $mrn_header_secondary_location );
		$mrn_show_tertiary_menu            = ! empty( $mrn_header_options['header_show_tertiary_menu'] ) && function_exists( 'mrn_base_stack_nav_location_has_items' ) && mrn_base_stack_nav_location_has_items( $mrn_header_tertiary_location );
		$mrn_show_primary_menu             = function_exists( 'mrn_base_stack_nav_location_has_items' ) && mrn_base_stack_nav_location_has_items( 'menu-1' );
		$mrn_show_search                   = ! empty( $mrn_header_options['header_show_search'] ) && ! empty( $mrn_header_options['header_searchwp_form_id'] ) && function_exists( 'mrn_base_stack_has_action' ) && mrn_base_stack_has_action( 'mrn_base_stack_header_search' );
		$mrn_show_business_phone           = ! empty( $mrn_header_options['header_show_business_phone'] ) && ! empty( $mrn_business_information['phone'] ) && ! empty( $mrn_business_information['phone_uri'] );
		$mrn_show_business_profile         = ! empty( $mrn_header_options['header_show_business_profile'] ) && ! empty( $mrn_business_information['business_profile'] );
		$mrn_has_header_menu_rows          = $mrn_show_secondary_menu;
		$mrn_business_logo                 = function_exists( 'mrn_base_stack_get_business_logo' ) ? mrn_base_stack_get_business_logo( 'header' ) : null;
		$mrn_has_custom_logo               = function_exists( 'has_custom_logo' ) && has_custom_logo();
		$mrn_header_layout_grid            = isset( $mrn_header_options['header_layout_grid'] ) && is_array( $mrn_header_options['header_layout_grid'] ) ? $mrn_header_options['header_layout_grid'] : ( function_exists( 'mrn_base_stack_get_theme_header_footer_layout_grid' ) ? mrn_base_stack_get_theme_header_footer_layout_grid( 'header' ) : array() );
		$mrn_header_attributes             = function_exists( 'mrn_base_stack_get_theme_header_footer_shell_attributes' ) ? mrn_base_stack_get_theme_header_footer_shell_attributes( 'header', $mrn_header_options, $mrn_header_layout_grid ) : array();
		$mrn_header_attribute_html         = function_exists( 'mrn_base_stack_get_theme_header_footer_html_attributes' ) ? mrn_base_stack_get_theme_header_footer_html_attributes( $mrn_header_attributes ) : '';
		$mrn_header_content_width          = isset( $mrn_header_options['header_content_width'] ) ? $mrn_header_options['header_content_width'] : 'wide';
		$mrn_header_width_class            = function_exists( 'mrn_base_stack_get_theme_header_footer_content_width_class' ) ? mrn_base_stack_get_theme_header_footer_content_width_class( $mrn_header_content_width, 'wide' ) : 'mrn-theme-hf-layout-grid--width-wide';
		$mrn_header_primary_nav_inherits   = ! isset( $mrn_header_options['header_primary_nav_inherit_header_settings'] ) || ! empty( $mrn_header_options['header_primary_nav_inherit_header_settings'] );
		$mrn_header_primary_nav_width      = $mrn_header_primary_nav_inherits && function_exists( 'mrn_base_stack_normalize_theme_header_footer_content_width' ) ? mrn_base_stack_normalize_theme_header_footer_content_width( $mrn_header_content_width, 'wide' ) : '';
		$mrn_header_primary_nav_width_slug = 'full-width' === $mrn_header_primary_nav_width ? 'full' : $mrn_header_primary_nav_width;
		$mrn_header_primary_nav_style      = $mrn_header_primary_nav_inherits && function_exists( 'mrn_base_stack_get_theme_header_footer_appearance_style' ) ? mrn_base_stack_get_theme_header_footer_appearance_style( 'header', $mrn_header_options ) : '';
		$mrn_header_primary_nav_classes    = 'main-navigation mrn-site-primary-navigation';
		$mrn_header_primary_nav_classes   .= '' !== $mrn_header_primary_nav_width_slug ? ' mrn-site-primary-navigation--width-' . sanitize_html_class( $mrn_header_primary_nav_width_slug ) : ' mrn-site-primary-navigation--independent';
		$mrn_header_classes                = trim( 'site-header mrn-theme-hf-layout-grid mrn-theme-hf-layout-grid--header ' . $mrn_header_width_class );
		$mrn_header_grid_item_style        = static function ( $item_key ) use ( $mrn_header_layout_grid ) {
			return function_exists( 'mrn_base_stack_get_theme_header_footer_layout_grid_item_style' ) ? mrn_base_stack_get_theme_header_footer_layout_grid_item_style( $mrn_header_layout_grid, $item_key ) : '';
		};
		$mrn_header_rows                   = array(
			array(
				'show'          => $mrn_show_secondary_menu,
				'modifier'      => 'secondary',
				'location'      => $mrn_header_secondary_location,
				'menu_id'       => 'header-secondary-menu',
				'menu_class'    => 'menu',
				'aria_label'    => __( 'Header secondary menu', 'mrn-base-stack' ),
			),
		);
	?>

		<header id="masthead" class="<?php echo esc_attr( $mrn_header_classes ); ?>"<?php echo '' !== $mrn_header_attribute_html ? ' ' . $mrn_header_attribute_html : ''; ?>>
		<?php if ( $mrn_has_header_menu_rows ) : ?>
			<div class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--secondary-menu mrn-site-header__menu-rows" data-mrn-layout-slot="secondary-menu" data-mrn-layout-item="secondary_menu"<?php echo '' !== $mrn_header_grid_item_style( 'secondary_menu' ) ? ' style="' . esc_attr( $mrn_header_grid_item_style( 'secondary_menu' ) ) . '"' : ''; ?>>
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
									'menu_id'        => $mrn_header_row['menu_id'],
									'container'      => false,
									'menu_class'     => isset( $mrn_header_row['menu_class'] ) ? (string) $mrn_header_row['menu_class'] : 'menu',
								)
							);
							?>
						</nav>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--header-brand mrn-site-header__main" data-mrn-layout-slot="header" data-mrn-layout-item="header_brand"<?php echo '' !== $mrn_header_grid_item_style( 'header_brand' ) ? ' style="' . esc_attr( $mrn_header_grid_item_style( 'header_brand' ) ) . '"' : ''; ?>>
			<div class="site-branding">
				<?php
				if ( function_exists( 'mrn_base_stack_image_has_content' ) && mrn_base_stack_image_has_content( $mrn_business_logo ) ) :
					?>
					<a class="custom-logo-link mrn-site-logo-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<?php
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

				$mrn_base_stack_description = get_bloginfo( 'description', 'display' );
				if ( $mrn_base_stack_description || is_customize_preview() ) :
					?>
					<p class="site-description"><?php echo $mrn_base_stack_description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php endif; ?>
			</div><!-- .site-branding -->
		</div>

		<?php if ( $mrn_show_business_profile ) : ?>
			<div class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--business-profile mrn-site-header__meta" data-mrn-layout-slot="business-profile" data-mrn-layout-item="business_profile"<?php echo '' !== $mrn_header_grid_item_style( 'business_profile' ) ? ' style="' . esc_attr( $mrn_header_grid_item_style( 'business_profile' ) ) . '"' : ''; ?>>
				<div class="mrn-site-header__business-text"><?php echo esc_html( $mrn_business_information['business_profile'] ); ?></div>
			</div>
		<?php endif; ?>

		<?php if ( $mrn_show_business_phone ) : ?>
			<div class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--business-phone mrn-site-header__meta" data-mrn-layout-slot="business-phone" data-mrn-layout-item="business_phone"<?php echo '' !== $mrn_header_grid_item_style( 'business_phone' ) ? ' style="' . esc_attr( $mrn_header_grid_item_style( 'business_phone' ) ) . '"' : ''; ?>>
				<a class="mrn-site-header__phone" href="<?php echo esc_url( $mrn_business_information['phone_uri'] ); ?>"><?php echo esc_html( $mrn_business_information['phone'] ); ?></a>
			</div>
		<?php endif; ?>

		<?php if ( $mrn_show_search && function_exists( 'mrn_base_stack_render_header_search' ) ) : ?>
			<div class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--search mrn-site-header__meta" data-mrn-layout-slot="search" data-mrn-layout-item="search"<?php echo '' !== $mrn_header_grid_item_style( 'search' ) ? ' style="' . esc_attr( $mrn_header_grid_item_style( 'search' ) ) . '"' : ''; ?>>
				<?php mrn_base_stack_render_header_search(); ?>
			</div>
		<?php endif; ?>

			<?php if ( $mrn_show_tertiary_menu ) : ?>
				<div class="mrn-theme-hf-layout-grid__item mrn-theme-hf-layout-grid__item--header-tertiary-menu mrn-site-header__navigation mrn-site-header__menu-row mrn-site-header__menu-row--tertiary" data-mrn-layout-slot="header-tertiary" data-mrn-layout-item="header_tertiary_menu"<?php echo '' !== $mrn_header_grid_item_style( 'header_tertiary_menu' ) ? ' style="' . esc_attr( $mrn_header_grid_item_style( 'header_tertiary_menu' ) ) . '"' : ''; ?>>
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
			<?php endif; ?>
		</header><!-- #masthead -->

		<?php if ( $mrn_show_primary_menu ) : ?>
			<nav id="site-navigation" class="<?php echo esc_attr( $mrn_header_primary_nav_classes ); ?>" aria-label="<?php esc_attr_e( 'Primary menu', 'mrn-base-stack' ); ?>"<?php echo '' !== $mrn_header_primary_nav_style ? ' style="' . esc_attr( $mrn_header_primary_nav_style ) . '"' : ''; ?>>
				<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false"><?php esc_html_e( 'Primary Menu', 'mrn-base-stack' ); ?></button>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'menu-1',
						'menu_id'        => 'primary-menu',
						'container'      => false,
						'menu_class'     => 'menu',
					)
				);
				?>
			</nav><!-- #site-navigation -->
		<?php endif; ?>

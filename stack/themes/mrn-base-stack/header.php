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
	$mrn_header_options        = function_exists( 'mrn_base_stack_get_theme_header_footer_options' ) ? mrn_base_stack_get_theme_header_footer_options() : array();
	$mrn_business_information  = function_exists( 'mrn_base_stack_get_business_information' ) ? mrn_base_stack_get_business_information() : array();
	$mrn_show_utility_menu     = ! empty( $mrn_header_options['header_show_utility_menu'] ) && has_nav_menu( 'menu-2' );
	$mrn_show_search           = ! empty( $mrn_header_options['header_show_search'] ) && function_exists( 'mrn_base_stack_has_action' ) && mrn_base_stack_has_action( 'mrn_base_stack_header_search' );
	$mrn_show_business_phone   = ! empty( $mrn_header_options['header_show_business_phone'] ) && ! empty( $mrn_business_information['phone'] ) && ! empty( $mrn_business_information['phone_uri'] );
	$mrn_show_business_profile = ! empty( $mrn_header_options['header_show_business_profile'] ) && ! empty( $mrn_business_information['business_profile'] );
	$mrn_has_header_meta       = $mrn_show_search || $mrn_show_business_phone || $mrn_show_business_profile;
	$mrn_business_logo         = function_exists( 'mrn_base_stack_get_business_logo' ) ? mrn_base_stack_get_business_logo( 'header' ) : null;
	$mrn_has_custom_logo       = function_exists( 'has_custom_logo' ) && has_custom_logo();
	?>

	<header id="masthead" class="site-header">
		<?php if ( $mrn_show_utility_menu ) : ?>
			<div class="mrn-site-header__utility-row">
				<nav class="mrn-site-header__utility-nav" aria-label="<?php esc_attr_e( 'Utility menu', 'mrn-base-stack' ); ?>">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'menu-2',
							'menu_id'        => 'utility-menu',
							'container'      => false,
						)
					);
					?>
				</nav>
			</div>
		<?php endif; ?>

		<?php if ( $mrn_has_header_meta ) : ?>
			<div class="mrn-site-header__meta">
				<?php if ( $mrn_show_business_profile ) : ?>
					<div class="mrn-site-header__business-text"><?php echo esc_html( $mrn_business_information['business_profile'] ); ?></div>
				<?php endif; ?>

				<?php if ( $mrn_show_business_phone ) : ?>
					<a class="mrn-site-header__phone" href="<?php echo esc_url( $mrn_business_information['phone_uri'] ); ?>"><?php echo esc_html( $mrn_business_information['phone'] ); ?></a>
				<?php endif; ?>

				<?php
				if ( $mrn_show_search && function_exists( 'mrn_base_stack_render_header_search' ) ) {
					mrn_base_stack_render_header_search();
				}
				?>
			</div>
		<?php endif; ?>

		<div class="mrn-site-header__main">
			<div class="site-branding">
				<?php
				if ( ! empty( $mrn_business_logo['ID'] ) ) :
					?>
					<a class="custom-logo-link mrn-site-logo-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<?php
						echo wp_get_attachment_image(
							(int) $mrn_business_logo['ID'],
							'full',
							false,
							array(
								'class' => 'custom-logo mrn-site-logo',
								'alt'   => get_bloginfo( 'name' ),
							)
						);
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

			<nav id="site-navigation" class="main-navigation">
				<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false"><?php esc_html_e( 'Primary Menu', 'mrn-base-stack' ); ?></button>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'menu-1',
						'menu_id'        => 'primary-menu',
					)
				);
				?>
			</nav><!-- #site-navigation -->
		</div>
	</header><!-- #masthead -->

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
	$mrn_header_secondary_location = has_nav_menu( 'header-secondary' ) ? 'header-secondary' : 'menu-2';
	$mrn_show_social_menu      = ! empty( $mrn_header_options['header_show_social_menu'] ) && has_nav_menu( 'social-media' );
	$mrn_show_tertiary_menu    = ! empty( $mrn_header_options['header_show_tertiary_menu'] ) && has_nav_menu( 'header-tertiary' );
	$mrn_show_secondary_menu   = ! empty( $mrn_header_options['header_show_secondary_menu'] ) && has_nav_menu( $mrn_header_secondary_location );
	$mrn_show_primary_menu     = ! empty( $mrn_header_options['header_show_primary_menu'] ) && has_nav_menu( 'menu-1' );
	$mrn_header_social_icon_tone = function_exists( 'mrn_base_stack_normalize_social_icon_tone' )
		? mrn_base_stack_normalize_social_icon_tone( isset( $mrn_header_options['header_social_icon_tone'] ) ? $mrn_header_options['header_social_icon_tone'] : 'dark' )
		: 'dark';
	$mrn_header_social_menu_class = 'mrn-social-links mrn-social-links--icon-tone-' . sanitize_html_class( $mrn_header_social_icon_tone );
	$mrn_show_search           = ! empty( $mrn_header_options['header_show_search'] ) && function_exists( 'mrn_base_stack_has_action' ) && mrn_base_stack_has_action( 'mrn_base_stack_header_search' );
	$mrn_show_business_phone   = ! empty( $mrn_header_options['header_show_business_phone'] ) && ! empty( $mrn_business_information['phone'] ) && ! empty( $mrn_business_information['phone_uri'] );
	$mrn_show_business_profile = ! empty( $mrn_header_options['header_show_business_profile'] ) && ! empty( $mrn_business_information['business_profile'] );
	$mrn_has_header_meta       = $mrn_show_search || $mrn_show_business_phone || $mrn_show_business_profile;
	$mrn_has_header_menu_rows  = $mrn_show_social_menu || $mrn_show_tertiary_menu || $mrn_show_secondary_menu;
	$mrn_business_logo         = function_exists( 'mrn_base_stack_get_business_logo' ) ? mrn_base_stack_get_business_logo( 'header' ) : null;
	$mrn_has_custom_logo       = function_exists( 'has_custom_logo' ) && has_custom_logo();
	$mrn_header_rows           = array(
		array(
			'show'          => $mrn_show_social_menu,
			'modifier'      => 'social',
			'location'      => 'social-media',
			'menu_id'       => 'header-social-menu',
			'menu_class'    => $mrn_header_social_menu_class,
			'icon_tone'     => $mrn_header_social_icon_tone,
			'aria_label'    => __( 'Social menu', 'mrn-base-stack' ),
		),
		array(
			'show'          => $mrn_show_tertiary_menu,
			'modifier'      => 'tertiary',
			'location'      => 'header-tertiary',
			'menu_id'       => 'header-tertiary-menu',
			'menu_class'    => 'menu',
			'aria_label'    => __( 'Header tertiary menu', 'mrn-base-stack' ),
		),
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

	<header id="masthead" class="site-header">
		<?php if ( $mrn_has_header_menu_rows ) : ?>
			<div class="mrn-site-header__menu-rows">
				<?php foreach ( $mrn_header_rows as $mrn_header_row ) : ?>
					<?php if ( empty( $mrn_header_row['show'] ) || empty( $mrn_header_row['location'] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<?php
					$mrn_header_nav_class = 'mrn-site-header__menu-nav mrn-site-header__menu-nav--' . sanitize_html_class( (string) $mrn_header_row['modifier'] );
					if ( ! empty( $mrn_header_row['icon_tone'] ) ) {
						$mrn_header_nav_class .= ' mrn-site-header__menu-nav--icon-tone-' . sanitize_html_class( (string) $mrn_header_row['icon_tone'] );
					}
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

		<div class="mrn-site-header__main mrn-site-header__menu-row mrn-site-header__menu-row--primary">
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

			<?php if ( $mrn_show_primary_menu ) : ?>
				<nav id="site-navigation" class="main-navigation mrn-site-header__menu-nav mrn-site-header__menu-nav--primary" aria-label="<?php esc_attr_e( 'Primary menu', 'mrn-base-stack' ); ?>">
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
			<?php endif; ?>
		</div>

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
	</header><!-- #masthead -->

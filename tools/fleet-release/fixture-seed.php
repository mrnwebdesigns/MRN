<?php
/** Synthetic, replayable content for an isolated qualification database. */
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$administrator = get_user_by( 'login', 'fleet-admin' );
if ( ! $administrator ) {
	throw new RuntimeException( 'Fixture administrator is missing.' );
}
wp_set_current_user( $administrator->ID );
if ( class_exists( 'WooCommerce' ) || is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
	throw new RuntimeException( 'Qualification must run without WooCommerce.' );
}
update_option( 'permalink_structure', '/%postname%/' );
$ids = array();
foreach ( array( 'home' => 'Home', 'contact' => 'Contact', 'about' => 'About', 'resources' => 'Resources' ) as $slug => $title ) {
	$ids[ $slug ] = wp_insert_post( array(
		'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug,
		'post_title' => $title,
		'post_content' => '<p>Synthetic source qualification content for native WordPress.</p>',
	), true );
	if ( is_wp_error( $ids[ $slug ] ) ) {
		throw new RuntimeException( 'Fixture page creation failed.' );
	}
}
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $ids['home'] );
$menu = wp_create_nav_menu( 'Fleet fixture navigation' );
foreach ( $ids as $page_id ) {
	wp_update_nav_menu_item( $menu, 0, array(
		'menu-item-object-id' => $page_id, 'menu-item-object' => 'page',
		'menu-item-type' => 'post_type', 'menu-item-status' => 'publish',
	) );
}
set_theme_mod( 'nav_menu_locations', array( 'menu-1' => $menu, 'menu-3' => $menu ) );
if ( ! function_exists( 'update_field' ) || ! function_exists( 'wpforms' ) ) {
	throw new RuntimeException( 'Native ACF and WPForms are required fixture dependencies.' );
}
update_field( 'field_mrn_page_after_content_rows', array(
	array( 'acf_fc_layout' => 'cta', 'heading' => 'Fleet After Content',
		'content' => '<p>Native saved ACF content.</p>' ),
	array( 'acf_fc_layout' => 'basic_block', 'heading' => 'Fleet saved block',
		'content' => '<p>Second native saved ACF content.</p>' ),
), $ids['home'] );
$rows = get_field( 'page_after_content_rows', $ids['home'] );
if ( 2 !== count( $rows ) || 'Fleet After Content' !== $rows[0]['heading'] ) {
	throw new RuntimeException( 'Native After Content persistence failed.' );
}
$form = array(
	'fields' => array( 1 => array( 'id' => 1, 'type' => 'text', 'label' => 'Fixture message', 'required' => '1' ) ),
	'settings' => array( 'notification_enable' => '0', 'form_class' => '' ),
);
$ids['form'] = wpforms()->obj( 'form' )->add( 'Fleet native form', array( 'post_content' => wpforms_encode( $form ) ) );
$form['id'] = (string) $ids['form'];
if ( ! $ids['form'] || ! wpforms()->obj( 'form' )->update( $ids['form'], $form ) ) {
	throw new RuntimeException( 'Native WPForms creation/save failed.' );
}
wp_update_post( array( 'ID' => $ids['contact'], 'post_content' => '[wpforms id="' . $ids['form'] . '"]' ) );
flush_rewrite_rules( false );
echo wp_json_encode( $ids );

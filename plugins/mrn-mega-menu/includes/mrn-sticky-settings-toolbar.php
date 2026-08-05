<?php

if (
	function_exists( 'mrn_sticky_toolbar_render' )
	&& function_exists( 'mrn_sticky_toolbar_render_css' )
) {
	return;
}

if ( defined( 'WP_CONTENT_DIR' ) && file_exists( WP_CONTENT_DIR . '/shared/mrn-sticky-settings-toolbar.php' ) ) {
	require_once WP_CONTENT_DIR . '/shared/mrn-sticky-settings-toolbar.php';
}

if (
	! function_exists( 'mrn_sticky_toolbar_render' )
	&& defined( 'WP_PLUGIN_DIR' )
	&& file_exists( WP_PLUGIN_DIR . '/mrn-universal-sticky-bar/includes/mrn-sticky-settings-toolbar.php' )
) {
	require_once WP_PLUGIN_DIR . '/mrn-universal-sticky-bar/includes/mrn-sticky-settings-toolbar.php';
}

<?php
/** Detach only clone-specific integrations, then retain native editor data. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'admin-editor-pilot.mrndev.io' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'Wrong review target.' );
}
$destination = untrailingslashit( home_url() );
update_option( 'home', $destination );
update_option( 'siteurl', $destination );
update_option( 'blog_public', 0 );
update_option( 'cron', array() );
$plugins = (array) get_option( 'active_plugins', array() );
$plugins = array_values( array_filter( $plugins, static function ( $plugin ) {
	return ! preg_match( '#^(mainwp-child|fluent-smtp|wp-mail-smtp)/#', $plugin );
} ) );
if ( ! in_array( 'updraftplus/updraftplus.php', $plugins, true ) ) { $plugins[] = 'updraftplus/updraftplus.php'; }
update_option( 'active_plugins', $plugins );
require __DIR__ . '/apply-backup-settings.php';
echo "Clone identity detached; native content retained.\n";

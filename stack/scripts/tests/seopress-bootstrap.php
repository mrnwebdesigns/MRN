<?php
/** Exercise the real bootstrap option transition, including a second run. */
// phpcs:disable WordPress.Security.EscapeOutput -- Standalone CLI fixture output only.
// phpcs:disable WordPress.WP.AlternativeFunctions -- Temporary CLI source fixture, never a WordPress request.
if ( 'cli' !== PHP_SAPI ) { exit; }
$bootstrap = file_get_contents( dirname( __DIR__ ) . '/site-bootstrap.sh' );
$function = explode( 'provision_seopress_schema_defaults() {', explode( 'configure_mrn_breadcrumb_schema_ownership() {', $bootstrap )[1] )[0];
$php = explode( "')\"; then", explode( "run_wp eval '", $function )[1] )[0];
$options = array(
	'mrn_helper_settings' => array( 'unrelated' => 'keep', 'breadcrumbs' => array( 'schema_source' => 'stack', 'provider' => 'legacy', 'position' => 'after_header' ) ),
	'seopress_pro_option_name' => array( 'seopress_breadcrumbs_json_enable' => '', 'identity' => 'keep' ),
	'seopress_toggle_option_name' => array( 'toggle-breadcrumbs' => '', 'toggle-titles' => '1' ),
);
$writes = 0;
function get_option( $name, $default = false ) { global $options; return $options[$name] ?? $default; }
function update_option( $name, $value, $autoload = null ) { global $options, $writes; $options[$name] = $value; ++$writes; return true; }
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$fixture = tempnam( sys_get_temp_dir(), 'mrn-bootstrap-fixture-' );
if ( false === $fixture || false === file_put_contents( $fixture, "<?php\n" . $php ) ) {
	throw new RuntimeException( 'Could not create bootstrap source fixture.' );
}
try {
// Only the extracted, version-controlled bootstrap block is written to this mode-0600 CLI fixture.
require $fixture; // nosemgrep: php-dynamic-include
check( 'seopress' === $options['mrn_helper_settings']['breadcrumbs']['provider'], 'Native provider missing.' );
check( 'seo_provider' === $options['mrn_helper_settings']['breadcrumbs']['schema_source'], 'Schema ownership mismatch.' );
check( 'after_header' === $options['mrn_helper_settings']['breadcrumbs']['position'], 'Placement changed.' );
check( 'keep' === $options['mrn_helper_settings']['unrelated'] && 'keep' === $options['seopress_pro_option_name']['identity'], 'Unrelated settings changed.' );
check( '1' === $options['seopress_pro_option_name']['seopress_breadcrumbs_enable'] && '1' === $options['seopress_pro_option_name']['seopress_breadcrumbs_json_enable'], 'Visible/schema pair incomplete.' );
$first_writes = $writes;
// Only the extracted, version-controlled bootstrap block is written to this mode-0600 CLI fixture.
require $fixture; // nosemgrep: php-dynamic-include
check( $first_writes === $writes, 'Bootstrap is not idempotent.' );
echo "PASS: native breadcrumb bootstrap preserves placement and is idempotent\n";

} finally {
	unlink( $fixture );
}

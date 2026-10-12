<?php
// phpcs:ignoreFile -- Standalone WordPress script-registry contract harness.
/**
 * Run: php mu-plugins/mrn-editor-lockdown/tests/php/editor-assets.php
 *
 * @package MRNEditorLockdown
 */

function add_action() {}
function add_filter() {}
function wp_script_is( $handle ) { return 'mrn-editor-palette-loader' === $handle; }
function wp_scripts() { return $GLOBALS['scripts']; }

class WP_Scripts {
	public $queue = array( 'wp-core-commands', 'seopress-command-palette', 'seo-metabox' );
	public $done = array( 'jquery' );
	public $registered = array();
	public function get_data( $handle ) { return 'head-library' === $handle ? 0 : 1; }
	public function add_inline_script( $handle, $script ) { $this->registered[ $handle ]->extra['before'][] = $script; }
	public function add_data( $handle, $key, $value ) { $this->registered[ $handle ]->extra[ $key ] = $value; }
}

require __DIR__ . '/../../includes/editor-assets.php';

function check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}

$GLOBALS['scripts'] = new WP_Scripts();
foreach ( array(
	'wp-core-commands' => array( 'core-data', 'commands' ),
	'core-data' => array( 'block-editor', 'head-library' ),
	'block-editor' => array( 'components' ),
	'commands' => array( 'components' ),
	'components' => array( 'jquery' ),
	'seopress-command-palette' => array( 'commands' ),
	'seo-metabox' => array( 'components' ),
	'head-library' => array(),
	'jquery' => array(),
	'custom-feature' => array( 'core-data' ),
	'cycle-a' => array( 'cycle-b' ),
	'cycle-b' => array( 'cycle-a' ),
) as $name => $dependencies ) {
		$GLOBALS['scripts']->registered[ $name ] = (object) array( 'deps' => $dependencies, 'extra' => array() );
}
$order = array( 'jquery', 'components', 'block-editor', 'core-data', 'commands', 'wp-core-commands', 'seo-metabox' );
check( $order === mrn_editor_lockdown_prepare_palette_scripts( $order ), 'Native printing order must remain intact.' );
$deferred = $GLOBALS['mrn_editor_palette_scripts'];
check( isset( $deferred['block-editor'], $deferred['core-data'], $deferred['wp-core-commands'] ), 'Exclusive palette libraries should wait.' );
check( ! isset( $deferred['components'], $deferred['jquery'], $deferred['head-library'] ), 'Other features, printed scripts and head libraries stay native.' );
check( 1 === count( $GLOBALS['scripts']->registered['core-data']->extra['before'] ), 'A native inline boundary keeps an exclusive script out of concatenated downloads.' );
foreach ( array( 'before', 'after', 'extra', 'translations' ) as $suffix ) {
	$attributes = mrn_editor_lockdown_defer_palette_inline_script( array( 'id' => 'core-data-js-' . $suffix, 'nonce' => 'native-csp-nonce' ) );
	check( 'application/x-mrn-command-palette' === $attributes['type'] && 'native-csp-nonce' === $attributes['nonce'], 'Inline settings, translations and CSP attributes must survive.' );
}
check( array( 'id' => 'components-js-after' ) === mrn_editor_lockdown_defer_palette_inline_script( array( 'id' => 'components-js-after' ) ), 'Shared inline settings must execute normally.' );
$GLOBALS['scripts']->queue[] = 'custom-feature';
mrn_editor_lockdown_prepare_palette_scripts( $order );
check( 1 === count( $GLOBALS['scripts']->registered['commands']->extra['before'] ), 'Rechecking the queue must not duplicate inline boundaries.' );
check( ! isset( $GLOBALS['mrn_editor_palette_scripts']['core-data'], $GLOBALS['mrn_editor_palette_scripts']['block-editor'] ), 'A separate consumer must protect its entire dependency tree.' );
check( 2 === count( mrn_editor_lockdown_script_dependencies( $GLOBALS['scripts'], array( 'cycle-a' ) ) ), 'Cycles must terminate without duplicating dependencies.' );
echo "PASS: native order, shared consumers, inline settings/translations, CSP and cyclic dependencies.\n";

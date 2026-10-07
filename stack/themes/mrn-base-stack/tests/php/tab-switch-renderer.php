<?php
// phpcs:ignoreFile -- Standalone WordPress boundary stubs; actual helper and renderer are executed.
/** Regression: every saved animation choice reaches the rendered tab root. */
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_html_class( $value ) { return sanitize_key( $value ); }
function add_filter() {}
function add_action() {}
function apply_filters( $name, $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return esc_attr( $value ); }
function esc_html__( $value ) { return $value; }
function __( $value ) { return $value; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function wp_unique_id( $prefix ) { static $i = 0; return $prefix . ++$i; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, $args ); }
function mrn_base_stack_render_builder_row() { echo '<div class="mrn-ui__text">Fixture panel.</div>'; }

require dirname( __DIR__, 2 ) . '/inc/builder/helpers.php';
$choices = mrn_base_stack_get_tab_switch_effect_choices();
$cases = array_merge( array_keys( $choices ), array( 'unknown-effect' ) );
foreach ( $cases as $effect ) {
	$args = array( 'post_id' => 42, 'index' => 1, 'row' => array(
		'tab_switch_effect' => $effect,
		'tabs' => array( array( 'tab_label' => 'Fixture tab', 'panel_rows' => array( array( 'acf_fc_layout' => 'text' ) ) ) ),
	) );
	ob_start();
	include dirname( __DIR__, 2 ) . '/template-parts/builder/tabbed-layout.php';
	$html = ob_get_clean();
	$expected = isset( $choices[ $effect ] ) ? $effect : 'instant';
	if ( false === strpos( $html, 'mrn-tabbed-layout--transition-' . $expected ) ) {
		fwrite( STDERR, 'Saved animation did not reach the real renderer: ' . $effect . "\n" );
		exit( 1 );
	}
}
echo "Saved tab animation choices reach the real renderer; unknown values switch instantly.\n";

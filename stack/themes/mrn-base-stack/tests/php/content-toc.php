<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness for the content TOC contract.
/**
 * Focused regression coverage for the CPT-agnostic content TOC helper.
 *
 * @package mrn-base-stack
 */

function absint( $value ) {
	return abs( (int) $value );
}

function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) );
}

function sanitize_title( $value ) {
	$value = strtolower( trim( (string) $value ) );
	$value = preg_replace( '/[^a-z0-9]+/', '-', $value );
	return trim( $value, '-' );
}

function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function apply_filters( $hook_name, $value ) {
	return $value;
}

require dirname( __DIR__, 2 ) . '/inc/content-toc.php';

$result = mrn_base_stack_prepare_content_toc(
	'<p>Intro</p><h2>First Section</h2><h3>Sub section</h3><h2 id="existing">Existing</h2><h2>First Section</h2><h4>Ignored</h4>'
);

if ( 4 !== count( $result['items'] ) ) {
	throw new RuntimeException( 'The helper did not collect the configured heading levels.' );
}

$ids = array_column( $result['items'], 'id' );
if ( array( 'first-section', 'sub-section', 'existing', 'first-section-2' ) !== $ids ) {
	throw new RuntimeException( 'Heading IDs were not generated and deduplicated correctly.' );
}

if ( false === strpos( $result['html'], 'id="first-section-2"' ) || false !== strpos( $result['html'], '<h4 id=' ) ) {
	throw new RuntimeException( 'The processed content does not match the TOC entries.' );
}

echo "PASS: Content TOC contract.\n";

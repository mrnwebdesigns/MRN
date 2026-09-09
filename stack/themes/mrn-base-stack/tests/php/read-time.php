<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness for the read-time contract.
/**
 * Focused regression coverage for the CPT-agnostic reading-time helpers.
 *
 * @package mrn-base-stack
 */

function absint( $value ) {
	return abs( (int) $value );
}

function get_the_ID() {
	return 101;
}

function get_post_field( $field, $post_id ) {
	$posts = array(
		101 => '[button] One two three four five six seven eight nine ten. [\/button]',
		202 => str_repeat( 'word ', 450 ),
	);

	return isset( $posts[ $post_id ] ) && 'post_content' === $field ? $posts[ $post_id ] : '';
}

function strip_shortcodes( $content ) {
	return preg_replace( '/\[[^\]]+\]/', '', $content );
}

function wp_strip_all_tags( $content ) {
	return strip_tags( $content );
}

function apply_filters( $hook_name, $value, $post_id = null ) {
	if ( 'mrn_base_stack_read_time_words_per_minute' === $hook_name ) {
		return 150;
	}

	return $value;
}

function _n( $single, $plural, $number, $domain = 'default' ) {
	return 1 === (int) $number ? $single : $plural;
}

require dirname( __DIR__, 2 ) . '/inc/read-time.php';

if ( 1 !== mrn_base_stack_get_read_time_minutes( 101 ) ) {
	throw new RuntimeException( 'The default post estimate was not calculated correctly.' );
}

if ( 3 !== mrn_base_stack_get_read_time_minutes( 202, 225 ) ) {
	throw new RuntimeException( 'The helper did not support a second post type without branching on CPT.' );
}

if ( '1 min read' !== mrn_base_stack_get_read_time_label( 101 ) ) {
	throw new RuntimeException( 'The reading-time label was not formatted correctly.' );
}

echo "PASS: Read-time contract.\n";

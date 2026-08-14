<?php
// phpcs:ignoreFile -- Standalone SearchWP/WordPress stub harness for stack form provisioning.
/**
 * Focused regression coverage for the canonical SearchWP form contract.
 *
 * Run with:
 * php stack/themes/mrn-base-stack/tests/php/searchwp-form-contract.php
 *
 * @package mrn-base-stack
 */

namespace SearchWP {
	class Settings {
		public static function update( string $setting = '', $value = null ) {
			if ( 'forms' !== $setting || ! is_string( $value ) ) {
				return null;
			}

			\update_option( 'searchwp_forms', $value, false );

			return $value;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );

	$GLOBALS['mrn_searchwp_test_options'] = array(
		'searchwp_forms' => wp_json_encode(
			array(
				'forms'   => array(
					2 => array(
						'id'    => 2,
						'title' => 'Editorial Search',
					),
				),
				'next_id' => 3,
			)
		),
	);

	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}

	function apply_filters( $hook_name, $value ) {
		return $value;
	}

	function __( $text, $domain = 'default' ) {
		return $text;
	}

	function shortcode_exists( $shortcode ) {
		return 'searchwp_form' === $shortcode;
	}

	function absint( $value ) {
		return abs( (int) $value );
	}

	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['mrn_searchwp_test_options'] )
			? $GLOBALS['mrn_searchwp_test_options'][ $option ]
			: $default;
	}

	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['mrn_searchwp_test_options'][ $option ] = $value;

		return true;
	}

	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, is_array( $args ) ? $args : array() );
	}

	function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
		return json_encode( $value, (int) $flags, (int) $depth );
	}

	function mrn_searchwp_test_assert( $condition, $message ) {
		if ( $condition ) {
			return;
		}

		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}

	require dirname( __DIR__, 2 ) . '/inc/template-tags.php';

	mrn_base_stack_seed_searchwp_form();

	$stored = json_decode( (string) get_option( 'searchwp_forms', '' ), true );
	$forms  = isset( $stored['forms'] ) && is_array( $stored['forms'] ) ? $stored['forms'] : array();
	$site_search_forms = array_filter(
		$forms,
		static function ( $form ) {
			return is_array( $form ) && 'Site Search' === ( $form['title'] ?? '' );
		}
	);

	mrn_searchwp_test_assert( 2 === count( $forms ), 'provisioning preserves unrelated SearchWP forms' );
	mrn_searchwp_test_assert( 1 === count( $site_search_forms ), 'provisioning creates exactly one Site Search form' );
	mrn_searchwp_test_assert( 3 === (int) get_option( 'mrn_base_stack_searchwp_form_id', 0 ), 'canonical form ID is stored' );
	mrn_searchwp_test_assert( '3' === get_option( 'options_header_searchwp_form_id', '' ), 'canonical form is selected for a new header configuration' );
	mrn_searchwp_test_assert( '1' === get_option( 'options_header_show_search', '' ), 'header search defaults on for a new configuration' );

	mrn_base_stack_seed_searchwp_form();
	$stored_second = json_decode( (string) get_option( 'searchwp_forms', '' ), true );
	mrn_searchwp_test_assert( $stored === $stored_second, 'repeated provisioning is idempotent' );

	update_option( 'options_header_searchwp_form_id', '2', false );
	update_option( '_options_header_searchwp_form_id', 'field_mrn_theme_header_searchwp_form_id', false );
	update_option( 'options_header_show_search', '0', false );
	update_option( '_options_header_show_search', 'field_mrn_theme_header_show_search', false );
	mrn_base_stack_seed_searchwp_form();

	mrn_searchwp_test_assert( '2' === get_option( 'options_header_searchwp_form_id', '' ), 'a valid administrator-selected form is preserved' );
	mrn_searchwp_test_assert( '0' === get_option( 'options_header_show_search', '' ), 'an explicit administrator search toggle is preserved' );

	echo "PASS: SearchWP form provisioning contract.\n";
}

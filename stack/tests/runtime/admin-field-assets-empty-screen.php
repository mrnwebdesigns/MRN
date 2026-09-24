<?php
/**
 * Temporary local QA fixture for ACF's HPOS Orders-list enqueue contract.
 * Copy to local mu-plugins only for the test and remove afterwards.
 *
 * @package MRN_Stack_Tests
 */

if ( ! defined( 'ABSPATH' ) || ! preg_match( '/\.localhost$/', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
	return;
}
add_action(
	'admin_menu',
	static function () {
		$hook = add_management_page(
			'Empty ACF input QA',
			'Empty ACF input QA',
			'manage_options',
			'mrn-admin-field-assets-qa-empty',
			static function () {
				echo '<div class="wrap"><h1>Empty ACF input QA</h1><p>This local fixture requests ACF input and uploader assets without rendering any fields, matching the HPOS Orders-list initialization contract.</p></div>';
			}
		);
		add_action(
			'load-' . $hook,
			static function () {
				if ( function_exists( 'acf_enqueue_scripts' ) ) {
					acf_enqueue_scripts( array( 'uploader' => true ) );
				}
			}
		);
	}
);

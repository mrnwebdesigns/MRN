<?php
/**
 * Local integration checks for empty builders, options layouts and repeaters.
 *
 * @package mrn-base-stack
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) {
	throw new RuntimeException( 'An explicitly resolved Local Hub runtime is required.' );
}

/**
 * Require a runtime contract.
 *
 * @param bool   $condition Test result.
 * @param string $message Failure description.
 * @return void
 * @throws RuntimeException When a contract fails.
 */
function mrn_performance_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

$fixture_mode = $args[0] ?? 'check';
if ( 'check' === $fixture_mode ) {
	$empty_post = (int) get_option( 'page_on_front' );
	$counts     = array();
	foreach ( array( 'page_hero_rows', 'page_after_content_rows' ) as $name ) {
		mrn_performance_expect( mrn_base_stack_builder_field_is_empty( $name, $empty_post ), 'Expected an empty uncustomized fixture builder.' );
		$before = acf_get_store( 'local-fields' )->count();
		ob_start();
		$result = 'page_hero_rows' === $name ? mrn_base_stack_render_hero_builder( $empty_post ) : mrn_base_stack_render_after_content_builder( $empty_post );
		$html   = ob_get_clean();
		$added  = acf_get_store( 'local-fields' )->count() - $before;
		mrn_performance_expect( false === $result && '' === $html && 0 === $added, 'Empty render hydrated fields or emitted markup.' );
		$counts[ $name ] = $added;

		// Custom callbacks on any relevant ACF stage must keep the native path.
		$fixture_identity = static function ( $value ) {
			return $value;
		};
		foreach ( array( 'acf/pre_load_value', 'acf/load_value/name=' . $name, 'acf/format_value/key=field_mrn_' . $name, 'acf/load_field/name=' . $name, 'acf/validate_field', 'acf/pre_load_metadata', 'acf/pre_load_post_id' ) as $hook ) {
			add_filter( $hook, $fixture_identity );
			mrn_performance_expect( ! mrn_base_stack_builder_field_is_empty( $name, $empty_post ), 'Custom value/definition callback was skipped.' );
			remove_filter( $hook, $fixture_identity );
		}
		$local                         = acf_get_local_field( 'field_mrn_' . $name );
		$with_default                  = $local;
		$with_default['default_value'] = array( array( 'acf_fc_layout' => 'body_text' ) );
		acf_get_store( 'local-fields' )->set( $local['key'], $with_default );
		mrn_performance_expect( ! mrn_base_stack_builder_field_is_empty( $name, $empty_post ), 'A default was skipped.' );
		acf_get_store( 'local-fields' )->set( $local['key'], $local );
		acf_get_store( 'values' )->set( $empty_post . ':' . $name, array( array( 'acf_fc_layout' => 'body_text' ) ) );
		mrn_performance_expect( ! mrn_base_stack_builder_field_is_empty( $name, $empty_post ), 'A cached value was skipped.' );
		acf_get_store( 'values' )->remove( $empty_post . ':' . $name );
		$metadata = static function ( $value, $object_id, $meta_key ) use ( $name ) {
			return $meta_key === $name ? array( 'body_text' ) : $value;
		};
		add_filter( 'get_post_metadata', $metadata, 10, 3 );
		mrn_performance_expect( ! mrn_base_stack_builder_field_is_empty( $name, $empty_post ), 'WordPress metadata values were skipped.' );
		remove_filter( 'get_post_metadata', $metadata, 10 );
	}
	$GLOBALS['wp_query']->is_preview = true;
	mrn_performance_expect( ! mrn_base_stack_builder_field_is_empty( 'page_hero_rows', $empty_post ), 'Preview did not retain native behavior.' );
	$GLOBALS['wp_query']->is_preview = false;

	$repeaters = array();
	foreach ( acf_get_store( 'local-fields' )->get_data() as $local ) {
		if ( 'repeater' !== ( $local['type'] ?? '' ) || false === strpos( $local['key'], 'field_mrn_' ) || ! empty( $local['max'] ) ) {
			continue;
		}
		$field          = acf_get_field( $local['key'] );
		$field['value'] = array_fill( 0, 55, array() );
		$prepared       = acf_prepare_field( $field );
		$repeaters[]    = array(
			'key'              => $field['key'],
			'pagination'       => (bool) $field['pagination'],
			'max_with_55_rows' => $prepared['max'],
		);
		mrn_performance_expect( 55 === $prepared['max'], 'Legacy repeater rows were restricted.' );
		$field['value'] = array();
		$prepared       = acf_prepare_field( $field );
		mrn_performance_expect( empty( $field['pagination'] ) && 5 === $prepared['max'], 'Repeater ceiling was not applied.' );
	}
	// An inherited paginated definition must lose pagination when nested in a group.
	acf_get_store( 'local-fields' )->set(
		'field_mrn_qa_group',
		array(
			'key'           => 'field_mrn_qa_group',
			'type'          => 'group',
			'parent_layout' => 'layout_qa',
		)
	);
	$nested = mrn_base_stack_limit_repeater_definition(
		array(
			'key'        => 'field_mrn_qa_nested',
			'type'       => 'repeater',
			'parent'     => 'field_mrn_qa_group',
			'pagination' => 1,
		)
	);
	mrn_performance_expect( 0 === $nested['pagination'], 'Grouped nested repeater retained unsupported pagination.' );
	WP_CLI::line(
		wp_json_encode(
			array(
				'empty_added_definitions' => $counts,
				'fallback_contracts'      => 'pass',
				'repeaters'               => $repeaters,
			),
			JSON_PRETTY_PRINT
		)
	);
	return;
}

$state_path = getenv( 'MRN_PERFORMANCE_FIXTURE' );
if ( in_array( $fixture_mode, array( 'setup', 'readback', 'cleanup' ), true ) ) {
	mrn_performance_expect( (bool) $state_path, 'MRN_PERFORMANCE_FIXTURE is required.' );
	if ( 'setup' === $fixture_mode ) {
		mrn_performance_expect( ! file_exists( $state_path ), 'Fixture state already exists.' );
		$state = array();
		foreach ( array( 'page', 'mrn_reusable_faq' ) as $fixture_type ) {
			mrn_performance_expect( post_type_exists( $fixture_type ), 'Fixture post type is unavailable.' );
			$fixture_id = wp_insert_post(
				array(
					'post_type'   => $fixture_type,
					'post_status' => 'draft',
					'post_title'  => 'MRN Performance Repair QA',
					'meta_input'  => array( '_mrn_performance_fixture' => '2026-10-07' ),
				),
				true
			);
			mrn_performance_expect( ! is_wp_error( $fixture_id ), 'Cannot create owned fixture.' );
			$state[ $fixture_type ] = $fixture_id;
		}
		$stats = array();
		$faqs  = array();
		for ( $i = 1; $i <= 55; $i++ ) {
			$stats[] = array(
				'value'      => (string) $i,
				'item_label' => 'Legacy stat ' . $i,
			);
			if ( $i <= 25 ) {
				$faqs[] = array(
					'question' => 'Fixture question ' . $i,
					'answer'   => '<p>Fixture answer ' . $i . '</p>',
				);
			}
		}
		// Seed historical data only; normal ACF writes must obey the current ceiling.
		remove_filter( 'acf/pre_update_value', 'mrn_base_stack_guard_repeater_write', 5 );
		update_field(
			'field_mrn_page_content_rows',
			array(
				array(
					'acf_fc_layout' => 'stats',
					'stat_items'    => $stats,
				),
			),
			$state['page']
		);
		update_field( 'field_mrn_faq_items', $faqs, $state['mrn_reusable_faq'] );
		add_filter( 'acf/pre_update_value', 'mrn_base_stack_guard_repeater_write', 5, 4 );
		// Back up only the exact 404 options namespace before its native editor test.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read-only exact local fixture namespace snapshot.
		$state['options'] = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( 'options_not_found_' ) . '%', $wpdb->esc_like( '_options_not_found_' ) . '%' ), ARRAY_A );
		file_put_contents( $state_path, wp_json_encode( $state, JSON_PRETTY_PRINT ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Explicit local fixture state.
		WP_CLI::line(
			wp_json_encode(
				array(
					'page' => $state['page'],
					'faq'  => $state['mrn_reusable_faq'],
				)
			)
		);
		return;
	}
	$state = json_decode( file_get_contents( $state_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Explicit local fixture state.
	foreach ( array( 'page', 'mrn_reusable_faq' ) as $fixture_type ) {
		mrn_performance_expect( '2026-10-07' === get_post_meta( $state[ $fixture_type ], '_mrn_performance_fixture', true ), 'Fixture ownership is missing.' );
	}
	if ( 'readback' === $fixture_mode ) {
		$rows = get_field( 'page_content_rows', $state['page'] );
		$faqs = get_field( 'faq_items', $state['mrn_reusable_faq'] );
		mrn_performance_expect( 55 === count( $rows[0]['stat_items'] ), 'Legacy rows were lost.' );
		mrn_performance_expect( 25 === count( $faqs ), 'Legacy frontend values were truncated.' );
		WP_CLI::line(
			wp_json_encode(
				array(
					'legacy_stats' => count( $rows[0]['stat_items'] ),
					'last_stat'    => $rows[0]['stat_items'][54]['value'],
					'faqs'         => count( $faqs ),
					'last_faq'     => $faqs[24]['question'],
					'404_rows'     => get_field( 'not_found_content_rows', 'option' ),
				),
				JSON_PRETTY_PRINT
			)
		);
		return;
	}
	// Restore the saved options snapshot only after proving the test row is ours.
	$rows = get_field( 'not_found_content_rows', 'option' );
	mrn_performance_expect( ! $rows || false !== strpos( wp_json_encode( $rows ), 'MRN performance fixture' ), '404 content is not owned by this test; refusing restoration.' );
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read-only enumeration of the exact local fixture namespace.
	$keys = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( 'options_not_found_' ) . '%', $wpdb->esc_like( '_options_not_found_' ) . '%' ) );
	foreach ( $keys as $key ) {
		delete_option( $key );
	}
	foreach ( $state['options'] as $option ) {
		add_option( $option['option_name'], maybe_unserialize( $option['option_value'] ), '', $option['autoload'] );
	}
	foreach ( array( 'page', 'mrn_reusable_faq' ) as $fixture_type ) {
		wp_trash_post( $state[ $fixture_type ] );
	}
	WP_CLI::line( 'Owned fixtures moved to Trash and the exact 404 options snapshot restored.' );
	return;
}

if ( 'request' === $fixture_mode ) {
	$case   = $args[1] ?? 'valid';
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	wp_set_current_user( 'capability' === $case ? 0 : (int) $admins[0] );
	$key   = 'field_mrn_404_content_rows';
	$_POST = array(
		'field_key'  => $key,
		'post_id'    => 'target' === $case ? 'options_other' : 'options',
		'nonce'      => 'nonce' === $case ? 'invalid' : wp_create_nonce( 'acf_field_flexible_content_' . $key ),
		'input_name' => 'input' === $case ? 'acf[field_mrn_page_content_rows][' . $key . ']' : 'acf[' . $key . ']',
		'layout'     => 'layout' === $case ? 'nonexistent_layout' : 'body_text',
	);
	mrn_base_stack_ajax_builder_layout();
	return;
}

throw new RuntimeException( 'Unknown test mode.' );

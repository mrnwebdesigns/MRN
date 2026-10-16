<?php
/**
 * Local-only integration checks for the bounded ACF collection policy.
 *
 * Run through wp eval-file. All data belongs to disposable draft fixtures.
 *
 * @package mrn-base-stack
 */

$test_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ( 'platform.localhost' !== $test_host && ( ! str_ends_with( $test_host, '.localhost' ) || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) ) ) ) {
	throw new RuntimeException( 'An explicitly selected Local Hub runtime is required.' );
}

/**
 * Assert and record a runtime contract.
 *
 * @param bool   $condition Result.
 * @param string $label Contract.
 * @return void
 * @throws RuntimeException When a contract fails.
 */
function mrn_limit_expect( $condition, $label ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $label ) );
	}
	$GLOBALS['mrn_limit_results'][] = $label;
}

/**
 * Generate distinct FAQ rows.
 *
 * @param int $count Number of rows.
 * @return array<int, array<string, string>>
 */
function mrn_limit_rows( $count ) {
	$rows = array();
	for ( $i = 1; $i <= $count; $i++ ) {
		$rows[] = array(
			'question' => 'Limit fixture ' . $i,
			'answer'   => '<p>Answer ' . $i . '</p>',
		);
	}
	return $rows;
}

/**
 * Seed historical oversized data before re-enabling the policy.
 *
 * @param string $key Field key.
 * @param array  $value Fixture data.
 * @param int    $post_id Owned draft.
 * @return void
 */
function mrn_limit_seed_legacy( $key, $value, $post_id ) {
	remove_filter( 'acf/pre_update_value', 'mrn_base_stack_guard_repeater_write', 5 );
	try {
		update_field( $key, $value, $post_id );
	} finally {
		add_filter( 'acf/pre_update_value', 'mrn_base_stack_guard_repeater_write', 5, 4 );
	}
}

$fixture_id = wp_insert_post(
	array(
		'post_type'   => 'mrn_reusable_faq',
		'post_status' => 'draft',
		'post_title'  => 'MRN bounded repeater QA',
		'meta_input'  => array( '_mrn_repeater_limit_fixture' => 'bounded' ),
	),
	true
);
mrn_limit_expect( ! is_wp_error( $fixture_id ), 'Owned draft created' );
$keep_fixture = 'browser' === ( $args[0] ?? '' );
try {
	$key    = 'field_mrn_faq_items';
	$thirty = mrn_limit_rows( 30 );
	$over   = mrn_limit_rows( 31 );
	mrn_limit_expect( (bool) update_field( $key, mrn_limit_rows( 27 ), $fixture_id ), 'One set of 27 FAQs saves without splitting' );
	mrn_limit_expect( 27 === count( get_field( $key, $fixture_id ) ), 'All 27 FAQ questions survive readback' );
	mrn_limit_expect( (bool) update_field( $key, $thirty, $fixture_id ), 'Thirty FAQ rows save through update_field' );
	$before = get_post_meta( $fixture_id );
	mrn_limit_expect( false === update_field( $key, $over, $fixture_id ) && get_post_meta( $fixture_id ) === $before, 'FAQ row 31 rejected without any metadata changes' );
	// ACF's add_row returns the attempted count even when its write is refused.
	add_row( $key, $over[30], $fixture_id );
	mrn_limit_expect( get_post_meta( $fixture_id ) === $before, 'add_row cannot bypass the FAQ ceiling' );

	$field = acf_get_field( $key );
	acf_set_form_data( 'post_id', $fixture_id );
	mrn_limit_expect( true === mrn_base_stack_validate_repeater_limits( true, $thirty, $field, 'acf[' . $key . ']' ), 'Editor validation accepts 30 FAQs' );
	mrn_limit_expect( is_string( mrn_base_stack_validate_repeater_limits( true, $over, $field, 'acf[' . $key . ']' ) ), 'Editor validation reports FAQ row 31 before saving' );
	$schema = acf_get_field_type( 'repeater' )->get_rest_schema( $field );
	mrn_limit_expect( 30 === $schema['maxItems'] && is_wp_error( rest_validate_value_from_schema( $over, $schema ) ), 'REST schema rejects FAQ row 31' );
	foreach ( array( 1, 2, 5, 20, 0 ) as $configured ) {
		$definition = mrn_base_stack_limit_repeater_definition(
			array(
				'type' => 'repeater',
				'key'  => 'field_mrn_fixture',
				'max'  => $configured,
			)
		);
		mrn_limit_expect( ( $configured > 0 ? min( 5, $configured ) : 5 ) === $definition['max'], 'Configured maximum ' . $configured . ' is bounded correctly' );
	}
	$external = array(
		'type' => 'repeater',
		'key'  => 'field_external_fixture',
		'max'  => 0,
	);
	mrn_limit_expect( mrn_base_stack_limit_repeater_definition( $external ) === $external, 'Non-Stack fields remain unchanged' );

	$legacy = mrn_limit_rows( 32 );
	mrn_limit_seed_legacy( $key, $legacy, $fixture_id );
	$legacy[31]['question'] = 'Legacy FAQ row 32 edited';
	update_field( $key, $legacy, $fixture_id );
	mrn_limit_expect( 'Legacy FAQ row 32 edited' === get_field( $key, $fixture_id )[31]['question'], 'Existing oversized FAQ content remains editable' );
	$before = get_post_meta( $fixture_id );
	mrn_limit_expect( false === update_field( $key, mrn_limit_rows( 33 ), $fixture_id ) && get_post_meta( $fixture_id ) === $before, 'Legacy FAQ content cannot grow beyond its saved count' );
	update_field( $key, $thirty, $fixture_id );
	mrn_limit_expect( false === update_field( $key, $over, $fixture_id ), 'Reducing legacy FAQs to 30 removes the larger allowance' );

	// Exercise the actual Stack flexible-content field, including atomic refusal.
	$builder = 'field_mrn_page_content_rows';
	$stats   = array_fill(
		0,
		5,
		array(
			'value'      => '1',
			'item_label' => 'Fixture stat',
		)
	);
	$tree    = array(
		array(
			'acf_fc_layout' => 'stats',
			'stat_items'    => $stats,
		),
	);
	update_field( $builder, $tree, $fixture_id );
	mrn_limit_expect( 5 === count( get_field( $builder, $fixture_id )[0]['stat_items'] ), 'Nested five-row repeater saves' );
	$bad_tree                              = $tree;
	$bad_tree[0]['stat_items'][]           = $stats[0];
	$bad_tree[0]['stat_items'][0]['value'] = 'Must not persist';
	$before                                = get_post_meta( $fixture_id );
	mrn_limit_expect( false === update_field( $builder, $bad_tree, $fixture_id ) && get_post_meta( $fixture_id ) === $before, 'Oversized nested write rejected before any sibling or parent mutation' );
	mrn_limit_expect( false === update_sub_field( array( $builder, 1, 'stat_items' ), $bad_tree[0]['stat_items'], $fixture_id ) && get_post_meta( $fixture_id ) === $before, 'update_sub_field cannot bypass the ceiling' );

	$legacy_tree = array(
		array(
			'acf_fc_layout' => 'stats',
			'stat_items'    => array_fill( 0, 7, $stats[0] ),
		),
		array(
			'acf_fc_layout' => 'stats',
			'stat_items'    => array_fill( 0, 2, $stats[0] ),
		),
	);
	mrn_limit_seed_legacy( $builder, $legacy_tree, $fixture_id );
	$reordered                                    = array(
		'row-1' => $legacy_tree[1],
		'row-0' => $legacy_tree[0],
	);
	$reordered['row-0']['stat_items'][6]['value'] = 'Moved legacy seven';
	update_field( $builder, $reordered, $fixture_id );
	$readback = get_field( $builder, $fixture_id );
	mrn_limit_expect( 2 === count( $readback[0]['stat_items'] ) && 'Moved legacy seven' === $readback[1]['stat_items'][6]['value'], 'Reordering preserves the correct nested legacy allowance' );
	$before = get_post_meta( $fixture_id );
	$copy   = array( 'row-new' => $legacy_tree[0] );
	mrn_limit_expect( false === update_field( $builder, $copy, $fixture_id ) && get_post_meta( $fixture_id ) === $before, 'A newly added row cannot inherit a legacy allowance' );
	mrn_limit_expect( empty( $GLOBALS['mrn_repeater_checked_children'] ) && empty( $GLOBALS['mrn_repeater_checked_roots'] ), 'Nested write allowances expire after the parent save' );

	// Group wrapping must enforce the same complete-tree contract.
	acf_add_local_field_group(
		array(
			'key'    => 'group_mrn_limit_fixture',
			'title'  => 'Limit fixture',
			'fields' => array(
				array(
					'key'            => 'field_mrn_limit_group',
					'name'           => 'limit_group',
					'type'           => 'group',
					'sub_fields'     => array(
						array(
							'key'            => 'field_mrn_limit_rows',
							'name'           => 'rows',
							'type'           => 'repeater',
							'sub_fields'     => array(
								array(
									'key'  => 'field_mrn_limit_text',
									'name' => 'text',
									'type' => 'text',
								),
							),
						),
					),
				),
			),
		)
	);
	$group = array( 'rows' => array_fill( 0, 5, array( 'text' => 'Group fixture' ) ) );
	update_field( 'field_mrn_limit_group', $group, $fixture_id );
	mrn_limit_expect( 5 === count( get_field( 'field_mrn_limit_group', $fixture_id )['rows'] ), 'Group-wrapped repeater saves five' );
	$group['rows'][] = array( 'text' => 'Sixth' );
	$before          = get_post_meta( $fixture_id );
	mrn_limit_expect( false === update_field( 'field_mrn_limit_group', $group, $fixture_id ) && get_post_meta( $fixture_id ) === $before, 'Group-wrapped six-row write is atomic and rejected' );

	$options_field         = acf_get_field( 'field_mrn_limit_rows' );
	$options_field['key']  = 'field_mrn_limit_options_fixture';
	$options_field['name'] = 'mrn_limit_fixture_' . $fixture_id;
	acf_add_local_field( $options_field );
	$options_rows = array_fill( 0, 5, array( 'text' => 'Option fixture' ) );
	update_field( $options_field['key'], $options_rows, 'option' );
	$before_options = get_field( $options_field['key'], 'option' );
	mrn_limit_expect( 5 === count( $before_options ), 'Options repeater saves five' );
	$options_rows[] = array( 'text' => 'Sixth' );
	mrn_limit_expect( false === update_field( $options_field['key'], $options_rows, 'option' ) && get_field( $options_field['key'], 'option' ) === $before_options, 'Options repeater rejects six without changing saved values' );

	if ( $keep_fixture ) {
		delete_field( $builder, $fixture_id );
		delete_field( 'field_mrn_limit_group', $fixture_id );
	}
	WP_CLI::line(
		wp_json_encode(
			array(
				'checks'        => $GLOBALS['mrn_limit_results'],
				'browser_draft' => $keep_fixture ? $fixture_id : null,
			),
			JSON_PRETTY_PRINT
		)
	);
} finally {
	if ( isset( $options_field ) ) {
		delete_field( $options_field['key'], 'option' );
	}
	if ( ! $keep_fixture ) {
		wp_delete_post( $fixture_id, true );
	}
}

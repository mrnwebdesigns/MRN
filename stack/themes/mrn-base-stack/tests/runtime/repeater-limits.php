<?php
/**
 * Local-only integration checks for the five-row ACF write policy.
 *
 * Run through wp eval-file. All data belongs to disposable draft fixtures.
 *
 * @package mrn-base-stack
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'platform.localhost' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'The Platform Local Hub runtime is required.' );
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
		'post_title'  => 'MRN five-row repeater QA',
		'meta_input'  => array( '_mrn_repeater_limit_fixture' => 'five-row' ),
	),
	true
);
mrn_limit_expect( ! is_wp_error( $fixture_id ), 'Owned draft created' );
$keep_fixture = 'browser' === ( $args[0] ?? '' );
try {
	$key  = 'field_mrn_faq_items';
	$five = mrn_limit_rows( 5 );
	$six  = mrn_limit_rows( 6 );
	mrn_limit_expect( (bool) update_field( $key, $five, $fixture_id ), 'Five rows save through update_field' );
	$before = get_post_meta( $fixture_id );
	mrn_limit_expect( false === update_field( $key, $six, $fixture_id ) && get_post_meta( $fixture_id ) === $before, 'Six-row import rejected without any metadata changes' );
	// ACF's add_row returns the attempted count even when its write is refused.
	add_row( $key, $six[5], $fixture_id );
	mrn_limit_expect( get_post_meta( $fixture_id ) === $before, 'add_row cannot bypass the ceiling' );

	$field = acf_get_field( $key );
	acf_set_form_data( 'post_id', $fixture_id );
	mrn_limit_expect( true === mrn_base_stack_validate_repeater_limits( true, $five, $field, 'acf[' . $key . ']' ), 'Editor validation accepts five' );
	mrn_limit_expect( is_string( mrn_base_stack_validate_repeater_limits( true, $six, $field, 'acf[' . $key . ']' ) ), 'Editor validation reports six before saving' );
	$schema = acf_get_field_type( 'repeater' )->get_rest_schema( $field );
	mrn_limit_expect( 5 === $schema['maxItems'] && is_wp_error( rest_validate_value_from_schema( $six, $schema ) ), 'REST schema rejects six rows' );
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

	$seven = mrn_limit_rows( 7 );
	mrn_limit_seed_legacy( $key, $seven, $fixture_id );
	$seven[6]['question'] = 'Legacy row seven edited';
	update_field( $key, $seven, $fixture_id );
	mrn_limit_expect( 'Legacy row seven edited' === get_field( $key, $fixture_id )[6]['question'], 'Existing seven-row content remains editable' );
	$before = get_post_meta( $fixture_id );
	mrn_limit_expect( false === update_field( $key, mrn_limit_rows( 8 ), $fixture_id ) && get_post_meta( $fixture_id ) === $before, 'Legacy content cannot grow beyond its saved count' );
	update_field( $key, $five, $fixture_id );
	mrn_limit_expect( false === update_field( $key, $six, $fixture_id ), 'Reducing legacy content to five removes the larger allowance' );

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
		wp_trash_post( $fixture_id );
	}
}

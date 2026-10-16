<?php
/**
 * Qualify migration collection contracts on explicitly selected local fixtures.
 *
 * Run with wp eval-file and MRN_STACK_TEST_HOME set to this Local Hub home URL.
 *
 * @package mrn-base-stack
 */

$test_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! str_ends_with( $test_host, '.localhost' ) || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'An explicitly selected administrator-owned local runtime is required.' );
}

/**
 * Assert a native integration guarantee.
 *
 * @param bool   $condition Result.
 * @param string $message Contract.
 * @return void
 * @throws RuntimeException If the contract fails.
 */
function mrn_migration_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	$GLOBALS['mrn_migration_checks'][] = $message;
}

$fixture = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'draft',
		'post_title'  => 'Migration collection qualification',
	),
	true
);
mrn_migration_expect( ! is_wp_error( $fixture ) && $fixture > 0, 'Owned fixture created' );
$block = 0;
try {
	$expected = array(
		'faq_items'      => 30,
		'logo_items'     => 30,
		'showcase_items' => 12,
		'card_items'     => 12,
		'grid_items'     => 12,
		'slider_items'   => 12,
	);
	$content  = acf_get_field( 'field_mrn_page_content_rows' );
	$layouts  = array_column( $content['layouts'], null, 'name' );
	$found    = array();
	foreach ( $layouts as $layout ) {
		foreach ( $layout['sub_fields'] as $child ) {
			$name = $child['name'];
			if ( 'repeater' === $child['type'] && isset( $expected[ $name ] ) ) {
				mrn_migration_expect( $expected[ $name ] === $child['max'], $name . ' exposes its reviewed capacity' );
				$found[ $name ] = true;
			}
		}
	}
	mrn_migration_expect( count( $found ) === count( $expected ), 'Every affected native collection was checked' );
	foreach ( array(
		'logos'    => array( 'logo_items', 18, 30 ),
		'showcase' => array( 'showcase_items', 10, 12 ),
		'card'     => array( 'card_items', 6, 12 ),
		'grid'     => array( 'grid_items', 6, 12 ),
		'slider'   => array( 'slider_items', 10, 12 ),
	) as $layout => $collection ) {
		list( $name, $count, $maximum ) = $collection;
		$items                          = array_fill(
			0,
			$count,
			array(
				'heading' => 'Collection fixture',
				'image'   => 0,
			)
		);
		$rows                           = array(
			array(
				'acf_fc_layout' => $layout,
				$name           => $items,
			),
		);
		update_field( 'field_mrn_page_content_rows', $rows, $fixture );
		mrn_migration_expect( count( get_field( 'page_content_rows', $fixture )[0][ $name ] ) === $count, $name . ' preserves the required migration count' );
		$rows[0][ $name ] = array_fill( 0, $maximum + 1, $items[0] );
		$before           = get_post_meta( $fixture );
		mrn_migration_expect( false === update_field( 'field_mrn_page_content_rows', $rows, $fixture ) && get_post_meta( $fixture ) === $before, $name . ' refuses overflow atomically' );
	}
	$hero         = acf_get_field( 'field_mrn_page_hero_rows' );
	$hero_layouts = array_column( $hero['layouts'], null, 'name' );
	$hero_fields  = array_column( $hero_layouts['basic_block']['sub_fields'], null, 'name' );
	mrn_migration_expect( 4 === $hero_fields['links']['max'], 'Hero editor and API expose four links' );
	$fixture_link = array(
		'link' => array(
			'url'    => home_url( '/fixture/' ),
			'title'  => 'Fixture destination',
			'target' => '',
		),
	);
	$hero_rows    = array(
		array(
			'acf_fc_layout' => 'basic_block',
			'heading'       => 'Hero fixture',
			'links'         => array_fill( 0, 2, $fixture_link ),
		),
	);
	update_field( 'field_mrn_page_hero_rows', $hero_rows, $fixture );
	mrn_migration_expect( 2 === count( get_field( 'page_hero_rows', $fixture )[0]['links'] ), 'Two authored Hero links survive native save/readback' );
	$hero_rows[0]['links'] = array_fill( 0, 5, $fixture_link );
	$before                = get_post_meta( $fixture );
	mrn_migration_expect( false === update_field( 'field_mrn_page_hero_rows', $hero_rows, $fixture ) && get_post_meta( $fixture ) === $before, 'Fifth Hero link is refused atomically' );

	$faq_items = array();
	for ( $index = 1; $index <= 27; ++$index ) {
		$faq_items[] = array(
			'question' => 'Fixture question ' . $index,
			'answer'   => '<p>Fixture answer ' . $index . '</p>',
		);
	}
	$split = array(
		array(
			'acf_fc_layout'     => 'two_column_split',
			'column_ratio'      => '67-33',
			'left_column_rows'  => array(
				array(
					'acf_fc_layout' => 'faq',
					'faq_items'     => $faq_items,
				),
			),
			'right_column_rows' => array(
				array(
					'acf_fc_layout' => 'body_text',
					'heading'       => 'Aside fixture',
					'body_text'     => '<p>Adjacent authored content.</p>',
				),
			),
		),
	);
	update_field( 'field_mrn_page_content_rows', $split, $fixture );
	$readback = get_field( 'page_content_rows', $fixture );
	mrn_migration_expect( 27 === count( $readback[0]['left_column_rows'][0]['faq_items'] ), 'Split column preserves one complete 27-question FAQ' );
	mrn_migration_expect( 'Aside fixture' === $readback[0]['right_column_rows'][0]['heading'], 'Adjacent content survives the same save' );
	ob_start();
	mrn_base_stack_render_builder_row( $readback[0], $fixture, 0 );
	$markup = ob_get_clean();
	mrn_migration_expect( 27 === substr_count( $markup, 'class="mrn-faq__trigger' ) || 27 === substr_count( $markup, 'class="mrn-faq__question' ), 'Native split renders all 27 questions' );
	mrn_migration_expect( str_contains( $markup, 'Adjacent authored content.' ), 'Native split renders the aside' );
	$bad                                        = $split;
	$bad[0]['left_column_rows'][0]['faq_items'] = array_fill( 0, 31, $faq_items[0] );
	$bad[0]['right_column_rows'][0]['heading']  = 'Must not persist';
	$before                                     = get_post_meta( $fixture );
	mrn_migration_expect( false === update_field( 'field_mrn_page_content_rows', $bad, $fixture ) && get_post_meta( $fixture ) === $before, 'Nested FAQ overflow refuses the whole tree before changing its aside' );

	$block = wp_insert_post(
		array(
			'post_type'   => 'mrn_reusable_faq',
			'post_status' => 'draft',
			'post_title'  => 'Owned reusable fixture',
		),
		true
	);
	mrn_migration_expect( ! is_wp_error( $block ) && $block > 0, 'Owned reusable fixture created' );
	update_field( 'field_mrn_faq_items', array( $faq_items[0] ), $block );
	update_field( 'field_mrn_faq_section_width', 'wide', $block );
	$row = array(
		'acf_fc_layout'                 => 'reusable_block',
		'block'                         => $block,
		'section_width'                 => 'full-width',
		'row_spacing_margin_top_preset' => '0px',
	);
	update_field( 'field_mrn_page_content_rows', array( $row ), $fixture );
	$saved = get_field( 'page_content_rows', $fixture )[0];
	mrn_migration_expect( 'full-width' === $saved['section_width'] && '0px' === $saved['row_spacing_margin_top_preset'], 'Reusable instance width and native spacing survive API save/readback' );
	$resolved = mrn_base_stack_get_reusable_block_builder_row( get_post( $block ), $saved );
	mrn_migration_expect( 'full-width' === $resolved['section_width'] && 'wide' === get_field( 'field_mrn_faq_section_width', $block ), 'Instance width overrides rendering without changing the reusable source' );

	$entries = array();
	foreach ( array( 'terms', 'privacy', 'cookies', 'accessibility', 'contact' ) as $anchor ) {
		$entries[] = array(
			'label'  => ucfirst( $anchor ),
			'anchor' => $anchor,
		);
	}
	$nav = array(
		'acf_fc_layout'    => 'faq_jump_nav',
		'jump_nav_source'  => 'manual',
		'jump_nav_entries' => $entries,
	);
	update_field( 'field_mrn_page_content_rows', array( $nav ), $fixture );
	$saved            = get_field( 'page_content_rows', $fixture )[0];
	$resolved_entries = mrn_base_stack_get_jump_nav_row_entries( $saved, $fixture );
	mrn_migration_expect( array_column( $entries, 'anchor' ) === array_column( $resolved_entries, 'anchor' ) && array_column( $entries, 'label' ) === array_column( $resolved_entries, 'label' ), 'Five manual section links preserve authored order' );
	ob_start();
	mrn_base_stack_render_builder_row( $saved, $fixture, 0 );
	$markup = ob_get_clean();
	mrn_migration_expect( str_contains( $markup, 'aria-label="Page sections"' ) && str_contains( $markup, 'href="#privacy"' ), 'Manual navigation uses native semantic links and an appropriate accessible label' );
	mrn_migration_expect( mrn_base_stack_get_faq_jump_nav_entries( $fixture ) === mrn_base_stack_get_jump_nav_row_entries( array(), $fixture ), 'Unset navigation source retains automatic FAQ discovery' );
	WP_CLI::line( wp_json_encode( array( 'checks' => $GLOBALS['mrn_migration_checks'] ), JSON_PRETTY_PRINT ) );
} finally {
	if ( is_int( $block ) && $block > 0 ) {
		wp_delete_post( $block, true );
	}
	wp_delete_post( $fixture, true );
}

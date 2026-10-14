<?php
/**
 * Read-only regression for saved tab rows in either ACF field load order.
 *
 * Run each order in a fresh process:
 * wp --context=admin eval-file acf-tabbed-load-order.php <post-id> panel-first
 * wp --context=admin eval-file acf-tabbed-load-order.php <post-id> page-first
 *
 * @package mrn-base-stack
 */

if ( ! function_exists( 'acf_get_field' ) || ! function_exists( 'acf_set_form_data' ) ) {
	WP_CLI::error( 'WordPress with ACF PRO is required.' );
}

$mrn_tabbed_post_id = absint( $args[0] ?? 0 );
$mrn_tabbed_order   = $args[1] ?? 'panel-first';
if ( ! get_post( $mrn_tabbed_post_id ) || ! in_array( $mrn_tabbed_order, array( 'panel-first', 'page-first' ), true ) ) {
	WP_CLI::error( 'Supply a saved post ID and panel-first or page-first.' );
}

/**
 * Find the tab-panel schema within the loaded Content field.
 *
 * @param array $tree Field tree.
 * @return array|null
 */
function mrn_tabbed_load_order_find_panel( array $tree ) {
	if ( 'field_mrn_tabbed_layout_panel_rows' === ( $tree['key'] ?? '' ) ) {
		return $tree;
	}
	foreach ( $tree as $child ) {
		if ( ! is_array( $child ) ) {
			continue;
		}
		$match = mrn_tabbed_load_order_find_panel( $child );
		if ( null !== $match ) {
			return $match;
		}
	}
	return null;
}

$mrn_tabbed_before = hash( 'sha256', serialize( get_post_meta( $mrn_tabbed_post_id ) ) );
acf_set_form_data( 'post_id', $mrn_tabbed_post_id );
if ( 'panel-first' === $mrn_tabbed_order ) {
	acf_get_field( 'field_mrn_tabbed_layout_panel_rows' );
}
$mrn_tabbed_rows  = get_field( 'page_content_rows', $mrn_tabbed_post_id );
$mrn_tabbed_field = acf_get_field( 'field_mrn_page_content_rows' );
$mrn_tabbed_panel = mrn_tabbed_load_order_find_panel( $mrn_tabbed_field );
if ( empty( $mrn_tabbed_panel['layouts'] ) ) {
	WP_CLI::error( 'The Content field cached an empty nested tab-panel schema.' );
}

$mrn_tabbed_checked = 0;
foreach ( (array) get_post_meta( $mrn_tabbed_post_id, 'page_content_rows', true ) as $mrn_tabbed_index => $mrn_tabbed_layout ) {
	if ( 'tabbed_layout' !== $mrn_tabbed_layout ) {
		continue;
	}
	$mrn_tabbed_prefix = 'page_content_rows_' . $mrn_tabbed_index . '_tabs';
	$mrn_tabbed_count  = absint( get_post_meta( $mrn_tabbed_post_id, $mrn_tabbed_prefix, true ) );
	for ( $mrn_tabbed_tab = 0; $mrn_tabbed_tab < $mrn_tabbed_count; $mrn_tabbed_tab++ ) {
		$mrn_tabbed_name   = $mrn_tabbed_prefix . '_' . $mrn_tabbed_tab . '_panel_rows';
		$mrn_tabbed_saved  = get_post_meta( $mrn_tabbed_post_id, $mrn_tabbed_name, true );
		$mrn_tabbed_loaded = $mrn_tabbed_rows[ $mrn_tabbed_index ]['tabs'][ $mrn_tabbed_tab ]['panel_rows'] ?? false;
		if ( ! is_array( $mrn_tabbed_saved ) || ! is_array( $mrn_tabbed_loaded ) || count( $mrn_tabbed_saved ) !== count( $mrn_tabbed_loaded ) ) {
			WP_CLI::error( $mrn_tabbed_name . ' did not load its saved rows.' );
		}
		$mrn_tabbed_render           = $mrn_tabbed_panel;
		$mrn_tabbed_render['name']    = $mrn_tabbed_name;
		$mrn_tabbed_render['prefix']  = 'acf';
		$mrn_tabbed_render['value']   = acf_get_value( $mrn_tabbed_post_id, $mrn_tabbed_render );
		ob_start();
		acf_render_field_wrap( $mrn_tabbed_render );
		$mrn_tabbed_html = ob_get_clean();
		foreach ( $mrn_tabbed_saved as $mrn_tabbed_row => $mrn_tabbed_saved_layout ) {
			if ( $mrn_tabbed_saved_layout !== ( $mrn_tabbed_loaded[ $mrn_tabbed_row ]['acf_fc_layout'] ?? '' ) ) {
				WP_CLI::error( $mrn_tabbed_name . ' changed its saved layout.' );
			}
			$mrn_tabbed_heading = get_post_meta( $mrn_tabbed_post_id, $mrn_tabbed_name . '_' . $mrn_tabbed_row . '_heading', true );
			if ( '' !== $mrn_tabbed_heading && $mrn_tabbed_heading !== ( $mrn_tabbed_loaded[ $mrn_tabbed_row ]['heading'] ?? '' ) ) {
				WP_CLI::error( $mrn_tabbed_name . ' did not load its saved heading.' );
			}
			if ( false === strpos( $mrn_tabbed_html, 'data-layout="' . esc_attr( $mrn_tabbed_saved_layout ) . '"' ) || ( '' !== $mrn_tabbed_heading && false === strpos( $mrn_tabbed_html, esc_attr( $mrn_tabbed_heading ) ) ) ) {
				WP_CLI::error( $mrn_tabbed_name . ' did not render its saved editor controls.' );
			}
			++$mrn_tabbed_checked;
		}
	}
}
if ( ! $mrn_tabbed_checked || $mrn_tabbed_before !== hash( 'sha256', serialize( get_post_meta( $mrn_tabbed_post_id ) ) ) ) {
	WP_CLI::error( 'No saved tab rows were checked, or post metadata changed.' );
}
WP_CLI::success( $mrn_tabbed_order . ': ' . $mrn_tabbed_checked . ' saved tab rows loaded with intact metadata.' );

<?php
/**
 * Content table-of-contents helpers.
 *
 * @package mrn-base-stack
 */

/**
 * Add anchor IDs to selected headings and return their TOC entries.
 *
 * @param string               $html    Filtered content HTML.
 * @param array<string, mixed> $options Preparation options.
 * @return array{html: string, items: array<int, array{id: string, text: string, level: int}>}
 */
function mrn_base_stack_prepare_content_toc( $html, array $options = array() ) {
	$html  = (string) $html;
	$items = array();

	if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
		return array(
			'html'  => $html,
			'items' => $items,
		);
	}

	$heading_levels = isset( $options['heading_levels'] ) && is_array( $options['heading_levels'] )
		? $options['heading_levels']
		: array( 2, 3 );
	$heading_levels = array_values(
		array_filter(
			array_map( 'absint', $heading_levels ),
			static function ( $level ) {
				return $level >= 1 && $level <= 6;
			}
		)
	);
	$heading_levels = apply_filters( 'mrn_base_stack_content_toc_heading_levels', $heading_levels, $html, $options );
	$heading_levels = array_values( array_unique( array_map( 'absint', (array) $heading_levels ) ) );

	if ( empty( $heading_levels ) ) {
		return array(
			'html'  => $html,
			'items' => $items,
		);
	}

	$root_id = isset( $options['root_id'] ) ? sanitize_key( (string) $options['root_id'] ) : 'mrn-content-toc-root';
	$root_id = '' !== $root_id ? $root_id : 'mrn-content-toc-root';

	$dom    = new DOMDocument();
	$prev   = libxml_use_internal_errors( true );
	$loaded = $dom->loadHTML(
		'<?xml encoding="utf-8" ?><div id="' . esc_attr( $root_id ) . '">' . $html . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );

	if ( ! $loaded ) {
		return array(
			'html'  => $html,
			'items' => $items,
		);
	}

	$xpath   = new DOMXPath( $dom );
	$queries = array_map(
		static function ( $level ) {
			return '//h' . (int) $level;
		},
		$heading_levels
	);
	$nodes   = $xpath->query( implode( ' | ', $queries ) );
	$used    = array();

	if ( false === $nodes ) {
		return array(
			'html'  => $html,
			'items' => $items,
		);
	}

	foreach ( $nodes as $node ) {
		if ( ! $node instanceof DOMElement ) {
			continue;
		}

		// DOMNode exposes camelCase properties; keep the names required by PHP's DOM API.
		$text = trim( preg_replace( '/\s+/', ' ', (string) $node->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOM API property.
		if ( '' === $text ) {
			continue;
		}

		$id = sanitize_title( (string) $node->getAttribute( 'id' ) );
		if ( '' === $id ) {
			$id = sanitize_title( $text );
		}
		if ( '' === $id ) {
			$id = 'section';
		}

		$base   = $id;
		$suffix = 2;
		while ( isset( $used[ $id ] ) ) {
			$id = $base . '-' . $suffix;
			++$suffix;
		}

		$node->setAttribute( 'id', $id );
		$used[ $id ] = true;
		$items[]     = array(
			'id'    => $id,
			'text'  => $text,
			'level' => (int) substr( $node->nodeName, 1 ), // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOM API property.
		);
	}

	$root   = $xpath->query( '//div[@id="' . $root_id . '"]' )->item( 0 );
	$output = '';
	if ( $root ) {
		foreach ( $root->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOM API property.
			$output .= $dom->saveHTML( $child );
		}
	}

	return array(
		'html'  => '' !== trim( $output ) ? $output : $html,
		'items' => $items,
	);
}

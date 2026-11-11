<?php
/**
 * Optional service tabs, term dropdown filters, reset and load-more for project lists.
 *
 * Every queried record stays in the document. Controls are revealed only by the
 * front-end enhancement script, so the unfiltered list works without JavaScript.
 *
 * @package mrn-base-stack
 */

/** Whether the project list selected the taxonomy controls. */
function mrn_base_stack_is_project_filtered( array $row ) {
	return mrn_base_stack_is_project_list( $row ) && 'taxonomy-controls' === ( $row['project_filters'] ?? '' );
}

/** Editor fields, shown only for Project Cards. */
function mrn_base_stack_get_project_filter_fields( $seed ) {
	$make   = static function ( $name, $type, array $extra = array() ) use ( $seed ) {
		return array_merge(
			array(
				'key'           => $seed . '_project_filter_' . $name,
				'name'          => 'project_filter_' . $name,
				'label'         => 'Project Filter ' . ucwords( str_replace( '_', ' ', $name ) ),
				'type'          => $type,
				'default_value' => '',
			),
			$extra
		);
	};
	$terms  = array(
		'' => 'Use Existing Records Only',
		'all' => 'All Terms',
		'first' => 'First Term Only',
	);
	$fields = array(
		$make(
			'mode',
			'select',
			array(
				'name'         => 'project_filters',
				'key'          => $seed . '_project_filters',
				'label'        => 'Project Filters',
				'choices'      => array(
					''                  => 'No Filters',
					'taxonomy-controls' => 'Service Tabs, Term Dropdowns and Load More',
				),
				'instructions' => 'Reveals tabs and dropdowns only when JavaScript is available; every queried record stays in the document.',
			)
		),
		$make( 'tabs_taxonomy', 'text', array( 'instructions' => 'Taxonomy whose terms become the tab row. Blank hides the tabs.' ) ),
		$make( 'tabs_excluded', 'text', array( 'instructions' => 'Term slugs, separated by commas, kept out of the tab row (their records still appear under All).' ) ),
		$make( 'tabs_all_label', 'text', array( 'default_value' => 'All' ) ),
		$make( 'tabs_label', 'text', array( 'default_value' => 'Filter by service' ) ),
		$make( 'one_taxonomy', 'text', array( 'instructions' => 'Taxonomy for the first dropdown. Blank hides it.' ) ),
		$make( 'one_label', 'text', array( 'default_value' => 'Industries' ) ),
		$make( 'one_title', 'text', array( 'default_value' => 'Filter by Industry' ) ),
		$make( 'one_terms', 'select', array( 'choices' => array( 'all' => 'Match Any Term', 'first' => 'Match First Term Only' ), 'default_value' => 'all' ) ),
		$make( 'two_taxonomy', 'text', array( 'instructions' => 'Taxonomy for the second dropdown. Blank hides it.' ) ),
		$make( 'two_label', 'text', array( 'default_value' => 'Features' ) ),
		$make( 'two_title', 'text', array( 'default_value' => 'Filter by Feature' ) ),
		$make( 'two_terms', 'select', array( 'choices' => array( 'all' => 'Match Any Term', 'first' => 'Match First Term Only' ), 'default_value' => 'all' ) ),
		$make( 'reset_label', 'text', array( 'default_value' => 'Reset filters' ) ),
		$make( 'empty_label', 'text', array( 'default_value' => 'No posts found.' ) ),
		$make( 'initial', 'number', array( 'min' => 1, 'max' => 1000, 'step' => 1, 'default_value' => 9, 'instructions' => 'Records shown before Load More.' ) ),
		$make( 'batch', 'number', array( 'min' => 1, 'max' => 1000, 'step' => 1, 'default_value' => 6 ) ),
		$make( 'more_label', 'text', array( 'default_value' => 'Load More' ) ),
		$make( 'media_ratio', 'text', array( 'name' => 'project_media_ratio', 'key' => $seed . '_project_media_ratio', 'label' => 'Project Media Ratio', 'instructions' => 'Optional positive width/height ratio, for example 360/420. Replaces the fixed media height.' ) ),
	);
	foreach ( $fields as &$field ) {
		$field['conditional_logic'] = array( array( array( 'field' => $seed . '_record_presentation', 'operator' => '==', 'value' => 'projects' ) ) );
	}
	unset( $field );
	return $fields;
}

/** A taxonomy name that is registered for the source post type, else empty. */
function mrn_base_stack_get_project_filter_taxonomy( array $row, $part, $post_type ) {
	$name = $row[ 'project_filter_' . $part . '_taxonomy' ] ?? '';
	return is_string( $name ) && taxonomy_exists( $name ) && is_object_in_taxonomy( $post_type, $name ) ? $name : '';
}

/** Visible term map for one group, in name order. */
function mrn_base_stack_get_project_filter_terms( $taxonomy, array $excluded = array() ) {
	if ( '' === $taxonomy ) {
		return array();
	}
	$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	return array_values( array_filter( $terms, static function ( $term ) use ( $excluded ) {
		return ! in_array( $term->slug, $excluded, true );
	} ) );
}

function mrn_base_stack_get_project_filter_label( array $row, $part, $default ) {
	$value = $row[ 'project_filter_' . $part ] ?? '';
	return is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : $default;
}

/** Slug list for one card, using the group's term matching rule. */
function mrn_base_stack_get_project_filter_card_slugs( WP_Post $post, $taxonomy, $first_only ) {
	if ( '' === $taxonomy ) {
		return '';
	}
	$terms = get_the_terms( $post->ID, $taxonomy );
	if ( ! is_array( $terms ) || ! $terms ) {
		return '';
	}
	if ( $first_only ) {
		$terms = array( reset( $terms ) );
	}
	return implode( ' ', array_map( static function ( $term ) { return sanitize_html_class( $term->slug ); }, $terms ) );
}

/** data-* attributes for one record, empty unless filters are on. */
function mrn_base_stack_get_project_filter_item_attributes( WP_Post $post, array $row, $index = -1 ) {
	if ( ! mrn_base_stack_is_project_filtered( $row ) ) {
		return '';
	}
	$out = '';
	foreach ( array( 'tabs' => 'tabs', 'one' => 'one', 'two' => 'two' ) as $part => $attribute ) {
		$taxonomy = mrn_base_stack_get_project_filter_taxonomy( $row, $part, $post->post_type );
		$slugs    = mrn_base_stack_get_project_filter_card_slugs( $post, $taxonomy, 'first' === ( $row[ 'project_filter_' . $part . '_terms' ] ?? ( 'tabs' === $part ? 'all' : 'all' ) ) );
		$out     .= ' data-project-' . $attribute . '="' . esc_attr( $slugs ) . '"';
	}
	// Records past the first batch stay display:none until the script is ready, so their lazy images never start loading.
	$initial = max( 1, min( 1000, absint( $row['project_filter_initial'] ?? 9 ) ) );
	return $out . ' data-project-card' . ( (int) $index >= $initial ? ' data-project-late' : '' );
}

/** Controls above (tabs, dropdowns, reset) or below (status, load more) the list. */
function mrn_base_stack_get_project_filter_controls( array $row, $post_type, $after = false ) {
	if ( ! mrn_base_stack_is_project_filtered( $row ) ) {
		return '';
	}
	if ( $after ) {
		$more = esc_html( mrn_base_stack_get_project_filter_label( $row, 'more_label', __( 'Load More', 'mrn-base-stack' ) ) );
		return '<div class="mrn-project-filters__more" data-project-controls hidden><button type="button" data-project-more>' . $more . ' <svg aria-hidden="true" focusable="false" width="12" height="10" viewBox="0 0 12 10"><path d="M4.608 9.952L9.584 4.96 4.624 0H6.4L11.36 4.976 6.384 9.952ZM0 4.32H10.56V5.632H0Z" fill="currentColor"/></svg></button></div><p class="screen-reader-text" role="status" aria-live="polite" data-project-status></p>';
	}
	$excluded = array_filter( array_map( 'sanitize_title', explode( ',', (string) ( $row['project_filter_tabs_excluded'] ?? '' ) ) ) );
	$html     = '<div class="mrn-project-filters" data-project-controls hidden>';
	$tabs_tax = mrn_base_stack_get_project_filter_taxonomy( $row, 'tabs', $post_type );
	$tabs     = mrn_base_stack_get_project_filter_terms( $tabs_tax, $excluded );
	if ( $tabs ) {
		$html .= '<div class="mrn-project-filters__tabs" role="group" aria-label="' . esc_attr( mrn_base_stack_get_project_filter_label( $row, 'tabs_label', __( 'Filter by service', 'mrn-base-stack' ) ) ) . '"><button type="button" data-project-tab="*" aria-pressed="true">' . esc_html( mrn_base_stack_get_project_filter_label( $row, 'tabs_all_label', __( 'All', 'mrn-base-stack' ) ) ) . '</button>';
		foreach ( $tabs as $term ) {
			$html .= '<button type="button" data-project-tab="' . esc_attr( sanitize_html_class( $term->slug ) ) . '" aria-pressed="false">' . esc_html( $term->name ) . '</button>';
		}
		$html .= '</div>';
	}
	$html .= '<div class="mrn-project-filters__bar">';
	foreach ( array( 'one', 'two' ) as $part ) {
		$taxonomy = mrn_base_stack_get_project_filter_taxonomy( $row, $part, $post_type );
		$terms    = mrn_base_stack_get_project_filter_terms( $taxonomy );
		if ( ! $terms ) {
			continue;
		}
		$id     = 'mrn-project-filter-' . $part . '-' . wp_unique_id();
		$title  = mrn_base_stack_get_project_filter_label( $row, $part . '_title', '' );
		$html  .= '<div class="mrn-project-filters__group" data-project-group="' . esc_attr( $part ) . '"><button type="button" class="mrn-project-filters__trigger" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '"><span class="mrn-project-filters__badge" data-project-badge hidden aria-hidden="true">0</span><span>' . esc_html( mrn_base_stack_get_project_filter_label( $row, $part . '_label', 'one' === $part ? __( 'Industries', 'mrn-base-stack' ) : __( 'Features', 'mrn-base-stack' ) ) ) . '</span><svg class="mrn-project-filters__chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg></button>';
		$html  .= '<div class="mrn-project-filters__panel" id="' . esc_attr( $id ) . '" role="group" aria-label="' . esc_attr( $title ) . '" hidden><ul>';
		foreach ( $terms as $term ) {
			$slug  = sanitize_html_class( $term->slug );
			$html .= '<li data-project-term="' . esc_attr( $slug ) . '"><input type="checkbox" id="' . esc_attr( $id . '-' . $slug ) . '" value="' . esc_attr( $slug ) . '"><label for="' . esc_attr( $id . '-' . $slug ) . '">' . esc_html( $term->name ) . '</label><span class="mrn-project-filters__count" data-project-count>' . esc_html( (string) $term->count ) . '</span></li>';
		}
		$html .= '</ul></div></div>';
	}
	$html .= '<button type="button" class="mrn-project-filters__reset" data-project-reset disabled><span>' . esc_html( mrn_base_stack_get_project_filter_label( $row, 'reset_label', __( 'Reset filters', 'mrn-base-stack' ) ) ) . '</span> <span data-project-reset-count aria-hidden="true"></span></button></div></div>';
	return $html;
}

/** Attributes the enhancement script reads from the section. */
function mrn_base_stack_get_project_filter_contract( array $row ) {
	$out = array( 'classes' => array(), 'attributes' => array() );
	if ( ! mrn_base_stack_is_project_filtered( $row ) ) {
		return $out;
	}
	$out['classes'][] = 'has-project-filters';
	$initial          = max( 1, min( 1000, absint( $row['project_filter_initial'] ?? 9 ) ) );
	$batch            = max( 1, min( 1000, absint( $row['project_filter_batch'] ?? 6 ) ) );
	$out['attributes']['data-mrn-project-filters']    = (string) $initial . ',' . (string) $batch;
	$out['attributes']['data-project-empty']          = mrn_base_stack_get_project_filter_label( $row, 'empty_label', __( 'No posts found.', 'mrn-base-stack' ) );
	$out['attributes']['data-project-status-message'] = __( 'Showing %1$d of %2$d projects', 'mrn-base-stack' );
	return $out;
}

/** Positive width/height ratio, or empty. */
function mrn_base_stack_get_project_media_ratio( array $row ) {
	$ratio = $row['project_media_ratio'] ?? '';
	return is_string( $ratio ) && preg_match( '/^(\d{1,4}(?:\.\d{1,3})?)\s*\/\s*(\d{1,4}(?:\.\d{1,3})?)$/D', trim( $ratio ), $m ) && (float) $m[1] > 0 && (float) $m[2] > 0 ? $m[1] . ' / ' . $m[2] : '';
}

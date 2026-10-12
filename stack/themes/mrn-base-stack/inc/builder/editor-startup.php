<?php
/**
 * Start native builder rows closed and defer their rich-text widgets.
 *
 * @package mrn-base-stack
 */

/**
 * Identify the supported Classic Editor request, excluding template AJAX.
 *
 * @return bool
 */
function mrn_base_stack_is_builder_startup_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return is_admin() && ! wp_doing_ajax() && $screen instanceof WP_Screen
		&& 'post' === $screen->base && mrn_base_stack_admin_is_safe_acf_editor_helper_screen( $screen )
		&& in_array( $screen->post_type, mrn_base_stack_get_singular_shell_post_types(), true );
}

/**
 * Resolve the simple saved layouts needed by this initial editor request.
 *
 * Complex/nested layouts retain complete native discovery. POST, AJAX, CLI,
 * REST and public requests always use the complete definitions.
 *
 * @return array<int, string>|null Null preserves complete native discovery.
 */
function mrn_base_stack_get_initial_builder_source_names() {
	$method = sanitize_text_field( wp_unslash( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) );
	if ( ! mrn_base_stack_is_builder_startup_screen() || 'GET' !== $method
		|| ! class_exists( '\ACF\Pro\Fields\FlexibleContent\Layout' ) ) {
		return null;
	}
	$post_id = mrn_base_stack_get_builder_layout_allowlist_post_id();
	if ( ! $post_id ) {
		return null;
	}
	$names = array();
	foreach ( array_keys( mrn_base_stack_get_builder_layout_allowlist_targets() ) as $name ) {
		$names = array_merge( $names, mrn_base_stack_get_builder_layout_allowlist_used_layout_names( $post_id, $name ) );
	}
	$names = array_values( array_unique( $names ) );
	return array_diff( $names, array( 'basic', 'body_text', 'basic_block', 'cta', 'cta_block', 'image_content', 'reusable_block' ) ) ? null : $names;
}

/**
 * Hydrate saved simple layouts without loading unused nested field trees.
 *
 * @param array<string, mixed> $field Raw local Content field.
 * @return array|null Render catalog or null for native full hydration.
 */
function mrn_base_stack_get_initial_builder_source_layouts( array $field ) {
	if ( 'field_mrn_page_content_rows' !== ( $field['key'] ?? '' ) || ! function_exists( 'acf_get_local_fields' ) ) {
		return null;
	}
	$names = mrn_base_stack_get_initial_builder_source_names();
	if ( null === $names || ! is_array( $field['layouts'] ?? null ) ) {
		return null;
	}
	$layouts = $field['layouts'];
	$keys = array();
	foreach ( $layouts as &$layout ) {
		if ( ! is_array( $layout ) || empty( $layout['key'] ) ) {
			return null;
		}
		$layout['sub_fields'] = array();
		if ( in_array( $layout['name'] ?? '', $names, true ) ) {
			$keys[ $layout['key'] ] = true;
		}
	}
	unset( $layout );
	$children = (array) acf_get_local_fields( $field['key'] );
	foreach ( $children as $child ) {
		if ( empty( $child['parent_layout'] ) ) {
			return null;
		}
	}
	$loaded = array();
	foreach ( $children as $child ) {
		if ( isset( $keys[ $child['parent_layout'] ] ) ) {
			$loaded[] = acf_get_field( $child['key'] );
		}
	}
	// Preserve ACF's seamless clone expansion and the normal parent filters.
	$loaded = apply_filters( 'acf/load_fields', $loaded, $field );
	$grouped = array();
	foreach ( $loaded as $child ) {
		if ( is_array( $child ) && isset( $keys[ $child['parent_layout'] ?? '' ] ) ) {
			$grouped[ $child['parent_layout'] ][] = $child;
		}
	}
	foreach ( $layouts as &$layout ) {
		$layout['sub_fields'] = $grouped[ $layout['key'] ] ?? array();
	}
	unset( $layout );
	return $layouts;
}

/**
 * Bypass ACF's eager expansion only for an initial simple Content catalog.
 *
 * @param array $field Local flexible-content field.
 * @return array
 */
function mrn_base_stack_stage_initial_builder_catalog( $field ) {
	if ( ! is_array( $field ) || 'field_mrn_page_content_rows' !== ( $field['key'] ?? '' )
		|| ! is_array( $field['layouts'] ?? null ) || null === mrn_base_stack_get_initial_builder_source_names() ) {
		return $field;
	}
	$field['_mrn_initial_layouts'] = $field['layouts'];
	$field['layouts'] = array();
	return $field;
}
add_filter( 'acf/load_field/type=flexible_content', 'mrn_base_stack_stage_initial_builder_catalog', 5 );

/**
 * Restore native layout metadata and complete saved simple controls.
 *
 * @param array $field Local flexible-content field.
 * @return array
 */
function mrn_base_stack_restore_initial_builder_catalog( $field ) {
	if ( ! is_array( $field ) || ! isset( $field['_mrn_initial_layouts'] ) ) {
		return $field;
	}
	$field['layouts'] = $field['_mrn_initial_layouts'];
	unset( $field['_mrn_initial_layouts'] );
	$layouts = mrn_base_stack_get_initial_builder_source_layouts( $field );
	if ( null === $layouts ) {
		// Unrecognized local definitions retain ACF's native loader.
		$field_type = function_exists( 'acf_get_field_type' ) ? acf_get_field_type( 'flexible_content' ) : false;
		if ( is_object( $field_type ) && is_callable( array( $field_type, 'load_field' ) ) ) {
			return $field_type->load_field( $field );
		}
		return $field;
	}
	$field['layouts'] = $layouts;
	return $field;
}
add_filter( 'acf/load_field/type=flexible_content', 'mrn_base_stack_restore_initial_builder_catalog', 11 );

/**
 * Mark only initial top-level builder rows; leave nested/new rows native.
 *
 * @param array<string, mixed>|false $field Prepared ACF field.
 * @return array<string, mixed>|false
 */
function mrn_base_stack_prepare_initial_builder_rows( $field ) {
	if ( ! is_array( $field ) || ! mrn_base_stack_is_builder_startup_screen() ) {
		return $field;
	}
	$type = $field['type'] ?? '';
	$name = $field['_name'] ?? $field['name'] ?? '';
	$path = (string) ( $field['prefix'] ?? '' );
	if ( false !== strpos( $path, '[row-' ) || false !== strpos( $path, '[acfcloneindex]' ) ) {
		return $field;
	}
	if ( 'flexible_content' === $type && ! array_key_exists( $name, mrn_base_stack_get_builder_layout_allowlist_targets() ) ) {
		return $field;
	}
	if ( ! in_array( $type, array( 'flexible_content', 'repeater' ), true )
		|| false === strpos( (string) ( $field['key'] ?? '' ), 'field_mrn_' )
		|| ! mrn_base_stack_admin_is_initial_acf_row_collapse_enabled( $type, get_current_screen()->post_type ) ) {
		return $field;
	}
	$field['wrapper']['class'] = trim( ( $field['wrapper']['class'] ?? '' ) . ' mrn-acf-start-collapsed' );
	return $field;
}
add_filter( 'acf/prepare_field/type=flexible_content', 'mrn_base_stack_prepare_initial_builder_rows', 1000 );
add_filter( 'acf/prepare_field/type=repeater', 'mrn_base_stack_prepare_initial_builder_rows', 1000 );

/**
 * Retain native saved textareas while deferring TinyMCE in Stack row fields.
 *
 * ACF's own delayed-editor action initializes a widget when the row is shown.
 * Native editor content and other field owners keep their current behavior.
 *
 * @param array<string, mixed>|false $field Prepared ACF field.
 * @return array<string, mixed>|false
 */
function mrn_base_stack_defer_builder_rich_text( $field ) {
	if ( ! is_array( $field ) || 'wysiwyg' !== ( $field['type'] ?? '' )
		|| false === strpos( (string) ( $field['key'] ?? '' ), 'field_mrn_' )
		|| ( ! mrn_base_stack_is_builder_startup_screen() && empty( $GLOBALS['mrn_builder_layout_request'] ) ) ) {
		return $field;
	}
	$field['delay'] = 1;
	$field['wrapper']['class'] = trim( ( $field['wrapper']['class'] ?? '' ) . ' mrn-acf-deferred-editor' );
	return $field;
}
add_filter( 'acf/prepare_field/type=wysiwyg', 'mrn_base_stack_defer_builder_rich_text', 30 );

/**
 * Hide initial row bodies before first paint and set native classes before ACF.
 *
 * No synthetic toggle clicks, title AJAX, preferences, or saved values change.
 * Without JavaScript the head marker is absent and native fields remain usable.
 *
 * @return void
 */
function mrn_base_stack_print_builder_startup_state() {
	if ( ! mrn_base_stack_is_builder_startup_screen() ) {
		return;
	}
	?>
	<style>
	.mrn-builder-starting .mrn-acf-start-collapsed > .acf-input > .acf-flexible-content > .values > .layout > .acf-fields,
	.mrn-builder-starting .mrn-acf-start-collapsed > .acf-input > .acf-flexible-content > .values > .layout > .acf-table,
	.mrn-builder-starting .mrn-acf-start-collapsed > .acf-input > .acf-repeater > .acf-table > tbody > .acf-row:not(.acf-clone) > .acf-fields,
	.mrn-builder-starting .mrn-acf-start-collapsed > .acf-input > .acf-repeater > .acf-table > tbody > .acf-row:not(.acf-clone) > .acf-field {
		display: none;
	}
	</style>
	<script>
	( function() {
		var root = document.documentElement;
		root.classList.add( 'mrn-builder-starting' );
		document.addEventListener( 'DOMContentLoaded', function() {
			document.querySelectorAll( '.mrn-acf-start-collapsed' ).forEach( function( field ) {
				var selector = field.getAttribute( 'data-type' ) === 'flexible_content'
					? ':scope > .acf-input > .acf-flexible-content > .values > .layout:not(.acf-clone)'
					: ':scope > .acf-input > .acf-repeater > .acf-table > tbody > .acf-row:not(.acf-clone)';
				field.querySelectorAll( selector ).forEach( function( row ) { row.classList.add( '-collapsed' ); } );
			} );
			root.classList.remove( 'mrn-builder-starting' );
		}, { once: true } );
	} )();
	</script>
	<?php
}
add_action( 'admin_head', 'mrn_base_stack_print_builder_startup_state', 1 );

<?php
/**
 * Keep optional command-palette libraries out of Classic Editor startup.
 *
 * @package MRNEditorLockdown
 */

/**
 * Collect registered dependencies without changing WordPress's script queue.
 *
 * @param WP_Scripts $scripts Script registry.
 * @param array      $handles Root handles.
 * @return array<string, bool>
 */
function mrn_editor_lockdown_script_dependencies( $scripts, array $handles ) {
	$found = array();
	while ( $handles ) {
		$handle = array_pop( $handles );
		if ( isset( $found[ $handle ] ) || ! isset( $scripts->registered[ $handle ] ) ) {
			continue;
		}
		$found[ $handle ] = true;
		$handles         = array_merge( $handles, $scripts->registered[ $handle ]->deps );
	}
	return $found;
}

/**
 * Defer only palette dependencies that no other enqueued feature consumes.
 *
 * The native tags, translations, settings, nonces and dependency order remain
 * in the document. Their script type stays inert until the palette is opened.
 *
 * @param array $handles WordPress's ordered script handles.
 * @return array
 */
function mrn_editor_lockdown_prepare_palette_scripts( $handles ) {
	if ( ! wp_script_is( 'mrn-editor-palette-loader', 'enqueued' ) ) {
		return $handles;
	}
	$scripts = wp_scripts();
	$roots   = array_intersect( $scripts->queue, array( 'wp-core-commands', 'seopress-command-palette', 'seopress-pro-command-palette' ) );
	$owned   = mrn_editor_lockdown_script_dependencies( $scripts, $roots );
	$shared  = mrn_editor_lockdown_script_dependencies( $scripts, array_diff( $scripts->queue, $roots ) );
	$defer   = array_diff_key( $owned, $shared, array_fill_keys( $scripts->done, true ) );
	foreach ( array_keys( $defer ) as $handle ) {
		// Head scripts may be consumed by inline admin code before the loader.
		if ( 1 !== (int) ( $scripts->groups[ $handle ] ?? $scripts->get_data( $handle, 'group' ) ) ) {
			unset( $defer[ $handle ] );
			continue;
		}
		if ( empty( $scripts->registered[ $handle ]->extra['mrn_palette_ordered'] ) ) {
			// Native inline scripts prevent load-scripts.php concatenation, which
			// would bypass the inert external tag and download this library early.
			$scripts->add_inline_script( $handle, '/* Command palette: load on first use. */', 'before' );
			$scripts->add_data( $handle, 'mrn_palette_ordered', true );
		}
	}
	$GLOBALS['mrn_editor_palette_scripts'] = $defer;
	return $handles;
}
add_filter( 'print_scripts_array', 'mrn_editor_lockdown_prepare_palette_scripts' );

/**
 * Preserve native inline settings/translations with their deferred script.
 *
 * @param array $attributes Script attributes.
 * @return array
 */
function mrn_editor_lockdown_defer_palette_inline_script( $attributes ) {
	$id = $attributes['id'] ?? '';
	foreach ( $GLOBALS['mrn_editor_palette_scripts'] ?? array() as $handle => $deferred ) {
		if ( 0 === strpos( $id, $handle . '-js-' ) ) {
			$attributes['type']                    = 'application/x-mrn-command-palette';
			$attributes['data-mrn-palette-script'] = '1';
			break;
		}
	}
	return $attributes;
}
add_filter( 'wp_inline_script_attributes', 'mrn_editor_lockdown_defer_palette_inline_script' );

/**
 * Make the native external script tag inert without dropping its attributes.
 *
 * @param string $tag Script HTML.
 * @param string $handle Script handle.
 * @return string
 */
function mrn_editor_lockdown_defer_palette_script( $tag, $handle ) {
	if ( empty( $GLOBALS['mrn_editor_palette_scripts'][ $handle ] ) ) {
		return $tag;
	}
	$html = new WP_HTML_Tag_Processor( $tag );
	while ( $html->next_tag( 'SCRIPT' ) ) {
		$html->set_attribute( 'type', 'application/x-mrn-command-palette' );
		$html->set_attribute( 'data-mrn-palette-script', '1' );
	}
	return $html->get_updated_html();
}
add_filter( 'script_loader_tag', 'mrn_editor_lockdown_defer_palette_script', 100, 2 );

/**
 * Apply the optional-asset path to locked Classic Editor screens only.
 *
 * @return void
 */
function mrn_editor_lockdown_enqueue_palette_loader() {
	$screen = get_current_screen();
	if ( ! mrn_editor_lockdown_is_supported_screen( $screen ) || ! class_exists( 'WP_HTML_Tag_Processor' )
		|| ! apply_filters( 'mrn_editor_lockdown_defer_command_palette', true ) ) {
		return;
	}
	// This checklist renders Gutenberg's pre-publish panel, absent in Classic.
	wp_dequeue_script( 'seopress-pre-publish-checklist' );

	$scripts = wp_scripts();
	if ( isset( $scripts->registered['post-duplicator'] ) ) {
		// Post Duplicator uses its native wp.media path outside core/editor.
		$scripts->registered['post-duplicator']->deps = array_values( array_diff( $scripts->registered['post-duplicator']->deps, array( 'wp-block-editor' ) ) );
	}
	if ( ! wp_script_is( 'wp-core-commands', 'enqueued' ) ) {
		return;
	}
	$path = __DIR__ . '/../assets/command-palette-loader.js';
	wp_enqueue_script( 'mrn-editor-palette-loader', plugin_dir_url( __DIR__ ) . 'assets/command-palette-loader.js', array(), (string) filemtime( $path ), true );
	wp_localize_script( 'mrn-editor-palette-loader', 'mrnEditorPalette', array( 'failed' => __( 'The command palette could not be loaded. Please try again.', 'mrn-editor-lockdown' ) ) );
}
add_action( 'admin_enqueue_scripts', 'mrn_editor_lockdown_enqueue_palette_loader', PHP_INT_MAX );

<?php
/**
 * Plugin Name: MRN Template Inspector
 * Description: Standalone local testing tool to inspect selected page elements, template tree, and related CSS, then open files in VS Code.
 * Author: MRN Web Designs
 * Version: 0.2.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'MRN_TEMPLATE_INSPECTOR_LOADED' ) ) {
	return;
}
define( 'MRN_TEMPLATE_INSPECTOR_LOADED', true );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	return;
}

$host          = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_HOST'] ) ) : '';
$is_local_host = false !== strpos( $host, '.local' ) || false !== strpos( $host, 'localhost' ) || false !== strpos( $host, '.test' );

if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
	$environment_type = (string) WP_ENVIRONMENT_TYPE;
	if ( ! in_array( $environment_type, array( 'local', 'development' ), true ) && ! $is_local_host ) {
		return;
	}
} elseif ( ! $is_local_host ) {
	return;
}

add_action( 'admin_bar_menu', 'mrn_template_inspector_admin_bar', 2000 );
add_action( 'wp_before_admin_bar_render', 'mrn_template_inspector_admin_bar_render_fallback', 99999 );
add_filter( 'template_include', 'mrn_template_inspector_capture_main_template', 9999 );
add_action( 'wp_before_load_template', 'mrn_template_inspector_before_load_template', 10, 3 );
add_action( 'wp_after_load_template', 'mrn_template_inspector_after_load_template', 10, 3 );
add_action( 'wp_footer', 'mrn_template_inspector_render_selection_script', 9999 );
add_action( 'wp_ajax_mrn_template_inspector_resolve_selection', 'mrn_template_inspector_resolve_selection' );
add_filter( 'show_admin_bar', 'mrn_template_inspector_force_show_admin_bar', 99999 );

/**
 * Late-pass fallback to re-add toolbar node if another plugin removed it.
 *
 * @return void
 */
function mrn_template_inspector_admin_bar_render_fallback() {
	if ( is_admin() || ! is_admin_bar_showing() || ! mrn_template_inspector_user_can_use() ) {
		return;
	}

	global $wp_admin_bar;
	if ( ! $wp_admin_bar instanceof WP_Admin_Bar ) {
		return;
	}

	mrn_template_inspector_admin_bar( $wp_admin_bar );
}

/**
 * Pick a stable parent toolbar node when available.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
 * @return string
 */
function mrn_template_inspector_toolbar_parent_id( $wp_admin_bar ) {
	$preferred = array( 'site-name', 'top-secondary' );
	foreach ( $preferred as $node_id ) {
		$node = $wp_admin_bar->get_node( $node_id );
		if ( null !== $node ) {
			return $node_id;
		}
	}

	return '';
}

/**
 * Render all inspector child nodes under the root.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
 * @return void
 */
function mrn_template_inspector_render_toolbar_children( $wp_admin_bar ) {
	if ( is_admin() || ! is_admin_bar_showing() || ! mrn_template_inspector_user_can_use() ) {
		return;
	}

	if ( mrn_template_inspector_can_use_selection() ) {
		$wp_admin_bar->add_node(
			array(
				'id'     => 'mrn-inspector-selection-pick',
				'parent' => 'mrn-inspector-root',
				'title'  => 'Selection: Pick Element',
				'href'   => '#',
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'mrn-inspector-spacing-toggle',
				'parent' => 'mrn-inspector-root',
				'title'  => 'Stack Spacing: Off',
				'href'   => '#',
			)
		);
	}

	global $template;
	$template_path = is_string( $template ) ? wp_normalize_path( $template ) : '';

	$css_paths = mrn_template_inspector_get_theme_css_paths();
	$main_css  = mrn_template_inspector_pick_main_css_path( $css_paths );

	if ( $template_path && file_exists( $template_path ) ) {
		$wp_admin_bar->add_node(
			array(
				'id'     => 'mrn-inspector-template-label',
				'parent' => 'mrn-inspector-root',
				'title'  => 'Template: ' . esc_html( basename( $template_path ) ),
				'href'   => esc_url( mrn_template_inspector_open_link( $template_path, 1 ) ),
			)
		);
	} else {
		$wp_admin_bar->add_node(
			array(
				'id'     => 'mrn-inspector-template-missing',
				'parent' => 'mrn-inspector-root',
				'title'  => 'Template: not detected',
				'href'   => '#',
			)
		);
	}

	if ( $main_css ) {
		$wp_admin_bar->add_node(
			array(
				'id'     => 'mrn-inspector-main-css-open',
				'parent' => 'mrn-inspector-root',
				'title'  => 'Main CSS: ' . esc_html( basename( $main_css ) ),
				'href'   => esc_url( mrn_template_inspector_open_link( $main_css, 1 ) ),
			)
		);
	} else {
		$wp_admin_bar->add_node(
			array(
				'id'     => 'mrn-inspector-main-css-missing',
				'parent' => 'mrn-inspector-root',
				'title'  => 'Main CSS: not detected',
				'href'   => '#',
			)
		);
	}

	mrn_template_inspector_render_template_tree_menu( $wp_admin_bar, $template_path );

	$max_css_items = 6;
	$index         = 0;
	foreach ( $css_paths as $path => $handle ) {
		if ( $index >= $max_css_items ) {
			break;
		}

		$wp_admin_bar->add_node(
			array(
				'id'     => 'mrn-inspector-css-' . $index,
				'parent' => 'mrn-inspector-root',
				'title'  => 'CSS [' . esc_html( (string) $handle ) . ']: ' . esc_html( basename( $path ) ),
				'href'   => esc_url( mrn_template_inspector_open_link( $path, 1 ) ),
			)
		);

		$index++;
	}
}

/**
 * Add template/css inspector links to the front-end admin bar.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
 */
function mrn_template_inspector_admin_bar( $wp_admin_bar ) {
	if ( is_admin() || ! is_admin_bar_showing() || ! mrn_template_inspector_user_can_use() ) {
		return;
	}

	$wp_admin_bar->add_node(
		array(
		'id'    => 'mrn-inspector-root',
		'title' => 'Template Inspector',
		'href'  => '#',
		)
	);
	mrn_template_inspector_render_toolbar_children( $wp_admin_bar );
}

/**
 * Check whether front-end tooling can run in this request.
 *
 * @return bool
 */
function mrn_template_inspector_can_boot_frontend_tools() {
	return ! is_admin() && mrn_template_inspector_user_can_use();
}

/**
 * Check whether selection mode can run in this request.
 *
 * @return bool
 */
function mrn_template_inspector_can_use_selection() {
	return mrn_template_inspector_can_boot_frontend_tools();
}

/**
 * Capability required to use inspector tools.
 *
 * @return string
 */
function mrn_template_inspector_required_capability() {
	return (string) apply_filters( 'mrn_template_inspector_required_capability', 'edit_posts' );
}

/**
 * Whether current user can use inspector tools.
 *
 * @return bool
 */
function mrn_template_inspector_user_can_use() {
	return is_user_logged_in() && current_user_can( mrn_template_inspector_required_capability() );
}

/**
 * Ensure admin bar is visible on local front-end for admins using inspector.
 *
 * @param bool $show Current show admin bar value.
 * @return bool
 */
function mrn_template_inspector_force_show_admin_bar( $show ) {
	if ( is_admin() ) {
		return $show;
	}

	if ( mrn_template_inspector_user_can_use() ) {
		return true;
	}

	return $show;
}

/**
 * Build a localhost opener URL handled by Local add-on service.
 *
 * @param string $path Absolute filesystem path.
 * @param int    $line Line number.
 * @return string
 */
function mrn_template_inspector_open_link( $path, $line = 1 ) {
	$path = wp_normalize_path( (string) $path );
	$line = max( 1, (int) $line );

	if ( '' === $path ) {
		return '#';
	}

	$encoded_path = base64_encode( $path ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes file paths for local listener transport only.

	return add_query_arg(
		array(
			'path' => $encoded_path,
			'line' => $line,
			'back' => mrn_template_inspector_current_url(),
		),
		'http://127.0.0.1:17777/open'
	);
}

/**
 * Capture the main template selected for the current request.
 *
 * @param string $template_path Absolute template path.
 * @return string
 */
function mrn_template_inspector_capture_main_template( $template_path ) {
	$normalized = wp_normalize_path( (string) $template_path );
	if ( '' !== $normalized && mrn_template_inspector_is_theme_file( $normalized ) ) {
		$state                  = &mrn_template_inspector_template_state();
		$state['main_template'] = $normalized;
	}

	return $template_path;
}

/**
 * Track theme template loads before include().
 *
 * @param string $template_path Absolute template path.
 * @param bool   $load_once     Included by WordPress, ignored here.
 * @param array  $args          Included by WordPress, ignored here.
 * @return void
 */
function mrn_template_inspector_before_load_template( $template_path, $load_once = true, $args = array() ) {
	$path = wp_normalize_path( (string) $template_path );
	if ( '' === $path || ! file_exists( $path ) || ! mrn_template_inspector_is_theme_file( $path ) ) {
		return;
	}

	$state  = &mrn_template_inspector_template_state();
	$parent = '';
	if ( ! empty( $state['stack'] ) ) {
		$last = end( $state['stack'] );
		if ( is_string( $last ) ) {
			$parent = $last;
		}
	}

	$state['counter']++;
	$node_id                 = 'n' . (string) $state['counter'];
	$state['nodes'][ $node_id ] = array(
		'path'   => $path,
		'parent' => $parent,
	);
	$state['stack'][] = $node_id;

	$encoded_path = base64_encode( $path ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes file paths in template markers for local-only selection mapping.

	if ( mrn_template_inspector_can_use_selection() ) {
		echo "\n<!--MRN_TI_START:" . $node_id . ':' . $encoded_path . "-->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Track theme template loads after include() completes.
 *
 * @param string $template_path Absolute template path.
 * @param bool   $load_once     Included by WordPress, ignored here.
 * @param array  $args          Included by WordPress, ignored here.
 * @return void
 */
function mrn_template_inspector_after_load_template( $template_path, $load_once = true, $args = array() ) {
	$path = wp_normalize_path( (string) $template_path );
	if ( '' === $path || ! mrn_template_inspector_is_theme_file( $path ) ) {
		return;
	}

	$state = &mrn_template_inspector_template_state();
	if ( empty( $state['stack'] ) ) {
		return;
	}

	$closed_node_id = '';
	$last = end( $state['stack'] );
	if ( is_string( $last ) && ! empty( $state['nodes'][ $last ]['path'] ) && $state['nodes'][ $last ]['path'] === $path ) {
		$closed_node_id = $last;
		array_pop( $state['stack'] );
		if ( mrn_template_inspector_can_use_selection() ) {
			echo "\n<!--MRN_TI_END:" . $closed_node_id . "-->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return;
	}

	for ( $index = count( $state['stack'] ) - 1; $index >= 0; $index-- ) {
		$candidate_id = $state['stack'][ $index ];
		if ( ! is_string( $candidate_id ) || empty( $state['nodes'][ $candidate_id ]['path'] ) ) {
			continue;
		}

		if ( $state['nodes'][ $candidate_id ]['path'] === $path ) {
			$closed_node_id = $candidate_id;
			$state['stack'] = array_slice( $state['stack'], 0, $index );
			break;
		}
	}

	if ( '' !== $closed_node_id && mrn_template_inspector_can_use_selection() ) {
		echo "\n<!--MRN_TI_END:" . $closed_node_id . "-->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Add "Template Tree" submenu and clickable include chain.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
 * @param string       $template_path Main template path.
 * @return void
 */
function mrn_template_inspector_render_template_tree_menu( $wp_admin_bar, $template_path ) {
	$tree_nodes = mrn_template_inspector_get_template_tree_nodes( $template_path );

	$wp_admin_bar->add_node(
		array(
			'id'     => 'mrn-inspector-template-tree',
			'parent' => 'mrn-inspector-root',
			'title'  => 'Template Tree',
			'href'   => '#',
		)
	);

	if ( empty( $tree_nodes ) ) {
		$wp_admin_bar->add_node(
			array(
				'id'     => 'mrn-inspector-template-tree-empty',
				'parent' => 'mrn-inspector-template-tree',
				'title'  => 'No theme includes detected',
				'href'   => '#',
			)
		);
		return;
	}

	$index = 0;
	foreach ( $tree_nodes as $node_id => $node ) {
		$path  = isset( $node['path'] ) ? (string) $node['path'] : '';
		$depth = mrn_template_inspector_template_tree_depth( (string) $node_id, $tree_nodes );
		if ( '' === $path || ! file_exists( $path ) ) {
			continue;
		}

		$prefix = '';
		if ( $depth > 0 ) {
			$prefix = str_repeat( '|   ', min( 8, $depth ) ) . '|-- ';
		}

		$wp_admin_bar->add_node(
			array(
				'id'     => 'mrn-inspector-template-tree-node-' . $index,
				'parent' => 'mrn-inspector-template-tree',
				'title'  => esc_html( $prefix . mrn_template_inspector_relative_theme_path( $path ) ),
				'href'   => esc_url( mrn_template_inspector_open_link( $path, 1 ) ),
			)
		);

		$index++;
	}

	if ( count( $tree_nodes ) > $index ) {
		$wp_admin_bar->add_node(
			array(
				'id'     => 'mrn-inspector-template-tree-truncated',
				'parent' => 'mrn-inspector-template-tree',
				'title'  => 'More files were detected but hidden for menu size',
				'href'   => '#',
			)
		);
	}
}

/**
 * Return tracked template nodes filtered to theme files.
 *
 * @param string $template_path Main template path.
 * @return array<string, array{path:string,parent:string}>
 */
function mrn_template_inspector_get_template_tree_nodes( $template_path ) {
	$state = &mrn_template_inspector_template_state();
	$nodes = array();
	$seen  = array();

	$max_nodes = 30;
	foreach ( $state['nodes'] as $node_id => $node ) {
		if ( count( $nodes ) >= $max_nodes ) {
			break;
		}

		$path = isset( $node['path'] ) ? wp_normalize_path( (string) $node['path'] ) : '';
		if ( '' === $path || ! file_exists( $path ) || ! mrn_template_inspector_is_theme_file( $path ) ) {
			continue;
		}

		$parent = isset( $node['parent'] ) ? (string) $node['parent'] : '';
		if ( '' !== $parent && ! isset( $state['nodes'][ $parent ] ) ) {
			$parent = '';
		}

		$signature = $parent . '|' . $path;
		if ( isset( $seen[ $signature ] ) ) {
			continue;
		}

		$nodes[ (string) $node_id ] = array(
			'path'   => $path,
			'parent' => $parent,
		);
		$seen[ $signature ] = true;
	}

	if ( ! empty( $nodes ) ) {
		return $nodes;
	}

	$main_template = isset( $state['main_template'] ) ? (string) $state['main_template'] : '';
	if ( '' === $main_template && is_string( $template_path ) ) {
		$main_template = wp_normalize_path( $template_path );
	}

	if ( '' !== $main_template && file_exists( $main_template ) && mrn_template_inspector_is_theme_file( $main_template ) ) {
		$nodes['fallback'] = array(
			'path'   => $main_template,
			'parent' => '',
		);
	}

	return $nodes;
}

/**
 * Compute a node depth using parent links.
 *
 * @param string                                 $node_id Node identifier.
 * @param array<string, array{path:string,parent:string}> $nodes   Tracked nodes.
 * @return int
 */
function mrn_template_inspector_template_tree_depth( $node_id, $nodes ) {
	$depth  = 0;
	$cursor = $node_id;
	$safety = 0;

	while ( $safety < 20 && ! empty( $nodes[ $cursor ]['parent'] ) ) {
		$parent = (string) $nodes[ $cursor ]['parent'];
		if ( ! isset( $nodes[ $parent ] ) ) {
			break;
		}

		$depth++;
		$cursor = $parent;
		$safety++;
	}

	return $depth;
}

/**
 * Convert absolute path to a short path relative to the active theme.
 *
 * @param string $path Absolute filesystem path.
 * @return string
 */
function mrn_template_inspector_relative_theme_path( $path ) {
	$path           = wp_normalize_path( (string) $path );
	$stylesheet_dir = wp_normalize_path( get_stylesheet_directory() );
	$template_dir   = wp_normalize_path( get_template_directory() );

	if ( 0 === strpos( $path, $stylesheet_dir . '/' ) ) {
		return ltrim( (string) substr( $path, strlen( $stylesheet_dir ) ), '/' );
	}

	if ( 0 === strpos( $path, $template_dir . '/' ) ) {
		return ltrim( (string) substr( $path, strlen( $template_dir ) ), '/' );
	}

	return basename( $path );
}

/**
 * Determine whether a path belongs to the active theme.
 *
 * @param string $path Absolute filesystem path.
 * @return bool
 */
function mrn_template_inspector_is_theme_file( $path ) {
	$path = wp_normalize_path( (string) $path );
	if ( '' === $path ) {
		return false;
	}

	$theme_dirs = array_values(
		array_unique(
			array(
				wp_normalize_path( get_stylesheet_directory() ),
				wp_normalize_path( get_template_directory() ),
			)
		)
	);

	foreach ( $theme_dirs as $theme_dir ) {
		if ( $path === $theme_dir || 0 === strpos( $path, $theme_dir . '/' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Store tracked template include tree state for the current request.
 *
 * @return array{main_template:string,nodes:array<string,array{path:string,parent:string}>,stack:array<int,string>,counter:int}
 */
function &mrn_template_inspector_template_state() {
	static $state = null;
	if ( ! is_array( $state ) ) {
		$state = array(
			'main_template' => '',
			'nodes'         => array(),
			'stack'         => array(),
			'counter'       => 0,
		);
	}

	return $state;
}

/**
 * Get current request URL for return navigation.
 *
 * @return string
 */
function mrn_template_inspector_current_url() {
	$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_HOST'] ) ) : '';
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '/';

	if ( '' === $host ) {
		return home_url( '/' );
	}

	$scheme = is_ssl() ? 'https' : 'http';
	$url    = $scheme . '://' . $host . $uri;

	return esc_url_raw( $url );
}

/**
 * AJAX resolver for selection mode.
 *
 * @return void
 */
function mrn_template_inspector_resolve_selection() {
	check_ajax_referer( 'aqr-ti-selection', 'nonce' );

	if ( ! mrn_template_inspector_user_can_use() ) {
		wp_send_json_error(
			array(
				'message' => 'Not allowed.',
			),
			403
		);
	}

	$template_path = isset( $_POST['template_path'] ) ? wp_normalize_path( sanitize_text_field( wp_unslash( (string) $_POST['template_path'] ) ) ) : '';
	$template_item = null;
	if ( '' !== $template_path ) {
		$template_item = mrn_template_inspector_build_open_item( $template_path );
	}

	$css_hrefs = array();
	if ( isset( $_POST['css_hrefs'] ) && is_array( $_POST['css_hrefs'] ) ) {
		$css_hrefs = array_map(
			'sanitize_text_field',
			wp_unslash( (array) $_POST['css_hrefs'] )
		);
	}

	$css_items = array();
	$seen      = array();
	foreach ( $css_hrefs as $href_raw ) {
		if ( count( $css_items ) >= 8 ) {
			break;
		}

		$href = trim( (string) $href_raw );
		if ( '' === $href ) {
			continue;
		}

		$resolved_path = mrn_template_inspector_src_to_path( $href, home_url( '/' ) );
		if ( '' === $resolved_path || isset( $seen[ $resolved_path ] ) ) {
			continue;
		}

		$item = mrn_template_inspector_build_open_item( $resolved_path );
		if ( null === $item ) {
			continue;
		}

		$item['href']      = $href;
		$css_items[]       = $item;
		$seen[ $resolved_path ] = true;
	}

	if ( empty( $css_items ) ) {
		$css_paths = mrn_template_inspector_get_theme_css_paths();
		$main_css  = mrn_template_inspector_pick_main_css_path( $css_paths );
		if ( '' !== $main_css ) {
			$main_css_item = mrn_template_inspector_build_open_item( $main_css );
			if ( null !== $main_css_item ) {
				$css_items[] = $main_css_item;
			}
		}
	}

	wp_send_json_success(
		array(
			'template' => $template_item,
			'css'      => $css_items,
		)
	);
}

/**
 * Build a response item for a local file.
 *
 * @param string $path Absolute filesystem path.
 * @return array<string, string>|null
 */
function mrn_template_inspector_build_open_item( $path ) {
	$path = wp_normalize_path( (string) $path );
	if ( '' === $path || ! file_exists( $path ) || ! mrn_template_inspector_is_local_file( $path ) ) {
		return null;
	}

	$label = basename( $path );
	if ( mrn_template_inspector_is_theme_file( $path ) ) {
		$label = mrn_template_inspector_relative_theme_path( $path );
	}

	return array(
		'path'     => $path,
		'label'    => $label,
		'open_url' => mrn_template_inspector_open_link( $path, 1 ),
	);
}

/**
 * Check whether a path points to a safe local WordPress file.
 *
 * @param string $path Absolute filesystem path.
 * @return bool
 */
function mrn_template_inspector_is_local_file( $path ) {
	$path = wp_normalize_path( (string) $path );
	if ( '' === $path ) {
		return false;
	}

	$real = realpath( $path );
	if ( false === $real ) {
		return false;
	}

	$real  = wp_normalize_path( $real );
	$roots = array(
		wp_normalize_path( ABSPATH ),
		wp_normalize_path( WP_CONTENT_DIR ),
	);

	foreach ( $roots as $root ) {
		if ( $real === $root || 0 === strpos( $real, $root . '/' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Render front-end selection mode script.
 *
 * @return void
 */
function mrn_template_inspector_render_selection_script() {
	if ( ! mrn_template_inspector_can_use_selection() ) {
		return;
	}

	$config = array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'aqr-ti-selection' ),
	);
	?>
		<script>
			window.MRNTemplateInspectorSelection = <?php echo wp_json_encode( $config ); ?>;
			(function () {
				const config = window.MRNTemplateInspectorSelection;
				let triggerLink = document.querySelector('#wp-admin-bar-mrn-inspector-selection-pick > a');
				let spacingToggleLink = document.querySelector('#wp-admin-bar-mrn-inspector-spacing-toggle > a');
				if (!config) {
					return;
				}

				let active = false;
				let hoverBox = null;
				let panel = null;
				let panelBody = null;
				let spacingOverlayActive = false;
					let spacingOverlayFrame = 0;
					let spacingOverlayLayer = null;
					let spacingHoverTarget = null;
					let spacingMutationObserver = null;
					const spacingStorageKey = 'mrn-ti-stack-spacing-overlay-active';

				function ensureFloatingLauncher() {
					let launcher = document.getElementById('mrn-ti-floating-launcher');
					if (launcher) {
						return launcher;
					}

					launcher = document.createElement('button');
					launcher.type = 'button';
					launcher.id = 'mrn-ti-floating-launcher';
					launcher.textContent = 'Template Inspector';
					launcher.style.position = 'fixed';
					launcher.style.left = '16px';
					launcher.style.bottom = '16px';
					launcher.style.zIndex = '2147483644';
					launcher.style.border = '1px solid #2e7d32';
					launcher.style.background = '#13301b';
					launcher.style.color = '#b9f6ca';
					launcher.style.borderRadius = '8px';
					launcher.style.padding = '8px 10px';
					launcher.style.font = '12px/1.2 -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif';
					launcher.style.cursor = 'pointer';
					launcher.style.boxShadow = '0 8px 18px rgba(0,0,0,0.25)';
					document.body.appendChild(launcher);
					return launcher;
				}

				function ensureFloatingSpacingToggle() {
					let toggle = document.getElementById('mrn-ti-floating-spacing-toggle');
					if (toggle) {
						return toggle;
					}

					toggle = document.createElement('button');
					toggle.type = 'button';
					toggle.id = 'mrn-ti-floating-spacing-toggle';
					toggle.textContent = 'Stack Spacing: Off';
					toggle.style.position = 'fixed';
					toggle.style.left = '16px';
					toggle.style.bottom = '52px';
					toggle.style.zIndex = '2147483644';
					toggle.style.border = '1px solid #1e88e5';
					toggle.style.background = '#10233a';
					toggle.style.color = '#bbdefb';
					toggle.style.borderRadius = '8px';
					toggle.style.padding = '8px 10px';
					toggle.style.font = '12px/1.2 -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif';
					toggle.style.cursor = 'pointer';
					toggle.style.boxShadow = '0 8px 18px rgba(0,0,0,0.25)';
					document.body.appendChild(toggle);
					return toggle;
				}

			function ensureHoverBox() {
				if (hoverBox) {
					return hoverBox;
				}

				hoverBox = document.createElement('div');
				hoverBox.style.position = 'fixed';
				hoverBox.style.zIndex = '2147483646';
				hoverBox.style.pointerEvents = 'none';
				hoverBox.style.border = '2px solid #00c853';
				hoverBox.style.background = 'rgba(0, 200, 83, 0.10)';
				hoverBox.style.boxSizing = 'border-box';
				hoverBox.style.display = 'none';
				document.body.appendChild(hoverBox);
				return hoverBox;
			}

			function ensurePanel() {
				if (panel) {
					return panel;
				}

				panel = document.createElement('div');
				panel.style.position = 'fixed';
				panel.style.right = '16px';
				panel.style.bottom = '16px';
				panel.style.width = '360px';
				panel.style.maxWidth = 'calc(100vw - 24px)';
				panel.style.maxHeight = '60vh';
				panel.style.overflow = 'auto';
				panel.style.zIndex = '2147483647';
				panel.style.background = '#101317';
				panel.style.color = '#f5f6f7';
				panel.style.border = '1px solid #263038';
				panel.style.borderRadius = '10px';
				panel.style.boxShadow = '0 10px 30px rgba(0,0,0,0.35)';
				panel.style.padding = '10px 12px 12px';
				panel.style.font = '12px/1.45 -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif';
				panel.style.boxSizing = 'border-box';
				panel.style.display = 'none';

				const closeButton = document.createElement('button');
				closeButton.type = 'button';
				closeButton.textContent = 'x';
				closeButton.title = 'Close';
				closeButton.setAttribute('aria-label', 'Close selection panel');
				closeButton.style.position = 'absolute';
				closeButton.style.top = '6px';
				closeButton.style.right = '8px';
				closeButton.style.border = '1px solid #41505a';
				closeButton.style.background = '#161d23';
				closeButton.style.color = '#d4d9de';
				closeButton.style.borderRadius = '4px';
				closeButton.style.padding = '0 6px';
				closeButton.style.fontSize = '14px';
				closeButton.style.lineHeight = '20px';
				closeButton.style.cursor = 'pointer';
				closeButton.addEventListener('click', function (event) {
					event.preventDefault();
					event.stopPropagation();
					hidePanel();
				});
				panel.appendChild(closeButton);

				panelBody = document.createElement('div');
				panelBody.style.paddingTop = '14px';
				panel.appendChild(panelBody);
				document.body.appendChild(panel);
				return panel;
			}

			function isPanelVisible() {
				return !!(panel && panel.style.display !== 'none');
			}

			function showPanel() {
				const box = ensurePanel();
				box.style.display = 'block';
			}

			function hidePanel() {
				if (active) {
					stopSelection();
				}
				hideHoverBox();
				if (panel) {
					panel.style.display = 'none';
				}
			}

			function clearPanel() {
				showPanel();
				if (panelBody) {
					panelBody.innerHTML = '';
				}
			}

			function addPanelTitle(text) {
				if (!panelBody) {
					ensurePanel();
				}
				const box = panelBody || panel;
				const title = document.createElement('div');
				title.textContent = text;
				title.style.fontWeight = '700';
				title.style.marginBottom = '8px';
				box.appendChild(title);
			}

			function addPanelText(text) {
				if (!panelBody) {
					ensurePanel();
				}
				const box = panelBody || panel;
				const line = document.createElement('div');
				line.textContent = text;
				line.style.marginBottom = '8px';
				line.style.opacity = '0.9';
				box.appendChild(line);
			}

			function addActionButton(label, onClick) {
				if (!panelBody) {
					ensurePanel();
				}
				const box = panelBody || panel;
				const button = document.createElement('button');
				button.type = 'button';
				button.textContent = label;
				button.style.display = 'inline-block';
				button.style.marginRight = '8px';
				button.style.marginBottom = '8px';
				button.style.padding = '6px 10px';
				button.style.borderRadius = '6px';
				button.style.border = '1px solid #2e7d32';
				button.style.background = '#13301b';
				button.style.color = '#b9f6ca';
				button.style.cursor = 'pointer';
				button.addEventListener('click', onClick);
				box.appendChild(button);
			}

			function openViaBackgroundRequest(openUrl) {
				if (!openUrl) {
					return;
				}

				const iframe = document.createElement('iframe');
				iframe.style.display = 'none';
				iframe.src = openUrl;
				document.body.appendChild(iframe);
				window.setTimeout(function () {
					iframe.remove();
				}, 2500);
			}

				function isInspectorUIElement(el) {
					if (!(el instanceof Element)) {
						return false;
					}

					if (el.closest('#mrn-ti-floating-launcher')) {
						return true;
					}

					if (el.closest('#mrn-ti-floating-spacing-toggle')) {
						return true;
					}

					if (el.closest('#mrn-ti-spacing-overlay')) {
						return true;
					}

					if (el.closest('#wpadminbar')) {
						return true;
					}

				const box = panel;
				return !!(box && box.contains(el));
			}

			function ensureSpacingOverlayLayer() {
				if (spacingOverlayLayer) {
					return spacingOverlayLayer;
				}

				spacingOverlayLayer = document.createElement('div');
				spacingOverlayLayer.id = 'mrn-ti-spacing-overlay';
				spacingOverlayLayer.setAttribute('aria-hidden', 'true');
				spacingOverlayLayer.style.position = 'fixed';
				spacingOverlayLayer.style.left = '0';
				spacingOverlayLayer.style.top = '0';
				spacingOverlayLayer.style.width = '100vw';
				spacingOverlayLayer.style.height = '100vh';
				spacingOverlayLayer.style.zIndex = '2147483645';
				spacingOverlayLayer.style.pointerEvents = 'none';
				spacingOverlayLayer.style.font = '11px/1.35 -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif';
				spacingOverlayLayer.style.display = 'none';
				document.body.appendChild(spacingOverlayLayer);
				return spacingOverlayLayer;
			}

			function readSpacingOverlayPreference() {
				try {
					return window.localStorage.getItem(spacingStorageKey) === '1';
				} catch (e) {
					return false;
				}
			}

			function writeSpacingOverlayPreference(isEnabled) {
				try {
					window.localStorage.setItem(spacingStorageKey, isEnabled ? '1' : '0');
				} catch (e) {
					// Ignore storage failures in private browsing or locked-down runtimes.
				}
			}

			function parsePixelValue(value) {
				const parsed = parseFloat(String(value || ''));
				return Number.isFinite(parsed) ? parsed : 0;
			}

			function formatCssValue(value) {
				const raw = String(value || '').trim();
				if (!raw) {
					return '0';
				}

				const parsed = parseFloat(raw);
				if (Number.isFinite(parsed) && raw.indexOf('px') !== -1) {
					const rounded = Math.round(parsed * 100) / 100;
					return String(rounded).replace(/\.0$/, '') + 'px';
				}

				return raw;
			}

			function getBoxValues(style, prefix) {
				return {
					top: style.getPropertyValue(prefix + '-top'),
					right: style.getPropertyValue(prefix + '-right'),
					bottom: style.getPropertyValue(prefix + '-bottom'),
					left: style.getPropertyValue(prefix + '-left')
				};
			}

			function getBoxPixels(values) {
				return {
					top: parsePixelValue(values.top),
					right: parsePixelValue(values.right),
					bottom: parsePixelValue(values.bottom),
					left: parsePixelValue(values.left)
				};
			}

					function formatBoxValues(values) {
						return [
							formatCssValue(values.top),
							formatCssValue(values.right),
							formatCssValue(values.bottom),
							formatCssValue(values.left)
						].join(' / ');
					}

				function collectGapSummary(el, style) {
					const summaries = [];
					const ownRowGap = String(style.rowGap || '').trim();
					const ownColumnGap = String(style.columnGap || '').trim();
					if (ownRowGap && ownRowGap !== 'normal' && (parsePixelValue(ownRowGap) || parsePixelValue(ownColumnGap))) {
						summaries.push(formatCssValue(ownRowGap) + ' / ' + formatCssValue(ownColumnGap));
					}

					const gapTarget = el.querySelector('.mrn-layout-grid, .mrn-ui__body, .mrn-ui__items');
					if (gapTarget instanceof Element) {
						const gapStyle = window.getComputedStyle(gapTarget);
						const rowGap = String(gapStyle.rowGap || '').trim();
						const columnGap = String(gapStyle.columnGap || '').trim();
						if (rowGap && rowGap !== 'normal' && (parsePixelValue(rowGap) || parsePixelValue(columnGap))) {
							summaries.push(formatCssValue(rowGap) + ' / ' + formatCssValue(columnGap));
						}
					}

					return summaries;
				}

					function getSpacingTargetMeta(el) {
						const spacingName = String(el.getAttribute('data-mrn-row-spacing') || '').trim();
						if (spacingName) {
							return {
								setOnRow: true
							};
						}

					const modifier = Array.from(el.classList || []).find(function (className) {
						return className.indexOf('mrn-content-builder__row--') === 0;
					});

						if (modifier) {
							return {
								setOnRow: false
							};
						}

						if (el.classList && el.classList.contains('entry-content--builder')) {
							return {
								setOnRow: false
							};
						}

						if (el.classList && el.classList.contains('entry-header')) {
							return {
								setOnRow: false
							};
						}

						if (el.classList && el.classList.contains('post-thumbnail')) {
							return {
								setOnRow: false
							};
						}

						if (el.classList && el.classList.contains('mrn-singular-shell')) {
							return {
								setOnRow: false
							};
						}

						if (el.classList && el.classList.contains('site-main')) {
							return {
								setOnRow: false
							};
						}

						return {
							setOnRow: false
						};
					}

			function clampOverlayPosition(value, min, max) {
				if (max < min) {
					return min;
				}

				return Math.max(min, Math.min(max, value));
			}

			function addSpacingOverlayPart(layer, left, top, width, height, background, border) {
				if (width <= 0 || height <= 0) {
					return;
				}

				const part = document.createElement('div');
				part.style.position = 'fixed';
				part.style.left = Math.round(left) + 'px';
				part.style.top = Math.round(top) + 'px';
				part.style.width = Math.round(width) + 'px';
				part.style.height = Math.round(height) + 'px';
				part.style.boxSizing = 'border-box';
				part.style.pointerEvents = 'none';
				part.style.background = background;
				part.style.border = border;
				layer.appendChild(part);
			}

					function addSpacingTooltip(layer, el, rect, style, marginValues, paddingValues) {
						const meta = getSpacingTargetMeta(el);
						const gapSummary = collectGapSummary(el, style);
						const parts = [
							'Row: ' + (meta.setOnRow ? 'yes' : 'no'),
							'M ' + formatBoxValues(marginValues),
							'P ' + formatBoxValues(paddingValues)
						];

						if (gapSummary.length) {
							parts.push('G ' + gapSummary.join(' | '));
						}

						const label = document.createElement('div');
						label.textContent = parts.join('  |  ');
						label.style.position = 'fixed';
						label.style.left = '0';
						label.style.top = '0';
						label.style.maxWidth = 'calc(100vw - 24px)';
						label.style.padding = '7px 10px';
						label.style.borderRadius = '3px';
						label.style.border = '0';
						label.style.background = '#303842';
						label.style.color = '#d7dee8';
						label.style.whiteSpace = 'nowrap';
						label.style.boxShadow = '0 8px 18px rgba(0,0,0,0.28)';
						label.style.pointerEvents = 'none';
						label.style.font = '12px/1.2 -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif';

						const arrow = document.createElement('div');
						arrow.style.position = 'absolute';
						arrow.style.left = '50%';
						arrow.style.width = '0';
						arrow.style.height = '0';
						arrow.style.marginLeft = '-7px';
						arrow.style.borderLeft = '7px solid transparent';
						arrow.style.borderRight = '7px solid transparent';
						label.appendChild(arrow);
						layer.appendChild(label);

						const labelRect = label.getBoundingClientRect();
						const aboveTop = rect.top - labelRect.height - 14;
						const placeBelow = aboveTop < 36;
						const left = clampOverlayPosition(
							rect.left + (rect.width / 2) - (labelRect.width / 2),
							8,
							window.innerWidth - labelRect.width - 8
						);
						const top = placeBelow ? rect.bottom + 14 : aboveTop;

						label.style.left = Math.round(left) + 'px';
						label.style.top = Math.round(clampOverlayPosition(top, 36, window.innerHeight - labelRect.height - 8)) + 'px';

						if (placeBelow) {
							arrow.style.top = '-7px';
							arrow.style.borderBottom = '7px solid #303842';
						} else {
							arrow.style.bottom = '-7px';
							arrow.style.borderTop = '7px solid #303842';
						}
				}

				function drawSpacingTarget(layer, el, showTooltip) {
				const rect = el.getBoundingClientRect();
				const style = window.getComputedStyle(el);
				const marginValues = getBoxValues(style, 'margin');
				const paddingValues = getBoxValues(style, 'padding');
				const margin = getBoxPixels(marginValues);
				const padding = getBoxPixels(paddingValues);
				const marginFill = 'rgba(255, 152, 0, 0.22)';
				const marginBorder = '1px solid rgba(255, 152, 0, 0.75)';
				const paddingFill = 'rgba(76, 175, 80, 0.24)';
				const paddingBorder = '1px solid rgba(76, 175, 80, 0.80)';
				const contentFill = 'rgba(30, 136, 229, 0.08)';
				const contentBorder = '1px solid rgba(30, 136, 229, 0.95)';

				addSpacingOverlayPart(layer, rect.left - margin.left, rect.top - margin.top, rect.width + margin.left + margin.right, margin.top, marginFill, marginBorder);
				addSpacingOverlayPart(layer, rect.left - margin.left, rect.bottom, rect.width + margin.left + margin.right, margin.bottom, marginFill, marginBorder);
				addSpacingOverlayPart(layer, rect.left - margin.left, rect.top, margin.left, rect.height, marginFill, marginBorder);
				addSpacingOverlayPart(layer, rect.right, rect.top, margin.right, rect.height, marginFill, marginBorder);

				addSpacingOverlayPart(layer, rect.left, rect.top, rect.width, padding.top, paddingFill, paddingBorder);
				addSpacingOverlayPart(layer, rect.left, rect.bottom - padding.bottom, rect.width, padding.bottom, paddingFill, paddingBorder);
				addSpacingOverlayPart(layer, rect.left, rect.top + padding.top, padding.left, Math.max(0, rect.height - padding.top - padding.bottom), paddingFill, paddingBorder);
				addSpacingOverlayPart(layer, rect.right - padding.right, rect.top + padding.top, padding.right, Math.max(0, rect.height - padding.top - padding.bottom), paddingFill, paddingBorder);

				addSpacingOverlayPart(
					layer,
					rect.left + padding.left,
					rect.top + padding.top,
					Math.max(0, rect.width - padding.left - padding.right),
					Math.max(0, rect.height - padding.top - padding.bottom),
					contentFill,
					contentBorder
				);
					addSpacingOverlayPart(layer, rect.left, rect.top, rect.width, rect.height, 'rgba(30, 136, 229, 0.04)', '2px solid rgba(30, 136, 229, 0.95)');

					if (showTooltip) {
						addSpacingTooltip(layer, el, rect, style, marginValues, paddingValues);
					}
				}

				function getSpacingFallbackTargetSelector() {
					return [
						'.entry-content--builder',
						'.entry-header',
						'.post-thumbnail',
						'.mrn-singular-shell',
						'.mrn-shell-section',
						'.mrn-layout-section',
						'.mrn-layout-surface',
						'.site-main'
					].join(', ');
				}

				function getSpacingTargetSelector() {
					const explicitSelector = '[data-mrn-row-spacing], .mrn-content-builder__row';
					if (document.querySelector(explicitSelector)) {
						return explicitSelector;
					}

					return getSpacingFallbackTargetSelector();
				}

				function findHoveredSpacingTarget(target) {
					if (!(target instanceof Element) || isInspectorUIElement(target)) {
						return null;
					}

					const candidate = target.closest(getSpacingTargetSelector());
					if (!(candidate instanceof Element) || isInspectorUIElement(candidate)) {
						return null;
					}

					const rect = candidate.getBoundingClientRect();
					if (rect.width <= 0 || rect.height <= 0 || rect.bottom < 0 || rect.top > window.innerHeight) {
						return null;
					}

					const style = window.getComputedStyle(candidate);
					if (style.display === 'none' || style.visibility === 'hidden') {
						return null;
					}

					return candidate;
				}

				function onSpacingMouseMove(event) {
					if (!spacingOverlayActive) {
						return;
					}

					const nextTarget = findHoveredSpacingTarget(event.target);
					if (nextTarget === spacingHoverTarget) {
						return;
					}

					spacingHoverTarget = nextTarget;
					scheduleSpacingOverlayUpdate();
				}

				function collectSpacingTargets() {
					const targets = [];
					const seen = new Set();
					const candidates = Array.from(document.querySelectorAll(getSpacingTargetSelector()));

				for (const el of candidates) {
					if (!(el instanceof Element) || seen.has(el) || isInspectorUIElement(el)) {
						continue;
					}

					seen.add(el);
					const rect = el.getBoundingClientRect();
					if (rect.width <= 0 || rect.height <= 0 || rect.bottom < -160 || rect.top > window.innerHeight + 160) {
						continue;
					}

					const style = window.getComputedStyle(el);
					if (style.display === 'none' || style.visibility === 'hidden') {
						continue;
					}

					targets.push(el);
					if (targets.length >= 60) {
						break;
					}
				}

				return targets;
			}

			function showSpacingEmptyState(layer) {
				const note = document.createElement('div');
				note.textContent = 'No stack or theme spacing targets found on this viewport.';
				note.style.position = 'fixed';
				note.style.left = '16px';
				note.style.top = '48px';
				note.style.padding = '7px 9px';
				note.style.border = '1px solid rgba(255,255,255,0.22)';
				note.style.borderRadius = '6px';
				note.style.background = 'rgba(16, 19, 23, 0.94)';
				note.style.color = '#f5f6f7';
				note.style.boxShadow = '0 8px 20px rgba(0,0,0,0.32)';
				layer.appendChild(note);
			}

			function updateSpacingOverlay() {
				spacingOverlayFrame = 0;
				if (!spacingOverlayActive) {
					return;
				}

				const layer = ensureSpacingOverlayLayer();
				layer.style.display = 'block';
				layer.innerHTML = '';

				const targets = collectSpacingTargets();
					if (!targets.length) {
						showSpacingEmptyState(layer);
						return;
					}

					for (const target of targets) {
						if (target !== spacingHoverTarget) {
							continue;
						}

						drawSpacingTarget(layer, target, true);
						break;
					}
				}

			function scheduleSpacingOverlayUpdate() {
				if (!spacingOverlayActive || spacingOverlayFrame) {
					return;
				}

				spacingOverlayFrame = window.requestAnimationFrame(updateSpacingOverlay);
			}

			function onSpacingMutation(mutations) {
				for (const mutation of mutations) {
					const target = mutation.target;
					if (target instanceof Element && isInspectorUIElement(target)) {
						continue;
					}

					scheduleSpacingOverlayUpdate();
					return;
				}
			}

				function bindSpacingOverlayWatchers() {
					document.addEventListener('mousemove', onSpacingMouseMove, true);
					window.addEventListener('scroll', scheduleSpacingOverlayUpdate, true);
					window.addEventListener('resize', scheduleSpacingOverlayUpdate, true);

				if ('MutationObserver' in window && document.body) {
					spacingMutationObserver = new MutationObserver(onSpacingMutation);
					spacingMutationObserver.observe(document.body, {
						attributes: true,
						childList: true,
						subtree: true,
						attributeFilter: ['class', 'style', 'data-mrn-row-spacing']
					});
				}
			}

				function unbindSpacingOverlayWatchers() {
					document.removeEventListener('mousemove', onSpacingMouseMove, true);
					window.removeEventListener('scroll', scheduleSpacingOverlayUpdate, true);
					window.removeEventListener('resize', scheduleSpacingOverlayUpdate, true);

				if (spacingMutationObserver) {
					spacingMutationObserver.disconnect();
					spacingMutationObserver = null;
				}
			}

			function updateSpacingToggleLabel() {
				if (!spacingToggleLink) {
					return;
				}

				spacingToggleLink.textContent = 'Stack Spacing: ' + (spacingOverlayActive ? 'On' : 'Off');
				spacingToggleLink.setAttribute('aria-pressed', spacingOverlayActive ? 'true' : 'false');
			}

			function startSpacingOverlay() {
				if (spacingOverlayActive) {
					scheduleSpacingOverlayUpdate();
					return;
				}

					spacingOverlayActive = true;
					spacingHoverTarget = null;
					writeSpacingOverlayPreference(true);
					updateSpacingToggleLabel();
				ensureSpacingOverlayLayer();
				bindSpacingOverlayWatchers();
				scheduleSpacingOverlayUpdate();
			}

			function stopSpacingOverlay() {
					spacingOverlayActive = false;
					spacingHoverTarget = null;
					writeSpacingOverlayPreference(false);
					updateSpacingToggleLabel();
				unbindSpacingOverlayWatchers();

				if (spacingOverlayFrame) {
					window.cancelAnimationFrame(spacingOverlayFrame);
					spacingOverlayFrame = 0;
				}

				if (spacingOverlayLayer) {
					spacingOverlayLayer.innerHTML = '';
					spacingOverlayLayer.style.display = 'none';
				}
			}

			function toggleSpacingOverlay(event) {
				if (event) {
					event.preventDefault();
					event.stopPropagation();
				}

				if (spacingOverlayActive) {
					stopSpacingOverlay();
					return;
				}

				startSpacingOverlay();
			}

			function setHoverBoxForElement(el) {
				const box = ensureHoverBox();
				const rect = el.getBoundingClientRect();
				box.style.left = rect.left + 'px';
				box.style.top = rect.top + 'px';
				box.style.width = rect.width + 'px';
				box.style.height = rect.height + 'px';
				box.style.display = 'block';
			}

			function hideHoverBox() {
				const box = ensureHoverBox();
				box.style.display = 'none';
			}

			function parseStartMarker(raw) {
				const match = raw.match(/^MRN_TI_START:([^:]+):([A-Za-z0-9+/=]+)$/);
				if (!match) {
					return null;
				}

				let decodedPath = '';
				try {
					decodedPath = window.atob(match[2]);
				} catch (e) {
					return null;
				}

				return {
					id: match[1],
					path: decodedPath
				};
			}

			function parseEndMarker(raw) {
				const match = raw.match(/^MRN_TI_END:([^:]+)$/);
				return match ? match[1] : '';
			}

			function findTemplatePathForElement(target) {
				if (!(target instanceof Element)) {
					return '';
				}

				const stack = [];
				const pathById = Object.create(null);
				const walker = document.createTreeWalker(
					document.documentElement,
					NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_COMMENT
				);

				let node = walker.currentNode;
				while (node) {
					if (node.nodeType === Node.COMMENT_NODE) {
						const raw = String(node.nodeValue || '').trim();
						if (raw.indexOf('MRN_TI_START:') === 0) {
							const start = parseStartMarker(raw);
							if (start && start.id && start.path) {
								pathById[start.id] = start.path;
								stack.push(start.id);
							}
						} else if (raw.indexOf('MRN_TI_END:') === 0) {
							const endId = parseEndMarker(raw);
							if (endId) {
								const idx = stack.lastIndexOf(endId);
								if (idx !== -1) {
									stack.splice(idx, 1);
								}
							}
						}
					} else if (node === target) {
						const activeId = stack.length ? stack[stack.length - 1] : '';
						if (activeId && pathById[activeId]) {
							return pathById[activeId];
						}
						return '';
					}

					node = walker.nextNode();
				}

				return '';
			}

			function getElementChain(el) {
				const chain = [];
				let current = el;
				let depth = 0;
				while (current && current.nodeType === Node.ELEMENT_NODE && depth < 8) {
					chain.push(current);
					current = current.parentElement;
					depth++;
				}
				return chain;
			}

			function collectMatchingRules(ruleList, elementChain, out, sheetHref, budget) {
				for (const rule of ruleList) {
					if (budget.count <= 0 || out.size >= 8) {
						return;
					}
					budget.count--;

					if (rule.type === CSSRule.STYLE_RULE && rule.selectorText && sheetHref) {
						for (const el of elementChain) {
							try {
								if (el.matches(rule.selectorText)) {
									out.add(sheetHref);
									break;
								}
							} catch (e) {
								// Ignore unsupported selectors.
							}
						}
						continue;
					}

					if (rule.cssRules && rule.cssRules.length) {
						collectMatchingRules(rule.cssRules, elementChain, out, sheetHref, budget);
					}
				}
			}

			function findMatchingCssHrefs(target) {
				const out = new Set();
				const elementChain = getElementChain(target);
				const budget = {
					count: 8000
				};

				for (const sheet of Array.from(document.styleSheets || [])) {
					if (out.size >= 8) {
						break;
					}

					const href = sheet && sheet.href ? String(sheet.href) : '';
					if (!href) {
						continue;
					}

					let rules = null;
					try {
						rules = sheet.cssRules;
					} catch (e) {
						rules = null;
					}

					if (!rules || !rules.length) {
						continue;
					}

					collectMatchingRules(rules, elementChain, out, href, budget);
				}

				return Array.from(out);
			}

			function stopSelection() {
				active = false;
				hideHoverBox();
				document.removeEventListener('mousemove', onMouseMove, true);
				document.removeEventListener('click', onElementClick, true);
				document.removeEventListener('keydown', onKeyDown, true);
			}

			function startSelection() {
				active = true;
				clearPanel();
				addPanelTitle('Selection Mode');
				addPanelText('Click any page element to inspect template and CSS. Press Esc to cancel.');
				document.addEventListener('mousemove', onMouseMove, true);
				document.addEventListener('click', onElementClick, true);
				document.addEventListener('keydown', onKeyDown, true);
			}

			function onMouseMove(event) {
				if (!active) {
					return;
				}

				const target = event.target;
				if (!(target instanceof Element) || isInspectorUIElement(target)) {
					hideHoverBox();
					return;
				}

				setHoverBoxForElement(target);
			}

			async function inspectSelection(target) {
				clearPanel();
				addPanelTitle('Selection Result');
				addPanelText('Resolving template and matching CSS files...');

				const templatePath = findTemplatePathForElement(target);
				const cssHrefs = findMatchingCssHrefs(target);
				const payload = new URLSearchParams();
				payload.set('action', 'mrn_template_inspector_resolve_selection');
				payload.set('nonce', config.nonce);
				if (templatePath) {
					payload.set('template_path', templatePath);
				}
				for (const href of cssHrefs) {
					payload.append('css_hrefs[]', href);
				}

				let response;
				try {
					response = await fetch(config.ajaxUrl, {
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
						},
						body: payload.toString()
					});
				} catch (error) {
					clearPanel();
					addPanelTitle('Selection Result');
					addPanelText('Could not resolve files from this selection.');
					addPanelText(String(error && error.message ? error.message : error));
					addActionButton('Pick Another Element', startSelection);
					return;
				}

				const json = await response.json();
				if (!json || !json.success) {
					clearPanel();
					addPanelTitle('Selection Result');
					addPanelText('Could not resolve files from this selection.');
					addActionButton('Pick Another Element', startSelection);
					return;
				}

				const data = json.data || {};
				clearPanel();
				addPanelTitle('Selection Result');

				if (data.template && data.template.open_url) {
					addPanelText('Template: ' + String(data.template.label || data.template.path || ''));
					addActionButton('Open Template', function () {
						openViaBackgroundRequest(String(data.template.open_url));
					});
				} else {
					addPanelText('Template: not mapped from this element.');
				}

				if (Array.isArray(data.css) && data.css.length) {
					addPanelText('CSS matches:');
					for (const item of data.css) {
						if (!item || !item.open_url) {
							continue;
						}

						addActionButton('Open CSS: ' + String(item.label || item.path || 'style.css'), function () {
							openViaBackgroundRequest(String(item.open_url));
						});
					}
				} else {
					addPanelText('CSS: no matched file detected.');
				}

				addActionButton('Pick Another Element', startSelection);
			}

			function onElementClick(event) {
				if (!active) {
					return;
				}

				const target = event.target;
				if (!(target instanceof Element) || isInspectorUIElement(target)) {
					return;
				}

				event.preventDefault();
				event.stopPropagation();
				event.stopImmediatePropagation();
				stopSelection();
				inspectSelection(target);
			}

			function onKeyDown(event) {
				if (!active) {
					return;
				}

				if (event.key === 'Escape') {
					hidePanel();
				}
			}

			function onGlobalKeyDown(event) {
				if (event.key !== 'Escape') {
					return;
				}

				if (active) {
					hidePanel();
					return;
				}

				if (isPanelVisible()) {
					hidePanel();
					return;
				}

				if (spacingOverlayActive) {
					stopSpacingOverlay();
				}
			}

				document.addEventListener('keydown', onGlobalKeyDown, true);

				if (!triggerLink) {
					triggerLink = ensureFloatingLauncher();
				}

				if (!spacingToggleLink) {
					spacingToggleLink = ensureFloatingSpacingToggle();
				}

				if (spacingToggleLink) {
					spacingToggleLink.addEventListener('click', toggleSpacingOverlay);
					updateSpacingToggleLabel();
					if (readSpacingOverlayPreference()) {
						startSpacingOverlay();
					}
				}

				if (!triggerLink) {
					return;
				}

				triggerLink.addEventListener('click', function (event) {
					event.preventDefault();
					event.stopPropagation();
					startSelection();
			});
		})();
	</script>
	<?php
}

/**
 * Resolve currently enqueued theme CSS files to local filesystem paths.
 *
 * @return array<string, string> Map of absolute path => style handle.
 */
function mrn_template_inspector_get_theme_css_paths() {
	$paths      = array();
	$wp_styles  = wp_styles();
	$theme_dirs = array_values(
		array_unique(
			array(
				wp_normalize_path( get_stylesheet_directory() ),
				wp_normalize_path( get_template_directory() ),
			)
		)
	);

	if ( ! $wp_styles instanceof WP_Styles ) {
		return $paths;
	}

	foreach ( (array) $wp_styles->queue as $handle ) {
		if ( empty( $wp_styles->registered[ $handle ] ) ) {
			continue;
		}

		$registered = $wp_styles->registered[ $handle ];
		if ( empty( $registered->src ) || ! is_string( $registered->src ) ) {
			continue;
		}

		$resolved_path = mrn_template_inspector_src_to_path( $registered->src, (string) $wp_styles->base_url );
		if ( '' === $resolved_path || ! file_exists( $resolved_path ) ) {
			continue;
		}

		$resolved_path = wp_normalize_path( $resolved_path );
		foreach ( $theme_dirs as $theme_dir ) {
			if ( 0 === strpos( $resolved_path, $theme_dir . '/' ) || $resolved_path === $theme_dir ) {
				$paths[ $resolved_path ] = (string) $handle;
				break;
			}
		}
	}

	foreach ( $theme_dirs as $theme_dir ) {
		$style_css = wp_normalize_path( $theme_dir . '/style.css' );
		if ( file_exists( $style_css ) ) {
			$paths[ $style_css ] = 'style.css';
		}
	}

	return $paths;
}

/**
 * Pick the best "main CSS" path.
 *
 * @param array<string, string> $css_paths Map of absolute path => style handle.
 * @return string
 */
function mrn_template_inspector_pick_main_css_path( $css_paths ) {
	if ( empty( $css_paths ) ) {
		return '';
	}

	$stylesheet_dir = wp_normalize_path( get_stylesheet_directory() );
	foreach ( array_keys( $css_paths ) as $path ) {
		if ( 'style.css' === basename( $path ) && 0 === strpos( $path, $stylesheet_dir . '/' ) ) {
			return $path;
		}
	}

	foreach ( array_keys( $css_paths ) as $path ) {
		if ( 'style.css' === basename( $path ) ) {
			return $path;
		}
	}

	$first_key = array_key_first( $css_paths );
	return is_string( $first_key ) ? $first_key : '';
}

/**
 * Convert a stylesheet source URL into a local filesystem path when possible.
 *
 * @param string $src      Enqueued style src.
 * @param string $base_url WP_Styles base URL.
 * @return string
 */
function mrn_template_inspector_src_to_path( $src, $base_url ) {
	$src = trim( (string) $src );
	if ( '' === $src ) {
		return '';
	}

	if ( 0 === strpos( $src, '//' ) ) {
		$src = ( is_ssl() ? 'https:' : 'http:' ) . $src;
	} elseif ( 0 !== strpos( $src, 'http://' ) && 0 !== strpos( $src, 'https://' ) ) {
		$src = rtrim( $base_url, '/' ) . '/' . ltrim( $src, '/' );
	}

	$src = strtok( $src, '?' );
	if ( ! is_string( $src ) || '' === $src ) {
		return '';
	}

	$url_to_path = array(
		trailingslashit( content_url() )  => trailingslashit( WP_CONTENT_DIR ),
		trailingslashit( includes_url() ) => trailingslashit( ABSPATH . WPINC ),
		trailingslashit( site_url() )     => trailingslashit( ABSPATH ),
		trailingslashit( home_url() )     => trailingslashit( ABSPATH ),
	);

	foreach ( $url_to_path as $url_prefix => $path_prefix ) {
		if ( 0 !== strpos( $src, $url_prefix ) ) {
			continue;
		}

		$relative = ltrim( (string) substr( $src, strlen( $url_prefix ) ), '/' );
		return wp_normalize_path( $path_prefix . $relative );
	}

	return '';
}

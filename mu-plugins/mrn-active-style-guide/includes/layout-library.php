<?php
/**
 * Site-aware Layout Library.
 *
 * The library deliberately obtains layouts from the active Stack theme. It does
 * not define, clone, or persist site content as PHP defaults. Preview fixtures
 * are private, clearly marked WordPress records and are only created by an
 * administrator's explicit regeneration request.
 *
 * @package mrn-active-style-guide
 */

defined( 'ABSPATH' ) || exit;

const MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_OPTION = 'mrn_active_style_guide_layout_library';
const MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_FIXTURE_META = '_mrn_layout_library_fixture';

/**
 * Return the theme's currently available builder layouts, keyed by layout name.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_active_style_guide_get_layout_library_catalog(): array {
	if ( ! function_exists( 'mrn_base_stack_get_builder_layout_allowlist_catalog' ) ) {
		return array();
	}

	$catalog = array();
	foreach ( array( 'page_hero_rows', 'page_content_rows', 'page_after_content_rows' ) as $field_name ) {
		$layouts = mrn_base_stack_get_builder_layout_allowlist_catalog( $field_name );
		if ( ! is_array( $layouts ) ) {
			continue;
		}

		foreach ( $layouts as $layout_name => $layout ) {
			$layout_name = sanitize_key( (string) $layout_name );
			if ( '' === $layout_name || ! is_array( $layout ) ) {
				continue;
			}

			if ( ! isset( $catalog[ $layout_name ] ) ) {
				$catalog[ $layout_name ] = array(
					'layout'     => $layout,
					'field_name' => $field_name,
				);
			}
		}
	}

	ksort( $catalog, SORT_NATURAL | SORT_FLAG_CASE );
	return $catalog;
}

/**
 * Produce a fingerprint that changes with the active site styling or catalog.
 *
 * @return string
 */
function mrn_active_style_guide_get_layout_library_fingerprint(): string {
	$files = array_filter(
		array(
			get_template_directory() . '/style.css',
			get_stylesheet_directory() . '/style.css',
		),
		'is_readable'
	);
	$styles = array();
	foreach ( $files as $file ) {
		$styles[ wp_normalize_path( $file ) ] = (string) filemtime( $file );
	}

	$colors = function_exists( 'mrn_site_colors_get_all' ) ? mrn_site_colors_get_all() : array();
	$layouts = array_keys( mrn_active_style_guide_get_layout_library_catalog() );

	return hash( 'sha256', wp_json_encode( array( 'styles' => $styles, 'colors' => $colors, 'layouts' => $layouts ) ) ?: '' );
}

/**
 * Find an existing, published example of a layout without scanning post content.
 *
 * @param string $layout_name Layout name.
 * @return int
 */
function mrn_active_style_guide_find_layout_library_representative( string $layout_name ): int {
	global $wpdb;

	$layout_name = sanitize_key( $layout_name );
	if ( '' === $layout_name || ! isset( $wpdb ) ) {
		return 0;
	}

	$like = $wpdb->esc_like( '_acf_fc_layout' );
	$post_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key LIKE %s AND pm.meta_value = %s AND p.post_status = 'publish' AND p.post_type NOT IN ('revision', 'attachment') ORDER BY p.post_modified_gmt DESC LIMIT 1",
			'%' . $like,
			$layout_name
		)
	);

	return absint( $post_id );
}

/**
 * Generate safe, schema-shaped example values for a private preview fixture.
 *
 * @param array<int, array<string, mixed>> $fields ACF fields.
 * @param string                            $layout_name Layout name.
 * @param int                               $depth Recursion guard.
 * @return array<string, mixed>
 */
function mrn_active_style_guide_get_layout_library_sample_values( array $fields, string $layout_name, int $depth = 0 ): array {
	if ( $depth > 4 ) {
		return array();
	}

	$values = array();
	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) || empty( $field['name'] ) ) {
			continue;
		}
		$name = (string) $field['name'];
		$type = isset( $field['type'] ) ? (string) $field['type'] : '';
		$label = isset( $field['label'] ) ? wp_strip_all_tags( (string) $field['label'] ) : ucwords( str_replace( '_', ' ', $name ) );

		switch ( $type ) {
			case 'text':
			case 'number':
			case 'email':
				$values[ $name ] = 'number' === $type ? 3 : sprintf( __( 'Example %s', 'mrn-active-style-guide' ), $label );
				break;
			case 'textarea':
			case 'wysiwyg':
				$values[ $name ] = sprintf( __( '<p>Example content for the %1$s layout. This protected fixture is used only to generate a site-aware preview.</p>', 'mrn-active-style-guide' ), esc_html( $layout_name ) );
				break;
			case 'url':
				$values[ $name ] = home_url( '/' );
				break;
			case 'link':
				$values[ $name ] = array( 'title' => __( 'Learn more', 'mrn-active-style-guide' ), 'url' => home_url( '/' ), 'target' => '' );
				break;
			case 'true_false':
				$values[ $name ] = 1;
				break;
			case 'select':
			case 'radio':
			case 'button_group':
				$choices = isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : array();
				$values[ $name ] = (string) array_key_first( $choices );
				break;
			case 'group':
				$values[ $name ] = mrn_active_style_guide_get_layout_library_sample_values( $field['sub_fields'] ?? array(), $layout_name, $depth + 1 );
				break;
			case 'repeater':
				$item = mrn_active_style_guide_get_layout_library_sample_values( $field['sub_fields'] ?? array(), $layout_name, $depth + 1 );
				$values[ $name ] = empty( $item ) ? array() : array( $item, $item, $item );
				break;
		}
	}

	return $values;
}

/**
 * Resolve a layout's full ACF field schema from the active site's field group.
 *
 * The Stack allowlist intentionally holds rendering metadata only, so its
 * layouts do not include the ACF sub-field schema required for fixtures.
 *
 * @param array<string, mixed> $entry       Layout catalog entry.
 * @param string               $layout_name Layout machine name.
 * @return array<int, array<string, mixed>>
 */
function mrn_active_style_guide_get_layout_library_layout_fields( array $entry, string $layout_name ): array {
	if ( ! function_exists( 'acf_get_field' ) ) {
		return array();
	}

	$field = acf_get_field( sanitize_key( (string) ( $entry['field_name'] ?? '' ) ) );
	$layout_name = sanitize_key( $layout_name );
	if ( ! is_array( $field ) || '' === $layout_name || empty( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
		return array();
	}

	foreach ( $field['layouts'] as $layout ) {
		if ( is_array( $layout ) && $layout_name === sanitize_key( (string) ( $layout['name'] ?? '' ) ) ) {
			return isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ? $layout['sub_fields'] : array();
		}
	}

	return array();
}

/**
 * Create or update private fixtures for layouts with no published example.
 *
 * @return array<string, mixed>
 */
function mrn_active_style_guide_regenerate_layout_library_fixtures(): array {
	if ( ! function_exists( 'update_field' ) || ! function_exists( 'acf_get_field' ) ) {
		return array( 'created' => array(), 'skipped' => array( 'acf-unavailable' ) );
	}

	$result = array( 'created' => array(), 'skipped' => array() );
	foreach ( mrn_active_style_guide_get_layout_library_catalog() as $layout_name => $entry ) {
		if ( mrn_active_style_guide_find_layout_library_representative( $layout_name ) > 0 ) {
			continue;
		}

		$field_name = sanitize_key( (string) $entry['field_name'] );
		$field = acf_get_field( $field_name );
		$field_key = is_array( $field ) && isset( $field['key'] ) ? (string) $field['key'] : '';
		if ( '' === $field_key ) {
			$result['skipped'][] = $layout_name;
			continue;
		}

		$existing = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'meta_key'       => MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_FIXTURE_META,
			'meta_value'     => $layout_name,
			'fields'         => 'ids',
		) );
		$post_id = ! empty( $existing ) ? absint( $existing[0] ) : wp_insert_post( array(
			'post_title'  => sprintf( 'MRN Layout Preview: %s', $layout_name ),
			'post_status' => 'private',
			'post_type'   => 'page',
			'post_name'   => 'mrn-layout-preview-' . $layout_name,
		), true );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			$result['skipped'][] = $layout_name;
			continue;
		}

		update_post_meta( $post_id, MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_FIXTURE_META, $layout_name );
		$row = array_merge(
			array( 'acf_fc_layout' => $layout_name ),
			mrn_active_style_guide_get_layout_library_sample_values( mrn_active_style_guide_get_layout_library_layout_fields( $entry, $layout_name ), $layout_name )
		);
		update_field( $field_key, array( $row ), $post_id );
		$result['created'][ $layout_name ] = $post_id;
	}

	return $result;
}

/** Get the generated thumbnail attachment for a layout, if it is current. */
function mrn_active_style_guide_get_layout_library_thumbnail_id( string $layout_name ): int {
	$state = get_option( MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_OPTION, array() );
	$state = is_array( $state ) ? $state : array();
	$thumbnails = isset( $state['thumbnails'] ) && is_array( $state['thumbnails'] ) ? $state['thumbnails'] : array();
	$attachment_id = isset( $thumbnails[ sanitize_key( $layout_name ) ] ) ? absint( $thumbnails[ sanitize_key( $layout_name ) ] ) : 0;

	return $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ? $attachment_id : 0;
}

/** Give the existing Stack picker its site-generated thumbnail. */
function mrn_active_style_guide_add_layout_library_picker_thumbnail( array $metadata, $layout_name ): array {
	$attachment_id = mrn_active_style_guide_get_layout_library_thumbnail_id( (string) $layout_name );
	if ( $attachment_id <= 0 ) {
		return $metadata;
	}

	$url = wp_get_attachment_image_url( $attachment_id, 'medium_large' );
	if ( is_string( $url ) && '' !== $url ) {
		$metadata['preview_thumbnail_url'] = $url;
		$metadata['preview_alt_text'] = sprintf( __( '%s layout preview from this site', 'mrn-active-style-guide' ), (string) $layout_name );
	}

	return $metadata;
}
add_filter( 'mrn_base_stack_acf_layout_picker_metadata', 'mrn_active_style_guide_add_layout_library_picker_thumbnail', 20, 2 );

/** Build the capture plan used by the reviewed Playwright capture tool. */
function mrn_active_style_guide_get_layout_library_capture_manifest(): array {
	$targets = array();
	$unavailable = array();
	foreach ( mrn_active_style_guide_get_layout_library_catalog() as $layout_name => $entry ) {
		$post_id = mrn_active_style_guide_find_layout_library_representative( $layout_name );
		$source = 'published';
		if ( $post_id <= 0 ) {
			$fixtures = get_posts( array(
				'post_type' => 'page', 'post_status' => 'private', 'posts_per_page' => 1,
				'meta_key' => MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_FIXTURE_META,
				'meta_value' => $layout_name, 'fields' => 'ids',
			) );
			$post_id = ! empty( $fixtures ) ? absint( $fixtures[0] ) : 0;
			$source = 'fixture';
		}
		if ( $post_id > 0 ) {
			$markup = function_exists( 'mrn_base_stack_get_builder_markup' ) ? mrn_base_stack_get_builder_markup( (string) $entry['field_name'], $post_id ) : '';
			$preview = array(
				'layout' => $layout_name,
				'label' => isset( $entry['layout']['label'] ) ? (string) $entry['layout']['label'] : $layout_name,
				'source' => $source, 'post_id' => $post_id, 'url' => get_permalink( $post_id ),
				'selector' => '.mrn-content-builder__row--' . sanitize_html_class( $layout_name ),
			);
			if ( '' === trim( (string) $markup ) ) {
				$unavailable[] = $preview;
				continue;
			}
			$targets[] = array(
				'layout' => $preview['layout'], 'label' => $preview['label'], 'source' => $preview['source'], 'post_id' => $preview['post_id'], 'url' => $preview['url'], 'selector' => $preview['selector'],
			);
		}
	}

	return array( 'fingerprint' => mrn_active_style_guide_get_layout_library_fingerprint(), 'generatedAt' => gmdate( 'c' ), 'targets' => $targets, 'unavailable' => $unavailable );
}

/** Import locally captured screenshots into the Media Library and map them. */
function mrn_active_style_guide_import_layout_library_thumbnails( string $directory ): array {
	$directory = wp_normalize_path( $directory );
	if ( ! is_dir( $directory ) || ! is_readable( $directory ) ) {
		throw new InvalidArgumentException( 'Capture directory is not readable.' );
	}
	$report_path = trailingslashit( $directory ) . 'capture-report.json';
	$report = is_readable( $report_path ) ? json_decode( (string) file_get_contents( $report_path ), true ) : null;
	if ( ! is_array( $report ) || ! empty( $report['failed'] ) || empty( $report['fingerprint'] ) || ! hash_equals( mrn_active_style_guide_get_layout_library_fingerprint(), (string) $report['fingerprint'] ) ) {
		throw new InvalidArgumentException( 'Capture report is missing, failed, or no longer matches the current site fingerprint.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$thumbnails = array();
	foreach ( glob( trailingslashit( $directory ) . '*.jpg' ) ?: array() as $file ) {
		$layout_name = sanitize_key( pathinfo( $file, PATHINFO_FILENAME ) );
		if ( '' === $layout_name || ! isset( mrn_active_style_guide_get_layout_library_catalog()[ $layout_name ] ) ) {
			continue;
		}
		$attachment_id = media_handle_sideload( array( 'name' => basename( $file ), 'tmp_name' => $file ), 0, 'Layout Library preview: ' . $layout_name );
		if ( ! is_wp_error( $attachment_id ) ) {
			update_post_meta( $attachment_id, '_mrn_layout_library_preview', $layout_name );
			$thumbnails[ $layout_name ] = (int) $attachment_id;
		}
	}
	$state = get_option( MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_OPTION, array() );
	$state = is_array( $state ) ? $state : array();
	$state['thumbnails'] = array_merge( isset( $state['thumbnails'] ) && is_array( $state['thumbnails'] ) ? $state['thumbnails'] : array(), $thumbnails );
	$state['fingerprint'] = mrn_active_style_guide_get_layout_library_fingerprint();
	$state['captured_at'] = time();
	update_option( MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_OPTION, $state, false );
	return $thumbnails;
}

/**
 * Hide WordPress chrome inside the authenticated, read-only library preview.
 *
 * The underlying preview page remains subject to its ordinary post-status and
 * capability rules; this only gives the iframe a useful visual surface.
 *
 * @param bool $show Whether to show the admin bar.
 * @return bool
 */
function mrn_active_style_guide_hide_admin_bar_in_layout_library_preview( bool $show ): bool {
	return ! empty( $_GET['mrn-layout-library-preview'] ) && current_user_can( 'edit_theme_options' ) ? false : $show;
}
add_filter( 'show_admin_bar', 'mrn_active_style_guide_hide_admin_bar_in_layout_library_preview' );

/** Print preview-only styles after the active theme has registered its chrome. */
function mrn_active_style_guide_print_layout_library_preview_styles(): void {
	if ( empty( $_GET['mrn-layout-library-preview'] ) || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	?>
	<style id="mrn-layout-library-preview-styles">
		#wpadminbar, #masthead, #colophon { display: none !important; }
		html { margin-top: 0 !important; }
		#page, #primary, .site-main, .site-content { margin: 0 !important; padding: 0 !important; max-width: none !important; width: 100% !important; }
	</style>
	<?php
}
add_action( 'wp_head', 'mrn_active_style_guide_print_layout_library_preview_styles', 999 );

/** Render the admin Layout Library page. */
function mrn_active_style_guide_render_layout_library_page(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to view the Layout Library.', 'mrn-active-style-guide' ) );
	}

	$state = get_option( MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_OPTION, array() );
	$state = is_array( $state ) ? $state : array();
	$fingerprint = mrn_active_style_guide_get_layout_library_fingerprint();
	$is_stale = empty( $state['fingerprint'] ) || ! hash_equals( (string) $state['fingerprint'], $fingerprint );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Layout Library', 'mrn-active-style-guide' ); ?></h1>
		<p><?php esc_html_e( 'This catalog is read from the active Stack theme. Published layout instances are preferred; private, no-index fixtures cover layouts without an existing example.', 'mrn-active-style-guide' ); ?></p>
		<?php if ( $is_stale ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Layout previews need regeneration because the active site styles or layout catalog changed.', 'mrn-active-style-guide' ); ?></p></div>
		<?php else : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Layout preview fixtures match the current visual fingerprint.', 'mrn-active-style-guide' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="mrn_active_style_guide_regenerate_layout_library">
			<?php wp_nonce_field( 'mrn_active_style_guide_regenerate_layout_library' ); ?>
			<?php submit_button( __( 'Regenerate preview fixtures', 'mrn-active-style-guide' ), 'primary', 'submit', false ); ?>
		</form>
		<h2><?php esc_html_e( 'Live previews', 'mrn-active-style-guide' ); ?></h2>
		<p><?php esc_html_e( 'These are the active site’s rendered layouts—not static mockups. Open a preview to inspect it at full size.', 'mrn-active-style-guide' ); ?></p>
		<style>
			.mrn-layout-library-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(360px,1fr)); gap:20px; max-width:1500px; margin:20px 0; }
			.mrn-layout-library-card { background:#fff; border:1px solid #ccd0d4; border-radius:4px; overflow:hidden; }
			.mrn-layout-library-card__header { display:flex; gap:12px; align-items:baseline; justify-content:space-between; padding:12px 14px; }
			.mrn-layout-library-card__title { margin:0; font-size:14px; }
			.mrn-layout-library-card__frame { display:block; width:100%; height:360px; border:0; border-top:1px solid #ccd0d4; background:#f6f7f7; }
			.mrn-layout-library-card__meta { padding:9px 14px; color:#50575e; font-size:12px; }
		</style>
		<?php $manifest = mrn_active_style_guide_get_layout_library_capture_manifest(); ?>
		<div class="mrn-layout-library-grid">
		<?php foreach ( $manifest['targets'] as $target ) : ?>
			<?php $preview_url = add_query_arg( 'mrn-layout-library-preview', '1', (string) $target['url'] ); ?>
			<article class="mrn-layout-library-card">
				<div class="mrn-layout-library-card__header">
					<h3 class="mrn-layout-library-card__title"><?php echo esc_html( $target['label'] ); ?></h3>
					<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open full size', 'mrn-active-style-guide' ); ?></a>
				</div>
				<iframe class="mrn-layout-library-card__frame" title="<?php echo esc_attr( sprintf( __( '%s live layout preview', 'mrn-active-style-guide' ), $target['label'] ) ); ?>" src="<?php echo esc_url( $preview_url ); ?>" loading="lazy"></iframe>
				<div class="mrn-layout-library-card__meta"><code><?php echo esc_html( $target['layout'] ); ?></code> · <?php echo esc_html( ucfirst( (string) $target['source'] ) ); ?></div>
			</article>
		<?php endforeach; ?>
		</div>
		<?php if ( ! empty( $manifest['unavailable'] ) ) : ?>
			<h2><?php esc_html_e( 'Needs site content', 'mrn-active-style-guide' ); ?></h2>
			<p><?php esc_html_e( 'These layouts require a site-owned form, image, reusable block, selected content, or another dependency before a meaningful preview can be rendered. They will join the live gallery automatically once that content is available.', 'mrn-active-style-guide' ); ?></p>
			<ul class="ul-disc">
			<?php foreach ( $manifest['unavailable'] as $preview ) : ?>
				<li><strong><?php echo esc_html( $preview['label'] ); ?></strong> <code><?php echo esc_html( $preview['layout'] ); ?></code></li>
			<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}

/** Register the library below Appearance. */
function mrn_active_style_guide_register_layout_library_menu(): void {
	add_theme_page( __( 'Layout Library', 'mrn-active-style-guide' ), __( 'Layout Library', 'mrn-active-style-guide' ), 'edit_theme_options', 'mrn-layout-library', 'mrn_active_style_guide_render_layout_library_page' );
}
add_action( 'admin_menu', 'mrn_active_style_guide_register_layout_library_menu', 20 );

/** Handle the explicit, capability-protected fixture regeneration action. */
function mrn_active_style_guide_handle_layout_library_regeneration(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to regenerate Layout Library fixtures.', 'mrn-active-style-guide' ) );
	}
	check_admin_referer( 'mrn_active_style_guide_regenerate_layout_library' );

	$result = mrn_active_style_guide_regenerate_layout_library_fixtures();
	$fingerprint = mrn_active_style_guide_get_layout_library_fingerprint();
	update_option( MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_OPTION, array( 'fingerprint' => $fingerprint, 'regenerated_at' => time(), 'result' => $result ), false );

	/**
	 * Allows an approved capture worker to generate thumbnail attachments after
	 * fixtures have been refreshed. The hook never makes an external request.
	 */
	do_action( 'mrn_active_style_guide_layout_library_regenerated', $result, $fingerprint );

	wp_safe_redirect( add_query_arg( 'mrn-layout-library-regenerated', '1', admin_url( 'themes.php?page=mrn-layout-library' ) ) );
	exit;
}
add_action( 'admin_post_mrn_active_style_guide_regenerate_layout_library', 'mrn_active_style_guide_handle_layout_library_regeneration' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/** Expose only explicit local/on-host capture operations to WP-CLI. */
	WP_CLI::add_command( 'mrn-layout-library capture-manifest', static function (): void {
		WP_CLI::line( wp_json_encode( mrn_active_style_guide_get_layout_library_capture_manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	} );
	WP_CLI::add_command( 'mrn-layout-library import-thumbnails', static function ( array $args ): void {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Usage: wp mrn-layout-library import-thumbnails <directory>' );
		}
		try {
			$imported = mrn_active_style_guide_import_layout_library_thumbnails( (string) $args[0] );
			WP_CLI::success( sprintf( 'Imported %d layout thumbnails.', count( $imported ) ) );
		} catch ( InvalidArgumentException $error ) {
			WP_CLI::error( $error->getMessage() );
		}
	} );
}

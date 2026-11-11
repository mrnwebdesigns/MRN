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
const MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_SUPPORT_META = '_mrn_layout_library_support';

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
			case 'image':
			case 'file':
				$values[ $name ] = mrn_active_style_guide_get_layout_library_sample_attachment_id();
				break;
			case 'gallery':
				$attachment_id = mrn_active_style_guide_get_layout_library_sample_attachment_id();
				$values[ $name ] = $attachment_id ? array( $attachment_id ) : array();
				break;
			case 'post_object':
			case 'page_link':
				$values[ $name ] = mrn_active_style_guide_get_layout_library_sample_post_id( $field );
				break;
			case 'relationship':
				$post_id = mrn_active_style_guide_get_layout_library_sample_post_id( $field );
				$values[ $name ] = $post_id ? array( $post_id ) : array();
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
			case 'checkbox':
				$choices = isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : array();
				$choice = array_key_first( $choices );
				$values[ $name ] = null === $choice ? array() : array( (string) $choice );
				break;
			case 'group':
				$values[ $name ] = mrn_active_style_guide_get_layout_library_sample_values( $field['sub_fields'] ?? array(), $layout_name, $depth + 1 );
				break;
			case 'repeater':
				$item = mrn_active_style_guide_get_layout_library_sample_values( $field['sub_fields'] ?? array(), $layout_name, $depth + 1 );
				$values[ $name ] = empty( $item ) ? array() : array( $item, $item, $item );
				break;
			case 'flexible_content':
				// Nested flexible groups have layout-specific contracts. Leave them
				// empty here; a blueprint can opt into a valid nested composition.
				$values[ $name ] = array();
				break;
		}
	}

	return $values;
}

/**
 * Supply the small nested compositions that a generic field walk cannot infer.
 *
 * These are private fixture values, not defaults for editor-authored content.
 * Keeping them here means an unused layout still has a truthful HTML example.
 *
 * @param string $layout_name Layout machine name.
 * @return array<string, mixed>
 */
function mrn_active_style_guide_get_layout_library_fixture_blueprint( string $layout_name ): array {
	$body_row = array(
		'acf_fc_layout' => 'body_text',
		'heading'       => __( 'Example content heading', 'mrn-active-style-guide' ),
		'heading_tag'   => 'h3',
		'body_text'     => __( '<p>This is representative preview content. Editors replace it with their own content when they add the layout.</p>', 'mrn-active-style-guide' ),
	);

	switch ( $layout_name ) {
		case 'faq_jump_nav':
			return array(
				'jump_nav_source'  => 'manual',
				'jump_nav_entries' => array(
					array( 'label' => __( 'First question', 'mrn-active-style-guide' ), 'anchor' => 'example-question-one' ),
					array( 'label' => __( 'Second question', 'mrn-active-style-guide' ), 'anchor' => 'example-question-two' ),
					array( 'label' => __( 'Third question', 'mrn-active-style-guide' ), 'anchor' => 'example-question-three' ),
				),
			);
		case 'tabbed_layout':
			return array(
				'tabs' => array(
					array( 'tab_label' => __( 'Overview', 'mrn-active-style-guide' ), 'panel_rows' => array( $body_row ) ),
					array( 'tab_label' => __( 'Details', 'mrn-active-style-guide' ), 'panel_rows' => array( array_merge( $body_row, array( 'heading' => __( 'Example detail heading', 'mrn-active-style-guide' ) ) ) ) ),
				),
			);
		case 'two_column_split':
			return array(
				'column_rows_mode' => 'stack',
				'left_column_rows' => array( $body_row ),
				'right_column_rows' => array( array_merge( $body_row, array( 'heading' => __( 'Second column', 'mrn-active-style-guide' ) ) ) ),
			);
	}

	return array();
}

/** Return the private reusable block that backs the reusable-block preview. */
function mrn_active_style_guide_get_layout_library_reusable_block_id(): int {
	$existing = get_posts( array( 'post_type' => 'mrn_reusable_basic', 'post_status' => 'any', 'posts_per_page' => 1, 'meta_key' => MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_SUPPORT_META, 'meta_value' => 'reusable-basic', 'fields' => 'ids' ) );
	if ( ! empty( $existing ) ) {
		return absint( $existing[0] );
	}

	$post_id = wp_insert_post( array( 'post_title' => __( 'Layout Library example reusable block', 'mrn-active-style-guide' ), 'post_status' => 'private', 'post_type' => 'mrn_reusable_basic' ), true );
	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return 0;
	}
	update_post_meta( $post_id, MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_SUPPORT_META, 'reusable-basic' );
	update_field( 'heading', __( 'Example reusable content', 'mrn-active-style-guide' ), $post_id );
	update_field( 'content', __( '<p>This private source record exists only to demonstrate the reusable block layout.</p>', 'mrn-active-style-guide' ), $post_id );
	return absint( $post_id );
}

/** Return the private WPForms form that backs the WPForms preview. */
function mrn_active_style_guide_get_layout_library_wpforms_form_id(): int {
	$existing = get_posts( array( 'post_type' => 'wpforms', 'post_status' => 'any', 'posts_per_page' => 1, 'meta_key' => MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_SUPPORT_META, 'meta_value' => 'wpforms', 'fields' => 'ids' ) );
	if ( ! empty( $existing ) ) {
		return absint( $existing[0] );
	}
	if ( ! shortcode_exists( 'wpforms' ) ) {
		return 0;
	}

	$data = array(
		'fields' => array(
			'1' => array( 'id' => '1', 'type' => 'text', 'label' => __( 'Name', 'mrn-active-style-guide' ), 'required' => '1' ),
			'2' => array( 'id' => '2', 'type' => 'email', 'label' => __( 'Email', 'mrn-active-style-guide' ), 'required' => '1' ),
		),
		'settings' => array( 'form_title' => __( 'Layout Library example form', 'mrn-active-style-guide' ), 'submit_text' => __( 'Send', 'mrn-active-style-guide' ) ),
	);
	$form_id = wp_insert_post( array(
		'post_title'   => __( 'Layout Library example form', 'mrn-active-style-guide' ),
		'post_status'  => 'private',
		'post_type'    => 'wpforms',
		'post_content' => function_exists( 'wpforms_encode' ) ? wpforms_encode( $data ) : wp_json_encode( $data ),
	), true );
	if ( is_wp_error( $form_id ) || ! $form_id ) {
		return 0;
	}
	update_post_meta( $form_id, MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_SUPPORT_META, 'wpforms' );
	return absint( $form_id );
}

/** Return a reusable, existing site image for synthetic preview-only content. */
function mrn_active_style_guide_get_layout_library_sample_attachment_id(): int {
	static $attachment_id = null;
	if ( null !== $attachment_id ) {
		return $attachment_id;
	}
	$attachments = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids' ) );
	$attachment_id = ! empty( $attachments ) ? absint( $attachments[0] ) : 0;
	return $attachment_id;
}

/** Return an existing record compatible with an ACF relationship-style field. */
function mrn_active_style_guide_get_layout_library_sample_post_id( array $field ): int {
	$post_types = isset( $field['post_type'] ) ? (array) $field['post_type'] : array( 'page', 'post' );
	$post_types = array_values( array_filter( array_map( 'sanitize_key', $post_types ) ) );
	if ( empty( $post_types ) ) {
		$post_types = array( 'page', 'post' );
	}
	$posts = get_posts( array( 'post_type' => $post_types, 'post_status' => array( 'publish', 'private' ), 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids' ) );
	return ! empty( $posts ) ? absint( $posts[0] ) : 0;
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

/** Persist a fixture row, including nested ACF structures that need row context. */
function mrn_active_style_guide_update_layout_library_fixture_row( string $field_key, string $field_name, array $row, array $fields, int $post_id ): void {
	update_field( $field_key, array( $row ), $post_id );
	if ( ! function_exists( 'update_sub_field' ) ) {
		return;
	}
	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) || empty( $field['name'] ) || ! isset( $row[ $field['name'] ] ) ) {
			continue;
		}
		// Saving from the row path makes ACF persist the full layout schema,
		// including regular text fields and nested repeater metadata. A root
		// flexible-content update alone drops both on some ACF versions.
		update_sub_field( array( $field_name, 1, $field['name'] ), $row[ $field['name'] ], $post_id );
	}
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
			'post_status'    => 'any',
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
		if ( 'private' !== get_post_status( $post_id ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'private' ) );
		}

		update_post_meta( $post_id, MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_FIXTURE_META, $layout_name );
		$layout_fields = mrn_active_style_guide_get_layout_library_layout_fields( $entry, $layout_name );
		$blueprint = mrn_active_style_guide_get_layout_library_fixture_blueprint( $layout_name );
		if ( 'reusable_block' === $layout_name ) {
			$blueprint['block'] = mrn_active_style_guide_get_layout_library_reusable_block_id();
		}
		if ( 'wpforms' === $layout_name ) {
			$blueprint['form'] = mrn_active_style_guide_get_layout_library_wpforms_form_id();
		}
		$row = array_merge(
			array( 'acf_fc_layout' => $layout_name ),
			mrn_active_style_guide_get_layout_library_sample_values( $layout_fields, $layout_name ),
			$blueprint
		);
		mrn_active_style_guide_update_layout_library_fixture_row( $field_key, $field_name, $row, $layout_fields, $post_id );
		$result['created'][ $layout_name ] = $post_id;
	}

	return $result;
}

/** Build the capture plan used by the reviewed Playwright capture tool. */
function mrn_active_style_guide_get_layout_library_capture_manifest(): array {
	$targets = array();
	$unavailable = array();
	foreach ( mrn_active_style_guide_get_layout_library_catalog() as $layout_name => $entry ) {
		$post_id = mrn_active_style_guide_find_layout_library_representative( $layout_name );
		$source = 'published';
		if ( $post_id <= 0 ) {
			$fixtures = get_posts( array(
				'post_type' => 'page', 'post_status' => 'any', 'posts_per_page' => 1,
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
				// Every fixture owns exactly one layout, while individual renderer
				// wrappers vary. The page article is the stable capture boundary.
				'selector' => '#post-' . $post_id,
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

/**
 * Render a purpose-built, authenticated document for an HTML layout preview.
 *
 * This deliberately renders the builder markup rather than a screenshot or a
 * theme page with its navigation hidden by CSS. The fixture remains private,
 * and only people able to manage the theme may view it.
 */
function mrn_active_style_guide_render_layout_library_preview_document(): void {
	if ( empty( $_GET['mrn-layout-library-preview'] ) || ! is_singular() ) {
		return;
	}

	$post_id = get_queried_object_id();
	$layout_name = sanitize_key( (string) get_post_meta( $post_id, MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_FIXTURE_META, true ) );
	$catalog = mrn_active_style_guide_get_layout_library_catalog();
	if ( ! $post_id || ! current_user_can( 'edit_theme_options' ) || '' === $layout_name || empty( $catalog[ $layout_name ] ) ) {
		wp_die( esc_html__( 'This Layout Library preview is not available.', 'mrn-active-style-guide' ), '', array( 'response' => 404 ) );
	}

	$field_name = (string) $catalog[ $layout_name ]['field_name'];
	$markup = function_exists( 'mrn_base_stack_get_builder_markup' ) ? mrn_base_stack_get_builder_markup( $field_name, $post_id ) : '';
	if ( '' === trim( (string) $markup ) ) {
		wp_die( esc_html__( 'This layout does not yet have a valid preview fixture.', 'mrn-active-style-guide' ), '', array( 'response' => 404 ) );
	}

	nocache_headers();
	?>
	<!doctype html>
	<html <?php language_attributes(); ?>><head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
	<style id="mrn-layout-library-preview-document-styles">
		html { margin: 0 !important; }
		body { margin: 0; min-width: 0; background: #fff; }
		.mrn-layout-library-preview-document { overflow: hidden; }
	</style>
	</head><body <?php body_class( 'mrn-layout-library-preview-document' ); ?>>
	<?php wp_body_open(); ?>
	<main id="mrn-layout-library-preview" aria-label="<?php esc_attr_e( 'Layout preview', 'mrn-active-style-guide' ); ?>">
		<?php echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Stack renderer escapes its complete markup contract. ?>
	</main>
	<?php wp_footer(); ?>
	</body></html>
	<?php
	exit;
}
add_action( 'template_redirect', 'mrn_active_style_guide_render_layout_library_preview_document', 99 );

/** Return catalog metadata used by the searchable editor guide. */
function mrn_active_style_guide_get_layout_library_guide_metadata( string $layout_name, array $entry ): array {
	$metadata = function_exists( 'mrn_base_stack_get_acf_layout_picker_metadata' )
		? mrn_base_stack_get_acf_layout_picker_metadata( $layout_name, $entry['layout'] ?? array(), array( 'name' => $entry['field_name'] ?? '' ) )
		: array();
	$category = isset( $metadata['category'] ) ? (string) $metadata['category'] : '';
	if ( '' === $category ) {
		if ( preg_match( '/cta|form|wpforms/', $layout_name ) ) {
			$category = __( 'Calls to action and forms', 'mrn-active-style-guide' );
		} elseif ( preg_match( '/image|video|slider|showcase|logos/', $layout_name ) ) {
			$category = __( 'Media and showcase', 'mrn-active-style-guide' );
		} elseif ( preg_match( '/card|grid|list|stats/', $layout_name ) ) {
			$category = __( 'Content collections', 'mrn-active-style-guide' );
		} elseif ( preg_match( '/faq|tab|jump/', $layout_name ) ) {
			$category = __( 'Interactive content', 'mrn-active-style-guide' );
		} else {
			$category = __( 'Content and structure', 'mrn-active-style-guide' );
		}
	}
	$keywords = isset( $metadata['keywords'] ) && is_array( $metadata['keywords'] ) ? $metadata['keywords'] : array();
	return array(
		'category'    => $category,
		'description' => isset( $metadata['description'] ) && '' !== $metadata['description'] ? (string) $metadata['description'] : __( 'A site-aware layout available in the active Stack theme.', 'mrn-active-style-guide' ),
		'keywords'    => $keywords,
	);
}

/** Render the admin Layout Library page. */
function mrn_active_style_guide_render_layout_library_page(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to view the Layout Library.', 'mrn-active-style-guide' ) );
	}

	$state = get_option( MRN_ACTIVE_STYLE_GUIDE_LAYOUT_LIBRARY_OPTION, array() );
	$state = is_array( $state ) ? $state : array();
	$fingerprint = mrn_active_style_guide_get_layout_library_fingerprint();
	$is_stale = empty( $state['fingerprint'] ) || ! hash_equals( (string) $state['fingerprint'], $fingerprint );
	$catalog = mrn_active_style_guide_get_layout_library_catalog();
	$manifest = mrn_active_style_guide_get_layout_library_capture_manifest();
	$previews = array();
	foreach ( array_merge( $manifest['targets'], $manifest['unavailable'] ) as $preview ) {
		$previews[ $preview['layout'] ] = $preview;
	}
	$categories = array();
	foreach ( $catalog as $layout_name => $entry ) {
		$categories[] = mrn_active_style_guide_get_layout_library_guide_metadata( $layout_name, $entry )['category'];
	}
	$categories = array_values( array_unique( $categories ) );
	sort( $categories, SORT_NATURAL | SORT_FLAG_CASE );
	?>
	<div class="wrap mrn-layout-library">
		<h1><?php esc_html_e( 'Layout Library', 'mrn-active-style-guide' ); ?></h1>
		<p class="mrn-layout-library__intro"><?php esc_html_e( 'Choose a layout for your content. Each card uses this site’s current theme and shows the fields an editor can change.', 'mrn-active-style-guide' ); ?></p>
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
		<div class="mrn-layout-library__tools">
			<label for="mrn-layout-library-search"><?php esc_html_e( 'Search layouts', 'mrn-active-style-guide' ); ?></label>
			<input type="search" id="mrn-layout-library-search" placeholder="<?php esc_attr_e( 'Text, image, FAQ, call to action…', 'mrn-active-style-guide' ); ?>">
			<label for="mrn-layout-library-category"><?php esc_html_e( 'Layout family', 'mrn-active-style-guide' ); ?></label>
			<select id="mrn-layout-library-category"><option value=""><?php esc_html_e( 'All families', 'mrn-active-style-guide' ); ?></option><?php foreach ( $categories as $category ) : ?><option value="<?php echo esc_attr( $category ); ?>"><?php echo esc_html( $category ); ?></option><?php endforeach; ?></select>
			<p id="mrn-layout-library-count" role="status"></p>
		</div>
		<style>
			.mrn-layout-library { max-width:1500px; }
			.mrn-layout-library__intro { font-size:16px; max-width:800px; }
			.mrn-layout-library__tools { display:flex; flex-wrap:wrap; align-items:center; gap:12px; margin:24px 0; }
			.mrn-layout-library__tools input { min-width:min(100%,300px); }
			.mrn-layout-library-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(min(100%,340px),1fr)); gap:24px; margin:20px 0; }
			.mrn-layout-library-card { background:#fff; border:1px solid #ccd0d4; border-radius:6px; overflow:hidden; }
			.mrn-layout-library-card[hidden] { display:none; }
			.mrn-layout-library-card__header { display:flex; gap:12px; align-items:baseline; justify-content:space-between; padding:12px 14px; }
			.mrn-layout-library-card__title { margin:0; font-size:18px; }
			.mrn-layout-library-card__frame { display:block; width:100%; height:360px; border:0; border-top:1px solid #ccd0d4; background:#f6f7f7; }
			.mrn-layout-library-card__unavailable { display:grid; min-height:300px; place-items:center; padding:20px; box-sizing:border-box; background:#f6f7f7; color:#50575e; text-align:center; }
			.mrn-layout-library-card__copy { padding:16px; }
			.mrn-layout-library-card__category { color:#50575e; font-size:12px; margin:0 0 8px; }
			.mrn-layout-library-card__meta { color:#50575e; font-size:12px; }
			.mrn-layout-library :focus-visible { outline:3px solid #2271b1; outline-offset:3px; }
		</style>
		<div class="mrn-layout-library-grid">
		<?php foreach ( $catalog as $layout_name => $entry ) : ?>
			<?php
			$guide = mrn_active_style_guide_get_layout_library_guide_metadata( $layout_name, $entry );
			$preview = $previews[ $layout_name ] ?? null;
			$fields = array_filter( mrn_active_style_guide_get_layout_library_layout_fields( $entry, $layout_name ), static function ( $field ) { return is_array( $field ) && ! empty( $field['label'] ) && ! in_array( $field['type'] ?? '', array( 'tab', 'accordion', 'message' ), true ); } );
			$search = strtolower( $entry['layout']['label'] . ' ' . $layout_name . ' ' . $guide['description'] . ' ' . implode( ' ', $guide['keywords'] ) );
			?>
			<article class="mrn-layout-library-card" data-category="<?php echo esc_attr( $guide['category'] ); ?>" data-search="<?php echo esc_attr( $search ); ?>">
				<div class="mrn-layout-library-card__header">
					<h2 class="mrn-layout-library-card__title"><?php echo esc_html( $entry['layout']['label'] ); ?></h2>
					<?php if ( $preview && in_array( $preview, $manifest['targets'], true ) ) : ?><a href="<?php echo esc_url( add_query_arg( 'mrn-layout-library-preview', '1', (string) $preview['url'] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open full size', 'mrn-active-style-guide' ); ?></a><?php endif; ?>
				</div>
				<?php if ( $preview && in_array( $preview, $manifest['targets'], true ) ) : ?><iframe class="mrn-layout-library-card__frame" title="<?php echo esc_attr( sprintf( __( '%s live HTML layout preview', 'mrn-active-style-guide' ), $entry['layout']['label'] ) ); ?>" src="<?php echo esc_url( add_query_arg( 'mrn-layout-library-preview', '1', (string) $preview['url'] ) ); ?>" loading="lazy"></iframe><?php else : ?><div class="mrn-layout-library-card__unavailable"><?php esc_html_e( 'Regenerate to create this layout’s private HTML preview fixture.', 'mrn-active-style-guide' ); ?></div><?php endif; ?>
				<div class="mrn-layout-library-card__copy"><p class="mrn-layout-library-card__category"><?php echo esc_html( $guide['category'] ); ?></p><p><?php echo esc_html( $guide['description'] ); ?></p><details><summary><?php esc_html_e( 'Editable fields', 'mrn-active-style-guide' ); ?></summary><ul><?php foreach ( $fields as $field ) : ?><li><strong><?php echo esc_html( $field['label'] ); ?></strong><?php if ( 'repeater' === ( $field['type'] ?? '' ) ) : ?> — <?php esc_html_e( 'repeatable items', 'mrn-active-style-guide' ); ?><?php endif; ?></li><?php endforeach; ?></ul></details><p class="mrn-layout-library-card__meta"><code><?php echo esc_html( $layout_name ); ?></code> · <?php echo esc_html( $preview ? ucfirst( (string) $preview['source'] ) : __( 'No preview source', 'mrn-active-style-guide' ) ); ?></p></div>
			</article>
		<?php endforeach; ?>
		</div>
		<p class="mrn-layout-library__empty" hidden><?php esc_html_e( 'No layouts match. Try another search or choose All families.', 'mrn-active-style-guide' ); ?></p>
		<script>document.addEventListener('DOMContentLoaded',function(){var s=document.getElementById('mrn-layout-library-search'),c=document.getElementById('mrn-layout-library-category'),a=[].slice.call(document.querySelectorAll('.mrn-layout-library-card')),n=document.getElementById('mrn-layout-library-count'),e=document.querySelector('.mrn-layout-library__empty');function f(){var q=s.value.trim().toLowerCase(),v=0;a.forEach(function(x){x.hidden=!!((q&&-1===x.dataset.search.indexOf(q))||(c.value&&c.value!==x.dataset.category));if(!x.hidden){v++;}});n.textContent=v+' of '+a.length+' layouts';e.hidden=0!==v;}s.addEventListener('input',f);c.addEventListener('change',f);f();});</script>
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

	wp_safe_redirect( add_query_arg( 'mrn-layout-library-regenerated', '1', admin_url( 'themes.php?page=mrn-layout-library' ) ) );
	exit;
}
add_action( 'admin_post_mrn_active_style_guide_regenerate_layout_library', 'mrn_active_style_guide_handle_layout_library_regeneration' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/** Expose only explicit local/on-host capture operations to WP-CLI. */
	WP_CLI::add_command( 'mrn-layout-library capture-manifest', static function (): void {
		WP_CLI::line( wp_json_encode( mrn_active_style_guide_get_layout_library_capture_manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	} );
}

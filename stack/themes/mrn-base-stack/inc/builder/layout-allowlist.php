<?php
/**
 * Builder layout allowlist controls for classic editor screens.
 *
 * @package mrn-base-stack
 */

/**
 * Get builder flexible-content fields that use per-entry allowlists.
 *
 * @return array<string, array<string, string>>
 */
function mrn_base_stack_get_builder_layout_allowlist_targets() {
	return array(
		'page_hero_rows' => array(
			'field_key' => 'field_mrn_page_hero_rows',
			'group_key' => 'group_mrn_hero_builder',
			'label'     => 'Hero',
		),
		'page_content_rows' => array(
			'field_key' => 'field_mrn_page_content_rows',
			'group_key' => 'group_mrn_content_builder',
			'label'     => 'Content',
		),
		'page_after_content_rows' => array(
			'field_key' => 'field_mrn_page_after_content_rows',
			'group_key' => 'group_mrn_after_content_builder',
			'label'     => 'After Content',
		),
	);
}

/**
 * Get the post-meta key used for per-entry builder layout allowlists.
 *
 * @return string
 */
function mrn_base_stack_get_builder_layout_allowlist_meta_key() {
	return '_mrn_builder_layout_allowlist';
}

/**
 * Parse a WordPress post reference into a numeric post ID.
 *
 * @param mixed $reference Raw post reference.
 * @return int
 */
function mrn_base_stack_parse_builder_layout_allowlist_post_id_reference( $reference ) {
	if ( is_numeric( $reference ) ) {
		return absint( $reference );
	}

	if ( is_string( $reference ) && preg_match( '/^post_(\d+)$/', $reference, $matches ) ) {
		return absint( $matches[1] );
	}

	return 0;
}

/**
 * Resolve the active editor post ID for ACF field allowlist filtering.
 *
 * @return int
 */
function mrn_base_stack_get_builder_layout_allowlist_post_id() {
	$post_id = 0;

	if ( function_exists( 'acf_get_form_data' ) ) {
		$post_id = mrn_base_stack_parse_builder_layout_allowlist_post_id_reference( acf_get_form_data( 'post_id' ) );
	}

	if ( $post_id < 1 && isset( $_POST['post_ID'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only context lookup.
		$post_id = mrn_base_stack_parse_builder_layout_allowlist_post_id_reference( wp_unslash( $_POST['post_ID'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only context lookup.
	}

	if ( $post_id < 1 && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
		$post_id = mrn_base_stack_parse_builder_layout_allowlist_post_id_reference( wp_unslash( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
	}

	if ( $post_id < 1 ) {
		$post = get_post();
		if ( $post instanceof WP_Post ) {
			$post_id = (int) $post->ID;
		}
	}

	return max( 0, (int) $post_id );
}

/**
 * Resolve the current admin post type for builder allowlist usage.
 *
 * @param int $post_id Optional post ID.
 * @return string
 */
function mrn_base_stack_get_builder_layout_allowlist_context_post_type( $post_id = 0 ) {
	$post_type = '';
	$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( $screen instanceof WP_Screen ) {
		$post_type = sanitize_key( (string) $screen->post_type );
	}

	if ( '' === $post_type && $post_id > 0 ) {
		$post_type = sanitize_key( (string) get_post_type( $post_id ) );
	}

	if ( '' === $post_type && isset( $_REQUEST['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
		$post_type = sanitize_key( (string) wp_unslash( $_REQUEST['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
	}

	if ( '' === $post_type && isset( $_REQUEST['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
		$post_type = sanitize_key( (string) get_post_type( absint( wp_unslash( $_REQUEST['post'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
	}

	return $post_type;
}

/**
 * Determine whether the current request should apply builder allowlist logic.
 *
 * @param int $post_id Optional post ID.
 * @return bool
 */
function mrn_base_stack_is_builder_layout_allowlist_context( $post_id = 0 ) {
	if ( ! is_admin() ) {
		return false;
	}

	$post_type = mrn_base_stack_get_builder_layout_allowlist_context_post_type( $post_id );
	if ( '' === $post_type ) {
		return false;
	}

	return in_array( $post_type, mrn_base_stack_get_singular_shell_post_types(), true );
}

/**
 * Build a catalog of flexible-content layouts from an ACF field definition.
 *
 * @param array<string, mixed> $field Field definition.
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_builder_layout_allowlist_catalog_from_field( array $field ) {
	if ( empty( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
		return array();
	}

	$catalog = array();

	foreach ( $field['layouts'] as $layout ) {
		if ( ! is_array( $layout ) ) {
			continue;
		}

		$name  = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
		$label = isset( $layout['label'] ) ? trim( wp_strip_all_tags( (string) $layout['label'] ) ) : '';

		if ( '' === $name ) {
			continue;
		}

		if ( '' === $label ) {
			$label = ucfirst( str_replace( array( '-', '_' ), ' ', $name ) );
		}

		$catalog[ $name ] = array(
			'label'        => $label,
			'layout'       => $layout,
			'is_page_only' => false !== stripos( $label, '(Page Only)' ),
		);
	}

	return $catalog;
}

/**
 * Resolve the unfiltered ACF field definition for a builder allowlist target.
 *
 * @param string $field_name Flexible-content field name.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_builder_layout_allowlist_field_definition( $field_name ) {
	static $cache = array();

	$field_name = sanitize_key( (string) $field_name );
	if ( isset( $cache[ $field_name ] ) ) {
		return $cache[ $field_name ];
	}

	$targets = mrn_base_stack_get_builder_layout_allowlist_targets();
	if ( ! isset( $targets[ $field_name ] ) || ! is_array( $targets[ $field_name ] ) ) {
		$cache[ $field_name ] = array();
		return $cache[ $field_name ];
	}

	$target    = $targets[ $field_name ];
	$field_key = isset( $target['field_key'] ) ? (string) $target['field_key'] : '';
	$group_key = isset( $target['group_key'] ) ? (string) $target['group_key'] : '';

	if ( function_exists( 'acf_get_fields' ) && '' !== $group_key ) {
		$fields = acf_get_fields( $group_key );
		if ( is_array( $fields ) ) {
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$current_key  = isset( $field['key'] ) ? (string) $field['key'] : '';
				$current_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';

				if ( $current_key === $field_key || $current_name === $field_name ) {
					$cache[ $field_name ] = $field;
					return $cache[ $field_name ];
				}
			}
		}
	}

	if ( function_exists( 'acf_get_field' ) && '' !== $field_key ) {
		$field = acf_get_field( $field_key );
		if ( is_array( $field ) ) {
			$cache[ $field_name ] = $field;
			return $cache[ $field_name ];
		}
	}

	$cache[ $field_name ] = array();
	return $cache[ $field_name ];
}

/**
 * Get layout names currently used by a flexible-content field on a post.
 *
 * @param int    $post_id Post ID.
 * @param string $field_name Flexible-content field name.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_allowlist_used_layout_names( $post_id, $field_name ) {
	$post_id    = absint( $post_id );
	$field_name = sanitize_key( (string) $field_name );

	if ( $post_id < 1 || '' === $field_name ) {
		return array();
	}

	$row_count = absint( get_post_meta( $post_id, $field_name, true ) );
	if ( $row_count < 1 ) {
		return array();
	}

	$layout_names = array();

	for ( $index = 0; $index < $row_count; $index++ ) {
		$layout_name = sanitize_key( (string) get_post_meta( $post_id, $field_name . '_' . $index . '_acf_fc_layout', true ) );
		if ( '' !== $layout_name ) {
			$layout_names[] = $layout_name;
		}
	}

	return array_values( array_unique( $layout_names ) );
}

/**
 * Get configurable layout names from a field catalog.
 *
 * Configurable names exclude internal page-only rows.
 *
 * @param array<string, array<string, mixed>> $catalog Field catalog.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_allowlist_configurable_names( array $catalog ) {
	$names = array();

	foreach ( $catalog as $name => $layout_meta ) {
		$name = sanitize_key( (string) $name );
		if ( '' === $name ) {
			continue;
		}

		if ( ! empty( $layout_meta['is_page_only'] ) ) {
			continue;
		}

		$names[] = $name;
	}

	return array_values( array_unique( $names ) );
}

/**
 * Get saved allowlist settings for a post.
 *
 * @param int $post_id Post ID.
 * @return array<string, array<int, string>>
 */
function mrn_base_stack_get_builder_layout_allowlist_saved_settings( $post_id ) {
	$post_id = absint( $post_id );
	$targets = mrn_base_stack_get_builder_layout_allowlist_targets();
	$saved   = array();

	foreach ( $targets as $field_name => $target ) {
		$saved[ $field_name ] = array();
	}

	if ( $post_id < 1 ) {
		return $saved;
	}

	$raw = get_post_meta( $post_id, mrn_base_stack_get_builder_layout_allowlist_meta_key(), true );
	if ( ! is_array( $raw ) ) {
		return $saved;
	}

	foreach ( $targets as $field_name => $target ) {
		if ( empty( $raw[ $field_name ] ) || ! is_array( $raw[ $field_name ] ) ) {
			continue;
		}

		$saved[ $field_name ] = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $raw[ $field_name ] )
				)
			)
		);
	}

	return $saved;
}

/**
 * Get default layout limits for unsaved allowlists.
 *
 * @return array<string, int>
 */
function mrn_base_stack_get_builder_layout_allowlist_default_limits() {
	$defaults = array(
		'page_hero_rows'          => 4,
		'page_content_rows'       => 8,
		'page_after_content_rows' => 6,
	);
	$limits   = apply_filters( 'mrn_base_stack_builder_layout_allowlist_default_limits', $defaults );

	if ( ! is_array( $limits ) ) {
		return $defaults;
	}

	$normalized = array();

	foreach ( $defaults as $field_name => $fallback ) {
		$normalized[ $field_name ] = isset( $limits[ $field_name ] ) ? max( 0, absint( $limits[ $field_name ] ) ) : $fallback;
	}

	return $normalized;
}

/**
 * Get default allowlist values for a flexible-content field.
 *
 * @param string                              $field_name Flexible-content field name.
 * @param array<string, array<string, mixed>> $catalog Layout catalog.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_allowlist_default_names( $field_name, array $catalog ) {
	$field_name         = sanitize_key( (string) $field_name );
	$configurable_names = mrn_base_stack_get_builder_layout_allowlist_configurable_names( $catalog );
	$limit_map          = mrn_base_stack_get_builder_layout_allowlist_default_limits();
	$limit              = isset( $limit_map[ $field_name ] ) ? (int) $limit_map[ $field_name ] : 0;
	$defaults           = $limit > 0 ? array_slice( $configurable_names, 0, $limit ) : array();

	$defaults = apply_filters(
		'mrn_base_stack_builder_layout_allowlist_defaults',
		$defaults,
		$field_name,
		$configurable_names,
		$catalog
	);

	if ( ! is_array( $defaults ) ) {
		return $limit > 0 ? array_slice( $configurable_names, 0, $limit ) : array();
	}

	return array_values(
		array_unique(
			array_intersect(
				array_filter(
					array_map( 'sanitize_key', $defaults )
				),
				$configurable_names
			)
		)
	);
}

/**
 * Get the effective layout allowlist for a post and flexible-content field.
 *
 * @param int                                 $post_id Post ID.
 * @param string                              $field_name Flexible-content field name.
 * @param array<string, array<string, mixed>> $catalog Layout catalog.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_allowlist_effective_names( $post_id, $field_name, array $catalog ) {
	$post_id            = absint( $post_id );
	$field_name         = sanitize_key( (string) $field_name );
	$all_layout_names   = array_keys( $catalog );
	$configurable_names = mrn_base_stack_get_builder_layout_allowlist_configurable_names( $catalog );
	$configured_names   = mrn_base_stack_get_builder_layout_allowlist_default_names( $field_name, $catalog );

	if ( $post_id > 0 && metadata_exists( 'post', $post_id, mrn_base_stack_get_builder_layout_allowlist_meta_key() ) ) {
		$saved_settings = mrn_base_stack_get_builder_layout_allowlist_saved_settings( $post_id );
		$configured_names = isset( $saved_settings[ $field_name ] ) && is_array( $saved_settings[ $field_name ] )
			? $saved_settings[ $field_name ]
			: array();
	}

	$configured_names = array_values(
		array_unique(
			array_intersect(
				array_filter(
					array_map( 'sanitize_key', $configured_names )
				),
				$configurable_names
			)
		)
	);

	$used_names = $post_id > 0 ? mrn_base_stack_get_builder_layout_allowlist_used_layout_names( $post_id, $field_name ) : array();
	$effective  = array_values(
		array_unique(
			array_merge( $configured_names, $used_names )
		)
	);

	$effective = array_values(
		array_unique(
			array_intersect(
				array_filter(
					array_map( 'sanitize_key', $effective )
				),
				$all_layout_names
			)
		)
	);

	if ( ! empty( $effective ) ) {
		return $effective;
	}

	if ( ! empty( $used_names ) ) {
		return array_values(
			array_unique(
				array_intersect( $used_names, $all_layout_names )
			)
		);
	}

	if ( ! empty( $configurable_names ) ) {
		return array( $configurable_names[0] );
	}

	return ! empty( $all_layout_names ) ? array( $all_layout_names[0] ) : array();
}

/**
 * Apply per-entry allowlist filtering to builder flexible-content field layouts.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_filter_builder_layout_allowlist_field_layouts( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
	$targets    = mrn_base_stack_get_builder_layout_allowlist_targets();

	if ( '' === $field_name || ! isset( $targets[ $field_name ] ) ) {
		return $field;
	}

	if ( empty( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
		return $field;
	}

	$post_id = mrn_base_stack_get_builder_layout_allowlist_post_id();
	if ( ! mrn_base_stack_is_builder_layout_allowlist_context( $post_id ) ) {
		return $field;
	}

	$catalog = mrn_base_stack_get_builder_layout_allowlist_catalog_from_field( $field );
	if ( empty( $catalog ) ) {
		return $field;
	}

	$effective_names = mrn_base_stack_get_builder_layout_allowlist_effective_names( $post_id, $field_name, $catalog );
	if ( empty( $effective_names ) ) {
		return $field;
	}

	$effective_lookup = array_fill_keys( $effective_names, true );
	$filtered_layouts = array();

	foreach ( $field['layouts'] as $layout_key => $layout ) {
		if ( ! is_array( $layout ) ) {
			continue;
		}

		$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
		if ( '' === $layout_name || ! isset( $effective_lookup[ $layout_name ] ) ) {
			continue;
		}

		$filtered_layouts[ $layout_key ] = $layout;
	}

	if ( ! empty( $filtered_layouts ) ) {
		$field['layouts'] = $filtered_layouts;
	}

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_page_hero_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/load_field/key=field_mrn_page_content_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/load_field/key=field_mrn_page_after_content_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/prepare_field/key=field_mrn_page_hero_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/prepare_field/key=field_mrn_page_content_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/prepare_field/key=field_mrn_page_after_content_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );

/**
 * Register a classic-editor metabox for per-entry builder layout allowlists.
 *
 * @return void
 */
function mrn_base_stack_register_builder_layout_allowlist_meta_box() {
	if ( ! is_admin() || ! function_exists( 'add_meta_box' ) ) {
		return;
	}

	foreach ( mrn_base_stack_get_singular_shell_post_types() as $post_type ) {
		add_meta_box(
			'mrn-builder-layout-allowlist',
			'Builder Layout Allowlist',
			'mrn_base_stack_render_builder_layout_allowlist_meta_box',
			$post_type,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'mrn_base_stack_register_builder_layout_allowlist_meta_box' );

/**
 * Render the per-entry builder layout allowlist metabox.
 *
 * @param WP_Post $post Current post object.
 * @return void
 */
function mrn_base_stack_render_builder_layout_allowlist_meta_box( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$post_id   = (int) $post->ID;
	$targets   = mrn_base_stack_get_builder_layout_allowlist_targets();
	$has_saved = metadata_exists( 'post', $post_id, mrn_base_stack_get_builder_layout_allowlist_meta_key() );
	$saved     = mrn_base_stack_get_builder_layout_allowlist_saved_settings( $post_id );

	wp_nonce_field( 'mrn_base_stack_builder_layout_allowlist_save', 'mrn_base_stack_builder_layout_allowlist_nonce' );
	?>
	<p>Select which layout types should be loaded for this entry. Save and reload to apply changes.</p>
	<p><em>Layouts already used on this entry always stay available for editing.</em></p>
	<?php

	foreach ( $targets as $field_name => $target ) {
		$catalog            = mrn_base_stack_get_builder_layout_allowlist_catalog_from_field( mrn_base_stack_get_builder_layout_allowlist_field_definition( $field_name ) );
		$configurable_names = mrn_base_stack_get_builder_layout_allowlist_configurable_names( $catalog );
		$label              = isset( $target['label'] ) ? (string) $target['label'] : ucfirst( str_replace( array( '-', '_' ), ' ', $field_name ) );

		if ( empty( $catalog ) ) {
			continue;
		}

		$selected = $has_saved
			? ( isset( $saved[ $field_name ] ) && is_array( $saved[ $field_name ] ) ? $saved[ $field_name ] : array() )
			: mrn_base_stack_get_builder_layout_allowlist_default_names( $field_name, $catalog );
		$selected = array_fill_keys(
			array_values(
				array_unique(
					array_intersect(
						array_filter(
							array_map( 'sanitize_key', $selected )
						),
						$configurable_names
					)
				)
			),
			true
		);

		$used_names         = mrn_base_stack_get_builder_layout_allowlist_used_layout_names( $post_id, $field_name );
		$used_nonselectable = array();

		foreach ( $used_names as $used_name ) {
			if ( in_array( $used_name, $configurable_names, true ) ) {
				continue;
			}

			$used_nonselectable[] = $used_name;
		}
		?>
		<hr />
		<p><strong><?php echo esc_html( $label ); ?></strong></p>
		<?php if ( empty( $configurable_names ) ) : ?>
			<p><em>No selectable layouts are available for this section.</em></p>
		<?php else : ?>
			<div style="max-height: 180px; overflow: auto; border: 1px solid #dcdcde; padding: 8px;">
				<?php foreach ( $configurable_names as $layout_name ) : ?>
					<?php
					$layout_label = isset( $catalog[ $layout_name ]['label'] ) ? (string) $catalog[ $layout_name ]['label'] : $layout_name;
					?>
					<label style="display:block; margin: 0 0 6px;">
						<input
							type="checkbox"
							name="mrn_builder_layout_allowlist[<?php echo esc_attr( $field_name ); ?>][]"
							value="<?php echo esc_attr( $layout_name ); ?>"
							<?php checked( isset( $selected[ $layout_name ] ) ); ?>
						/>
						<?php echo esc_html( $layout_label ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $used_nonselectable ) ) : ?>
			<p style="margin-top:8px;"><em>Already used and forced-available:</em> <?php echo esc_html( implode( ', ', $used_nonselectable ) ); ?></p>
		<?php endif; ?>
		<?php
	}
}

/**
 * Save per-entry builder layout allowlist selections.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post Post object.
 * @return void
 */
function mrn_base_stack_save_builder_layout_allowlist_meta_box( $post_id, $post ) {
	$post_id = absint( $post_id );

	if ( $post_id < 1 || ! $post instanceof WP_Post ) {
		return;
	}

	if ( ! isset( $_POST['mrn_base_stack_builder_layout_allowlist_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mrn_base_stack_builder_layout_allowlist_nonce'] ) ), 'mrn_base_stack_builder_layout_allowlist_save' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verification handled inline.
		return;
	}

	if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
		return;
	}

	$post_type = sanitize_key( (string) $post->post_type );
	if ( '' === $post_type || ! in_array( $post_type, mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$input      = isset( $_POST['mrn_builder_layout_allowlist'] ) && is_array( $_POST['mrn_builder_layout_allowlist'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verification handled inline.
		? wp_unslash( $_POST['mrn_builder_layout_allowlist'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verification handled inline.
		: array();
	$targets    = mrn_base_stack_get_builder_layout_allowlist_targets();
	$allowlists = array();

	foreach ( $targets as $field_name => $target ) {
		$catalog            = mrn_base_stack_get_builder_layout_allowlist_catalog_from_field( mrn_base_stack_get_builder_layout_allowlist_field_definition( $field_name ) );
		$configurable_names = mrn_base_stack_get_builder_layout_allowlist_configurable_names( $catalog );
		$selected           = isset( $input[ $field_name ] ) && is_array( $input[ $field_name ] )
			? array_filter(
				array_map(
					'sanitize_key',
					$input[ $field_name ]
				)
			)
			: array();

		$allowlists[ $field_name ] = array_values(
			array_unique(
				array_intersect(
					$selected,
					$configurable_names
				)
			)
		);
	}

	update_post_meta( $post_id, mrn_base_stack_get_builder_layout_allowlist_meta_key(), $allowlists );
}
add_action( 'save_post', 'mrn_base_stack_save_builder_layout_allowlist_meta_box', 10, 2 );

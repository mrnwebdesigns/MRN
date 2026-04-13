<?php
/**
 * Builder helpers and nested layout definitions.
 *
 * @package mrn-base-stack
 */

/**
 * Build shared context passed into row template parts.
 *
 * @param array<string, mixed> $row Builder row data.
 * @param int|string           $post_id Current post ID.
 * @param int|string           $index Row index.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_builder_row_context( array $row, $post_id, $index ) {
	return array(
		'row'     => $row,
		'post_id' => (int) $post_id,
		'index'   => (int) $index,
	);
}

/**
 * Clone an ACF layout tree while making every `key` unique.
 *
 * @param array<string, mixed> $value ACF layout or field tree.
 * @param string               $prefix Prefix to prepend to each ACF key.
 * @return array<string, mixed>
 */
function mrn_base_stack_clone_acf_keys_with_prefix( array $value, $prefix ) {
	foreach ( $value as $item_key => $item_value ) {
		if ( 'key' === $item_key && is_string( $item_value ) ) {
			$value[ $item_key ] = $prefix . $item_value;
			continue;
		}

		if ( 'field' === $item_key && is_string( $item_value ) && 0 === strpos( $item_value, 'field_' ) ) {
			$value[ $item_key ] = $prefix . $item_value;
			continue;
		}

		if ( is_array( $item_value ) ) {
			$value[ $item_key ] = mrn_base_stack_clone_acf_keys_with_prefix( $item_value, $prefix );
		}
	}

	return $value;
}

/**
 * Shared section-width choices for theme-owned builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_section_width_choices() {
	return array(
		'content'    => 'Content',
		'wide'       => 'Wide',
		'full-width' => 'Full Width',
	);
}

/**
 * Shared post-type choices for query-driven builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_content_list_post_type_choices() {
	$post_types = get_post_types(
		array(
			'public'  => true,
			'show_ui' => true,
		),
		'objects'
	);
	$choices    = array();
	$excluded   = array(
		'attachment',
		'wp_block',
		'wp_font_face',
		'wp_font_family',
		'wp_global_styles',
		'wp_navigation',
		'wp_template',
		'wp_template_part',
		'acf-field',
		'acf-field-group',
	);

	foreach ( $post_types as $post_type => $post_type_object ) {
		if ( ! $post_type_object instanceof WP_Post_Type ) {
			continue;
		}

		if ( in_array( $post_type, $excluded, true ) ) {
			continue;
		}

		$label = isset( $post_type_object->labels->name ) ? trim( (string) $post_type_object->labels->name ) : '';
		if ( '' === $label ) {
			$label = ucfirst( str_replace( array( '-', '_' ), ' ', $post_type ) );
		}

		$choices[ $post_type ] = $label;
	}

	if ( empty( $choices['post'] ) ) {
		$choices = array_merge( array( 'post' => 'Posts' ), $choices );
	}

	return $choices;
}

/**
 * Load live post-type choices into the Content Lists builder field.
 *
 * This keeps the row selector aligned with the currently registered public
 * content types instead of only the choices present when the field group was
 * registered.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_load_content_list_post_type_field_choices( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field['choices'] = mrn_base_stack_get_content_list_post_type_choices();

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_content_lists_post_type', 'mrn_base_stack_load_content_list_post_type_field_choices' );
add_filter( 'acf/load_field/name=list_post_type', 'mrn_base_stack_load_content_list_post_type_field_choices' );
add_filter( 'acf/prepare_field/key=field_mrn_content_lists_post_type', 'mrn_base_stack_load_content_list_post_type_field_choices' );
add_filter( 'acf/prepare_field/name=list_post_type', 'mrn_base_stack_load_content_list_post_type_field_choices' );

/**
 * Get the capability required to view Effects controls in builder/admin UIs.
 *
 * Advanced Menu Editor can grant this custom capability to non-admin roles
 * that should retain access to Effects tabs and motion settings.
 *
 * @return string
 */
function mrn_base_stack_get_effects_controls_capability() {
	$capability = apply_filters( 'mrn_base_stack_effects_controls_capability', 'layout_effects' );

	if ( ! is_string( $capability ) || '' === $capability ) {
		return 'layout_effects';
	}

	return sanitize_key( $capability );
}

/**
 * Determine whether the current user can view Effects controls.
 *
 * Access is controlled by a dedicated custom capability so Advanced Menu Editor
 * can manage it in the Roles screen.
 *
 * @return bool
 */
function mrn_base_stack_current_user_can_manage_effects_controls() {
	$capability = mrn_base_stack_get_effects_controls_capability();

	return current_user_can( $capability );
}

/**
 * Ensure the Effects capability exists on the Administrator role and remove prior slugs.
 *
 * This seeds the capability into WordPress role data so Advanced Menu Editor
 * can expose it as a real permission on the Roles screen.
 *
 * @return void
 */
function mrn_base_stack_register_effects_controls_capability() {
	$administrator = get_role( 'administrator' );

	if ( ! $administrator instanceof WP_Role ) {
		return;
	}

	$capability = mrn_base_stack_get_effects_controls_capability();

	if ( ! $administrator->has_cap( $capability ) ) {
		$administrator->add_cap( $capability );
	}

	$roles = wp_roles();

	if ( ! $roles instanceof WP_Roles || ! is_array( $roles->role_objects ) ) {
		return;
	}

	foreach ( $roles->role_objects as $role ) {
		if ( ! $role instanceof WP_Role ) {
			continue;
		}

		if ( $role->has_cap( 'mrn_manage_effects_controls' ) ) {
			$role->remove_cap( 'mrn_manage_effects_controls' );
		}

		if ( $role->has_cap( 'mrn_layout_blocks_effects_controls' ) ) {
			$role->remove_cap( 'mrn_layout_blocks_effects_controls' );
		}
	}
}
add_action( 'init', 'mrn_base_stack_register_effects_controls_capability' );

/**
 * Hide shared Effects controls from users who do not have the required capability.
 *
 * @param array<string, mixed>|mixed $field ACF field definition.
 * @return array<string, mixed>|false|mixed
 */
function mrn_base_stack_prepare_effects_fields_for_permissions( $field ) {
	if ( ! is_array( $field ) || mrn_base_stack_current_user_can_manage_effects_controls() ) {
		return $field;
	}

	$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
	$field_name  = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
	$field_key   = isset( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';
	$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';

	$is_effects_tab  = 'tab' === $field_type && 'effects' === $field_label && false !== strpos( $field_key, 'effects_tab' );
	$is_motion_group = 'group' === $field_type && 'motion_settings' === $field_name;

	if ( $is_effects_tab || $is_motion_group ) {
		return false;
	}

	return $field;
}
add_filter( 'acf/prepare_field', 'mrn_base_stack_prepare_effects_fields_for_permissions', 15 );

/**
 * Preserve existing motion settings when an unauthorized user submits Effects data.
 *
 * This enforces the capability at save time in addition to hiding the fields in
 * the editor UI.
 *
 * @param mixed                $value Submitted ACF value.
 * @param int|string           $post_id ACF object identifier.
 * @param array<string, mixed> $field ACF field definition.
 * @return mixed
 */
function mrn_base_stack_enforce_effects_controls_capability_on_save( $value, $post_id, array $field ) {
	if ( mrn_base_stack_current_user_can_manage_effects_controls() ) {
		return $value;
	}

	if ( ! function_exists( 'get_field' ) ) {
		return false;
	}

	$field_name   = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : 'motion_settings';
	$stored_value = get_field( $field_name, $post_id, false );

	return false === $stored_value ? false : $stored_value;
}
add_filter( 'acf/update_value/name=motion_settings', 'mrn_base_stack_enforce_effects_controls_capability_on_save', 20, 3 );

/**
 * Shared list-style choices for query-driven builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_content_list_style_choices() {
	return array(
		'unordered' => 'Unordered List',
		'ordered'   => 'Ordered List',
	);
}

/**
 * Shared display-mode choices for query-driven builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_content_list_display_mode_choices() {
	$choices = array(
		'' => 'Use Row Settings',
	);

	foreach ( mrn_base_stack_get_content_list_display_mode_choice_map() as $post_type => $post_type_choices ) {
		if ( ! is_array( $post_type_choices ) ) {
			continue;
		}

		foreach ( $post_type_choices as $mode => $label ) {
			$label = trim( (string) $label );
			if ( '' === $label || isset( $choices[ $mode ] ) ) {
				continue;
			}

			$choices[ $mode ] = $label;
		}
	}

	return $choices;
}

/**
 * Load live display-mode choices into the Content Lists builder field.
 *
 * The field group registers a baseline set of choices, but the actual options
 * need to reflect client-managed Display Modes from Config Helper each time the
 * builder form loads.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_load_content_list_display_mode_field_choices( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field['choices']    = mrn_base_stack_get_content_list_display_mode_choices();
	$field['allow_null'] = 1;
	$field['ui']         = 0;

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_content_lists_display_mode', 'mrn_base_stack_load_content_list_display_mode_field_choices' );
add_filter( 'acf/load_field/name=display_mode', 'mrn_base_stack_load_content_list_display_mode_field_choices' );
add_filter( 'acf/prepare_field/key=field_mrn_content_lists_display_mode', 'mrn_base_stack_load_content_list_display_mode_field_choices' );
add_filter( 'acf/prepare_field/name=display_mode', 'mrn_base_stack_load_content_list_display_mode_field_choices' );

/**
 * Robustly normalize dynamic choices for Content Lists select subfields.
 *
 * Some builder contexts can bypass the narrower ACF key/name hooks depending on
 * how the flexible-content row is prepared. This catches the rendered field
 * instance itself and reapplies the dynamic choice sources when the field is a
 * Content Lists subfield.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_prepare_dynamic_content_list_select_fields( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field_type    = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
	$field_name    = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
	$field_origin  = isset( $field['_name'] ) ? sanitize_key( (string) $field['_name'] ) : $field_name;
	$parent_layout = isset( $field['parent_layout'] ) ? sanitize_key( (string) $field['parent_layout'] ) : '';

	if ( 'select' !== $field_type ) {
		return $field;
	}

	$is_content_list_layout = false !== strpos( $parent_layout, 'content_lists' );

	if ( $is_content_list_layout && in_array( $field_origin, array( 'list_post_type', 'display_mode' ), true ) ) {
		if ( 'list_post_type' === $field_origin ) {
			$field['choices'] = mrn_base_stack_get_content_list_post_type_choices();
		}

		if ( 'display_mode' === $field_origin ) {
			$field['choices']    = mrn_base_stack_get_content_list_display_mode_choices();
			$field['allow_null'] = 1;
			$field['ui']         = 0;
		}
	}

	return $field;
}
add_filter( 'acf/load_field', 'mrn_base_stack_prepare_dynamic_content_list_select_fields', 20 );
add_filter( 'acf/prepare_field', 'mrn_base_stack_prepare_dynamic_content_list_select_fields', 20 );

/**
 * Get display-mode choices for a specific content-list post type.
 *
 * @param string $post_type Post type slug.
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_content_list_display_modes_for_post_type( $post_type = 'post' ) {
	$post_type = sanitize_key( (string) $post_type );
	$modes     = mrn_base_stack_get_content_list_display_modes();
	$filtered  = array();

	foreach ( $modes as $mode_key => $mode_config ) {
		if ( ! is_array( $mode_config ) ) {
			continue;
		}

		$entity_type    = isset( $mode_config['entity_type'] ) ? sanitize_key( (string) $mode_config['entity_type'] ) : 'post_type';
		$entity_subtype = isset( $mode_config['entity_subtype'] ) ? sanitize_key( (string) $mode_config['entity_subtype'] ) : 'post';

		if ( 'post_type' !== $entity_type || $entity_subtype !== $post_type ) {
			continue;
		}

		$filtered[ $mode_key ] = $mode_config;
	}

	if ( empty( $filtered ) && 'post' !== $post_type ) {
		return mrn_base_stack_get_content_list_display_modes_for_post_type( 'post' );
	}

	return $filtered;
}

/**
 * Get display-mode labels grouped by post type for builder-admin filtering.
 *
 * @return array<string, array<string, string>>
 */
function mrn_base_stack_get_content_list_display_mode_choice_map() {
	$map = array();

	foreach ( mrn_base_stack_get_content_list_post_type_choices() as $post_type => $label ) {
		$choices = array();

		foreach ( mrn_base_stack_get_content_list_display_modes_for_post_type( $post_type ) as $mode_key => $mode_config ) {
			if ( ! is_array( $mode_config ) ) {
				continue;
			}

			$mode_label = isset( $mode_config['label'] ) ? trim( (string) $mode_config['label'] ) : '';
			if ( '' === $mode_label ) {
				continue;
			}

			$choices[ $mode_key ] = $mode_label;
		}

		$map[ $post_type ] = $choices;
	}

	return $map;
}

/**
 * Shared display-mode registry for query-driven builder layouts.
 *
 * This intentionally starts small, but the contract is filterable so future
 * list-capable layouts can reuse the same mode vocabulary without rewriting the
 * builder field schema.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_content_list_display_modes() {
	$modes = array(
		'standard'   => array(
			'entity_type'      => 'post_type',
			'entity_subtype'   => 'post',
			'label'            => 'Standard',
			'fields'           => array( 'title', 'featured_image', 'publish_date', 'excerpt', 'read_more' ),
			'allows_image'     => true,
			'allows_date'      => true,
			'allows_excerpt'   => true,
			'allows_read_more' => true,
		),
		'title_only' => array(
			'entity_type'      => 'post_type',
			'entity_subtype'   => 'post',
			'label'            => 'Title Only',
			'fields'           => array( 'title' ),
			'allows_image'     => false,
			'allows_date'      => false,
			'allows_excerpt'   => false,
			'allows_read_more' => false,
		),
	);

	if ( function_exists( 'mrn_config_helper_get_display_modes' ) ) {
		$saved_modes = mrn_config_helper_get_display_modes();

		if ( is_array( $saved_modes ) ) {
			foreach ( $saved_modes as $saved_mode ) {
				if ( ! is_array( $saved_mode ) ) {
					continue;
				}

				$mode_key       = isset( $saved_mode['mode_key'] ) ? sanitize_key( (string) $saved_mode['mode_key'] ) : '';
				$label          = isset( $saved_mode['label'] ) ? trim( (string) $saved_mode['label'] ) : '';
				$entity_type    = isset( $saved_mode['entity_type'] ) ? sanitize_key( (string) $saved_mode['entity_type'] ) : 'post_type';
				$entity_subtype = isset( $saved_mode['entity_subtype'] ) ? sanitize_key( (string) $saved_mode['entity_subtype'] ) : 'post';
				$fields         = isset( $saved_mode['fields'] ) && is_array( $saved_mode['fields'] ) ? array_values( array_unique( array_map( 'sanitize_key', $saved_mode['fields'] ) ) ) : array();

				if ( '' === $mode_key || '' === $label ) {
					continue;
				}

				$modes[ $mode_key ] = array(
					'entity_type'      => $entity_type,
					'entity_subtype'   => $entity_subtype,
					'label'            => $label,
					'fields'           => $fields,
					'allows_image'     => in_array( 'featured_image', $fields, true ) || in_array( 'image', $fields, true ),
					'allows_date'      => in_array( 'publish_date', $fields, true ),
					'allows_excerpt'   => in_array( 'excerpt', $fields, true ) || in_array( 'body', $fields, true ),
					'allows_read_more' => in_array( 'read_more', $fields, true ) || in_array( 'link', $fields, true ),
				);
			}
		}
	}

	return apply_filters( 'mrn_base_stack_content_list_display_modes', $modes );
}

/**
 * Normalize a content-list display mode to a supported key.
 *
 * @param string $mode Candidate display-mode key.
 * @return string
 */
function mrn_base_stack_normalize_content_list_display_mode( $mode ) {
	$mode  = sanitize_key( (string) $mode );
	$modes = mrn_base_stack_get_content_list_display_modes();

	if ( '' === $mode ) {
		return '';
	}

	if ( isset( $modes[ $mode ] ) ) {
		return $mode;
	}

	return '';
}

/**
 * Get the configuration for one content-list display mode.
 *
 * @param string $mode Display-mode key.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_content_list_display_mode_config( $mode ) {
	$modes = mrn_base_stack_get_content_list_display_modes();
	$mode  = mrn_base_stack_normalize_content_list_display_mode( $mode );

	return isset( $modes[ $mode ] ) && is_array( $modes[ $mode ] ) ? $modes[ $mode ] : array();
}

/**
 * Build the legacy row-settings display contract for Content Lists.
 *
 * @param array<string, mixed> $args Render arguments.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_content_list_legacy_mode_config( array $args = array() ) {
	$fields = array( 'title' );

	if ( ! empty( $args['show_featured_image'] ) ) {
		$fields[] = 'featured_image';
	}

	if ( ! empty( $args['show_publish_date'] ) ) {
		$fields[] = 'publish_date';
	}

	if ( ! empty( $args['show_excerpt'] ) ) {
		$fields[] = 'excerpt';
	}

	if ( ! empty( $args['show_read_more'] ) ) {
		$fields[] = 'read_more';
	}

	return array(
		'label'            => 'Row Settings',
		'fields'           => $fields,
		'allows_image'     => ! empty( $args['show_featured_image'] ),
		'allows_date'      => ! empty( $args['show_publish_date'] ),
		'allows_excerpt'   => ! empty( $args['show_excerpt'] ),
		'allows_read_more' => ! empty( $args['show_read_more'] ),
	);
}

/**
 * Render one query result item for the Content Lists layout.
 *
 * @param WP_Post              $item_post Post to render.
 * @param array<string, mixed> $args Render arguments.
 * @return string
 */
function mrn_base_stack_render_content_list_item( WP_Post $item_post, array $args = array() ) {
	$display_mode      = mrn_base_stack_normalize_content_list_display_mode( $args['display_mode'] ?? '' );
	$mode_config       = '' !== $display_mode ? mrn_base_stack_get_content_list_display_mode_config( $display_mode ) : mrn_base_stack_get_content_list_legacy_mode_config( $args );
	$permalink         = get_permalink( $item_post );
	$item_title        = get_the_title( $item_post );
	$uses_row_settings = '' === $display_mode;
	$show_date         = ( ! $uses_row_settings || ! empty( $args['show_publish_date'] ) ) && ! empty( $mode_config['allows_date'] );
	$show_excerpt      = ( ! $uses_row_settings || ! empty( $args['show_excerpt'] ) ) && ! empty( $mode_config['allows_excerpt'] );
	$show_read_more    = ( ! $uses_row_settings || ! empty( $args['show_read_more'] ) ) && ! empty( $mode_config['allows_read_more'] ) && '' !== $permalink;
	$show_image        = ( ! $uses_row_settings || ! empty( $args['show_featured_image'] ) ) && ! empty( $mode_config['allows_image'] ) && has_post_thumbnail( $item_post );
	$excerpt_length    = max( 5, absint( $args['excerpt_length'] ?? 24 ) );
	$read_more_label   = isset( $args['read_more_label'] ) ? trim( (string) $args['read_more_label'] ) : 'Read More';
	$item_excerpt      = $show_excerpt && function_exists( 'mrn_base_stack_get_content_list_excerpt' ) ? mrn_base_stack_get_content_list_excerpt( $item_post, $excerpt_length ) : '';
	$fields            = isset( $mode_config['fields'] ) && is_array( $mode_config['fields'] ) ? array_values( array_unique( array_map( 'sanitize_key', $mode_config['fields'] ) ) ) : array();
	$variant           = array( 'title' ) === $fields ? 'title_only' : 'card';
	$image_first       = ! empty( $fields ) && 'featured_image' === $fields[0];
	$item_classes      = array(
		'mrn-content-list-row__item',
		'mrn-ui__item',
		'mrn-content-list-row__item--display-' . ( '' !== $display_mode ? $display_mode : 'row-settings' ),
		'mrn-content-list-row__item--variant-' . $variant,
	);

	if ( $show_image ) {
		$item_classes[] = 'mrn-content-list-row__item--has-image';
		if ( $image_first ) {
			$item_classes[] = 'mrn-content-list-row__item--image-leading';
		}
	}

	ob_start();
	?>
	<li class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>">
		<?php if ( 'title_only' === $variant ) : ?>
			<div class="mrn-content-list-row__body mrn-ui__body">
				<div class="mrn-content-list-row__head mrn-ui__head">
					<span class="mrn-content-list-row__title mrn-content-list-row__title--only mrn-ui__heading">
						<?php if ( '' !== $permalink ) : ?>
							<a class="mrn-ui__link" href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $item_title ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $item_title ); ?>
						<?php endif; ?>
					</span>
				</div>
			</div>
		<?php else : ?>
				<article class="mrn-content-list-row__card">
					<div class="mrn-content-list-row__body mrn-ui__body">
						<?php $head_open = false; ?>
						<?php foreach ( $fields as $field_key ) : ?>
							<?php
							$is_head_field = (
								( 'publish_date' === $field_key && $show_date ) ||
								( 'title' === $field_key && '' !== $item_title )
							);
							?>
							<?php if ( $is_head_field && ! $head_open ) : ?>
								<div class="mrn-content-list-row__head mrn-ui__head">
								<?php $head_open = true; ?>
							<?php elseif ( ! $is_head_field && $head_open ) : ?>
								</div>
								<?php $head_open = false; ?>
							<?php endif; ?>
							<?php if ( 'featured_image' === $field_key && $show_image && '' !== $permalink ) : ?>
								<a class="mrn-content-list-row__media mrn-ui__media mrn-ui__link" href="<?php echo esc_url( $permalink ); ?>">
								<?php echo get_the_post_thumbnail( $item_post, 'medium_large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</a>
						<?php elseif ( 'featured_image' === $field_key && $show_image ) : ?>
								<div class="mrn-content-list-row__media mrn-ui__media">
								<?php echo get_the_post_thumbnail( $item_post, 'medium_large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php elseif ( 'publish_date' === $field_key && $show_date ) : ?>
							<p class="mrn-content-list-row__meta"><?php echo esc_html( get_the_date( '', $item_post ) ); ?></p>
						<?php elseif ( 'title' === $field_key && '' !== $item_title ) : ?>
								<h3 class="mrn-content-list-row__title mrn-ui__heading">
									<?php if ( '' !== $permalink ) : ?>
										<a class="mrn-ui__link" href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $item_title ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $item_title ); ?>
								<?php endif; ?>
							</h3>
						<?php elseif ( 'excerpt' === $field_key && '' !== $item_excerpt ) : ?>
								<p class="mrn-content-list-row__excerpt mrn-ui__text"><?php echo esc_html( $item_excerpt ); ?></p>
							<?php elseif ( 'read_more' === $field_key && $show_read_more ) : ?>
									<p class="mrn-content-list-row__link">
										<a class="mrn-ui__link" href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( '' !== $read_more_label ? $read_more_label : 'Read More' ); ?></a>
							</p>
						<?php endif; ?>
					<?php endforeach; ?>
						<?php if ( $head_open ) : ?>
							</div>
						<?php endif; ?>
				</div>
			</article>
		<?php endif; ?>
	</li>
	<?php

	return (string) ob_get_clean();
}

/**
 * Shared order-by choices for query-driven builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_content_list_orderby_choices() {
	return array(
		'date'          => 'Publish Date',
		'modified'      => 'Modified Date',
		'title'         => 'Title',
		'menu_order'    => 'Menu Order',
		'comment_count' => 'Comment Count',
		'rand'          => 'Random',
	);
}

/**
 * Shared taxonomy choices for query-driven builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_content_list_taxonomy_choices() {
	$taxonomies = get_taxonomies(
		array(
			'public'  => true,
			'show_ui' => true,
		),
		'objects'
	);
	$choices    = array();
	$excluded   = array(
		'nav_menu',
		'link_category',
		'post_format',
	);

	foreach ( $taxonomies as $taxonomy => $taxonomy_object ) {
		if ( ! $taxonomy_object instanceof WP_Taxonomy ) {
			continue;
		}

		if ( in_array( $taxonomy, $excluded, true ) ) {
			continue;
		}

		$label = isset( $taxonomy_object->labels->name ) ? trim( (string) $taxonomy_object->labels->name ) : '';
		if ( '' === $label ) {
			$label = ucfirst( str_replace( array( '-', '_' ), ' ', $taxonomy ) );
		}

		$choices[ $taxonomy ] = $label;
	}

	if ( empty( $choices['category'] ) && taxonomy_exists( 'category' ) ) {
		$choices = array_merge( array( 'category' => 'Categories' ), $choices );
	}

	return $choices;
}

/**
 * Shared filter source choices for query-driven builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_content_list_filter_source_choices() {
	return array(
		'none'               => 'No Filter',
		'current_post_terms' => 'Use Current Page/Post Terms',
		'manual_terms'       => 'Use Specific Terms',
	);
}

/**
 * Shared term matching choices for query-driven builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_content_list_filter_match_choices() {
	return array(
		'any' => 'Match Any Selected Term',
		'all' => 'Match All Selected Terms',
	);
}

/**
 * Build a standard section-width ACF field definition.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $default_width Default width choice.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_section_width_field( $key, $name = 'section_width', $default_width = 'wide', $label = 'Section Width' ) {
	return array(
		'key'               => $key,
		'label'             => $label,
		'name'              => $name,
		'aria-label'        => '',
		'type'              => 'select',
		'choices'           => mrn_base_stack_get_section_width_choices(),
		'default_value'     => $default_width,
		'ui'                => 1,
		'wrapper'           => array(
			'width' => '50',
		),
	);
}

/**
 * Build the standard anchor ACF field definition for builder rows.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_anchor_field( $key, $name = 'anchor', $label = 'Anchor ID' ) {
	return array(
		'key'          => $key,
		'label'        => $label,
		'name'         => $name,
		'aria-label'   => '',
		'type'         => 'text',
		'instructions' => 'Optional anchor slug for one-page links. Enter the value without #.',
		'wrapper'      => array(
			'width' => '50',
		),
	);
}

/**
 * Shared motion effect choices for builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_motion_effect_choices() {
	return array(
		'surface'          => 'Switch Light/Dark Surface',
		'active-class'     => 'Mark Row As Active',
		'dark-scroll-card' => 'Darken Card On Scroll',
	);
}

/**
 * Get beginner-friendly motion preset choices for a supported effect.
 *
 * @param string $effect Effect key.
 * @return array<string, string>
 */
function mrn_base_stack_get_motion_preset_choices( $effect ) {
	$effect = sanitize_key( (string) $effect );

	if ( 'dark-scroll-card' === $effect ) {
		if ( function_exists( 'mrn_site_styles_get_dark_scroll_card_preset_choices' ) ) {
			return mrn_site_styles_get_dark_scroll_card_preset_choices();
		}

		return array(
			'' => 'Default Dark Card',
		);
	}

	return array(
		'' => 'Default',
	);
}

/**
 * Shared beginner-friendly trigger choices for motion effects.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_motion_trigger_choices() {
	return array(
		'early'  => 'Early',
		'center' => 'Center',
		'late'   => 'Late',
	);
}

/**
 * Shared target choices for non-surface motion effects.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_motion_target_choices() {
	return array(
		'row'          => 'Entire Layout',
		'surface'      => 'Inner Surface',
		'content'      => 'Text / Content Area',
		'media'        => 'Image / Media',
		'header'       => 'Heading Area',
		'items'        => 'Items / Grid',
		'left-column'  => 'Left Sub-Layout',
		'right-column' => 'Right Sub-Layout',
	);
}

/**
 * Normalize a stored motion target to a supported value.
 *
 * @param mixed $value Raw stored target value.
 * @return string
 */
function mrn_base_stack_normalize_motion_target( $value ) {
	$target  = sanitize_key( (string) $value );
	$choices = mrn_base_stack_get_motion_target_choices();

	if ( ! isset( $choices[ $target ] ) ) {
		return 'row';
	}

	return $target;
}

/**
 * Convert a stored trigger position into a Motion margin string.
 *
 * @param mixed $value Raw stored trigger value.
 * @return string
 */
function mrn_base_stack_get_motion_margin_for_trigger( $value ) {
	$trigger = is_string( $value ) ? sanitize_key( $value ) : '';

	if ( 'early' === $trigger ) {
		return '-20% 0px -20% 0px';
	}

	if ( 'late' === $trigger ) {
		return '-45% 0px -10% 0px';
	}

	return '-35% 0px -35% 0px';
}

/**
 * Build the standard motion-settings ACF group field definition.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_motion_group_field( $key, $name = 'motion_settings', $label = 'Motion Effects' ) {
	$enabled_key   = $key . '_enabled';
	$effect_key    = $key . '_effect';
	$preset_key    = $key . '_preset';
	$surface_key   = $key . '_surface';
	$target_key    = $key . '_target';
	$enabled_logic = array(
		array(
			array(
				'field'    => $enabled_key,
				'operator' => '==',
				'value'    => '1',
			),
		),
	);

	return array(
		'key'        => $key,
		'label'      => $label,
		'name'       => $name,
		'aria-label' => '',
		'type'       => 'group',
		'layout'     => 'block',
		'sub_fields' => array(
			array(
				'key'           => $enabled_key,
				'label'         => 'Enable Row Effects',
				'name'          => 'enabled',
				'aria-label'    => '',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 0,
				'ui_on_text'    => 'On',
				'ui_off_text'   => 'Off',
				'wrapper'       => array(
					'width' => '33',
				),
			),
			array(
				'key'               => $effect_key,
				'label'             => 'Effect Style',
				'name'              => 'effect',
				'aria-label'        => '',
				'type'              => 'select',
				'choices'           => mrn_base_stack_get_motion_effect_choices(),
				'default_value'     => 'surface',
				'ui'                => 1,
				'wrapper'           => array(
					'width' => '34',
				),
				'conditional_logic' => $enabled_logic,
			),
			array(
				'key'               => $key . '_trigger_position',
				'label'             => 'Start Effect',
				'name'              => 'trigger_position',
				'aria-label'        => '',
				'type'              => 'select',
				'choices'           => mrn_base_stack_get_motion_trigger_choices(),
				'default_value'     => 'center',
				'ui'                => 1,
				'instructions'      => 'Choose where in the viewport the effect should become noticeable.',
				'wrapper'           => array(
					'width' => '33',
				),
				'conditional_logic' => $enabled_logic,
			),
			array(
				'key'               => $target_key,
				'label'             => 'Apply To',
				'name'              => 'target',
				'aria-label'        => '',
				'type'              => 'select',
				'choices'           => mrn_base_stack_get_motion_target_choices(),
				'default_value'     => 'row',
				'ui'                => 1,
				'instructions'      => 'Choose which part of the layout should receive the effect.',
				'wrapper'           => array(
					'width' => '33',
				),
				'conditional_logic' => array(
					array(
						array(
							'field'    => $enabled_key,
							'operator' => '==',
							'value'    => '1',
						),
						array(
							'field'    => $effect_key,
							'operator' => '!=',
							'value'    => 'surface',
						),
					),
				),
			),
			array(
				'key'               => $surface_key,
				'label'             => 'Surface Look',
				'name'              => 'surface',
				'aria-label'        => '',
				'type'              => 'select',
				'choices'           => array(
					'light' => 'Light',
					'dark'  => 'Dark',
				),
				'default_value'     => 'dark',
				'ui'                => 1,
				'wrapper'           => array(
					'width' => '50',
				),
				'conditional_logic' => array(
					array(
						array(
							'field'    => $enabled_key,
							'operator' => '==',
							'value'    => '1',
						),
						array(
							'field'    => $effect_key,
							'operator' => '==',
							'value'    => 'surface',
						),
					),
				),
			),
			array(
				'key'               => $preset_key,
				'label'             => 'Effect Preset',
				'name'              => 'preset',
				'aria-label'        => '',
				'type'              => 'select',
				'choices'           => mrn_base_stack_get_motion_preset_choices( 'dark-scroll-card' ),
				'default_value'     => '',
				'ui'                => 1,
				'instructions'      => 'Choose a saved visual preset from Site Styles.',
				'wrapper'           => array(
					'width' => '50',
				),
				'conditional_logic' => array(
					array(
						array(
							'field'    => $enabled_key,
							'operator' => '==',
							'value'    => '1',
						),
						array(
							'field'    => $effect_key,
							'operator' => '==',
							'value'    => 'dark-scroll-card',
						),
					),
				),
			),
		),
	);
}

/**
 * Build the standard Effects tab field definition for builder layouts.
 *
 * @param string $key Unique ACF field key.
 * @param string $label Tab label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_effects_tab_field( $key, $label = 'Effects' ) {
	return array(
		'key'        => $key,
		'label'      => $label,
		'name'       => '',
		'aria-label' => '',
		'type'       => 'tab',
		'placement'  => 'top',
		'endpoint'   => 0,
	);
}

/**
 * Recursively move row effect controls into a dedicated Effects tab.
 *
 * This preserves existing motion field keys/names and only changes their tab
 * placement in row editors that already use top-level tabs.
 *
 * @param array<int, mixed> $fields Field definitions.
 * @return array<int, mixed>
 */
function mrn_base_stack_relocate_effect_fields( array $fields ) {
	$processed_fields = array();

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			$processed_fields[] = $field;
			continue;
		}

		if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			$field['sub_fields'] = mrn_base_stack_relocate_effect_fields( $field['sub_fields'] );
		}

		if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
			$field['fields'] = mrn_base_stack_relocate_effect_fields( $field['fields'] );
		}

		if ( isset( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			foreach ( $field['layouts'] as $layout_key => $layout ) {
				if ( ! is_array( $layout ) ) {
					continue;
				}

				if ( isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
					$layout['sub_fields'] = mrn_base_stack_relocate_effect_fields( $layout['sub_fields'] );
				}

				$field['layouts'][ $layout_key ] = $layout;
			}
		}

		$processed_fields[] = $field;
	}

	$has_tabs      = false;
	$motion_fields = array();
	$remaining     = array();

	foreach ( $processed_fields as $field ) {
		if ( ! is_array( $field ) ) {
			$remaining[] = $field;
			continue;
		}

		$field_type  = isset( $field['type'] ) ? (string) $field['type'] : '';
		$field_name  = isset( $field['name'] ) ? (string) $field['name'] : '';
		$field_label = isset( $field['label'] ) ? (string) $field['label'] : '';

		if ( 'tab' === $field_type ) {
			$has_tabs = true;

			if ( 'effects' === sanitize_title( $field_label ) ) {
				continue;
			}
		}

		if ( 'motion_settings' === $field_name ) {
			$motion_fields[] = $field;
			continue;
		}

		$remaining[] = $field;
	}

	if ( ! $has_tabs || empty( $motion_fields ) ) {
		return $remaining;
	}

	$effects_tab_key = 'field_mrn_effects_tab';

	if ( isset( $motion_fields[0]['key'] ) && is_string( $motion_fields[0]['key'] ) && '' !== $motion_fields[0]['key'] ) {
		$effects_tab_key = $motion_fields[0]['key'] . '_effects_tab';
	}

	$remaining[] = mrn_base_stack_get_effects_tab_field( $effects_tab_key );

	return array_merge( $remaining, $motion_fields );
}

/**
 * Apply the builder Effects tab transform to an ACF field group.
 *
 * @param array<string, mixed> $field_group Field group config.
 * @return array<string, mixed>
 */
function mrn_base_stack_with_effects_tabs( array $field_group ) {
	if ( isset( $field_group['fields'] ) && is_array( $field_group['fields'] ) ) {
		$field_group['fields'] = mrn_base_stack_relocate_effect_fields( $field_group['fields'] );
	}

	return $field_group;
}

/**
 * Normalize a raw section-width setting to a supported value.
 *
 * @param mixed  $value Raw stored value.
 * @param string $default_width Default width.
 * @return string
 */
function mrn_base_stack_normalize_section_width( $value, $default_width = 'wide' ) {
	$width = is_string( $value ) ? sanitize_key( $value ) : '';

	if ( in_array( $value, array( 1, '1', true, 'true' ), true ) ) {
		$width = 'full-width';
	}

	if ( ! in_array( $width, array( 'content', 'wide', 'full-width' ), true ) ) {
		$width = $default_width;
	}

	return $width;
}

/**
 * Convert a section-width setting into a shell modifier class.
 *
 * @param mixed  $value Raw stored value.
 * @param string $default_width Default width.
 * @return string
 */
function mrn_base_stack_get_section_width_class( $value, $default_width = 'wide' ) {
	$width = mrn_base_stack_normalize_section_width( $value, $default_width );

	if ( 'content' === $width ) {
		return 'mrn-shell-section--width-content';
	}

	if ( 'full-width' === $width ) {
		return 'mrn-shell-section--width-full';
	}

	return 'mrn-shell-section--width-wide';
}

/**
 * Resolve section-width UI choice into section and container layer classes.
 *
 * `content` and `wide` are container-width choices inside a contained section.
 * `full-width` is a full-bleed section with a layout-owned inner container.
 *
 * @param mixed  $value Raw stored value.
 * @param string $default_width Default width choice.
 * @param string $full_container_width Inner container width to use when the section is full bleed.
 * @return array{width:string,section_class:string,container_class:string}
 */
function mrn_base_stack_get_section_width_layers( $value, $default_width = 'wide', $full_container_width = 'wide' ) {
	$width                = mrn_base_stack_normalize_section_width( $value, $default_width );
	$full_container_width = mrn_base_stack_normalize_section_width( $full_container_width, 'wide' );

	$section_class = 'mrn-layout-section--contained';
	$container_map = array(
		'content'    => 'mrn-layout-container--content',
		'wide'       => 'mrn-layout-container--wide',
		'full-width' => 'mrn-layout-container--full',
	);
	$container_key = $width;

	if ( 'full-width' === $width ) {
		$section_class = 'mrn-layout-section--full';
		$container_key = $full_container_width;
	}

	return array(
		'width'           => $width,
		'section_class'   => $section_class,
		'container_class' => $container_map[ $container_key ] ?? $container_map['wide'],
	);
}

/**
 * Resolve a builder row width setting into the shell modifier class.
 *
 * Supports legacy boolean full-width fields when requested.
 *
 * @param array<string, mixed> $row Builder row data.
 * @param string               $default_width Default width choice.
 * @param string               $legacy_full_width_key Optional legacy field name.
 * @return string
 */
function mrn_base_stack_get_row_section_width_class( array $row, $default_width = 'wide', $legacy_full_width_key = '' ) {
	$value = $row['section_width'] ?? '';

	if ( '' === $value && '' !== $legacy_full_width_key && ! empty( $row[ $legacy_full_width_key ] ) ) {
		$value = 'full-width';
	}

	return mrn_base_stack_get_section_width_class( $value, $default_width );
}

/**
 * Normalize a builder anchor ID for safe front-end output.
 *
 * @param mixed $value Raw stored anchor value.
 * @return string
 */
function mrn_base_stack_normalize_anchor_id( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}

	$value = trim( $value );
	if ( '' === $value ) {
		return '';
	}

	$value = ltrim( $value, "# \t\n\r\0\x0B" );

	return sanitize_title( $value );
}

/**
 * Build the top-of-row anchor markup for a builder row.
 *
 * @param array<string, mixed> $row Builder row data.
 * @param string               $fallback_anchor Optional fallback anchor when the row does not store one.
 * @return string
 */
function mrn_base_stack_get_builder_anchor_markup( array $row, $fallback_anchor = '' ) {
	$anchor_id = mrn_base_stack_normalize_anchor_id( $row['anchor'] ?? '' );

	if ( '' === $anchor_id && '' !== $fallback_anchor ) {
		$anchor_id = mrn_base_stack_normalize_anchor_id( $fallback_anchor );
	}

	if ( '' === $anchor_id ) {
		return '';
	}

	return sprintf(
		'<div id="%1$s" class="mrn-content-builder__anchor" aria-hidden="true"></div>',
		esc_attr( $anchor_id )
	);
}

/**
 * Get the standard accent contract for a builder section.
 *
 * @param bool   $enabled Whether the bottom accent is enabled.
 * @param string $accent_slug Optional accent style slug.
 * @return array{classes:array<int,string>,attributes:array<string,string>}
 */
function mrn_base_stack_get_builder_accent_contract( $enabled, $accent_slug = '' ) {
	if ( function_exists( 'mrn_site_styles_get_bottom_accent_contract' ) ) {
		$contract = mrn_site_styles_get_bottom_accent_contract( (bool) $enabled, (string) $accent_slug );
		$classes  = isset( $contract['classes'] ) && is_array( $contract['classes'] ) ? array_values( $contract['classes'] ) : array();
		$attrs    = isset( $contract['attributes'] ) && is_array( $contract['attributes'] ) ? $contract['attributes'] : array();

		return array(
			'classes'    => $classes,
			'attributes' => $attrs,
		);
	}

	return array(
		'classes'    => $enabled ? array( 'has-bottom-accent' ) : array(),
		'attributes' => array(),
	);
}

/**
 * Append accent classes to a builder section class list.
 *
 * @param array<int, string>                $classes Existing section classes.
 * @param array{classes?:array<int,string>} $accent_contract Accent contract array.
 * @return array<int, string>
 */
function mrn_base_stack_merge_builder_section_classes( array $classes, array $accent_contract ) {
	if ( ! empty( $accent_contract['classes'] ) && is_array( $accent_contract['classes'] ) ) {
		$classes = array_merge( $classes, $accent_contract['classes'] );
	}

	return array_values( array_unique( array_filter( $classes, 'strlen' ) ) );
}

/**
 * Merge a builder attribute contract into an existing attribute map.
 *
 * @param array<string, string> $attributes Existing attributes.
 * @param array<string, string> $extra_attributes Additional attributes.
 * @return array<string, string>
 */
function mrn_base_stack_merge_builder_attributes( array $attributes, array $extra_attributes ) {
	foreach ( $extra_attributes as $attribute_name => $attribute_value ) {
		$attribute_name  = is_string( $attribute_name ) ? trim( $attribute_name ) : '';
		$attribute_value = is_scalar( $attribute_value ) ? trim( (string) $attribute_value ) : '';

		if ( '' === $attribute_name || '' === $attribute_value ) {
			continue;
		}

		$attributes[ $attribute_name ] = $attribute_value;
	}

	return $attributes;
}

/**
 * Normalize a builder motion-settings payload.
 *
 * @param mixed $value Raw motion settings.
 * @return array<string, string|bool>
 */
function mrn_base_stack_normalize_motion_settings( $value ) {
	$settings = is_array( $value ) ? $value : array();

	return array(
		'enabled'          => ! empty( $settings['enabled'] ),
		'effect'           => sanitize_key( (string) ( $settings['effect'] ?? '' ) ),
		'preset'           => sanitize_key( (string) ( $settings['preset'] ?? '' ) ),
		'trigger_position' => sanitize_key( (string) ( $settings['trigger_position'] ?? '' ) ),
		'target'           => mrn_base_stack_normalize_motion_target( $settings['target'] ?? 'row' ),
		'surface'          => sanitize_key( (string) ( $settings['surface'] ?? '' ) ),
		'active_class'     => sanitize_html_class( (string) ( $settings['active_class'] ?? '' ) ),
		'margin'           => is_string( $settings['margin'] ?? null ) ? trim( $settings['margin'] ) : '',
	);
}

/**
 * Build the motion contract for a normalized motion-settings payload.
 *
 * @param mixed $settings Raw motion settings.
 * @return array{classes:array<int,string>,attributes:array<string,string>}
 */
function mrn_base_stack_get_motion_contract_for_settings( $settings ) {
	$settings = mrn_base_stack_normalize_motion_settings( $settings );

	if ( empty( $settings['enabled'] ) ) {
		return array(
			'classes'    => array(),
			'attributes' => array(),
		);
	}

	$effect = $settings['effect'];
	$margin = '' !== $settings['margin'] ? $settings['margin'] : mrn_base_stack_get_motion_margin_for_trigger( $settings['trigger_position'] ?? '' );
	$target = mrn_base_stack_normalize_motion_target( $settings['target'] ?? 'row' );

	if ( 'surface' === $effect ) {
		$surface = $settings['surface'];

		if ( ! in_array( $surface, array( 'light', 'dark' ), true ) ) {
			$surface = 'dark';
		}

		return array(
			'classes'    => array(),
			'attributes' => array(
				'data-mrn-surface'        => $surface,
				'data-mrn-surface-margin' => $margin,
			),
		);
	}

	if ( 'active-class' === $effect ) {
		$active_class = 'is-mrn-in-view';

		return array(
			'classes'    => array( 'mrn-motion-effect--active-class' ),
			'attributes' => array(
				'data-mrn-motion-effect' => 'active-class',
				'data-mrn-motion-class'  => $active_class,
				'data-mrn-motion-margin' => $margin,
				'data-mrn-motion-target' => $target,
			),
		);
	}

	if ( 'dark-scroll-card' === $effect ) {
		$preset = $settings['preset'];

		return array(
			'classes'    => array( 'mrn-motion-effect--dark-scroll-card' ),
			'attributes' => array(
				'data-mrn-motion-effect' => 'dark-scroll-card',
				'data-mrn-effect-preset' => $preset,
				'data-mrn-motion-margin' => $margin,
				'data-mrn-motion-target' => $target,
			),
		);
	}

	return array(
		'classes'    => array(),
		'attributes' => array(),
	);
}

/**
 * Build the motion contract for a builder row.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return array{classes:array<int,string>,attributes:array<string,string>}
 */
function mrn_base_stack_get_builder_motion_contract( array $row ) {
	return mrn_base_stack_get_motion_contract_for_settings( $row['motion_settings'] ?? array() );
}

/**
 * Convert an array of CSS declarations into a style attribute value.
 *
 * @param array<int, string> $styles CSS declarations.
 * @return string
 */
function mrn_base_stack_get_inline_style_attribute( array $styles ) {
	$styles = array_values(
		array_filter(
			array_map( 'trim', $styles ),
			'strlen'
		)
	);

	return implode( '; ', $styles );
}

/**
 * Convert an associative array into escaped HTML attributes.
 *
 * @param array<string, scalar> $attributes Associative attribute map.
 * @return string
 */
function mrn_base_stack_get_html_attributes( array $attributes ) {
	$parts = array();

	foreach ( $attributes as $attribute_name => $attribute_value ) {
		$attribute_name  = is_string( $attribute_name ) ? trim( $attribute_name ) : '';
		$attribute_value = is_scalar( $attribute_value ) ? trim( (string) $attribute_value ) : '';

		if ( '' === $attribute_name || '' === $attribute_value ) {
			continue;
		}

		$parts[] = sprintf( '%s="%s"', esc_attr( $attribute_name ), esc_attr( $attribute_value ) );
	}

	return implode( ' ', $parts );
}

/**
 * Shared HTML tag choices for heading-style text fields.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_text_tag_choices() {
	return array(
		'h1'   => 'H1',
		'h2'   => 'H2',
		'h3'   => 'H3',
		'h4'   => 'H4',
		'h5'   => 'H5',
		'h6'   => 'H6',
		'p'    => 'Paragraph',
		'span' => 'Span',
		'div'  => 'Div',
	);
}

/**
 * Normalize a requested HTML tag to the supported text-tag set.
 *
 * @param mixed  $value Raw tag value.
 * @param string $default_tag Default tag value.
 * @return string
 */
function mrn_base_stack_normalize_text_tag( $value, $default_tag = 'p' ) {
	$tag          = is_string( $value ) ? sanitize_key( $value ) : '';
	$default_tag  = is_string( $default_tag ) ? sanitize_key( $default_tag ) : 'p';
	$allowed_tags = array_keys( mrn_base_stack_get_text_tag_choices() );

	if ( ! in_array( $default_tag, $allowed_tags, true ) ) {
		$default_tag = 'p';
	}

	if ( ! in_array( $tag, $allowed_tags, true ) ) {
		$tag = $default_tag;
	}

	return $tag;
}

/**
 * Build a standard inline-HTML-enabled text field definition.
 *
 * @param string $key Unique ACF field key.
 * @param string $label Field label.
 * @param string $name Field name.
 * @param string $instructions Field instructions.
 * @param string $width Wrapper width percentage.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_inline_text_field( $key, $label, $name, $instructions = 'Limited inline HTML allowed: span, strong, em, br.', $width = '75' ) {
	return array(
		'key'           => $key,
		'label'         => $label,
		'name'          => $name,
		'aria-label'    => '',
		'type'          => 'text',
		'instructions'  => $instructions,
		'wrapper'       => array(
			'width' => $width,
		),
	);
}

/**
 * Build a standard label-tag ACF field definition.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $default_tag Default tag choice.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_label_tag_field( $key, $name = 'label_tag', $default_tag = 'p', $label = 'HTML Tag for Label' ) {
	return array(
		'key'               => $key,
		'label'             => $label,
		'name'              => $name,
		'aria-label'        => '',
		'type'              => 'select',
		'choices'           => mrn_base_stack_get_text_tag_choices(),
		'default_value'     => mrn_base_stack_normalize_text_tag( $default_tag, 'p' ),
		'ui'                => 1,
		'wrapper'           => array(
			'width' => '25',
		),
	);
}

/**
 * Build a standard heading/subheading tag ACF field definition.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $default_tag Default tag choice.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_text_tag_field( $key, $name = 'heading_tag', $default_tag = 'h2', $label = 'Heading Tag' ) {
	return array(
		'key'               => $key,
		'label'             => $label,
		'name'              => $name,
		'aria-label'        => '',
		'type'              => 'select',
		'choices'           => mrn_base_stack_get_text_tag_choices(),
		'default_value'     => mrn_base_stack_normalize_text_tag( $default_tag, 'h2' ),
		'ui'                => 1,
		'wrapper'           => array(
			'width' => '25',
		),
	);
}

/**
 * Build the shared builder button-link icon chooser fields.
 *
 * @param string $key_prefix Unique ACF key prefix for this chooser set.
 * @param string $link_style_field_key ACF key for the parent row's link-style field.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_button_link_icon_fields( $key_prefix, $link_style_field_key ) {
	$base_condition = array(
		array(
			'field'    => $link_style_field_key,
			'operator' => '==',
			'value'    => 'button',
		),
	);

	return array(
		array(
			'key'               => $key_prefix . '_source',
			'label'             => 'Button Icon',
			'name'              => 'link_icon_source',
			'aria-label'        => '',
			'type'              => 'button_group',
			'choices'           => array(
				'dashicons'   => 'Dashicons',
				'fontawesome' => 'Font Awesome',
				'media'       => 'Media',
			),
			'default_value'     => '',
			'layout'            => 'horizontal',
			'return_format'     => 'value',
			'instructions'      => 'Optional. Uses the shared icon chooser when links render as buttons.',
			'wrapper'           => array(
				'width' => '50',
				'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--source mrn-icon-chooser-field--allow-empty',
			),
			'conditional_logic' => array(
				$base_condition,
			),
		),
		array(
			'key'               => $key_prefix . '_dashicons',
			'label'             => 'Dashicon',
			'name'              => 'link_icon_dashicon',
			'aria-label'        => '',
			'type'              => 'select',
			'choices'           => function_exists( 'mrn_base_stack_get_header_search_standard_icon_choices' ) ? mrn_base_stack_get_header_search_standard_icon_choices() : array(),
			'default_value'     => '',
			'return_format'     => 'value',
			'ui'                => 1,
			'wrapper'           => array(
				'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--dashicons',
			),
			'conditional_logic' => array(
				array(
					array(
						'field'    => $link_style_field_key,
						'operator' => '==',
						'value'    => 'button',
					),
					array(
						'field'    => $key_prefix . '_source',
						'operator' => '==',
						'value'    => 'dashicons',
					),
				),
			),
		),
		array(
			'key'               => $key_prefix . '_fontawesome',
			'label'             => 'Font Awesome Icon',
			'name'              => 'link_icon_fa_class',
			'aria-label'        => '',
			'type'              => 'select',
			'choices'           => function_exists( 'mrn_base_stack_get_header_search_fontawesome_choices' ) ? mrn_base_stack_get_header_search_fontawesome_choices() : array(),
			'default_value'     => '',
			'return_format'     => 'value',
			'ui'                => 1,
			'wrapper'           => array(
				'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--fontawesome',
			),
			'conditional_logic' => array(
				array(
					array(
						'field'    => $link_style_field_key,
						'operator' => '==',
						'value'    => 'button',
					),
					array(
						'field'    => $key_prefix . '_source',
						'operator' => '==',
						'value'    => 'fontawesome',
					),
				),
			),
		),
		array(
			'key'               => $key_prefix . '_media',
			'label'             => 'Media Icon',
			'name'              => 'link_icon_media_icon',
			'aria-label'        => '',
			'type'              => 'image',
			'return_format'     => 'array',
			'preview_size'      => 'thumbnail',
			'library'           => 'all',
			'mime_types'        => 'jpg,jpeg,png,gif,webp,svg',
			'wrapper'           => array(
				'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--media',
			),
			'conditional_logic' => array(
				array(
					array(
						'field'    => $link_style_field_key,
						'operator' => '==',
						'value'    => 'button',
					),
					array(
						'field'    => $key_prefix . '_source',
						'operator' => '==',
						'value'    => 'media',
					),
				),
			),
		),
		array(
			'key'               => $key_prefix . '_position',
			'label'             => 'Icon Position',
			'name'              => 'link_icon_position',
			'aria-label'        => '',
			'type'              => 'select',
			'choices'           => array(
				'left'  => 'Left',
				'right' => 'Right',
			),
			'default_value'     => 'left',
			'return_format'     => 'value',
			'ui'                => 1,
			'wrapper'           => array(
				'width' => '25',
			),
			'conditional_logic' => array(
				$base_condition,
			),
		),
		array(
			'key'               => $key_prefix . '_gap',
			'label'             => 'Icon Gap',
			'name'              => 'link_icon_gap',
			'aria-label'        => '',
			'type'              => 'number',
			'default_value'     => 10,
			'min'               => 0,
			'step'              => 1,
			'append'            => 'px',
			'wrapper'           => array(
				'width' => '25',
			),
			'conditional_logic' => array(
				$base_condition,
			),
		),
	);
}

/**
 * Resolve the chosen icon source for a builder button link.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return string
 */
function mrn_base_stack_get_button_link_icon_source( array $row ) {
	$icon_source = isset( $row['link_icon_source'] ) ? sanitize_key( (string) $row['link_icon_source'] ) : '';
	$media_icon  = isset( $row['link_icon_media_icon'] ) && is_array( $row['link_icon_media_icon'] ) ? $row['link_icon_media_icon'] : array();
	$media_id    = isset( $media_icon['ID'] ) ? absint( $media_icon['ID'] ) : 0;
	$media_url   = isset( $media_icon['url'] ) ? esc_url_raw( (string) $media_icon['url'] ) : '';
	$fa_class    = isset( $row['link_icon_fa_class'] ) ? trim( (string) $row['link_icon_fa_class'] ) : '';
	$dashicon    = isset( $row['link_icon_dashicon'] ) ? sanitize_html_class( (string) $row['link_icon_dashicon'] ) : '';

	if ( in_array( $icon_source, array( 'dashicons', 'fontawesome', 'media' ), true ) ) {
		return $icon_source;
	}

	if ( $media_id > 0 || '' !== $media_url ) {
		return 'media';
	}

	if ( '' !== $fa_class ) {
		return 'fontawesome';
	}

	if ( '' !== $dashicon ) {
		return 'dashicons';
	}

	return '';
}

/**
 * Resolve the chosen icon position for a builder button link.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return string
 */
function mrn_base_stack_get_button_link_icon_position( array $row ) {
	$position = isset( $row['link_icon_position'] ) ? sanitize_key( (string) $row['link_icon_position'] ) : 'left';

	return in_array( $position, array( 'left', 'right' ), true ) ? $position : 'left';
}

/**
 * Resolve the chosen icon gap for a builder button link.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return string
 */
function mrn_base_stack_get_button_link_icon_gap( array $row ) {
	if ( ! array_key_exists( 'link_icon_gap', $row ) || '' === (string) $row['link_icon_gap'] ) {
		return '';
	}

	$gap = is_numeric( $row['link_icon_gap'] ) ? (float) $row['link_icon_gap'] : 10.0;
	$gap = max( 0, $gap );

	if ( 0.0 === fmod( $gap, 1.0 ) ) {
		return (string) (int) $gap . 'px';
	}

	return rtrim( rtrim( sprintf( '%.2f', $gap ), '0' ), '.' ) . 'px';
}

/**
 * Build the frontend icon markup for builder button links.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return string
 */
function mrn_base_stack_get_button_link_icon_markup( array $row ) {
	$icon_source = mrn_base_stack_get_button_link_icon_source( $row );
	$position    = mrn_base_stack_get_button_link_icon_position( $row );
	$gap         = mrn_base_stack_get_button_link_icon_gap( $row );
	$style_attr  = '' !== $gap ? ' style="--mrn-link-icon-gap:' . esc_attr( $gap ) . ';"' : '';

	if ( '' === $icon_source ) {
		return '';
	}

	if ( 'fontawesome' === $icon_source ) {
		$fa_class = isset( $row['link_icon_fa_class'] ) ? trim( (string) $row['link_icon_fa_class'] ) : '';

		if ( '' === $fa_class ) {
			return '';
		}

		return '<span class="mrn-ui__link-icon mrn-ui__link-icon--' . esc_attr( $position ) . ' mrn-ui__link-icon--fontawesome" aria-hidden="true"' . $style_attr . '><i class="' . esc_attr( $fa_class ) . '"></i></span>';
	}

	if ( 'dashicons' === $icon_source ) {
		$dashicon = isset( $row['link_icon_dashicon'] ) ? sanitize_html_class( (string) $row['link_icon_dashicon'] ) : '';

		if ( '' !== $dashicon && 0 !== strpos( $dashicon, 'dashicons-' ) ) {
			$dashicon = 'dashicons-' . $dashicon;
		}

		if ( '' === $dashicon ) {
			return '';
		}

		return '<span class="mrn-ui__link-icon mrn-ui__link-icon--' . esc_attr( $position ) . ' mrn-ui__link-icon--dashicons" aria-hidden="true"' . $style_attr . '><span class="dashicons ' . esc_attr( $dashicon ) . '"></span></span>';
	}

	$media_icon = isset( $row['link_icon_media_icon'] ) && is_array( $row['link_icon_media_icon'] ) ? $row['link_icon_media_icon'] : array();
	$media_id   = isset( $media_icon['ID'] ) ? absint( $media_icon['ID'] ) : 0;
	$media_url  = isset( $media_icon['url'] ) ? esc_url( (string) $media_icon['url'] ) : '';

	if ( $media_id > 0 ) {
		$image_markup = wp_get_attachment_image(
			$media_id,
			'thumbnail',
			false,
			array(
				'class'      => 'mrn-ui__link-icon-image',
				'alt'        => '',
				'aria-hidden' => 'true',
			)
		);

		if ( '' !== $image_markup ) {
			return '<span class="mrn-ui__link-icon mrn-ui__link-icon--' . esc_attr( $position ) . ' mrn-ui__link-icon--media" aria-hidden="true"' . $style_attr . '>' . $image_markup . '</span>';
		}
	}

	if ( '' === $media_url ) {
		return '';
	}

	return '<span class="mrn-ui__link-icon mrn-ui__link-icon--' . esc_attr( $position ) . ' mrn-ui__link-icon--media" aria-hidden="true"' . $style_attr . '><img class="mrn-ui__link-icon-image" src="' . esc_url( $media_url ) . '" alt="" /></span>';
}

/**
 * Recursively collect builder button-link icon asset requirements.
 *
 * @param mixed $value Builder field data.
 * @param bool  $needs_fontawesome Whether Font Awesome is needed.
 * @param bool  $needs_dashicons Whether Dashicons are needed.
 * @return void
 */
function mrn_base_stack_collect_builder_link_icon_asset_needs( $value, &$needs_fontawesome, &$needs_dashicons ) {
	if ( ! is_array( $value ) ) {
		return;
	}

	$link_style = isset( $value['link_style'] ) ? sanitize_key( (string) $value['link_style'] ) : '';

	if ( 'button' === $link_style ) {
		$icon_source = mrn_base_stack_get_button_link_icon_source( $value );

		if ( 'fontawesome' === $icon_source ) {
			$needs_fontawesome = true;
		}

		if ( 'dashicons' === $icon_source ) {
			$needs_dashicons = true;
		}
	}

	foreach ( $value as $child ) {
		if ( is_array( $child ) ) {
			mrn_base_stack_collect_builder_link_icon_asset_needs( $child, $needs_fontawesome, $needs_dashicons );
		}

		if ( $needs_fontawesome && $needs_dashicons ) {
			return;
		}
	}
}

/**
 * Allow a small, intentional inline HTML subset for heading-style fields.
 *
 * @param string $value Raw heading text value.
 * @return string
 */
function mrn_base_stack_format_heading_inline_html( $value ) {
	$allowed_tags = array(
		'span'   => array(
			'class' => true,
		),
		'strong' => array(),
		'em'     => array(),
		'br'     => array(),
	);

	return wp_kses( (string) $value, $allowed_tags );
}

/**
 * Build a row-specific pagination query arg for content-list builder rows.
 *
 * @param int $post_id Current post ID.
 * @param int $index Zero-based row index.
 * @return string
 */
function mrn_base_stack_get_content_list_pagination_query_arg( $post_id, $index ) {
	return sanitize_key( sprintf( 'mrn_list_page_%d_%d', absint( $post_id ), absint( $index ) ) );
}

/**
 * Resolve the requested content-list page from the current query string.
 *
 * @param int $post_id Current post ID.
 * @param int $index Zero-based row index.
 * @return int
 */
function mrn_base_stack_get_content_list_current_page( $post_id, $index ) {
	$query_arg = mrn_base_stack_get_content_list_pagination_query_arg( $post_id, $index );

	if ( isset( $_GET[ $query_arg ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,Generic.WhiteSpace.ScopeIndent.IncorrectExact
		return max( 1, absint( wp_unslash( $_GET[ $query_arg ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,Generic.WhiteSpace.ScopeIndent.IncorrectExact
	}

	return 1;
}

/**
 * Build a trimmed excerpt for content-list rows.
 *
 * @param WP_Post $post Current listing post.
 * @param int     $word_count Desired word count.
 * @return string
 */
function mrn_base_stack_get_content_list_excerpt( WP_Post $post, $word_count = 24 ) {
	$word_count = max( 1, absint( $word_count ) );
	$excerpt    = trim( (string) get_the_excerpt( $post ) );

	if ( '' === $excerpt ) {
		$excerpt = trim( wp_strip_all_tags( (string) $post->post_content ) );
	}

	if ( '' === $excerpt ) {
		return '';
	}

	return wp_trim_words( $excerpt, $word_count, '...' );
}

/**
 * Build a taxonomy filter query for a content-list row.
 *
 * @param array<string, mixed> $row Content-list row settings.
 * @param int                  $context_post_id Current page/post ID.
 * @param string               $target_post_type Queried post type.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_content_list_tax_query( array $row, $context_post_id, $target_post_type ) {
	$filter_source = isset( $row['filter_source'] ) ? sanitize_key( (string) $row['filter_source'] ) : 'none';
	$taxonomy      = isset( $row['filter_taxonomy'] ) ? sanitize_key( (string) $row['filter_taxonomy'] ) : '';
	$match_mode    = isset( $row['filter_match'] ) ? sanitize_key( (string) $row['filter_match'] ) : 'any';

	if ( 'none' === $filter_source || '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	if ( '' !== $target_post_type && ! is_object_in_taxonomy( $target_post_type, $taxonomy ) ) {
		return array();
	}

	$operator = 'all' === $match_mode ? 'AND' : 'IN';

	if ( 'current_post_terms' === $filter_source ) {
		$term_ids = wp_get_post_terms(
			absint( $context_post_id ),
			$taxonomy,
			array(
				'fields' => 'ids',
			)
		);

		if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
			return array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => array( 0 ),
					'operator' => 'IN',
				),
			);
		}

		return array(
			array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => array_map( 'absint', $term_ids ),
				'operator' => $operator,
			),
		);
	}

	if ( 'manual_terms' === $filter_source ) {
		$raw_terms  = isset( $row['filter_term_slugs'] ) ? (string) $row['filter_term_slugs'] : '';
		$term_slugs = array_values(
			array_filter(
				array_map(
					'sanitize_title',
					false !== preg_split( '/[\s,]+/', $raw_terms ) ? preg_split( '/[\s,]+/', $raw_terms ) : array()
				),
				'strlen'
			)
		);

		if ( empty( $term_slugs ) ) {
			return array();
		}

		return array(
			array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $term_slugs,
				'operator' => $operator,
			),
		);
	}

	return array();
}

/**
 * Build a CSS custom-property declaration for a selected background image.
 *
 * @param mixed  $image ACF image field value.
 * @param string $css_var CSS custom property name.
 * @return string
 */
function mrn_base_stack_get_background_image_style( $image, $css_var ) {
	$image_url = '';

	if ( is_array( $image ) && ! empty( $image['url'] ) ) {
		$image_url = (string) $image['url'];
	} elseif ( is_string( $image ) ) {
		$image_url = $image;
	}

	$image_url = trim( $image_url );
	$css_var   = trim( (string) $css_var );

	if ( '' === $image_url || '' === $css_var ) {
		return '';
	}

	return $css_var . ": url('" . esc_url_raw( $image_url ) . "')";
}

/**
 * Normalize a YouTube or Vimeo URL into an embed URL.
 *
 * @param mixed                $url Raw video field value.
 * @param array<string, mixed> $options Embed behavior options.
 * @return array{provider:string,embed_url:string}
 */
function mrn_base_stack_get_video_embed( $url, array $options = array() ) {
	$raw_url = is_string( $url ) ? trim( $url ) : '';
	$options = wp_parse_args(
		$options,
		array(
			'autoplay'   => false,
			'muted'      => false,
			'loop'       => false,
			'controls'   => true,
			'background' => false,
		)
	);

	if ( '' === $raw_url ) {
		return array(
			'provider'  => '',
			'embed_url' => '',
		);
	}

	$sanitized_url = esc_url_raw( $raw_url );
	if ( '' === $sanitized_url ) {
		return array(
			'provider'  => '',
			'embed_url' => '',
		);
	}

	if ( preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $sanitized_url, $matches ) ) {
		$video_id = $matches[1];
		$query    = array(
			'autoplay'       => ! empty( $options['autoplay'] ) ? '1' : '0',
			'mute'           => ! empty( $options['muted'] ) ? '1' : '0',
			'controls'       => ! empty( $options['controls'] ) ? '1' : '0',
			'loop'           => ! empty( $options['loop'] ) ? '1' : '0',
			'playlist'       => ! empty( $options['loop'] ) ? $video_id : '',
			'playsinline'    => '1',
			'rel'            => '0',
			'modestbranding' => '1',
		);

		return array(
			'provider'  => 'youtube',
			'embed_url' => sprintf( 'https://www.youtube.com/embed/%s?%s', rawurlencode( $video_id ), http_build_query( array_filter( $query, 'strlen' ), '', '&', PHP_QUERY_RFC3986 ) ),
		);
	}

	if ( preg_match( '~vimeo\.com/(?:video/)?([0-9]+)~', $sanitized_url, $matches ) ) {
		$video_id = $matches[1];
		$query    = array(
			'autoplay'   => ! empty( $options['autoplay'] ) ? '1' : '0',
			'muted'      => ! empty( $options['muted'] ) ? '1' : '0',
			'loop'       => ! empty( $options['loop'] ) ? '1' : '0',
			'background' => ! empty( $options['background'] ) ? '1' : '0',
			'autopause'  => ! empty( $options['background'] ) ? '0' : '1',
			'controls'   => ! empty( $options['controls'] ) ? '1' : '0',
			'byline'     => '0',
			'title'      => '0',
		);

		return array(
			'provider'  => 'vimeo',
			'embed_url' => sprintf( 'https://player.vimeo.com/video/%s?%s', rawurlencode( $video_id ), http_build_query( array_filter( $query, 'strlen' ), '', '&', PHP_QUERY_RFC3986 ) ),
		);
	}

	return array(
		'provider'  => '',
		'embed_url' => '',
	);
}

/**
 * Nested layouts available inside the Two Column Split builder row.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_two_column_nested_layouts() {
	return array(
		'layout_mrn_nested_body_text' => array(
			'key'        => 'layout_mrn_nested_body_text',
			'name'       => 'body_text',
			'label'      => 'Text - rich text',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_body_text_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'          => 'field_mrn_nested_body_text_content',
					'label'        => 'Body Text',
					'name'         => 'body_text',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'        => 'field_mrn_nested_body_text_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_body_text_bottom_accent',
					'label'         => 'Bottom Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_body_text_bottom_accent_style',
					'label'         => 'Bottom Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_body_text_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_body_text_motion_settings' ),
			),
		),
		'layout_mrn_nested_basic' => array(
			'key'        => 'layout_mrn_nested_basic',
			'name'       => 'basic',
			'label'      => 'Basic - label|heading|subheading|text with editor|image|link',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_basic_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_basic_label', 'Label', 'label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_basic_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_basic_heading', 'Heading', 'heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_basic_heading_tag', 'heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_basic_subheading', 'Subheading', 'subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_basic_subheading_tag', 'subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'          => 'field_mrn_nested_basic_content',
					'label'        => 'Text area with editor',
					'name'         => 'content',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'           => 'field_mrn_nested_basic_image',
					'label'         => 'Image',
					'name'          => 'image',
					'aria-label'    => '',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_link',
					'label'         => 'Link',
					'name'          => 'link',
					'aria-label'    => '',
					'type'          => 'link',
					'return_format' => 'array',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'        => 'field_mrn_nested_basic_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_basic_link_style',
					'label'         => 'Link style',
					'name'          => 'link_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_link_style_choices' )
						? mrn_rbl_get_link_style_choices()
						: array(
							'link'   => 'Link',
							'button' => 'Button',
						),
					'default_value' => 'link',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				...mrn_base_stack_get_button_link_icon_fields( 'field_mrn_nested_basic_link_icon', 'field_mrn_nested_basic_link_style' ),
				array(
					'key'           => 'field_mrn_nested_basic_link_color',
					'label'         => 'Link color',
					'name'          => 'link_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_image_placement',
					'label'         => 'Image placement',
					'name'          => 'image_placement',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'left'  => 'Left',
						'right' => 'Right',
					),
					'default_value' => 'left',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_background_image',
					'label'         => 'Background image',
					'name'          => 'background_image',
					'aria-label'    => '',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_basic_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_basic_motion_settings' ),
			),
		),
		'layout_mrn_nested_card' => array(
			'key'        => 'layout_mrn_nested_card',
			'name'       => 'card',
			'label'      => 'Card - image|text|link',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_card_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_card_heading', 'Heading', 'heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_card_heading_tag', 'heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_card_subheading', 'Subheading', 'subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_card_subheading_tag', 'subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'           => 'field_mrn_nested_card_link',
					'label'         => 'Link',
					'name'          => 'link',
					'aria-label'    => '',
					'type'          => 'link',
					'return_format' => 'array',
				),
				array(
					'key'          => 'field_mrn_nested_card_items',
					'label'        => 'Cards',
					'name'         => 'card_items',
					'aria-label'   => '',
					'type'         => 'repeater',
					'layout'       => 'row',
					'collapsed'    => 'field_mrn_nested_card_item_text',
					'button_label' => 'Add Card',
					'min'          => 1,
					'sub_fields'   => array(
						array(
							'key'           => 'field_mrn_nested_card_item_image',
							'label'         => 'Image',
							'name'          => 'image',
							'aria-label'    => '',
							'type'          => 'image',
							'return_format' => 'array',
							'preview_size'  => 'medium',
							'library'       => 'all',
							'wrapper'       => array(
								'width' => '33',
							),
						),
						array(
							'key'          => 'field_mrn_nested_card_item_text',
							'label'        => 'Text',
							'name'         => 'text',
							'aria-label'   => '',
							'type'         => 'wysiwyg',
							'tabs'         => 'all',
							'toolbar'      => 'full',
							'media_upload' => 1,
							'delay'        => 0,
							'wrapper'      => array(
								'width' => '34',
							),
						),
						array(
							'key'           => 'field_mrn_nested_card_item_link',
							'label'         => 'Link',
							'name'          => 'link',
							'aria-label'    => '',
							'type'          => 'link',
							'return_format' => 'array',
							'wrapper'       => array(
								'width' => '33',
							),
						),
					),
				),
				array(
					'key'        => 'field_mrn_nested_card_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'           => 'field_mrn_nested_card_background_color',
					'label'         => 'Background Color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
				),
				array(
					'key'           => 'field_mrn_nested_card_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_card_bottom_accent_style',
					'label'         => 'Bottom Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_card_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_card_motion_settings' ),
			),
		),
		'layout_mrn_nested_cta' => array(
			'key'        => 'layout_mrn_nested_cta',
			'name'       => 'cta',
			'label'      => 'CTA - label|heading|subheading|text with editor|link',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'          => 'field_mrn_nested_cta_fields',
					'label'        => 'CTA',
					'name'         => '',
					'aria-label'   => '',
					'type'         => 'clone',
					'clone'        => array( 'group_mrn_reusable_cta' ),
					'display'      => 'seamless',
					'layout'       => 'block',
					'prefix_label' => 0,
					'prefix_name'  => 0,
				),
				mrn_base_stack_get_section_width_field( 'field_mrn_nested_cta_section_width' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_cta_motion_settings' ),
			),
		),
		'layout_mrn_nested_grid' => array(
			'key'        => 'layout_mrn_nested_grid',
			'name'       => 'grid',
			'label'      => 'Grid - label|heading|subheading|repeater',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'          => 'field_mrn_nested_grid_fields',
					'label'        => 'Grid',
					'name'         => '',
					'aria-label'   => '',
					'type'         => 'clone',
					'clone'        => array( 'group_mrn_reusable_content_grid' ),
					'display'      => 'seamless',
					'layout'       => 'block',
					'prefix_label' => 0,
					'prefix_name'  => 0,
				),
				mrn_base_stack_get_section_width_field( 'field_mrn_nested_grid_section_width' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_grid_motion_settings' ),
			),
		),
		'layout_mrn_nested_image_content' => array(
			'key'        => 'layout_mrn_nested_image_content',
			'name'       => 'image_content',
			'label'      => 'Image - label|heading|subheading|text with editor',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'           => 'field_mrn_nested_image_content_content_tab',
					'label'         => 'Content',
					'name'          => '',
					'aria-label'    => '',
					'type'          => 'tab',
					'placement'     => 'top',
				),
				array(
					'key'           => 'field_mrn_nested_image_content_image',
					'label'         => 'Image',
					'name'          => 'image',
					'aria-label'    => '',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
				),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_image_content_label', 'Label', 'label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_image_content_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_image_content_heading', 'Heading', 'heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_image_content_heading_tag', 'heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_image_content_subheading', 'Subheading', 'subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_image_content_subheading_tag', 'subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'          => 'field_mrn_nested_image_content_copy',
					'label'        => 'Text area with editor',
					'name'         => 'content',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'        => 'field_mrn_nested_image_content_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_image_content_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
				),
				array(
					'key'           => 'field_mrn_nested_image_content_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_full_width',
					'label'         => 'Full width',
					'name'          => 'full_width',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_position',
					'label'         => 'Image position',
					'name'          => 'image_position',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'top'    => 'Top',
						'bottom' => 'Bottom',
					),
					'default_value' => 'top',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_size',
					'label'         => 'Image size',
					'name'          => 'image_size',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'contained' => 'Contained',
						'cover'     => 'Cover',
					),
					'default_value' => 'contained',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_alignment',
					'label'         => 'Image alignment',
					'name'          => 'image_alignment',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'left'   => 'Left',
						'center' => 'Center',
						'right'  => 'Right',
					),
					'default_value' => 'center',
					'ui'            => 1,
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_image_content_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_image_content_motion_settings' ),
			),
		),
		'layout_mrn_nested_video' => array(
			'key'        => 'layout_mrn_nested_video',
			'name'       => 'video',
			'label'      => 'Video - remote|upload',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_video_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_video_label', 'Label', 'label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_video_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_video_heading', 'Heading', 'heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_video_heading_tag', 'heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_video_subheading', 'Subheading', 'subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_video_subheading_tag', 'subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'          => 'field_mrn_nested_video_content',
					'label'        => 'Text area with editor',
					'name'         => 'content',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'          => 'field_mrn_nested_video_remote',
					'label'        => 'Remote video URL',
					'name'         => 'video_remote',
					'aria-label'   => '',
					'type'         => 'url',
					'instructions' => 'Paste a YouTube or Vimeo URL.',
					'wrapper'      => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_video_upload',
					'label'         => 'Video upload',
					'name'          => 'video_upload',
					'aria-label'    => '',
					'type'          => 'file',
					'return_format' => 'array',
					'library'       => 'all',
					'mime_types'    => 'mp4,webm,mov',
					'instructions'  => 'Optional local upload. When both upload and remote URL are set, the upload is used first.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'        => 'field_mrn_nested_video_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_video_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_video_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_video_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_video_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_video_motion_settings' ),
			),
		),
		'layout_mrn_nested_logos' => array(
			'key'        => 'layout_mrn_nested_logos',
			'name'       => 'logos',
			'label'      => 'Logos - label|heading|image|link',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_logos_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_logos_label', 'Label', 'label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_logos_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_logos_heading', 'Heading', 'heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_logos_heading_tag', 'heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_logos_subheading', 'Subheading', 'subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_logos_subheading_tag', 'subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'          => 'field_mrn_nested_logos_items',
					'label'        => 'Logos',
					'name'         => 'logo_items',
					'aria-label'   => '',
					'type'         => 'repeater',
					'layout'       => 'row',
					'button_label' => 'Add Logo',
					'min'          => 1,
					'sub_fields'   => array(
						array(
							'key'           => 'field_mrn_nested_logos_item_image',
							'label'         => 'Image',
							'name'          => 'image',
							'aria-label'    => '',
							'type'          => 'image',
							'return_format' => 'array',
							'preview_size'  => 'medium',
							'library'       => 'all',
							'wrapper'       => array(
								'width' => '50',
							),
						),
						array(
							'key'           => 'field_mrn_nested_logos_item_link',
							'label'         => 'Link',
							'name'          => 'link',
							'aria-label'    => '',
							'type'          => 'link',
							'return_format' => 'array',
							'wrapper'       => array(
								'width' => '50',
							),
						),
					),
				),
				array(
					'key'        => 'field_mrn_nested_logos_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_logos_display_mode',
					'label'         => 'Display mode',
					'name'          => 'display_mode',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'grid'   => 'Grid',
						'slider' => 'Slider',
					),
					'default_value' => 'grid',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_per_page',
					'label'         => 'Logos per row/view',
					'name'          => 'per_page',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'3' => '3',
						'4' => '4',
						'5' => '5',
						'6' => '6',
					),
					'default_value' => '4',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_arrows',
					'label'         => 'Show arrows',
					'name'          => 'show_arrows',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_pagination',
					'label'         => 'Show pagination',
					'name'          => 'show_pagination',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_autoplay',
					'label'         => 'Autoplay',
					'name'          => 'autoplay',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_pause_on_hover',
					'label'         => 'Pause on hover',
					'name'          => 'pause_on_hover',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 1,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_delay_start',
					'label'         => 'Delay start',
					'name'          => 'delay_start',
					'aria-label'    => '',
					'type'          => 'number',
					'default_value' => 0,
					'step'          => 0.1,
					'min'           => 0,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_delay_time',
					'label'         => 'Delay time',
					'name'          => 'delay_time',
					'aria-label'    => '',
					'type'          => 'number',
					'default_value' => 5,
					'step'          => 0.1,
					'min'           => 0,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_time_on_slide',
					'label'         => 'Time on slide',
					'name'          => 'time_on_slide',
					'aria-label'    => '',
					'type'          => 'number',
					'default_value' => 600,
					'step'          => 10,
					'min'           => 100,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
				),
				array(
					'key'           => 'field_mrn_nested_logos_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_logos_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_logos_motion_settings' ),
			),
		),
		'layout_mrn_nested_external_widget' => array(
			'key'        => 'layout_mrn_nested_external_widget',
			'name'       => 'external_widget',
			'label'      => 'External - widget/iFrame',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_external_widget_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'          => 'field_mrn_nested_external_widget_code',
					'label'        => 'Snippet/Code',
					'name'         => 'code',
					'aria-label'   => '',
					'type'         => 'textarea',
					'rows'         => 8,
				),
				array(
					'key'        => 'field_mrn_nested_external_widget_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_external_widget_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_external_widget_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_external_widget_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_external_widget_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_external_widget_motion_settings' ),
			),
		),
		'layout_mrn_nested_wpforms' => array(
			'key'        => 'layout_mrn_nested_wpforms',
			'name'       => 'wpforms',
			'label'      => 'WPForms - label|heading|subheading|rich text|form',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_wpforms_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_wpforms_label', 'Label', 'label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_wpforms_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_wpforms_heading', 'Heading', 'heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_wpforms_heading_tag', 'heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_wpforms_subheading', 'Subheading', 'subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_wpforms_subheading_tag', 'subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'          => 'field_mrn_nested_wpforms_intro',
					'label'        => 'Text area with editor',
					'name'         => 'intro',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'           => 'field_mrn_nested_wpforms_form',
					'label'         => 'Form',
					'name'          => 'form',
					'aria-label'    => '',
					'type'          => 'post_object',
					'post_type'     => array( 'wpforms' ),
					'return_format' => 'object',
					'ui'            => 1,
					'allow_null'    => 0,
					'multiple'      => 0,
					'instructions'  => 'Choose from the WPForms forms available on this site.',
				),
				array(
					'key'        => 'field_mrn_nested_wpforms_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_wpforms_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
				),
				array(
					'key'           => 'field_mrn_nested_wpforms_bottom_accent',
					'label'         => 'Bottom Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_wpforms_bottom_accent_style',
					'label'         => 'Bottom Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_wpforms_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_wpforms_motion_settings' ),
			),
		),
		'layout_mrn_nested_reusable_block' => array(
			'key'        => 'layout_mrn_nested_reusable_block',
			'name'       => 'reusable_block',
			'label'      => 'Reusable Block',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'           => 'field_mrn_nested_reusable_block_post',
					'label'         => 'Block',
					'name'          => 'block',
					'aria-label'    => '',
					'type'          => 'post_object',
					'post_type'     => function_exists( 'mrn_rbl_get_post_types' ) ? mrn_rbl_get_post_types() : array(),
					'return_format' => 'object',
					'ui'            => 1,
					'allow_null'    => 0,
					'multiple'      => 0,
					'instructions'  => 'Choose a reusable block from the library. Editing that block updates it everywhere it is used.',
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_reusable_block_anchor' ),
			),
		),
	);
}

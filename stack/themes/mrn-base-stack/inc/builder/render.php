<?php
/**
 * Builder rendering, conversion, and search integration.
 *
 * @package mrn-base-stack
 */

/**
 * Render one hero-compatible builder row.
 *
 * @param array<string, mixed> $row Flexible content row.
 * @param int                  $post_id Current post ID.
 * @param int                  $index Zero-based row index.
 * @return bool True when the row was rendered as a hero layout.
 */
function mrn_base_stack_render_hero_row( array $row, $post_id, $index ) {
	if ( empty( $row['acf_fc_layout'] ) ) {
		return false;
	}

	$layout  = (string) $row['acf_fc_layout'];
	$context = mrn_base_stack_get_builder_row_context( $row, $post_id, $index );

	if ( 'basic' === $layout ) {
		get_template_part( 'template-parts/builder/hero', null, $context );
		return true;
	}

	if ( 'two_column_split' === $layout ) {
		get_template_part( 'template-parts/builder/two-column-split', null, $context );
		return true;
	}

	return false;
}

/**
 * Render the ACF hero rows for posts and pages.
 *
 * @param int|null $post_id Post ID to render. Defaults to current post.
 * @return bool True when at least one hero row was rendered.
 */
function mrn_base_stack_render_hero_builder( $post_id = null ) {
	if ( ! function_exists( 'get_field' ) ) {
		return false;
	}

	$post_id = $post_id ? (int) $post_id : get_the_ID();
	if ( ! $post_id ) {
		return false;
	}

	$rows = get_field( 'page_hero_rows', $post_id );
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return false;
	}

	$rendered = false;

	foreach ( $rows as $index => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$row['__mrn_builder_post_id']    = (int) $post_id;
		$row['__mrn_builder_field_name'] = 'page_hero_rows';
		$row['__mrn_builder_row_index']  = (int) $index;

		if ( mrn_base_stack_render_hero_row( $row, $post_id, $index ) ) {
			$rendered = true;
		}
	}

	return $rendered;
}

/**
 * Wrap cloned reusable-block markup in the same layered width shell as native layouts.
 *
 * @param string               $inner_markup HTML from `mrn_rbl_render_fields_as_block()`.
 * @param array<string, mixed> $row Flexible content row (may include `section_width`).
 * @param string               $row_modifier Extra class on `.mrn-content-builder__row`.
 * @param string               $section_modifier Extra class on `.mrn-layout-section` (e.g. `mrn-layout-section--reusable-cta`).
 * @param string               $default_width Default width when `section_width` is empty.
 * @param bool                 $include_motion_contract Whether the wrapper should carry the row motion contract.
 * @return string
 */
function mrn_base_stack_wrap_cloned_reusable_builder_markup( $inner_markup, array $row, $row_modifier, $section_modifier, $default_width = 'wide', $include_motion_contract = true ) {
	$inner_markup = is_string( $inner_markup ) ? $inner_markup : '';
	if ( '' === trim( $inner_markup ) ) {
		return '';
	}

	$width_layers = function_exists( 'mrn_base_stack_get_section_width_layers' )
		? mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', $default_width, 'full-width' )
		: array(
			'width'           => 'wide',
			'section_class'   => 'mrn-layout-section--contained',
			'container_class' => 'mrn-layout-container--wide',
		);

	$row_classes       = trim( 'mrn-content-builder__row ' . $row_modifier );
	$section_classes   = trim( 'mrn-layout-section ' . $section_modifier . ' ' . ( $width_layers['section_class'] ?? 'mrn-layout-section--contained' ) );
	$container_classes = trim( 'mrn-layout-container ' . ( $width_layers['container_class'] ?? 'mrn-layout-container--wide' ) );
	$row_attributes    = array();
	$layout_name       = isset( $row['acf_fc_layout'] ) ? sanitize_key( (string) $row['acf_fc_layout'] ) : '';
	if ( '' !== $layout_name && function_exists( 'mrn_base_stack_get_builder_display_contract' ) ) {
		$display_contract = mrn_base_stack_get_builder_display_contract( $row, $layout_name );

		if ( ! empty( $display_contract['classes'] ) && is_array( $display_contract['classes'] ) ) {
			$row_classes = trim( $row_classes . ' ' . implode( ' ', array_filter( $display_contract['classes'], 'strlen' ) ) );
		}

		if ( function_exists( 'mrn_base_stack_merge_builder_attributes' ) ) {
			$row_attributes = mrn_base_stack_merge_builder_attributes(
				$row_attributes,
				isset( $display_contract['attributes'] ) && is_array( $display_contract['attributes'] ) ? $display_contract['attributes'] : array()
			);
		} elseif ( isset( $display_contract['attributes'] ) && is_array( $display_contract['attributes'] ) ) {
			$row_attributes = array_merge( $row_attributes, $display_contract['attributes'] );
		}
	}

	if ( ! $include_motion_contract ) {
		$flex_contract        = function_exists( 'mrn_base_stack_get_builder_flex_contract' ) ? mrn_base_stack_get_builder_flex_contract( $row ) : array(
			'classes'    => array(),
			'attributes' => array(),
		);
		$sub_content_contract = function_exists( 'mrn_base_stack_get_builder_sub_content_width_contract' ) ? mrn_base_stack_get_builder_sub_content_width_contract( $row ) : array(
			'classes'    => array(),
			'attributes' => array(),
		);
		$row_spacing_contract = function_exists( 'mrn_base_stack_get_builder_row_spacing_contract' ) ? mrn_base_stack_get_builder_row_spacing_contract( $row ) : array(
			'classes'    => array(),
			'attributes' => array(),
		);
		$combined_contract    = array(
			'classes'    => array_merge(
				isset( $flex_contract['classes'] ) && is_array( $flex_contract['classes'] ) ? $flex_contract['classes'] : array(),
				isset( $sub_content_contract['classes'] ) && is_array( $sub_content_contract['classes'] ) ? $sub_content_contract['classes'] : array(),
				isset( $row_spacing_contract['classes'] ) && is_array( $row_spacing_contract['classes'] ) ? $row_spacing_contract['classes'] : array()
			),
			'attributes' => array_merge(
				isset( $flex_contract['attributes'] ) && is_array( $flex_contract['attributes'] ) ? $flex_contract['attributes'] : array(),
				isset( $sub_content_contract['attributes'] ) && is_array( $sub_content_contract['attributes'] ) ? $sub_content_contract['attributes'] : array(),
				isset( $row_spacing_contract['attributes'] ) && is_array( $row_spacing_contract['attributes'] ) ? $row_spacing_contract['attributes'] : array()
			),
		);

		if ( ! empty( $combined_contract['classes'] ) && is_array( $combined_contract['classes'] ) ) {
			$row_classes = trim( $row_classes . ' ' . implode( ' ', array_filter( $combined_contract['classes'], 'strlen' ) ) );
		}

		if ( function_exists( 'mrn_base_stack_merge_builder_attributes' ) ) {
			$row_attributes = mrn_base_stack_merge_builder_attributes(
				$row_attributes,
				isset( $combined_contract['attributes'] ) && is_array( $combined_contract['attributes'] ) ? $combined_contract['attributes'] : array()
			);
		} elseif ( isset( $combined_contract['attributes'] ) && is_array( $combined_contract['attributes'] ) ) {
			$row_attributes = array_merge( $row_attributes, $combined_contract['attributes'] );
		}
	}

	if ( $include_motion_contract ) {
		$motion_contract = function_exists( 'mrn_base_stack_get_builder_motion_contract' ) ? mrn_base_stack_get_builder_motion_contract( $row ) : array(
			'classes'    => array(),
			'attributes' => array(),
		);

		if ( ! empty( $motion_contract['classes'] ) && is_array( $motion_contract['classes'] ) ) {
			$row_classes = trim( $row_classes . ' ' . implode( ' ', array_filter( $motion_contract['classes'], 'strlen' ) ) );
		}

		if ( function_exists( 'mrn_base_stack_merge_builder_attributes' ) ) {
			$row_attributes = mrn_base_stack_merge_builder_attributes(
				$row_attributes,
				isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array()
			);
		} elseif ( isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ) {
			$row_attributes = array_merge( $row_attributes, $motion_contract['attributes'] );
		}
	}

	$row_attribute_html = function_exists( 'mrn_base_stack_get_html_attributes' ) ? mrn_base_stack_get_html_attributes( $row_attributes ) : '';

		$anchor_fallback = '';
		$anchor_row      = $row;
	if ( in_array( $layout_name, array( 'faq', 'faq_block' ), true ) && function_exists( 'mrn_base_stack_get_faq_jump_nav_anchor_from_row' ) ) {
		$anchor_fallback = mrn_base_stack_get_faq_jump_nav_anchor_from_row( $row );
		$anchor_source   = function_exists( 'mrn_base_stack_normalize_anchor_id' )
			? mrn_base_stack_normalize_anchor_id( $row['anchor'] ?? '' )
			: sanitize_title( (string) ( $row['anchor'] ?? '' ) );
		if ( '' !== $anchor_fallback && $anchor_source !== $anchor_fallback ) {
			$anchor_row['anchor'] = '';
		}
	}
		$anchor_markup = function_exists( 'mrn_base_stack_get_builder_anchor_markup' ) ? mrn_base_stack_get_builder_anchor_markup( $anchor_row, $anchor_fallback ) : '';

	return sprintf(
		'%6$s<div class="%1$s"%5$s><div class="%2$s"><div class="%3$s"><div class="mrn-layout-grid mrn-layout-grid--reusable"><div class="mrn-layout-content mrn-layout-content--reusable">%4$s</div></div></div></div></div>',
		esc_attr( $row_classes ),
		esc_attr( $section_classes ),
		esc_attr( $container_classes ),
		$inner_markup, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		'' !== $row_attribute_html ? ' ' . $row_attribute_html : '',
		$anchor_markup // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
	);
}

/**
 * Map reusable block post types to the theme's shared layered section modifier classes.
 *
 * @param string $post_type Reusable block post type.
 * @return string
 */
function mrn_base_stack_get_reusable_block_shell_modifier( $post_type ) {
	$map = array(
		'mrn_reusable_basic'   => 'mrn-layout-section--reusable-basic',
		'mrn_reusable_cta'     => 'mrn-layout-section--reusable-cta',
		'mrn_reusable_list'    => 'mrn-layout-section--reusable-content-lists',
		'mrn_reusable_faq'     => 'mrn-layout-section--reusable-faq',
		'mrn_reusable_grid'    => 'mrn-layout-section--reusable-grid',
		'mrn_reusable_search'  => 'mrn-layout-section--reusable-search-form',
		'mrn_reusable_partner' => 'mrn-layout-section--reusable-partners',
	);

	$post_type = sanitize_key( (string) $post_type );

	return $map[ $post_type ] ?? 'mrn-layout-section--reusable-block';
}

/**
 * Map reusable block post types to shared row modifier classes.
 *
 * @param string $post_type Reusable block post type.
 * @return string
 */
function mrn_base_stack_get_reusable_block_row_modifier( $post_type ) {
	$map = array(
		'mrn_reusable_basic'   => 'mrn-content-builder__row--basic-block',
		'mrn_reusable_cta'     => 'mrn-content-builder__row--cta',
		'mrn_reusable_list'    => 'mrn-content-builder__row--content-lists',
		'mrn_reusable_faq'     => 'mrn-content-builder__row--faq-block',
		'mrn_reusable_grid'    => 'mrn-content-builder__row--content-grid',
		'mrn_reusable_search'  => 'mrn-content-builder__row--searchwp-form',
		'mrn_reusable_partner' => 'mrn-content-builder__row--partners',
	);

	$post_type = sanitize_key( (string) $post_type );

	return $map[ $post_type ] ?? 'mrn-content-builder__row--reusable-block';
}

/**
 * Wrap reusable block markup in the standard builder width shell.
 *
 * @param string               $inner_markup Reusable block HTML.
 * @param array<string, mixed> $row Flexible content row.
 * @param string               $post_type Reusable block post type.
 * @param string               $default_width Default width when none is stored.
 * @param bool                 $include_motion_contract Whether the wrapper should carry the row motion contract.
 * @return string
 */
function mrn_base_stack_wrap_reusable_builder_markup( $inner_markup, array $row, $post_type, $default_width = 'wide', $include_motion_contract = true ) {
	return mrn_base_stack_wrap_cloned_reusable_builder_markup(
		$inner_markup,
		$row,
		mrn_base_stack_get_reusable_block_row_modifier( $post_type ),
		mrn_base_stack_get_reusable_block_shell_modifier( $post_type ),
		$default_width,
		$include_motion_contract
	);
}

/**
 * Render a single builder row.
 *
 * @param array<string, mixed> $row Flexible Content row.
 * @param int                  $post_id Current post ID.
 * @param int                  $index Zero-based row index.
 * @return bool True when a known row type was rendered.
 */
function mrn_base_stack_render_builder_row( array $row, $post_id, $index ) {
	if ( empty( $row['acf_fc_layout'] ) ) {
		return false;
	}

	$layout  = (string) $row['acf_fc_layout'];
	$context = mrn_base_stack_get_builder_row_context( $row, $post_id, $index );

	if ( 'body_text' === $layout ) {
		get_template_part( 'template-parts/builder/body-text', null, $context );
		return true;
	}

	if ( 'content_lists' === $layout ) {
		get_template_part( 'template-parts/builder/content-lists', null, $context );
		return true;
	}

	if ( 'basic' === $layout ) {
		get_template_part( 'template-parts/builder/basic', null, $context );
		return true;
	}

	if ( 'cta' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			$markup         = mrn_rbl_render_fields_as_block(
				'mrn_reusable_cta',
				$row,
				array(
					'post_id'               => (int) $post_id,
					'post_name'             => 'page-cta',
					'block_name'            => 'Page Specific CTA',
					'suppress_anchor'       => true,
					'apply_motion_contract' => true,
				)
			);
			$wrapped_markup = mrn_base_stack_wrap_reusable_builder_markup(
				$markup,
				$row,
				'mrn_reusable_cta',
				'wide',
				false
			);
			echo $wrapped_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapped reusable markup is escaped within the helper.
			return true;
		}

		return false;
	}

	if ( 'grid' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			$markup         = mrn_rbl_render_fields_as_block(
				'mrn_reusable_grid',
				$row,
				array(
					'post_id'               => (int) $post_id,
					'post_name'             => 'page-grid',
					'block_name'            => 'Page Grid',
					'suppress_anchor'       => true,
					'apply_motion_contract' => true,
				)
			);
			$wrapped_markup = mrn_base_stack_wrap_reusable_builder_markup(
				$markup,
				$row,
				'mrn_reusable_grid',
				'wide',
				false
			);
			echo $wrapped_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapped reusable markup is escaped within the helper.
			return true;
		}

		return false;
	}

	if ( 'faq' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			$markup         = mrn_rbl_render_fields_as_block(
				'mrn_reusable_faq',
				$row,
				array(
					'post_id'               => (int) $post_id,
					'post_name'             => 'page-faq-accordion',
					'block_name'            => 'Page FAQs/Accordion',
					'suppress_anchor'       => true,
					'apply_motion_contract' => true,
				)
			);
			$wrapped_markup = mrn_base_stack_wrap_reusable_builder_markup( $markup, $row, 'mrn_reusable_faq', 'wide', false );
			echo $wrapped_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapped reusable markup is escaped within the helper.
			return true;
		}

		return false;
	}

	if ( 'faq_jump_nav' === $layout ) {
		get_template_part( 'template-parts/builder/faq-jump-nav', null, $context );
		return true;
	}

	if ( 'image_content' === $layout ) {
		get_template_part( 'template-parts/builder/image-content', null, $context );
		return true;
	}

	if ( 'video' === $layout ) {
		get_template_part( 'template-parts/builder/video', null, $context );
		return true;
	}

	if ( 'slider' === $layout ) {
		get_template_part( 'template-parts/builder/slider', null, $context );
		return true;
	}

	if ( 'tabbed_layout' === $layout ) {
		get_template_part( 'template-parts/builder/tabbed-layout', null, $context );
		return true;
	}

	if ( 'logos' === $layout ) {
		get_template_part( 'template-parts/builder/logos', null, $context );
		return true;
	}

	if ( 'stats' === $layout ) {
		get_template_part( 'template-parts/builder/stats', null, $context );
		return true;
	}

	if ( 'showcase' === $layout ) {
		get_template_part( 'template-parts/builder/showcase', null, $context );
		return true;
	}

	if ( 'external_widget' === $layout ) {
		get_template_part( 'template-parts/builder/external-widget', null, $context );
		return true;
	}

	if ( 'wpforms' === $layout ) {
		get_template_part( 'template-parts/builder/wpforms', null, $context );
		return true;
	}

	if ( 'searchwp_form' === $layout ) {
		get_template_part( 'template-parts/builder/searchwp-form', null, $context );
		return true;
	}

	if ( 'card' === $layout ) {
		get_template_part( 'template-parts/builder/card', null, $context );
		return true;
	}

	if ( 'two_column_split' === $layout ) {
		get_template_part( 'template-parts/builder/two-column-split', null, $context );
		return true;
	}

	if ( 'reusable_block' === $layout ) {
		get_template_part( 'template-parts/builder/reusable-block', null, $context );
		return true;
	}

	if ( 'basic_block' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			$markup         = mrn_rbl_render_fields_as_block(
				'mrn_reusable_basic',
				$row,
				array(
					'post_id'               => (int) $post_id,
					'post_name'             => 'page-basic-block',
					'block_name'            => 'Page Basic Block',
					'suppress_anchor'       => true,
					'apply_motion_contract' => true,
				)
			);
			$wrapped_markup = mrn_base_stack_wrap_reusable_builder_markup( $markup, $row, 'mrn_reusable_basic', 'wide', false );
			echo $wrapped_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapped reusable markup is escaped within the helper.
			return true;
		}

		return false;
	}

	if ( 'content_grid' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			$markup         = mrn_rbl_render_fields_as_block(
				'mrn_reusable_grid',
				$row,
				array(
					'post_id'               => (int) $post_id,
					'post_name'             => 'page-content-grid',
					'block_name'            => 'Page Content Grid',
					'suppress_anchor'       => true,
					'apply_motion_contract' => true,
				)
			);
			$wrapped_markup = mrn_base_stack_wrap_reusable_builder_markup(
				$markup,
				$row,
				'mrn_reusable_grid',
				'wide',
				false
			);
			echo $wrapped_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapped reusable markup is escaped within the helper.
			return true;
		}

		return false;
	}

	if ( 'cta_block' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			$markup         = mrn_rbl_render_fields_as_block(
				'mrn_reusable_cta',
				$row,
				array(
					'post_id'               => (int) $post_id,
					'post_name'             => 'page-cta-block',
					'block_name'            => 'Page Specific CTA Block',
					'suppress_anchor'       => true,
					'apply_motion_contract' => true,
				)
			);
			$wrapped_markup = mrn_base_stack_wrap_reusable_builder_markup(
				$markup,
				$row,
				'mrn_reusable_cta',
				'wide',
				false
			);
			echo $wrapped_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapped reusable markup is escaped within the helper.
			return true;
		}

		return false;
	}

	if ( 'faq_block' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			$markup         = mrn_rbl_render_fields_as_block(
				'mrn_reusable_faq',
				$row,
				array(
					'post_id'               => (int) $post_id,
					'post_name'             => 'page-faq-block',
					'block_name'            => 'Page FAQ Block',
					'suppress_anchor'       => true,
					'apply_motion_contract' => true,
				)
			);
			$wrapped_markup = mrn_base_stack_wrap_reusable_builder_markup( $markup, $row, 'mrn_reusable_faq', 'wide', false );
			echo $wrapped_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapped reusable markup is escaped within the helper.
			return true;
		}

		return false;
	}

	return false;
}

/**
 * Map reusable block post types to their page-specific builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_page_specific_layout_map() {
	return array(
		'mrn_reusable_cta'     => 'cta',
		'mrn_reusable_basic'   => 'basic_block',
		'mrn_reusable_list'    => 'content_lists',
		'mrn_reusable_grid'    => 'grid',
		'mrn_reusable_faq'     => 'faq',
		'mrn_reusable_search'  => 'searchwp_form',
		'mrn_reusable_partner' => 'logos',
	);
}

/**
 * Map reusable block post types to their page-specific builder layout keys.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_page_specific_layout_key_map() {
	return array(
		'mrn_reusable_cta'     => 'layout_mrn_cta',
		'mrn_reusable_basic'   => 'layout_mrn_basic_block',
		'mrn_reusable_list'    => 'layout_mrn_content_lists',
		'mrn_reusable_grid'    => 'layout_mrn_grid',
		'mrn_reusable_faq'     => 'layout_mrn_faq',
		'mrn_reusable_search'  => 'layout_mrn_searchwp_form',
		'mrn_reusable_partner' => 'layout_mrn_logos',
	);
}

/**
 * Determine whether a host reusable-block row value should override saved block fields.
 *
 * @param mixed $value Field value.
 * @return bool
 */
function mrn_base_stack_reusable_block_host_value_is_meaningful( $value ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $item ) {
			if ( mrn_base_stack_reusable_block_host_value_is_meaningful( $item ) ) {
				return true;
			}
		}

		return false;
	}

	if ( is_bool( $value ) ) {
		return true === $value;
	}

	if ( is_scalar( $value ) ) {
		return '' !== trim( (string) $value );
	}

	return null !== $value;
}

/**
 * Convert a reusable block post into the matching page builder row contract.
 *
 * Reusable block placement is intended to behave like a saved instance of the
 * page row layout, not like a parallel rendering system.
 *
 * @param WP_Post              $block    Reusable block post.
 * @param array<string, mixed> $host_row The `reusable_block` row placing it.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_reusable_block_builder_row( WP_Post $block, array $host_row = array() ) {
	if ( ! function_exists( 'get_fields' ) ) {
		return array();
	}

	$layout_map = mrn_base_stack_get_page_specific_layout_map();
	$layout     = isset( $layout_map[ $block->post_type ] ) ? (string) $layout_map[ $block->post_type ] : '';

	if ( '' === $layout ) {
		return array();
	}

	$block_fields = get_fields( $block->ID );
	$row          = is_array( $block_fields ) ? $block_fields : array();

	$row['acf_fc_layout']                  = $layout;
	$row['__mrn_reusable_block_id']        = (int) $block->ID;
	$row['__mrn_reusable_block_post_type'] = (string) $block->post_type;

	$override_fields = apply_filters(
		'mrn_base_stack_reusable_block_placement_override_fields',
		array( 'anchor', 'internal_name', 'include_in_faq_jump_nav', 'faq_jump_nav_label' ),
		$block,
		$host_row
	);
	$override_fields = is_array( $override_fields ) ? array_filter( array_map( 'sanitize_key', $override_fields ) ) : array( 'anchor', 'internal_name', 'include_in_faq_jump_nav', 'faq_jump_nav_label' );

	foreach ( $override_fields as $field_name ) {
		if ( ! array_key_exists( $field_name, $host_row ) ) {
			continue;
		}

		if ( in_array( $field_name, array( 'internal_name', 'include_in_faq_jump_nav', 'faq_jump_nav_label' ), true ) ) {
			$row[ $field_name ] = $host_row[ $field_name ];
			continue;
		}

		if ( mrn_base_stack_reusable_block_host_value_is_meaningful( $host_row[ $field_name ] ) ) {
			$row[ $field_name ] = $host_row[ $field_name ];
		}
	}

	return $row;
}

/**
 * Render a reusable block through its matching page builder row layout and return markup.
 *
 * @param WP_Post              $block    Reusable block post.
 * @param array<string, mixed> $host_row The `reusable_block` row placing it.
 * @param int                  $post_id  Host post ID.
 * @param int                  $index    Host row index.
 * @return string
 */
function mrn_base_stack_get_reusable_block_builder_row_markup( WP_Post $block, array $host_row, $post_id, $index ) {
	$row = mrn_base_stack_get_reusable_block_builder_row( $block, $host_row );

	if ( empty( $row['acf_fc_layout'] ) ) {
		return '';
	}

	$row['__mrn_builder_post_id']    = (int) $post_id;
	$row['__mrn_builder_field_name'] = isset( $host_row['__mrn_builder_field_name'] ) ? (string) $host_row['__mrn_builder_field_name'] : '';
	$row['__mrn_builder_row_index']  = (int) $index;

	ob_start();
	$rendered = mrn_base_stack_render_builder_row( $row, $post_id, $index );
	$markup   = (string) ob_get_clean();

	return $rendered && '' !== trim( $markup ) ? $markup : '';
}

/**
 * Render a reusable block through its matching page builder row layout.
 *
 * @param WP_Post              $block   Reusable block post.
 * @param array<string, mixed> $host_row The `reusable_block` row placing it.
 * @param int                  $post_id Host post ID.
 * @param int                  $index   Host row index.
 * @return bool
 */
function mrn_base_stack_render_reusable_block_as_builder_row( WP_Post $block, array $host_row, $post_id, $index ) {
	$markup = mrn_base_stack_get_reusable_block_builder_row_markup( $block, $host_row, $post_id, $index );

	if ( '' === $markup ) {
		return false;
	}

	echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Row markup is escaped by the matching builder template.
	return true;
}

/**
 * Get builder fields that may contain FAQ rows for page jump navigation.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_faq_jump_nav_builder_field_names() {
	return array(
		'page_content_rows',
		'page_after_content_rows',
		'page_sidebar_rows',
	);
}

/**
 * Resolve a reusable block row's selected post.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return WP_Post|null
 */
function mrn_base_stack_get_reusable_block_post_from_row( array $row ) {
	$block = $row['block'] ?? null;

	if ( $block instanceof WP_Post ) {
		return $block;
	}

	if ( is_numeric( $block ) ) {
		$post = get_post( (int) $block );
		return $post instanceof WP_Post ? $post : null;
	}

	if ( function_exists( 'mrn_rbl_get_block_post' ) ) {
		$post = mrn_rbl_get_block_post( $block );
		return $post instanceof WP_Post ? $post : null;
	}

	return null;
}

/**
 * Build the FAQ jump-nav anchor from a row.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return string
 */
function mrn_base_stack_get_faq_jump_nav_anchor_from_row( array $row ) {
	if ( empty( $row['include_in_faq_jump_nav'] ) ) {
		return '';
	}

	$label  = isset( $row['faq_jump_nav_label'] ) ? trim( wp_strip_all_tags( (string) $row['faq_jump_nav_label'] ) ) : '';
	$source = isset( $row['anchor'] ) ? (string) $row['anchor'] : '';
	if ( '' !== trim( $source ) && preg_match( '/^\d+$/', trim( $source ) ) ) {
		$source = '';
	}
	if ( '' === trim( $source ) && function_exists( 'mrn_base_stack_get_builder_row_default_anchor' ) ) {
		$source = mrn_base_stack_get_builder_row_default_anchor( $row );
	}
	if ( '' === trim( $source ) && '' !== $label ) {
		$source = $label;
	}

	if ( '' === trim( $source ) ) {
		return '';
	}

	return function_exists( 'mrn_base_stack_normalize_anchor_id' )
		? mrn_base_stack_normalize_anchor_id( $source )
		: sanitize_title( $source );
}

/**
 * Build a FAQ jump-nav entry from a FAQ row.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return array<string, string>
 */
function mrn_base_stack_get_faq_jump_nav_entry_from_row( array $row ) {
	$layout = isset( $row['acf_fc_layout'] ) ? sanitize_key( (string) $row['acf_fc_layout'] ) : '';
	if ( ! in_array( $layout, array( 'faq', 'faq_block' ), true ) ) {
		return array();
	}

	if ( empty( $row['include_in_faq_jump_nav'] ) ) {
		return array();
	}

	$label  = isset( $row['faq_jump_nav_label'] ) ? trim( wp_strip_all_tags( (string) $row['faq_jump_nav_label'] ) ) : '';
	$anchor = mrn_base_stack_get_faq_jump_nav_anchor_from_row( $row );

	if ( '' === $anchor || '' === $label ) {
		return array();
	}

	return array(
		'anchor' => $anchor,
		'label'  => $label,
	);
}

/**
 * Recursively collect FAQ jump-nav entries from builder rows.
 *
 * @param array<int, mixed>                 $rows Builder rows.
 * @param array<int, array<string, string>> $entries Current entries.
 * @return array<int, array<string, string>>
 */
function mrn_base_stack_collect_faq_jump_nav_entries_from_rows( array $rows, array $entries = array() ) {
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$layout = isset( $row['acf_fc_layout'] ) ? sanitize_key( (string) $row['acf_fc_layout'] ) : '';
		if ( in_array( $layout, array( 'faq', 'faq_block' ), true ) ) {
			$entry = mrn_base_stack_get_faq_jump_nav_entry_from_row( $row );
			if ( ! empty( $entry ) ) {
				$entries[] = $entry;
			}
		} elseif ( 'reusable_block' === $layout ) {
			$block = mrn_base_stack_get_reusable_block_post_from_row( $row );
			if ( $block instanceof WP_Post && 'mrn_reusable_faq' === $block->post_type ) {
				$faq_row = mrn_base_stack_get_reusable_block_builder_row( $block, $row );
				foreach ( array( 'anchor', 'internal_name', 'include_in_faq_jump_nav', 'faq_jump_nav_label' ) as $placement_field ) {
					$faq_row[ $placement_field ] = array_key_exists( $placement_field, $row ) ? $row[ $placement_field ] : '';
				}
				$entry = mrn_base_stack_get_faq_jump_nav_entry_from_row( $faq_row );
				if ( ! empty( $entry ) ) {
					$entries[] = $entry;
				}
			}
		}

		foreach ( $row as $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			$child_rows = array();
			foreach ( $value as $child ) {
				if ( is_array( $child ) && isset( $child['acf_fc_layout'] ) ) {
					$child_rows[] = $child;
				}
			}

			if ( ! empty( $child_rows ) ) {
				$entries = mrn_base_stack_collect_faq_jump_nav_entries_from_rows( $child_rows, $entries );
			}
		}
	}

	return $entries;
}

/**
 * Get explicit FAQ jump-nav entries for a page.
 *
 * @param int|null $post_id Current post ID.
 * @return array<int, array<string, string>>
 */
function mrn_base_stack_get_faq_jump_nav_entries( $post_id = null ) {
	if ( ! function_exists( 'get_field' ) ) {
		return array();
	}

	$post_id = $post_id ? (int) $post_id : get_the_ID();
	if ( $post_id < 1 ) {
		return array();
	}

	static $cache = array();
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$entries = array();
	foreach ( mrn_base_stack_get_faq_jump_nav_builder_field_names() as $field_name ) {
		$rows = get_field( $field_name, $post_id );
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			continue;
		}

		$entries = mrn_base_stack_collect_faq_jump_nav_entries_from_rows( $rows, $entries );
	}

	$unique = array();
	$seen   = array();
	foreach ( $entries as $entry ) {
		$anchor = isset( $entry['anchor'] ) ? (string) $entry['anchor'] : '';
		if ( '' === $anchor || isset( $seen[ $anchor ] ) ) {
			continue;
		}

		$seen[ $anchor ] = true;
		$unique[]        = $entry;
	}

	$cache[ $post_id ] = $unique;
	return $unique;
}

/**
 * Normalize block field data for use in AJAX responses.
 *
 * @param mixed $value Field value.
 * @return mixed
 */
function mrn_base_stack_normalize_page_specific_payload_value( $value ) {
	if ( $value instanceof WP_Post ) {
		return (int) $value->ID;
	}

	if ( is_array( $value ) ) {
		if ( isset( $value['ID'] ) && is_numeric( $value['ID'] ) && ( isset( $value['url'] ) || isset( $value['filename'] ) || isset( $value['sizes'] ) ) ) {
			return (int) $value['ID'];
		}

		foreach ( $value as $key => $item ) {
			$value[ $key ] = mrn_base_stack_normalize_page_specific_payload_value( $item );
		}
	}

	return $value;
}

/**
 * Build the conversion payload for a reusable block.
 *
 * @param int $block_id Reusable block post ID.
 * @return array<string, mixed>|WP_Error
 */
function mrn_base_stack_get_page_specific_payload_for_block( $block_id ) {
	if ( ! function_exists( 'get_fields' ) ) {
		return new WP_Error( 'acf_missing', 'ACF is required to convert reusable blocks.' );
	}

	$block = get_post( $block_id );
	if ( ! ( $block instanceof WP_Post ) ) {
		return new WP_Error( 'invalid_block', 'The selected reusable block could not be found.' );
	}

	$layout_map     = mrn_base_stack_get_page_specific_layout_map();
	$layout_key_map = mrn_base_stack_get_page_specific_layout_key_map();
	$target_layout  = $layout_map[ $block->post_type ] ?? '';
	$target_key     = $layout_key_map[ $block->post_type ] ?? '';

	if ( '' === $target_layout || '' === $target_key ) {
		return new WP_Error( 'unsupported_block_type', 'This reusable block type does not have a page-specific version yet.' );
	}

	$block_fields = get_fields( $block->ID );
	if ( ! is_array( $block_fields ) || empty( $block_fields ) ) {
		return new WP_Error( 'empty_block', 'The selected reusable block does not have field data to copy yet.' );
	}

	return array(
		'layout'     => $target_layout,
		'layoutKey'  => $target_key,
		'fields'     => mrn_base_stack_normalize_page_specific_payload_value( $block_fields ),
		'blockId'    => (int) $block->ID,
		'blockTitle' => get_the_title( $block ),
	);
}

/**
 * AJAX: prepare a reusable block for page-specific conversion.
 *
 * @return void
 */
function mrn_base_stack_ajax_prepare_page_specific_block() {
	check_ajax_referer( 'mrn-base-stack-convert-reusable-block', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
		wp_send_json_error(
			array(
				'message' => 'You do not have permission to convert reusable blocks.',
			),
			403
		);
	}

	$block_id = isset( $_POST['block_id'] ) ? absint( wp_unslash( $_POST['block_id'] ) ) : 0;
	if ( $block_id < 1 ) {
		wp_send_json_error(
			array(
				'message' => 'Choose a reusable block before converting it.',
			),
			400
		);
	}

	$payload = mrn_base_stack_get_page_specific_payload_for_block( $block_id );
	if ( is_wp_error( $payload ) ) {
		wp_send_json_error(
			array(
				'message' => $payload->get_error_message(),
			),
			400
		);
	}

	wp_send_json_success( $payload );
}
add_action( 'wp_ajax_mrn_base_stack_prepare_page_specific_block', 'mrn_base_stack_ajax_prepare_page_specific_block' );

/**
 * Keep draft reusable blocks out of the page/post builder picker.
 *
 * @param array<string, mixed> $args WP_Query args for the post object field.
 * @return array<string, mixed>
 */
function mrn_base_stack_filter_reusable_block_picker_query( $args ) {
	$args['post_status'] = array( 'publish' );

	return $args;
}
add_filter( 'acf/fields/post_object/query/key=field_mrn_reusable_block_post', 'mrn_base_stack_filter_reusable_block_picker_query' );
add_filter( 'acf/fields/post_object/query/key=field_mrn_nested_reusable_block_post', 'mrn_base_stack_filter_reusable_block_picker_query' );

/**
 * Read a builder sub-field value with legacy fallback names.
 *
 * @param string                   $primary Primary sub field name.
 * @param array<int, string>       $fallbacks Legacy fallback names.
 * @param array<string,mixed>|null $row_values Optional raw row values for fast lookups.
 * @return string
 */
function mrn_base_stack_get_builder_sub_field_value( $primary, array $fallbacks = array(), $row_values = null ) {
	$names = array_merge( array( $primary ), $fallbacks );

	if ( is_array( $row_values ) ) {
		foreach ( $names as $name ) {
			if ( ! is_string( $name ) || '' === $name || ! array_key_exists( $name, $row_values ) ) {
				continue;
			}

			$value = trim( (string) $row_values[ $name ] );

			if ( '' !== $value ) {
				return $value;
			}
		}
	}

	$value = trim( (string) get_sub_field( $primary ) );

	if ( '' !== $value ) {
		return $value;
	}

	foreach ( $fallbacks as $fallback ) {
		$value = trim( (string) get_sub_field( $fallback ) );

		if ( '' !== $value ) {
			return $value;
		}
	}

	return '';
}

/**
 * Improve flexible content row titles in the builder using ACF's native layout title filter.
 *
 * @param string               $title  Current layout title HTML.
 * @param array<string, mixed> $field  Flexible content field settings.
 * @param array<string, mixed> $layout Current layout settings.
 * @param int|string           $i      Row index.
 * @return string
 */
function mrn_base_stack_filter_builder_layout_title( $title, $field, $layout, $i ) {
	unset( $field, $i );

	if ( ! is_array( $layout ) ) {
		return $title;
	}

	if ( ! function_exists( 'get_sub_field' ) ) {
		return $title;
	}

	$layout_name             = isset( $layout['name'] ) ? (string) $layout['name'] : '';
	$row_values              = function_exists( 'get_row' ) ? get_row( true ) : array();
	$row_values              = is_array( $row_values ) ? $row_values : array();
	$internal                = mrn_base_stack_get_builder_sub_field_value( 'internal_name', array(), $row_values );
	$label                   = mrn_base_stack_get_builder_sub_field_value( 'label', array(), $row_values );
	$heading                 = mrn_base_stack_get_builder_sub_field_value( 'heading', array(), $row_values );
	$title_text              = '' !== $heading ? $heading : $label;
	static $post_title_cache = array();

	$is_layout_title_ajax = false;
	if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request context.
		$ajax_action          = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request context.
		$is_layout_title_ajax = ( 'acf/fields/flexible_content/layout_title' === $ajax_action );
	}

	if ( '' !== $internal ) {
		return esc_html( wp_strip_all_tags( $internal ) );
	}

	/*
	 * Fast path: this AJAX action can fire repeatedly on heavy builder screens.
	 * Keep labels simple and avoid expensive per-row lookups so save/publish stays responsive.
	 */
	if ( $is_layout_title_ajax ) {
		if ( '' !== $title_text ) {
			$heading_prefixes = array(
				'basic'            => 'Basic',
				'cta'              => 'Page Specific CTA',
				'cta_block'        => 'Page Specific CTA',
				'grid'             => 'Grid',
				'faq'              => 'FAQs/Accordion',
				'faq_jump_nav'     => 'FAQ Jump Nav',
				'slider'           => 'Slider',
				'tabbed_layout'    => 'Tabbed Layout',
				'logos'            => 'Logos',
				'stats'            => 'Stats',
				'showcase'         => 'Showcase',
				'image_content'    => 'Image',
				'two_column_split' => 'Two Column Split',
				'video'            => 'Video',
				'body_text'        => 'Text',
				'content_lists'    => 'Content',
				'wpforms'          => 'WPForms',
				'searchwp_form'    => 'SearchWP Form',
				'card'             => 'Card',
			);
			$prefix           = isset( $heading_prefixes[ $layout_name ] ) ? $heading_prefixes[ $layout_name ] : '';

			if ( '' !== $prefix ) {
				return $prefix . ': ' . esc_html( wp_strip_all_tags( $title_text ) );
			}

			return esc_html( wp_strip_all_tags( $title_text ) );
		}

		return $title;
	}

	if ( 'reusable_block' === $layout_name ) {
		$block    = array_key_exists( 'block', $row_values ) ? $row_values['block'] : get_sub_field( 'block' );
		$block_id = 0;

		if ( $block instanceof WP_Post ) {
			$block_id = (int) $block->ID;
		} elseif ( is_numeric( $block ) ) {
			$block_id = (int) $block;
		}

		if ( $block_id > 0 && ! array_key_exists( $block_id, $post_title_cache ) ) {
			$post_title_cache[ $block_id ] = trim( (string) get_the_title( $block_id ) );
		}

		$block_title = ( $block_id > 0 && array_key_exists( $block_id, $post_title_cache ) ) ? $post_title_cache[ $block_id ] : '';

		if ( '' === $block_title ) {
			return $title;
		}

		return 'Reusable Block: ' . esc_html( $block_title );
	}

	if ( 'basic' === $layout_name ) {
		if ( '' === $title_text ) {
			return $title;
		}

		return 'Basic: ' . esc_html( wp_strip_all_tags( $title_text ) );
	}

	if ( in_array( $layout_name, array( 'cta', 'cta_block' ), true ) ) {
		if ( '' === $title_text ) {
			return $title;
		}

		return 'Page Specific CTA: ' . esc_html( wp_strip_all_tags( $title_text ) );
	}

	if ( 'grid' === $layout_name ) {
		if ( '' === $title_text ) {
			return $title;
		}

		return 'Grid: ' . esc_html( wp_strip_all_tags( $title_text ) );
	}

	if ( 'faq' === $layout_name ) {
		if ( '' === $title_text ) {
			return $title;
		}

		return 'FAQs/Accordion: ' . esc_html( wp_strip_all_tags( $title_text ) );
	}

	if ( 'faq_jump_nav' === $layout_name ) {
		if ( '' === $title_text ) {
			return 'FAQ Jump Nav';
		}

		return 'FAQ Jump Nav: ' . esc_html( wp_strip_all_tags( $title_text ) );
	}

	if ( 'slider' === $layout_name ) {
		if ( '' === $title_text ) {
			return $title;
		}

		return 'Slider: ' . esc_html( wp_strip_all_tags( $title_text ) );
	}

	if ( 'tabbed_layout' === $layout_name ) {
		if ( '' !== $title_text ) {
			return 'Tabbed Layout: ' . esc_html( wp_strip_all_tags( $title_text ) );
		}

		$tabs = array_key_exists( 'tabs', $row_values ) ? $row_values['tabs'] : get_sub_field( 'tabs' );
		if ( is_array( $tabs ) ) {
			foreach ( $tabs as $tab ) {
				if ( ! is_array( $tab ) ) {
					continue;
				}

				$tab_label = isset( $tab['tab_label'] ) ? trim( (string) $tab['tab_label'] ) : '';
				if ( '' !== $tab_label ) {
					return 'Tabbed Layout: ' . esc_html( wp_strip_all_tags( $tab_label ) );
				}
			}
		}

		return 'Tabbed Layout';
	}

	if ( 'logos' === $layout_name ) {
		if ( '' === $title_text ) {
			return $title;
		}

		return '<div class="mrn-shell-section mrn-shell-section--logos">Logos: ' . esc_html( wp_strip_all_tags( $title_text ) ) . '</div>';
	}

	if ( 'stats' === $layout_name ) {
		if ( '' === $title_text ) {
			return $title;
		}

		return '<div class="mrn-shell-section mrn-shell-section--stats">Stats: ' . esc_html( wp_strip_all_tags( $title_text ) ) . '</div>';
	}

	if ( 'showcase' === $layout_name ) {
		if ( '' === $title_text ) {
			return $title;
		}

		return '<div class="mrn-shell-section mrn-shell-section--showcase">Showcase: ' . esc_html( wp_strip_all_tags( $title_text ) ) . '</div>';
	}

	if ( 'image_content' === $layout_name ) {
		if ( '' === $title_text ) {
			return $title;
		}

		return 'Image: ' . esc_html( wp_strip_all_tags( $title_text ) );
	}

	if ( 'two_column_split' === $layout_name ) {
		if ( '' === $title_text ) {
			return $title;
		}

		return 'Two Column Split: ' . esc_html( wp_strip_all_tags( $title_text ) );
	}

	if ( 'video' === $layout_name ) {
		if ( '' === $title_text ) {
			return $title;
		}

		return 'Video: ' . esc_html( wp_strip_all_tags( $title_text ) );
	}

	if ( 'body_text' === $layout_name ) {
		if ( '' === $title_text ) {
			return $title;
		}

		return 'Text: ' . esc_html( wp_strip_all_tags( $title_text ) );
	}

	if ( 'content_lists' === $layout_name ) {
		if ( '' !== $title_text ) {
			return 'Content: ' . esc_html( wp_strip_all_tags( $title_text ) );
		}

		$post_type = sanitize_key( mrn_base_stack_get_builder_sub_field_value( 'list_post_type', array(), $row_values ) );
		$choices   = function_exists( 'mrn_base_stack_get_content_list_post_type_choices' ) ? mrn_base_stack_get_content_list_post_type_choices() : array();

		if ( isset( $choices[ $post_type ] ) ) {
			return 'Content: ' . esc_html( $choices[ $post_type ] );
		}

		return 'Content';
	}

	if ( 'wpforms' === $layout_name ) {
		if ( '' !== $title_text ) {
			return 'WPForms: ' . esc_html( wp_strip_all_tags( $title_text ) );
		}

		$form    = array_key_exists( 'form', $row_values ) ? $row_values['form'] : get_sub_field( 'form' );
		$form_id = 0;

		if ( $form instanceof WP_Post ) {
			$form_id = (int) $form->ID;
		} elseif ( is_numeric( $form ) ) {
			$form_id = (int) $form;
		}

		if ( $form_id > 0 && ! array_key_exists( $form_id, $post_title_cache ) ) {
			$post_title_cache[ $form_id ] = trim( (string) get_the_title( $form_id ) );
		}

		$form_title = ( $form_id > 0 && array_key_exists( $form_id, $post_title_cache ) ) ? $post_title_cache[ $form_id ] : '';

		if ( '' !== $form_title ) {
			return 'WPForms: ' . esc_html( $form_title );
		}

		return 'WPForms';
	}

	if ( 'searchwp_form' === $layout_name ) {
		if ( '' !== $title_text ) {
			return 'SearchWP Form: ' . esc_html( wp_strip_all_tags( $title_text ) );
		}

		$form_id    = array_key_exists( 'searchwp_form_id', $row_values ) ? $row_values['searchwp_form_id'] : get_sub_field( 'searchwp_form_id' );
		$form_title = function_exists( 'mrn_base_stack_get_searchwp_form_title' ) ? mrn_base_stack_get_searchwp_form_title( $form_id ) : '';
		$form_title = is_string( $form_title ) ? trim( $form_title ) : '';

		if ( '' !== $form_title ) {
			return 'SearchWP Form: ' . esc_html( $form_title );
		}

		return 'SearchWP Form';
	}

	if ( 'card' === $layout_name ) {
		if ( '' === $title_text ) {
			return $title;
		}

		return '<div class="mrn-shell-section mrn-shell-section--card">Card: ' . esc_html( wp_strip_all_tags( $title_text ) ) . '</div>';
	}

	return $title;
}
add_filter( 'acf/fields/flexible_content/layout_title/name=page_content_rows', 'mrn_base_stack_filter_builder_layout_title', 10, 4 );
add_filter( 'acf/fields/flexible_content/layout_title/name=page_after_content_rows', 'mrn_base_stack_filter_builder_layout_title', 10, 4 );
add_filter( 'acf/fields/flexible_content/layout_title/name=page_hero_rows', 'mrn_base_stack_filter_builder_layout_title', 10, 4 );
add_filter( 'acf/fields/flexible_content/layout_title/name=page_sidebar_rows', 'mrn_base_stack_filter_builder_layout_title', 10, 4 );
add_filter( 'acf/fields/flexible_content/layout_title/key=field_mrn_page_hero_rows', 'mrn_base_stack_filter_builder_layout_title', 10, 4 );
add_filter( 'acf/fields/flexible_content/layout_title/key=field_mrn_sidebar_rows', 'mrn_base_stack_filter_builder_layout_title', 10, 4 );
add_filter( 'acf/fields/flexible_content/layout_title/key=field_mrn_page_template_sidebar_rows', 'mrn_base_stack_filter_builder_layout_title', 10, 4 );
add_filter( 'acf/fields/flexible_content/layout_title/key=field_mrn_tabbed_layout_panel_rows', 'mrn_base_stack_filter_builder_layout_title', 10, 4 );

/**
 * Render a flexible-content builder field for posts and pages.
 *
 * @param string   $field_name ACF field name.
 * @param int|null $post_id Post ID to render. Defaults to current post.
 * @param string   $wrapper_class Wrapper class for the builder markup.
 * @return bool True when at least one builder row was rendered.
 */
function mrn_base_stack_render_builder_field( $field_name, $post_id = null, $wrapper_class = 'mrn-content-builder' ) {
	if ( ! function_exists( 'get_field' ) ) {
		return false;
	}

	$post_id = $post_id ? (int) $post_id : get_the_ID();
	if ( ! $post_id ) {
		return false;
	}

	$rows = get_field( $field_name, $post_id );
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return false;
	}

	$rendered_rows = array();

	foreach ( $rows as $index => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$row['__mrn_builder_post_id']    = (int) $post_id;
		$row['__mrn_builder_field_name'] = sanitize_key( (string) $field_name );
		$row['__mrn_builder_row_index']  = (int) $index;

		ob_start();
		mrn_base_stack_render_builder_row( $row, $post_id, $index );
		$row_markup = trim( (string) ob_get_clean() );

		if ( '' === $row_markup ) {
			continue;
		}

		$rendered_rows[] = $row_markup;
	}

	if ( empty( $rendered_rows ) ) {
		return false;
	}

	echo '<div class="' . esc_attr( trim( $wrapper_class ) ) . '">';
	echo implode( '', $rendered_rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</div>';

	return true;
}

/**
 * Render the ACF content builder rows for posts and pages.
 *
 * @param int|null $post_id Post ID to render. Defaults to current post.
 * @return bool True when at least one builder row was rendered.
 */
function mrn_base_stack_render_content_builder( $post_id = null ) {
	return mrn_base_stack_render_builder_field( 'page_content_rows', $post_id, 'mrn-content-builder' );
}

/**
 * Render the ACF after-content builder rows for posts and pages.
 *
 * @param int|null $post_id Post ID to render. Defaults to current post.
 * @return bool True when at least one builder row was rendered.
 */
function mrn_base_stack_render_after_content_builder( $post_id = null ) {
	return mrn_base_stack_render_builder_field( 'page_after_content_rows', $post_id, 'mrn-content-builder mrn-content-builder--after-content' );
}

/**
 * Get the rendered builder markup for a post without echoing it.
 *
 * @param string   $field_name ACF field name.
 * @param int|null $post_id Post ID to render. Defaults to current post.
 * @param string   $wrapper_class Wrapper class for the builder markup.
 * @return string Rendered builder markup, or an empty string when unavailable.
 */
function mrn_base_stack_get_builder_markup( $field_name, $post_id = null, $wrapper_class = 'mrn-content-builder' ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	if ( ! $post_id ) {
		return '';
	}

	ob_start();
	$rendered = mrn_base_stack_render_builder_field( $field_name, $post_id, $wrapper_class );
	$markup   = ob_get_clean();

	if ( ! $rendered || ! is_string( $markup ) ) {
		return '';
	}

	return trim( $markup );
}

/**
 * Get the rendered main content builder markup for a post without echoing it.
 *
 * @param int|null $post_id Post ID to render. Defaults to current post.
 * @return string Rendered builder markup, or an empty string when unavailable.
 */
function mrn_base_stack_get_content_builder_markup( $post_id = null ) {
	return mrn_base_stack_get_builder_markup( 'page_content_rows', $post_id, 'mrn-content-builder' );
}

/**
 * Get the rendered after-content builder markup for a post without echoing it.
 *
 * @param int|null $post_id Post ID to render. Defaults to current post.
 * @return string Rendered builder markup, or an empty string when unavailable.
 */
function mrn_base_stack_get_after_content_builder_markup( $post_id = null ) {
	return mrn_base_stack_get_builder_markup( 'page_after_content_rows', $post_id, 'mrn-content-builder mrn-content-builder--after-content' );
}

/**
 * Build markup for SmartCrawl content analysis when the builder is in use.
 *
 * SmartCrawl's recommended "Content" mode only inspects `the_content()` output.
 * Our starter theme renders the ACF builder directly, so we provide equivalent
 * singular markup here when builder rows exist.
 *
 * @param int $post_id Post ID being analyzed.
 * @return string Markup string for SmartCrawl, or an empty string to fall back.
 */
function mrn_base_stack_get_smartcrawl_markup( $post_id ) {
	$post = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) ) {
		return '';
	}

	$builder_markup    = mrn_base_stack_get_content_builder_markup( $post->ID );
	$gallery_markup    = function_exists( 'mrn_base_stack_get_gallery_smartcrawl_markup' ) ? mrn_base_stack_get_gallery_smartcrawl_markup( $post->ID ) : '';
	$case_study_markup = function_exists( 'mrn_base_stack_get_case_study_smartcrawl_markup' ) ? mrn_base_stack_get_case_study_smartcrawl_markup( $post->ID ) : '';
	$after_markup      = mrn_base_stack_get_after_content_builder_markup( $post->ID );

	if ( '' === $builder_markup && '' === $gallery_markup && '' === $case_study_markup && '' === $after_markup ) {
		return '';
	}

	$title_markup = sprintf(
		'<h1 class="entry-title">%s</h1>',
		esc_html( get_the_title( $post ) )
	);

	return trim( $title_markup . "\n" . $builder_markup . "\n" . $gallery_markup . "\n" . $case_study_markup . "\n" . $after_markup );
}

/**
 * Feed builder-rendered markup into SmartCrawl endpoint analysis.
 *
 * @param mixed        $subject Existing subject from earlier filters.
 * @param string|array $keywords Focus keyword(s), unused here.
 * @param bool         $is_primary Whether SmartCrawl is running the primary check set.
 * @return mixed Markup string when builder content exists, otherwise the original subject.
 */
function mrn_base_stack_filter_smartcrawl_subject_endpoint( $subject, $keywords, $is_primary ) {
	unset( $keywords, $is_primary );

	if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
		return $subject;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->base ) {
		return $subject;
	}

	// SmartCrawl reads the current editor context here without a nonce-bearing form submission.
	$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $post_id && isset( $_POST['post_ID'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_id = absint( wp_unslash( $_POST['post_ID'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	if ( ! $post_id ) {
		return $subject;
	}

	$markup = mrn_base_stack_get_smartcrawl_markup( $post_id );

	return '' !== $markup ? $markup : $subject;
}
add_filter( 'wds-checks-subject-endpoint', 'mrn_base_stack_filter_smartcrawl_subject_endpoint', 10, 3 );

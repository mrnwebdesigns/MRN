<?php

/**

 * Builder rendering, conversion, and search integration.

 *

 * @package mrn-base-stack

 */

function mrn_base_stack_render_hero_row( array $row, $post_id, $index ) {
	if ( empty( $row['acf_fc_layout'] ) ) {
		return false;
	}

	$layout  = (string) $row['acf_fc_layout'];
	$context = mrn_base_stack_get_builder_row_context( $row, $post_id, $index );

	if ( 'hero' === $layout ) {
		get_template_part( 'template-parts/builder/hero', null, $context );
		return true;
	}

	if ( 'hero_two_column_split' === $layout ) {
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

		if ( mrn_base_stack_render_hero_row( $row, $post_id, $index ) ) {
			$rendered = true;
		}
	}

	return $rendered;
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

	if ( 'hero' === $layout ) {
		get_template_part( 'template-parts/builder/hero', null, $context );
		return true;
	}

	if ( 'basic' === $layout ) {
		get_template_part( 'template-parts/builder/basic', null, $context );
		return true;
	}

	if ( 'cta' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			echo mrn_rbl_render_fields_as_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'mrn_reusable_cta',
				$row,
				array(
					'post_id'    => (int) $post_id,
					'post_name'  => 'page-cta',
					'block_name' => 'Page CTA',
				)
			);
			return true;
		}

		return false;
	}

	if ( 'grid' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			echo mrn_rbl_render_fields_as_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'mrn_reusable_grid',
				$row,
				array(
					'post_id'    => (int) $post_id,
					'post_name'  => 'page-grid',
					'block_name' => 'Page Grid',
				)
			);
			return true;
		}

		return false;
	}

	if ( 'faq' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			echo mrn_rbl_render_fields_as_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'mrn_reusable_faq',
				$row,
				array(
					'post_id'    => (int) $post_id,
					'post_name'  => 'page-faq-accordion',
					'block_name' => 'Page FAQs/Accordion',
				)
			);
			return true;
		}

		return false;
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
			echo mrn_rbl_render_fields_as_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'mrn_reusable_basic',
				$row,
				array(
					'post_id'    => (int) $post_id,
					'post_name'  => 'page-basic-block',
					'block_name' => 'Page Basic Block',
				)
			);
			return true;
		}

		return false;
	}

	if ( 'content_grid' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			echo mrn_rbl_render_fields_as_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'mrn_reusable_grid',
				$row,
				array(
					'post_id'    => (int) $post_id,
					'post_name'  => 'page-content-grid',
					'block_name' => 'Page Content Grid',
				)
			);
			return true;
		}

		return false;
	}

	if ( 'cta_block' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			echo mrn_rbl_render_fields_as_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'mrn_reusable_cta',
				$row,
				array(
					'post_id'    => (int) $post_id,
					'post_name'  => 'page-cta-block',
					'block_name' => 'Page CTA Block',
				)
			);
			return true;
		}

		return false;
	}

	if ( 'faq_block' === $layout ) {
		if ( function_exists( 'mrn_rbl_render_fields_as_block' ) ) {
			echo mrn_rbl_render_fields_as_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'mrn_reusable_faq',
				$row,
				array(
					'post_id'    => (int) $post_id,
					'post_name'  => 'page-faq-block',
					'block_name' => 'Page FAQ Block',
				)
			);
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
		'mrn_reusable_cta'   => 'cta_block',
		'mrn_reusable_basic' => 'basic_block',
		'mrn_reusable_grid'  => 'content_grid',
		'mrn_reusable_faq'   => 'faq_block',
	);
}

/**
 * Map reusable block post types to their page-specific builder layout keys.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_page_specific_layout_key_map() {
	return array(
		'mrn_reusable_cta'   => 'layout_mrn_cta_block',
		'mrn_reusable_basic' => 'layout_mrn_basic_block',
		'mrn_reusable_grid'  => 'layout_mrn_content_grid',
		'mrn_reusable_faq'   => 'layout_mrn_faq_block',
	);
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

	$layout_map = mrn_base_stack_get_page_specific_layout_map();
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

	$layout_name = isset( $layout['name'] ) ? (string) $layout['name'] : '';

	if ( 'reusable_block' === $layout_name ) {
		$block = get_sub_field( 'block' );
		if ( $block instanceof WP_Post ) {
			$block_title = get_the_title( $block );
		} elseif ( is_numeric( $block ) ) {
			$block_title = get_the_title( (int) $block );
		} else {
			$block_title = '';
		}

		$block_title = is_string( $block_title ) ? trim( $block_title ) : '';

		if ( '' === $block_title ) {
			return $title;
		}

		return 'Reusable Block: ' . esc_html( $block_title );
	}

	if ( 'basic' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'text_field' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Basic: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'cta' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'text_field' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'CTA: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'grid' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'text_field' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Grid: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'faq' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'heading' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'FAQs/Accordion: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'slider' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'text_field' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Slider: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'logos' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'heading' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Logos: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'stats' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'heading' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Stats: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'showcase' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'heading' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Showcase: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'image_content' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'text_field' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Image: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'video' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'text_field' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Video: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'body_text' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'title_field' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Text: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	if ( 'card' === $layout_name ) {
		$heading = trim( (string) get_sub_field( 'text_field' ) );

		if ( '' === $heading ) {
			return $title;
		}

		return 'Card: ' . esc_html( wp_strip_all_tags( $heading ) );
	}

	return $title;
}
add_filter( 'acf/fields/flexible_content/layout_title/name=page_content_rows', 'mrn_base_stack_filter_builder_layout_title', 10, 4 );

/**
 * Render the ACF content builder rows for posts and pages.
 *
 * @param int|null $post_id Post ID to render. Defaults to current post.
 * @return bool True when at least one builder row was rendered.
 */
function mrn_base_stack_render_content_builder( $post_id = null ) {
	if ( ! function_exists( 'get_field' ) ) {
		return false;
	}

	$post_id = $post_id ? (int) $post_id : get_the_ID();
	if ( ! $post_id ) {
		return false;
	}

	$rows = get_field( 'page_content_rows', $post_id );
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return false;
	}

	echo '<div class="mrn-content-builder">';

	foreach ( $rows as $index => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		if ( mrn_base_stack_render_builder_row( $row, $post_id, $index ) ) {
			continue;
		}
	}

	echo '</div>';

	return true;
}

/**
 * Get the rendered builder markup for a post without echoing it.
 *
 * @param int|null $post_id Post ID to render. Defaults to current post.
 * @return string Rendered builder markup, or an empty string when unavailable.
 */
function mrn_base_stack_get_content_builder_markup( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	if ( ! $post_id ) {
		return '';
	}

	ob_start();
	$rendered = mrn_base_stack_render_content_builder( $post_id );
	$markup   = ob_get_clean();

	if ( ! $rendered || ! is_string( $markup ) ) {
		return '';
	}

	return trim( $markup );
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

	$builder_markup = mrn_base_stack_get_content_builder_markup( $post->ID );
	if ( '' === $builder_markup ) {
		return '';
	}

	$title_markup = sprintf(
		'<h1 class="entry-title">%s</h1>',
		esc_html( get_the_title( $post ) )
	);

	return $title_markup . "\n" . $builder_markup;
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

	$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
	if ( ! $post_id && isset( $_POST['post_ID'] ) ) {
		$post_id = absint( wp_unslash( $_POST['post_ID'] ) );
	}

	if ( ! $post_id ) {
		return $subject;
	}

	$markup = mrn_base_stack_get_smartcrawl_markup( $post_id );

	return '' !== $markup ? $markup : $subject;
}
add_filter( 'wds-checks-subject-endpoint', 'mrn_base_stack_filter_smartcrawl_subject_endpoint', 10, 3 );

<?php
/**
 * Native presentation contract qualification on an explicitly selected local site.
 *
 * MRN_STACK_TEST_HOME must equal home_url(). Optional MRN_PRESENTATION_PROOF_PATH
 * writes a static preview outside the web root; it never installs candidate code.
 *
 * @package mrn-base-stack
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'An explicitly selected administrator-owned local fixture is required.' );
}

/**
 * Assert a native contract.
 *
 * @param bool   $condition Result.
 * @param string $message Contract label.
 * @return void
 * @throws RuntimeException If the contract fails.
 */
function mrn_presentation_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	$GLOBALS['mrn_presentation_checks'][] = $message;
}

$fixture_id = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'draft',
		'post_title'  => 'Native Stack presentation qualification',
		'meta_input'  => array( '_mrn_presentation_fixture' => 1 ),
	),
	true
);
mrn_presentation_expect( ! is_wp_error( $fixture_id ) && $fixture_id > 0, 'Owned draft fixture created' );
try {
	$body_field = acf_get_field( 'field_mrn_page_content_rows' );
	$layouts    = array_column( $body_field['layouts'], null, 'name' );
	foreach ( array(
		'image_content' => 'image_presentation',
		'cta'           => 'rotating_phrases_one',
		'showcase'      => 'showcase_presentation',
		'card'          => 'card_presentation',
		'content_lists' => 'record_presentation',
		'video'         => 'video_presentation',
	) as $layout => $name ) {
		$matches = array_values(
			array_filter(
				$layouts[ $layout ]['sub_fields'],
				static function ( $field ) use ( $name ) {
					return ( $field['name'] ?? '' ) === $name;
				}
			)
		);
		mrn_presentation_expect( 1 === count( $matches ), $layout . ' registers one authoritative ' . $name . ' field' );
	}
	$hero_field   = acf_get_field( 'field_mrn_page_hero_rows' );
	$hero_layout  = array_column( $hero_field['layouts'], null, 'name' )['basic_block'];
	$hero_phrases = array_values(
		array_filter(
			$hero_layout['sub_fields'],
			static function ( $field ) {
				return 'rotating_phrases_two' === ( $field['name'] ?? '' );
			}
		)
	);
	mrn_presentation_expect( 1 === count( $hero_phrases ) && 30 === $hero_phrases[0]['max'], 'Hero exposes one 30-phrase collection through its cloned native key' );

	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'numberposts'    => 10,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	mrn_presentation_expect( count( $attachments ) >= 5, 'Existing local images resolved without importing media' );
	$media = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'video/mp4',
			'numberposts'    => 1,
			'fields'         => 'ids',
		)
	);
	mrn_presentation_expect( 1 === count( $media ), 'Existing local MP4 resolved' );
	$people   = get_posts(
		array(
			'post_type'   => 'mrn_team',
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
			'orderby'     => 'menu_order ID',
			'order'       => 'ASC',
		)
	);
	$projects = get_posts(
		array(
			'post_type'   => 'mrn_before_after',
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
			'orderby'     => 'menu_order ID',
			'order'       => 'ASC',
		)
	);
	mrn_presentation_expect( 9 === count( $people ), 'All nine existing team records resolved' );
	mrn_presentation_expect( 7 === count( $projects ), 'All seven existing comparison records resolved' );

	$phrases = array();
	foreach ( array( 'clear', 'useful', 'fast', 'focused', 'accessible', 'connected', 'flexible', 'consistent', 'helpful', 'thoughtful', 'ready' ) as $phrase ) {
		$phrases[] = array( 'text' => $phrase );
	}
	$fixture_link = array(
		'link'      => array(
			'url'    => home_url( '/#qualification' ),
			'title'  => 'Review details',
			'target' => '',
		),
		'is_button' => 1,
	);
	$hero         = array(
		array(
			'acf_fc_layout'        => 'basic_block',
			'hero_heading_mode'    => 'custom_title',
			'heading'              => 'Make your website {phrase_two}',
			'rotating_phrases_two' => $phrases,
			'links'                => array( $fixture_link, $fixture_link ),
			'rotation_interval'    => 1500,
		),
	);
	update_field( 'field_mrn_page_hero_rows', $hero, $fixture_id );
	$hero_readback = get_field( 'page_hero_rows', $fixture_id );
	mrn_presentation_expect( 11 === count( $hero_readback[0]['rotating_phrases_two'] ) && 2 === count( $hero_readback[0]['links'] ), 'Hero retains all eleven phrases and both links' );
	$overflow                            = $hero;
	$overflow[0]['rotating_phrases_two'] = array_fill( 0, 31, $phrases[0] );
	$overflow[0]['heading']              = 'Must not persist';
	$before                              = get_post_meta( $fixture_id );
	mrn_presentation_expect( false === update_field( 'field_mrn_page_hero_rows', $overflow, $fixture_id ) && get_post_meta( $fixture_id ) === $before, 'Phrase overflow is refused before changing the Hero heading' );

	$columns = array();
	for ( $index = 0; $index < 5; ++$index ) {
		$columns[] = array( 'images' => array_values( array_merge( array_slice( $attachments, $index, 5 ), array_slice( $attachments, 0, max( 0, 5 - count( array_slice( $attachments, $index, 5 ) ) ) ) ) ) );
	}
	$showcase = array_map(
		static function ( $image ) {
			return array( 'image' => $image );
		},
		array_slice( $attachments, 0, 9 )
	);
	$steps    = array();
	for ( $index = 1; $index <= 4; ++$index ) {
		$child = array(
			'acf_fc_layout' => 'basic_block',
			'content'       => '<p>Editable timeline content for step ' . $index . '.</p>',
			'links'         => array( $fixture_link ),
		);
		foreach ( mrn_base_stack_get_row_spacing_side_selector_definitions() as $spacing ) {
			$child[ $spacing['name'] ] = '0px';
		}
		$steps[] = array(
			'card_label' => 'Step ' . $index,
			'card_rows'  => array( $child ),
		);
	}
	$colors = function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array();
	$color  = '';
	foreach ( array_keys( $colors ) as $color_key ) {
		if ( '' !== (string) $color_key ) {
			$color = (string) $color_key;
			break;
		}
	}
	$rows = array(
		array(
			'acf_fc_layout'        => 'cta',
			'heading'              => 'Keep it {phrase_one} and {phrase_two}',
			'content'              => '<p>Both phrase collections remain editable.</p>',
			'rotating_phrases_one' => $phrases,
			'rotating_phrases_two' => array_reverse( $phrases ),
			'rotation_interval'    => 1500,
			'links'                => array( $fixture_link ),
		),
		array(
			'acf_fc_layout'       => 'image_content',
			'heading'             => 'Rotated gallery',
			'image'               => $attachments[0],
			'image_presentation'  => 'tilted-gallery',
			'gallery_columns'     => $columns,
			'media_height'        => '600px',
			'media_height_mobile' => '340px',
			'image_overlay'       => 1,
		),
		array(
			'acf_fc_layout'       => 'image_content',
			'heading'             => 'Scroll zoom',
			'image'               => $attachments[1],
			'image_presentation'  => 'scroll-zoom',
			'media_height'        => '500px',
			'media_height_mobile' => '340px',
		),
		array(
			'acf_fc_layout'         => 'showcase',
			'heading'               => 'Project strip',
			'showcase_presentation' => 'scroll-strip',
			'showcase_items'        => $showcase,
		),
		array(
			'acf_fc_layout'       => 'card',
			'heading'             => 'Our process',
			'card_presentation'   => 'timeline',
			'timeline_image'      => $attachments[2],
			'timeline_skip_label' => 'Skip process',
			'card_items'          => $steps,
		),
		array(
			'acf_fc_layout'       => 'content_lists',
			'heading'             => 'People',
			'list_post_type'      => 'mrn_team',
			'filter_source'       => 'manual_posts',
			'filter_posts'        => $people,
			'posts_per_page'      => 20,
			'record_presentation' => 'people',
			'link_items'          => 0,
		),
		array(
			'acf_fc_layout'       => 'content_lists',
			'heading'             => 'Before and after',
			'list_post_type'      => 'mrn_before_after',
			'filter_source'       => 'manual_posts',
			'filter_posts'        => $projects,
			'posts_per_page'      => 20,
			'record_presentation' => 'comparisons',
			'link_items'          => 0,
		),
		array(
			'acf_fc_layout'      => 'video',
			'heading'            => 'Video expansion',
			'video_upload'       => $media[0],
			'video_thumbnail'    => $attachments[3],
			'video_presentation' => 'scroll-expand',
			'video_follow_color' => $color,
		),
		array(
			'acf_fc_layout' => 'body_text',
			'heading'       => 'Following section',
			'body_text'     => '<p>This native row demonstrates the selected background transition.</p>',
		),
	);
	foreach ( $rows as &$row ) {
		foreach ( mrn_base_stack_get_row_spacing_side_selector_definitions() as $spacing ) {
			$row[ $spacing['name'] ] = '0px';
		}
	}
	unset( $row );
	update_field( 'field_mrn_page_content_rows', $rows, $fixture_id );
	$saved = get_field( 'page_content_rows', $fixture_id );
	mrn_presentation_expect( str_contains( $saved[4]['card_items'][0]['card_rows'][0]['content'] ?? '', 'step 1' ) && 1 === count( $saved[4]['card_items'][0]['card_rows'][0]['links'] ?? array() ), 'Timeline preserves nested native copy and links' );
	mrn_presentation_expect( count( $saved ) === count( $rows ), 'Every authored presentation row survives native ACF save' );
	mrn_presentation_expect( 11 === count( $saved[0]['rotating_phrases_one'] ) && 11 === count( $saved[0]['rotating_phrases_two'] ), 'CTA retains both complete eleven-phrase collections' );
	mrn_presentation_expect( 5 === count( $saved[1]['gallery_columns'] ) && 5 === count( $saved[1]['gallery_columns'][4]['images'] ), 'Rotated gallery preserves five ordered columns of five images' );
	mrn_presentation_expect( '600px' === $saved[1]['media_height'] && '340px' === $saved[1]['media_height_mobile'], 'Desktop and mobile media heights round trip as content fields' );
	$bad_gallery                                    = $rows;
	$bad_gallery[1]['gallery_columns'][0]['images'] = array_fill( 0, 31, $attachments[0] );
	$bad_gallery[0]['heading']                      = 'Must not persist';
	$before = get_post_meta( $fixture_id );
	mrn_presentation_expect( false === update_field( 'field_mrn_page_content_rows', $bad_gallery, $fixture_id ) && get_post_meta( $fixture_id ) === $before, 'Column gallery overflow refuses the complete tree before sibling changes' );
	mrn_presentation_expect( 9 === count( $saved[3]['showcase_items'] ) && 4 === count( $saved[4]['card_items'] ), 'Nine-image strip and four-step timeline retain all items' );
	$needs = mrn_base_stack_get_front_end_component_needs_for_post( $fixture_id );
	mrn_presentation_expect( ! empty( $needs['presentation'] ), 'Metadata discovery requests presentation assets before wp_head' );
	ob_start();
	mrn_base_stack_render_hero_row( $hero_readback[0], $fixture_id, 0 );
	foreach ( $saved as $index => $row ) {
		mrn_base_stack_render_builder_row( $row, $fixture_id, $index );
	}
	$html = ob_get_clean();
	foreach ( array( 'data-mrn-rotation-group', 'data-mrn-image-stage="tilted-gallery"', 'data-mrn-image-stage="scroll-zoom"', 'data-mrn-scroll-strip', 'data-mrn-timeline', 'mrn-record--people', 'mrn-record--comparisons', 'data-mrn-video-expand' ) as $marker ) {
		mrn_presentation_expect( str_contains( $html, $marker ), 'Native markup includes ' . $marker );
	}
	mrn_presentation_expect( 9 === substr_count( $html, 'mrn-record mrn-record--people' ) && 7 === substr_count( $html, 'mrn-record mrn-record--comparisons' ), 'All nine people and seven projects render from their existing records' );
	mrn_presentation_expect( 7 === substr_count( $html, '--mrn-comparison-surface:' ) && '#e6effa' === mrn_base_stack_get_record_color( 'rgb(230,239,250)' ) && '' === mrn_base_stack_get_record_color( 'rgb(0,0,0);background:url(bad)' ), 'Comparison colors preserve legacy RGB values and refuse injected declarations' );
	$existing_hover_count = substr_count( $html, 'data-mrn-hover-video' );
	// Qualify the optional video contract without changing a real member record.
	set_post_thumbnail( $fixture_id, $attachments[0] );
	$hover_adapter = static function ( $data, $post, $profile ) use ( $fixture_id, $media ) {
		if ( $fixture_id === $post->ID && 'people' === $profile ) {
			$data['hover_video'] = wp_get_attachment_url( $media[0] );
			$data['designation'] = 'Owned hover-video fixture';
		}
		return $data;
	};
	add_filter( 'mrn_base_stack_content_list_item_presentation', $hover_adapter, 10, 3 );
	try {
		$hover_markup = mrn_base_stack_render_record_presentation( get_post( $fixture_id ), array( 'record_presentation' => 'people' ) );
	} finally {
		remove_filter( 'mrn_base_stack_content_list_item_presentation', $hover_adapter, 10 );
	}
	mrn_presentation_expect( str_contains( $hover_markup, 'data-mrn-hover-video' ) && str_contains( $hover_markup, 'preload="none"' ) && str_contains( $hover_markup, 'Owned hover-video fixture' ), 'People item-data contract supports designation and deferred hover video' );
	$html .= '<section class="mrn-record-list--people"><h2>Hover video preview</h2><ul class="mrn-content-list-row__items">' . $hover_markup . '</ul></section>';
	mrn_presentation_expect( str_contains( $html, 'Skip process' ) && 4 === substr_count( $html, '<details open>' ), 'Timeline has a skip link and complete no-JS content' );
	mrn_presentation_expect( 3 === substr_count( $html, 'data-mrn-phrases=' ), 'Hero and CTA each render their authored phrase slots' );
	mrn_presentation_expect( 4 === substr_count( $html, 'Editable timeline content for step' ) && str_contains( $html, 'data-video-controls="true"' ) && str_contains( $html, 'data-video-poster=' ), 'Timeline copy and inline video controls/poster survive native rendering' );
	$unsafe = mrn_base_stack_get_rotating_heading_markup( array( 'rotating_phrases_one' => array( array( 'text' => '"><script>alert(1)</script>' ), array( 'text' => 'safe' ) ) ), '<img src=x onerror=alert(1)>{phrase_one}' );
	mrn_presentation_expect( ! str_contains( $unsafe, '<script' ) && ! str_contains( $unsafe, '<img' ) && ! str_contains( $unsafe, 'onerror' ), 'Rotating headings escape text, JSON attributes and authored HTML' );
	mrn_presentation_expect( 'Stay clear' === mrn_base_stack_get_rotating_heading_markup( array( 'rotating_phrases_one' => array( array( 'text' => 'clear' ) ) ), 'Stay {phrase_one}' ), 'A single phrase renders plain text without CSS-dependent duplicate spans' );
	mrn_presentation_expect( mrn_base_stack_format_heading_inline_html( 'Ordinary heading' ) === mrn_base_stack_get_rotating_heading_markup( array(), 'Ordinary heading' ), 'Unset phrase collections preserve the existing heading formatter' );
	$unsafe_stage = mrn_base_stack_get_image_stage_attributes(
		array(
			'image_presentation' => 'scroll-zoom',
			'media_height'       => '10px;background:url(javascript:bad)',
		)
	);
	mrn_presentation_expect( ! str_contains( $unsafe_stage, 'javascript' ) && ! str_contains( $unsafe_stage, 'url(' ), 'Media dimensions reject CSS injection' );
	$proof_path = getenv( 'MRN_PRESENTATION_PROOF_PATH' );
	if ( $proof_path ) {
		$parent = realpath( dirname( $proof_path ) );
		mrn_presentation_expect( false !== $parent && ! str_starts_with( $parent . '/', realpath( ABSPATH ) . '/' ) && str_ends_with( $proof_path, '.html' ), 'Static proof destination is outside the web root' );
		do_action( 'wp_enqueue_scripts' );
		mrn_base_stack_enqueue_layout_styles_for_post( $fixture_id );
		ob_start();
		if ( function_exists( 'mrn_site_colors_print_css_variables' ) ) {
			mrn_site_colors_print_css_variables();
		}
		$site_styles = ob_get_clean();
		ob_start();
		?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Native Stack presentations</title><?php echo $site_styles; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing Site Styles emitter. ?><?php wp_print_styles(); ?></head><body><main id="qualification"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Native escaped renderers above. ?></main><?php wp_print_scripts( array( 'mrn-base-stack-presentation', 'mrn-base-stack-front-end-deferred-media' ) ); ?></body></html>
		<?php
		$document = ob_get_clean();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Explicit local test artifact outside the web root.
		mrn_presentation_expect( false !== file_put_contents( $proof_path, $document ), 'Native static preview written' );
	}
	WP_CLI::line(
		wp_json_encode(
			array(
				'checks'                       => $GLOBALS['mrn_presentation_checks'],
				'fixture_id'                   => $fixture_id,
				'people_ids'                   => $people,
				'comparison_ids'               => $projects,
				'existing_hover_video_records' => $existing_hover_count,
				'html_sha256'                  => hash( 'sha256', $html ),
				'static_preview'               => $proof_path ? $proof_path : null,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		)
	);
} finally {
	wp_delete_post( $fixture_id, true );
}

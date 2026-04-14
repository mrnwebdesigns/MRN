<?php
/**
 * Seed component matches for the first-pass sync pipeline.
 *
 * The mapper stays deterministic by resolving a source Figma component to an
 * exact builder bucket/layout pair first, then copying only fields that exist in
 * the live ACF schema.
 */

return array(
	'hero_basic' => array(
		'match'         => array( 'hero-basic', 'hero-basic-default', 'hero-basic-split-media', 'hero-basic-shell', 'hero / basic', 'page hero' ),
		'target_field'  => 'page_hero_rows',
		'target_layout' => 'hero',
		'field_aliases' => array(
			'content'         => array( 'body', 'text', 'rich_text' ),
			'primary_link'    => array( 'primary_cta', 'primary_action' ),
			'secondary_link'  => array( 'secondary_cta', 'secondary_action' ),
			'background_color' => array( 'surface' ),
		),
	),
	'hero_two_column_split' => array(
		'match'         => array( 'hero-two-column', 'hero-split', 'hero / two column split', 'hero / split' ),
		'target_field'  => 'page_hero_rows',
		'target_layout' => 'hero_two_column_split',
		'field_aliases' => array(
			'left_column_rows'  => array( 'left_column', 'left_slot' ),
			'right_column_rows' => array( 'right_column', 'right_slot' ),
		),
	),
	'body_text' => array(
		'match'         => array( 'body-text', 'rich-text-section', 'content-body-text', 'body text', 'editorial text' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'body_text',
		'field_aliases' => array(
			'body_text' => array( 'content', 'body', 'rich_text' ),
		),
	),
	'basic' => array(
		'match'         => array( 'basic-content', 'basic-media', 'basic-block', 'basic section' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'basic',
		'field_aliases' => array(
			'content' => array( 'body', 'rich_text' ),
			'link'    => array( 'primary_link', 'cta' ),
		),
	),
	'cta' => array(
		'match'         => array( 'cta', 'call-to-action', 'callout-cta', 'page cta' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'cta',
	),
	'grid' => array(
		'match'         => array( 'grid', 'content-grid', 'feature-grid', 'cards-grid' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'grid',
	),
	'faq' => array(
		'match'         => array( 'faq', 'accordion', 'faqs-accordion', 'questions' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'faq',
	),
	'image_content' => array(
		'match'         => array( 'image-content', 'media-content', 'image-and-text', 'split-media-content' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'image_content',
		'field_aliases' => array(
			'content' => array( 'body', 'rich_text' ),
		),
	),
	'tabbed_layout' => array(
		'match'         => array( 'tabbed-layout', 'tabs', 'content-tabs' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'tabbed_layout',
	),
	'two_column_split' => array(
		'match'         => array( 'two-column-split', 'two-column-layout', 'split-layout' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'two_column_split',
		'field_aliases' => array(
			'left_column_rows'  => array( 'left_column', 'left_slot' ),
			'right_column_rows' => array( 'right_column', 'right_slot' ),
		),
	),
	'logos' => array(
		'match'         => array( 'logos', 'logo-strip', 'logo-carousel', 'brand-strip' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'logos',
	),
	'stats' => array(
		'match'         => array( 'stats', 'statistics', 'numbers-band', 'metrics' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'stats',
	),
	'showcase' => array(
		'match'         => array( 'showcase', 'portfolio-grid', 'gallery-showcase' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'showcase',
	),
	'video' => array(
		'match'         => array( 'video', 'video-section', 'embedded-video' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'video',
	),
	'card' => array(
		'match'         => array( 'card', 'card-collection', 'cards' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'card',
	),
	'wpforms' => array(
		'match'         => array( 'form', 'wpforms', 'contact-form', 'lead-form' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'wpforms',
		'field_aliases' => array(
			'intro' => array( 'content', 'body' ),
			'form'  => array( 'form_id' ),
		),
	),
	'searchwp_form' => array(
		'match'         => array( 'search-form', 'searchwp-form', 'site-search' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'searchwp_form',
		'field_aliases' => array(
			'intro'             => array( 'content', 'body' ),
			'searchwp_form_id'  => array( 'form_id' ),
		),
	),
	'content_lists' => array(
		'match'         => array( 'content-list', 'content-lists', 'post-feed', 'listing' ),
		'target_field'  => 'page_content_rows',
		'target_layout' => 'content_lists',
		'field_aliases' => array(
			'content' => array( 'intro', 'body' ),
		),
	),
);

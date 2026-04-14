<?php
/**
 * Static schema definitions.
 *
 * @package mrn-figma-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema definitions used by the sync pipeline.
 */
final class MRN_Figma_Sync_Schema {
	/**
	 * Get the component registry schema.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_component_registry_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'schema_version', 'generated_at', 'field_groups', 'components', 'tokens' ),
			'properties' => array(
				'schema_version' => array( 'type' => 'string' ),
				'generated_at'   => array( 'type' => 'string' ),
				'field_groups'   => array(
					'type'  => 'object',
					'items' => array( 'type' => 'object' ),
				),
				'components'     => array(
					'type'  => 'object',
					'items' => array( 'type' => 'object' ),
				),
				'reusable_blocks' => array(
					'type'  => 'object',
					'items' => array( 'type' => 'object' ),
				),
				'tokens'         => array(
					'type'       => 'object',
					'required'   => array( 'site_colors', 'graphic_elements' ),
					'properties' => array(
						'site_colors'       => array(
							'type'  => 'object',
							'items' => array( 'type' => 'object' ),
						),
						'graphic_elements' => array(
							'type'  => 'object',
							'items' => array( 'type' => 'object' ),
						),
						'section_widths'   => array(
							'type'  => 'object',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
				'constraints'    => array(
					'type'  => 'object',
					'items' => array( 'type' => 'object' ),
				),
			),
		);
	}

	/**
	 * Get the normalized Figma export schema.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_figma_export_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'schema_version', 'target', 'sections' ),
			'properties' => array(
				'schema_version' => array( 'type' => 'string' ),
				'document_id'    => array( 'type' => 'string' ),
				'page_name'      => array( 'type' => 'string' ),
				'target'         => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'   => array( 'type' => 'integer' ),
						'post_type' => array( 'type' => 'string' ),
						'post_slug' => array( 'type' => 'string' ),
					),
				),
				'tokens'         => array(
					'type'  => 'object',
					'items' => array(),
				),
				'sections'       => array(
					'type'  => 'array',
					'items' => self::get_figma_section_schema(),
				),
				'sidebar'        => array(
					'type'       => 'object',
					'properties' => array(
						'layout'   => array(
							'type' => 'string',
							'enum' => array( 'none', 'left', 'right' ),
						),
						'sections' => array(
							'type'  => 'array',
							'items' => self::get_figma_section_schema(),
						),
					),
				),
			),
		);
	}

	/**
	 * Get the normalized Figma section schema.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_figma_section_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'source_component' => array(
					'type'       => 'object',
					'properties' => array(
						'key'  => array( 'type' => 'string' ),
						'name' => array( 'type' => 'string' ),
					),
				),
				'target'           => array(
					'type'       => 'object',
					'properties' => array(
						'field_name' => array( 'type' => 'string' ),
						'layout'     => array( 'type' => 'string' ),
					),
				),
				'variant'          => array( 'type' => 'string' ),
				'props'            => array(
					'type'  => 'object',
					'items' => array(),
				),
				'tokens'           => array(
					'type'  => 'object',
					'items' => array(),
				),
				'slots'            => array(
					'type'  => 'object',
					'items' => array(),
				),
			),
		);
	}

	/**
	 * Get the WordPress layout payload schema.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_wp_layout_payload_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'schema_version', 'target', 'fields' ),
			'properties' => array(
				'schema_version' => array( 'type' => 'string' ),
				'source'         => array(
					'type'  => 'object',
					'items' => array(),
				),
				'target'         => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'   => array( 'type' => 'integer' ),
						'post_type' => array( 'type' => 'string' ),
					),
				),
				'fields'         => array(
					'type'       => 'object',
					'properties' => array(
						'page_hero_rows'         => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'page_content_rows'      => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'page_after_content_rows' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'sidebar_layout'         => array(
							'type' => 'string',
							'enum' => array( 'none', 'left', 'right' ),
						),
						'page_sidebar_rows'      => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Get the field mapping schema.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_field_mapping_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'match', 'target_field', 'target_layout' ),
			'properties' => array(
				'match'         => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'target_field'  => array( 'type' => 'string' ),
				'target_layout' => array( 'type' => 'string' ),
				'variants'      => array(
					'type'  => 'object',
					'items' => array( 'type' => 'object' ),
				),
				'field_aliases' => array(
					'type'  => 'object',
					'items' => array(),
				),
				'defaults'      => array(
					'type'  => 'object',
					'items' => array(),
				),
				'static_values' => array(
					'type'  => 'object',
					'items' => array(),
				),
			),
		);
	}

	/**
	 * Get the token mapping schema.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_token_mapping_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'site_colors'       => array(
					'type'  => 'object',
					'items' => array( 'type' => 'string' ),
				),
				'graphic_elements' => array(
					'type'  => 'object',
					'items' => array( 'type' => 'string' ),
				),
				'section_widths'   => array(
					'type'  => 'object',
					'items' => array( 'type' => 'string' ),
				),
			),
		);
	}

	/**
	 * Get the validation issue schema.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_validation_issue_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'code', 'message', 'path' ),
			'properties' => array(
				'code'    => array( 'type' => 'string' ),
				'message' => array( 'type' => 'string' ),
				'path'    => array( 'type' => 'string' ),
			),
		);
	}
}

<?php
/**
 * Lightweight recursive schema validator.
 *
 * @package mrn-figma-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates arrays against lightweight schemas.
 */
final class MRN_Figma_Sync_Validator {
	/**
	 * Validate a payload against a schema.
	 *
	 * @param mixed                $payload Payload to validate.
	 * @param array<string, mixed> $schema Schema definition.
	 * @param string               $path Current path.
	 * @return array<int, array<string, mixed>>
	 */
	public static function validate( $payload, array $schema, $path = '$' ) {
		$issues = array();
		$type   = isset( $schema['type'] ) ? $schema['type'] : null;

		if ( null !== $type && ! self::matches_type( $payload, $type ) ) {
			$issues[] = MRN_Figma_Sync_Plugin::build_issue(
				'invalid_type',
				sprintf( 'Expected %1$s at %2$s.', is_array( $type ) ? implode( '|', $type ) : $type, $path ),
				$path
			);

			return $issues;
		}

		if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) && ! in_array( $payload, $schema['enum'], true ) ) {
			$issues[] = MRN_Figma_Sync_Plugin::build_issue(
				'invalid_enum',
				sprintf( 'Unexpected value at %1$s.', $path ),
				$path,
				array(
					'allowed' => array_values( $schema['enum'] ),
				)
			);
		}

		if ( 'object' === $type && is_array( $payload ) ) {
			$required = isset( $schema['required'] ) && is_array( $schema['required'] ) ? $schema['required'] : array();

			foreach ( $required as $required_key ) {
				if ( ! array_key_exists( $required_key, $payload ) ) {
					$issues[] = MRN_Figma_Sync_Plugin::build_issue(
						'missing_required_property',
						sprintf( 'Missing required property %1$s at %2$s.', $required_key, $path ),
						$path . '.' . $required_key
					);
				}
			}

			$properties = isset( $schema['properties'] ) && is_array( $schema['properties'] ) ? $schema['properties'] : array();
			$item_schema = isset( $schema['items'] ) && is_array( $schema['items'] ) ? $schema['items'] : null;

			foreach ( $payload as $key => $value ) {
				if ( isset( $properties[ $key ] ) && is_array( $properties[ $key ] ) ) {
					$issues = array_merge( $issues, self::validate( $value, $properties[ $key ], $path . '.' . $key ) );
				} elseif ( $item_schema ) {
					$issues = array_merge( $issues, self::validate( $value, $item_schema, $path . '.' . $key ) );
				}
			}
		}

		if ( 'array' === $type && is_array( $payload ) ) {
			$item_schema = isset( $schema['items'] ) && is_array( $schema['items'] ) ? $schema['items'] : null;

			if ( $item_schema ) {
				foreach ( array_values( $payload ) as $index => $value ) {
					$issues = array_merge( $issues, self::validate( $value, $item_schema, $path . '.' . $index ) );
				}
			}
		}

		return $issues;
	}

	/**
	 * Check whether a payload matches the expected schema type.
	 *
	 * @param mixed               $payload Payload value.
	 * @param string|array<int,string> $type Expected type.
	 * @return bool
	 */
	private static function matches_type( $payload, $type ) {
		$types = is_array( $type ) ? $type : array( $type );

		foreach ( $types as $candidate ) {
			switch ( $candidate ) {
				case 'object':
					if ( is_array( $payload ) ) {
						return true;
					}
					break;
				case 'array':
					if ( is_array( $payload ) ) {
						return true;
					}
					break;
				case 'string':
					if ( is_string( $payload ) ) {
						return true;
					}
					break;
				case 'integer':
					if ( is_int( $payload ) ) {
						return true;
					}
					break;
				case 'number':
					if ( is_int( $payload ) || is_float( $payload ) ) {
						return true;
					}
					break;
				case 'boolean':
					if ( is_bool( $payload ) ) {
						return true;
					}
					break;
				case 'null':
					if ( null === $payload ) {
						return true;
					}
					break;
			}
		}

		return false;
	}
}

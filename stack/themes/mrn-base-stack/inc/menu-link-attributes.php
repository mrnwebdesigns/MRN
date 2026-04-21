<?php
/**
 * Menu item additional link attributes.
 *
 * @package mrn-base-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the meta key that stores additional menu link attributes.
 *
 * @return string
 */
function mrn_base_stack_get_menu_item_link_attributes_meta_key() {
	return '_mrn_menu_item_link_attributes';
}

/**
 * Determine whether a requested custom link attribute name is supported.
 *
 * @param string $attribute_name Raw attribute name.
 * @return bool
 */
function mrn_base_stack_is_allowed_menu_item_link_attribute_name( $attribute_name ) {
	$attribute_name = strtolower( trim( (string) $attribute_name ) );

	if ( '' === $attribute_name ) {
		return false;
	}

	$blocked_attributes = array(
		'href',
		'target',
		'rel',
		'title',
		'id',
		'aria-current',
	);

	if ( in_array( $attribute_name, $blocked_attributes, true ) ) {
		return false;
	}

	if ( 1 === preg_match( '/^(data|aria)-[a-z0-9][a-z0-9_.:-]*$/', $attribute_name ) ) {
		return true;
	}

	$allowed_attributes = array(
		'class',
		'css_classes',
		'button',
		'is_button',
		'download',
		'hreflang',
		'media',
		'ping',
		'referrerpolicy',
		'type',
	);

	return in_array( $attribute_name, $allowed_attributes, true );
}

/**
 * Normalize a class-string into safe CSS class tokens.
 *
 * @param string $raw_classes Raw class string.
 * @return array<int, string>
 */
function mrn_base_stack_normalize_menu_item_class_tokens( $raw_classes ) {
	if ( ! is_string( $raw_classes ) ) {
		return array();
	}

	$tokens = preg_split( '/\s+/', trim( $raw_classes ) );
	if ( ! is_array( $tokens ) ) {
		return array();
	}

	$normalized = array();
	foreach ( $tokens as $token ) {
		if ( ! is_string( $token ) ) {
			continue;
		}

		$token = sanitize_html_class( $token );
		if ( '' === $token ) {
			continue;
		}

		$normalized[] = $token;
	}

	return array_values( array_unique( $normalized ) );
}

/**
 * Normalize button-style toggles from menu link attribute values.
 *
 * @param string $raw_value Raw attribute value.
 * @return bool
 */
function mrn_base_stack_menu_item_button_toggle_enabled( $raw_value ) {
	$value = strtolower( trim( (string) $raw_value ) );

	if ( '' === $value ) {
		return true;
	}

	if ( in_array( $value, array( '1', 'true', 'yes', 'on', 'button' ), true ) ) {
		return true;
	}

	return false;
}

/**
 * Normalize raw menu link attributes into a safe storage format.
 *
 * Expected format is one attribute per line using either `name=value` or
 * `name` for boolean-style attributes such as `download`.
 *
 * @param string $raw_attributes Raw user input.
 * @return string
 */
function mrn_base_stack_normalize_menu_item_link_attributes_raw( $raw_attributes ) {
	if ( ! is_string( $raw_attributes ) ) {
		return '';
	}

	$raw_attributes = trim( $raw_attributes );
	if ( '' === $raw_attributes ) {
		return '';
	}

	$lines = preg_split( '/\r\n|\r|\n/', $raw_attributes );
	if ( ! is_array( $lines ) ) {
		return '';
	}

	$normalized_lines = array();

	foreach ( $lines as $line ) {
		if ( ! is_string( $line ) ) {
			continue;
		}

		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}

		$attribute_name      = $line;
		$attribute_value     = '';
		$has_explicit_value  = false;
		$line_parts_position = strpos( $line, '=' );

		if ( false !== $line_parts_position ) {
			$line_parts = explode( '=', $line, 2 );
			if ( is_array( $line_parts ) ) {
				$attribute_name     = isset( $line_parts[0] ) ? trim( (string) $line_parts[0] ) : '';
				$attribute_value    = isset( $line_parts[1] ) ? trim( (string) $line_parts[1] ) : '';
				$has_explicit_value = true;
			}
		}

		$attribute_name = strtolower( $attribute_name );

		if ( 'css_classes' === $attribute_name ) {
			$attribute_name = 'class';
		}
		if ( 'is_button' === $attribute_name ) {
			$attribute_name = 'button';
		}

		if ( ! mrn_base_stack_is_allowed_menu_item_link_attribute_name( $attribute_name ) ) {
			continue;
		}

		if ( 'class' === $attribute_name ) {
			$class_tokens = mrn_base_stack_normalize_menu_item_class_tokens( $attribute_value );
			if ( empty( $class_tokens ) ) {
				continue;
			}

			$attribute_value    = implode( ' ', $class_tokens );
			$has_explicit_value = true;
		}

		if ( $has_explicit_value ) {
			$attribute_value = sanitize_text_field( trim( $attribute_value, " \t\n\r\0\x0B\"'" ) );
			if ( '' === $attribute_value && ! in_array( $attribute_name, array( 'download', 'button' ), true ) ) {
				continue;
			}
		}

		$normalized_lines[ $attribute_name ] = ( $has_explicit_value && '' !== $attribute_value )
			? $attribute_name . '=' . $attribute_value
			: $attribute_name;
	}

	if ( empty( $normalized_lines ) ) {
		return '';
	}

	return implode( "\n", array_values( $normalized_lines ) );
}

/**
 * Parse normalized menu item link attributes into a map for HTML output.
 *
 * @param string $raw_attributes Raw or normalized saved attribute text.
 * @return array<string, string>
 */
function mrn_base_stack_get_menu_item_link_attributes_map( $raw_attributes ) {
	$normalized_raw = mrn_base_stack_normalize_menu_item_link_attributes_raw( $raw_attributes );
	if ( '' === $normalized_raw ) {
		return array();
	}

	$lines = preg_split( '/\r\n|\r|\n/', $normalized_raw );
	if ( ! is_array( $lines ) ) {
		return array();
	}

	$attributes = array();

	foreach ( $lines as $line ) {
		if ( ! is_string( $line ) ) {
			continue;
		}

		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}

		$attribute_name  = $line;
		$attribute_value = '';

		if ( false !== strpos( $line, '=' ) ) {
			$parts = explode( '=', $line, 2 );
			if ( is_array( $parts ) ) {
				$attribute_name  = isset( $parts[0] ) ? trim( (string) $parts[0] ) : '';
				$attribute_value = isset( $parts[1] ) ? trim( (string) $parts[1] ) : '';
			}
		}

		$attribute_name = strtolower( $attribute_name );
		if ( 'css_classes' === $attribute_name ) {
			$attribute_name = 'class';
		}
		if ( 'is_button' === $attribute_name ) {
			$attribute_name = 'button';
		}

		if ( ! mrn_base_stack_is_allowed_menu_item_link_attribute_name( $attribute_name ) ) {
			continue;
		}

		if ( 'class' === $attribute_name ) {
			$class_tokens = mrn_base_stack_normalize_menu_item_class_tokens( $attribute_value );
			if ( empty( $class_tokens ) ) {
				continue;
			}

			$attribute_value = implode( ' ', $class_tokens );
		}

		$attributes[ $attribute_name ] = '' !== $attribute_value ? $attribute_value : $attribute_name;
	}

	return $attributes;
}

/**
 * Render additional menu-item link attributes field in Appearance > Menus.
 *
 * @param int     $item_id Menu item post ID.
 * @param WP_Post $menu_item Menu item object.
 * @param int     $depth Menu depth.
 * @param array   $args Menu args.
 * @param int     $current_object_id Nav menu ID.
 * @return void
 */
function mrn_base_stack_render_menu_item_link_attributes_field( $item_id, $menu_item, $depth, $args, $current_object_id ) {
	unset( $menu_item, $depth, $args, $current_object_id );

	$meta_key         = mrn_base_stack_get_menu_item_link_attributes_meta_key();
	$saved_attributes = get_post_meta( (int) $item_id, $meta_key, true );
	$saved_attributes = is_string( $saved_attributes ) ? $saved_attributes : '';
	$field_id         = 'edit-menu-item-mrn-link-attributes-' . (int) $item_id;
	$field_name       = 'menu-item-mrn-link-attributes[' . (int) $item_id . ']';
	?>
	<p class="description description-wide mrn-menu-item-link-attributes-field">
		<label for="<?php echo esc_attr( $field_id ); ?>">
			<?php esc_html_e( 'Additional Link Attributes', 'mrn-base-stack' ); ?><br />
			<textarea class="widefat code edit-menu-item-mrn-link-attributes" rows="3" cols="20" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>"><?php echo esc_textarea( $saved_attributes ); ?></textarea>
			<span class="description"><?php esc_html_e( 'One per line. Use name=value (for example data-analytics=header-cta). Contract aliases: class/css_classes and button/is_button. Use button or button=true to apply button styling.', 'mrn-base-stack' ); ?></span>
		</label>
	</p>
	<?php
}
add_action( 'wp_nav_menu_item_custom_fields', 'mrn_base_stack_render_menu_item_link_attributes_field', 10, 5 );

/**
 * Save additional menu-item link attributes.
 *
 * @param int $menu_id Nav menu term ID.
 * @param int $menu_item_db_id Menu item post ID.
 * @return void
 */
function mrn_base_stack_save_menu_item_link_attributes( $menu_id, $menu_item_db_id ) {
	unset( $menu_id );

	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$nonce = isset( $_POST['update-nav-menu-nonce'] )
		? sanitize_text_field( wp_unslash( (string) $_POST['update-nav-menu-nonce'] ) )
		: '';

	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'update-nav_menu' ) ) {
		return;
	}

	$input_map = isset( $_POST['menu-item-mrn-link-attributes'] ) && is_array( $_POST['menu-item-mrn-link-attributes'] )
		? wp_unslash( $_POST['menu-item-mrn-link-attributes'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Individual values are sanitized in $raw_attributes before use.
		: array();

	$raw_attributes = isset( $input_map[ $menu_item_db_id ] )
		? sanitize_textarea_field( (string) $input_map[ $menu_item_db_id ] )
		: '';

	$normalized = mrn_base_stack_normalize_menu_item_link_attributes_raw( $raw_attributes );
	$meta_key   = mrn_base_stack_get_menu_item_link_attributes_meta_key();

	if ( '' === $normalized ) {
		delete_post_meta( $menu_item_db_id, $meta_key );
		return;
	}

	update_post_meta( $menu_item_db_id, $meta_key, $normalized );
}
add_action( 'wp_update_nav_menu_item', 'mrn_base_stack_save_menu_item_link_attributes', 10, 2 );

/**
 * Merge menu-item custom link attributes into rendered nav menu link markup.
 *
 * @param array<string, string> $atts Existing link attributes.
 * @param WP_Post               $menu_item Menu item object.
 * @param stdClass              $args Menu arguments.
 * @param int                   $depth Menu depth.
 * @return array<string, string>
 */
function mrn_base_stack_merge_menu_item_link_attributes_into_nav_links( $atts, $menu_item, $args, $depth ) {
	unset( $args, $depth );

	if ( ! $menu_item instanceof WP_Post ) {
		return $atts;
	}

	$meta_key         = mrn_base_stack_get_menu_item_link_attributes_meta_key();
	$saved_attributes = get_post_meta( $menu_item->ID, $meta_key, true );
	$saved_attributes = is_string( $saved_attributes ) ? $saved_attributes : '';

	if ( '' === $saved_attributes ) {
		return $atts;
	}

	$custom_attributes = mrn_base_stack_get_menu_item_link_attributes_map( $saved_attributes );
	if ( empty( $custom_attributes ) ) {
		return $atts;
	}

	$existing_classes = isset( $atts['class'] ) && is_string( $atts['class'] ) ? $atts['class'] : '';
	$class_tokens     = mrn_base_stack_normalize_menu_item_class_tokens( $existing_classes );

	foreach ( $custom_attributes as $attribute_name => $attribute_value ) {
		if ( 'class' === $attribute_name ) {
			$class_tokens = array_merge( $class_tokens, mrn_base_stack_normalize_menu_item_class_tokens( (string) $attribute_value ) );
			continue;
		}

		if ( 'button' === $attribute_name && mrn_base_stack_menu_item_button_toggle_enabled( (string) $attribute_value ) ) {
			$class_tokens = array_merge( $class_tokens, array( 'mrn-ui__link', 'mrn-ui__link--button' ) );
			continue;
		}

		if ( isset( $atts[ $attribute_name ] ) && '' !== (string) $atts[ $attribute_name ] ) {
			continue;
		}

		$atts[ $attribute_name ] = $attribute_value;
	}

	$class_tokens = array_values( array_unique( array_filter( $class_tokens, 'strlen' ) ) );
	if ( ! empty( $class_tokens ) ) {
		$atts['class'] = implode( ' ', $class_tokens );
	}

	return $atts;
}
add_filter( 'nav_menu_link_attributes', 'mrn_base_stack_merge_menu_item_link_attributes_into_nav_links', 10, 4 );

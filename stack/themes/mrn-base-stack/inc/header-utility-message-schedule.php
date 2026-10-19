<?php
/**
 * Time-zone-aware scheduling for the Header Utility Message.
 *
 * @package mrn-base-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the schedule controls for the existing ACF options page.
 *
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_header_utility_message_schedule_fields() {
	$condition = array(
		array(
			array(
				'field'    => 'field_mrn_header_utility_message_schedule_enabled',
				'operator' => '==',
				'value'    => '1',
			),
		),
	);
	$fields    = array(
		array(
			'key'           => 'field_mrn_header_utility_message_schedule_enabled',
			'name'          => 'header_utility_message_schedule_enabled',
			'label'         => __( 'Schedule', 'mrn-base-stack' ),
			'type'          => 'true_false',
			'ui'            => 1,
			'default_value' => 0,
			'instructions'  => __( 'Automatically show and hide the message during the selected dates. Active must also be switched on. Timing is verified with the site server; the message stays hidden if time cannot be verified.', 'mrn-base-stack' ),
		),
		array(
			'key'               => 'field_mrn_header_utility_message_schedule_timezone',
			'name'              => 'header_utility_message_schedule_timezone',
			'label'             => __( 'Schedule Time Zone', 'mrn-base-stack' ),
			'type'              => 'select',
			'ui'                => 1,
			'default_value'     => '',
			'return_format'     => 'value',
			'conditional_logic' => $condition,
			'instructions'      => __( 'Start and end times use this time zone. City time zones automatically account for daylight saving time.', 'mrn-base-stack' ),
		),
	);

	foreach ( array(
		'start' => __( 'Starts', 'mrn-base-stack' ),
		'end'   => __( 'Ends', 'mrn-base-stack' ),
	) as $boundary => $label ) {
		$fields[] = array(
			'key'               => 'field_mrn_header_utility_message_schedule_' . $boundary,
			'name'              => 'header_utility_message_schedule_' . $boundary,
			'label'             => $label,
			'type'              => 'date_time_picker',
			'wrapper'           => array( 'width' => '50' ),
			'display_format'    => 'F j, Y g:i a',
			'return_format'     => 'Y-m-d H:i:s',
			'conditional_logic' => $condition,
			'instructions'      => 'start' === $boundary
				? __( 'Optional. Day rules and daily hours below are evaluated inside this window. Leave blank for no start limit.', 'mrn-base-stack' )
				: __( 'Optional. Leave blank to keep showing after the start. The message hides at this exact time.', 'mrn-base-stack' ),
		);
	}
	$fields[] = array(
		'key'               => 'field_mrn_header_utility_message_schedule_weekdays',
		'name'              => 'header_utility_message_schedule_weekdays',
		'label'             => __( 'Days of the week', 'mrn-base-stack' ),
		'type'              => 'checkbox',
		'choices'           => array(
			'0' => __( 'Sunday', 'mrn-base-stack' ),
			'1' => __( 'Monday', 'mrn-base-stack' ),
			'2' => __( 'Tuesday', 'mrn-base-stack' ),
			'3' => __( 'Wednesday', 'mrn-base-stack' ),
			'4' => __( 'Thursday', 'mrn-base-stack' ),
			'5' => __( 'Friday', 'mrn-base-stack' ),
			'6' => __( 'Saturday', 'mrn-base-stack' ),
		),
		'layout'            => 'horizontal',
		'return_format'     => 'value',
		'conditional_logic' => $condition,
		'instructions'      => __( 'Repeat on the selected days in the schedule time zone. If no weekday, holiday or custom day is selected, every day is eligible. Otherwise, at least one day rule must match.', 'mrn-base-stack' ),
	);
	$fields[] = array(
		'key'               => 'field_mrn_header_utility_message_schedule_holidays',
		'name'              => 'header_utility_message_schedule_holidays',
		'label'             => __( 'US national holidays', 'mrn-base-stack' ),
		'type'              => 'checkbox',
		'choices'           => mrn_base_stack_get_header_utility_message_holidays(),
		'layout'            => 'horizontal',
		'return_format'     => 'value',
		'conditional_logic' => $condition,
		'instructions'      => __( 'Matches the holiday itself and its observed weekday, as in Announcements.', 'mrn-base-stack' ),
	);
	$fields[] = array(
		'key'               => 'field_mrn_header_utility_message_schedule_custom_days',
		'name'              => 'header_utility_message_schedule_custom_days',
		'label'             => __( 'Custom days', 'mrn-base-stack' ),
		'type'              => 'textarea',
		'rows'              => 3,
		'conditional_logic' => $condition,
		'instructions'      => __( 'Use YYYY-MM-DD for one-time days or MM-DD for annual recurring days. One per line; commas also work.', 'mrn-base-stack' ),
	);
	foreach ( array(
		'daily_start' => __( 'Daily On Time', 'mrn-base-stack' ),
		'daily_end'   => __( 'Daily Off Time', 'mrn-base-stack' ),
	) as $boundary => $label ) {
		$fields[] = array(
			'key'               => 'field_mrn_header_utility_message_schedule_' . $boundary,
			'name'              => 'header_utility_message_schedule_' . $boundary,
			'label'             => $label,
			'type'              => 'time_picker',
			'wrapper'           => array( 'width' => '50' ),
			'display_format'    => 'g:i a',
			'return_format'     => 'H:i:s',
			'conditional_logic' => $condition,
			'instructions'      => __( 'Optional. Leave both daily times blank for the whole day. An off time earlier than the on time continues into the following day; equal times mean 24 hours from the on time.', 'mrn-base-stack' ),
		);
	}

	return $fields;
}

/**
 * Holiday choices match the Announcements scheduling controls.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_header_utility_message_holidays() {
	return array(
		'new_years_day'    => __( "New Year's Day", 'mrn-base-stack' ),
		'mlk_day'          => __( 'Martin Luther King Jr. Day', 'mrn-base-stack' ),
		'presidents_day'   => __( "Presidents' Day", 'mrn-base-stack' ),
		'memorial_day'     => __( 'Memorial Day', 'mrn-base-stack' ),
		'juneteenth'       => __( 'Juneteenth', 'mrn-base-stack' ),
		'independence_day' => __( 'Independence Day', 'mrn-base-stack' ),
		'labor_day'        => __( 'Labor Day', 'mrn-base-stack' ),
		'columbus_day'     => __( "Columbus Day / Indigenous Peoples' Day", 'mrn-base-stack' ),
		'veterans_day'     => __( 'Veterans Day', 'mrn-base-stack' ),
		'thanksgiving_day' => __( 'Thanksgiving Day', 'mrn-base-stack' ),
		'christmas_day'    => __( 'Christmas Day', 'mrn-base-stack' ),
	);
}

/**
 * Populate time zones only when ACF loads this field.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_load_header_utility_message_timezones( $field ) {
	$field['choices'] = array(
		'' => sprintf(
			/* translators: %s: WordPress site time zone. */
			__( 'Site time zone (%s)', 'mrn-base-stack' ),
			wp_timezone_string()
		),
	);
	foreach ( DateTimeZone::listIdentifiers() as $timezone ) {
		$field['choices'][ $timezone ] = str_replace( '_', ' ', $timezone );
	}

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_header_utility_message_schedule_timezone', 'mrn_base_stack_load_header_utility_message_timezones' );

/**
 * Parse a local date strictly, rejecting invalid dates and DST clock gaps.
 * During a repeated autumn hour, use the first occurrence consistently.
 *
 * @param string       $value    ACF database date string.
 * @param DateTimeZone $timezone Selected time zone.
 * @return int|null
 */
function mrn_base_stack_parse_header_utility_message_date( $value, DateTimeZone $timezone ) {
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value ) ) {
		return null;
	}
	$date   = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, $timezone );
	$errors = DateTimeImmutable::getLastErrors();
	if ( ! $date || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $date->format( 'Y-m-d H:i:s' ) !== $value ) {
		return null;
	}

	$timestamp   = $date->getTimestamp();
	$transitions = $timezone->getTransitions( $timestamp - DAY_IN_SECONDS, $timestamp + DAY_IN_SECONDS );
	$wall_time   = $timestamp + $date->getOffset();
	foreach ( is_array( $transitions ) ? $transitions : array() as $transition ) {
		$candidate = $wall_time - $transition['offset'];
		if ( $candidate < $timestamp && ( new DateTimeImmutable( '@' . $candidate ) )->setTimezone( $timezone )->format( 'Y-m-d H:i:s' ) === $value ) {
			$timestamp = $candidate;
		}
	}

	return $timestamp;
}

/**
 * Validate schedule data and convert local wall times to absolute timestamps.
 *
 * @param bool   $enabled  Whether scheduling is enabled.
 * @param string $timezone Named time zone, or blank for the site time zone.
 * @param string $start    Optional local start date.
 * @param string $end      Optional local end date.
 * @param array  $rules    Optional recurring day and daily-time rules.
 * @return array<string, mixed>
 */
function mrn_base_stack_normalize_header_utility_message_schedule( $enabled, $timezone, $start, $end, $rules = array() ) {
	$schedule = array(
		'enabled'  => (bool) $enabled,
		'valid'    => true,
		'timezone' => $timezone,
		'start'    => null,
		'end'      => null,
		'errors'   => array(),
		'rules'    => array(),
	);
	if ( ! $enabled ) {
		return $schedule;
	}

	$schedule['timezone'] = '' === $timezone ? wp_timezone_string() : $timezone;
	if ( '' !== $timezone && ! in_array( $timezone, DateTimeZone::listIdentifiers( DateTimeZone::ALL_WITH_BC ), true ) ) {
		$schedule['errors']['timezone'] = __( 'Choose a valid schedule time zone.', 'mrn-base-stack' );
	} else {
		$zone = new DateTimeZone( $schedule['timezone'] );
		foreach ( array(
			'start' => $start,
			'end'   => $end,
		) as $boundary => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$schedule[ $boundary ] = mrn_base_stack_parse_header_utility_message_date( $value, $zone );
			if ( null === $schedule[ $boundary ] ) {
				$schedule['errors'][ $boundary ] = __( 'Enter a valid local date and time in the selected time zone. Times skipped by daylight saving changes cannot be used.', 'mrn-base-stack' );
			}
		}
	}

	if ( null !== $schedule['start'] && null !== $schedule['end'] && $schedule['end'] <= $schedule['start'] ) {
		$schedule['errors']['end'] = __( 'The turn-off time must be later than the turn-on time.', 'mrn-base-stack' );
	}
	$rule_result        = mrn_base_stack_normalize_header_utility_message_day_rules( $rules );
	$schedule['rules']  = $rule_result['rules'];
	$schedule['errors'] = array_merge( $schedule['errors'], $rule_result['errors'] );
	$schedule['valid']  = empty( $schedule['errors'] );

	return $schedule;
}

/**
 * Normalize optional Announcements-style day rules and repeating daily hours.
 *
 * @param array<string, mixed> $input Submitted or stored rules.
 * @return array<string, mixed>
 */
function mrn_base_stack_normalize_header_utility_message_day_rules( $input ) {
	$rules  = array(
		'weekdays'    => array(),
		'holidays'    => array(),
		'custom_days' => array(),
		'daily_start' => null,
		'daily_end'   => null,
	);
	$errors = array();
	foreach ( array(
		'weekdays' => array( '0', '1', '2', '3', '4', '5', '6' ),
		'holidays' => array_keys( mrn_base_stack_get_header_utility_message_holidays() ),
	) as $name => $allowed ) {
		$values = $input[ $name ] ?? array();
		$values = empty( $values ) ? array() : $values;
		if ( ! is_array( $values ) || array_filter( $values, 'is_array' ) || array_filter( $values, 'is_object' ) ) {
			$errors[ $name ] = __( 'Choose valid schedule days.', 'mrn-base-stack' );
			continue;
		}
		$values = array_map( 'strval', $values );
		if ( array_diff( $values, $allowed ) ) {
			$errors[ $name ] = __( 'Choose valid schedule days.', 'mrn-base-stack' );
		}
		$rules[ $name ] = array_values( array_unique( array_intersect( $values, $allowed ) ) );
	}

	$custom = isset( $input['custom_days'] ) && is_string( $input['custom_days'] ) ? $input['custom_days'] : '';
	if ( ! empty( $input['custom_days'] ) && ! is_string( $input['custom_days'] ) ) {
		$errors['custom_days'] = __( 'Enter valid custom days using YYYY-MM-DD or MM-DD.', 'mrn-base-stack' );
	}
	foreach ( preg_split( '/[\r\n,]+/', $custom ) as $value ) {
		$value = trim( str_replace( '/', '-', $value ) );
		if ( '' === $value ) {
			continue;
		}
		$parts = explode( '-', $value );
		$year  = 3 === count( $parts ) ? array_shift( $parts ) : '2024';
		if ( ! preg_match( '/^(?:\d{4}-)?\d{1,2}-\d{1,2}$/D', $value ) || ! checkdate( (int) $parts[0], (int) ( $parts[1] ?? 0 ), (int) $year ) ) {
			$errors['custom_days'] = __( 'Enter valid custom days using YYYY-MM-DD or MM-DD.', 'mrn-base-stack' );
			continue;
		}
		$rules['custom_days'][] = ( strlen( $value ) > 5 ? $year . '-' : '' ) . sprintf( '%02d-%02d', (int) $parts[0], (int) $parts[1] );
	}
	foreach ( array( 'daily_start', 'daily_end' ) as $name ) {
		$value = $input[ $name ] ?? '';
		if ( '' === $value || false === $value || null === $value ) {
			continue;
		}
		if ( ! is_string( $value ) || ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/D', $value, $parts ) ) {
			$errors[ $name ] = __( 'Enter a valid daily time.', 'mrn-base-stack' );
			continue;
		}
		$rules[ $name ] = (int) $parts[1] * HOUR_IN_SECONDS + (int) $parts[2] * MINUTE_IN_SECONDS + (int) ( $parts[3] ?? 0 );
	}
	if ( ( null === $rules['daily_start'] ) !== ( null === $rules['daily_end'] ) ) {
		$errors['daily_end'] = __( 'Enter both daily times, or leave both blank for the whole day.', 'mrn-base-stack' );
	}

	return array(
		'rules'  => $rules,
		'errors' => $errors,
	);
}

/**
 * Read unformatted ACF dates, preserving their selected local wall times.
 *
 * @return array<string, mixed>
 */
function mrn_base_stack_get_header_utility_message_schedule() {
	$enabled = function_exists( 'get_field' ) && (bool) get_field( 'header_utility_message_schedule_enabled', 'option' );
	$rules   = array();
	if ( $enabled ) {
		foreach ( array( 'weekdays', 'holidays', 'custom_days', 'daily_start', 'daily_end' ) as $name ) {
			$rules[ $name ] = get_field( 'header_utility_message_schedule_' . $name, 'option', false );
		}
	}

	return mrn_base_stack_normalize_header_utility_message_schedule(
		$enabled,
		$enabled ? (string) get_field( 'header_utility_message_schedule_timezone', 'option' ) : '',
		$enabled ? (string) get_field( 'header_utility_message_schedule_start', 'option', false ) : '',
		$enabled ? (string) get_field( 'header_utility_message_schedule_end', 'option', false ) : '',
		$rules
	);
}

/**
 * Validate the submitted schedule through ACF's authenticated save workflow.
 */
function mrn_base_stack_validate_header_utility_message_schedule() {
	// ACF validates the form nonce before invoking this validation hook. No writes occur here.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ACF verifies the nonce; specific scalar fields are sanitized below. This callback performs no writes.
	$posted = isset( $_POST['acf'] ) && is_array( $_POST['acf'] ) ? wp_unslash( $_POST['acf'] ) : array();
	$key    = 'field_mrn_header_utility_message_schedule_';
	if ( ! isset( $posted[ $key . 'enabled' ] ) ) {
		return;
	}

	$values = array();
	foreach ( array( 'timezone', 'start', 'end', 'custom_days', 'daily_start', 'daily_end' ) as $name ) {
		$values[ $name ] = isset( $posted[ $key . $name ] ) && is_string( $posted[ $key . $name ] ) ? sanitize_text_field( $posted[ $key . $name ] ) : '';
	}
	$values['custom_days'] = isset( $posted[ $key . 'custom_days' ] ) && is_string( $posted[ $key . 'custom_days' ] ) ? sanitize_textarea_field( $posted[ $key . 'custom_days' ] ) : '';
	foreach ( array( 'weekdays', 'holidays' ) as $name ) {
		$values[ $name ] = isset( $posted[ $key . $name ] ) && is_array( $posted[ $key . $name ] ) ? array_map( 'sanitize_text_field', $posted[ $key . $name ] ) : array();
	}
	$schedule = mrn_base_stack_normalize_header_utility_message_schedule( ! empty( $posted[ $key . 'enabled' ] ), $values['timezone'], $values['start'], $values['end'], $values );
	foreach ( $schedule['errors'] as $name => $error ) {
		if ( function_exists( 'acf_add_validation_error' ) ) {
			acf_add_validation_error( 'acf[' . $key . $name . ']', $error );
		}
	}
}
add_action( 'acf/validate_save_post', 'mrn_base_stack_validate_header_utility_message_schedule' );

/**
 * Bind a cached message to its current saved settings without publishing them.
 *
 * @return string
 */
function mrn_base_stack_get_header_utility_message_schedule_version() {
	return hash( 'sha256', wp_json_encode( array( mrn_base_stack_get_header_utility_message_options(), mrn_base_stack_get_header_utility_message_schedule() ) ) );
}

/**
 * Register a public, read-only clock. POST prevents intermediary GET caching;
 * the random request token also lets the browser reject a replayed response.
 */
function mrn_base_stack_register_header_utility_message_clock() {
	// nosemgrep: wp-open-rest-permission-callback -- Intentionally public, read-only UTC and opaque fingerprint; bounded validated token, no content disclosure or writes.
	register_rest_route(
		'mrn/v1',
		'/header-utility-message-clock',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'mrn_base_stack_get_header_utility_message_clock',
			'args'                => array(
				'token' => array(
					'type'     => 'string',
					'required' => true,
					'pattern'  => '^[a-f0-9]{32}$',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'mrn_base_stack_register_header_utility_message_clock' );

/**
 * Return current UTC and a settings fingerprint, with no content or writes.
 * The token is a cache challenge, not an authentication nonce.
 *
 * @param WP_REST_Request $request Validated public clock request.
 * @return WP_REST_Response
 */
function mrn_base_stack_get_header_utility_message_clock( $request ) {
	$schedule = mrn_base_stack_get_header_utility_message_schedule();
	$version  = $schedule['enabled'] && mrn_base_stack_has_header_utility_message() ? mrn_base_stack_get_header_utility_message_schedule_version() : '';
	$now      = microtime( true ) * 1000;
	// PHP records this request's start before WordPress boots. Subtracting
	// server processing time prevents slow bootstrap from skewing transit estimates.
	$received = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] * 1000 : $now;

	return new WP_REST_Response(
		array(
			'token'    => $request['token'],
			'version'  => $version,
			'received' => $received,
			'now'      => $now,
		),
		200,
		array(
			'Cache-Control'     => 'no-store, no-cache, must-revalidate, max-age=0, private',
			'CDN-Cache-Control' => 'no-store',
			'Surrogate-Control' => 'no-store',
			'Pragma'            => 'no-cache',
			'Expires'           => '0',
		)
	);
}

/**
 * Return escaped schedule attributes for the header grid item.
 *
 * @return string
 */
function mrn_base_stack_get_header_utility_message_schedule_attributes() {
	$schedule = mrn_base_stack_get_header_utility_message_schedule();
	if ( ! $schedule['enabled'] ) {
		return '';
	}

	// Start hidden so cached HTML never flashes an expired or future message.
	$rules             = $schedule['rules'];
	$rules['timezone'] = $schedule['timezone'];
	$rules['offset']   = null;
	if ( preg_match( '/^[+-]\d{2}:\d{2}$/D', $schedule['timezone'] ) ) {
		$rules['offset'] = ( new DateTimeZone( $schedule['timezone'] ) )->getOffset( new DateTimeImmutable( '@0' ) );
	}

	return 'hidden data-mrn-utility-schedule data-mrn-utility-clock-url="' . esc_url( rest_url( 'mrn/v1/header-utility-message-clock' ) ) . '" data-mrn-utility-version="' . esc_attr( mrn_base_stack_get_header_utility_message_schedule_version() ) . '" data-mrn-utility-start="' . esc_attr( (string) $schedule['start'] ) . '" data-mrn-utility-end="' . esc_attr( (string) $schedule['end'] ) . '" data-mrn-utility-rules="' . esc_attr( wp_json_encode( $rules ) ) . '"';
}

/**
 * Render the small, synchronous controller beside a scheduled header item.
 * Fresh server UTC anchors the controller; WordPress cron is not needed.
 *
 * @return string
 */
function mrn_base_stack_get_header_utility_message_schedule_script() {
	$schedule = mrn_base_stack_get_header_utility_message_schedule();
	if ( ! $schedule['enabled'] || ! $schedule['valid'] ) {
		return '';
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Trusted local theme source, inlined to apply cached schedules before paint.
	$script = file_get_contents( __DIR__ . '/../js/header-utility-message-schedule.js' );

	return false !== $script ? wp_get_inline_script_tag( $script ) : '';
}

/**
 * Preserve hidden state against the header grid's display declarations.
 */
function mrn_base_stack_enqueue_header_utility_message_schedule_style() {
	if ( mrn_base_stack_has_header_utility_message() && mrn_base_stack_get_header_utility_message_schedule()['enabled'] ) {
		wp_add_inline_style( 'mrn-base-stack-style', '[data-mrn-utility-schedule][hidden]{display:none!important}' );
	}
}
add_action( 'wp_enqueue_scripts', 'mrn_base_stack_enqueue_header_utility_message_schedule_style', 20 );

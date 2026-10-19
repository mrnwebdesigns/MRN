<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness; also emits real helper markup for browser tests.
/**
 * Scheduling boundaries, time zones, validation and rendering regression tests.
 *
 * @package mrn-base-stack
 */

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['mrn_schedule_fields'] = array();
$GLOBALS['mrn_schedule_errors'] = array();
$GLOBALS['mrn_schedule_hooks']  = array();

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['mrn_schedule_hooks'][ $hook ] = $callback;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	add_action( $hook, $callback, $priority, $args );
}
function __( $text, $domain = '' ) {
	return $text;
}
function wp_timezone_string() {
	return $GLOBALS['mrn_schedule_site_timezone'] ?? 'America/New_York';
}
function get_field( $name, $post_id = null, $format = true ) {
	return $GLOBALS['mrn_schedule_fields'][ $name ] ?? '';
}
function wp_unslash( $value ) {
	return $value;
}
function sanitize_text_field( $value ) {
	return trim( strip_tags( $value ) );
}
function sanitize_textarea_field( $value ) {
	return sanitize_text_field( $value );
}
function wp_json_encode( $value ) {
	return json_encode( $value );
}
function rest_url( $path ) {
	return 'https://example.test/wp-json/' . $path;
}
function includes_url( $path ) {
	return 'https://example.test/wp-includes/' . $path;
}
function esc_url_raw( $value ) {
	return $value;
}
function register_rest_route( $namespace, $route, $args ) {
	$GLOBALS['mrn_schedule_route'] = array( $namespace, $route, $args );
}
class WP_REST_Response {
	public function __construct( public $data, public $status, public $headers ) {}
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
}
function sanitize_html_class( $value ) {
	return sanitize_key( $value );
}
function wp_kses( $value, $allowed ) {
	return strip_tags( $value, '<span><strong><em><br>' );
}
function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES );
}
function esc_html( $value ) {
	return esc_attr( $value );
}
function esc_url( $value ) {
	return esc_attr( $value );
}
function wp_get_inline_script_tag( $script ) {
	return '<script>' . $script . '</script>';
}
function wp_add_inline_style( $handle, $style ) {
	$GLOBALS['mrn_schedule_style'] = $style;
}
function acf_add_validation_error( $input, $error ) {
	$GLOBALS['mrn_schedule_errors'][ $input ] = $error;
}
function mrn_schedule_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

require dirname( __DIR__, 2 ) . '/inc/header-utility-message-schedule.php';
require dirname( __DIR__, 2 ) . '/inc/template-tags.php';

$GLOBALS['mrn_schedule_fields'] = array(
	'header_utility_message_enabled'           => 1,
	'header_utility_message_text'              => 'Scheduled message',
	'header_utility_message_link'              => array( 'url' => 'https://example.test/shop/', 'title' => 'Shop now' ),
	'header_utility_message_schedule_enabled'  => 1,
	'header_utility_message_schedule_timezone' => 'America/New_York',
	'header_utility_message_schedule_start'    => '2026-10-16 09:00:00',
	'header_utility_message_schedule_end'      => '2026-10-16 17:00:00',
);

if ( in_array( $argv[1] ?? '', array( '--fixture', '--refresh-fixture' ), true ) ) {
	$mode = $argv[2] ?? 'schedule';
	if ( 'manual' === $mode ) {
		$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_enabled'] = 0;
	} elseif ( 'inactive' === $mode ) {
		$GLOBALS['mrn_schedule_fields']['header_utility_message_enabled'] = 0;
	} elseif ( 'invalid' === $mode ) {
		$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_end'] = '2026-10-15 17:00:00';
	} elseif ( 'start-only' === $mode ) {
		$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_end'] = '';
	} elseif ( 'end-only' === $mode ) {
		$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_start'] = '';
	}
	if ( in_array( $mode, array( 'weekly', 'bounded-weekly', 'overnight', 'holiday', 'annual', 'exact-day', 'fixed-offset', 'daily', 'dst', 'every-day' ), true ) ) {
		if ( 'bounded-weekly' !== $mode ) {
		$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_start'] = '';
		$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_end']   = '';
		}
		if ( 'weekly' === $mode || 'bounded-weekly' === $mode || 'overnight' === $mode ) {
			$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_weekdays'] = array( '5' );
			$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_daily_start'] = 'overnight' === $mode ? '22:00:00' : '09:00:00';
			$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_daily_end'] = 'overnight' === $mode ? '02:00:00' : '17:00:00';
		} elseif ( 'holiday' === $mode ) {
			$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_holidays'] = array( 'new_years_day', 'independence_day', 'thanksgiving_day', 'memorial_day' );
		} elseif ( 'annual' === $mode || 'exact-day' === $mode ) {
			$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_custom_days'] = 'annual' === $mode ? '12-24' : '2026-12-24';
		} elseif ( 'fixed-offset' === $mode ) {
			$GLOBALS['mrn_schedule_site_timezone'] = '+05:30';
			$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_timezone'] = '';
			$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_weekdays'] = array( '5' );
		} elseif ( 'daily' === $mode || 'dst' === $mode ) {
			$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_daily_start'] = 'dst' === $mode ? '01:00:00' : '09:00:00';
			$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_daily_end'] = 'dst' === $mode ? '02:00:00' : '17:00:00';
		}
	}
	if ( isset( $argv[3] ) ) {
		$GLOBALS['mrn_schedule_fields']['header_utility_message_text'] = $argv[3];
	}
	if ( '--refresh-fixture' === $argv[1] ) {
		echo wp_json_encode( array( 'version' => mrn_base_stack_get_header_utility_message_schedule_version(), 'message' => mrn_base_stack_get_header_utility_message_refresh() ) );
		exit;
	}
	$markup = '';
	mrn_base_stack_enqueue_header_utility_message_schedule_style();
	$markup = '<div class="mrn-site-header__utility-message-item" ' . mrn_base_stack_get_header_utility_message_schedule_attributes() . '>' . mrn_base_stack_get_header_utility_message_markup() . '</div>' . mrn_base_stack_get_header_utility_message_schedule_script();
	echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Schedule fixture</title><style>body{font:16px Arial;color:#222;background:#fff}.mrn-site-header__utility-message-item{display:flex}' . ( $GLOBALS['mrn_schedule_style'] ?? '' ) . '</style></head><body><header>' . $markup . '</header><main><h1>Schedule fixture</h1><label>Your note <input name="note"></label><a href="#next">Next link</a></main></body></html>';
	exit;
}

$schedule = mrn_base_stack_get_header_utility_message_schedule();
mrn_schedule_assert( $schedule['valid'], 'A normal schedule should be valid.' );
mrn_base_stack_register_header_utility_message_clock();
$route = $GLOBALS['mrn_schedule_route'];
mrn_schedule_assert( 'POST' === $route[2]['methods'] && '__return_true' === $route[2]['permission_callback'], 'The read-only public clock must avoid cached GET requests.' );
mrn_schedule_assert( $route[2]['args']['token']['required'] && '^[a-f0-9]{32}$' === $route[2]['args']['token']['pattern'], 'Each request needs a bounded random cache challenge.' );
$before_clock = microtime( true ) * 1000;
$clock = mrn_base_stack_get_header_utility_message_clock( array( 'token' => str_repeat( 'a', 32 ) ) );
mrn_schedule_assert( $clock->data['now'] >= $before_clock && $clock->data['now'] <= microtime( true ) * 1000, 'The endpoint must return real server UTC milliseconds.' );
mrn_schedule_assert( str_repeat( 'a', 32 ) === $clock->data['token'] && mrn_base_stack_get_header_utility_message_schedule_version() === $clock->data['version'], 'Clock responses must bind the request and the saved message.' );
mrn_schedule_assert( str_contains( $clock->headers['Cache-Control'], 'no-store' ) && 'no-store' === $clock->headers['CDN-Cache-Control'], 'Browser and edge caches must not store clock responses.' );
mrn_schedule_assert( array( 'token', 'version', 'received', 'now' ) === array_keys( $clock->data ), 'The public clock must expose neither message content nor stored settings.' );
$version = $clock->data['version'];
$GLOBALS['mrn_schedule_fields']['header_utility_message_text'] = 'Changed message';
mrn_schedule_assert( $version !== mrn_base_stack_get_header_utility_message_schedule_version(), 'A stale cached message must lose its settings fingerprint.' );
$GLOBALS['mrn_schedule_fields']['header_utility_message_text'] = 'Scheduled message';
$GLOBALS['mrn_schedule_fields']['header_utility_message_enabled'] = 0;
mrn_schedule_assert( '' === mrn_base_stack_get_header_utility_message_clock( array( 'token' => str_repeat( 'b', 32 ) ) )->data['version'], 'Inactive messages must never receive clock authorization.' );
$refresh = mrn_base_stack_get_header_utility_message_clock( array( 'token' => str_repeat( 'c', 32 ), 'refresh' => true ) );
mrn_schedule_assert( false === $refresh->data['message']['enabled'] && '' === $refresh->data['message']['html'] && null === $refresh->data['message']['start'], 'Inactive refreshes must not disclose saved content or schedules.' );
$GLOBALS['mrn_schedule_fields']['header_utility_message_enabled'] = 1;
$refresh = mrn_base_stack_get_header_utility_message_clock( array( 'token' => str_repeat( 'c', 32 ), 'refresh' => true, 'version' => $version ) );
mrn_schedule_assert( null === $refresh->data['message'], 'Unchanged versions must omit replacement markup.' );
$refresh = mrn_base_stack_get_header_utility_message_clock( array( 'token' => str_repeat( 'c', 32 ), 'refresh' => true, 'version' => str_repeat( '0', 64 ) ) );
mrn_schedule_assert( $refresh->data['message']['enabled'] && $refresh->data['message']['scheduled'] && str_contains( $refresh->data['message']['html'], 'Scheduled message' ), 'A stale page must receive the current sanitized public message and schedule.' );
$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_enabled'] = 0;
mrn_schedule_assert( ! mrn_base_stack_get_header_utility_message_refresh()['scheduled'] && mrn_base_stack_get_header_utility_message_refresh()['enabled'], 'Manual messages must refresh without schedule limits.' );
$GLOBALS['mrn_schedule_fields']['header_utility_message_schedule_enabled'] = 1;
mrn_schedule_assert( strtotime( '2026-10-16T13:00:00Z' ) === $schedule['start'], 'Start uses the selected time zone.' );
mrn_schedule_assert( strtotime( '2026-10-16T21:00:00Z' ) === $schedule['end'], 'End uses the selected time zone.' );

foreach ( array(
	array( false, 'bad zone', 'bad date', 'bad date', true ),
	array( true, 'UTC', '', '', true ),
	array( true, 'UTC', '2026-10-16 09:00:00', '', true ),
	array( true, 'UTC', '', '2026-10-16 17:00:00', true ),
	array( true, 'bad zone', '2026-10-16 09:00:00', '', false ),
	array( true, 'UTC', '2026-10-16 09:00:00', '2026-10-16 09:00:00', false ),
	array( true, 'UTC', '2026-10-16 09:00:00', '2026-10-15 09:00:00', false ),
	array( true, 'UTC', '2026-02-30 09:00:00', '', false ),
	array( true, 'UTC', 'tomorrow', '', false ),
	array( true, 'America/New_York', '2026-03-08 02:30:00', '', false ),
) as $case ) {
	$expected = array_pop( $case );
	$actual   = mrn_base_stack_normalize_header_utility_message_schedule( ...$case );
	mrn_schedule_assert( $expected === $actual['valid'], 'Unexpected validity for ' . json_encode( $case ) );
}

foreach ( array(
	array( 'America/New_York', '2026-01-16 09:00:00', '2026-01-16T14:00:00Z' ),
	array( 'America/New_York', '2026-07-16 09:00:00', '2026-07-16T13:00:00Z' ),
	array( 'America/New_York', '2026-11-01 01:30:00', '2026-11-01T05:30:00Z' ),
	array( 'Asia/Kathmandu', '2026-10-16 09:00:00', '2026-10-16T03:15:00Z' ),
	array( 'Australia/Lord_Howe', '2026-04-05 01:45:00', '2026-04-04T14:45:00Z' ),
) as $case ) {
	$actual = mrn_base_stack_normalize_header_utility_message_schedule( true, $case[0], $case[1], '' );
	mrn_schedule_assert( strtotime( $case[2] ) === $actual['start'], 'Incorrect DST or fractional-offset conversion for ' . json_encode( $case ) );
}

$GLOBALS['mrn_schedule_site_timezone'] = '+05:30';
$site_schedule = mrn_base_stack_normalize_header_utility_message_schedule( true, '', '2026-10-16 09:00:00', '' );
mrn_schedule_assert( strtotime( '2026-10-16T03:30:00Z' ) === $site_schedule['start'], 'Blank zone follows the site fixed offset.' );

$key = 'field_mrn_header_utility_message_schedule_';
$_POST['acf'] = array( $key . 'enabled' => '1', $key . 'timezone' => 'UTC', $key . 'start' => '2026-10-16 17:00:00', $key . 'end' => '2026-10-16 09:00:00' );
mrn_base_stack_validate_header_utility_message_schedule();
mrn_schedule_assert( isset( $GLOBALS['mrn_schedule_errors'][ 'acf[' . $key . 'end]' ] ), 'ACF must reject reversed boundaries beside the end field.' );
$GLOBALS['mrn_schedule_errors'] = array();
$_POST['acf'][ $key . 'enabled' ] = '0';
mrn_base_stack_validate_header_utility_message_schedule();
mrn_schedule_assert( array() === $GLOBALS['mrn_schedule_errors'], 'Disabled schedules must not block manual settings.' );
$_POST['acf'] = array( 'unrelated_field' => '1' );
mrn_base_stack_validate_header_utility_message_schedule();
mrn_schedule_assert( array() === $GLOBALS['mrn_schedule_errors'], 'Other ACF forms must remain unaffected.' );

$fields = mrn_base_stack_get_header_utility_message_schedule_fields();
mrn_schedule_assert( 9 === count( $fields ), 'The schedule must expose its switch, zone, dates, day rules and optional daily hours.' );
$zones = mrn_base_stack_load_header_utility_message_timezones( $fields[1] );
mrn_schedule_assert( isset( $zones['choices']['America/New_York'], $zones['choices']['UTC'], $zones['choices'][''] ), 'Time zone choices must include cities, UTC and the site default.' );
mrn_schedule_assert( 'Y-m-d H:i:s' === $fields[2]['return_format'] && 'Y-m-d H:i:s' === $fields[3]['return_format'], 'Dates must preserve their local database format.' );
mrn_schedule_assert( mrn_base_stack_has_header_utility_message(), 'Scheduled content must remain available to cached pages.' );
$GLOBALS['mrn_schedule_fields']['header_utility_message_enabled'] = 0;
mrn_schedule_assert( ! mrn_base_stack_has_header_utility_message(), 'Active must remain the master switch.' );

foreach ( array(
	array( array( 'weekdays' => array( '1', '5' ) ), true ),
	array( array( 'weekdays' => array( '8' ) ), false ),
	array( array( 'weekdays' => array( array() ) ), false ),
	array( array( 'holidays' => array( 'thanksgiving_day' ) ), true ),
	array( array( 'holidays' => array( 'invented_holiday' ) ), false ),
	array( array( 'custom_days' => "2026-12-24\n12-25,02-29" ), true ),
	array( array( 'custom_days' => '2026-02-29' ), false ),
	array( array( 'custom_days' => '02-30' ), false ),
	array( array( 'custom_days' => '<script>' ), false ),
	array( array( 'daily_start' => '09:00', 'daily_end' => '17:00:00' ), true ),
	array( array( 'daily_start' => '22:00', 'daily_end' => '02:00' ), true ),
	array( array( 'daily_start' => '09:00', 'daily_end' => '09:00' ), true ),
	array( array( 'daily_start' => '09:00' ), false ),
	array( array( 'daily_start' => '25:00', 'daily_end' => '17:00' ), false ),
) as $case ) {
	$actual = mrn_base_stack_normalize_header_utility_message_schedule( true, 'America/New_York', '', '', $case[0] );
	mrn_schedule_assert( $case[1] === $actual['valid'], 'Unexpected recurring rule validity for ' . json_encode( $case[0] ) );
}
$custom = mrn_base_stack_normalize_header_utility_message_day_rules( array( 'custom_days' => "2026/1/2\n2-3\n02-29" ) );
mrn_schedule_assert( array( '2026-01-02', '02-03', '02-29' ) === $custom['rules']['custom_days'], 'Custom dates must retain exact and annual forms.' );
echo "PASS: Header Utility Message time zones, DST, ACF validation and master switch.\n";

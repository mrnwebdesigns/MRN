<?php
/**
 * Plugin Name: MRN Recovery Agent
 * Description: Detects and self-heals fatal-PHP-error failures caused by a QA Engine-triggered plugin/theme update, without requiring SSH or a host API.
 * Version: 0.1.1
 * Author: MRN Web Designs
 *
 * @package MRN_Recovery_Agent
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MRN_RECOVERY_AGENT_VERSION' ) ) {
	define( 'MRN_RECOVERY_AGENT_VERSION', '0.1.1' );
}

// This must run at top-level, unconditionally, as early as possible in this
// file's load — mu-plugins load before every regular plugin, so this is the
// earliest point in the request any MRN code can register a shutdown handler.
register_shutdown_function( 'mrn_recovery_agent_shutdown_tripwire' );

/**
 * Inspect the last PHP error on shutdown and, if it is a fatal error whose
 * file resolves under a plugin this recovery agent has a pending-update
 * marker for, disable that one plugin so the next request recovers.
 *
 * Deliberately scoped narrowly: this only acts on fatals tied to an update
 * QA Engine is actively tracking (a pending-update marker set by the
 * `/snapshot` REST action immediately before QA Engine triggers the real
 * update), not every plugin fatal on the site. See AGENTS.md for why.
 *
 * @return void
 */
function mrn_recovery_agent_shutdown_tripwire() {
	$error = error_get_last();
	if ( ! is_array( $error ) || ! isset( $error['type'], $error['file'] ) ) {
		return;
	}

	$fatal_types = array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR );
	if ( ! in_array( (int) $error['type'], $fatal_types, true ) ) {
		return;
	}

	$slug = mrn_recovery_agent_slug_from_error_file( (string) $error['file'] );
	if ( '' === $slug ) {
		return;
	}

	$pending = mrn_recovery_agent_pending_update_slug();
	if ( '' === $pending || $pending !== $slug ) {
		// Not a fatal tied to a QA Engine-tracked update — record it for
		// the next authenticated /status poll, but do not act unilaterally.
		mrn_recovery_agent_record_untracked_fatal( $slug, $error );
		return;
	}

	$result = mrn_recovery_agent_disable_plugin( $slug, 'shutdown_tripwire_auto_heal' );
	// The tripwire calls mrn_recovery_agent_disable_plugin() directly rather
	// than going through the /action REST route, so it must log itself —
	// confirmed missing during live testing: an autonomous heal was only
	// visible in the single-slot "last disabled" option, never in the
	// durable audit trail an /action-triggered heal produces.
	mrn_recovery_agent_audit_log( 'disable_plugin', $slug, 'shutdown_tripwire_auto_heal', $result );
}

/**
 * Resolve a plugin slug (the "folder" part of "folder/file.php") from an
 * error's absolute file path, if that path is under WP_PLUGIN_DIR.
 *
 * @param string $file Absolute file path from error_get_last().
 * @return string Plugin folder slug, or '' if not resolvable.
 */
function mrn_recovery_agent_slug_from_error_file( $file ) {
	if ( ! defined( 'WP_PLUGIN_DIR' ) || '' === $file ) {
		return '';
	}

	$plugin_dir = wp_normalize_path( WP_PLUGIN_DIR );
	$file       = wp_normalize_path( $file );

	if ( 0 !== strpos( $file, trailingslashit( $plugin_dir ) ) ) {
		return '';
	}

	$relative = substr( $file, strlen( trailingslashit( $plugin_dir ) ) );
	$parts    = explode( '/', $relative );

	return isset( $parts[0] ) ? sanitize_file_name( $parts[0] ) : '';
}

/**
 * Read the pending-update marker slug, if any and not expired.
 *
 * @return string
 */
function mrn_recovery_agent_pending_update_slug() {
	$pending = get_transient( 'mrn_recovery_agent_pending_update' );

	return is_string( $pending ) ? $pending : '';
}

/**
 * Record a fatal error that was NOT tied to a QA Engine-tracked update, so
 * the next authenticated /status poll can still surface it. Bounded to a
 * small rolling list to avoid unbounded option growth.
 *
 * @param string $slug  Implicated plugin slug.
 * @param array  $error error_get_last() payload.
 * @return void
 */
function mrn_recovery_agent_record_untracked_fatal( $slug, $error ) {
	$log   = get_option( 'mrn_recovery_agent_untracked_fatals', array() );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array(
		'slug'    => $slug,
		'message' => isset( $error['message'] ) ? (string) $error['message'] : '',
		'file'    => isset( $error['file'] ) ? (string) $error['file'] : '',
		'line'    => isset( $error['line'] ) ? (int) $error['line'] : 0,
		'time'    => time(),
	);
	$log   = array_slice( $log, -10 );

	update_option( 'mrn_recovery_agent_untracked_fatals', $log, false );
}

// ---------------------------------------------------------------------
// REST API surface: mrn-recovery/v1
// ---------------------------------------------------------------------

add_action( 'rest_api_init', 'mrn_recovery_agent_register_routes' );

/**
 * Register the mrn-recovery/v1 REST routes.
 *
 * @return void
 */
function mrn_recovery_agent_register_routes() {
	register_rest_route(
		'mrn-recovery/v1',
		'/status',
		array(
			'methods'             => 'GET',
			'callback'            => 'mrn_recovery_agent_route_status',
			'permission_callback' => 'mrn_recovery_agent_check_bearer_auth',
		)
	);

	register_rest_route(
		'mrn-recovery/v1',
		'/action',
		array(
			'methods'             => 'POST',
			'callback'            => 'mrn_recovery_agent_route_action',
			'permission_callback' => 'mrn_recovery_agent_check_bearer_auth',
			'args'                => array(
				'action'      => array(
					'required' => true,
					'type'     => 'string',
					'enum'     => array( 'disable_plugin', 'enable_plugin', 'switch_theme', 'clear_cache', 'restore_snapshot' ),
				),
				'target'      => array(
					'required' => false,
					'type'     => 'string',
				),
				'reason'      => array(
					'required' => false,
					'type'     => 'string',
				),
				'snapshot_id' => array(
					'required' => false,
					'type'     => 'string',
				),
				'scope'       => array(
					'required' => false,
					'type'     => 'string',
					'enum'     => array( 'code', 'htaccess', 'both' ),
				),
			),
		)
	);

	register_rest_route(
		'mrn-recovery/v1',
		'/snapshot',
		array(
			'methods'             => 'POST',
			'callback'            => 'mrn_recovery_agent_route_snapshot',
			'permission_callback' => 'mrn_recovery_agent_check_bearer_auth',
			'args'                => array(
				'update_type' => array(
					'required' => true,
					'type'     => 'string',
				),
				'slug'        => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		)
	);
}

/**
 * Shared REST permission callback: bearer token compared against the
 * per-site derived recovery key. Fails closed (denies) if the key has not
 * been provisioned on this site yet — this plugin ships inert until a
 * separate provisioning mechanism defines MRN_RECOVERY_KEY.
 *
 * @param WP_REST_Request $request Request object.
 * @return bool
 */
function mrn_recovery_agent_check_bearer_auth( $request ) {
	if ( ! defined( 'MRN_RECOVERY_KEY' ) || ! is_string( MRN_RECOVERY_KEY ) || '' === MRN_RECOVERY_KEY ) {
		return false;
	}

	$header = $request->get_header( 'authorization' );
	if ( ! is_string( $header ) || 0 !== stripos( $header, 'Bearer ' ) ) {
		return false;
	}

	$token = trim( substr( $header, 7 ) );

	return hash_equals( MRN_RECOVERY_KEY, $token );
}

/**
 * GET /status — reachability + health signal collection.
 *
 * @return WP_REST_Response
 */
function mrn_recovery_agent_route_status() {
	$checks = mrn_recovery_agent_run_health_checks();

	return new WP_REST_Response(
		array(
			'healthy' => $checks['healthy'],
			'checks'  => $checks['checks'],
			'version' => MRN_RECOVERY_AGENT_VERSION,
			'time'    => time(),
		),
		200
	);
}

/**
 * Run the health-check battery. The mere fact this function executes at all
 * (i.e. a request reached this REST callback) already proves WordPress
 * bootstrapped through mu-plugins and REST routing. The remaining checks
 * differentiate which layer, if any, is broken.
 *
 * @return array{healthy: bool, checks: array}
 */
function mrn_recovery_agent_run_health_checks() {
	$checks = array();

	$home                = mrn_recovery_agent_probe_url( home_url( '/' ) );
	$checks['home_page'] = $home;

	$rest                = mrn_recovery_agent_probe_url( rest_url( '/' ) );
	$checks['rest_root'] = $rest;

	$login              = mrn_recovery_agent_probe_url( wp_login_url() );
	$checks['wp_login'] = array(
		'reachable' => isset( $login['status'] ) && $login['status'] > 0,
		'status'    => isset( $login['status'] ) ? $login['status'] : 0,
	);

	$checks['memory'] = array(
		'peak_bytes'  => memory_get_peak_usage( true ),
		'limit_bytes' => mrn_recovery_agent_php_memory_limit_bytes(),
	);

	$checks['recent_fatal_log_tail'] = mrn_recovery_agent_tail_error_log();

	$checks['untracked_fatals'] = get_option( 'mrn_recovery_agent_untracked_fatals', array() );

	// Healthy requires: home page has no fatal-error signature AND (either
	// the REST root works, or — if it doesn't — that's still reported so
	// the caller can distinguish "front-end broken" from "rewrite rules
	// broken" rather than lumping both under one boolean.
	$healthy = empty( $home['has_error_signature'] ) && ( $home['status'] >= 200 && $home['status'] < 500 );

	return array(
		'healthy' => $healthy,
		'checks'  => $checks,
	);
}

/**
 * Fetch a URL with a short timeout and scan the body for verbatim PHP error
 * signatures. Many hosts (including SiteGround tiers) leave display_errors
 * on, so this is a real signal, not just a status-code check.
 *
 * @param string $url URL to probe.
 * @return array{status:int, has_error_signature:bool}
 */
function mrn_recovery_agent_probe_url( $url ) {
	$response = wp_remote_get(
		$url,
		array(
			'timeout'   => 8,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false, $url ), // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Reusing WP core's own local-loopback filter documented in class-wp-http-streams.php.
		)
	);

	if ( is_wp_error( $response ) ) {
		return array(
			'status'              => 0,
			'has_error_signature' => false,
			'error'               => $response->get_error_message(),
		);
	}

	$status = (int) wp_remote_retrieve_response_code( $response );
	$body   = (string) wp_remote_retrieve_body( $response );

	$signatures    = array( 'Fatal error', 'Parse error', 'Uncaught Error', 'Recoverable fatal error' );
	$has_signature = false;
	foreach ( $signatures as $signature ) {
		if ( false !== strpos( $body, $signature ) ) {
			$has_signature = true;
			break;
		}
	}

	return array(
		'status'              => $status,
		'has_error_signature' => $has_signature,
	);
}

/**
 * Resolve the configured PHP memory limit in bytes.
 *
 * @return int
 */
function mrn_recovery_agent_php_memory_limit_bytes() {
	$limit = ini_get( 'memory_limit' );
	if ( ! is_string( $limit ) || '-1' === $limit ) {
		return -1;
	}

	return wp_convert_hr_to_bytes( $limit );
}

/**
 * Best-effort tail-read of a PHP error log for the most recent fatal or
 * memory-exhaustion line. The readable log path varies by host and PHP
 * version — this is explicitly best-effort and must never be treated as a
 * hard gate. Returns null when no log path could be resolved or read.
 *
 * @return string|null
 */
function mrn_recovery_agent_tail_error_log() {
	$candidates = array_filter(
		array(
			ini_get( 'error_log' ),
			defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/debug.log' : '',
		)
	);

	foreach ( $candidates as $path ) {
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			continue;
		}

		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Best-effort tail read; WP_Filesystem is not guaranteed available in this failure path.
		if ( ! $handle ) {
			continue;
		}

		$tail = mrn_recovery_agent_read_tail( $handle, 8192 );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- See fopen() above.

		$lines = array_filter(
			explode( "\n", $tail ),
			static function ( $line ) {
				return false !== strpos( $line, 'Fatal error' ) || false !== strpos( $line, 'Allowed memory size' );
			}
		);

		if ( ! empty( $lines ) ) {
			return trim( (string) end( $lines ) );
		}
	}

	return null;
}

/**
 * Read the last N bytes of an open file handle.
 *
 * @param resource $handle Open file handle.
 * @param int      $bytes  Number of trailing bytes to read.
 * @return string
 */
function mrn_recovery_agent_read_tail( $handle, $bytes ) {
	fseek( $handle, 0, SEEK_END );
	$size = ftell( $handle );
	$seek = max( 0, $size - $bytes );
	fseek( $handle, $seek );

	return (string) fread( $handle, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- See fopen() in mrn_recovery_agent_tail_error_log().
}

/**
 * POST /action — fixed allowlist dispatch. Refuses mutation on an already
 * healthy site (self-enforced, not just trusted from the caller).
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function mrn_recovery_agent_route_action( $request ) {
	$action = (string) $request->get_param( 'action' );
	$target = (string) $request->get_param( 'target' );
	$reason = (string) $request->get_param( 'reason' );

	// Every /action mutation — including restore_snapshot — is gated on the
	// site currently being unhealthy. Only the separate /snapshot *capture*
	// route (a different function, never calls this gate) is exempt, since
	// capturing a backup is passive and safe regardless of current health.
	$health = mrn_recovery_agent_run_health_checks();
	if ( $health['healthy'] ) {
		return new WP_REST_Response(
			array(
				'ok'      => false,
				'message' => 'Refusing to mutate: site currently reports healthy.',
			),
			409
		);
	}

	switch ( $action ) {
		case 'disable_plugin':
			$result = mrn_recovery_agent_disable_plugin( $target, $reason );
			break;
		case 'enable_plugin':
			$result = mrn_recovery_agent_enable_plugin( $target );
			break;
		case 'switch_theme':
			$result = mrn_recovery_agent_switch_to_default_theme();
			break;
		case 'clear_cache':
			$result = mrn_recovery_agent_clear_cache();
			break;
		case 'restore_snapshot':
			$scope_param = $request->get_param( 'scope' );
			$result      = mrn_recovery_agent_restore_snapshot(
				(string) $request->get_param( 'snapshot_id' ),
				$scope_param ? (string) $scope_param : 'both'
			);
			break;
		default:
			$result = array(
				'ok'      => false,
				'message' => 'Unknown action.',
			);
	}

	mrn_recovery_agent_audit_log( $action, $target, $reason, $result );

	return new WP_REST_Response( $result, ! empty( $result['ok'] ) ? 200 : 500 );
}

/**
 * Disable a single plugin by removing it from the active_plugins option
 * directly — never calls deactivate_plugin(), since that hook chain could
 * itself be part of what is fataling. Also skips MainWP's own filesystem
 * inventory sync by leaving the plugin's files in place.
 *
 * @param string $slug   Plugin folder slug.
 * @param string $reason Human-readable reason, stored for the audit log.
 * @return array{ok: bool, message: string}
 */
function mrn_recovery_agent_disable_plugin( $slug, $reason = '' ) {
	$slug = sanitize_file_name( $slug );
	if ( '' === $slug ) {
		return array(
			'ok'      => false,
			'message' => 'No plugin slug given.',
		);
	}

	$active   = (array) get_option( 'active_plugins', array() );
	$matching = array_values(
		array_filter(
			$active,
			static function ( $entry ) use ( $slug ) {
				return 0 === strpos( (string) $entry, $slug . '/' );
			}
		)
	);

	if ( empty( $matching ) ) {
		return array(
			'ok'      => true,
			'message' => 'Plugin was not active.',
		);
	}

	$remaining = array_values( array_diff( $active, $matching ) );
	update_option( 'active_plugins', $remaining );

	if ( is_multisite() ) {
		$network_active = (array) get_site_option( 'active_sitewide_plugins', array() );
		foreach ( $matching as $entry ) {
			unset( $network_active[ $entry ] );
		}
		update_site_option( 'active_sitewide_plugins', $network_active );
	}

	update_option(
		'mrn_recovery_agent_last_disabled',
		array(
			'slug'   => $slug,
			'entry'  => $matching[0],
			'time'   => time(),
			'reason' => $reason,
		),
		false
	);

	return array(
		'ok'             => true,
		'message'        => 'Disabled ' . $matching[0] . '.',
		'disabled_entry' => $matching[0],
	);
}

/**
 * Re-enable a plugin previously disabled by mrn_recovery_agent_disable_plugin().
 *
 * @param string $slug Plugin folder slug.
 * @return array{ok: bool, message: string}
 */
function mrn_recovery_agent_enable_plugin( $slug ) {
	$last = get_option( 'mrn_recovery_agent_last_disabled', array() );
	$slug = sanitize_file_name( $slug );

	$entry = ( is_array( $last ) && isset( $last['slug'], $last['entry'] ) && $last['slug'] === $slug )
		? $last['entry']
		: mrn_recovery_agent_guess_plugin_entry( $slug );

	if ( '' === (string) $entry ) {
		return array(
			'ok'      => false,
			'message' => 'Could not resolve plugin file for slug.',
		);
	}

	$active = (array) get_option( 'active_plugins', array() );
	if ( ! in_array( $entry, $active, true ) ) {
		$active[] = $entry;
		update_option( 'active_plugins', $active );
	}

	return array(
		'ok'      => true,
		'message' => 'Enabled ' . $entry . '.',
	);
}

/**
 * Best-effort resolution of a plugin's "folder/file.php" entry from its
 * folder slug alone, by scanning the plugins directory.
 *
 * @param string $slug Plugin folder slug.
 * @return string
 */
function mrn_recovery_agent_guess_plugin_entry( $slug ) {
	if ( '' === $slug || ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$all = get_plugins( '/' . $slug );
	foreach ( $all as $file => $data ) {
		return $slug . '/' . $file;
	}

	return '';
}

/**
 * Switch the active theme to whichever default WordPress theme is installed
 * (the newest "twenty*" theme found), as a known-good fallback.
 *
 * @return array{ok: bool, message: string}
 */
function mrn_recovery_agent_switch_to_default_theme() {
	$themes     = wp_get_themes();
	$candidates = array_filter(
		array_keys( $themes ),
		static function ( $slug ) {
			return (bool) preg_match( '/^twenty/', $slug );
		}
	);
	rsort( $candidates );

	if ( empty( $candidates ) ) {
		return array(
			'ok'      => false,
			'message' => 'No known-good default theme is installed on this site.',
		);
	}

	$previous = get_option( 'stylesheet' );
	switch_theme( $candidates[0] );

	return array(
		'ok'      => true,
		'message' => 'Switched theme to ' . $candidates[0] . ' (was ' . $previous . ').',
	);
}

/**
 * Clear WordPress transients and, where present, common object-cache /
 * page-cache flush hooks.
 *
 * @return array{ok: bool, message: string}
 */
function mrn_recovery_agent_clear_cache() {
	global $wpdb;

	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk transient sweep; targeted option-name deletion, not a general query.
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%' OR option_name LIKE '\\_transient\\_timeout\\_%'"
	);

	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}

	do_action( 'mrn_recovery_agent_clear_cache' );

	return array(
		'ok'      => true,
		'message' => 'Cleared transients and object cache.',
	);
}

// ---------------------------------------------------------------------
// Snapshot capture / restore
// ---------------------------------------------------------------------

/**
 * Root directory for stored snapshots. Prefers a location outside the
 * public webroot; falls back to an in-webroot, deny-all-guarded directory
 * for hosts where that is not writable.
 *
 * @return string
 */
function mrn_recovery_agent_snapshot_dir() {
	$outside = trailingslashit( dirname( ABSPATH ) ) . 'mrn-recovery-snapshots';
	if ( wp_mkdir_p( $outside ) && wp_is_writable( $outside ) ) {
		return $outside;
	}

	$inside = trailingslashit( WP_CONTENT_DIR ) . 'mrn-recovery-snapshots';
	if ( wp_mkdir_p( $inside ) ) {
		$htaccess = trailingslashit( $inside ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Require all denied\ndeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Guard file for a directory only this plugin writes to.
		}
		$index = trailingslashit( $inside ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	return $inside;
}

/**
 * POST /snapshot — zip a plugin/theme's code plus .htaccess and the
 * rewrite_rules option, ahead of QA Engine triggering the real update.
 * Exempt from the must-be-unhealthy gate: capturing is passive and safe.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function mrn_recovery_agent_route_snapshot( $request ) {
	$slug = sanitize_file_name( (string) $request->get_param( 'slug' ) );
	$type = (string) $request->get_param( 'update_type' );

	if ( '' === $slug ) {
		return new WP_REST_Response(
			array(
				'ok'      => false,
				'message' => 'No slug given.',
			),
			400
		);
	}

	$source_dir = ( 'theme' === $type )
		? trailingslashit( get_theme_root() ) . $slug
		: trailingslashit( WP_PLUGIN_DIR ) . $slug;

	if ( ! is_dir( $source_dir ) ) {
		return new WP_REST_Response(
			array(
				'ok'      => false,
				'message' => 'Source directory not found: ' . $source_dir,
			),
			404
		);
	}

	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_REST_Response(
			array(
				'ok'      => false,
				'message' => 'ZipArchive is not available on this host.',
			),
			500
		);
	}

	$snapshot_id = $slug . '-' . gmdate( 'Ymd-His' );
	$dir         = trailingslashit( mrn_recovery_agent_snapshot_dir() );
	$zip_path    = $dir . $snapshot_id . '.zip';

	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		return new WP_REST_Response(
			array(
				'ok'      => false,
				'message' => 'Could not create snapshot archive.',
			),
			500
		);
	}

	mrn_recovery_agent_zip_add_dir( $zip, $source_dir, 'code' );

	$htaccess_path = trailingslashit( ABSPATH ) . '.htaccess';
	if ( file_exists( $htaccess_path ) ) {
		$zip->addFile( $htaccess_path, 'htaccess/.htaccess' );
	}
	$zip->addFromString( 'htaccess/rewrite_rules_option.txt', (string) get_option( 'rewrite_rules', '' ) );

	$zip->close();

	mrn_recovery_agent_prune_snapshots( $dir, $slug, 3 );

	return new WP_REST_Response(
		array(
			'ok'          => true,
			'snapshot_id' => $snapshot_id,
			'message'     => 'Snapshot captured.',
		),
		200
	);
}

/**
 * Recursively add a directory's contents to an open ZipArchive.
 *
 * @param ZipArchive $zip       Open archive.
 * @param string     $source    Absolute source directory.
 * @param string     $zip_root  Path prefix inside the archive.
 * @return void
 */
function mrn_recovery_agent_zip_add_dir( $zip, $source, $zip_root ) {
	$source = untrailingslashit( $source );
	$files  = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $files as $file ) {
		$local_path = $zip_root . '/' . substr( $file->getPathname(), strlen( $source ) + 1 );
		if ( $file->isDir() ) {
			$zip->addEmptyDir( $local_path );
		} else {
			$zip->addFile( $file->getPathname(), $local_path );
		}
	}
}

/**
 * Keep only the newest N snapshots for a given slug, deleting older ones.
 *
 * @param string $dir    Snapshot directory.
 * @param string $slug   Plugin/theme slug.
 * @param int    $window Number of snapshots to retain.
 * @return void
 */
function mrn_recovery_agent_prune_snapshots( $dir, $slug, $window ) {
	$matches = glob( trailingslashit( $dir ) . $slug . '-*.zip' );
	if ( ! is_array( $matches ) || count( $matches ) <= $window ) {
		return;
	}

	usort(
		$matches,
		static function ( $a, $b ) {
			return filemtime( $a ) <=> filemtime( $b );
		}
	);

	$excess = array_slice( $matches, 0, count( $matches ) - $window );
	foreach ( $excess as $path ) {
		wp_delete_file( $path );
	}
}

/**
 * Restore a previously captured snapshot's code and/or .htaccess.
 *
 * @param string $snapshot_id Snapshot identifier returned by /snapshot.
 * @param string $scope       One of 'code', 'htaccess', 'both'.
 * @return array{ok: bool, message: string}
 */
function mrn_recovery_agent_restore_snapshot( $snapshot_id, $scope ) {
	$snapshot_id = sanitize_file_name( $snapshot_id );
	if ( '' === $snapshot_id ) {
		return array(
			'ok'      => false,
			'message' => 'No snapshot_id given.',
		);
	}

	$dir      = trailingslashit( mrn_recovery_agent_snapshot_dir() );
	$zip_path = $dir . $snapshot_id . '.zip';
	if ( ! file_exists( $zip_path ) ) {
		return array(
			'ok'      => false,
			'message' => 'Snapshot not found: ' . $snapshot_id,
		);
	}

	if ( ! class_exists( 'ZipArchive' ) ) {
		return array(
			'ok'      => false,
			'message' => 'ZipArchive is not available on this host.',
		);
	}

	$slug = preg_replace( '/-\d{8}-\d{6}$/', '', $snapshot_id );

	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		return array(
			'ok'      => false,
			'message' => 'Could not open snapshot archive.',
		);
	}

	$restored = array();

	if ( in_array( $scope, array( 'code', 'both' ), true ) ) {
		$target_dir = trailingslashit( WP_PLUGIN_DIR ) . $slug;
		mrn_recovery_agent_extract_prefix( $zip, 'code/', $target_dir );
		$restored[] = 'code';
	}

	if ( in_array( $scope, array( 'htaccess', 'both' ), true ) ) {
		$htaccess_contents = $zip->getFromName( 'htaccess/.htaccess' );
		if ( false !== $htaccess_contents ) {
			file_put_contents( trailingslashit( ABSPATH ) . '.htaccess', $htaccess_contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Restoring a captured snapshot; WP_Filesystem may itself be part of what is broken.
		}
		$restored[] = 'htaccess';
	}

	$zip->close();

	return array(
		'ok'      => true,
		'message' => 'Restored: ' . implode( ', ', $restored ) . '.',
	);
}

/**
 * Extract every entry under a given prefix in an open ZipArchive into a
 * target directory, stripping the prefix.
 *
 * @param ZipArchive $zip        Open archive.
 * @param string     $prefix     Prefix to strip (e.g. "code/").
 * @param string     $target_dir Absolute destination directory.
 * @return void
 */
function mrn_recovery_agent_extract_prefix( $zip, $prefix, $target_dir ) {
	wp_mkdir_p( $target_dir );

	for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive's own public property name, not ours to rename.
		$name = $zip->getNameIndex( $i );
		if ( 0 !== strpos( $name, $prefix ) ) {
			continue;
		}

		$relative = substr( $name, strlen( $prefix ) );
		if ( '' === $relative ) {
			continue;
		}

		$destination = trailingslashit( $target_dir ) . $relative;
		if ( '/' === substr( $name, -1 ) ) {
			wp_mkdir_p( $destination );
			continue;
		}

		wp_mkdir_p( dirname( $destination ) );
		copy( 'zip://' . $zip->filename . '#' . $name, $destination ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Zip-stream extraction; WP_Filesystem cannot read zip:// streams.
	}
}

// ---------------------------------------------------------------------
// Pending-update marker (set by QA Engine's own snapshot call so the
// shutdown tripwire can scope its auto-heal to tracked updates only)
// ---------------------------------------------------------------------

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'mrn-recovery/v1',
			'/mark-pending',
			array(
				'methods'             => 'POST',
				'callback'            => function ( $request ) {
					$slug = sanitize_file_name( (string) $request->get_param( 'slug' ) );
					set_transient( 'mrn_recovery_agent_pending_update', $slug, 5 * MINUTE_IN_SECONDS );

					return new WP_REST_Response( array( 'ok' => true ), 200 );
				},
				'permission_callback' => 'mrn_recovery_agent_check_bearer_auth',
				'args'                => array(
					'slug' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
	}
);

/**
 * Append an entry to the audit log, shipped to error_log immediately (not
 * just stored locally, since local state may itself be unreliable during
 * the failure this plugin exists to handle).
 *
 * @param string $action Action name.
 * @param string $target Action target.
 * @param string $reason Reason string.
 * @param array  $result Result payload.
 * @return void
 */
function mrn_recovery_agent_audit_log( $action, $target, $reason, $result ) {
	$entry = array(
		'time'    => time(),
		'action'  => $action,
		'target'  => $target,
		'reason'  => $reason,
		'ok'      => ! empty( $result['ok'] ),
		'message' => isset( $result['message'] ) ? $result['message'] : '',
	);

	$log   = get_option( 'mrn_recovery_agent_audit_log', array() );
	$log   = is_array( $log ) ? $log : array();
	$log[] = $entry;
	$log   = array_slice( $log, -50 );
	update_option( 'mrn_recovery_agent_audit_log', $log, false );

	error_log( 'mrn-recovery-agent: ' . wp_json_encode( $entry ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Deliberate off-host-visible audit trail; see AGENTS.md.
}

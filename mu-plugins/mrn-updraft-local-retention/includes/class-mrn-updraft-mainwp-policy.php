<?php
/**
 * Closed backup-policy operations over MainWP Child's authenticated transport.
 *
 * @package MRN_Updraft_Backup_Policy
 */

defined( 'ABSPATH' ) || exit;

/** No public REST route, arbitrary option names, code, paths, or credentials. */
final class MRN_Updraft_MainWP_Policy {

	const REQUEST_KEY = 'mrn_updraft_policy_request';
	const PROFILE     = 'mrn-7d-4w-12p-395-v1';
	const MAX_HISTORY = 2000;
	const MAX_FILES   = 100;
	const PLAN_TTL    = 600;
	const LOCK_KEY    = 'mrn_updraft_policy_operation_lock';
	const RECEIPTS    = 'mrn_updraft_policy_operation_receipts';
	const ENTITIES    = array( 'db', 'plugins', 'themes', 'uploads', 'others', 'mu-plugins' );

	/** Register only the post-authentication MainWP Child hook. */
	public static function init() {
		add_filter( 'mainwp_child_extra_execution', array( __CLASS__, 'dispatch' ), 20, 2 );
		add_filter( 'updraft_backupnow_options', array( __CLASS__, 'mainwp_backup_options' ), 9, 2 );
	}

	/**
	 * Preserve the fixed MRN label when MainWP Child omits Updraft's request argument.
	 *
	 * This filter starts no backup and grants no permission. MainWP authenticates
	 * backup_now before Updraft invokes it. Never relabel existing backup history.
	 *
	 * @param array $options Native options for the new backup.
	 * @param array $request Native Updraft request (empty in MainWP Child 6.2.1).
	 * @return array
	 */
	public static function mainwp_backup_options( $options, $request ) {
		if ( ! is_array( $options ) || array() !== $request || isset( $options['label'] ) || empty( $options['use_nonce'] ) || ! is_string( $options['use_nonce'] ) || ! preg_match( '/^[0-9a-f]{12}$/D', $options['use_nonce'] ) || ! array_key_exists( 'nocloud', $options ) || false !== $options['nocloud'] ) {
			return $options;
		}
		$fields = array_fill_keys( array( 'function', 'mwp_action', 'backupnow_nofiles', 'backupnow_label', 'mainwpsignature', 'backupnow_nodb', 'backupnow_nocloud', 'onlythisfileentity', 'onlythesetableentities' ), true );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Existing signed MainWP backup flow invokes this metadata-only filter; it cannot start a backup. Never copy unrelated upload payloads.
		$post = wp_unslash( array_intersect_key( $_POST, $fields ) );
		if ( 'updraftplus' === ( $post['function'] ?? '' ) && 'backup_now' === ( $post['mwp_action'] ?? '' ) && '1' === ( $post['backupnow_nofiles'] ?? '' ) && 'MRN MainWP database-only backup' === ( $post['backupnow_label'] ?? '' ) && ! empty( $post['mainwpsignature'] ) && is_string( $post['mainwpsignature'] ) && empty( $post['backupnow_nodb'] ) && empty( $post['backupnow_nocloud'] ) && empty( $post['onlythisfileentity'] ) && empty( $post['onlythesetableentities'] ) ) {
			$options['label'] = 'MRN MainWP database-only backup';
		}
		return $options;
	}

	/**
	 * @param array $information Previous Child response.
	 * @param array $post        Signature-verified MainWP request.
	 * @return array
	 */
	public static function dispatch( $information, $post ) {
		if ( ! is_array( $post ) || ! isset( $post[ self::REQUEST_KEY ] ) ) {
			return $information;
		}
		if ( 'extra_execution' !== ( $post['function'] ?? '' ) ) {
			return self::error( 'invalid_transport' );
		}
		$raw = $post[ self::REQUEST_KEY ];
		if ( ! is_string( $raw ) || strlen( $raw ) > 16384 ) {
			return self::error( 'invalid_request' );
		}
		$request = json_decode( wp_unslash( $raw ), true );
		if ( ! self::keys( $request, array( 'protocol', 'operation', 'expected_host', 'request_ref', 'payload' ) ) || 1 !== $request['protocol'] || ! is_string( $request['expected_host'] ) || strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) !== $request['expected_host'] ) {
			return self::error( 'target_mismatch' );
		}
		$operation = $request['operation'];
		if ( ! in_array( $operation, array( 'inspect', 'preview_policy', 'apply_policy', 'preview_decommission', 'decommission_policy', 'preview_cleanup', 'execute_cleanup' ), true ) || ! is_array( $request['payload'] ) ) {
			return self::error( 'invalid_request' );
		}
		$mutating = in_array( $operation, array( 'apply_policy', 'decommission_policy', 'execute_cleanup' ), true );
		if ( $mutating && ( ! is_string( $request['request_ref'] ) || ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $request['request_ref'] ) ) ) {
			return self::error( 'invalid_request_ref' );
		}
		if ( ! $mutating && null !== $request['request_ref'] ) {
			return self::error( 'invalid_request_ref' );
		}
		if ( ! class_exists( 'UpdraftPlus_Backup_History' ) || ! class_exists( 'UpdraftPlus_Storage_Methods_Interface' ) ) {
			return self::error( 'updraft_unavailable' );
		}
		if ( ! $mutating ) {
			try {
				return self::response( self::run( $operation, $request['payload'] ) );
			} catch ( Throwable $exception ) {
				return self::error( 'inspection_unavailable' );
			}
		}
		$digest   = self::hash( $request );
		$receipts = get_option( self::RECEIPTS, array() );
		if ( ! is_array( $receipts ) ) {
			return self::error( 'receipt_storage_unavailable' );
		}
		if ( isset( $receipts[ $request['request_ref'] ] ) ) {
			$receipt = $receipts[ $request['request_ref'] ];
			return isset( $receipt['digest'], $receipt['response'] ) && hash_equals( $receipt['digest'], $digest ) ? $receipt['response'] : self::error( 'request_conflict' );
		}
		if ( ! add_option( self::LOCK_KEY, array( 'request_ref' => $request['request_ref'], 'started_at' => time() ), '', false ) ) {
			return self::error( 'operation_locked' );
		}
		try {
			$result = self::response( self::run( $operation, $request['payload'] ) );
			// Persist uncertain outcomes as well: retrying the same request never repeats a write.
			if ( count( $receipts ) >= 100 ) {
				array_shift( $receipts );
			}
			$receipts[ $request['request_ref'] ] = array( 'digest' => $digest, 'response' => $result );
			if ( ! update_option( self::RECEIPTS, $receipts, false ) && get_option( self::RECEIPTS ) !== $receipts ) {
				return self::error( 'outcome_unknown' );
			}
			return $result;
		} catch ( Throwable $exception ) {
			$result = self::error( 'outcome_unknown' );
			$receipts[ $request['request_ref'] ] = array( 'digest' => $digest, 'response' => $result );
			update_option( self::RECEIPTS, $receipts, false );
			return $result;
		} finally {
			delete_option( self::LOCK_KEY );
		}
	}

	/** @param string $operation Closed operation. @param array $payload Closed data. @return array|WP_Error */
	private static function run( $operation, $payload ) {
		if ( 'inspect' === $operation ) {
			if ( ! self::keys( $payload, array( 'offset', 'limit' ) ) || ! is_int( $payload['offset'] ) || $payload['offset'] < 0 || ! is_int( $payload['limit'] ) || $payload['limit'] < 1 || $payload['limit'] > 100 ) {
				return self::failure( 'invalid_request' );
			}
			return self::inspect( $payload['offset'], $payload['limit'] );
		}
		if ( 'preview_policy' === $operation ) {
			if ( ! self::keys( $payload, array( 'migrate_prefix' ) ) || ! is_bool( $payload['migrate_prefix'] ) ) {
				return self::failure( 'invalid_request' );
			}
			return self::policy_plan( $payload['migrate_prefix'] );
		}
		if ( 'apply_policy' === $operation ) {
			if ( ! self::keys( $payload, array( 'migrate_prefix', 'plan_sha256', 'backup_nonce' ) ) || ! is_bool( $payload['migrate_prefix'] ) || ! self::sha( $payload['plan_sha256'] ) ) {
				return self::failure( 'invalid_request' );
			}
			$plan = self::policy_plan( $payload['migrate_prefix'] );
			if ( is_wp_error( $plan ) ) {
				return $plan;
			}
			if ( ! $plan['ready'] || ! hash_equals( $plan['plan_sha256'], $payload['plan_sha256'] ) ) {
				return self::failure( 'policy_precondition_changed' );
			}
			$backup = self::verify_backup( $payload['backup_nonce'] );
			if ( is_wp_error( $backup ) ) {
				return $backup;
			}
			if ( $plan['prefix_change'] ) {
				self::migrate_prefix( $plan['prefix_change'] );
			}
			if ( mrn_updraft_backup_policy_is_excluded() ) {
				delete_option( MRN_UPDRAFT_BACKUP_POLICY_EXCLUDED_OPTION );
				if ( mrn_updraft_backup_policy_is_excluded() ) {
					return self::failure( 'policy_exclusion_clear_failed' );
				}
			}
			mrn_updraft_backup_policy_enforce_settings();
			mrn_updraft_local_retention_repair_backup_schedules();
			$status = mrn_updraft_backup_policy_get_retention_status();
			return array( 'status' => ! empty( $status['compliant'] ) ? 'applied' : 'verification_failed', 'policy' => $status, 'storage' => self::public_storage(), 'precondition' => self::generation() );
		}
		if ( 'preview_decommission' === $operation ) {
			if ( array() !== $payload ) {
				return self::failure( 'invalid_request' );
			}
			return self::decommission_plan( time() );
		}
		if ( 'decommission_policy' === $operation ) {
			if ( ! self::keys( $payload, array( 'plan_sha256', 'as_of', 'backup_nonce' ) ) || ! self::sha( $payload['plan_sha256'] ) || ! is_int( $payload['as_of'] ) || $payload['as_of'] > time() || $payload['as_of'] < time() - self::PLAN_TTL ) {
				return self::failure( 'invalid_or_expired_plan' );
			}
			$plan = self::decommission_plan( $payload['as_of'] );
			if ( is_wp_error( $plan ) ) {
				return $plan;
			}
			if ( ! $plan['ready'] || ! hash_equals( $plan['plan_sha256'], $payload['plan_sha256'] ) ) {
				return self::failure( 'decommission_precondition_changed' );
			}
			$backup = self::verify_backup( $payload['backup_nonce'] );
			if ( is_wp_error( $backup ) ) {
				return $backup;
			}
			return self::decommission( $plan );
		}
		if ( 'preview_cleanup' === $operation ) {
			if ( array() !== $payload ) {
				return self::failure( 'invalid_request' );
			}
			return self::cleanup_plan( time() );
		}
		if ( ! self::keys( $payload, array( 'plan_sha256', 'as_of', 'backup_nonce' ) ) || ! self::sha( $payload['plan_sha256'] ) || ! is_int( $payload['as_of'] ) || $payload['as_of'] > time() || $payload['as_of'] < time() - self::PLAN_TTL ) {
			return self::failure( 'invalid_or_expired_plan' );
		}
		$plan = self::cleanup_plan( $payload['as_of'] );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		if ( ! $plan['ready'] || ! hash_equals( $plan['plan_sha256'], $payload['plan_sha256'] ) ) {
			return self::failure( 'cleanup_precondition_changed' );
		}
		$backup = self::verify_backup( $payload['backup_nonce'] );
		return is_wp_error( $backup ) ? $backup : self::execute_cleanup( $plan );
	}

	/** Secret-free inventory; no remote rescan and no raw logs. @return array|WP_Error */
	private static function inspect( $offset, $limit ) {
		$history = self::history();
		if ( is_wp_error( $history ) ) {
			return $history;
		}
		$rows = array();
		foreach ( array_slice( $history, $offset, $limit, true ) as $timestamp => $record ) {
			$rows[] = self::record( $timestamp, $record );
		}
		return array(
			'profile' => self::PROFILE, 'policy_version' => '0.7.0',
			'site_url' => home_url( '/' ), 'generation' => self::generation(),
			'policy' => mrn_updraft_backup_policy_get_retention_status(),
			'policy_excluded' => mrn_updraft_backup_policy_is_excluded(),
			'remote_services' => self::active_services(),
			'settings' => self::settings(),
			'storage' => self::public_storage(), 'history_count' => count( $history ),
			'backups' => $rows, 'next_offset' => $offset + count( $rows ) < count( $history ) ? $offset + count( $rows ) : null,
			'remote_objects_verified' => false,
		);
	}

	/** @return array|WP_Error Bounded, native history without importing remote sets. */
	private static function history() {
		$history = UpdraftPlus_Backup_History::get_history();
		if ( ! is_array( $history ) || count( $history ) > self::MAX_HISTORY ) {
			return self::failure( 'history_limit_or_schema' );
		}
		krsort( $history, SORT_NUMERIC );
		return $history;
	}

	/** @return array Private provider configuration, never returned through MainWP. */
	private static function storage() {
		$raw = mrn_updraft_backup_policy_get_option( 'updraft_s3', array() );
		$raw = is_array( $raw ) ? $raw : array();
		return isset( $raw['settings'] ) && is_array( $raw['settings'] ) ? $raw['settings'] : array( 's3' => $raw );
	}

	/** @return array|string The exact Updraft remote-destination list. */
	private static function active_services() {
		$services = mrn_updraft_backup_policy_get_option( 'updraft_service', array() );
		if ( ! is_array( $services ) ) {
			return 'invalid';
		}
		$normalized = array();
		foreach ( $services as $service ) {
			if ( ! is_string( $service ) || '' === sanitize_key( $service ) ) {
				return 'invalid';
			}
			$normalized[] = sanitize_key( $service );
		}
		return array_values( array_unique( $normalized ) );
	}

	/** @return array Redacted namespaces and activation state. */
	private static function public_storage() {
		$rows = array();
		$services = self::active_services();
		$s3_active = is_array( $services ) && in_array( 's3', $services, true );
		foreach ( self::storage() as $id => $settings ) {
			if ( ! is_array( $settings ) ) {
				continue;
			}
			$path = isset( $settings['path'] ) && is_string( $settings['path'] ) ? trim( $settings['path'], '/' ) : '';
			$rows[] = array( 'instance_id' => (string) $id, 'path' => $path, 'active' => $s3_active && ( ! isset( $settings['instance_enabled'] ) || '1' === (string) $settings['instance_enabled'] ), 'site_owned' => self::owned_path( $path ), 'canonical' => self::canonical_path( $path ), 'credentials_present' => ! empty( $settings['accesskey'] ) && ! empty( $settings['secretkey'] ) );
		}
		return $rows;
	}

	/** Require an exact site-specific namespace, never a shared bucket root. @return bool */
	private static function owned_path( $path ) {
		$host = mrn_updraft_backup_policy_get_hostname();
		$suffixes = array( '/sites/' . $host, '/sites/' . mrn_updraft_backup_policy_get_sanitized_hostname() );
		if ( ! is_string( $path ) || false !== strpos( $path, '..' ) || false !== strpos( $path, '/./' ) || false !== strpos( $path, '//' ) || ! preg_match( '#^[a-z0-9][a-z0-9.-]+/[A-Za-z0-9._/-]+$#D', $path ) ) {
			return false;
		}
		foreach ( $suffixes as $suffix ) {
			if ( '' !== $host && substr( $path, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}
		return false;
	}

	/** @return bool */
	private static function canonical_path( $path ) {
		$suffix = '/sites/' . mrn_updraft_backup_policy_get_sanitized_hostname();
		return self::owned_path( $path ) && substr( $path, -strlen( $suffix ) ) === $suffix;
	}

	/** @return string Hash contains state, never credential values. */
	private static function generation() {
		return self::hash( array( self::PROFILE, home_url( '/' ), self::active_services(), mrn_updraft_backup_policy_is_excluded(), self::public_storage(), self::settings(), self::history() ) );
	}

	/** @return array Fixed allowlist; credentials and destination options are excluded. */
	private static function settings() {
		$values = array();
		foreach ( array_keys( mrn_updraft_backup_policy_get_desired_settings() ) as $key ) {
			$values[ $key ] = mrn_updraft_backup_policy_get_option( $key, null );
		}
		$values['updraft_service'] = self::active_services();
		return $values;
	}

	/** @return array|WP_Error Hash-locked plan to disable remote destinations and MRN retention. */
	private static function decommission_plan( $as_of ) {
		$services = self::active_services();
		$raw_s3 = mrn_updraft_backup_policy_get_option( 'updraft_s3', array() );
		if ( ! is_array( $services ) || ! self::s3_storage_schema_ready( $raw_s3 ) || ! is_int( $as_of ) || $as_of > time() || $as_of < time() - self::PLAN_TTL ) {
			return self::failure( 'decommission_state_unavailable' );
		}
		$s3_after = self::public_storage();
		foreach ( $s3_after as &$instance ) {
			$instance['active'] = false;
		}
		unset( $instance );
		$plan = array(
			'profile' => self::PROFILE,
			'action' => 'disable_remote_backup_destinations_and_mrn_policy',
			'ready' => true,
			'generation' => self::generation(),
			'policy_mode_before' => mrn_updraft_backup_policy_is_excluded() ? 'excluded' : 'managed',
			'policy_mode_after' => 'excluded',
			'remote_services_before' => $services,
			'remote_services_after' => array(),
			's3_instances_before' => self::public_storage(),
			's3_instances_after' => $s3_after,
			'schedule_before' => array(
				'files' => mrn_updraft_backup_policy_get_option( 'updraft_interval', '' ),
				'database' => mrn_updraft_backup_policy_get_option( 'updraft_interval_database', '' ),
			),
			'schedule_after' => array( 'files' => 'manual', 'database' => 'manual' ),
			'historical_remote_objects_deleted' => false,
			'storage_credentials_retained_disabled' => true,
			'as_of' => $as_of,
			'expires_at' => $as_of + self::PLAN_TTL,
		);
		$plan['plan_sha256'] = self::hash( $plan );
		return $plan;
	}

	/** @param mixed $raw Updraft S3 configuration. @return bool Whether the write shape is understood. */
	private static function s3_storage_schema_ready( $raw ) {
		if ( ! is_array( $raw ) ) {
			return false;
		}
		if ( array_key_exists( 'settings', $raw ) ) {
			if ( ! is_array( $raw['settings'] ) ) {
				return false;
			}
			foreach ( $raw['settings'] as $settings ) {
				if ( ! is_array( $settings ) ) {
					return false;
				}
			}
			return true;
		}
		foreach ( $raw as $value ) {
			if ( ! is_scalar( $value ) && null !== $value ) {
				return false;
			}
		}
		return true;
	}

	/** Apply a previously previewed decommission plan without deleting cloud objects. @return array */
	private static function decommission( $plan ) {
		update_option( MRN_UPDRAFT_BACKUP_POLICY_EXCLUDED_OPTION, '1', false );
		if ( ! mrn_updraft_backup_policy_is_excluded() ) {
			return self::failure( 'policy_exclusion_write_failed' );
		}
		foreach ( array( 'updraft_backup', 'updraft_backup_database', MRN_UPDRAFT_LOCAL_RETENTION_CRON_HOOK ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		foreach ( array( 'updraft_interval', 'updraft_interval_database' ) as $key ) {
			mrn_updraft_backup_policy_update_option( $key, 'manual' );
			if ( 'manual' !== (string) mrn_updraft_backup_policy_get_option( $key, '' ) ) {
				return self::failure( 'schedule_disable_write_failed' );
			}
		}
		$raw_s3 = mrn_updraft_backup_policy_get_option( 'updraft_s3', array() );
		if ( isset( $raw_s3['settings'] ) ) {
			if ( ! is_array( $raw_s3['settings'] ) ) {
				return self::failure( 'storage_schema_unavailable' );
			}
			foreach ( $raw_s3['settings'] as &$settings ) {
				if ( ! is_array( $settings ) ) {
					return self::failure( 'storage_schema_unavailable' );
				}
				$settings['instance_enabled'] = '0';
			}
			unset( $settings );
		} elseif ( ! empty( $raw_s3 ) ) {
			$raw_s3['instance_enabled'] = '0';
		}
		mrn_updraft_backup_policy_update_option( 'updraft_s3', $raw_s3 );
		if ( mrn_updraft_backup_policy_get_option( 'updraft_s3', array() ) !== $raw_s3 ) {
			return self::failure( 'storage_disable_write_failed' );
		}
		mrn_updraft_backup_policy_update_option( 'updraft_service', array() );
		if ( array() !== mrn_updraft_backup_policy_get_option( 'updraft_service', null ) ) {
			return self::failure( 'remote_destination_disable_write_failed' );
		}
		$status = mrn_updraft_backup_policy_get_retention_status();
		$services = self::active_services();
		$storage = self::public_storage();
		foreach ( $storage as $instance ) {
			if ( ! empty( $instance['active'] ) ) {
				return self::failure( 'remote_destination_readback_failed' );
			}
		}
		foreach ( array( 'updraft_backup', 'updraft_backup_database', MRN_UPDRAFT_LOCAL_RETENTION_CRON_HOOK ) as $hook ) {
			if ( false !== wp_next_scheduled( $hook ) ) {
				return self::failure( 'scheduled_backup_hooks_readback_failed' );
			}
		}
		return array(
			'status' => 'decommissioned',
			'policy' => $status,
			'remote_services' => $services,
			's3_instances' => $storage,
			'scheduled_backup_hooks_cleared' => true,
			'historical_remote_objects_deleted' => false,
			'credentials_retained_disabled' => true,
			'precondition' => self::generation(),
			'plan_sha256' => $plan['plan_sha256'],
		);
	}

	/** @return array|WP_Error */
	private static function policy_plan( $migrate ) {
		$storage = self::public_storage();
		$active = array_values( array_filter( $storage, static function ( $row ) { return $row['active']; } ) );
		$services = self::active_services();
		$ready = array( 's3' ) === $services && count( $active ) === 1 && $active[0]['site_owned'] && $active[0]['credentials_present'] && mrn_updraft_backup_policy_has_advanced_retention();
		$change = null;
		if ( $ready && ! $active[0]['canonical'] ) {
			$ready = $migrate && 's3' !== $active[0]['instance_id'];
			if ( $ready ) {
				$path = preg_replace( '#/sites/[^/]+$#D', '/sites/' . mrn_updraft_backup_policy_get_sanitized_hostname(), $active[0]['path'] );
				$id = 's-' . md5( self::PROFILE . $path ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- non-secret provider instance identifier.
				if ( isset( self::storage()[ $id ] ) ) {
					return self::failure( 'destination_instance_conflict' );
				}
				$change = array( 'old_instance' => $active[0]['instance_id'], 'old_path' => $active[0]['path'], 'new_instance' => $id, 'new_path' => $path, 'preserve_old_instance_disabled' => true, 'move_or_delete_existing_objects' => false );
			}
		}
		$plan = array( 'profile' => self::PROFILE, 'ready' => $ready, 'generation' => self::generation(), 'settings_before' => self::settings(), 'settings_after' => mrn_updraft_backup_policy_get_desired_settings(), 'prefix_change' => $change, 'file_cap' => MRN_UPDRAFT_REMOTE_FILE_RETENTION_MAX_SETS, 'database_cap' => MRN_UPDRAFT_REMOTE_DATABASE_RETENTION_MAX_SETS, 'rules' => mrn_updraft_backup_policy_get_retention_rules(), 'expires_after_days' => MRN_UPDRAFT_ROUTINE_RETENTION_MAX_DAYS, 'schedule' => mrn_updraft_backup_policy_is_dev_environment() ? 'manual' : 'daily', 'reason' => $ready ? '' : 'premium_or_isolated_storage_required' );
		$plan['plan_sha256'] = self::hash( $plan );
		return $plan;
	}

	/** @param array $change Reviewed, internally generated namespace change. @return void */
	private static function migrate_prefix( $change ) {
		$raw = mrn_updraft_backup_policy_get_option( 'updraft_s3', array() );
		$settings = $raw['settings'][ $change['old_instance'] ];
		$raw['settings'][ $change['old_instance'] ]['instance_enabled'] = '0';
		$settings['instance_enabled'] = '1';
		$settings['path'] = $change['new_path'];
		$raw['settings'][ $change['new_instance'] ] = $settings;
		mrn_updraft_backup_policy_update_option( 'updraft_s3', $raw );
		if ( mrn_updraft_backup_policy_get_option( 'updraft_s3' ) !== $raw ) {
			throw new RuntimeException( 'Storage write not verified.' );
		}
	}

	/** @return array Public native set metadata. */
	private static function record( $timestamp, $record ) {
		$components = array();
		foreach ( self::ENTITIES as $entity ) {
			if ( ! empty( $record[ $entity ] ) ) {
				$components[ $entity ] = array_values( (array) $record[ $entity ] );
			}
		}
		return array( 'timestamp' => (int) $timestamp, 'nonce' => isset( $record['nonce'] ) ? sanitize_key( $record['nonce'] ) : '', 'components' => $components, 'label' => isset( $record['label'] ) && is_string( $record['label'] ) ? sanitize_text_field( $record['label'] ) : '', 'protected' => ! empty( $record['always_keep'] ) || ! empty( $record['keep'] ), 'imported' => isset( $record['native'] ) && false === $record['native'] || ! empty( $record['imported'] ) || ! empty( $record['remote_scan'] ) || ! empty( $record['foreign'] ), 'incremental' => ! empty( $record['incremental'] ) || ! empty( $record['incremental_sets'] ) || ! empty( $record['morefiles_linked_indexes'] ), 'storage_instances' => isset( $record['service_instance_ids']['s3'] ) ? array_values( (array) $record['service_instance_ids']['s3'] ) : array() );
	}

	/** Pure planner keeps each recent component, then newest per weekly/30-day period. */
	public static function plan_history( $history, $storage, $as_of ) {
		krsort( $history, SORT_NUMERIC );
		$owned = array();
		foreach ( $storage as $row ) {
			if ( $row['site_owned'] && $row['credentials_present'] ) {
				$owned[ $row['instance_id'] ] = $row;
			}
		}
		$seen = array();
		$candidates = array();
		$preserved = array();
		foreach ( $history as $timestamp => $record ) {
			$row = self::record( $timestamp, $record );
			$age = $as_of - (int) $timestamp;
			$services = isset( $record['service'] ) ? array_values( (array) $record['service'] ) : array();
			$ids = $row['storage_instances'];
			$reason = '';
			if ( $row['protected'] || $row['imported'] || $row['incremental'] || ! empty( $record['autobackup'] ) ) {
				$reason = 'protected_imported_or_incremental';
			} elseif ( array( 's3' ) !== $services || count( $ids ) !== 1 || ! isset( $owned[ $ids[0] ] ) || empty( $row['components'] ) || ! preg_match( '/^[a-f0-9]{12}$/D', $row['nonce'] ) ) {
				$reason = 'unproven_storage_ownership';
			} elseif ( $age <= 7 * DAY_IN_SECONDS ) {
				$reason = 'recent_seven_days';
			}
			if ( '' !== $reason ) {
				$preserved[] = array( 'timestamp' => (int) $timestamp, 'reason' => $reason );
				continue;
			}
			foreach ( $row['components'] as $entity => $files ) {
				$period = $age <= 35 * DAY_IN_SECONDS ? 'week-' . (int) floor( ( $age - 7 * DAY_IN_SECONDS - 1 ) / ( 7 * DAY_IN_SECONDS ) ) : 'period-' . (int) floor( ( $age - 35 * DAY_IN_SECONDS - 1 ) / ( 30 * DAY_IN_SECONDS ) );
				$expired = $age > MRN_UPDRAFT_ROUTINE_RETENTION_MAX_DAYS * DAY_IN_SECONDS;
				if ( ! $expired && empty( $seen[ $entity ][ $period ] ) ) {
					$seen[ $entity ][ $period ] = true;
					continue;
				}
				$valid = true;
				foreach ( $files as $file ) {
					if ( ! is_string( $file ) || basename( $file ) !== $file || ! preg_match( '/^backup_[0-9-]+_.+_' . preg_quote( $row['nonce'], '/' ) . '-' . preg_quote( $entity, '/' ) . '[0-9]*\.(?:zip|gz)(?:\.crypt)?$/D', $file ) ) {
						$valid = false;
					}
				}
				if ( $valid ) {
					$candidates[] = array( 'timestamp' => (int) $timestamp, 'nonce' => $row['nonce'], 'entity' => $entity, 'instance_id' => $ids[0], 'path' => $owned[ $ids[0] ]['path'], 'files' => $files, 'reason' => $expired ? 'older_than_395_days' : 'redundant_time_bucket', 'record_sha256' => self::hash( $record ) );
				}
			}
		}
		return array( 'candidates' => $candidates, 'preserved' => $preserved );
	}

	/** @return array|WP_Error Plan is immutable for ten minutes and capped per operation. */
	private static function cleanup_plan( $as_of ) {
		$history = self::history();
		if ( is_wp_error( $history ) ) {
			return $history;
		}
		$status = mrn_updraft_backup_policy_get_retention_status();
		$plan = self::plan_history( $history, self::public_storage(), $as_of );
		$all = $plan['candidates'];
		$selected = array();
		$count = 0;
		foreach ( $all as $candidate ) {
			if ( $count + count( $candidate['files'] ) > self::MAX_FILES ) {
				break;
			}
			$selected[] = $candidate;
			$count += count( $candidate['files'] );
		}
		$plan['candidates'] = $selected;
		$plan['remaining_candidate_components'] = count( $all ) - count( $selected );
		$plan['profile'] = self::PROFILE;
		$plan['as_of'] = $as_of;
		$plan['expires_at'] = $as_of + self::PLAN_TTL;
		$plan['generation'] = self::generation();
		$plan['ready'] = ! empty( $status['compliant'] );
		$plan['remote_verification_required'] = true;
		$plan['plan_sha256'] = self::hash( $plan );
		return $plan;
	}

	/** @return object|WP_Error Updraft's existing S3 engine, scoped to one reviewed instance. */
	private static function provider( $id, $expected_path = null ) {
		$storage = UpdraftPlus_Storage_Methods_Interface::get_storage_objects_and_ids( array( 's3' ) );
		$object = $storage['s3']['object'] ?? null;
		$settings = $storage['s3']['instance_settings'][ $id ] ?? null;
		if ( ! is_object( $object ) || ! is_array( $settings ) || ! self::owned_path( $settings['path'] ?? '' ) || null !== $expected_path && $settings['path'] !== $expected_path || ! method_exists( $object, 'set_options' ) || ! method_exists( $object, 'delete' ) || ! method_exists( $object, 'getS3' ) ) {
			return self::failure( 'storage_adapter_unavailable' );
		}
		$object->set_options( $settings, false, $id );
		return array( 'object' => $object, 'settings' => $settings );
	}

	/** @return array|WP_Error Existing object names; provider never scans a bucket root. */
	private static function remote_files( $provider, $nonce, $filename ) {
		$settings = $provider['settings'];
		$stem_end = strpos( $filename, '_' . $nonce . '-' );
		if ( false === $stem_end || basename( $filename ) !== $filename || false !== strpos( $filename, '..' ) ) {
			return self::failure( 'invalid_archive_name' );
		}
		$stem = substr( $filename, 0, $stem_end + strlen( '_' . $nonce . '-' ) );
		$parts = explode( '/', $settings['path'], 2 );
		$prefix = $parts[1] . '/';
		// Use Updraft's own S3 client. Unlike listfiles(), getBucket distinguishes
		// a successful empty listing from an authentication/network failure.
		$client = $provider['object']->getS3( $settings['accesskey'], $settings['secretkey'], mrn_updraft_backup_policy_get_option( 'updraft_ssl_useservercerts', false ), false, false, null, ! empty( $settings['server_side_encryption'] ), $settings['sessiontoken'] ?? null );
		if ( is_wp_error( $client ) || ! is_object( $client ) || ! method_exists( $client, 'getBucket' ) ) {
			return self::failure( 'remote_inventory_unavailable' );
		}
		$list = $client->getBucket( $parts[0], $prefix . $stem, null, 1000 );
		if ( ! is_array( $list ) || count( $list ) >= 1000 ) {
			return self::failure( 'remote_inventory_unavailable' );
		}
		$names = array();
		foreach ( $list as $file ) {
			$name = is_array( $file ) ? ( $file['name'] ?? null ) : null;
			if ( ! is_string( $name ) || 0 !== strpos( $name, $prefix . $stem ) || (int) ( $file['size'] ?? 0 ) < 1 ) {
				return self::failure( 'remote_inventory_schema' );
			}
			$name = substr( $name, strlen( $prefix ) );
			if ( basename( $name ) !== $name ) {
				return self::failure( 'remote_inventory_schema' );
			}
			$names[] = $name;
		}
		return $names;
	}

	/** Independently verify a recent labeled DB-only recovery point before mutations. */
	private static function verify_backup( $nonce ) {
		if ( ! is_string( $nonce ) || ! preg_match( '/^[a-f0-9]{12}$/D', $nonce ) ) {
			return self::failure( 'backup_nonce_invalid' );
		}
		$history = self::history();
		if ( is_wp_error( $history ) ) {
			return $history;
		}
		foreach ( $history as $timestamp => $record ) {
			$row = self::record( $timestamp, $record );
			if ( $row['nonce'] !== $nonce || $timestamp < time() - 2 * HOUR_IN_SECONDS || $timestamp > time() || array( 'db' ) !== array_keys( $row['components'] ) || '' === $row['label'] || $row['protected'] || $row['imported'] || count( $row['storage_instances'] ) !== 1 ) {
				continue;
			}
			$provider = self::provider( $row['storage_instances'][0] );
			if ( is_wp_error( $provider ) ) {
				return $provider;
			}
			$names = self::remote_files( $provider, $nonce, $row['components']['db'][0] );
			if ( is_wp_error( $names ) || array_diff( $row['components']['db'], $names ) ) {
				return self::failure( 'backup_remote_verification_failed' );
			}
			return true;
		}
		return self::failure( 'recent_labeled_database_backup_required' );
	}

	/**
	 * Prove a native labeled DB-only recovery point without returning logs or storage secrets.
	 *
	 * @param string $nonce Exact Updraft nonce.
	 * @return array|WP_Error
	 */
	public static function maintenance_backup( $nonce ) {
		$valid = self::verify_backup( $nonce );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		global $updraftplus;
		if ( ! is_object( $updraftplus ) || ! is_callable( array( $updraftplus, 'backups_dir_location' ) ) ) {
			return self::failure( 'maintenance_backup_log_unavailable' );
		}
		$file = trailingslashit( $updraftplus->backups_dir_location() ) . 'log.' . $nonce . '.txt';
		if ( is_link( $file ) || ! is_file( $file ) || ! is_readable( $file ) || filesize( $file ) < 1 || filesize( $file ) > 8388608 ) {
			return self::failure( 'maintenance_backup_log_unavailable' );
		}
		// Bound the read even if another process appends after the size check.
		$log = file_get_contents( $file, false, null, 0, 8388609 );
		if ( ! is_string( $log ) || strlen( $log ) > 8388608 || ! preg_match( '/The backup succeeded and is now complete/i', $log ) || ! preg_match( '/Recording as successfully uploaded:[^\r\n]+/i', $log ) || preg_match( '/\b(error|warning):|backup failed|fatal error|errors have occurred|failed to upload|more services to follow/i', $log ) ) {
			return self::failure( 'maintenance_backup_not_complete' );
		}
		unset( $log );
		$history = self::history();
		if ( is_wp_error( $history ) ) {
			return $history;
		}
		foreach ( $history as $timestamp => $record ) {
			$row = self::record( $timestamp, $record );
			if ( $row['nonce'] !== $nonce || 'MRN MainWP database-only backup' !== $row['label'] || $row['incremental'] ) {
				continue;
			}
			if ( ! is_int( $timestamp ) || $timestamp > time() || time() - $timestamp >= 2 * HOUR_IN_SECONDS ) {
				return self::failure( 'maintenance_backup_expired' );
			}
			return array(
				'provider'        => 'updraftplus',
				'nonce'           => $nonce,
				'database_only'   => true,
				'remote_verified' => true,
				'completed_at'    => $timestamp * 1000,
				'expires_at'      => ( $timestamp + 2 * HOUR_IN_SECONDS ) * 1000,
			);
		}
		return self::failure( 'maintenance_backup_label_required' );
	}

	/** Exact component deletion through Updraft's S3 engine, then history readback. */
	private static function execute_cleanup( $plan ) {
		$deleted = array();
		$expected_records = array();
		foreach ( $plan['candidates'] as $candidate ) {
			$history = self::history();
			$record = is_array( $history ) ? ( $history[ $candidate['timestamp'] ] ?? null ) : null;
			$expected_hash = $expected_records[ $candidate['timestamp'] ] ?? $candidate['record_sha256'];
			if ( ! is_array( $record ) || ! hash_equals( $expected_hash, self::hash( $record ) ) ) {
				return array( 'status' => 'reconciliation_required', 'deleted' => $deleted, 'error_code' => 'history_changed' );
			}
			$provider = self::provider( $candidate['instance_id'], $candidate['path'] );
			if ( is_wp_error( $provider ) ) {
				return array( 'status' => 'reconciliation_required', 'deleted' => $deleted, 'error_code' => 'storage_adapter_unavailable' );
			}
			$before = self::remote_files( $provider, $candidate['nonce'], $candidate['files'][0] );
			if ( is_wp_error( $before ) || array_diff( $candidate['files'], $before ) ) {
				return array( 'status' => 'reconciliation_required', 'deleted' => $deleted, 'error_code' => 'remote_manifest_incomplete' );
			}
			$ok = $provider['object']->delete( $candidate['files'], false, array() );
			$after = self::remote_files( $provider, $candidate['nonce'], $candidate['files'][0] );
			if ( true !== $ok || is_wp_error( $after ) || array_intersect( $candidate['files'], $after ) ) {
				return array( 'status' => 'reconciliation_required', 'deleted' => $deleted, 'error_code' => 'remote_delete_not_verified' );
			}
			// Only the exact component that was verified absent is removed from native history.
			unset( $record[ $candidate['entity'] ] );
			UpdraftPlus_Backup_History::save_backup( $candidate['timestamp'], $record );
			$saved = UpdraftPlus_Backup_History::get_history( $candidate['timestamp'] );
			if ( ! is_array( $saved ) || isset( $saved[ $candidate['entity'] ] ) ) {
				return array( 'status' => 'reconciliation_required', 'deleted' => $deleted, 'error_code' => 'history_write_not_verified' );
			}
			$expected_records[ $candidate['timestamp'] ] = self::hash( $saved );
			$deleted[] = array( 'timestamp' => $candidate['timestamp'], 'entity' => $candidate['entity'], 'file_count' => count( $candidate['files'] ), 'remote_absence_verified' => true );
		}
		return array( 'status' => 'completed', 'deleted' => $deleted, 'remaining_candidate_components' => $plan['remaining_candidate_components'], 'generation' => self::generation(), 'scope' => 'known_native_site_owned_backup_components', 's3_noncurrent_versions_verified' => false );
	}

	/** @return bool */
	private static function keys( $value, $keys ) {
		if ( ! is_array( $value ) ) {
			return false;
		}
		$actual = array_keys( $value );
		sort( $actual );
		sort( $keys );
		return $actual === $keys;
	}

	/** @return bool */
	private static function sha( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $value );
	}

	/** @return string */
	private static function hash( $value ) {
		return hash( 'sha256', wp_json_encode( $value, JSON_UNESCAPED_SLASHES ) );
	}

	/** @return WP_Error */
	private static function failure( $code ) {
		return new WP_Error( 'mrn_updraft_' . $code, 'The guarded backup-policy operation could not proceed.' );
	}

	/** @return array */
	private static function error( $code ) {
		return array( 'protocol' => 1, 'ok' => false, 'error_code' => 'mrn_updraft_' . $code );
	}

	/** @return array */
	private static function response( $data ) {
		return is_wp_error( $data ) ? array( 'protocol' => 1, 'ok' => false, 'error_code' => $data->get_error_code() ) : array( 'protocol' => 1, 'ok' => true, 'data' => $data );
	}
}

<?php
/** Version-pinned maintenance over MainWP Child's authenticated transport.
 *
 * @package MRN_Updraft_Backup_Policy
 */

defined( 'ABSPATH' ) || exit;

/**
 * No public endpoint, arbitrary paths, code execution, core, or install operation.
 */
final class MRN_Updraft_MainWP_Maintenance {

	const CONTRACT    = 'mrn-maintenance-exact-versions-remote-updraft-v1';
	const REQUEST_KEY = 'mrn_maintenance_request';
	const LOCK_KEY    = 'mrn_maintenance_lock_v1';
	const MAX_ZIP     = 52428800;
	const MAX_TREE    = 536870912;
	const MAX_FILES   = 50000;
	const DIAGNOSTIC_KEY = 'mrn_maintenance_preflight_refusal_v1';
	const DIAGNOSTIC_TTL = 600;
	const DIAGNOSTIC_CODES = array(
		'unsupported_runtime', 'invalid_schema', 'selection_required', 'invalid_override',
		'invalid_target', 'unknown_version', 'version_refused', 'unused_override',
		'tree_unavailable', 'tree_unsafe', 'tree_limit', 'tree_unreadable',
		'plugin_unreadable', 'theme_invalid', 'core_unavailable', 'offer_changed',
		'shared_plugin_directory', 'download_unavailable', 'download_failed',
		'package_invalid', 'package_limit', 'package_alias_or_type', 'package_path',
		'package_version', 'package_name', 'theme_template_changed', 'backup_unverified',
		'preflight_state_changed', 'backup_nonce_invalid', 'storage_adapter_unavailable',
		'remote_inventory_unavailable', 'remote_inventory_schema', 'remote_region_unavailable',
		'backup_remote_verification_failed', 'recent_labeled_database_backup_required',
		'maintenance_backup_log_unavailable', 'maintenance_backup_not_complete',
		'maintenance_backup_expired', 'maintenance_backup_label_required', 'internal_error',
		'download_http_blocked', 'download_transport_failed', 'download_dns_failed',
		'download_connection_failed', 'download_incomplete', 'download_stream_failed',
		'download_timeout', 'download_tls_failed', 'download_empty_reply',
		'download_certificate_failed', 'download_http_denied', 'download_http_unavailable',
		'download_http_limited', 'download_http_server_error', 'download_http_status',
		'download_empty', 'download_size_limit',
		'package_changed', 'mainwp_rollback_contract_unavailable', 'installed_version_changed',
		'backup_expired_before_write', 'postinstall_state_changed', 'native_update_failed',
		'stale_plan', 'operation_identity', 'operation_locked', 'request_conflict',
		'request_already_reserved', 'audit_capacity', 'ledger_unavailable',
		'backup_nonce_consumed', 'ledger_readback_failed', 'operation_unavailable',
		'read_identity', 'read_selection', 'stale_preinstall', 'activation_change_blocked',
		'not_attempted',
	);
	/** Closed diagnostic context, reset for every authenticated dispatch. */
	private static $stage = 'child_validation';
	private static $item_results = array();
	private static $replacement_started = false;

	/** Map native exceptions to closed codes; never retain their text.
	 *
	 * @param Throwable $error Native error.
	 * @return string
	 */
	private static function code( $error ) {
		return $error instanceof RuntimeException && in_array( $error->getMessage(), self::DIAGNOSTIC_CODES, true ) ? $error->getMessage() : 'internal_error';
	}

	/**
	 * Register only MainWP Child's post-authentication hook.
	 */
	public static function init() {
		add_filter( 'mainwp_child_extra_execution', array( __CLASS__, 'dispatch' ), 25, 2 );
	}

	/**
	 * Dispatch only a signature-verified MainWP request.
	 *
	 * @param array $information Previous response.
	 * @param array $post MainWP's signature-verified request.
	 * @return array
	 * @throws RuntimeException When the closed contract cannot be proven.
	 */
	public static function dispatch( $information, $post ) {
		if ( ! is_array( $post ) || ! isset( $post[ self::REQUEST_KEY ] ) ) {
			if ( is_array( $post ) && 'extra_execution' === ( $post['function'] ?? '' ) && 'report' === ( $post['mrn_stack_report_action'] ?? '' ) && is_array( $information ) && true === ( $information['success'] ?? null ) && is_array( $information['report'] ?? null ) ) {
				$diagnostic = get_transient( self::DIAGNOSTIC_KEY );
				if ( is_array( $diagnostic ) && array_keys( $diagnostic ) === array( 'schema_version', 'code', 'recorded_at' ) && 1 === $diagnostic['schema_version'] && in_array( $diagnostic['code'], self::DIAGNOSTIC_CODES, true ) && is_int( $diagnostic['recorded_at'] ) && $diagnostic['recorded_at'] <= time() && time() - $diagnostic['recorded_at'] < self::DIAGNOSTIC_TTL ) {
					$information['report']['last_maintenance_preflight_refusal'] = $diagnostic;
				}
			}
			return $information;
		}
		$operation = '';
		self::$stage = 'child_validation';
		self::$item_results = array();
		try {
			if ( 'extra_execution' !== ( $post['function'] ?? '' ) || ! is_string( $post[ self::REQUEST_KEY ] ) || strlen( $post[ self::REQUEST_KEY ] ) > 262144 ) {
				throw new RuntimeException( 'invalid_transport' );
			}
			$input = json_decode( wp_unslash( $post[ self::REQUEST_KEY ] ), true );
			if ( ! is_array( $input ) || 1 !== ( $input['protocol'] ?? null ) || self::url( home_url( '/' ) ) !== ( $input['site_url'] ?? null ) ) {
				throw new RuntimeException( 'site_identity' );
			}
			$operation = $input['operation'] ?? '';
			self::libraries();
			if ( 'preflight' === $operation ) {
				self::keys( $input, array( 'protocol', 'operation', 'site_id', 'site_url', 'kind', 'items', 'major_overrides', 'backup_nonce' ) );
				$data = self::inspect( $input );
			} elseif ( 'execute' === $operation ) {
				self::keys( $input, array( 'protocol', 'operation', 'site_id', 'site_url', 'kind', 'items', 'major_overrides', 'backup_nonce', 'before', 'packages', 'plan_sha256', 'request_id' ) );
				$data = self::execute( $input );
			} elseif ( 'read' === $operation ) {
				self::keys( $input, array( 'protocol', 'operation', 'site_id', 'site_url', 'kind', 'slugs', 'plan_sha256', 'request_id' ) );
				$data = self::read( $input );
			} else {
				throw new RuntimeException( 'operation_forbidden' );
			}
			if ( 'preflight' === $operation ) {
				delete_transient( self::DIAGNOSTIC_KEY );
			}
			return array(
				'success' => true,
				'data'    => $data,
			);
		} catch ( Throwable $error ) {
			$code = self::code( $error );
			// Exception text may contain a download URL or a native provider secret.
			if ( 'preflight' === $operation ) {
				set_transient( self::DIAGNOSTIC_KEY, array( 'schema_version' => 1, 'code' => $code, 'recorded_at' => time() ), self::DIAGNOSTIC_TTL );
			}
			unset( $error );
			return array(
				'success'    => false,
				'error_code' => 'mrn_maintenance_refused',
				'failure' => array( 'stage' => self::$stage, 'code' => $code, 'items' => self::$item_results ),
			);
		}
	}

	/**
	 * Load only native WordPress inventory/upgrader APIs.
	 *
	 * @throws RuntimeException When the closed contract cannot be proven.
	 */
	private static function libraries() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		if ( is_multisite() || ! class_exists( 'ZipArchive' ) || 'direct' !== get_filesystem_method() ) {
			throw new RuntimeException( 'unsupported_runtime' );
		}
	}

	/**
	 * Validate or execute the scoped maintenance contract.
	 *
	 * @param string $url Site URL.
	 * @return string
	 * @throws RuntimeException When the closed contract cannot be proven.
	 */
	private static function url( $url ) {
		$url   = untrailingslashit( $url );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			throw new RuntimeException( 'invalid_url' );
		}
		return $url;
	}

	/**
	 * Validate or execute the scoped maintenance contract.
	 *
	 * @param array $value Input.
	 * @param array $keys Exact properties.
	 * @throws RuntimeException When the closed contract cannot be proven.
	 */
	private static function keys( $value, $keys ) {
		if ( ! is_array( $value ) || count( $value ) !== count( $keys ) || array_diff( array_keys( $value ), $keys ) ) {
			throw new RuntimeException( 'invalid_schema' );
		}
	}

	/**
	 * Validate or execute the scoped maintenance contract.
	 *
	 * @param mixed $value Hash.
	 * @return bool
	 */
	private static function is_hash( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $value );
	}

	/**
	 * Validate or execute the scoped maintenance contract.
	 *
	 * @param mixed $value UUID.
	 * @return bool
	 */
	private static function is_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value );
	}

	/**
	 * Validate or execute the scoped maintenance contract.
	 *
	 * @param mixed $value Public canonical data.
	 * @return string
	 * @throws RuntimeException When the closed contract cannot be proven.
	 */
	public static function digest( $value ) {
		$json = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS );
		if ( ! is_string( $json ) ) {
			throw new RuntimeException( 'invalid_encoding' );
		}
		return hash( 'sha256', $json );
	}

	/**
	 * Independently reject core, ambiguous targets, majors, installs and downgrades.
	 *
	 * @param array $input Request.
	 * @throws RuntimeException When the closed contract cannot be proven.
	 */
	private static function selection( $input ) {
		if ( ! is_int( $input['site_id'] ?? null ) || $input['site_id'] < 1 || ! in_array( $input['kind'] ?? '', array( 'plugin', 'theme' ), true ) || ! is_array( $input['items'] ?? null ) || count( $input['items'] ) < 1 || count( $input['items'] ) > 20 || ! is_array( $input['major_overrides'] ?? null ) || count( $input['major_overrides'] ) > 20 ) {
			throw new RuntimeException( 'selection_required' );
		}
		$overrides = array();
		foreach ( $input['major_overrides'] as $override ) {
			self::keys( $override, array( 'slug', 'from', 'to', 'reason' ) );
			if ( 'plugin' !== $input['kind'] || ! is_string( $override['reason'] ) || strlen( trim( $override['reason'] ) ) < 12 || strlen( $override['reason'] ) > 2000 || ! is_string( $override['slug'] ) || isset( $overrides[ $override['slug'] ] ) ) {
				throw new RuntimeException( 'invalid_override' );
			}
			$overrides[ $override['slug'] ] = $override;
		}
		$seen = array();
		foreach ( $input['items'] as $item ) {
			self::keys( $item, array( 'slug', 'from', 'to' ) );
			$slug    = $item['slug'];
			$pattern = 'plugin' === $input['kind'] ? '#^[A-Za-z0-9_-][A-Za-z0-9._-]*/[A-Za-z0-9_-][A-Za-z0-9._-]*\.php$#D' : '#^[A-Za-z0-9_-][A-Za-z0-9._-]*$#D';
			if ( ! is_string( $slug ) || strlen( $slug ) > 200 || ! preg_match( $pattern, $slug ) || false !== strpos( $slug, '..' ) || isset( $seen[ $slug ] ) || 0 === strpos( strtolower( $slug ), 'mrn-' ) ) {
				throw new RuntimeException( 'invalid_target' );
			}
			foreach ( array( $item['from'], $item['to'] ) as $version ) {
				if ( ! is_string( $version ) || strlen( $version ) > 80 || ! preg_match( '/^\d+(?:\.\d+)*$/D', $version ) ) {
					throw new RuntimeException( 'unknown_version' );
				}
			}
			$major = ltrim( explode( '.', $item['from'] )[0], '0' ) !== ltrim( explode( '.', $item['to'] )[0], '0' );
			if ( version_compare( $item['to'], $item['from'], '<=' ) || ( $major && ( ! isset( $overrides[ $slug ] ) || $overrides[ $slug ]['from'] !== $item['from'] || $overrides[ $slug ]['to'] !== $item['to'] ) ) ) {
				throw new RuntimeException( 'version_refused' );
			}
			if ( isset( $overrides[ $slug ] ) && ! $major ) {
				throw new RuntimeException( 'unused_override' );
			}
			unset( $overrides[ $slug ] );
			$seen[ $slug ] = true;
		}
		if ( $overrides ) {
			throw new RuntimeException( 'unused_override' );
		}
	}

	/**
	 * Hash fixed runtime trees; reject symlinks and unbounded inventories.
	 *
	 * @param string $root Fixed WP directory.
	 * @return string
	 * @throws RuntimeException When the closed contract cannot be proven.
	 */
	private static function tree( $root ) {
		if ( ! is_dir( $root ) || is_link( $root ) ) {
			throw new RuntimeException( 'tree_unavailable' );
		}
		$files    = array();
		$bytes    = 0;
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( $file->isLink() ) {
				throw new RuntimeException( 'tree_unsafe' );
			}
			if ( $file->isDir() ) {
				continue;
			}
			if ( ! $file->isFile() || ! $file->isReadable() ) {
				throw new RuntimeException( 'tree_unsafe' );
			}
			$bytes += $file->getSize();
			if ( count( $files ) >= self::MAX_FILES || $bytes > self::MAX_TREE ) {
				throw new RuntimeException( 'tree_limit' );
			}
			$path = $file->getPathname();
			$hash = hash_file( 'sha256', $path );
			if ( ! self::is_hash( $hash ) ) {
				throw new RuntimeException( 'tree_unreadable' );
			}
			$files[ substr( $path, strlen( $root ) + 1 ) ] = $hash;
		}
		ksort( $files, SORT_STRING );
		return self::digest( $files );
	}

	/**
	 * Native code/activation snapshot; configuration secrets are never read.
	 *
	 * @return array
	 * @throws RuntimeException When the closed contract cannot be proven.
	 */
	private static function state() {
		wp_clean_plugins_cache( false );
		wp_clean_themes_cache( false );
		$plugins = array();
		foreach ( get_plugins() as $slug => $plugin ) {
			$root             = dirname( $slug );
			$plugins[ $slug ] = array(
				'version' => (string) $plugin['Version'],
				'active'  => is_plugin_active( $slug ),
				'tree'    => '.' === $root ? hash_file( 'sha256', WP_PLUGIN_DIR . '/' . $slug ) : self::tree( WP_PLUGIN_DIR . '/' . $root ),
			);
		}
		foreach ( $plugins as $plugin ) {
			if ( ! self::is_hash( $plugin['tree'] ) ) {
				throw new RuntimeException( 'plugin_unreadable' );
			}
		}
		ksort( $plugins, SORT_STRING );
		$themes = array();
		foreach ( wp_get_themes() as $slug => $theme ) {
			if ( $theme->errors() ) {
				throw new RuntimeException( 'theme_invalid' );
			}
			$themes[ $slug ] = array(
				'version'  => $theme->get( 'Version' ),
				'template' => $theme->get( 'Template' ),
				'tree'     => self::tree( $theme->get_stylesheet_directory() ),
			);
		}
		ksort( $themes, SORT_STRING );
		$root = array();
		foreach ( array( 'index.php', 'wp-activate.php', 'wp-blog-header.php', 'wp-comments-post.php', 'wp-cron.php', 'wp-links-opml.php', 'wp-load.php', 'wp-login.php', 'wp-mail.php', 'wp-settings.php', 'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php' ) as $file ) {
			$path = ABSPATH . $file;
			if ( is_link( $path ) || ! is_file( $path ) || ! is_readable( $path ) || filesize( $path ) > 8388608 ) {
				throw new RuntimeException( 'core_unavailable' );
			}
			$root[ $file ] = hash_file( 'sha256', $path );
			if ( ! self::is_hash( $root[ $file ] ) ) {
				throw new RuntimeException( 'core_unavailable' );
			}
		}
		$core = array(
			'version'    => $GLOBALS['wp_version'],
			'db_version' => get_option( 'db_version' ),
			'admin'      => self::tree( ABSPATH . 'wp-admin' ),
			'includes'   => self::tree( ABSPATH . WPINC ),
			'root'       => self::digest( $root ),
		);
		return array(
			'plugins'    => $plugins,
			'themes'     => $themes,
			'stylesheet' => get_option( 'stylesheet' ),
			'template'   => get_option( 'template' ),
			'core'       => $core,
		);
	}

	/**
	 * Refresh native offers and prove each target is already installed exactly once.
	 *
	 * @param array $input Request.
	 * @param array $state Snapshot.
	 * @return array
	 * @throws RuntimeException When the closed contract cannot be proven.
	 */
	private static function offers( $input, $state ) {
		$kind = $input['kind'];
		if ( 'plugin' === $kind ) {
			wp_update_plugins();
		} else {
			wp_update_themes();
		}
		$updates = get_site_transient( 'update_' . $kind . 's' );
		$offers  = array();
		foreach ( $input['items'] as $item ) {
			$slug      = $item['slug'];
			$installed = $state[ $kind . 's' ][ $slug ] ?? null;
			$offer     = is_object( $updates ) && is_array( $updates->response ?? null ) ? ( $updates->response[ $slug ] ?? null ) : null;
			$offer     = is_object( $offer ) ? get_object_vars( $offer ) : $offer;
			if ( ! is_array( $installed ) || $installed['version'] !== $item['from'] || ! is_array( $offer ) || ( $offer['new_version'] ?? null ) !== $item['to'] || ! is_string( $offer['package'] ?? null ) || 0 !== strpos( $offer['package'], 'https://' ) || ! wp_http_validate_url( $offer['package'] ) ) {
				throw new RuntimeException( 'offer_changed' );
			}
			if ( 'plugin' === $kind ) {
				foreach ( array_keys( $state['plugins'] ) as $other ) {
					if ( $other !== $slug && dirname( $other ) === dirname( $slug ) ) {
						throw new RuntimeException( 'shared_plugin_directory' );
					}
				}
			}
			$offers[ $slug ] = $offer;
		}
		return $offers;
	}

	/**
	 * Download bounded native offer bytes; no caller-supplied URL or secret is persisted.
	 *
	 * @param string $url Native offer.
	 * @return string
	 * @throws RuntimeException When the closed contract cannot be proven.
	 */
	private static function download( $url ) {
		$file = wp_tempnam( 'mrn-maintenance.zip' );
		if ( ! is_string( $file ) || ! is_file( $file ) ) {
			throw new RuntimeException( 'download_unavailable' );
		}
		$response = wp_safe_remote_get(
			$url,
			array(
				'stream'              => true,
				'filename'            => $file,
				'limit_response_size' => self::MAX_ZIP + 1,
				'timeout'             => 30,
				'redirection'         => 3,
				'sslverify'           => true,
			)
		);
		if ( is_wp_error( $response ) ) {
			$code = 'http_request_not_executed' === $response->get_error_code() ? 'download_http_blocked' : 'download_transport_failed';
			// Inspect only the fixed cURL prefix in memory; never retain native messages or URLs.
			if ( 'http_request_failed' === $response->get_error_code() && preg_match( '/^cURL error (6|7|18|23|28|35|52|60):/', substr( $response->get_error_message(), 0, 24 ), $match ) ) {
				$codes = array( 6 => 'download_dns_failed', 7 => 'download_connection_failed', 18 => 'download_incomplete', 23 => 'download_stream_failed', 28 => 'download_timeout', 35 => 'download_tls_failed', 52 => 'download_empty_reply', 60 => 'download_certificate_failed' );
				$code  = $codes[ (int) $match[1] ];
			}
			wp_delete_file( $file );
			throw new RuntimeException( esc_html( $code ) );
		}
		$status = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			$code = in_array( $status, array( 401, 403, 407 ), true ) ? 'download_http_denied' : ( in_array( $status, array( 404, 410 ), true ) ? 'download_http_unavailable' : ( 429 === $status ? 'download_http_limited' : ( is_int( $status ) && $status >= 500 && $status <= 599 ? 'download_http_server_error' : 'download_http_status' ) ) );
			wp_delete_file( $file );
			throw new RuntimeException( esc_html( $code ) );
		}
		clearstatcache( true, $file );
		$bytes = filesize( $file );
		if ( false === $bytes || $bytes < 1 || $bytes > self::MAX_ZIP ) {
			wp_delete_file( $file );
			throw new RuntimeException( false === $bytes || $bytes < 1 ? 'download_empty' : 'download_size_limit' );
		}
		return $file;
	}

	/**
	 * Reject wrong versions and unsafe archive entries BEFORE native pre-install hooks.
	 *
	 * @param string $file ZIP.
	 * @param array  $item Selection.
	 * @param string $kind Kind.
	 * @param array  $state Snapshot.
	 * @return array
	 * @throws RuntimeException When the closed contract cannot be proven.
	 */
	private static function package( $file, $item, $kind, $state ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file ) ) {
			throw new RuntimeException( 'package_invalid' );
		}
		try {
			$root  = 'plugin' === $kind ? dirname( $item['slug'] ) : $item['slug'];
			$entry = 'plugin' === $kind ? $item['slug'] : $root . '/style.css';
			$bytes = 0;
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native ZipArchive API.
			$zip_count = $zip->numFiles;
			if ( $zip_count > self::MAX_FILES ) {
				throw new RuntimeException( 'package_limit' );
			}
			$seen = array();
			for ( $i = 0; $i < $zip_count; ++$i ) {
				$name       = $zip->getNameIndex( $i );
				$metadata   = $zip->statIndex( $i );
				$system     = 0;
				$attributes = 0;
				$zip->getExternalAttributesIndex( $i, $system, $attributes );
				$bytes    += $metadata['size'];
				// ZIP directories may end in one slash; internal empty/dot segments remain invalid.
				$pathname  = '/' === substr( (string) $name, -1 ) ? substr( $name, 0, -1 ) : (string) $name;
				$canonical = strtolower( $pathname );
				$type      = ( $attributes >> 16 ) & 0170000;
				if ( isset( $seen[ $canonical ] ) || preg_match( '#(?:^|/)\.?(?:/|$)#', $pathname ) || ( 3 === $system && ! in_array( $type, array( 0, 0100000, 0040000 ), true ) ) ) {
					throw new RuntimeException( 'package_alias_or_type' );
				}
				$seen[ $canonical ] = true;
				if ( ! is_string( $name ) || 0 !== strpos( $name, $root . '/' ) || false !== strpos( $name, '..' ) || false !== strpos( $name, '\\' ) || false !== strpos( $name, "\0" ) || $bytes > self::MAX_TREE || ( 3 === $system && 0120000 === ( ( $attributes >> 16 ) & 0170000 ) ) ) {
					throw new RuntimeException( 'package_path' );
				}
			}
			$header = $zip->getFromName( $entry, 8192 );
			if ( ! is_string( $header ) || ! preg_match( '/^[ \t\/*#@]*Version:[ \t]*([^\r\n]+)/mi', $header, $match ) || trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $match[1] ) ) !== $item['to'] ) {
				throw new RuntimeException( 'package_version' );
			}
			$name_header = 'plugin' === $kind ? 'Plugin Name' : 'Theme Name';
			if ( ! preg_match( '/^[ \t\/*#@]*' . $name_header . ':[ \t]*\S+/mi', $header ) ) {
				throw new RuntimeException( 'package_name' );
			}
			if ( 'theme' === $kind ) {
				$template = preg_match( '/^[ \t\/*#@]*Template:[ \t]*([^\r\n]+)/mi', $header, $match ) ? trim( $match[1] ) : '';
				if ( $template !== $state['themes'][ $item['slug'] ]['template'] ) {
					throw new RuntimeException( 'theme_template_changed' );
				}
			}
			return array(
				'slug'   => $item['slug'],
				'sha256' => hash_file( 'sha256', $file ),
				'bytes'  => filesize( $file ),
			);
		} finally {
			$zip->close();
		}
	}

	/**
	 * Validate or execute the scoped maintenance contract.
	 *
	 * @param array $input Request.
	 * @return array
	 * @throws RuntimeException When the closed contract cannot be proven.
	 */
	private static function inspect( $input ) {
		self::selection( $input );
		self::$stage = 'child_backup';
		$backup = MRN_Updraft_MainWP_Policy::maintenance_backup( $input['backup_nonce'] );
		if ( is_wp_error( $backup ) ) {
			$raw  = $backup->get_error_code();
			$code = is_string( $raw ) && 0 === strpos( $raw, 'mrn_updraft_' ) ? substr( $raw, strlen( 'mrn_updraft_' ) ) : '';
			$code = in_array( $code, self::DIAGNOSTIC_CODES, true ) ? $code : 'backup_unverified';
			throw new RuntimeException( esc_html( $code ) );
		}
		self::$stage = 'child_snapshot';
		$before   = self::state();
		$packages = array();
		self::$item_results = array();
		$failed = false;
		foreach ( $input['items'] as $item ) {
			$file = null;
			try {
				self::$stage = 'child_offer';
				$single = $input;
				$single['items'] = array( $item );
				$offers = self::offers( $single, $before );
				self::$stage = 'child_download';
				$file = self::download( $offers[ $item['slug'] ]['package'] );
				self::$stage = 'child_package';
				$packages[] = self::package( $file, $item, $input['kind'], $before );
				self::$item_results[] = array( 'slug' => $item['slug'], 'success' => true, 'stage' => 'child_package', 'code' => 'verified' );
			} catch ( Throwable $error ) {
				$failed = true;
				self::$item_results[] = array( 'slug' => $item['slug'], 'success' => false, 'stage' => self::$stage, 'code' => self::code( $error ) );
			} finally {
				if ( is_string( $file ) ) {
					wp_delete_file( $file );
				}
			}
		}
		if ( $failed ) {
			throw new RuntimeException( 'package_invalid' );
		}
		self::$stage = 'child_snapshot';
		if ( self::digest( self::state() ) !== self::digest( $before ) ) {
			throw new RuntimeException( 'preflight_state_changed' );
		}
		return array(
			'contract' => self::CONTRACT,
			'site_id'  => $input['site_id'],
			'site_url' => $input['site_url'],
			'kind'     => $input['kind'],
			'items'    => $input['items'],
			'before'   => $before,
			'packages' => $packages,
			'backup'   => $backup,
		);
	}

	/**
	 * Fresh scoped option read, never a global cache flush.
	 *
	 * @param string $key Fixed derived key.
	 * @return mixed
	 */
	private static function saved( $key ) {
		wp_cache_delete( $key, 'options' );
		return get_option( $key, null );
	}

	/**
	 * Bound durable audit storage; never silently forget unknown operations.
	 *
	 * @return bool
	 */
	private static function ledger_capacity() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh bounded count required before reserving an audit record.
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( 'mrn_maintenance_op_' ) . '%', $wpdb->esc_like( 'mrn_maintenance_used_' ) . '%' ) );
		return is_numeric( $count ) && (int) $count < 2000;
	}

	/**
	 * Validate or execute the scoped maintenance contract.
	 *
	 * @param string $id UUID.
	 * @return string
	 */
	private static function operation_key( $id ) {
		return 'mrn_maintenance_op_' . hash( 'sha256', $id );
	}

	/**
	 * Execute a single pinned package through WordPress's update-only API.
	 *
	 * @param array $input Request.
	 * @param array $item Selection.
	 * @param array $package Manifest.
	 * @param array $offer Fresh native offer.
	 * @param array $expected Installed state immediately before this update.
	 * @return bool
	 * @throws RuntimeException When the closed contract cannot be proven.
	 */
	private static function upgrade( $input, $item, $package, $offer, $expected ) {
		self::$replacement_started = false;
		self::$stage = 'child_download';
		$file = self::download( $offer['package'] );
		try {
			self::$stage = 'child_package';
			if ( self::package( $file, $item, $input['kind'], $input['before'] ) !== $package ) {
				throw new RuntimeException( 'package_changed' );
			}
			// Returning the already-verified local ZIP binds the native upgrader to exact bytes.
			$guard = static function ( $reply, $url, $upgrader, $extra ) use ( $input, $item, $offer, $file, $package ) {
				unset( $upgrader );
				if ( is_wp_error( $reply ) ) {
					return $reply;
				}
				if ( $url !== $offer['package'] || ( $extra[ $input['kind'] ] ?? null ) !== $item['slug'] || isset( $extra['core'] ) || hash_file( 'sha256', $file ) !== $package['sha256'] ) {
					return new WP_Error( 'mrn_maintenance_package_changed', 'The pinned update package changed.' );
				}
				return $file;
			};
			if ( ! is_callable( array( '\\MainWP\\Child\\MainWP_Child_Updates', 'enable_pre_auto_rollback_hooking' ) ) ) {
				throw new RuntimeException( 'mainwp_rollback_contract_unavailable' );
			}
			\MainWP\Child\MainWP_Child_Updates::enable_pre_auto_rollback_hooking();
			$preinstall = static function ( $reply ) use ( $input, $expected ) {
				self::$stage = 'child_preinstall';
				if ( is_wp_error( $reply ) || is_wp_error( MRN_Updraft_MainWP_Policy::maintenance_backup( $input['backup_nonce'] ) ) || self::digest( self::state() ) !== self::digest( $expected ) ) {
					return new WP_Error( 'mrn_maintenance_stale_preinstall', 'The approved state or backup changed before replacement.' );
				}
				return $reply;
			};
			add_filter( 'upgrader_pre_install', $preinstall, PHP_INT_MAX );
			add_filter( 'upgrader_pre_download', $guard, PHP_INT_MAX, 4 );
			// A guarded update never authorizes activation changes, including on failure.
			$activation_blocked = false;
			$activation = static function ( $value, $old ) use ( &$activation_blocked ) {
				if ( $value !== $old ) {
					$activation_blocked = true;
				}
				return $old;
			};
			add_filter( 'pre_update_option_active_plugins', $activation, PHP_INT_MAX, 2 );
			$replacement = static function ( $reply ) {
				// Destination processing can mutate bytes even when the updater later fails.
				self::$replacement_started = true;
				self::$stage = 'child_update';
				return $reply;
			};
			add_filter( 'upgrader_clear_destination', $replacement, 0 );
			try {
				$skin     = new Automatic_Upgrader_Skin();
				$upgrader = 'plugin' === $input['kind'] ? new Plugin_Upgrader( $skin ) : new Theme_Upgrader( $skin );
				do_action( 'mainwp_child_before_update', $input['kind'], array( $item['slug'] ) );
				if ( $activation_blocked ) {
					throw new RuntimeException( 'activation_change_blocked' );
				}
				self::$stage = 'child_update';
				if ( 'plugin' === $input['kind'] ) {
					// The native one-item bulk path preserves activation, unlike upgrade().
					$results = $upgrader->bulk_upgrade( array( $item['slug'] ), array( 'clear_update_cache' => false ) );
					$result  = is_array( $results ) ? ( $results[ $item['slug'] ] ?? false ) : false;
					do_action( 'mainwp_child_after_update', 'plugin', $results, array( $item['slug'] ) );
					if ( $activation_blocked ) {
						self::$stage = 'child_postinstall';
						throw new RuntimeException( 'activation_change_blocked' );
					}
					if ( is_wp_error( $result ) || false === $result || null === $result ) {
						throw new RuntimeException( 'mrn_maintenance_stale_preinstall' === ( is_wp_error( $result ) ? $result->get_error_code() : '' ) ? 'stale_preinstall' : 'native_update_failed' );
					}
					return true === $result || is_array( $result );
				}
				$result = $upgrader->upgrade( $item['slug'], array( 'clear_update_cache' => false ) );
				do_action( 'mainwp_child_after_update', 'theme', array( $item['slug'] => $result ), array( $item['slug'] ) );
				if ( $activation_blocked ) {
					self::$stage = 'child_postinstall';
					throw new RuntimeException( 'activation_change_blocked' );
				}
				if ( true !== $result ) {
					throw new RuntimeException( 'mrn_maintenance_stale_preinstall' === ( is_wp_error( $result ) ? $result->get_error_code() : '' ) ? 'stale_preinstall' : 'native_update_failed' );
				}
				return true === $result;
			} finally {
				remove_filter( 'upgrader_clear_destination', $replacement, 0 );
				remove_filter( 'pre_update_option_active_plugins', $activation, PHP_INT_MAX );
				remove_filter( 'upgrader_pre_install', $preinstall, PHP_INT_MAX );
				remove_filter( 'upgrader_pre_download', $guard, PHP_INT_MAX );
			}
		} finally {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Durable once-only child ledger and lock; never steals an in-flight lock.
	 *
	 * @param array $input Request.
	 * @return array
	 * @throws RuntimeException When the closed contract cannot be proven.
	 */
	private static function execute( $input ) {
		self::selection( $input );
		if ( ! self::is_uuid( $input['request_id'] ) || ! self::is_hash( $input['plan_sha256'] ) ) {
			throw new RuntimeException( 'operation_identity' );
		}
		$key      = self::operation_key( $input['request_id'] );
		$digest   = self::digest( $input );
		$existing = self::saved( $key );
		if ( is_array( $existing ) ) {
			if ( ( $existing['digest'] ?? null ) !== $digest ) {
				throw new RuntimeException( 'request_conflict' );
			}
			return $existing['result'];
		}
		if ( ! add_option( self::LOCK_KEY, $input['request_id'], '', false ) ) {
			throw new RuntimeException( 'operation_locked' );
		}
		try {
			$existing = self::saved( $key );
			if ( null !== $existing ) {
				throw new RuntimeException( 'request_already_reserved' );
			}
			$inspection = self::inspect( array_intersect_key( $input, array_flip( array( 'protocol', 'operation', 'site_id', 'site_url', 'kind', 'items', 'major_overrides', 'backup_nonce' ) ) ) );
			if ( self::digest( $inspection['before'] ) !== self::digest( $input['before'] ) || $inspection['packages'] !== $input['packages'] ) {
				throw new RuntimeException( 'stale_plan' );
			}
			if ( ! self::ledger_capacity() ) {
				throw new RuntimeException( 'audit_capacity' );
			}
			$result = array(
				'contract'    => self::CONTRACT,
				'status'      => 'outcome_unknown',
				'request_id'  => $input['request_id'],
				'site_id'     => $input['site_id'],
				'site_url'    => $input['site_url'],
				'kind'        => $input['kind'],
				'plan_sha256' => $input['plan_sha256'],
				'results'     => array(),
			);
			$record = array(
				'digest' => $digest,
				'before' => $input['before'],
				'slugs'  => array_column( $input['items'], 'slug' ),
				'result' => $result,
			);
			if ( ! add_option( $key, $record, '', false ) || self::saved( $key ) !== $record ) {
				throw new RuntimeException( 'ledger_unavailable' );
			}
			$nonce_key = 'mrn_maintenance_used_' . hash( 'sha256', $input['backup_nonce'] );
			if ( ! add_option( $nonce_key, $input['request_id'], '', false ) ) {
				throw new RuntimeException( 'backup_nonce_consumed' );
			}
			$offers   = self::offers( $input, $inspection['before'] );
			$expected = $inspection['before'];
			foreach ( $input['items'] as $index => $item ) {
				self::$replacement_started = false;
				try {
					self::$stage = 'child_snapshot';
					// Recheck current installed version immediately before each native updater.
					$current = self::state();
					if ( self::digest( $current ) !== self::digest( $expected ) || ( $current[ $input['kind'] . 's' ][ $item['slug'] ]['version'] ?? null ) !== $item['from'] ) {
						throw new RuntimeException( 'installed_version_changed' );
					}
					if ( is_wp_error( MRN_Updraft_MainWP_Policy::maintenance_backup( $input['backup_nonce'] ) ) ) {
						throw new RuntimeException( 'backup_expired_before_write' );
					}
					$ok                  = self::upgrade( $input, $item, $input['packages'][ $index ], $offers[ $item['slug'] ], $expected );
					if ( $ok ) {
						self::$stage = 'child_postinstall';
						$after = self::state();
						$next  = $expected;
						$kind  = $input['kind'] . 's';
						// Admit only this item's version/tree; activation and every other entry stay bound.
						$next[ $kind ][ $item['slug'] ]['version'] = $item['to'];
						$next[ $kind ][ $item['slug'] ]['tree']    = $after[ $kind ][ $item['slug'] ]['tree'] ?? null;
						if ( self::digest( $after ) !== self::digest( $next ) ) {
							throw new RuntimeException( 'postinstall_state_changed' );
						}
						$expected = $next;
					}
					$result['results'][] = array(
						'slug'    => $item['slug'],
						'success' => $ok,
						'stage' => 'child_postinstall',
						'code' => $ok ? 'verified' : 'native_update_failed',
						'replacement_started' => self::$replacement_started,
					);
					if ( ! $ok ) {
						break;
					}
				} catch ( Throwable $error ) {
					$result['results'][] = array(
						'slug'    => $item['slug'],
						'success' => false,
						'stage' => self::$stage,
						'code' => self::code( $error ),
						'replacement_started' => self::$replacement_started,
					);
					break;
				}
			}
			foreach ( array_slice( $input['items'], count( $result['results'] ) ) as $item ) {
				$result['results'][] = array( 'slug' => $item['slug'], 'success' => false, 'stage' => 'child_update', 'code' => 'not_attempted', 'replacement_started' => false );
			}
			$result['status'] = count( $result['results'] ) === count( $input['items'] ) && ! in_array( false, array_column( $result['results'], 'success' ), true ) ? 'complete' : 'partial';
			$record['result'] = $result;
			update_option( $key, $record, false );
			if ( self::saved( $key ) !== $record ) {
				throw new RuntimeException( 'ledger_readback_failed' );
			}
			return $result;
		} finally {
			if ( self::saved( self::LOCK_KEY ) === $input['request_id'] ) {
				delete_option( self::LOCK_KEY );
			}
		}
	}

	/**
	 * Independent installed readback bound to the exact retained operation.
	 *
	 * @param array $input Request.
	 * @return array
	 * @throws RuntimeException When the closed contract cannot be proven.
	 */
	private static function read( $input ) {
		if ( ! self::is_uuid( $input['request_id'] ) || ! self::is_hash( $input['plan_sha256'] ) || ! in_array( $input['kind'], array( 'plugin', 'theme' ), true ) || ! is_array( $input['slugs'] ) || count( $input['slugs'] ) < 1 || count( $input['slugs'] ) > 20 ) {
			throw new RuntimeException( 'read_identity' );
		}
		$record = self::saved( self::operation_key( $input['request_id'] ) );
		$result = is_array( $record ) ? ( $record['result'] ?? null ) : null;
		if ( ! is_array( $result ) || ( $record['slugs'] ?? null ) !== $input['slugs'] || $result['site_id'] !== $input['site_id'] || $result['site_url'] !== $input['site_url'] || $result['kind'] !== $input['kind'] || $result['plan_sha256'] !== $input['plan_sha256'] || self::saved( self::LOCK_KEY ) ) {
			throw new RuntimeException( 'operation_unavailable' );
		}
		$after  = self::state();
		$before = $record['before'];
		$changed = self::digest( $after ) !== self::digest( $before );
		// Retained pre-repair ledgers omit replacement evidence. Never turn those
		// omissions (or partial/duplicate rows) into a claim that no write occurred.
		$no_replacement = in_array( $result['status'], array( 'complete', 'partial' ), true ) && is_array( $result['results'] ?? null ) && count( $result['results'] ) === count( $input['slugs'] );
		if ( $no_replacement ) {
			foreach ( $input['slugs'] as $index => $slug ) {
				$row = $result['results'][ $index ] ?? null;
				if ( ! is_array( $row ) || ( $row['slug'] ?? null ) !== $slug || ! is_bool( $row['success'] ?? null ) || false !== ( $row['replacement_started'] ?? null ) ) {
					$no_replacement = false;
					break;
				}
			}
		}
		$items  = array();
		foreach ( $input['slugs'] as $slug ) {
			if ( ! is_string( $slug ) || ! isset( $after[ $input['kind'] . 's' ][ $slug ], $before[ $input['kind'] . 's' ][ $slug ] ) ) {
				throw new RuntimeException( 'read_selection' );
			}
			$items[] = array(
				'slug'    => $slug,
				'version' => $after[ $input['kind'] . 's' ][ $slug ]['version'],
			);
			// Selected versions and code may change; activation state must remain equal.
			foreach ( array( 'version', 'tree' ) as $field ) {
				unset( $before[ $input['kind'] . 's' ][ $slug ][ $field ], $after[ $input['kind'] . 's' ][ $slug ][ $field ] );
			}
		}
		return array(
			'contract'            => self::CONTRACT,
			'site_id'             => $input['site_id'],
			'site_url'            => $input['site_url'],
			'kind'                => $input['kind'],
			'request_id'          => $input['request_id'],
			'plan_sha256'         => $input['plan_sha256'],
			'items'               => $items,
			'provider_status'     => $result['status'],
			'unrelated_unchanged' => self::digest( $before ) === self::digest( $after ),
			'core_unchanged'      => $before['core'] === $after['core'],
			'change_reached_site' => $changed ? true : ( $no_replacement ? false : 'unknown' ),
			'installed_state_changed' => $changed,
			'results' => $result['results'],
		);
	}
}

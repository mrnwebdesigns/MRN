<?php
/**
 * Remove retired Stack entries from an existing Admin Menu Editor configuration.
 *
 * wp eval-file clean-ame-stack-settings.php plan
 * wp eval-file clean-ame-stack-settings.php apply <plan-sha256>
 * wp eval-file clean-ame-stack-settings.php rollback <plan-sha256>
 *
 * Shared remote runtimes require their authorized pre-write backup workflow.
 * This migration changes only AME options and keeps an exact rollback journal.
 * It never changes plugin activation, WordPress roles, content or SEO metadata.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 'WP-CLI only.' );
}
require_once dirname( __DIR__, 2 ) . '/configs/ame-stack-cleanup.php';

/** Decode only the vendor's JSON and WH dashboard formats. */
function mrn_ame_cleanup_decode( $value ) {
	if ( is_array( $value ) ) {
		return $value;
	}
	if ( ! is_string( $value ) || strlen( $value ) > 8 * 1024 * 1024 ) {
		throw new RuntimeException( 'Unsupported or oversized AME option format.' );
	}
	if ( 0 === strpos( $value, 'WH' ) ) {
		if ( ! preg_match( '/^WH([ a-z0-9]{4})D/', $value, $header ) ) {
			throw new RuntimeException( 'Unknown AME dashboard encoding.' );
		}
		if ( ! in_array( $header[1], array( '    ', '  gz', '  64', 'gz64' ), true ) ) {
			throw new RuntimeException( 'Unknown AME dashboard compression tags.' );
		}
		$value = substr( $value, 7 );
		if ( false !== strpos( $header[1], '64' ) ) {
			$value = base64_decode( $value, true );
		}
		if ( is_string( $value ) && false !== strpos( $header[1], 'gz' ) ) {
			$value = gzuncompress( $value, 8 * 1024 * 1024 );
		}
		if ( ! is_string( $value ) ) {
			throw new RuntimeException( 'Invalid AME dashboard encoding.' );
		}
	}
	$decoded = json_decode( $value, true, 512, JSON_THROW_ON_ERROR );
	if ( ! is_array( $decoded ) ) {
		throw new RuntimeException( 'AME option must contain an object or array.' );
	}
	return $decoded;
}

/** Keep the option's original storage format. */
function mrn_ame_cleanup_encode( $data, $original ) {
	if ( is_array( $original ) ) {
		return $data;
	}
	$json = wp_json_encode( $data );
	if ( ! is_string( $json ) ) {
		throw new RuntimeException( 'Could not encode AME settings.' );
	}
	if ( 0 === strpos( $original, 'WH' ) ) {
		$header = substr( $original, 0, 7 );
		if ( false !== strpos( substr( $header, 2, 4 ), 'gz' ) ) {
			$json = gzcompress( $json );
			if ( false === $json ) {
				throw new RuntimeException( 'Could not encode AME dashboard settings.' );
			}
		}
		if ( false !== strpos( substr( $header, 2, 4 ), '64' ) ) {
			$json = base64_encode( $json );
		}
		return $header . $json;
	}
	return $json;
}

function mrn_ame_cleanup_hash( $value ) {
	return hash( 'sha256', serialize( $value ) );
}

function mrn_ame_cleanup_run( $arguments ) {
	$mode     = $arguments[0] ?? 'plan';
	$expected = $arguments[1] ?? '';
	if ( is_multisite() || ! in_array( $mode, array( 'plan', 'apply', 'rollback' ), true ) ) {
		WP_CLI::error( 'Use plan, apply or rollback on a single-site installation.' );
	}
	if ( 'plan' !== $mode && ! preg_match( '/^[a-f0-9]{64}$/', $expected ) ) {
		WP_CLI::error( 'A reviewed plan hash is required.' );
	}
	$lock_key    = 'mrn_ame_stack_cleanup_lock';
	$journal_key = 'mrn_ame_stack_cleanup_' . $expected;
	$journal     = 'plan' === $mode ? null : get_option( $journal_key );
	if ( is_array( $journal ) ) {
		if ( home_url() !== $journal['site'] ) {
			WP_CLI::error( 'Rollback journal belongs to another site.' );
		}
		$target = 'rollback' === $mode ? 'before' : 'after';
		$other  = 'rollback' === $mode ? 'after' : 'before';
		$complete = true;
		foreach ( $journal['changes'] as $name => $change ) {
			$current = get_option( $name );
			if ( $current === $change[ $target ] ) {
				continue;
			}
			$complete = false;
			if ( $current !== $change[ $other ] ) {
				WP_CLI::error( 'AME settings changed after planning; refusing to overwrite ' . $name );
			}
		}
		if ( $complete && get_option( $lock_key ) ) {
			WP_CLI::error( 'A prior worker retained the lock; inspect its journal before recovery.' );
		}
		if ( $complete ) {
			WP_CLI::success( 'AME settings already match this ' . $mode . '.' );
			return;
		}
		if ( 'apply' === $mode && get_option( 'active_plugins', array() ) !== $journal['active_plugins'] ) {
			WP_CLI::error( 'Plugin prerequisites changed; create a new reviewed plan.' );
		}
	} elseif ( 'rollback' === $mode ) {
		WP_CLI::error( 'No matching AME rollback journal.' );
	} else {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = get_option( 'active_plugins', array() );
		foreach ( $active as $plugin ) {
			if ( in_array( strtok( $plugin, '/' ), MRN_AME_Stack_Cleanup::RETIRED_PLUGINS, true ) ) {
				WP_CLI::error( 'Migrate or retire the active legacy plugin first: ' . $plugin );
			}
		}
		if ( ! is_plugin_active( 'admin-menu-editor-pro/menu-editor.php' )
			|| ! is_plugin_active( 'wp-seopress/seopress.php' )
			|| ! is_plugin_active( 'wp-seopress-pro/seopress-pro.php' )
			|| ! function_exists( 'mrn_public_security_admin_capability' ) ) {
			WP_CLI::error( 'This cleanup requires the current AME/native SEOPress/Public Security Stack.' );
		}
		$cleaner = new MRN_AME_Stack_Cleanup();
		$methods = array(
			'ws_menu_editor_pro' => 'menu', 'ws_ame_meta_boxes' => 'metaboxes',
			'ws_ame_table_columns' => 'columns', 'ws_ame_dashboard_widgets' => 'widgets',
			'ws_abe_admin_bar_settings' => 'toolbar',
			'ws_abe_admin_bar_nodes' => 'toolbar-cache',
		);
		$changes = array();
		foreach ( $methods as $name => $method ) {
			$before = get_option( $name, null );
			if ( null === $before ) {
				continue;
			}
			$data = mrn_ame_cleanup_decode( $before );
			if ( 'ws_menu_editor_pro' === $name ) {
				$after_data = $data;
				if ( isset( $data['custom_menu'] ) ) {
					$after_data['custom_menu'] = $cleaner->menu( $data['custom_menu'] );
				}
			} elseif ( 'toolbar-cache' === $method ) {
				$after_data = $cleaner->toolbar( array( 'nodes' => $data ) )['nodes'];
			} else {
				$after_data = $cleaner->$method( $data );
			}
			if ( $after_data !== $data ) {
				$changes[ $name ] = array( 'before' => $before, 'after' => mrn_ame_cleanup_encode( $after_data, $before ) );
			}
		}
		$digests = array();
		foreach ( $changes as $name => $change ) {
			$digests[ $name ] = array_map( 'mrn_ame_cleanup_hash', $change );
		}
		$plan = array( 'site' => home_url(), 'policy' => hash_file( 'sha256', dirname( __DIR__, 2 ) . '/configs/ame-stack-cleanup.php' ),
			'active_plugins' => $active, 'options' => $digests, 'removed' => $cleaner->removed );
		$hash = mrn_ame_cleanup_hash( $plan );
		if ( 'plan' === $mode ) {
			WP_CLI::line( wp_json_encode( array( 'plan_sha256' => $hash, 'plan' => $plan ) ) );
			return;
		}
		if ( ! hash_equals( $hash, $expected ) ) {
			WP_CLI::error( 'AME configuration or Stack prerequisites changed after planning.' );
		}
		$journal = array( 'site' => home_url(), 'changes' => $changes, 'active_plugins' => $active, 'status' => 'prepared' );
		$target = 'after';
	}

	if ( ! add_option( $lock_key, $expected, '', false ) ) {
		WP_CLI::error( 'Another AME migration owns the lock. Inspect its journal before recovery.' );
	}
	if ( ! get_option( $journal_key ) && ! add_option( $journal_key, $journal, '', false ) ) {
		WP_CLI::error( 'Could not preserve the AME rollback journal; lock retained.' );
	}
	foreach ( $journal['changes'] as $name => $change ) {
		$current = get_option( $name );
		if ( $current !== $change['before'] && $current !== $change['after'] ) {
			WP_CLI::error( 'AME configuration changed during cleanup; lock and journal retained.' );
		}
		if ( $current !== $change[ $target ] ) {
			update_option( $name, $change[ $target ] );
		}
		if ( get_option( $name ) !== $change[ $target ] ) {
			WP_CLI::error( 'AME setting verification failed; lock and journal retained.' );
		}
	}
	$journal['status'] = 'rollback' === $mode ? 'rolled-back' : 'applied';
	update_option( $journal_key, $journal, false );
	delete_option( $lock_key );
	WP_CLI::success( 'AME ' . $mode . ' verified for ' . count( $journal['changes'] ) . ' option(s).' );
}

try {
	mrn_ame_cleanup_run( $args );
} catch ( Throwable $error ) {
	WP_CLI::error( $error->getMessage() );
}

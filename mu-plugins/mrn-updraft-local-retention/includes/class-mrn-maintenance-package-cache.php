<?php
/** Private, short-lived storage for the exact maintenance archive reviewed by the owner.
 *
 * @package MRN_Updraft_Backup_Policy
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Exclusive files, owner-only modes and native locks protect licensed update packages outside the web root.

/** Never substitute a new vendor download for an unexpired inspected package. */
final class MRN_Maintenance_Package_Cache {

	const TTL = 1200;
	const MAX_BYTES = 209715200;
	const MAX_ENTRIES = 24;
	const CLEANUP_HOOK = 'mrn_maintenance_package_cleanup';

	/** Resolve an owner-only directory in the system temporary filesystem. */
	private static function root( $create ) {
		$base = realpath( sys_get_temp_dir() );
		if ( ! is_string( $base ) || ! is_dir( $base ) ) {
			throw new RuntimeException( 'download_unavailable' );
		}
		$roots = array( ABSPATH );
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$roots[] = WP_CONTENT_DIR;
		}
		if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
			$roots[] = $_SERVER['DOCUMENT_ROOT']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Physical-path comparison only; never used as a write destination.
		}
		foreach ( $roots as $public ) {
			$public = realpath( $public );
			if ( ! is_string( $public ) || $base === $public || 0 === strpos( $base . '/', rtrim( $public, '/' ) . '/' ) ) {
				throw new RuntimeException( 'download_unavailable' );
			}
		}
		$root = $base . '/mrn-maintenance-' . substr( hash_hmac( 'sha256', ABSPATH, wp_salt( 'auth' ) ), 0, 32 );
		if ( ! file_exists( $root ) && ! is_link( $root ) ) {
			if ( ! $create ) {
				return null;
			}
			if ( ! mkdir( $root, 0700 ) && ! is_dir( $root ) ) {
				throw new RuntimeException( 'download_unavailable' );
			}
		}
		clearstatcache( true, $root );
		if ( is_link( $root ) || ! is_dir( $root ) || realpath( $root ) !== $root || 0700 !== ( fileperms( $root ) & 0777 ) || ( function_exists( 'posix_geteuid' ) && fileowner( $root ) !== posix_geteuid() ) ) {
			throw new RuntimeException( 'download_unavailable' );
		}
		return $root;
	}

	/** Serialize preparation and cleanup; never wait on or steal an active writer. */
	private static function lock( $root ) {
		$path = $root . '/writer.lock';
		if ( is_link( $path ) || ( file_exists( $path ) && ( ! is_file( $path ) || realpath( $path ) !== $path ) ) ) {
			throw new RuntimeException( 'download_unavailable' );
		}
		$lock = fopen( $path, 'c+b' );
		if ( false === $lock || ! chmod( $path, 0600 ) ) {
			if ( is_resource( $lock ) ) {
				fclose( $lock );
			}
			throw new RuntimeException( 'download_unavailable' );
		}
		if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			fclose( $lock );
			throw new RuntimeException( 'operation_locked' );
		}
		return $lock;
	}

	/** Inspect only this cache's fixed filenames; remove expired temporary artifacts. */
	private static function inventory( $root ) {
		$paths = glob( $root . '/*' );
		if ( false === $paths || count( $paths ) > 2 * self::MAX_ENTRIES + 3 ) {
			throw new RuntimeException( 'package_limit' );
		}
		$bytes = 0;
		$entries = 0;
		foreach ( $paths as $path ) {
			if ( 'writer.lock' === basename( $path ) ) {
				continue;
			}
			if ( ! preg_match( '/^[a-f0-9]{64}\.(?:zip|json)$/D', basename( $path ) ) || is_link( $path ) || ! is_file( $path ) || realpath( $path ) !== $path || 0600 !== ( fileperms( $path ) & 0777 ) || ( function_exists( 'posix_geteuid' ) && fileowner( $path ) !== posix_geteuid() ) ) {
				throw new RuntimeException( 'download_unavailable' );
			}
			if ( time() - filemtime( $path ) >= self::TTL ) {
				if ( ! unlink( $path ) ) {
					throw new RuntimeException( 'download_unavailable' );
				}
				continue;
			}
			$bytes += filesize( $path );
			$entries += '.zip' === substr( $path, -4 ) ? 1 : 0;
		}
		return array( $bytes, $entries );
	}

	/**
	 * Return a disposable working copy after verifying the pinned manifest.
	 * The caller still validates archive paths, headers and its approved SHA-256.
	 */
	public static function obtain( $identity, $fetch, $validate ) {
		$key = hash( 'sha256', wp_json_encode( $identity ) );
		$root = self::root( true );
		$lock = self::lock( $root );
		$download = null;
		$working = null;
		$created = false;
		$archive = $root . '/' . $key . '.zip';
		$record = $root . '/' . $key . '.json';
		try {
			list( $bytes, $entries ) = self::inventory( $root );
			if ( file_exists( $archive ) || file_exists( $record ) ) {
				if ( ! is_file( $archive ) || ! is_file( $record ) || filesize( $record ) > 2048 ) {
					throw new RuntimeException( 'package_changed' );
				}
				$manifest = json_decode( file_get_contents( $record ), true );
				if ( ! is_array( $manifest ) || $validate( $archive ) !== $manifest ) {
					throw new RuntimeException( 'package_changed' );
				}
			} else {
				if ( $entries >= self::MAX_ENTRIES ) {
					throw new RuntimeException( 'package_limit' );
				}
				$download = $fetch();
				$manifest = $validate( $download );
				if ( $bytes + filesize( $download ) + 2048 > self::MAX_BYTES ) {
					throw new RuntimeException( 'package_limit' );
				}
				$created = true;
				// The exclusive lock and physical-path inventory make these fixed, new paths safe.
				if ( ! copy( $download, $archive ) || ! chmod( $archive, 0600 ) ) {
					throw new RuntimeException( 'download_unavailable' );
				}
				clearstatcache( true, $archive );
				if ( $validate( $archive ) !== $manifest || false === file_put_contents( $record, wp_json_encode( $manifest ) ) || ! chmod( $record, 0600 ) ) {
					throw new RuntimeException( 'download_unavailable' );
				}
				if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
					wp_schedule_single_event( time() + self::TTL, self::CLEANUP_HOOK );
				}
			}
			$working = wp_tempnam( 'mrn-pinned-maintenance.zip' );
			if ( ! is_string( $working ) || ! is_file( $working ) || ! copy( $archive, $working ) ) {
				throw new RuntimeException( 'package_changed' );
			}
			clearstatcache( true, $working );
			if ( $validate( $working ) !== $manifest ) {
				throw new RuntimeException( 'package_changed' );
			}
			return $working;
		} catch ( Throwable $error ) {
			if ( is_string( $working ) && is_file( $working ) ) {
				wp_delete_file( $working );
			}
			if ( $created ) {
				foreach ( array( $archive, $record ) as $path ) {
					if ( is_file( $path ) && ! is_link( $path ) ) {
						unlink( $path );
					}
				}
			}
			throw $error;
		} finally {
			if ( is_string( $download ) && is_file( $download ) ) {
				wp_delete_file( $download );
			}
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	/** Remove a consumed operation's pin; failures cannot change its durable outcome. */
	public static function release( $identity ) {
		$lock = null;
		try {
			$root = self::root( false );
			if ( null === $root ) {
				return;
			}
			$lock = self::lock( $root );
			$key = hash( 'sha256', wp_json_encode( $identity ) );
			foreach ( array( 'zip', 'json' ) as $extension ) {
				$path = $root . '/' . $key . '.' . $extension;
				if ( is_file( $path ) && ! is_link( $path ) && realpath( $path ) === $path ) {
					unlink( $path );
				}
			}
		} catch ( Throwable $error ) {
			unset( $error );
		} finally {
			if ( is_resource( $lock ) ) {
				flock( $lock, LOCK_UN );
				fclose( $lock );
			}
		}
	}

	/** Cron is housekeeping only and never grants maintenance authority. */
	public static function cleanup() {
		$lock = null;
		try {
			$root = self::root( false );
			if ( null === $root ) {
				return;
			}
			$lock = self::lock( $root );
			self::inventory( $root );
			$remaining = glob( $root . '/*.zip' );
			if ( is_array( $remaining ) && count( $remaining ) && ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
				wp_schedule_single_event( time() + self::TTL, self::CLEANUP_HOOK );
			}
		} catch ( Throwable $error ) {
			// Never log native filesystem paths or exception details.
			unset( $error );
		} finally {
			if ( is_resource( $lock ) ) {
				flock( $lock, LOCK_UN );
				fclose( $lock );
			}
		}
	}
}

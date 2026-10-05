<?php
/**
 * Read-only, early plugin selection for an isolated MRN release candidate.
 *
 * No network, database writes, activation or site discovery. One physical code
 * path and its asset manifest are captured before WordPress loads components.
 *
 * @package MRNComponentRelease
 */

defined( 'ABSPATH' ) || exit;

/** Request-scoped immutable component selection. */
final class MRN_Component_Release_Runtime {
	/** @var array Captured, validated components. */
	private static $components = array();
	/** @var bool Whether selection has already occurred. */
	private static $booted = false;

	/**
	 * Read a small deployment-owned JSON artifact.
	 *
	 * @param string $path Physical file.
	 * @return array
	 */
	private static function read_json( $path ) {
		if ( realpath( $path ) !== $path || ! is_file( $path ) || filesize( $path ) > 4194304 ) {
			throw new RuntimeException( 'Component release metadata is unavailable or aliased.' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Small local immutable artifact, read once at bootstrap.
		$value = json_decode( file_get_contents( $path ), true );
		if ( ! is_array( $value ) || 1 !== ( $value['schema'] ?? null ) ) {
			throw new RuntimeException( 'Component release metadata is invalid.' );
		}
		return $value;
	}

	/**
	 * Capture a selection once. Repeated calls never observe a newer pointer.
	 *
	 * @param string $root Qualified private state root.
	 * @return void
	 */
	public static function boot( $root ) {
		if ( self::$booted ) {
			return;
		}
		$public_root = realpath( ABSPATH );
		if ( ! is_string( $root ) || realpath( $root ) !== $root || ! is_dir( $root )
			|| false === $public_root || $root === $public_root
			|| 0 === strpos( $root, $public_root . DIRECTORY_SEPARATOR )
			|| ( fileperms( $root ) & 0077 ) ) {
			throw new RuntimeException( 'Component release storage must be private and outside WordPress.' );
		}
		$selection = self::read_json( $root . '/current.json' );
		$records   = $selection['components'] ?? null;
		if ( ! is_array( $records ) || empty( $records ) || count( $records ) > 40 ) {
			throw new RuntimeException( 'Component selection is empty or too large.' );
		}
		$components = array();
		foreach ( $records as $slug => $record ) {
			if ( ! is_string( $slug ) || ! preg_match( '/^mrn-[a-z0-9-]+$/D', $slug )
				|| 'mrn-stack-deployment-agent' === $slug || ! is_array( $record ) ) {
				throw new RuntimeException( 'Unsupported managed component.' );
			}
			$id       = $record['artifact_sha256'] ?? '';
			$checksum = $record['manifest_sha256'] ?? '';
			if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9]{64}$/D', $id )
				|| ! is_string( $checksum ) || ! preg_match( '/^[a-f0-9]{64}$/D', $checksum ) ) {
				throw new RuntimeException( 'Component selection identity is invalid.' );
			}
			$release_path = $root . '/releases/' . $id;
			$release      = self::read_json( $release_path . '/release.json' );
			$kind         = $release['kind'] ?? '';
			$entrypoint   = $release['entrypoint'] ?? '';
			$directory    = $release_path . '/component/' . $slug;
			if ( ( $release['slug'] ?? null ) !== $slug || ( $release['manifest_sha256'] ?? null ) !== $checksum
				|| ! in_array( $kind, array( 'parent-theme', 'standard-plugin' ), true )
				|| ! is_string( $entrypoint ) || ! preg_match( '/^[a-zA-Z0-9_-]+\.php$/D', $entrypoint )
				|| realpath( $directory ) !== $directory || ! is_readable( $directory . '/' . $entrypoint )
				|| realpath( $directory . '/' . $entrypoint ) !== $directory . '/' . $entrypoint
				|| ( 'parent-theme' === $kind && ( 'mrn-base-stack' !== $slug || 'functions.php' !== $entrypoint ) ) ) {
				throw new RuntimeException( 'Selected code does not match the component contract.' );
			}
			// WP_Theme discovers parent files without the template_directory
			// filter. Accepting a parent here would mix public and pinned trees.
			// Packaging a parent is supported; activating it is not qualified.
			if ( 'parent-theme' === $kind ) {
				throw new RuntimeException( 'Parent theme adoption requires qualified WordPress theme discovery.' );
			}
			$manifest_path = $directory . '/mrn-assets.json';
			$manifest      = self::read_json( $manifest_path );
			if ( ! hash_equals( $checksum, hash_file( 'sha256', $manifest_path ) )
				|| ( $manifest['slug'] ?? null ) !== $slug || ( $manifest['scope'] ?? null ) !== $kind
				|| ! is_string( $manifest['generation'] ?? null )
				|| ! preg_match( '/^[a-f0-9]{64}$/D', $manifest['generation'] )
				|| ( $manifest['public_path'] ?? null ) !== 'mrn-assets/' . $slug . '/' . $manifest['generation']
				|| ! is_array( $manifest['assets'] ?? null ) || ! is_array( $manifest['static_files'] ?? null ) ) {
				throw new RuntimeException( 'Selected asset manifest does not match the code generation.' );
			}
			$public = WP_PLUGIN_DIR . '/' . $slug;
			if ( realpath( $public ) !== $public || ! is_dir( $public ) ) {
				throw new RuntimeException( 'Adopted component public identity is unavailable or aliased.' );
			}
			// Capture unfiltered public roots before installing URL hooks. Calling
			// plugins_url() from its own filter would recurse indefinitely.
			$legacy_url = plugins_url( '', $public . '/' . $entrypoint );
			$components[ $slug ] = array(
				'kind' => $kind, 'directory' => $directory, 'public' => $public,
				'entrypoint' => $entrypoint, 'manifest' => $manifest,
				'version' => (string) ( $release['version'] ?? '' ), 'artifact_sha256' => $id,
				'legacy_url' => $legacy_url,
			);
		}
		// Install hooks only after the complete selection validates.
		self::$components = $components;
		self::$booted     = true;
		foreach ( $components as $component ) {
			if ( 'standard-plugin' === $component['kind'] ) {
				// WordPress's plugin_basename() uses this same map for symlinked
				// plugins. A trailing slash prevents a sibling-prefix match. Core's
				// registration of the public stub does not replace this mapping.
				$GLOBALS['wp_plugin_paths'][ wp_normalize_path( $component['public'] ) . '/' ] = wp_normalize_path( $component['directory'] ) . '/';
			}
		}
		add_filter( 'plugins_url', array( __CLASS__, 'asset_url' ), PHP_INT_MAX );
		add_filter( 'style_loader_src', array( __CLASS__, 'asset_url' ), PHP_INT_MAX );
		add_filter( 'script_loader_src', array( __CLASS__, 'asset_url' ), PHP_INT_MAX );
		add_filter( 'all_plugins', array( __CLASS__, 'plugin_metadata' ) );
		add_filter( 'upgrader_pre_install', array( __CLASS__, 'guard_update' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Resolve only the exact public entrypoint, retaining normal WP activation.
	 *
	 * @param string $file Stable plugin entrypoint from its generated stub.
	 * @return string
	 */
	public static function entrypoint( $file ) {
		foreach ( self::$components as $component ) {
			if ( 'standard-plugin' === $component['kind'] && $file === $component['public'] . '/' . $component['entrypoint'] ) {
				return $component['directory'] . '/' . $component['entrypoint'];
			}
		}
		throw new RuntimeException( 'Plugin entrypoint has no pinned component release.' );
	}

	/**
	 * Retain stable plugin identity with the selected version.
	 *
	 * @param array $plugins Public plugin metadata.
	 * @return array
	 */
	public static function plugin_metadata( $plugins ) {
		foreach ( self::$components as $slug => $component ) {
			$key = $slug . '/' . $component['entrypoint'];
			if ( 'standard-plugin' === $component['kind'] && isset( $plugins[ $key ] ) ) {
				$plugins[ $key ]['Version'] = $component['version'];
			}
		}
		return $plugins;
	}

	/**
	 * Prevent an ordinary overwrite of adopted components.
	 *
	 * @param mixed $response Previous result.
	 * @param array $extra Upgrader target.
	 * @return mixed
	 */
	public static function guard_update( $response, $extra ) {
		foreach ( self::$components as $slug => $component ) {
			if ( ( 'standard-plugin' === $component['kind'] && ( $extra['plugin'] ?? '' ) === $slug . '/' . $component['entrypoint'] )
				|| ( 'parent-theme' === $component['kind'] && ( $extra['theme'] ?? '' ) === $slug ) ) {
				return new WP_Error( 'mrn_component_managed_release', 'This component requires its qualified immutable release workflow.' );
			}
		}
		return $response;
	}

	/**
	 * Resolve release-owned URLs; external and unrelated URLs pass through.
	 *
	 * @param string $url Original asset URL.
	 * @return string
	 */
	public static function asset_url( $url ) {
		$source = wp_parse_url( $url );
		if ( ! is_array( $source ) || empty( $source['path'] ) ) {
			return $url;
		}
		foreach ( self::$components as $component ) {
			$manifest = $component['manifest'];
			$base     = content_url( '/' . $manifest['public_path'] );
			$legacy   = $component['legacy_url'];
			foreach ( array( $base, $legacy ) as $prefix_url ) {
				$prefix = wp_parse_url( $prefix_url );
				if ( ( $source['host'] ?? '' ) !== ( $prefix['host'] ?? '' ) || ( $source['port'] ?? null ) !== ( $prefix['port'] ?? null ) ) {
					continue;
				}
				$path = $prefix['path'] ?? '';
				if ( $source['path'] !== $path && 0 !== strpos( $source['path'], $path . '/' ) ) {
					continue;
				}
				$name = rawurldecode( ltrim( substr( $source['path'], strlen( $path ) ), '/' ) );
				if ( '' === $name ) {
					return $base;
				}
				if ( ! isset( $manifest['static_files'][ $name ] ) ) {
					// Plugin constants commonly expose an asset directory first and
					// concatenate filenames later. Only map a known static prefix.
					foreach ( array_keys( $manifest['static_files'] ) as $static_name ) {
						if ( 0 === strpos( $static_name, rtrim( $name, '/' ) . '/' ) ) {
							return $base . '/' . $name;
						}
					}
					if ( preg_match( '/\.(css|js|mjs|svg|png|jpe?g|gif|webp|avif|ico|woff2?|ttf|otf|eot)$/iD', $name ) ) {
						throw new RuntimeException( 'A component asset is absent from its pinned manifest.' );
					}
					return $url;
				}
				$debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
				$file  = $debug ? $name : ( $manifest['assets'][ $name ]['file'] ?? $name );
				if ( ! is_string( $file ) || ! isset( $manifest['static_files'][ $file ] )
					|| preg_match( '~(^|/)[.]|[\\\\?#\x00-\x20]~', $file ) || '/' === substr( $file, 0, 1 ) ) {
					throw new RuntimeException( 'Unsafe component asset mapping.' );
				}
				return $base . '/' . $file . ( isset( $source['fragment'] ) ? '#' . $source['fragment'] : '' );
			}
		}
		return $url;
	}
}

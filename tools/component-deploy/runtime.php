<?php
/**
 * Read-only, early component selection for an isolated MRN release candidate.
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
	/** @var array Request-scoped parent/child discovery view, when selected. */
	private static $theme = array();
	/** @var bool Recursion guard for reading the native transient. */
	private static $reading_theme_roots = false;

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
			$manifest_path = $directory . '/mrn-assets.json';
			$manifest      = self::read_json( $manifest_path );
			$generation    = $manifest['generation'] ?? '';
			$public_path   = is_string( $generation ) ? ( 'parent-theme' === $kind ? 'mrn-assets/' . $generation . '/' . $slug : 'mrn-assets/' . $slug . '/' . $generation ) : '';
			if ( ! hash_equals( $checksum, hash_file( 'sha256', $manifest_path ) )
				|| ( $manifest['slug'] ?? null ) !== $slug || ( $manifest['scope'] ?? null ) !== $kind
				|| ! is_string( $manifest['generation'] ?? null )
				|| ! preg_match( '/^[a-f0-9]{64}$/D', $manifest['generation'] )
				|| ( $manifest['public_path'] ?? null ) !== $public_path
				|| ! is_array( $manifest['assets'] ?? null ) || ! is_array( $manifest['static_files'] ?? null ) ) {
				throw new RuntimeException( 'Selected asset manifest does not match the code generation.' );
			}
			$public = ( 'parent-theme' === $kind ? WP_CONTENT_DIR . '/themes' : WP_PLUGIN_DIR ) . '/' . $slug;
			if ( realpath( $public ) !== $public || ! is_dir( $public ) ) {
				throw new RuntimeException( 'Adopted component public identity is unavailable or aliased.' );
			}
			// Capture unfiltered public roots before installing URL hooks. Calling
			// plugins_url() from its own filter would recurse indefinitely.
			$legacy_url = 'parent-theme' === $kind ? content_url( '/themes/' . $slug ) : plugins_url( '', $public . '/' . $entrypoint );
			$components[ $slug ] = array(
				'kind' => $kind, 'directory' => $directory, 'public' => $public,
				'entrypoint' => $entrypoint, 'manifest' => $manifest,
				'version' => (string) ( $release['version'] ?? '' ), 'artifact_sha256' => $id,
				'legacy_url' => $legacy_url,
			);
		}
		$theme = array();
		if ( isset( $components['mrn-base-stack'] ) ) {
			$theme = self::validate_theme_view( $root, $selection, $components['mrn-base-stack'] );
		}
		// Install hooks only after the complete selection validates.
		self::$components = $components;
		self::$theme      = $theme;
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
		add_filter( 'upgrader_pre_install', array( __CLASS__, 'guard_update' ), -PHP_INT_MAX, 2 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'guard_install_source' ), PHP_INT_MAX, 4 );
		// Core has actions (not short-circuit filters) before uninstall/delete.
		// Throw before it can run uninstall callbacks or remove the stable stub.
		add_action( 'pre_uninstall_plugin', array( __CLASS__, 'guard_plugin_removal' ), -PHP_INT_MAX );
		add_action( 'delete_plugin', array( __CLASS__, 'guard_plugin_removal' ), -PHP_INT_MAX );
		if ( $theme ) {
			register_theme_directory( WP_CONTENT_DIR . '/themes' );
			register_theme_directory( $theme['root'] );
			add_filter( 'pre_option_template_root', array( __CLASS__, 'theme_root' ), PHP_INT_MAX );
			add_filter( 'pre_option_stylesheet_root', array( __CLASS__, 'theme_root' ), PHP_INT_MAX );
			add_filter( 'pre_site_transient_theme_roots', array( __CLASS__, 'theme_roots' ), PHP_INT_MAX );
			add_filter( 'pre_set_site_transient_theme_roots', array( __CLASS__, 'persist_theme_roots' ), PHP_INT_MAX );
			add_filter( 'wp_cache_themes_persistently', array( __CLASS__, 'theme_cache_policy' ), PHP_INT_MAX, 2 );
			add_filter( 'theme_root_uri', array( __CLASS__, 'theme_root_uri' ), PHP_INT_MAX, 3 );
			add_filter( 'template_directory', array( __CLASS__, 'template_directory' ), PHP_INT_MAX );
			add_filter( 'stylesheet_directory', array( __CLASS__, 'stylesheet_directory' ), PHP_INT_MAX );
			add_filter( 'stylesheet_directory_uri', array( __CLASS__, 'stylesheet_uri' ), PHP_INT_MAX, 2 );
			add_filter( 'mrn_loader_runtime_report', array( __CLASS__, 'runtime_report' ), PHP_INT_MAX );
			add_filter( 'mainwp_child_extra_execution', array( __CLASS__, 'guard_stack_write' ), -PHP_INT_MAX, 2 );
			if ( isset( $theme['child_release_id'] ) && ! headers_sent() ) {
				header( 'X-MRN-Site-Release: ' . $theme['child_release_id'] );
			}
			if ( ! headers_sent() ) {
				header( 'X-MRN-Parent-Release: ' . $components['mrn-base-stack']['artifact_sha256'] );
			}
			add_action( 'delete_theme', array( __CLASS__, 'guard_theme_removal' ), -PHP_INT_MAX );
			add_filter( 'validate_theme_requirements', array( __CLASS__, 'guard_theme_switch' ), PHP_INT_MAX, 2 );
			add_filter( 'pre_update_option_template', array( __CLASS__, 'guard_theme_option' ), -PHP_INT_MAX, 3 );
			add_filter( 'pre_update_option_stylesheet', array( __CLASS__, 'guard_theme_option' ), -PHP_INT_MAX, 3 );
			add_filter( 'pre_update_option_template_root', array( __CLASS__, 'guard_theme_option' ), -PHP_INT_MAX, 3 );
			add_filter( 'pre_update_option_stylesheet_root', array( __CLASS__, 'guard_theme_option' ), -PHP_INT_MAX, 3 );
		}
	}

	/**
	 * Validate a deployment-owned immutable view before registering any hooks.
	 * Only two explicit directory links are permitted. Core WP_Theme discovers
	 * a parent in the child's root without using template_directory filters.
	 * Both must therefore be in this same immutable discovery view.
	 *
	 * @param string $root Private state root.
	 * @param array  $selection Captured pointer.
	 * @param array  $parent Selected parent.
	 * @return array
	 */
	private static function validate_theme_view( $root, $selection, $parent ) {
		$id = $selection['theme_view'] ?? '';
		if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9]{64}$/D', $id ) ) {
			throw new RuntimeException( 'Parent theme adoption requires an immutable discovery view.' );
		}
		$base       = $root . '/theme-views/' . $id;
		$descriptor = self::read_json( $base . '/view.json' );
		$child      = $descriptor['child_stylesheet'] ?? '';
		$view       = $base . '/themes';
		$public     = WP_CONTENT_DIR . '/themes';
		if ( ! hash_equals( $id, hash_file( 'sha256', $base . '/view.json' ) )
			|| ( $descriptor['parent_artifact_sha256'] ?? '' ) !== $parent['artifact_sha256']
			|| ! is_string( $child ) || ! preg_match( '/^[a-zA-Z0-9_-]+$/D', $child ) || 'mrn-base-stack' === $child
			|| realpath( $view ) !== $view || realpath( $public . '/' . $child ) !== $public . '/' . $child
			|| ! is_file( $public . '/' . $child . '/style.css' )
			|| ! is_link( $view . '/mrn-base-stack' ) || realpath( $view . '/mrn-base-stack' ) !== $parent['directory']
			|| ! is_link( $view . '/' . $child ) || realpath( $view . '/' . $child ) !== $public . '/' . $child
			|| is_multisite() || get_option( 'template' ) !== 'mrn-base-stack' || get_option( 'stylesheet' ) !== $child ) {
			throw new RuntimeException( 'Parent theme discovery view does not match this single-site parent/child pair.' );
		}
		$entries = array_values( array_diff( scandir( $view ), array( '.', '..' ) ) );
		sort( $entries );
		$expected = array( 'mrn-base-stack', $child );
		sort( $expected );
		if ( $entries !== $expected || array_diff( (array) ( $GLOBALS['wp_theme_directories'] ?? array() ), array( $public ) ) ) {
			throw new RuntimeException( 'Additional theme roots require a qualified adapter.' );
		}
		$header = get_file_data( $public . '/' . $child . '/style.css', array( 'Template' => 'Template' ) );
		if ( 'mrn-base-stack' !== $header['Template'] ) {
			throw new RuntimeException( 'Child theme header differs from the selected parent.' );
		}
		$result = array( 'root' => $view, 'child' => $child, 'child_directory' => $public . '/' . $child );
		if ( isset( $descriptor['child_release_root'] ) ) {
			$child_root = $descriptor['child_release_root'];
			$wordpress  = realpath( ABSPATH );
			if ( ! is_string( $child_root ) || realpath( $child_root ) !== $child_root || ! is_dir( $child_root )
				|| ( fileperms( $child_root ) & 0077 ) || $child_root === $wordpress
				|| 0 === strpos( $child_root, $wordpress . DIRECTORY_SEPARATOR ) ) {
				throw new RuntimeException( 'Child release storage is unavailable or public.' );
			}
			$adoption = self::read_json( $child_root . '/adoption.json' );
			if ( ( $adoption['slug'] ?? '' ) !== $child || ! is_string( $adoption['bootstrap_sha256'] ?? null )
				|| ! hash_equals( $adoption['bootstrap_sha256'], hash_file( 'sha256', $public . '/' . $child . '/functions.php' ) ) ) {
				throw new RuntimeException( 'Independent child release loader is unrecognized or changed.' );
			}
			$pointer = self::read_json( $child_root . '/current.json' );
			$child_id = $pointer['release_id'] ?? '';
			$path     = $pointer['public_path'] ?? '';
			if ( ( $pointer['slug'] ?? '' ) !== $child || ! is_string( $child_id ) || ! preg_match( '/^[a-f0-9]{64}$/D', $child_id )
				|| ! is_string( $path ) || ! preg_match( '~^mrn-assets/' . preg_quote( $child, '~' ) . '/[a-f0-9]{64}$~D', $path ) ) {
				throw new RuntimeException( 'Independent child release selection is invalid.' );
			}
			$directory = $child_root . '/releases/' . $child_id . '/theme';
			$installed = self::read_json( $child_root . '/releases/' . $child_id . '/installed.json' );
			$manifest  = self::read_json( $directory . '/mrn-assets.json' );
			$checksum  = $installed['theme_files']['mrn-assets.json'] ?? '';
			if ( realpath( $directory ) !== $directory || ( $installed['slug'] ?? '' ) !== $child
				|| ( $installed['release_id'] ?? '' ) !== $child_id || ( $installed['public_path'] ?? '' ) !== $path
				|| ( $manifest['public_path'] ?? '' ) !== $path || ( $manifest['slug'] ?? '' ) !== $child
				|| ! is_string( $checksum ) || ! hash_equals( $checksum, hash_file( 'sha256', $directory . '/mrn-assets.json' ) )
				|| ! is_readable( $directory . '/functions.php' ) ) {
				throw new RuntimeException( 'Independent child code and manifest differ.' );
			}
			$result['child_directory']  = $directory;
			$result['child_public_path'] = $path;
			$result['child_release_id']  = $child_id;
		}
		return $result;
	}

	/** Resolve the independently pinned child's existing public generation. */
	public static function stylesheet_uri( $uri, $stylesheet ) {
		return self::$theme['child'] === $stylesheet && isset( self::$theme['child_public_path'] )
			? content_url( '/' . self::$theme['child_public_path'] ) : $uri;
	}

	/** Preserve the old Stack lock while reporting the actual loaded parent. */
	public static function runtime_report( $report ) {
		$parent = self::$components['mrn-base-stack'];
		if ( ! function_exists( 'mrn_loader_tree_hash' ) || ! is_array( $report ) ) {
			return $report;
		}
		$hash = mrn_loader_tree_hash( $parent['directory'] );
		foreach ( $report['themes'] as &$theme ) {
			if ( 'mrn-base-stack' === ( $theme['slug'] ?? '' ) ) {
				$theme['version']         = $parent['version'];
				$theme['sha256']          = $hash['sha256'];
				$theme['file_count']      = $hash['file_count'];
				$theme['matches_release'] = false;
				$theme['artifact_sha256'] = $parent['artifact_sha256'];
				$theme['asset_generation'] = $parent['manifest']['generation'];
				$theme['source_sha']      = $parent['manifest']['source_sha'];
				$theme['path']            = 'component-release/' . $parent['artifact_sha256'] . '/mrn-base-stack';
			}
		}
		unset( $theme );
		$report['drifted_required'][] = 'mrn-base-stack';
		$report['drifted_required']   = array_values( array_unique( $report['drifted_required'] ) );
		$report['parent_release']     = array( 'artifact_sha256' => $parent['artifact_sha256'], 'asset_generation' => $parent['manifest']['generation'] );
		return $report;
	}

	/** Fail closed before a legacy full Stack writer can bypass this selection. */
	public static function guard_stack_write( $information, $post ) {
		if ( is_array( $post ) && in_array( $post['mrn_stack_deployment_action'] ?? '', array( 'apply', 'rollback' ), true ) ) {
			throw new RuntimeException( 'Release the managed parent selection through its qualified workflow before a full Stack write.' );
		}
		return $information;
	}

	/** @return string Request-pinned native discovery root. */
	public static function theme_root() {
		return self::$theme['root'];
	}

	/**
	 * Preserve unrelated roots while overlaying the selected pair per request.
	 *
	 * @param mixed $roots A previous short circuit.
	 * @return array
	 */
	public static function theme_roots( $roots ) {
		if ( self::$reading_theme_roots ) {
			return $roots;
		}
		if ( ! is_array( $roots ) ) {
			self::$reading_theme_roots = true;
			try {
				$roots = get_site_transient( 'theme_roots' );
			} finally {
				self::$reading_theme_roots = false;
			}
		}
		$roots = is_array( $roots ) ? $roots : array();
		$roots['mrn-base-stack']     = self::$theme['root'];
		$roots[ self::$theme['child'] ] = self::$theme['root'];
		return $roots;
	}

	/**
	 * Preserve native directory scans so unrelated installed themes stay visible.
	 * WP_Theme caches are already isolated by the immutable view path.
	 *
	 * @param mixed  $policy Existing cache policy.
	 * @param string $context WordPress cache consumer.
	 * @return mixed
	 */
	public static function theme_cache_policy( $policy, $context ) {
		return 'search_theme_directories' === $context ? false : $policy;
	}

	/**
	 * Never persist a request's private view into the shared theme-root cache.
	 *
	 * @param array $roots Core's refreshed discovery cache.
	 * @return array
	 */
	public static function persist_theme_roots( $roots ) {
		$roots['mrn-base-stack']        = '/themes';
		$roots[ self::$theme['child'] ] = '/themes';
		return $roots;
	}

	/**
	 * Match WP_Theme's native root/slug URL composition without leaking paths.
	 *
	 * @param string $uri Original URI.
	 * @param string $siteurl WordPress site URL (unused).
	 * @param string $slug Theme identity.
	 * @return string
	 */
	public static function theme_root_uri( $uri, $siteurl, $slug ) {
		if ( 'mrn-base-stack' === $slug ) {
			return content_url( '/mrn-assets/' . self::$components[ $slug ]['manifest']['generation'] );
		}
		return self::$theme['child'] === $slug ? content_url( '/themes' ) : $uri;
	}

	/** @return string Physical immutable parent code, including relative requires. */
	public static function template_directory() {
		return self::$components['mrn-base-stack']['directory'];
	}

	/** @return string Unmodified physical child directory. */
	public static function stylesheet_directory() {
		return self::$theme['child_directory'];
	}

	/**
	 * Refuse changing the qualified pair or persisting a private root.
	 *
	 * @param mixed  $value Requested option.
	 * @param mixed  $old_value Previous option.
	 * @param string $option Option identity.
	 * @return mixed
	 */
	public static function guard_theme_option( $value, $old_value, $option ) {
		$expected = 'template' === $option ? 'mrn-base-stack' : self::$theme['child'];
		if ( in_array( $option, array( 'template_root', 'stylesheet_root' ), true ) ) {
			return $old_value;
		}
		if ( $value !== $expected ) {
			throw new RuntimeException( 'Changing the managed parent/child pair requires its qualified release workflow.' );
		}
		return $value;
	}

	/**
	 * Block core removal before uninstall callbacks or filesystem mutation.
	 *
	 * @param string $plugin Plugin identity.
	 * @return void
	 */
	public static function guard_plugin_removal( $plugin ) {
		foreach ( self::$components as $slug => $component ) {
			if ( 'standard-plugin' === $component['kind'] && dirname( $plugin ) === $slug ) {
				throw new RuntimeException( 'Removing a managed plugin requires its qualified release workflow.' );
			}
		}
	}

	/**
	 * Protect the selected pair, including the child link within its view.
	 *
	 * @param string $slug Theme identity.
	 * @return void
	 */
	public static function guard_theme_removal( $slug ) {
		if ( in_array( $slug, array( 'mrn-base-stack', self::$theme['child'] ), true ) ) {
			throw new RuntimeException( 'Removing a managed theme requires its qualified release workflow.' );
		}
	}

	/**
	 * Stop core theme switching before widget/menu options can be migrated.
	 *
	 * @param mixed  $result Prior validation.
	 * @param string $slug Requested theme.
	 * @return mixed
	 */
	public static function guard_theme_switch( $result, $slug ) {
		return self::$theme['child'] === $slug ? $result : new WP_Error( 'mrn_component_managed_release', 'Changing the managed theme requires its qualified release workflow.' );
	}

	/**
	 * An uploaded overwrite ZIP has no plugin/theme identity in pre_install.
	 * Core derives its final destination from the selected source basename.
	 * Refuse that destination before core moves the existing tree to backup.
	 *
	 * @param mixed  $source Selected unpacked source or error.
	 * @param string $remote_source Original unpacked root (unused).
	 * @param object $upgrader Core upgrader (unused).
	 * @param array  $extra Core operation identity.
	 * @return mixed
	 */
	public static function guard_install_source( $source, $remote_source, $upgrader, $extra ) {
		if ( ! is_string( $source ) ) {
			return $source;
		}
		$slug = basename( rtrim( $source, '/\\' ) );
		if ( isset( self::$components[ $slug ] ) ) {
			$kind = self::$components[ $slug ]['kind'];
			if ( ( 'standard-plugin' === $kind && ( $extra['type'] ?? '' ) === 'plugin' )
				|| ( 'parent-theme' === $kind && ( $extra['type'] ?? '' ) === 'theme' ) ) {
				return new WP_Error( 'mrn_component_managed_release', 'Overwriting a managed component requires its qualified release workflow.' );
			}
		}
		if ( self::$theme && $slug === self::$theme['child'] && ( $extra['type'] ?? '' ) === 'theme' ) {
			return new WP_Error( 'mrn_component_managed_release', 'The child theme requires its separate qualified release workflow.' );
		}
		return $source;
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
		if ( self::$theme && ( $extra['theme'] ?? '' ) === self::$theme['child'] ) {
			return new WP_Error( 'mrn_component_managed_release', 'The child theme requires its separate qualified release workflow.' );
		}
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

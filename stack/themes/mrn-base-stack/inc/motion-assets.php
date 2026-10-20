<?php
/**
 * Optional built-in Motion runtime; legacy consumers retain their full handle.
 *
 * @package mrn-base-stack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue the complete runtime by default and register the opt-in core build.
 *
 * @return void
 */
function mrn_base_stack_enqueue_motion_assets() {
	$directory       = get_template_directory();
	$uri             = get_template_directory_uri();
	$effects_path    = $directory . '/js/front-end-effects.js';
	$effects_version = is_readable( $effects_path ) ? _S_VERSION . '.' . (string) filemtime( $effects_path ) : _S_VERSION;
	$core_path       = $directory . '/js/vendor/motion-core.js';
	/**
	 * Select the built-in effects runtime after reviewing a site's consumers.
	 *
	 * Full remains the compatibility default. Extensions needing any other
	 * Motion API must depend on the existing mrn-base-stack-motion handle.
	 *
	 * @param string $runtime Either full (default) or core.
	 */
	$core = 'core' === apply_filters( 'mrn_base_stack_motion_runtime', 'full' ) && is_readable( $core_path );

	wp_register_script( 'mrn-base-stack-motion', $uri . '/js/vendor/motion.js', array(), '12.38.0', true );
	if ( $core ) {
		wp_register_script( 'mrn-base-stack-motion-core', $uri . '/js/vendor/motion-core.js', array(), _S_VERSION . '.' . (string) filemtime( $core_path ), true );
	} else {
		wp_enqueue_script( 'mrn-base-stack-motion' );
	}
	wp_enqueue_script(
		'mrn-base-stack-front-end-effects',
		$uri . '/js/front-end-effects.js',
		array( $core ? 'mrn-base-stack-motion-core' : 'mrn-base-stack-motion' ),
		$effects_version,
		true
	);
}

/**
 * Avoid the core download if another queued consumer needs the full runtime.
 *
 * Run before WordPress resolves/prints either head or footer dependencies.
 * Never rewrite an extension's own dependency list or an already printed asset.
 *
 * @return void
 */
function mrn_base_stack_resolve_motion_consumers() {
	$scripts = wp_scripts();
	$effects = $scripts->registered['mrn-base-stack-front-end-effects'] ?? null;
	if ( ! $effects || array( 'mrn-base-stack-motion-core' ) !== $effects->deps
		|| in_array( 'mrn-base-stack-front-end-effects', $scripts->done, true ) ) {
		return;
	}
	$visited       = array();
	$requires_full = static function ( $handle ) use ( &$requires_full, &$visited, $scripts ) {
		if ( 'mrn-base-stack-motion' === $handle ) {
			return true;
		}
		if ( isset( $visited[ $handle ] ) || 'mrn-base-stack-front-end-effects' === $handle ) {
			return false;
		}
		$visited[ $handle ] = true;
		foreach ( (array) ( $scripts->registered[ $handle ]->deps ?? array() ) as $dependency ) {
			if ( $requires_full( $dependency ) ) {
				return true;
			}
		}
		return false;
	};
	foreach ( array_merge( $scripts->queue, $scripts->to_do, $scripts->done ) as $handle ) {
		if ( $requires_full( $handle ) ) {
			$effects->deps = array( 'mrn-base-stack-motion' );
			return;
		}
	}
}
add_action( 'wp_print_scripts', 'mrn_base_stack_resolve_motion_consumers', PHP_INT_MAX );
add_action( 'wp_print_footer_scripts', 'mrn_base_stack_resolve_motion_consumers', 0 );

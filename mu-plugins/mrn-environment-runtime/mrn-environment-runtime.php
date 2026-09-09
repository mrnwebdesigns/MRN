<?php
/**
 * Plugin Name: MRN Environment Runtime
 * Description: Reports the deployment-managed environment and performance policy without adding frontend work.
 * Version: 0.5.1
 * Author: MRN Web Designs
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MRN_ENVIRONMENT_RUNTIME_VERSION' ) ) {
	define( 'MRN_ENVIRONMENT_RUNTIME_VERSION', '0.5.1' );
}

/**
 * Return an allowed constant value or its safe default.
 *
 * @param string $constant_name Constant name.
 * @param array  $allowed       Allowed string values.
 * @param string $default       Default value.
 * @return string
 */
function mrn_environment_runtime_constant( $constant_name, $allowed, $default ) {
	if ( ! is_string( $constant_name ) || ! defined( $constant_name ) ) {
		return $default;
	}

	$value = constant( $constant_name );
	if ( ! is_string( $value ) ) {
		return $default;
	}

	$value = strtolower( trim( $value ) );

	return in_array( $value, $allowed, true ) ? $value : $default;
}

/**
 * Resolve the canonical WordPress environment type.
 *
 * @return string
 */
function mrn_environment_runtime_environment_type() {
	$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

	return in_array( $environment, array( 'local', 'development', 'staging', 'production' ), true )
		? $environment
		: 'production';
}

/**
 * Return the site host, lowercased and stripped of port and leading "www.".
 *
 * @param string|null $url Optional URL override for testing.
 * @return string Empty string when the host cannot be resolved.
 */
function mrn_environment_runtime_site_host( $url = null ) {
	if ( null === $url ) {
		$url = function_exists( 'home_url' ) ? home_url( '/' ) : '';
	}

	if ( ! is_string( $url ) || '' === $url ) {
		return '';
	}

	$host = parse_url( $url, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Runs before WordPress helpers are guaranteed.
	if ( ! is_string( $host ) || '' === $host ) {
		// Accept a bare host as well as a full URL.
		$host = preg_replace( '#^.*://#', '', $url );
		$host = is_string( $host ) ? strtok( $host, '/' ) : '';
	}

	if ( ! is_string( $host ) ) {
		return '';
	}

	$host = strtolower( trim( $host ) );
	$host = preg_replace( '/:\d+$/', '', $host );
	$host = preg_replace( '/^www\./', '', (string) $host );

	return (string) $host;
}

/**
 * Return suffixes that always indicate a non-production host.
 *
 * These are local, review, and hosting-provider temporary domains. A site
 * reachable only at one of these is never a customer-facing production site.
 *
 * @return array<int, string>
 */
function mrn_environment_runtime_non_production_suffixes() {
	$suffixes = array(
		'localhost',
		'.localhost',
		'.local',
		'.test',
		'.invalid',
		'.example',
		'.mrndev.io',
		'.tempurl.host',
		'.wpengine.com',
		'.wpenginepowered.com',
		'.kinsta.cloud',
		'.pantheonsite.io',
		'.sg-host.com',
		'.siteground.site',
		'.myftpupload.com',
		'.cloudwaysapps.com',
	);

	if ( function_exists( 'apply_filters' ) ) {
		$filtered = apply_filters( 'mrn_environment_runtime_non_production_suffixes', $suffixes );
		if ( is_array( $filtered ) ) {
			$suffixes = $filtered;
		}
	}

	return $suffixes;
}

/**
 * Classify a host as a production or non-production destination.
 *
 * Deliberately conservative: a host is only called non-production on positive
 * evidence. Anything else is treated as production so a real launch is never
 * silently assumed to be a review site.
 *
 * @param string|null $host Optional host override for testing.
 * @return string production, non_production, or unknown.
 */
function mrn_environment_runtime_host_signal( $host = null ) {
	if ( null === $host ) {
		$host = mrn_environment_runtime_site_host();
	}

	$host = is_string( $host ) ? strtolower( trim( $host ) ) : '';
	if ( '' === $host ) {
		return 'unknown';
	}

	// Bare IP addresses are provisioning artifacts, never a launched domain.
	if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
		return 'non_production';
	}

	// A single-label host has no public domain.
	if ( false === strpos( $host, '.' ) ) {
		return 'non_production';
	}

	foreach ( mrn_environment_runtime_non_production_suffixes() as $suffix ) {
		$suffix = strtolower( (string) $suffix );
		if ( '' === $suffix ) {
			continue;
		}
		if ( $host === $suffix || $host === ltrim( $suffix, '.' ) ) {
			return 'non_production';
		}
		if ( '.' === substr( $suffix, 0, 1 ) && substr( $host, -strlen( $suffix ) ) === $suffix ) {
			return 'non_production';
		}
	}

	/*
	 * Environment-token labels such as "staging", "staging3", or "dev-humbird".
	 * The trailing group requires a digit or separator so real customer labels
	 * like "devon-smith" are not misread as development hosts.
	 */
	foreach ( explode( '.', $host ) as $label ) {
		if ( preg_match( '/^(staging|stage|dev|test|qa|preview|sandbox|demo)([0-9]+)?([-_].*)?$/', $label ) ) {
			return 'non_production';
		}
	}

	return 'production';
}

/**
 * Compare the declared environment against the host the site actually answers on.
 *
 * The policy constants are written once at bootstrap and never revisited, so a
 * site migrated to a live domain keeps its development contract. Every other
 * check in this component compares the declared contract against plugin state,
 * which stays self-consistent and silent in exactly that case. This is the
 * external referent that catches it, and it works on any host because it runs
 * inside WordPress rather than in provisioning tooling.
 *
 * @param string|null $host Optional host override for testing.
 * @return array<string, mixed>
 */
function mrn_environment_runtime_environment_alignment( $host = null ) {
	$declared = mrn_environment_runtime_environment_type();
	$host     = null === $host ? mrn_environment_runtime_site_host() : $host;
	$signal   = mrn_environment_runtime_host_signal( $host );
	$contract = mrn_environment_runtime_contract();

	$status  = 'aligned';
	$risks   = array();

	if ( 'unknown' === $signal ) {
		$status = 'unknown';
	} elseif ( 'production' === $signal && 'production' !== $declared ) {
		$status = 'live_without_production_policy';

		if ( function_exists( 'get_option' ) && ! get_option( 'blog_public' ) ) {
			$risks[] = 'search engines are discouraged (blog_public is off)';
		}
		if ( 'disabled' === $contract['seo_indexing'] ) {
			$risks[] = 'SEO indexing policy is disabled';
		}
		if ( 'disabled' === $contract['relevanssi'] ) {
			$risks[] = 'Relevanssi is disabled, so site search falls back to unranked native results';
		}
	} elseif ( 'non_production' === $signal && 'production' === $declared ) {
		$status = 'production_policy_on_non_production_host';

		if ( function_exists( 'get_option' ) && get_option( 'blog_public' ) ) {
			$risks[] = 'a review host is open to search engines';
		}
		if ( 'configured' === $contract['seo_indexing'] ) {
			$risks[] = 'SEO indexing is configured on a review host';
		}
	}

	return array(
		'declared'    => $declared,
		'host'        => $host,
		'host_signal' => $signal,
		'status'      => $status,
		'risks'       => $risks,
	);
}

/**
 * Return the complete, deployment-managed runtime contract.
 *
 * @return array<string, string>
 */
function mrn_environment_runtime_contract() {
	return array(
		'environment'          => mrn_environment_runtime_environment_type(),
		'site_profile'        => mrn_environment_runtime_constant( 'MRN_SITE_PROFILE', array( 'plain', 'stack' ), 'stack' ),
		'workload_class'      => mrn_environment_runtime_constant( 'MRN_WORKLOAD_CLASS', array( 'standard', 'dynamic' ), 'standard' ),
		'page_cache'          => mrn_environment_runtime_constant( 'MRN_PAGE_CACHE_POLICY', array( 'disabled', 'server_native', 'redis' ), 'disabled' ),
		'object_cache'        => mrn_environment_runtime_constant( 'MRN_OBJECT_CACHE_POLICY', array( 'disabled', 'review_required', 'enabled' ), 'disabled' ),
		'deploy_cache_purge'  => mrn_environment_runtime_constant( 'MRN_DEPLOY_CACHE_PURGE', array( 'object', 'all' ), 'object' ),
		'relevanssi'          => mrn_environment_runtime_constant( 'MRN_RELEVANSSI_POLICY', array( 'disabled', 'configured' ), 'disabled' ),
		'seo_indexing'        => mrn_environment_runtime_constant( 'MRN_SEO_INDEXING_POLICY', array( 'disabled', 'configured' ), 'disabled' ),
		'import_tools'        => mrn_environment_runtime_constant( 'MRN_IMPORT_TOOLS_POLICY', array( 'disabled', 'temporary' ), 'disabled' ),
		'asset_version_source'=> mrn_environment_runtime_constant( 'MRN_ASSET_VERSION_SOURCE', array( 'commit_sha' ), 'commit_sha' ),
		'deployment_ref'      => mrn_environment_runtime_deployment_ref(),
	);
}

/**
 * Return a display-safe deployment reference.
 *
 * @return string
 */
function mrn_environment_runtime_deployment_ref() {
	if ( ! defined( 'MRN_DEPLOYMENT_REF' ) || ! is_string( MRN_DEPLOYMENT_REF ) ) {
		return 'not-set';
	}

	$value = preg_replace( '/[^a-zA-Z0-9._-]/', '', MRN_DEPLOYMENT_REF );

	return is_string( $value ) && '' !== $value ? substr( $value, 0, 80 ) : 'not-set';
}

/**
 * Return display-safe PHP OPcache health information.
 *
 * This is called only from wp-admin Site Health and admin notices. Passing a
 * status array keeps the calculation independently testable.
 *
 * @param array<string, mixed>|false|null $status Optional OPcache status.
 * @return array<string, string>
 */
function mrn_environment_runtime_opcache_information( $status = null ) {
	if ( null === $status ) {
		$status = function_exists( 'opcache_get_status' ) ? opcache_get_status( false ) : false;
	}

	if ( ! is_array( $status ) || empty( $status['opcache_enabled'] ) ) {
		return array(
			'enabled'        => 'no',
			'cache_full'     => 'unknown',
			'memory_mb'      => '0',
			'free_percent'   => '0.0',
			'hit_rate'       => '0.0',
			'cached_scripts' => '0',
		);
	}

	$memory       = isset( $status['memory_usage'] ) && is_array( $status['memory_usage'] ) ? $status['memory_usage'] : array();
	$statistics   = isset( $status['opcache_statistics'] ) && is_array( $status['opcache_statistics'] ) ? $status['opcache_statistics'] : array();
	$used_memory  = max( 0, (int) ( $memory['used_memory'] ?? 0 ) );
	$free_memory  = max( 0, (int) ( $memory['free_memory'] ?? 0 ) );
	$total_memory = $used_memory + $free_memory;
	$free_percent = $total_memory > 0 ? ( $free_memory / $total_memory ) * 100 : 0.0;
	$hit_rate     = max( 0.0, min( 100.0, (float) ( $statistics['opcache_hit_rate'] ?? 0.0 ) ) );

	return array(
		'enabled'        => 'yes',
		'cache_full'     => ! empty( $status['cache_full'] ) ? 'yes' : 'no',
		'memory_mb'      => number_format( $total_memory / 1048576, 0, '.', '' ),
		'free_percent'   => number_format( $free_percent, 1, '.', '' ),
		'hit_rate'       => number_format( $hit_rate, 1, '.', '' ),
		'cached_scripts' => (string) max( 0, (int) ( $statistics['num_cached_scripts'] ?? 0 ) ),
	);
}

/**
 * Find active Relevanssi components for policy diagnostics.
 *
 * This only runs in wp-admin Site Health/admin-notice requests.
 *
 * @return array<int, string>
 */
function mrn_environment_runtime_active_relevanssi_plugins() {
	$active = get_option( 'active_plugins', array() );
	$active = is_array( $active ) ? $active : array();

	if ( is_multisite() ) {
		$network_active = get_site_option( 'active_sitewide_plugins', array() );
		if ( is_array( $network_active ) ) {
			$active = array_merge( $active, array_keys( $network_active ) );
		}
	}

	$relevanssi_plugins = array_filter(
		$active,
		static function ( $plugin ) {
			return is_string( $plugin ) && 0 === strpos( $plugin, 'relevanssi/' );
		}
	);

	return array_values( $relevanssi_plugins );
}

/**
 * Determine whether Relevanssi is active.
 *
 * This only runs in wp-admin Site Health/admin-notice requests.
 *
 * @return bool
 */
function mrn_environment_runtime_relevanssi_core_is_active() {
	return ! empty( mrn_environment_runtime_active_relevanssi_plugins() );
}

/**
 * Find active SEO indexing plugins for policy diagnostics.
 *
 * This only runs in wp-admin Site Health/admin-notice requests.
 *
 * @return array<int, string>
 */
function mrn_environment_runtime_active_seo_indexing_plugins() {
	$active = get_option( 'active_plugins', array() );
	$active = is_array( $active ) ? $active : array();

	if ( is_multisite() ) {
		$network_active = get_site_option( 'active_sitewide_plugins', array() );
		if ( is_array( $network_active ) ) {
			$active = array_merge( $active, array_keys( $network_active ) );
		}
	}

	$seo_plugins = array_filter(
		$active,
		static function ( $plugin ) {
			return is_string( $plugin ) && (
				0 === strpos( $plugin, 'wpmu-dev-seo/' ) ||
				0 === strpos( $plugin, 'smartcrawl-seo/' )
			);
		}
	);

	return array_values( $seo_plugins );
}

/**
 * Add the contract to WordPress Site Health information.
 *
 * @param array $information Existing Site Health information.
 * @return array
 */
function mrn_environment_runtime_debug_information( $information ) {
	$contract = mrn_environment_runtime_contract();
	$opcache  = mrn_environment_runtime_opcache_information();
	$fields   = array();

	foreach ( $contract as $key => $value ) {
		$fields[ $key ] = array(
			'label' => ucwords( str_replace( '_', ' ', $key ) ),
			'value' => $value,
		);
	}

	$relevanssi_active = mrn_environment_runtime_active_relevanssi_plugins();
	$relevanssi_core   = mrn_environment_runtime_relevanssi_core_is_active();

	if ( 'disabled' === $contract['relevanssi'] ) {
		$relevanssi_match = empty( $relevanssi_active );
	} else {
		$relevanssi_match = $relevanssi_core;
	}

	$fields['relevanssi_policy_match'] = array(
		'label' => 'Relevanssi policy match',
		'value' => $relevanssi_match ? 'yes' : 'no',
	);
	$fields['seo_indexing_policy_match'] = array(
		'label' => 'SEO indexing policy match',
		'value' => 'disabled' === $contract['seo_indexing'] && ! empty( mrn_environment_runtime_active_seo_indexing_plugins() ) ? 'no' : 'yes',
	);
	$alignment = mrn_environment_runtime_environment_alignment();
	$fields['site_host'] = array(
		'label' => 'Site host',
		'value' => '' === $alignment['host'] ? 'unknown' : $alignment['host'],
	);
	$fields['host_signal'] = array(
		'label' => 'Host signal',
		'value' => $alignment['host_signal'],
	);
	$fields['environment_alignment'] = array(
		'label' => 'Environment alignment',
		'value' => $alignment['status'],
	);
	$fields['opcache_enabled'] = array(
		'label' => 'PHP OPcache enabled',
		'value' => $opcache['enabled'],
	);
	$fields['opcache_cache_full'] = array(
		'label' => 'PHP OPcache full',
		'value' => $opcache['cache_full'],
	);
	$fields['opcache_memory_mb'] = array(
		'label' => 'PHP OPcache capacity (MB)',
		'value' => $opcache['memory_mb'],
	);
	$fields['opcache_free_percent'] = array(
		'label' => 'PHP OPcache free memory (%)',
		'value' => $opcache['free_percent'],
	);
	$fields['opcache_hit_rate'] = array(
		'label' => 'PHP OPcache hit rate (%)',
		'value' => $opcache['hit_rate'],
	);
	$fields['opcache_cached_scripts'] = array(
		'label' => 'PHP OPcache cached scripts',
		'value' => $opcache['cached_scripts'],
	);

	$information['mrn-environment-runtime'] = array(
		'label'  => 'MRN Environment Runtime',
		'fields' => $fields,
	);

	return $information;
}

/**
 * Warn administrators when PHP OPcache cannot cache additional scripts.
 */
function mrn_environment_runtime_opcache_notice() {
	if ( ! mrn_environment_runtime_can_view_notifications() ) {
		return;
	}

	$opcache = mrn_environment_runtime_opcache_information();
	if ( 'yes' !== $opcache['cache_full'] ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'PHP OPcache is full. Increase server-level OPcache capacity and verify healthy free memory before performance testing or production rollout.', 'mrn-environment-runtime' )
	);
}

/**
 * Warn administrators when Relevanssi conflicts with environment policy.
 */
function mrn_environment_runtime_relevanssi_notice() {
	if ( ! mrn_environment_runtime_can_view_notifications() ) {
		return;
	}

	$contract = mrn_environment_runtime_contract();
	$active   = mrn_environment_runtime_active_relevanssi_plugins();

	if ( 'disabled' === $contract['relevanssi'] && ! empty( $active ) ) {
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'MRN environment policy disables Relevanssi here, but Relevanssi is active. Run the environment-policy reconciliation before performance testing.', 'mrn-environment-runtime' )
		);
		return;
	}

	if ( 'configured' === $contract['relevanssi'] && ! mrn_environment_runtime_relevanssi_core_is_active() ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'MRN environment policy requires Relevanssi search, but Relevanssi is inactive. Activate Relevanssi or change the environment policy.', 'mrn-environment-runtime' )
		);
		return;
	}
}

/**
 * Warn administrators when SEO indexing conflicts with environment policy.
 */
function mrn_environment_runtime_seo_indexing_notice() {
	if ( ! mrn_environment_runtime_can_view_notifications() ) {
		return;
	}

	$contract = mrn_environment_runtime_contract();
	$active   = mrn_environment_runtime_active_seo_indexing_plugins();
	if ( 'disabled' !== $contract['seo_indexing'] || empty( $active ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'MRN environment policy disables SEO indexing here, but an SEO indexing plugin is active. Run the environment-policy reconciliation before performance testing.', 'mrn-environment-runtime' )
	);
}

/**
 * Warn administrators when the declared environment disagrees with the live host.
 */
function mrn_environment_runtime_environment_alignment_notice() {
	if ( ! mrn_environment_runtime_can_view_notifications() ) {
		return;
	}

	$alignment = mrn_environment_runtime_environment_alignment();
	if ( ! in_array( $alignment['status'], array( 'live_without_production_policy', 'production_policy_on_non_production_host' ), true ) ) {
		return;
	}

	$detail = empty( $alignment['risks'] )
		? ''
		: ' ' . sprintf(
			/* translators: %s: comma-separated list of detected risks. */
			esc_html__( 'Detected: %s.', 'mrn-environment-runtime' ),
			esc_html( implode( '; ', $alignment['risks'] ) )
		);

	if ( 'live_without_production_policy' === $alignment['status'] ) {
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s%s</p></div>',
			esc_html__( 'MRN: this site is live but still running a non-production policy.', 'mrn-environment-runtime' ),
			sprintf(
				/* translators: 1: host name, 2: declared environment type. */
				esc_html__( '%1$s looks like a production domain, but the environment is declared as %2$s.', 'mrn-environment-runtime' ),
				esc_html( $alignment['host'] ),
				esc_html( $alignment['declared'] )
			),
			$detail // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
		);

		return;
	}

	printf(
		'<div class="notice notice-warning"><p><strong>%s</strong> %s%s</p></div>',
		esc_html__( 'MRN: production policy is active on a review host.', 'mrn-environment-runtime' ),
		sprintf(
			/* translators: %s: host name. */
			esc_html__( '%s is not a production domain, but the environment is declared as production.', 'mrn-environment-runtime' ),
			esc_html( $alignment['host'] )
		),
		$detail // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
	);
}

/**
 * Check whether the current user can view MRN admin notifications.
 *
 * @return bool
 */
function mrn_environment_runtime_can_view_notifications() {
	if ( function_exists( 'mrn_dashboard_support_notifications_capability' ) ) {
		return current_user_can( mrn_dashboard_support_notifications_capability() );
	}

	return current_user_can( 'manage_options' );
}

/**
 * Collect environment runtime notices for the Notifications Center.
 *
 * @param array<int, array<string, mixed>> $notifications Existing notifications.
 * @return array<int, array<string, mixed>>
 */
function mrn_environment_runtime_dashboard_notifications( $notifications ) {
	if ( ! mrn_environment_runtime_can_view_notifications() ) {
		return is_array( $notifications ) ? $notifications : array();
	}

	if ( ! is_array( $notifications ) ) {
		$notifications = array();
	}

	$contract = mrn_environment_runtime_contract();
	$active   = mrn_environment_runtime_active_relevanssi_plugins();

	if ( 'disabled' === $contract['relevanssi'] && ! empty( $active ) ) {
		$notifications[] = array(
			'id'       => 'mrn-environment-runtime-relevanssi-active',
			'group'    => 'stack',
			'type'     => 'warning',
			'title'    => 'MRN environment policy disables Relevanssi here.',
			'message'  => 'Relevanssi is active. Run the environment-policy reconciliation before performance testing.',
			'source'   => 'MRN Environment Runtime',
			'priority' => 40,
		);
	}

	if ( 'configured' === $contract['relevanssi'] && ! mrn_environment_runtime_relevanssi_core_is_active() ) {
		$notifications[] = array(
			'id'       => 'mrn-environment-runtime-relevanssi-inactive',
			'group'    => 'stack',
			'type'     => 'error',
			'title'    => 'MRN environment policy requires Relevanssi search.',
			'message'  => 'Relevanssi is inactive. Activate Relevanssi or change the environment policy.',
			'source'   => 'MRN Environment Runtime',
			'priority' => 50,
		);
	}

	$active_seo_plugins = mrn_environment_runtime_active_seo_indexing_plugins();
	if ( 'disabled' === $contract['seo_indexing'] && ! empty( $active_seo_plugins ) ) {
		$notifications[] = array(
			'id'       => 'mrn-environment-runtime-seo-indexing-active',
			'group'    => 'stack',
			'type'     => 'warning',
			'title'    => 'MRN environment policy disables SEO indexing here.',
			'message'  => 'An SEO indexing plugin is active. Run the environment-policy reconciliation before performance testing.',
			'source'   => 'MRN Environment Runtime',
			'priority' => 60,
		);
	}

	$alignment = mrn_environment_runtime_environment_alignment();
	if ( in_array( $alignment['status'], array( 'live_without_production_policy', 'production_policy_on_non_production_host' ), true ) ) {
		$detail = empty( $alignment['risks'] )
			? array()
			: array(
				sprintf(
					'Detected: %s.',
					implode( '; ', $alignment['risks'] )
				),
			);

		if ( 'live_without_production_policy' === $alignment['status'] ) {
			$notifications[] = array(
				'id'       => 'mrn-environment-runtime-live-policy-mismatch',
				'group'    => 'stack',
				'type'     => 'error',
				'title'    => 'MRN: this site is live but still running a non-production policy.',
				'message'  => sprintf(
					'%1$s looks like a production domain, but the environment is declared as %2$s.',
					$alignment['host'],
					$alignment['declared']
				),
				'details'  => $detail,
				'source'   => 'MRN Environment Runtime',
				'priority' => 10,
			);
		} else {
			$notifications[] = array(
				'id'       => 'mrn-environment-runtime-production-policy-review-host',
				'group'    => 'stack',
				'type'     => 'warning',
				'title'    => 'MRN: production policy is active on a review host.',
				'message'  => sprintf(
					'%s is not a production domain, but the environment is declared as production.',
					$alignment['host']
				),
				'details'  => $detail,
				'source'   => 'MRN Environment Runtime',
				'priority' => 20,
			);
		}
	}

	$opcache = mrn_environment_runtime_opcache_information();
	if ( 'yes' === $opcache['cache_full'] ) {
		$notifications[] = array(
			'id'       => 'mrn-environment-runtime-opcache-full',
			'group'    => 'stack',
			'type'     => 'error',
			'title'    => 'PHP OPcache is full.',
			'message'  => 'Increase server-level OPcache capacity and verify healthy free memory before performance testing or production rollout.',
			'source'   => 'MRN Environment Runtime',
			'priority' => 30,
		);
	}

	return $notifications;
}

if ( function_exists( 'is_admin' ) && is_admin() ) {
	add_filter( 'debug_information', 'mrn_environment_runtime_debug_information' );
}

/**
 * Register dashboard notifications after all MU plugins have loaded.
 */
function mrn_environment_runtime_register_dashboard_notifications(): void {
	if ( ! is_admin() ) {
		return;
	}

	if ( function_exists( 'mrn_dashboard_support_collect_notifications' ) ) {
		add_filter( 'mrn_dashboard_support_notifications', 'mrn_environment_runtime_dashboard_notifications' );
		return;
	}

	add_action( 'admin_notices', 'mrn_environment_runtime_relevanssi_notice' );
	add_action( 'admin_notices', 'mrn_environment_runtime_environment_alignment_notice' );
	add_action( 'admin_notices', 'mrn_environment_runtime_seo_indexing_notice' );
	add_action( 'admin_notices', 'mrn_environment_runtime_opcache_notice' );
}

add_action( 'plugins_loaded', 'mrn_environment_runtime_register_dashboard_notifications', 20 );

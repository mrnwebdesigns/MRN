<?php
/**
 * Inspect or refresh an exact, site-owned set of HTML URLs through native APIs.
 * Invoked only by the backup-gated controller; never exposed as an HTTP route.
 *
 * @package MRNSiteDeploy
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$mrn_cache_request = json_decode( getenv( 'MRN_HTML_CACHE_REQUEST' ), true );
if ( ! is_array( $mrn_cache_request ) || untrailingslashit( home_url() ) !== ( $mrn_cache_request['url'] ?? '' ) ) {
	WP_CLI::error( 'HTML cache request does not match WordPress home.' );
}
$mrn_cache_provider = $mrn_cache_request['provider'] ?? '';
$mrn_cache_action   = $mrn_cache_request['action'] ?? '';
$mrn_cache_urls     = $mrn_cache_request['urls'] ?? array();
$mrn_cache_home     = wp_parse_url( home_url() );
if ( ! in_array( $mrn_cache_action, array( 'inspect', 'refresh' ), true ) ) {
	WP_CLI::error( 'Unknown HTML cache operation.' );
}
if ( 'nexcess' === $mrn_cache_provider ) {
	if ( ! is_callable( array( 'Cache_Enabler', 'clear_page_cache_by_url' ) ) ) {
		WP_CLI::error( 'Qualified Cache Enabler page API is missing.' );
	}
} elseif ( 'siteground' === $mrn_cache_provider ) {
	if ( ! is_callable( array( 'SiteGround_Optimizer\Supercacher\Supercacher', 'purge_cache_request' ) )
		|| get_option( 'siteground_optimizer_file_caching' )
		|| '1' !== (string) get_option( 'siteground_optimizer_enable_cache' ) ) {
		WP_CLI::error( 'SiteGround dynamic-cache adapter requires its API and file caching disabled.' );
	}
} elseif ( 'wpengine' === $mrn_cache_provider ) {
	if ( ! is_callable( array( 'WpeCommon', 'http_to_varnish' ) ) ) {
		WP_CLI::error( 'WP Engine scoped Varnish API is missing.' );
	}
} else {
	WP_CLI::error( 'Unsupported HTML cache provider.' );
}

if ( 'inspect' === $mrn_cache_action ) {
	// This release changes globally loaded child-theme assets. Enumerate public
	// WordPress routes with the active child loaded; do not copy content or IDs.
	$mrn_cache_urls[] = home_url( '/' );
	$mrn_cache_types  = get_post_types( array( 'public' => true ), 'names' );
	unset( $mrn_cache_types['attachment'] );
	$mrn_cache_posts = get_posts(
		array(
			'post_type'        => array_values( $mrn_cache_types ),
			'post_status'      => 'publish',
			'posts_per_page'   => 10001,
			'fields'           => 'ids',
            'suppress_filters' => true,
			'orderby'          => 'ID',
			'order'            => 'ASC',
		)
	);
	if ( count( $mrn_cache_posts ) > 10000 ) {
		WP_CLI::error( 'HTML route inventory exceeds the reviewed limit.' );
	}
	foreach ( $mrn_cache_posts as $mrn_cache_post ) {
		$mrn_cache_urls[] = get_permalink( $mrn_cache_post );
	}
	foreach ( $mrn_cache_types as $mrn_cache_type ) {
		$mrn_cache_archive = get_post_type_archive_link( $mrn_cache_type );
		if ( $mrn_cache_archive ) {
			$mrn_cache_urls[] = $mrn_cache_archive;
		}
	}
	$mrn_cache_terms = get_terms( array( 'taxonomy' => get_taxonomies( array( 'public' => true ) ), 'hide_empty' => true ) );
	if ( is_wp_error( $mrn_cache_terms ) ) {
		WP_CLI::error( 'Public taxonomy inventory failed.' );
	}
	foreach ( $mrn_cache_terms as $mrn_cache_term ) {
		$mrn_cache_term_url = get_term_link( $mrn_cache_term );
		if ( is_wp_error( $mrn_cache_term_url ) ) {
			WP_CLI::error( 'Public term URL could not be resolved.' );
		}
		$mrn_cache_urls[] = $mrn_cache_term_url;
	}
	// Include already-cached pagination and other theme routes. Cache Enabler's
	// on-disk layout is host/path/index*.html; read only this site's cache tree.
	if ( 'nexcess' === $mrn_cache_provider ) {
		$mrn_cache_directory = WP_CONTENT_DIR . '/cache/cache-enabler/' . $mrn_cache_home['host'];
		if ( is_dir( $mrn_cache_directory ) ) {
			$mrn_cache_files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $mrn_cache_directory, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $mrn_cache_files as $mrn_cache_file ) {
				if ( $mrn_cache_file->isLink() ) {
					WP_CLI::error( 'Aliased HTML cache entry is not supported.' );
				}
				if ( $mrn_cache_file->isFile() && preg_match( '/^index(?:-[a-z0-9_-]+)?\.html(?:\.gz)?$/', $mrn_cache_file->getFilename() ) ) {
					$mrn_cache_path = substr( dirname( $mrn_cache_file->getPathname() ), strlen( $mrn_cache_directory ) );
					$mrn_cache_urls[] = home_url( trailingslashit( $mrn_cache_path ) );
				}
			}
		}
	}
	// Cache providers address paths; include every cached query variant of each
	// discovered HTML path. Public acceptance still uses canonical query-free URLs.
	$mrn_cache_urls = array_map(
		static function ( $url ) { return preg_replace( '/[?#].*$/', '', $url ); },
		$mrn_cache_urls
	);
}

$mrn_cache_urls = array_values( array_unique( $mrn_cache_urls ) );
if ( empty( $mrn_cache_urls ) || count( $mrn_cache_urls ) > 20000 ) {
	WP_CLI::error( 'A bounded nonempty HTML URL scope is required.' );
}
foreach ( $mrn_cache_urls as $mrn_cache_url ) {
	$mrn_cache_parts = wp_parse_url( $mrn_cache_url );
	if ( ! is_array( $mrn_cache_parts ) || ( $mrn_cache_parts['scheme'] ?? '' ) !== 'https'
		|| ( $mrn_cache_parts['host'] ?? '' ) !== $mrn_cache_home['host']
		|| isset( $mrn_cache_parts['query'] ) || isset( $mrn_cache_parts['fragment'] )
		|| isset( $mrn_cache_parts['user'] ) || isset( $mrn_cache_parts['pass'] )
		|| isset( $mrn_cache_parts['port'] )
		|| empty( $mrn_cache_parts['path'] ) || 0 !== strpos( $mrn_cache_parts['path'], '/' )
		|| preg_match( '~(?:^|/)\.\.(?:/|$)|[\\\\\x00-\x20]|%2[ef]|%5c~i', $mrn_cache_parts['path'] )
		|| preg_match( '~^/(?:wp-admin|wp-json|wp-content|wp-includes)(?:/|$)|\.(?:css|js|json|xml|jpg|png|pdf|woff2?)/?$~i', $mrn_cache_parts['path'] ) ) {
		WP_CLI::error( 'HTML scope contains an unsupported or cross-site URL.' );
	}
}

$mrn_cache_refreshed = array();
if ( 'refresh' === $mrn_cache_action ) {
	if ( 'wpengine' === $mrn_cache_provider ) {
		foreach ( array_chunk( $mrn_cache_urls, 40 ) as $mrn_cache_chunk ) {
			$mrn_cache_paths = array_map(
				static function ( $url ) { return preg_quote( wp_parse_url( $url, PHP_URL_PATH ), '~' ); },
				$mrn_cache_chunk
			);
			$mrn_cache_result = WpeCommon::http_to_varnish(
				'PURGE',
				$mrn_cache_home['host'],
				array( 'X-Purge-Path' => '^(' . implode( '|', $mrn_cache_paths ) . ')(?:\?.*)?$', 'X-Purge-Host' => '^' . preg_quote( $mrn_cache_home['host'], '~' ) . '$' )
			);
			if ( is_wp_error( $mrn_cache_result ) || false === $mrn_cache_result ) {
				WP_CLI::error( 'WP Engine scoped HTML invalidation failed.' );
			}
			$mrn_cache_refreshed = array_merge( $mrn_cache_refreshed, $mrn_cache_chunk );
		}
	} else {
		foreach ( $mrn_cache_urls as $mrn_cache_url ) {
			if ( 'nexcess' === $mrn_cache_provider ) {
				Cache_Enabler::clear_page_cache_by_url( $mrn_cache_url, 'page' );
			} elseif ( true !== \SiteGround_Optimizer\Supercacher\Supercacher::purge_cache_request( $mrn_cache_url, false ) ) {
				WP_CLI::error( 'SiteGround scoped HTML invalidation failed.' );
			}
			$mrn_cache_refreshed[] = $mrn_cache_url;
		}
	}
}
WP_CLI::line( 'MRN_RESULT=' . wp_json_encode( array( 'provider' => $mrn_cache_provider, 'action' => $mrn_cache_action, 'urls' => $mrn_cache_urls, 'refreshed_urls' => $mrn_cache_refreshed, 'object_cache_changed' => false, 'transients_changed' => false, 'static_cache_changed' => false ) ) );

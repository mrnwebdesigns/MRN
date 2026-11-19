<?php
/**
 * Saved ACF incoming-link discovery for native SEOPress analysis.
 *
 * @package mrn-base-stack
 */

/** Retain native incoming links and add verified saved-ACF body links. */
class MRN_Base_Stack_SEOPress_Incoming_Extractor {
	/**
	 * Return incoming sources, never the analysed page's outgoing links.
	 *
	 * @param DOMXPath $xpath Public document.
	 * @param array    $options Native post context.
	 * @return array
	 */
	public function getDataByXPath( $xpath, $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Native SEOPress descriptor contract.
		$id     = absint( $options['id'] );
		$url    = get_permalink( $id );
		$path   = wp_parse_url( $url, PHP_URL_PATH );
		$data   = array();
		$native = '\\SEOPress\\Services\\ContentAnalysis\\GetContent\\InternalLinks';
		if ( ! class_exists( $native ) ) {
			return $data;
		}
		// This native query looks up a literal URL; Relevanssi's search takeover
		// otherwise returns no rows in audit/cron context. Scope its supported
		// opt-out filters to this query and restore them before rendering fields.
		$lookup = static function ( $allow, $query ) use ( $id, $url ) {
			if ( $query instanceof WP_Query && $url === $query->get( 's' ) && array( $id ) === $query->get( 'post__not_in' ) && 'ids' === $query->get( 'fields' ) && 'publish' === $query->get( 'post_status' ) && -1 === $query->get( 'posts_per_page' ) && $query->get( 'no_found_rows' ) ) {
				return false;
			}
			return $allow;
		};
		$hooks  = array( 'relevanssi_search_ok', 'relevanssi_admin_search_ok', 'relevanssi_prevent_default_request' );
		foreach ( $hooks as $hook ) {
			add_filter( $hook, $lookup, 100, 2 );
		}
		try {
			$native_sources = ( new $native() )->getDataByXPath( $xpath, $options );
		} finally {
			foreach ( $hooks as $hook ) {
				remove_filter( $hook, $lookup, 100 );
			}
		}
		foreach ( $native_sources as $source ) {
			if ( $id !== (int) $source['id'] && mrn_base_stack_seopress_public_source( get_post( $source['id'] ) ) ) {
				$data[ $source['id'] ] = $source;
			}
		}
		if ( ! is_string( $path ) || '' === $path ) {
			return array_values( $data );
		}
		foreach ( mrn_base_stack_seopress_link_candidates( $path ) as $candidate ) {
			$post = get_post( $candidate );
			if ( $candidate === $id || isset( $data[ $candidate ] ) || ! mrn_base_stack_seopress_public_source( $post ) ) {
				continue;
			}
			$dom      = new DOMDocument();
			$previous = libxml_use_internal_errors( true );
			$dom->loadHTML( '<?xml encoding="utf-8" ?><main>' . mrn_base_stack_seopress_builder_markup( $post ) . '</main>', LIBXML_NONET );
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
			$links = mrn_base_stack_seopress_main_xpath( new DOMXPath( $dom ) )->query( '//a[@href]' );
			foreach ( $links as $link ) {
				if ( ! $link instanceof DOMElement ) {
					continue;
				}
				$href = WP_Http::make_absolute_url( $link->getAttribute( 'href' ), get_permalink( $candidate ) );
				if ( in_array( wp_parse_url( $href, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) && strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) ) === strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) && wp_parse_url( $href, PHP_URL_PORT ) === wp_parse_url( $url, PHP_URL_PORT ) && untrailingslashit( (string) wp_parse_url( $href, PHP_URL_PATH ) ) === untrailingslashit( $path ) ) {
					$data[ $candidate ] = array(
						'id'             => $candidate,
						'url'            => get_permalink( $candidate ),
						'value'          => get_the_title( $candidate ),
						'edit_post_link' => (string) get_edit_post_link( $candidate, 'raw' ),
					);
					break;
				}
			}
		}
		return array_values( $data );
	}
}

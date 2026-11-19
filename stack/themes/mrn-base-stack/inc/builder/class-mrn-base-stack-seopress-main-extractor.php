<?php
/**
 * Native descriptor adapters; safe to load with SEOPress absent.
 *
 * @package mrn-base-stack
 */

/** Delegate body checks to native extractors on an isolated main-only document. */
class MRN_Base_Stack_SEOPress_Main_Extractor {
	/**
	 * Execute the provider's extractor without altering head/schema extraction.
	 *
	 * @param DOMXPath $xpath Public document.
	 * @param array    $options Trusted descriptor options.
	 * @return mixed
	 */
	public function getDataByXPath( $xpath, $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Native SEOPress descriptor contract.
		$class = $options['mrn_original_class'];
		return ( new $class() )->getDataByXPath( mrn_base_stack_seopress_main_xpath( $xpath ), $options );
	}
}

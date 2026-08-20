<?php
/**
 * Standalone tests for the REST auth gate — the single most
 * security-critical function in this plugin, since every mutating action
 * sits behind it.
 *
 * This file deliberately mixes a function stub with a mock class, and its
 * name doesn't match that class — both are throwaway test-harness choices,
 * not production plugin code.
 *
 * @package MRN_Recovery_Agent
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName, Universal.Files.SeparateFunctionsFromOO.Mixed -- See file docblock above.

define( 'ABSPATH', __DIR__ );

/**
 * Stub for the plugin's top-level add_action( 'rest_api_init', ... ) call.
 * Deliberately a no-op — this test targets the auth callback directly, not
 * REST route registration.
 *
 * @return void
 */
function add_action() {}

/**
 * Minimal stand-in for WP_REST_Request, just enough for the auth callback.
 *
 * Mixing this class with the function stub above in one file is a
 * deliberate exception for this small, throwaway test harness — not
 * production plugin code.
 */
class WP_REST_Request {

	/**
	 * Lowercased header name => value.
	 *
	 * @var array<string, string>
	 */
	private $headers;

	/**
	 * Store the stubbed request headers.
	 *
	 * @param array<string, string> $headers Request headers, keyed lowercase.
	 */
	public function __construct( $headers = array() ) {
		$this->headers = $headers;
	}

	/**
	 * Look up a stubbed header by name.
	 *
	 * @param string $name Header name.
	 * @return string|null
	 */
	public function get_header( $name ) {
		return $this->headers[ strtolower( $name ) ] ?? null;
	}

	/**
	 * Unused by the function under test here; present only to satisfy the
	 * WP_REST_Request shape mrn_recovery_agent_check_bearer_auth() expects.
	 *
	 * @param string $name Parameter name.
	 * @return null
	 */
	public function get_param( $name ) {
		return null;
	}
}

require dirname( __DIR__ ) . '/mrn-recovery-agent.php';

// --- fails closed when MRN_RECOVERY_KEY is undefined ---
$request = new WP_REST_Request( array( 'authorization' => 'Bearer anything' ) );
if ( false !== mrn_recovery_agent_check_bearer_auth( $request ) ) {
	throw new RuntimeException( 'Expected auth to fail closed with no MRN_RECOVERY_KEY defined.' );
}

define( 'MRN_RECOVERY_KEY', 'test-key-do-not-use-in-production' );

// --- rejects a missing/malformed Authorization header ---
$request = new WP_REST_Request( array() );
if ( false !== mrn_recovery_agent_check_bearer_auth( $request ) ) {
	throw new RuntimeException( 'Expected auth to reject a missing Authorization header.' );
}

$request = new WP_REST_Request( array( 'authorization' => 'Basic dXNlcjpwYXNz' ) );
if ( false !== mrn_recovery_agent_check_bearer_auth( $request ) ) {
	throw new RuntimeException( 'Expected auth to reject a non-Bearer scheme.' );
}

// --- rejects a wrong token ---
$request = new WP_REST_Request( array( 'authorization' => 'Bearer wrong-token' ) );
if ( false !== mrn_recovery_agent_check_bearer_auth( $request ) ) {
	throw new RuntimeException( 'Expected auth to reject an incorrect token.' );
}

// --- accepts the correct token ---
$request = new WP_REST_Request( array( 'authorization' => 'Bearer test-key-do-not-use-in-production' ) );
if ( true !== mrn_recovery_agent_check_bearer_auth( $request ) ) {
	throw new RuntimeException( 'Expected auth to accept the correct bearer token.' );
}

echo "status-endpoint: OK\n";

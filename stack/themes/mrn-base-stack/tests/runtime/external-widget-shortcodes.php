<?php
// phpcs:ignoreFile -- Local WordPress integration checks; no persisted content changes.
/**
 * Run through wp eval-file on a local runtime with WPForms and an existing form.
 * MRN_EXTERNAL_WIDGET_TEMPLATE may point at the candidate template in a worktree.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) {
	throw new RuntimeException( 'Use an explicitly resolved local .localhost runtime.' );
}

$template = getenv( 'MRN_EXTERNAL_WIDGET_TEMPLATE' ) ?: get_template_directory() . '/template-parts/builder/external-widget.php';
$form_id  = absint( getenv( 'MRN_EXTERNAL_WIDGET_FORM_ID' ) );
if ( 'external-widget.php' !== basename( $template ) || ! is_file( $template ) || ! shortcode_exists( 'wpforms' ) || 'wpforms' !== get_post_type( $form_id ) ) {
	throw new RuntimeException( 'The candidate template and an existing local WPForms form are required.' );
}

$checks = 0;
$assert = function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
	echo 'PASS: ' . $message . PHP_EOL;
};
$render = function ( $source ) use ( $template ) {
	$args = array( 'row' => array( 'embed_code' => $source ) );
	ob_start();
	include $template; // nosemgrep: php-dynamic-include -- WP-CLI-only fixture; an operator chooses the candidate template, never request input.
	return (string) ob_get_clean();
};

$calls = 0;
add_shortcode( 'mrn_qa_embed_provider', function () use ( &$calls ) {
	++$calls;
	return '<style id="mrn-qa-provider-style">.mrn-qa-provider{color:inherit}</style>'
		. '<form class="mrn-qa-provider" data-formid="qa" data-provider="test" aria-live="polite">'
		. '<label for="mrn-qa-name">Name</label><input id="mrn-qa-name" name="name" aria-required="true">'
		. '<button type="submit">Send</button></form>'
		. '<script type="application/json" id="mrn-qa-provider-config">{"form":"qa"}</script>';
} );

try {
	$provider = $render( '[mrn_qa_embed_provider]' );
	$assert( 1 === $calls, 'Registered shortcode executes exactly once' );
	$assert( str_contains( $provider, 'data-formid="qa"' ) && str_contains( $provider, 'data-provider="test"' ), 'Provider data attributes survive' );
	$assert( str_contains( $provider, 'aria-live="polite"' ) && str_contains( $provider, 'aria-required="true"' ), 'Provider accessibility attributes survive' );
	$assert( str_contains( $provider, '<style id="mrn-qa-provider-style">' ), 'Provider styles survive' );
	$assert( str_contains( $provider, '<script type="application/json" id="mrn-qa-provider-config">' ), 'Provider configuration survives' );

	$raw = $render( '<div onclick="bad()"><script id="raw-script">bad()</script><style id="raw-style">bad{color:red}</style><a href="javascript:bad()">Link</a></div>' );
	$assert( ! str_contains( $raw, '<script' ) && ! str_contains( $raw, '<style' ), 'Raw script and style tags stay prohibited' );
	$assert( ! str_contains( $raw, 'onclick=' ) && ! str_contains( $raw, 'javascript:' ), 'Raw event handlers and unsafe URLs stay prohibited' );
	$iframe = $render( '<iframe src="https://example.test/embed" onload="bad()"></iframe>' );
	$assert( str_contains( $iframe, 'loading="lazy"' ) && str_contains( $iframe, 'referrerpolicy="strict-origin-when-cross-origin"' ), 'Iframe performance and privacy defaults survive' );
	$assert( str_contains( $iframe, 'title="Embedded content"' ) && ! str_contains( $iframe, 'onload=' ), 'Iframe accessible title and raw HTML filtering survive' );
	$assert( str_contains( $render( '[mrn_qa_unknown]' ), '[mrn_qa_unknown]' ), 'Unknown shortcode remains literal' );
	$assert( str_contains( $render( '[[mrn_qa_embed_provider]]' ), '[mrn_qa_embed_provider]' ) && 1 === $calls, 'Escaped shortcode remains literal' );
	$mixed = $render( '<p onclick="bad()">Before</p>[mrn_qa_embed_provider]<p>After</p>' );
	$assert( ! str_contains( $mixed, 'onclick=' ) && str_contains( $mixed, 'data-formid="qa"' ) && str_contains( $mixed, '<p>After</p>' ), 'Mixed embed HTML stays filtered while provider output survives' );

	$shortcode = sprintf( '[wpforms id="%d" title="false"]', $form_id );
	$direct = do_shortcode( $shortcode );
	$form = $render( $shortcode );
	$assert( 1 === substr_count( $form, 'id="wpforms-form-' . $form_id . '"' ), 'Real WPForms form renders exactly once' );
	$assert( str_contains( $direct, 'data-formid=' ) && str_contains( $form, 'data-formid=' ), 'Real WPForms data attributes survive' );
	$assert( str_contains( $direct, '<style' ) && str_contains( $form, '<style' ), 'Real WPForms styles survive' );
	$assert( str_contains( $direct, '<script' ) && str_contains( $form, '<script' ), 'Real WPForms inline configuration survives' );
} finally {
	remove_shortcode( 'mrn_qa_embed_provider' );
}

echo "PASS: {$checks} shortcode/embed checks; no submissions or saved-content changes.\n";

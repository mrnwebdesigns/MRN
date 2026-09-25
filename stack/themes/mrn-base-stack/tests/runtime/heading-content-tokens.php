<?php
// phpcs:ignoreFile -- Local WordPress integration checks; registrations exist only in this process.
/**
 * Run with wp eval-file against a local runtime with the candidate parent and MRN Tokens.
 * No posts, options, or other persisted content are changed.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) {
	throw new RuntimeException( 'Use an explicitly resolved local .localhost WordPress runtime.' );
}
if ( ! function_exists( 'mrn_tokens_register' ) || ! shortcode_exists( 'mrn_token' ) ) {
	throw new RuntimeException( 'MRN Tokens must be active.' );
}

function mrn_heading_token_assert( $actual, $expected, $message ) {
	if ( $actual !== $expected ) {
		throw new RuntimeException( $message . ': ' . var_export( $actual, true ) );
	}
	echo 'PASS: ' . $message . PHP_EOL;
}

mrn_tokens_register( 'mrn_qa_heading', 'North & South', array( 'url' => 'https://example.test/contact/' ) );
mrn_tokens_register( 'mrn_qa_nested', '[mrn_qa_side_effect]' );
$shortcode = '[mrn_token name="mrn_qa_heading"]';
$format = 'mrn_base_stack_format_heading_inline_html';
$side_effects = 0;
add_shortcode( 'mrn_qa_side_effect', function () use ( &$side_effects ) { ++$side_effects; return 'Unexpected'; } );

mrn_heading_token_assert( $format( $shortcode ), 'North &amp; South', 'MRN token renders escaped text' );
mrn_heading_token_assert( $format( 'In ' . $shortcode . ' today' ), 'In North &amp; South today', 'Surrounding text is retained' );
mrn_heading_token_assert( $format( $shortcode . ' / ' . $shortcode ), 'North &amp; South / North &amp; South', 'Multiple tokens render' );
mrn_heading_token_assert( $format( '<strong>' . $shortcode . '</strong><br><em>Now</em>' ), '<strong>North &amp; South</strong><br><em>Now</em>', 'Existing inline formatting is retained' );
mrn_heading_token_assert( $format( '<span class="eyebrow" onclick="bad()">' . $shortcode . '</span><img src=x onerror=bad()>' ), '<span class="eyebrow">North &amp; South</span>', 'Disallowed attributes and elements are removed' );
mrn_heading_token_assert( $format( '[mrn_token name="mrn_qa_heading" format="link" link_text="Contact"]' ), 'Contact', 'Link-format tokens respect heading markup restrictions' );
mrn_heading_token_assert( $format( '[mrn_token name="mrn_qa_heading" format="url"]' ), 'https://example.test/contact/', 'URL-format token remains escaped text' );
mrn_heading_token_assert( $format( '[mrn_token name="mrn_qa_missing_20260925"]' ), '', 'Unknown token uses the existing empty-value contract' );
mrn_heading_token_assert( $format( '[' . $shortcode . ']' ), $shortcode, 'Escaped token syntax stays literal' );
mrn_heading_token_assert( $format( '[mrn_qa_side_effect] ' . $shortcode ), '[mrn_qa_side_effect] North &amp; South', 'Unrelated registered shortcode remains literal' );
mrn_heading_token_assert( $format( '[mrn_token name="mrn_qa_nested"]' ), '[mrn_qa_side_effect]', 'Token values do not execute nested shortcodes' );
mrn_heading_token_assert( $side_effects, 0, 'Unrelated callbacks never run' );
mrn_heading_token_assert( $format( 'Plain &amp; <em>formatted</em> text' ), 'Plain &amp; <em>formatted</em> text', 'Non-token text is unchanged' );

$fields = array( 'label' => $shortcode, 'heading' => $shortcode, 'subheading' => $shortcode );
$args = array( 'row' => $fields, 'post_id' => 0, 'index' => 0 );
ob_start();
include get_template_directory() . '/template-parts/builder/basic.php';
$builder_markup = ob_get_clean();
mrn_heading_token_assert( substr_count( $builder_markup, 'North &amp; South' ), 3, 'Builder label, heading and subheading render tokens' );

if ( ! is_file( WP_PLUGIN_DIR . '/mrn-reusable-block-library/templates/basic-block.php' ) ) {
	throw new RuntimeException( 'The reusable block template must be installed for this regression.' );
}
$context = array( 'fields' => $fields, 'post_id' => 0 );
ob_start();
include WP_PLUGIN_DIR . '/mrn-reusable-block-library/templates/basic-block.php';
$reusable_markup = ob_get_clean();
mrn_heading_token_assert( substr_count( $reusable_markup, 'North &amp; South' ), 3, 'Reusable label, heading and subheading render tokens' );

$token_callback = $GLOBALS['shortcode_tags']['mrn_token'];
remove_shortcode( 'mrn_token' );
mrn_heading_token_assert( $format( $shortcode ), $shortcode, 'Missing Tokens plugin degrades safely without executing anything' );
add_shortcode( 'mrn_token', $token_callback );
remove_shortcode( 'mrn_qa_side_effect' );
echo "PASS: 16 real WordPress heading-token checks; no persistent content changes.\n";

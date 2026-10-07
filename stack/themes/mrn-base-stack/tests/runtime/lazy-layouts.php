<?php
/**
 * Local-only integration fixture and AJAX contract checks for lazy layouts.
 *
 * Run through wp eval-file with setup, request, check, or cleanup.
 * MRN_LAZY_FIXTURE identifies a private state file outside tracked source.
 *
 * @package mrn-base-stack
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) {
	throw new RuntimeException( 'This fixture requires an explicitly resolved Local Hub runtime.' );
}

$state_path   = getenv( 'MRN_LAZY_FIXTURE' );
$fixture_mode = $args[0] ?? '';
if ( ! $state_path ) {
	throw new RuntimeException( 'MRN_LAZY_FIXTURE is required.' );
}

/**
 * Hash authored page metadata while excluding ephemeral editor locks.
 *
 * @return array<int, string>
 */
function mrn_lazy_fixture_page_hashes() {
	$hashes = array();
	foreach ( get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		)
	) as $page ) {
		$meta = get_post_meta( $page->ID );
		unset( $meta['_edit_lock'], $meta['_edit_last'] );
		ksort( $meta );
		$hashes[ $page->ID ] = hash( 'sha256', serialize( array( $page->post_content, $meta ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Deterministic read-only hashing; never deserialized.
	}
	return $hashes;
}

if ( 'setup' === $fixture_mode ) {
	if ( file_exists( $state_path ) ) {
		throw new RuntimeException( 'Fixture state already exists.' );
	}
	$before          = mrn_lazy_fixture_page_hashes();
	$fixture_post_id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'draft',
			'post_title'  => 'MRN ACF Lazy Layout QA',
			'meta_input'  => array( '_mrn_lazy_layout_fixture' => '2026-10-07' ),
		),
		true
	);
	if ( is_wp_error( $fixture_post_id ) ) {
		throw new RuntimeException( esc_html( $fixture_post_id->get_error_message() ) );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Explicit local test state.
	file_put_contents(
		$state_path,
		wp_json_encode(
			array(
				'id'     => $fixture_post_id,
				'before' => $before,
			),
			JSON_PRETTY_PRINT
		)
	);
	WP_CLI::line(
		wp_json_encode(
			array(
				'id'     => $fixture_post_id,
				'editor' => get_edit_post_link( $fixture_post_id, 'raw' ),
			)
		)
	);
	return;
}

$state = json_decode( file_get_contents( $state_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Explicit local test state.
if ( ! is_array( $state ) || '2026-10-07' !== get_post_meta( $state['id'], '_mrn_lazy_layout_fixture', true ) ) {
	throw new RuntimeException( 'Fixture ownership could not be verified.' );
}

if ( 'block' === $fixture_mode ) {
	if ( ! empty( $state['block'] ) ) {
		throw new RuntimeException( 'Fixture block already exists.' );
	}
	$block = wp_insert_post(
		array(
			'post_type'   => 'mrn_reusable_basic',
			'post_status' => 'publish',
			'post_title'  => 'MRN Lazy Layout Fixture Block',
			'meta_input'  => array( '_mrn_lazy_layout_fixture' => '2026-10-07' ),
		),
		true
	);
	if ( is_wp_error( $block ) ) {
		throw new RuntimeException( esc_html( $block->get_error_message() ) );
	}
	$state['block'] = $block;
	file_put_contents( $state_path, wp_json_encode( $state, JSON_PRETTY_PRINT ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Explicit local test state.
	WP_CLI::line( wp_json_encode( array( 'block' => $block ) ) );
	return;
}

if ( 'block-data' === $fixture_mode ) {
	if ( empty( $state['block'] ) || '2026-10-07' !== get_post_meta( $state['block'], '_mrn_lazy_layout_fixture', true ) ) {
		throw new RuntimeException( 'Fixture block ownership could not be verified.' );
	}
	update_field( 'field_mrn_basic_block_title', 'Lazy conversion heading', $state['block'] );
	update_field( 'field_mrn_basic_block_text', '<p>Lazy conversion content.</p>', $state['block'] );
	WP_CLI::line( 'Owned reusable-block conversion data prepared.' );
	return;
}

if ( 'request' === $fixture_mode ) {
	$case = $args[1] ?? 'valid';
	if ( ! function_exists( 'set_current_screen' ) ) {
		require_once ABSPATH . 'wp-admin/includes/admin.php';
	}
	set_current_screen( 'page' );
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	wp_set_current_user( 'capability' === $case ? 0 : (int) $admins[0] );
	$key   = $args[2] ?? 'field_mrn_page_after_content_rows';
	$_POST = array(
		'field_key'  => $key,
		'post_id'    => $state['id'],
		'nonce'      => 'nonce' === $case ? 'invalid' : wp_create_nonce( 'acf_field_flexible_content_' . $key ),
		'input_name' => 'input' === $case ? 'acf[x"><script>]' : 'acf[' . $key . ']',
		'layout'     => 'layout' === $case ? 'nonexistent_layout' : ( $args[3] ?? 'reusable_block' ),
	);
	mrn_base_stack_ajax_builder_layout();
	return;
}

if ( mrn_lazy_fixture_page_hashes() !== $state['before'] ) {
	throw new RuntimeException( 'Published page content or metadata changed during qualification.' );
}

if ( 'check' === $fixture_mode ) {
	$rows  = get_field( 'page_content_rows', $state['id'] );
	$after = get_field( 'page_after_content_rows', $state['id'] );
	$hero  = get_field( 'page_hero_rows', $state['id'] );
	WP_CLI::line(
		wp_json_encode(
			array(
				'published_pages_unchanged' => true,
				'content_rows'              => $rows,
				'after_rows'                => $after,
				'hero_rows'                 => $hero,
			),
			JSON_PRETTY_PRINT
		)
	);
	return;
}

if ( 'cleanup' === $fixture_mode ) {
	wp_trash_post( $state['id'] );
	if ( ! empty( $state['block'] ) && '2026-10-07' === get_post_meta( $state['block'], '_mrn_lazy_layout_fixture', true ) ) {
		wp_trash_post( $state['block'] );
	}
	WP_CLI::line( 'Owned local fixture moved to Trash; published pages unchanged.' );
	return;
}

throw new RuntimeException( 'Unknown fixture mode.' );

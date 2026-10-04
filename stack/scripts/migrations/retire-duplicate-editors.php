<?php
/**
 * Retire duplicate editor plugins after the site's SEOPress migration.
 *
 * wp eval-file retire-duplicate-editors.php plan
 * wp eval-file retire-duplicate-editors.php apply <plan-sha256>
 * wp eval-file retire-duplicate-editors.php rollback <plan-sha256>
 *
 * Remote apply/rollback must run inside the approved, freshly backed-up deployment
 * workflow. This file does not create or claim a backup. It never deletes legacy
 * metadata, plugin files, AI tables, or Schema Bridge overrides.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 'WP-CLI only.' );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$mode = $args[0] ?? 'plan';
$expected = $args[1] ?? '';
if ( ! in_array( $mode, array( 'plan', 'apply', 'rollback' ), true ) || is_multisite() ) {
	WP_CLI::error( 'Use plan, apply, or rollback on a qualified single-site installation.' );
}
if ( 'plan' !== $mode && ! preg_match( '/^[a-f0-9]{64}$/', $expected ) ) {
	WP_CLI::error( 'A reviewed 64-character plan hash is required.' );
}
$journal_key = 'mrn_editor_retirement_' . $expected;
$lock_key = 'mrn_editor_retirement_lock';

if ( 'rollback' === $mode ) {
	$journal = get_option( $journal_key );
	if ( ! is_array( $journal ) || $journal['home'] !== home_url() ) {
		WP_CLI::error( 'No matching site journal.' );
	}
	if ( 'rolled-back' === ( $journal['status'] ?? '' ) ) {
		WP_CLI::success( 'This migration is already rolled back.' );
		return;
	}
	foreach ( $journal['copies'] as $copy ) {
		$values = get_post_meta( $copy['post_id'], $copy['target'], false );
		if ( array() !== $values && array( $copy['value'] ) !== $values ) {
			WP_CLI::error( 'Rollback refused: SEO metadata changed after migration on post ' . $copy['post_id'] );
		}
	}
	$lock = get_option( $lock_key );
	if ( $lock && $lock !== $expected ) {
		WP_CLI::error( 'Another migration owns the lock.' );
	}
	if ( ! $lock && ! add_option( $lock_key, $expected, '', false ) ) {
		WP_CLI::error( 'Could not acquire rollback lock.' );
	}
	foreach ( $journal['copies'] as $copy ) {
		delete_post_meta( $copy['post_id'], $copy['target'], $copy['value'] );
		if ( metadata_exists( 'post', $copy['post_id'], $copy['target'] ) ) {
			WP_CLI::error( 'Metadata rollback failed; lock retained.' );
		}
	}
	foreach ( $journal['deactivate'] as $plugin ) {
		// Restore activation state without re-running legacy bulk seed/sync hooks.
		$result = activate_plugin( $plugin, '', false, true );
		if ( is_wp_error( $result ) || ! is_plugin_active( $plugin ) ) {
			WP_CLI::error( 'Plugin reactivation failed; journal and lock retained: ' . $plugin );
		}
	}
	$journal['status'] = 'rolled-back';
	update_option( $journal_key, $journal, false );
	delete_option( $lock_key );
	WP_CLI::success( 'Rolled back exact migration additions and prior active plugins. Legacy data was preserved.' );
	return;
}

$installed = get_plugins();
$active = (array) get_option( 'active_plugins', array() );
sort( $active );
$blockers = array();
foreach ( array( 'wp-seopress/seopress.php', 'wp-seopress-pro/seopress-pro.php' ) as $provider ) {
	if ( ! is_plugin_active( $provider ) ) {
		$blockers[] = 'Native SEO provider must be active: ' . $provider;
	}
}
foreach ( $active as $plugin ) {
	if ( preg_match( '#^(wpmu-dev-seo|smartcrawl-seo)/#', $plugin ) ) {
		$blockers[] = 'Complete and verify the native SmartCrawl import before editor retirement.';
	}
}
$retired = array( 'mrn-seo-helper', 'mrn-acf-character-count', 'mrn-ai-assist', 'mrn-editor-tools' );
$deactivate = array_values( array_filter( $active, static function ( $plugin ) use ( $retired ) {
	return in_array( explode( '/', $plugin )[0], $retired, true );
} ) );
$versions = array();
foreach ( $active as $plugin ) {
	$versions[$plugin] = $installed[$plugin]['Version'] ?? 'unknown';
}
$map = array(
	'mrn_seo_title' => '_seopress_titles_title',
	'mrn_seo_description' => '_seopress_titles_desc',
	'mrn_seo_focus_keywords' => '_seopress_analysis_target_kw',
);
$copies = array();
$snapshot = array();
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read-only, parameterized migration inventory.
$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s) ORDER BY post_id", ...array_keys( $map ) ) );
if ( $wpdb->last_error ) {
	WP_CLI::error( 'Metadata inventory failed.' );
}
foreach ( $ids as $id ) {
	foreach ( $map as $source => $target ) {
		$old = get_post_meta( $id, $source, false );
		$new = get_post_meta( $id, $target, false );
		$snapshot[] = array( (int) $id, $source, $old, $target, $new );
		if ( array() === $old || array( '' ) === $old ) {
			continue;
		}
		if ( 1 !== count( $old ) || ! is_string( $old[0] ) ) {
			$blockers[] = 'Ambiguous legacy metadata: post ' . $id . ', ' . $source;
			continue;
		}
		if ( $new === $old ) {
			continue;
		}
		if ( array() !== $new ) {
			$blockers[] = 'Resolve native/legacy metadata conflict: post ' . $id . ', ' . $target;
			continue;
		}
		$copies[] = array( 'post_id' => (int) $id, 'source' => $source, 'target' => $target, 'value' => $old[0] );
	}
}
$plan = array( 'home' => home_url(), 'versions' => $versions, 'active' => $active, 'copies' => $copies, 'deactivate' => $deactivate, 'snapshot' => $snapshot );
$hash = hash( 'sha256', wp_json_encode( $plan ) );
WP_CLI::line( wp_json_encode( array( 'home' => $plan['home'], 'plan_sha256' => $hash, 'metadata_to_copy' => count( $copies ), 'deactivate' => $deactivate, 'blockers' => $blockers ), JSON_PRETTY_PRINT ) );
if ( 'plan' === $mode ) {
	return;
}
if ( $blockers || ! hash_equals( $hash, $expected ) ) {
	WP_CLI::error( 'Apply refused: resolve blockers or review a new plan; site state changed.' );
}
if ( ! $copies && ! $deactivate ) {
	WP_CLI::success( 'Already reconciled; no writes needed.' );
	return;
}
if ( get_option( $journal_key ) || ! add_option( $lock_key, $hash, '', false ) ) {
	WP_CLI::error( 'Existing journal or migration lock requires inspection before another apply.' );
}
$journal = array( 'home' => home_url(), 'copies' => $copies, 'deactivate' => $deactivate, 'status' => 'applying' );
if ( ! add_option( $journal_key, $journal, '', false ) ) {
	WP_CLI::error( 'Journal could not be saved; lock retained.' );
}
// Run real deactivation hooks, including AI scheduler cleanup, before copying.
deactivate_plugins( $deactivate );
foreach ( $deactivate as $plugin ) {
	if ( is_plugin_active( $plugin ) ) {
		WP_CLI::error( 'Plugin remains active; journal and lock retained: ' . $plugin );
	}
}
foreach ( $copies as $copy ) {
	if ( ! add_post_meta( $copy['post_id'], $copy['target'], wp_slash( $copy['value'] ), true )
		|| array( $copy['value'] ) !== get_post_meta( $copy['post_id'], $copy['target'], false ) ) {
		WP_CLI::error( 'Metadata copy failed; journal and lock retained. Inspect or roll back this plan.' );
	}
}
$journal['status'] = 'applied';
update_option( $journal_key, $journal, false );
delete_option( $lock_key );
WP_CLI::success( 'Duplicate editor plugins retired; copied metadata verified and all legacy data retained.' );

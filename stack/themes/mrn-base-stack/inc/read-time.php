<?php
/**
 * Reading-time helpers for singular content.
 *
 * @package mrn-base-stack
 */

/**
 * Estimate the reading time for any post type.
 *
 * @param int|null $post_id          Post ID. Defaults to the current post.
 * @param int      $words_per_minute Reading speed used for the estimate.
 * @return int Estimated reading time in minutes.
 */
function mrn_base_stack_get_read_time_minutes( $post_id = null, $words_per_minute = 225 ) {
	$post_id          = $post_id ? absint( $post_id ) : get_the_ID();
	$words_per_minute = max( 1, (int) apply_filters( 'mrn_base_stack_read_time_words_per_minute', $words_per_minute, $post_id ) );

	if ( ! $post_id ) {
		return 1;
	}

	$content = (string) get_post_field( 'post_content', $post_id );
	$content = wp_strip_all_tags( strip_shortcodes( $content ) );
	$content = preg_replace( '/\s+/', ' ', $content );
	$words   = str_word_count( trim( (string) $content ) );
	$minutes = (int) ceil( $words / $words_per_minute );

	return max( 1, (int) apply_filters( 'mrn_base_stack_read_time_minutes', $minutes, $post_id ) );
}

/**
 * Format a reading-time label for any post type.
 *
 * @param int|null $post_id          Post ID. Defaults to the current post.
 * @param int      $words_per_minute Reading speed used for the estimate.
 * @return string Translated reading-time label.
 */
function mrn_base_stack_get_read_time_label( $post_id = null, $words_per_minute = 225 ) {
	$minutes = mrn_base_stack_get_read_time_minutes( $post_id, $words_per_minute );

	return sprintf(
		/* translators: %d: estimated reading time in minutes. */
		_n( '%d min read', '%d min read', $minutes, 'mrn-base-stack' ),
		$minutes
	);
}

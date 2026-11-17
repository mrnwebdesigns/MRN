<?php
/**
 * Example of replacing a single page's featured image.
 *
 * @package MRN_Site_Release
 */

return static function ( $import ) {
	$page = get_page_by_path( 'about', OBJECT, 'page' );
	if ( ! $page || 'publish' !== $page->post_status ) {
		return false;
	}
	$attachment = $import( 'media/approved-hero.webp', 'About page hero', 'Team standing outside their office' );
	if ( get_post_thumbnail_id( $page ) !== $attachment ) {
		set_post_thumbnail( $page, $attachment );
	}
	return get_post_thumbnail_id( $page ) === $attachment;
};

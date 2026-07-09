<?php
/**
 * Shared responsive image helpers.
 *
 * @package mrn-base-stack
 */

/**
 * Resolve an attachment ID from an ACF image value.
 *
 * @param mixed $image ACF image value, attachment ID, or legacy local URL.
 * @return int
 */
function mrn_base_stack_get_image_attachment_id( $image ) {
	if ( is_numeric( $image ) ) {
		return absint( $image );
	}

	if ( is_array( $image ) ) {
		foreach ( array( 'ID', 'id', 'attachment_id' ) as $key ) {
			if ( isset( $image[ $key ] ) && is_numeric( $image[ $key ] ) ) {
				return absint( $image[ $key ] );
			}
		}

		if ( isset( $image['url'] ) && is_string( $image['url'] ) ) {
			return mrn_base_stack_get_image_attachment_id( $image['url'] );
		}

		return 0;
	}

	if ( ! is_string( $image ) ) {
		return 0;
	}

	$url = trim( $image );
	if ( '' === $url ) {
		return 0;
	}

	if ( is_numeric( $url ) ) {
		return absint( $url );
	}

	$attachment_id = attachment_url_to_postid( esc_url_raw( $url ) );
	if ( $attachment_id > 0 ) {
		return absint( $attachment_id );
	}

	$full_size_candidate = preg_replace( '/-\d+x\d+(\.[a-zA-Z0-9]+)(?:\?.*)?$/', '$1', $url );
	if ( is_string( $full_size_candidate ) && $full_size_candidate !== $url ) {
		$attachment_id = attachment_url_to_postid( esc_url_raw( $full_size_candidate ) );
	}

	return $attachment_id > 0 ? absint( $attachment_id ) : 0;
}

/**
 * Determine whether an image value can be rendered through WordPress media.
 *
 * @param mixed $image ACF image value, attachment ID, or legacy local URL.
 * @return bool
 */
function mrn_base_stack_image_has_content( $image ) {
	return mrn_base_stack_get_image_attachment_id( $image ) > 0;
}

/**
 * Get alt text for a normalized image.
 *
 * @param mixed  $image         ACF image value, attachment ID, or legacy local URL.
 * @param int    $attachment_id Attachment ID.
 * @param string $fallback      Optional fallback alt text.
 * @return string
 */
function mrn_base_stack_get_image_alt( $image, $attachment_id = 0, $fallback = '' ) {
	if ( is_array( $image ) && isset( $image['alt'] ) && is_scalar( $image['alt'] ) && '' !== trim( (string) $image['alt'] ) ) {
		return trim( (string) $image['alt'] );
	}

	$attachment_id = $attachment_id > 0 ? absint( $attachment_id ) : mrn_base_stack_get_image_attachment_id( $image );
	if ( $attachment_id > 0 ) {
		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( is_scalar( $alt ) && '' !== trim( (string) $alt ) ) {
			return trim( (string) $alt );
		}
	}

	return trim( (string) $fallback );
}

/**
 * Render an image using WordPress responsive image markup.
 *
 * @param mixed                $image ACF image value, attachment ID, or legacy local URL.
 * @param string|int[]         $size  Registered image size.
 * @param array<string, mixed> $attr  Image attributes.
 * @return string
 */
function mrn_base_stack_get_attachment_image( $image, $size = 'large', array $attr = array() ) {
	$attachment_id = mrn_base_stack_get_image_attachment_id( $image );
	if ( $attachment_id <= 0 ) {
		return '';
	}

	$attr = wp_parse_args(
		$attr,
		array(
			'alt'      => mrn_base_stack_get_image_alt( $image, $attachment_id ),
			'loading'  => 'lazy',
			'decoding' => 'async',
		)
	);

	return (string) wp_get_attachment_image( $attachment_id, $size, false, $attr );
}

/**
 * Resolve a size-specific image URL for CSS background or JS poster use.
 *
 * @param mixed        $image ACF image value, attachment ID, or legacy local URL.
 * @param string|int[] $size  Registered image size.
 * @return string
 */
function mrn_base_stack_get_attachment_image_url( $image, $size = 'large' ) {
	$attachment_id = mrn_base_stack_get_image_attachment_id( $image );
	if ( $attachment_id <= 0 ) {
		return '';
	}

	$url = wp_get_attachment_image_url( $attachment_id, $size );

	return is_string( $url ) ? $url : '';
}

/**
 * Render a decorative responsive image for row background artwork.
 *
 * Background images are presentational by contract. Use a normal row image
 * field when the image communicates content that needs alt text.
 *
 * @param mixed                $image ACF image value, attachment ID, or legacy local URL.
 * @param array<string, mixed> $args  Render options.
 * @return string
 */
function mrn_base_stack_get_background_image_markup( $image, array $args = array() ) {
	$attachment_id = mrn_base_stack_get_image_attachment_id( $image );
	if ( $attachment_id <= 0 ) {
		return '';
	}

	$args = wp_parse_args(
		$args,
		array(
			'class'         => 'mrn-row-background-media',
			'img_class'     => 'mrn-row-background-media__image',
			'size'          => 'mrn-background',
			'loading'       => 'lazy',
			'decoding'      => 'async',
			'fetchpriority' => '',
			'sizes'         => '100vw',
		)
	);

	$wrapper_classes = preg_split( '/\s+/', trim( (string) $args['class'] ) );
	$wrapper_classes = array_filter( array_map( 'sanitize_html_class', is_array( $wrapper_classes ) ? $wrapper_classes : array() ) );

	if ( empty( $wrapper_classes ) ) {
		$wrapper_classes = array( 'mrn-row-background-media' );
	}

	$image_classes = preg_split( '/\s+/', trim( (string) $args['img_class'] ) );
	$image_classes = array_filter( array_map( 'sanitize_html_class', is_array( $image_classes ) ? $image_classes : array() ) );

	if ( empty( $image_classes ) ) {
		$image_classes = array( 'mrn-row-background-media__image' );
	}

	$image_attr = array(
		'alt'      => '',
		'class'    => implode( ' ', $image_classes ),
		'decoding' => sanitize_key( (string) $args['decoding'] ),
		'loading'  => sanitize_key( (string) $args['loading'] ),
		'sizes'    => trim( (string) $args['sizes'] ),
	);

	if ( '' !== trim( (string) $args['fetchpriority'] ) ) {
		$image_attr['fetchpriority'] = sanitize_key( (string) $args['fetchpriority'] );
	}

	if ( '' === $image_attr['decoding'] ) {
		unset( $image_attr['decoding'] );
	}

	if ( '' === $image_attr['loading'] ) {
		unset( $image_attr['loading'] );
	}

	if ( '' === $image_attr['sizes'] ) {
		unset( $image_attr['sizes'] );
	}

	$image_markup = wp_get_attachment_image( $attachment_id, $args['size'], false, $image_attr );

	if ( '' === $image_markup ) {
		return '';
	}

	return sprintf(
		'<div class="%1$s" data-mrn-row-background-media aria-hidden="true">%2$s</div>',
		esc_attr( implode( ' ', $wrapper_classes ) ),
		$image_markup
	);
}

/**
 * Resolve a local video upload value into a URL and MIME type.
 *
 * @param mixed $video_upload ACF file value, attachment ID, or URL.
 * @return array{url:string,mime:string}
 */
function mrn_base_stack_get_video_upload_source( $video_upload ) {
	$source = array(
		'url'  => '',
		'mime' => '',
	);

	if ( is_array( $video_upload ) ) {
		$url  = isset( $video_upload['url'] ) && is_scalar( $video_upload['url'] ) ? trim( (string) $video_upload['url'] ) : '';
		$mime = isset( $video_upload['mime_type'] ) && is_scalar( $video_upload['mime_type'] ) ? trim( (string) $video_upload['mime_type'] ) : '';

		if ( '' !== $url ) {
			$source['url']  = esc_url_raw( $url );
			$source['mime'] = sanitize_mime_type( $mime );
			return $source;
		}

		foreach ( array( 'ID', 'id', 'attachment_id' ) as $key ) {
			if ( isset( $video_upload[ $key ] ) && is_numeric( $video_upload[ $key ] ) ) {
				$video_upload = absint( $video_upload[ $key ] );
				break;
			}
		}
	}

	if ( is_numeric( $video_upload ) ) {
		$attachment_id = absint( $video_upload );
		$url           = $attachment_id > 0 ? wp_get_attachment_url( $attachment_id ) : '';

		if ( is_string( $url ) && '' !== $url ) {
			$source['url']  = esc_url_raw( $url );
			$source['mime'] = sanitize_mime_type( (string) get_post_mime_type( $attachment_id ) );
		}

		return $source;
	}

	if ( is_string( $video_upload ) && '' !== trim( $video_upload ) ) {
		$source['url'] = esc_url_raw( trim( $video_upload ) );
	}

	return $source;
}

/**
 * Render a deferred decorative background video wrapper.
 *
 * Background videos are presentational by contract. The front-end runtime
 * avoids loading them for reduced-motion users, mobile-only-disabled contexts,
 * and save-data connections.
 *
 * @param mixed                $remote_video Remote YouTube/Vimeo URL.
 * @param mixed                $video_upload ACF file value, attachment ID, or URL.
 * @param array<string, mixed> $args         Render options.
 * @return string
 */
function mrn_base_stack_get_background_video_markup( $remote_video, $video_upload = null, array $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'class'        => 'mrn-section-background-media mrn-row-background-video',
			'poster_image' => null,
			'delay'        => 2000,
			'desktop_only' => true,
		)
	);

	$video_source = mrn_base_stack_get_video_upload_source( $video_upload );
	$video_url    = $video_source['url'];
	$video_kind   = '' !== $video_url ? 'local' : '';
	$video_mime   = $video_source['mime'];

	if ( '' === $video_url && function_exists( 'mrn_base_stack_get_video_embed' ) ) {
		$video_embed = mrn_base_stack_get_video_embed(
			$remote_video,
			array(
				'autoplay'   => true,
				'muted'      => true,
				'loop'       => true,
				'controls'   => false,
				'background' => true,
			)
		);
		$video_url   = isset( $video_embed['embed_url'] ) ? (string) $video_embed['embed_url'] : '';
		$video_kind  = '' !== $video_url ? 'remote' : '';
	}

	if ( '' === $video_url ) {
		return '';
	}

	$wrapper_classes = preg_split( '/\s+/', trim( (string) $args['class'] ) );
	$wrapper_classes = array_filter( array_map( 'sanitize_html_class', is_array( $wrapper_classes ) ? $wrapper_classes : array() ) );

	if ( empty( $wrapper_classes ) ) {
		$wrapper_classes = array( 'mrn-section-background-media', 'mrn-row-background-video' );
	}

	$attrs = array(
		'class'                   => implode( ' ', $wrapper_classes ),
		'data-mrn-row-background-video' => 'true',
		'data-video-src'          => esc_url( $video_url ),
		'data-video-kind'         => $video_kind,
		'data-video-background'   => 'true',
		'data-video-autoplay'     => 'true',
		'data-video-muted'        => 'true',
		'data-video-loop'         => 'true',
		'data-video-controls'     => 'false',
		'data-video-delay'        => max( 0, (int) $args['delay'] ),
		'data-video-desktop-only' => ! empty( $args['desktop_only'] ) ? 'true' : 'false',
		'aria-hidden'             => 'true',
	);

	if ( 'local' === $video_kind && '' !== $video_mime ) {
		$attrs['data-video-mime'] = $video_mime;
	}

	$poster_url = mrn_base_stack_get_attachment_image_url( $args['poster_image'], 'mrn-background' );
	if ( '' !== $poster_url ) {
		$attrs['data-video-poster'] = esc_url( $poster_url );
	}

	$attr_html = '';
	foreach ( $attrs as $name => $value ) {
		if ( '' === trim( (string) $value ) ) {
			continue;
		}

		$attr_html .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( (string) $value ) );
	}

	return '<div' . $attr_html . '></div>';
}

/**
 * Build a CSS custom-property declaration for a selected background image.
 *
 * @param mixed  $image   ACF image value, attachment ID, or legacy local URL.
 * @param string $css_var CSS custom property name.
 * @return string
 */
function mrn_base_stack_get_background_image_style( $image, $css_var ) {
	$image_url = mrn_base_stack_get_attachment_image_url( $image, 'mrn-background' );
	$css_var   = trim( (string) $css_var );

	if ( '' === $image_url || '' === $css_var ) {
		return '';
	}

	return $css_var . ": url('" . esc_url_raw( $image_url ) . "')";
}

/**
 * Resolve the full image source tuple when dimensions are needed separately.
 *
 * @param mixed        $image ACF image value, attachment ID, or legacy local URL.
 * @param string|int[] $size  Registered image size.
 * @return array<int, mixed>|false
 */
function mrn_base_stack_get_attachment_image_src( $image, $size = 'large' ) {
	$attachment_id = mrn_base_stack_get_image_attachment_id( $image );
	if ( $attachment_id <= 0 ) {
		return false;
	}

	return wp_get_attachment_image_src( $attachment_id, $size );
}

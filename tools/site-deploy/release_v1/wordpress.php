<?php
/**
 * Host-local WP-CLI runner for approved, immutable site migrations.
 *
 * @package MRN_Site_Release
 */

namespace MRN\SiteReleaseV1;

/**
 * Resolve and verify a private artifact member.
 *
 * @param array  $context Verified migration context.
 * @param string $name Declared file name.
 * @return string
 */
function member( $context, $name ) {
	$path = $context['root'] . '/' . $name;
	if ( ! isset( $context['files'][ $name ] ) || realpath( $path ) !== $path || ! is_file( $path )
		|| ! hash_equals( $context['files'][ $name ], hash_file( 'sha256', $path ) ) ) {
		throw new \RuntimeException( 'Migration file differs from its declared artifact hash.' );
	}
	return $path;
}

/**
 * Validate the attachment and its generated styles before it can be referenced.
 *
 * @param int    $attachment WordPress attachment ID.
 * @param string $path Exact expected upload path.
 * @param string $sha Approved asset checksum.
 * @return void
 */
function verify_attachment( $attachment, $path, $sha ) {
	$metadata = wp_get_attachment_metadata( $attachment );
	if ( 'attachment' !== get_post_type( $attachment ) || get_attached_file( $attachment, true ) !== $path
		|| realpath( $path ) !== $path || ! hash_equals( $sha, hash_file( 'sha256', $path ) )
		|| empty( $metadata['width'] ) || empty( $metadata['height'] ) || ! wp_get_attachment_url( $attachment ) ) {
		throw new \RuntimeException( 'Attachment file, URL or image metadata could not be verified.' );
	}
	foreach ( $metadata['sizes'] ?? array() as $size ) {
		$derived = dirname( $path ) . '/' . wp_basename( $size['file'] );
		if ( realpath( $derived ) !== $derived || ! is_file( $derived ) ) {
			throw new \RuntimeException( 'Attachment derivative is missing or aliased.' );
		}
	}
}

/**
 * Name the original file from its migration, declared path and immutable bytes.
 *
 * @param array  $context Verified deployment context.
 * @param string $name Declared image path.
 * @return string
 */
function media_filename( $context, $name ) {
	$key = hash( 'sha256', $context['row']['id'] . "\0" . $name . "\0" . $context['files'][ $name ] );
	return $key . '.' . strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
}

/**
 * Import a versioned image under a deterministic, migration-owned key.
 *
 * @param array  $context Verified deployment context.
 * @param string $name Declared media path.
 * @param string $title Approved attachment title.
 * @param string $alt Approved alt text, including empty for decorative images.
 * @param string $caption Approved caption.
 * @return int
 */
function import_image( $context, $name, $title, $alt, $caption ) {
	global $wpdb;
	if ( ! in_array( $name, $context['row']['assets'], true ) ) {
		throw new \RuntimeException( 'Media path was not declared for this migration.' );
	}
	$source  = member( $context, $name );
	$sha     = $context['files'][ $name ];
	$file    = media_filename( $context, $name );
	$key     = pathinfo( $file, PATHINFO_FILENAME );
	$marker  = 'mrn-site-release-v1:' . $key;
	$uploads = wp_get_upload_dir();
	$base    = $uploads['basedir'];
	$dir     = $base . '/mrn-release-media';
	$ext     = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	$path    = $dir . '/' . $file;
	$type    = wp_check_filetype_and_ext( $source, $file );
	if ( $uploads['error'] || realpath( $base ) !== $base || ! in_array( $type['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true )
		|| $type['ext'] !== $ext || ! empty( $type['proper_filename'] ) ) {
		throw new \RuntimeException( 'WordPress rejected the upload directory or file format.' );
	}

	// Read the durable marker stored in the original insert, including after a
	// stop before attachment metadata was written. An unowned record is an error.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verify the exact current database state.
	$records = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, post_content_filtered FROM {$wpdb->posts} WHERE post_type = 'attachment' AND (post_name = %s OR guid = %s) LIMIT 2",
			'mrn-' . $key,
			$uploads['baseurl'] . '/mrn-release-media/' . $file
		)
	);
	if ( $wpdb->last_error || count( $records ) > 1 || ( $records && $records[0]->post_content_filtered !== $marker ) ) {
		throw new \RuntimeException( 'Ambiguous or unowned migration attachment.' );
	}

	if ( ! wp_mkdir_p( $dir ) || realpath( $dir ) !== $dir ) {
		throw new \RuntimeException( 'Migration upload directory is unavailable or aliased.' );
	}
	if ( ! file_exists( $path ) ) {
		// Publish once with an exclusive hard link. An interrupted run may leave
		// a temporary copy, but can never select partial or replaced original bytes.
		$temp = wp_tempnam( $file, trailingslashit( $dir ) );
		try {
			if ( ! $temp || realpath( dirname( $temp ) ) !== $dir || ! copy( $source, $temp )
				|| ! hash_equals( $sha, hash_file( 'sha256', $temp ) )
				|| ! chmod( $temp, 0644 ) || ! link( $temp, $path ) ) {
				throw new \RuntimeException( 'Could not publish verified media without overwriting.' );
			}
		} finally {
			if ( $temp && dirname( $temp ) === $dir ) {
				wp_delete_file( $temp );
			}
		}
	}
	if ( realpath( $path ) !== $path || ! hash_equals( $sha, hash_file( 'sha256', $path ) ) ) {
		throw new \RuntimeException( 'Existing media differs from the approved source.' );
	}

	$attachment = $records ? (int) $records[0]->ID : wp_insert_attachment(
		array(
			'guid'                  => $uploads['baseurl'] . '/mrn-release-media/' . $file,
			'post_name'             => 'mrn-' . $key,
			'post_content_filtered' => $marker,
			'post_title'            => sanitize_text_field( $title ),
			'post_excerpt'          => sanitize_textarea_field( $caption ),
			'post_mime_type'        => $type['type'],
			'post_status'           => 'inherit',
		),
		$path,
		0,
		true
	);
	if ( ! $attachment || is_wp_error( $attachment ) ) {
		throw new \RuntimeException( 'WordPress rejected migration attachment creation.' );
	}
	$attached = get_attached_file( $attachment, true );
	if ( $attached && $attached !== $path ) {
		throw new \RuntimeException( 'Migration attachment points to different media.' );
	}
	update_attached_file( $attachment, $path );
	if ( ! wp_get_attachment_metadata( $attachment ) ) {
		add_filter( 'big_image_size_threshold', '__return_false', PHP_INT_MAX );
		try {
			wp_update_attachment_metadata( $attachment, wp_generate_attachment_metadata( $attachment, $path ) );
		} finally {
			remove_filter( 'big_image_size_threshold', '__return_false', PHP_INT_MAX );
		}
	}
	update_post_meta( $attachment, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
	if ( get_post_meta( $attachment, '_wp_attachment_image_alt', true ) !== sanitize_text_field( $alt ) ) {
		throw new \RuntimeException( 'Migration attachment alt text could not be verified.' );
	}
	verify_attachment( $attachment, $path, $sha );
	return $attachment;
}

/**
 * Apply one migration and record its durable WordPress fingerprint.
 *
 * @param array $context Verified migration context provided by the host controller.
 * @return array
 */
function apply_migration( $context ) {
	$target = $context['target'];
	$row    = $context['row'];
	$type   = wp_get_environment_type();
	$env    = 'development' === $type ? 'dev' : $type;
	if ( home_url() !== $target['origin'] || $env !== $target['environment']
		|| ! in_array( $env, $row['environments'], true ) || empty( $context['backup_nonce'] )
		|| ! preg_match( '/^[a-f0-9]{64}$/D', $context['fingerprint'] ) ) {
		throw new \RuntimeException( 'Backup, WordPress origin or environment binding failed.' );
	}
	foreach ( array_merge( array( $row['script'] ), $row['assets'] ) as $name ) {
		member( $context, $name );
	}
	$key      = 'mrn_site_release_' . hash( 'sha256', wp_json_encode( array( $target, $row['id'] ) ) );
	$previous = get_option( $key, false );
	$result   = array(
		'id'          => $row['id'],
		'fingerprint' => $context['fingerprint'],
		'result'      => 'already-applied',
	);
	if ( false !== $previous ) {
		if ( ! is_array( $previous ) || ( $previous['fingerprint'] ?? null ) !== $context['fingerprint'] ) {
			throw new \RuntimeException( 'Migration ID already belongs to different bytes. Use a new migration ID.' );
		}
		$uploads = wp_get_upload_dir();
		foreach ( $row['assets'] as $name ) {
			$attachment = $previous['attachments'][ $name ] ?? 0;
			$path       = $uploads['basedir'] . '/mrn-release-media/' . media_filename( $context, $name );
			verify_attachment( $attachment, $path, $context['files'][ $name ] );
		}
		$result['attachments'] = (object) $previous['attachments'];
		return $result;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$migration = require member( $context, $row['script'] );
	if ( ! $migration instanceof \Closure ) {
		throw new \RuntimeException( 'Migration must return a closure.' );
	}
	$attachments = array();
	$import      = static function ( $name, $title, $alt, $caption = '' ) use ( $context, &$attachments ) {
		if ( isset( $attachments[ $name ] ) ) {
			throw new \RuntimeException( 'An approved image may be imported only once per migration.' );
		}
		$attachments[ $name ] = import_image( $context, $name, $title, $alt, $caption );
		return $attachments[ $name ];
	};
	if ( true !== $migration( $import ) ) {
		throw new \RuntimeException( 'Migration must resolve and verify the intended record.' );
	}
	if ( count( $attachments ) !== count( $row['assets'] ) ) {
		throw new \RuntimeException( 'The migration did not import every declared image.' );
	}
	$completed = array(
		'fingerprint' => $context['fingerprint'],
		'attachments' => $attachments,
	);
	if ( ! add_option( $key, $completed, '', false ) && get_option( $key ) !== $completed ) {
		throw new \RuntimeException( 'WordPress could not record the completion fingerprint.' );
	}
	$result['result']      = 'applied';
	$result['attachments'] = (object) $attachments;
	return $result;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

try {
	$mrn_context = json_decode( getenv( 'MRN_RELEASE_MIGRATION' ), true, 32, JSON_THROW_ON_ERROR );
	\WP_CLI::line( 'MRN_MIGRATION=' . wp_json_encode( apply_migration( $mrn_context ) ) );
} catch ( \Throwable $mrn_error ) {
	\WP_CLI::error( $mrn_error->getMessage() );
}

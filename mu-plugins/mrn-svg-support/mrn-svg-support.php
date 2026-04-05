<?php
/**
 * Plugin Name: Enable SVG Support (MU)
 * Description: Allows SVG file uploads in WordPress.
 * Author: MRN Web Designs
 * Version: 1.0
 */

/**
 * Allow SVG uploads via mime types.
 */
add_filter('upload_mimes', function ($mimes) {
    $mimes['svg']  = 'image/svg+xml';
    $mimes['svgz'] = 'image/svg+xml';
    return $mimes;
});

/**
 * Fix SVG display in Media Library.
 */
add_action('admin_head', function () {
    echo '<style>
        .attachment-266x266,
        .thumbnail img {
            width: 100% !important;
            height: auto !important;
        }
    </style>';
});

/**
 * Allow SVG preview and avoid "Sorry, this file type is not permitted".
 */
add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename, $mimes) {
    if (strpos($filename, '.svg') !== false) {
        $data['ext']  = 'svg';
        $data['type'] = 'image/svg+xml';
    }
    return $data;
}, 10, 4);
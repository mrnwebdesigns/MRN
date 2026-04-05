<?php
/**
 * Plugin Name: Disable Comments (MU)
 * Description: Fully disables comments everywhere (UI + admin menu + admin bar + REST + XML-RPC + submission blocking).
 * Version: 1.2.3
 */

defined('ABSPATH') || exit;

/**
 * Block comment creation everywhere.
 */
add_filter('preprocess_comment', function () {
  wp_die('Comments are disabled on this site.', 'Comments Disabled', array('response' => 403));
}, PHP_INT_MAX);

/**
 * Force comments/pings closed everywhere.
 */
add_filter('comments_open', '__return_false', PHP_INT_MAX, 2);
add_filter('pings_open', '__return_false', PHP_INT_MAX, 2);
add_filter('comments_array', '__return_empty_array', PHP_INT_MAX, 2);
add_filter('get_comments_number', '__return_zero', PHP_INT_MAX);

/**
 * Defaults closed (new content + discussion defaults).
 */
function mrn_dc_return_closed() {
  return 'closed';
}
add_filter('pre_option_default_comment_status', 'mrn_dc_return_closed');
add_filter('pre_option_default_ping_status', 'mrn_dc_return_closed');
add_filter('pre_option_default_comment_status_page', 'mrn_dc_return_closed');

/**
 * Strip support from all post types (including late-registered CPTs).
 */
function mrn_dc_strip_support_all() {
  $types = get_post_types(array(), 'names');
  if (!is_array($types)) return;

  foreach ($types as $post_type) {
    remove_post_type_support($post_type, 'comments');
    remove_post_type_support($post_type, 'trackbacks');
  }
}
add_action('init', 'mrn_dc_strip_support_all', PHP_INT_MAX);

add_action('registered_post_type', function ($post_type) {
  if (!is_string($post_type) || $post_type === '') return;
  remove_post_type_support($post_type, 'comments');
  remove_post_type_support($post_type, 'trackbacks');
}, PHP_INT_MAX, 1);

/**
 * Remove comment-related meta boxes.
 */
add_action('add_meta_boxes', function () {
  $types = get_post_types(array(), 'names');
  if (!is_array($types)) return;

  foreach ($types as $post_type) {
    remove_meta_box('commentstatusdiv', $post_type, 'normal');
    remove_meta_box('commentsdiv', $post_type, 'normal');
    remove_meta_box('trackbacksdiv', $post_type, 'normal');
  }
}, PHP_INT_MAX);

/**
 * Admin: block direct access to the Comments screen.
 */
add_action('admin_init', function () {
  if (!is_admin()) return;

  $pagenow = isset($GLOBALS['pagenow']) ? (string) $GLOBALS['pagenow'] : '';
  if ($pagenow === 'edit-comments.php') {
    wp_die('Comments are disabled on this site.', 'Comments Disabled', array('response' => 403));
  }
}, PHP_INT_MAX);

/**
 * Admin UI: remove menu pages + sweep out anything "comment"-like added by plugins.
 */
function mrn_dc_remove_comment_menus() {
  // Core comments screen.
  remove_menu_page('edit-comments.php');

  // Also remove common plugin comment slugs (safe no-ops if not present).
  remove_menu_page('comments');
  remove_menu_page('comment');
  remove_menu_page('wp-comments');

  // Sweep: remove any top-level menu item whose slug/title suggests comments.
  global $menu;
  if (!is_array($menu)) return;

  foreach ($menu as $index => $item) {
    if (!is_array($item)) continue;

    $title = isset($item[0]) ? strtolower(wp_strip_all_tags((string) $item[0])) : '';
    $slug  = isset($item[2]) ? strtolower((string) $item[2]) : '';

    if (
      $slug === 'edit-comments.php' ||
      strpos($slug, 'comment') !== false ||
      strpos($title, 'comment') !== false
    ) {
      unset($menu[$index]);
    }
  }
}
add_action('admin_menu', 'mrn_dc_remove_comment_menus', PHP_INT_MAX);
add_action('network_admin_menu', 'mrn_dc_remove_comment_menus', PHP_INT_MAX);

/**
 * Admin bar: remove comment nodes (core + common variants).
 */
add_action('admin_bar_menu', function ($wp_admin_bar) {
  if (!is_object($wp_admin_bar)) return;

  $wp_admin_bar->remove_node('comments');
  $wp_admin_bar->remove_node('comments-menu');
  $wp_admin_bar->remove_node('wp-comments');
}, PHP_INT_MAX);

/**
 * Dashboard: remove "Recent Comments" and other comment-related widgets if present.
 */
add_action('wp_dashboard_setup', function () {
  remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
  remove_meta_box('dashboard_recent_comments', 'dashboard', 'side');
}, PHP_INT_MAX);

/**
 * REST API: remove comments endpoints.
 */
add_filter('rest_endpoints', function ($endpoints) {
  if (!is_array($endpoints)) return $endpoints;

  unset($endpoints['/wp/v2/comments']);
  unset($endpoints['/wp/v2/comments/(?P<id>[\\d]+)']);

  return $endpoints;
}, PHP_INT_MAX);

/**
 * Feeds: disable comment feeds.
 */
add_action('do_feed_rss2_comments', function () {
  wp_die('Comments are disabled.', '', array('response' => 404));
}, 1);

add_action('do_feed_atom_comments', function () {
  wp_die('Comments are disabled.', '', array('response' => 404));
}, 1);

/**
 * XML-RPC: disable pingbacks.
 */
add_filter('xmlrpc_methods', function ($methods) {
  if (!is_array($methods)) return $methods;

  unset($methods['pingback.ping']);
  unset($methods['pingback.extensions.getPingbacks']);

  return $methods;
}, PHP_INT_MAX);

/**
 * Hide discussion settings UI (optional cleanup).
 */
add_action('admin_init', function () {
  remove_meta_box('commentstatusdiv', 'post', 'normal');
  remove_meta_box('commentstatusdiv', 'page', 'normal');

  // Block direct access to Settings > Discussion.
  $pagenow = isset($GLOBALS['pagenow']) ? (string) $GLOBALS['pagenow'] : '';
  if ($pagenow === 'options-discussion.php') {
    wp_die('Comments are disabled on this site.', 'Comments Disabled', array('response' => 403));
  }
}, PHP_INT_MAX);

/**
 * Remove comment-related columns in list tables where possible.
 */
add_filter('manage_posts_columns', function ($cols) {
  if (is_array($cols) && isset($cols['comments'])) unset($cols['comments']);
  return $cols;
}, PHP_INT_MAX);

add_filter('manage_pages_columns', function ($cols) {
  if (is_array($cols) && isset($cols['comments'])) unset($cols['comments']);
  return $cols;
}, PHP_INT_MAX);

/**
 * Remove comments column from all post type list tables (including CPTs).
 */
add_action('init', function () {
  $types = get_post_types(array(), 'names');
  if (!is_array($types)) {
    return;
  }

  foreach ($types as $post_type) {
    if (!is_string($post_type) || $post_type === '') {
      continue;
    }

    add_filter("manage_{$post_type}_posts_columns", function ($cols) {
      if (is_array($cols) && isset($cols['comments'])) {
        unset($cols['comments']);
      }
      return $cols;
    }, PHP_INT_MAX);
  }
}, PHP_INT_MAX);

/**
 * Dashboard + admin cleanup for lingering comment UI pieces.
 */
add_filter('dashboard_glance_items', function ($items) {
  if (!is_array($items)) {
    return $items;
  }

  return array_values(array_filter($items, function ($item) {
    return stripos(wp_strip_all_tags((string) $item), 'comment') === false;
  }));
}, PHP_INT_MAX);

add_action('admin_head', function () {
  if (!is_admin()) {
    return;
  }
  ?>
  <style>
    /* Hide comments menu and counters if anything re-adds them */
    #menu-comments,
    #wp-admin-bar-comments,
    .comment-count,
    .column-comments,
    #dashboard_recent_comments,
    .welcome-comments {
      display: none !important;
    }

    /* Hide block/classic editor discussion-related controls */
    .editor-post-discussion-panel,
    .edit-post-post-comment-status,
    #commentstatusdiv,
    #commentsdiv,
    #trackbacksdiv {
      display: none !important;
    }
  </style>
  <?php
}, PHP_INT_MAX);

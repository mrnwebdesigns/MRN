<?php
/**
 * Plugin Name: Admin UI CSS (MU Legacy)
 * Description: Legacy admin CSS loader retained for backwards compatibility. Automatically stands down when the unified MRN Admin UI CSS loader is present.
 * Version: 1.0.8
 */

defined('ABSPATH') || exit;

if (defined('WPMU_PLUGIN_DIR')) {
  $unified_loader = trailingslashit(WPMU_PLUGIN_DIR) . 'mrn-admin-ui-css.php';
  $this_file = function_exists('wp_normalize_path') ? wp_normalize_path(__FILE__) : __FILE__;
  $unified_file = function_exists('wp_normalize_path') ? wp_normalize_path($unified_loader) : $unified_loader;

  if (is_readable($unified_loader) && $this_file !== $unified_file) {
    return;
  }
}

add_action('admin_enqueue_scripts', function ($hook) {

  /**
   * ------------------------------------------------------------
   * HARD SKIPS — NEVER LOAD HERE
   * ------------------------------------------------------------
   */

  // 1) Site Health
  if ($hook === 'site-health.php' || $hook === 'tools_page_site-health') {
    return;
  }

  // 2) Post editor screens (Classic + Block)
  if (in_array($hook, array('post.php', 'post-new.php'), true)) {
    return;
  }

  // 3) Media Library screens (Grid + List) + Add New Media
  // - upload.php is Media → Library (both modes)
  // - media-new.php is Media → Add New
  if ($hook === 'upload.php' || $hook === 'media-new.php') {
    return;
  }

  // 4) Extra safety: if screen resolves to Media Library, bail
  if (function_exists('get_current_screen')) {
    $screen = get_current_screen();
    if (is_object($screen)) {
      // Media library screen IDs vary a bit; these cover the common ones.
      if (
        (isset($screen->base) && $screen->base === 'upload') ||
        (isset($screen->id) && $screen->id === 'upload')
      ) {
        return;
      }

      // Block editor detection anywhere (belt + suspenders)
      if (method_exists($screen, 'is_block_editor') && $screen->is_block_editor()) {
        return;
      }
    }
  }

  /**
   * ------------------------------------------------------------
   * ENQUEUE ADMIN CSS (MU-safe)
   * ------------------------------------------------------------
   */

  if (!defined('WPMU_PLUGIN_DIR') || !defined('WPMU_PLUGIN_URL')) {
    return;
  }

  // CSS must live directly in /wp-content/mu-plugins/mrn-admin.css
  $abs = trailingslashit(WPMU_PLUGIN_DIR) . 'mrn-admin.css';
  if (!file_exists($abs)) {
    return;
  }

  $url = trailingslashit(WPMU_PLUGIN_URL) . 'mrn-admin.css';

  wp_enqueue_style(
    'mrn-admin-css',
    $url,
    array(),
    (string) filemtime($abs),
    'all'
  );

}, 20);

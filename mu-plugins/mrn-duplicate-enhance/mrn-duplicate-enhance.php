<?php
/**
 * Plugin Name: Post Duplicator Admin Bar Enhance
 * Description: Adds a "Duplicate" item to the front-end admin bar that opens Post Duplicator's duplicate modal in the editor.
 * Author: MRN Web Designs
 * Version: 1.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add "Duplicate" to the admin bar on the front end for single posts/pages.
 * Clicking it goes to the editor with a query flag we use to auto-open the plugin modal.
 */
add_action('admin_bar_menu', function ($wp_admin_bar) {

    // Front-end only
    if (is_admin()) {
        return;
    }

    if (!is_admin_bar_showing()) {
        return;
    }

    // Only on single Posts & Pages
    if (!is_singular(array('post', 'page'))) {
        return;
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $edit_link = get_edit_post_link($post_id, '');
    if (!$edit_link) {
        return;
    }

    // Add flag to tell the editor screen to auto-open the Post Duplicator modal.
    $duplicate_link = add_query_arg('mrn_pd_open_duplicate', '1', $edit_link);
    $duplicate_link = wp_nonce_url($duplicate_link, 'mrn_pd_open_duplicate_' . $post_id, 'mrn_pd_nonce');

    $wp_admin_bar->add_node(array(
        'id'    => 'mrn-post-duplicator-duplicate',
        'title' => 'Duplicate',
        'href'  => $duplicate_link,
        'meta'  => array(
            'title' => 'Duplicate this content (Post Duplicator)',
        ),
    ));

}, PHP_INT_MAX);

/**
 * On the post edit screen, if our flag is present, try to auto-click Post Duplicator's Duplicate button
 * to open its modal UI.
 */
add_action('admin_footer-post.php', 'mrn_pd_autoclick_duplicate_button');
add_action('admin_footer-post-new.php', 'mrn_pd_autoclick_duplicate_button');

function mrn_pd_autoclick_duplicate_button() {

    if (empty($_GET['mrn_pd_open_duplicate'])) {
        return;
    }

    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
    if ($post_id <= 0) {
        return;
    }

    $nonce = isset($_GET['mrn_pd_nonce']) ? sanitize_text_field(wp_unslash($_GET['mrn_pd_nonce'])) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'mrn_pd_open_duplicate_' . $post_id)) {
        return;
    }

    // Permission check before rendering script.
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    ?>
    <script>
    (function () {
      /**
       * Try to find and click the Post Duplicator UI control.
       * We keep selectors broad because plugins/themes vary.
       */
      function findDuplicateControl() {
        // Prefer buttons/links that clearly say "Duplicate"
        var els = Array.prototype.slice.call(document.querySelectorAll('a, button'));

        // First pass: exact/contains "duplicate"
        for (var i = 0; i < els.length; i++) {
          var el = els[i];
          var txt = (el.textContent || '').trim().toLowerCase();
          if (!txt) continue;

          if (txt === 'duplicate' || txt.indexOf('duplicate') !== -1) {
            return el;
          }
        }

        // Second pass: IDs/classes that hint duplicate
        for (var j = 0; j < els.length; j++) {
          var el2 = els[j];
          var id = (el2.id || '').toLowerCase();
          var cls = (el2.className || '').toLowerCase();
          if (id.indexOf('duplicate') !== -1 || cls.indexOf('duplicate') !== -1) {
            return el2;
          }
        }

        return null;
      }

      var attempts = 0;
      var maxAttempts = 30; // ~7.5s at 250ms

      var timer = setInterval(function () {
        attempts++;

        var control = findDuplicateControl();
        if (control) {
          control.click();
          clearInterval(timer);
          return;
        }

        if (attempts >= maxAttempts) {
          clearInterval(timer);
        }
      }, 250);
    })();
    </script>
    <?php
}

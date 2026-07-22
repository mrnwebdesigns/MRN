<?php
/**
 * Plugin Name: MRN Admin UI CSS
 * Description: Unified admin UI CSS loader for wp-admin.
 * Version: 3.2.2
 */

defined('ABSPATH') || exit;

if (!function_exists('mrn_admin_ui_contract_version')) {
  /**
   * Return the semantic MRN admin UI contract version.
   *
   * Consumers must continue to use WordPress-native markup and classes when
   * this function is unavailable. The contract enhances those primitives; it
   * does not replace them.
   *
   * @return string
   */
  function mrn_admin_ui_contract_version() {
    return '1.1.0';
  }
}

if (!function_exists('mrn_admin_ui_contract_get')) {
  /**
   * Expose stable native class and language choices to stack consumers.
   *
   * @return array<string, mixed>
   */
  function mrn_admin_ui_contract_get() {
    $contract = array(
      'version' => mrn_admin_ui_contract_version(),
      'classes' => array(
        'primary_action'     => 'button button-primary',
        'secondary_action'   => 'button',
        'link_action'        => 'button-link',
        'destructive_action' => 'button-link-delete',
        'card_remove'        => 'button-link-delete mrn-admin-card-remove',
        'row_actions'        => 'row-actions',
        'notice_success'     => 'notice notice-success is-dismissible',
        'notice_error'       => 'notice notice-error',
        'tabs'               => 'nav-tab-wrapper',
        'tab'                => 'nav-tab',
        'tab_active'         => 'nav-tab-active',
      ),
      'verbs' => array(
        'trash'              => __('Move to Trash', 'mrn-admin-ui-css'),
        'delete_permanently' => __('Delete permanently', 'mrn-admin-ui-css'),
        'remove'             => __('Remove', 'mrn-admin-ui-css'),
        'restore'            => __('Restore', 'mrn-admin-ui-css'),
      ),
      'icons' => array(
        'trash' => 'dashicons dashicons-trash',
      ),
    );

    return apply_filters('mrn_admin_ui_contract', $contract);
  }
}

add_action('admin_enqueue_scripts', function ($hook) {

  if (!defined('WPMU_PLUGIN_DIR')) {
    return;
  }

  // Never load this cleanup stylesheet on JS-heavy editor/app screens.
  $is_editor_or_app = false;
  if (
    in_array($hook, array('post.php', 'post-new.php', 'upload.php', 'media-new.php', 'site-editor.php', 'widgets.php', 'site-health.php', 'tools_page_site-health'), true)
  ) {
    $is_editor_or_app = true;
  }

  if (!$is_editor_or_app && function_exists('get_current_screen')) {
    $screen = get_current_screen();
    if (is_object($screen)) {
      if (method_exists($screen, 'is_block_editor') && $screen->is_block_editor()) {
        $is_editor_or_app = true;
      }

      if (
        (isset($screen->base) && in_array($screen->base, array('upload', 'site-editor', 'site-health'), true)) ||
        (isset($screen->id) && (
          $screen->id === 'upload' ||
          $screen->id === 'site-health' ||
          strpos((string) $screen->id, 'site-editor') !== false
        ))
      ) {
        $is_editor_or_app = true;
      }
    }
  }

  // Load the shared admin design foundations on every wp-admin screen.
  $foundations_file = trailingslashit(WPMU_PLUGIN_DIR) . 'mrn-admin-ui-css/mrn-admin-foundations.css';
  if (file_exists($foundations_file)) {
    wp_enqueue_style(
      'mrn-admin-ui-foundations',
      content_url('mu-plugins/mrn-admin-ui-css/mrn-admin-foundations.css'),
      array(),
      (string) filemtime($foundations_file),
      'all'
    );
  }

  // Always inject minimal ad-hiding rules, including on editor screens.
  wp_register_style('mrn-admin-ui-ads-only', false, array(), '3.2.2');
  wp_enqueue_style('mrn-admin-ui-ads-only');
  wp_add_inline_style(
    'mrn-admin-ui-ads-only',
    '
    .duplicate-post-modal__marketing-banner,
    a.duplicate-post-modal__marketing-banner[href*="metaphorcreations.com/wordpress-plugins/email-customizer"],
    .mlo-pro-admin-notice.notice,
    #media_library_organizer_review_flag-notification.notice.notice-success.is-dismissible.themeisle-sdk-notice,
    .notice.notice-success.is-dismissible.themeisle-sdk-notice[data-notification-id="media_library_organizer_review_flag"],
    .notice.notice-success.is-dismissible:has(a.button.button-primary[href$="/wp-admin/"]) {
      display: none !important;
    }
    '
  );

  add_action('admin_print_footer_scripts', function () {
    ?>
    <script>
    (function() {
      function hideViewAdminAsNotice(root) {
        var scope = root || document;
        var notices = scope.querySelectorAll('.notice.notice-success.is-dismissible');
        notices.forEach(function(notice) {
          if (!notice) {
            return;
          }
          var text = (notice.textContent || '').replace(/\s+/g, ' ').trim();
          if (text.indexOf('Thank you for installing View Admin As!') !== -1) {
            notice.style.setProperty('display', 'none', 'important');
            notice.setAttribute('hidden', 'hidden');
          }
        });
      }

      hideViewAdminAsNotice(document);

      var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
          mutation.addedNodes.forEach(function(node) {
            if (node && node.nodeType === 1) {
              hideViewAdminAsNotice(node);
            }
          });
        });
      });

      observer.observe(document.body, { childList: true, subtree: true });
    })();
    </script>
    <?php
  }, 99);

  if ($is_editor_or_app) {
    return;
  }

  $plugin_slug = 'mrn-admin-ui-css';
  $css_file = trailingslashit(WPMU_PLUGIN_DIR) . $plugin_slug . '/mrn-admin.css';
  if (!file_exists($css_file)) {
    return;
  }

  $css_url = content_url('mu-plugins/' . $plugin_slug . '/mrn-admin.css');

  wp_enqueue_style(
    'mrn-admin-ui-css',
    $css_url,
    array(),
    (string) filemtime($css_file),
    'all'
  );

  $is_gtm_injector_active = false;
  $gtm_injector_slug = 'mrn-gtm-injector/mrn-gtm-injector.php';
  $active_plugins = (array) get_option('active_plugins', array());
  if (in_array($gtm_injector_slug, $active_plugins, true)) {
    $is_gtm_injector_active = true;
  } elseif (is_multisite()) {
    $network_active = (array) get_site_option('active_sitewide_plugins', array());
    if (isset($network_active[$gtm_injector_slug])) {
      $is_gtm_injector_active = true;
    }
  }

  $is_beehive_screen = false;
  if (isset($_GET['page'])) {
    $page = sanitize_key((string) wp_unslash($_GET['page']));
    if (strpos($page, 'beehive') !== false || strpos($page, 'wds') !== false) {
      $is_beehive_screen = true;
    }
  }

  if ($is_gtm_injector_active && $is_beehive_screen) {
    wp_add_inline_style(
      'mrn-admin-ui-css',
      '
      .sui-dashboard-widget:has(.sui-dashboard-widget__footer a[href*="beehive-google-tag-manager"]),
      .sui-dashboard-widget:has(.sui-dashboard-widget__header-title .sui-icon):has(.sui-dashboard-widget__footer .sui-button[href*="google-tag-manager"]) {
        display: none !important;
      }
      '
    );

    add_action('admin_print_footer_scripts', function () {
      ?>
      <script>
      (function() {
        function hideBeehiveGtmWidget() {
          var widgets = document.querySelectorAll('.sui-dashboard-widget');
          widgets.forEach(function(widget) {
            if (!widget) {
              return;
            }
            var gtmLink = widget.querySelector('a[href*="beehive-google-tag-manager"], a[href*="google-tag-manager"]');
            if (gtmLink) {
              widget.style.setProperty('display', 'none', 'important');
            }
          });
        }

        hideBeehiveGtmWidget();

        var observer = new MutationObserver(function() {
          hideBeehiveGtmWidget();
        });
        observer.observe(document.body, { childList: true, subtree: true });
      })();
      </script>
      <?php
    }, 99);
  }

}, 20);

<?php
/**
 * Plugin Name: Admin UI CSS (MU)
 * Description: Unified admin UI CSS loader for wp-admin.
 * Version: 3.1.13
 */

defined('ABSPATH') || exit;

if (!defined('MRN_POST_PAGE_EDITOR_STACK_COOKIE')) {
	define('MRN_POST_PAGE_EDITOR_STACK_COOKIE', 'mrn_post_page_editor_stack');
}

if (!defined('MRN_POST_PAGE_EDITOR_ACF_ONLY_COOKIE')) {
	define('MRN_POST_PAGE_EDITOR_ACF_ONLY_COOKIE', 'mrn_post_page_editor_acf_only');
}

/**
 * Get the current post type for a post/page editor request.
 */
function mrn_get_post_page_editor_request_post_type(): string {
	$post_type = '';

	if (function_exists('get_current_screen')) {
		$screen = get_current_screen();
		if ($screen instanceof WP_Screen && isset($screen->post_type)) {
			$post_type = sanitize_key((string) $screen->post_type);
		}
	}

	if ($post_type !== '') {
		return $post_type;
	}

	if (isset($_GET['post_type'])) {
		$post_type = sanitize_key((string) wp_unslash($_GET['post_type']));
	}

	if ($post_type !== '') {
		return $post_type;
	}

	$post_id = 0;

	if (isset($_GET['post'])) {
		$post_id = absint(wp_unslash($_GET['post']));
	} elseif (isset($_POST['post_ID'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only request inspection for editor diagnostics.
		$post_id = absint(wp_unslash($_POST['post_ID'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only request inspection for editor diagnostics.
	}

	if ($post_id > 0) {
		$post = get_post($post_id);
		if ($post instanceof WP_Post) {
			return sanitize_key((string) $post->post_type);
		}
	}

	$pagenow = isset($GLOBALS['pagenow']) ? (string) $GLOBALS['pagenow'] : '';
	if ('post-new.php' === $pagenow) {
		return 'post';
	}

	return '';
}

/**
 * Determine whether the current request is a post/page editor screen.
 *
 * @param string $post_type Optional post type restriction.
 */
function mrn_is_post_page_editor_request(string $post_type = ''): bool {
	if (!is_admin()) {
		return false;
	}

	$pagenow = isset($GLOBALS['pagenow']) ? (string) $GLOBALS['pagenow'] : '';
	if (!in_array($pagenow, array('post.php', 'post-new.php'), true)) {
		return false;
	}

	$request_post_type = mrn_get_post_page_editor_request_post_type();
	if (!in_array($request_post_type, array('post', 'page'), true)) {
		return false;
	}

	if ($post_type === '') {
		return true;
	}

	return $request_post_type === sanitize_key($post_type);
}

/**
 * Determine whether the post/page editor is in ACF-only diagnostic mode.
 */
function mrn_is_post_page_editor_acf_only_mode(): bool {
	if (!mrn_is_post_page_editor_request()) {
		return false;
	}

	if (defined('MRN_ENABLE_POST_PAGE_EDITOR_ACF_ONLY')) {
		return (bool) MRN_ENABLE_POST_PAGE_EDITOR_ACF_ONLY;
	}

	$cookie_value = isset($_COOKIE[MRN_POST_PAGE_EDITOR_ACF_ONLY_COOKIE]) ? sanitize_key((string) wp_unslash($_COOKIE[MRN_POST_PAGE_EDITOR_ACF_ONLY_COOKIE])) : '';

	return 'on' === $cookie_value;
}

/**
 * Determine whether stack-owned editor bootstraps are disabled for this browser.
 */
function mrn_is_post_page_editor_stack_disabled(): bool {
	if (!mrn_is_post_page_editor_request()) {
		return false;
	}

	if (mrn_is_post_page_editor_acf_only_mode()) {
		return true;
	}

	if (defined('MRN_DISABLE_POST_PAGE_EDITOR_STACK')) {
		return (bool) MRN_DISABLE_POST_PAGE_EDITOR_STACK;
	}

	$cookie_value = isset($_COOKIE[MRN_POST_PAGE_EDITOR_STACK_COOKIE]) ? sanitize_key((string) wp_unslash($_COOKIE[MRN_POST_PAGE_EDITOR_STACK_COOKIE])) : '';

	return 'off' === $cookie_value;
}

/**
 * Determine whether stack-owned editor bootstraps are disabled for a specific post type.
 */
function mrn_is_post_page_editor_stack_disabled_for_post_type($post_type): bool {
	$post_type = sanitize_key((string) $post_type);

	if ('' === $post_type) {
		return false;
	}

	return mrn_is_post_page_editor_request($post_type) && mrn_is_post_page_editor_stack_disabled();
}

/**
 * Build the toggle URL for the post/page editor diagnostic mode.
 */
function mrn_get_post_page_editor_stack_toggle_url(string $state): string {
	$state = 'off' === $state ? 'off' : 'on';

	return wp_nonce_url(
		add_query_arg(
			array(
				'mrn_post_page_editor_stack' => $state,
			)
		),
		'mrn_post_page_editor_stack_toggle',
		'mrn_post_page_editor_stack_nonce'
	);
}

/**
 * Build the toggle URL for the post/page editor ACF-only mode.
 */
function mrn_get_post_page_editor_acf_only_toggle_url(string $state): string {
	$state = 'on' === $state ? 'on' : 'off';

	return wp_nonce_url(
		add_query_arg(
			array(
				'mrn_post_page_editor_acf_only' => $state,
			)
		),
		'mrn_post_page_editor_acf_only_toggle',
		'mrn_post_page_editor_acf_only_nonce'
	);
}

/**
 * Persist a post/page editor diagnostic cookie for the current browser.
 */
function mrn_set_post_page_editor_diagnostic_cookie(string $cookie_name, string $cookie_value, bool $enabled): void {
	$path   = defined('COOKIEPATH') && is_string(COOKIEPATH) && COOKIEPATH !== '' ? COOKIEPATH : '/';
	$expire = $enabled ? time() + WEEK_IN_SECONDS : time() - HOUR_IN_SECONDS;

	setcookie(
		$cookie_name,
		$enabled ? $cookie_value : '',
		$expire,
		$path,
		defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
		is_ssl(),
		true
	);

	$_COOKIE[$cookie_name] = $enabled ? $cookie_value : '';
}

/**
 * Persist the post/page editor diagnostic mode toggle for the current browser.
 */
function mrn_handle_post_page_editor_stack_toggle(): void {
	if (!is_admin() || !isset($_GET['mrn_post_page_editor_stack'])) {
		return;
	}

	$state = sanitize_key((string) wp_unslash($_GET['mrn_post_page_editor_stack']));
	if (!in_array($state, array('off', 'on'), true)) {
		return;
	}

	$nonce = isset($_GET['mrn_post_page_editor_stack_nonce']) ? sanitize_text_field((string) wp_unslash($_GET['mrn_post_page_editor_stack_nonce'])) : '';
	if ('' === $nonce || !wp_verify_nonce($nonce, 'mrn_post_page_editor_stack_toggle')) {
		return;
	}

	if (!current_user_can('edit_pages')) {
		return;
	}

	mrn_set_post_page_editor_diagnostic_cookie(MRN_POST_PAGE_EDITOR_STACK_COOKIE, 'off', 'off' === $state);

	wp_safe_redirect(
		remove_query_arg(
			array(
				'mrn_post_page_editor_stack',
				'mrn_post_page_editor_stack_nonce',
			)
		)
	);
	exit;
}
add_action('admin_init', 'mrn_handle_post_page_editor_stack_toggle');

/**
 * Persist the post/page editor ACF-only mode toggle for the current browser.
 */
function mrn_handle_post_page_editor_acf_only_toggle(): void {
	if (!is_admin() || !isset($_GET['mrn_post_page_editor_acf_only'])) {
		return;
	}

	$state = sanitize_key((string) wp_unslash($_GET['mrn_post_page_editor_acf_only']));
	if (!in_array($state, array('off', 'on'), true)) {
		return;
	}

	$nonce = isset($_GET['mrn_post_page_editor_acf_only_nonce']) ? sanitize_text_field((string) wp_unslash($_GET['mrn_post_page_editor_acf_only_nonce'])) : '';
	if ('' === $nonce || !wp_verify_nonce($nonce, 'mrn_post_page_editor_acf_only_toggle')) {
		return;
	}

	if (!current_user_can('edit_pages')) {
		return;
	}

	$is_enabled = 'on' === $state;

	mrn_set_post_page_editor_diagnostic_cookie(MRN_POST_PAGE_EDITOR_ACF_ONLY_COOKIE, 'on', $is_enabled);

	if ($is_enabled) {
		mrn_set_post_page_editor_diagnostic_cookie(MRN_POST_PAGE_EDITOR_STACK_COOKIE, 'off', true);
	}

	wp_safe_redirect(
		remove_query_arg(
			array(
				'mrn_post_page_editor_acf_only',
				'mrn_post_page_editor_acf_only_nonce',
			)
		)
	);
	exit;
}
add_action('admin_init', 'mrn_handle_post_page_editor_acf_only_toggle');

/**
 * Show the current post/page editor diagnostic mode on targeted editor screens.
 */
function mrn_render_post_page_editor_stack_notice(): void {
	if (!mrn_is_post_page_editor_request() || !current_user_can('edit_pages')) {
		return;
	}

	$is_disabled = mrn_is_post_page_editor_stack_disabled();
	$is_acf_only = mrn_is_post_page_editor_acf_only_mode();
	$post_type   = mrn_get_post_page_editor_request_post_type();
	$label       = 'page' === $post_type ? 'page' : 'post';
	$toggle_url  = mrn_get_post_page_editor_stack_toggle_url($is_disabled ? 'on' : 'off');
	$toggle_text = $is_disabled ? 'Re-enable MRN editor stack' : 'Disable MRN editor stack';
	$acf_toggle_url  = mrn_get_post_page_editor_acf_only_toggle_url($is_acf_only ? 'off' : 'on');
	$acf_toggle_text = $is_acf_only ? 'Disable ACF-only mode' : 'Enable ACF-only mode';
	$notice_type = ($is_disabled || $is_acf_only) ? 'notice-warning' : 'notice-info';
	$status_text = $is_acf_only
		? 'ACF-only editor diagnostic mode is active for this browser.'
		: ($is_disabled
			? 'MRN post/page editor diagnostic mode is active for this browser.'
			: 'MRN post/page editor diagnostic mode is available on this screen.');
	?>
	<div class="notice <?php echo esc_attr($notice_type); ?>">
		<p>
			<strong><?php echo esc_html($status_text); ?></strong>
			<?php if ($is_acf_only) : ?>
				<?php echo esc_html(sprintf('Only ACF field groups and essential ACF/core dependencies stay loaded where possible on %s edit and new screens.', $label)); ?>
			<?php elseif ($is_disabled) : ?>
				<?php echo esc_html(sprintf('Stack-owned editor bootstraps are bypassed where possible on %s edit and new screens.', $label)); ?>
			<?php else : ?>
				<?php echo esc_html(sprintf('Use it to bypass stack-owned editor bootstraps on %s edit and new screens for performance testing.', $label)); ?>
			<?php endif; ?>
		</p>
		<p>
			<?php if (!$is_acf_only) : ?>
				<a class="button <?php echo $is_disabled ? '' : 'button-primary'; ?>" href="<?php echo esc_url($toggle_url); ?>">
					<?php echo esc_html($toggle_text); ?>
				</a>
			<?php endif; ?>
			<a class="button <?php echo $is_acf_only ? 'button-primary' : ''; ?>" href="<?php echo esc_url($acf_toggle_url); ?>">
				<?php echo esc_html($acf_toggle_text); ?>
			</a>
		</p>
	</div>
	<?php
}
add_action('admin_notices', 'mrn_render_post_page_editor_stack_notice');

/**
 * Remove known MRN metaboxes that can still appear even after callback scrubs.
 *
 * @param string $post_type Current post type slug.
 * @return void
 */
function mrn_remove_known_stack_editor_metaboxes_during_diagnostics($post_type): void {
	if (!mrn_is_post_page_editor_stack_disabled_for_post_type($post_type)) {
		return;
	}

	$post_type = sanitize_key((string) $post_type);

	if (function_exists('acf_remove_local_field_group')) {
		acf_remove_local_field_group('group_69a1c0f3a1b01');
	}

	foreach (array('normal', 'advanced', 'side') as $context) {
		remove_meta_box('acf-group_69a1c0f3a1b01', $post_type, $context);
	}
}
add_action('add_meta_boxes', 'mrn_remove_known_stack_editor_metaboxes_during_diagnostics', PHP_INT_MAX);

/**
 * Strip post/page editor metaboxes down to ACF groups and publish controls.
 *
 * @param string $post_type Current post type slug.
 * @return void
 */
function mrn_reduce_post_page_editor_to_acf_only($post_type): void {
	if (!mrn_is_post_page_editor_acf_only_mode()) {
		return;
	}

	$post_type = sanitize_key((string) $post_type);
	if ('' === $post_type) {
		return;
	}

	global $wp_meta_boxes;

	if (!isset($wp_meta_boxes[$post_type]) || !is_array($wp_meta_boxes[$post_type])) {
		return;
	}

	$allowed_ids = array(
		'submitdiv',
	);

	foreach ($wp_meta_boxes[$post_type] as $context => $priorities) {
		if (!is_array($priorities)) {
			continue;
		}

		foreach ($priorities as $priority => $boxes) {
			if (!is_array($boxes)) {
				continue;
			}

			foreach ($boxes as $id => $box) {
				$id = (string) $id;

				if ('acf-group_69a1c0f3a1b01' === $id) {
					unset($wp_meta_boxes[$post_type][$context][$priority][$id]);
					remove_meta_box($id, $post_type, $context);
					continue;
				}

				if (in_array($id, $allowed_ids, true) || 0 === strpos($id, 'acf-group_')) {
					continue;
				}

				unset($wp_meta_boxes[$post_type][$context][$priority][$id]);
				remove_meta_box($id, $post_type, $context);
			}
		}
	}
}
add_action('add_meta_boxes', 'mrn_reduce_post_page_editor_to_acf_only', PHP_INT_MAX);

/**
 * Determine whether a registered script should stay loaded in ACF-only mode.
 *
 * @param string        $handle Script handle.
 * @param _WP_Dependency $registered Registered script object.
 * @return bool
 */
function mrn_should_keep_post_page_acf_only_script(string $handle, $registered): bool {
	$handle = sanitize_key($handle);
	$src    = is_object($registered) && isset($registered->src) ? strtolower((string) $registered->src) : '';

	$allowed_handles = array(
		'jquery-core',
		'jquery-migrate',
		'wp-i18n',
		'wp-a11y',
		'hoverintent-js',
		'hoverintent',
		'utils',
		'wp-dom-ready',
		'wp-hooks',
		'common',
		'heartbeat',
		'jquery-ui-core',
		'jquery-ui-mouse',
		'jquery-ui-widget',
		'jquery-ui-position',
		'jquery-ui-sortable',
		'jquery-ui-resizable',
		'jquery-ui-draggable',
		'jquery-ui-controlgroup',
		'jquery-ui-checkboxradio',
		'jquery-ui-button',
		'jquery-ui-dialog',
		'jquery-ui-datepicker',
		'jquery-ui-slider',
		'select2',
		'moxiejs',
		'plupload',
		'plupload-handlers',
		'wp-plupload',
		'iris',
		'color-picker',
		'editor',
		'quicktags',
		'wplink',
		'media-upload',
		'media-models',
		'media-views',
		'media-editor',
		'media-audiovideo',
		'mce-view',
		'image-edit',
		'imgareaselect',
		'underscore',
		'backbone',
		'wp-util',
		'wp-backbone',
		'api-request',
		'thickbox',
		'mediaelement',
		'wp-mediaelement',
		'shortcode',
		'clipboard',
		'wp-embed',
	);

	if (in_array($handle, $allowed_handles, true) || 0 === strpos($handle, 'acf')) {
		return true;
	}

	$allowed_src_fragments = array(
		'/wp-admin/load-scripts.php',
		'/wp-content/plugins/advanced-custom-fields-pro/',
		'/wp-includes/js/dist/i18n',
		'/wp-includes/js/dist/a11y',
		'/wp-includes/js/jquery/',
		'/wp-includes/js/plupload/',
		'/wp-admin/js/iris',
		'/wp-admin/js/common',
		'/wp-admin/js/color-picker',
		'/wp-admin/js/editor',
		'/wp-admin/js/media-upload',
		'/wp-includes/js/hoverintent',
		'/wp-includes/js/quicktags',
		'/wp-includes/js/heartbeat',
		'/wp-includes/js/shortcode',
		'/wp-includes/js/clipboard',
		'/wp-includes/js/wp-embed',
		'/wp-includes/js/wplink',
		'/wp-includes/js/media-',
		'/wp-includes/js/mce-view',
		'/wp-includes/js/imgareaselect/',
		'/wp-includes/js/api-request',
		'/wp-includes/js/backbone',
		'/wp-includes/js/wp-util',
		'/wp-includes/js/wp-backbone',
		'/wp-includes/js/underscore',
		'/wp-includes/js/thickbox/',
		'/wp-includes/js/mediaelement/',
		'/wp-includes/js/tinymce/',
	);

	foreach ($allowed_src_fragments as $fragment) {
		if ('' !== $src && false !== strpos($src, $fragment)) {
			return true;
		}
	}

	return false;
}

/**
 * Determine whether a registered style should stay loaded in ACF-only mode.
 *
 * @param string         $handle Style handle.
 * @param _WP_Dependency $registered Registered style object.
 * @return bool
 */
function mrn_should_keep_post_page_acf_only_style(string $handle, $registered): bool {
	$handle = sanitize_key($handle);
	$src    = is_object($registered) && isset($registered->src) ? strtolower((string) $registered->src) : '';

	if (0 === strpos($handle, 'acf')) {
		return true;
	}

	$allowed_src_fragments = array(
		'/wp-admin/load-styles.php',
		'/wp-content/plugins/advanced-custom-fields-pro/',
		'/wp-includes/js/thickbox/',
		'/wp-includes/js/mediaelement/',
		'/wp-includes/js/imgareaselect/',
		'/wp-includes/js/tinymce/skins/',
	);

	foreach ($allowed_src_fragments as $fragment) {
		if ('' !== $src && false !== strpos($src, $fragment)) {
			return true;
		}
	}

	return false;
}

/**
 * Dequeue non-ACF assets on post/page editor screens for ACF-only diagnostics.
 *
 * @return void
 */
function mrn_dequeue_post_page_editor_assets_for_acf_only_mode(): void {
	if (!mrn_is_post_page_editor_acf_only_mode()) {
		return;
	}

	global $wp_scripts, $wp_styles;

	if ($wp_scripts instanceof WP_Scripts) {
		foreach ((array) $wp_scripts->queue as $handle) {
			$registered = $wp_scripts->registered[$handle] ?? null;

			if (mrn_should_keep_post_page_acf_only_script((string) $handle, $registered)) {
				continue;
			}

			wp_dequeue_script((string) $handle);
		}
	}

	if ($wp_styles instanceof WP_Styles) {
		foreach ((array) $wp_styles->queue as $handle) {
			$registered = $wp_styles->registered[$handle] ?? null;

			if (mrn_should_keep_post_page_acf_only_style((string) $handle, $registered)) {
				continue;
			}

			wp_dequeue_style((string) $handle);
		}
	}
}
add_action('admin_print_scripts', 'mrn_dequeue_post_page_editor_assets_for_acf_only_mode', PHP_INT_MAX);
add_action('admin_print_styles', 'mrn_dequeue_post_page_editor_assets_for_acf_only_mode', PHP_INT_MAX);

/**
 * Filter printed script handles for ACF-only diagnostics.
 *
 * @param string[] $handles Handles about to print.
 * @return string[]
 */
function mrn_filter_post_page_acf_only_script_handles(array $handles): array {
	if (!mrn_is_post_page_editor_acf_only_mode()) {
		return $handles;
	}

	global $wp_scripts;

	return array_values(
		array_filter(
			$handles,
			static function ($handle) use ($wp_scripts) {
				$registered = $wp_scripts instanceof WP_Scripts ? ($wp_scripts->registered[$handle] ?? null) : null;

				return mrn_should_keep_post_page_acf_only_script((string) $handle, $registered);
			}
		)
	);
}
add_filter('print_scripts_array', 'mrn_filter_post_page_acf_only_script_handles', PHP_INT_MAX);

/**
 * Filter printed style handles for ACF-only diagnostics.
 *
 * @param string[] $handles Handles about to print.
 * @return string[]
 */
function mrn_filter_post_page_acf_only_style_handles(array $handles): array {
	if (!mrn_is_post_page_editor_acf_only_mode()) {
		return $handles;
	}

	global $wp_styles;

	return array_values(
		array_filter(
			$handles,
			static function ($handle) use ($wp_styles) {
				$registered = $wp_styles instanceof WP_Styles ? ($wp_styles->registered[$handle] ?? null) : null;

				return mrn_should_keep_post_page_acf_only_style((string) $handle, $registered);
			}
		)
	);
}
add_filter('print_styles_array', 'mrn_filter_post_page_acf_only_style_handles', PHP_INT_MAX);

add_action('admin_enqueue_scripts', function ($hook) {
	if (mrn_is_post_page_editor_stack_disabled()) {
		return;
	}

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

  // Always inject minimal ad-hiding rules, including on editor screens.
  wp_register_style('mrn-admin-ui-ads-only', false, array(), '3.1.13');
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

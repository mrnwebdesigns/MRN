<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Canonical shared sticky toolbar source for settings-style admin screens.
 *
 * New code should call the `mrn_sticky_toolbar_*` API directly.
 * Legacy `mrn_render_admin_top_bar*` wrappers remain for older consumers.
 */

if (!function_exists('mrn_sticky_toolbar_universal_css')) {
	function mrn_sticky_toolbar_universal_css() {
		return <<<'CSS'
.mrn-universal-sticky-toolbar {
  position: sticky;
  top: 32px;
  z-index: 1001;
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  clear: both;
  box-sizing: border-box;
  margin: 0 0 10px;
  padding: 10px 14px;
  border: 1px solid #2c2c2c;
  border-radius: 0;
  background: #1D2327;
  box-shadow: 0 2px 8px rgba(0,0,0,.35);
}
.mrn-universal-sticky-toolbar .mrn-usb-meta {display:flex;align-items:center;gap:10px;min-width:0;flex:1 1 auto}
.mrn-universal-sticky-toolbar .mrn-usb-title {font-weight:600;color:#f6f7f7;white-space:nowrap}
.mrn-universal-sticky-toolbar .mrn-usb-actions {display:flex;align-items:center;gap:8px;margin-left:auto;flex:0 0 auto}
@media (max-width: 782px) {
  .mrn-universal-sticky-toolbar {top:46px;flex-wrap:wrap}
}
CSS;
	}
}

if (!function_exists('mrn_sticky_toolbar_render')) {
	/**
	 * Render a reusable MRN settings toolbar.
	 *
	 * Args:
	 * - toolbar_id (string)
	 * - form_id (string)
	 * - save_label (string)
	 * - title (string)
	 * - aria_label (string)
	 * - tabs (array of [key,label,active])
	 */
	function mrn_sticky_toolbar_render($args = array()) {
		$toolbar_id = isset($args['toolbar_id']) ? sanitize_html_class((string) $args['toolbar_id']) : 'mrn-settings-toolbar';
		$form_id = isset($args['form_id']) ? sanitize_html_class((string) $args['form_id']) : '';
		$save_label = isset($args['save_label']) ? (string) $args['save_label'] : 'Save Settings';
		$aria_label = isset($args['aria_label']) ? (string) $args['aria_label'] : 'Settings tabs';
		$tabs = isset($args['tabs']) && is_array($args['tabs']) ? $args['tabs'] : array();

		if (empty($tabs)) {
			$tabs = array(
				array(
					'key' => 'general',
					'label' => 'General',
					'active' => true,
				),
			);
		}

		$title = isset($args['title']) ? trim((string) $args['title']) : '';
		if ($title === '') {
			$first_tab = reset($tabs);
			$title = isset($first_tab['label']) ? (string) $first_tab['label'] : 'Settings';
		}

		$single_tab_mode = count($tabs) === 1;
		?>
		<div id="<?php echo esc_attr($toolbar_id); ?>" class="mrn-sticky-save-bar">
			<div class="mrn-settings-toolbar__meta">
				<span class="mrn-settings-toolbar__title"><?php echo esc_html($title); ?></span>
			</div>
			<div class="mrn-settings-toolbar__actions">
				<?php if (!$single_tab_mode) : ?>
					<nav class="mrn-settings-tabs" aria-label="<?php echo esc_attr($aria_label); ?>">
						<?php
						$default_icons = array(
							'customization' => 'dashicons-admin-customizer',
							'assets' => 'dashicons-media-code',
							'general' => 'dashicons-admin-generic',
							'branding' => 'dashicons-format-image',
							'preview' => 'dashicons-visibility',
							'instructions' => 'dashicons-editor-help',
						);

						foreach ($tabs as $tab) :
							$key = isset($tab['key']) ? (string) $tab['key'] : '';
							$label = isset($tab['label']) ? (string) $tab['label'] : '';
							$active = !empty($tab['active']);
							$icon_class = isset($tab['icon']) ? (string) $tab['icon'] : '';

							if ($icon_class === '' && isset($default_icons[$key])) {
								$icon_class = $default_icons[$key];
							}

							$tab_classes = 'mrn-settings-tab' . ($active ? ' is-active' : '');
							?>
							<button type="button" class="<?php echo esc_attr($tab_classes); ?>" data-mrn-tab="<?php echo esc_attr($key); ?>">
								<?php if ($icon_class !== '') : ?>
									<span class="mrn-settings-tab__icon dashicons <?php echo esc_attr($icon_class); ?>" aria-hidden="true"></span>
								<?php endif; ?>
								<span class="mrn-settings-tab__label"><?php echo esc_html($label); ?></span>
							</button>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>
				<button type="submit" class="button button-primary mrn-settings-tab mrn-settings-tab--save"<?php echo $form_id !== '' ? ' form="' . esc_attr($form_id) . '"' : ''; ?>><?php echo esc_html($save_label); ?></button>
			</div>
		</div>
		<div class="mrn-admin-toolbar-spacer" aria-hidden="true"></div>
		<script>
		(function () {
			var toolbarId = <?php echo wp_json_encode($toolbar_id); ?>;
			var formId = <?php echo wp_json_encode($form_id); ?>;

			function syncToolbarLeftOffset(toolbar) {
				var wpContent = document.getElementById('wpcontent');
				if (!toolbar || !wpContent) {
					return;
				}

				if (window.innerWidth <= 1000) {
					toolbar.style.removeProperty('--mrn-toolbar-left');
					return;
				}

				var left = Math.max(0, Math.round(wpContent.getBoundingClientRect().left));
				toolbar.style.setProperty('--mrn-toolbar-left', left + 'px');
			}

			function serializeForm(form) {
				var data = new FormData(form);
				var entries = [];

				data.forEach(function (value, key) {
					entries.push(key + '=' + String(value));
				});

				entries.sort();

				return entries.join('&');
			}

			function initStickyDirtyState() {
				var toolbar = document.getElementById(toolbarId);
				if (!toolbar) {
					return;
				}

				syncToolbarLeftOffset(toolbar);

				var body = document.body;
				if (body && !toolbar.dataset.mrnStickyAlignInit) {
					toolbar.dataset.mrnStickyAlignInit = '1';
					window.addEventListener('resize', function () {
						syncToolbarLeftOffset(toolbar);
					});

					var observer = new MutationObserver(function () {
						syncToolbarLeftOffset(toolbar);
					});

					observer.observe(body, {
						attributes: true,
						attributeFilter: ['class']
					});
				}

				if (!formId) {
					return;
				}

				var form = document.getElementById(formId);
				if (!form || form.dataset.mrnStickyDirtyInit === '1') {
					return;
				}

				var saveButton = toolbar.querySelector('.mrn-settings-tab--save[form="' + formId + '"]') || toolbar.querySelector('.mrn-settings-tab--save');
				if (!saveButton) {
					return;
				}

				form.dataset.mrnStickyDirtyInit = '1';
				var initialState = serializeForm(form);

				function updateSaveState() {
					var isDirty = serializeForm(form) !== initialState;
					saveButton.disabled = !isDirty;
					saveButton.setAttribute('aria-disabled', isDirty ? 'false' : 'true');
				}

				form.addEventListener('input', updateSaveState, true);
				form.addEventListener('change', updateSaveState, true);
				form.addEventListener('reset', function () {
					window.setTimeout(updateSaveState, 0);
				});
				form.addEventListener('submit', function () {
					saveButton.disabled = true;
					saveButton.setAttribute('aria-disabled', 'true');
				});

				updateSaveState();
			}

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', initStickyDirtyState, { once: true });
			} else {
				initStickyDirtyState();
			}
		})();
		</script>
		<?php
	}
}

if (!function_exists('mrn_sticky_toolbar_render_css')) {
	/**
	 * Print reusable CSS for the MRN settings toolbar.
	 *
	 * Args:
	 * - toolbar_id (string)
	 * - page_class (string)
	 * - desktop_left (int)
	 * - desktop_right (int)
	 * - mobile_left (int)
	 * - mobile_right (int)
	 * - spacer_height (int)
	 * - spacer_height_mobile (int)
	 */
	function mrn_sticky_toolbar_render_css($args = array()) {
		$toolbar_id = isset($args['toolbar_id']) ? sanitize_html_class((string) $args['toolbar_id']) : 'mrn-settings-toolbar';
		$page_class = isset($args['page_class']) ? sanitize_html_class((string) $args['page_class']) : '';
		$desktop_left = isset($args['desktop_left']) ? (int) $args['desktop_left'] : 196;
		$desktop_right = isset($args['desktop_right']) ? (int) $args['desktop_right'] : 0;
		$mobile_left = isset($args['mobile_left']) ? (int) $args['mobile_left'] : 10;
		$mobile_right = isset($args['mobile_right']) ? (int) $args['mobile_right'] : 10;
		$spacer_height = isset($args['spacer_height']) ? (int) $args['spacer_height'] : 96;
		$spacer_height_mobile = isset($args['spacer_height_mobile']) ? (int) $args['spacer_height_mobile'] : 116;

		$id_selector = '#' . $toolbar_id;
		$folded_selector = '.folded ' . $id_selector;
		if ($page_class !== '') {
			$folded_selector = '.folded.' . $page_class . ' ' . $id_selector;
		}
		?>
		<style>
		.mrn-settings-tabs {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			margin: 0;
			align-items: center;
			padding: 4px;
			border-radius: 8px;
			background: rgba(255, 255, 255, 0.06);
		}
		.mrn-settings-toolbar__meta {
			display: flex;
			align-items: center;
			gap: 10px;
			min-width: 0;
			flex: 0 0 auto;
		}
		.mrn-settings-toolbar__title {
			font-weight: 600;
			color: #f6f7f7;
			white-space: nowrap;
		}
		.mrn-settings-toolbar__actions {
			display: flex;
			align-items: center;
			gap: 12px;
			margin-left: 24px;
			min-width: 0;
			flex: 1 1 auto;
		}
		.mrn-settings-tab {
			appearance: none;
			border: 0;
			background: transparent;
			color: #f6f7f7;
			border-radius: 4px;
			padding: 7px 10px;
			font-weight: 600;
			line-height: 1.2;
			cursor: pointer;
			display: inline-flex;
			align-items: center;
			gap: 6px;
			margin: 0;
		}
		.mrn-settings-tab__icon {
			font-size: 16px;
			width: 16px;
			height: 16px;
			line-height: 16px;
		}
		.mrn-settings-tab:hover,
		.mrn-settings-tab:focus {
			background: #2c2c2c;
			color: #ffffff;
			outline: none;
		}
		.mrn-settings-tab.is-active {
			background: #4a4a4a;
			color: #ffffff;
			border-radius: 4px;
		}
		.mrn-sticky-save-bar .mrn-settings-tab--save.button.button-primary {
			margin-left: auto;
			min-height: 36px;
			border-radius: 6px;
			background: #f6f7f7;
			border-color: #8c8f94;
			color: #1d2327;
			box-shadow: none;
		}
		.mrn-sticky-save-bar .mrn-settings-tab--save.button.button-primary:hover,
		.mrn-sticky-save-bar .mrn-settings-tab--save.button.button-primary:focus {
			background: #ffffff;
			border-color: #c3c4c7;
			color: #1d2327;
		}
		.mrn-sticky-save-bar .mrn-settings-tab--save.button.button-primary:disabled,
		.mrn-sticky-save-bar .mrn-settings-tab--save.button.button-primary[aria-disabled="true"] {
			background: #dcdcde;
			border-color: #b5bfc9;
			color: #646970;
			cursor: not-allowed;
			box-shadow: none;
		}
		.mrn-admin-toolbar-spacer {
			height: <?php echo (int) $spacer_height; ?>px;
		}
		<?php echo esc_html($id_selector); ?> {
			--mrn-toolbar-left: <?php echo (int) $desktop_left; ?>px;
			--mrn-toolbar-right: <?php echo (int) $desktop_right; ?>px;
			position: fixed;
			top: 32px;
			left: var(--mrn-toolbar-left);
			right: var(--mrn-toolbar-right);
			z-index: 1001;
			width: calc(100vw - var(--mrn-toolbar-left) - var(--mrn-toolbar-right));
			box-sizing: border-box;
			display: flex;
			align-items: center;
			justify-content: flex-start;
			gap: 12px;
			margin: 0;
			padding: 10px 18px 10px 16px;
			border: 1px solid #2c2c2c;
			border-radius: 0;
			background: #1D2327;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
		}
		<?php echo esc_html($folded_selector); ?> {
			--mrn-toolbar-left: 56px;
		}
		@media (max-width: 1000px) {
			<?php echo esc_html($id_selector); ?> {
				--mrn-toolbar-left: <?php echo (int) $mobile_left; ?>px;
				--mrn-toolbar-right: <?php echo (int) $mobile_right; ?>px;
				top: 46px;
				flex-wrap: wrap;
				width: calc(100vw - var(--mrn-toolbar-left) - var(--mrn-toolbar-right));
			}
			.mrn-settings-toolbar__actions {
				flex-wrap: wrap;
				width: 100%;
			}
			.mrn-admin-toolbar-spacer {
				height: <?php echo (int) $spacer_height_mobile; ?>px;
			}
		}
		</style>
		<?php
	}
}

if (!function_exists('mrn_shared_universal_sticky_bar_css')) {
	function mrn_shared_universal_sticky_bar_css() {
		return mrn_sticky_toolbar_universal_css();
	}
}

if (!function_exists('mrn_render_admin_top_bar')) {
	function mrn_render_admin_top_bar($args = array()) {
		mrn_sticky_toolbar_render($args);
	}
}

if (!function_exists('mrn_render_admin_top_bar_css')) {
	function mrn_render_admin_top_bar_css($args = array()) {
		mrn_sticky_toolbar_render_css($args);
	}
}

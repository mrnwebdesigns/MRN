<?php
// phpcs:ignoreFile -- Standalone enqueue/lifecycle regression harness with WordPress/ACF fixtures.
/**
 * Run with optional Config Helper and Character Count checkout paths.
 * php stack/tests/php/admin-field-assets.php <config-helper-root> <character-count-root> [legacy]
 */
$argv = $argv ?? array();
define('ABSPATH', __DIR__);
define('_S_VERSION', 'test');
$GLOBALS['hooks'] = array();
$GLOBALS['groups'] = array();
$GLOBALS['queries'] = 0;
$GLOBALS['field_queries'] = 0;
$GLOBALS['options'] = array('mrn_acf_char_count_fields' => array('summary', 'field_abc123'));
$GLOBALS['admin'] = true;
$GLOBALS['disabled'] = false;
$GLOBALS['scripts'] = $GLOBALS['styles'] = $GLOBALS['localized'] = array();
class WP_Post { public $ID = 42; public $post_type = 'page'; public $post_excerpt = ''; }
class WP_Screen {
 public $base; public $id; public $post_type; public $block = false;
 public function __construct($base, $type = '') { $this->base = $base; $this->post_type = $type; $this->id = $base . '-' . $type . '-' . uniqid(); }
 public function is_block_editor() { return $this->block; }
}
$GLOBALS['post'] = new WP_Post();
function add_action($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['hooks'][$hook][$priority][] = array($callback, $args); }
function add_filter($hook, $callback, $priority = 10, $args = 1) { add_action($hook, $callback, $priority, $args); }
function remove_filter($hook, $callback, $priority = 10) { foreach ($GLOBALS['hooks'][$hook][$priority] ?? array() as $i => $entry) { if ($entry[0] === $callback) { unset($GLOBALS['hooks'][$hook][$priority][$i]); } } }
function do_action($hook, ...$args) { $hooks = $GLOBALS['hooks'][$hook] ?? array(); ksort($hooks); foreach ($hooks as $entries) { foreach ($entries as $entry) { call_user_func_array($entry[0], array_slice($args, 0, $entry[1])); } } }
function apply_filters($hook, $value, ...$args) { $hooks = $GLOBALS['hooks'][$hook] ?? array(); ksort($hooks); foreach ($hooks as $entries) { foreach ($entries as $entry) { $value = call_user_func_array($entry[0], array_slice(array_merge(array($value), $args), 0, $entry[1])); } } return $value; }
function add_shortcode() {}
function __return_false() { return false; }
function is_admin() { return $GLOBALS['admin']; }
function get_current_screen() { return $GLOBALS['screen']; }
function sanitize_html_class($s) { return preg_replace('/[^A-Za-z0-9_-]/', '', $s); }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $s)); }
function wp_strip_all_tags($s) { return strip_tags($s); }
function has_filter($hook) { return !empty($GLOBALS['hooks'][$hook]); }
function wp_unslash($s) { return $s; }
function esc_url_raw($s) { return $s; }
function absint($s) { return abs((int) $s); }
function sanitize_textarea_field($s) { return trim(strip_tags($s)); }
function sanitize_text_field($s) { return trim(strip_tags($s)); }
function __( $s, $domain = '' ) { return $s; }
function admin_url($s = '') { return 'https://fixture.test/wp-admin/' . $s; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file) { return 'https://fixture.test/plugins/' . basename(dirname($file)) . '/'; }
function plugins_url($file, $base = '') { return plugin_dir_url($base) . $file; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function wp_parse_args($args, $defaults = array()) { return array_merge($defaults, (array) $args); }
function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $footer = false) { $GLOBALS['scripts'][$handle] = compact('src', 'deps', 'footer'); }
function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false) { $GLOBALS['styles'][$handle] = compact('src', 'deps'); }
function script_dependencies($handle) { return $GLOBALS['scripts'][$handle]['deps'] ?? array(); }
function wp_script_is($handle, $status = 'enqueued') { return isset($GLOBALS['scripts'][$handle]); }
function wp_localize_script($handle, $name, $data) { $GLOBALS['localized'][$handle][] = $data; }
function wp_enqueue_media() { wp_enqueue_script('media-views'); }
function mrn_shared_assets_enqueue_admin_icon_chooser($script, $style) { wp_enqueue_script($script); wp_enqueue_style($style); wp_enqueue_style($style . '-fontawesome'); wp_enqueue_media(); wp_localize_script($script, 'icons', array('catalog' => 'fixture')); }
function mrn_shared_assets_enqueue_fontawesome($handle) { wp_enqueue_style($handle); }
function mrn_is_post_page_editor_stack_disabled() { return $GLOBALS['disabled']; }
function mrn_base_stack_get_singular_shell_post_types() { return array('post', 'page', 'product', 'mrn_reusable_block'); }
function get_template_directory() { return dirname(__DIR__, 2) . '/themes/mrn-base-stack'; }
function get_template_directory_uri() { return 'https://fixture.test/theme'; }
function acf_get_field_groups($context) {
 $GLOBALS['queries']++;
 $out = array();
 foreach ($GLOBALS['groups'] as $group) {
  if (empty($group['active'])) { continue; }
  foreach ($group['location'] as $rules) {
   $match = true;
   foreach ($rules as $rule) {
    $actual = $context[$rule['param']] ?? '';
    $result = '!=' === ($rule['operator'] ?? '==') ? $actual !== $rule['value'] : $actual === $rule['value'];
    if (!apply_filters('acf/location/rule_match', $result, $rule, $context, $group)) { $match = false; break; }
   }
   if ($match) { $out[] = $group; break; }
  }
 }
 return $out;
}
function acf_get_fields($group) { if (!is_array($group)) { return array(); } $GLOBALS['field_queries']++; return $group['fields']; }
function acf_get_field($key) { return false; }
function check($test, $message) { if (!$test) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } $GLOBALS['assertions'] = ($GLOBALS['assertions'] ?? 0) + 1; }
function reset_screen($base, $type = '') {
 $GLOBALS['screen'] = new WP_Screen($base, $type); $GLOBALS['scripts'] = $GLOBALS['styles'] = $GLOBALS['localized'] = array(); $GLOBALS['groups'] = array();
 // Each case represents a new HTTP request, with a fresh plugin instance.
 if (class_exists('MRN_ACF_Character_Count')) {
  foreach ($GLOBALS['hooks'] as $hook => $priorities) { foreach ($priorities as $priority => $entries) { foreach ($entries as $i => $entry) {
   if (is_array($entry[0]) && $entry[0][0] instanceof MRN_ACF_Character_Count) { unset($GLOBALS['hooks'][$hook][$priority][$i]); }
  } } }
  new MRN_ACF_Character_Count();
 }
}
function render_field($field) { do_action('acf/render_field/type=' . $field['type'], $field); do_action('acf/render_field', $field); }
function group($fields, $post_type = 'page', $extra = array()) { return array('active' => true, 'fields' => $fields, 'location' => array(array_merge(array(array('param' => 'post_type', 'value' => $post_type)), $extra))); }
reset_screen('dashboard');
$legacy = in_array('legacy', $argv, true);
if (!$legacy) { require dirname(__DIR__, 3) . '/mu-plugins/mrn-shared-assets/includes/acf-admin-assets.php'; }
require get_template_directory() . '/inc/builder/admin.php';
require get_template_directory() . '/inc/admin-acf-assets.php';
$config = isset($argv[1]) && is_file($argv[1] . '/mrn-config-helper.php');
$counter = isset($argv[2]) && is_file($argv[2] . '/mrn-acf-character-count.php');
if ($config) { require $argv[1] . '/mrn-config-helper.php'; }
if ($counter) { require $argv[2] . '/mrn-acf-character-count.php'; }
$picker = $config ? 'mrn-config-helper-acf-layout-picker' : 'mrn-base-stack-acf-layout-picker';
$repeater = array('type' => 'repeater', 'key' => 'field_rows', 'name' => 'rows');
$icon = array('type' => 'button_group', 'key' => 'field_icons', 'wrapper' => array('class' => 'other mrn-icon-chooser-field--source more'));
$text = array('type' => 'textarea', 'key' => 'field_abc123', '_name' => 'summary', 'name' => 'acf[field_abc123]');
$flex = array('type' => 'flexible_content', 'key' => 'field_builder', 'name' => 'content', 'layouts' => array(array('sub_fields' => array($repeater, $icon, $text))));
foreach (array(array('woocommerce_page_wc-orders', 'shop_order'), array('edit', 'page'), array('edit', 'product'), array('site-health'), array('settings_page_unrelated'), array('dashboard')) as $case) {
 reset_screen(...$case); $before = $GLOBALS['queries']; do_action('acf/input/admin_enqueue_scripts');
 check(!$GLOBALS['scripts'] && !$GLOBALS['styles'] && !$GLOBALS['localized'], 'unused screen has no helper assets/catalog: ' . $case[0]);
 check($before === $GLOBALS['queries'], 'unused screen does not query ACF groups');
}
if (!$legacy) {
 reset_screen('post', 'page');
 $GLOBALS['groups'] = array(group(array($flex), 'product'), group(array(array('type' => 'text', 'name' => 'other')), 'page'));
 do_action('acf/input/admin_enqueue_scripts');
 check(!$GLOBALS['scripts'], 'another post type or unmatched configured field must not enqueue');
 check(1 === $GLOBALS['field_queries'], 'only matching group definitions are loaded');
 reset_screen('post', 'page');
 $GLOBALS['groups'] = array(group(array($flex), 'page', array(array('param' => 'page_template', 'value' => 'new-template.php'))));
 $before = $GLOBALS['queries']; do_action('acf/input/admin_enqueue_scripts');
 check(isset($GLOBALS['scripts'][$picker], $GLOBALS['scripts']['mrn-base-stack-admin-repeater-controls'], $GLOBALS['scripts']['mrn-base-stack-admin-icon-choosers']), 'nested templates and potential AJAX groups retain editor helpers');
 if ($counter) { check(isset($GLOBALS['scripts']['mrn-acf-char-count']), 'nested configured target retains counter'); }
 check($GLOBALS['queries'] === $before + 1, 'all consumers reuse one group discovery');
 check(!apply_filters('acf/location/rule_match', false, array('param' => 'page_template')), 'asset discovery does not change actual location matching');
 check(in_array('acf-input', script_dependencies($picker), true), 'layout picker keeps ACF dependency');
 check(in_array('mrn-shared-icon-chooser', script_dependencies('mrn-base-stack-admin-icon-choosers'), true), 'icon consumer keeps chooser dependency');
 check(isset($GLOBALS['styles']['mrn-shared-icon-chooser-fontawesome'], $GLOBALS['scripts']['media-views']), 'actual icon field retains font and media dependencies');
 render_field($flex); render_field($icon); render_field($text);
 check(count($GLOBALS['localized'][$picker] ?? array()) === 1 && count($GLOBALS['localized']['mrn-shared-icon-chooser'] ?? array()) === 1, 'multiple fields do not repeat inline catalogs');
}
foreach (array(array('acf-options-theme-options'), array('term', 'product'), array('edit-tags'), array('profile'), array('woocommerce_page_wc-orders', 'shop_order')) as $case) {
 reset_screen(...$case); do_action('acf/input/admin_enqueue_scripts'); render_field($repeater);
 check(isset($GLOBALS['scripts']['mrn-base-stack-admin-repeater-controls']), 'rendered repeater works in ' . $case[0]);
 check(!isset($GLOBALS['scripts']['mrn-base-stack-admin-icon-choosers']), 'repeater alone does not load icon catalog');
 render_field($icon); check(isset($GLOBALS['scripts']['mrn-base-stack-admin-icon-choosers']), 'actual icon works in ' . $case[0]);
 if ($config) { render_field($flex); check(isset($GLOBALS['scripts'][$picker]), 'flexible-content form keeps picker'); }
 if ($counter) {
  render_field(array('type' => 'text', 'key' => 'field_other', '_name' => 'unmatched')); check(!isset($GLOBALS['scripts']['mrn-acf-char-count']), 'unmatched field does not load counter');
  render_field($text); check(isset($GLOBALS['scripts']['mrn-acf-char-count']), 'prepared field identifier keeps counter');
 }
}
reset_screen('post', 'acf-field-group'); render_field($flex); render_field($icon); render_field($repeater); render_field($text);
check(!$GLOBALS['scripts'], 'ACF definition editor stays excluded');
reset_screen('post', 'page'); $GLOBALS['screen']->block = true; $GLOBALS['groups'] = array(group(array($flex))); do_action('acf/input/admin_enqueue_scripts');
check(!isset($GLOBALS['scripts']['mrn-base-stack-admin-repeater-controls']) && !isset($GLOBALS['scripts']['mrn-base-stack-admin-icon-choosers']), 'theme block-editor exclusion preserved');
if ($config) { check(isset($GLOBALS['scripts'][$picker]), 'plugin block editor support preserved'); }
if ($counter) {
 check(isset($GLOBALS['scripts']['mrn-acf-char-count']), 'counter supports AJAX ACF block forms');
 reset_screen('profile'); $GLOBALS['disabled'] = true; render_field($text); check(!isset($GLOBALS['scripts']['mrn-acf-char-count']), 'existing editor-disable policy preserved'); $GLOBALS['disabled'] = false;
 reset_screen('front'); $GLOBALS['admin'] = false; do_action('acf/input/admin_enqueue_scripts'); check(isset($GLOBALS['scripts']['mrn-acf-char-count']), 'existing front-end counter behavior preserved'); $GLOBALS['admin'] = true;
 reset_screen('profile'); $GLOBALS['options']['mrn_acf_char_count_fields'] = array(); render_field($text); check(!isset($GLOBALS['scripts']['mrn-acf-char-count']), 'no targets means no counter');
}
if ($counter) {
 $GLOBALS['options']['mrn_acf_char_count_fields'] = array('summary');
 reset_screen('profile'); render_field(array('type' => 'textarea', 'key' => 'field_different', '_name' => 'summary', 'name' => 'acf[row][field_different]'));
 check(isset($GLOBALS['scripts']['mrn-acf-char-count']), 'name-only target matches ACF data-name rather than prepared input name');
 reset_screen('profile'); render_field(array('type' => 'textarea', 'key' => 'field_different', '_name' => 'prefix_summary', 'name' => 'summary'));
 check(!isset($GLOBALS['scripts']['mrn-acf-char-count']), 'a clone name must match its actual DOM identifier exactly');
}
if ($config) {
 reset_screen('profile');
 add_filter('mrn_config_helper_acf_layout_picker_enabled', '__return_false');
 render_field($flex); check(!isset($GLOBALS['scripts'][$picker]), 'layout picker disable filter preserved');
 remove_filter('mrn_config_helper_acf_layout_picker_enabled', '__return_false');
}
if ($config) { reset_screen('settings_page_mrn-config-helper'); call_user_func(array('MRN_Config_Helper', 'enqueue_settings_assets'), 'settings_page_mrn-config-helper'); check(isset($GLOBALS['scripts']['mrn-shared-icon-chooser']), 'settings icon consumer preserved'); }
echo 'PASS: ' . $GLOBALS['assertions'] . ' admin asset assertions (' . ($legacy ? 'older shared-assets fallback' : 'field-aware discovery') . ").\n";

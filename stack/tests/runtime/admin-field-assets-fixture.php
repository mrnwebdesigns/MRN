<?php
// phpcs:ignoreFile -- Local-only WP-CLI fixture; never served as a plugin or endpoint.
/**
 * wp eval-file admin-field-assets-fixture.php setup|reset|cleanup /private/session.json
 * Creates disposable draft/ACF fixtures and one short-lived test login session.
 */
if (!defined('WP_CLI') || !WP_CLI || !preg_match('/\.localhost$/', (string) wp_parse_url(home_url(), PHP_URL_HOST))) {
 throw new RuntimeException('This fixture requires WP-CLI on a .localhost site.');
}
$mode = $args[0] ?? '';
$session_path = $args[1] ?? '';
if ('' === $session_path || !is_dir(dirname($session_path)) || 0 === strpos(realpath(dirname($session_path)), realpath(ABSPATH))) {
 throw new RuntimeException('Session file must be outside the web root in an existing private directory.');
}
$option = 'mrn_admin_field_assets_qa_fixture';
$state = get_option($option);
if ('reset' === $mode && is_array($state)) {
 wp_update_post(array('ID' => $state['post_id'], 'post_status' => 'draft'));
 delete_post_meta($state['post_id'], '_wp_page_template');
 $late = acf_get_field_group('group_mrn_asset_qa_late');
 $late['location'] = array(array(array('param' => 'post', 'operator' => '==', 'value' => (string) $state['post_id']), array('param' => 'page_template', 'operator' => '==', 'value' => 'page-sidebar-left.php')));
 acf_update_field_group($late);
 foreach (array('field_mrnqa_rows', 'field_mrnqa_sections') as $key) { update_field($key, array(), $state['post_id']); }
 foreach (array('field_mrnqa_summary', 'field_mrnqa_prototype', 'field_mrnqa_late_copy') as $key) { update_field($key, '', $state['post_id']); }
 WP_CLI::success('Reset disposable fixture draft and rows.');
 return;
}
if ('cleanup' === $mode) {
 if (is_array($state)) {
  foreach ($state['groups'] as $group_key) {
   $group = acf_get_field_group($group_key);
   if ($group) { acf_delete_field_group($group['ID']); }
  }
  wp_delete_post($state['post_id'], true);
  update_option('mrn_acf_char_count_fields', $state['targets']);
  delete_option($option);
 }
 if (is_file($session_path)) {
  $session = json_decode(file_get_contents($session_path), true);
  if (!empty($session['user_id']) && !empty($session['token'])) {
   WP_Session_Tokens::get_instance($session['user_id'])->destroy($session['token']);
  }
  unlink($session_path);
 }
 WP_CLI::success('Removed local fixture, restored count targets, and revoked test session.');
 return;
}
if ('setup' !== $mode || $state) { throw new RuntimeException('Use setup once, then cleanup.'); }
if (!function_exists('acf_import_field_group')) { throw new RuntimeException('ACF PRO is required.'); }
$post_id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'MRN admin asset QA - disposable draft'), true);
if (is_wp_error($post_id)) { throw new RuntimeException($post_id->get_error_message()); }
$state = array('post_id' => $post_id, 'targets' => get_option('mrn_acf_char_count_fields', array()), 'groups' => array('group_mrn_asset_qa', 'group_mrn_asset_qa_late'));
update_option($option, $state, false);
$text = static function ($key, $name, $label) { return array('key' => $key, 'name' => $name, 'label' => $label, 'type' => 'text', 'maxlength' => 30); };
$icon_fields = array(
 array('key' => 'field_mrnqa_icon_source', 'name' => 'source', 'label' => 'Icon source', 'type' => 'button_group', 'choices' => array('dashicons' => 'Dashicons', 'fontawesome' => 'Font Awesome', 'media' => 'Image'), 'default_value' => 'dashicons', 'wrapper' => array('class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--source')),
 array('key' => 'field_mrnqa_icon_dashicons', 'name' => 'dashicons', 'label' => 'Dashicon', 'type' => 'text', 'default_value' => 'dashicons-star-filled', 'wrapper' => array('class' => 'mrn-icon-chooser-field--dashicons')),
 array('key' => 'field_mrnqa_icon_fontawesome', 'name' => 'fontawesome', 'label' => 'Font Awesome', 'type' => 'text', 'wrapper' => array('class' => 'mrn-icon-chooser-field--fontawesome')),
 array('key' => 'field_mrnqa_icon_media', 'name' => 'image', 'label' => 'Image', 'type' => 'image', 'return_format' => 'array', 'wrapper' => array('class' => 'mrn-icon-chooser-field--media')),
);
$location = array(array(array('param' => 'post', 'operator' => '==', 'value' => (string) $post_id)));
acf_import_field_group(array(
 'key' => 'group_mrn_asset_qa', 'title' => 'Asset QA fields', 'active' => true, 'position' => 'acf_after_title', 'location' => $location,
 'fields' => array(
  $text('field_mrnqa_summary', 'qa_summary', 'QA summary'),
  array('key' => 'field_mrnqa_rows', 'name' => 'qa_rows', 'label' => 'QA rows', 'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add QA row', 'sub_fields' => array($text('field_mrnqa_row_copy', 'qa_row_copy', 'QA row copy'))),
  array('key' => 'field_mrnqa_sections', 'name' => 'qa_sections', 'label' => 'QA sections', 'type' => 'flexible_content', 'button_label' => 'Add QA section', 'layouts' => array(array('key' => 'layout_mrnqa_copy', 'name' => 'qa_copy', 'label' => 'QA Copy', 'display' => 'block', 'sub_fields' => array($text('field_mrnqa_section_copy', 'qa_section_copy', 'QA section copy'))))),
  array('key' => 'field_mrnqa_icons', 'name' => 'qa_icons', 'label' => 'QA icon chooser', 'type' => 'group', 'sub_fields' => $icon_fields),
  $text('field_mrnqa_prototype', 'qa_prototype', 'QA clone source'),
  array('key' => 'field_mrnqa_clone', 'name' => 'qa_clone', 'label' => 'QA cloned field', 'type' => 'clone', 'clone' => array('field_mrnqa_prototype'), 'display' => 'seamless', 'prefix_name' => 1, 'prefix_label' => 1),
 ),
));
$location[0][] = array('param' => 'page_template', 'operator' => '==', 'value' => 'page-sidebar-left.php');
acf_import_field_group(array('key' => 'group_mrn_asset_qa_late', 'title' => 'QA dynamically inserted fields', 'active' => true, 'location' => $location, 'fields' => array($text('field_mrnqa_late_copy', 'qa_late_copy', 'QA late copy'))));
update_option('mrn_acf_char_count_fields', array('qa_summary', 'qa_row_copy', 'qa_section_copy', 'qa_clone_qa_prototype', 'qa_late_copy'));
$users = get_users(array('role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC'));
if (!$users) { throw new RuntimeException('No local admin is available.'); }
$user_id = $users[0]->ID;
$expiration = time() + HOUR_IN_SECONDS;
$token = WP_Session_Tokens::get_instance($user_id)->create($expiration);
$cookies = array();
foreach (array(SECURE_AUTH_COOKIE => 'secure_auth', LOGGED_IN_COOKIE => 'logged_in') as $name => $scheme) {
 $cookies[] = array('name' => $name, 'value' => wp_generate_auth_cookie($user_id, $expiration, $scheme, $token), 'domain' => wp_parse_url(home_url(), PHP_URL_HOST), 'path' => '/', 'expires' => $expiration, 'httpOnly' => true, 'secure' => true, 'sameSite' => 'Lax');
}
$previous_umask = umask(0077);
try {
 $stream = fopen($session_path, 'x');
 if (!$stream) { throw new RuntimeException('Refusing to overwrite a session file.'); }
 fwrite($stream, wp_json_encode(array('user_id' => $user_id, 'token' => $token, 'cookies' => $cookies, 'post_id' => $post_id, 'base_url' => home_url())));
 fclose($stream);
} finally {
 umask($previous_umask);
}
WP_CLI::success('Local fixture ready: draft ' . $post_id . '; private session written.');

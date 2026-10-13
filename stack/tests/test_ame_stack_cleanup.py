"""Retired AME UI cleanup, retained permissions and reversible site migration."""
import base64
import json
from pathlib import Path
import subprocess
import tempfile
import unittest
import zlib

STACK = Path(__file__).parents[1]
LIBRARY = STACK / 'configs/ame-stack-cleanup.php'
MIGRATION = STACK / 'scripts/migrations/clean-ame-stack-settings.php'
EXPORTS = STACK / 'configs/exports'


def original_container():
    return {'format': {'name': 'Admin Menu Editor configuration container', 'version': '1.0'},
            'settings': {
                'admin-menu': {'tree': {
                    'tools.php': {'defaults': {'file': 'tools.php'}, 'items': [
                        {'defaults': {'file': 'mrn-seo-helper'}},
                        {'defaults': {'file': 'mrn-schema-health', 'menu_title': 'SEO Health'}}]},
                    '#ame-unclickable-menu-item-1': {'template_id': '>special:none',
                        'grant_access': {'role:editor': False}, 'items': []},
                    'seopress-option': {'defaults': {'file': 'seopress-option'}}}},
                'metaboxes': {'screens': {'case_study': {'metaBoxes:': {
                    'acf-group_69a1c0f3a1b01': {'id': 'acf-group_69a1c0f3a1b01',
                        'title': 'SEO Must Have Fields - These fields work with Smart Crawl Pro'},
                    'wds-wds-meta-box': {'id': 'wds-wds-meta-box', 'title': 'SmartCrawl'},
                    'seopress_content_analysis': {'id': 'seopress_content_analysis', 'title': 'SEO'},
                    'acf-group_mrn_content_builder': {'id': 'acf-group_mrn_content_builder',
                        'grantAccess': {'role:editor': True}}}}}},
                'table-columns': {'screens': {'edit-case_study': {
                    'defaultOrder': ['title', 'seo', 'smartcrawl-robots', 'seopress_score'],
                    'columns': {'title': {'present': True}, 'seo': {'present': True},
                        'smartcrawl-robots': {'present': True}, 'seopress_score': {'present': True}}}}},
                'dashboard-widget-editor': {'format': {'name': 'Admin Menu Editor dashboard widgets', 'version': '1.0'},
                    'widgets': [{'id': 'wds_sitemaps_dashboard_widget'}, {'id': 'mrn_support_widget'}]},
                'roles-and-capabilities': {'capabilityIndex': ['read', 'searchwp_settings'],
                    'roles': {'editor': {'compCaps': {'format': 'rle', 'data': [1, 2]}}}},
                'branding': {'custom_howdy_text': 'Welcome'}, 'enabled-modules': {'metaboxes': True}}}


def transform(container, toolbar=None):
    code = ('require ' + json.dumps(str(LIBRARY)) + ';'
            '$x=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);'
            '$c=new MRN_AME_Stack_Cleanup();$a=$c->container($x["container"]);'
            '$t=$c->toolbar($x["toolbar"]);echo json_encode([$a,$t,$c->removed]);')
    result = subprocess.run(['php', '-r', code], input=json.dumps(
        {'container': container, 'toolbar': toolbar or {'nodes': {}}}),
        text=True, capture_output=True, check=True)
    return json.loads(result.stdout)


STUB = '''<?php
define('WP_CLI', true); define('ABSPATH', __DIR__.'/');
$statePath=getenv('AME_TEST_STATE');
$options=json_decode(file_get_contents($statePath),true);
function get_option($name,$default=false){global $options;return $options[$name]??$default;}
function update_option($name,$value,$autoload=null){global $options,$statePath;$options[$name]=$value;file_put_contents($statePath,json_encode($options));return true;}
function add_option($name,$value,$deprecated='',$autoload=null){global $options;if(array_key_exists($name,$options))return false;return update_option($name,$value);}
function delete_option($name){global $options,$statePath;unset($options[$name]);file_put_contents($statePath,json_encode($options));return true;}
function is_multisite(){return false;}
function is_plugin_active($name){return in_array($name,get_option('active_plugins',[]),true);}
function home_url(){return 'https://fixture.localhost';}
function wp_json_encode($value){return json_encode($value);}
function mrn_public_security_admin_capability(){return 'manage_options';}
class WP_CLI {
 static function error($message){fwrite(STDERR,$message);exit(1);}
 static function line($message){echo $message;}
 static function success($message){echo $message;}
}
$args=array_slice($argv,2);require $argv[1];
'''


class Cleanup(unittest.TestCase):
    def test_retired_ui_is_removed_but_native_seo_content_and_permissions_survive(self):
        before = original_container()
        after, _, counts = transform(before)
        settings = after['settings']
        boxes = settings['metaboxes']['screens']['case_study']['metaBoxes:']
        self.assertEqual(set(boxes), {'seopress_content_analysis', 'acf-group_mrn_content_builder'})
        self.assertEqual(settings['table-columns']['screens']['edit-case_study']['defaultOrder'],
                         ['title', 'seopress_score'])
        self.assertEqual(settings['dashboard-widget-editor']['widgets'], [{'id': 'mrn_support_widget'}])
        for key in ['roles-and-capabilities', 'branding', 'enabled-modules']:
            self.assertEqual(settings[key], before['settings'][key])
        self.assertEqual(settings['admin-menu']['tree']['tools.php']['items'],
                         [{'defaults': {'file': 'mrn-schema-health', 'menu_title': 'SEO Health'}}])
        advanced = settings['admin-menu']['tree']['mrn-public-security-hardening']
        self.assertEqual(advanced['grant_access'], {'role:editor': False})
        self.assertEqual(advanced['defaults']['access_level'], 'manage_options')
        self.assertEqual(counts['metaboxes'], 2)

    def test_existing_native_advanced_configuration_wins(self):
        before = original_container()
        native = {'defaults': {'file': 'mrn-public-security-hardening'}, 'hidden': True}
        before['settings']['admin-menu']['tree']['mrn-public-security-hardening'] = native
        after, _, _ = transform(before)
        self.assertEqual(after['settings']['admin-menu']['tree']['mrn-public-security-hardening'], native)

    def test_toolbar_children_of_retired_nodes_are_removed(self):
        toolbar = {'nodes': {'wds_wizard': {'id': 'wds_wizard'},
            'custom-child': {'id': 'custom-child', 'parent': 'wds_wizard'},
            'grandchild': {'id': 'grandchild', 'parent': 'custom-child'},
            'seopress': {'id': 'seopress', 'defaults': {'title': 'SEO'}}}}
        _, after, _ = transform(original_container(), toolbar)
        self.assertEqual(list(after['nodes']), ['seopress'])

    def test_shared_exports_are_clean_and_cleanup_is_idempotent(self):
        container = json.loads((EXPORTS / 'ame-config-container.json').read_text())
        toolbar = json.loads((EXPORTS / 'ame-toolbar-editor.settings.json').read_text())
        after, bar, counts = transform(container, toolbar['ws_abe_admin_bar_settings'])
        self.assertEqual(after, container)
        self.assertEqual(bar, toolbar['ws_abe_admin_bar_settings'])
        self.assertFalse(counts)
        for screen in container['settings']['metaboxes']['screens'].values():
            self.assertNotIn('acf-group_69a1c0f3a1b01', screen.get('metaBoxes:', {}))
            self.assertNotIn('wds-wds-meta-box', screen.get('metaBoxes:', {}))


class Migration(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.stub = self.root / 'stub.php'; self.stub.write_text(STUB)
        self.state = self.root / 'state.json'
        settings = original_container()['settings']
        widget_json = json.dumps(settings['dashboard-widget-editor']).encode()
        self.before = {'active_plugins': ['admin-menu-editor-pro/menu-editor.php',
                'wp-seopress/seopress.php', 'wp-seopress-pro/seopress-pro.php'],
            'ws_menu_editor_pro': {'custom_menu': settings['admin-menu'], 'plugin_access': 'manage_options'},
            'ws_ame_meta_boxes': json.dumps(settings['metaboxes']),
            'ws_ame_table_columns': json.dumps(settings['table-columns']),
            'ws_ame_dashboard_widgets': 'WHgz64D' + base64.b64encode(zlib.compress(widget_json)).decode(),
            'ws_abe_admin_bar_settings': {'nodes': {'wds_wizard': {'id': 'wds_wizard'},
                'seopress': {'id': 'seopress'}}, 'plugin_access': 'manage_options'},
            'ws_abe_admin_bar_nodes': {'wds_wizard': {'id': 'wds_wizard'}},
            'wp_user_roles': {'editor': {'capabilities': {'edit_posts': True}}},
            'client_seo_metadata': {'title': 'Client title'}, 'unrelated': {'retained': True}}
        self.save(self.before)

    def tearDown(self):
        self.temp.cleanup()

    def save(self, value):
        self.state.write_text(json.dumps(value))

    def read(self):
        return json.loads(self.state.read_text())

    def run_mode(self, mode, digest=None):
        import os
        return subprocess.run(['php', str(self.stub), str(MIGRATION), mode] + ([digest] if digest else []),
            env={**os.environ, 'AME_TEST_STATE': str(self.state)}, text=True, capture_output=True)

    def plan(self):
        result = self.run_mode('plan')
        self.assertEqual(result.returncode, 0, result.stderr)
        return json.loads(result.stdout)['plan_sha256']

    def test_plan_apply_repeat_rollback_and_repeat_preserve_unrelated_data(self):
        digest = self.plan()
        self.assertEqual(self.read(), self.before)
        for mode in ['apply', 'apply']:
            result = self.run_mode(mode, digest)
            self.assertEqual(result.returncode, 0, result.stderr)
        after = self.read()
        for key in ['wp_user_roles', 'client_seo_metadata', 'unrelated', 'active_plugins']:
            self.assertEqual(after[key], self.before[key])
        self.assertNotIn('wds-wds-meta-box', json.loads(after['ws_ame_meta_boxes'])['screens']['case_study']['metaBoxes:'])
        self.assertFalse(after['ws_abe_admin_bar_nodes'])
        for mode in ['rollback', 'rollback']:
            result = self.run_mode(mode, digest)
            self.assertEqual(result.returncode, 0, result.stderr)
        for key, value in self.before.items():
            self.assertEqual(self.read()[key], value)

    def test_changed_plan_and_active_legacy_plugin_refuse_before_writing(self):
        digest = self.plan()
        value = self.read(); value['ws_menu_editor_pro']['plugin_access'] = 'administrator'; self.save(value)
        self.assertNotEqual(self.run_mode('apply', digest).returncode, 0)
        self.assertEqual(self.read(), value)
        value['active_plugins'].append('wpmu-dev-seo/wpmu-dev-seo.php'); self.save(value)
        self.assertNotEqual(self.run_mode('plan').returncode, 0)
        self.assertEqual(self.read(), value)

    def test_rollback_refuses_a_subsequent_admin_change(self):
        digest = self.plan(); self.assertEqual(self.run_mode('apply', digest).returncode, 0)
        value = self.read(); value['ws_abe_admin_bar_settings']['plugin_access'] = 'administrator'; self.save(value)
        self.assertNotEqual(self.run_mode('rollback', digest).returncode, 0)
        self.assertEqual(self.read(), value)

    def test_existing_lock_and_unsupported_encoding_fail_closed(self):
        digest = self.plan()
        value = self.read(); value['mrn_ame_stack_cleanup_lock'] = 'other-worker'; self.save(value)
        self.assertNotEqual(self.run_mode('apply', digest).returncode, 0)
        self.assertEqual(self.read(), value)
        value.pop('mrn_ame_stack_cleanup_lock'); value['ws_ame_table_columns'] = 'gzcompress:invalid'; self.save(value)
        self.assertNotEqual(self.run_mode('plan').returncode, 0)
        self.assertEqual(self.read(), value)


if __name__ == '__main__':
    unittest.main()

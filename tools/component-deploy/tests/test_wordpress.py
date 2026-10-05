"""Real WordPress bootstrap in a fresh temporary SQLite fixture, never a site.

Provide downloaded core/SQLite ZIPs in MRN_COMPONENT_WP_ARCHIVE and
MRN_COMPONENT_SQLITE_ARCHIVE. Both are checked against the qualified digests.
The test only extracts into a newly created TemporaryDirectory; it has no
existing-site target, credentials, mail delivery or remote transport option.
"""
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
import zipfile

import test_components as fixtures


ARCHIVES = {
    'MRN_COMPONENT_WP_ARCHIVE': ('wordpress', '8fc96c59a78b7219e4a130222b7fadb51b03e503e8b0123beaa7e28961c21ce2'),
    'MRN_COMPONENT_SQLITE_ARCHIVE': ('sqlite-database-integration', '1602e75577ad9b3a7e3e4a6a44a81b9541cdee2124d48928faf61c6fd3cd4f74'),
}


@unittest.skipUnless(all(os.environ.get(name) for name in ARCHIVES), 'Pinned WordPress/SQLite test archives not supplied')
class WordPressBootstrap(unittest.TestCase):
    def test_real_plugin_loader_activation_assets_and_request_pinning(self):
        fixtures.Components.setUpClass()
        self.addCleanup(fixtures.Components.tearDownClass)
        with tempfile.TemporaryDirectory(prefix='mrn-component-wordpress-') as temporary:
            root = Path(temporary).resolve()
            for variable, (prefix, expected) in ARCHIVES.items():
                archive_path = Path(os.environ[variable])
                self.assertEqual(expected, fixtures.sha(archive_path), 'Unqualified fixture archive')
                with zipfile.ZipFile(archive_path) as archive:
                    self.assertTrue(all(name.startswith(prefix + '/') and '..' not in name.split('/') for name in archive.namelist()))
                    archive.extractall(root / 'dependencies')
            shutil.move(root / 'dependencies/wordpress', root / 'public')
            public, plugin, parent, state = fixtures.Components().prepare_runtime(temporary)
            sqlite = root / 'dependencies/sqlite-database-integration'
            dropin = (sqlite / 'db.copy').read_text().replace('{SQLITE_IMPLEMENTATION_FOLDER_PATH}', str(sqlite))
            (public / 'wp-content/db.php').write_text(dropin)
            (public / 'wp-config.php').write_text('''<?php
define('DB_NAME','isolated_component_fixture'); define('DB_USER',''); define('DB_PASSWORD',''); define('DB_HOST','');
define('DB_ENGINE','sqlite'); define('DB_DIR',__DIR__.'/wp-content/database/');
define('WP_HTTP_BLOCK_EXTERNAL',true); define('DISABLE_WP_CRON',true);
define('WP_AUTO_UPDATE_CORE',false); define('AUTOMATIC_UPDATER_DISABLED',true);
define('WP_HOME','http://127.0.0.1:9876'); define('WP_SITEURL','http://127.0.0.1:9876');
define('WP_ENVIRONMENT_TYPE','local'); define('WP_DEBUG',false);
$table_prefix='fixture_'; if(!defined('ABSPATH')){define('ABSPATH',__DIR__.'/');} require ABSPATH.'wp-settings.php';
''')
            mu = public / 'wp-content/mu-plugins'
            mu.mkdir()
            (mu / '000-fixture-boundary.php').write_text('''<?php
add_filter('pre_wp_mail','__return_false');
add_filter('pre_http_request',static function(){return new WP_Error('fixture_network_disabled');});
''')
            (state / 'control').mkdir()
            shutil.copyfile(fixtures.TOOLS / 'runtime.php', state / 'control/runtime.php')
            bootstrap = (fixtures.TOOLS / 'bootstrap.php').read_text().replace('__MRN_COMPONENT_STATE_RELATIVE__', os.path.relpath(state, public))
            bootstrap = bootstrap.replace('__MRN_COMPONENT_RUNTIME_SHA256__', fixtures.sha(fixtures.TOOLS / 'runtime.php'))
            (mu / '001-component-candidate.php').write_text(bootstrap)
            (plugin / 'mrn-fixture.php').write_text('''<?php
/* Plugin Name: Fixture
Version: 0.0.0
*/
require MRN_Component_Release_Runtime::entrypoint(__FILE__);
''')
            (parent / 'style.css').write_text('/* Theme Name: Fixture parent\nVersion: 0.0.0\n*/')
            (parent / 'functions.php').write_text("<?php $GLOBALS['untouched_parent'] = true;")
            (parent / 'index.php').write_text('<?php // Untouched parent fixture.')
            child = public / 'wp-content/themes/fixture-child'
            child.mkdir()
            (child / 'style.css').write_text('/* Theme Name: Fixture child\nTemplate: mrn-base-stack\nVersion: 1.0.0\n*/')
            (child / 'functions.php').write_text("<?php $GLOBALS['untouched_child'] = true;")

            def php(body, install=False):
                program = '<?php\n' + ("define('WP_INSTALLING',true);\n" if install else '')
                program += 'require ' + repr(str(public / 'wp-load.php')) + ';\n' + body
                result = subprocess.run(['php'], input=program, capture_output=True, text=True, timeout=60)
                self.assertEqual(0, result.returncode, result.stderr + result.stdout)

                try:
                    return json.loads(result.stdout)
                except json.JSONDecodeError:
                    self.fail(result.stderr + result.stdout[:6000])

            installed = php('''
require ABSPATH.'wp-admin/includes/upgrade.php';
wp_install('Isolated fixture','fixture-admin','fixture@example.invalid',false,'',bin2hex(random_bytes(24)));
update_option('active_plugins',['mrn-fixture/mrn-fixture.php']);
update_option('template','mrn-base-stack'); update_option('stylesheet','fixture-child');
echo json_encode(['installed'=>is_blog_installed()]);
''', install=True)
            self.assertTrue(installed['installed'])
            old_pointer = (state / 'current.json').read_bytes()
            result = php('''
require_once ABSPATH.'wp-admin/includes/plugin.php';
$entry=MRN_Component_Release_Runtime::entrypoint(WP_PLUGIN_DIR.'/mrn-fixture/mrn-fixture.php');
$asset=plugins_url('assets/site.css',$entry);
register_activation_hook($entry,static function(){$GLOBALS['activation_identity']=true;});
do_action('activate_mrn-fixture/mrn-fixture.php');
rename(%s,%s);
$result=['code'=>$GLOBALS['fixture_code'],'basename'=>plugin_basename($entry),
 'pinned'=>plugins_url('assets/site.css',$entry)===$asset,'asset'=>$asset,
 'activation'=>$GLOBALS['activation_identity']??false,'active'=>is_plugin_active('mrn-fixture/mrn-fixture.php'),
 'metadata'=>apply_filters('all_plugins',get_plugins())['mrn-fixture/mrn-fixture.php']['Version'],
 'native_metadata'=>get_plugins()['mrn-fixture/mrn-fixture.php']['Version'],
 'parent'=>$GLOBALS['untouched_parent']??false,'child'=>$GLOBALS['untouched_child']??false,
 'template'=>get_template_directory(),'stylesheet'=>get_stylesheet_directory(),
 'wp_version'=>$GLOBALS['wp_version']];
echo json_encode($result);
''' % (repr(str(state / 'next.json')), repr(str(state / 'current.json'))))
            self.assertEqual('old', result['code'])
            self.assertEqual('mrn-fixture/mrn-fixture.php', result['basename'])
            for key in ('pinned', 'activation', 'active', 'parent', 'child'):
                self.assertTrue(result[key], key)
            self.assertEqual(str(parent), result['template'])
            self.assertEqual(str(child), result['stylesheet'])
            self.assertEqual('1.0.0', result['metadata'])
            # A stable stub leaves raw get_plugins() metadata unchanged; signed
            # inventory/readback integration is a required promotion gate.
            self.assertEqual('0.0.0', result['native_metadata'])
            self.assertIn('/mrn-assets/mrn-fixture/', result['asset'])
            self.assertTrue(result['asset'].endswith('/assets/site.min.css'))
            self.assertEqual('7.1.2', result['wp_version'])
            self.assertEqual('new', php("echo json_encode($GLOBALS['fixture_code']);"))
            # A fresh request following a pointer rollback gets old code again.
            (state / 'rollback.json').write_bytes(old_pointer)
            os.replace(state / 'rollback.json', state / 'current.json')
            self.assertEqual('old', php("echo json_encode($GLOBALS['fixture_code']);"))
            php("update_option('active_plugins',[]); echo json_encode(true);")
            self.assertFalse(php("echo json_encode(isset($GLOBALS['fixture_code']));"))


if __name__ == '__main__':
    unittest.main()

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
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.request
import zipfile

import test_components as fixtures


ARCHIVES = {
    'MRN_COMPONENT_WP_ARCHIVE': ('wordpress', '8fc96c59a78b7219e4a130222b7fadb51b03e503e8b0123beaa7e28961c21ce2'),
    'MRN_COMPONENT_SQLITE_ARCHIVE': ('sqlite-database-integration', '1602e75577ad9b3a7e3e4a6a44a81b9541cdee2124d48928faf61c6fd3cd4f74'),
}


@unittest.skipUnless(all(os.environ.get(name) for name in ARCHIVES), 'Pinned WordPress/SQLite test archives not supplied')
class WordPressBootstrap(unittest.TestCase):
    def test_real_plugin_loader_activation_assets_and_request_pinning(self):
        self.qualify(False)

    def test_real_parent_discovery_cohort_rollback_and_lifecycle(self):
        self.qualify(True)

    @unittest.skipUnless(os.environ.get('MRN_COMPONENT_BROWSER') == '1', 'Disposable HTTP/browser qualification not requested')
    def test_real_http_browser_cache_assets_and_opcache(self):
        self.qualify(True, public_probe=True)

    def test_real_parent_with_independent_child_release_loader(self):
        self.qualify(True, atomic_child=True)

    def qualify(self, parent_selected, public_probe=False, atomic_child=False):
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
define('FS_METHOD','direct');
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
            child_state = None
            if atomic_child:
                child_state = root / 'child-state'
                child_state.mkdir(mode=0o700)
                for label, code in [('a', 'old'), ('b', 'new')]:
                    identity = label * 64
                    directory = child_state / 'releases' / identity / 'theme'
                    directory.mkdir(parents=True)
                    shutil.copyfile(child / 'style.css', directory / 'style.css')
                    (directory / 'functions.php').write_text("<?php $GLOBALS['untouched_child']=true; $GLOBALS['released_child']='" + code + "';")
                    public_path = 'mrn-assets/fixture-child/' + identity
                    manifest = {'schema': 1, 'slug': 'fixture-child', 'public_path': public_path}
                    (directory / 'mrn-assets.json').write_text(json.dumps(manifest))
                    metadata = {'schema': 1, 'slug': 'fixture-child', 'release_id': identity,
                                'public_path': public_path, 'theme_files': {'mrn-assets.json': fixtures.sha(directory / 'mrn-assets.json')}}
                    (directory.parent / 'installed.json').write_text(json.dumps(metadata))
                    pointer = {'schema': 1, 'slug': 'fixture-child', 'release_id': identity, 'public_path': public_path}
                    (child_state / ('current.json' if label == 'a' else 'next.json')).write_text(json.dumps(pointer))
                bootstrap = (fixtures.TOOLS.parent / 'site-deploy/release-bootstrap.php').read_text()
                (child / 'functions.php').write_text(bootstrap.replace('__MRN_STATE_RELATIVE__', os.path.relpath(child_state, public)))
                (child_state / 'adoption.json').write_text(json.dumps({'schema': 1, 'slug': 'fixture-child', 'bootstrap_sha256': fixtures.sha(child / 'functions.php')}))
            child_before = {path.name: path.read_bytes() for path in child.iterdir()}
            if parent_selected:
                view = fixtures.Components().add_theme_view(state, child, 'current.json', fixtures.Components.parent, child_state=child_state)
                fixtures.Components().add_theme_view(state, child, 'next.json', fixtures.Components.new_parent, child_state=child_state)
            old_pointer = (state / 'current.json').read_bytes()
            next_pointer = (state / 'next.json').read_bytes()
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
 'parent_code'=>$GLOBALS['parent_code']??null,
 'template'=>get_template_directory(),'stylesheet'=>get_stylesheet_directory(),
 'theme_version'=>wp_get_theme()->parent()->get('Version'),
 'theme_template'=>wp_get_theme()->get_template_directory(),
 'theme_stylesheet_uri'=>wp_get_theme()->parent()->get_stylesheet_directory_uri().'/style.css',
 'theme_child_uri'=>wp_get_theme()->get_stylesheet_directory_uri(),
 'template_uri'=>get_template_directory_uri(),
 'located'=>locate_template('index.php'),
 'block_template'=>get_theme_file_path('templates/index.html'),
 'themes'=>array_keys(wp_get_themes()),
 'parent_files'=>wp_get_theme()->parent()->get_files('php',1),
 'pattern'=>WP_Block_Patterns_Registry::get_instance()->get_registered('mrn-base-stack/fixture'),
 'saved_roots'=>get_option('_site_transient_theme_roots'),
 'wp_version'=>$GLOBALS['wp_version'],'released_child'=>$GLOBALS['released_child']??null];
echo json_encode($result);
''' % (repr(str(state / 'next.json')), repr(str(state / 'current.json'))))
            self.assertEqual('old', result['code'])
            self.assertEqual('mrn-fixture/mrn-fixture.php', result['basename'])
            for key in ('pinned', 'activation', 'active', 'child'):
                self.assertTrue(result[key], key)
            if parent_selected:
                selected_parent = state / 'releases' / fixtures.Components.parent['artifact_sha256'] / 'component/mrn-base-stack'
                self.assertEqual('old', result['parent_code'])
                self.assertFalse(result['parent'])
                self.assertEqual(str(selected_parent), result['template'])
                self.assertEqual(str(view / 'mrn-base-stack'), result['theme_template'])
                self.assertEqual('1.0.0', result['theme_version'])
                self.assertEqual(str(selected_parent / 'index.php'), result['located'])
                self.assertEqual(str(selected_parent / 'templates/index.html'), result['block_template'])
                self.assertEqual(str(view / 'mrn-base-stack/functions.php'), result['parent_files']['functions.php'])
                self.assertIn('Old parent pattern.', result['pattern']['content'])
                self.assertIn('/mrn-assets/', result['template_uri'])
                self.assertTrue(result['theme_stylesheet_uri'].endswith('/mrn-base-stack/style.css'))
                child_url = 'http://127.0.0.1:9876/wp-content/themes/fixture-child'
                self.assertEqual(child_url, result['theme_child_uri'])
                self.assertIn('twentytwentyfive', result['themes'])
                self.assertNotIn(str(state), json.dumps(result['saved_roots']))
            else:
                self.assertTrue(result['parent'])
                self.assertEqual(str(parent), result['template'])
            expected_child = child_state / 'releases' / ('a' * 64) / 'theme' if atomic_child else child
            self.assertEqual(str(expected_child), result['stylesheet'])
            if atomic_child:
                self.assertEqual('old', result['released_child'])
                subprocess.run(['mv', str(child_state / 'next.json'), str(child_state / 'current.json')], check=True)
                fresh_child = php("echo json_encode([$GLOBALS['released_child'],get_stylesheet_directory(),get_stylesheet_directory_uri()]);")
                self.assertEqual('new', fresh_child[0])
                self.assertEqual(str(child_state / 'releases' / ('b' * 64) / 'theme'), fresh_child[1])
                self.assertTrue(fresh_child[2].endswith('/mrn-assets/fixture-child/' + 'b' * 64))
            self.assertEqual('1.0.0', result['metadata'])
            # A stable stub leaves raw get_plugins() metadata unchanged; signed
            # inventory/readback integration is a required promotion gate.
            self.assertEqual('0.0.0', result['native_metadata'])
            self.assertIn('/mrn-assets/mrn-fixture/', result['asset'])
            self.assertTrue(result['asset'].endswith('/assets/site.min.css'))
            self.assertEqual('7.1.2', result['wp_version'])
            self.assertEqual('new', php("echo json_encode($GLOBALS['fixture_code']);"))
            if parent_selected:
                fresh = php("echo json_encode([$GLOBALS['parent_code'],wp_get_theme()->parent()->get('Version'),get_template_directory_uri()]);")
                self.assertEqual(['new', '1.1.0'], fresh[:2])
                self.assertNotEqual(result['template_uri'], fresh[2])
            # A fresh request following a pointer rollback gets old code again.
            (state / 'rollback.json').write_bytes(old_pointer)
            os.replace(state / 'rollback.json', state / 'current.json')
            self.assertEqual('old', php("echo json_encode($GLOBALS['fixture_code']);"))
            if parent_selected:
                self.assertEqual(['old', '1.0.0'], php("echo json_encode([$GLOBALS['parent_code'],wp_get_theme()->parent()->get('Version')]);"))
            if public_probe:
                self.qualify_public(public, state, old_pointer, next_pointer)
            guarded = php('''
require_once ABSPATH.'wp-admin/includes/plugin.php';
require_once ABSPATH.'wp-admin/includes/file.php';
require_once ABSPATH.'wp-admin/includes/theme.php';
$blocked=[];
foreach(['uninstall'=>static function(){uninstall_plugin('mrn-fixture/mrn-fixture.php');},
 'delete'=>static function(){delete_plugins(['mrn-fixture/mrn-fixture.php']);}] as $key=>$attempt){
 try{$attempt();}catch(RuntimeException $e){$blocked[]=$key;}
}
if(isset($GLOBALS['parent_code'])){
 foreach(['parent-delete'=>static function(){delete_theme('mrn-base-stack');},
 'child-delete'=>static function(){delete_theme('fixture-child');},
 'switch'=>static function(){update_option('stylesheet','twentytwentyfive');}] as $key=>$attempt){
  try{$attempt();}catch(RuntimeException $e){$blocked[]=$key;}
 }
}
echo json_encode($blocked);
''')
            self.assertEqual(['uninstall', 'delete'] + (['parent-delete', 'child-delete', 'switch'] if parent_selected else []), guarded)
            upgrades = php('''
require_once ABSPATH.'wp-admin/includes/plugin.php';
require_once ABSPATH.'wp-admin/includes/file.php';
require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
$source=ABSPATH.'wp-content/upgrade/fixture/mrn-fixture';
wp_mkdir_p($source);
file_put_contents($source.'/mrn-fixture.php',"<?php /* Plugin Name: Overwrite */");
$upgrader=new WP_Upgrader(new Automatic_Upgrader_Skin());
$upgrader->fs_connect([WP_CONTENT_DIR,WP_PLUGIN_DIR]);
$result=$upgrader->install_package(['source'=>dirname($source),'destination'=>WP_PLUGIN_DIR,
 'clear_destination'=>true,'hook_extra'=>['type'=>'plugin','action'=>'install']]);
echo json_encode(['overwrite_error'=>is_wp_error($result)?$result->get_error_code():null,
 'stub_intact'=>str_contains(file_get_contents(WP_PLUGIN_DIR.'/mrn-fixture/mrn-fixture.php'),'MRN_Component_Release_Runtime'),
 'still_active'=>is_plugin_active('mrn-fixture/mrn-fixture.php')]);
''')
            self.assertEqual({'overwrite_error': 'mrn_component_managed_release', 'stub_intact': True, 'still_active': True}, upgrades)
            if parent_selected:
                refused = php('''
add_filter('wp_die_handler',static function(){return static function($message){throw new RuntimeException('theme-switch-blocked');};});
$before=get_option('theme_switch_menu_locations','absent');
try{switch_theme('twentytwentyfive');}catch(RuntimeException $e){$blocked=true;}
echo json_encode([$blocked??false,get_option('theme_switch_menu_locations','absent')===$before,get_stylesheet()]);
''')
                self.assertEqual([True, True, 'fixture-child'], refused)
            php("update_option('active_plugins',[]); echo json_encode(true);")
            self.assertFalse(php("echo json_encode(isset($GLOBALS['fixture_code']));"))
            self.assertEqual(child_before, {path.name: path.read_bytes() for path in child.iterdir()})
            if parent_selected:
                link = view / 'mrn-base-stack'
                original_link = link.readlink()
                link.unlink()
                link.symlink_to(parent, target_is_directory=True)
                rejected = subprocess.run(['php'], input='<?php try { require ' + repr(str(public / 'wp-load.php')) +
                    '; echo "UNSAFE"; } catch(RuntimeException $e) { echo $e->getMessage(); }', text=True, capture_output=True, check=True)
                self.assertIn('does not match', rejected.stdout)
                link.unlink()
                link.symlink_to(original_link, target_is_directory=True)

    def qualify_public(self, public, state, old_pointer, next_pointer):
        """Serve only a new temporary fixture. This is not a hosting adapter."""
        with socket.socket() as probe:
            probe.bind(('127.0.0.1', 0))
            port = probe.getsockname()[1]
        base_url = f'http://127.0.0.1:{port}'
        config = public / 'wp-config.php'
        config.write_text(config.read_text().replace('http://127.0.0.1:9876', base_url))
        (state / 'browser-old.json').write_bytes(old_pointer)
        (state / 'browser-next.json').write_bytes(next_pointer)
        assets = {}
        for release in (state / 'releases').iterdir():
            for path in (release / 'assets').rglob('*'):
                if path.is_file():
                    relative = path.relative_to(release / 'assets')
                    destination = public / 'wp-content' / relative
                    destination.parent.mkdir(parents=True, exist_ok=True)
                    if destination.exists():
                        self.assertEqual(path.read_bytes(), destination.read_bytes())
                    else:
                        shutil.copyfile(path, destination)
                    assets['/wp-content/' + relative.as_posix()] = fixtures.sha(path)
        (public / 'fixture-page.php').write_text('''<?php
require __DIR__.'/wp-load.php';
header('Cache-Control: no-cache');
$entry=MRN_Component_Release_Runtime::entrypoint(WP_PLUGIN_DIR.'/mrn-fixture/mrn-fixture.php');
$style=plugins_url('assets/site.css',$entry);
$script=plugins_url('assets/main.js',$entry);
$parent_style=MRN_Component_Release_Runtime::asset_url(get_template_directory_uri().'/style.css');
$opcache=function_exists('opcache_get_status')&&opcache_get_status(false)!==false;
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Component fixture</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" href="data:,">
<link rel="stylesheet" href="<?php echo esc_url($parent_style); ?>">
<link rel="stylesheet" href="<?php echo esc_url($style); ?>">
<script type="module" src="<?php echo esc_url($script); ?>"></script>
</head><body><main><h1 class="fixture">Component fixture</h1>
<p id="generation" data-opcache="<?php echo $opcache?'yes':'no'; ?>"><?php echo esc_html($GLOBALS['parent_code'].'/'.$GLOBALS['fixture_code']); ?></p>
<a href="/sample-page/">Sample page</a></main></body></html>
''')
        router = state / 'fixture-router.php'
        router.write_text('''<?php
// Test-only serving policy, never a production server configuration.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/wp-content/mrn-assets/')){
 $file=realpath($_SERVER['DOCUMENT_ROOT'].$path);
 if(!$file||!str_starts_with($file,$_SERVER['DOCUMENT_ROOT'].'/wp-content/mrn-assets/')||!is_file($file)){http_response_code(404);return true;}
 $types=['css'=>'text/css','js'=>'text/javascript','mjs'=>'text/javascript','svg'=>'image/svg+xml'];
 header('Content-Type: '.($types[pathinfo($file,PATHINFO_EXTENSION)]??'application/octet-stream'));
 header('Cache-Control: public, max-age=31536000, immutable');readfile($file);return true;
}
if($path==='/wp-json/'){$_GET['rest_route']='/';require $_SERVER['DOCUMENT_ROOT'].'/index.php';return true;}
if(in_array($path,['/','/sample-page/'],true)){require $_SERVER['DOCUMENT_ROOT'].'/fixture-page.php';return true;}
return false;
''')
        with tempfile.TemporaryFile(mode='w+') as server_log:
            server = subprocess.Popen(['php', '-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_timestamps=0',
                                       '-S', f'127.0.0.1:{port}', '-t', str(public), str(router)],
                                      stdout=server_log, stderr=server_log)
            try:
                for _ in range(100):
                    if server.poll() is not None:
                        server_log.seek(0)
                        self.fail('Fixture server failed: ' + server_log.read())
                    try:
                        with urllib.request.urlopen(base_url + '/', timeout=1) as response:
                            self.assertEqual(200, response.status)
                        break
                    except OSError:
                        time.sleep(0.05)
                else:
                    self.fail('Fixture server did not become ready')
                for path, checksum in assets.items():
                    with urllib.request.urlopen(base_url + path, timeout=5) as response:
                        self.assertEqual(checksum, hashlib.sha256(response.read()).hexdigest(), path)
                        self.assertIn('immutable', response.headers['Cache-Control'])
                        if path.endswith('.css'):
                            self.assertEqual('text/css', response.headers.get_content_type())
                        elif path.endswith(('.js', '.mjs')):
                            self.assertEqual('text/javascript', response.headers.get_content_type())
                result = subprocess.run(['node', str(fixtures.TOOLS / 'tests/browser.mjs'),
                                         base_url, str(public), str(state)], capture_output=True, text=True, timeout=90)
                self.assertEqual(0, result.returncode, result.stderr + result.stdout)
                browser_report = json.loads(result.stdout)
                self.assertEqual(['old/old', 'old/old', 'new/new', 'new/new', 'old/old', 'old/old'], browser_report['generations'])
                self.assertTrue(browser_report['opcache_enabled'])
                qa_output = os.environ.get('MRN_COMPONENT_QA_OUTPUT')
                if qa_output:
                    report = Path(qa_output).resolve()
                    report.parent.mkdir(parents=True, exist_ok=True)
                    report.with_suffix('.browser.json').write_text(json.dumps(browser_report, indent=2) + '\n')
                    engine = Path(os.environ['MRN_COMPONENT_QA_ENGINE']).resolve()
                    project = fixtures.TOOLS.parents[1]
                    # The engine's ordinary Stack scope invokes a broad SSH
                    # parity audit even in site-only mode. The task boundary
                    # skips that audit; explicit runtime flags below still run.
                    # Deny SSH as a second boundary if engine defaults change.
                    guard_bin = state / 'qa-guard-bin'
                    guard_bin.mkdir()
                    ssh_guard = guard_bin / 'ssh'
                    ssh_guard.write_text('#!/bin/sh\necho "Fixture QA forbids SSH" >&2\nexit 89\n')
                    ssh_guard.chmod(0o700)
                    env = {**os.environ, 'MRN_QA_SAMPLE_PATH': '/sample-page/', 'MRN_QA_PHPSTAN_STRICT': '1',
                           'MRN_QA_COMMIT_GATE': '1', 'PATH': str(guard_bin) + os.pathsep + os.environ['PATH']}
                    with report.with_suffix('.log').open('w') as log:
                        result = subprocess.run([str(engine), 'run', '--project-root', str(project), '--stack-root', str(project),
                            '--site-path', str(public), '--site-url', base_url, '--scope', 'site-only',
                            '--run-smoke', 'always', '--run-accessibility', 'always', '--run-performance', 'always',
                            '--run-api', 'always', '--run-cwv', 'always', '--run-phpcbf', 'never',
                            '--smoke-strict', '1', '--output-file', str(report)], env=env, stdout=log, stderr=log, timeout=600)
                    self.assertEqual(0, result.returncode, report.with_suffix('.log').read_text()[-8000:])
            finally:
                server.terminate()
                server.wait(timeout=10)


if __name__ == '__main__':
    unittest.main()

"""Isolated source/artifact and request-selection regressions. No site access."""
import copy
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest
import zipfile

TOOLS = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(TOOLS))
from build import build
from verify import verify


def git(repo, *args):
    return subprocess.check_output(['git', '-C', str(repo), *args], stderr=subprocess.DEVNULL).decode().strip()


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


class Components(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temporary = tempfile.TemporaryDirectory(prefix='mrn-component-tests-')
        cls.root = Path(cls.temporary.name).resolve()
        cls.repo = cls.root / 'repo'
        cls.repo.mkdir()
        git(cls.repo, 'init', '-q')
        git(cls.repo, 'config', 'user.name', 'Fixture')
        git(cls.repo, 'config', 'user.email', 'fixture@example.invalid')
        source = {
            'plugin/mrn-fixture.php': "<?php\n/* Plugin Name: Fixture\nVersion: 1.0.0\n*/\n$GLOBALS['fixture_code'] = 'old';\n",
            'plugin/assets/site.css': '.fixture { background: url(./dot.svg); color: red; }',
            'plugin/assets/site.min.css': 'STALE UNRELEASED BYTES',
            'plugin/assets/dot.svg': '<svg xmlns="http://www.w3.org/2000/svg"/>',
            'plugin/assets/main.js': "import './dependency.js'; import('./lazy.js');",
            'plugin/assets/dependency.js': 'window.fixtureDependency = 1;',
            'plugin/assets/lazy.js': 'window.fixtureLazy = 1;',
            'plugin/scripts/runtime.js': 'window.requiredScript = 1;',
            'plugin/vendor/runtime.php': '<?php // Required runtime library.',
            'plugin/vendor/runtime.css': '.library { color: black; }',
            'parent/style.css': '/* Theme Name: Fixture parent\nVersion: 1.0.0\n*/\n.fixture { color: red; }',
            'parent/functions.php': "<?php $GLOBALS['parent_code'] = 'old';",
            'parent/index.php': '<?php // Template.',
            'parent/templates/index.html': '<!-- wp:paragraph --><p>Old parent template.</p><!-- /wp:paragraph -->',
            'parent/patterns/fixture.php': '<?php /* Title: Fixture\nSlug: mrn-base-stack/fixture\n*/ ?>Old parent pattern.',
        }
        for name, body in source.items():
            target = cls.repo / name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_text(body)
        git(cls.repo, 'add', '.')
        git(cls.repo, 'commit', '-qm', 'Fixture old')
        cls.old_commit = git(cls.repo, 'rev-parse', 'HEAD')
        cls.old = cls.make('old', cls.old_commit)
        cls.parent = cls.make('parent', cls.old_commit, parent=True)
        (cls.repo / 'plugin/mrn-fixture.php').write_text(source['plugin/mrn-fixture.php'].replace("'old'", "'new'").replace('1.0.0', '1.1.0'))
        (cls.repo / 'plugin/assets/site.css').write_text('.fixture { color: blue; }')
        for name in ('style.css', 'functions.php', 'templates/index.html', 'patterns/fixture.php'):
            path = cls.repo / 'parent' / name
            path.write_text(path.read_text().replace('old', 'new').replace('Old', 'New').replace('1.0.0', '1.1.0'))
        git(cls.repo, 'add', '.')
        git(cls.repo, 'commit', '-qm', 'Fixture new')
        cls.new_commit = git(cls.repo, 'rev-parse', 'HEAD')
        cls.new = cls.make('new', cls.new_commit)
        cls.new_parent = cls.make('new-parent', cls.new_commit, parent=True)

    @classmethod
    def tearDownClass(cls):
        cls.temporary.cleanup()

    @classmethod
    def make(cls, label, commit, parent=False):
        return build(cls.repo, commit, 'parent' if parent else 'plugin',
                     'mrn-base-stack' if parent else 'mrn-fixture',
                     'parent-theme' if parent else 'standard-plugin',
                     'functions.php' if parent else 'mrn-fixture.php', cls.root / (label + '.zip'))

    def verify(self, path=None, checksum=None, **changes):
        receipt = {**self.old, **changes}
        return verify(path or self.root / 'old.zip', checksum or self.old['artifact_sha256'],
                      receipt['source_sha'], receipt['source_path'], receipt['slug'],
                      receipt['kind'], receipt['entrypoint'])

    def manifest(self, label='old'):
        with zipfile.ZipFile(self.root / (label + '.zip')) as archive:
            return json.loads(archive.read('component/mrn-fixture/mrn-assets.json'))

    def mutate(self, callback):
        with zipfile.ZipFile(self.root / 'old.zip') as archive:
            files = [(member, archive.read(member)) for member in archive.infolist()]
        changed = self.root / 'mutated.zip'
        with zipfile.ZipFile(changed, 'w') as archive:
            for member, body in callback(files):
                archive.writestr(member, body)
        return changed

    def test_deterministic_rebuild(self):
        receipt = self.make('rebuilt', self.old_commit)
        self.assertEqual(self.old, receipt)
        self.assertEqual((self.root / 'old.zip').read_bytes(), (self.root / 'rebuilt.zip').read_bytes())
        self.assertFalse(self.verify()['runtime_qualified'])

    def test_parent_rebuild_uses_native_theme_root_slug_layout(self):
        receipt = self.make('parent-rebuilt', self.old_commit, parent=True)
        self.assertEqual(self.parent, receipt)
        result = verify(self.root / 'parent-rebuilt.zip', receipt['artifact_sha256'], self.old_commit,
                        'parent', 'mrn-base-stack', 'parent-theme', 'functions.php')
        self.assertEqual(f"mrn-assets/{result['generation']}/mrn-base-stack", result['public_path'])

    def test_stale_minified_bytes_rebuilt_and_dependencies_preserved(self):
        manifest = self.manifest()
        with zipfile.ZipFile(self.root / 'old.zip') as archive:
            component = archive.read('component/mrn-fixture/assets/site.min.css')
            public = archive.read('assets/' + manifest['public_path'] + '/assets/site.min.css')
        self.assertEqual(component, public)
        self.assertNotIn(b'STALE', public)
        self.assertEqual(['assets/dot.svg'], manifest['dependencies']['assets/site.css'])
        self.assertEqual(['assets/dependency.js', 'assets/lazy.js'], manifest['dependencies']['assets/main.js'])
        self.assertIn('scripts/runtime.js', manifest['static_files'])
        self.assertIn('vendor/runtime.css', manifest['static_files'])

    def test_changed_bytes_change_generation(self):
        self.assertNotEqual(self.manifest()['generation'], self.manifest('new')['generation'])

    def test_refuses_overwrite(self):
        with self.assertRaisesRegex(ValueError, 'overwrite'):
            self.make('old', self.old_commit)

    def test_wrong_checksum(self):
        with self.assertRaisesRegex(ValueError, 'checksum'):
            self.verify(checksum='0' * 64)

    def test_wrong_source_or_kind(self):
        for values in ({'source_sha': '0' * 40}, {'source_path': 'other'}, {'kind': 'mu-plugin'}):
            with self.subTest(values=values), self.assertRaises(ValueError):
                self.verify(**values)

    def test_traversal_and_symlinks_rejected(self):
        for name, mode in (('../escape.php', 0o100644), ('component/mrn-fixture/link.php', 0o120777)):
            entry = zipfile.ZipInfo(name)
            entry.create_system = 3
            entry.compress_type = zipfile.ZIP_DEFLATED
            entry.external_attr = mode << 16
            path = self.mutate(lambda files: files + [(entry, b'outside')])
            with self.subTest(name=name), self.assertRaises(ValueError):
                self.verify(path, sha(path))

    def test_unlisted_file_or_changed_payload_rejected(self):
        path = self.mutate(lambda files: [(member, b'changed' if member.filename.endswith('site.css') else body) for member, body in files])
        with self.assertRaisesRegex(ValueError, 'tree'):
            self.verify(path, sha(path))

    def test_duplicate_json_key_rejected(self):
        path = self.mutate(lambda files: [(member, body.replace(b'"schema": 1', b'"schema": 1, "schema": 1') if member.filename == 'release.json' else body) for member, body in files])
        with self.assertRaisesRegex(ValueError, 'Duplicate JSON'):
            self.verify(path, sha(path))

    def test_unsupported_loader_and_missing_dependency_refused(self):
        for number, body in enumerate(("import('./missing.js');", 'import(window.dynamicChunk);', 'new Worker("./lazy.js");')):
            (self.repo / 'plugin/assets/main.js').write_text(body)
            git(self.repo, 'add', '.')
            git(self.repo, 'commit', '-qm', 'Unsupported fixture')
            with self.subTest(body=body), self.assertRaises(subprocess.CalledProcessError):
                # Hide expected parser diagnostics; never contacts a runtime.
                subprocess.run([sys.executable, str(TOOLS / 'build.py'), '--repo', str(self.repo),
                                '--commit', git(self.repo, 'rev-parse', 'HEAD'), '--source', 'plugin',
                                '--slug', 'mrn-fixture', '--kind', 'standard-plugin', '--entrypoint', 'mrn-fixture.php',
                                '--output', str(self.root / f'unsupported-{number}.zip')], check=True, capture_output=True)

    def prepare_runtime(self, temporary):
        root = Path(temporary).resolve()
        public = root / 'public'
        plugin = public / 'wp-content/plugins/mrn-fixture'
        parent = public / 'wp-content/themes/mrn-base-stack'
        plugin.mkdir(parents=True)
        parent.mkdir(parents=True)
        state = root / 'state'
        state.mkdir(mode=0o700)
        for label, receipt in (('old', self.old), ('new', self.new), ('parent', self.parent), ('new-parent', self.new_parent)):
            destination = state / 'releases' / receipt['artifact_sha256']
            with zipfile.ZipFile(self.root / (label + '.zip')) as archive:
                archive.extractall(destination)
        def selection(plugin_receipt):
            return {'schema': 1, 'components': {receipt['slug']: {key: receipt[key] for key in ('artifact_sha256', 'manifest_sha256')} for receipt in (plugin_receipt,)}}
        (state / 'current.json').write_text(json.dumps(selection(self.old)))
        (state / 'next.json').write_text(json.dumps(selection(self.new)))
        return public, plugin, parent, state

    def add_theme_view(self, state, child, pointer_name, parent_receipt, child_state=None):
        data = {'schema': 1, 'parent_artifact_sha256': parent_receipt['artifact_sha256'], 'child_stylesheet': child.name}
        if child_state is not None:
            data['child_release_root'] = str(child_state)
        descriptor = json.dumps(data, sort_keys=True).encode()
        identity = hashlib.sha256(descriptor).hexdigest()
        view = state / 'theme-views' / identity
        (view / 'themes').mkdir(parents=True)
        (view / 'view.json').write_bytes(descriptor)
        (view / 'themes' / child.name).symlink_to(child, target_is_directory=True)
        (view / 'themes/mrn-base-stack').symlink_to(
            state / 'releases' / parent_receipt['artifact_sha256'] / 'component/mrn-base-stack', target_is_directory=True)
        pointer = json.loads((state / pointer_name).read_text())
        pointer['components']['mrn-base-stack'] = {key: parent_receipt[key] for key in ('artifact_sha256', 'manifest_sha256')}
        pointer['theme_view'] = identity
        (state / pointer_name).write_text(json.dumps(pointer))
        return view / 'themes'

    def run_php(self, public, state, body, debug=False):
        # Deliberately small unit doubles. A separate real-core probe is required
        # before a deployment adapter may qualify these mechanics for WordPress.
        prefix = '''<?php
define('ABSPATH', %s . '/');
define('WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins');
define('SCRIPT_DEBUG', %s);
function add_filter($name,$callback,$priority=10,$count=1){$GLOBALS['filters'][$name][]=$callback;}
function add_action($name,$callback,$priority=10,$count=1){add_filter($name,$callback,$priority,$count);}
function apply_filters($name,$value){foreach($GLOBALS['filters'][$name]??[] as $callback){$value=$callback($value);}return $value;}
function wp_normalize_path($path){return $path;}
function wp_parse_url($url){return parse_url($url);}
function content_url($path){return 'https://fixture.invalid/wp-content'.$path;}
function get_theme_root($slug){return ABSPATH.'wp-content/themes';}
function get_theme_root_uri($slug){return content_url('/themes');}
function plugins_url($path='',$file=''){return apply_filters('plugins_url',content_url('/plugins/'.basename(dirname($file))).($path?'/'.$path:''));}
class WP_Error {public function __construct(public $code,public $message){}}
require %s;
MRN_Component_Release_Runtime::boot(%s);
''' % (repr(str(public)), 'true' if debug else 'false', repr(str(TOOLS / 'runtime.php')), repr(str(state)))
        result = subprocess.run(['php'], input=prefix + body, capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr + result.stdout)
        return json.loads(result.stdout)

    def test_request_pins_code_and_assets_when_pointer_changes(self):
        with tempfile.TemporaryDirectory() as temporary:
            public, plugin, parent, state = self.prepare_runtime(temporary)
            result = self.run_php(public, state, '''
$entry = WP_PLUGIN_DIR . '/mrn-fixture/mrn-fixture.php';
$before = MRN_Component_Release_Runtime::entrypoint($entry);
$url = plugins_url('assets/site.css?ver=old', $entry);
rename(%s, %s);
MRN_Component_Release_Runtime::boot(%s);
require MRN_Component_Release_Runtime::entrypoint($entry);
echo json_encode([$GLOBALS['fixture_code'], $before === MRN_Component_Release_Runtime::entrypoint($entry), $url === plugins_url('assets/site.css?ver=new',$entry), $url, array_keys($GLOBALS['wp_plugin_paths'])]);
''' % (repr(str(state / 'next.json')), repr(str(state / 'current.json')), repr(str(state))))
            self.assertEqual(result[:3], ['old', True, True])
            self.assertIn(self.manifest()['generation'] + '/assets/site.min.css', result[3])
            self.assertEqual([str(plugin) + '/'], result[4])
            fresh = self.run_php(public, state, "require MRN_Component_Release_Runtime::entrypoint(WP_PLUGIN_DIR.'/mrn-fixture/mrn-fixture.php'); echo json_encode($GLOBALS['fixture_code']);")
            self.assertEqual('new', fresh)

    def test_debug_and_directory_urls_and_unowned_urls(self):
        with tempfile.TemporaryDirectory() as temporary:
            public, plugin, parent, state = self.prepare_runtime(temporary)
            result = self.run_php(public, state, '''
$entry=WP_PLUGIN_DIR.'/mrn-fixture/mrn-fixture.php';
echo json_encode([plugins_url('assets/site.css?ver=legacy#fragment',$entry),plugins_url('assets',$entry),MRN_Component_Release_Runtime::asset_url('https://outside.invalid/wp-content/plugins/mrn-fixture/assets/site.css'),MRN_Component_Release_Runtime::asset_url('https://fixture.invalid/wp-content/plugins/mrn-fixture-extra/assets/site.css')]);
''', debug=True)
            self.assertTrue(result[0].endswith('/assets/site.css#fragment'))
            self.assertTrue(result[1].endswith(self.manifest()['generation'] + '/assets'))
            self.assertIn('outside.invalid', result[2])
            self.assertIn('mrn-fixture-extra', result[3])

    def test_missing_asset_and_entrypoint_refuse_fallback(self):
        with tempfile.TemporaryDirectory() as temporary:
            public, plugin, parent, state = self.prepare_runtime(temporary)
            result = self.run_php(public, state, '''
$failures=[];
try {plugins_url('assets/missing.css',WP_PLUGIN_DIR.'/mrn-fixture/mrn-fixture.php');}catch(RuntimeException $e){$failures[]='missing';}
try {MRN_Component_Release_Runtime::entrypoint(WP_PLUGIN_DIR.'/other/other.php');}catch(RuntimeException $e){$failures[]='unowned';}
echo json_encode($failures);
''')
            self.assertEqual(['missing', 'unowned'], result)

    def test_ordinary_updates_guarded_and_metadata_keeps_basename(self):
        with tempfile.TemporaryDirectory() as temporary:
            public, plugin, parent, state = self.prepare_runtime(temporary)
            result = self.run_php(public, state, '''
$plugins=['mrn-fixture/mrn-fixture.php'=>['Version'=>'legacy','Name'=>'Fixture'],'other/other.php'=>['Version'=>'2']];
echo json_encode([MRN_Component_Release_Runtime::plugin_metadata($plugins), MRN_Component_Release_Runtime::guard_update(true,['plugin'=>'mrn-fixture/mrn-fixture.php'])->code,MRN_Component_Release_Runtime::guard_update(true,['theme'=>'mrn-base-stack']),MRN_Component_Release_Runtime::guard_update('unchanged',['plugin'=>'other/other.php'])]);
''')
            self.assertEqual({'Version': '1.0.0', 'Name': 'Fixture'}, result[0]['mrn-fixture/mrn-fixture.php'])
            self.assertEqual(['mrn_component_managed_release', True, 'unchanged'], result[1:])

    def test_unqualified_parent_selection_fails_closed_before_any_hooks(self):
        with tempfile.TemporaryDirectory() as temporary:
            public, plugin, parent, state = self.prepare_runtime(temporary)
            pointer = json.loads((state / 'current.json').read_text())
            pointer['components']['mrn-base-stack'] = {key: self.parent[key] for key in ('artifact_sha256', 'manifest_sha256')}
            (state / 'current.json').write_text(json.dumps(pointer))
            program = '''<?php
define('ABSPATH', %s . '/'); define('WP_PLUGIN_DIR', ABSPATH.'wp-content/plugins'); define('WP_CONTENT_DIR', ABSPATH.'wp-content');
function add_filter(){throw new RuntimeException('Hook installed before validation');}
function plugins_url(){return 'https://fixture.invalid/wp-content/plugins/mrn-fixture';}
function content_url($path){return 'https://fixture.invalid/wp-content'.$path;}
require %s;
try { MRN_Component_Release_Runtime::boot(%s); echo 'UNSAFE'; }
catch(RuntimeException $e){echo $e->getMessage();}
''' % (repr(str(public)), repr(str(TOOLS / 'runtime.php')), repr(str(state)))
            result = subprocess.run(['php'], input=program, text=True, capture_output=True, check=True)
            self.assertIn('Parent theme adoption requires', result.stdout)


if __name__ == '__main__':
    unittest.main()

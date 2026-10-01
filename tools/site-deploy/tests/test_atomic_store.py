import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))
from atomic_store import Store, inventory
from deploy import digest
import test_release as release_fixture
TOOLS = release_fixture.TOOLS


class LegacyTemporaryDirectory(tempfile.TemporaryDirectory):
    """Python 3.6 cleanup fails if the context's directory was renamed."""

    def cleanup(self):
        if self._finalizer.detach():
            shutil.rmtree(self.name)


class AtomicStoreContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        release_fixture.ReleaseArtifactContract.setUpClass()

    @classmethod
    def tearDownClass(cls):
        release_fixture.ReleaseArtifactContract.tearDownClass()

    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name).resolve()
        self.content = self.root / 'public/wp-content'
        self.theme = self.content / 'themes/child'
        self.theme.mkdir(parents=True)
        (self.theme / 'functions.php').write_text('<?php /* original phase */\n')
        (self.theme / 'style.css').write_text('body{color:old}\n')
        (self.theme / 'style.min.css').write_text('body{color:old}\n')
        (self.theme / 'template.php').write_text('<?php echo "old";\n')
        self.state = self.root / 'private'
        self.state.mkdir(mode=0o700)
        self.store = Store(self.state, self.theme, self.content, 'child')
        self.before = inventory(self.theme)
        self.bootstrap = TOOLS / 'release-bootstrap.php'

    def tearDown(self):
        self.temp.cleanup()

    def adopt(self):
        return self.store.adopt(digest(self.before), self.bootstrap)

    def stage(self):
        fixture = release_fixture.ReleaseArtifactContract
        return self.store.stage(fixture.archive, fixture.expected, fixture.sha, '.')

    def test_adoption_activation_and_rollback_retain_both_asset_generations(self):
        with self.store.locked_store():
            old = self.adopt()
            self.store.verify_public_snapshot()
            staged = self.stage()
            # Staging must not select new code or change the old public URL.
            self.assertEqual(old, self.store.pointer())
            self.assertEqual('body{color:old}\n', (self.theme / 'style.css').read_text())
            new = self.store.select(staged['release_id'], old)
            self.assertNotEqual(old, new)
            self.assertEqual(new, self.store.pointer())
            restored = self.store.select(old['release_id'], new)
            self.assertEqual(old, restored)
            self.assertTrue((self.content / new['public_path'] / 'style.min.css').is_file())
            self.assertEqual(self.before['style.min.css'], inventory(self.theme)['style.min.css'])
            self.assertEqual(0o755, (self.content / new['public_path']).stat().st_mode & 0o777)

    def test_adoption_preserves_temporary_root_for_legacy_cleanup(self):
        with self.store.locked_store():
            with patch('atomic_store.tempfile.TemporaryDirectory', LegacyTemporaryDirectory):
                old = self.adopt()
            self.assertEqual(old, self.store.pointer())
            self.store.verify_public_snapshot()
            self.assertEqual([], list(self.state.glob('.stage-*')))

    def test_staging_preserves_temporary_root_for_legacy_cleanup(self):
        with self.store.locked_store():
            old = self.adopt()
            with patch('atomic_store.tempfile.TemporaryDirectory', LegacyTemporaryDirectory):
                staged = self.stage()
            self.assertEqual(old, self.store.pointer())
            self.assertEqual([], list(self.state.glob('.stage-*')))
            self.assertEqual(0o700, (self.store.releases / staged['release_id']).stat().st_mode & 0o777)
            new = self.store.select(staged['release_id'], old)
            self.assertEqual(old, self.store.select(old['release_id'], new))
            self.assertTrue((self.content / new['public_path'] / 'style.min.css').is_file())

    def test_interrupted_legacy_snapshot_is_verified_before_adoption_retry(self):
        rename = os.rename

        def interrupted_rename(source, destination):
            rename(source, destination)
            raise OSError('Interrupted after snapshot rename')

        with self.store.locked_store():
            with patch('atomic_store.os.rename', side_effect=interrupted_rename):
                with self.assertRaisesRegex(OSError, 'Interrupted after snapshot rename'):
                    self.adopt()
            self.assertIsNone(self.store.pointer())
            self.assertFalse((self.state / 'adoption.json').exists())
            self.assertEqual(self.before, inventory(self.theme))
            retained = list(self.store.releases.iterdir())
            self.assertEqual(1, len(retained))
            saved = retained[0] / 'theme/style.css'
            original = saved.read_bytes()
            saved.write_text('unexpected retained snapshot drift')
            with self.assertRaisesRegex(ValueError, 'Stored release drift'):
                self.adopt()
            self.assertIsNone(self.store.pointer())
            saved.write_bytes(original)
            with patch('atomic_store.tempfile.TemporaryDirectory', LegacyTemporaryDirectory):
                old = self.adopt()
            self.assertEqual(retained[0].name, old['release_id'])
            self.store.verify_public_snapshot()
            self.assertEqual([], list(self.state.glob('.stage-*')))

    def test_changed_baseline_missing_lock_and_concurrent_deployments_fail(self):
        with self.assertRaisesRegex(ValueError, 'lock'):
            self.adopt()
        with self.store.locked_store():
            other = Store(self.state, self.theme, self.content, 'child')
            with self.assertRaises(BlockingIOError), other.locked_store():
                pass
            with self.assertRaisesRegex(ValueError, 'adoption tree'):
                self.store.adopt('0' * 64, self.bootstrap)
            old = self.adopt()
            staged = self.stage()
            with self.assertRaisesRegex(ValueError, 'changed during'):
                self.store.select(staged['release_id'], None)
            self.assertEqual(old, self.store.pointer())

    def test_asset_collision_and_saved_code_drift_block_activation(self):
        with self.store.locked_store():
            old = self.adopt()
            new = self.stage()
            asset = self.content / new['public_path'] / 'style.min.css'
            asset.write_text('wrong cached bytes')
            with self.assertRaisesRegex(ValueError, 'asset drift'):
                self.store.select(new['release_id'], old)
            self.assertEqual(old, self.store.pointer())
            (self.state / 'releases' / old['release_id'] / 'theme/functions.php').write_text('changed')
            with self.assertRaisesRegex(ValueError, 'Stored release drift'):
                self.store.select(old['release_id'], old)

    def test_public_legacy_changes_block_subsequent_staging(self):
        with self.store.locked_store():
            self.adopt()
            (self.theme / 'style.css').write_text('an old uploader overwrote the file')
            with self.assertRaisesRegex(ValueError, 'public tree'):
                self.stage()

    def test_aliased_or_public_state_is_rejected(self):
        linked = self.root / 'alias'
        linked.symlink_to(self.state, target_is_directory=True)
        for state in [linked, self.content]:
            with self.subTest(state=state), self.assertRaises(ValueError):
                Store(state, self.theme, self.content, 'child')

    def test_php_request_keeps_its_selected_release_after_pointer_switch(self):
        # Real PHP executes the loader and its captured WordPress filters. The
        # first release switches the pointer mid-request; later template lookups
        # must still resolve to the request's original immutable directory.
        functions = '''<?php
$selected = get_stylesheet_directory();
file_put_contents(%s, file_get_contents(%s));
echo json_encode(array('selected' => $selected, 'after' => get_stylesheet_directory(),
'global' => $GLOBALS['wp_stylesheet_path'], 'uri' => get_stylesheet_directory_uri()));
'''
        pointer_path = str(self.state / 'current.json')
        next_path = str(self.state / 'test-next.json')
        (self.theme / 'functions.php').write_text(functions % (repr(pointer_path), repr(next_path)))
        self.before = inventory(self.theme)
        with self.store.locked_store():
            old = self.adopt()
            staged = self.stage()
            next_pointer = {'schema': 1, 'slug': 'child', 'release_id': staged['release_id'], 'public_path': staged['public_path']}
            (self.state / 'test-next.json').write_text(json.dumps(next_pointer))
        harness = self.root / 'request.php'
        harness.write_text('''<?php
define('ABSPATH', __DIR__ . '/public/');
$GLOBALS['filters'] = array();
function get_stylesheet() { return 'child'; }
function add_filter($name, $callback, $priority, $count) { $GLOBALS['filters'][$name][] = $callback; }
function apply_filters($name, $value) { foreach ($GLOBALS['filters'][$name] ?? array() as $callback) { $value = $callback($value, 'child'); } return $value; }
function get_stylesheet_directory() { return apply_filters('stylesheet_directory', 'old-public-path'); }
function get_stylesheet_directory_uri() { return apply_filters('stylesheet_directory_uri', 'https://example.org/wp-content/themes/child'); }
function content_url($path) { return 'https://example.org/wp-content' . $path; }
function wp_set_template_globals() { $GLOBALS['wp_stylesheet_path'] = get_stylesheet_directory(); }
require %s;
''' % repr(str(self.theme / 'functions.php')))
        result = subprocess.run(['php', str(harness)], check=True, capture_output=True, text=True)
        response = json.loads(result.stdout)
        expected = str(self.state / 'releases' / old['release_id'] / 'theme')
        self.assertEqual(expected, response['selected'])
        self.assertEqual(expected, response['after'])
        self.assertEqual(expected, response['global'])
        self.assertEqual(next_pointer, self.store.pointer())

    def test_php_released_assets_map_to_minified_generation_and_unknown_assets_fail(self):
        with self.store.locked_store():
            old = self.adopt()
            new = self.stage()
            self.store.select(new['release_id'], old)
        harness = self.root / 'new-request.php'
        harness.write_text('''<?php
define('ABSPATH', __DIR__ . '/public/');
$GLOBALS['filters'] = array();
function get_stylesheet() { return 'child'; }
function add_filter($name, $callback, $priority, $count = 1) { $GLOBALS['filters'][$name][] = $callback; }
function apply_filters($name, $value) { foreach ($GLOBALS['filters'][$name] ?? array() as $callback) { $value = $callback($value, 'child'); } return $value; }
function get_stylesheet_directory() { return apply_filters('stylesheet_directory', 'old-public-path'); }
function get_stylesheet_directory_uri() { return apply_filters('stylesheet_directory_uri', 'https://example.org/wp-content/themes/child'); }
function content_url($path) { return 'https://example.org/wp-content' . $path; }
function wp_set_template_globals() { $GLOBALS['wp_stylesheet_path'] = get_stylesheet_directory(); }
function wp_parse_url($url, $part) { return parse_url($url, $part); }
function trailingslashit($path) { return rtrim($path, '/') . '/'; }
require %s;
$uri = get_stylesheet_directory_uri();
$unknown = false;
try { mrn_site_release_asset_url($uri . '/missing.js'); } catch (RuntimeException $error) { $unknown = true; }
echo json_encode(array('uri'=>$uri, 'style'=>mrn_site_release_asset_url($uri . '/style.css?ver=old'),
'script'=>mrn_site_release_asset_url($uri . '/app.js?ver=old'), 'unknown'=>$unknown,
'parent'=>mrn_site_release_asset_url('https://example.org/wp-content/themes/parent/style.css?ver=1')));
''' % repr(str(self.theme / 'functions.php')))
        response = json.loads(subprocess.check_output(['php', str(harness)], text=True))
        prefix = 'https://example.org/wp-content/' + new['public_path']
        self.assertEqual(prefix, response['uri'])
        self.assertEqual(prefix + '/style.min.css', response['style'])
        self.assertEqual(prefix + '/app.min.js', response['script'])
        self.assertTrue(response['unknown'])
        self.assertEqual('https://example.org/wp-content/themes/parent/style.css?ver=1', response['parent'])


if __name__ == '__main__':
    unittest.main()

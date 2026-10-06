"""Run the real JSON importer with a PHP WP stub; secrets must travel on stdin."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest


SOURCE = Path(__file__).parents[1] / 'configs/importers/stack-export-importer.sh'
FUNCTION = 'apply_option_json() {' + SOURCE.read_text().split('apply_option_json() {', 1)[1].split('\napply_advanced_editor_tools_json()', 1)[0]


class PrivateSettings(unittest.TestCase):
    def run_import(self, value, storage='option_json', option='mrn_helper_settings'):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary); source = root / 'protected-source.json'
            source.write_text(value); source.chmod(0o600)
            (root / 'wp-stub.php').write_text('''<?php
function get_option($name, $default = []) { return ['site_value' => 'preserved', 'nested' => ['existing' => 1]]; }
function update_option($name, $value, $autoload = null) { echo json_encode(['storage'=>'option','name'=>$name,'value'=>$value]) . "\\n"; }
function update_site_option($name, $value) { echo json_encode(['storage'=>'site_option','name'=>$name,'value'=>$value]) . "\\n"; }
''')
            script = '''set -eu
run_wp() {
  test "$1" = eval
  shift
  printf '%s' "$1" > "$TEST_ROOT/argv-code.txt"
  php -d auto_prepend_file=none -r "require getenv('TEST_ROOT') . '/wp-stub.php'; $1"
}
''' + FUNCTION + '\napply_option_json "$TEST_STORAGE" "$TEST_SOURCE" "$TEST_OPTION"\n'
            run = subprocess.run(['bash'], input=script, text=True, capture_output=True,
                                 env={**os.environ, 'TEST_ROOT': str(root), 'TEST_SOURCE': str(source),
                                      'TEST_STORAGE': storage, 'TEST_OPTION': option})
            argv = (root / 'argv-code.txt').read_text()
            self.assertNotIn(str(source), argv)
            self.assertNotIn('fixture-private-token', argv)
            self.assertEqual(0o600, source.stat().st_mode & 0o777)
            return run

    def test_private_json_uses_stdin_and_preserves_site_owned_settings(self):
        run = self.run_import(json.dumps({'api_token': 'fixture-private-token', 'nested': {'new': 2}}))
        self.assertEqual(0, run.returncode, run.stderr)
        receipt = json.loads(run.stdout.splitlines()[0])
        self.assertEqual({'api_token': 'fixture-private-token', 'site_value': 'preserved',
                          'nested': {'existing': 1, 'new': 2}}, receipt['value'])

    def test_site_option_storage_still_uses_its_correct_api(self):
        run = self.run_import('{"enabled":true}', 'site_option_json', 'network_fixture')
        self.assertEqual(0, run.returncode, run.stderr)
        self.assertEqual('site_option', json.loads(run.stdout.splitlines()[0])['storage'])

    def test_invalid_or_empty_json_stops_before_writing(self):
        for value in ['', 'invalid-json']:
            with self.subTest(value=value):
                run = self.run_import(value)
                self.assertNotEqual(0, run.returncode)
                self.assertEqual('', run.stdout)

"""Exercise the actual installer against a ZIP-path is-installed false positive."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest


SCRIPT = (Path(__file__).parents[1] / 'scripts/site-bootstrap.sh').read_text()
INFER = 'infer_new_plugin_slug() {' + SCRIPT.split('infer_new_plugin_slug() {', 1)[1].split('\nderive_site_theme_slug()', 1)[0]
INSTALL = 'install_plugins() {' + SCRIPT.split('install_plugins() {', 1)[1].split('\nreset_standard_plugins()', 1)[0]


class PackageIdentity(unittest.TestCase):
    def test_archive_path_is_never_accepted_as_an_installed_plugin_slug(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            manifest = root / 'plugins.txt'; manifest.write_text(str(root / 'happyfiles-pro.zip') + '\n')
            script = '''set -euo pipefail
SITE_PROFILE=stack
add_warning() { printf 'WARNING %s\\n' "$*"; }
run_wp() {
  printf '%s\\n' "$*" >> "$TEST_ROOT/calls"
  case "$2" in
    list) if test -f "$TEST_ROOT/installed"; then printf 'happyfiles-pro\\n'; fi; return 0 ;;
    is-installed) return 0 ;; # Reproduce WP-CLI treating an external ZIP as installed.
    install) touch "$TEST_ROOT/installed"; return 0 ;;
    is-active) return 1 ;;
    activate) test "$3" = happyfiles-pro ;;
  esac
}
''' + INFER + INSTALL + '\ninstall_plugins\n'
            result = subprocess.run(['bash'], input=script, text=True, capture_output=True,
                                    env={**os.environ, 'TEST_ROOT': str(root), 'PLUGINS_FILE': str(manifest)})
            self.assertEqual(0, result.returncode, result.stderr)
            calls = (root / 'calls').read_text()
            self.assertIn('plugin install ' + str(root / 'happyfiles-pro.zip'), calls)
            self.assertIn('plugin activate happyfiles-pro', calls)
            self.assertNotIn('is-installed ' + str(root), calls)
            self.assertNotIn('WARNING', result.stdout)

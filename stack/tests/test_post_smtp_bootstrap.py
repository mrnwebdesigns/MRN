"""The real installer must execute the command after Post SMTP activation."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

SCRIPT = (Path(__file__).parents[1] / 'scripts/site-bootstrap.sh').read_text()
HELPER = 'clear_post_smtp_activation_redirect() {' + SCRIPT.split('clear_post_smtp_activation_redirect() {', 1)[1].split('\ninstall_plugins()', 1)[0]
INSTALL = 'install_plugins() {' + SCRIPT.split('install_plugins() {', 1)[1].split('\nreset_standard_plugins()', 1)[0]

class PostSmtpBootstrap(unittest.TestCase):
    def execute(self, fail_clear=False):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            (root / 'plugins.txt').write_text('post-smtp|4.0.2\nclassic-editor|1.7.0\n')
            fixture = r'''set -euo pipefail
SITE_PROFILE=stack
add_warning() { echo "WARNING $*"; }
run_wp() {
 printf '%s\n' "$*" >> "$TEST_ROOT/calls"
 # Match vendor's next-request exit: success status, command never executes.
 if test -f "$TEST_ROOT/redirect" && [[ "$*" != *--skip-plugins=post-smtp* ]]; then
   rm "$TEST_ROOT/redirect"; return 0
 fi
 if test "$1" = eval; then
   [[ "$*" == *'delete_option("post_smtp_activation_redirect");'* ]]
   [[ "$*" == *--skip-plugins=post-smtp* ]]
   test "$FAIL_CLEAR" = 0 || return 1
   rm -f "$TEST_ROOT/redirect"; return 0
 fi
 case "$2" in
  is-installed) test -f "$TEST_ROOT/$3-installed" ;;
  install) touch "$TEST_ROOT/$3-installed" ;;
  is-active) test -f "$TEST_ROOT/$3-active" ;;
  activate) test -f "$TEST_ROOT/$3-installed" || return 1
    touch "$TEST_ROOT/$3-active"
    if test "$3" = post-smtp; then touch "$TEST_ROOT/redirect"; fi ;;
 esac
}
''' + HELPER + INSTALL + '\ninstall_plugins\n'
            result = subprocess.run(['bash'], input=fixture, text=True, capture_output=True,
                env={**os.environ, 'TEST_ROOT': str(root), 'PLUGINS_FILE': str(root / 'plugins.txt'), 'FAIL_CLEAR': str(int(fail_clear))})
            return result, (root / 'calls').read_text(), (root / 'classic-editor-active').exists()

    def test_next_plugin_installs_and_activates_after_vendor_onboarding_flag(self):
        result, calls, active = self.execute()
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertTrue(active)
        self.assertIn('plugin install classic-editor --version=1.7.0', calls)
        self.assertEqual(1, calls.count('--skip-plugins=post-smtp'))

    def test_clear_failure_stops_before_next_plugin(self):
        result, calls, active = self.execute(True)
        self.assertNotEqual(0, result.returncode)
        self.assertFalse(active)
        self.assertNotIn('classic-editor', calls)
        self.assertIn('stopping bootstrap', result.stderr)

    def test_unrelated_plugins_do_not_trigger_option_write(self):
        result = subprocess.run(['bash'], input='run_wp() { return 99; }\n' + HELPER + '\nclear_post_smtp_activation_redirect classic-editor\n', text=True, capture_output=True)
        self.assertEqual(0, result.returncode)

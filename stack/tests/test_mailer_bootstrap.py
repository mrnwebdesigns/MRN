"""A fresh development bootstrap must never enable its configured mailer."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

SCRIPT = (Path(__file__).parents[1] / 'scripts/site-bootstrap.sh').read_text()
INSTALL = 'install_plugins() {' + SCRIPT.split('install_plugins() {', 1)[1].split('\nconfigure_wordfence_defaults()', 1)[0]
ACTIVATE = 'ensure_all_plugins_active() {' + SCRIPT.split('ensure_all_plugins_active() {', 1)[1].split('\napply_licenses()', 1)[0]
# End at the actual next function, regardless of future additions below it.
ACTIVATE = ACTIVATE.split('\n}\n', 1)[0] + '\n}\n'


class MailerBootstrap(unittest.TestCase):
    def test_install_and_activate_remaining_keep_fluentsmtp_inactive(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            (root / 'plugins.txt').write_text('fluent-smtp|2.4.1\nclassic-editor|1.7.0\n')
            shell = r'''set -euo pipefail
SITE_PROFILE=stack
PLUGINS_FILE="$TEST_ROOT/plugins.txt"
add_warning() { echo "WARNING $*"; }
clear_post_smtp_activation_redirect() { :; }
run_wp() {
 case "$2" in
  is-installed) test -f "$TEST_ROOT/$3-installed" ;;
  install) touch "$TEST_ROOT/$3-installed" ;;
  is-active) test -f "$TEST_ROOT/$3-active" ;;
  activate) touch "$TEST_ROOT/$3-active" ;;
  list) printf 'fluent-smtp\nclassic-editor\n' ;;
 esac
}
''' + INSTALL + ACTIVATE + '\ninstall_plugins\nensure_all_plugins_active\n'
            result = subprocess.run(['bash'], input=shell, text=True, capture_output=True,
                                    env={**os.environ, 'TEST_ROOT': str(root)})
            self.assertEqual(0, result.returncode, result.stderr)
            self.assertTrue((root / 'fluent-smtp-installed').exists())
            self.assertFalse((root / 'fluent-smtp-active').exists())
            self.assertTrue((root / 'classic-editor-active').exists())


if __name__ == '__main__':
    unittest.main()

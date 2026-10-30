"""Qualification must retain vendor warnings even if PHP emission is filtered."""
import json
from pathlib import Path
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from common import ReleaseError
from fixture import WordPressFixture


class FixtureDiagnostics(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name) / 'native-fixture'
        self.fixture = WordPressFixture(Path('.'), self.root, {})
        self.fixture.content.mkdir(parents=True)
        self.debug = self.fixture.content / 'debug.log'
        self.trace = self.root / 'translation-trace.log'

    def tearDown(self):
        self.fixture.close()
        self.temporary.cleanup()

    def summary(self):
        return json.loads((self.root / 'php-diagnostics.json').read_text())

    def test_non_emitted_translation_warning_blocks_with_redacted_origin(self):
        frames = [{'file': '/private/owner/wordpress/wp-includes/l10n.php', 'line': 42},
                  {'file': '/private/owner/wordpress/wp-content/plugins/wpforms/src/Admin/Forms/Page.php',
                   'line': 37, 'args': ['never-copy-this-value']}]
        self.trace.write_text((json.dumps(frames) + '\n') * 2)
        with self.assertRaisesRegex(ReleaseError, 'wpforms.*native-fixture/php-diagnostics.json'):
            self.fixture.assert_clean_diagnostics()
        summary = self.summary()
        self.assertEqual(summary['php_log_rows'], 0)
        self.assertEqual(summary['early_translations'], [
            {'plugin': 'wpforms', 'file': 'plugins/wpforms/src/Admin/Forms/Page.php', 'line': 37, 'count': 2}])
        self.assertNotIn('/private/', json.dumps(summary))
        self.assertNotIn('never-copy-this-value', json.dumps(summary))

    def test_php_error_is_retained_after_disposable_runtime_removal(self):
        self.debug.write_text('PHP Fatal error: synthetic regression\n')
        with self.assertRaises(ReleaseError):
            self.fixture.assert_clean_diagnostics()
        self.fixture.close()
        self.assertFalse(self.fixture.public.exists())
        self.assertEqual(self.summary()['status'], 'blocked')
        self.assertEqual((self.root / 'php-diagnostics.log').read_text(),
                         'PHP Fatal error: synthetic regression\n')

    def test_structured_application_log_still_blocks(self):
        self.debug.write_text(json.dumps({'source': 'mrn-db-retention',
                                          'event': 'policy_reconcile'}) + '\n')
        with self.assertRaises(ReleaseError):
            self.fixture.assert_clean_diagnostics()
        self.assertEqual(self.summary()['php_log_rows'], 1)
        self.assertEqual(self.summary()['status'], 'blocked')

    def test_clean_empty_diagnostics_pass(self):
        self.debug.write_text('\n')
        self.trace.write_text('\n')
        self.fixture.assert_clean_diagnostics()
        self.assertEqual(self.summary()['status'], 'pass')

    def test_malformed_trace_fails_closed(self):
        for value in ('{invalid', '{}', '[]', '[null]'):
            self.trace.write_text(value)
            with self.assertRaises(ReleaseError):
                self.fixture.assert_clean_diagnostics()
            self.assertTrue(self.summary()['invalid_translation_trace'])


if __name__ == '__main__':
    unittest.main()

"""Presentation failures must reach the coordinator's actionable failure path."""
from pathlib import Path
import sys
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import Mock, patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from common import ReleaseError
from presentation import qualify_presentations


class PresentationFailures(unittest.TestCase):
    def test_failed_commands_retain_logs_and_raise_the_coordinator_error(self):
        for failure_index, message in ((0, 'Presentation contract failed'),
                                       (5, 'Presentation browser qualification failed')):
            with self.subTest(failure_index=failure_index), tempfile.TemporaryDirectory() as temporary:
                evidence = Path(temporary)
                fixture = Mock(spec=['url', 'php', 'browser_input', 'assert_clean_diagnostics'],
                               url='http://127.0.0.1:12345')
                fixture.php.return_value = {'image': 1}
                fixture.browser_input.return_value = {}
                outcomes = [SimpleNamespace(returncode=0)] * failure_index + [SimpleNamespace(returncode=1)]
                with patch('presentation.RUNTIME_CASES', ()), patch('presentation.subprocess.run', side_effect=outcomes):
                    with self.assertRaisesRegex(ReleaseError, message):
                        qualify_presentations(fixture, evidence, {'node': 'node'}, evidence)
                self.assertTrue(list(evidence.glob('*.log')))
                self.assertFalse((evidence / 'qualification.json').exists())
                fixture.assert_clean_diagnostics.assert_not_called()


if __name__ == '__main__':
    unittest.main()

"""Disposable fixture capacity must stay explicit and bounded."""
from pathlib import Path
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from common import ReleaseError
from fixture import WordPressFixture


class FixtureRuntimeLimits(unittest.TestCase):
    def fixture(self, **kwargs):
        with tempfile.TemporaryDirectory() as temporary:
            return WordPressFixture(Path(temporary) / 'bootstrap', Path(temporary) / 'fixture',
                                    {'php': 'php'}, **kwargs)

    def test_default_execution_capacity_supports_a_large_native_save(self):
        self.assertEqual(self.fixture().max_execution_time, 120)

    def test_execution_capacity_rejects_unbounded_or_too_short_values(self):
        for value in (29, 301, '120'):
            with self.subTest(value=value):
                with self.assertRaisesRegex(ReleaseError, 'execution capacity'):
                    self.fixture(max_execution_time=value)


if __name__ == '__main__':
    unittest.main()

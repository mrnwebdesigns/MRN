#!/usr/bin/env python3
"""Reproduce Python import artifacts tripping the native source cleanliness gate."""
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest


class NativeSourceCleanliness(unittest.TestCase):
    def check_import(self, no_bytecode):
        provider = Path(os.environ['MRN_GATEWAY_PROVIDER_SOURCE']).resolve()
        with tempfile.TemporaryDirectory(prefix='mrn-native-source-clean-') as name:
            root = Path(name).resolve()
            # This entirely local synthetic repository has no inherited hooks,
            # credentials or user configuration and never contacts a remote.
            env = {**os.environ, 'GIT_CONFIG_GLOBAL': os.devnull, 'GIT_CONFIG_NOSYSTEM': '1',
                   'GIT_AUTHOR_NAME': 'Fixture', 'GIT_COMMITTER_NAME': 'Fixture',
                   'GIT_AUTHOR_EMAIL': 'fixture@example.invalid', 'GIT_COMMITTER_EMAIL': 'fixture@example.invalid'}
            for key in ['GIT_DIR', 'GIT_WORK_TREE', 'GIT_INDEX_FILE', 'GIT_COMMON_DIR',
                        'PYTHONPYCACHEPREFIX', 'PYTHONDONTWRITEBYTECODE']:
                env.pop(key, None)
            if no_bytecode:
                env['PYTHONDONTWRITEBYTECODE'] = '1'
            def git(*args):
                return subprocess.check_output(['git', '-C', str(root), *args], env=env, stderr=subprocess.PIPE)
            git('init', '--quiet')
            runtime = root / 'tests/runtime'
            runtime.mkdir(parents=True)
            for file in ['prepare-existing-source-cache.py', 'prepare-source-cache.py']:
                (runtime / file).write_bytes((provider / 'tests/runtime' / file).read_bytes())
            git('add', 'tests/runtime')
            git('commit', '--quiet', '-m', 'Synthetic source baseline')
            self.assertEqual(git('status', '--porcelain'), b'')
            # Run the real pinned early preparation module's import path without
            # its main/Docker work. The nested helper import caused the CI drift.
            subprocess.run([sys.executable, '-c',
                            'import runpy,sys;runpy.run_path(sys.argv[1],run_name="fixture_import")',
                            str(runtime / 'prepare-existing-source-cache.py')],
                           env=env, check=True, capture_output=True, timeout=10)
            status = git('status', '--porcelain')
            if no_bytecode:
                self.assertEqual(status, b'')
                self.assertEqual(list(runtime.glob('__pycache__/*.pyc')), [])
            else:
                self.assertIn(b'__pycache__/', status)
                self.assertTrue(list(runtime.glob('__pycache__/prepare-source-cache.*.pyc')))
            # A real source edit must still trip the same unmodified gate.
            with (runtime / 'prepare-source-cache.py').open('a') as changed:
                changed.write('\n# Deliberate fixture-only source drift.\n')
            self.assertIn(b' M tests/runtime/prepare-source-cache.py', git('status', '--porcelain'))

    def test_default_import_reproduces_dirty_provider(self):
        self.check_import(False)

    def test_no_bytecode_preserves_clean_source_and_real_drift_detection(self):
        self.check_import(True)


if __name__ == '__main__':
    unittest.main()

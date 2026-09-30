import importlib.util
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('source', Path(__file__).parents[1] / 'resolve_source.py')
source = importlib.util.module_from_spec(spec)
spec.loader.exec_module(source)

class SourceSelection(unittest.TestCase):
    def test_feature_branch_is_dev_only(self):
        with patch.object(source.subprocess, 'run') as command, patch.object(source.subprocess, 'check_output', return_value='a'*40):
            self.assertEqual(source.resolve('dev', 'feature/test-css', 'refs/heads/main'), 'a'*40)
            self.assertIn('+refs/heads/feature/test-css:refs/remotes/origin/mrn-deploy-source', command.call_args.args[0])
            for target in ('live', 'both'):
                with self.assertRaises(ValueError): source.resolve(target, 'feature/test-css', 'refs/heads/main')
    def test_untrusted_workflow_or_ref_is_rejected_before_fetch(self):
        with patch.object(source.subprocess, 'run') as command:
            for ref in ('refs/pull/1/merge', '-x', '../main', 'main\nsource_sha=bad', 'refs/heads/main'):
                with self.assertRaises(ValueError): source.resolve('dev', ref, 'refs/heads/main')
            with self.assertRaises(ValueError): source.resolve('dev', 'main', 'refs/heads/feature')
            command.assert_not_called()

import importlib.util
from pathlib import Path
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('kinsta', Path(__file__).parents[1] / 'kinsta.py')
kinsta = importlib.util.module_from_spec(spec)
spec.loader.exec_module(kinsta)


class NativeBackupIdentity(unittest.TestCase):
    def test_wrong_environment_never_resolves_ssh_password(self):
        adapter = kinsta.Kinsta('test-token', 'a'*36, 'b'*36)
        with patch.object(adapter, 'api', return_value={'site': {'environments': []}}) as api:
            with self.assertRaises(ValueError): adapter.connect({'url': 'https://example.org', 'root': '/www/example/public'})
            self.assertEqual(1, api.call_count)

    def test_pending_or_missing_receipt_is_not_success(self):
        adapter = kinsta.Kinsta('test-token', 'a'*36, 'b'*36)
        with patch.object(adapter, 'api', side_effect=[{'operation_id': 'backup:123'}, {'status': 200}, {'environment': {'backups': []}}]):
            with self.assertRaisesRegex(RuntimeError, 'could not be verified'): adapter.backup('pre-release')

    def test_completed_operation_and_matching_manual_backup_are_both_required(self):
        adapter = kinsta.Kinsta('test-token', 'a'*36, 'b'*36)
        responses = [{'operation_id': 'backup:123'}, {'status': 202}, {'status': 200},
                     {'environment': {'backups': [{'id': 42, 'type': 'manual', 'note': 'pre-release'}]}}]
        with patch.object(adapter, 'api', side_effect=responses), patch.object(kinsta.time, 'sleep'):
            receipt = adapter.backup('pre-release')
            self.assertTrue(receipt['valid'])
            self.assertEqual(42, receipt['backup_id'])

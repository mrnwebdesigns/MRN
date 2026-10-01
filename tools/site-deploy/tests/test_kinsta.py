import importlib.util
from pathlib import Path
import unittest
import time
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

    def test_each_native_step_rechecks_transaction_snapshot_and_rejects_stale_or_missing(self):
        adapter = kinsta.Kinsta('test-token', 'a'*36, 'b'*36)
        receipt = dict(valid=True, provider='kinsta', environment_id='b'*36,
                       scope='deployment-transaction', created_at=int(time.time()), backup_id=42, label='deployment')
        data = {'environment': {'backups': [{'id':42,'type':'manual','note':'deployment'}]}}
        with patch.object(adapter, 'api', return_value=data) as api:
            self.assertEqual('activate', adapter.verify_transaction_backup(receipt, 'activate')['operation'])
            adapter.verify_transaction_backup(receipt, 'rollback')
            self.assertEqual(2, api.call_count)
            with self.assertRaises(ValueError):
                adapter.verify_transaction_backup(dict(receipt, created_at=0), 'activate')
            with self.assertRaises(ValueError):
                adapter.verify_transaction_backup(dict(receipt, environment_id='c'*36), 'activate')
        with patch.object(adapter, 'api', return_value={'environment':{'backups':[]}}):
            with self.assertRaises(RuntimeError):
                adapter.verify_transaction_backup(receipt, 'activate')

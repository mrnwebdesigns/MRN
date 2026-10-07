"""Backup failure must precede state provisioning or release writes."""
from pathlib import Path
import tempfile
import unittest
import sys
from unittest.mock import patch, MagicMock

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import parent_host


class HostGates(unittest.TestCase):
    def test_failed_backup_leaves_no_private_or_public_release_state(self):
        with tempfile.TemporaryDirectory(prefix='mrn-parent-host-') as temporary:
            root = Path(temporary)
            state = root / 'not-created'
            plan = {'root': str(root), 'state': str(state), 'backup_nonce': 'a' * 12}
            with patch.object(parent_host, 'preflight', return_value=(root, root, {})), \
                 patch.object(parent_host, 'wp', return_value={'valid': False, 'nonce': 'a' * 12}), \
                 self.assertRaisesRegex(ValueError, 'backup'):
                parent_host.execute(plan)
            self.assertFalse(state.exists())

    def test_retained_inactive_pointer_recovers_to_the_actual_legacy_runtime(self):
        with tempfile.TemporaryDirectory(prefix='mrn-parent-host-') as temporary:
            base = Path(temporary).resolve()
            root, state = base / 'public', base / 'state'
            root.mkdir()
            state.mkdir(mode=0o700)
            old = {'components': {'mrn-base-stack': {'artifact_sha256': 'b' * 64}}}
            plan = {'root': str(root), 'state': str(state), 'url': 'https://fixture.mrndev.io',
                    'environment': 'dev', 'child': 'child', 'child_state': str(state),
                    'backup_nonce': 'a' * 12, 'expected_current': old, 'archive': 'fixture.zip',
                    'artifact_sha256': 'b' * 64, 'source_sha': 'c' * 40, 'source_path': 'parent'}
            store = MagicMock()
            store.pointer.return_value = old
            store.activate.return_value = old
            store.bootstrap.is_file.return_value = False
            store.bootstrap.exists.return_value = True
            with patch.object(parent_host, 'preflight', return_value=(root, root, {})), \
                 patch.object(parent_host, 'wp', return_value={'valid': True, 'nonce': 'a' * 12}), \
                 patch.object(parent_host, 'ParentStore', return_value=store), \
                 patch.object(parent_host, 'other_stack', return_value={}), \
                 patch.object(parent_host, 'public_check', side_effect=[ValueError('forced verification failure'), {'legacy': True}]):
                result = parent_host.execute({**plan, 'other_stack': {}})
            self.assertEqual('failed-recovered', result['status'])
            store.disable.assert_called_once_with(old, {})
            self.assertEqual(1, store.activate.call_count)

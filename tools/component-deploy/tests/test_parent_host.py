"""Backup failure must precede state provisioning or release writes."""
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

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

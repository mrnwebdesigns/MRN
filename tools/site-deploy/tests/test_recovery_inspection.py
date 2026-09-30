import json
from pathlib import Path
import shlex
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))
from deploy import Target
import atomic_runner


class RecoveryInspection(unittest.TestCase):
    def test_recovery_inspection_never_bootstraps_the_broken_theme(self):
        target = Target({'root': '/site', 'key_file': '/key', 'port': '22',
                         'known_hosts_file': '/hosts', 'user': 'owner', 'host': 'host', 'state_dir': '/private'})
        state = {'theme': '/site/wp-content/themes/child', 'git': False}
        with patch.object(target, 'shell', side_effect=['MRN_RESULT=' + json.dumps(state), '']) as shell:
            target.inspect('child', skip_themes=True)
        args = shlex.split(shell.call_args_list[0].args[0])
        self.assertIn('--skip-themes', args)
        self.assertIn('eval', args)

    def test_atomic_runner_uses_recovery_safe_identity_before_backup(self):
        plan = {'environment': 'dev', 'slug': 'child', 'expected_current': {'release_id': 'old'},
                'rollback_to': 'old'}
        config = {'backup_provider': 'updraft', 'url': 'https://site.mrndev.io', 'ready': True}
        with patch.object(atomic_runner, 'Target') as constructor:
            constructor.return_value.inspect.side_effect = ValueError('stop before backup')
            with self.assertRaisesRegex(ValueError, 'stop before backup'):
                atomic_runner.run(plan, config)
            constructor.return_value.inspect.assert_called_once_with('child', skip_themes=True)

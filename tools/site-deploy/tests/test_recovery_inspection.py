import json
from pathlib import Path
import shlex
import sys
import unittest
from unittest.mock import patch
import subprocess

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

    def test_native_controller_keeps_credentials_out_of_arguments_and_returns_failed_receipt(self):
        target = Target({'root': '/site', 'key_file': '/key', 'port': '22',
                         'known_hosts_file': '/hosts', 'user': 'owner', 'host': 'host', 'state_dir': '/private'})
        target.password = 'private-password'
        payload = {'plan': {'repository': 'org/site'}, 'native': {'token': 'private-token'}}
        failed = subprocess.CompletedProcess([], 1, stdout='MRN_RESULT={"status":"failed"}', stderr='')
        with patch('deploy.subprocess.run', return_value=failed) as process:
            self.assertIs(failed, target.controller('/private/job/host_controller.py', payload))
        args, kwargs = process.call_args
        self.assertEqual(['sshpass', '-e', 'ssh'], args[0][:3])
        self.assertNotIn('private-token', ' '.join(args[0]))
        self.assertNotIn('private-password', ' '.join(args[0]))
        self.assertEqual(payload, json.loads(kwargs['input']))
        self.assertEqual('private-password', kwargs['env']['SSHPASS'])

    def test_native_backup_exception_is_disabled_before_connection_or_writes(self):
        with patch.object(atomic_runner, 'Target') as target:
            with self.assertRaisesRegex(ValueError, 'explicit owner approval'):
                atomic_runner.run({'environment':'live'}, {'host_provider':'kinsta','backup_provider':'kinsta'})
            target.assert_not_called()

    def test_old_run_is_rejected_before_tool_transfer_backup(self):
        from test_automatic_dev import order
        plan = {'environment':'dev', 'slug':'child', 'expected_current':{'release_id':'current'},
                'archive':'/release.tar', 'artifact_sha256':'a'*64, 'source_sha':'a'*40,
                'source_path':'.', 'deployment_order':order(2)}
        config = {'backup_provider':'updraft', 'url':'https://site.mrndev.io', 'ready':True}
        with patch.object(atomic_runner, 'Target') as target, patch.object(atomic_runner, 'verify'), \
                patch.object(atomic_runner, 'verify_identity'), patch.object(atomic_runner, 'verify_state_privacy'), \
                patch.object(atomic_runner, 'transfer_backup') as backup:
            target.return_value.inspect.return_value = {'state':plan['expected_current'], 'git':False,
                                                       'deployment_order':order(3)}
            with self.assertRaisesRegex(ValueError, 'Stale'):
                atomic_runner.run(plan, config)
            backup.assert_not_called()

import argparse
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

SPEC = importlib.util.spec_from_file_location('site_deploy', Path(__file__).parents[1] / 'deploy.py')
deploy = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(deploy)


class DeploymentSafety(unittest.TestCase):
    def setUp(self):
        self.files = {'style.css': 'a' * 64}
        self.config = {
            'url': 'https://example.org', 'host': 'host.example.org', 'root': '/home/site/public',
            'template': 'parent', 'state_dir': '/home/site/private', 'transport': 'rsync',
            'baseline': deploy.digest(self.files), 'ready': True,
        }
        self.before = {
            'home': 'https://example.org', 'stylesheet': 'child', 'template': 'parent',
            'theme': '/home/site/public/wp-content/themes/child', 'files': self.files,
            'state': None, 'state_ready': True, 'writable': True, 'backup_ready': True, 'git': False,
        }

    def test_scope_excludes_hidden_and_nonruntime_paths(self):
        for path in ['.git/config', 'docs/launch.md', 'assets/.secrets', 'scripts/migrate.php', 'vendor/a.php']:
            self.assertFalse(deploy.allowed(path), path)
        self.assertTrue(deploy.allowed('assets/css/theme.css'))

    def test_wrong_site_parent_transport_and_unready_backup_are_rejected(self):
        for field, value in [('home', 'https://other.org'), ('template', 'other'), ('git', True),
                             ('state_ready', False), ('backup_ready', False), ('writable', False)]:
            with self.subTest(field=field), self.assertRaises(ValueError):
                deploy.verify_identity(self.config, {**self.before, field: value}, 'child')

    def test_unreviewed_drift_and_cross_target_receipt_rejected(self):
        deploy.require_baseline(self.config, self.before, 'org/site', 'live')
        with self.assertRaises(ValueError):
            deploy.require_baseline(self.config, {**self.before, 'files': {'style.css': 'b' * 64}}, 'org/site', 'live')
        with self.assertRaises(ValueError):
            deploy.require_baseline(self.config, {**self.before, 'state': {'tree': self.config['baseline']}}, 'org/site', 'live')

    def exercise(self, mode, backup=None, ready=True, after=None):
        operations = []
        before = self.before
        config = {**self.config, 'ready': ready}

        class FakeTarget:
            def __init__(self, config):
                self.count = 0
                self.login = 'site@host'
                self.options = []

            def inspect(self, slug):
                operations.append('inspect')
                self.count += 1
                return after if after is not None and self.count >= 2 else before

            def php(self, code):
                operations.append('backup')
                if backup is False:
                    return {'valid': False}
                label = code.split("MRN_BACKUP_LABEL=", 1)[1].split("'", 1)[0]
                return {'valid': True, 'label': label}

            def shell(self, script):
                operations.append('write')

        with tempfile.TemporaryDirectory() as temp:
            args = argparse.Namespace(mode=mode, sha='a' * 40, source='.', slug='child', environment='live', receipt=temp + '/receipt.json')
            with patch.dict(os.environ, {'GITHUB_REPOSITORY': 'org/site'}), \
                 patch.object(deploy, 'Target', FakeTarget), \
                 patch.object(deploy, 'export_payload', return_value=self.files), \
                 patch.object(deploy, 'run', side_effect=lambda *a, **kw: operations.append('transfer')), \
                 patch.object(deploy, 'http_check', side_effect=lambda *a, **kw: operations.append('http')):
                try:
                    deploy.deploy(args, config)
                except (ValueError, RuntimeError) as error:
                    return operations, error, json.loads(Path(args.receipt).read_text())
                return operations, None, json.loads(Path(args.receipt).read_text())

    def test_preflight_never_starts_backup_or_writes(self):
        operations, error, receipt = self.exercise('preflight')
        self.assertIsNone(error)
        self.assertEqual(['inspect'], operations)
        self.assertEqual('preflight', receipt['status'])

    def test_disabled_or_failed_backup_never_writes(self):
        for values in [{'ready': False}, {'backup': False}]:
            operations, error, receipt = self.exercise('deploy', **values)
            self.assertIsNotNone(error)
            self.assertNotIn('write', operations)
            self.assertNotIn('transfer', operations)

    def test_drift_during_backup_stops_transfer(self):
        operations, error, _ = self.exercise('deploy', after={**self.before, 'files': {'style.css': 'c' * 64}})
        self.assertIsNotNone(error)
        self.assertIn('backup', operations)
        self.assertNotIn('write', operations)

    def test_success_preserves_backup_write_and_verification_order(self):
        operations, error, receipt = self.exercise('deploy')
        self.assertIsNone(error)
        self.assertEqual(['inspect', 'backup', 'inspect', 'write', 'transfer', 'write', 'inspect', 'http', 'http', 'write'], operations)
        self.assertEqual('verified', receipt['status'])

    def test_unsafe_configuration_is_rejected(self):
        values = {
            'HOST': 'host.example.org', 'PORT': '22', 'USER': 'site', 'ROOT': '/home/site/public',
            'URL': 'https://example.org', 'TEMPLATE': 'parent', 'STATE_DIR': '/home/site/private',
            'TRANSPORT': 'rsync', 'KEY_FILE': '/tmp/key', 'KNOWN_HOSTS_FILE': '/tmp/hosts',
        }
        for field, value in [('HOST', '-oProxyCommand=bad'), ('ROOT', '/'), ('PORT', '0'),
                             ('STATE_DIR', '/home/site/public/backup'), ('ROOT', '/home/../etc')]:
            with self.subTest(field=field), self.assertRaises(ValueError):
                deploy.config({'DEPLOY_' + k: v for k, v in {**values, field: value}.items()})


if __name__ == '__main__':
    unittest.main()

"""Exercise the workflow's provider selection without contacting an SSH host."""
import importlib.util
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import Mock, patch

import yaml

TOOLS = Path(__file__).parents[1]
sys.path.insert(0, str(TOOLS))
spec = importlib.util.spec_from_file_location('native_transport_deploy', TOOLS / 'deploy.py')
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)


class NativeTransportWorkflow(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        workflow = yaml.safe_load((TOOLS.parents[1] / '.github/workflows/site-deploy-target.yml').read_text())
        cls.steps = workflow['jobs']['deploy']['steps']
        cls.configure = next(step['run'] for step in cls.steps if step.get('name') == 'Configure pinned SSH identity')

    def configure_transport(self, root, provider='kinsta', approval='', known_hosts='host ssh-ed25519 fixture'):
        env = dict(os.environ, RUNNER_TEMP=str(root), GITHUB_ENV=str(root / 'environment'),
                   SSH_KEY='', KNOWN_HOSTS=known_hosts, DEPLOY_BACKUP_PROVIDER=provider,
                   DEPLOY_KINSTA_MANUAL_BACKUP_APPROVAL=approval, DEPLOY_HOST_PROVIDER='kinsta')
        return subprocess.run(['bash', '-c', self.configure], env=env, capture_output=True, text=True)

    def test_native_api_mode_is_inherited_by_activation_and_every_rollback(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            result = self.configure_transport(root)
            self.assertEqual(0, result.returncode, result.stderr)
            self.assertEqual('DEPLOY_KEY_FILE=native-kinsta\n', (root / 'environment').read_text())
        consumers = [step for step in self.steps if any(name in step.get('run', '')
                     for name in ('/deploy.py', '/atomic_runner.py'))]
        self.assertEqual(4, len(consumers))
        for step in consumers:
            self.assertNotIn('DEPLOY_KEY_FILE', step.get('env', {}), step['name'])

    def test_key_managed_routes_still_require_their_explicit_private_key(self):
        for provider, approval in [('updraft', ''), ('kinsta', 'owner-managed')]:
            with self.subTest(provider=provider, approval=approval), tempfile.TemporaryDirectory() as temporary:
                root = Path(temporary)
                result = self.configure_transport(root, provider=provider, approval=approval)
                self.assertNotEqual(0, result.returncode)
                self.assertIn('Missing deployment SSH private-key secret', result.stdout)
                self.assertFalse((root / 'environment').exists())

    def test_native_api_mode_still_requires_pinned_host_verification(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            result = self.configure_transport(root, known_hosts='')
            self.assertNotEqual(0, result.returncode)
            self.assertIn('Missing pinned SSH known-hosts', result.stdout)
            self.assertFalse((root / 'environment').exists())

    def test_native_marker_selects_the_configured_provider_transport(self):
        config = dict(backup_provider='kinsta', key_file='native-kinsta', port='22',
                      known_hosts_file='/private/known_hosts', user='owner', host='host')
        adapter = Mock()
        adapter.connect.return_value = 'fixture-password'
        with patch('kinsta.adapter', return_value=adapter):
            target = deploy.Target(config)
        adapter.connect.assert_called_once_with(config)
        adapter.identity.assert_not_called()
        self.assertEqual('fixture-password', target.password)

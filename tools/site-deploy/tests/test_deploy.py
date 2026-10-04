import argparse
import importlib.util
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))

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

    def exercise(self, mode, backup=None, ready=True, after=None, artifact=True, artifact_error=None, github_env=None):
        operations = []
        before = self.before
        config = {**self.config, 'ready': ready}

        class FakeTarget:
            def __init__(self, config):
                self.count = 0
                self.login = 'site@host'
                self.options = []

            def inspect(self, slug, skip_themes=False):
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
            args.artifact = temp + '/release.tar' if artifact else None
            args.artifact_sha256 = 'b' * 64 if artifact else None
            # These fixtures exercise explicit local/operator calls. Never inherit
            # the CI runner's PR trigger; trigger cases opt into their own context.
            with patch.dict(os.environ, {'GITHUB_REPOSITORY': 'org/site', 'GITHUB_ACTIONS': 'false', **(github_env or {})}), \
                 patch('verify_release.verify', return_value={'theme_files': self.files, 'artifact_sha256': 'b' * 64,
                       'runtime_qualified': False}, side_effect=artifact_error), \
                 patch.object(deploy, 'Target', FakeTarget), \
                 patch.object(deploy, 'export_payload', return_value=self.files) as exporter, \
                 patch.object(deploy, 'run', side_effect=lambda *a, **kw: operations.append('transfer')), \
                 patch.object(deploy, 'http_check', side_effect=lambda *a, **kw: operations.append('http')):
                try:
                    deploy.deploy(args, config)
                except (ValueError, RuntimeError) as error:
                    receipt = json.loads(Path(args.receipt).read_text()) if Path(args.receipt).exists() else None
                    return operations, error, receipt
                finally:
                    if artifact:
                        exporter.assert_not_called()
                return operations, None, json.loads(Path(args.receipt).read_text())

    def test_pull_request_context_is_rejected_before_site_access(self):
        operations, error, receipt = self.exercise('deploy', github_env={
            'GITHUB_ACTIONS': 'true', 'GITHUB_EVENT_NAME': 'pull_request',
            'GITHUB_REF': 'refs/pull/123/merge'})
        self.assertEqual([], operations)
        self.assertIn('Untrusted deployment trigger', str(error))
        self.assertIsNone(receipt)

    def test_preflight_never_starts_backup_or_writes(self):
        operations, error, receipt = self.exercise('preflight')
        self.assertIsNone(error)
        self.assertEqual(['inspect'], operations)
        self.assertEqual('preflight', receipt['status'])
        self.assertEqual('verified-build-artifact', receipt['payload_kind'])
        self.assertFalse(receipt['artifact']['runtime_qualified'])

    def test_qualified_native_routine_deploy_reaches_guarded_atomic_controller(self):
        self.config.update(host_provider='kinsta', backup_provider='kinsta', native_transaction_backup_approved=True)
        self.before['state'] = {'schema':1, 'release_id':'c'*64, 'public_path':'mrn-assets/child/'+'d'*64}
        result = {'status':'public-verified','current':self.before['state'],'runtime_qa_required':True}
        with patch.dict(os.environ, {'DEPLOY_VERIFY_PAGES':'["https://example.org/"]'}), \
             patch('atomic_runner.run', return_value=result) as activate:
            operations, error, receipt = self.exercise('deploy')
        self.assertIsNone(error)
        self.assertEqual('public-verified', receipt['status'])
        plan, config = activate.call_args.args
        self.assertEqual(self.before['state'], plan['expected_current'])
        self.assertEqual('kinsta', config['backup_provider'])
        self.assertTrue(config['native_transaction_backup_approved'])

    def test_bad_artifact_is_rejected_before_site_access(self):
        operations, error, receipt = self.exercise('preflight', artifact_error=ValueError('wrong archive'))
        self.assertEqual([], operations)
        self.assertIn('wrong archive', str(error))
        self.assertIsNone(receipt)

    def test_source_only_adoption_is_read_only_and_cannot_be_deployed(self):
        operations, error, receipt = self.exercise('preflight', artifact=False)
        self.assertIsNone(error)
        self.assertEqual('source-adoption-inventory', receipt['payload_kind'])
        operations, error, receipt = self.exercise('deploy', artifact=False)
        self.assertEqual([], operations)
        self.assertIn('verified build artifact', str(error))

    def test_artifact_preflight_does_not_export_or_rebuild_source(self):
        operations, error, receipt = self.exercise('preflight')
        self.assertIsNone(error)
        self.assertEqual('b' * 64, receipt['artifact']['artifact_sha256'])

    def test_disabled_or_failed_backup_never_writes(self):
        for values in [{'ready': False}, {'backup': False}]:
            operations, error, receipt = self.exercise('deploy', **values)
            self.assertIsNotNone(error)
            self.assertNotIn('write', operations)
            self.assertNotIn('transfer', operations)

    def test_unqualified_transport_cannot_write_even_when_ready_is_enabled(self):
        operations, error, receipt = self.exercise('deploy', ready=True)
        self.assertIsNotNone(error)
        self.assertIn('Runtime writes disabled', str(error))
        self.assertEqual(['inspect'], operations)
        self.assertEqual('preflight', receipt['status'])

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

    def test_git_transport_accepts_only_matching_private_source_layout(self):
        source = 'public/wp-content/themes/child'
        state = {**self.before, 'git_root': '/home/site/deploy-repo', 'theme': '/home/site/deploy-repo/' + source}
        self.assertEqual('/home/site/deploy-repo', deploy.git_destination(self.config, state, source))
        with self.assertRaises(ValueError):
            deploy.git_destination(self.config, state, '.')
        with self.assertRaises(ValueError):
            deploy.git_destination(self.config, {**state, 'git_root': self.config['root'], 'theme': self.config['root']}, '.')
        aliased = {
            **state, 'wp_root': '/chroot/home/site/public',
            'git_root': '/chroot/home/site/public/wp-content/themes/child',
            'theme': '/chroot/home/site/public/wp-content/themes/child',
            'theme_url': 'https://example.org/wp-content/themes/child',
        }
        self.assertEqual(aliased['git_root'], deploy.git_destination(self.config, aliased, '.'))
        def denied(url, **kwargs):
            raise deploy.urllib.error.HTTPError(url.full_url, 403, 'Forbidden', {}, None)
        with patch.object(deploy, 'http_check'), patch.object(deploy.urllib.request, 'urlopen', side_effect=denied) as request:
            deploy.verify_git_privacy(self.config, aliased)
            self.assertEqual(2, request.call_count)
        with patch.object(deploy, 'http_check'), patch.object(deploy.urllib.request, 'urlopen'), self.assertRaises(ValueError):
            deploy.verify_git_privacy(self.config, aliased)

    def test_wpengine_private_storage_requires_provider_identity_and_blocked_existing_file(self):
        values = {
            'HOST': 'example.ssh.wpengine.net', 'PORT': '22', 'USER': 'example', 'ROOT': '/sites/example',
            'URL': 'https://example.org', 'TEMPLATE': 'parent',
            'STATE_DIR': '/sites/example/_wpeprivate/mrn-site-deploy/live',
            'TRANSPORT': 'rsync', 'KEY_FILE': '/tmp/key', 'KNOWN_HOSTS_FILE': '/tmp/hosts',
        }
        c = deploy.config({'DEPLOY_' + k: v for k, v in values.items()})
        for field, value in [('HOST', 'other.example.org'), ('USER', 'other'),
                             ('STATE_DIR', '/sites/example/wp-content/private'),
                             ('STATE_DIR', '/sites/example/_wpeprivate/mrn-site-deploy/../bad')]:
            with self.subTest(field=field), self.assertRaises(ValueError):
                deploy.config({'DEPLOY_' + k: v for k, v in {**values, field: value}.items()})
        with self.assertRaises(ValueError):
            deploy.verify_state_privacy(c, {'state_protection_probe_exists': False})
        def denied(url, **kwargs):
            raise deploy.urllib.error.HTTPError(url.full_url, 403, 'Forbidden', {}, None)
        with patch.object(deploy, 'http_check'), patch.object(deploy.urllib.request, 'urlopen', side_effect=denied) as request:
            deploy.verify_state_privacy(c, {'state_protection_probe_exists': True})
            request.assert_called_once()
            self.assertEqual('https://example.org/_wpeprivate/config.json', request.call_args.args[0].full_url)
        with patch.object(deploy, 'http_check'), patch.object(deploy.urllib.request, 'urlopen'), self.assertRaises(ValueError):
            deploy.verify_state_privacy(c, {'state_protection_probe_exists': True})

    def test_ssh_diagnostics_never_echo_sensitive_stderr(self):
        from types import SimpleNamespace
        error = SimpleNamespace(returncode=255, stdout=b'', stderr=b'Load key: invalid format. PRIVATE-DIAGNOSTIC')
        with patch.object(deploy.subprocess, 'run', return_value=error):
            with self.assertRaisesRegex(RuntimeError, '^SSH preflight failed: deployment private key has an invalid format$'):
                deploy.run(['ssh', 'sensitive-argument'])

    def test_blocked_homepage_cannot_prove_private_path_protection(self):
        c = {**self.config, 'host': 'site.ssh.wpengine.net', 'user': 'site',
             'root': '/sites/site', 'state_dir': '/sites/site/_wpeprivate/mrn-site-deploy/live'}
        with patch.object(deploy, 'http_check', side_effect=RuntimeError('challenge')), patch.object(deploy, 'require_http_denied') as denied:
            with self.assertRaisesRegex(RuntimeError, 'challenge'):
                deploy.verify_state_privacy(c, {'state_protection_probe_exists': True})
            denied.assert_not_called()


if __name__ == '__main__':
    unittest.main()

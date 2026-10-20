import argparse
import os
from pathlib import Path
import sys
import time
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))
import deploy
import kinsta


class DosterManualBackup(unittest.TestCase):
    def adapter(self, **changes):
        args = dict(token='', site_id=kinsta.DOSTER_SITE, environment_id=kinsta.DOSTER_DEV,
                    manual_approval=kinsta.DOSTER_MANUAL_APPROVAL, repository=kinsta.DOSTER_REPOSITORY)
        return kinsta.adapter(**dict(args, **changes))

    def test_default_still_requires_native_api_credentials(self):
        with self.assertRaises(ValueError):
            kinsta.adapter('', kinsta.DOSTER_SITE, kinsta.DOSTER_DEV)
        self.assertIsInstance(kinsta.adapter('token', kinsta.DOSTER_SITE, kinsta.DOSTER_DEV), kinsta.Kinsta)
        for change in (dict(site_id='a'*36), dict(environment_id='b'*36),
                       dict(repository='mrnwebdesigns/another-site'), dict(manual_approval='true')):
            with self.subTest(change=change), self.assertRaises(ValueError):
                self.adapter(**change)

    def test_every_target_field_is_pinned_and_no_password_fallback_exists(self):
        adapter = self.adapter()
        adapter.identity(kinsta.DOSTER_TARGET)
        for key in kinsta.DOSTER_TARGET:
            with self.subTest(key=key), self.assertRaises(ValueError):
                adapter.identity(dict(kinsta.DOSTER_TARGET, **{key: 'different'}))
            with self.assertRaises(ValueError):
                adapter.backup('mrn-tool-transfer-123')
        for env in ('live', 'staging', 'production'):
            with self.assertRaises(ValueError):
                adapter.identity(dict(kinsta.DOSTER_TARGET, environment=env))
        with self.assertRaisesRegex(ValueError, 'pinned SSH key'):
            adapter.connect(kinsta.DOSTER_TARGET)

    def test_receipt_reports_exception_without_claiming_a_new_snapshot(self):
        adapter = self.adapter()
        with patch('urllib.request.urlopen', side_effect=AssertionError('No API allowed')):
            adapter.identity(kinsta.DOSTER_TARGET)
            receipt = adapter.backup('mrn-tool-transfer-123')
            self.assertFalse(receipt['automatic_backup_created'])
            self.assertEqual('owner-manual-exception', receipt['provider'])
            for operation in ('adopt', 'activate', 'rollback', 'reactivate'):
                self.assertEqual(operation, adapter.verify_transaction_backup(receipt, operation)['operation'])
            for change in (dict(provider='kinsta'), dict(environment_id='a'*36), dict(approval=''),
                           dict(automatic_backup_created=True), dict(label=''), dict(approval_recorded_at=0)):
                with self.subTest(change=change), self.assertRaises(ValueError):
                    adapter.verify_transaction_backup(dict(receipt, **change), 'activate')

    def test_target_needs_real_key_and_receives_no_api_password(self):
        config = dict(kinsta.DOSTER_TARGET, backup_provider='kinsta', key_file='/private/key',
                      known_hosts_file='/private/known_hosts', manual_backup_approval=kinsta.DOSTER_MANUAL_APPROVAL)
        env = dict(DEPLOY_KINSTA_SITE_ID=kinsta.DOSTER_SITE, DEPLOY_KINSTA_ENVIRONMENT_ID=kinsta.DOSTER_DEV,
                   GITHUB_REPOSITORY=kinsta.DOSTER_REPOSITORY)
        with patch.dict(os.environ, env, clear=True), patch('urllib.request.urlopen', side_effect=AssertionError('No API allowed')):
            target = deploy.Target(config)
            self.assertIsNone(target.password)
            self.assertIn('StrictHostKeyChecking=yes', target.options)
            with self.assertRaisesRegex(ValueError, 'pinned SSH key'):
                deploy.Target(dict(config, key_file='native-kinsta'))

    def test_first_adoption_keeps_exact_main_qa_and_readiness_gates(self):
        sha = 'a'*40
        c = dict(kinsta.DOSTER_TARGET, host_provider='kinsta', backup_provider='kinsta', ready=False,
                 enrollment_sha=sha, baseline='b'*64, manual_backup_approval=kinsta.DOSTER_MANUAL_APPROVAL)
        env = dict(QUALIFY_DEV='true', GITHUB_ACTIONS='true', GITHUB_EVENT_NAME='workflow_dispatch',
                   GITHUB_REF='refs/heads/main', SOURCE_BRANCH='main', MRN_SOURCE_QA_SHA=sha,
                   DEPLOY_KINSTA_SITE_ID=kinsta.DOSTER_SITE, DEPLOY_KINSTA_ENVIRONMENT_ID=kinsta.DOSTER_DEV,
                   GITHUB_REPOSITORY=kinsta.DOSTER_REPOSITORY)
        args = argparse.Namespace(sha=sha, mode='deploy', environment='dev')
        with patch('release_request.api', return_value={'object': {'sha':sha}}):
            self.assertTrue(deploy.qualification_request(args, c, env))
            for change in (dict(GITHUB_EVENT_NAME='push'), dict(MRN_SOURCE_QA_SHA='b'*40),
                           dict(SOURCE_BRANCH='feature'), dict(DEPLOY_KINSTA_ENVIRONMENT_ID='c'*36)):
                with self.subTest(change=change), self.assertRaises(ValueError):
                    deploy.qualification_request(args, c, dict(env, **change))
            with self.assertRaises(ValueError):
                deploy.qualification_request(args, dict(c, ready=True), env)
        with patch('release_request.api', return_value={'object': {'sha':'c'*40}}):
            with self.assertRaises(ValueError):
                deploy.qualification_request(args, c, env)

    def test_workflow_requires_pinned_noninteractive_key_for_exception(self):
        workflow = (Path(__file__).parents[3] / '.github/workflows/site-deploy-target.yml').read_text()
        self.assertIn('DEPLOY_KINSTA_MANUAL_BACKUP_APPROVAL: ${{ vars.DEPLOY_KINSTA_MANUAL_BACKUP_APPROVAL }}', workflow)
        self.assertIn('|| -n "$DEPLOY_KINSTA_MANUAL_BACKUP_APPROVAL"', workflow)
        self.assertIn("vars.DEPLOY_KINSTA_MANUAL_BACKUP_APPROVAL == ''", workflow)

"""A branch signal is notification only; trusted main QA owns every release."""
import copy
import io
import json
from pathlib import Path
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))
import deployment_request as request

SHA = 'a' * 40


class SourcePushContract(unittest.TestCase):
    def setUp(self):
        self.env = dict(GITHUB_ACTIONS='true', GITHUB_EVENT_NAME='workflow_run',
                        GITHUB_REF='refs/heads/main', GITHUB_SHA='b'*40, GITHUB_REPOSITORY='mrn/site',
                        GITHUB_EVENT_PATH='event.json', AUTO_DEV_BRANCH='phase-2',
                        AUTO_DEV_AFTER='2026-10-02T12:00:00Z', TARGET='dev', MODE='deploy',
                        SOURCE_BRANCH='phase-2', MRN_SOURCE_QA_SHA=SHA,
                        GITHUB_WORKFLOW_REF='mrn/site/.github/workflows/site-deploy.yml@refs/heads/main',
                        GITHUB_RUN_ID='102', GITHUB_RUN_NUMBER='2', GITHUB_RUN_ATTEMPT='1')
        repo = {'full_name': 'mrn/site'}
        self.payload = dict(action='completed', repository=repo, workflow_run=dict(
            event='push', status='completed', conclusion='success', path='.github/workflows/site-push.yml',
            name='MRN source push', repository=repo, head_repository=repo, head_branch='phase-2',
            head_sha=SHA, created_at='2026-10-02T12:01:00Z'))

    def payload_file(self):
        return patch('builtins.open', return_value=io.StringIO(json.dumps(self.payload)))

    def test_exact_source_is_signal_commit_not_default_branch_sha(self):
        self.assertEqual(SHA, request.source_push(self.env, self.payload))
        with self.payload_file(), patch.object(request, 'resolve', return_value=SHA) as resolve:
            self.assertEqual((SHA, 'phase-2'), request.select_request(self.env))
            resolve.assert_called_once_with('dev', 'phase-2', 'refs/heads/main')

    def test_pr_fork_wrong_workflow_or_default_ref_cannot_deploy(self):
        for change in ({'event': 'pull_request'}, {'event': 'workflow_dispatch'},
                       {'head_repository': {'full_name': 'fork/site'}}, {'repository': {'full_name':'fork/site'}},
                       {'path': '.github/workflows/other.yml'}, {'name': 'other'}, {'status':'in_progress'},
                       {'head_sha':'0'*40}, {'head_sha':'invalid'}):
            payload = copy.deepcopy(self.payload)
            payload['workflow_run'].update(change)
            with self.subTest(change=change), self.assertRaises(ValueError):
                request.source_push(self.env, payload)
        for change in ({'GITHUB_REF':'refs/heads/phase-2'}, {'GITHUB_EVENT_NAME':'pull_request'}):
            with self.assertRaises(ValueError): request.source_push({**self.env, **change}, self.payload)

    def test_failures_unconfigured_branches_and_unarmed_installations_noop(self):
        for change in ({'conclusion':'failure'}, {'conclusion':'cancelled'}, {'conclusion':'skipped'},
                       {'head_branch':'main'}, {'head_branch':'feature/unapproved'}):
            payload = copy.deepcopy(self.payload)
            payload['workflow_run'].update(change)
            self.assertIsNone(request.source_push(self.env, payload))
        for cutoff in ('', '2026-10-02T12:01:00Z', '2026-10-02T12:02:00Z'):
            self.assertIsNone(request.source_push({**self.env, 'AUTO_DEV_AFTER':cutoff}, self.payload))
        for cutoff in ('today', '2026-10-02T12:00:00'):
            with self.assertRaises(ValueError): request.source_push({**self.env, 'AUTO_DEV_AFTER':cutoff}, self.payload)

    def test_current_branch_check_skips_superseded_signal_without_substitution(self):
        with self.payload_file(), patch.object(request, 'resolve', return_value='c'*40):
            self.assertEqual((None, 'phase-2'), request.select_request(self.env))
        with self.payload_file(), patch.object(request, 'resolve') as resolve:
            self.assertEqual((None, 'phase-2'), request.select_request({**self.env, 'AUTO_DEV_AFTER':''}))
            resolve.assert_not_called()

    def test_controller_requires_exact_source_qa_and_dev_only(self):
        with self.payload_file():
            order = request.github_order(self.env, SHA, 'dev', 'deploy')
        self.assertEqual('phase-2', order['source_branch'])
        self.assertEqual('workflow_run', order['event'])
        for change in ({'MRN_SOURCE_QA_SHA':''}, {'MRN_SOURCE_QA_SHA':'b'*40}, {'AUTO_DEV_AFTER':''},
                       {'SOURCE_BRANCH':'main'}, {'GITHUB_WORKFLOW_REF':'mrn/site/.github/workflows/site-deploy.yml@refs/heads/phase-2'}):
            with self.payload_file(), self.assertRaises(ValueError):
                request.github_order({**self.env, **change}, SHA, 'dev', 'deploy')
        for target, mode in (('live','deploy'), ('both','deploy'), ('dev','preflight')):
            with self.assertRaises(ValueError): request.github_order(self.env, SHA, target, mode)
            with self.assertRaises(ValueError): request.select_request({**self.env, 'TARGET':target, 'MODE':mode})

    def test_stale_branch_validation_and_legacy_watermark_compatibility(self):
        with self.payload_file(): order = request.github_order(self.env, SHA, 'dev', 'deploy')
        old = {**order, 'event':'push', 'run_number':1, 'run_id':'101'}
        del old['source_branch']
        request.check_order(order, old)
        with self.assertRaises(ValueError): request.check_order(old, order)
        request.check_order(order, order, rollback=True)
        with patch.object(request.urllib.request, 'urlopen', return_value=io.BytesIO(
                json.dumps({'object':{'sha':'c'*40}}).encode())) as api:
            with self.assertRaisesRegex(ValueError, 'superseded'):
                request.require_current_push(order, {'GH_TOKEN':'read-only-test-token'})
            self.assertTrue(api.call_args.args[0].full_url.endswith('/git/ref/heads/phase-2'))


if __name__ == '__main__':
    unittest.main()

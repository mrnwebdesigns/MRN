import io
import json
import os
from pathlib import Path
import re
import sys
from types import SimpleNamespace as NS
import unittest
from unittest.mock import patch

import yaml

sys.path.insert(0, str(Path(__file__).parents[1]))
import deployment_request as request

SHA = 'a' * 40


def order(number=1, attempt=1, event='push'):
    return dict(repository='mrn/site', workflow='mrn/site/.github/workflows/site-deploy.yml',
                source_sha=SHA, event=event, run_id=str(100 + number), run_number=number, run_attempt=attempt)


class RequestContract(unittest.TestCase):
    def test_push_uses_event_commit_even_when_main_has_moved(self):
        with patch.object(request, 'resolve', return_value='b' * 40) as resolve:
            self.assertEqual(SHA, request.selection('push', 'refs/heads/main', SHA, 'dev', 'deploy', 'main'))
            resolve.assert_not_called()

    def test_automatic_trigger_matrix_rejects_pr_feature_and_live(self):
        cases = [('pull_request', 'refs/heads/main', 'dev', 'deploy', 'main'),
                 ('workflow_run', 'refs/heads/main', 'dev', 'deploy', 'main'),
                 ('push', 'refs/heads/feature', 'dev', 'deploy', 'main'),
                 ('push', 'refs/heads/main', 'live', 'deploy', 'main'),
                 ('push', 'refs/heads/main', 'both', 'deploy', 'main'),
                 ('push', 'refs/heads/main', 'dev', 'deploy', 'feature/test'),
                 ('push', 'refs/heads/main', 'dev', 'preflight', 'main')]
        with patch.object(request, 'resolve') as resolve:
            for event, ref, target, mode, branch in cases:
                with self.subTest(event=event, target=target, ref=ref), self.assertRaises(ValueError):
                    request.selection(event, ref, SHA, target, mode, branch)
            resolve.assert_not_called()

    def test_manual_dev_feature_and_phase_preview_guard(self):
        with patch.object(request, 'resolve', return_value=SHA) as resolve:
            self.assertEqual(SHA, request.selection('workflow_dispatch', 'refs/heads/main', 'b'*40,
                                                   'dev', 'deploy', 'phase-2', False))
            resolve.assert_called_once_with('dev', 'phase-2', 'refs/heads/main')
        self.assertIsNone(request.selection('push', 'refs/heads/main', SHA, 'dev', 'deploy', 'main', False))
        for target in ('dev', 'both'):
            with self.assertRaises(ValueError):
                request.selection('workflow_dispatch', 'refs/heads/main', SHA, target, 'deploy', 'main', False)

    def test_controller_requires_exact_successful_source_qa_for_push(self):
        env = dict(GITHUB_ACTIONS='true', GITHUB_EVENT_NAME='push', GITHUB_REF='refs/heads/main',
                   GITHUB_SHA=SHA, GITHUB_REPOSITORY='mrn/site', MRN_SOURCE_QA_SHA=SHA,
                   GITHUB_WORKFLOW_REF='mrn/site/.github/workflows/site-deploy.yml@refs/heads/main',
                   GITHUB_RUN_ID='101', GITHUB_RUN_NUMBER='1', GITHUB_RUN_ATTEMPT='1')
        self.assertEqual(order(), request.github_order(env, SHA, 'dev', 'deploy'))
        for change in ({'MRN_SOURCE_QA_SHA':''}, {'MRN_SOURCE_QA_SHA':'b'*40},
                       {'GITHUB_SHA':'b'*40}, {'GITHUB_EVENT_NAME':'pull_request'}):
            with self.assertRaises(ValueError):
                request.github_order({**env, **change}, SHA, 'dev', 'deploy')
        with self.assertRaises(ValueError): request.github_order(env, SHA, 'live', 'deploy')
        self.assertEqual('workflow_dispatch', request.github_order(
            {**env, 'GITHUB_EVENT_NAME':'workflow_dispatch'}, SHA, 'live', 'deploy')['event'])

    def test_superseded_push_stops_before_remote_deployment(self):
        with patch.object(request.urllib.request, 'urlopen', return_value=io.BytesIO(
                json.dumps({'object':{'sha':'b'*40}}).encode())):
            with self.assertRaisesRegex(ValueError, 'superseded'):
                request.require_current_push(order(), {'GH_TOKEN':'test-read-only-token'})

    def test_sequence_rerun_recovery_and_namespace_contract(self):
        request.check_order(order(3), order(1))
        request.check_order(order(3, 2), order(3))
        request.check_order(order(3), order(3), rollback=True)
        request.check_order(None, order(3), rollback=True)
        for incoming in (order(2, 99), order(3), None, {**order(4), 'workflow':'mrn/site/.github/workflows/other.yml'},
                         {**order(3, 2), 'source_sha':'b'*40}):
            with self.assertRaises(ValueError): request.check_order(incoming, order(3))


class WorkflowGraph(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.root = Path(__file__).resolve().parents[3]
        cls.workflow = yaml.load((cls.root / '.github/workflows/site-deploy.yml').read_text(), Loader=yaml.BaseLoader)

    def enabled(self, job, event='push', target='dev', mode='deploy', qa='success', dev='success'):
        needs = NS(request=NS(result='success', outputs=NS(enabled='true')),
                   source_qa=NS(result=qa), build=NS(result='success'), dev=NS(result=dev))
        expression = self.workflow['jobs'][job]['if'].replace('needs.source-qa', 'needs.source_qa')
        expression = expression.replace('&&', ' and ').replace('||', ' or ').replace('!cancelled()', 'not cancelled()')
        return eval(expression, {'__builtins__':{}}, dict(needs=needs, inputs=NS(target=target, mode=mode),
                    github=NS(event_name=event), always=lambda:True, cancelled=lambda:False))

    def test_failed_skipped_or_cancelled_qa_prevents_build(self):
        for outcome in ('failure', 'skipped', 'cancelled'):
            self.assertFalse(self.enabled('build', qa=outcome))
        self.assertTrue(self.enabled('build'))
        self.assertTrue(self.enabled('build', event='workflow_dispatch', mode='preflight', qa='skipped'))
        self.assertIn('source-qa', self.workflow['jobs']['build']['needs'])

    def test_live_is_manual_and_both_waits_for_dev(self):
        for event in ('push', 'workflow_run', 'pull_request'):
            for target in ('live', 'both'):
                self.assertFalse(self.enabled('live', event=event, target=target))
        self.assertTrue(self.enabled('live', event='workflow_dispatch', target='live', dev='skipped'))
        self.assertTrue(self.enabled('live', event='workflow_dispatch', target='both'))
        for outcome in ('failure', 'cancelled', 'skipped'):
            self.assertFalse(self.enabled('live', event='workflow_dispatch', target='both', dev=outcome))

    def test_one_artifact_and_one_commit_flow_to_both_environments(self):
        jobs = self.workflow['jobs']
        for name in ('source-qa', 'build', 'dev', 'live'):
            self.assertEqual('${{ needs.request.outputs.source_sha }}', jobs[name]['with']['source_sha'])
        for name in ('dev', 'live'):
            self.assertEqual('${{ needs.build.outputs.artifact_id }}', jobs[name]['with']['artifact_id'])
            self.assertEqual('${{ needs.build.outputs.artifact_sha256 }}', jobs[name]['with']['artifact_sha256'])
            self.assertEqual('${{ needs.source-qa.outputs.source_sha }}', jobs[name]['with']['qa_source_sha'])

    def test_thin_wrapper_and_per_environment_serialization(self):
        wrapper = yaml.load((self.root / 'tools/site-deploy/site-deploy.yml.template').read_text(), Loader=yaml.BaseLoader)
        self.assertEqual({'workflow_run', 'workflow_dispatch'}, set(wrapper['on']))
        self.assertEqual(['MRN source push'], wrapper['on']['workflow_run']['workflows'])
        self.assertEqual('${{ vars.MRN_AUTO_DEV_AFTER }}', wrapper['jobs']['deploy']['with']['auto_dev_after'])
        signal = yaml.load((self.root / 'tools/site-deploy/site-push.yml.template').read_text(), Loader=yaml.BaseLoader)
        self.assertEqual({'push'}, set(signal['on']))
        self.assertEqual(['main'], signal['on']['push']['branches'])
        self.assertEqual({}, signal['permissions'])
        self.assertEqual(1, len(signal['jobs']['signal']['steps']))
        self.assertNotIn('uses', signal['jobs']['signal']['steps'][0])
        self.assertEqual(['deploy'], list(wrapper['jobs']))
        self.assertNotIn('steps', wrapper['jobs']['deploy'])
        target = yaml.load((self.root / '.github/workflows/site-deploy-target.yml').read_text(), Loader=yaml.BaseLoader)['jobs']['deploy']
        self.assertEqual('site-code-${{ github.repository }}-${{ inputs.target }}', target['concurrency']['group'])
        self.assertEqual('false', target['concurrency']['cancel-in-progress'])
        self.assertEqual('${{ inputs.target }}', target['environment']['name'])
        self.assertEqual('${{ vars.DEPLOY_URL }}', target['env']['DEPLOY_URL'])


if __name__ == '__main__':
    unittest.main()

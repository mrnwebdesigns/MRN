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

    def test_main_dispatch_and_retry_keep_the_event_commit(self):
        with patch.object(request, 'resolve') as resolve:
            for mode in ('deploy', 'preflight', 'qualify'):
                self.assertEqual(SHA, request.selection('workflow_dispatch', 'refs/heads/main', SHA,
                                                        'live', mode, 'main'))
            resolve.assert_not_called()
        env = {'GITHUB_EVENT_NAME': 'workflow_dispatch', 'GITHUB_RUN_ATTEMPT': '2',
               'MODE': 'deploy', 'SOURCE_BRANCH': 'phase-2'}
        with self.assertRaisesRegex(ValueError, 'original SHA'):
            request.deployment_selection(env)

    def test_main_dispatch_stops_before_writes_when_main_moved(self):
        with patch.object(request.urllib.request, 'urlopen', return_value=io.BytesIO(
                json.dumps({'object':{'sha':'b'*40}}).encode())):
            with self.assertRaisesRegex(ValueError, 'superseded'):
                request.require_current_push(order(event='workflow_dispatch'),
                                             {'GH_TOKEN':'read-only-fixture', 'SOURCE_BRANCH':'main'})

    def test_write_retry_accepts_only_definitive_no_write_for_that_target(self):
        env = {'GITHUB_RUN_ID': '101', 'GITHUB_RUN_ATTEMPT': '2'}
        job = {'name': 'deploy / live / Site target (live)', 'status': 'completed',
               'steps': [{'name': 'Preflight or deploy exact child-theme payload', 'conclusion': 'failure'}]}
        with patch.object(request.release_request, 'api', return_value={'total_count':1, 'jobs':[job]}):
            request.require_safe_retry(env, 'dev')
            with self.assertRaisesRegex(ValueError, 'may have written to live'):
                request.require_safe_retry(env, 'live')
            job['steps'].append({'name': 'Confirm no remote site write', 'conclusion': 'success'})
            request.require_safe_retry(env, 'live')
            job['status'] = 'in_progress'
            with self.assertRaisesRegex(ValueError, 'may have written'):
                request.require_safe_retry(env, 'live')

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

    def enabled(self, job, event='push', target='dev', mode='deploy', qa='success', dev='success',
                intent='{}', publication='success', promotion='79', build='success'):
        needs = NS(request=NS(result='success', outputs=NS(enabled='true', target=target, release_intent=intent)),
                   source_qa=NS(result=qa), build=NS(result=build),
                   dev=NS(result=dev, outputs=NS(promotion_evidence_id=promotion)),
                   publish_qualification=NS(result=publication))
        expression = self.workflow['jobs'][job]['if'].replace('needs.source-qa', 'needs.source_qa')
        expression = expression.replace('needs.publish-qualification', 'needs.publish_qualification')
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

    def test_explicit_tag_live_and_both_waits_for_dev(self):
        self.assertTrue(self.enabled('live', event='workflow_run', target='live', dev='skipped', intent='validated'))
        self.assertTrue(self.enabled('live', event='workflow_run', target='both', intent='validated'))
        for outcome in ('failure', 'cancelled', 'skipped'):
            self.assertFalse(self.enabled('live', event='workflow_run', target='both', dev=outcome, intent='validated'))
        self.assertFalse(self.enabled('dev', event='workflow_run', target='live', intent='validated'))
        self.assertTrue(self.enabled('dev', event='workflow_run', target='both', intent='validated'))

    def test_one_artifact_and_one_commit_flow_to_both_environments(self):
        jobs = self.workflow['jobs']
        for name in ('source-qa', 'build', 'dev', 'live'):
            self.assertEqual('${{ needs.request.outputs.source_sha }}', jobs[name]['with']['source_sha'])
        for name in ('dev', 'live'):
            self.assertEqual('${{ needs.build.outputs.artifact_id }}', jobs[name]['with']['artifact_id'])
            self.assertEqual('${{ needs.build.outputs.artifact_sha256 }}', jobs[name]['with']['artifact_sha256'])
            self.assertEqual('${{ needs.source-qa.outputs.source_sha }}', jobs[name]['with']['qa_source_sha'])

    def test_qualification_and_failed_publication_cannot_dispatch_target(self):
        self.assertTrue(self.enabled('build', target='qualify', mode='qualify'))
        for target in ('dev', 'live'):
            self.assertFalse(self.enabled(target, event='workflow_dispatch', target='qualify', mode='qualify'))
            self.assertTrue(self.enabled(target, event='workflow_dispatch', target=target, mode='preflight',
                                         dev='skipped', publication='skipped'))
        self.assertFalse(self.enabled('dev', event='workflow_dispatch', target='dev', publication='failure'))
        self.assertFalse(self.enabled('live', event='workflow_dispatch', target='both', promotion=''))
        self.assertTrue(self.enabled('live', event='workflow_dispatch', target='live', qa='skipped',
                                     build='skipped', dev='skipped', publication='skipped'))

    def test_live_requires_promotion_before_site_access(self):
        steps = yaml.load((self.root / '.github/workflows/site-deploy-target.yml').read_text(),
                          Loader=yaml.BaseLoader)['jobs']['deploy']['steps']
        by_name = {step.get('name'): (index, step) for index, step in enumerate(steps)}
        gate = by_name['Require passing QA from the exact pre-production release'][0]
        self.assertLess(gate, by_name['Configure pinned SSH identity'][0])
        self.assertLess(gate, by_name['Preflight or deploy exact child-theme payload'][0])
        _, archive = by_name['Download the exact immutable release artifact']
        self.assertIn('steps.promotion.outputs.archive_id', archive['with']['artifact-ids'])
        self.assertIn("steps.runtime.outcome == 'success'",
                      by_name['Record passing pre-production promotion evidence'][1]['if'])

    def test_hotfix_builds_only_for_the_manual_live_path(self):
        args = dict(event='workflow_dispatch', target='live', mode='hotfix', qa='skipped', dev='skipped',
                    publication='skipped')
        self.assertTrue(self.enabled('build', **args))
        self.assertTrue(self.enabled('live', **args))
        self.assertFalse(self.enabled('dev', **args))
        self.assertFalse(self.enabled('live', **{**args, 'build': 'failure'}))
        self.assertFalse(self.enabled('live', **{**args, 'event': 'workflow_run', 'intent': 'validated'}))
        self.assertFalse(self.enabled('live', **{**args, 'target': 'both'}))

    def test_hotfix_retains_preflight_smoke_and_rollback_controls(self):
        target = yaml.load((self.root / '.github/workflows/site-deploy-target.yml').read_text(),
                           Loader=yaml.BaseLoader)['jobs']['deploy']
        steps = target['steps']
        by_name = {step.get('name'): (index, step) for index, step in enumerate(steps)}
        _, promote = by_name['Require passing QA from the exact pre-production release']
        self.assertIn("inputs.mode == 'deploy'", promote['if'])
        approval, _ = by_name['Record emergency approval for this exact Live artifact']
        self.assertLess(approval, by_name['Configure pinned SSH identity'][0])
        activation, deploy = by_name['Preflight or deploy exact child-theme payload']
        self.assertIn('tools/site-deploy/deploy.py', deploy['run'])
        smoke, smoke_step = by_name['Smoke-check the activated Live hotfix']
        self.assertGreater(smoke, activation)
        self.assertIn("http_check(url + '/wp-json/', rest=True)", smoke_step['run'])
        _, rollback = by_name['Restore the prior release if strict runtime acceptance fails']
        self.assertIn("steps.hotfix-smoke.outcome == 'failure'", rollback['if'])

    def test_thin_wrapper_and_per_environment_serialization(self):
        wrapper = yaml.load((self.root / 'tools/site-deploy/site-deploy.yml.template').read_text(), Loader=yaml.BaseLoader)
        self.assertEqual({'workflow_run', 'workflow_dispatch'}, set(wrapper['on']))
        self.assertEqual(['MRN source push'], wrapper['on']['workflow_run']['workflows'])
        self.assertEqual('${{ vars.MRN_AUTO_DEV_AFTER }}', wrapper['jobs']['deploy']['with']['auto_dev_after'])
        signal = yaml.load((self.root / 'tools/site-deploy/site-push.yml.template').read_text(), Loader=yaml.BaseLoader)
        self.assertEqual({'push'}, set(signal['on']))
        self.assertEqual(['main'], signal['on']['push']['branches'])
        self.assertEqual(['deploy-dev-*', 'deploy-live-*', 'deploy-both-*'], signal['on']['push']['tags'])
        self.assertIn('github.event.deleted', signal['run-name'])
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

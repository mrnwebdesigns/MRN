"""GUI-equivalent Git tag requests cross the trusted main receiver only."""
import copy
import io
import json
from pathlib import Path
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))
import deployment_request as request
import release_request as release
import test_source_push
SHA = test_source_push.SHA
from test_automatic_dev import order


class ReleaseRequests(unittest.TestCase):
    def setUp(self):
        fixture = test_source_push.SourcePushContract()
        fixture.setUp()
        self.env = {**fixture.env, 'AUTO_DEV_BRANCH':'main', 'SOURCE_BRANCH':'main',
                    'RELEASE_REQUESTS_AFTER':'2026-10-02T12:00:00Z', 'GH_TOKEN':'read-only-test-token'}
        self.payload = fixture.payload
        self.run = self.payload['workflow_run']
        self.run.update(head_branch='main', run_number=17,
                        display_title=f'MRN request refs/tags/deploy-both-test {SHA} true false')
        self.template = Path(release.__file__).with_name('site-push.yml.template').read_bytes()
        self.producer = patch.object(release.subprocess, 'check_output', return_value=self.template)
        self.api = patch.object(release, 'api', side_effect=self.responses)
        self.producer.start()
        self.api.start()
        self.addCleanup(patch.stopall)

    def responses(self, env, path):
        if path.startswith('git/ref/tags/'):
            return {'object':{'type':'tag', 'sha':'c'*40}}
        if path.startswith('git/tags/'):
            return {'object':{'type':'commit', 'sha':SHA}}
        if path == 'git/ref/heads/main':
            return {'object':{'sha':SHA}}
        if path.startswith('compare/'):
            return {'status':'identical'}
        raise AssertionError(path)

    def event_file(self):
        return patch('builtins.open', return_value=io.StringIO(json.dumps(self.payload)))

    def test_exact_tag_commit_not_downstream_head_and_all_targets(self):
        for target in ('dev', 'live', 'both'):
            self.run['display_title'] = f'MRN request refs/tags/deploy-{target}-test {SHA} true false'
            result = request.source_request(self.env, self.payload)
            self.assertEqual(SHA, result['source_sha'])
            self.assertEqual(target, result['target'])
            self.assertEqual('c'*40, result['tag_object'])  # annotated GUI tag
            with self.event_file():
                self.assertEqual(result, request.deployment_selection(self.env))

    def test_lightweight_and_nested_annotated_tag_and_noncommit_rejection(self):
        with patch.object(release, 'api', return_value={'object':{'type':'commit','sha':SHA}}):
            self.assertEqual((SHA, SHA), release.tag_identity(self.env, 'deploy-dev-test'))
        with patch.object(release, 'api', side_effect=[{'object':{'type':'tag','sha':'c'*40}},
                {'object':{'type':'tag','sha':'d'*40}}, {'object':{'type':'commit','sha':SHA}}]):
            self.assertEqual(('c'*40, SHA), release.tag_identity(self.env, 'deploy-dev-test'))
        with patch.object(release, 'api', return_value={'object':{'type':'tree','sha':SHA}}):
            with self.assertRaises(ValueError): release.tag_identity(self.env, 'deploy-dev-test')

    def test_workflow_name_and_commit_message_cannot_forge_release_intent(self):
        self.run['display_title'] = 'ordinary commit message'
        with self.assertRaisesRegex(ValueError, 'template'):
            request.source_request(self.env, self.payload)
        self.run['display_title'] = f'MRN request refs/tags/deploy-live-test {SHA} true false'
        with patch.object(release.subprocess, 'check_output', return_value=self.template + b'\n# modified producer\n'):
            with self.assertRaisesRegex(ValueError, 'pinned'):
                request.source_request(self.env, self.payload)

    def test_failed_signal_pr_fork_deleted_updated_and_wrong_sha_block(self):
        for change in ({'event':'pull_request'}, {'head_repository':{'full_name':'attacker/site'}},
                       {'display_title':f'MRN request refs/tags/deploy-live-test {SHA} true true'},
                       {'display_title':f'MRN request refs/tags/deploy-live-test {SHA} false false'},
                       {'display_title':f'MRN request refs/tags/deploy-live-test {"b"*40} true false'},
                       {'display_title':f'MRN request refs/tags/arbitrary {SHA} true false'}):
            payload = copy.deepcopy(self.payload)
            payload['workflow_run'].update(change)
            with self.subTest(change=change), self.assertRaises(ValueError):
                request.source_request(self.env, payload)
        self.run['conclusion'] = 'failure'
        self.assertIsNone(request.source_request(self.env, self.payload))

    def test_unarmed_and_old_installation_reruns_do_not_deploy(self):
        for cutoff in ('', '2026-10-02T12:01:00Z', '2026-10-02T12:02:00Z'):
            self.assertIsNone(request.source_request({**self.env, 'RELEASE_REQUESTS_AFTER':cutoff}, self.payload))
        self.run['run_attempt'] = 2
        self.assertIsNone(request.source_request({**self.env, 'RELEASE_REQUESTS_AFTER':'2026-10-03T12:00:00Z'}, self.payload))

    def test_dev_only_site_and_phase_preview_guards(self):
        for env in ({'LIVE_ENABLED':'false'}, {'DEV_MAIN_ENABLED':'false'}):
            with self.assertRaises(ValueError): request.source_request({**self.env, **env}, self.payload)
        with self.event_file(), patch.object(request, 'resolve', return_value=SHA):
            with self.assertRaises(ValueError):
                request.deployment_selection({**self.env, 'GITHUB_EVENT_NAME':'workflow_dispatch', 'TARGET':'live', 'LIVE_ENABLED':'false'})

    def test_tag_retarget_stale_main_and_missing_hotfix_rejected(self):
        intent = request.source_request(self.env, self.payload)
        with patch.object(release, 'tag_identity', return_value=('d'*40, SHA)):
            with self.assertRaisesRegex(ValueError, 'changed'): release.current(self.env, intent)
        with patch.object(release, 'api', side_effect=lambda env,p: {'object':{'sha':'d'*40}} if p=='git/ref/heads/main' else self.responses(env,p)):
            with self.assertRaisesRegex(ValueError, 'superseded'): release.current(self.env, intent)
        intent['target'] = 'dev'
        with patch.object(release, 'api', side_effect=lambda env,p: {'status':'diverged'} if p.startswith('compare/') else self.responses(env,p)):
            with self.assertRaisesRegex(ValueError, 'include current main'): release.current(self.env, intent)

    def test_controller_binds_qa_source_intent_and_destination(self):
        result = request.source_request(self.env, self.payload)
        intent = {k:result[k] for k in ('kind','tag','tag_object','source_sha','target')}
        env = {**self.env, 'RELEASE_INTENT':json.dumps(intent)}
        for target in ('dev','live'):
            with self.event_file(): sequence = request.github_order(env, SHA, target, 'deploy')
            self.assertEqual(intent, sequence['release_intent'])
            self.assertEqual(17, sequence['source_run_number'])
        for change in ({'MRN_SOURCE_QA_SHA':'b'*40}, {'RELEASE_INTENT':'{}'}, {'SOURCE_BRANCH':'feature/other'}):
            with self.event_file(), self.assertRaises(ValueError):
                request.github_order({**env, **change}, SHA, 'live', 'deploy')

    def test_out_of_order_signal_completion_and_changed_rerun_rejected(self):
        previous = {**order(5,event='workflow_run'), 'source_branch':'main', 'source_run_number':20}
        incoming = {**order(6,event='workflow_run'), 'source_branch':'main', 'source_run_number':19}
        with self.assertRaisesRegex(ValueError, 'Stale source'): request.check_order(incoming, previous)
        result = request.source_request(self.env, self.payload)
        intent = {k:result[k] for k in ('kind','tag','tag_object','source_sha','target')}
        with self.assertRaises(ValueError):
            request.check_order({**previous,'run_attempt':2,'release_intent':intent}, previous)
        request.check_order(previous, previous, rollback=True)

    def test_branch_signal_remains_dev_only_even_if_head_branch_is_ambiguous(self):
        self.run['display_title'] = f'MRN request refs/heads/main {SHA} false false'
        self.run['head_branch'] = 'deploy-live-confusing-tag'
        result = request.source_request(self.env, self.payload)
        self.assertEqual(('dev','main','branch'), (result['target'],result['source_branch'],result['kind']))
        self.run['display_title'] = f'MRN request refs/heads/feature/test {SHA} false false'
        with self.assertRaises(ValueError): request.source_request(self.env, self.payload)


if __name__ == '__main__':
    unittest.main()

import io
import json
from pathlib import Path
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))
import deployment_request as request


SHA = 'a' * 40
REPOSITORY = 'mrnwebdesigns/example'


class LiveHotfix(unittest.TestCase):
    def setUp(self):
        self.env = {
            'GITHUB_ACTIONS': 'true', 'GITHUB_EVENT_NAME': 'workflow_dispatch', 'GITHUB_REF': 'refs/heads/main',
            'GITHUB_SHA': SHA, 'GITHUB_ACTOR': 'operator', 'GITHUB_EVENT_PATH': 'event.json',
            'GITHUB_REPOSITORY': REPOSITORY, 'GITHUB_WORKFLOW_REF': REPOSITORY + '/.github/workflows/site-deploy.yml@refs/heads/main',
            'GITHUB_RUN_ID': '100', 'GITHUB_RUN_NUMBER': '10', 'GITHUB_RUN_ATTEMPT': '1',
            'TARGET': 'live', 'MODE': 'hotfix', 'LIVE_ENABLED': 'true', 'SOURCE_BRANCH': 'main',
        }
        self.inputs = {'mode': 'hotfix', 'target': 'live', 'hotfix_sha': SHA, 'hotfix_reason': 'Restore public checkout'}

    def event(self, **change):
        return patch('builtins.open', return_value=io.StringIO(json.dumps({'inputs': {**self.inputs, **change}})))

    def test_reviewed_main_dispatch_binds_sha_actor_and_reason_without_source_qa(self):
        with self.event():
            self.assertEqual(SHA, request.deployment_selection(self.env)['source_sha'])
        with self.event():
            order = request.github_order(self.env, SHA, 'live', 'deploy')
        self.assertEqual({'actor': 'operator', 'reason': 'Restore public checkout'}, order['hotfix'])
        self.assertEqual(SHA, order['source_sha'])
        request.check_order(order, order, rollback=True)
        with self.assertRaisesRegex(ValueError, 'original run and source identity'):
            request.check_order({**order, 'run_attempt': 2, 'hotfix': {'actor': 'operator', 'reason': 'different'}}, order)

    def test_other_triggers_targets_and_disabled_live_cannot_use_hotfix(self):
        for change in ({'GITHUB_EVENT_NAME': 'workflow_run'}, {'GITHUB_EVENT_NAME': 'push'},
                       {'GITHUB_REF': 'refs/heads/hotfix/unreviewed'}, {'SOURCE_BRANCH': 'feature'},
                       {'TARGET': 'dev'}, {'TARGET': 'staging'}, {'TARGET': 'both'}, {'LIVE_ENABLED': 'false'}):
            with self.subTest(change=change), self.event(), self.assertRaises(ValueError):
                request.deployment_selection({**self.env, **change})

    def test_confirmation_is_checked_again_before_target_activation(self):
        for change in ({'hotfix_sha': ''}, {'hotfix_sha': SHA[:8]}, {'hotfix_sha': 'b' * 40},
                       {'hotfix_reason': ''}, {'hotfix_reason': 'one\ntwo'}, {'mode': 'deploy'}, {'target': 'dev'}):
            with self.subTest(change=change), self.event(**change), self.assertRaises(ValueError):
                request.github_order(self.env, SHA, 'live', 'deploy')
        with self.event(), self.assertRaisesRegex(ValueError, 'reviewed main dispatch SHA'):
            request.github_order(self.env, 'c' * 40, 'live', 'deploy')

    def test_ordinary_live_still_requires_qualification(self):
        with self.event(), self.assertRaisesRegex(ValueError, 'requires QA'):
            request.github_order({**self.env, 'MODE': 'deploy'}, SHA, 'live', 'deploy')

    def test_new_main_push_invalidates_the_confirmed_hotfix(self):
        with self.event():
            order = request.github_order(self.env, SHA, 'live', 'deploy')
        response = io.BytesIO(json.dumps({'object': {'sha': 'c' * 40}}).encode())
        with patch.object(request.urllib.request, 'urlopen', return_value=response), \
             self.assertRaisesRegex(ValueError, 'superseded'):
            request.require_current_push(order, {**self.env, 'GH_TOKEN': 'fixture'})

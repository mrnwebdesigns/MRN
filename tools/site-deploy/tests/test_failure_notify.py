"""Failure observers send one bounded metadata-only email and never deploy code."""
import copy
import io
import json
from pathlib import Path
import sys
import tempfile
import unittest
import urllib.error
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))
import notify_failure as notify
from enrollment_github import GitHub

REPO = 'mrnwebdesigns/example-site'
SHA = 'a' * 40
MESSAGE_ID = '9d80a934-72af-4bf6-af85-a0d4eeb5d8e1'


def event():
    repository = {'full_name': REPO}
    return {'action': 'completed', 'repository': repository, 'workflow_run': {
        'repository': repository, 'head_repository': repository,
        'name': 'Deploy site', 'path': '.github/workflows/site-deploy.yml',
        'status': 'completed', 'conclusion': 'failure', 'id': 123, 'run_attempt': 2,
        'head_sha': 'b' * 40, 'head_branch': 'main'}}


class API:
    def __init__(self, response=None, exception=None):
        self.requests = []
        self.response = response or {'ErrorCode': 0, 'MessageID': MESSAGE_ID}
        self.exception = exception

    def open(self, request, timeout):
        self.requests.append(request)
        if request.full_url == 'https://api.postmarkapp.com/email':
            if self.exception:
                raise self.exception
            result = self.response
        elif '/jobs?' in request.full_url:
            result = {'total_count': 2, 'jobs': [
                {'name': 'deploy / source-qa / qa', 'conclusion': 'failure',
                 'steps': [{'name': 'MRN source acceptance', 'conclusion': 'failure'}]},
                {'name': 'deploy / dev', 'conclusion': 'skipped', 'steps': []}]}
        elif '/artifacts?' in request.full_url:
            result = {'artifacts': [{'name': 'site-source-qa-' + SHA}]}
        else:
            raise AssertionError('Unexpected URL: ' + request.full_url)
        return io.BytesIO(json.dumps(result).encode())

    def posts(self):
        return [r for r in self.requests if r.get_method() == 'POST']


class FailureEmail(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.receipt = Path(self.temp.name) / 'receipt.json'
        self.env = {'GITHUB_REPOSITORY': REPO, 'GITHUB_EVENT_NAME': 'workflow_run',
                    'GITHUB_RUN_ATTEMPT': '1', 'GH_TOKEN': 'github-fixture-only',
                    'POSTMARK_SERVER_TOKEN': 'postmark-fixture-only'}

    def test_source_qa_failure_sends_one_email_with_exact_source_and_failed_step(self):
        api = API()
        result = notify.run(event(), self.env, self.receipt, api)
        self.assertEqual('provider-accepted', result['status'])
        self.assertEqual(MESSAGE_ID, result['message_id'])
        self.assertEqual(1, len(api.posts()))
        payload = json.loads(api.posts()[0].data)
        self.assertEqual('mrn-developer@mrnwebdesigns.com', payload['To'])
        self.assertNotIn('Cc', payload)
        self.assertNotIn('Bcc', payload)
        self.assertIn(SHA, payload['TextBody'])
        self.assertIn('MRN source acceptance', payload['TextBody'])
        self.assertIn('/attempts/2/jobs?', api.requests[0].full_url)
        self.assertIn('/actions/runs/123', payload['TextBody'])
        self.assertEqual('not_yet_verified', result['recipient_server_delivery'])

    def test_success_cancellation_and_skipped_runs_send_nothing(self):
        for outcome in ['success', 'cancelled', 'skipped', 'neutral']:
            data = event();data['workflow_run']['conclusion'] = outcome
            api = API()
            self.assertEqual({'status': 'not-required'}, notify.run(data, self.env, self.receipt, api))
            self.assertEqual([], api.requests)

    def test_wrong_workflow_repository_fork_or_event_cannot_use_mail_credential(self):
        for path, value in [('name', 'other'), ('path', '.github/workflows/other.yml'),
                            ('repository', {'full_name': 'other/site'}),
                            ('head_repository', {'full_name': 'fork/site'}), ('status', 'in_progress')]:
            data = event();data['workflow_run'][path] = value
            api = API()
            with self.subTest(path=path), self.assertRaises(ValueError):
                notify.run(data, self.env, self.receipt, api)
            self.assertEqual([], api.requests)

    def test_notification_reruns_require_reconciliation_and_never_resend(self):
        api = API()
        with self.assertRaisesRegex(ValueError, 'reconciliation'):
            notify.run(event(), {**self.env, 'GITHUB_RUN_ATTEMPT': '2'}, self.receipt, api)
        self.assertEqual([], api.requests)

    def test_existing_receipt_blocks_second_send(self):
        api = API();notify.run(event(), self.env, self.receipt, api)
        with self.assertRaisesRegex(ValueError, 'already exists'):
            notify.run(event(), self.env, self.receipt, api)
        self.assertEqual(1, len(api.posts()))

    def test_timeout_is_unknown_and_has_no_automatic_retry(self):
        api = API(exception=TimeoutError('provider may have accepted'))
        with self.assertRaisesRegex(ValueError, 'unknown'):
            notify.run(event(), self.env, self.receipt, api)
        self.assertEqual('outcome-unknown', json.loads(self.receipt.read_text())['status'])
        self.assertEqual(1, len(api.posts()))

    def test_invalid_acceptance_response_is_unknown_not_delivery(self):
        api = API(response={'ErrorCode': 0, 'MessageID': 'not-a-uuid'})
        with self.assertRaises(ValueError):
            notify.run(event(), self.env, self.receipt, api)
        self.assertEqual('outcome-unknown', json.loads(self.receipt.read_text())['status'])
        self.assertEqual(1, len(api.posts()))

    def test_provider_rejection_is_visible_and_never_retried(self):
        api = API(response={'ErrorCode': 300, 'Message': 'withheld fixture detail'})
        with self.assertRaisesRegex(ValueError, 'rejected'):
            notify.run(event(), self.env, self.receipt, api)
        self.assertEqual('rejected', json.loads(self.receipt.read_text())['status'])
        self.assertEqual(1, len(api.posts()))

    def test_server_error_is_ambiguous_and_credentials_never_enter_receipt(self):
        api = API(exception=urllib.error.HTTPError('https://api.postmarkapp.com/email', 503, 'withheld', {}, None))
        with self.assertRaises(ValueError):
            notify.run(event(), self.env, self.receipt, api)
        record = self.receipt.read_text()
        self.assertEqual('outcome-unknown', json.loads(record)['status'])
        self.assertNotIn(self.env['POSTMARK_SERVER_TOKEN'], record)
        self.assertNotIn(self.env['GH_TOKEN'], record)

    def test_missing_notification_credential_fails_without_sending(self):
        api = API()
        with self.assertRaisesRegex(ValueError, 'credential'):
            notify.run(event(), {**self.env, 'POSTMARK_SERVER_TOKEN': ''}, self.receipt, api)
        self.assertEqual([], api.posts())
        self.assertEqual('rejected', json.loads(self.receipt.read_text())['status'])

    def test_body_uses_only_metadata_and_cannot_inject_recipient_or_headers(self):
        data = event();data['workflow_run']['head_branch'] = 'main\r\nBcc: attacker@example.invalid'
        data['workflow_run']['head_commit'] = {'message': 'PRIVATE LOG OR SECRET'}
        api = API();notify.run(data, self.env, self.receipt, api)
        payload = json.loads(api.posts()[0].data)
        self.assertNotIn('PRIVATE LOG OR SECRET', json.dumps(payload))
        self.assertEqual(notify.RECIPIENT, payload['To'])
        self.assertNotIn('\r', payload['TextBody'])
        self.assertNotIn(self.env['GH_TOKEN'], json.dumps(payload))
        self.assertNotIn(self.env['POSTMARK_SERVER_TOKEN'], json.dumps(payload))

    def test_no_redirect_can_forward_a_provider_credential(self):
        self.assertIsNone(notify.NoRedirect().redirect_request(None, None, 302, '', {}, 'https://other.invalid'))

    def test_future_enrollment_stores_mail_secret_outside_dev_environment(self):
        github = GitHub('github-fixture-only', REPO)
        with patch.object(github, 'request', side_effect=[None, {'branch_policies': [{'name': 'main', 'type': 'branch'}]}, None]), patch.object(github, 'secret') as secret:
            github.configure({}, {'MRN_DEPLOY_NOTIFY_POSTMARK_TOKEN': 'mail-fixture',
                                  'DEPLOY_SSH_PRIVATE_KEY': 'deploy-fixture'})
            secret.assert_any_call('MRN_DEPLOY_NOTIFY_POSTMARK_TOKEN', 'mail-fixture', None)
            secret.assert_any_call('DEPLOY_SSH_PRIVATE_KEY', 'deploy-fixture', 'dev')

    def test_caller_observes_completion_without_replacing_deployment_wrapper(self):
        root = Path(__file__).parents[1]
        text = (root / 'site-deploy-failure.yml.template').read_text()
        self.assertIn('workflows: [Deploy site]', text)
        self.assertIn("conclusion == 'failure'", text)
        self.assertIn('site-deploy-notify.yml@TOOLING_SHA', text)
        self.assertIn('MRN_DEPLOY_NOTIFY_POSTMARK_TOKEN', text)
        self.assertNotIn('DEPLOY_SSH_PRIVATE_KEY', text)
        self.assertNotIn('workflow_dispatch', text)


if __name__ == '__main__':
    unittest.main()

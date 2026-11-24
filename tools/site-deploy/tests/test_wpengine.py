import io
import json
from pathlib import Path
import sys
import unittest
from unittest.mock import Mock, patch
from urllib.error import HTTPError, URLError

sys.path.insert(0, str(Path(__file__).parents[1]))
from wpengine import WPEngine, ProviderThrottle
import deploy
import host_controller

INSTALL = '294deacc-d8b8-4005-82c4-0727ba8ddde0'
TARGET = {'url': 'https://example.org', 'host': 'example.ssh.wpengine.net', 'user': 'example', 'environment': 'live'}
IDENTITY = {'id': INSTALL, 'name': 'example', 'status': 'active', 'primary_domain': 'example.org',
            'environment': 'production', 'is_multisite': False}

def response(body, status=200):
    result = Mock(status=status)
    result.read.return_value = json.dumps(body).encode()
    result.__enter__ = Mock(return_value=result)
    result.__exit__ = Mock(return_value=False)
    return result


class WPETests(unittest.TestCase):
    def adapter(self, *answers):
        self.opener = Mock()
        self.opener.open.side_effect = answers
        self.sleep = Mock()
        return WPEngine('api-user', 'private-password', INSTALL, opener=self.opener, sleep=self.sleep, clock=lambda: 100)

    def test_exact_provider_identity_precedes_page_and_cdn_purge(self):
        adapter = self.adapter(response(IDENTITY), response({}, 202), response({}, 202))
        identity = adapter.identity(TARGET)
        receipt = adapter.refresh_delivery(TARGET['url'])
        self.assertEqual(identity['install_id'], receipt['install_id'])
        self.assertTrue(receipt['public_proof_required'])
        requests = [call.args[0] for call in self.opener.open.call_args_list]
        self.assertEqual(['GET', 'POST', 'POST'], [request.get_method() for request in requests])
        self.assertEqual([{'type': 'page'}, {'type': 'cdn'}], [json.loads(request.data) for request in requests[1:]])
        self.assertTrue(all(request.get_header('Authorization').startswith('Basic ') for request in requests))
        self.assertNotIn('private-password', json.dumps(receipt))
        self.assertNotIn('api-user', json.dumps(receipt))

    def test_wrong_domain_install_environment_or_ssh_refuses_cache_writes(self):
        for change in ({'primary_domain': 'other.org'}, {'id': 'a'*36}, {'environment': 'staging'},
                       {'name': 'other'}, {'status': 'pending'}, {'is_multisite': True}):
            adapter = self.adapter(response(dict(IDENTITY, **change)))
            with self.assertRaises(ValueError): adapter.identity(TARGET)
            with self.assertRaises(ValueError): adapter.refresh_delivery(TARGET['url'])
            self.assertEqual(1, self.opener.open.call_count)
        adapter = self.adapter()
        with self.assertRaises(ValueError): adapter.refresh_delivery(TARGET['url'])

    def test_staging_cannot_resolve_to_production_or_a_development_install(self):
        for native in ('production','development'):
            adapter=self.adapter(response(dict(IDENTITY,environment=native)))
            with self.assertRaises(ValueError): adapter.identity(dict(TARGET,environment='staging'))
        adapter=self.adapter(response(dict(IDENTITY,environment='staging')))
        self.assertEqual('staging',adapter.identity(dict(TARGET,environment='staging'))['environment'])

    def test_missing_credentials_fail_before_network_or_backup(self):
        with patch.dict('os.environ', {}, clear=True), patch.object(deploy, 'run') as ssh:
            with self.assertRaises(ValueError): deploy.Target({'host_provider': 'wpengine'})
            ssh.assert_not_called()
        with patch.object(host_controller, 'identity') as identity, patch.object(host_controller, 'backup') as backup:
            with self.assertRaises(ValueError): host_controller.execute({'host_provider':'wpengine'})
            identity.assert_not_called(); backup.assert_not_called()

    def test_long_retry_after_and_auth_failure_are_bounded_and_sanitized(self):
        for status, headers, error in ((429, {'Retry-After':'3600'}, ProviderThrottle), (401, {}, RuntimeError)):
            adapter = self.adapter(HTTPError('https://api.invalid', status, 'private-message', headers, io.BytesIO(b'secret')))
            with self.assertRaises(error) as caught: adapter.identity(TARGET)
            self.assertNotIn('private', str(caught.exception))
            self.assertEqual(1, self.opener.open.call_count); self.sleep.assert_not_called()

    def test_short_retry_after_is_observed_without_replaying_successful_operations(self):
        throttle = HTTPError('https://api.invalid', 429, '', {'Retry-After':'1'}, io.BytesIO())
        adapter = self.adapter(response(IDENTITY), response({},202), throttle, response({},202))
        adapter.identity(TARGET); receipt = adapter.refresh_delivery(TARGET['url'])
        self.assertEqual(2, len(receipt['operations'])); self.sleep.assert_called_once_with(1)
        posts = [json.loads(call.args[0].data) for call in self.opener.open.call_args_list[1:]]
        self.assertEqual([{'type':'page'}, {'type':'cdn'}, {'type':'cdn'}], posts)

    def test_ambiguous_post_is_not_retried(self):
        adapter = self.adapter(response(IDENTITY), URLError('private credential could be in upstream message'))
        adapter.identity(TARGET)
        with self.assertRaisesRegex(RuntimeError, 'outcome unknown'): adapter.refresh_delivery(TARGET['url'])
        self.assertEqual(2, self.opener.open.call_count)

    def test_rollback_uses_same_delivery_cache_and_public_proof_gate(self):
        adapter = Mock()
        store = Mock()
        pointer = {'release_id':'a'*64, 'public_path':None}
        plan = {'host_provider':'wpengine', 'url':TARGET['url'], 'pages':[TARGET['url']+'/']}
        with patch.object(host_controller,'html_cache',return_value={}), patch.object(host_controller,'http_check'), \
                patch.object(host_controller,'verify_cached_public',return_value={}), patch.object(host_controller,'legacy_assets',return_value={}):
            result = host_controller.public_check(plan, store, pointer, delivery_cache=adapter)
        adapter.refresh_delivery.assert_called_once_with(TARGET['url'])
        self.assertIn('delivery', result['cache'])
        store.verify_public_snapshot.assert_called_once()

if __name__ == '__main__': unittest.main()

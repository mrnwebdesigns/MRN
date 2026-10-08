"""Manual Live qualification gates and stale cached-parent rejection."""
from datetime import datetime, timedelta, timezone
import json
from pathlib import Path
import subprocess
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import parent_deploy
import parent_host
from parent_target import validate_route


def target():
    return {'url': 'https://fixture.example.com', 'environment': 'live', 'host_provider': 'nexcess',
            'backup_provider': 'updraft', 'qualify_live': True, 'exercise_rollback': True,
            'ssh_login': 'fixture@fixture.nxcli.io', 'root': '/home/fixture/html',
            'state': '/home/fixture/private-backups/mrn-parent-deploy/live/fixture-example-com',
            'child_state': '/home/fixture/private-backups/child-live',
            'mainwp': {'connected': True, 'dashboardHost': 'wpcontrol.mrndev.io', 'abilitiesCount': 84,
                       'site_id': 1, 'site_url': 'https://fixture.example.com',
                       'synced_at': datetime.now(timezone.utc).isoformat()},
            'pages': ['https://fixture.example.com/'], 'html_urls': ['https://fixture.example.com/']}


class LiveParent(unittest.TestCase):
    def test_manual_nexcess_qualification_uses_fresh_bound_mainwp_evidence(self):
        plan = target()
        self.assertEqual('nexcess', validate_route(plan))
        parent_deploy.validate_target(plan)
        parent_deploy.validate_target({**plan, 'child_state': None})
        plan['mainwp']['synced_at'] = (datetime.now(timezone.utc) - timedelta(minutes=16)).isoformat()
        with self.assertRaisesRegex(ValueError, '15 minutes'):
            parent_deploy.validate_target(plan)

    def test_live_flags_provider_paths_and_ssh_are_not_interchangeable(self):
        for changes in [
            {'qualify_live': False}, {'qualify_live': 'false'}, {'exercise_rollback': False}, {'exercise_rollback': 'false'}, {'backup_provider': 'kinsta'},
            {'host_provider': 'cloudpanel'}, {'host_provider': 'siteground'}, {'environment': 'dev'},
            {'url': 'https://fixture.mrndev.io'}, {'url': 'https://fixture.example.com?bypass=1'},
            {'url': 'https://fixture.example.com:443'}, {'url': 'https://127.0.0.1'},
            {'ssh_login': 'fixture@unrelated.example.com'}, {'state': '/home/fixture/shared-live'},
            {'root': '/home/fixture/../other/html'}, {'child_state': '/home/fixture/../other'},
        ]:
            with self.subTest(changes=changes), self.assertRaises(ValueError):
                validate_route({**target(), **changes})

    def test_unqualified_live_refuses_before_transport(self):
        with patch.object(parent_deploy, 'ssh') as ssh, self.assertRaises(ValueError):
            parent_deploy.run({**target(), 'qualify_live': False}, confirm=True)
        ssh.assert_not_called()

    def test_html_inventory_change_blocks_before_refresh(self):
        plan = target()
        with patch.object(parent_host, 'html_cache', return_value={'urls': plan['pages'] + ['https://fixture.example.com/new/']}) as cache:
            with self.assertRaisesRegex(ValueError, 'reviewed scope'):
                parent_host.live_html_scope(plan)
        cache.assert_called_once_with(plan, 'inspect')

    def test_large_scope_stays_on_stdin_not_environment_or_arguments(self):
        plan = target()
        plan['_html_scope'] = ['https://fixture.example.com/path-' + str(i) + '/' for i in range(5000)]
        with patch.object(parent_host, 'wp', return_value={}) as wp:
            parent_host.html_cache(plan, 'refresh')
        request = json.loads(wp.call_args.kwargs['stdin'])
        self.assertEqual(plan['_html_scope'], request['urls'])
        self.assertNotIn(plan['_html_scope'][0], wp.call_args.args[1])

    def test_complete_large_html_inventory_remains_bounded_and_exact(self):
        plan = target()
        plan['html_urls'] = ['https://fixture.example.com/path-' + str(i) + '/' for i in range(5000)]
        with patch.object(parent_host, 'html_cache', return_value={'urls': plan['html_urls']}):
            parent_host.live_html_scope(plan)
        self.assertEqual(plan['html_urls'], plan['_html_scope'])
        for urls in [[], [plan['url'] + '/'] * 2, [plan['url'] + '/'] * 20001, ['https://other.example.com/']]:
            with patch.object(parent_host, 'html_cache', return_value={'urls': urls}), self.assertRaises(ValueError):
                parent_host.live_html_scope(plan)

    def test_cache_hits_without_php_headers_require_exact_body_attestation(self):
        plan = target()
        plan['_html_scope'] = plan['html_urls']
        selected = {'components': {'mrn-base-stack': {'artifact_sha256': 'a' * 64}}}
        marker = '<meta name="mrn-parent-release" content="' + 'a' * 64 + '">'
        with patch.object(parent_host, 'html_cache', return_value={'refreshed_urls': plan['html_urls']}), \
             patch.object(parent_host, 'head', return_value={'content-type': 'text/html', 'cf-cache-status': 'HIT'}), \
             patch.object(parent_host, 'fetch', return_value=marker.encode()):
            result = parent_host.live_public_cache(plan, selected)
        self.assertEqual(2, len(result['samples']))

    def test_stale_missing_duplicate_or_script_only_marker_is_rejected(self):
        plan = target()
        plan['_html_scope'] = plan['html_urls']
        selected = {'components': {'mrn-base-stack': {'artifact_sha256': 'a' * 64}}}
        marker = '<meta name="mrn-parent-release" content="' + 'a' * 64 + '">'
        for html in [marker.replace('a' * 64, 'b' * 64), '<head></head>', marker + marker,
                     '<script>"' + marker + '"</script>']:
            with self.subTest(html=html), \
                 patch.object(parent_host, 'html_cache', return_value={'refreshed_urls': plan['html_urls']}), \
                 patch.object(parent_host, 'head', return_value={'content-type': 'text/html'}), \
                 patch.object(parent_host, 'fetch', return_value=html.encode()), \
                 self.assertRaisesRegex(ValueError, 'expected parent'):
                parent_host.live_public_cache(plan, selected)

    def test_header_cannot_contradict_cached_html(self):
        plan = target()
        plan['_html_scope'] = plan['html_urls']
        selected = {'components': {'mrn-base-stack': {'artifact_sha256': 'a' * 64}}}
        with patch.object(parent_host, 'html_cache', return_value={'refreshed_urls': plan['html_urls']}), \
             patch.object(parent_host, 'head', return_value={'content-type': 'text/html', 'x-mrn-parent-release': 'b' * 64}), \
             patch.object(parent_host, 'fetch', return_value=('<meta name="mrn-parent-release" content="' + 'a' * 64 + '">').encode()), \
             self.assertRaisesRegex(ValueError, 'disagree'):
            parent_host.live_public_cache(plan, selected)

    def test_partial_refresh_and_stale_rollback_fail(self):
        plan = target()
        plan['_html_scope'] = plan['html_urls']
        with patch.object(parent_host, 'html_cache', return_value={'refreshed_urls': []}), self.assertRaisesRegex(ValueError, 'reviewed scope'):
            parent_host.live_public_cache(plan, None)
        with patch.object(parent_host, 'html_cache', return_value={'refreshed_urls': plan['html_urls']}), \
             patch.object(parent_host, 'head', return_value={'content-type': 'text/html'}), \
             patch.object(parent_host, 'fetch', return_value=b'<meta name="mrn-parent-release" content="stale">'), \
             self.assertRaisesRegex(ValueError, 'expected parent'):
            parent_host.live_public_cache(plan, None)

    def test_wp_cli_sets_stdin_switch_without_putting_scope_in_environment(self):
        result = subprocess.CompletedProcess([], 0, 'MRN_RESULT={}\n', '')
        with patch.object(parent_host.subprocess, 'run', return_value=result) as run:
            parent_host.wp('/isolated', '<?php', stdin='{"urls":[]}')
        self.assertEqual('1', run.call_args.kwargs['env']['MRN_HTML_CACHE_STDIN'])
        self.assertEqual('{"urls":[]}', run.call_args.kwargs['input'])
        self.assertNotIn('MRN_HTML_CACHE_REQUEST', run.call_args.kwargs['env'])

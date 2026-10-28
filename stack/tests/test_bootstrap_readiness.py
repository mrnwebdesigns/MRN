import importlib.util
import io
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest
import urllib.error
from unittest.mock import Mock, patch

sys.path.insert(0, str(Path(__file__).parents[1] / 'scripts'))
import bootstrap_readiness as status


class Notifications(unittest.TestCase):
    def test_http_success_without_slack_ack_is_failure(self):
        response = Mock(status=200); response.read.return_value = b'invalid_payload'
        response.__enter__ = Mock(return_value=response); response.__exit__ = Mock(return_value=False)
        with patch.object(status.urllib.request, 'build_opener') as opener:
            opener.return_value.open.return_value = response
            self.assertEqual('failed', status.slack('https://hooks.slack.com/services/test', 'title', 'body')['status'])

    def test_http_rejection_retries_are_bounded_and_do_not_leak_webhook(self):
        error = urllib.error.HTTPError('https://hooks.slack.com/services/secret', 503, 'secret', {}, io.BytesIO())
        with patch.object(status.urllib.request, 'build_opener') as opener, patch.object(status.time, 'sleep'):
            opener.return_value.open.side_effect = error
            result = status.slack(error.url, 'title', 'body')
            self.assertEqual(3, opener.return_value.open.call_count)
            self.assertEqual({'status':'failed', 'http_status':503}, result)
            self.assertNotIn('secret', json.dumps(result))

    def test_terminal_event_is_deduplicated_and_unknown_outcome_is_not_resent(self):
        with tempfile.TemporaryDirectory() as temp, patch.object(status, 'slack', return_value={'status':'delivered'}) as send:
            config = {'slack_webhook':'https://hooks.slack.com/services/secret'}
            for _ in range(3):
                value = status.update(temp, '/home/site/htdocs/site.mrndev.io', 'ready', 'ready', notify=True, config=config)
            self.assertEqual(1, send.call_count)
            self.assertFalse(value['notification_failed'])
            send.return_value = {'status':'unknown'}
            for _ in range(3):
                value = status.update(temp, '/home/site/htdocs/site.mrndev.io', 'qualification', 'failed', notify=True, config=config)
            self.assertEqual(2, send.call_count)
            self.assertTrue(value['notification_failed'])
            self.assertEqual('failed', value['status'])
            self.assertNotIn('secret', json.dumps(value))

    def test_failed_delivery_survives_status_updates(self):
        with tempfile.TemporaryDirectory() as temp, patch.object(status, 'slack', return_value={'status':'failed','http_status':400}):
            value = status.update(temp, '/home/site/htdocs/site.mrndev.io', 'qualification', 'failed', notify=True,
                                  config={'slack_webhook':'https://hooks.slack.com/services/secret'})
            value = status.update(temp, '/home/site/htdocs/site.mrndev.io', 'qualification', 'pending')
            self.assertTrue(value['notification_failed'])
            self.assertTrue(value['events'])


class Certificates(unittest.TestCase):
    def test_maintenance_reports_custom_application_layouts_without_attempting_import(self):
        ordinary = {'domain':'site.mrndev.io','user':'owner','root':'/home/owner/htdocs/site.mrndev.io'}
        custom = {'domain':'manager.mrndev.io','user':'manager','root':'/home/manager/stack'}
        selected, excluded = self.cert.maintenance_targets([ordinary,custom])
        self.assertEqual([ordinary], selected)
        self.assertEqual([{'domain':'manager.mrndev.io','reason':'nonstandard-layout-requires-separate-adapter'}], excluded)

    @classmethod
    def setUpClass(cls):
        spec = importlib.util.spec_from_file_location('cert_sync', Path(__file__).parents[2] / 'tools/cloudpanel/cert_sync.py')
        cls.cert = importlib.util.module_from_spec(spec); spec.loader.exec_module(cls.cert)

    def test_wildcard_does_not_cover_nested_or_similar_domains(self):
        self.assertEqual('mrndev.io', self.cert.zone_for('lifebeyond.mrndev.io'))
        for value in ['deep.site.mrndev.io', 'site.mrndevXio', 'mrndev.io.evil.test']:
            with self.assertRaises(ValueError): self.cert.zone_for(value)

    def test_unchanged_new_site_is_verified_without_import_or_backup(self):
        with tempfile.TemporaryDirectory() as temp, patch.object(self.cert, 'identity', return_value=(Path('vhost'),Path('active'),Path('key'))), \
             patch.object(self.cert, 'approved', return_value=(Path('cert'),Path('key'),'a'*64)), \
             patch.object(self.cert, 'fingerprint', return_value='a'*64), patch.object(self.cert, 'served', return_value='a'*64), \
             patch.object(self.cert, 'backup') as backup, patch.object(self.cert, 'run') as run:
            result = self.cert.ensure({'domain':'new.mrndev.io'}, state_root=Path(temp))
            self.assertEqual('verified', result['status']); backup.assert_not_called(); run.assert_not_called()

    def test_import_failure_is_persistent_and_does_not_claim_success(self):
        with tempfile.TemporaryDirectory() as temp, patch.object(self.cert, 'identity', return_value=(Path('vhost'),Path('active'),Path('key'))), \
             patch.object(self.cert, 'approved', return_value=(Path('cert'),Path('key'),'a'*64)), \
             patch.object(self.cert, 'fingerprint', return_value='b'*64), \
             patch.object(self.cert, 'backup', return_value={'valid':True}), patch.object(self.cert.shutil, 'copyfile', side_effect=lambda a,b: b.touch()), \
             patch.object(self.cert, 'run', side_effect=ValueError('clpctl failed with exit 1')):
            result = self.cert.ensure({'domain':'new.mrndev.io'}, state_root=Path(temp))
            self.assertEqual('failed', result['status'])
            self.assertEqual(result, json.loads((Path(temp)/'new.mrndev.io.json').read_text()))
            self.assertIn('recovery', result)

    def test_backup_failure_prevents_certificate_import(self):
        with tempfile.TemporaryDirectory() as temp, patch.object(self.cert, 'identity', return_value=(None,None,None)), \
             patch.object(self.cert, 'approved', return_value=(None,None,'a'*64)), patch.object(self.cert, 'fingerprint', return_value='b'*64), \
             patch.object(self.cert, 'backup', side_effect=ValueError('backup failed')), patch.object(self.cert, 'run') as run:
            result = self.cert.ensure({'domain':'new.mrndev.io'}, state_root=Path(temp))
            self.assertEqual('failed', result['status']); run.assert_not_called()

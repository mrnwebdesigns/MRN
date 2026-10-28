import io
import json
from pathlib import Path
import sys
import unittest
import urllib.error
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))
import public_http
import host_controller


class PublicDiagnostics(unittest.TestCase):
    def test_default_identity_and_safe_1010_diagnostics(self):
        error = urllib.error.HTTPError('https://example.mrndev.io/?token=secret', 403, 'secret',
                                      {'cf-ray': '12345678abcdef00-IAD', 'set-cookie': 'secret'},
                                      io.BytesIO(b'error code: 1010 sensitive body'))
        with patch.object(public_http.urllib.request, 'urlopen', side_effect=error) as request:
            with self.assertRaises(public_http.PublicHTTPError) as caught:
                public_http.open_public(error.url)
        self.assertEqual(public_http.USER_AGENT, request.call_args.args[0].get_header('User-agent'))
        evidence = host_controller.failure_receipt(caught.exception)
        self.assertEqual(403, evidence['diagnostics'][0]['http']['http_status'])
        self.assertEqual(1010, evidence['diagnostics'][0]['http']['cloudflare_error'])
        self.assertNotIn('secret', json.dumps(evidence))
        self.assertNotIn('sensitive body', json.dumps(evidence))

    def test_legacy_http_error_keeps_status_and_ray_without_url_secrets(self):
        error = urllib.error.HTTPError('https://user:secret@example.mrndev.io/path?key=secret', 526, 'secret',
                                      {'cf-ray': '12345678abcdef00-IAD'}, io.BytesIO())
        result = public_http.diagnostics(error)
        self.assertEqual(526, result['http_status'])
        self.assertEqual('https://example.mrndev.io/path', result['url'])
        self.assertNotIn('secret', json.dumps(result))

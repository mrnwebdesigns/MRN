import io
import json
from pathlib import Path
import sys
import unittest
import urllib.error
from unittest.mock import patch, Mock
from email.utils import formatdate
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import threading
import hashlib

sys.path.insert(0, str(Path(__file__).parents[1]))
import public_http
import host_controller
import verify_public_assets


class PublicDiagnostics(unittest.TestCase):
    def setUp(self):
        public_http._last_request.clear()

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
        error.close()


class RateLimitPolicy(unittest.TestCase):
    def setUp(self):
        public_http._last_request.clear()
        self.clock = 1000.0
        self.sleeps = []
        self.patches = [patch.object(public_http.time, 'monotonic', side_effect=lambda: self.clock),
                        patch.object(public_http.time, 'time', return_value=1700000000),
                        patch.object(public_http.time, 'sleep', side_effect=self.sleep)]
        for item in self.patches:
            item.start()

    def tearDown(self):
        for item in reversed(self.patches):
            item.stop()

    def sleep(self, delay):
        self.sleeps.append(delay)
        self.clock += delay

    def error(self, code=429, headers=None):
        return urllib.error.HTTPError('https://example.org/asset?token=secret', code, 'secret',
                                      headers or {}, io.BytesIO(b'private body'))

    def test_pacing_is_shared_by_same_origin_but_not_other_origins(self):
        with patch.object(public_http.urllib.request, 'urlopen', return_value=Mock()):
            for url in ['https://example.org/a', 'https://example.org/b', 'https://other.org/a']:
                public_http.open_public(url)
        self.assertEqual([1.0], self.sleeps)

    def test_retry_after_seconds_and_http_date_are_honored_without_changing_request(self):
        for header in ['7', formatdate(1700000007, usegmt=True)]:
            with self.subTest(header=header):
                self.sleeps.clear()
                public_http._last_request.clear()
                error, response = self.error(headers={'Retry-After': header}), Mock()
                with patch.object(public_http.urllib.request, 'urlopen', side_effect=[error, response]) as request:
                    self.assertIs(response, public_http.open_public(error.url, {'Accept-Encoding': 'gzip'}, timeout=9))
                self.assertEqual([7], self.sleeps)
                self.assertIs(request.call_args_list[0].args[0], request.call_args_list[1].args[0])
                self.assertEqual(9, request.call_args.kwargs['timeout'])
                self.assertTrue(error.fp is None or error.fp.closed)

    def test_missing_or_invalid_retry_after_uses_bounded_backoff(self):
        errors = [self.error(), self.error(headers={'Retry-After': 'private-invalid-header'}), self.error()]
        with patch.object(public_http.urllib.request, 'urlopen', side_effect=errors + [Mock()]) as request:
            public_http.open_public(errors[0].url)
        self.assertEqual([15, 30, 60], self.sleeps)
        self.assertEqual(4, request.call_count)

    def test_persistent_rate_limit_fails_closed_at_attempt_bound(self):
        with patch.object(public_http.urllib.request, 'urlopen', side_effect=[self.error() for _ in range(4)]) as request:
            with self.assertRaises(public_http.PublicHTTPError) as caught:
                public_http.open_public('https://example.org/asset?token=secret')
        self.assertEqual(4, request.call_count)
        self.assertEqual(105, caught.exception.diagnostics['retry_wait_seconds'])
        self.assertEqual(4, caught.exception.diagnostics['attempts'])
        self.assertNotIn('secret', str(caught.exception))
        self.assertNotIn('private body', str(caught.exception))

    def test_server_delay_beyond_remaining_budget_is_not_shortened(self):
        for delays, expected_calls, expected_sleeps in [(['121'], 1, []), (['80', '80'], 2, [80])]:
            with self.subTest(delays=delays):
                public_http._last_request.clear()
                self.sleeps.clear()
                with patch.object(public_http.urllib.request, 'urlopen', side_effect=[
                        self.error(headers={'Retry-After': value}) for value in delays]) as request:
                    with self.assertRaises(public_http.PublicHTTPError):
                        public_http.open_public('https://example.org/asset')
                self.assertEqual(expected_calls, request.call_count)
                self.assertEqual(expected_sleeps, self.sleeps)

    def test_zero_or_past_retry_after_still_observes_request_pacing(self):
        for value in ['0', formatdate(1699999990, usegmt=True)]:
            public_http._last_request.clear()
            self.sleeps.clear()
            with patch.object(public_http.urllib.request, 'urlopen', side_effect=[self.error(headers={'Retry-After': value}), Mock()]):
                public_http.open_public('https://example.org/asset')
            self.assertEqual([0, 1.0], self.sleeps)

    def test_access_denial_and_transport_errors_are_not_retried(self):
        for error in [self.error(code=403), self.error(code=503), urllib.error.URLError('secret')]:
            with self.subTest(error=type(error).__name__), patch.object(public_http.urllib.request, 'urlopen', side_effect=error) as request:
                with self.assertRaises(public_http.PublicHTTPError):
                    public_http.open_public('https://example.org/asset')
                self.assertEqual(1, request.call_count)


class RealHTTPRateLimit(unittest.TestCase):
    def test_rate_limit_recovery_still_checks_both_phases_and_rejects_stale_bytes(self):
        body = b'body { color: blue; }'
        calls = []
        stale = False

        class Handler(BaseHTTPRequestHandler):
            def do_GET(self):
                calls.append(self.path)
                if len(calls) == 2:
                    self.send_response(429)
                    self.send_header('Retry-After', '0')
                    self.end_headers()
                    return
                self.send_response(200)
                self.send_header('Content-Type', 'text/html' if self.path == '/' else 'text/css')
                self.end_headers()
                self.wfile.write(b'<link rel="stylesheet" href="/wp-content/mrn-assets/child/new/style.min.css">'
                                 if self.path == '/' else (b'stale' if stale else body))

            def log_message(self, *args):
                pass

        server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
        worker = threading.Thread(target=server.serve_forever, daemon=True)
        worker.start()
        origin = 'http://127.0.0.1:' + str(server.server_port)
        manifest = {'slug': 'child', 'generation': 'new', 'public_path': 'mrn-assets/child/new',
                    'assets': {'style.css': {'file': 'style.min.css'}},
                    'static_files': {'style.min.css': {'sha256': hashlib.sha256(body).hexdigest(), 'bytes': len(body)}}}
        try:
            # Keep the verifier's canonical HTTPS contract; only the local
            # transport fixture maps that identity to its loopback HTTP server.
            def fetch(url, types):
                return verify_public_assets.fetch(url.replace('https://example.org', origin), types)
            with patch.object(public_http, 'REQUEST_INTERVAL', 0):
                proof = verify_public_assets.verify(manifest, ['https://example.org/'], fetch)
                self.assertEqual(['first', 'warm'], [row['phase'] for row in proof['pages']])
                self.assertEqual(['/', '/wp-content/mrn-assets/child/new/style.min.css',
                                  '/wp-content/mrn-assets/child/new/style.min.css', '/',
                                  '/wp-content/mrn-assets/child/new/style.min.css'], calls)
                stale = True
                with self.assertRaisesRegex(ValueError, 'checksum differs'):
                    verify_public_assets.verify(manifest, ['https://example.org/'], fetch)
        finally:
            server.shutdown()
            server.server_close()
            worker.join()

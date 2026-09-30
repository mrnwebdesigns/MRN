"""Reproduce stale HTML/CSS over HTTP, scoped refresh, and retained rollback."""
import hashlib
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import sys
from pathlib import Path
import threading
import unittest
from urllib.request import urlopen
from urllib.parse import urlsplit

sys.path.insert(0, str(Path(__file__).parents[1]))
from verify_public_assets import verify


class GlovesWarmCacheRegression(unittest.TestCase):
    def test_affected_html_refresh_keeps_old_assets_and_unrelated_cache(self):
        generations = {'old': b'.gloves{color:red}', 'new': b'.gloves{color:blue}'}
        current = ['old']
        cache = {'/unrelated/': b'untouched cached page'}
        paths = {key: '/wp-content/mrn-assets/child/' + key + '/style.min.css' for key in generations}
        class Handler(BaseHTTPRequestHandler):
            def do_GET(self):
                if self.path in paths.values():
                    generation = next(key for key, path in paths.items() if path == self.path)
                    body = generations[generation]
                    kind = 'text/css'
                else:
                    if self.path not in cache:
                        cache[self.path] = ('<html><link rel="stylesheet" href="' + paths[current[0]] + '"></html>').encode()
                    body = cache[self.path]
                    kind = 'text/html'
                self.send_response(200)
                self.send_header('Content-Type', kind)
                self.end_headers()
                self.wfile.write(body)
            def log_message(self, *args):
                pass
        server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
        thread = threading.Thread(target=server.serve_forever, daemon=True)
        thread.start()
        try:
            def fetch(url, types):
                with urlopen('http://127.0.0.1:' + str(server.server_port) + urlsplit(url).path) as response:
                    self.assertIn(response.headers.get_content_type(), types)
                    return response.read()
            def manifest(generation):
                body = generations[generation]
                return {'slug': 'child', 'generation': generation, 'public_path': 'mrn-assets/child/' + generation,
                        'assets': {'style.css': {'file': 'style.min.css'}},
                        'static_files': {'style.min.css': {'sha256': hashlib.sha256(body).hexdigest(), 'bytes': len(body)}}}
            page = 'https://fixture.invalid/affected/'
            verify(manifest('old'), [page], fetch)  # Warm the old HTML and CSS.
            current[0] = 'new'
            with self.assertRaisesRegex(ValueError, 'different asset generation'):
                verify(manifest('new'), [page], fetch)
            del cache['/affected/']  # Only this affected HTML entry is refreshed.
            verify(manifest('new'), [page], fetch)
            self.assertEqual(b'untouched cached page', cache['/unrelated/'])
            self.assertEqual(generations['old'], fetch('https://fixture.invalid' + paths['old'], {'text/css'}))
            current[0] = 'old'
            del cache['/affected/']
            verify(manifest('old'), [page], fetch)
            self.assertEqual(generations['new'], fetch('https://fixture.invalid' + paths['new'], {'text/css'}))
        finally:
            server.shutdown()
            server.server_close()
            thread.join()

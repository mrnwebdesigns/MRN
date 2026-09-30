import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

FIXTURE = Path(__file__).with_name('html-cache-fixture.php')

class ScopedHTMLCache(unittest.TestCase):
    def invoke(self, provider, action='refresh', urls=None, **extra):
        with tempfile.TemporaryDirectory() as directory:
            for host in ('example.org', 'other.org'):
                cached = Path(directory) / 'cache/cache-enabler' / host / 'news/page/2/index.html'
                cached.parent.mkdir(parents=True)
                cached.write_text('<html>old theme</html>')
            request = dict(url='https://example.org', provider=provider, action=action,
                           urls=urls if urls is not None else ['https://example.org/', 'https://example.org/page-1/'])
            response = subprocess.run(['php', str(FIXTURE)], capture_output=True, text=True,
                env=dict(os.environ, FIXTURE_DIR=directory, MRN_HTML_CACHE_REQUEST=json.dumps(request), **extra))
            return response

    def test_nexcess_uses_only_exact_page_api(self):
        response = self.invoke('nexcess')
        self.assertEqual(0, response.returncode, response.stderr)
        calls = json.loads(response.stdout.split('CALLS=')[1])
        self.assertEqual([['https://example.org/', 'page'], ['https://example.org/page-1/', 'page']], calls)

    def test_inventory_includes_cached_pagination_only_for_selected_host(self):
        response = self.invoke('nexcess', 'inspect')
        self.assertEqual(0, response.returncode, response.stderr)
        receipt = json.loads(response.stdout.splitlines()[0][11:])
        self.assertIn('https://example.org/news/page/2/', receipt['urls'])
        self.assertFalse(any('other.org' in u for u in receipt['urls']))
        self.assertEqual([], receipt['refreshed_urls'])
        self.assertEqual([], json.loads(response.stdout.split('CALLS=')[1]))

    def test_siteground_never_purges_child_paths(self):
        response = self.invoke('siteground')
        self.assertEqual(0, response.returncode, response.stderr)
        self.assertTrue(all(row[1] is False for row in json.loads(response.stdout.split('CALLS=')[1])))
        self.assertNotEqual(0, self.invoke('siteground', FILE_CACHE='1').returncode)
        self.assertNotEqual(0, self.invoke('siteground', FAIL_PURGE='1').returncode)

    def test_wpengine_patterns_are_exact_and_escaped(self):
        response = self.invoke('wpengine', urls=['https://example.org/a+b/'])
        self.assertEqual(0, response.returncode, response.stderr)
        call = json.loads(response.stdout.split('CALLS=')[1])[0]
        self.assertEqual('PURGE', call[0])
        self.assertEqual('^example\\.org$', call[2]['X-Purge-Host'])
        self.assertEqual(r'^(/a\+b/)(?:\?.*)?$', call[2]['X-Purge-Path'])
        self.assertNotEqual(0, self.invoke('wpengine', FAIL_PURGE='1').returncode)

    def test_cross_site_static_admin_and_ambiguous_routes_are_rejected(self):
        for url in ['https://other.org/', 'https://example.org:443/',
                    'https://example.org/a/?x=1', 'https://example.org/a/#x',
                    'https://example.org/%2e%2e/', 'https://example.org/a%2fb/',
                    'https://example.org/wp-content/a.css', 'https://example.org/wp-admin/',
                    'https://example.org/wp-json/', 'https://example.org/feed.xml']:
            with self.subTest(url=url):
                self.assertNotEqual(0, self.invoke('nexcess', urls=[url]).returncode)

import json
from pathlib import Path
import sys
import tempfile
import unittest
sys.path.insert(0, str(Path(__file__).parents[1]))
from cache_policy import canonical_pages, verify_cloudpanel_origin, verify_uncached_html


class CacheScopeContract(unittest.TestCase):
    def test_unknown_or_enabled_origin_cache_is_not_silently_ignored(self):
        with tempfile.TemporaryDirectory() as temporary:
            settings = Path(temporary).resolve() / 'settings.json'
            wp = {'wp_cache': False, 'advanced_cache': False, 'cache_plugins': []}
            for enabled in [True, None, 0]:
                settings.write_text(json.dumps({'enabled': enabled}))
                with self.subTest(enabled=enabled), self.assertRaises(ValueError):
                    verify_cloudpanel_origin(settings, wp)
            settings.write_text('{"enabled":false}')
            self.assertEqual('disabled', verify_cloudpanel_origin(settings, wp)['html_cache'])
            with self.assertRaises(ValueError):
                verify_cloudpanel_origin(settings, {**wp, 'advanced_cache': True})

    def test_cross_site_urls_bypass_queries_and_empty_invalidation_scope_fail(self):
        for pages in [[], ['https://other.org/'], ['https://example.org/?bypass=1'], ['http://example.org/'],
                      ['https://example.org/a/../b/'], ['https://example.org/', 'https://example.org/']]:
            with self.subTest(pages=pages), self.assertRaises(ValueError):
                canonical_pages('https://example.org', pages)

    def test_first_and_warm_html_are_proven_without_any_purge(self):
        calls = []
        def headers(url):
            calls.append(url)
            return {'content-type': 'text/html; charset=UTF-8', 'cf-cache-status': 'DYNAMIC'}
        result = verify_uncached_html('https://example.org', ['https://example.org/'], headers)
        self.assertEqual(['https://example.org/'] * 2, calls)
        self.assertEqual([], result['purged_urls'])
        self.assertFalse(result['object_cache_changed'])
        self.assertFalse(result['transients_changed'])
        for changed in [{'cf-cache-status': 'HIT'}, {'cf-cache-status': 'MISS'}, {'cf-cache-status': ''}, {'age': '30'}]:
            with self.subTest(changed=changed), self.assertRaises(ValueError):
                verify_uncached_html('https://example.org', ['https://example.org/'],
                    lambda url: {'content-type': 'text/html', 'cf-cache-status': 'DYNAMIC', **changed})

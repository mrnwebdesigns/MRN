import hashlib
import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('public_assets', Path(__file__).parents[1] / 'verify_public_assets.py')
public = importlib.util.module_from_spec(spec)
spec.loader.exec_module(public)


class PublicAssetEvidence(unittest.TestCase):
    def setUp(self):
        self.body = b'.gloves{color:#00f}'
        self.manifest = {'slug': 'child', 'generation': 'new', 'public_path': 'mrn-assets/child/new',
                         'assets': {'style.css': {'file': 'style.min.css'}},
                         'static_files': {'style.min.css': {'sha256': hashlib.sha256(self.body).hexdigest(), 'bytes': len(self.body)}}}
        self.page = 'https://example.org/'
        self.url = 'https://example.org/wp-content/mrn-assets/child/new/style.min.css'

    def test_gloves_stale_cdn_bytes_fail_even_when_html_has_new_url(self):
        def fetch(url, types):
            return ('<html><link rel="stylesheet" href="' + self.url + '"></html>').encode() if url == self.page else b'.gloves{color:red}'
        with self.assertRaisesRegex(ValueError, 'checksum differs'):
            public.verify(self.manifest, [self.page], fetch)

    def test_old_html_url_is_not_hidden_by_fetching_origin_directly(self):
        for old in ['/wp-content/themes/child/style.css?ver=1', '/wp-content/mrn-assets/child/old/style.min.css']:
            with self.subTest(old=old), self.assertRaises(ValueError):
                public.verify(self.manifest, [self.page], lambda url, types: ('<link rel="stylesheet" href="' + old + '">').encode())

    def test_first_and_warm_normal_public_pages_and_asset_bytes_are_checked(self):
        calls = []
        def fetch(url, types):
            calls.append(url)
            return ('<html><link rel="stylesheet" href="' + self.url + '"></html>').encode() if url == self.page else self.body
        result = public.verify(self.manifest, [self.page], fetch)
        self.assertEqual([self.page, self.url, self.page, self.url], calls)
        self.assertEqual(['first', 'warm'], [r['phase'] for r in result['pages']])
        with self.assertRaises(ValueError): public.verify(self.manifest, [self.page + '?bypass=1'], fetch)

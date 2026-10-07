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

    def test_content_addressed_image_preload_can_keep_an_existing_query(self):
        image = b'fixture image'
        self.manifest['static_files']['logo.png'] = {'sha256': hashlib.sha256(image).hexdigest(), 'bytes': len(image)}
        logo = self.url.replace('style.min.css', 'logo.png?v=2')
        def fetch(url, types):
            if url == self.page:
                return ('<link rel="stylesheet" href="' + self.url + '"><link rel="preload" href="' + logo + '">').encode()
            return image if url == logo else self.body
        result = public.verify(self.manifest, [self.page], fetch)
        self.assertIn(logo, result['pages'][0]['assets'])
        with self.assertRaisesRegex(ValueError, 'different asset generation'):
            public.verify(self.manifest, [self.page], lambda url, types: ('<link rel="stylesheet" href="' + self.url + '?ver=old">').encode())

    def test_homepage_variant_is_scoped_and_still_checksum_checked(self):
        self.manifest['assets']['style-home.css'] = {'file': 'style-home.min.css'}
        self.manifest['static_files']['style-home.min.css'] = self.manifest['static_files']['style.min.css']
        self.manifest['stylesheet_routes'] = {'/': ['style-home.css', 'style.css']}
        variant = self.url.replace('style.min.css', 'style-home.min.css')
        def fetch(url, types):
            return ('<link rel="stylesheet" href="' + variant + '">').encode() if url.startswith(self.page) and '/wp-content/' not in url else self.body
        result = public.verify(self.manifest, [self.page], fetch)
        self.assertIn(variant, result['pages'][0]['assets'])
        with self.assertRaisesRegex(ValueError, 'did not reference'):
            public.verify(self.manifest, [self.page + 'shop/'], fetch)
        with self.assertRaisesRegex(ValueError, 'checksum differs'):
            public.verify(self.manifest, [self.page], lambda url, types: fetch(url, types) if url == self.page else b'stale CSS')

    def test_preload_alone_does_not_satisfy_stylesheet_contract(self):
        def fetch(url, types):
            return ('<link rel="preload" as="style" href="' + self.url + '">').encode() if url == self.page else self.body
        with self.assertRaisesRegex(ValueError, 'did not reference'):
            public.verify(self.manifest, [self.page], fetch)

    def test_invalid_route_declarations_fail_closed(self):
        for routes in [[], {'/': []}, {'/': ['missing.css']}, {'//other.test/': ['style.css']},
                       {'/shop/../': ['style.css']}, {'/?x=1': ['style.css']}, {'/%2f': ['style.css']}]:
            self.manifest['stylesheet_routes'] = routes
            with self.subTest(routes=routes), self.assertRaisesRegex(ValueError, 'Invalid stylesheet route'):
                public.verify(self.manifest, [self.page], lambda url, types: b'')

    def test_parent_native_root_slug_layout_verifies_its_assets_and_preserves_child(self):
        generation = 'a' * 64
        self.manifest.update(scope='parent-theme', slug='mrn-base-stack', generation=generation,
                             public_path='mrn-assets/' + generation + '/mrn-base-stack')
        parent = self.page + 'wp-content/' + self.manifest['public_path'] + '/style.min.css'
        child = self.page + 'wp-content/mrn-assets/child/kept/style.min.css'
        calls = []
        def fetch(url, types):
            calls.append(url)
            if url == self.page:
                return ('<link rel="stylesheet" href="' + parent + '"><link rel="stylesheet" href="' + child + '">').encode()
            return self.body
        result = public.verify(self.manifest, [self.page], fetch)
        self.assertIn(parent, result['pages'][0]['assets'])
        self.assertNotIn(child, calls)
        old = parent.replace(generation, 'b' * 64)
        with self.assertRaisesRegex(ValueError, 'different asset generation'):
            public.verify(self.manifest, [self.page], lambda url, types: ('<link rel="stylesheet" href="' + old + '">').encode())

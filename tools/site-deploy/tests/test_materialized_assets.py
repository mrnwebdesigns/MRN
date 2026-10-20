import hashlib
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))
import host_controller as host


class MaterializedAssetAdoption(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.plan = dict(root=str(self.root), slug='child', url='https://site.example', pages=['https://site.example/'])
        self.css = b'body { color: #123456; }'
        self.sha = hashlib.sha256(self.css).hexdigest()
        self.public = '/wp-content/mrn-assets/child/' + 'a'*64 + '/style.min.css'
        self.html = ('<html><link rel="stylesheet" href="' + self.public + '"></html>').encode()
        self.manifest = dict(schema=1, scope='child-theme', slug='child', generation='a'*64,
                             public_path='mrn-assets/child/'+'a'*64,
                             assets={'style.css':dict(file='style.min.css')},
                             static_files={'style.min.css':dict(sha256=self.sha, bytes=len(self.css))})
        self.path = self.root/'wp-content/themes/child/mrn-assets.json'
        self.path.parent.mkdir(parents=True)
        self.write_manifest()

    def write_manifest(self):
        self.path.write_text(json.dumps(self.manifest))

    def fetch(self, url, types):
        if url == self.plan['url'] + '/':
            return self.html
        self.assertEqual(self.plan['url'] + self.public, url)
        self.assertIn('text/css', types)
        return self.css

    def test_migrated_assets_are_verified_and_retained_for_rollback_comparison(self):
        with patch.object(host, 'fetch', side_effect=self.fetch) as fetch:
            assets = host.legacy_assets(self.plan)
            self.assertEqual({self.plan['url']+self.public:dict(sha256=self.sha,types=['text/css'])}, assets)
            host.verify_legacy_assets(assets)
            self.assertEqual(6, fetch.call_count)

    def test_changed_asset_bytes_are_rejected_before_adoption(self):
        self.css += b'corrupted'
        with patch.object(host, 'fetch', side_effect=self.fetch), self.assertRaisesRegex(ValueError, 'checksum differs'):
            host.legacy_assets(self.plan)

    def test_wrong_manifest_identity_or_generation_is_rejected(self):
        for change in (dict(slug='other'), dict(scope='parent-theme'), dict(schema=2),
                       dict(generation='../other'), dict(public_path='mrn-assets/another/'+'a'*64)):
            with self.subTest(change=change):
                original = dict(self.manifest)
                self.manifest.update(change)
                self.write_manifest()
                with patch.object(host, 'fetch', side_effect=self.fetch), self.assertRaises(ValueError):
                    host.legacy_assets(self.plan)
                self.manifest = original

    def test_missing_or_symlinked_manifest_does_not_enable_adoption(self):
        self.path.unlink()
        with patch.object(host, 'fetch', side_effect=self.fetch), self.assertRaisesRegex(ValueError, 'No legacy'):
            host.legacy_assets(self.plan)
        other = self.root/'other.json'
        other.write_text(json.dumps(self.manifest))
        self.path.symlink_to(other)
        with patch.object(host, 'fetch', side_effect=self.fetch), self.assertRaisesRegex(ValueError, 'No legacy'):
            host.legacy_assets(self.plan)

    def test_foreign_origin_and_wrong_stylesheet_generation_are_rejected(self):
        for url in ('https://other.example'+self.public, self.public.replace('a'*64,'b'*64)):
            self.html = ('<html><link rel="stylesheet" href="'+url+'"></html>').encode()
            with patch.object(host, 'fetch', side_effect=self.fetch), self.assertRaises(ValueError):
                host.legacy_assets(self.plan)

    def test_existing_mutable_asset_adoption_is_unchanged(self):
        self.path.unlink()
        url = self.plan['url'] + '/wp-content/themes/child/style.css'
        self.html = ('<html><link rel="stylesheet" href="'+url+'"></html>').encode()
        def fetch(target, types):
            return self.html if target == self.plan['url']+'/' else self.css
        with patch.object(host, 'fetch', side_effect=fetch), patch.object(host, 'verify_public') as verify:
            self.assertEqual({url:dict(sha256=self.sha, types=['text/css'])}, host.legacy_assets(self.plan))
            verify.assert_not_called()

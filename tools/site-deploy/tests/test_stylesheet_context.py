"""Immutable manifest context selection and unchanged URL resolution in PHP."""
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest


class StylesheetContextTest(unittest.TestCase):
    def test_native_fallback_and_url_contract(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            shutil.copyfile(Path(__file__).resolve().parents[1] / 'release-assets.php', root / 'release-assets.php')
            manifest = {'schema': 1, 'public_path': 'mrn-assets/child/generation',
                        'assets': {'style.css': {'file': 'style.min.css'}, 'home.css': {'file': 'home.min.css'}},
                        'stylesheet_contexts': {'home.css': {'required_body_classes': ['home', 'page'],
                                                            'absent_body_classes': ['single-post']}}}
            (root / 'mrn-assets.json').write_text(json.dumps(manifest))
            (root / 'test.php').write_text('''<?php
define('ABSPATH', __DIR__);
define('PHP_URL_PATH_TEST', 1);
function add_filter(...$args) {}
function wp_parse_url($url, $component) { return parse_url($url, $component); }
function trailingslashit($path) { return rtrim($path, '/') . '/'; }
function get_stylesheet_directory_uri() { return 'https://example.test/wp-content/themes/child'; }
function content_url($path) { return 'https://example.test/wp-content' . $path; }
require __DIR__ . '/release-assets.php';
echo json_encode([
 mrn_site_release_stylesheet_matches_body('home.css', ['home','page']),
 mrn_site_release_stylesheet_matches_body('home.css', ['home']),
 mrn_site_release_stylesheet_matches_body('home.css', ['home','page','single-post']),
 mrn_site_release_stylesheet_matches_body('style.css', ['home','page']),
 mrn_site_release_stylesheet_matches_body('missing.css', ['home','page']),
 mrn_site_release_asset_url('https://example.test/wp-content/themes/child/style.css?ver=1'),
 mrn_site_release_asset_url('https://example.test/wp-content/themes/child/home.css'),
 mrn_site_release_asset_url('https://other.test/wp-content/themes/child/style.css'),
]);
''')
            result = subprocess.run(['php', str(root / 'test.php')], text=True, capture_output=True, check=True)
            self.assertEqual(json.loads(result.stdout), [True, False, False, False, False,
                'https://example.test/wp-content/mrn-assets/child/generation/style.min.css',
                'https://example.test/wp-content/mrn-assets/child/generation/home.min.css',
                'https://other.test/wp-content/themes/child/style.css'])
            self.assertEqual(result.stderr, '')


if __name__ == '__main__':
    unittest.main()

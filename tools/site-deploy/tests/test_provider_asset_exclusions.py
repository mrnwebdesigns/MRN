"""Exercise native optimizer hooks against manifest-owned WordPress registries."""
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest


class ProviderAssetExclusions(unittest.TestCase):
    def test_styles_and_scripts_keep_immutable_urls_and_preserve_unowned_assets(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            shutil.copyfile(Path(__file__).resolve().parents[1] / 'release-assets.php', root / 'release-assets.php')
            manifest = {'schema': 1, 'public_path': 'mrn-assets/child/generation',
                        'assets': {'css/custom.css': {'file': 'css/custom.min.css'},
                                   'js/global.js': {'file': 'js/global.min.js'}}}
            (root / 'mrn-assets.json').write_text(json.dumps(manifest))
            (root / 'test.php').write_text('''<?php
define('ABSPATH', __DIR__);
$hooks=[];
function add_filter($hook,$callback,$priority) { $GLOBALS['hooks'][$hook]=$callback; }
function wp_parse_url($url,$component=-1) { return parse_url($url,$component); }
function trailingslashit($value) { return rtrim($value,'/').'/'; }
function get_stylesheet_directory_uri() { return 'https://example.test/wp-content/mrn-assets/child/generation'; }
function content_url($path) { return 'https://example.test/wp-content'.$path; }
require __DIR__.'/release-assets.php';
$base=get_stylesheet_directory_uri();
$wp_styles=(object)['registered'=>[
 'child'=>(object)['src'=>$base.'/css/custom.css?ver=1'],
 'external'=>(object)['src'=>'https://other.test/wp-content/mrn-assets/child/generation/css/custom.css'],
 'sibling'=>(object)['src'=>$base.'-other/css/custom.css'],
 'unknown'=>(object)['src'=>$base.'/css/unknown.css'],
 'inline'=>(object)['src'=>false],
 'absent'=>(object)[],
]];
$wp_scripts=(object)['registered'=>[
 'script'=>(object)['src'=>$base.'/js/global.js'],
 'parent'=>(object)['src'=>'https://example.test/wp-content/themes/parent/app.js'],
]];
$results=[];
foreach(['sgo_css_minify_exclude','sgo_css_combine_exclude','sgo_js_minify_exclude','sgo_javascript_combine_exclude'] as $hook){
 $results[$hook]=$hooks[$hook](['existing','existing']);
}
$results['empty']=mrn_site_release_optimizer_exclusions(['existing'],null);
$results['resolved_css']=mrn_site_release_asset_url($base.'/css/custom.css?ver=1');
echo json_encode($results);
''')
            process = subprocess.run(['php', str(root / 'test.php')], capture_output=True, text=True, check=True)
            result = json.loads(process.stdout)
            for hook in ('sgo_css_minify_exclude', 'sgo_css_combine_exclude'):
                self.assertEqual(result[hook], ['existing', 'child'])
            for hook in ('sgo_js_minify_exclude', 'sgo_javascript_combine_exclude'):
                self.assertEqual(result[hook], ['existing', 'script'])
            self.assertEqual(result['empty'], ['existing'])
            self.assertEqual(result['resolved_css'], 'https://example.test/wp-content/mrn-assets/child/generation/css/custom.min.css')
            self.assertEqual(process.stderr, '')


if __name__ == '__main__':
    unittest.main()

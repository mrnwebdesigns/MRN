"""Exercise editor retirement with WordPress's revision-to-parent write behavior."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

MIGRATION = Path(__file__).parents[1] / 'scripts/migrations/retire-duplicate-editors.php'
RETIRED = ['mrn-acf-character-count/mrn-acf-character-count.php',
           'mrn-ai-assist/mrn-ai-assist.php', 'mrn-editor-tools/mrn-editor-tools.php',
           'mrn-seo-helper/mrn-seo-helper.php']

STUB = r'''<?php
define('WP_CLI', true); define('ABSPATH', __DIR__.'/');
$statePath=getenv('EDITOR_TEST_STATE');
$state=json_decode(file_get_contents($statePath),true);
function persist(){global $state,$statePath;file_put_contents($statePath,json_encode($state));}
function get_option($key,$default=false){global $state;return $state['options'][$key]??$default;}
function update_option($key,$value,$autoload=null){global $state;$state['options'][$key]=$value;persist();return true;}
function add_option($key,$value,$deprecated='',$autoload=null){global $state;if(array_key_exists($key,$state['options']))return false;return update_option($key,$value);}
function delete_option($key){global $state;unset($state['options'][$key]);persist();return true;}
function is_multisite(){return false;}
function home_url(){return 'https://fixture.localhost';}
function is_plugin_active($file){return in_array($file,get_option('active_plugins',[]),true);}
function get_plugins(){return array_fill_keys(get_option('active_plugins',[]),array('Version'=>'1.0'));}
function deactivate_plugins($files){update_option('active_plugins',array_values(array_diff(get_option('active_plugins'),$files)));}
function activate_plugin($file,$redirect='',$network=false,$silent=false){$active=get_option('active_plugins');$active[]=$file;update_option('active_plugins',array_values(array_unique($active)));return null;}
function is_wp_error($result){return false;}
function wp_json_encode($value){return json_encode($value);}
function wp_slash($value){return addslashes($value);}
function wp_is_post_revision($id){global $state;return $state['revisions'][(string)$id]??false;}
function get_post_meta($id,$key,$single=false){global $state;$values=$state['meta'][(string)$id][$key]??[];return $single?($values[0]??''):$values;}
function metadata_exists($type,$id,$key){return count(get_post_meta($id,$key,false))>0;}
function add_post_meta($id,$key,$value,$unique=false){
 global $state;$id=wp_is_post_revision($id)?:$id;
 if($unique&&metadata_exists('post',$id,$key))return false;
 $state['meta'][(string)$id][$key][]=stripslashes($value);persist();return 1;
}
function delete_post_meta($id,$key,$value){
 global $state;$id=wp_is_post_revision($id)?:$id;
 $remaining=array_values(array_filter(get_post_meta($id,$key,false),static fn($x)=>$x!==$value));
 if($remaining){$state['meta'][(string)$id][$key]=$remaining;}else{unset($state['meta'][(string)$id][$key]);}
 persist();return true;
}
class Database {
 public $postmeta='wp_postmeta';public $last_error='';
 function prepare($query,...$args){return $query;}
 function get_col($query){global $state;return array_keys($state['meta']);}
}
$wpdb=new Database();
class WP_CLI {
 static function error($message){fwrite(STDERR,$message);exit(1);}
 static function line($message){echo $message."\n";}
 static function success($message){echo $message."\n";}
}
$args=array_slice($argv,2);require $argv[1];
'''


class EditorRetirement(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.stub = self.root / 'fixture.php'
        self.stub.write_text(STUB)
        include = self.root / 'wp-admin/includes'
        include.mkdir(parents=True)
        (include / 'plugin.php').write_text('<?php\n')
        self.state_path = self.root / 'state.json'
        self.before = {
            'options': {'active_plugins': ['wp-seopress/seopress.php',
                'wp-seopress-pro/seopress-pro.php', *RETIRED], 'unrelated': 'retained'},
            'revisions': {'12': 11, '13': 11},
            'meta': {'11': {'mrn_seo_title': ['Current title'],
                            '_seopress_titles_title': ['Current title']},
                     '12': {'mrn_seo_title': ['Old revision title']},
                     '13': {'mrn_seo_description': ['Autosaved description']},
                     '21': {'mrn_seo_title': ["A client's draft title"],
                            'mrn_seo_description': ['Draft description']}}}
        self.write(self.before)

    def tearDown(self):
        self.temp.cleanup()

    def write(self, state):
        self.state_path.write_text(json.dumps(state))

    def read(self):
        return json.loads(self.state_path.read_text())

    def run_mode(self, mode, digest=None):
        return subprocess.run(['php', str(self.stub), str(MIGRATION), mode,
                               *([digest] if digest else [])],
                              env={**os.environ, 'EDITOR_TEST_STATE': str(self.state_path)},
                              capture_output=True, text=True)

    def plan(self):
        result = self.run_mode('plan')
        self.assertEqual(result.returncode, 0, result.stderr)
        return json.loads(result.stdout)

    def test_revision_metadata_never_seeds_parent_and_apply_rollback_preserve_history(self):
        plan = self.plan()
        self.assertEqual(plan['metadata_to_copy'], 2)
        self.assertEqual(plan['blockers'], [])
        self.assertEqual(self.read(), self.before)
        result = self.run_mode('apply', plan['plan_sha256'])
        self.assertEqual(result.returncode, 0, result.stderr)
        after = self.read()
        for post_id in ['11', '12', '13']:
            self.assertEqual(after['meta'][post_id], self.before['meta'][post_id])
        self.assertEqual(after['meta']['21']['_seopress_titles_title'], ["A client's draft title"])
        self.assertEqual(after['meta']['21']['_seopress_titles_desc'], ['Draft description'])
        journal = after['options']['mrn_editor_retirement_' + plan['plan_sha256']]
        self.assertEqual({row['post_id'] for row in journal['copies']}, {21})
        self.assertEqual(journal['status'], 'applied')
        repeated = self.run_mode('apply', self.plan()['plan_sha256'])
        self.assertEqual(repeated.returncode, 0, repeated.stderr)
        self.assertIn('Already reconciled', repeated.stdout)
        for _ in range(2):
            rollback = self.run_mode('rollback', plan['plan_sha256'])
            self.assertEqual(rollback.returncode, 0, rollback.stderr)
        self.assertEqual(self.read()['meta'], self.before['meta'])
        self.assertCountEqual(self.read()['options']['active_plugins'], self.before['options']['active_plugins'])

    def test_revision_only_values_do_not_populate_an_empty_parent(self):
        before = self.read()
        before['meta']['11'] = []
        before['meta']['21'] = []
        self.write(before)
        plan = self.plan()
        self.assertEqual(plan['metadata_to_copy'], 0)
        result = self.run_mode('apply', plan['plan_sha256'])
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(self.read()['meta'], before['meta'])

    def test_current_post_conflict_still_blocks_before_deactivation(self):
        before = self.read()
        before['meta']['21']['_seopress_titles_title'] = ['Different native title']
        self.write(before)
        plan = self.plan()
        self.assertTrue(plan['blockers'])
        result = self.run_mode('apply', plan['plan_sha256'])
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.read(), before)


if __name__ == '__main__':
    unittest.main()

<?php
if (!defined('WP_CLI') || !WP_CLI || wp_parse_url(home_url(), PHP_URL_HOST) !== 'admin-editor-pilot.mrndev.io' || !is_file(ABSPATH.'.mrn-pilot-review')) { throw new RuntimeException('Exact pilot required'); }
function qa_assert($v,$m){if(!$v){throw new RuntimeException(esc_html($m));}}
$checks=[];
foreach([2523,2524] as $pid){
 $revisions=wp_get_post_revisions($pid,['posts_per_page'=>1,'orderby'=>'ID','order'=>'DESC']);$revision=reset($revisions);qa_assert((bool)$revision,'Missing native revision');
 $parent=acf_get_meta($pid);$copied=acf_get_meta($revision->ID);ksort($parent);ksort($copied);qa_assert($parent===$copied,'Native revision ACF mismatch');
 $checks[]=['post'=>$pid,'revision'=>$revision->ID,'acf_keys'=>count($parent),'native_revision_matches_parent'=>true];
}
acf_add_local_field_group(['key'=>'group_mrn_revision_cache_qa','title'=>'Temporary revision QA','fields'=>[
 ['key'=>'field_mrn_revision_qa_text','name'=>'mrn_revision_qa_text','type'=>'text'],
 ['key'=>'field_mrn_revision_qa_rows','name'=>'mrn_revision_qa_rows','type'=>'repeater','sub_fields'=>[['key'=>'field_mrn_revision_qa_child','name'=>'child','type'=>'textarea']]]
], 'location'=>[]]);
$id=wp_insert_post(['post_type'=>'page','post_status'=>'draft','post_title'=>'MRN disposable restore verification','meta_input'=>['_mrn_cache_qa_owned'=>'20261105']],true);qa_assert(!is_wp_error($id),'Could not create fixture');
try {
 acf_save_post($id,['field_mrn_revision_qa_text'=>'Unicode 漢字 and "quotes"','field_mrn_revision_qa_rows'=>[['field_mrn_revision_qa_child'=>'Nested one'],['field_mrn_revision_qa_child'=>"Nested two\nnext line"]]]);
 wp_update_post(['ID'=>$id,'post_content'=>'Native WordPress content before']);
 $revisions=wp_get_post_revisions($id,['posts_per_page'=>1,'orderby'=>'ID','order'=>'DESC']);$before=reset($revisions);qa_assert((bool)$before,'No restore source');$expected=acf_get_meta($id);ksort($expected);
 acf_save_post($id,['field_mrn_revision_qa_text'=>'changed','field_mrn_revision_qa_rows'=>[['field_mrn_revision_qa_child'=>'replacement']]]);
 wp_update_post(['ID'=>$id,'post_content'=>'Changed native content']);
 qa_assert(wp_restore_post_revision($before->ID)===$id,'Native restore failed');wp_cache_delete($id,'post_meta');$actual=acf_get_meta($id);ksort($actual);
 qa_assert($actual===$expected,'Native restored ACF values differ');qa_assert(get_post_field('post_content',$id)==='Native WordPress content before','Native content restore differs');
 $checks[]=['native_restore'=>'pass','nested_repeater'=>'pass','unicode_and_quotes'=>'pass','core_content'=>'pass'];
} finally {
 if(get_post_meta($id,'_mrn_cache_qa_owned',true)==='20261105'){wp_delete_post($id,true);}
}
echo wp_json_encode(['status'=>'pass','checks'=>$checks,'disposable_fixture_removed'=>!get_post($id)],JSON_PRETTY_PRINT).PHP_EOL;

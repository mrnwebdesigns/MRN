<?php
// Native WordPress/ACF integration check; only a disposable, owned draft is changed.
$host = wp_parse_url(home_url(), PHP_URL_HOST);
if (!defined('WP_CLI') || !WP_CLI || 'admin-editor-pilot.mrndev.io' !== $host || !is_file(ABSPATH.'.mrn-pilot-review')) {throw new RuntimeException('Exact review fixture runtime required.');}
if (!function_exists('mrn_base_stack_prime_revision_insert_cache')) {throw new RuntimeException('Revision cache candidate must be installed.');}
function expect_revision($ok,$message){if(!$ok){throw new RuntimeException(esc_html($message));}}
$fixture=wp_insert_post(array('post_type'=>'page','post_status'=>'draft','post_title'=>'MRN owned revision cache QA','meta_input'=>array('_mrn_revision_cache_fixture'=>'20261105')),true);
expect_revision(!is_wp_error($fixture),'Fixture creation failed');
global $wpdb;
$results=array();
try {
 for($i=0;$i<200;$i++) {
  $name='mrn_qa_'.$i;
  update_post_meta($fixture,$name,wp_slash($i%3===0?array('text'=>'Quotes " and \\ slash — 漢字','empty'=>'','integer'=>$i):'Value '.$i));
  update_post_meta($fixture,'_'.$name,'field_mrn_qa_'.$i);
 }
 $source=acf_get_meta($fixture);
 foreach(array('native','candidate','persistent-fallback') as $phase) {
  if('native'===$phase){remove_action('_wp_put_post_revision','mrn_base_stack_prime_revision_insert_cache',1);}else{add_action('_wp_put_post_revision','mrn_base_stack_prime_revision_insert_cache',1,2);}
  $fallback_start=static function() use($phase){if('persistent-fallback'===$phase){wp_using_ext_object_cache(true);}};
  $fallback_end=static function() {wp_using_ext_object_cache(false);};
  add_action('_wp_put_post_revision',$fallback_start,0);add_action('_wp_put_post_revision',$fallback_end,2);
  $extra=false;
  $observer=static function($mid,$id,$key,$value) use(&$extra,$fixture){
   if((int)wp_is_post_revision($id)!==(int)$fixture){return;}
   if(str_starts_with($key,'qa_scalar_')){$cache=get_metadata('post',$id);$stored=maybe_serialize($value);expect_revision($cache[$key][0]===(null===$stored?null:(string)$stored),'Native scalar storage type changed');}
   if(str_starts_with($key,'mrn_qa_')){expect_revision(get_metadata('post',$id,$key,true)===maybe_unserialize(maybe_serialize($value)), 'Read inside added_post_meta changed');}
   if(!$extra&&'mrn_qa_1'===$key){$extra=true;add_metadata('post',$id,'qa_duplicate','one');add_metadata('post',$id,'qa_duplicate','two');update_metadata('post',$id,'qa_mutated','before');update_metadata('post',$id,'qa_mutated','after');add_metadata('post',$id,'qa_removed','gone');delete_metadata('post',$id,'qa_removed');foreach([true,false,42,2.5,null] as $i=>$scalar){get_metadata('post',$id);add_metadata('post',$id,'qa_scalar_'.$i,$scalar);}}
  };
  add_action('added_post_meta',$observer,20,4);
  $copy=static function($rid,$pid) use($fixture){if((int)$pid===(int)$fixture){acf_copy_metadata($pid,$rid);}};
  add_action('_wp_put_post_revision',$copy,20,2);
  $count_before=count($GLOBALS['wp_filter']['added_post_meta']->callbacks,COUNT_RECURSIVE);
  $q=$wpdb->num_queries;$t=microtime(true);$rid=_wp_put_post_revision($fixture);
  expect_revision(!is_wp_error($rid)&&$rid,'Revision creation failed');
  $seconds=microtime(true)-$t;$queries=$wpdb->num_queries-$q;
  $warm=get_metadata('post',$rid);wp_cache_delete($rid,'post_meta');$cold=get_metadata('post',$rid);
  expect_revision($warm===$cold,'Warm cache differs from database after callback writes');
  expect_revision(acf_get_meta($rid)===$source,'ACF revision value or reference changed');
  expect_revision(get_metadata('post',$rid,'qa_duplicate',false)===array('one','two'),'Duplicate values changed');
  expect_revision(get_metadata('post',$rid,'qa_mutated',true)==='after'&&!metadata_exists('post',$rid,'qa_removed'),'Mutation/deletion hooks changed');
  expect_revision(count($GLOBALS['wp_filter']['added_post_meta']->callbacks,COUNT_RECURSIVE)===$count_before,'Temporary callbacks leaked outside revision');
  $results[]=array('phase'=>$phase,'seconds'=>$seconds,'queries'=>$queries,'acf_keys'=>count($source),'native_hooks_cache_and_values'=>'pass');
  remove_action('added_post_meta',$observer,20);remove_action('_wp_put_post_revision',$copy,20);remove_action('_wp_put_post_revision',$fallback_start,0);remove_action('_wp_put_post_revision',$fallback_end,2);
 }
 expect_revision($results[1]['queries']<$results[0]['queries'],'No reduction in revision query count');
 echo wp_json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;
} finally {
 wp_using_ext_object_cache(false);
 if('20261105'===get_post_meta($fixture,'_mrn_revision_cache_fixture',true)){wp_delete_post($fixture,true);}
}

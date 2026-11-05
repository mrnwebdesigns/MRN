<?php
// Isolated scope boundary regression: no WordPress database or runtime writes.
if ( 'cli' !== PHP_SAPI ) { exit; }
define('ABSPATH', __DIR__);
$enabled=true;$screen=(object)['base'=>'edit','id'=>'edit-page','post_type'=>'page'];
$registry=['page'=>[true,true],'job_posting'=>[true,true],'client_story'=>[true,true],'resource'=>[false,true],'event'=>[false,true],'location'=>[false,true],'mrn_reusable_basic'=>[false,true],'hidden'=>[true,false],'acf-field-group'=>[false,true],'seopress_404'=>[false,true],'product'=>[true,true],'tutor_quiz'=>[true,true],'wpforms'=>[false,true]];
function add_filter(...$args){} function mrn_editor_experience_enabled(){return $GLOBALS['enabled'];}function get_current_screen(){return $GLOBALS['screen'];}
function mrn_base_stack_get_singular_shell_post_types(){return ['page','job_posting'];}
function mrn_admin_data_post_types_get_config(){return array_fill_keys(['resource','event','location','mrn_reusable_basic','hidden','acf-field-group','seopress_404','product','tutor_quiz','wpforms'],[]);}
function get_post_types($args){return array_keys(array_filter($GLOBALS['registry'],fn($v)=>(!isset($args['public'])||$args['public']===$v[0])&&(!isset($args['show_ui'])||$args['show_ui']===$v[1])));}
require dirname( __DIR__, 5 ) . '/mu-plugins/mrn-admin-ui-css/modern-admin.php';
$checks=[];foreach($registry as $type=>$properties){$screen->post_type=$type;$expected=in_array($type,['page','job_posting','client_story','resource','event','location','mrn_reusable_basic'],true)?'list':'';if(mrn_admin_ui_modern_screen()!==$expected)throw new Exception('Post-type scope mismatch');$checks[$type]='pass';}
$enabled=false;$screen->post_type='page';if(mrn_admin_ui_modern_screen()!=='')throw new Exception('emergency switch');$checks['emergency_switch']='pass';
$enabled=true;$screen->base='post';if(mrn_admin_ui_modern_screen()!=='')throw new Exception('editor');$checks['editor_unchanged']='pass';echo json_encode($checks,JSON_PRETTY_PRINT)."\n";

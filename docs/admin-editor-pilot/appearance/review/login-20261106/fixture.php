<?php
if (!defined('WP_CLI') || !WP_CLI || wp_parse_url(home_url(), PHP_URL_HOST)!=='admin-editor-pilot.mrndev.io' || wp_get_environment_type()!=='development' || !is_file(ABSPATH.'.mrn-pilot-review') || posix_getpwuid(posix_geteuid())['name']!=='mrndev-admin-editor') {throw new RuntimeException('Exact review owner/runtime required');}
$private=dirname(ABSPATH,2).'/review-private/';$path=$private.'login-qa-20261106.json';$mode=$args[0]??'';
$receipt=json_decode(file_get_contents($private.'verified-backup.json'),true);
if(empty($receipt['valid']) || $receipt['host']!=='admin-editor-pilot.mrndev.io' || time()-$receipt['checked_at']>=300 || time()<$receipt['checked_at'] || !str_starts_with($receipt['label'],'pre-admin-pilot-login-')){throw new RuntimeException('Fresh login QA backup receipt required');}
$history=UpdraftPlus_Backup_History::get_backup_set_by_nonce($receipt['nonce']);if(($history['checksums']['sha256']['db0']??'')!==$receipt['sha256']){throw new RuntimeException('Native backup history mismatch');}
add_filter('pre_wp_mail','__return_false');
if($mode==='create'){
 if(file_exists($path)||username_exists('mrn-login-qa-20261106'))throw new RuntimeException('QA fixture already exists; inspect owner before using');
 $password=wp_generate_password(32,true,true);$id=wp_insert_user(['user_login'=>'mrn-login-qa-20261106','user_pass'=>$password,'user_email'=>'mrn-login-qa-20261106@example.invalid','role'=>'subscriber','display_name'=>'MRN disposable login QA']);
 if(is_wp_error($id))throw new RuntimeException('Could not create disposable login fixture');
 update_user_meta($id,'_mrn_login_qa_owned','20261106');update_user_meta($id,'mrn_admin_appearance','light');
 $user=get_user_by('id',$id);$key=get_password_reset_key($user);if(is_wp_error($key))throw new RuntimeException('Native reset token creation failed');
 $record=['id'=>$id,'username'=>$user->user_login,'password'=>$password,'reset_url'=>add_query_arg(['action'=>'rp','key'=>$key,'login'=>$user->user_login],wp_login_url())];
 $stream=fopen($path,'x');chmod($path,0600);fwrite($stream,wp_json_encode($record));fclose($stream);
 echo "Disposable subscriber login fixture prepared privately; mail suppressed for creation.\n";
}elseif($mode==='remove'){
 $record=json_decode(file_get_contents($path),true);$user=get_user_by('id',(int)$record['id']);
 if(!$user||$user->user_login!=='mrn-login-qa-20261106'||get_user_meta($user->ID,'_mrn_login_qa_owned',true)!=='20261106')throw new RuntimeException('QA fixture ownership mismatch');
 if(count(get_posts(['author'=>$user->ID,'post_type'=>'any','post_status'=>'any','numberposts'=>1])))throw new RuntimeException('Unexpected fixture content; preserve for review');
 require_once ABSPATH.'wp-admin/includes/user.php';if(!wp_delete_user($user->ID))throw new RuntimeException('Native fixture cleanup failed');unlink($path);echo "Only the owned subscriber fixture and private credentials removed.\n";
}else{throw new RuntimeException('Expected create or remove');}

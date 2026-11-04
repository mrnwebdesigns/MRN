<?php
// Read-only helper qualification; callbacks are registered but never executed.
if ( PHP_SAPI !== 'cli' ) { exit; }
define('ABSPATH', __DIR__);
function add_action(...$args) {}
function add_filter(...$args) {}
function is_admin() { return $GLOBALS['admin']; }
function mrn_editor_experience_enabled() { return $GLOBALS['enabled']; }
function get_current_screen() { return $GLOBALS['screen']; }
require getcwd() . '/mu-plugins/mrn-admin-ui-css/appearance.php';
$GLOBALS['admin']=true; $GLOBALS['enabled']=true;
foreach ([['upload','upload',true],['media','media',true],['media_page_vendor','media',false],['options-media','options-media',false],['post','post',false],['dashboard','dashboard',false]] as [$id,$base,$expected]) {
 $GLOBALS['screen']=(object)compact('id','base');
 if (mrn_admin_media_workspace_screen() !== $expected) { throw new Exception('Screen boundary failed'); }
}
$GLOBALS['screen']=(object)['id'=>'upload','base'=>'upload'];
$GLOBALS['enabled']=false;if(mrn_admin_media_workspace_screen())throw new Exception('Emergency disable failed');
$GLOBALS['enabled']=true;$GLOBALS['admin']=false;if(mrn_admin_media_workspace_screen())throw new Exception('Frontend boundary failed');
echo "PASS: exact native Media screens, vendor/settings/editor/dashboard exclusions, emergency disable and frontend guard\n";

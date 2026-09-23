<?php
/** Isolated migration precondition/rollback tests. No WordPress runtime is used. */
$case = getenv('MRN_MIGRATION_TEST_CASE') ?: 'plan';
$temp = sys_get_temp_dir() . '/mrn-sitekit-migration-' . getmypid();
mkdir($temp . '/wp-admin/includes', 0700, true);
file_put_contents($temp . '/wp-admin/includes/plugin.php', '<?php');
define('ABSPATH', $temp . '/');
define('WP_CLI', true);
class WP_CLI {
  public static function error($message) { throw new RuntimeException(esc_html($message)); }
  public static function line($message) {}
  public static function success($message) {}
}
class UpdraftPlus_Backup_History {
  public static function get_history() {
    global $case;
    if ($case === 'no-backup') return array();
    return array(time() - ($case === 'stale-backup' ? 3600 : 10) => array('nonce'=>'abcdef123456','db'=>'fixture-db.gz','db-size'=>100,'service'=>array('s3'),'label'=>'pre-sitekit-consent-1.1.46-fixture','checksums'=>array('sha256'=>array('db0'=>str_repeat('a',64)))));
  }
}
class FixtureUpdraft { public function backups_dir_location() { return ABSPATH; } }
$updraftplus = new FixtureUpdraft();
file_put_contents($temp . '/log.abcdef123456.txt', $case === 'failed-upload' ? 'Backup failed' : 'Recording as successfully uploaded. The backup succeeded and is now complete');
function home_url() { global $case; return $case === 'wrong-site' ? 'https://other.example' : 'https://tharringtonsmith.com'; }
function site_url() { return home_url(); }
function untrailingslashit($s) { return rtrim($s, '/'); }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
function is_multisite() { return false; }
function is_plugin_active($file) { return true; }
function get_plugins() { global $case; return array('mrn-cookie-consent/mrn-cookie-consent.php'=>array('Version'=>$case === 'old-plugin' ? '1.1.45':'1.1.46'),'google-site-kit/google-site-kit.php'=>array('Version'=>'1.160.1')); }
function get_option($key) { global $options; return isset($options[$key]) ? $options[$key] : false; }
function update_option($key, $value) { global $options,$writes; $writes[]=$key; $options[$key]=$value; return true; }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES); }
function wp_json_encode($value) { return json_encode($value); }
$options = array('mrn_silktide_consent_settings'=>array('enabled'=>1),'googlesitekit_active_modules'=>array('pagespeed-insights','analytics-4'),'googlesitekit_analytics-4_settings'=>array('measurementID'=>'G-0DPCZE00EQ','googleTagID'=>'GT-WPD79HLV','propertyID'=>'504163756','webDataStreamID'=>'12132291507','adsConversionID'=>'','trackingDisabled'=>array('loggedinUsers'),'useSnippet'=>true,'unrelated'=>'preserve'));
if (in_array($case,array('already-applied','rollback','restore-prior'),true)) $options['mrn_silktide_consent_settings']['sitekit_analytics_gate']=1;
if ($case === 'drift') $options['mrn_silktide_consent_settings']['unreviewed']=1;
if ($case === 'destination-drift') $options['googlesitekit_analytics-4_settings']['measurementID']='G-OTHER';
$original=$options;$writes=array();
$args=array(in_array($case,array('plan','rollback','restore-prior'),true) ? $case : 'apply','abcdef123456');
$blocked=in_array($case,array('wrong-site','old-plugin','drift','destination-drift','no-backup','stale-backup','failed-upload','restore-prior'),true);
$error=null;
try { require dirname(__DIR__,2).'/scripts/integrations/20260923-tharringtonsmith-sitekit-consent.php'; }
catch (RuntimeException $e) { $error=$e->getMessage(); }
$pass=($blocked === ($error!==null));
if ($blocked || in_array($case,array('plan','already-applied'),true)) $pass=$pass && !$writes && $options===$original;
if ($case==='apply') $pass=$pass && $options['mrn_silktide_consent_settings']===array('enabled'=>1,'sitekit_analytics_gate'=>1) && $options['googlesitekit_analytics-4_settings']===$original['googlesitekit_analytics-4_settings'];
if ($case==='rollback') $pass=$pass && $options['mrn_silktide_consent_settings']===array('enabled'=>1) && $options['googlesitekit_analytics-4_settings']['useSnippet']===false && $writes[0]==='googlesitekit_analytics-4_settings';
unlink($temp.'/wp-admin/includes/plugin.php');unlink($temp.'/log.abcdef123456.txt');rmdir($temp.'/wp-admin/includes');rmdir($temp.'/wp-admin');rmdir($temp);
echo ($pass?'PASS ':'FAIL ').esc_html($case).PHP_EOL;
exit($pass?0:1);

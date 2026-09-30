<?php
namespace SiteGround_Optimizer\Supercacher {
 class Supercacher { static function purge_cache_request($url,$children=true) { $GLOBALS['calls'][]=[$url,$children]; return getenv('FAIL_PURGE') !== '1'; } }
}
namespace {
 define('WP_CLI',true);
 define('WP_CONTENT_DIR',getenv('FIXTURE_DIR'));
 $GLOBALS['calls']=[];
 class WP_CLI { static function error($m) { fwrite(STDERR,$m);exit(1); } static function line($m) {
 // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture emits machine-readable JSON, never HTML.
 echo $m."\n";
 } }
 class Cache_Enabler { static function clear_page_cache_by_url($url,$scope) { $GLOBALS['calls'][]=[$url,$scope]; } }
 class WpeCommon { static function http_to_varnish($method,$host,$headers) { $GLOBALS['calls'][]=[$method,$host,$headers]; return getenv('FAIL_PURGE') === '1' ? false : null; } }
 function home_url($p='') { return 'https://example.org'.$p; }
 function untrailingslashit($p) { return rtrim($p,'/'); }
 function trailingslashit($p) { return rtrim($p,'/').'/'; }
 function wp_parse_url($u,$part=-1) { return parse_url($u,$part); }
 function wp_json_encode($v) { return json_encode($v); }
 function is_wp_error($v) { return false; }
 function get_option($key) { return $key === 'siteground_optimizer_enable_cache' ? '1' : getenv('FILE_CACHE') === '1'; }
 function get_post_types($a,$b) { return ['page'=>'page','post'=>'post','attachment'=>'attachment']; }
 function get_posts($a) { return [1,2]; }
 function get_permalink($i) { return home_url('/page-'.$i.'/'); }
 function get_post_type_archive_link($t) { return $t === 'post' ? home_url('/news/') : false; }
 function get_taxonomies($a) { return ['category']; }
 function get_terms($a) { return ['topic']; }
 function get_term_link($t) { return home_url('/category/'.$t.'/'); }
 require dirname(__DIR__).'/html_cache.php';
 echo 'CALLS='.json_encode($GLOBALS['calls'])."\n";
}

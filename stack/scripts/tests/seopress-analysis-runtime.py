#!/usr/bin/env python3
"""Native direct/audit analysis regression; no client runtime writes."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import time
import urllib.request
import urllib.parse

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / 'tools/fleet-release'))
from fixture import WordPressFixture


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--bootstrap', type=Path, required=True)
    parser.add_argument('--config', type=Path, required=True)
    parser.add_argument('--evidence', type=Path, required=True)
    parser.add_argument('--qa', action='store_true')
    args = parser.parse_args()
    settings = json.loads(args.config.read_text())['qualification']
    args.evidence.mkdir(parents=True)
    fixture = WordPressFixture(args.bootstrap, args.evidence / 'native', settings)

    def analyze(key, mode='direct'):
        post_id = seed['ids'][key]
        source = urllib.request.urlopen(seed['urls'][key], timeout=40).read().decode()
        # Exercise chrome/hidden-duplicate scoping without altering the public page.
        source = source.replace('</main>', '<div hidden><h2>Hidden duplicate</h2></div><div class="mrn-singular-shell__sidebar"><h2>Sidebar duplicate</h2></div></main>', 1)
        source = source.replace('</head>', '<script type="application/ld+json">' + json.dumps({'@context': 'https://schema.org', '@type': 'WebSite', 'url': fixture.url}) + '</script></head>')
        source += '<footer><h1>Ignored footer heading</h1><img src="footer.gif" alt=""></footer>'
        payload = args.evidence / 'analysis-input.json'
        payload.write_text(json.dumps({'id': post_id, 'html': source, 'mode': mode}))
        program = '''$input=json_decode(file_get_contents(INPUT_FILE),true);wp_set_current_user(1);add_filter('posts_request',static function($sql,$query){if(!empty($query->query_vars['s']))$GLOBALS['mrn_fixture_search'][]=['s'=>$query->query_vars['s'],'sql'=>$sql];return$sql;},999,2);
if('direct'===$input['mode']){$r=new WP_REST_Request('POST','/seopress/v1/posts/'.$input['id'].'/content-analysis/analyze');$r->set_param('id',$input['id']);$r->set_param('html',$input['html']);$response=rest_do_request($r);$code=$response->get_status();}
else{add_filter('pre_http_request',static function($result,$options,$url)use($input){if(wp_parse_url($url,PHP_URL_HOST)==='127.0.0.1'){return ['headers'=>['content-type'=>'text/html'],'body'=>$input['html'],'response'=>['code'=>200,'message'=>'OK'],'cookies'=>[]];}return$result;},999,3);$r=new WP_REST_Request('POST');$r->set_param('post_id',$input['id']);$response=(new \\SEOPressPro\\Actions\\Api\\SiteAudit())->processRescanPost($r);$code=is_wp_error($response)?500:$response->get_status();}
$post=get_post($input['id']);$analysis=seopress_get_service('GetContentAnalysis')->getAnalyzes($post);$data=seopress_get_service('DomFilterContent')->getData($input['html'],$input['id']);
global $wpdb;$issues=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.$wpdb->prefix.'seopress_seo_issues WHERE post_id=%d',$input['id']),ARRAY_A);echo wp_json_encode(['status'=>$code,'analysis'=>$analysis,'data'=>$data,'issues'=>$issues,'search_queries'=>$GLOBALS['mrn_fixture_search']??[],'keywords'=>get_post_meta($input['id'],'_seopress_analysis_target_kw',true)]);'''.replace('INPUT_FILE', repr(str(payload)))
        result = fixture.php(program)
        (args.evidence / (key + '-' + mode + '-' + phase + '.json')).write_text(json.dumps(result, indent=2))
        assert result['status'] == 200, result
        return result

    def incoming(result):
        return {int(item['id']) for item in result['data']['internal_links']['value']}

    def issues(result):
        return {item['issue_name'] for item in result['issues']}

    def public_bodies(label):
        result = {}
        for key in ['target', 'acf_source', 'native', 'job_source', 'mixed', 'reusable_host']:
            body = urllib.request.urlopen(seed['urls'][key], timeout=40).read().decode()
            main = re.search(r'<main\b.*?</main>', body, re.S).group()
            (args.evidence / (key + '-public-' + label + '.html')).write_text(main)
            result[key] = hashlib.sha256(main.encode()).hexdigest()
        return result

    preservation = '''global $wpdb;$result=[];foreach(['posts','postmeta','terms','term_taxonomy','term_relationships']as$table)$result[$table]=hash('sha256',wp_json_encode($wpdb->get_results('SELECT * FROM '.$wpdb->$table,ARRAY_A)));foreach(['seopress_titles_option_name','seopress_social_option_name','seopress_xml_sitemap_option_name','mrn_helper_settings','mrn_disabled_post_types']as$key)$result[$key]=hash('sha256',wp_json_encode(get_option($key)));echo wp_json_encode($result);'''
    try:
        fixture.start()
        seed = fixture.php((ROOT / 'stack/themes/mrn-base-stack/tests/runtime/seopress-analysis.php').read_text().removeprefix('<?php\n'))
        before = fixture.php(preservation)
        public_before = public_bodies('before')
        phase = 'baseline'
        baseline = analyze('target')
        only = analyze('acf_only_target')
        assert 'content_too_thin' in issues(baseline), baseline
        assert seed['ids']['native'] in incoming(baseline) and seed['ids']['acf_source'] not in incoming(baseline), baseline
        assert 'internal_links_missing' in issues(only), only
        shutil.copytree(ROOT / 'stack/themes/mrn-base-stack', fixture.content / 'themes/mrn-base-stack', dirs_exist_ok=True)
        fixture.process.terminate(); fixture.process.wait(timeout=10)
        fixture.process = subprocess.Popen([settings['php'], '-S', '127.0.0.1:' + str(fixture.port), '-t', str(fixture.public), str(fixture.router)], stdout=fixture.log, stderr=fixture.log)
        for attempt in range(50):
            try:
                urllib.request.urlopen(fixture.url + '/wp-json/', timeout=2).close(); break
            except OSError:
                time.sleep(.1)
        phase = 'candidate'
        expected = {seed['ids'][name] for name in ['native', 'acf_source', 'job_source', 'mixed', 'reusable_host']}
        direct = analyze('target')
        assert incoming(direct) == expected, (incoming(direct), expected)
        assert 'content_too_thin' not in issues(direct) and 'skipped' in direct['analysis']['content_depth']['desc'], direct
        assert direct['data']['h1']['value'] == ['Analysis target'], direct['data']['h1']
        assert not {'Hidden duplicate', 'Sidebar duplicate'} & set(direct['data']['h2']['value']), direct['data']['h2']
        assert direct['data']['title']['value'] == baseline['data']['title']['value']
        assert direct['data']['schemas']['value'] == baseline['data']['schemas']['value']
        assert 'img_alt_missing' in issues(direct), issues(direct)
        refreshed = analyze('acf_only_target')
        assert 'internal_links_missing' not in issues(refreshed), refreshed
        audit = analyze('target', 'audit')
        assert incoming(audit) == incoming(direct) and issues(audit) == issues(direct), {'incoming_direct': list(incoming(direct)), 'incoming_audit': list(incoming(audit)), 'issues_direct': list(issues(direct)), 'issues_audit': list(issues(audit))}
        assert audit['analysis']['content_depth'] == direct['analysis']['content_depth']
        native = analyze('native')
        assert 'skipped' not in native['analysis']['content_depth']['desc'] and 'content_too_thin' not in issues(native), native
        mixed = analyze('mixed')
        assert 'skipped' in mixed['analysis']['content_depth']['desc'], mixed
        job = analyze('job_source')
        assert 'skipped' in job['analysis']['content_depth']['desc'], job
        fixture.php("update_option('mrn_disabled_post_types',[],false);echo json_encode(['done'=>true]);")
        phase = 'team-enabled'
        team = analyze('disabled_source')
        assert 'skipped' in team['analysis']['content_depth']['desc'], team
        assert incoming(analyze('target')) == expected | {seed['ids']['disabled_source']}
        fixture.php("update_option('mrn_disabled_post_types',['job_posting'],false);echo json_encode(['done'=>true]);")
        phase = 'job-disabled'
        assert incoming(analyze('target')) == (expected - {seed['ids']['job_source']}) | {seed['ids']['disabled_source']}
        fixture.php("update_option('mrn_disabled_post_types',['team_member'],false);echo json_encode(['done'=>true]);")
        phase = 'candidate'
        control = fixture.php('''$counts=static function(){$result=[];foreach(['relevanssi_search_ok','relevanssi_admin_search_ok','relevanssi_prevent_default_request']as$hook){$result[$hook]=0;foreach($GLOBALS['wp_filter'][$hook]->callbacks??[]as$callbacks)$result[$hook]+=count($callbacks);}return$result;};$before=$counts();$q=new WP_Query(['s'=>'unrelated control phrase','fields'=>'ids']);$sql=$q->request;$dom=new DOMDocument();$dom->loadHTML('<div>Fixture</div>');(new MRN_Base_Stack_SEOPress_Incoming_Extractor())->getDataByXPath(new DOMXPath($dom),['id'=>TARGET_ID]);$q=new WP_Query(['s'=>'unrelated control phrase','fields'=>'ids']);echo wp_json_encode(['filters_restored'=>$before===$counts(),'ordinary_search_preserved'=>$sql===$q->request]);'''.replace('TARGET_ID', str(seed['ids']['target'])))
        assert all(control.values()), control
        fragment = fixture.php('''$data=seopress_get_service('DomFilterContent')->getData('<h1>Headless fragment heading</h1><p>Original fragment content.</p>',TARGET_ID);$zero=['internal_links'=>['class'=>'native','options'=>['id'=>0]]];echo wp_json_encode(['h1'=>$data['h1']['value'],'unanchored_unchanged'=>$zero===mrn_base_stack_seopress_analysis_extractors($zero)]);'''.replace('TARGET_ID', str(seed['ids']['native'])))
        assert fragment == {'h1': ['Headless fragment heading'], 'unanchored_unchanged': True}, fragment
        public_after = public_bodies('after')
        assert public_before == public_after, (public_before, public_after)
        (args.evidence / 'public-preservation.json').write_text(json.dumps({'passed': True, 'main_before': public_before, 'main_after': public_after, **control}, indent=2))
        after = fixture.php(preservation)
        assert before == after, (before, after)
        (args.evidence / 'preservation.json').write_text(json.dumps({'passed': True, 'before': before, 'after': after}, indent=2))
        fixture.php("require_once ABSPATH.'wp-admin/includes/plugin.php';deactivate_plugins(['wp-seopress/seopress.php','wp-seopress-pro/seopress-pro.php']);echo json_encode(['done'=>true]);")
        assert urllib.request.urlopen(seed['urls']['target'], timeout=40).status == 200
        if args.qa:
            fixture.php("require_once ABSPATH.'wp-admin/includes/plugin.php';activate_plugin('wp-seopress/seopress.php');activate_plugin('wp-seopress-pro/seopress-pro.php');echo json_encode(['done'=>true]);")
            env = os.environ.copy(); env['MRN_QA_SAMPLE_PATH'] = urllib.parse.urlsplit(seed['urls']['acf_source']).path
            with (args.evidence / 'mrn-qa.log').open('w') as log:
                run = subprocess.run([settings['qa_engine'], 'run', '--project-root', str(ROOT), '--site-path', str(fixture.public), '--site-url', fixture.url, '--scope', 'site-only', '--output-file', str(args.evidence / 'mrn-qa.md')], stdout=log, stderr=subprocess.STDOUT, env=env, timeout=3600)
            assert run.returncode == 0, 'See mrn-qa.log'
        fixture.assert_clean_diagnostics()
        (args.evidence / 'regression.json').write_text(json.dumps({'passed': True, 'native_incoming_retained': True, 'acf_only_incoming_reproduced_and_fixed': True, 'stale_issues_reconciled': True, 'genuine_image_issue_retained': True, 'direct_audit_agreement': True, 'enabled_disabled_team_job': True, 'ordinary_search_preserved': True, 'public_markup_and_native_data_preserved': True, 'absent_seopress': True, 'remote_site_writes': False}, indent=2))
        print('PASS native SEOPress direct/audit analysis, issue reconciliation and preservation', flush=True)
    finally:
        fixture.close()


if __name__ == '__main__':
    main()

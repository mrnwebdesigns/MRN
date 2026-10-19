#!/usr/bin/env python3
"""Prove list URL identity against actual WordPress/ACF/SEOPress rendering."""
import argparse
import html
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

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
    results = []

    def fetch(url):
        try:
            response = urllib.request.urlopen(url, timeout=40)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, response.read().decode()

    def snapshot(url):
        status, body = fetch(url)
        canonical = [html.unescape(v) for v in re.findall(r'<link rel="canonical" href="([^"]+)"', body)]
        og = [html.unescape(v) for v in re.findall(r'<meta property="og:url" content="([^"]+)"', body)]
        lists = re.findall(r'<ul class="mrn-content-list-row__items.*?</ul>', body, re.S)
        items = [re.findall(r'pagination-item-([0-9]+)/', block) for block in lists]
        return {'status': status, 'canonical': canonical, 'og': og, 'items': items,
                'h1_count': len(re.findall(r'<h1\b', body)), 'title_count': len(re.findall(r'<title>', body)),
                'description_count': len(re.findall(r'<meta name="description"', body)), 'debug': re.findall(r'<script id="pagination-fixture-debug" type="application/json">(.*?)</script>', body)}

    try:
        fixture.start()
        test = ROOT / 'stack/themes/mrn-base-stack/tests/runtime/list-pagination.php'
        seed = fixture.php(test.read_text().removeprefix('<?php\n'))
        pages = seed['pages']
        base = pages['single']['url']
        key = 'mrn_list_page_' + str(pages['single']['id']) + '_0'
        before = snapshot(base + '?' + key + '=2')
        archive_before = snapshot(seed['archive_url'])
        assert archive_before['status'] == 200
        preservation_code = '''global $wpdb;$result=[];foreach(['posts','postmeta','terms','term_taxonomy','term_relationships'] as $table){$rows=$wpdb->get_results('SELECT * FROM '.$wpdb->$table,ARRAY_A);$result[$table]=hash('sha256',wp_json_encode($rows));}foreach(['seopress_titles_option_name','seopress_social_option_name','seopress_xml_sitemap_option_name']as$key)$result[$key]=hash('sha256',wp_json_encode(get_option($key)));echo wp_json_encode($result);'''
        preserved_before = fixture.php(preservation_code)
        assert before['status'] == 200 and before['canonical'] == [base], before
        (args.evidence / 'baseline-defect.json').write_text(json.dumps(before, indent=2))
        source = ROOT / 'stack/themes/mrn-base-stack'
        shutil.copytree(source, fixture.content / 'themes/mrn-base-stack', dirs_exist_ok=True)
        (fixture.content / 'mu-plugins/099-pagination-proof.php').write_text(r"""<?php
add_action('wp_head',static function(){ $id=get_queried_object_id();echo '<script id="pagination-fixture-debug" type="application/json">'.wp_json_encode(['loaded'=>function_exists('mrn_base_stack_get_list_pagination_request'),'template'=>get_template_directory(),'state'=>function_exists('mrn_base_stack_get_list_pagination_request')?mrn_base_stack_get_list_pagination_request():null,'singular'=>is_singular(),'tag_url'=>class_exists('SEOPress\\Tags\\PostUrl')?(new \SEOPress\Tags\PostUrl())->getValue([['is_single'=>true,'post'=>get_queried_object()]]):null,'page_reference'=>function_exists('mrn_schema_bridge_get_webpage_reference')?mrn_schema_bridge_get_webpage_reference(get_permalink($id)):null]).'</script>';},99);
""")
        # Restart our owned disposable server so the new parent bytes are certain.
        fixture.process.terminate()
        fixture.process.wait(timeout=10)
        fixture.process = subprocess.Popen([settings['php'], '-S', '127.0.0.1:' + str(fixture.port),
            '-t', str(fixture.public), str(fixture.router)], stdout=fixture.log, stderr=fixture.log)

        candidate = fixture.php("echo json_encode(['loaded'=>function_exists('mrn_base_stack_get_list_pagination_request'),'template'=>get_template_directory(),'builder'=>mrn_base_stack_is_layout_builder_enabled()]);")
        (args.evidence / 'candidate-loaded.json').write_text(json.dumps(candidate, indent=2))
        assert candidate['loaded'], candidate
        for attempt in range(50):
            try:
                urllib.request.urlopen(fixture.url + '/wp-json/', timeout=2).close()
                break
            except (OSError, urllib.error.URLError):
                time.sleep(0.1)
        else:
            raise RuntimeError('Owned candidate server did not start')

        def check(label, url, expected, status=200):
            actual = snapshot(url)
            assert actual['status'] == status, (label, actual)
            if status == 200:
                assert actual['canonical'] == [expected], (label, actual)
                assert not actual['og'] or actual['og'] == [expected], (label, actual)
                assert actual['h1_count'] == actual['title_count'] == actual['description_count'] == 1, (label, actual)
                debug = json.loads(actual['debug'][0])
                assert html.unescape(debug['tag_url']) == expected, (label, debug)
                if debug['page_reference']:
                    assert debug['page_reference']['@id'] == expected + '#schema-webpage', (label, debug)
            else:
                assert not actual['canonical'] and not actual['og'], (label, actual)
            results.append({'case': label, 'url': url, **actual})
            return actual

        first = check('first', base, base)
        second = check('second', base + '?' + key + '=2', base + '?' + key + '=2')
        assert first['items'] != second['items'] and second['items'] == before['items'], (first, second, before)
        check('final', base + '?' + key + '=3', base + '?' + key + '=3')
        for value in ['0', '1', '-2', '1.5', '2e0', 'oops', '']:
            current = check('normalized-' + value, base + '?' + key + '=' + value, base)
            assert current['items'] == first['items'], (value, current)
        check('array', base + '?' + key + '%5B%5D=2', base)
        check('leading-zeros', base + '?' + key + '=0002', base + '?' + key + '=2')
        for value in ['4', '9999999999999999999999999999999']:
            check('out-of-range-' + value, base + '?' + key + '=' + value, None, 404)
        check('campaign', base + '?utm_source=qa&' + key + '=2&mrn_list_page_999_0=7&other=no', base + '?' + key + '=2')
        multi = pages['multiple']; mk = 'mrn_list_page_' + str(multi['id'])
        expected = multi['url'] + '?' + mk + '_0=2&' + mk + '_1=3'
        check('independent-lists', multi['url'] + '?' + mk + '_1=3&' + mk + '_0=2&utm_campaign=test', expected)
        empty = pages['empty']; ek = 'mrn_list_page_' + str(empty['id']) + '_0'
        check('empty-first', empty['url'], empty['url'])
        check('empty-second', empty['url'] + '?' + ek + '=2', None, 404)
        nested = pages['nested']; nk = 'mrn_list_page_' + str(nested['id']) + '_10100'
        check('nested-list', nested['url'] + '?' + nk + '=2', nested['url'] + '?' + nk + '=2')
        for name in ['team_member', 'job_posting']:
            owner = pages[name]; own_key = 'mrn_list_page_' + str(owner['id']) + '_0'
            check('enabled-' + name, owner['url'] + '?' + own_key + '=2', owner['url'] + '?' + own_key + '=2')
        fixture.php("update_option('mrn_disabled_post_types',['gallery','team_member'],false);echo json_encode(['done'=>true]);")
        disabled = pages['disabled_source']; dk = 'mrn_list_page_' + str(disabled['id']) + '_0'
        check('disabled-source-no-pagination', disabled['url'] + '?' + dk + '=2', disabled['url'])
        check('disabled-team-route', pages['team_member']['url'], None, 404)
        check('disabled-gallery-route', seed['gallery_url'], None, 404)
        fixture.php("update_option('mrn_disabled_post_types',[],false);echo json_encode(['done'=>true]);")
        owner = pages['team_member']; own_key = 'mrn_list_page_' + str(owner['id']) + '_0'
        check('reenabled-team', owner['url'] + '?' + own_key + '=2', owner['url'] + '?' + own_key + '=2')
        archive_after = snapshot(seed['archive_url'])
        assert archive_after['canonical'] == archive_before['canonical'] and archive_after['status'] == 200, (archive_before, archive_after)
        results.append({'case': 'native-archive-preserved', **archive_after})
        custom = fixture.url + '/explicit-editorial-destination/'
        fixture.php("update_post_meta(" + str(pages['single']['id']) + ", '_seopress_robots_canonical', " + repr(custom) + ");echo json_encode(['done'=>true]);")
        editorial = snapshot(base + '?' + key + '=2')
        assert editorial['canonical'] == [custom] and editorial['og'] == [base], editorial
        fixture.php("delete_post_meta(" + str(pages['single']['id']) + ", '_seopress_robots_canonical');echo json_encode(['done'=>true]);")
        results.append({'case': 'explicit-editorial-canonical-preserved', **editorial})
        preserved_after = fixture.php(preservation_code)
        assert preserved_after == preserved_before, (preserved_before, preserved_after)
        (args.evidence / 'preservation.json').write_text(json.dumps({'passed': True, 'before': preserved_before, 'after': preserved_after}, indent=2))
        fixture.php("require_once ABSPATH.'wp-admin/includes/plugin.php';deactivate_plugins(['wp-seopress/seopress.php','wp-seopress-pro/seopress-pro.php']);echo json_encode(['done'=>true]);")
        # Core emits its own canonical and retains title/H1; its native description is absent.
        core = snapshot(base + '?' + key + '=2')
        assert core['status'] == 200 and core['canonical'] == [base + '?' + key + '=2'], core
        assert core['items'] == second['items'] and core['h1_count'] == core['title_count'] == 1, core
        results.append({'case': 'without-seopress', **core})
        if args.qa:
            fixture.php("require_once ABSPATH.'wp-admin/includes/plugin.php';activate_plugin('wp-seopress/seopress.php');activate_plugin('wp-seopress-pro/seopress-pro.php');echo json_encode(['done'=>true]);")
            env = os.environ.copy()
            env['MRN_QA_SAMPLE_PATH'] = urllib.parse.urlsplit(base).path + '?' + key + '=2'
            with (args.evidence / 'mrn-qa.log').open('w') as log:
                run = subprocess.run([settings['qa_engine'], 'run', '--project-root', str(ROOT),
                    '--site-path', str(fixture.public), '--site-url', fixture.url,
                    '--scope', 'site-only', '--output-file', str(args.evidence / 'mrn-qa.md')],
                    stdout=log, stderr=subprocess.STDOUT, env=env, timeout=3600)
            print('MRN QA exit:', run.returncode, flush=True)
            assert run.returncode == 0, 'See mrn-qa.log and mrn-qa.md'
        fixture.assert_clean_diagnostics()
        (args.evidence / 'regression.json').write_text(json.dumps({'status': 'pass', 'cases': results,
            'remote_site_writes': False, 'site_adoption_verified': False}, indent=2))
        print('PASS native pagination cases:', len(results), flush=True)
    finally:
        fixture.close()


if __name__ == '__main__':
    main()

#!/usr/bin/env python3
"""Exercise the candidate on a disposable native WordPress/SEOPress fixture."""
import argparse
import json
from pathlib import Path
import shutil
import subprocess
import sys
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / 'tools/fleet-release'))
from fixture import WordPressFixture  # noqa: E402


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--bootstrap', type=Path, required=True)
    parser.add_argument('--config', type=Path, required=True)
    parser.add_argument('--evidence', type=Path, required=True)
    parser.add_argument('--qa', action='store_true')
    args = parser.parse_args()
    settings = json.loads(args.config.read_text())['qualification']
    args.evidence.mkdir(parents=True, exist_ok=True)
    fixture = WordPressFixture(args.bootstrap, args.evidence / 'native-fixture', settings)
    results = []
    try:
        fixture.start()
        source = ROOT / 'mu-plugins/mrn-admin-data-post-types'
        shutil.copytree(source, fixture.content / 'mu-plugins/mrn-admin-data-post-types', dirs_exist_ok=True)
        shutil.copyfile(ROOT / 'stack/mu-plugins/mrn-admin-data-post-types.php',
                        fixture.content / 'mu-plugins/mrn-admin-data-post-types.php')
        (fixture.content / 'mu-plugins/001-disabled-types-fixture.php').write_text("""<?php
define('MRN_DISABLED_TYPES_FIXTURE', true);
add_action('init', static function () {
    register_taxonomy('fixture_shared', ['gallery', 'team_member'], ['public'=>true, 'rewrite'=>['slug'=>'fixture-shared']]);
    register_taxonomy('fixture_media', ['gallery', 'attachment'], ['public'=>true, 'rewrite'=>['slug'=>'fixture-media']]);
    register_taxonomy_for_object_type('category', 'gallery');
    register_taxonomy_for_object_type('post_tag', 'gallery');
}, 20);
""")
        test = fixture.content / 'mu-plugins/mrn-admin-data-post-types/tests/runtime-regression.php'

        def php_test(action):
            return fixture.php('$args=[' + repr(action) + '];require ' + repr(str(test)) + ';')

        def fetch(url):
            try:
                with urllib.request.urlopen(url, timeout=30) as response:
                    return response.status, response.read().decode('utf-8', errors='replace')
            except urllib.error.HTTPError as error:
                return error.code, error.read().decode('utf-8', errors='replace')

        seed = php_test('seed')
        for phase in ['enabled', 'menu_hidden', 'content_only', 'disabled', 'reenabled']:
            php_test(phase)
            result = php_test('assert')
            excluded = phase in ['content_only', 'disabled']
            routes = []
            for name in ['gallery', 'case_study', 'team_member']:
                for kind, url in seed['urls'][name].items():
                    status, body = fetch(url)
                    expected = 404 if excluded and name != 'team_member' else 200
                    assert status == expected, (phase, name, kind, status, expected)
                    routes.append({'type': name, 'route': kind, 'status': status})
            for name in ['gallery_category', 'gallery_tag', 'fixture_shared', 'fixture_media']:
                status, body = fetch(seed['urls'][name])
                expected = 404 if phase == 'disabled' and name.startswith('gallery_') else 200
                assert status == expected, (phase, name, status, expected)
                routes.append({'taxonomy': name, 'status': status})
            status, xml = fetch(fixture.url + '/sitemaps.xml')
            assert status == 200, (phase, 'sitemap index', status)
            for name in ['gallery', 'case_study', 'gallery_category', 'gallery_tag']:
                hidden = phase == 'disabled' if name.startswith('gallery_') else excluded
                assert (('/' + name + '-sitemap') in xml) == (not hidden), (phase, 'index', name)
                status, xml_single = fetch(fixture.url + '/' + name + '-sitemap.xml')
                assert status == (404 if hidden else 200), (phase, 'direct sitemap', name, status)
            status, team = fetch(fixture.url + '/team_member-sitemap.xml')
            assert status == 200 and 'fixture-team_member' in team, (phase, 'team sitemap')
            status, news = fetch(fixture.url + '/?seopress_news=1')
            (args.evidence / ('news-' + phase + '.xml')).write_text(news)
            assert status == 200 and 'fixture-team_member' in news, (phase, 'news sitemap')
            assert ('fixture-gallery' in news) == (not excluded), (phase, 'news exclusion')
            result['http'] = routes
            results.append(result)
            (args.evidence / ('state-' + phase + '.json')).write_text(json.dumps(result, indent=2))
            print('PASS native state, routes and sitemaps:', phase, flush=True)
            if phase == 'disabled':
                browser = fixture.browser_input()
                browser.update(login_url=fixture.inventory['login_url'], evidence=str(args.evidence))
                with (args.evidence / 'browser.log').open('w') as log:
                    run = subprocess.run([settings['node'], source / 'tests/settings-browser.cjs'],
                                         input=json.dumps(browser), text=True, stdout=log,
                                         stderr=subprocess.STDOUT, timeout=240)
                assert run.returncode == 0, 'See browser.log'
                php_test('assert')
                print('PASS native settings/browser/keyboard/axe', flush=True)

        results.append(php_test('legacy-preservation'))
        print('PASS legacy menu, Content Only and search-destination preservation', flush=True)
        # The disabled policy is independent of either SEOPress package.
        php_test('disabled')
        fixture.php("require_once ABSPATH.'wp-admin/includes/plugin.php';deactivate_plugins(['wp-seopress/seopress.php','wp-seopress-pro/seopress-pro.php']);echo json_encode(['deactivated'=>true]);")
        result = php_test('assert')
        assert result['seopress'] is False
        assert fetch(seed['urls']['gallery']['single'])[0] == 404
        assert fetch(seed['urls']['team_member']['single'])[0] == 200
        results.append(result)
        print('PASS operation without SEOPress', flush=True)
        fixture.php("require_once ABSPATH.'wp-admin/includes/plugin.php';activate_plugin('wp-seopress/seopress.php');activate_plugin('wp-seopress-pro/seopress-pro.php');echo json_encode(['activated'=>true]);")
        php_test('reenabled')
        php_test('assert')
        if args.qa:
            report = args.evidence / 'mrn-qa.md'
            with (args.evidence / 'mrn-qa.log').open('w') as log:
                run = subprocess.run([settings['qa_engine'], 'run', '--project-root', str(ROOT),
                                      '--site-path', str(fixture.public), '--site-url', fixture.url,
                                      '--scope', 'site-only', '--output-file', str(report)],
                                     stdout=log, stderr=subprocess.STDOUT, timeout=3600)
            print('MRN QA exit:', run.returncode, flush=True)
            assert run.returncode == 0, 'See mrn-qa.log and mrn-qa.md'
        fixture.assert_clean_diagnostics()
        (args.evidence / 'regression.json').write_text(json.dumps({'status': 'pass', 'states': results,
            'site_adoption_verified': False, 'remote_site_writes': False}, indent=2))
    finally:
        fixture.close()


if __name__ == '__main__':
    main()

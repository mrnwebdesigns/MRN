"""Bind required MRN/native/browser qualification to exact locked artifacts."""
from __future__ import annotations

import json
import os
from pathlib import Path
import re
import shutil
import subprocess
from urllib.parse import urlparse

from common import ReleaseError, canonical, clean_main, digest, file_hash, read, write
from fixture import WordPressFixture
from presentation import qualify_presentations


def execute(command, evidence, *, env=None, stdin=None, timeout=3600, cwd=None):
    with Path(evidence).open('w') as log:
        result = subprocess.run([str(v) for v in command], input=stdin, text=True,
                                env=env, cwd=cwd, stdout=log, stderr=subprocess.STDOUT,
                                timeout=timeout, check=False)
    if result.returncode:
        raise ReleaseError('Qualification failed: ' + Path(evidence).name)


def require_engine_pass(report, *, runtime):
    text = Path(report).read_text()
    if '**Release QA Result: 100% SUCCESS**' not in text or re.search(r'\| (?:Ran|Skipped) \| Fail \|', text):
        raise ReleaseError('MRN release QA is not 100% successful')
    if runtime:
        required = ('WordPress API runtime smoke', 'Accessibility smoke',
                    'qa-playwright-local-stack-site.sh', 'qa-page-speed.sh', 'Core Web Vitals')
        for label in required:
            row = next((line for line in text.splitlines() if line.startswith('| ' + label + ' |')), '')
            if '| Ran | Pass |' not in row:
                raise ReleaseError('Required MRN runtime row did not pass: ' + label)
    return True


def qualify_form_success(fixture, source, settings, evidence, env):
    """Require real acceptance/negative cases when Form Success is a default."""
    if 'mrn-form-success/mrn-form-success.php' not in fixture.inventory['plugins']:
        return False
    if urlparse(fixture.url).hostname != '127.0.0.1':
        raise ReleaseError('Form Success qualification must stay on loopback')
    source, evidence = Path(source), Path(evidence)
    inactive = fixture.php("do_action('wp_enqueue_scripts');echo json_encode(["
                           "'settings'=>MRN_Form_Success::settings(),"
                           "'script'=>wp_script_is('mrn-form-success','enqueued'),"
                           "'woocommerce'=>class_exists('WooCommerce'),"
                           "'wpforms'=>WPFORMS_VERSION]);")
    if (inactive['settings'] != {'enabled': False, 'diagnostics': False, 'forms': []}
            or inactive['script'] or inactive['woocommerce']):
        raise ReleaseError('Form Success must be inactive on an unconfigured new site')
    write(evidence / 'form-success-default.json', inactive)
    report = evidence / 'component-mrn-form-success.md'
    execute([settings['qa_engine'], 'run', '--project-root', source, '--mode', 'release',
             '--site-path', fixture.public, '--site-url', fixture.url,
             '--run-smoke', 'never', '--run-accessibility', 'never', '--run-performance', 'never',
             '--run-cwv', 'never', '--output-file', report],
            evidence / 'component-mrn-form-success.log', env=env, timeout=1800, cwd=source)
    require_engine_pass(report, runtime=False)
    # Default browser/API/AA/performance checks already ran on this disposable
    # database. Replace only its synthetic form to run the plugin's native cases.
    shutil.copyfile(source / 'tests/local-safety.php',
                    fixture.content / 'mu-plugins/001-form-success-test-safety.php')
    fixture.php("foreach((array)wpforms()->obj('form')->get() as $form)"
                "wp_delete_post($form->ID,true);echo json_encode(['cleared'=>true]);")
    forms = fixture.php('require ' + repr(str(source / 'tests/create-fixture.php')) + ';')
    fixture.php('$fixture=json_decode(' + repr(json.dumps(forms)) + ',true);'
                'foreach($fixture["forms"] as $row){$id=url_to_postid($row["url"]);'
                'update_field("field_mrn_page_content_rows",[["acf_fc_layout"=>"wpforms",'
                '"heading"=>"Form acceptance fixture","form"=>$row["form_id"]]],$id);}'
                'echo json_encode(["native_builder"=>true]);')
    write(evidence / 'form-success-forms.json', forms)
    database = fixture.php('ob_start();require ' + repr(str(source / 'tests/integration.php'))
                            + ';$out=ob_get_clean();echo json_encode(["result"=>$out]);')
    write(evidence / 'form-success-database.json', database)
    execute([settings['node'], source / 'tests/browser.mjs'], evidence / 'form-success-browser.log',
            env={**env, 'MRN_FS_FIXTURE': str(evidence / 'form-success-forms.json'),
                 'MRN_PLAYWRIGHT_ROOT': settings['qa_engine_root'],
                 'MRN_FS_SCREENSHOT': str(evidence / 'form-success-browser.png')},
            timeout=900, cwd=source)
    fixture.assert_clean_diagnostics()
    return True


def qualify(repo, standalone, built, settings, evidence, selected):
    evidence = Path(evidence)
    evidence.mkdir(parents=True, exist_ok=True)
    toolchain = {'qa_engine_commit': clean_main(settings['qa_engine_root']),
                 'wp_cli_sha256': file_hash(settings['wp_cli']),
                 'mysql_server_sha256': file_hash(settings['mysql_server']),
                 'mysql_server_version': subprocess.run(
                     [settings['mysql_server'], '--no-defaults', '--version'],
                     capture_output=True, text=True, check=True).stdout.strip()}
    if toolchain['qa_engine_commit'] != selected['refs']['MRN-qa-engine']:
        raise ReleaseError('Qualification engine differs from the selected accepted source')
    execute([settings['node'], '--test', str(Path(repo) / 'tools/fleet-release/tests/published-source.test.mjs')],
            evidence / 'published-source-contracts.log', cwd=repo)
    execute(['python3', '-m', 'unittest', 'discover', '-s', str(Path(repo) / 'tools/fleet-release/tests')],
            evidence / 'source-release-contracts.log', cwd=repo)
    execute(['python3', '-m', 'unittest', 'discover', '-s', str(Path(repo) / 'stack/tests')],
            evidence / 'stack-contracts.log', env={**os.environ,
            'MRN_STANDALONE_PLUGINS_ROOT': str(standalone),
            'MRN_STACK_AGENT_ROOT': str(Path(standalone) / 'mrn-stack-deployment-agent'),
            'MRN_MAINWP_OPERATIONS_ROOT': str(Path(standalone) / 'mrn-mainwp-operations-api')}, timeout=1800, cwd=repo)
    fixture = WordPressFixture(built['bootstrap'], evidence / 'native-fixture', settings)
    try:
        fixture.start()
        fixture.assert_clean_diagnostics()
        browser = fixture.browser_input()
        browser['login_url'] = fixture.inventory['login_url']
        execute([settings['node'], Path(__file__).with_name('fixture-browser.cjs')],
                evidence / 'native-browser.log', stdin=json.dumps(browser), timeout=900, cwd=repo)
        presentations = qualify_presentations(fixture, repo, settings, evidence / 'presentations')
        env = {**os.environ, 'MRN_QA_SITE_PATH': str(fixture.public),
               'MRN_QA_SITE_URL': fixture.url, 'MRN_QA_STACK_ROOT': str(repo),
               'MRN_STANDALONE_PLUGINS_ROOT': str(standalone),
               'MRN_QA_CODE_ANALYSIS_SCOPE': 'all', 'MRN_QA_SCOPE': 'site-only',
               'MRN_QA_SAMPLE_PATH': '/presentation-qualification/',
               'MRN_QA_PLAYWRIGHT_PROVIDER': 'engine'}
        # CLI arguments override repository-owned .mrn-qa.env and prevent a
        # future source change from redirecting qualification to a real site.
        report = evidence / 'mrn-release-qa.md'
        execute([settings['qa_engine'], 'run', '--project-root', repo, '--mode', 'release',
                 '--site-path', fixture.public, '--site-url', fixture.url,
                 '--smoke-strict', '1', '--output-file', report],
                evidence / 'mrn-release-qa.log', env=env, timeout=7200, cwd=repo)
        require_engine_pass(report, runtime=True)
        fixture.assert_clean_diagnostics()
        installed = fixture.inventory['runtime']
        if installed.get('release_lock', {}).get('release_id') != built['proof']['release_id']:
            raise ReleaseError('Reference runtime release identity differs')
        # Native runtime schema versions have used either components list or
        # required_components. Inspect actual component rows, never a sync stamp.
        rows = installed.get('components') or installed.get('required_components') or []
        if isinstance(rows, dict):
            rows = list(rows.values())
        expected = read(Path(repo) / 'stack/manifests/stack-release.lock.json')['components']
        by_slug = {row.get('slug'): row for row in rows}
        for item in expected:
            observed = by_slug.get(item['slug'])
            if not observed or observed.get('matches_release') is not True:
                raise ReleaseError('Reference runtime component differs: ' + item['slug'])
        form_success = qualify_form_success(fixture, Path(standalone) / 'mrn-form-success',
                                            settings, evidence, env)
        fixture.close()
        additional_fixture = WordPressFixture(built['bootstrap'], evidence / 'optional-fixture',
                                               settings, additional=built['optional'])
        try:
            additional_fixture.start()
            additional_fixture.assert_clean_diagnostics()
            execute([settings['node'], Path(__file__).with_name('fixture-browser.cjs')],
                    evidence / 'optional-browser.log', stdin=json.dumps(additional_fixture.browser_input()),
                    timeout=900, cwd=repo)
            # Inspect each non-bootstrap source with its own full component QA,
            # against the isolated runtime. Shared native/browser/API/AA/timing
            # coverage above still binds the cumulative default distribution.
            for record in read(Path(built['optional']) / 'manifest.json')['plugins']:
                name = record['source']['repository'].removeprefix('mrnwebdesigns/')
                source = Path(standalone) / name
                report = evidence / ('component-' + record['slug'] + '.md')
                component_env = {**env, 'MRN_QA_SITE_PATH': str(additional_fixture.public),
                                 'MRN_QA_SITE_URL': additional_fixture.url}
                execute([settings['qa_engine'], 'run', '--project-root', source, '--mode', 'release',
                         '--site-path', additional_fixture.public, '--site-url', additional_fixture.url,
                         '--run-smoke', 'never', '--run-accessibility', 'never', '--run-performance', 'never',
                         '--run-cwv', 'never', '--output-file', report],
                        evidence / ('component-' + record['slug'] + '.log'), env=component_env,
                        timeout=1800, cwd=source)
                require_engine_pass(report, runtime=False)
            additional_fixture.assert_clean_diagnostics()
        finally:
            additional_fixture.close()
        proof = {'schema_version': 1, 'status': 'pass',
                 'release_id': built['proof']['release_id'],
                 'source_vector_sha256': selected['source_vector_sha256'],
                 'material_sha256': selected['material_sha256'],
                 'toolchain': toolchain,
                 'held_defaults': read(Path(__file__).with_name('policy.json'))['held_defaults'],
                 'native_presentations': presentations,
                 'lock_sha256': built['proof']['lock_sha256'],
                 'fleet_sha256': file_hash(built['fleet']),
                 'bootstrap_sha256': file_hash(built['bootstrap_archive']),
                 'optional_sha256': file_hash(built['optional_archive']),
                 'coverage': ['source', 'contracts', 'installed-default-packages', 'no-woocommerce',
                              'installed-optional-packages',
                              'native-editor', 'native-wpforms', 'native-presentations', 'api', 'browser',
                              'accessibility', 'performance', 'core-web-vitals', 'distribution'],
                 'evidence': {p.relative_to(evidence).as_posix(): file_hash(p)
                              for p in sorted(evidence.rglob('*')) if p.is_file()
                              and p.suffix in ('.md', '.json', '.log')},
                 'site_adoption_verified': False, 'site_writes': False}
        if form_success:
            proof['coverage'].append('native-form-success')
        write(evidence / 'qualification.json', proof)
        return proof
    finally:
        fixture.close()

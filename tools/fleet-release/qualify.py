"""Bind required MRN/native/browser qualification to exact locked artifacts."""
from __future__ import annotations

import json
import os
from pathlib import Path
import re
import subprocess

from common import ReleaseError, canonical, clean_main, digest, file_hash, read, write
from fixture import WordPressFixture


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


def qualify(repo, standalone, built, settings, evidence, selected):
    evidence = Path(evidence)
    evidence.mkdir(parents=True, exist_ok=True)
    toolchain = {'qa_engine_commit': clean_main(settings['qa_engine_root']),
                 'wp_cli_sha256': file_hash(settings['wp_cli'])}
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
        env = {**os.environ, 'MRN_QA_SITE_PATH': str(fixture.public),
               'MRN_QA_SITE_URL': fixture.url, 'MRN_QA_STACK_ROOT': str(repo),
               'MRN_STANDALONE_PLUGINS_ROOT': str(standalone),
               'MRN_QA_CODE_ANALYSIS_SCOPE': 'all', 'MRN_QA_SCOPE': 'site-only',
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
        fixture.close()
        proof = {'schema_version': 1, 'status': 'pass',
                 'release_id': built['proof']['release_id'],
                 'source_vector_sha256': selected['source_vector_sha256'],
                 'material_sha256': selected['material_sha256'],
                 'toolchain': toolchain,
                 'held_defaults': read(Path(__file__).with_name('policy.json'))['held_defaults'],
                 'lock_sha256': built['proof']['lock_sha256'],
                 'fleet_sha256': file_hash(built['fleet']),
                 'bootstrap_sha256': file_hash(built['bootstrap_archive']),
                 'coverage': ['source', 'contracts', 'installed-default-packages', 'no-woocommerce',
                              'native-editor', 'native-wpforms', 'api', 'browser',
                              'accessibility', 'performance', 'core-web-vitals', 'distribution'],
                 'evidence': {p.relative_to(evidence).as_posix(): file_hash(p)
                              for p in sorted(evidence.rglob('*')) if p.is_file()
                              and p.suffix in ('.md', '.json', '.log')},
                 'site_adoption_verified': False, 'site_writes': False}
        write(evidence / 'qualification.json', proof)
        return proof
    finally:
        fixture.close()

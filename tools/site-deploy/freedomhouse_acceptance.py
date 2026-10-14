#!/usr/bin/env python3
"""Explicit local qualification and release-bound Live readback for Freedom House.

This is an opt-in acceptance path, not a transport or ordering exception.
The normal deployment controller still owns backups, activation and rollback.
"""
import argparse
from datetime import datetime, timezone
import hashlib
import io
import json
import os
from pathlib import Path
import re
import subprocess
import time
import urllib.request
import zipfile

REPOSITORY = 'mrnwebdesigns/freedomhouse-site'
URL = 'https://freedomhouserecovery.org'
SOURCE = 'public/wp-content/themes/freedomhouse-child02'
PROOF = 'qa/release-acceptance/freedomhouse-20261014.json'
PATHS = ['/', '/services/', '/locations/', '/careers/open-positions/']
VIEWPORTS = {'desktop': 1440, 'tablet': 1024, 'mobile': 390}
QA_INPUTS = [SOURCE, 'composer.json', 'composer.lock', 'phpstan.neon.dist',
             'phpstan-bootstrap.php', '.mrn-qa.env', 'qa/phpstan']
REQUIRED_ROWS = ['PHP lint', 'PHPCS', 'WordPress best practices', 'PHP compatibility',
                 'PHPStan', 'Semgrep', 'Secrets scan', 'WordPress API surface audit',
                 'WordPress API runtime smoke', 'qa-theme.sh', 'qa-security.sh',
                 'qa-playwright-local-stack-site.sh', 'Accessibility smoke',
                 'qa-page-speed.sh', 'Core Web Vitals', 'Composer audit', 'PHPUnit']


def require(condition, message):
    if not condition:
        raise ValueError(message)


def recent(value, seconds):
    require(isinstance(value, str) and re.fullmatch(r'\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z', value), 'Invalid evidence timestamp')
    age = (datetime.now(timezone.utc) - datetime.strptime(value, '%Y-%m-%dT%H:%M:%SZ').replace(tzinfo=timezone.utc)).total_seconds()
    require(-60 <= age <= seconds, 'Evidence is stale or future-dated')


def git(*args):
    return subprocess.check_output(['git', *args], text=True).strip()


def qualify(environ):
    require(environ.get('GITHUB_REPOSITORY') == REPOSITORY and
            environ.get('GITHUB_EVENT_NAME') == 'workflow_dispatch' and
            environ.get('GITHUB_REF') == 'refs/heads/main' and
            environ.get('TARGET') == 'live' and environ.get('MODE') == 'deploy' and
            environ.get('SOURCE_BRANCH') == 'main' and
            environ.get('SOURCE_PATH') == SOURCE and
            environ.get('STYLESHEET') == 'freedomhouse-child02' and
            environ.get('DEPLOY_URL', '').rstrip('/') == URL and
            environ.get('LOCAL_QA_ACCEPTANCE') == PROOF,
            'Local acceptance is restricted to the approved Freedom House Live dispatch')
    proof = json.loads(Path(PROOF).read_text())
    require(proof.get('schema') == 1 and proof.get('repository') == REPOSITORY and
            proof.get('url') == URL and proof.get('operator') == environ.get('GITHUB_ACTOR'),
            'Local proof identity or approved operator differs')
    recent(proof['qualified_at'], 7 * 86400)
    source = proof['qualified_source_sha']
    require(re.fullmatch('[0-9a-f]{40}', source) is not None, 'Invalid qualified commit')
    require(git('rev-parse', 'HEAD') == environ['SOURCE_SHA'], 'Selected source differs from checkout')
    # A squash or workflow-only pin change may change the commit, never the QA inputs.
    subprocess.run(['git', 'diff', '--exit-code', '--quiet', source, 'HEAD', '--', *QA_INPUTS], check=True)
    require(git('rev-parse', f'{source}:{SOURCE}') == proof['source_tree'], 'Qualified theme tree differs')
    report_path = proof['report']
    require(re.fullmatch(r'qa/release-acceptance/[a-z0-9-]+\.md', report_path) is not None, 'Unsafe report path')
    body = Path(report_path).read_bytes()
    require(hashlib.sha256(body).hexdigest() == proof['report_sha256'], 'Local report checksum differs')
    report = body.decode()
    require(f'commit: {source}' in report and '- Mode: release' in report and
            '**Release QA Result: 100% SUCCESS**' in report, 'A passing exact-source release report is required')
    for row in REQUIRED_ROWS:
        require(f'| {row} | Ran | Pass |' in report, f'Missing passing required local QA row: {row}')
    require('| Fail |' not in report, 'Local report contains a failed row')
    require(proof.get('php_version') == '8.4.26' and proof.get('parent_version') == '1.2.106' and
            proof.get('searchwp_version') == '4.6.1.1', 'Qualified runtime differs from approved Live baseline')
    return {**proof, 'status': 'locally-qualified-awaiting-live-readback',
            'deployment_run_id': environ['GITHUB_RUN_ID'], 'source_sha': environ['SOURCE_SHA'],
            'artifact_sha256': environ['ARTIFACT_SHA256'], 'hosted_runtime_qa': 'not-run-cloudflare-blocked'}


def validate_readback(receipt, qualification, deployment):
    require(deployment.get('status') == 'public-verified', 'Public-verified activation is required')
    for key in ('repository', 'url', 'operator', 'source_sha', 'artifact_sha256', 'deployment_run_id'):
        require(receipt.get(key) == qualification[key], f'Live readback identity differs: {key}')
    recent(receipt['verified_at'], 1800)
    generation = deployment['current']['public_path']
    require(receipt.get('asset_prefix') == generation, 'Live browser loaded another asset generation')
    rows = receipt.get('pages', [])
    expected = {(path, viewport) for path in PATHS for viewport in VIEWPORTS}
    require(len(rows) == len(expected) and {(row['path'], row['viewport']) for row in rows} == expected,
            'All four Live pages at all three viewports are required')
    prefix = URL + '/wp-content/' + generation + '/'
    for row in rows:
        require(row.get('width') == VIEWPORTS[row['viewport']] and row.get('content_present') is True and
                row.get('horizontal_overflow') is False and row.get('errors') == [] and
                row.get('screenshot_sha256') and re.fullmatch('[0-9a-f]{64}', row['screenshot_sha256']) is not None,
                'Live browser/layout evidence failed')
        styles = row.get('applied_stylesheets', [])
        require(styles and any(item.startswith(prefix) for item in styles) and
                all('/wp-content/mrn-assets/freedomhouse-child02/' not in item or item.startswith(prefix) for item in styles),
                'Live browser applied a stale child stylesheet')
    require(receipt.get('search', {}).get('query') == 'mobile crisis' and
            type(receipt['search'].get('results')) is int and receipt['search']['results'] > 0 and
            receipt.get('admin', {}).get('selected_form') == '2' and
            receipt['admin'].get('repair_notices') == [] and
            receipt.get('services', {}).get('learn_more_count') == 5 and
            receipt['services'].get('contrast_ratio', 0) >= 4.5,
            'Search, admin notice, or Services contrast acceptance failed')
    return {**receipt, 'status': 'local-qa-and-live-readback-accepted',
            'hosted_runtime_qa': 'not-run-cloudflare-blocked'}


class ArtifactRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, *args):
        redirected = super().redirect_request(request, *args)
        if redirected is not None:
            redirected.remove_header('Authorization')
        return redirected


def api(path):
    request = urllib.request.Request('https://api.github.com/repos/' + REPOSITORY + '/' + path,
                                    headers={'Authorization': 'Bearer ' + os.environ['GH_TOKEN'],
                                             'Accept': 'application/vnd.github+json'})
    with urllib.request.urlopen(request, timeout=30) as response:
        return json.load(response)


def wait_readback(qualification, deployment, timeout=1200):
    # The attestation workflow is selected by trusted GitHub metadata, not user artifacts/code.
    deadline = time.monotonic() + timeout
    title = 'Freedom House Live readback ' + qualification['deployment_run_id']
    while time.monotonic() < deadline:
        runs = api('actions/workflows/freedomhouse-live-readback.yml/runs?event=workflow_dispatch&per_page=20')['workflow_runs']
        for run in runs:
            if (run['display_title'] != title or run['head_branch'] != 'main' or
                    run['actor']['login'] != qualification['operator'] or
                    run['head_sha'] != qualification['source_sha'] or
                    run['status'] != 'completed' or run['conclusion'] != 'success'):
                continue
            artifacts = api(f"actions/runs/{run['id']}/artifacts")['artifacts']
            matches = [a for a in artifacts if a['name'] == 'freedomhouse-live-readback' and not a['expired']]
            require(len(matches) == 1 and matches[0]['size_in_bytes'] < 100000, 'Invalid Live evidence artifact')
            request = urllib.request.Request(matches[0]['archive_download_url'],
                                              headers={'Authorization': 'Bearer ' + os.environ['GH_TOKEN']})
            # GitHub supplies the artifact redirect; credentials are never written or logged.
            with urllib.request.build_opener(ArtifactRedirect()).open(request, timeout=30) as response:
                body = response.read(100001)
            require(len(body) <= 100000, 'Live evidence exceeds size limit')
            with zipfile.ZipFile(io.BytesIO(body)) as archive:
                require(archive.namelist() == ['live-readback.json'] and
                        archive.getinfo('live-readback.json').file_size <= 65000, 'Unexpected Live evidence payload')
                receipt = json.loads(archive.read('live-readback.json'))
            return validate_readback(receipt, qualification, deployment)
        time.sleep(20)
    raise ValueError('Fresh release-bound Live readback did not arrive; rollback required')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('mode', choices=['qualify', 'attest', 'wait'])
    parser.add_argument('--receipt', required=True)
    parser.add_argument('--qualification')
    parser.add_argument('--deployment')
    args = parser.parse_args()
    if args.mode == 'qualify':
        result = qualify(os.environ)
    elif args.mode == 'attest':
        result = json.loads(os.environ['LIVE_READBACK'])
        require(os.environ.get('GITHUB_REPOSITORY') == REPOSITORY and
                os.environ.get('GITHUB_REF') == 'refs/heads/main' and
                os.environ.get('GITHUB_EVENT_NAME') == 'workflow_dispatch' and
                result.get('operator') == os.environ.get('GITHUB_ACTOR') and
                result.get('deployment_run_id') == os.environ.get('DEPLOYMENT_RUN_ID'), 'Untrusted readback dispatch')
        recent(result['verified_at'], 1800)
    else:
        qualification = json.loads(Path(args.qualification).read_text())
        deployment = json.loads(Path(args.deployment).read_text())
        # REST remains a fresh Live check in this acceptance path.
        with urllib.request.urlopen(URL + '/wp-json/', timeout=30) as response:
            index = json.load(response)
            require(response.status == 200 and isinstance(index.get('routes'), dict) and index['routes'], 'Live REST health failed')
        result = wait_readback(qualification, deployment)
        result['live_rest'] = 'passed'
    Path(args.receipt).write_text(json.dumps(result, indent=2) + '\n')
    print(result.get('status', 'Live readback evidence recorded'))


if __name__ == '__main__':
    main()

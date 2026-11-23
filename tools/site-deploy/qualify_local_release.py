#!/usr/bin/env python3
"""Run strict MRN QA on a clean local site checkout and optionally publish its legacy status."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys
from urllib.parse import urlsplit


CONTEXT = 'MRN/local-release-qa'


def git(root, *args):
    return subprocess.check_output(['git', '-C', str(root), *args], text=True).strip()


def gh(*args):
    return subprocess.check_output(['gh', 'api', '--hostname', 'github.com', *args], text=True).strip()


def require_source(root, repository, sha):
    if Path(git(root, 'rev-parse', '--show-toplevel')).resolve() != root:
        raise ValueError('The local runtime theme must be its own Git checkout')
    origin = git(root, 'remote', 'get-url', 'origin')
    paths = ('https://github.com/', 'git@github.com:', 'git@github-mrnwebdesigns:', 'ssh://git@github.com/')
    if origin.removesuffix('.git') not in {prefix + repository for prefix in paths}:
        raise ValueError('The local theme origin does not match the requested GitHub repository')
    if git(root, 'rev-parse', 'HEAD') != sha or git(root, 'status', '--porcelain', '--untracked-files=all'):
        raise ValueError('Use a clean local runtime theme checked out at the exact requested SHA')
    if gh(f'repos/{repository}/git/ref/heads/main', '--jq', '.object.sha') != sha:
        raise ValueError('The selected SHA is not the current reviewed main commit')


def publish(args, state, description):
    status = json.loads(gh(f'repos/{args.repo}/statuses/{args.sha}', '--method', 'POST',
                           '-f', f'context={CONTEXT}', '-f', f'state={state}', '-f', f'description={description}'))
    if status.get('state') != state or status.get('context') != CONTEXT or type(status.get('id')) is not int:
        raise ValueError('GitHub did not confirm publication of the exact qualification status')
    return status['id']


def qualify(args):
    if not re.fullmatch(r'mrnwebdesigns/[A-Za-z0-9_.-]+', args.repo):
        raise ValueError('Expected one MRN GitHub repository')
    if not re.fullmatch(r'[0-9a-f]{40}', args.sha):
        raise ValueError('Supply the full merged SHA')
    if not re.fullmatch(r'[A-Za-z0-9_-]+', args.stylesheet):
        raise ValueError('Invalid stylesheet')
    url = urlsplit(args.site_url)
    if (url.scheme not in ('http', 'https') or not url.hostname or url.username or url.password
            or not (url.hostname in ('localhost', '127.0.0.1', '::1') or url.hostname.endswith('.localhost'))):
        raise ValueError('Supply the Local Hub URL for this exact runtime')
    site = args.site_path.resolve(strict=True)
    if not (site / 'wp-load.php').is_file():
        raise ValueError('Site path must be the Local Hub WordPress document root')
    root = (site / 'wp-content' / 'themes' / args.stylesheet).resolve(strict=True)
    evidence = args.evidence_dir.resolve()
    if evidence.is_relative_to(site) or evidence.is_relative_to(root):
        raise ValueError('Keep qualification evidence outside the WordPress runtime and source checkout')
    require_source(root, args.repo, args.sha)
    evidence.mkdir(parents=True, exist_ok=False)
    report = evidence / 'release-qa.md'
    if args.publish:
        publish(args, 'pending', 'Strict local release QA is running.')
    try:
        subprocess.run(['mrn-qa', 'run', '--project-root', str(root), '--scope', 'site-only',
                        '--site-path', str(site), '--site-url', args.site_url, '--mode', 'release',
                        '--smoke-strict', '1', '--run-api', 'always', '--run-smoke', 'always',
                        '--run-accessibility', 'always', '--run-performance', 'always',
                        '--run-cwv', 'always', '--run-phpcbf', 'never', '--output-file', str(report)],
                       env={**os.environ, 'MRN_QA_CODE_ANALYSIS_SCOPE': 'all'}, cwd=root, check=True)
        if not report.is_file() or '**Release QA Result: 100% SUCCESS**' not in report.read_text():
            raise ValueError('Strict release QA did not produce a successful report')
        require_source(root, args.repo, args.sha)
    except (OSError, ValueError, subprocess.CalledProcessError):
        if args.publish:
            publish(args, 'failure', 'Strict local release QA failed or was incomplete. No remote site write.')
        raise
    checksum = hashlib.sha256(report.read_bytes()).hexdigest()
    receipt = {'repository': args.repo, 'source_sha': args.sha, 'context': CONTEXT,
               'site_url': args.site_url, 'report_sha256': checksum, 'qualification': 'strict-local-release-qa'}
    (evidence / 'qualification.json').write_text(json.dumps(receipt, indent=2) + '\n')
    if args.publish:
        receipt['github_status_id'] = publish(args, 'success', f'Strict local QA passed. Report SHA256 {checksum}')
        (evidence / 'qualification.json').write_text(json.dumps(receipt, indent=2) + '\n')
    print(f'Qualified {args.repo}@{args.sha}. Evidence: {evidence}')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', required=True)
    parser.add_argument('--sha', required=True)
    parser.add_argument('--site-path', type=Path, required=True)
    parser.add_argument('--site-url', required=True)
    parser.add_argument('--stylesheet', required=True)
    parser.add_argument('--evidence-dir', type=Path, required=True)
    parser.add_argument('--publish', action='store_true', help='Publish the successful proof to the exact GitHub SHA')
    try:
        qualify(parser.parse_args())
    except (OSError, ValueError, subprocess.CalledProcessError) as error:
        print(f'Qualification stopped. No remote WordPress write. {error}', file=sys.stderr)
        sys.exit(1)

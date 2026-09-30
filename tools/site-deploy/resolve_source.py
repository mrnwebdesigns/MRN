#!/usr/bin/env python3
"""Resolve one same-repository branch; only Dev may use unmerged source."""
import argparse
import os
import re
import subprocess


def resolve(target, branch, workflow_ref):
    if workflow_ref != 'refs/heads/main':
        raise ValueError('Run the trusted deployment workflow from main')
    if target not in ('dev', 'live', 'both'):
        raise ValueError('Invalid target')
    if target != 'dev' and branch != 'main':
        raise ValueError('Live and Both require reviewed main')
    if (not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_./-]*', branch)
            or branch.startswith('refs/') or '..' in branch or '@{' in branch):
        raise ValueError('Select a same-repository branch name, not a SHA, tag, or pull-request ref')
    subprocess.run(['git', 'check-ref-format', '--branch', branch], check=True, capture_output=True)
    subprocess.run(['git', 'fetch', '--no-tags', 'origin',
                    '+refs/heads/' + branch + ':refs/remotes/origin/mrn-deploy-source'],
                   check=True, capture_output=True)
    sha = subprocess.check_output(['git', 'rev-parse', 'refs/remotes/origin/mrn-deploy-source^{commit}'], text=True).strip()
    if not re.fullmatch('[0-9a-f]{40}', sha):
        raise ValueError('Invalid resolved source commit')
    return sha


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--target', required=True)
    parser.add_argument('--branch', required=True)
    args = parser.parse_args()
    sha = resolve(args.target, args.branch, os.environ.get('GITHUB_REF', ''))
    with open(os.environ['GITHUB_OUTPUT'], 'a') as output:
        output.write('source_sha=' + sha + '\n')
    print('Resolved source commit: ' + sha)

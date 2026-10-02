"""Trusted GitHub request selection and per-environment deployment ordering.

No provider credentials or deployment writes belong in this module.
"""
import json
import os
import re
import urllib.request

from resolve_source import resolve


def selection(event, ref, sha, target, mode, branch, dev_main_enabled=True):
    if ref != 'refs/heads/main' or event not in ('push', 'workflow_dispatch'):
        raise ValueError('Only main pushes and manual workflows from main may deploy')
    if target not in ('dev', 'live', 'both') or mode not in ('preflight', 'deploy'):
        raise ValueError('Invalid deployment selection')
    if event == 'push':
        if (target, mode, branch) != ('dev', 'deploy', 'main'):
            raise ValueError('Automatic deployment is Dev-only from main')
        if not re.fullmatch('[0-9a-f]{40}', sha) or sha == '0' * 40:
            raise ValueError('Invalid push commit')
        if not dev_main_enabled:
            return None
        # Never resolve a moving branch tip in place of the triggering commit.
        return sha
    if not dev_main_enabled and (target == 'both' or (target == 'dev' and branch == 'main')):
        raise ValueError('This site protects a separate Dev preview; select its feature branch')
    return resolve(target, branch, ref)


def github_order(environ, source_sha, target, mode):
    if environ.get('GITHUB_ACTIONS') != 'true':
        return None  # Explicit local qualification/operator tooling remains supported.
    event = environ.get('GITHUB_EVENT_NAME')
    if environ.get('GITHUB_REF') != 'refs/heads/main' or event not in ('push', 'workflow_dispatch'):
        raise ValueError('Untrusted deployment trigger')
    if event == 'push' and (target != 'dev' or mode != 'deploy'
                           or source_sha != environ.get('GITHUB_SHA')
                           or source_sha != environ.get('MRN_SOURCE_QA_SHA')):
        raise ValueError('Automatic Dev requires source QA for the exact push commit')
    repository = environ.get('GITHUB_REPOSITORY', '')
    workflow_ref = environ.get('GITHUB_WORKFLOW_REF', '')
    prefix = repository + '/.github/workflows/'
    if not workflow_ref.startswith(prefix) or not workflow_ref.endswith('@refs/heads/main'):
        raise ValueError('Deployment workflow identity is not the trusted main wrapper')
    workflow = workflow_ref.split('@', 1)[0]
    order = {'repository': repository, 'workflow': workflow, 'source_sha': source_sha,
             'event': event, 'run_id': environ.get('GITHUB_RUN_ID', '')}
    for name in ('run_number', 'run_attempt'):
        raw = environ.get('GITHUB_' + name.upper(), '')
        if not re.fullmatch('[1-9][0-9]*', raw):
            raise ValueError('Missing GitHub deployment sequence')
        order[name] = int(raw)
    validate_order(order)
    return order


def validate_order(order):
    if (not isinstance(order, dict)
            or not re.fullmatch(r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', order.get('repository', ''))
            or not re.fullmatch(re.escape(order['repository']) + r'/\.github/workflows/[A-Za-z0-9_.-]+\.ya?ml', order.get('workflow', ''))
            or not re.fullmatch('[0-9a-f]{40}', order.get('source_sha', ''))
            or not re.fullmatch('[1-9][0-9]*', order.get('run_id', ''))
            or order.get('event') not in ('push', 'workflow_dispatch')
            or any(type(order.get(k)) is not int or order[k] < 1 for k in ('run_number', 'run_attempt'))):
        raise ValueError('Invalid deployment order receipt')


def check_order(incoming, previous, rollback=False):
    if incoming is not None:
        validate_order(incoming)
    if previous is None:
        return
    validate_order(previous)
    if incoming is None:
        if rollback:
            return  # Explicit receipt-bound operator rollback; do not reset ordering.
        raise ValueError('Ordered deployment requires the current shared workflow')
    if (incoming['repository'], incoming['workflow']) != (previous['repository'], previous['workflow']):
        raise ValueError('Deployment workflow changed; explicit sequence migration is required')
    if rollback and incoming == previous:
        return  # Recovery in this same serialized deployment job.
    if incoming['run_number'] == previous['run_number'] and (
            incoming['run_id'] != previous['run_id'] or incoming['source_sha'] != previous['source_sha']):
        raise ValueError('A rerun must retain its original run and source identity')
    if (incoming['run_number'], incoming['run_attempt']) <= (previous['run_number'], previous['run_attempt']):
        raise ValueError('Stale or already-attempted deployment run')


def require_current_push(order, environ):
    if not order or order['event'] != 'push':
        return
    token = environ.get('GH_TOKEN') or environ.get('GITHUB_TOKEN')
    if not token:
        raise ValueError('Current main verification requires the read-only GitHub token')
    request = urllib.request.Request(
        'https://api.github.com/repos/' + order['repository'] + '/git/ref/heads/main',
        headers={'Authorization': 'Bearer ' + token, 'Accept': 'application/vnd.github+json'})
    with urllib.request.urlopen(request, timeout=30) as response:
        current = json.load(response)['object']['sha']
    if current != order['source_sha']:
        raise ValueError('Push was superseded by a newer main commit; no deployment allowed')


if __name__ == '__main__':
    sha = selection(os.environ['GITHUB_EVENT_NAME'], os.environ['GITHUB_REF'], os.environ['GITHUB_SHA'],
                    os.environ['TARGET'], os.environ['MODE'], os.environ['SOURCE_BRANCH'],
                    os.environ.get('DEV_MAIN_ENABLED', 'true') == 'true')
    with open(os.environ['GITHUB_OUTPUT'], 'a') as output:
        output.write('enabled=' + ('true' if sha else 'false') + '\n')
        output.write('source_sha=' + (sha or '') + '\n')
    print('Selected commit: ' + sha if sha else 'Automatic Dev is disabled for this site preview')

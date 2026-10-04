"""Trusted GitHub request selection and per-environment deployment ordering.

No provider credentials or deployment writes belong in this module.
"""
import json
import os
import re
import urllib.request
import urllib.parse
from datetime import datetime, timezone

from resolve_source import resolve
import release_request


def source_request(environ, payload=None):
    """Authenticate the no-secret signal; never consume its code or artifacts.

    The downstream workflow and its configuration must come from main. GitHub's
    event envelope supplies the immutable source identity; trusted QA runs anew.
    A cutoff prevents installation pushes and delayed reruns from deploying.
    """
    if environ.get('GITHUB_EVENT_NAME') != 'workflow_run' or environ.get('GITHUB_REF') != 'refs/heads/main':
        raise ValueError('Source-push requests require the trusted main workflow')
    if payload is None:
        with open(environ['GITHUB_EVENT_PATH']) as handle:
            payload = json.load(handle)
    run = payload.get('workflow_run', {})
    repository = environ.get('GITHUB_REPOSITORY')
    if (payload.get('action') != 'completed' or run.get('status') != 'completed'
            or run.get('event') != 'push' or run.get('path') != '.github/workflows/site-push.yml'
            or not (run.get('name') == 'MRN source push'
                    or (run.get('name') == run.get('display_title')
                        and run.get('display_title', '').startswith(release_request.PREFIX)))
            or payload.get('repository', {}).get('full_name') != repository
            or run.get('repository', {}).get('full_name') != repository
            or run.get('head_repository', {}).get('full_name') != repository):
        raise ValueError('Untrusted source-push signal')
    branch = environ.get('AUTO_DEV_BRANCH', 'main')
    if (not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_./-]*', branch)
            or '..' in branch or branch.startswith('refs/')):
        raise ValueError('Invalid automatic Dev branch')
    sha = run.get('head_sha', '')
    if not re.fullmatch('[0-9a-f]{40}', sha) or sha == '0' * 40:
        raise ValueError('Invalid source-push commit')
    if run.get('conclusion') != 'success':
        return None
    request = release_request.signal(environ, run)
    if request and request['kind'] in ('tag', 'disabled'):
        if request['kind'] == 'disabled':
            return None
        request['source_run_number'] = run['run_number']
        return request
    if not request and run.get('head_branch') != branch:
        return None
    cutoff = environ.get('AUTO_DEV_AFTER', '')
    if not cutoff:
        return None
    def utc_time(value):
        if not re.fullmatch(r'\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z', value):
            raise ValueError('Automatic Dev cutoff and signal time must be UTC timestamps')
        return datetime.strptime(value, '%Y-%m-%dT%H:%M:%SZ').replace(tzinfo=timezone.utc)
    if utc_time(run.get('created_at', '')) <= utc_time(cutoff):
        return None
    request = request or {'kind':'branch', 'source_sha':sha, 'source_branch':branch, 'target':'dev'}
    if 'run_number' in run:
        request['source_run_number'] = run['run_number']
    return request


def source_push(environ, payload=None):
    request = source_request(environ, payload)
    return request['source_sha'] if request and request['kind'] == 'branch' else None


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
    if environ.get('GITHUB_REF') != 'refs/heads/main' or event not in ('push', 'workflow_run', 'workflow_dispatch'):
        raise ValueError('Untrusted deployment trigger')
    intent = None
    signal = None
    if event == 'workflow_run':
        signal = source_request(environ)
        if (not signal or mode != 'deploy' or source_sha != signal['source_sha']
                or source_sha != environ.get('MRN_SOURCE_QA_SHA')
                or environ.get('SOURCE_BRANCH') != signal['source_branch']
                or target not in (('dev', 'live') if signal['target'] == 'both' else (signal['target'],))):
            raise ValueError('Deployment requires source QA for the exact approved push request')
        if signal['kind'] == 'tag':
            intent = {k: signal[k] for k in ('kind', 'tag', 'tag_object', 'source_sha', 'target')}
            if json.loads(environ.get('RELEASE_INTENT', '{}')) != intent:
                raise ValueError('Release intent changed after source selection')
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
    if event == 'workflow_run':
        order['source_branch'] = signal['source_branch']
        if 'source_run_number' in signal:
            order['source_run_number'] = signal['source_run_number']
        if intent:
            order['release_intent'] = intent
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
            or order.get('event') not in ('push', 'workflow_run', 'workflow_dispatch')
            or any(type(order.get(k)) is not int or order[k] < 1 for k in ('run_number', 'run_attempt'))):
        raise ValueError('Invalid deployment order receipt')
    if order['event'] == 'workflow_run':
        branch = order.get('source_branch', '')
        if (not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_./-]*', branch)
                or '..' in branch or branch.startswith('refs/')):
            raise ValueError('Invalid ordered source branch')


    if 'release_intent' in order:
        if order['event'] != 'workflow_run':
            raise ValueError('Release tags require the trusted workflow-run receiver')
        release_request.validate_intent(order['release_intent'], order['source_sha'])
    if 'source_run_number' in order and (type(order['source_run_number']) is not int or order['source_run_number'] < 1):
        raise ValueError('Invalid source signal sequence')


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
            incoming['run_id'] != previous['run_id'] or incoming['source_sha'] != previous['source_sha']
            or incoming.get('release_intent') != previous.get('release_intent')):
        raise ValueError('A rerun must retain its original run and source identity')
    if (incoming.get('source_run_number') and previous.get('source_run_number')
            and incoming['source_run_number'] < previous['source_run_number']):
        raise ValueError('Stale source request cannot replace a newer release')
    if (incoming['run_number'], incoming['run_attempt']) <= (previous['run_number'], previous['run_attempt']):
        raise ValueError('Stale or already-attempted deployment run')


def require_current_push(order, environ):
    if not order or order['event'] not in ('push', 'workflow_run'):
        return
    validate_order(order)
    if order.get('release_intent'):
        release_request.current(environ, order['release_intent'])
        return
    branch = order.get('source_branch', 'main')
    token = environ.get('GH_TOKEN') or environ.get('GITHUB_TOKEN')
    if not token:
        raise ValueError('Current branch verification requires the read-only GitHub token')
    request = urllib.request.Request(
        'https://api.github.com/repos/' + order['repository'] + '/git/ref/heads/' + urllib.parse.quote(branch, safe='/'),
        headers={'Authorization': 'Bearer ' + token, 'Accept': 'application/vnd.github+json'})
    with urllib.request.urlopen(request, timeout=30) as response:
        current = json.load(response)['object']['sha']
    if current != order['source_sha']:
        raise ValueError('Push was superseded by a newer branch commit; no deployment allowed')


def select_request(environ):
    branch = environ['SOURCE_BRANCH']
    if environ['GITHUB_EVENT_NAME'] == 'workflow_run':
        if (environ['TARGET'], environ['MODE']) != ('dev', 'deploy'):
            raise ValueError('Source pushes may deploy only Dev')
        branch = environ.get('AUTO_DEV_BRANCH', 'main')
        sha = source_push(environ)
        if branch == 'main' and environ.get('DEV_MAIN_ENABLED', 'true') != 'true':
            sha = None
        if sha and resolve('dev', branch, environ['GITHUB_REF']) != sha:
            print('Source push has been superseded; skipping deployment')
            sha = None
    else:
        sha = selection(environ['GITHUB_EVENT_NAME'], environ['GITHUB_REF'], environ['GITHUB_SHA'],
                        environ['TARGET'], environ['MODE'], branch,
                        environ.get('DEV_MAIN_ENABLED', 'true') == 'true')
    return sha, branch


def deployment_selection(environ):
    if environ['GITHUB_EVENT_NAME'] == 'workflow_run':
        request = source_request(environ)
        if request and request['kind'] == 'tag':
            return request
    sha, branch = select_request(environ)
    target = environ['TARGET']
    if target != 'dev' and environ.get('LIVE_ENABLED', 'true') != 'true':
        raise ValueError('This site is Dev-only; Live/Both are unavailable')
    return {'source_sha':sha, 'source_branch':branch, 'target':target, 'kind':'branch'}


if __name__ == '__main__':
    request = deployment_selection(os.environ)
    sha = request['source_sha']
    intent = ({k:request[k] for k in ('kind', 'tag', 'tag_object', 'source_sha', 'target')}
              if request['kind'] == 'tag' else {})
    with open(os.environ['GITHUB_OUTPUT'], 'a') as output:
        output.write('enabled=' + ('true' if sha else 'false') + '\n')
        output.write('source_sha=' + (sha or '') + '\n')
        output.write('source_branch=' + (request['source_branch'] if sha else '') + '\n')
        output.write('target=' + request['target'] + '\n')
        output.write('release_intent=' + json.dumps(intent, separators=(',', ':')) + '\n')
    print('Selected commit: ' + sha if sha else 'No eligible new source push; no deployment requested')

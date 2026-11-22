"""Trusted GitHub request selection and per-environment deployment ordering.

No provider credentials or deployment writes belong in this module.
"""
import json
import os
import re
import urllib.request
import urllib.parse

from resolve_source import resolve
import release_request


def source_request(environ, payload=None, qualify_main=False):
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
    request = release_request.signal(environ, run, qualify_main=qualify_main)
    if request and request['kind'] in ('tag', 'disabled'):
        if request['kind'] == 'disabled':
            return None
        request['source_run_number'] = run['run_number']
        return request
    main_signal = (request and request['source_branch'] == 'main') or (
        not request and run.get('head_branch') == 'main')
    qualification = {'kind':'qualification', 'source_sha':sha, 'source_branch':'main', 'target':'qualify'}
    if qualify_main and main_signal and (branch != 'main' or environ.get('DEV_MAIN_ENABLED') == 'false'):
        return qualification
    if not request and run.get('head_branch') != branch:
        return None
    if not release_request.after(run.get('created_at', ''), environ.get('AUTO_DEV_AFTER', '')):
        return qualification if qualify_main and main_signal else None
    request = request or {'kind':'branch', 'source_sha':sha, 'source_branch':branch, 'target':'dev'}
    if 'run_number' in run:
        request['source_run_number'] = run['run_number']
    return request


def source_push(environ, payload=None):
    request = source_request(environ, payload)
    return request['source_sha'] if request and request['kind'] == 'branch' else None


def require_hotfix(environ, source_sha, target):
    """Bind the exception to one explicitly confirmed main dispatch."""
    if ((environ.get('GITHUB_EVENT_NAME'), environ.get('GITHUB_REF'), environ.get('SOURCE_BRANCH'), target)
            != ('workflow_dispatch', 'refs/heads/main', 'main', 'live')
            or environ.get('LIVE_ENABLED', 'true') != 'true'):
        raise ValueError('Hotfix requires a manual Live dispatch from main on a Live-enabled site')
    with open(environ['GITHUB_EVENT_PATH']) as handle:
        inputs = json.load(handle).get('inputs', {})
    if (not re.fullmatch(r'[0-9a-f]{40}', source_sha) or source_sha == '0' * 40
            or inputs.get('mode') != 'hotfix' or inputs.get('target') != 'live'
            or inputs.get('hotfix_sha') != source_sha or environ.get('GITHUB_SHA') != source_sha):
        raise ValueError('Hotfix confirmation must match the full reviewed main dispatch SHA')
    reason = inputs.get('hotfix_reason', '')
    if not isinstance(reason, str) or not reason.strip() or not reason.isprintable() or len(reason) > 500:
        raise ValueError('Hotfix requires a one-line emergency reason of up to 500 characters')
    actor = environ.get('GITHUB_ACTOR', '')
    if not re.fullmatch(r'[A-Za-z0-9_-]+(?:\[bot\])?', actor):
        raise ValueError('Hotfix requires the GitHub dispatch actor')
    return {'actor': actor, 'reason': reason.strip()}


def selection(event, ref, sha, target, mode, branch, dev_main_enabled=True):
    if ref != 'refs/heads/main' or event not in ('push', 'workflow_dispatch'):
        raise ValueError('Only main pushes and manual workflows from main may deploy')
    if target not in ('dev', 'staging', 'live', 'both') or mode not in ('preflight', 'deploy', 'qualify', 'hotfix'):
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
    if mode == 'qualify' and branch != 'main':
        raise ValueError('Qualification-only dispatch requires main')
    if mode == 'hotfix' and target != 'live':
        raise ValueError('Hotfix is a Live-only dispatch')
    if target != 'dev' and branch != 'main':
        raise ValueError('Staging, Live and Both require reviewed main')
    if not dev_main_enabled and mode != 'qualify' and (target == 'both' or (target == 'dev' and branch == 'main')):
        raise ValueError('This site protects a separate Dev preview; select its feature branch')
    if branch == 'main':
        if not re.fullmatch('[0-9a-f]{40}', sha) or sha == '0' * 40:
            raise ValueError('Invalid dispatch commit')
        return sha
    return resolve(target, branch, ref)


def github_order(environ, source_sha, target, mode):
    if environ.get('GITHUB_ACTIONS') != 'true':
        return None  # Explicit local qualification/operator tooling remains supported.
    event = environ.get('GITHUB_EVENT_NAME')
    if environ.get('GITHUB_REF') != 'refs/heads/main' or event not in ('push', 'workflow_run', 'workflow_dispatch'):
        raise ValueError('Untrusted deployment trigger')
    hotfix = None
    if mode == 'deploy':
        if environ.get('MODE') == 'hotfix':
            hotfix = require_hotfix(environ, source_sha, target)
        elif environ.get('MRN_SOURCE_QA_SHA') != source_sha:
            raise ValueError('Deployment requires QA for the selected source SHA')
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
    if hotfix:
        order['hotfix'] = hotfix
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
    if 'hotfix' in order:
        details = order['hotfix']
        if (order['event'] != 'workflow_dispatch' or not isinstance(details, dict)
                or set(details) != {'actor', 'reason'} or not all(isinstance(v, str) and v for v in details.values())):
            raise ValueError('Invalid emergency deployment order')
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
            or incoming.get('release_intent') != previous.get('release_intent')
            or incoming.get('hotfix') != previous.get('hotfix')):
        raise ValueError('A rerun must retain its original run and source identity')
    if (incoming.get('source_run_number') and previous.get('source_run_number')
            and incoming['source_run_number'] < previous['source_run_number']):
        raise ValueError('Stale source request cannot replace a newer release')
    if (incoming['run_number'], incoming['run_attempt']) <= (previous['run_number'], previous['run_attempt']):
        raise ValueError('Stale or already-attempted deployment run')


def require_current_push(order, environ):
    if not order:
        return
    if order['event'] == 'workflow_dispatch' and environ.get('SOURCE_BRANCH') != 'main':
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
        raise ValueError('Source was superseded by a newer branch commit; no deployment allowed')


def require_safe_retry(environ, target):
    """Use previous attempt metadata to stop blind retries of target writes."""
    run_id = environ['GITHUB_RUN_ID']
    attempt = environ['GITHUB_RUN_ATTEMPT']
    if target not in ('dev', 'staging', 'live') or any(not re.fullmatch(r'[1-9][0-9]*', n) for n in (run_id, attempt)):
        raise ValueError('Invalid deployment retry identity')
    job_name = f'Site target ({target})'
    for prior in range(1, int(attempt)):
        result = release_request.api(environ, f'actions/runs/{run_id}/attempts/{prior}/jobs?per_page=100')
        jobs = result['jobs']
        if result['total_count'] > len(jobs):
            raise ValueError('Cannot establish the prior write boundary from GitHub job metadata')
        for job in jobs:
            if job['name'].split(' / ')[-1] != job_name:
                continue
            if job['status'] == 'completed' and job.get('conclusion') == 'skipped':
                continue
            steps = {step['name']: step['conclusion'] for step in job['steps']}
            no_write = (steps.get('Preflight or deploy exact child-theme payload') == 'skipped'
                        or steps.get('Confirm no remote site write') == 'success')
            if job['status'] != 'completed' or not no_write:
                raise ValueError(f'Attempt {prior} may have written to {target}. Reconcile target and rollback evidence before starting a new deployment.')


def select_request(environ):
    request = deployment_selection(environ)
    if request['kind'] == 'tag':
        return None, environ.get('AUTO_DEV_BRANCH', 'main')
    return request['source_sha'], request['source_branch']


def deployment_selection(environ):
    if environ['GITHUB_EVENT_NAME'] == 'workflow_run':
        qualify_main = environ.get('QUALIFY_MAIN') == 'true'
        if (environ['TARGET'], environ['MODE']) != ('dev', 'deploy'):
            raise ValueError('Source pushes may deploy only Dev unless an explicit release tag is approved')
        request = source_request(environ, qualify_main=qualify_main)
        if request and request['kind'] in ('tag', 'qualification'):
            return request
        branch = environ.get('AUTO_DEV_BRANCH', 'main')
        if request and (branch != 'main' or environ.get('DEV_MAIN_ENABLED', 'true') == 'true'):
            if resolve('dev', branch, environ['GITHUB_REF']) == request['source_sha']:
                return request
            # Retain qualification for every main event. A superseded event
            # must never use the new tip or dispatch a target.
            if qualify_main and request['source_branch'] == 'main':
                return {**request, 'kind':'qualification', 'target':'qualify'}
        return {'source_sha':None, 'source_branch':branch, 'target':'dev', 'kind':'branch'}
    if (environ['GITHUB_EVENT_NAME'] == 'workflow_dispatch' and environ['MODE'] == 'deploy'
            and environ['SOURCE_BRANCH'] != 'main' and environ.get('GITHUB_RUN_ATTEMPT', '1') != '1'):
        raise ValueError('Start a new feature deployment request or rerun only the failed jobs to retain the original SHA')
    target = environ['TARGET']
    branch = environ['SOURCE_BRANCH']
    sha = selection(environ['GITHUB_EVENT_NAME'], environ['GITHUB_REF'], environ['GITHUB_SHA'],
                    target, environ['MODE'], branch, environ.get('DEV_MAIN_ENABLED', 'true') == 'true')
    if environ['MODE'] == 'qualify':
        return {'source_sha':sha, 'source_branch':branch, 'target':'qualify', 'kind':'qualification'}
    if target in ('live', 'both') and environ.get('LIVE_ENABLED', 'true') != 'true':
        raise ValueError('This site is Dev-only; Live/Both are unavailable')
    if environ['MODE'] == 'hotfix':
        require_hotfix(environ, sha, target)
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

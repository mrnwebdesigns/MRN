#!/usr/bin/env python3
"""Send one metadata-only deployment failure email through a scoped Postmark server.

Run only from the pinned notification workflow. Never execute upstream source,
download its artifacts, include its logs, or retry an ambiguous send.
"""
import argparse
from datetime import datetime, timezone
import json
import os
from pathlib import Path
import re
import sys
import urllib.error
import urllib.request
import uuid

RECIPIENT = 'mrn-developer@mrnwebdesigns.com'
SENDER = 'MRN Deployments <deployments@mrnwebdesigns.com>'
LIMIT = 2 * 1024 * 1024


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Rejected(ValueError):
    """The provider or local validation definitively refused a send."""


def clean(value, limit=180):
    return ''.join(c if 32 <= ord(c) < 127 else ' ' for c in str(value))[:limit]


def context(event, env):
    if env.get('GITHUB_RUN_ATTEMPT') != '1':
        raise ValueError('Notification reruns require provider reconciliation before any resend')
    repository = env.get('GITHUB_REPOSITORY', '')
    if not re.fullmatch(r'mrnwebdesigns/[A-Za-z0-9_.-]+', repository):
        raise ValueError('Expected an MRN repository')
    run = event.get('workflow_run', {})
    # GitHub exposes run-name as name for first-time enrollment runs. The
    # registered workflow is still Deploy site at the exact trusted path.
    name = run.get('name', '')
    qualification = (name == run.get('display_title')
                     and re.fullmatch(r'MRN Dev qualification [0-9a-f]{24}', name))
    if (env.get('GITHUB_EVENT_NAME') != 'workflow_run'
            or event.get('action') != 'completed'
            or event.get('repository', {}).get('full_name') != repository
            or run.get('repository', {}).get('full_name') != repository
            or run.get('head_repository', {}).get('full_name') != repository
            or not (name == 'Deploy site' or qualification)
            or run.get('path', '').split('@', 1)[0] != '.github/workflows/site-deploy.yml'
            or run.get('status') != 'completed'):
        raise ValueError('Not a completed same-repository deployment workflow')
    if run.get('conclusion') != 'failure':
        return None
    if (type(run.get('id')) is not int or run['id'] <= 0
            or type(run.get('run_attempt')) is not int or run['run_attempt'] <= 0
            or not re.fullmatch(r'[0-9a-f]{40}', run.get('head_sha', ''))):
        raise ValueError('Invalid failed deployment identity')
    return {'repository': repository, 'run_id': run['id'], 'run_attempt': run['run_attempt'],
            'workflow_sha': run['head_sha'], 'branch': clean(run.get('head_branch', 'unknown')),
            'url': f'https://github.com/{repository}/actions/runs/{run["id"]}'}


def read_json(opener, request):
    with opener.open(request, timeout=25) as response:
        body = response.read(LIMIT + 1)
    if len(body) > LIMIT:
        raise ValueError('Notification metadata response is too large')
    return json.loads(body)


def metadata(ctx, token, opener):
    if not token:
        raise ValueError('GitHub metadata credential is missing')
    prefix = f'https://api.github.com/repos/{ctx["repository"]}/actions/runs/{ctx["run_id"]}'
    headers = {'Authorization': 'Bearer ' + token, 'Accept': 'application/vnd.github+json',
               'X-GitHub-Api-Version': '2022-11-28'}
    jobs = []
    for page in range(1, 11):
        result = read_json(opener, urllib.request.Request(
            prefix + f'/attempts/{ctx["run_attempt"]}/jobs?per_page=100&page={page}', headers=headers))
        rows = result.get('jobs', [])
        jobs.extend(rows)
        if len(jobs) >= result.get('total_count', len(jobs)):
            break
    else:
        raise ValueError('Deployment job metadata exceeds notification limit')
    failed = [{'name': clean(job.get('name', 'unknown')),
               'steps': [clean(step.get('name', 'unknown')) for step in job.get('steps', [])
                         if step.get('conclusion') == 'failure']}
              for job in jobs if job.get('conclusion') == 'failure']
    # Artifact names are metadata only. Never download or execute their content.
    artifacts = read_json(opener, urllib.request.Request(prefix + '/artifacts?per_page=100', headers=headers))
    shas = {match[1] for artifact in artifacts.get('artifacts', [])
            if (match := re.fullmatch(r'site-(?:source-qa|release|deployment-(?:dev|live))-([0-9a-f]{40})', artifact.get('name', '')))}
    source = next(iter(shas)) if len(shas) == 1 else None
    return {'failed_jobs': failed, 'source_sha': source}


def message(ctx, details):
    failed = details.get('failed_jobs', [])
    lines = ['MRN deployment failed.', '', 'Repository: ' + ctx['repository'],
             f'Run: {ctx["run_id"]}, attempt {ctx["run_attempt"]}',
             'Branch: ' + ctx['branch'],
             'Source commit: ' + (details.get('source_sha') or 'Unavailable; see the workflow evidence.'),
             'Workflow commit: ' + ctx['workflow_sha'], '', 'Failed checks:']
    for job in failed:
        lines.append('- ' + clean(job['name']))
        lines.extend('  - ' + clean(step) for step in job.get('steps', []))
    if not failed:
        lines.append('- Workflow startup or validation failure; see the run link.')
    lines += ['', 'Review the failure and deployment/rollback evidence before retrying.',
              'A failure does not establish whether a runtime write occurred.', '', ctx['url']]
    return {'From': SENDER, 'To': RECIPIENT,
            'Subject': f'[MRN deployment failed] {ctx["repository"]} — run {ctx["run_id"]}',
            'TextBody': '\n'.join(lines), 'MessageStream': 'outbound',
            'Tag': 'deployment-failure', 'TrackOpens': False, 'TrackLinks': 'None',
            'Metadata': {'repository': ctx['repository'], 'run_id': str(ctx['run_id']),
                         'run_attempt': str(ctx['run_attempt']),
                         'notification_key': f'{ctx["repository"]}:{ctx["run_id"]}:{ctx["run_attempt"]}'}}


def send(payload, token, opener):
    if not token or len(token) > 500 or any(ord(c) < 33 for c in token):
        raise Rejected('Scoped Postmark notification credential is missing or invalid')
    request = urllib.request.Request('https://api.postmarkapp.com/email', method='POST',
        data=json.dumps(payload).encode(), headers={'Content-Type': 'application/json',
        'Accept': 'application/json', 'X-Postmark-Server-Token': token})
    result = read_json(opener, request)
    if type(result.get('ErrorCode')) is not int or result['ErrorCode'] < 0:
        raise ValueError('Unexpected Postmark response')
    if result['ErrorCode'] != 0:
        raise Rejected('Postmark rejected the failure notification')
    identifier = str(uuid.UUID(result['MessageID']))
    return {'status': 'provider-accepted', 'message_id': identifier,
            'recipient_server_delivery': 'not_yet_verified'}


def run(event, env, receipt, opener=None):
    if receipt.exists():
        raise ValueError('Notification receipt already exists; reconcile before any resend')
    ctx = context(event, env)
    if ctx is None:
        return {'status': 'not-required'}
    opener = opener or urllib.request.build_opener(NoRedirect)
    details = metadata(ctx, env.get('GH_TOKEN', ''), opener)
    payload = message(ctx, details)
    record = {**ctx, **details, 'recipient': RECIPIENT,
              'recorded_at': datetime.now(timezone.utc).isoformat(), 'status': 'attempt-reserved'}
    receipt.parent.mkdir(parents=True, exist_ok=True)
    # Exclusive reservation precedes the one POST. An unknown result is never retried.
    with receipt.open('x') as handle:
        json.dump(record, handle, indent=2)
    try:
        record.update(send(payload, env.get('POSTMARK_SERVER_TOKEN', ''), opener))
    except Rejected:
        record['status'] = 'rejected'
        raise
    except urllib.error.HTTPError as error:
        record['status'] = 'rejected' if error.code < 500 else 'outcome-unknown'
        raise ValueError('Failure email provider rejected the request; response withheld') from None
    except (ValueError, OSError, urllib.error.URLError, KeyError, TypeError):
        record['status'] = 'outcome-unknown'
        raise ValueError('Failure email outcome is unknown; inspect provider before retrying') from None
    finally:
        receipt.write_text(json.dumps(record, indent=2) + '\n')
    return record


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--receipt', type=Path, required=True)
    args = parser.parse_args()
    try:
        with open(os.environ['GITHUB_EVENT_PATH']) as handle:
            event = json.load(handle)
        result = run(event, os.environ, args.receipt)
        print(json.dumps(result))
    except (ValueError, OSError, KeyError, urllib.error.URLError):
        print('Deployment failure email was not confirmed; inspect the notification receipt. No automatic retry.', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())

#!/usr/bin/env python3
"""Admit the original artifact from a successful MRN Dev or staging deployment."""
import argparse
import json
import os
from pathlib import Path
import re
import sys

from deploy import check
from release_qualification import identity
from release_request import api
from verify_release import parse_json, sha256_file

NUMBER = r'[1-9][0-9]*'
SHA256 = r'[0-9a-f]{64}'
WORKFLOW = 'mrnwebdesigns/MRN/.github/workflows/'
PREFLIGHT_TARGETS = ('dev', 'staging')
PASSING_REPORT = '**Release QA Result: 100% SUCCESS**'


def read_json(path, limit=65536):
    if path.is_symlink() or not path.is_file() or path.stat().st_size > limit:
        raise ValueError(f'Missing or invalid {path.name}')
    return parse_json(path.read_bytes())


def require_report(path):
    if path.is_symlink() or not path.is_file() or path.stat().st_size > 5 * 1024 * 1024:
        raise ValueError('Missing or invalid pre-production runtime QA report')
    if PASSING_REPORT not in path.read_text():
        raise ValueError('Pre-production runtime QA did not pass')
    with path.open('rb') as stream:
        return sha256_file(stream)


def write_receipt(directory, env, destination):
    expected = identity(env)
    result = read_json(directory / 'site-deployment.json')
    environment = env['TARGET']
    if (environment not in PREFLIGHT_TARGETS or env['SOURCE_BRANCH'] != 'main'
            or (result.get('repository'), result.get('environment'), result.get('source_sha'), result.get('status'))
            != (expected['repository'], environment, expected['source_sha'], 'public-verified')
            or result.get('current', {}).get('release_id') != expected['archive_sha256']
            or result.get('transfer_backup', {}).get('valid') is not True):
        raise ValueError('Deployment did not verify this exact protected pre-production release')
    receipt = {
        **expected, 'environment': environment, 'run_attempt': check(env['GITHUB_RUN_ATTEMPT'], NUMBER, 'QA attempt'),
        'archive_artifact_id': check(env['ARTIFACT_ID'], NUMBER, 'archive artifact ID'),
        'runtime_qa_sha256': require_report(directory / 'site-runtime-qa.md'),
    }
    destination.write_text(json.dumps(receipt, indent=2, sort_keys=True) + '\n')
    return receipt


def wrapper_path(env):
    prefix = env['GITHUB_REPOSITORY'] + '/'
    ref = env['GITHUB_WORKFLOW_REF']
    if not ref.startswith(prefix + '.github/workflows/') or not ref.endswith('@refs/heads/main'):
        raise ValueError('Promotion requires the trusted main deployment workflow')
    return ref.removeprefix(prefix).removesuffix('@refs/heads/main')


def validate_run(run, run_id, env):
    same_run = run_id == env['GITHUB_RUN_ID']
    workflows = {row.get('path'): row.get('sha') for row in run.get('referenced_workflows', [])}
    pin = env['TOOLING_REF']
    if (str(run.get('id')) != run_id or run.get('path', '').split('@', 1)[0] != wrapper_path(env)
            or run.get('event') not in ('push', 'workflow_dispatch', 'workflow_run')
            or run.get('head_branch') != 'main'
            or run.get('repository', {}).get('full_name') != env['GITHUB_REPOSITORY']
            or run.get('head_repository', {}).get('full_name') != env['GITHUB_REPOSITORY']
            or (run['event'] != 'workflow_run' and run.get('head_sha') != env['SOURCE_SHA'])
            or not all(workflows.get(WORKFLOW + workflow + '@' + pin) == pin
                       for workflow in ('site-deploy.yml', 'site-deploy-target.yml',
                                        'site-source-qa.yml', 'site-release-build.yml'))
            or (not same_run and (run.get('status'), run.get('conclusion')) != ('completed', 'success'))):
        raise ValueError('Promotion requires a passing run from this repository and pinned deployment workflow')


def validate_artifact(artifact, name, run_id=None):
    if artifact.get('name') != name or artifact.get('expired') is not False:
        raise ValueError('Promotion artifact is missing, expired, or has an unexpected name')
    origin = check(str(artifact.get('workflow_run', {}).get('id', '')), NUMBER, 'artifact run ID')
    if run_id and origin != run_id:
        raise ValueError('Promotion artifacts must come from the verified producer run')
    return origin


def select(env):
    name = 'site-promotion-' + env['SOURCE_SHA']
    requested = env.get('PROMOTION_EVIDENCE_ID', '')
    if requested:
        candidates = [api(env, 'actions/artifacts/' + check(requested, NUMBER, 'promotion evidence ID'))]
    else:
        if env.get('PROMOTION_SAME_RUN') == 'true':
            raise ValueError('Both requires passing Dev QA from this run')
        response = api(env, f'actions/artifacts?name={name}&per_page=100')
        if response.get('total_count', 0) > len(response['artifacts']):
            raise ValueError('Select an explicit promotion evidence artifact ID for this SHA')
        candidates = sorted(response['artifacts'], key=lambda item: item['id'], reverse=True)
    for artifact in candidates:
        try:
            run_id = validate_artifact(artifact, name)
            if env.get('PROMOTION_SAME_RUN') == 'true' and run_id != env['GITHUB_RUN_ID']:
                raise ValueError('Both requires passing Dev QA from this run')
            validate_run(api(env, f'actions/runs/{run_id}'), run_id, env)
            return {'evidence_id': check(str(artifact['id']), NUMBER, 'promotion evidence ID'), 'run_id': run_id}
        except ValueError:
            if requested:
                raise
    raise ValueError('No passing Dev or staging QA evidence for this exact source and tooling pin')


def verify_receipt(path, env, run_id):
    if env.get('PROMOTION_SAME_RUN') == 'true' and run_id != env['GITHUB_RUN_ID']:
        raise ValueError('Both requires passing Dev QA from this run')
    receipt = read_json(path)
    environment = receipt.get('environment')
    if environment not in PREFLIGHT_TARGETS or (env.get('PROMOTION_SAME_RUN') == 'true' and environment != 'dev'):
        raise ValueError('Promotion requires QA from the selected pre-production environment')
    attempt = check(receipt.get('run_attempt', ''), NUMBER, 'producer run attempt')
    if (run_id == env['GITHUB_RUN_ID'] and int(attempt) > int(env['GITHUB_RUN_ATTEMPT'])):
        raise ValueError('Promotion refers to a future run attempt')
    inherited = dict(env, GITHUB_RUN_ID=run_id, BUILD_ATTEMPT=receipt['build_attempt'],
                     ARTIFACT_SHA256=receipt['archive_sha256'], QA_ARTIFACT_ID=receipt['source_qa_artifact_id'],
                     QA_REPORT_SHA256=receipt['source_qa_report_sha256'])
    expected = identity(inherited)
    if (any(receipt.get(key) != value for key, value in expected.items())
            or int(expected['build_attempt']) > int(attempt)
            or receipt.get('runtime_qa_sha256') != require_report(path.with_name('site-runtime-qa.md'))):
        raise ValueError('Promotion receipt does not match the exact source, workflow, artifact and passing QA report')
    prefix = f'actions/runs/{run_id}/attempts/{attempt}'
    run = api(env, prefix)
    validate_run(run, run_id, env)
    if str(run.get('run_attempt')) != attempt:
        raise ValueError('Promotion run attempt changed')
    jobs = api(env, prefix + '/jobs?per_page=100')
    passing = []
    for job in jobs['jobs']:
        if job['name'].split(' / ')[-1] == f'Site target ({environment})' and job['conclusion'] == 'success':
            steps = {step['name']: step['conclusion'] for step in job['steps']}
            passing.append(all(steps.get(name) == 'success' for name in (
                'Preflight or deploy exact child-theme payload', 'Verify browser-loaded assets and responsive layout contracts',
                'Run runtime QA', 'Publish passing pre-production promotion evidence')))
    if jobs['total_count'] > len(jobs['jobs']) or passing != [True]:
        raise ValueError('GitHub did not verify a successful pre-production deployment and QA producer')
    archive_id = check(receipt.get('archive_artifact_id', ''), NUMBER, 'archive artifact ID')
    validate_artifact(api(env, 'actions/artifacts/' + archive_id),
                      f"site-release-{env['SOURCE_SHA']}-attempt-{expected['build_attempt']}", run_id)
    return {'archive_id': archive_id, 'archive_sha256': expected['archive_sha256'], 'run_id': run_id,
            'run_attempt': attempt, 'build_attempt': expected['build_attempt'],
            'qa_artifact_id': expected['source_qa_artifact_id'], 'qa_report_sha256': expected['source_qa_report_sha256']}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('operation', choices=('write', 'select', 'verify'))
    parser.add_argument('--receipt', type=Path)
    parser.add_argument('--run-id')
    args = parser.parse_args()
    try:
        if args.operation == 'write':
            write_receipt(args.receipt.parent, os.environ, args.receipt)
        else:
            result = (select(os.environ) if args.operation == 'select'
                      else verify_receipt(args.receipt, os.environ, check(args.run_id, NUMBER, 'producer run ID')))
            with open(os.environ['GITHUB_OUTPUT'], 'a') as output:
                for key, value in result.items():
                    print(f'{key}={value}', file=output)
    except (ValueError, KeyError, OSError, TypeError) as error:
        print(f'Promotion blocked. No Live write. {error}', file=sys.stderr)
        sys.exit(1)

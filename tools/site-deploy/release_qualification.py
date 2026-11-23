#!/usr/bin/env python3
"""Bind successful hosted source QA and the exact verified tar to one Actions run."""
import argparse
import json
import os
from pathlib import Path

from deploy import check
from verify_release import parse_json, sha256_file


def identity(env):
    return {
        'schema': 1,
        'qualification': 'source-qa-and-verified-artifact',
        'repository': check(env['GITHUB_REPOSITORY'], r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', 'repository'),
        'workflow': env['GITHUB_WORKFLOW_REF'],
        'run_id': check(env['GITHUB_RUN_ID'], r'[1-9][0-9]*', 'run ID'),
        'tooling_sha': check(env['TOOLING_REF'], r'[0-9a-f]{40}', 'tooling SHA'),
        'source_sha': check(env['SOURCE_SHA'], r'[0-9a-f]{40}', 'source SHA'),
        'source_path': env['SOURCE_PATH'],
        'stylesheet': env['SLUG'],
        'archive_sha256': check(env['ARTIFACT_SHA256'], r'[0-9a-f]{64}', 'artifact SHA-256'),
        'source_qa_artifact_id': check(env['QA_ARTIFACT_ID'], r'[1-9][0-9]*', 'QA artifact ID'),
        'source_qa_report_sha256': check(env['QA_REPORT_SHA256'], r'[0-9a-f]{64}', 'QA report SHA-256'),
        'build_attempt': check(env['BUILD_ATTEMPT'], r'[1-9][0-9]*', 'build attempt'),
    }


def require_report(path, expected):
    report = path.with_name('site-source-qa.md')
    if report.is_symlink() or not report.is_file():
        raise ValueError('Successful source QA report is missing from the artifact')
    with report.open('rb') as stream:
        if sha256_file(stream) != expected['source_qa_report_sha256']:
            raise ValueError('Source QA report does not match the successful QA job')


def verify(path, env):
    path = Path(path)
    if path.is_symlink() or not path.is_file() or path.stat().st_size > 8192:
        raise ValueError('Missing or invalid release qualification receipt')
    expected = identity(env)
    # A rerun of failed jobs can reuse a successful build in the same run.
    if int(expected['build_attempt']) > int(env['GITHUB_RUN_ATTEMPT']):
        raise ValueError('Qualification came from a newer run attempt')
    if parse_json(path.read_bytes()) != expected:
        raise ValueError('Qualification does not match this repository, run, source, QA and artifact')
    require_report(path, expected)
    return expected


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('operation', choices=('write', 'verify'))
    parser.add_argument('receipt', type=Path)
    args = parser.parse_args()
    if args.operation == 'write':
        expected = identity(os.environ)
        require_report(args.receipt, expected)
        args.receipt.write_text(json.dumps(expected, indent=2, sort_keys=True) + '\n')
    else:
        verify(args.receipt, os.environ)

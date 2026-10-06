#!/usr/bin/env python3
"""Owner-only 1Password broker; stdout is private input to enroll_dev.py.

The host's MRN service account must be provisioned out of band. Never run this
interactively to inspect secret values. No personal account fallback is allowed.
"""
import argparse
import json
import os
import re
import subprocess
import sys
from enroll_dev import ACCOUNT, private_path


def resolve(config_path, request):
    if request.get('account') != ACCOUNT:
        raise ValueError('Only the MRN business account is allowed')
    for key, pattern in [('domain', r'[a-z0-9][a-z0-9-]*\.mrndev\.io'), ('site_user', r'[a-z_][a-z0-9_-]*')]:
        if not re.fullmatch(pattern, request.get(key, '')):
            raise ValueError('Invalid credential target')
    config = json.loads(private_path(config_path).read_text())
    if config.get('account') != ACCOUNT:
        raise ValueError('Credential configuration belongs to another account')
    if not os.environ.get('OP_SERVICE_ACCOUNT_TOKEN') or not config.get('service_account_id'):
        raise ValueError('A configured noninteractive MRN service identity is required')
    env = {k: v for k, v in os.environ.items() if not k.startswith('OP_CONNECT_')}
    env['OP_ACCOUNT'] = ACCOUNT
    identity = subprocess.run(['op', 'whoami', '--format=json'], env=env,
                              capture_output=True, text=True, timeout=30)
    if identity.returncode:
        raise ValueError('MRN service account authentication failed')
    who = json.loads(identity.stdout)
    if (who.get('url', '').removeprefix('https://').rstrip('/') != ACCOUNT
            or who.get('user_uuid') != config['service_account_id']):
        raise ValueError('Credential service identity is not the configured MRN account')
    result = {'account': ACCOUNT}
    for name in ('github_token', 'qa_engine_token', 'deploy_private_key'):
        reference = config['references'][name].format(domain=request['domain'], site_user=request['site_user'])
        if not reference.startswith('op://Production Hub/') or '\n' in reference:
            raise ValueError('Credential reference must use the MRN Production Hub vault')
        process = subprocess.run(['op', 'read', reference],
            env=env, capture_output=True, text=True, timeout=30)
        if process.returncode or not process.stdout.strip():
            raise ValueError('MRN service credential is unavailable: ' + name)
        result[name] = process.stdout.strip()
    return result


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config', required=True)
    args = parser.parse_args()
    try:
        request = json.loads(sys.stdin.read(8192))
        print(json.dumps(resolve(args.config, request)))
    except (ValueError, KeyError, OSError, subprocess.TimeoutExpired):
        print('MRN credential lookup failed; secret output withheld', file=sys.stderr)
        sys.exit(1)

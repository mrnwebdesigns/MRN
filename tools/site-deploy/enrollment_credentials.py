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


def op_json(arguments, env):
    # The broker receives its request on stdin. Do not let op interpret that
    # inherited pipe as an item template; SSH key creation rejects piped input.
    result = subprocess.run(['op', *arguments, '--format=json'], env=env,
                            stdin=subprocess.DEVNULL, capture_output=True, text=True, timeout=60)
    if result.returncode:
        raise ValueError('MRN credential operation failed; provider output withheld')
    return json.loads(result.stdout)


def deployment_identity(config, request, env):
    """Create one vault-backed identity for a newly enrolled owner, never rotate it.

    The controller invokes this only after validating its root-owned new-site
    queue/state and fresh WordPress identity. A failed read must never be treated
    as a missing key. Looking up the exact title recovers an unknown create result.
    """
    settings = config['new_site_identity']
    vault = config['vault_id']
    if settings.get('mode') != 'create-for-new-site' or not re.fullmatch(r'[a-z0-9]{26}', vault):
        raise ValueError('Explicit new-site identity provisioning and vault UUID required')
    host = settings.get('host', '')
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*', host):
        raise ValueError('Invalid deployment identity host')
    title = 'MRN Dev Deployment - ' + request['domain'] + ' - ' + request['site_user']
    rows = op_json(['item', 'list', '--vault', vault], env)
    matches = [row for row in rows if row.get('title') == title]
    if len(matches) > 1:
        raise ValueError('Ambiguous deployment identity; operator reconciliation required')
    binding = {'mrn_domain': request['domain'], 'mrn_site_user': request['site_user'], 'mrn_host': host}
    if matches:
        item_id = matches[0]['id']
    else:
        # Only non-secret binding metadata is passed in arguments. op creates and
        # stores the key; its private output remains inside this broker process.
        created = op_json(['item', 'create', '--vault', vault, '--category=SSH Key',
                          '--title', title, '--ssh-generate-key=ed25519',
                          '--tags=mrn-dev-enrollment',
                          *[key + '[text]=' + value for key, value in binding.items()]], env)
        item_id = created['id']
    if not re.fullmatch(r'[a-z0-9]{26}', item_id):
        raise ValueError('Invalid deployment identity item')
    item = op_json(['item', 'get', item_id, '--vault', vault], env)
    fields = {field['id']: field.get('value', '') for field in item.get('fields', [])}
    labels = {field.get('label'): field.get('value', '') for field in item.get('fields', [])}
    if (item.get('vault', {}).get('id') != vault or item.get('title') != title
            or item.get('category') != 'SSH_KEY'
            or any(labels.get(key) != value for key, value in binding.items())):
        raise ValueError('Deployment identity does not match this new site')
    key = fields.get('private_key', '')
    if not key.startswith('-----BEGIN OPENSSH PRIVATE KEY-----'):
        raise ValueError('Deployment private key unavailable; existing identity preserved')
    return key.strip()


def resolve(config_path, request):
    if request.get('account') != ACCOUNT:
        raise ValueError('Only the MRN business account is allowed')
    for key, pattern in [('domain', r'[a-z0-9][a-z0-9-]*\.mrndev\.io'), ('site_user', r'[a-z_][a-z0-9_-]*')]:
        if not re.fullmatch(pattern, request.get(key, '')):
            raise ValueError('Invalid credential target')
    config = json.loads(private_path(config_path).read_text())
    if config.get('account') != ACCOUNT:
        raise ValueError('Credential configuration belongs to another account')
    env = {k: v for k, v in os.environ.items() if not k.startswith('OP_CONNECT_')}
    env['OP_ACCOUNT'] = ACCOUNT
    if config.get('service_token_credential'):
        path = private_path(config['service_token_credential'])
        if path.stat().st_mode & 0o077:
            raise ValueError('Encrypted service credential must be owner-only')
        decoded = subprocess.run(['systemd-creds', 'decrypt', '--name=mrn-dev-enrollment', str(path), '-'],
                                 capture_output=True, text=True, timeout=30)
        if decoded.returncode or not decoded.stdout.strip().startswith('ops_'):
            raise ValueError('Encrypted MRN service identity is unavailable')
        env['OP_SERVICE_ACCOUNT_TOKEN'] = decoded.stdout.strip()
    if not env.get('OP_SERVICE_ACCOUNT_TOKEN') or not config.get('service_account_id'):
        raise ValueError('A configured noninteractive MRN service identity is required')
    identity = subprocess.run(['op', 'whoami', '--format=json'], env=env,
                              capture_output=True, text=True, timeout=30)
    if identity.returncode:
        raise ValueError('MRN service account authentication failed')
    who = json.loads(identity.stdout)
    if (who.get('url', '').removeprefix('https://').rstrip('/') != ACCOUNT
            or who.get('user_uuid') != config['service_account_id']):
        raise ValueError('Credential service identity is not the configured MRN account')
    result = {'account': ACCOUNT}
    vault = config.get('vault_id', 'Production Hub')
    if vault != 'Production Hub' and not re.fullmatch(r'[a-z0-9]{26}', vault):
        raise ValueError('Credential vault must be an explicit MRN vault UUID')
    provision = 'new_site_identity' in config
    if provision and 'deploy_private_key' in config['references']:
        raise ValueError('Choose an existing identity reference or new-site provisioning')
    names = ('github_token', 'qa_engine_token') if provision else ('github_token', 'qa_engine_token', 'deploy_private_key')
    for name in names:
        reference = config['references'][name].format(domain=request['domain'], site_user=request['site_user'])
        if not reference.startswith('op://' + vault + '/') or any(ord(c) < 32 for c in reference):
            raise ValueError('Credential reference must use the configured MRN vault')
        process = subprocess.run(['op', 'read', reference],
            env=env, capture_output=True, text=True, timeout=30)
        if process.returncode or not process.stdout.strip():
            raise ValueError('MRN service credential is unavailable: ' + name)
        result[name] = process.stdout.strip()
    if provision:
        result['deploy_private_key'] = deployment_identity(config, request, env)
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

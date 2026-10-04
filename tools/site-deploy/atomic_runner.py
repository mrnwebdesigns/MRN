#!/usr/bin/env python3
"""Transfer trusted release tooling after backup, then run the qualified host controller."""
import argparse
import base64
import hashlib
import json
import os
from pathlib import Path
import shlex
import subprocess
import time

from deploy import Target, check, config, digest, verify_identity, verify_state_privacy, verify_git_privacy
from deployment_request import check_order, github_order, require_current_push
from verify_release import verify

TOOLS = Path(__file__).resolve().parent
HOST_FILES = ('host_controller.py', 'atomic_store.py', 'cache_policy.py', 'deploy.py',
              'verify_release.py', 'verify_public_assets.py', 'backup.php', 'release-bootstrap.php',
              'host_paths.py', 'html_cache.php', 'kinsta.py', 'kinsta_html_cache.php',
              'deployment_request.py', 'resolve_source.py')


def transfer_backup(target):
    label = 'mrn-tool-transfer-' + str(int(time.time()))
    if target.native_backup:
        return target.native_backup.backup(label)
    code = (TOOLS / 'backup.php').read_text().removeprefix('<?php')
    # Target.php normally loads the active theme. This pre-transfer backup must
    # remain available for rollback after a theme bootstrap failure.
    command = shlex.join(['env', 'WP_CLI_PHP_ARGS=-d memory_limit=512M', 'MRN_BACKUP_LABEL=' + label, 'wp', '--path=' + target.c['root'],
                          '--skip-themes', 'eval', code])
    output = target.shell(command + '\n')
    receipts = [json.loads(line[11:]) for line in output.splitlines() if line.startswith('MRN_RESULT=')]
    if len(receipts) != 1 or receipts[0].get('valid') is not True or receipts[0].get('label') != label:
        raise ValueError('Tool transfer requires a fresh verified remote backup')
    return receipts[0]


def run(plan, c):
    # First adoption is an explicit qualification operation. Routine deployments
    # require the readiness switch plus the exact existing release pointer.
    native = c.get('host_provider') == 'kinsta' and c['backup_provider'] == 'kinsta'
    if native and not c.get('native_transaction_backup_approved'):
        raise ValueError('Native transaction backup policy requires explicit owner approval')
    if (c['backup_provider'] != 'updraft' and not native) or plan['environment'] not in ('dev', 'live'):
        raise ValueError('No qualified activation adapter for this backup provider')
    if c.get('host_provider', 'cloudpanel') == 'cloudpanel' and (plan['environment'] != 'dev' or not c['url'].endswith('.mrndev.io')):
        raise ValueError('Live activation remains disabled for the CloudPanel Dev adapter')
    if not plan.get('adopt') and not c.get('ready'):
        raise ValueError('DEPLOY_READY must be enabled after qualification')
    if not plan.get('rollback_to'):
        verify(plan['archive'], plan['artifact_sha256'], plan['source_sha'], plan['source_path'], plan['slug'])
    target = Target(c)
    # Recovery must not execute a potentially broken active child theme. The
    # pointer and raw public identity remain readable with theme loading skipped.
    before = target.inspect(plan['slug'], skip_themes=True)
    verify_identity(c, before, plan['slug'])
    verify_state_privacy(c, before)
    if before['git']:
        verify_git_privacy(c, before)
    if before['state'] != plan.get('expected_current'):
        raise ValueError('Target changed before backup and transfer')
    if plan.get('adopt') and digest(before['files']) != plan['baseline']:
        raise ValueError('Target differs from reviewed adoption baseline')
    order = plan.get('deployment_order')
    check_order(order, before.get('deployment_order'), rollback=bool(plan.get('rollback_to')))
    if not plan.get('rollback_to'):
        require_current_push(order, os.environ)
    backup = transfer_backup(target)
    job = c['state_dir'] + '/jobs/' + str(time.time_ns())
    check(job, r'/[A-Za-z0-9_./-]+', 'private job path')
    target.shell('umask 077\nmkdir -p ' + shlex.quote(job) + '\n')
    transfers = []
    for name in HOST_FILES:
        data = (TOOLS / name).read_bytes()
        encoded = base64.b64encode(data).decode()
        path = job + '/' + name
        script = "import base64,hashlib,pathlib; b=base64.b64decode(" + repr(encoded) + "); "
        script += "assert hashlib.sha256(b).hexdigest()==" + repr(hashlib.sha256(data).hexdigest()) + "; "
        script += "p=pathlib.Path(" + repr(path) + "); p.open('xb').write(b)"
        transfers.append('python3 -c ' + shlex.quote(script))
    target.shell('umask 077\n' + '\n'.join(transfers) + '\n')
    remote_plan = {**plan, **{k: c[k] for k in ('url', 'root', 'template', 'state_dir', 'backup_provider')},
                   'host_provider': c.get('host_provider', 'cloudpanel'), 'ssh_host': c['host'], 'ssh_user': c['user'], 'ssh_port': c['port']}
    if not plan.get('rollback_to'):
        # No shell interpolation of payload or credentials. Host key checking is
        # inherited from the verified site-owner Target connection.
        remote_archive = job + '/release.tar'
        encoded_archive = base64.b64encode(Path(plan['archive']).read_bytes()).decode()
        target.shell('umask 077\nbase64 -d > ' + shlex.quote(remote_archive) +
                     " <<'MRN_RELEASE_PAYLOAD'\n" + encoded_archive + '\nMRN_RELEASE_PAYLOAD\n')
        remote_plan['archive'] = remote_archive
    envelope = {'plan': remote_plan}
    if native:
        remote_plan['native_backup_receipt'] = backup
        envelope['native'] = {'token': target.native_backup.token, 'site_id': target.native_backup.site_id,
                              'environment_id': target.native_backup.environment_id}
    if not plan.get('rollback_to'):
        require_current_push(order, os.environ)
    process = target.controller(job + '/host_controller.py', envelope)
    receipts = [json.loads(line[11:]) for line in process.stdout.splitlines() if line.startswith('MRN_RESULT=')]
    if len(receipts) != 1:
        raise RuntimeError('Host controller failed without a receipt; inspect the retained private job ' + job)
    receipt = {**receipts[0], 'transfer_backup': backup, 'private_job': job}
    return receipt


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    source = parser.add_mutually_exclusive_group(required=True)
    source.add_argument('--plan')
    source.add_argument('--rollback-receipt')
    parser.add_argument('--receipt', required=True)
    args = parser.parse_args()
    if args.rollback_receipt:
        previous = json.loads(Path(args.rollback_receipt).read_text())
        if previous.get('status') != 'public-verified' or not previous.get('previous'):
            raise ValueError('No verified previous release is available for rollback')
        pages = list(dict.fromkeys(row['url'] for row in previous['activation']['cache']['pages']))
        plan = {key: previous[key] for key in ('repository', 'environment', 'slug')}
        plan.update(expected_current=previous['current'], rollback_to=previous['previous']['release_id'], pages=pages,
                    deployment_order=previous.get('deployment_order') if os.environ.get('GITHUB_ACTIONS') == 'true' else None)
        if os.environ.get('GITHUB_ACTIONS') == 'true':
            order = github_order(os.environ, previous['source_sha'], previous['environment'], 'deploy')
            if order != plan['deployment_order']:
                raise ValueError('Automatic rollback must belong to the original deployment job')
    else:
        plan = json.loads(Path(args.plan).read_text())
    receipt = run(plan, config(os.environ))
    Path(args.receipt).write_text(json.dumps(receipt, indent=2) + '\n')
    print(json.dumps({key: receipt.get(key) for key in ('status', 'current', 'previous', 'error', 'recovery')}))
    raise SystemExit(0 if receipt['status'] == 'public-verified' else 1)

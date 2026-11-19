#!/usr/bin/env python3
"""Install accepted onboarding tooling with exact-host/target and rollback gates."""
import argparse
import fcntl
import hashlib
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import sys
import time

REPO = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO / 'tools/site-deploy'))
sys.path.insert(0, str(REPO / 'stack/scripts'))
from bootstrap_readiness import save
from enroll_dev import identify


def git(root, *args):
    return subprocess.check_output(['git', '-C', str(root), *args], text=True).strip()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--site-path', required=True)
    parser.add_argument('--expected-tooling-ref', required=True)
    parser.add_argument('--expected-cert-script-sha256', required=True)
    parser.add_argument('--execute', action='store_true')
    args = parser.parse_args()
    if os.geteuid() != 0 or socket.gethostname() != 'mrndev':
        raise ValueError('Expected root on the verified mrndev host')
    config_path = Path('/etc/mrn/dev-enrollment.json')
    config = json.loads(config_path.read_text())
    target = Path('/opt/mrn-site-deploy/MRN')
    cert_script = Path('/usr/local/sbin/mrn-cert-sync')
    new_ref = git(REPO, 'rev-parse', 'HEAD')
    if (config['tooling_ref'] != args.expected_tooling_ref or git(target, 'rev-parse', 'HEAD') != args.expected_tooling_ref
            or git(target, 'status', '--porcelain', '--untracked-files=no')
            or git(REPO, 'status', '--porcelain', '--untracked-files=no')
            or hashlib.sha256(cert_script.read_bytes()).hexdigest() != args.expected_cert_script_sha256):
        raise ValueError('Host tooling changed since reviewed preflight')
    site = identify(args.site_path, config)
    from cert_sync import zone_for
    key = Path('/etc/nginx/ssl-certificates') / (zone_for(site['domain']) + '.key')
    if key.is_symlink() or key.stat().st_uid != 0:
        raise ValueError('Origin private key ownership differs')
    result = {'schema':1, 'site':site['url'], 'old_ref':config['tooling_ref'], 'new_ref':new_ref, 'status':'planned'}
    if not args.execute:
        print(json.dumps(result)); return
    locks = []
    try:
        for path in ('/var/run/mrn-bootstrap.lock', '/var/run/mrn-cert-sync.lock', '/var/lib/mrn-cert-sync/lock'):
            stream = open(path, 'a'); locks.append(stream)
            fcntl.flock(stream, fcntl.LOCK_EX | fcntl.LOCK_NB)
        recovery = Path('/var/lib/mrn-onboarding-install') / str(time.time_ns())
        recovery.mkdir(parents=True, mode=0o700)
        for path, name in ((config_path,'dev-enrollment.json'),(cert_script,'mrn-cert-sync')):
            shutil.copyfile(path, recovery/name); (recovery/name).chmod(0o600)
            if (recovery/name).read_bytes() != path.read_bytes():
                raise ValueError('Host configuration recovery copy failed verification')
        result['recovery'] = str(recovery)
        result['previous_origin_key_mode'] = key.stat().st_mode & 0o777
        # Even though the install changes host tooling, protect the exact site's
        # runtime before the ensuing enrollment work and preserve its receipt.
        sys.path.insert(0, str(REPO / 'tools/cloudpanel'))
        from cert_sync import backup
        result['backup'] = backup(site)
        save(recovery/'receipt.json', result)
        try:
            git(target, 'fetch', str(REPO), new_ref)
            git(target, 'checkout', '--detach', new_ref)
            if git(target, 'rev-parse', 'HEAD') != new_ref:
                raise ValueError('Installed controller revision differs')
            config.update(tooling_ref=new_ref,
                certificate_command=['python3',str(target/'tools/cloudpanel/cert_sync.py')],
                onboarding_state_root='/home/mrndev-stack-manager/stack/runtime/onboarding',
                notifications={'email':'wordpress_admin@mrnwebdesigns.com',
                               'slack_webhook_file':'/home/mrndev-stack-manager/stack/secrets/slack-webhook-url.txt'})
            save(config_path, config)
            key.chmod(0o600)
            subprocess.run(['nginx', '-t'], capture_output=True, check=True, timeout=30)
            result['origin_key_mode'] = '0600'
            # Preserve the existing five-minute systemd timer. Its default call
            # verifies only; target imports are explicit through onboarding.
            temporary = cert_script.with_suffix('.new')
            temporary.write_text('#!/bin/sh\nexec python3 /opt/mrn-site-deploy/MRN/tools/cloudpanel/cert_sync.py --maintenance\n')
            temporary.chmod(0o750); os.replace(temporary, cert_script)
            result.update(status='installed', certificate_script_sha256=hashlib.sha256(cert_script.read_bytes()).hexdigest())
            save(recovery/'receipt.json', result)
        except Exception:
            git(target, 'checkout', '--detach', args.expected_tooling_ref)
            shutil.copyfile(recovery/'dev-enrollment.json', config_path); config_path.chmod(0o600)
            shutil.copyfile(recovery/'mrn-cert-sync', cert_script); cert_script.chmod(0o750)
            key.chmod(result['previous_origin_key_mode'])
            result['status']='rolled-back'; save(recovery/'receipt.json',result)
            raise
        print(json.dumps(result))
    finally:
        for lock in reversed(locks):lock.close()


if __name__ == '__main__':
    main()

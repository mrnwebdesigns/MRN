#!/usr/bin/env python3
"""Targeted CloudPanel Origin CA onboarding; maintenance never rewrites matches."""
import argparse
import fcntl
import hashlib
import json
import os
from pathlib import Path
import pwd
import re
import shutil
import socket
import sqlite3
import ssl
import subprocess
import sys
import time

REPO = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO / 'stack/scripts'))
from bootstrap_readiness import save

STATE = Path('/var/lib/mrn-cert-sync')
CERTS = Path('/etc/nginx/ssl-certificates')
VHOSTS = Path('/etc/nginx/sites-enabled')
DATABASE = '/home/clp/htdocs/app/data/db.sq3'
ZONES = ('mrndev.io', 'mrnwebdesigns.com')


def run(argv, **kwargs):
    result = subprocess.run(argv, capture_output=True, timeout=kwargs.pop('timeout', 60), **kwargs)
    if result.returncode:
        raise ValueError(Path(argv[0]).name + ' failed with exit ' + str(result.returncode))
    return result.stdout


def zone_for(domain):
    for zone in ZONES:
        if domain == zone or re.fullmatch(r'[a-z0-9][a-z0-9-]*\.' + re.escape(zone), domain):
            return zone
    raise ValueError('Domain is outside approved first-level wildcard coverage')


def sites():
    with sqlite3.connect('file:' + DATABASE + '?mode=ro', uri=True) as db:
        return [{'domain': d, 'user': u, 'root': '/home/' + u + '/htdocs/' + r}
                for d, u, r in db.execute('select domain_name, user, root_directory from site')]


def identity(site):
    domain, owner, root = site['domain'], site['user'], Path(site['root'])
    zone_for(domain)
    if not re.fullmatch(r'[a-z_][a-z0-9_-]*', owner) or root != Path('/home') / owner / 'htdocs' / domain:
        raise ValueError('CloudPanel owner/root mapping differs')
    user = pwd.getpwnam(owner)
    if root.resolve() != root or user.pw_uid == 0 or root.stat().st_uid != user.pw_uid:
        raise ValueError('CloudPanel site root ownership differs')
    matches = []
    for path in VHOSTS.iterdir():
        if path.is_file():
            text = path.read_text()
            names = [v for row in re.findall(r'\bserver_name\s+([^;]+);', text) for v in row.split()]
            if domain in names:
                certs = set(re.findall(r'^\s*ssl_certificate\s+([^;]+);', text, re.M))
                keys = set(re.findall(r'^\s*ssl_certificate_key\s+([^;]+);', text, re.M))
                if len(certs) != 1 or len(keys) != 1:
                    raise ValueError('Ambiguous active certificate mapping')
                matches.append((path, Path(certs.pop().strip()), Path(keys.pop().strip())))
    if len(matches) != 1:
        raise ValueError('Expected one exact CloudPanel vhost')
    return matches[0]


def fingerprint(path):
    return hashlib.sha256(run(['openssl', 'x509', '-in', str(path), '-outform', 'DER'])).hexdigest()


def approved(domain):
    zone = zone_for(domain)
    cert, key = CERTS / (zone + '.crt'), CERTS / (zone + '.key')
    for path in (cert, key):
        if path.is_symlink() or not path.is_file() or path.stat().st_uid != 0 or path.stat().st_mode & 0o022:
            raise ValueError('Approved certificate material must be root-owned')
    run(['openssl', 'x509', '-in', str(cert), '-noout', '-checkend', '86400'])
    result = run(['openssl', 'x509', '-in', str(cert), '-noout', '-checkhost', domain], text=True)
    if 'does match certificate' not in result:
        raise ValueError('Approved certificate does not cover this hostname')
    issuer = run(['openssl', 'x509', '-in', str(cert), '-noout', '-issuer'], text=True)
    if 'Cloudflare' not in issuer:
        raise ValueError('Expected approved Cloudflare Origin CA material')
    cert_public = run(['openssl', 'x509', '-in', str(cert), '-pubkey', '-noout'])
    key_public = run(['openssl', 'pkey', '-in', str(key), '-pubout'])
    if cert_public != key_public:
        raise ValueError('Origin certificate and key differ')
    return cert, key, fingerprint(cert)


def served(domain):
    # Origin CA is trusted through the approved certificate fingerprint, not
    # the workstation public CA store. Public HTTPS still uses normal TLS.
    context = ssl.SSLContext(ssl.PROTOCOL_TLS_CLIENT)
    context.check_hostname = False; context.verify_mode = ssl.CERT_NONE
    with socket.create_connection(('127.0.0.1', 443), timeout=10) as raw:
        with context.wrap_socket(raw, server_hostname=domain) as connection:
            return hashlib.sha256(connection.getpeercert(binary_form=True)).hexdigest()


def backup(site):
    # Reuse the canonical verified Updraft transaction; never ship credentials.
    code = 'import sys,json;sys.path.insert(0,sys.argv[1]);from host_controller import backup;print(json.dumps(backup(sys.argv[2],"origin-certificate")))'
    value = json.loads(run(['sudo', '-u', site['user'], '--', 'env', '-i', 'PATH=/usr/local/bin:/usr/bin:/bin',
                           'HOME=/home/' + site['user'], 'python3', '-c', code,
                           str(REPO / 'tools/site-deploy'), site['root']], timeout=600, text=True))
    if value.get('valid') is not True:
        raise ValueError('Required remote database backup is not verified')
    return value


def ensure(site, check_only=False, state_root=STATE):
    state_root.mkdir(parents=True, mode=0o700, exist_ok=True)
    path = state_root / (site['domain'] + '.json')
    record = {'site': site, 'updated_at': int(time.time()), 'status': 'checking', 'changed': False}
    try:
        vhost, active_cert, active_key = identity(site)
        cert, key, desired = approved(site['domain'])
        record['certificate_sha256'] = desired
        if fingerprint(active_cert) == desired and served(site['domain']) == desired:
            record['status'] = 'verified'; save(path, record); return record
        if check_only:
            raise ValueError('Trusted origin certificate is not active for this exact site')
        record['backup'] = backup(site)
        recovery = state_root / ('recovery-' + site['domain'] + '-' + str(time.time_ns()))
        recovery.mkdir(mode=0o700)
        for original, name in [(vhost, 'vhost.conf'), (active_cert, 'certificate.crt'), (active_key, 'private.key')]:
            shutil.copyfile(original, recovery / name); (recovery / name).chmod(0o600)
        record['recovery'] = str(recovery); save(path, record)
        run(['clpctl', 'site:install:certificate', '--domainName=' + site['domain'],
             '--privateKey=' + str(key), '--certificate=' + str(cert)], timeout=120)
        record['changed'] = True
        run(['nginx', '-t']); run(['systemctl', 'reload', 'nginx'])
        if fingerprint(identity(site)[1]) != desired or served(site['domain']) != desired:
            raise ValueError('Imported certificate failed exact-site installed/served verification')
        record['status'] = 'verified'
    except (ValueError, OSError, subprocess.TimeoutExpired) as error:
        record.update(status='failed', error=str(error) if isinstance(error, ValueError) else type(error).__name__)
    save(path, record)
    return record


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    modes = parser.add_mutually_exclusive_group(required=True)
    modes.add_argument('--site-path')
    modes.add_argument('--maintenance', action='store_true')
    parser.add_argument('--check', action='store_true')
    args = parser.parse_args()
    if os.geteuid() != 0:
        raise SystemExit('Certificate tooling requires root')
    STATE.mkdir(mode=0o700, exist_ok=True)
    if STATE.is_symlink() or STATE.stat().st_uid != 0 or STATE.stat().st_mode & 0o022:
        raise SystemExit('Invalid certificate state ownership')
    with (STATE / 'lock').open('a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX)
        selected = []
        for site in sites():
            if args.site_path:
                if site['root'] == args.site_path:
                    selected.append(site)
            else:
                try: zone_for(site['domain'])
                except ValueError: continue
                selected.append(site)
        if args.site_path and len(selected) != 1:
            raise SystemExit('Exact CloudPanel site is missing or ambiguous')
        # No global cooldown: unchanged certificates are read-only checks;
        # newly discovered or failed sites are examined on every timer pass.
        # A timer may inspect every site, but only exact-target onboarding may
        # import a certificate. It must never force an all-sites rewrite.
        results = [ensure(site, args.check or args.maintenance) for site in selected]
        print(json.dumps({'status': 'pass' if all(r['status'] == 'verified' for r in results) else 'failed', 'sites': results}))
        return 0 if all(r['status'] == 'verified' for r in results) else 1


if __name__ == '__main__':
    sys.exit(main())

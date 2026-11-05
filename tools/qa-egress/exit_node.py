"""Checkpoint and configure only the existing MRN QA host's exit-node settings."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import socket
import subprocess

HOST = 'MRN-QA-Engine'
TAIL_IP = '100.97.81.44'
PUBLIC4 = '159.65.33.118'
PUBLIC6 = '2604:a880:800:14:0:2:f6f6:b000'
CONFIG = Path('/etc/sysctl.d/90-mrn-qa-egress.conf')
STATE = Path('/var/lib/mrn-qa-egress/checkpoint.json')
CONTENT = '# MRN QA fixed egress; managed by tools/qa-egress/exit_node.py\nnet.ipv4.ip_forward = 1\nnet.ipv6.conf.all.forwarding = 1\n'
KEYS = ('net.ipv4.ip_forward', 'net.ipv6.conf.all.forwarding')
CONTAINERS = ('qaengine-api', 'mrn-qa-engine-google-mcp-1', 'qaengine-edge-guard', 'qaengine-caddy')
ROUTES = {'0.0.0.0/0', '::/0'}


def call(args):
    return subprocess.check_output(args, text=True, timeout=30).strip()


def private_save(path, value):
    path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'w') as stream:
        json.dump(value, stream, indent=2)
        stream.write('\n')


def network_state():
    if socket.gethostname() != HOST:
        raise ValueError('Wrong QA host')
    status = json.loads(call(['tailscale', 'status', '--json']))
    if (status.get('BackendState') != 'Running' or TAIL_IP not in status.get('TailscaleIPs', []) or
            not {'tag:qa', 'tag:infrastructure'}.issubset(status.get('Self', {}).get('Tags') or [])):
        raise ValueError('Wrong or disconnected QA node')
    prefs = json.loads(call(['tailscale', 'debug', 'prefs']))
    # Never persist identity/private-key material from the full prefs response.
    prefs.pop('Config', None)
    if prefs.get('ExitNodeID') or prefs.get('ExitNodeIP'):
        raise ValueError('QA server must retain its direct Internet route')
    forward = {key: call(['sysctl', '-n', key]) for key in KEYS}
    if any(value not in ('0', '1') for value in forward.values()):
        raise ValueError('Unexpected forwarding state')
    return {'host': HOST, 'tailscale_ip': TAIL_IP, 'prefs': prefs, 'forwarding': forward}


def observed():
    value = network_state()
    for family, endpoint, expected in [('4', 'api4.ipify.org', PUBLIC4), ('6', 'api6.ipify.org', PUBLIC6)]:
        if call(['curl', '--ipv' + family, '--fail', '--silent', '--max-time', '15', 'https://' + endpoint]) != expected:
            raise ValueError('QA public address changed')
    containers = json.loads(call(['docker', 'inspect', *CONTAINERS]))
    if any(not item['State']['Running'] for item in containers):
        raise ValueError('An existing QA service is not running')
    services = {item['Name']: {'id': item['Id'], 'image': item['Image'],
                              'started': item['State']['StartedAt'],
                              'config_hash': hashlib.sha256(json.dumps(item['Config'], sort_keys=True).encode()).hexdigest()}
                for item in containers}
    call(['curl', '--fail', '--silent', '--max-time', '10', 'https://qaengine.mrndev.io/healthz'])
    return dict(value, public_ipv4=PUBLIC4, public_ipv6=PUBLIC6, services=services)


def verify(before, after, enabled):
    if before['services'] != after['services']:
        raise ValueError('Existing QA service identity or configuration changed')
    expected_prefs = dict(before['prefs'])
    expected_prefs['AdvertiseRoutes'] = ['0.0.0.0/0', '::/0'] if enabled else before['prefs'].get('AdvertiseRoutes')
    if after['prefs'] != expected_prefs:
        raise ValueError('Unexpected Tailscale preference change')
    expected_forwarding = {key: '1' for key in KEYS} if enabled else before['forwarding']
    if after['forwarding'] != expected_forwarding:
        raise ValueError('Forwarding state did not match the scoped change')


def rollback(before):
    # A concurrent administrator's routes or config must never be removed.
    # Recovery must remain possible when the failed change also broke HTTP health.
    current = network_state()
    if set(current['prefs'].get('AdvertiseRoutes') or []) not in (set(), ROUTES):
        raise ValueError('Concurrent route changes require review')
    if CONFIG.is_symlink() or (CONFIG.exists() and CONFIG.read_text() != CONTENT):
        raise ValueError('Owned sysctl file differs; refusing removal')
    for key in KEYS:
        if current['forwarding'][key] not in ('1', before['forwarding'][key]):
            raise ValueError('Concurrent forwarding changes require review')
    call(['tailscale', 'set', '--advertise-exit-node=false'])
    for key, value in before['forwarding'].items():
        call(['sysctl', '-w', key + '=' + value])
    if CONFIG.exists():
        CONFIG.unlink()
    verify(before, observed(), False)


def main(mode):
    if os.geteuid() != 0:
        raise ValueError('Run through the approved administrator with sudo')
    if mode == 'preflight':
        value = observed()
        if CONFIG.exists() or CONFIG.is_symlink() or STATE.exists():
            raise ValueError('An existing QA egress setup requires readback instead of replacement')
        if value['prefs'].get('AdvertiseRoutes'):
            raise ValueError('Do not replace existing advertised routes')
        print(json.dumps(value, indent=2))
        return
    if mode in ('verify', 'rollback'):
        before = json.loads(STATE.read_text())
        if before['host'] != HOST or before['tailscale_ip'] != TAIL_IP:
            raise ValueError('Wrong rollback checkpoint')
        if mode == 'rollback':
            rollback(before)
        else:
            verify(before, observed(), True)
        print(json.dumps({'mode': mode, 'verified': True}))
        return
    before = observed()
    if CONFIG.exists() or CONFIG.is_symlink() or before['prefs'].get('AdvertiseRoutes'):
        raise ValueError('Existing exit-node configuration must not be replaced')
    private_save(STATE, before)
    try:
        with CONFIG.open('x') as stream:
            stream.write(CONTENT)
        for key in KEYS:
            call(['sysctl', '-w', key + '=1'])
        call(['tailscale', 'set', '--advertise-exit-node'])
        verify(before, observed(), True)
    except Exception:
        rollback(before)
        raise
    print(json.dumps({'mode': mode, 'verified': True, 'route_approval_required': True}))


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('mode', choices=('preflight', 'apply', 'verify', 'rollback'))
    main(parser.parse_args().mode)

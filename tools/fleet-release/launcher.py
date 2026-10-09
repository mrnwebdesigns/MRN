#!/usr/bin/env python3
"""Owner-local service bootstrap: run the latest clean accepted controller."""
import json
import os
from pathlib import Path
import subprocess
import sys
import re

EVIDENCE = None

ENVIRONMENT_KEYS = {'HOME', 'USER', 'LOGNAME', 'PATH', 'LANG', 'LC_ALL', 'TMPDIR',
                    'SSH_AUTH_SOCK', 'GIT_TERMINAL_PROMPT'}


def service_environment(values):
    """Do not pass unrelated app credentials or site QA overrides to source jobs."""
    return {key: value for key, value in values.items() if key in ENVIRONMENT_KEYS}


def command(argv, cwd=None):
    result = subprocess.run(argv, cwd=cwd, text=True, capture_output=True, check=False,
                            env={**os.environ, 'GIT_TERMINAL_PROMPT': '0'}, timeout=600)
    if result.returncode:
        if EVIDENCE:
            diagnostic = re.sub(r'(?:gh[pousr]_[A-Za-z0-9]+|github_pat_[A-Za-z0-9_]+)', '[redacted]', result.stderr)
            diagnostic = re.sub(r'(https?://)[^/\s]+@', r'\1[redacted]@', diagnostic)
            EVIDENCE.write_text(diagnostic)
            EVIDENCE.chmod(0o600)
        raise RuntimeError('Source service bootstrap failed: ' + Path(argv[0]).name)
    return result.stdout.strip()


def main(config_file):
    global EVIDENCE
    os.umask(0o077)
    environment = service_environment(os.environ)
    os.environ.clear()
    os.environ.update(environment)
    path = Path(config_file)
    if path.is_symlink() or path.stat().st_uid != os.geteuid() or path.stat().st_mode & 0o022:
        raise RuntimeError('Source service configuration is not owner-controlled')
    config = json.loads(path.read_text())
    EVIDENCE = Path(config['state_root']) / 'bootstrap-error.log'
    checkout = Path(config['state_root']) / 'controller/MRN'
    base = ['git', '-c', 'credential.helper=', '-c', 'credential.helper=!gh auth git-credential']
    if not checkout.exists():
        checkout.parent.mkdir(parents=True, mode=0o700, exist_ok=True)
        command(base + ['clone', '--no-tags', 'https://github.com/mrnwebdesigns/MRN.git', str(checkout)])
    else:
        if command(['git', 'status', '--porcelain'], checkout):
            raise RuntimeError('Controller checkout is dirty; preserved for inspection')
        if command(['git', 'remote', 'get-url', 'origin'], checkout) != 'https://github.com/mrnwebdesigns/MRN.git':
            raise RuntimeError('Controller source origin differs')
        command(base + ['fetch', 'origin', 'main'], checkout)
        command(['git', 'checkout', '--detach', 'origin/main'], checkout)
    script = checkout / 'tools/fleet-release/coordinator.py'
    if not script.is_file():
        raise RuntimeError('Accepted source controller has not merged yet')
    # No shell, credential extraction, site command or mutable task checkout.
    os.execv(sys.executable, [sys.executable, str(script), '--config', str(path)])


if __name__ == '__main__':
    try:
        main(sys.argv[1])
    except (OSError, ValueError, RuntimeError, subprocess.TimeoutExpired) as error:
        print(json.dumps({'status': 'blocked', 'error': str(error), 'site_writes': False}))
        raise SystemExit(1)

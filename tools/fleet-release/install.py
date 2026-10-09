#!/usr/bin/env python3
"""Install the owner-local source service; never configure or update a site."""
import argparse
import json
import os
from pathlib import Path
import plistlib
import shutil
import subprocess
import sys

LABEL = 'com.mrn.fleet-source'


def definition(python, launcher, config, state, executable_path):
    return {'Label': LABEL, 'ProgramArguments': [str(python), str(launcher), str(config)],
            'RunAtLoad': True, 'StartInterval': 300, 'ProcessType': 'Background',
            'EnvironmentVariables': {'PATH': executable_path, 'GIT_TERMINAL_PROMPT': '0'},
            'StandardOutPath': str(state / 'service.log'),
            'StandardErrorPath': str(state / 'service-errors.log')}


def install(config, *, start=False):
    if sys.platform != 'darwin': raise RuntimeError('This owner-local service requires macOS launchd')
    config = Path(config).resolve()
    info = config.stat()
    if info.st_uid != os.geteuid() or info.st_mode & 0o022:
        raise RuntimeError('Service configuration must be owner-controlled')
    value = json.loads(config.read_text())
    state = Path(value['state_root'])
    state.mkdir(mode=0o700, parents=True, exist_ok=True)
    if state.is_symlink() or state.stat().st_uid != os.geteuid() or state.stat().st_mode & 0o077:
        raise RuntimeError('Service state must be private and owner-controlled')
    launcher = state / 'launcher.py'
    data = Path(__file__).with_name('launcher.py').read_bytes()
    if launcher.exists() and launcher.read_bytes() != data:
        raise RuntimeError('Existing service launcher differs; retain/review it before replacing')
    launcher.write_bytes(data); launcher.chmod(0o700)
    agents = Path.home() / 'Library/LaunchAgents'; agents.mkdir(exist_ok=True)
    target = agents / (LABEL + '.plist')
    spec = definition(sys.executable, launcher, config, state, os.environ['PATH'])
    encoded = plistlib.dumps(spec)
    if target.is_symlink(): raise RuntimeError('Service definition cannot be a symlink')
    if target.exists() and target.read_bytes() != encoded:
        raise RuntimeError('Existing service definition differs; preserve before changing')
    target.write_bytes(encoded); target.chmod(0o600)
    if start:
        check = subprocess.run(['launchctl', 'print', 'gui/' + str(os.getuid()) + '/' + LABEL],
                               capture_output=True, check=False)
        if check.returncode:
            subprocess.run(['launchctl', 'bootstrap', 'gui/' + str(os.getuid()), str(target)], check=True)
    return {'status': 'started' if start else 'installed', 'definition': str(target),
            'config': str(config), 'site_writes': False}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config', type=Path, required=True)
    parser.add_argument('--start', action='store_true')
    arguments = parser.parse_args()
    print(json.dumps(install(arguments.config, start=arguments.start), sort_keys=True))

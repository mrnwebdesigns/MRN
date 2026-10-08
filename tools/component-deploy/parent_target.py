"""Explicit parent transport routes; Live is a manual qualification candidate."""
from pathlib import PurePosixPath
import re
from urllib.parse import urlsplit


def validate_route(plan):
    url = urlsplit(plan['url'])
    if (url.scheme != 'https' or not url.hostname or url.netloc != url.hostname
            or url.path or url.query or url.fragment
            or not re.fullmatch(r'[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?', url.hostname)):
        raise ValueError('One exact canonical HTTPS site URL is required')
    provider = plan.get('host_provider', 'cloudpanel')
    environment = plan.get('environment')
    if environment == 'dev' and provider == 'cloudpanel' and url.hostname.endswith('.mrndev.io'):
        return provider
    if environment != 'live' or provider != 'nexcess':
        raise ValueError('Only CloudPanel Dev or explicit Nexcess Live qualification is supported')
    if (plan.get('qualify_live') is not True or plan.get('exercise_rollback') is not True
            or plan.get('backup_provider') != 'updraft'):
        raise ValueError('Live requires explicit qualification, rollback exercise and Updraft backup')
    if url.hostname.endswith(('.mrndev.io', '.localhost', '.local', '.test', '.invalid')) or '.' not in url.hostname or re.fullmatch(r'[0-9.]+', url.hostname):
        raise ValueError('Live qualification requires a production hostname')
    login = plan.get('ssh_login', '')
    if not re.fullmatch(r'[a-zA-Z0-9_-]+@[a-zA-Z0-9.-]+\.nxcli\.io', login):
        raise ValueError('Nexcess requires an explicit site-owner SSH endpoint')
    user = login.split('@')[0]
    expected = '/home/' + user + '/private-backups/mrn-parent-deploy/live/' + re.sub(r'[^a-z0-9]', '-', url.hostname)
    if plan.get('state') != expected:
        raise ValueError('Live parent storage must be site-isolated and private')
    for name in ('root', 'state', 'child_state'):
        if name == 'child_state' and plan.get(name) is None:
            continue
        path = plan.get(name, '')
        if not isinstance(path, str) or not re.fullmatch(r'/[a-zA-Z0-9_./-]+', path) or '..' in PurePosixPath(path).parts:
            raise ValueError('Explicit physical Live paths are required')
    return provider

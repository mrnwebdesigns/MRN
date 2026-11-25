"""Explicit mappings between SSH-visible paths and WordPress physical paths.

Provider aliases are resolved on the destination, never by trusting a caller's
replacement root. These helpers do not enable a deployment or perform writes.
"""
from pathlib import Path
import re
from urllib.parse import urlsplit


def activation_environment(environment, provider):
    if provider == 'cloudpanel' and environment != 'dev':
        raise ValueError('CloudPanel adapter is restricted to MRN Dev')
    if environment not in ('dev', 'live') and not (environment == 'staging' and provider in ('wpengine', 'siteground', 'kinsta')):
        raise ValueError('No qualified activation adapter for this environment/provider')


def cloudpanel_dev_url(url):
    if not re.fullmatch(r'https://[a-z0-9][a-z0-9-]*\.mrndev\.io', url):
        raise ValueError('CloudPanel requires an exact first-level HTTPS MRN Dev URL')


def qualify_provider(plan, wordpress):
    provider = plan.get('host_provider', 'cloudpanel')
    environment = plan.get('environment')
    host = urlsplit(plan['url']).hostname or ''
    root = Path(plan['root']).resolve()
    content = Path(wordpress['content']).resolve()
    state = Path(plan['state_dir']).resolve()
    expected_backup = 'kinsta' if provider == 'kinsta' else 'updraft'
    activation_environment(environment, provider)
    if plan.get('backup_provider') != expected_backup:
        raise ValueError('No qualified activation adapter for this environment/backup provider')
    if provider == 'cloudpanel':
        cloudpanel_dev_url(plan['url'])
        if (wordpress.get('siteurl') != plan['url']
                or wordpress.get('environment_type') != 'development'):
            raise ValueError('CloudPanel adapter is restricted to MRN Dev')
    elif provider == 'wpengine':
        account = plan.get('ssh_user', '')
        if (plan.get('ssh_host') != account + '.ssh.wpengine.net'
                or str(root) != '/nas/content/live/' + account
                or str(state) != str(root) + '/_wpeprivate/mrn-site-deploy/' + environment):
            raise ValueError('WP Engine protected storage identity does not match')
    elif provider == 'siteground':
        if (str(root) != '/home/customer/www/' + host + '/public_html'
                or not str(state).startswith('/home/customer/private-backups/mrn-site-deploy/')):
            raise ValueError('SiteGround site/private storage identity does not match')
    elif provider == 'nexcess':
        if not plan.get('ssh_host', '').endswith('.nxcli.io'):
            raise ValueError('Nexcess adapter requires its verified SSH endpoint')
    elif provider == 'kinsta':
        # The native API additionally verifies exact site/environment/root/SSH
        # identity. Only its private sibling of the public web root is allowed.
        allowed_states = {root.parent / 'deployments/mrn-site-deploy' / environment}
        if environment != 'staging':
            allowed_states.add(root.parent / 'private-backups/mrn-site-deploy' / environment)
        if (root.parent.parent != Path('/www') or root.name != 'public' or state not in allowed_states):
            raise ValueError('Kinsta public/private storage identity does not match')
    else:
        raise ValueError('Unknown host activation adapter')
    if str(root) != wordpress['root'] or content != root / 'wp-content':
        raise ValueError('WordPress physical root/content differs from the selected host')
    return {'provider': provider, 'root': str(root), 'state_dir': str(state),
            'protected_state_root': str(root / '_wpeprivate') if provider == 'wpengine' else None}

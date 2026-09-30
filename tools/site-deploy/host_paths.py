"""Explicit mappings between SSH-visible paths and WordPress physical paths.

Provider aliases are resolved on the destination, never by trusting a caller's
replacement root. These helpers do not enable a deployment or perform writes.
"""
from pathlib import Path
from urllib.parse import urlsplit


def qualify_provider(plan, wordpress):
    provider = plan.get('host_provider', 'cloudpanel')
    environment = plan.get('environment')
    host = urlsplit(plan['url']).hostname or ''
    root = Path(plan['root']).resolve()
    content = Path(wordpress['content']).resolve()
    state = Path(plan['state_dir']).resolve()
    if environment not in ('dev', 'live') or plan.get('backup_provider') != 'updraft':
        raise ValueError('No qualified activation adapter for this environment/backup provider')
    if provider == 'cloudpanel':
        if environment != 'dev' or not host.endswith('.mrndev.io'):
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
    else:
        raise ValueError('Unknown host activation adapter')
    if str(root) != wordpress['root'] or content != root / 'wp-content':
        raise ValueError('WordPress physical root/content differs from the selected host')
    return {'provider': provider, 'root': str(root), 'state_dir': str(state),
            'protected_state_root': str(root / '_wpeprivate') if provider == 'wpengine' else None}

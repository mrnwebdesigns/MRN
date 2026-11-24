"""WP Engine delivery-cache adapter. Credentials stay in memory, never receipts.

Official API uses Basic authentication with an API username/password, not the
SSH identity. Page and CDN purges cover delivery caches without flushing objects.
A 202 is acceptance only; QA Engine must prove the selected release afterwards.
"""
import base64
from email.utils import parsedate_to_datetime
import json
import math
import re
import time
import urllib.error
import urllib.request
from urllib.parse import urlsplit


class ProviderThrottle(RuntimeError):
    def __init__(self, retry_at):
        self.retry_not_before = retry_at
        super().__init__('WP Engine API rate limited; wait until ' + str(math.ceil(retry_at)))


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def retry_seconds(value, now):
    if not isinstance(value, str) or len(value) > 128:
        return None
    if re.fullmatch(r'\d{1,10}', value.strip()):
        return int(value)
    try:
        stamp = parsedate_to_datetime(value)
        return max(0, math.ceil(stamp.timestamp() - now)) if stamp.tzinfo else None
    except (ValueError, TypeError, OverflowError):
        return None


class WPEngine:
    def __init__(self, username, password, install_id, *, sleep=time.sleep, clock=time.time, opener=None):
        if (not username or not password or ':' in username or
                any(ord(char) < 32 for char in username + password) or
                not re.fullmatch(r'[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}', install_id)):
            raise ValueError('WP Engine requires protected API username/password and install UUID')
        self.username, self.password, self.install_id = username, password, install_id
        self.sleep, self.clock = sleep, clock
        self.opener = opener or urllib.request.build_opener(NoRedirect())
        self.binding = None

    def api(self, suffix='', body=None):
        auth = base64.b64encode((self.username + ':' + self.password).encode()).decode()
        req = urllib.request.Request('https://api.wpengineapi.com/v1/installs/' + self.install_id + suffix,
              data=None if body is None else json.dumps(body).encode(),
              headers={'Authorization': 'Basic ' + auth, 'Content-Type': 'application/json'})
        deadline = self.clock() + 30
        for attempt in range(3):
            try:
                with self.opener.open(req, timeout=30) as response:
                    if response.status != (200 if body is None else 202):
                        raise RuntimeError('Unexpected WP Engine API response status')
                    if body is not None:
                        return {'http_status': 202, 'status': 'accepted'}
                    return json.loads(response.read(1024 * 1024))
            except urllib.error.HTTPError as error:
                status, after = error.code, error.headers.get('Retry-After')
                error.close()
                if status != 429:
                    raise RuntimeError('WP Engine API failed (HTTP ' + str(status) + ')') from None
                seconds = retry_seconds(after, self.clock())
                seconds = seconds if seconds is not None else 5 * 2 ** attempt
                retry_at = self.clock() + seconds
                if attempt == 2 or retry_at > deadline:
                    raise ProviderThrottle(retry_at) from None
                self.sleep(seconds)
            except (OSError, ValueError):
                # An ambiguous POST outcome must never be retried blindly.
                raise RuntimeError('WP Engine API outcome unknown; inspect provider before retry') from None

    def identity(self, config):
        self.binding = None
        install = self.api()
        url = urlsplit(config['url'])
        environment = config['environment']
        if (environment not in ('dev', 'staging', 'live') or url.scheme != 'https' or url.username or url.password or url.port or url.path not in ('', '/')
                or url.query or url.fragment or install.get('id') != self.install_id
                or install.get('status') != 'active' or install.get('name') != config['user']
                or config['host'] != install['name'] + '.ssh.wpengine.net'
                or install.get('primary_domain') != url.hostname
                or install.get('is_multisite') is not False
                or ((environment == 'live') != (install.get('environment') == 'production'))
                or (environment == 'staging' and install.get('environment') != 'staging')
                or install.get('environment') not in ('production', 'staging', 'development')):
            raise ValueError('WP Engine API install, domain, environment or SSH identity differs from the target')
        self.binding = {'install_id': self.install_id, 'install': install['name'],
                        'origin': config['url'].rstrip('/'), 'environment': environment}
        return dict(self.binding, status='identity-verified')

    def refresh_delivery(self, origin):
        if not self.binding or origin.rstrip('/') != self.binding['origin']:
            raise ValueError('Fresh WP Engine target identity is required before cache writes')
        operations = []
        for kind in ('page', 'cdn'):
            result = self.api('/purge_cache', {'type': kind})
            operations.append(dict(result, type=kind))
        return {**self.binding, 'status': 'delivery-purge-accepted', 'scope': 'install-page-and-cdn',
                'operations': operations, 'public_proof_required': True}

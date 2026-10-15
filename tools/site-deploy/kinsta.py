"""Native Kinsta identity and backup adapter; never prints credential responses."""
import json
import re
import time
import urllib.error
import urllib.request
from urllib.parse import urlsplit

from cache_policy import canonical_pages


class Kinsta:
    def __init__(self, token, site_id, environment_id):
        if not token or not all(re.fullmatch(r'[0-9a-f-]{36}', value) for value in (site_id, environment_id)):
            raise ValueError('Missing native Kinsta credentials or identity')
        self.token, self.site_id, self.environment_id = token, site_id, environment_id

    def api(self, path, body=None):
        request = urllib.request.Request('https://api.kinsta.com/v2' + path,
                                         data=None if body is None else json.dumps(body).encode(),
                                         headers={'Authorization': 'Bearer ' + self.token, 'Content-Type': 'application/json'})
        try:
            with urllib.request.urlopen(request, timeout=40) as response:
                return json.load(response)
        except urllib.error.HTTPError as error:
            raise RuntimeError('Kinsta API request failed (HTTP ' + str(error.code) + ')') from None

    def refresh_edge_html(self, site_url, pages):
        """Purge only the reviewed canonical HTML URLs, then verify completion.

        The MU plugin's origin purge can finish before Edge Cache invalidation.
        Never broaden this request to descendants, the whole environment, CDN
        assets, or an object cache. Public byte verification remains mandatory.
        """
        pages = canonical_pages(site_url, pages)
        for page in pages:
            path = urlsplit(page).path
            if (re.search(r'[%*\\\s]', path)
                    or re.search(r'^/(?:wp-admin|wp-json|wp-content|wp-includes)(?:/|$)', path)
                    or re.search(r'\.(?:css|m?js|json|xml|jpe?g|png|webp|svg|pdf|woff2?)/?$', path, re.I)):
                raise ValueError('Kinsta edge purge requires exact public HTML URLs')
        operations = []
        for page in pages:
            started = self.api('/sites/edge-caching/clear', {
                'environment_id': self.environment_id, 'clear_subdirectories': False, 'url': page})
            operation = started.get('operation_id')
            if (started.get('status') != 202 or not isinstance(operation, str)
                    or not operation.startswith('edgeCache:clear-' + self.environment_id)
                    or not re.fullmatch(r'[A-Za-z0-9:_-]{1,200}', operation)):
                raise RuntimeError('Kinsta edge purge did not return a pending operation')
            for _ in range(60):
                status = self.api('/operations/' + operation).get('status')
                if status == 200:
                    break
                if status != 202:
                    raise RuntimeError('Kinsta edge purge failed; inspect operation ' + operation)
                time.sleep(2)
            else:
                raise RuntimeError('Kinsta edge purge is still pending; inspect operation ' + operation)
            operations.append({'url': page, 'operation_id': operation, 'status': 'complete'})
        return {'provider': 'kinsta', 'environment_id': self.environment_id,
                'status': 'exact-html-edge-purge-verified', 'clear_subdirectories': False,
                'operations': operations}

    def identity(self, config):
        environments = self.api('/sites/' + self.site_id + '/environments')['site']['environments']
        matches = [e for e in environments if e['id'] == self.environment_id]
        if len(matches) != 1:
            raise ValueError('Kinsta environment does not belong to the configured site')
        environment = matches[0]
        if config.get('environment') == 'staging' and (
                environment.get('name') != 'staging' or environment.get('primaryDomain', {}).get('type') != 'staging'):
            raise ValueError('Kinsta staging selection must resolve to a native staging environment')
        if config.get('environment') == 'live' and (
                environment.get('name') != 'live' or environment.get('primaryDomain', {}).get('type') != 'live'):
            raise ValueError('Kinsta Live selection must resolve to a native Live environment')
        if ('https://' + environment['primaryDomain']['name'] != config['url']
                or environment['web_root'] != config['root'] or environment.get('is_blocked')):
            raise ValueError('Kinsta environment URL/root is not the configured target')
        connection = self.api('/sites/' + self.site_id + '/environments/' + self.environment_id + '/ssh/config')
        if any(str(connection[k]) != config[v] for k, v in [('host', 'host'), ('port', 'port'), ('user', 'user')]):
            raise ValueError('Kinsta SSH identity differs from the configured target')
        # List backups read-only, proving the native backup route is accessible.
        self.api('/sites/environments/' + self.environment_id + '/backups')
        return environment

    def connect(self, config):
        self.identity(config)
        return self.api('/sites/environments/' + self.environment_id + '/ssh/password')['environment']['sftp_password']

    def backup(self, label):
        operation = self.api('/sites/environments/' + self.environment_id + '/manual-backups', {'tag': label})
        operation_id = operation.get('operation_id')
        if not operation_id:
            raise RuntimeError('Kinsta did not return a backup operation ID')
        for _ in range(120):
            result = self.api('/operations/' + operation_id)
            status = result.get('status')
            if status == 200:
                break
            if status != 202:
                raise RuntimeError('Kinsta backup operation did not complete successfully')
            time.sleep(5)
        else:
            raise RuntimeError('Kinsta backup operation timed out')
        data = self.api('/sites/environments/' + self.environment_id + '/backups')
        backups = data.get('environment', {}).get('backups', [])
        matches = [b for b in backups if b.get('note') == label or b.get('tag') == label]
        if len(matches) != 1 or matches[0].get('type') != 'manual':
            raise RuntimeError('Completed labeled native backup could not be verified')
        return {'valid': True, 'provider': 'kinsta', 'environment_id': self.environment_id,
                'label': label, 'operation_id': operation_id, 'backup_id': matches[0]['id'],
                'created_at': int(time.time()), 'scope': 'deployment-transaction'}

    def verify_transaction_backup(self, receipt, operation):
        # Kinsta has five manual snapshot slots. A code-only deployment is one
        # transaction, backed up immediately before the first remote write.
        # Read back that native snapshot before each subsequent step. A later
        # deployment or standalone rollback must create its own fresh snapshot.
        if (receipt.get('valid') is not True or receipt.get('provider') != 'kinsta'
                or receipt.get('environment_id') != self.environment_id
                or receipt.get('scope') != 'deployment-transaction'
                or not 0 <= time.time() - receipt.get('created_at', 0) < 3600):
            raise ValueError('Native deployment backup is missing, stale or belongs to another environment')
        data = self.api('/sites/environments/' + self.environment_id + '/backups')
        matches = [b for b in data.get('environment', {}).get('backups', [])
                   if b.get('id') == receipt.get('backup_id') and b.get('type') == 'manual'
                   and (b.get('note') == receipt.get('label') or b.get('tag') == receipt.get('label'))]
        if len(matches) != 1:
            raise RuntimeError('Native deployment backup is no longer available')
        return dict(receipt, verified_at=int(time.time()), operation=operation)

"""Native Kinsta identity and backup adapter; never prints credential responses."""
import json
import re
import time
import urllib.error
import urllib.request


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

    def connect(self, config):
        environments = self.api('/sites/' + self.site_id + '/environments')['site']['environments']
        matches = [e for e in environments if e['id'] == self.environment_id]
        if len(matches) != 1:
            raise ValueError('Kinsta environment does not belong to the configured site')
        environment = matches[0]
        if ('https://' + environment['primaryDomain']['name'] != config['url']
                or environment['web_root'] != config['root'] or environment.get('is_blocked')):
            raise ValueError('Kinsta environment URL/root is not the configured target')
        connection = self.api('/sites/' + self.site_id + '/environments/' + self.environment_id + '/ssh/config')
        if any(str(connection[k]) != config[v] for k, v in [('host', 'host'), ('port', 'port'), ('user', 'user')]):
            raise ValueError('Kinsta SSH identity differs from the configured target')
        # List backups read-only, proving the native backup route is accessible.
        self.api('/sites/environments/' + self.environment_id + '/backups')
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
                'label': label, 'operation_id': operation_id, 'backup_id': matches[0]['id']}

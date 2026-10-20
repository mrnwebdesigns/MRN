"""Native Kinsta identity and backup adapter; never prints credential responses."""
import json
import re
import time
import urllib.error
import urllib.request

DOSTER_MANUAL_APPROVAL = 'doster-owner-manual-2026-10-20'
DOSTER_SITE = '37f05135-d359-440e-a6bf-28e95ea5ec40'
DOSTER_DEV = '93162079-439b-45a5-b4b6-07ed7fcd6295'
DOSTER_LIVE = '37c7b8b2-66d6-47d3-8873-bc0377ed0d37'
DOSTER_REPOSITORY = 'mrnwebdesigns/doster-and-brown-child-theme'
DOSTER_TARGET = dict(environment='dev', url='https://stg-lowwd-mrndev.kinsta.cloud',
                     root='/www/lowwd_975/public', host='34.74.12.94', port='61903', user='lowwd')
DOSTER_LIVE_TARGET = dict(environment='live', url='https://www.dosterandbrown.com',
                          root='/www/lowwd_975/public', host='34.74.12.94', port='60098', user='lowwd')
DOSTER_TARGETS = {DOSTER_DEV: DOSTER_TARGET, DOSTER_LIVE: DOSTER_LIVE_TARGET}


def adapter(token, site_id, environment_id, manual_approval='', repository=''):
    """The default remains native/API-backed; exact owner-approved Doster targets."""
    if not manual_approval:
        return Kinsta(token, site_id, environment_id)
    if ((manual_approval, repository, site_id) != (
            DOSTER_MANUAL_APPROVAL, DOSTER_REPOSITORY, DOSTER_SITE)
            or environment_id not in DOSTER_TARGETS):
        raise ValueError('Manual backup exception is restricted to the approved Doster targets')
    return DosterOwnerManualBackup(environment_id)


class DosterOwnerManualBackup:
    """Owner manages backups; this never claims a fresh provider snapshot exists.

    Approved in the Doster site conversation on 2026-10-20. Pinned SSH plus fresh
    WordPress/physical-path identity, artifact, ordering and rollback gates stay on.
    The owner separately authorized the exact Live extension on 2026-10-20.
    No API/password fallback is supported by this exception.
    """
    token, site_id, environment_id = '', DOSTER_SITE, DOSTER_DEV

    def __init__(self, environment_id=DOSTER_DEV):
        if environment_id not in DOSTER_TARGETS:
            raise ValueError('Unapproved Doster environment')
        self.environment_id = environment_id
        self.identity_verified = False

    def identity(self, config):
        self.identity_verified = False
        if any(config.get(key) != value for key, value in DOSTER_TARGETS[self.environment_id].items()):
            raise ValueError('Manual backup exception target differs from the approved Doster environment')
        self.identity_verified = True
        return {'manual_backup_approval': DOSTER_MANUAL_APPROVAL}

    def connect(self, config):
        raise ValueError('Manual backup exception requires an explicitly pinned SSH key')

    def backup(self, label):
        if not self.identity_verified:
            raise ValueError('Manual backup exception requires exact target identity first')
        return dict(valid=True, provider='owner-manual-exception', approval=DOSTER_MANUAL_APPROVAL,
                    site_id=self.site_id, environment_id=self.environment_id, label=label,
                    scope='owner-managed-backups', automatic_backup_created=False,
                    approval_recorded_at=int(time.time()))

    def verify_transaction_backup(self, receipt, operation):
        expected = dict(valid=True, provider='owner-manual-exception', approval=DOSTER_MANUAL_APPROVAL,
                        site_id=self.site_id, environment_id=self.environment_id,
                        scope='owner-managed-backups', automatic_backup_created=False)
        if (not self.identity_verified or any(receipt.get(key) != value for key, value in expected.items())
                or not re.fullmatch(r'mrn-tool-transfer-[0-9]+', receipt.get('label', ''))
                or not 0 <= time.time() - receipt.get('approval_recorded_at', 0) < 3600):
            raise ValueError('Missing or mismatched Doster owner manual-backup approval receipt')
        return dict(receipt, operation=operation)


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

"""Native Kinsta backups with signed, target-bound, oldest-eligible rotation.

Instantiate only in the protected adapter, using the controller's persistent
Journal. MainWP/broker requests must use that same controller and writer lease.
An API label alone never establishes MRN ownership or safe retirement.
"""
from datetime import datetime
import fcntl
import math
import os
import time

from .artifact import SCHEMA, canonical, match, sha256
from .proof import Blocked

SCHEMA_BACKUPS = 'mrn.kinsta-backups/v1'
CAPACITY = 5


class KinstaBackupAdapter:
    """Add native backup/rotation callbacks to an existing qualified adapter."""
    def __init__(self, adapter, backups):
        self.adapter, self.backups = adapter, backups

    def __getattr__(self, name):
        return getattr(self.adapter, name)

    def preflight(self, request, code, manifest, urls):
        self.backups.preflight()
        return self.adapter.preflight(request, code, manifest, urls)

    def backup(self, request, challenge, purpose, fence):
        return self.backups.backup(request, challenge, purpose, fence)

    def verify_backup(self, request, backup):
        return self.backups.verify_backup(request, backup)

    def backup_status(self):
        return self.backups.status()


def backup_id(value):
    if type(value) not in (int, str) or not str(value).isdigit() or not 0 < int(value) < 10**20:
        raise Blocked('Kinsta returned an invalid backup ID')
    return str(int(value))


def created_at(value):
    if isinstance(value, str):
        try:
            date = datetime.fromisoformat(value.replace('Z', '+00:00'))
            if date.tzinfo is None:
                raise ValueError('timezone required')
            value = date.timestamp()
        except ValueError as error:
            raise Blocked('Kinsta backup creation time is invalid') from error
    if type(value) not in (int, float) or not math.isfinite(value) or value < 0:
        raise Blocked('Kinsta backup creation time is invalid')
    return value


class KinstaBackups:
    def __init__(self, provider, target, config, journal, *, rotate=False, protected_ids=(),
                 attempts=12, clock=time.time, sleep=time.sleep):
        if (target.site_id != provider.site_id or target.environment not in ('dev', 'staging', 'production')
                or config.get('url') != target.origin or type(rotate) is not bool
                or type(attempts) is not int or not 1 <= attempts <= 120):
            raise Blocked('Kinsta backup enrollment must bind the exact protected target and policy')
        config = dict(config)
        expected = 'live' if target.environment == 'production' else 'staging'
        if config.get('environment') not in (expected, 'dev' if expected == 'staging' else expected):
            raise Blocked('Kinsta backup environment class differs from the release target')
        config['environment'] = expected
        self.provider, self.target, self.config, self.journal = provider, target, config, journal
        self.rotate, self.protected_ids = rotate, {backup_id(item) for item in protected_ids}
        self.attempts, self.clock, self.sleep = attempts, clock, sleep
        self.path = journal.root / 'kinsta-backups.json'
        self.identity = {'target': vars(target), 'site_id': provider.site_id,
                         'environment_id': provider.environment_id}

    def load(self):
        value = self.journal.read(self.path)
        if value is None:
            return {'schema': SCHEMA_BACKUPS, **self.identity, 'records': {}}
        if value.get('schema') != SCHEMA_BACKUPS or any(value.get(k) != v for k, v in self.identity.items()):
            raise Blocked('Kinsta backup ledger belongs to a different target')
        return value

    def save(self, value):
        self.journal.save(value, self.path)

    def inventory(self):
        response = self.provider.api('/sites/environments/' + self.provider.environment_id + '/backups')
        rows = response.get('environment', {}).get('backups')
        if not isinstance(rows, list) or len(rows) > 10000:
            raise Blocked('Kinsta backup inventory is unavailable')
        result = {}
        for row in rows:
            key = backup_id(row.get('id'))
            if key in result:
                raise Blocked('Kinsta returned duplicate backup IDs')
            result[key] = row
        return result

    @staticmethod
    def binding(request, purpose):
        if purpose not in ('deploy', 'rollback'):
            raise Blocked('Unsupported Kinsta backup purpose')
        return {key: getattr(request, key) for key in
                ('deployment_id', 'owner', 'source_sha', 'artifact_sha256')} | {'purpose': purpose}

    def controller_state(self, record):
        name = record['binding']['deployment_id']
        state = self.journal.read(self.journal.root / ('run-' + name + '.json'))
        if state is None:
            state = self.journal.read()
        binding = record['binding']
        if (not state or state.get('schema') != SCHEMA or state.get('target') != vars(self.target)
                or any(state.get(k) != binding[k] for k in
                       ('deployment_id', 'owner', 'source_sha', 'artifact_sha256'))
                or state.get('finished') is not True):
            return None
        return state

    def abandoned_reservation(self, record):
        # slot-ready has no outstanding provider write: a recorded deletion
        # already completed and creation has not started. Abort never releases
        # create/delete intents or pending jobs, even if the lease is stale.
        if record['stage'] != 'slot-ready':
            return False
        state = self.controller_state(record)
        return bool(state and state.get('phases', {}).get('abort', {}).get('result') == 'passed'
                    and state.get('phases', {}).get('activate', {}).get('result') != 'passed')

    def pending(self, record):
        return record['stage'] not in ('complete', 'planned') and not self.abandoned_reservation(record)

    def terminal(self, record):
        """Only authenticated completed controller receipts release a snapshot."""
        state = self.controller_state(record)
        if state is None:
            return False
        phases = state.get('phases', {})
        terminal = (phases.get('verify', {}).get('result') == 'passed'
                    or phases.get('rollback', {}).get('result') == 'passed'
                    or phases.get('abort', {}).get('result') == 'passed')
        receipts = [state.get('backup'), phases.get('rollback', {}).get('rollback_backup')]
        nonce = 'kinsta:' + backup_id(record['receipt']['backup_id'])
        return terminal and any(row and row.get('nonce') == nonce for row in receipts)

    def candidates(self, ledger, inventory):
        eligible = []
        for record in ledger['records'].values():
            if record.get('stage') != 'complete':
                continue
            receipt = record['receipt']
            key = backup_id(receipt['backup_id'])
            row = inventory.get(key)
            if (key in self.protected_ids or not row or row.get('type') != 'manual'
                    or (row.get('note') or row.get('tag')) != receipt['label'] or not self.terminal(record)):
                continue
            timestamp = created_at(row.get('created_at'))
            if timestamp != record['snapshot_created_at']:
                raise Blocked('Tracked Kinsta backup identity changed')
            eligible.append((timestamp, int(key), record, row))
        return sorted(eligible, key=lambda item: item[:2])

    def status(self):
        """Read-only enrollment/preflight information; never rotates a backup."""
        self.provider.identity(self.config)
        ledger, inventory = self.load(), self.inventory()
        manual = sum(row.get('type') == 'manual' for row in inventory.values())
        eligible = self.candidates(ledger, inventory)
        pending = [row['binding']['deployment_id'] for row in ledger['records'].values()
                   if self.pending(row)]
        operations = [{'deployment_id': row['binding']['deployment_id'], 'stage': row['stage'],
                       'delete_operation': row.get('delete_operation'),
                       'create_operation': row.get('create_operation')}
                      for row in ledger['records'].values() if self.pending(row)]
        allowed = not pending and (manual < CAPACITY or (manual == CAPACITY and self.rotate and bool(eligible)))
        return {**self.identity, 'rotation_enabled': self.rotate, 'manual_count': manual,
                'capacity': CAPACITY, 'oldest_eligible': str(eligible[0][1]) if eligible else None,
                'manual_snapshot_ids': sorted(key for key, row in inventory.items() if row.get('type') == 'manual'),
                'pending_operations': operations,
                'pending_deployments': pending, 'ready': allowed,
                'next': 'backup' if allowed else 'reconcile-backup-operation' if pending else 'review-backup-retention'}

    def preflight(self):
        status = self.status()
        if not status['ready']:
            raise Blocked('Kinsta backup readiness blocked: ' + status['next'])
        return status

    def fence(self, descriptor):
        try:
            current = os.fstat(descriptor)
            expected = (self.journal.root / 'writer.lock').stat(follow_symlinks=False)
            if (current.st_dev, current.st_ino) != (expected.st_dev, expected.st_ino):
                raise ValueError('different writer')
            fcntl.flock(descriptor, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except (OSError, ValueError, TypeError) as error:
            raise Blocked('Kinsta backup mutation requires the controller writer lease') from error

    def poll(self, operation):
        match(operation, r'[A-Za-z][A-Za-z0-9:_-]{1,159}', 'Kinsta backup operation')
        for attempt in range(self.attempts):
            status = self.provider.api('/operations/' + operation).get('status')
            if status == 200:
                return
            if status != 202:
                raise Blocked('Kinsta backup operation failed; reconcile its recorded operation ID')
            if attempt + 1 < self.attempts:
                self.sleep(5)
        raise Blocked('Kinsta backup operation remains pending; resume the same request')

    def start_operation(self, ledger, record, kind, path, body=None):
        record['stage'] = kind + '-intent'
        self.save(ledger)  # Persist before sending any destructive/provider request.
        self.provider.identity(self.config)
        result = (self.provider.api(path, method='DELETE') if kind == 'delete'
                  else self.provider.api(path, body))
        operation = result.get('operation_id')
        if result.get('status') != 202:
            raise Blocked('Kinsta did not acknowledge the backup operation; reconcile its intent')
        match(operation, r'[A-Za-z][A-Za-z0-9:_-]{1,159}', 'Kinsta backup operation')
        record[kind + '_operation'] = operation
        record['stage'] = kind + '-pending'
        self.save(ledger)

    def backup(self, request, challenge, purpose, fence):
        """Controller backup callback. Known operations resume; uncertain writes stop."""
        if request.target != self.target:
            raise Blocked('Kinsta backup request target differs from protected enrollment')
        self.fence(fence)
        match(challenge, r'[a-f0-9]{32}', 'deployment backup challenge')
        self.provider.identity(self.config)
        binding = self.binding(request, purpose)
        current = self.journal.read()
        phase = 'backup' if purpose == 'deploy' else 'rollback'
        if (not current or current.get('finished') is not False or current.get('target') != vars(self.target)
                or any(current.get(k) != binding[k] for k in
                       ('deployment_id', 'owner', 'source_sha', 'artifact_sha256'))
                or current.get('phases', {}).get(phase, {}).get('result') != 'running'):
            raise Blocked('Kinsta backup must belong to the active controller backup phase')
        key = sha256(canonical(binding))
        ledger = self.load()
        record = ledger['records'].get(key)
        if record is None:
            if any(self.pending(row) for row in ledger['records'].values()):
                raise Blocked('An earlier Kinsta backup operation requires reconciliation')
            record = {'binding': binding, 'label': 'mrn-' + key[:32], 'stage': 'planned',
                      'requested_at': self.clock(), 'rotated_backup_id': None}
            ledger['records'][key] = record
            self.save(ledger)
        if record['binding'] != binding:
            raise Blocked('Kinsta backup request identity changed')
        if record['stage'] in ('create-intent', 'delete-intent'):
            raise Blocked('Kinsta backup outcome is unknown; inspect the retained intent before retrying')
        if record['stage'] == 'planned':
            inventory = self.inventory()
            manual = sum(row.get('type') == 'manual' for row in inventory.values())
            if manual >= CAPACITY:
                eligible = self.candidates(ledger, inventory)
                if manual != CAPACITY or not self.rotate or not eligible:
                    raise Blocked('Kinsta manual slots are full; no authorized tracked backup can be retired')
                _, _, old, selected = eligible[0]
                key_old = backup_id(old['receipt']['backup_id'])
                # Re-read immediately before selection; never broaden on a race.
                if self.inventory() != inventory:
                    raise Blocked('Kinsta backup inventory changed; recheck capacity')
                record.update(rotated_backup_id=key_old, retired_label=(selected.get('note') or selected.get('tag')))
                self.start_operation(ledger, record, 'delete', '/sites/environments/backups/' + key_old)
            else:
                record['stage'] = 'slot-ready'
                self.save(ledger)
        if record['stage'] == 'delete-pending':
            self.poll(record['delete_operation'])
            if record['rotated_backup_id'] in self.inventory():
                raise Blocked('Kinsta deletion completed without freeing the selected backup slot')
            record['stage'] = 'slot-ready'
            self.save(ledger)
        if record['stage'] == 'slot-ready':
            if sum(row.get('type') == 'manual' for row in self.inventory().values()) >= CAPACITY:
                raise Blocked('Kinsta backup capacity changed; this request will not delete another snapshot')
            self.start_operation(ledger, record, 'create',
                '/sites/environments/' + self.provider.environment_id + '/manual-backups', {'tag': record['label']})
        if record['stage'] == 'create-pending':
            self.poll(record['create_operation'])
            matches = [row for row in self.inventory().values()
                       if row.get('type') == 'manual' and (row.get('note') or row.get('tag')) == record['label']]
            if len(matches) != 1:
                raise Blocked('Completed Kinsta backup could not be read back uniquely')
            record['receipt'] = {'valid': True, 'provider': 'kinsta',
                'environment_id': self.provider.environment_id, 'label': record['label'],
                'operation_id': record['create_operation'], 'backup_id': matches[0]['id'],
                'created_at': record['requested_at'], 'scope': 'deployment-transaction'}
            backup_id(matches[0]['id'])
            record['snapshot_created_at'] = created_at(matches[0].get('created_at'))
            record['stage'] = 'complete'
            self.save(ledger)
        if record['stage'] != 'complete':
            raise Blocked('Kinsta backup operation requires reconciliation')
        self.provider.verify_transaction_backup(record['receipt'], purpose)
        return {'target': vars(self.target), 'challenge': challenge,
                'nonce': 'kinsta:' + backup_id(record['receipt']['backup_id']),
                'database_verified': True, 'remote_verified': True, 'verified_at': self.clock()}

    def verify_backup(self, request, backup):
        if request.target != self.target or backup.get('target') != vars(self.target):
            return False
        self.provider.identity(self.config)
        ledger = self.load()
        record = ledger['records'].get(sha256(canonical(self.binding(request, backup.get('purpose', 'deploy')))))
        if not record or record['stage'] != 'complete' or backup.get('nonce') != 'kinsta:' + backup_id(record['receipt']['backup_id']):
            return False
        try:
            self.provider.verify_transaction_backup(record['receipt'], record['binding']['purpose'])
        except (ValueError, RuntimeError):
            return False
        return True

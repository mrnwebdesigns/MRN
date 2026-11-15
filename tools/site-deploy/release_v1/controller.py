"""Resumable phase orchestration for a single authorized deployment target."""
from contextlib import contextmanager
from dataclasses import dataclass
import math
import secrets
import time
from urllib.parse import urlsplit

from .artifact import ENVIRONMENTS, Limits, SCHEMA, canonical, inspect, match, public_paths, sha256
from .proof import Blocked, Throttled, retry_delay, runtime_health

PHASES = ('preflight', 'backup', 'activate', 'proof', 'migrate', 'refresh', 'verify')
WRITES = ('activate', 'migrate', 'refresh', 'rollback')


@dataclass(frozen=True)
class Target:
    repository: str
    environment: str
    site_id: str
    origin: str

    def __post_init__(self):
        match(self.repository, r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', 'repository')
        match(self.site_id, r'[A-Za-z0-9_.:-]{1,160}', 'site identity')
        value = urlsplit(self.origin)
        schemes = ('http', 'https') if self.environment == 'local' else ('https',)
        if (self.environment not in ENVIRONMENTS or value.scheme not in schemes or not value.hostname
                or not value.hostname.isascii() or value.username is not None
                or self.origin != f'{value.scheme}://{value.netloc}' or self.origin.lower() != self.origin
                or value.netloc != (value.hostname if value.port is None else f'{value.hostname}:{value.port}')
                or any(char.isspace() for char in self.origin)):
            raise ValueError('Expected an exact canonical environment origin')

    def urls(self, paths):
        return [self.origin + path for path in paths]


@dataclass(frozen=True)
class Request:
    deployment_id: str
    owner: str
    target: Target
    source_sha: str
    artifact_sha256: str
    paths: list

    def __post_init__(self):
        match(self.deployment_id, r'[A-Za-z0-9][A-Za-z0-9_-]{0,95}', 'deployment ID')
        match(self.owner, r'[A-Za-z0-9][A-Za-z0-9_:@./-]{0,159}', 'deployment owner')
        match(self.source_sha, r'[a-f0-9]{40}', 'source commit')
        match(self.artifact_sha256, r'[a-f0-9]{64}', 'artifact SHA-256')
        if not public_paths(self.paths):
            raise ValueError('At least one verification path is required')


class Controller:
    def __init__(self, adapter, target, journal, *, limits=Limits(), stale_seconds=3600,
                 backup_seconds=900, proof_attempts=3, proof_wait_seconds=30,
                 clock=time.time, sleep=time.sleep):
        if (type(stale_seconds) is not int or stale_seconds < 1 or type(backup_seconds) is not int
                or backup_seconds < 1 or type(proof_attempts) is not int or not 1 <= proof_attempts <= 5
                or type(proof_wait_seconds) is not int or not 0 <= proof_wait_seconds <= 120):
            raise ValueError('Invalid release timing policy')
        self.adapter, self.target, self.journal = adapter, target, journal
        self.limits, self.stale_seconds, self.backup_seconds = limits, stale_seconds, backup_seconds
        self.proof_attempts, self.proof_wait_seconds = proof_attempts, proof_wait_seconds
        self.clock, self.sleep = clock, sleep

    def observed(self):
        if self.adapter.identify() != self.target:
            raise Blocked('Observed target identity differs from protected adapter configuration')
        return match(self.adapter.current(), r'[a-f0-9]{64}', 'observed release ID')

    def read(self):
        state = self.journal.read()
        if state and (state['schema'] != SCHEMA or state['target'] != vars(self.target)):
            raise Blocked('Journal belongs to a different target or contract version')
        return state

    def event(self, state, phase, result, **details):
        receipt = {key: state[key] for key in ('schema', 'target', 'source_sha', 'artifact_sha256',
                                               'deployment_id', 'owner', 'lock_id')}
        state['updated_at'] = self.clock()
        current = {'phase': phase, 'result': result, 'at': state['updated_at'],
                   'backup_nonce': (state.get('backup') or {}).get('nonce'),
                   'verification_urls': state['verification_urls'], 'rollback_state': state['rollback_state'],
                   **details}
        state['events'].append(self.journal.sign({**receipt, **current}))
        state['phases'][phase] = current
        self.journal.save(state)

    def start(self, request, state, current):
        archived = self.journal.root / ('run-' + request.deployment_id + '.json')
        if self.journal.read(archived):
            raise Blocked('Deployment ID has already been used')
        if state:
            self.journal.save(state, self.journal.root / ('run-' + state['deployment_id'] + '.json'))
        return {'schema': SCHEMA, **vars(request), 'target': vars(request.target), 'lock_id': secrets.token_hex(16),
                'baseline': current, 'desired': None, 'updated_at': self.clock(), 'finished': False,
                'backup': None, 'phases': {}, 'events': [], 'migrations': {}, 'rollback_state': 'available-before-content',
                'verification_urls': self.target.urls(request.paths)}

    def stale(self, state):
        proof = state['phases'].get('proof', {})
        last_activity = max(state['updated_at'], proof.get('retry_not_before', 0))
        return not state['finished'] and self.clock() >= last_activity + self.stale_seconds

    @staticmethod
    def result(state, phase):
        return state['phases'].get(phase, {}).get('result')

    def status(self):
        state = self.read()
        try:
            current = self.observed()
        except Exception:
            current = None
        if not state:
            return {'schema': SCHEMA, 'target': vars(self.target), 'current_release': current, 'lock': 'none',
                    'backup': None, 'proof': None, 'migrations': {}, 'pending_migrations': [],
                    'recovery_required': current is None,
                    'next': 'preflight' if current else 'inspect-target'}
        next_phase, recovery = self.next_phase(state, current)
        return {'schema': SCHEMA, 'target': state['target'], 'current_release': current,
                'deployment_id': state['deployment_id'], 'owner': state['owner'],
                'source_sha': state['source_sha'], 'artifact_sha256': state['artifact_sha256'],
                'lock': 'none' if state['finished'] else 'stale' if self.stale(state) else 'active',
                'lock_id': state['lock_id'], 'backup': state['backup'],
                'proof': state['phases'].get('proof'), 'migrations': state['migrations'],
                'desired_release': state['desired'], 'previous_release': state['baseline'],
                'pending_migrations': [name for name in state.get('selected_migrations', [])
                                       if name not in state['migrations']],
                'rollback_state': state['rollback_state'], 'recovery_required': recovery, 'next': next_phase,
                'verification_urls': state['verification_urls'], 'phases': state['phases']}

    def next_phase(self, state, current):
        if current is None:
            return 'inspect-target', True
        if state['finished']:
            expected = state['desired'] if self.result(state, 'verify') == 'passed' else state['baseline']
            return ('preflight', False) if current == expected else ('inspect-target', True)
        if self.stale(state) or any(self.result(state, phase) in ('running', 'uncertain') for phase in (*PHASES, 'rollback')):
            return 'recover', True
        if self.result(state, 'rollback') == 'reconciled':
            return ('rollback', True) if current in (state['baseline'], state['desired']) else ('inspect-target', True)
        expected = state['desired'] if self.result(state, 'activate') == 'passed' else state['baseline']
        if current != expected:
            return 'inspect-target', True
        for phase in PHASES:
            if self.result(state, phase) != 'passed':
                return phase, False
            if phase == 'backup' and self.result(state, 'activate') in (None, 'reconciled', 'failed') and not self.fresh(state['backup']):
                return 'backup', False
        raise Blocked('Deployment journal lacks a terminal receipt')

    def fresh(self, backup):
        return 0 <= self.clock() - backup['verified_at'] <= self.backup_seconds

    def run(self, phase, request, archive=None, *, confirmed_origin=None):
        if request.target != self.target:
            raise Blocked('Requested target differs from protected adapter configuration')
        if (self.target.environment == 'production' or phase in ('recover', 'rollback', 'abort')) and confirmed_origin != self.target.origin:
            raise Blocked('Explicit confirmation of the exact target origin is required')
        if self.adapter.authorize(phase, request) is not True:
            raise Blocked('Deployment owner, source and target authorization are required')
        with self.journal.writer() as fence:
            current = self.observed()
            state = self.read()
            if not state or state['deployment_id'] != request.deployment_id:
                if state and self.next_phase(state, current)[0] == 'inspect-target':
                    raise Blocked('Observed active release differs from the completed deployment; reconcile target drift')
                if phase != 'preflight' or (state and not state['finished']):
                    raise Blocked('Another run owns this target; inspect status and recover it explicitly')
                state = self.start(request, state, current)
            binding = {**vars(request), 'target': vars(request.target)}
            if any(state[key] != value for key, value in binding.items()):
                raise Blocked('Request differs from the run that owns the persisted lock')
            if state['finished']:
                if self.result(state, phase) == 'passed':
                    return self.status()
                raise Blocked('Deployment is finished; use a new deployment ID')
            if phase == 'recover':
                self.recover(request, state, current)
                return self.status()
            next_phase, recovery = self.next_phase(state, current)
            if phase == 'abort' and self.result(state, 'activate') in (None, 'failed', 'reconciled') and not recovery:
                state.update(finished=True, rollback_state='aborted-before-activation')
                self.event(state, 'abort', 'passed', observed_release=current)
                return self.status()
            if phase == 'rollback' and (not recovery or next_phase == 'rollback'):
                self.rollback(request, state, current, fence)
                return self.status()
            if recovery:
                raise Blocked('Target requires recovery; run status for the next action')
            if self.result(state, phase) == 'passed' and phase != next_phase:
                return self.status()
            if phase != next_phase:
                raise Blocked(f'Run the {next_phase} phase first')
            if phase in ('preflight', 'activate', 'migrate'):
                with self.package(state, phase, archive) as package:
                    self.execute(phase, request, state, current, fence, package)
            else:
                self.execute(phase, request, state, current, fence)
            return self.status()

    @contextmanager
    def package(self, state, phase, path):
        try:
            if path is None:
                raise ValueError('This phase requires the original immutable artifact')
            with inspect(path, state['artifact_sha256'], state['source_sha'], self.limits) as package:
                yield package
        except (ValueError, OSError, ImportError) as error:
            # Phase callbacks normalize their own errors, so this path is only
            # for local artifact inspection failures.
            self.event(state, phase, 'failed', diagnostic=str(error))
            raise Blocked('Artifact inspection failed; inspect the phase receipt') from error

    def execute(self, phase, request, state, current, fence, package=None):
        if phase == 'proof':
            self.prove(request, state)
            return
        if phase == 'activate':
            self.require_backup(request, state['backup'], fresh=True)
        if phase == 'migrate' and state['selected_migrations']:
            self.require_backup(request, state['backup'])
        if phase == 'migrate' and state['selected_migrations']:
            state['rollback_state'] = 'content-restore-requires-operator'
        self.event(state, phase, 'running', observed_release=current)
        try:
            details = getattr(self, 'do_' + phase)(request, state, current, fence, package)
        except Exception as error:
            result = 'uncertain' if phase in (*WRITES, 'backup') else 'failed'
            intent = state['phases'][phase]
            challenge = {key: intent[key] for key in ('backup_challenge', 'backup_purpose') if key in intent}
            self.event(state, phase, result, diagnostic=self.diagnostic(error), **challenge)
            raise Blocked(f'{phase} did not pass; inspect status') from error
        self.event(state, phase, 'passed', **details)

    @staticmethod
    def diagnostic(error):
        # Provider exceptions and WP-CLI stderr may contain credentials or SQL.
        return str(error) if isinstance(error, Blocked) else type(error).__name__

    def do_preflight(self, request, state, current, fence, package):
        root, manifest, media = package
        selected = [row for row in manifest['migrations'] if self.target.environment in row['environments']]
        paths = list(dict.fromkeys([*request.paths, *(path for row in selected for path in row['paths'])]))
        public_paths(paths)
        health = {}
        if self.target.environment == 'local':
            url = self.target.urls(request.paths)[0]
            health = runtime_health(url, **self.adapter.local_health_options())
        qa = self.adapter.preflight(request, root / 'code.tar', manifest, self.target.urls(paths))
        if (not isinstance(qa, dict) or qa.get('passed') is not True or qa.get('source_sha') != request.source_sha
                or qa.get('code_sha256') != manifest['code_sha256'] or qa.get('target') != vars(self.target)):
            raise Blocked('Exact artifact, target qualification and source QA evidence are required')
        evidence = match(qa.get('evidence_id'), r'[A-Za-z0-9_.:/-]{1,160}', 'QA evidence ID')
        state.update(desired=manifest['code_sha256'], selected_migrations=[row['id'] for row in selected],
                     verification_urls=self.target.urls(paths))
        return {'qa_evidence_id': evidence, 'code_sha256': state['desired'], 'runtime_health': health,
                'media': media, 'selected_migrations': state['selected_migrations'],
                'excluded_migrations': [row['id'] for row in manifest['migrations'] if row not in selected]}

    def backup_for(self, request, state, phase, purpose, fence):
        challenge = secrets.token_hex(16)
        self.event(state, phase, 'running', backup_challenge=challenge, backup_purpose=purpose)
        after = state['updated_at']
        backup = self.adapter.backup(request, challenge, purpose, fence)
        if (not isinstance(backup, dict) or backup.get('challenge') != challenge
                or backup.get('target') != vars(self.target) or backup.get('database_verified') is not True
                or (self.target.environment != 'local' and backup.get('remote_verified') is not True)
                or type(backup.get('verified_at')) not in (int, float)
                or not math.isfinite(backup['verified_at']) or not after <= backup['verified_at'] <= self.clock()):
            raise Blocked('A fresh, verified database backup for this target and deployment challenge is required')
        nonce = match(backup.get('nonce'), r'[A-Za-z0-9_.:-]{1,160}', 'provider backup nonce')
        return {'nonce': nonce, 'challenge': challenge, 'target': vars(self.target), 'database_verified': True,
                'remote_verified': backup.get('remote_verified') is True, 'purpose': purpose,
                'verified_at': backup['verified_at']}

    def require_backup(self, request, backup, *, fresh=False):
        if (not backup or (fresh and not self.fresh(backup))
                or self.adapter.verify_backup(request, backup) is not True):
            raise Blocked('Verified backup is unavailable or expired; inspect the backup before proceeding')

    def do_backup(self, request, state, current, fence, package):
        state['backup'] = self.backup_for(request, state, 'backup', 'deploy', fence)
        return {'backup': state['backup']}

    def do_activate(self, request, state, current, fence, package):
        root, manifest, _ = package
        if self.observed() != state['baseline']:
            raise Blocked('Release changed before activation')
        if current != state['desired']:
            self.adapter.activate(request, root / 'code.tar', manifest, current, state['backup'], fence)
        if self.observed() != state['desired']:
            raise Blocked('The immutable release could not be read back after activation')
        return {'previous_release': current, 'observed_release': state['desired']}

    def prove(self, request, state):
        prior = state['phases'].get('proof', {})
        if self.clock() < prior.get('retry_not_before', 0):
            raise Blocked('Provider Retry-After is still in effect; see proof receipt for the retry time')
        if self.observed() != state['desired']:
            raise Blocked('Active release changed before browser proof')
        deadline = self.clock() + self.proof_wait_seconds
        urls = self.target.urls(request.paths)
        for number in range(1, self.proof_attempts + 1):
            self.event(state, 'proof', 'running', attempt=number, urls=urls)
            try:
                self.check_public(self.adapter.proof(request, state['desired'], urls), state['desired'], urls)
            except Throttled as error:
                now = self.clock()
                delay = retry_delay(error.retry_after, now)
                delay = delay if delay is not None else 15 * 2 ** (number - 1)
                self.event(state, 'proof', 'throttled', attempt=number, urls=urls, http_status=error.status,
                           retry_seconds=delay, retry_not_before=now + delay)
                if number == self.proof_attempts or now + delay > deadline:
                    raise Blocked('Browser proof is rate limited; retry only proof after the recorded time') from error
                self.sleep(delay)
                if self.clock() < now + delay:
                    raise Blocked('Provider Retry-After is still in effect')
            except Exception as error:
                self.event(state, 'proof', 'failed', attempt=number, urls=urls, diagnostic=self.diagnostic(error))
                raise Blocked('Browser proof failed; retry only proof after correcting the failure') from error
            else:
                self.event(state, 'proof', 'passed', attempt=number, urls=urls, observed_release=state['desired'])
                return

    @staticmethod
    def check_public(result, release, urls):
        if (not isinstance(result, dict) or result.get('passed') is not True
                or result.get('release_id') != release or result.get('urls') != urls):
            raise Blocked('Required public evidence does not match the selected release and exact URLs')

    def do_migrate(self, request, state, current, fence, package):
        root, manifest, _ = package
        for row in manifest['migrations']:
            name = row['id']
            if name not in state['selected_migrations'] or name in state['migrations']:
                continue
            if self.observed() != state['desired']:
                raise Blocked('Release changed before content migration')
            self.require_backup(request, state['backup'])
            fingerprints = {path: manifest['files'][path]['sha256'] for path in [row['script'], *row['assets']]}
            definition = {key: value for key, value in row.items() if key != 'environments'}
            fingerprint = sha256(canonical({'migration': definition, 'files': fingerprints}))
            result = self.adapter.migrate(request, row, root / 'migrations', fingerprints,
                                          fingerprint, state['backup'], fence)
            if (not isinstance(result, dict) or result.get('result') not in ('applied', 'already-applied')
                    or result.get('id') != name or result.get('fingerprint') != fingerprint):
                raise Blocked('WordPress did not confirm an exact migration result')
            attachments = result.get('attachments', {})
            if (not isinstance(attachments, dict) or set(attachments) != set(row['assets'])
                    or any(type(attachment) is not int or attachment <= 0 for attachment in attachments.values())):
                raise Blocked('Migration did not confirm a WordPress attachment for each declared image')
            state['migrations'][name] = {'id': name, 'result': result['result'], 'fingerprint': fingerprint,
                                         'paths': row['paths'], 'attachments': attachments}
            self.event(state, 'migrate', 'running', migration=state['migrations'][name])
        return {'migrations': state['migrations']}

    def do_refresh(self, request, state, current, fence, package):
        urls = state['verification_urls']
        if self.adapter.refresh(request, urls, fence) != urls:
            raise Blocked('Scoped cache refresh did not acknowledge the exact URL list')
        return {'refreshed_urls': urls}

    def do_verify(self, request, state, current, fence, package):
        urls = state['verification_urls']
        self.check_public(self.adapter.verify(request, state['desired'], urls, state['migrations']), state['desired'], urls)
        if self.observed() != state['desired']:
            raise Blocked('Active release changed during final verification')
        state['finished'] = True
        if not state['selected_migrations']:
            state['rollback_state'] = 'operator-managed-after-verification'
        return {'observed_release': state['desired'], 'migrations': state['migrations']}

    def recover(self, request, state, current):
        interrupted = [phase for phase in (*PHASES, 'rollback')
                       if self.result(state, phase) in ('running', 'uncertain')]
        # Durable write intents stay unresolved until the adapter reconciles
        # outstanding provider jobs. An expired lease alone grants no authority.
        if not interrupted and not self.stale(state):
            raise Blocked('The active owner can continue; recovery is only for stale or interrupted work')
        intents = {name: state['phases'][name] for name in interrupted}
        if self.adapter.quiescent(request, intents, current) is not True:
            raise Blocked('Provider work is still active or unknown; resolve it before recovery')
        phase = interrupted[-1] if interrupted else None
        if phase in ('activate', 'rollback'):
            expected = {state['baseline'], state['desired']}
        else:
            expected = {state['desired'] if self.result(state, 'activate') == 'passed' else state['baseline']}
        if current not in expected:
            raise Blocked('The current release is outside the recovery receipt; investigate target drift')
        if phase == 'activate':
            self.require_backup(request, state['backup'])
            result = 'passed' if current == state['desired'] else 'reconciled'
            self.event(state, phase, result, observed_release=current, recovered=True)
        elif phase == 'rollback':
            # Selecting the baseline is only one part of rollback. Re-enter
            # rollback for scoped refresh and verification, never advance forward.
            self.event(state, phase, 'reconciled', observed_release=current, recovered=True)
        elif phase:
            self.event(state, phase, 'reconciled', observed_release=current, recovered=True)
        self.event(state, 'recover', 'passed', recovered_phase=phase, observed_release=current)

    def rollback(self, request, state, current, fence):
        if self.result(state, 'activate') != 'passed' or (state['selected_migrations'] and self.result(state, 'migrate') is not None):
            raise Blocked('Automatic code rollback requires activation and zero attempted content writes')
        if current not in (state['desired'], state['baseline']):
            raise Blocked('Rollback compare-and-switch failed: selected release changed')
        state['rollback_state'] = 'in-progress'
        self.event(state, 'rollback', 'running', observed_release=current)
        try:
            backup = self.backup_for(request, state, 'rollback', 'rollback', fence)
            self.require_backup(request, backup, fresh=True)
            self.event(state, 'rollback', 'running', rollback_backup=backup)
            if current == state['desired']:
                self.adapter.rollback(request, state['baseline'], current, backup, fence)
            if self.observed() != state['baseline']:
                raise Blocked('Previous release was not read back')
            urls = self.target.urls(request.paths)
            if self.adapter.refresh(request, urls, fence) != urls:
                raise Blocked('Rollback cache refresh did not acknowledge the exact URL list')
            self.check_public(self.adapter.verify(request, state['baseline'], urls, {}), state['baseline'], urls)
        except Exception as error:
            intent = state['phases']['rollback']
            context = {key: intent[key] for key in ('backup_challenge', 'backup_purpose', 'rollback_backup') if key in intent}
            self.event(state, 'rollback', 'uncertain', diagnostic=self.diagnostic(error), **context)
            raise Blocked('Rollback requires recovery; inspect status') from error
        state.update(finished=True, rollback_state='verified')
        self.event(state, 'rollback', 'passed', observed_release=state['baseline'], rollback_backup=backup)

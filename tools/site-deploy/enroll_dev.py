#!/usr/bin/env python3
"""Resumable, operator-owned CloudPanel Dev deployment enrollment.

Bootstrap only queues new sites; the scanner advances one bounded stage per
pass. Existing sites, Live, database migrations and Stack promotion are excluded.
"""
import argparse
import base64
import fcntl
import hashlib
import json
import os
from pathlib import Path
import pwd
import re
import stat
import subprocess
import sys
import tempfile
import time
from datetime import datetime, timezone

from deploy import allowed, digest
from enrollment_github import GitHub

TOOLS = Path(__file__).resolve().parent
ACCOUNT = 'mrnwebdesigns.1password.com'


def check(value, pattern, label):
    if not isinstance(value, str) or not re.fullmatch(pattern, value):
        raise ValueError('Invalid ' + label)
    return value


def private_path(path, directory=False):
    """Configuration/state must be operator-owned, never writable by a site."""
    path = Path(path)
    if path.is_symlink():
        raise ValueError('Symlinked enrollment path')
    info = path.stat()
    if info.st_uid != os.geteuid() or info.st_mode & 0o022:
        raise ValueError('Enrollment path must be operator-owned and not group/world writable')
    if directory != stat.S_ISDIR(info.st_mode):
        raise ValueError('Unexpected enrollment path type')
    return path


def save(path, value):
    path = Path(path)
    fd, name = tempfile.mkstemp(prefix='.enrollment-', dir=path.parent)
    try:
        with os.fdopen(fd, 'w') as output:
            json.dump(value, output, indent=2, sort_keys=True)
            output.write('\n'); output.flush(); os.fsync(output.fileno())
        os.replace(name, path)
    finally:
        if os.path.exists(name):
            os.unlink(name)


def command(argv, **kwargs):
    result = subprocess.run(argv, capture_output=True, timeout=kwargs.pop('timeout', 120), **kwargs)
    if result.returncode:
        # WordPress/credential subprocess output can contain secrets.
        raise RuntimeError('Enrollment subprocess failed: ' + Path(argv[0]).name + '; output withheld')
    return result.stdout


def load_config(path):
    config = json.loads(private_path(path).read_text())
    if config.get('schema') != 1 or config.get('enabled') is not True:
        raise ValueError('Dev enrollment is not enabled in the operator configuration')
    if config.get('organization') != 'mrnwebdesigns':
        raise ValueError('Enrollment is restricted to the MRN organization')
    check(config.get('tooling_ref'), r'[0-9a-f]{40}', 'immutable tooling revision')
    check(config.get('ssh_host'), r'[A-Za-z0-9][A-Za-z0-9.-]*', 'SSH hostname')
    if not isinstance(config.get('ssh_port'), int) or not 1 <= config['ssh_port'] <= 65535:
        raise ValueError('Invalid SSH port')
    for key in ('state_root', 'sites_root', 'known_hosts_file', 'qa_bin'):
        if not Path(config.get(key, '')).is_absolute():
            raise ValueError('Absolute operator path required: ' + key)
    if not config.get('credential_command') or not all(isinstance(v, str) for v in config['credential_command']):
        raise ValueError('Operator credential command required')
    if not config.get('team_slugs') or any(not re.fullmatch(r'[a-z0-9][a-z0-9-]*', v) for v in config['team_slugs']):
        raise ValueError('At least one approved developer team slug is required')
    root = Path(config['state_root'])
    root.mkdir(mode=0o700, parents=True, exist_ok=True)
    private_path(root, directory=True)
    if root.stat().st_mode & 0o077:
        raise ValueError('Enrollment state must be private (0700)')
    # Run only this reviewed tool checkout; a branch name or dirty tree is not a pin.
    repo = TOOLS.parents[1]
    if command(['git', '-C', str(repo), 'rev-parse', 'HEAD'], text=True).strip() != config['tooling_ref']:
        raise ValueError('Enrollment tooling checkout does not match its configured immutable revision')
    if command(['git', '-C', str(repo), 'status', '--porcelain', '--untracked-files=no'], text=True).strip():
        raise ValueError('Enrollment tooling checkout has local edits')
    return config


def site_process_env():
    # Website code and its source scanners must never inherit the credential
    # service's token or GitHub administration environment.
    return {key: os.environ[key] for key in ('PATH', 'LANG', 'LC_ALL', 'TMPDIR') if key in os.environ}


def wp(site, code):
    argv = ['sudo', '-u', site['user'], '--', 'env', 'WP_CLI_PHP_ARGS=-d memory_limit=512M',
            'wp', '--path=' + site['root'], '--skip-themes', 'eval', code]
    output = command(argv, text=True, timeout=300, env=site_process_env())
    rows = [line[11:] for line in output.splitlines() if line.startswith('MRN_RESULT=')]
    if len(rows) != 1:
        raise ValueError('WordPress did not return one identity/backup receipt')
    return json.loads(rows[0])


def identify(root, config):
    root = Path(root)
    sites = Path(config['sites_root']).resolve()
    if not root.is_absolute() or root.is_symlink() or root.resolve() != root:
        raise ValueError('WordPress root must be an absolute physical path')
    relative = root.relative_to(sites).parts
    if len(relative) != 3 or relative[1] != 'htdocs':
        raise ValueError('Expected a CloudPanel /home/<owner>/htdocs/<domain> site')
    user, _, domain = relative
    check(user, r'[a-z_][a-z0-9_-]*', 'CloudPanel owner')
    check(domain, r'[a-z0-9][a-z0-9-]*\.mrndev\.io', 'Dev domain')
    account = pwd.getpwnam(user)
    if account.pw_uid == 0 or root.stat().st_uid != account.pw_uid or Path(account.pw_dir) != sites / user:
        raise ValueError('CloudPanel owner or home differs from the intended site')
    site = {'root': str(root), 'user': user, 'uid': account.pw_uid, 'gid': account.pw_gid,
            'home': account.pw_dir, 'url': 'https://' + domain, 'domain': domain}
    identity = wp(site, "echo 'MRN_RESULT=' . wp_json_encode(array('url'=>untrailingslashit(home_url()), 'environment'=>wp_get_environment_type(), 'slug'=>get_stylesheet(), 'template'=>get_template(), 'root'=>realpath(ABSPATH)));" )
    if identity['url'] != site['url'] or identity['root'] != site['root'] or identity['environment'] != 'development':
        raise ValueError('Fresh WordPress identity is not this exact development environment')
    for key in ('slug', 'template'):
        site[key] = check(identity[key], r'[a-z0-9_-]+', 'theme identity')
    if site['slug'] == site['template']:
        raise ValueError('Dev enrollment requires an active child theme')
    site['source_path'] = 'public/wp-content/themes/' + site['slug']
    site['repository'] = config['organization'] + '/' + domain.removesuffix('.mrndev.io') + '-site'
    site['state_dir'] = str(sites / user / '.mrn-site-deploy' / 'dev')
    return site


def credentials(config, site):
    result = command(config['credential_command'], input=json.dumps({'account': ACCOUNT, 'domain': site['domain'],
                      'site_user': site['user'], 'repository': site['repository']}), text=True)
    data = json.loads(result)
    if data.get('account') != ACCOUNT or any(not data.get(k) for k in ('github_token', 'qa_engine_token', 'deploy_private_key')):
        raise ValueError('MRN business credential lookup is incomplete')
    # Canonical operator-verified host keys, never ssh-keyscan trust-on-first-use.
    data['known_hosts'] = private_path(config['known_hosts_file']).read_text()
    if not data['known_hosts'].strip():
        raise ValueError('Pinned SSH host keys are missing')
    return data


def runtime_prepare(site, secrets):
    """Every first runtime setup write follows the shared verified backup gate."""
    code = (TOOLS / 'backup.php').read_text().removeprefix('<?php')
    label = 'mrn-dev-enrollment-' + str(time.time_ns())
    backup = wp(site, "putenv('MRN_BACKUP_LABEL=" + label + "');\n" + code)
    if backup.get('valid') is not True or backup.get('label') != label:
        raise ValueError('Enrollment requires a fresh verified remote database backup')
    with tempfile.TemporaryDirectory(prefix='mrn-enrollment-key-') as temporary:
        key = Path(temporary) / 'identity'
        key.write_text(secrets['deploy_private_key'] + '\n'); key.chmod(0o600)
        public = command(['ssh-keygen', '-y', '-P', '', '-f', str(key)], text=True).strip()
    if not re.fullmatch(r'(ssh-ed25519|ssh-rsa|ecdsa-sha2-nistp\d+) [A-Za-z0-9+/=]+', public):
        raise ValueError('Unsupported deployment identity')
    # Run all site-owned filesystem writes as the site user. Even if that user
    # replaces a directory between checks, this cannot become a root write.
    setup = r"""
import json, os, pathlib, sys
request = json.load(sys.stdin)
home = pathlib.Path(request['home'])
if home.resolve() != home or home.stat().st_uid != os.geteuid():
    raise SystemExit('Wrong site home')
for path in (home / '.ssh', home / '.mrn-site-deploy', pathlib.Path(request['state_dir'])):
    if path.is_symlink() or (path.exists() and (path.stat().st_uid != os.geteuid() or not path.is_dir())):
        raise SystemExit('Unsafe site-private path')
    path.mkdir(mode=0o700, exist_ok=True)
    path.chmod(0o700)
keys = home / '.ssh/authorized_keys'
if keys.is_symlink() or (keys.exists() and (not keys.is_file() or keys.stat().st_uid != os.geteuid() or keys.stat().st_nlink != 1)):
    raise SystemExit('Unsafe authorized_keys')
existing = keys.read_text() if keys.exists() else ''
public = request['public']
parts = public.split()
# A key already carrying restrictions stays restricted. Do not add an
# unrestricted duplicate merely because its line starts with options.
present = any(any(words[i:i+2] == parts for i in range(len(words)-1))
              for words in (line.split() for line in existing.splitlines()))
if not present:
    fd = os.open(keys, os.O_WRONLY | os.O_APPEND | os.O_CREAT | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'w') as stream:
        stream.write('\n' + public + ' mrn-dev-enrollment\n')
    keys.chmod(0o600)
"""
    command(['sudo', '-u', site['user'], '--', 'python3', '-c', setup],
            input=json.dumps({'home': site['home'], 'state_dir': site['state_dir'], 'public': public}), text=True, env=site_process_env())
    return backup


def snapshot(site):
    root = Path(site['root']) / 'wp-content/themes' / site['slug']
    if root.is_symlink() or root.resolve() != root:
        raise ValueError('Enrollment refuses a deployed loader or symlinked child; reconcile existing deployment first')
    files, baseline = {}, {}
    total = 0
    for path in sorted(root.rglob('*')):
        relative = path.relative_to(root).as_posix()
        if any(ord(character) < 32 for character in relative):
            raise ValueError('Control character in child source filename')
        if not allowed(relative):
            continue
        if path.is_symlink() or (path.is_file() and path.stat().st_nlink != 1):
            raise ValueError('Child source contains symlinks or hard links')
        if not path.is_file():
            continue
        data = path.read_bytes(); total += len(data)
        if total > 32 * 1024 * 1024 or len(files) >= 2000:
            raise ValueError('Child snapshot exceeds automatic enrollment limit')
        baseline[relative] = hashlib.sha256(data).hexdigest()
        files[site['source_path'] + '/' + relative] = data
    functions = site['source_path'] + '/functions.php'
    if functions not in files or site['source_path'] + '/style.css' not in files:
        raise ValueError('Child functions.php and style.css are required')
    if any(name in baseline for name in ('mrn-assets.json', 'mrn-release.json', 'mrn-release-assets.php')):
        raise ValueError('Immutable deployment output is not editable source')
    if b'mrn-release-assets.php' not in files[functions]:
        original = files[functions]
        if not original.startswith(b'<?php'):
            raise ValueError('Unexpected child PHP entry point')
        optin = b"\n// The trusted MRN build supplies the immutable asset adapter.\nif ( is_file( __DIR__ . '/mrn-release-assets.php' ) ) {\n\trequire_once __DIR__ . '/mrn-release-assets.php';\n}\n"
        files[functions] = original[:5] + optin + original[5:]
    return files, digest(baseline)


def handoff(site):
    return (f"# Deploy this website\n\nDev: {site['url']}\nRepository: https://github.com/{site['repository']}\n\n"
            "Use your existing Git app. Pull first, commit your child-theme changes, and push main.\n"
            "QA must pass before the same commit is built, backed up and deployed to Dev.\n"
            "CSS/JS versions and minified files are generated automatically; do not flush all caches.\n"
            "For a deliberate Dev request, create and push a new deploy-dev-<date>-<number> tag.\n"
            "Wait for Deploy site, then check the Dev URL. A green MRN source push is only a signal.\n\n"
            "Live and Both are disabled. Databases, uploads, parent themes and shared plugins are outside this deployment.\n"
            "Do not upload theme changes directly to the server. GitHub CLI is not required.\n\n"
            "Deployment setup verification: pending.\n").encode()


def source_files(site, config, files):
    wrapper = (TOOLS / 'site-deploy.yml.template').read_text().replace('TOOLING_SHA', config['tooling_ref'])
    wrapper = wrapper.replace('SOURCE_PATH', site['source_path']).replace('THEME_SLUG', site['slug']).replace('live_enabled: true', 'live_enabled: false')
    return {**files, '.github/workflows/site-deploy.yml': wrapper.encode(),
            '.github/workflows/site-push.yml': (TOOLS / 'site-push.yml.template').read_bytes(),
            'DEPLOYMENT.md': handoff(site), '.mrn-qa.env': b'MRN_QA_SAMPLE_PATH=/\n', '.gitignore': b'.DS_Store\n.env*\nnode_modules/\nvendor/\n',
            'README.md': ('# ' + site['domain'] + '\n\nSite-owned child-theme source. See DEPLOYMENT.md.\n').encode()}


def source_qa(config, files, destination):
    # Only theme files plus trusted generated top-level files are materialized.
    destination.mkdir(mode=0o700, exist_ok=True)
    for name, content in files.items():
        path = destination / name; path.parent.mkdir(parents=True, exist_ok=True); path.write_bytes(content)
    command(['git', 'init', '--initial-branch=main', str(destination)], text=True)
    command(['git', '-C', str(destination), 'add', '.'], text=True)
    result = subprocess.run([config['qa_bin'], 'run', '--project-root', str(destination), '--run-api', 'static',
        '--run-smoke', 'never', '--run-accessibility', 'never', '--run-performance', 'never', '--run-cwv', 'never',
        '--run-phpcbf', 'never', '--output-file', str(destination.with_suffix('.qa.md'))],
        capture_output=True, timeout=900, env={**site_process_env(), 'HOME': str(destination.parent), 'MRN_QA_PROJECT_KIND': 'generic',
            'MRN_QA_CODE_ANALYSIS_SCOPE': 'all', 'MRN_QA_STACK_ROOT': str(TOOLS.parents[1])})
    destination.with_suffix('.qa.log').write_bytes(result.stdout + result.stderr)
    if result.returncode:
        raise ValueError('Initial child source failed MRN QA; no source was published')


def timestamp():
    return datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')


class Enrollment:
    def __init__(self, config, directory, state, github, secret_values):
        self.config, self.directory, self.state = config, directory, state
        self.github, self.secrets = github, secret_values
        self.site = state['site']

    def persist(self, **values):
        self.state.update(values, updated_at=timestamp())
        save(self.directory / 'state.json', self.state)

    def signal(self, sha):
        runs = [row for row in self.github.runs('site-push.yml', sha)
                if row.get('event') == 'push' and row.get('head_branch') == 'main' and row.get('head_sha') == sha]
        if len(runs) > 1:
            raise ValueError('Ambiguous source signal; inspect enrollment')
        if not runs or runs[0].get('status') != 'completed':
            return None
        if runs[0].get('conclusion') != 'success':
            raise ValueError('Source signal failed')
        return runs[0]

    def tick(self):
        s, site, gh = self.state, self.site, self.github
        stage = s['stage']
        if stage == 'queued':
            files, baseline = snapshot(site)
            files = source_files(site, self.config, files)
            source_qa(self.config, files, self.directory / ('source-' + str(time.time_ns())))
            # Persist immutable input bytes for retries; no resnapshot of changed code.
            save(self.directory / 'source.json', {key: base64.b64encode(value).decode() for key, value in files.items()})
            self.persist(stage='source-accepted', baseline=baseline)
        elif stage == 'source-accepted':
            gh.ensure_repository(s['binding'])
            base = gh.head()
            files = {key: base64.b64decode(value) for key, value in json.loads((self.directory / 'source.json').read_text()).items()}
            sha = gh.make_commit(base, files, 'Initialize site source and shared MRN Dev deployment')
            self.persist(stage='publish-source', base=base, source_sha=sha)
        elif stage == 'publish-source':
            gh.publish(s['base'], s['source_sha'])
            self.persist(stage='prepare-target')
        elif stage == 'prepare-target':
            if gh.head() != s['source_sha'] or snapshot(site)[1] != s['baseline']:
                raise ValueError('Source or Dev changed during enrollment; reconcile before continuing')
            backup = runtime_prepare(site, self.secrets)
            variables = {'DEPLOY_HOST': self.config['ssh_host'], 'DEPLOY_PORT': self.config['ssh_port'],
                'DEPLOY_USER': site['user'], 'DEPLOY_ROOT': site['root'], 'DEPLOY_URL': site['url'],
                'DEPLOY_TEMPLATE': site['template'], 'DEPLOY_STATE_DIR': site['state_dir'],
                'DEPLOY_VERIFY_PAGES': json.dumps([site['url'] + '/']),
                'DEPLOY_TRANSPORT': 'rsync', 'DEPLOY_HOST_PROVIDER': 'cloudpanel', 'DEPLOY_BACKUP_PROVIDER': 'updraft',
                'DEPLOY_BASELINE_TREE': s['baseline'], 'DEPLOY_READY': '0', 'DEPLOY_ENROLLMENT_SOURCE_SHA': s['source_sha']}
            gh.configure(variables, {'DEPLOY_SSH_PRIVATE_KEY': self.secrets['deploy_private_key'],
                'DEPLOY_SSH_KNOWN_HOSTS': self.secrets['known_hosts'], 'MRN_QA_ENGINE_TOKEN': self.secrets['qa_engine_token']})
            self.persist(stage='wait-install-signal', setup_backup=backup)
        elif stage == 'wait-install-signal':
            signal = self.signal(s['source_sha'])
            if signal:
                # Save before dispatch: an unknown HTTP outcome must not cause a second deployment.
                self.persist(stage='qualification-dispatched', install_signal=signal['id'], dispatched_at=timestamp())
                gh.dispatch(s['binding'])
        elif stage == 'qualification-dispatched':
            runs = [row for row in gh.runs('site-deploy.yml', s['source_sha'])
                    if row.get('event') == 'workflow_dispatch' and row.get('display_title') == 'MRN Dev qualification ' + s['binding']]
            if len(runs) > 1:
                raise ValueError('Multiple qualification runs; inspect before retrying')
            if not runs:
                age = datetime.now(timezone.utc) - datetime.fromisoformat(s['dispatched_at'].replace('Z', '+00:00'))
                if age.total_seconds() > 900:
                    raise ValueError('Qualification dispatch outcome is unknown; do not blindly dispatch again')
                return
            proof = gh.proof(runs[0], s['source_sha'], site['url'], site['slug'], qualification=True)
            if proof:
                save(self.directory / 'qualification.json', proof)
                self.persist(stage='arm-dev', qualification_run=proof['run_url'])
        elif stage == 'arm-dev':
            if gh.head() != s['source_sha']:
                raise ValueError('main advanced before arming Dev')
            # Installation signal is known; choose a later second so its delayed callbacks stay ineligible.
            signal = self.signal(s['source_sha'])
            if not signal or timestamp() <= signal['created_at']:
                return
            cutoff = s.get('activation_cutoff') or timestamp()
            self.persist(activation_cutoff=cutoff)
            gh.variable('DEPLOY_ENROLLMENT_SOURCE_SHA', '', 'dev')
            gh.variable('DEPLOY_READY', '1', 'dev')
            gh.variable('MRN_AUTO_DEV_AFTER', cutoff)
            gh.variable('MRN_RELEASE_REQUESTS_AFTER', cutoff)
            self.persist(stage='create-pilot')
        elif stage == 'create-pilot':
            # Later than cutoff; docs-only commit proves ordinary push automation with identical theme bytes.
            if timestamp() <= s['activation_cutoff']:
                return
            content = handoff(site).replace(b'Deployment setup verification: pending.',
                ('First deployment and rollback verified: ' + s['qualification_run'] + '\nAutomation is enabled. Check the latest Deploy site run for release acceptance.').encode())
            sha = gh.make_commit(s['source_sha'], {'DEPLOYMENT.md': content}, 'Verify automatic Dev deployment from an ordinary main push')
            self.persist(stage='publish-pilot', pilot_sha=sha)
        elif stage == 'publish-pilot':
            gh.publish(s['source_sha'], s['pilot_sha'])
            self.persist(stage='verify-push')
        elif stage == 'verify-push':
            signal = self.signal(s['pilot_sha'])
            if not signal:
                return
            runs = [row for row in gh.runs('site-deploy.yml', s['pilot_sha'])
                    if row.get('event') == 'workflow_run' and row.get('created_at', '') >= signal['created_at']]
            if not runs:
                return
            if len(runs) != 1:
                raise ValueError('Ambiguous automatic deployment runs; inspect evidence')
            proof = gh.proof(runs[0], s['pilot_sha'], site['url'], site['slug'], signal_number=signal['run_number'])
            if proof:
                if gh.head() != s['pilot_sha']:
                    raise ValueError('main advanced during push verification')
                save(self.directory / 'automatic-push.json', proof)
                self.persist(stage='grant-access', deployed_sha=s['pilot_sha'], deployment_run=proof['run_url'])
        elif stage == 'grant-access':
            if gh.head() != s['pilot_sha']:
                raise ValueError('main changed before developer handoff')
            gh.grant_teams(self.config['team_slugs'])
            self.persist(stage='ready')
        elif stage != 'ready':
            raise ValueError('Unknown enrollment stage')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config', required=True)
    parser.add_argument('--site-path', required=True)
    parser.add_argument('--enqueue', action='store_true', help='Only after successful new-site bootstrap')
    parser.add_argument('--resume', action='store_true', help='Advance an existing enrollment; never adopt old sites')
    args = parser.parse_args()
    if args.enqueue == args.resume:
        parser.error('Choose enqueue or resume')
    config = load_config(args.config)
    binding = hashlib.sha256(args.site_path.encode()).hexdigest()[:24]
    directory = Path(config['state_root']) / binding
    if not directory.exists() and args.resume:
        print(json.dumps({'status': 'not-enrolled', 'action': 'none'})); return
    directory.mkdir(mode=0o700, exist_ok=True)
    private_path(directory, directory=True)
    lock = directory / 'lock'
    if lock.is_symlink():
        raise ValueError('Symlinked enrollment lock')
    with lock.open('a') as stream:
        try:
            fcntl.flock(stream, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            print(json.dumps({'status': 'running'})); return
        state_path = directory / 'state.json'
        site = identify(args.site_path, config)
        if state_path.exists():
            state = json.loads(private_path(state_path).read_text())
            if state['site'] != site or state['tooling_ref'] != config['tooling_ref']:
                raise ValueError('Enrollment identity or tooling changed; operator review required')
        else:
            state = {'schema': 1, 'binding': binding, 'site': site, 'tooling_ref': config['tooling_ref'],
                     'stage': 'queued', 'created_at': timestamp()}
            save(state_path, state)
        if args.resume and state['stage'] != 'ready':
            secrets = credentials(config, site)
            enrollment = Enrollment(config, directory, state, GitHub(secrets['github_token'], site['repository']), secrets)
            try:
                enrollment.tick()
                state.pop('last_error', None)
                save(state_path, state)
            except (ValueError, RuntimeError) as error:
                # Messages are generated by this controller, never provider/subprocess response bodies.
                enrollment.persist(last_error=str(error))
                raise
        print(json.dumps({'status': state['stage'], 'repository': 'https://github.com/' + site['repository'],
                          'dev_url': site['url'], 'source_sha': state.get('deployed_sha'),
                          'evidence': str(directory)}))


if __name__ == '__main__':
    try:
        main()
    except (ValueError, RuntimeError, OSError, KeyError, subprocess.TimeoutExpired) as error:
        if isinstance(error, (ValueError, RuntimeError)):
            print('Dev enrollment stopped: ' + str(error), file=sys.stderr)
        else:
            print('Dev enrollment stopped: configuration, identity or process unavailable; inspect operator state', file=sys.stderr)
        sys.exit(1)

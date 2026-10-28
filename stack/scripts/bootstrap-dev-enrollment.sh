#!/usr/bin/env bash
# Thin bootstrap adapter; shared tooling owns GitHub and deployment logic.
set -euo pipefail
config="${MRN_DEV_ENROLLMENT_CONFIG:-/etc/mrn/dev-enrollment.json}"
runner="${MRN_DEV_ENROLLMENT_RUNNER:-/opt/mrn-site-deploy/MRN/tools/site-deploy/enroll_dev.py}"
python="${MRN_DEV_ENROLLMENT_PYTHON:-/opt/mrn-site-deploy/venv/bin/python3}"
queue="${MRN_DEV_ENROLLMENT_QUEUE:-/var/lib/mrn-dev-enrollment-queue}"
if [[ ! -f "$config" ]]; then
  echo 'Dev deployment enrollment is not configured on this host; WordPress bootstrap is independent.'
  exit 0
fi
# Record intent before depending on the controller/credentials. Site users cannot
# forge this queue, and a scanner retry never enrolls a previously unqueued site.
# Resolve command substitutions before attaching the Python heredoc. On macOS,
# Bash can block writing a large inherited heredoc into the substitution pipe.
scripts="$(dirname "${BASH_SOURCE[0]}")"
python3 - "$queue" "$config" "$runner" "$python" "$scripts" "$@" <<'PY'
import hashlib, json, os, pathlib, subprocess, sys
queue, config, runner, python, scripts, mode, flag, site = sys.argv[1:]
sys.path.insert(0, scripts)
from bootstrap_readiness import update
settings = json.loads(pathlib.Path(config).read_text())
status_root = settings.get('onboarding_state_root', str(pathlib.Path(scripts).parent / 'runtime/onboarding'))
notifications = settings.get('notifications', {})
def status(stage, outcome, detail='', notify=False):
    return update(status_root, site, stage, outcome, detail=detail, notify=notify, config=notifications)
if mode not in ('--enqueue', '--resume') or flag != '--site-path':
    raise SystemExit('Invalid enrollment adapter arguments')
root = pathlib.Path(queue)
root.mkdir(mode=0o700, parents=True, exist_ok=True)
info = root.stat()
if root.is_symlink() or info.st_uid != os.geteuid() or info.st_mode & 0o077:
    raise SystemExit('Enrollment queue must be operator-owned and private')
key = hashlib.sha256(site.encode()).hexdigest()
record = root / (key + '.json')
if record.is_symlink():
    raise SystemExit('Invalid queue entry')
if mode == '--enqueue' and not record.exists():
    fd = os.open(record, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'w') as output:
        json.dump({'site_path': site}, output)
        output.flush(); os.fsync(output.fileno())
if not record.exists():
    print('Dev enrollment: this existing site is not enrolled; no action.')
    raise SystemExit(0)
if record.stat().st_uid != os.geteuid() or record.stat().st_mode & 0o077 or json.loads(record.read_text()) != {'site_path': site}:
    raise SystemExit('Queue ownership or site identity mismatch')
if not pathlib.Path(runner).is_file() or not os.access(python, os.X_OK):
    status('enrollment-controller', 'failed', 'Shared runner is not installed.', True)
    raise SystemExit('Dev deployment enrollment is pending: the shared runner is not installed.')
# Only queued sites reach this gate. Certificate writes use their own verified
# backup; an already matching origin certificate is a read-only operation.
certificate = settings.get('certificate_command')
enrollment_path = pathlib.Path(settings.get('state_root', '/var/lib/mrn-site-enrollment')) / key[:24] / 'state.json'
already_ready = enrollment_path.is_file() and json.loads(enrollment_path.read_text()).get('stage') == 'ready'
if certificate and not already_ready:
    result = subprocess.run([*certificate, '--site-path', site], capture_output=True, text=True)
    if result.returncode:
        status('origin-certificate', 'failed', 'Targeted certificate verification failed; inspect /var/lib/mrn-cert-sync.', True)
        raise SystemExit(1)
    status('origin-certificate', 'verified')
for operation in ('--enqueue', '--resume') if mode == '--resume' else ('--enqueue',):
    before_status = pathlib.Path(status_root) / (key + '.json')
    before_time = before_status.stat().st_mtime_ns if before_status.exists() else None
    result = subprocess.run([python, runner, '--config', config, operation, '--site-path', site])
    if result.returncode:
        if not before_status.exists() or before_status.stat().st_mtime_ns == before_time:
            status('deployment-enrollment', 'failed', 'Enrollment failed; inspect the retained exact-site state and GitHub run.', True)
        raise SystemExit(result.returncode)
PY

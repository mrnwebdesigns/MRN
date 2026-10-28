#!/usr/bin/env python3
"""Private per-site onboarding outcomes and deduplicated notification receipts."""
import argparse
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request


def save(path, value):
    fd, name = tempfile.mkstemp(prefix='.onboarding-', dir=path.parent)
    try:
        with os.fdopen(fd, 'w') as output:
            json.dump(value, output, indent=2, sort_keys=True)
            output.write('\n'); output.flush(); os.fsync(output.fileno())
        os.replace(name, path)
    finally:
        if os.path.exists(name):
            os.unlink(name)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def slack(url, title, body):
    parsed = urllib.parse.urlsplit(url)
    if (parsed.scheme != 'https' or parsed.netloc not in ('hooks.slack.com', 'hooks.slack-gov.com')
            or not parsed.path.startswith('/services/') or parsed.query or parsed.fragment):
        return {'status': 'failed', 'reason': 'invalid_webhook_destination'}
    payload = json.dumps({'text': title + '\n' + body}).encode()
    opener = urllib.request.build_opener(NoRedirect)
    for attempt in range(3):
        try:
            request = urllib.request.Request(url, data=payload, headers={'Content-Type': 'application/json'})
            with opener.open(request, timeout=15) as response:
                code = response.status
                ack = response.read(1024).strip() == b'ok'
            if code == 200 and ack:
                return {'status': 'delivered', 'http_status': code, 'acknowledged': True}
            return {'status': 'failed', 'http_status': code, 'acknowledged': False}
        except urllib.error.HTTPError as error:
            code = error.code
            # Only a known rejection can be retried. Never persist the webhook/body.
            if code not in (429, 500, 502, 503, 504) or attempt == 2:
                return {'status': 'failed', 'http_status': code}
            delay = error.headers.get('Retry-After', '')
            if delay.isdigit() and int(delay) > 10:
                return {'status': 'failed', 'http_status': code, 'reason': 'retry_after_exceeds_bound'}
            time.sleep(int(delay) if delay.isdigit() else attempt + 1)
        except (OSError, urllib.error.URLError, TimeoutError):
            # The server may have accepted the message before transport failed.
            return {'status': 'unknown', 'reason': 'transport_outcome_requires_inspection'}


def email(recipient, title, body):
    if not re.fullmatch(r'[A-Za-z0-9_.+@-]+', recipient) or '@' not in recipient:
        return {'status': 'failed', 'reason': 'invalid_email_recipient'}
    try:
        if shutil.which('sendmail'):
            data = ('To: ' + recipient + '\nSubject: ' + title.replace('\n', ' ') + '\n\n' + body).encode()
            result = subprocess.run(['sendmail', '-t'], input=data, capture_output=True, timeout=20)
        elif shutil.which('mail'):
            result = subprocess.run(['mail', '-s', title, recipient], input=body.encode(), capture_output=True, timeout=20)
        else:
            return {'status': 'failed', 'reason': 'mail_transport_missing'}
        return {'status': 'accepted_by_local_transport' if result.returncode == 0 else 'failed', 'exit_code': result.returncode}
    except (OSError, subprocess.TimeoutExpired):
        return {'status': 'unknown', 'reason': 'mail_transport_outcome_requires_inspection'}


def update(root, site, stage, status, *, detail='', run_url='', notify=False, config=None):
    """A failed notification survives scanner exit. Successful events send once."""
    root = Path(root)
    root.mkdir(parents=True, mode=0o700, exist_ok=True)
    if root.is_symlink() or root.stat().st_uid != os.geteuid() or root.stat().st_mode & 0o077:
        raise ValueError('Onboarding status must be private and operator-owned')
    key = hashlib.sha256(site.encode()).hexdigest()
    path, lock = root / (key + '.json'), root / (key + '.lock')
    if path.is_symlink() or lock.is_symlink():
        raise ValueError('Symlinked onboarding state')
    with lock.open('a') as stream:
        os.chmod(lock, 0o600); fcntl.flock(stream, fcntl.LOCK_EX)
        state = json.loads(path.read_text()) if path.exists() else {'site_path': site, 'events': {}}
        if state['site_path'] != site:
            raise ValueError('Onboarding site identity differs')
        state.update(stage=stage, status=status, detail=detail, run_url=run_url, updated_at=int(time.time()))
        state.setdefault('stages', {})[stage] = {'status': status, 'detail': detail, 'updated_at': state['updated_at']}
        save(path, state)
        if notify:
            config = config or {}
            event = hashlib.sha256(json.dumps([stage, status, detail, run_url]).encode()).hexdigest()
            delivery = state['events'].setdefault(event, {'stage': stage, 'status': status, 'channels': {}})
            domain = Path(site).name
            title = 'MRN Dev ready: ' + domain if status == 'ready' else 'MRN onboarding ' + status + ': ' + domain
            body = f'Site: https://{domain}/\nStage: {stage}\n{detail}\n{run_url}\nScanner cadence: minutes 23 and 53 each hour.'
            webhook = config.get('slack_webhook', '')
            webhook_file = config.get('slack_webhook_file', '')
            if not webhook and webhook_file and Path(webhook_file).is_file():
                webhook = Path(webhook_file).read_text().strip()
            destinations = {'slack': webhook, 'email': config.get('email', '')}
            for channel, destination in destinations.items():
                previous = delivery['channels'].get(channel, {})
                if previous.get('status') in ('delivered', 'accepted_by_local_transport', 'unknown') or previous.get('attempts', 0) >= 3:
                    continue
                if not destination:
                    delivery['channels'][channel] = {'status': 'unconfigured'}
                    continue
                # Persist unknown before sending, so a crash cannot blindly duplicate.
                pending = {'status': 'unknown', 'attempts': previous.get('attempts', 0) + 1}
                delivery['channels'][channel] = pending; save(path, state)
                result = slack(destination, title, body) if channel == 'slack' else email(destination, title, body)
                delivery['channels'][channel] = {**pending, **result}; save(path, state)
            state['notification_failed'] = any(x.get('status') in ('failed', 'unknown') for x in delivery['channels'].values())
            save(path, state)
        return state


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--site-path', required=True)
    parser.add_argument('--stage', required=True)
    parser.add_argument('--status', required=True)
    parser.add_argument('--notify', action='store_true')
    args = parser.parse_args()
    stack = Path(__file__).resolve().parents[1]
    result = update(os.environ.get('MRN_ONBOARDING_STATE_ROOT', str(stack / 'runtime/onboarding')), args.site_path,
                    args.stage, args.status, detail=sys.stdin.read(4096), notify=args.notify,
                    config={'slack_webhook': os.environ.get('STACK_SLACK_WEBHOOK_URL', ''),
                            'slack_webhook_file': os.environ.get('STACK_SLACK_WEBHOOK_URL_FILE', str(stack / 'secrets/slack-webhook-url.txt')),
                            'email': os.environ.get('STACK_NOTIFY_EMAIL', 'wordpress_admin@mrnwebdesigns.com')})
    print(json.dumps({k: result.get(k) for k in ('stage', 'status', 'notification_failed')}))

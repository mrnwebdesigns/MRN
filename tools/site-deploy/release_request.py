"""Authenticate explicit release tags without executing source-controlled code.

The signal's run-name is produced by GitHub from the push envelope. It is only
trusted after byte-for-byte verification of that workflow at the triggering
commit against our pinned, no-secret template. Commit messages are never commands.
"""
import json
from pathlib import Path
import re
import subprocess
import urllib.parse
import urllib.request
from datetime import datetime, timezone

PREFIX = 'MRN request '
TAG = r'deploy-(dev|live|both)-[A-Za-z0-9][A-Za-z0-9._-]{0,63}'
SHA = r'[0-9a-f]{40}'


def after(created, cutoff):
    if not cutoff:
        return False
    def timestamp(value):
        if not re.fullmatch(r'\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z', value):
            raise ValueError('Release cutoff and signal time must be UTC timestamps')
        return datetime.strptime(value, '%Y-%m-%dT%H:%M:%SZ').replace(tzinfo=timezone.utc)
    return timestamp(created) > timestamp(cutoff)


def api(environ, path):
    token = environ.get('GH_TOKEN') or environ.get('GITHUB_TOKEN')
    if not token:
        raise ValueError('Release verification requires the read-only GitHub token')
    repository = environ.get('GITHUB_REPOSITORY', '')
    if not re.fullmatch(r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', repository):
        raise ValueError('Invalid release repository')
    req = urllib.request.Request('https://api.github.com/repos/' + repository + '/' + path,
        headers={'Authorization': 'Bearer ' + token, 'Accept': 'application/vnd.github+json'})
    with urllib.request.urlopen(req, timeout=30) as response:
        return json.load(response)


def tag_identity(environ, tag):
    if not re.fullmatch(TAG, tag):
        raise ValueError('Invalid release tag')
    obj = api(environ, 'git/ref/tags/' + urllib.parse.quote(tag, safe=''))['object']
    original = obj['sha']
    for _ in range(8):
        if not re.fullmatch(SHA, obj.get('sha', '')):
            break
        if obj.get('type') == 'commit':
            return original, obj['sha']
        if obj.get('type') != 'tag':
            break
        obj = api(environ, 'git/tags/' + obj['sha'])['object']
    raise ValueError('Release tag does not resolve to a commit')


def current(environ, request):
    obj, commit = tag_identity(environ, request['tag'])
    if obj != request['tag_object'] or commit != request['source_sha']:
        raise ValueError('Release tag changed after selection')
    # All tag releases include the current approved main baseline. This prevents
    # a stale feature preview from silently removing a hotfix already in main.
    main = api(environ, 'git/ref/heads/main')['object']['sha']
    if request['target'] in ('live', 'both'):
        if commit != main:
            raise ValueError('Live/Both require the current main commit; release was superseded')
    else:
        comparison = api(environ, 'compare/' + main + '...' + commit)
        if comparison.get('status') not in ('ahead', 'identical'):
            raise ValueError('Dev source must include current main; merge main before releasing')


def signal(environ, run):
    """Called only after deployment_request authenticates the workflow_run envelope."""
    title = run.get('display_title', '')
    if not title.startswith(PREFIX):
        if environ.get('RELEASE_REQUESTS_AFTER'):
            raise ValueError('Install the current source-push template before enabling release tags')
        return None  # Older consumers retain their branch-only protocol.
    match = re.fullmatch(re.escape(PREFIX) + r'(refs/(?:heads|tags)/\S+) (' + SHA + r') (true|false) (true|false)', title)
    if not match or match[2] != run['head_sha'] or match[4] != 'false':
        raise ValueError('Invalid release signal identity')
    branch = environ.get('AUTO_DEV_BRANCH', 'main')
    expected = (Path(__file__).with_name('site-push.yml.template').read_bytes()
                .replace(b'branches: [main]', ('branches: [' + branch + ']').encode()))
    actual = subprocess.check_output(['git', 'show', run['head_sha'] + ':.github/workflows/site-push.yml'])
    if actual != expected:
        raise ValueError('Source signal differs from the pinned no-secret workflow template')
    ref = match[1]
    if ref.startswith('refs/heads/'):
        if ref != 'refs/heads/' + branch:
            raise ValueError('Unapproved source branch signal')
        return {'kind': 'branch', 'source_sha': match[2], 'source_branch': branch, 'target': 'dev'}
    tag = ref.removeprefix('refs/tags/')
    target = re.fullmatch(TAG, tag)
    if not target or match[3] != 'true':
        raise ValueError('Use a new, uniquely named deploy-dev/live/both release tag')
    if not after(run.get('created_at', ''), environ.get('RELEASE_REQUESTS_AFTER', '')):
        return {'kind': 'disabled'}
    target = target[1]
    if target != 'dev' and environ.get('LIVE_ENABLED', 'true') != 'true':
        raise ValueError('This site is Dev-only; Live/Both are unavailable')
    if environ.get('DEV_MAIN_ENABLED', 'true') != 'true' and target in ('dev', 'both'):
        raise ValueError('This site protects a phase preview; use its configured branch/manual Dev workflow')
    obj, commit = tag_identity(environ, tag)
    if commit != match[2]:
        raise ValueError('Release tag no longer identifies the triggering source commit')
    request = {'kind':'tag', 'tag':tag, 'tag_object':obj, 'source_sha':commit,
               'source_branch':'main' if target != 'dev' else tag, 'target':target}
    current(environ, request)
    return request


def validate_intent(intent, sha):
    if (not isinstance(intent, dict) or intent.get('kind') != 'tag'
            or not re.fullmatch(TAG, intent.get('tag', ''))
            or not re.fullmatch(SHA, intent.get('tag_object', ''))
            or intent.get('source_sha') != sha
            or intent.get('target') != re.fullmatch(TAG, intent['tag'])[1]):
        raise ValueError('Invalid explicit release intent')

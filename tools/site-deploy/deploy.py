#!/usr/bin/env python3
"""Deploy one reviewed site-owned child theme. Preflight is read-only."""
import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import shlex
import subprocess
import sys
import tempfile
import time
import urllib.request
import urllib.error

EXCLUDED = {"tests", "docs", "scripts", "qa", "node_modules", "vendor", "AGENTS.md", "README.md",
            "composer.json", "composer.lock", "package.json", "package-lock.json", "phpunit.xml.dist"}


def check(value, pattern, name):
    if not re.fullmatch(pattern, value):
        raise ValueError(f"Invalid {name}")
    return value


def allowed(name):
    parts = PurePosixPath(name).parts
    return bool(parts) and not any(p.startswith('.') for p in parts) and parts[0] not in EXCLUDED


def digest(files):
    return hashlib.sha256(json.dumps(files, sort_keys=True, separators=(',', ':'), ensure_ascii=False).encode()).hexdigest()


def run(args, **kwargs):
    result = subprocess.run(args, capture_output=True, **kwargs)
    if result.returncode:
        # Remote output can contain plugin diagnostics or connection details.
        # Report only recognized transport categories, never raw stderr or argv.
        if args[0] == 'ssh':
            error = result.stderr
            if isinstance(error, bytes):
                error = error.decode('utf-8', errors='replace')
            categories = (
                ('Host key verification failed', 'pinned host key was rejected'),
                ('REMOTE HOST IDENTIFICATION HAS CHANGED', 'pinned host key changed'),
                ('error in libcrypto', 'deployment private key could not be parsed'),
                ('invalid format', 'deployment private key has an invalid format'),
                ('Permission denied', 'site-owner authentication was refused'),
                ('Connection timed out', 'connection timed out'),
                ('Connection refused', 'connection was refused'),
                ('Connection closed', 'connection was closed by the remote endpoint'),
            )
            for marker, explanation in categories:
                if marker in error:
                    raise RuntimeError('SSH preflight failed: ' + explanation)
        raise RuntimeError(f"Command failed: {args[0]} (exit {result.returncode}); inspect privately")
    return result.stdout


def wpengine_private(c):
    """Recognize only WP Engine's documented persistent, HTTP-blocked storage."""
    return (c['host'] == c.get('user', '') + '.ssh.wpengine.net'
            and c['root'] == '/sites/' + c.get('user', '')
            and c['state_dir'].startswith(c['root'] + '/_wpeprivate/mrn-site-deploy/'))


def config(environ):
    names = ['HOST', 'PORT', 'USER', 'ROOT', 'URL', 'TEMPLATE', 'STATE_DIR', 'TRANSPORT', 'KEY_FILE', 'KNOWN_HOSTS_FILE']
    c = {k.lower(): environ.get('DEPLOY_' + k, '') for k in names}
    c['backup_provider'] = environ.get('DEPLOY_BACKUP_PROVIDER', 'updraft') or 'updraft'
    if c['backup_provider'] not in ['updraft', 'kinsta']:
        raise ValueError('Unsupported backup provider')
    if c['backup_provider'] == 'kinsta':
        # The Kinsta adapter resolves a password in memory after API identity checks.
        c['key_file'] = c['key_file'] or 'native-kinsta'
    if any(not v for v in c.values()):
        raise ValueError('Missing DEPLOY_ configuration: ' + ', '.join(k for k, v in c.items() if not v))
    check(c['host'], r'[A-Za-z0-9][A-Za-z0-9.-]*', 'host')
    check(c['user'], r'[A-Za-z0-9_][A-Za-z0-9_.-]*', 'user')
    check(c['port'], r'[0-9]{1,5}', 'port')
    if not 1 <= int(c['port']) <= 65535:
        raise ValueError('Invalid port')
    check(c['template'], r'[A-Za-z0-9_-]+', 'template')
    for k in ['root', 'state_dir']:
        check(c[k], r'/[A-Za-z0-9_./-]+', k)
        if '..' in PurePosixPath(c[k]).parts or c[k] == '/' or c[k].endswith('/'):
            raise ValueError('Unsafe remote path')
    if (c['state_dir'] == c['root'] or c['state_dir'].startswith(c['root'] + '/')) and not wpengine_private(c):
        raise ValueError('Rollback directory must be outside the WordPress root')
    check(c['url'], r'https://[A-Za-z0-9.-]+(?:/[A-Za-z0-9_/-]*)?', 'URL')
    c['url'] = c['url'].rstrip('/')
    if c['transport'] not in ['rsync', 'git']:
        raise ValueError('Unsupported transport')
    c['baseline'] = environ.get('DEPLOY_BASELINE_TREE', '')
    c['ready'] = environ.get('DEPLOY_READY') == '1'
    c['host_provider'] = environ.get('DEPLOY_HOST_PROVIDER', 'cloudpanel') or 'cloudpanel'
    if c['host_provider'] not in ('cloudpanel', 'nexcess', 'siteground', 'wpengine'):
        raise ValueError('Unknown host provider')
    return c


class Target:
    def __init__(self, c):
        self.c = c
        self.native_backup = None
        self.password = None
        if c.get('backup_provider') == 'kinsta':
            from kinsta import Kinsta
            self.native_backup = Kinsta(os.environ.get('DEPLOY_KINSTA_API_TOKEN', ''),
                                        os.environ.get('DEPLOY_KINSTA_SITE_ID', ''),
                                        os.environ.get('DEPLOY_KINSTA_ENVIRONMENT_ID', ''))
            self.password = self.native_backup.connect(c)
        self.options = ['-i', c['key_file'], '-p', c['port'], '-o', 'IdentitiesOnly=yes',
                        '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
                        '-o', 'UserKnownHostsFile=' + c['known_hosts_file'], '-o', 'ConnectTimeout=20']
        self.login = c['user'] + '@' + c['host']
        if self.password:
            self.options = ['-p', c['port'], '-o', 'PreferredAuthentications=password',
                            '-o', 'PubkeyAuthentication=no', '-o', 'StrictHostKeyChecking=yes',
                            '-o', 'UserKnownHostsFile=' + c['known_hosts_file'], '-o', 'ConnectTimeout=20']

    def shell(self, script):
        if self.password:
            return run(['sshpass', '-e', 'ssh', *self.options, self.login, 'bash -se'], input=script,
                       text=True, env=dict(os.environ, SSHPASS=self.password))
        return run(['ssh', *self.options, self.login, 'bash -se'], input=script, text=True)

    def php(self, code, skip_themes=False):
        options = ['--skip-themes'] if skip_themes else []
        command = shlex.join(['env', 'WP_CLI_PHP_ARGS=-d memory_limit=512M', 'wp', '--path=' + self.c['root'], *options, 'eval', code])
        output = self.shell(command + '\n')
        markers = [line[11:] for line in output.splitlines() if line.startswith('MRN_RESULT=')]
        if len(markers) != 1:
            raise RuntimeError('Missing or ambiguous remote receipt')
        return json.loads(markers[0])

    def inspect(self, slug, skip_themes=False):
        settings = json.dumps({'slug': slug, 'exclude': sorted(EXCLUDED), 'state': self.c['state_dir'],
                               'wpengine_private': wpengine_private(self.c)})
        # Pass JSON as a PHP string literal using base64 to avoid PHP interpolation.
        import base64
        encoded = base64.b64encode(settings.encode()).decode()
        result = self.php('''
$c = json_decode(base64_decode('%s'), true);
$theme = get_stylesheet_directory();
$files = array();
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($theme, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $name = substr($file->getPathname(), strlen($theme) + 1);
    $parts = explode('/', $name);
    if (in_array($parts[0], $c['exclude'], true) || preg_match('~(^|/)\\.~', $name)) { continue; }
    if ($file->isLink() || !$file->isFile()) { WP_CLI::error('Unsupported theme filesystem entry.'); }
    $files[$name] = hash_file('sha256', $file->getPathname());
}
ksort($files, SORT_STRING);
$state = is_file($c['state'] . '/current.json') ? json_decode(file_get_contents($c['state'] . '/current.json'), true) : null;
$state_path = realpath($c['state']);
$root_path = realpath(ABSPATH);
$private = $state_path && $root_path && $state_path !== $root_path && strpos($state_path . '/', $root_path . '/') !== 0;
$wpe_private = $c['wpengine_private'] && $state_path && $root_path && strpos($state_path . '/', $root_path . '/_wpeprivate/mrn-site-deploy/') === 0;
$services = array_values(array_filter((array) get_option('updraft_service', array())));
global $updraftplus;
echo 'MRN_RESULT=' . wp_json_encode(array(
    'home' => untrailingslashit(get_option('home')), 'stylesheet' => get_stylesheet(), 'template' => get_template(),
    'wp_root' => $root_path,
    'theme_url' => set_url_scheme(get_stylesheet_directory_uri(), 'https'),
    'theme' => realpath($theme), 'files' => $files, 'state' => $state,
    'state_ready' => ($private || $wpe_private) && is_writable($c['state']) && (fileperms($c['state']) & 0077) === 0,
    'state_protection_probe_exists' => is_file(ABSPATH . '_wpeprivate/config.json'),
    'writable' => is_writable($theme),
    'backup_ready' => is_object($updraftplus) && is_callable(array($updraftplus, 'backupnow_database')) && !empty($services) && !in_array('none', $services, true),
    'git' => file_exists($theme . '/.git')
));
''' % encoded, skip_themes=skip_themes)
        check(result['theme'], r'/[A-Za-z0-9_./-]+', 'physical theme path')
        result['git_root'] = self.shell(
            'if git -C ' + shlex.quote(result['theme']) +
            ' rev-parse --show-toplevel 2>/dev/null; then :; fi\n'
        ).strip()
        result['git'] = bool(result['git_root'])
        if self.native_backup:
            result['backup_ready'] = True
            result['backup_provider'] = 'kinsta'
        return result


def verify_identity(c, state, slug, require_ready=True):
    if (state['home'], state['stylesheet'], state['template']) != (c['url'], slug, c['template']):
        raise ValueError('WordPress home/stylesheet/template does not match configured target')
    if slug == c['template']:
        raise ValueError('This adapter deploys child themes only')
    check(state['theme'], r'/[A-Za-z0-9_./-]+', 'physical theme path')
    if require_ready and (not state['writable'] or not state['state_ready'] or not state['backup_ready']):
        raise ValueError('Target requires writable theme/private rollback directory and remote Updraft backup readiness')
    if state['git'] != (c['transport'] == 'git'):
        raise ValueError('Transport does not match the existing theme directory; reconcile server layout first')


def export_payload(sha, source, directory):
    check(sha, r'[0-9a-f]{40}', 'commit SHA')
    if source != '.':
        check(source, r'[A-Za-z0-9_/-]+', 'source path')
        if '..' in PurePosixPath(source).parts:
            raise ValueError('Unsafe source path')
    tree = sha if source == '.' else sha + ':' + source
    entries = run(['git', 'ls-tree', '-rz', tree]).split(b'\0')
    files = {}
    for entry in filter(None, entries):
        meta, raw_name = entry.split(b'\t', 1)
        mode, kind, blob = meta.decode().split()
        name = raw_name.decode()
        if not allowed(name):
            continue
        if mode not in ['100644', '100755'] or kind != 'blob' or any(ord(ch) < 32 for ch in name):
            raise ValueError('Unsupported payload entry')
        path = directory / name
        path.parent.mkdir(parents=True, exist_ok=True)
        body = run(['git', 'cat-file', 'blob', blob])
        path.write_bytes(body)
        files[name] = hashlib.sha256(body).hexdigest()
    if not files or 'style.css' not in files:
        raise ValueError('Theme payload is missing style.css')
    return files


def require_baseline(c, before, repository, environment):
    state = before['state']
    if state is not None:
        if any(state.get(k) != value for k, value in {
            'repository': repository, 'environment': environment, 'url': c['url'],
            'host': c['host'], 'root': c['root'], 'template': c['template'],
        }.items()):
            raise ValueError('Previous deployment receipt belongs to another target')
        baseline = state.get('tree', '')
    else:
        baseline = c['baseline']
    if not re.fullmatch(r'[0-9a-f]{64}', baseline) or baseline != digest(before['files']):
        raise ValueError('Remote tree differs from reviewed baseline/last receipt; reconcile before deployment')


def git_destination(c, before, source):
    root = check(before['git_root'], r'/[A-Za-z0-9_./-]+', 'remote Git root')
    expected_theme = root if source == '.' else root + '/' + source
    if expected_theme != before['theme']:
        raise ValueError('Remote Git source layout does not match the reviewed theme source')
    wp_root = before.get('wp_root', c['root'])
    if (root == wp_root or root.startswith(wp_root + '/')) and (source != '.' or root != before['theme'] or root == wp_root):
        raise ValueError('A Git repository inside WordPress must own only this child-theme root')
    return shlex.quote(root)


def require_http_denied(url):
    # Never read response bodies, particularly for provider/Git private files.
    try:
        request = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (compatible; MRN-Deployment/1.0)'})
        with urllib.request.urlopen(request, timeout=30):
            pass
    except urllib.error.HTTPError as error:
        denied = error.code in [403, 404] and error.geturl() == url
        error.close()
        if denied:
            return
    raise ValueError('Private storage HTTP protection is not verified; no deployment allowed')


def verify_state_privacy(c, before):
    if wpengine_private(c):
        if not before.get('state_protection_probe_exists'):
            raise ValueError('Cannot verify protection of an existing WP Engine private file')
        http_check(c['url'] + '/')
        require_http_denied(c['url'] + '/_wpeprivate/config.json')


def verify_git_privacy(c, before):
    root = before['git_root']
    wp_root = before.get('wp_root', c['root'])
    if root != wp_root and not root.startswith(wp_root + '/'):
        return
    theme_url = before['theme_url'].rstrip('/')
    if not theme_url.startswith(c['url'] + '/'):
        raise ValueError('Cannot establish canonical public URL for Git privacy check')
    http_check(c['url'] + '/')
    for name in ['HEAD', 'config']:
        require_http_denied(theme_url + '/.git/' + name)


def http_check(url, rest=False):
    request = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (compatible; MRN-Deployment/1.0)'})
    with urllib.request.urlopen(request, timeout=30) as response:
        if response.status != 200 or response.url.rstrip('/') != url.rstrip('/'):
            raise RuntimeError('Public URL failed exact-target HTTP verification')
        body = response.read(8 * 1024 * 1024)
        if rest:
            data = json.loads(body)
            if not isinstance(data.get('routes'), dict) or not data.get('namespaces'):
                raise RuntimeError('REST discovery response is not valid WordPress discovery')
        elif not body or b'<html' not in body.lower():
            raise RuntimeError('Homepage did not return HTML')


def deploy(args, c):
    repository = check(os.environ['GITHUB_REPOSITORY'], r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', 'repository')
    artifact_path = getattr(args, 'artifact', None)
    artifact_sha256 = getattr(args, 'artifact_sha256', None)
    artifact = None
    if bool(artifact_path) != bool(artifact_sha256):
        raise ValueError('Artifact path and trusted build checksum are required together')
    if artifact_path:
        from verify_release import verify
        # Reject a wrong build before opening a site connection. Never rebuild here.
        artifact = verify(artifact_path, artifact_sha256, args.sha, args.source, args.slug)
    if args.mode == 'deploy' and not artifact:
        raise ValueError('Runtime writes disabled: a verified build artifact is required')
    with tempfile.TemporaryDirectory(prefix='mrn-site-payload-') as temp:
        payload = Path(temp)
        files = artifact['theme_files'] if artifact else export_payload(args.sha, args.source, payload)
        target = Target(c)
        before = target.inspect(args.slug, skip_themes=True)
        verify_identity(c, before, args.slug, require_ready=args.mode != 'preflight')
        verify_state_privacy(c, before)
        if c['transport'] == 'git':
            git_destination(c, before, args.source)
            verify_git_privacy(c, before)
        receipt = {'repository': repository, 'environment': args.environment, 'url': c['url'], 'sha': args.sha,
                   'host': c['host'], 'root': c['root'], 'template': c['template'],
                   'tree': digest(files), 'previous_tree': digest(before['files']), 'status': 'preflight',
                   'backup_provider': c.get('backup_provider', 'updraft'),
                   'readiness': {k: before[k] for k in ['writable', 'state_ready', 'backup_ready']},
                   'changed': sorted(n for n in set(files) | set(before['files']) if files.get(n) != before['files'].get(n))}
        receipt['payload_kind'] = 'verified-build-artifact' if artifact else 'source-adoption-inventory'
        if artifact:
            receipt['artifact'] = {key: value for key, value in artifact.items() if key != 'theme_files'}
        Path(args.receipt).write_text(json.dumps(receipt, indent=2) + '\n')
        if args.mode == 'preflight':
            print(json.dumps(receipt, indent=2))
            return
        if not c['ready']:
            raise ValueError('DEPLOY_READY is not enabled after target qualification')
        if c.get('backup_provider') != 'updraft' or (args.environment == 'live' and c.get('host_provider', 'cloudpanel') == 'cloudpanel'):
            raise ValueError('Runtime writes disabled for Live: its provider adapter is not qualified')
        if not before['state'] or before['state'].get('schema') != 1:
            raise ValueError('Runtime writes disabled: first adoption requires separate host qualification')
        from atomic_runner import run as activate
        from cache_policy import canonical_pages
        pages = canonical_pages(c['url'], json.loads(os.environ.get('DEPLOY_VERIFY_PAGES', '[]')))
        plan = {'repository': repository, 'environment': args.environment, 'slug': args.slug,
                'archive': str(Path(artifact_path).resolve()), 'artifact_sha256': artifact_sha256,
                'source_sha': args.sha, 'source_path': args.source, 'pages': pages,
                'expected_current': before['state'], 'adopt': False}
        result = activate(plan, c)
        Path(args.receipt).write_text(json.dumps(result, indent=2) + '\n')
        if result['status'] != 'public-verified':
            raise RuntimeError('Activation failed; inspect the deployment and recovery receipt')
        print(json.dumps({'status': result['status'], 'current': result['current'],
                          'runtime_qa_required': True}))


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--mode', choices=['preflight', 'deploy'], default='preflight')
    p.add_argument('--environment', choices=['dev', 'live'], required=True)
    p.add_argument('--sha', required=True)
    p.add_argument('--source', required=True)
    p.add_argument('--slug', required=True)
    p.add_argument('--receipt', required=True)
    p.add_argument('--artifact', help='Immutable tar from the trusted build job; no target-side rebuild')
    p.add_argument('--artifact-sha256', help='Expected SHA-256 supplied independently by the build job')
    args = p.parse_args()
    check(args.slug, r'[A-Za-z0-9_-]+', 'stylesheet')
    deploy(args, config(os.environ))


if __name__ == '__main__':
    try:
        main()
    except (ValueError, RuntimeError, OSError, KeyError) as error:
        print(f'Deployment stopped: {error}', file=sys.stderr)
        sys.exit(1)

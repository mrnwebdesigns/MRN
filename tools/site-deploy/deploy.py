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
        raise RuntimeError(f"Command failed: {args[0]} (exit {result.returncode}); inspect privately")
    return result.stdout


def config(environ):
    names = ['HOST', 'PORT', 'USER', 'ROOT', 'URL', 'TEMPLATE', 'STATE_DIR', 'TRANSPORT', 'KEY_FILE', 'KNOWN_HOSTS_FILE']
    c = {k.lower(): environ.get('DEPLOY_' + k, '') for k in names}
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
    if c['state_dir'] == c['root'] or c['state_dir'].startswith(c['root'] + '/'):
        raise ValueError('Rollback directory must be outside the WordPress root')
    check(c['url'], r'https://[A-Za-z0-9.-]+(?:/[A-Za-z0-9_/-]*)?', 'URL')
    c['url'] = c['url'].rstrip('/')
    if c['transport'] not in ['rsync', 'git']:
        raise ValueError('Unsupported transport')
    c['baseline'] = environ.get('DEPLOY_BASELINE_TREE', '')
    c['ready'] = environ.get('DEPLOY_READY') == '1'
    return c


class Target:
    def __init__(self, c):
        self.c = c
        self.options = ['-i', c['key_file'], '-p', c['port'], '-o', 'IdentitiesOnly=yes',
                        '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
                        '-o', 'UserKnownHostsFile=' + c['known_hosts_file'], '-o', 'ConnectTimeout=20']
        self.login = c['user'] + '@' + c['host']

    def shell(self, script):
        return run(['ssh', *self.options, self.login, 'bash -se'], input=script, text=True)

    def php(self, code):
        command = shlex.join(['wp', '--path=' + self.c['root'], 'eval', code])
        output = self.shell(command + '\n')
        markers = [line[11:] for line in output.splitlines() if line.startswith('MRN_RESULT=')]
        if len(markers) != 1:
            raise RuntimeError('Missing or ambiguous remote receipt')
        return json.loads(markers[0])

    def inspect(self, slug):
        settings = json.dumps({'slug': slug, 'exclude': sorted(EXCLUDED), 'state': self.c['state_dir']})
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
$services = array_values(array_filter((array) get_option('updraft_service', array())));
global $updraftplus;
echo 'MRN_RESULT=' . wp_json_encode(array(
    'home' => untrailingslashit(get_option('home')), 'stylesheet' => get_stylesheet(), 'template' => get_template(),
    'wp_root' => $root_path,
    'theme' => realpath($theme), 'files' => $files, 'state' => $state,
    'state_ready' => $private && is_writable($c['state']) && (fileperms($c['state']) & 0077) === 0,
    'writable' => is_writable($theme),
    'backup_ready' => is_object($updraftplus) && is_callable(array($updraftplus, 'backupnow_database')) && !empty($services) && !in_array('none', $services, true),
    'git' => file_exists($theme . '/.git')
));
''' % encoded)
        check(result['theme'], r'/[A-Za-z0-9_./-]+', 'physical theme path')
        result['git_root'] = self.shell(
            'if git -C ' + shlex.quote(result['theme']) +
            ' rev-parse --show-toplevel 2>/dev/null; then :; fi\n'
        ).strip()
        result['git'] = bool(result['git_root'])
        return result


def verify_identity(c, state, slug):
    if (state['home'], state['stylesheet'], state['template']) != (c['url'], slug, c['template']):
        raise ValueError('WordPress home/stylesheet/template does not match configured target')
    if slug == c['template']:
        raise ValueError('This adapter deploys child themes only')
    check(state['theme'], r'/[A-Za-z0-9_./-]+', 'physical theme path')
    if not state['writable'] or not state['state_ready'] or not state['backup_ready']:
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
    if root == wp_root or root.startswith(wp_root + '/'):
        raise ValueError('Deploy repository must be private, outside the WordPress document root')
    return shlex.quote(root)


def http_check(url, rest=False):
    request = urllib.request.Request(url, headers={'User-Agent': 'MRN-Deployment-Verification/1.0'})
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
    target = Target(c)
    repository = check(os.environ['GITHUB_REPOSITORY'], r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', 'repository')
    with tempfile.TemporaryDirectory(prefix='mrn-site-payload-') as temp:
        payload = Path(temp)
        files = export_payload(args.sha, args.source, payload)
        before = target.inspect(args.slug)
        verify_identity(c, before, args.slug)
        if c['transport'] == 'git':
            git_destination(c, before, args.source)
        receipt = {'repository': repository, 'environment': args.environment, 'url': c['url'], 'sha': args.sha,
                   'host': c['host'], 'root': c['root'], 'template': c['template'],
                   'tree': digest(files), 'previous_tree': digest(before['files']), 'status': 'preflight',
                   'changed': sorted(n for n in set(files) | set(before['files']) if files.get(n) != before['files'].get(n))}
        Path(args.receipt).write_text(json.dumps(receipt, indent=2) + '\n')
        if args.mode == 'preflight':
            print(json.dumps(receipt, indent=2))
            return
        if not c['ready']:
            raise ValueError('DEPLOY_READY is not enabled after target qualification')
        require_baseline(c, before, repository, args.environment)
        theme = shlex.quote(before['theme'])
        if c['transport'] == 'git':
            git_root = git_destination(c, before, args.source)
            target.shell(f'cd {git_root}\ntest "$(git rev-parse --show-toplevel)" = "$PWD"\ntest -z "$(git status --porcelain)"\ngit fetch origin {args.sha}\n')
        stamp = str(time.time_ns())
        label = f'pre-{args.environment}-{args.sha[:10]}-{stamp[-12:]}'
        backup_code = Path(__file__).with_name('backup.php').read_text().removeprefix('<?php')
        backup = target.php("putenv('MRN_BACKUP_LABEL=" + label + "');\n" + backup_code)
        if backup.get('valid') is not True or backup.get('label') != label:
            raise RuntimeError('Backup verification failed; no theme write performed')
        # Recheck drift after the backup, immediately before preserving/writing code.
        fresh = target.inspect(args.slug)
        verify_identity(c, fresh, args.slug)
        require_baseline(c, fresh, repository, args.environment)
        if digest(fresh['files']) != receipt['previous_tree']:
            raise ValueError('Theme changed during backup; refusing deployment')
        archive = c['state_dir'] + '/' + args.sha[:12] + '-' + stamp + '.tar.gz'
        receipt.update(backup=backup, rollback_archive=archive, status='write-started')
        Path(args.receipt).write_text(json.dumps(receipt, indent=2) + '\n')
        target.shell(f'umask 077\ntar --exclude=.git -czf {shlex.quote(archive)} -C {theme} .\ntar -tzf {shlex.quote(archive)} >/dev/null\n')
        if c['transport'] == 'git':
            if git_destination(c, fresh, args.source) != git_root:
                raise ValueError('Remote Git root changed during backup')
            target.shell(f'cd {git_root}\ntest -z "$(git status --porcelain)"\ngit checkout --detach {args.sha}\n')
        else:
            excludes = ['--exclude=.*'] + ['--exclude=/' + n for n in sorted(EXCLUDED)]
            run(['rsync', '-az', '--checksum', '--delete', '--delay-updates', *excludes,
                 '-e', shlex.join(['ssh', *target.options]), str(payload) + '/',
                 target.login + ':' + before['theme'] + '/'])
        root = shlex.quote(c['root'])
        target.shell(f'wp --path={root} cache flush\nwp --path={root} transient delete --all\n')
        after = target.inspect(args.slug)
        verify_identity(c, after, args.slug)
        if after['files'] != files:
            raise RuntimeError('Installed file/hash inventory does not match the immutable payload')
        http_check(c['url'] + '/')
        http_check(c['url'] + '/wp-json/', rest=True)
        receipt['status'] = 'verified'
        receipt['verified_at'] = time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
        body = json.dumps(receipt, indent=2) + '\n'
        state_path = shlex.quote(c['state_dir'] + '/current.json')
        target.shell('umask 077\nprintf %s ' + shlex.quote(body) + ' > ' + state_path + '.tmp\nmv ' + state_path + '.tmp ' + state_path + '\n')
        Path(args.receipt).write_text(body)
        print(body)


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--mode', choices=['preflight', 'deploy'], default='preflight')
    p.add_argument('--environment', choices=['dev', 'live'], required=True)
    p.add_argument('--sha', required=True)
    p.add_argument('--source', required=True)
    p.add_argument('--slug', required=True)
    p.add_argument('--receipt', required=True)
    args = p.parse_args()
    check(args.slug, r'[A-Za-z0-9_-]+', 'stylesheet')
    deploy(args, config(os.environ))


if __name__ == '__main__':
    try:
        main()
    except (ValueError, RuntimeError, OSError, KeyError) as error:
        print(f'Deployment stopped: {error}', file=sys.stderr)
        sys.exit(1)

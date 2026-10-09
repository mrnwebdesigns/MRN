"""Disposable native WordPress qualification; loopback, no WooCommerce or delivery."""
from __future__ import annotations

import contextlib
import json
import os
from pathlib import Path, PurePosixPath
import secrets
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.request
import zipfile

from common import ReleaseError, file_hash, read, write

CORE_SHA = '8fc96c59a78b7219e4a130222b7fadb51b03e503e8b0123beaa7e28961c21ce2'


def extract(archive, destination, *, expected=None, prefix=None):
    archive, destination = Path(archive), Path(destination)
    if expected and file_hash(archive) != expected:
        raise ReleaseError('Pinned fixture archive checksum differs')
    seen = set()
    with zipfile.ZipFile(archive) as package:
        if len(package.infolist()) > 30000 or sum(v.file_size for v in package.infolist()) > 512 * 1024**2:
            raise ReleaseError('Fixture archive exceeds limits')
        for member in package.infolist():
            path = PurePosixPath(member.filename)
            mode = member.external_attr >> 16
            if (path.is_absolute() or '..' in path.parts or member.filename in seen
                    or mode & 0o170000 == 0o120000
                    or (prefix and path.parts[0] != prefix)):
                raise ReleaseError('Unsafe fixture archive')
            seen.add(member.filename)
            target = destination / path
            if member.is_dir():
                target.mkdir(parents=True, exist_ok=True)
            else:
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(package.read(member))


class WordPressFixture:
    def __init__(self, bootstrap, root, settings):
        self.bootstrap, self.root, self.settings = Path(bootstrap), Path(root), settings
        self.public = self.root / 'wordpress'
        self.content = self.public / 'wp-content'
        self.process = None
        self.log = None
        self.database = None
        self.database_root = None
        self.database_log = None
        self.password = secrets.token_urlsafe(32)
        with socket.socket() as probe:
            probe.bind(('127.0.0.1', 0))
            self.port = probe.getsockname()[1]
        self.url = 'http://127.0.0.1:' + str(self.port)

    def start_database(self):
        # A short, private path keeps the Unix socket below its platform limit.
        # No defaults, TCP listener, global service or shared database is used.
        self.database_root = tempfile.TemporaryDirectory(prefix='mrn-fleet-db-')
        private = Path(self.database_root.name)
        self.database_socket = private / 'mysql.sock'
        self.database_log = (self.root / 'mysql-process.log').open('w')
        server = [self.settings['mysql_server'], '--no-defaults',
                  '--datadir=' + str(private / 'data')]
        initialization = subprocess.run(server + ['--initialize-insecure'],
                                        stdout=self.database_log, stderr=self.database_log,
                                        timeout=180, check=False, cwd=private)
        if initialization.returncode:
            raise ReleaseError('Private fixture database initialization failed')
        self.database = subprocess.Popen(server + [
            '--socket=' + str(self.database_socket), '--skip-networking', '--mysqlx=OFF',
            '--pid-file=' + str(private / 'mysql.pid'),
            '--log-error=' + str(self.root / 'mysql-server.log'),
            '--performance-schema=OFF', '--max-connections=10',
        ], stdout=self.database_log, stderr=self.database_log, cwd=private)
        client = [self.settings['mysql_client'], '--no-defaults',
                  '--socket=' + str(self.database_socket), '--user=root',
                  '--batch', '--skip-column-names']
        for _ in range(100):
            if self.database.poll() is not None:
                raise ReleaseError('Private fixture database exited; see mysql-server.log')
            result = subprocess.run(client, input='SELECT 1;\n', capture_output=True,
                                    text=True, timeout=5, check=False, cwd=private)
            if result.returncode == 0 and result.stdout.strip() == '1':
                break
            time.sleep(0.1)
        else:
            raise ReleaseError('Private fixture database did not become ready')
        result = subprocess.run(client, input=(
            'CREATE DATABASE mrn_fleet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n'),
            stdout=self.database_log, stderr=self.database_log, text=True,
            timeout=10, check=False, cwd=private)
        if result.returncode:
            raise ReleaseError('Private fixture database creation failed')

    def php(self, body, *, installing=False, skip_smtp=False):
        if installing:
            program = ("<?php\n$_SERVER['SERVER_NAME']='127.0.0.1';"
                       + "$_SERVER['HTTP_HOST']='127.0.0.1:" + str(self.port) + "';"
                       + "$_SERVER['REQUEST_URI']='/';$_SERVER['SERVER_PROTOCOL']='HTTP/1.1';"
                       + "define('WP_INSTALLING', true);\n"
                       + 'require ' + repr(str(self.public / 'wp-load.php')) + ';\n' + body)
            command = [self.settings['php']]
            stdin = program
        else:
            command = [self.settings['php'], self.settings['wp_cli'],
                       '--path=' + str(self.public), 'eval', body]
            if skip_smtp:
                command += ['--skip-plugins=post-smtp']
            stdin = None
        result = subprocess.run(command, input=stdin, capture_output=True, text=True,
                                cwd=self.public, timeout=180, check=False)
        with (self.root / 'native.log').open('a') as output:
            output.write(result.stdout + '\n' + result.stderr + '\n')
        if result.returncode:
            raise ReleaseError('Native fixture WordPress command failed; see private native.log')
        try:
            return json.loads(result.stdout)
        except json.JSONDecodeError as error:
            raise ReleaseError('Native fixture returned incomplete/non-JSON output') from error

    def start(self):
        self.root.mkdir()
        extract(self.settings['wordpress_archive'], self.root, expected=CORE_SHA, prefix='wordpress')
        self.start_database()
        (self.public / 'wp-config.php').write_text("<?php\n" + "\n".join([
            "define('DB_NAME', 'mrn_fleet');", "define('DB_USER', 'root');",
            "define('DB_PASSWORD', '');", "define('DB_CHARSET', 'utf8mb4');",
            "define('DB_HOST', " + repr('localhost:' + str(self.database_socket)) + ');',
            "define('WP_HTTP_BLOCK_EXTERNAL', true);", "define('DISABLE_WP_CRON', true);",
            "define('WP_AUTO_UPDATE_CORE', false);", "define('AUTOMATIC_UPDATER_DISABLED', true);",
            "define('WP_ENVIRONMENT_TYPE', 'local');", "define('MRN_SITE_PROFILE', 'stack');",
            "define('WP_DEBUG', true);", "define('WP_DEBUG_DISPLAY', false);",
            "define('WP_DEBUG_LOG', __DIR__.'/wp-content/debug.log');",
            "define('FS_METHOD', 'direct');", "define('WP_HOME', " + repr(self.url) + ');',
            "define('WP_SITEURL', " + repr(self.url) + ');',
            "$table_prefix='fleet_';", "if(!defined('ABSPATH'))define('ABSPATH', __DIR__.'/');",
            "require ABSPATH.'wp-settings.php';", '']))
        mu = self.content / 'mu-plugins'
        mu.mkdir(exist_ok=True)
        (mu / '000-fleet-fixture-boundary.php').write_text("""<?php
add_filter('pre_wp_mail', '__return_false');
add_filter('pre_http_request', static function () {
    return new WP_Error('fleet_fixture_network_disabled');
});
add_action('doing_it_wrong_run', static function ($function) {
    if ('_load_textdomain_just_in_time' === $function) {
        file_put_contents(dirname(ABSPATH).'/translation-trace.log',
            json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)).PHP_EOL, FILE_APPEND);
    }
});
""")
        self.php("require ABSPATH.'wp-admin/includes/upgrade.php';"
                 + "wp_install('Fleet qualification', 'fleet-admin', 'fleet@example.invalid',"
                 + ' false, \'\', ' + repr(self.password) + ');'
                 + "echo json_encode(['installed'=>is_blog_installed()]);", installing=True)
        packages = read(self.bootstrap / 'manifests/bootstrap-packages.lock.json')['plugins']
        for item in packages:
            extract(self.bootstrap / 'packages' / item['package'], self.content / 'plugins',
                    expected=item['sha256'], prefix=item['slug'])
        for directory in ('mu-plugins', 'shared', 'themes'):
            shutil.copytree(self.bootstrap / directory, self.content / directory, dirs_exist_ok=True)
        entries = json.dumps([item['main_file'] for item in packages])
        activation = self.php("require_once ABSPATH.'wp-admin/includes/plugin.php';$errors=[];"
                              + 'foreach(json_decode(' + repr(entries) + ') as $plugin){'
                              + "$result=activate_plugin($plugin,'',false,false);"
                              + 'if(is_wp_error($result))$errors[$plugin]=$result->get_error_code();}'
                              + "switch_theme('mrn-base-stack-child');"
                              + "echo json_encode(['errors'=>$errors,'active'=>count(get_option('active_plugins',[])),"
                              + "'woocommerce'=>class_exists('WooCommerce')]);")
        if activation['errors'] or activation['active'] != len(packages) or activation['woocommerce']:
            raise ReleaseError('Exact default activation/no-WooCommerce qualification failed')
        # The next CLI process would otherwise be terminated by native Post SMTP
        # onboarding. This is the same narrow cleanup as the accepted bootstrap.
        self.php("delete_option('post_smtp_activation_redirect');echo json_encode(['cleared'=>true]);",
                 skip_smtp=True)
        seed = (Path(__file__).with_name('fixture-seed.php')).read_text()
        self.ids = self.php(seed.removeprefix('<?php\n'))
        self.inventory = self.php("require_once ABSPATH.'wp-admin/includes/plugin.php';"
                                  + '$all=get_plugins();$versions=[];foreach(json_decode('
                                  + repr(entries) + ") as $p)$versions[$p]=$all[$p]['Version'];"
                                  + "echo json_encode(['plugins'=>$versions,'template'=>get_template(),"
                                  + "'stylesheet'=>get_stylesheet(),'woocommerce'=>class_exists('WooCommerce'),"
                                  + "'runtime'=>mrn_loader_get_runtime_report(),"
                                  + "'login_url'=>wp_login_url()]);")
        for item in packages:
            if self.inventory['plugins'][item['main_file']] != item['version']:
                raise ReleaseError('Installed fixture plugin version differs: ' + item['slug'])
        self.router = self.root / 'router.php'
        self.router.write_text("<?php\n$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);"
                               + "if(is_file(__DIR__.'/wordpress'.$path))return false;"
                               + "require __DIR__.'/wordpress/index.php';\n")
        self.log = (self.root / 'http.log').open('w')
        self.process = subprocess.Popen([self.settings['php'], '-S', '127.0.0.1:' + str(self.port),
                                         '-t', str(self.public), str(self.router)],
                                        stdout=self.log, stderr=self.log)
        for _ in range(100):
            if self.process.poll() is not None:
                raise ReleaseError('Loopback fixture server exited')
            try:
                with urllib.request.urlopen(self.url + '/wp-json/', timeout=2) as response:
                    if response.status == 200:
                        break
            except OSError:
                time.sleep(0.1)
        else:
            raise ReleaseError('Loopback fixture REST health failed')
        write(self.root / 'qualification.json', {'status': 'pass', 'packages_activated': len(packages),
                                                'database': 'private MySQL Unix socket; TCP disabled',
                                                'woocommerce': False, 'ids': self.ids,
                                                'inventory': self.inventory,
                                                'scope': 'disposable loopback fixture; no delivery/provider/site writes'})
        return self

    def browser_input(self):
        return {'url': self.url, 'username': 'fleet-admin', 'password': self.password,
                'login_url': self.inventory['login_url'],
                'ids': self.ids, 'engine_root': self.settings['qa_engine_root'],
                'output': str(self.root / 'browser.json')}

    def assert_clean_diagnostics(self):
        debug = self.content / 'debug.log'
        if debug.exists() and debug.read_text().strip():
            raise ReleaseError('Fixture PHP diagnostics must be resolved; see private debug.log')

    def close(self):
        if self.process:
            self.process.terminate()
            with contextlib.suppress(subprocess.TimeoutExpired):
                self.process.wait(timeout=10)
            if self.process.poll() is None:
                self.process.kill()
                self.process.wait()
        if self.log:
            self.log.close()
        if self.database:
            self.database.terminate()
            with contextlib.suppress(subprocess.TimeoutExpired):
                self.database.wait(timeout=15)
            if self.database.poll() is None:
                self.database.kill()
                self.database.wait()
        if self.database_log:
            self.database_log.close()
        if self.database_root:
            self.database_root.cleanup()
        self.password = ''
        # Keep only diagnostic evidence; remove the database and temporary
        # configuration containing disposable credentials even on failures.
        for item in ('wordpress',):
            path = self.root / item
            if item == 'wordpress' and (path / 'wp-content/debug.log').exists():
                shutil.copyfile(path / 'wp-content/debug.log', self.root / 'php-diagnostics.log')
            shutil.rmtree(path, ignore_errors=True)

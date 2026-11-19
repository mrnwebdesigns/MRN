"""Exercise the shared migration runner through real WP-CLI on a clean SQLite WordPress."""
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import zipfile
from unittest.mock import patch

from PIL import Image

from test_release_v1 import Project, ROW, commit
from release_v1.artifact import inspect
from release_v1.controller import Request, Target
from release_v1.host import WordPressMigrations
from release_v1.journal import Journal
from release_v1.proof import Blocked


FIXTURES = (
    ('MRN_COMPONENT_WP_ARCHIVE', 'wordpress', '8fc96c59a78b7219e4a130222b7fadb51b03e503e8b0123beaa7e28961c21ce2'),
    ('MRN_COMPONENT_SQLITE_ARCHIVE', 'sqlite-database-integration', '1602e75577ad9b3a7e3e4a6a44a81b9541cdee2124d48928faf61c6fd3cd4f74'),
)
ORIGIN = 'http://127.0.0.1:9876'


class WordPressMigration(Project):
    def test_real_wp_cli_interruption_and_completed_replay_reuse_attachment(self):
        wp = os.environ.get('MRN_RELEASE_TEST_WP') or shutil.which('wp')
        if not wp or not all(os.environ.get(key) for key, _, _ in FIXTURES):
            self.skipTest('WP-CLI and pinned disposable WordPress/SQLite archives are required')
        for key, prefix, digest in FIXTURES:
            fixture = Path(os.environ[key])
            self.assertEqual(digest, hashlib.sha256(fixture.read_bytes()).hexdigest())
            with zipfile.ZipFile(fixture) as archive:
                self.assertTrue(all(name.startswith(prefix + '/') and '..' not in name.split('/')
                                    for name in archive.namelist()))
                archive.extractall(self.root)
        public = self.root / 'wordpress'
        sqlite = self.root / 'sqlite-database-integration'
        (public / 'wp-content/db.php').write_text((sqlite / 'db.copy').read_text().replace(
            '{SQLITE_IMPLEMENTATION_FOLDER_PATH}', str(sqlite)))
        (public / 'wp-config.php').write_text(f"""<?php
define('DB_NAME','isolated_release_fixture'); define('DB_USER',''); define('DB_PASSWORD',''); define('DB_HOST','');
define('DB_ENGINE','sqlite'); define('DB_DIR',__DIR__.'/wp-content/database/');
define('WP_HTTP_BLOCK_EXTERNAL',true); define('DISABLE_WP_CRON',true);
define('WP_AUTO_UPDATE_CORE',false); define('AUTOMATIC_UPDATER_DISABLED',true);
define('WP_HOME','{ORIGIN}'); define('WP_SITEURL','{ORIGIN}');
define('WP_ENVIRONMENT_TYPE','local'); define('WP_DEBUG',false);
$table_prefix='fixture_'; if (!defined('ABSPATH')) {{ define('ABSPATH',__DIR__.'/'); }} require ABSPATH.'wp-settings.php';
""")
        mu = public / 'wp-content/mu-plugins'
        mu.mkdir()
        (mu / '000-fixture-boundary.php').write_text("""<?php
add_filter('pre_wp_mail','__return_false');
add_filter('pre_http_request',static function(){return new WP_Error('fixture_network_disabled');});
""")

        def call(*args):
            response = subprocess.run([wp, f'--path={public}', *args], capture_output=True, text=True, timeout=60)
            self.assertEqual(response.returncode, 0, response.stderr + response.stdout)
            return response.stdout.strip()

        uploads = public / 'wp-content/uploads'
        uploads.mkdir()
        call('core', 'install', '--url=' + ORIGIN, '--title=Fixture', '--admin_user=fixture',
             '--admin_email=fixture@example.invalid', '--skip-email')
        call('post', 'create', '--post_type=page', '--post_status=publish',
             '--post_title=About', '--post_name=about', '--porcelain')
        (self.content / ROW['script']).write_text("""<?php
return static function ( $import ) {
    $attachment = $import( 'media/hero.png', 'Hero', 'Approved text', 'Approved caption' );
    $page = get_page_by_path( 'about', OBJECT, 'page' );
    if ( ! $page || ! get_option( 'fixture_ready' ) ) {
        return false;
    }
    update_post_meta( $page->ID, '_fixture_hero', $attachment );
    return (int) get_post_meta( $page->ID, '_fixture_hero', true ) === $attachment;
};
""")
        Image.new('RGB', (320, 180), 'red').save(self.content / ROW['assets'][0])
        self.source = commit(self.repo)
        receipt = self.package()
        target = Target('example/fixture', 'local', 'wp-fixture', ORIGIN)
        request = Request('wp-test', 'owner', target, self.source, receipt['artifact_sha256'], ['/'])
        runner = WordPressMigrations(public, lambda: target, wp=wp)
        backup = {'target': vars(target), 'database_verified': True, 'nonce': 'fixture'}
        state = self.root / 'state'
        state.mkdir(mode=0o700)
        journal = Journal(state, b'a' * 32)
        with inspect(self.archive, receipt['artifact_sha256'], self.source) as (root, manifest, _):
            hashes = {path: row['sha256'] for path, row in manifest['files'].items()}
            with journal.writer() as fence:
                # Retain private errors only inside this credential-free fixture.
                process = subprocess.run
                test_errors = []

                def observe(*args, **kwargs):
                    result = process(*args, **kwargs)
                    test_errors.append(result.stderr)
                    return result

                with patch('release_v1.host.subprocess.run', side_effect=observe), self.assertRaises(Blocked):
                    runner.apply(request, ROW, root / 'migrations', hashes, 'a' * 64, backup, fence)
                self.assertIn('Migration must resolve and verify', test_errors[0])
                before = call('post', 'list', '--post_type=attachment', '--format=ids')
                self.assertEqual(len(before.split()), 1, before)
                call('option', 'update', 'fixture_ready', '1')
                applied = runner.apply(request, ROW, root / 'migrations', hashes, 'a' * 64, backup, fence)
                replay = runner.apply(request, ROW, root / 'migrations', hashes, 'a' * 64, backup, fence)
                self.assertEqual((applied['result'], replay['result']), ('applied', 'already-applied'))
                with self.assertRaises(Blocked):
                    runner.apply(request, ROW, root / 'migrations', hashes, 'b' * 64, backup, fence)
            self.assertEqual(call('post', 'list', '--post_type=attachment', '--format=ids'), before)
            self.assertEqual(call('post', 'meta', 'get', before, '_wp_attachment_image_alt'), 'Approved text')
            self.assertEqual(call('post', 'get', before, '--field=post_excerpt'), 'Approved caption')
            media = list((uploads / 'mrn-release-media').glob('*.png'))
            self.assertGreater(len(media), 1)  # Original plus generated WordPress image sizes.
            self.assertIn(hashes[ROW['assets'][0]], [hashlib.sha256(path.read_bytes()).hexdigest() for path in media])

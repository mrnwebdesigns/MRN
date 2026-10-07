"""Fresh, labeled, remotely complete DB backup gate with no WordPress writes."""
import json
from pathlib import Path
import subprocess
import tempfile
import unittest

TOOLS = Path(__file__).resolve().parents[1]


class BackupEvidence(unittest.TestCase):
    def test_valid_receipt_and_failure_cases(self):
        cases = [('valid', {}, True), ('unlabeled', {'label': ''}, False),
                 ('local-only', {'service': ['none']}, False), ('files', {'themes': ['file.zip']}, False),
                 ('protected', {'always_keep': True}, False), ('bad-hash', {'checksums': {}}, False),
                 ('failed-log', {'log': 'Backup failed'}, False), ('old-log', {'mtime': 1}, False)]
        for name, changes, expected in cases:
            with self.subTest(name=name), tempfile.TemporaryDirectory(prefix='mrn-parent-backup-') as temporary:
                root = Path(temporary)
                record = {'nonce': 'a' * 12, 'label': 'pre-parent-fixture', 'db': 'db.gz', 'db-size': 100,
                          'service': ['s3'], 'checksums': {'sha256': {'db0': 'b' * 64}}, **changes}
                log = root / ('log.' + 'a' * 12 + '.txt')
                log.write_text(record.pop('log', 'Recording as successfully uploaded: database.gz\nThe backup succeeded and is now complete\n'))
                if 'mtime' in record:
                    import os
                    os.utime(log, (record.pop('mtime'), 1))
                harness = root / 'probe.php'
                harness.write_text('''<?php
define('WP_CLI',true); putenv('MRN_PARENT_BACKUP_NONCE=aaaaaaaaaaaa');
class WP_CLI { public static function line($v){echo $v;} public static function error($v){exit(2);} }
function trailingslashit($p){return rtrim($p,'/').'/';} function wp_json_encode($v){return json_encode($v);}
class UpdraftPlus_Backup_History { public static function get_backup_set_by_nonce($n){return json_decode(%s,true);} }
class FixtureBackup { public function backups_dir_location(){return %s;} }
$updraftplus=new FixtureBackup();require %s;
''' % (repr(json.dumps(record)), repr(str(root)), repr(str(TOOLS / 'verify_backup.php'))))
                result = subprocess.run(['php', str(harness)], capture_output=True, text=True)
                self.assertEqual(expected, result.returncode == 0, result.stderr)
                self.assertEqual(expected, json.loads(result.stdout[11:])['valid'])

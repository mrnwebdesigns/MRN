import os
from pathlib import Path
import subprocess
import tempfile
import unittest


class BackupReceipt(unittest.TestCase):
    def test_backup_evidence_must_be_complete_remote_and_exact(self):
        for case in ['valid', 'wrong-label', 'local-only', 'file-backup', 'always-keep',
                     'partial-upload', 'failed', 'not-complete']:
            with self.subTest(case=case), tempfile.TemporaryDirectory() as temp:
                log = 'Recording as successfully uploaded: database.gz\nThe backup succeeded and is now complete\n'
                if case == 'partial-upload':
                    log = log.replace('database.gz', 'database.gz (s3, more services to follow)')
                elif case == 'failed':
                    log += 'Backup failed\n'
                elif case == 'not-complete':
                    log = log.replace('The backup succeeded and is now complete', 'Still running')
                Path(temp, 'log.123456789abc.txt').write_text(log)
                result = subprocess.run(['php', str(Path(__file__).with_name('backup-fixture.php'))],
                                        env={**os.environ, 'MRN_BACKUP_LABEL': 'pre-live-example-123',
                                             'FIXTURE_DIR': temp, 'FIXTURE_CASE': case}, capture_output=True)
                self.assertEqual(result.returncode == 0, case == 'valid', result.stderr.decode())


if __name__ == '__main__':
    unittest.main()

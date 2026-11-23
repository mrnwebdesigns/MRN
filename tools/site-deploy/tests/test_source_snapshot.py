from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).parents[1]))
from source_snapshot import snapshot

class SourceSnapshot(unittest.TestCase):
    def test_non_git_source_becomes_deterministic_clean_qa_input_without_source_mutation(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp); source=root/'source';source.mkdir()
            (source/'functions.php').write_text('<?php // reviewed\n')
            (source/'style.css').write_text('body {}')
            (source/'.env').write_text('private excluded file')
            files=['style.css','functions.php']
            first=snapshot(source,files,root/'first'); second=snapshot(source,list(reversed(files)),root/'second')
            self.assertEqual(first,second); self.assertFalse((source/'.git').exists())
            self.assertFalse((root/'first/.env').exists())
            self.assertEqual('',subprocess.check_output(['git','-C',str(root/'first'),'status','--porcelain']).decode())
            self.assertEqual(first['source_sha'],subprocess.check_output(['git','-C',str(root/'first'),'rev-parse','HEAD']).decode().strip())
            self.assertEqual(0,(root/'first').stat().st_mode & 0o077)
            with self.assertRaises(ValueError): snapshot(source,files,root/'first')

    def test_symlink_secret_traversal_and_oversized_source_fail_before_output(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);source=root/'source';source.mkdir()
            (source/'safe.php').write_text('<?php')
            (source/'alias.php').symlink_to(source/'safe.php')
            for files in (['../safe.php'],['.env'],['wp-config.php'],['alias.php'],['safe.php','safe.php']):
                with self.assertRaises((ValueError,FileNotFoundError)): snapshot(source,files,root/'output')
                self.assertFalse((root/'output').exists())
            with patch('source_snapshot.MAX_FILE',1):
                with self.assertRaises(ValueError): snapshot(source,['safe.php'],root/'output')

if __name__=='__main__':unittest.main()

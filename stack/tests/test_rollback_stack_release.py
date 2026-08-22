import importlib.util
import json
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace


SCRIPT_PATH = Path(__file__).parents[1] / "scripts" / "rollback-stack-release-on-site.py"
SPEC = importlib.util.spec_from_file_location("rollback_stack_release", SCRIPT_PATH)
rollback = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(rollback)


class RollbackStackReleaseTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name)
        self.receipt = {
            "schema_version": 1,
            "status": "deployment-failed",
            "release_id": "new-release",
            "hostname": "example.mrndev.io",
            "site_user": "example",
            "site_root": "/home/example/htdocs/example.mrndev.io",
            "ssh_login": "example@mrndev-site-owner",
            "remote_code_archive": "/home/example/deployment-backups/pre-new-release.tar.gz",
            "remote_code_archive_sha256": "b" * 64,
            "target_paths": [
                "wp-content/mu-plugins/mrn-loader.php",
                "wp-content/plugins/mrn-required",
            ],
            "previous_release": {
                "valid": True,
                "release_id": "old-release",
                "sha256": "a" * 64,
            },
        }
        self.receipt_path = self.root / "receipt.json"
        self.receipt_path.write_text(json.dumps(self.receipt) + "\n", encoding="utf-8")

    def tearDown(self):
        self.temporary.cleanup()

    def test_wrong_confirmation_stops_before_preflight(self):
        calls = []

        with self.assertRaisesRegex(rollback.RollbackError, "confirmation"):
            rollback.rollback(
                self.receipt_path,
                "wrong",
                command_runner=lambda command: calls.append(command),
            )

        self.assertEqual([], calls)

    def test_success_requires_new_backup_and_verifies_previous_release(self):
        calls = []

        def command_runner(command):
            text_command = [str(item) for item in command]
            calls.append(text_command)
            if text_command[0].endswith("preflight-live-site-deploy.sh"):
                label = text_command[text_command.index("--backup-label") + 1]
                return SimpleNamespace(
                    stdout=(
                        "SITE_ROOT=/home/example/htdocs/example.mrndev.io\n"
                        "SSH_LOGIN=example@mrndev-site-owner\n"
                        f"BACKUP_LABEL={label}\n"
                    )
                )
            if "mrn stack-report" in " ".join(text_command):
                return SimpleNamespace(
                    stdout=json.dumps(
                        {
                            "release_lock": {
                                "valid": True,
                                "release_id": "old-release",
                                "sha256": "a" * 64,
                            },
                            "missing_required": [],
                            "drifted_required": [],
                            "legacy_flat_collisions": [],
                        }
                    )
                )
            return SimpleNamespace(stdout="")

        result = rollback.rollback(
            self.receipt_path,
            "ROLLBACK example.mrndev.io new-release",
            command_runner=command_runner,
        )

        self.assertEqual("verified-restored", result["status"])
        self.assertTrue(
            any("rm -rf" in " ".join(command) for command in calls)
        )
        saved = json.loads(self.receipt_path.read_text())
        self.assertEqual("verified-restored", saved["rollback"]["status"])


if __name__ == "__main__":
    unittest.main()

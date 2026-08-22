import datetime as dt
import importlib.util
import json
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace


SCRIPT_PATH = Path(__file__).parents[1] / "scripts" / "deploy-stack-release-to-site.py"
SPEC = importlib.util.spec_from_file_location("deploy_stack_release", SCRIPT_PATH)
deploy = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(deploy)


FIXED_NOW = dt.datetime(2026, 8, 22, 12, 0, tzinfo=dt.timezone.utc)


class DeployStackReleaseTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name)
        self.artifacts = self.root / "artifacts"
        sources = {
            "mu-plugins/mrn-loader.php": "<?php /* Version: 1.0.0 */\n",
            "plugins/mrn-required/mrn-required.php": "<?php /* Version: 1.0.0 */\n",
            "shared/mrn-shared-runtime.php": "<?php /* Version: 1.0.0 */\n",
            "themes/mrn-base-stack/style.css": "/* Version: 1.0.0 */\n",
            "themes/mrn-base-stack-child/style.css": "/* Version: 1.0.0 */\n",
        }
        for relative, contents in sources.items():
            path = self.artifacts / relative
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(contents, encoding="utf-8")

        def locked_entry(slug, runtime_type, deployed_path):
            path = self.artifacts / deployed_path
            digest, count = deploy.release_lock.tree_sha256(path)
            return {
                "slug": slug,
                "name": slug,
                "version": "1.0.0",
                "runtime_type": runtime_type,
                "required": True,
                "deployed_path": deployed_path,
                "source": {
                    "repository": "MRN",
                    "git_commit": "a" * 40,
                    "path": deployed_path,
                },
                "sha256": digest,
                "file_count": count,
            }

        def locked_theme(slug, deployed_path, mode, role):
            path = self.artifacts / deployed_path
            digest, count = deploy.release_lock.tree_sha256(path)
            return {
                "slug": slug,
                "version": "1.0.0",
                "active": mode == "site-derived",
                "verification_mode": mode,
                "deployment_role": role,
                "deployed_path": deployed_path,
                "source": {
                    "repository": "MRN",
                    "git_commit": "a" * 40,
                    "path": deployed_path,
                },
                "sha256": digest,
                "file_count": count,
            }

        self.lock = {
            "schema_version": 1,
            "release_id": "fixture-release",
            "released_at": "2026-08-22T00:00:00Z",
            "source": {"repository": "MRN", "git_commit": "a" * 40},
            "stack_version": "fixture-release",
            "hash_algorithm": "sha256-tree-v1",
            "compatibility": {
                "minimum_loader_version": "1.0.0",
                "runtime_report_schema_version": 1,
            },
            "components": [
                locked_entry("mrn-loader", "mu-loader", "mu-plugins/mrn-loader.php"),
                locked_entry("mrn-required", "standard-plugin", "plugins/mrn-required"),
                locked_entry("mrn-shared-runtime", "shared-runtime", "shared"),
            ],
            "themes": [
                locked_theme(
                    "mrn-base-stack",
                    "themes/mrn-base-stack",
                    "exact",
                    "parent-template",
                ),
                locked_theme(
                    "mrn-base-stack-child",
                    "themes/mrn-base-stack-child",
                    "site-derived",
                    "active-stylesheet-template",
                ),
            ],
        }
        self.lock_path = self.root / "lock.json"
        self.lock_path.write_text(json.dumps(self.lock) + "\n", encoding="utf-8")
        embedded = self.artifacts / "mu-plugins/mrn-stack-release.lock.json"
        embedded.write_bytes(self.lock_path.read_bytes())

    def tearDown(self):
        self.temporary.cleanup()

    def command_runner(self, calls, include_backup=True):
        def run(command):
            text_command = [str(item) for item in command]
            calls.append(text_command)
            if text_command[0].endswith("preflight-live-site-deploy.sh"):
                backup = "BACKUP_LABEL=fixture-backup\n" if include_backup else ""
                return SimpleNamespace(
                    stdout=(
                        "SITE_HOSTNAME=example.mrndev.io\n"
                        "SITE_USER=example\n"
                        "SITE_ROOT=/home/example/htdocs/example.mrndev.io\n"
                        "SSH_ALIAS=mrndev-site-owner\n"
                        "SSH_LOGIN=example@mrndev-site-owner\n"
                        + backup
                    )
                )
            joined = " ".join(text_command)
            if "option get template" in joined:
                return SimpleNamespace(stdout="customer-parent\n")
            if "option get stylesheet" in joined:
                return SimpleNamespace(stdout="customer-child\n")
            return SimpleNamespace(stdout="")

        return run

    def test_operations_map_parent_to_live_template_and_preserve_child(self):
        operations, plugins = deploy.deployment_operations(
            self.lock, self.artifacts, "customer-parent"
        )
        targets = [operation["target"] for operation in operations]

        self.assertIn("wp-content/themes/customer-parent", targets)
        self.assertNotIn("wp-content/themes/customer-child", targets)
        self.assertIn("wp-content/shared", targets)
        self.assertEqual(["mrn-required"], plugins)
        self.assertEqual("mrn-stack-release-lock", operations[-1]["slug"])

    def test_dry_run_returns_complete_plan_without_rsync_or_receipt(self):
        calls = []
        receipt = self.root / "receipt.json"

        plan = deploy.deploy(
            release_lock_path=self.lock_path,
            artifact_root=self.artifacts,
            site_hostname="example.mrndev.io",
            discovery_ssh_host="mrndev",
            receipt_out=receipt,
            dry_run=True,
            now=FIXED_NOW,
            command_runner=self.command_runner(calls),
        )

        self.assertTrue(plan["dry_run"])
        self.assertTrue(plan["child_theme_preserved"])
        self.assertFalse(receipt.exists())
        self.assertFalse(any(command[0] == "rsync" for command in calls))

    def test_write_requires_matching_verified_backup_before_runtime_commands(self):
        calls = []

        with self.assertRaisesRegex(deploy.DeployError, "backup receipt"):
            deploy.deploy(
                release_lock_path=self.lock_path,
                artifact_root=self.artifacts,
                site_hostname="example.mrndev.io",
                discovery_ssh_host="mrndev",
                receipt_out=self.root / "receipt.json",
                backup_label="fixture-backup",
                dry_run=False,
                now=FIXED_NOW,
                command_runner=self.command_runner(calls, include_backup=False),
            )

        self.assertFalse(any("mrn stack-report" in " ".join(command) for command in calls))
        self.assertFalse(any(command[0] == "rsync" for command in calls))

    def test_runtime_verification_requires_exact_parent_hash(self):
        report = {
            "schema_version": 1,
            "release_lock": {
                "valid": True,
                "release_id": "fixture-release",
                "sha256": deploy.release_lock.file_sha256(self.lock_path),
            },
            "missing_required": [],
            "drifted_required": [],
            "legacy_flat_collisions": [],
            "themes": [
                {"slug": "mrn-base-stack", "matches_release": False}
            ],
        }

        with self.assertRaisesRegex(deploy.DeployError, "parent theme"):
            deploy.verify_runtime_report(
                self.lock,
                report,
                deploy.release_lock.file_sha256(self.lock_path),
            )

    def test_drifted_existing_runtime_is_not_an_acceptable_rollback_baseline(self):
        report = {
            "release_lock": {
                "valid": True,
                "release_id": "old-release",
                "sha256": "a" * 64,
            },
            "missing_required": ["mrn-required"],
            "drifted_required": [],
            "legacy_flat_collisions": [],
        }

        with self.assertRaisesRegex(deploy.DeployError, "missing_required"):
            deploy.verify_previous_runtime_report(report)


if __name__ == "__main__":
    unittest.main()

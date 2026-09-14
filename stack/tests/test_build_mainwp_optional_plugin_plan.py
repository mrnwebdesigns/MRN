import datetime as dt
import hashlib
import importlib.util
import json
import subprocess
import tempfile
import unittest
import zipfile
from pathlib import Path


SCRIPT_PATH = Path(__file__).parents[1] / "scripts" / "build-mainwp-optional-plugin-plan.py"
SPEC = importlib.util.spec_from_file_location("optional_plugin_plan", SCRIPT_PATH)
planner = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(planner)


def run(*command, cwd):
    result = subprocess.run(
        [str(item) for item in command],
        cwd=cwd,
        capture_output=True,
        text=True,
        check=False,
    )
    if result.returncode != 0:
        raise AssertionError(result.stderr or result.stdout)
    return result.stdout.strip()


def write_json(path, payload):
    Path(path).write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")


def checksum(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def build_zip(path, version):
    with zipfile.ZipFile(path, "w", zipfile.ZIP_DEFLATED) as archive:
        archive.writestr(
            "mrn-database-retention/mrn-database-retention.php",
            "<?php\n/**\n * Plugin Name: MRN Database Retention\n"
            f" * Version: {version}\n */\n",
        )
        archive.writestr(
            "mrn-database-retention/includes/policy.php",
            "<?php\n",
        )


class OptionalPluginPlanTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name)
        self.source = self.root / "mrn-database-retention"
        self.source.mkdir()
        run("git", "init", "--initial-branch=main", cwd=self.source)
        run("git", "config", "user.email", "qa@example.com", cwd=self.source)
        run("git", "config", "user.name", "QA Fixture", cwd=self.source)
        (self.source / "mrn-database-retention.php").write_text(
            "<?php\n/** Version: 1.1.1 */\n",
            encoding="utf-8",
        )
        run("git", "add", ".", cwd=self.source)
        run("git", "commit", "-m", "Release 1.1.1", cwd=self.source)
        self.commit = run("git", "rev-parse", "HEAD", cwd=self.source)
        self.origin = self.root / "origin.git"
        run("git", "init", "--bare", self.origin, cwd=self.source)
        run("git", "remote", "add", "origin", self.origin, cwd=self.source)
        run("git", "push", "-u", "origin", "main", cwd=self.source)

        self.artifact = self.root / "mrn-database-retention-1.1.1.zip"
        self.rollback = self.root / "mrn-database-retention-1.1.0.zip"
        build_zip(self.artifact, "1.1.1")
        build_zip(self.rollback, "1.1.0")
        self.catalog_path = self.root / "component-catalog.json"
        self.releases_path = self.root / "optional-plugin-releases.json"
        self.inventory_path = self.root / "inventory.json"
        self.generated_at = dt.datetime(2026, 9, 14, 12, 10, tzinfo=dt.timezone.utc)

        self.catalog = {
            "components": [
                {
                    "slug": "mrn-database-retention",
                    "version": "1.1.1",
                    "target_tier": "maintenance-only",
                    "current_distribution": "catalog-only",
                }
            ]
        }
        self.releases = {
            "releases": [
                {
                    "slug": "mrn-database-retention",
                    "version": "1.1.1",
                    "target_tier": "maintenance-only",
                    "current_distribution": "catalog-only",
                    "source": {
                        "repository": "mrnwebdesigns/mrn-database-retention",
                        "path": str(self.source),
                        "git_commit": self.commit,
                    },
                    "package": {
                        "path": str(self.artifact),
                        "filename": self.artifact.name,
                        "main_file": "mrn-database-retention/mrn-database-retention.php",
                        "size_bytes": self.artifact.stat().st_size,
                        "sha256": checksum(self.artifact),
                    },
                    "update_policy": {"mode": "upgrade-only"},
                }
            ]
        }
        self.inventory = {
            "schema_version": 1,
            "site": {
                "site_id": 123,
                "site_url": "https://example.com",
                "inventory_synced_at": "2026-09-14T12:00:00Z",
                "plugin": {
                    "slug": "mrn-database-retention",
                    "installed": True,
                    "active": False,
                    "version": "1.1.0",
                },
                "backup_readiness": {
                    "ready": True,
                    "provider": "UpdraftPlus",
                    "remote_destination_configured": True,
                    "wp_cli_available": True,
                    "backup_command_available": True,
                },
                "rollback_readiness": {
                    "ready": True,
                    "version": "1.1.0",
                    "package_path": str(self.rollback),
                    "package_sha256": checksum(self.rollback),
                },
            },
        }
        self.write_inputs()

    def tearDown(self):
        self.temporary.cleanup()

    def write_inputs(self):
        write_json(self.catalog_path, self.catalog)
        write_json(self.releases_path, self.releases)
        write_json(self.inventory_path, self.inventory)

    def build(self):
        self.write_inputs()
        return planner.build_plan(
            catalog_path=self.catalog_path,
            releases_path=self.releases_path,
            inventory_path=self.inventory_path,
            artifact_path=None,
            plugin_slug="mrn-database-retention",
            plan_id="db-retention-example-20260914",
            generated_at=self.generated_at,
            max_inventory_age_seconds=900,
        )

    def test_builds_one_site_upgrade_only_plan_and_preserves_active_state(self):
        plan = self.build()

        self.assertEqual("upgrade-only", plan["operation"])
        self.assertEqual(123, plan["site"]["site_id"])
        self.assertEqual("https://example.com", plan["site"]["site_url"])
        self.assertTrue(plan["site"]["installed"])
        self.assertFalse(plan["site"]["active"])
        self.assertEqual("1.1.0", plan["site"]["current_version"])
        self.assertEqual("1.1.1", plan["site"]["target_version"])
        self.assertEqual(checksum(self.artifact), plan["preflight"]["package_sha256"])
        self.assertTrue(plan["preflight"]["backup_readiness"]["ready"])
        self.assertTrue(plan["preflight"]["rollback_readiness"]["ready"])
        self.assertEqual(
            self.rollback.name,
            plan["preflight"]["rollback_readiness"]["package_filename"],
        )
        self.assertEqual(
            "mrn-database-retention/mrn-database-retention.php",
            plan["preflight"]["rollback_readiness"]["main_file"],
        )
        self.assertEqual(
            "mrn-mainwp/preflight-optional-plugin-update-v1",
            plan["execution_contract"]["preflight_ability"],
        )
        self.assertEqual("0.8.1", plan["execution_contract"]["minimum_controller_version"])
        self.assertEqual(
            "controller-preflight",
            plan["execution_contract"]["precondition_hash_source"],
        )
        self.assertEqual(
            "operator-supplied-checksum-locked-package",
            plan["execution_contract"]["rollback_artifact_model"],
        )
        self.assertFalse(plan["execution_contract"]["allow_new_install"])

    def test_refuses_missing_plugin_instead_of_creating_install_plan(self):
        self.inventory["site"]["plugin"]["installed"] = False

        with self.assertRaisesRegex(planner.PlanError, "new installation"):
            self.build()

    def test_refuses_stale_inventory(self):
        self.inventory["site"]["inventory_synced_at"] = "2026-09-14T11:00:00Z"

        with self.assertRaisesRegex(planner.PlanError, "not fresh"):
            self.build()

    def test_refuses_platform_required_component(self):
        self.catalog["components"][0]["target_tier"] = "platform-required"
        self.releases["releases"][0]["target_tier"] = "platform-required"

        with self.assertRaisesRegex(planner.PlanError, "schema-2"):
            self.build()

    def test_refuses_missing_backup_readiness(self):
        self.inventory["site"]["backup_readiness"]["ready"] = False

        with self.assertRaisesRegex(planner.PlanError, "not ready"):
            self.build()

    def test_refuses_unverified_rollback_artifact(self):
        self.inventory["site"]["rollback_readiness"]["package_sha256"] = "0" * 64

        with self.assertRaisesRegex(planner.PlanError, "Rollback package checksum"):
            self.build()

    def test_refuses_unsafe_rollback_artifact(self):
        with zipfile.ZipFile(self.rollback, "a") as archive:
            archive.writestr("../escape.php", "<?php\n")
        self.inventory["site"]["rollback_readiness"]["package_sha256"] = checksum(
            self.rollback
        )

        with self.assertRaisesRegex(planner.PlanError, "unsafe path"):
            self.build()

    def test_refuses_downgrade_or_noop(self):
        self.inventory["site"]["plugin"]["version"] = "1.1.1"
        self.inventory["site"]["rollback_readiness"]["version"] = "1.1.1"

        with self.assertRaisesRegex(planner.PlanError, "current_version < target_version"):
            self.build()


class OptionalReleaseCatalogTests(unittest.TestCase):
    def test_database_retention_release_stays_optional_and_out_of_bootstrap(self):
        stack = Path(__file__).parents[1]
        catalog = json.loads(
            (stack / "manifests/component-catalog.json").read_text(encoding="utf-8")
        )
        releases = json.loads(
            (stack / "manifests/optional-plugin-releases.json").read_text(
                encoding="utf-8"
            )
        )
        entry = next(
            item
            for item in catalog["components"]
            if item["slug"] == "mrn-database-retention"
        )
        release = next(
            item
            for item in releases["releases"]
            if item["slug"] == "mrn-database-retention"
        )
        manifest = (stack / "manifests/plugins.txt").read_text(encoding="utf-8")

        self.assertEqual("1.1.1", entry["version"])
        self.assertEqual("maintenance-only", entry["target_tier"])
        self.assertEqual("catalog-only", entry["current_distribution"])
        self.assertEqual(entry["version"], release["version"])
        self.assertEqual(entry["target_tier"], release["target_tier"])
        self.assertEqual("upgrade-only", release["update_policy"]["mode"])
        self.assertTrue(release["update_policy"]["preserve_active_state"])
        self.assertNotIn("mrn-database-retention.zip", manifest)
        self.assertEqual(
            "mrnwebdesigns/mrn-database-retention",
            release["source"]["repository"],
        )
        self.assertRegex(release["source"]["git_commit"], r"^[a-f0-9]{40}$")
        self.assertRegex(release["package"]["sha256"], r"^[a-f0-9]{64}$")

        controller = next(
            item
            for item in catalog["components"]
            if item["slug"] == "mrn-mainwp-operations-api"
        )
        self.assertEqual("0.8.1", controller["version"])
        self.assertEqual("dashboard-only", controller["target_tier"])
        self.assertIn("nineteen mrn-mainwp WordPress Abilities", controller["data"]["routes"])
        self.assertNotIn("Defender (legacy compatibility only)", entry["dependencies"]["soft"])


if __name__ == "__main__":
    unittest.main()

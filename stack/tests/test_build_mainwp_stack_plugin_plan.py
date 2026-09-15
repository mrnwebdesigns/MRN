import datetime as dt
import hashlib
import importlib.util
import json
import subprocess
import tempfile
import unittest
import zipfile
from pathlib import Path

STACK_DIR = Path(__file__).parents[1]
SCRIPT_PATH = STACK_DIR / "scripts" / "build-mainwp-stack-plugin-plan.py"
SPEC = importlib.util.spec_from_file_location("stack_plugin_plan", SCRIPT_PATH)
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


class StackPluginPlanTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name)
        self.source = self.root / "mrn-config-helper"
        self.source.mkdir()
        run("git", "init", "--initial-branch=main", cwd=self.source)
        run("git", "config", "user.email", "qa@example.com", cwd=self.source)
        run("git", "config", "user.name", "QA Fixture", cwd=self.source)
        (self.source / "includes").mkdir()
        (self.source / "includes" / "runtime.php").write_text("<?php\n", encoding="utf-8")
        self.write_main_file("0.1.59")
        run("git", "add", ".", cwd=self.source)
        run("git", "commit", "-m", "Release 0.1.59", cwd=self.source)
        self.rollback_commit = run("git", "rev-parse", "HEAD", cwd=self.source)

        self.write_main_file("0.1.60")
        run("git", "add", ".", cwd=self.source)
        run("git", "commit", "-m", "Release 0.1.60", cwd=self.source)
        self.target_commit = run("git", "rev-parse", "HEAD", cwd=self.source)

        self.origin = self.root / "origin.git"
        run("git", "init", "--bare", self.origin, cwd=self.source)
        run("git", "remote", "add", "origin", self.origin, cwd=self.source)
        run("git", "push", "-u", "origin", "main", cwd=self.source)

        self.rollback_artifact = self.root / "mrn-config-helper-0.1.59.zip"
        self.target_artifact = self.root / "mrn-config-helper-0.1.60.zip"
        self.archive(self.rollback_commit, self.rollback_artifact)
        self.archive(self.target_commit, self.target_artifact)
        self.rollback_tree = self.package_tree(self.rollback_artifact)
        self.target_tree = self.package_tree(self.target_artifact)

        self.catalog_path = self.root / "component-catalog.json"
        self.releases_path = self.root / "stack-plugin-releases.json"
        self.lock_path = self.root / "stack-release.lock.json"
        self.inventory_path = self.root / "inventory.json"
        self.generated_at = dt.datetime(2026, 9, 15, 12, 10, tzinfo=dt.timezone.utc)

        self.catalog = {
            "components": [
                {
                    "slug": "mrn-config-helper",
                    "version": "0.1.60",
                    "runtime_type": "standard-plugin",
                    "target_tier": "platform-required",
                    "current_distribution": "standard-bootstrap",
                }
            ]
        }
        self.releases = {
            "releases": [
                self.release("0.1.59", self.rollback_commit, self.rollback_artifact, self.rollback_tree),
                self.release("0.1.60", self.target_commit, self.target_artifact, self.target_tree),
            ]
        }
        self.lock = {
            "schema_version": 1,
            "release_id": "2026.09.11-mainwp-full-stack-fleet-canary-verified",
            "hash_algorithm": "sha256-tree-v1",
            "components": [
                {
                    "slug": "mrn-config-helper",
                    "runtime_type": "standard-plugin",
                    "version": "0.1.59",
                    "deployed_path": "plugins/mrn-config-helper",
                    "sha256": self.rollback_tree[0],
                    "file_count": self.rollback_tree[1],
                }
            ],
            "themes": [],
        }
        write_json(self.lock_path, self.lock)
        self.inventory = {
            "schema_version": 1,
            "site": {
                "site_id": 115,
                "site_url": "https://44ec8d3fc1.nxcli.io",
                "inventory_synced_at": "2026-09-15T12:00:00Z",
                "release": {
                    "present": True,
                    "valid": True,
                    "release_id": self.lock["release_id"],
                    "lock_sha256": checksum(self.lock_path),
                },
                "plugin": {
                    "slug": "mrn-config-helper",
                    "main_file": "mrn-config-helper/mrn-config-helper.php",
                    "installed": True,
                    "active": True,
                    "loaded": True,
                    "version": "0.1.59",
                    "runtime_type": "standard-plugin",
                    "path": "plugins/mrn-config-helper",
                    "hash_algorithm": "sha256-tree-v1",
                    "tree_sha256": self.rollback_tree[0],
                    "file_count": self.rollback_tree[1],
                },
                "backup_readiness": {
                    "ready": True,
                    "provider": "UpdraftPlus",
                    "plugin_installed": True,
                    "plugin_active": True,
                    "backup_api_available": True,
                    "remote_destination_configured": True,
                },
            },
        }
        self.write_inputs()

    def tearDown(self):
        self.temporary.cleanup()

    def write_main_file(self, version):
        (self.source / "mrn-config-helper.php").write_text(
            "<?php\n/**\n * Plugin Name: MRN Config Helper\n"
            f" * Version: {version}\n */\n",
            encoding="utf-8",
        )

    def archive(self, commit, path):
        run(
            "git",
            "archive",
            "--format=zip",
            "--prefix=mrn-config-helper/",
            commit,
            "-o",
            path,
            cwd=self.source,
        )

    def package_tree(self, path):
        with zipfile.ZipFile(path) as archive:
            return planner.zip_tree_hash(archive, "mrn-config-helper")

    def release(self, version, commit, artifact, tree):
        return {
            "slug": "mrn-config-helper",
            "version": version,
            "runtime_type": "standard-plugin",
            "target_tier": "platform-required",
            "current_distribution": "standard-bootstrap",
            "source": {
                "repository": "mrnwebdesigns/mrn-config-helper",
                "path": str(self.source),
                "git_commit": commit,
            },
            "package": {
                "path": str(artifact),
                "filename": artifact.name,
                "main_file": "mrn-config-helper/mrn-config-helper.php",
                "size_bytes": artifact.stat().st_size,
                "sha256": checksum(artifact),
            },
            "tree": {
                "hash_algorithm": "sha256-tree-v1",
                "sha256": tree[0],
                "file_count": tree[1],
            },
            "update_policy": {"mode": "upgrade-only", "preserve_active_state": True},
        }

    def write_inputs(self):
        write_json(self.catalog_path, self.catalog)
        write_json(self.releases_path, self.releases)
        write_json(self.inventory_path, self.inventory)

    def build(self):
        self.write_inputs()
        return planner.build_plan(
            catalog_path=self.catalog_path,
            releases_path=self.releases_path,
            release_lock_path=self.lock_path,
            inventory_path=self.inventory_path,
            target_artifact_path=None,
            rollback_artifact_path=None,
            plugin_slug="mrn-config-helper",
            target_version=None,
            plan_id="config-helper-0.1.60-site-115",
            generated_at=self.generated_at,
            max_inventory_age_seconds=900,
        )

    def test_builds_one_site_baseline_bound_component_overlay(self):
        plan = self.build()

        self.assertEqual("stack-plugin-update", plan["plan_type"])
        self.assertEqual(self.lock["release_id"], plan["baseline"]["release_id"])
        self.assertEqual(checksum(self.lock_path), plan["baseline"]["lock_sha256"])
        self.assertEqual("0.1.59", plan["plugin"]["rollback"]["version"])
        self.assertEqual("0.1.60", plan["plugin"]["target"]["version"])
        self.assertEqual(self.rollback_tree[0], plan["site"]["current_tree_sha256"])
        self.assertEqual("immutable-baseline-plus-component-overlay", plan["execution_contract"]["release_identity_model"])
        self.assertEqual("0.9.0", plan["execution_contract"]["minimum_controller_version"])
        self.assertFalse(plan["execution_contract"]["allow_new_install"])
        schema = json.loads(
            (STACK_DIR / "manifests" / "stack-plugin-update-plan.schema.json").read_text(encoding="utf-8")
        )
        self.assertEqual("stack-plugin-update", schema["properties"]["plan_type"]["const"])

    def test_refuses_plugin_absent_from_immutable_baseline(self):
        self.lock["components"] = []
        write_json(self.lock_path, self.lock)
        self.inventory["site"]["release"]["lock_sha256"] = checksum(self.lock_path)

        with self.assertRaisesRegex(planner.PlanError, "baseline component"):
            self.build()

    def test_refuses_missing_inactive_or_unloaded_plugin(self):
        self.inventory["site"]["plugin"]["installed"] = False
        with self.assertRaisesRegex(planner.PlanError, "new installation"):
            self.build()

        self.inventory["site"]["plugin"]["installed"] = True
        self.inventory["site"]["plugin"]["active"] = False
        with self.assertRaisesRegex(planner.PlanError, "active and loaded"):
            self.build()

    def test_refuses_unregistered_runtime_tree(self):
        self.inventory["site"]["plugin"]["tree_sha256"] = "0" * 64

        with self.assertRaisesRegex(planner.PlanError, "registered rollback"):
            self.build()

    def test_refuses_wrong_release_lock(self):
        self.inventory["site"]["release"]["lock_sha256"] = "0" * 64

        with self.assertRaisesRegex(planner.PlanError, "reviewed baseline"):
            self.build()

    def test_refuses_non_standard_plugin(self):
        self.catalog["components"][0]["runtime_type"] = "mu-component"

        with self.assertRaisesRegex(planner.PlanError, "standard plugins only"):
            self.build()

    def test_refuses_package_not_matching_source_commit(self):
        (self.source / "unpackaged.php").write_text("<?php\n", encoding="utf-8")
        run("git", "add", ".", cwd=self.source)
        run("git", "commit", "-m", "Unpackaged source change", cwd=self.source)
        changed_commit = run("git", "rev-parse", "HEAD", cwd=self.source)
        run("git", "push", "origin", "main", cwd=self.source)
        self.releases["releases"][1]["source"]["git_commit"] = changed_commit

        with self.assertRaisesRegex(planner.PlanError, "exact source commit"):
            self.build()

    def test_accepts_exact_legacy_supplement_for_rollback_only(self):
        contents = b"legacy internal note\n"
        relative = "ai-data/internal-notes.md"
        supplement = {
            "path": relative,
            "size_bytes": len(contents),
            "sha256": hashlib.sha256(contents).hexdigest(),
        }
        with zipfile.ZipFile(self.rollback_artifact, "a", zipfile.ZIP_DEFLATED) as archive:
            archive.writestr(f"mrn-config-helper/{relative}", contents)
        with zipfile.ZipFile(self.rollback_artifact) as archive:
            full_tree, source_tree = planner.zip_tree_evidence(
                archive, "mrn-config-helper", {relative: supplement}
            )
        self.assertEqual(self.rollback_tree, source_tree)
        release = self.releases["releases"][0]
        release["package"]["size_bytes"] = self.rollback_artifact.stat().st_size
        release["package"]["sha256"] = checksum(self.rollback_artifact)
        release["tree"]["sha256"] = full_tree[0]
        release["tree"]["file_count"] = full_tree[1]
        release["legacy_supplements"] = [supplement]
        self.inventory["site"]["plugin"]["tree_sha256"] = full_tree[0]
        self.inventory["site"]["plugin"]["file_count"] = full_tree[1]

        plan = self.build()

        self.assertEqual([supplement], plan["plugin"]["rollback"]["legacy_supplements"])

    def test_refuses_legacy_supplements_in_forward_target(self):
        self.releases["releases"][1]["legacy_supplements"] = [
            {
                "path": "ai-data/internal-notes.md",
                "size_bytes": 1,
                "sha256": "0" * 64,
            }
        ]

        with self.assertRaisesRegex(planner.PlanError, "cannot contain legacy"):
            self.build()


class StackPluginReleaseRegistryTests(unittest.TestCase):
    def test_config_helper_has_exact_forward_and_rollback_releases(self):
        catalog = json.loads(
            (STACK_DIR / "manifests" / "component-catalog.json").read_text(encoding="utf-8")
        )
        registry = json.loads(
            (STACK_DIR / "manifests" / "stack-plugin-releases.json").read_text(encoding="utf-8")
        )
        entry = next(item for item in catalog["components"] if item["slug"] == "mrn-config-helper")
        versions = {
            item["version"]: item
            for item in registry["releases"]
            if item["slug"] == "mrn-config-helper"
        }

        self.assertEqual("0.1.61", entry["version"])
        self.assertEqual({"0.1.59", "0.1.60", "0.1.61"}, set(versions))
        self.assertEqual(
            entry["version"],
            max(versions, key=lambda value: planner.version_tuple(value, "version")),
        )
        for release in versions.values():
            self.assertEqual("standard-plugin", release["runtime_type"])
            self.assertEqual("platform-required", release["target_tier"])
            self.assertEqual("standard-bootstrap", release["current_distribution"])
            self.assertEqual("sha256-tree-v1", release["tree"]["hash_algorithm"])
            self.assertRegex(release["source"]["git_commit"], r"^[a-f0-9]{40}$")
            self.assertRegex(release["package"]["sha256"], r"^[a-f0-9]{64}$")
            self.assertRegex(release["tree"]["sha256"], r"^[a-f0-9]{64}$")
        self.assertNotIn("legacy_supplements", versions["0.1.61"])
        self.assertEqual(2, len(versions["0.1.59"]["legacy_supplements"]))
        self.assertEqual(2, len(versions["0.1.60"]["legacy_supplements"]))

    def test_sticky_bar_has_clean_forward_and_exact_legacy_rollback(self):
        catalog = json.loads(
            (STACK_DIR / "manifests" / "component-catalog.json").read_text(encoding="utf-8")
        )
        registry = json.loads(
            (STACK_DIR / "manifests" / "stack-plugin-releases.json").read_text(encoding="utf-8")
        )
        entry = next(
            item for item in catalog["components"] if item["slug"] == "mrn-universal-sticky-bar"
        )
        versions = {
            item["version"]: item
            for item in registry["releases"]
            if item["slug"] == "mrn-universal-sticky-bar"
        }

        self.assertEqual("1.1.9", entry["version"])
        self.assertEqual({"1.1.8", "1.1.9"}, set(versions))
        self.assertEqual(1, len(versions["1.1.8"]["legacy_supplements"]))
        self.assertNotIn("legacy_supplements", versions["1.1.9"])


if __name__ == "__main__":
    unittest.main()

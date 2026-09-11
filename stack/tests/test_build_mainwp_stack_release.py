import importlib.util
import json
import os
import subprocess
import tempfile
import unittest
import zipfile
from pathlib import Path


SCRIPT_PATH = (
    Path(__file__).parents[1] / "scripts" / "build-mainwp-stack-release.py"
)
SPEC = importlib.util.spec_from_file_location("build_mainwp_stack_release", SCRIPT_PATH)
builder = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(builder)


class BuildMainWPStackReleaseTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name)
        self.artifacts = self.root / "artifacts"
        sources = {
            "mu-plugins/mrn-loader.php": "<?php /* Version: 1.0.0 */\n",
            "mu-plugins/mrn-example/mrn-example.php": "<?php /* Version: 1.0.0 */\n",
            "plugins/mrn-required/mrn-required.php": "<?php /* Plugin Name: Required Version: 1.0.0 */\n",
            "plugins/mrn-stack-deployment-agent/mrn-stack-deployment-agent.php": "<?php /* Plugin Name: Agent Version: 0.2.0 */\n",
            "shared/mrn-shared-runtime.php": "<?php /* Version: 1.0.0 */\n",
            "themes/mrn-base-stack/style.css": "/* Theme Name: Parent Version: 1.0.0 */\n",
            "themes/mrn-base-stack-child/style.css": "/* Theme Name: Child Version: 1.0.0 */\n",
        }
        for relative, contents in sources.items():
            path = self.artifacts / relative
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(contents, encoding="utf-8")

        def locked_component(slug, runtime_type, deployed_path, version="1.0.0"):
            digest, count = builder.release_lock.tree_sha256(
                self.artifacts / deployed_path
            )
            return {
                "slug": slug,
                "name": slug,
                "version": version,
                "runtime_type": runtime_type,
                "required": True,
                "deployed_path": deployed_path,
                "source": {
                    "repository": "MRN" if runtime_type != "standard-plugin" else slug,
                    "git_commit": "a" * 40,
                    "path": deployed_path,
                },
                "sha256": digest,
                "file_count": count,
            }

        def locked_theme(slug, role, mode, active):
            deployed_path = f"themes/{slug}"
            digest, count = builder.release_lock.tree_sha256(
                self.artifacts / deployed_path
            )
            return {
                "slug": slug,
                "version": "1.0.0",
                "active": active,
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
            "released_at": "2026-09-10T00:00:00Z",
            "source": {"repository": "MRN", "git_commit": "a" * 40},
            "stack_version": "fixture-release",
            "hash_algorithm": "sha256-tree-v1",
            "compatibility": {
                "minimum_loader_version": "1.0.0",
                "runtime_report_schema_version": 1,
            },
            "components": [
                locked_component(
                    "mrn-loader", "mu-loader", "mu-plugins/mrn-loader.php"
                ),
                locked_component(
                    "mrn-example", "mu-component", "mu-plugins/mrn-example"
                ),
                locked_component(
                    "mrn-required", "standard-plugin", "plugins/mrn-required"
                ),
                locked_component(
                    "mrn-shared-runtime", "shared-runtime", "shared"
                ),
                locked_component(
                    "mrn-stack-deployment-agent",
                    "standard-plugin",
                    "plugins/mrn-stack-deployment-agent",
                    "0.2.0",
                ),
            ],
            "themes": [
                locked_theme(
                    "mrn-base-stack", "parent-template", "exact", False
                ),
                locked_theme(
                    "mrn-base-stack-child",
                    "active-stylesheet-template",
                    "site-derived",
                    True,
                ),
            ],
        }
        self.lock_path = self.root / "lock.json"
        self.lock_path.write_text(json.dumps(self.lock) + "\n", encoding="utf-8")

    def tearDown(self):
        self.temporary.cleanup()

    def test_builds_exact_platform_package_without_child_or_agent_payloads(self):
        first = self.root / "first"
        second = self.root / "second"
        receipt = builder.build_release(
            self.lock_path, self.artifacts, first, "rollout-fixture-001"
        )
        second_receipt = builder.build_release(
            self.lock_path, self.artifacts, second, "rollout-fixture-001"
        )

        self.assertEqual(receipt["plan_sha256"], second_receipt["plan_sha256"])
        self.assertEqual(receipt["package_sha256"], second_receipt["package_sha256"])
        plan = json.loads((first / "plan.json").read_text(encoding="utf-8"))
        self.assertEqual(2, plan["schema_version"])
        self.assertEqual("mrn-base-stack", plan["site_contract"]["template"])
        self.assertEqual(
            "mrn-base-stack-child", plan["site_contract"]["stylesheet"]
        )
        self.assertEqual(
            "mrn-stack-deployment-agent", plan["prerequisites"][0]["slug"]
        )
        targets = [component["target"] for component in plan["components"]]
        self.assertIn("plugins/mrn-required", targets)
        self.assertIn("shared", targets)
        self.assertIn("themes/mrn-base-stack", targets)
        self.assertNotIn("themes/mrn-base-stack-child", targets)
        self.assertNotIn("plugins/mrn-stack-deployment-agent", targets)

        with zipfile.ZipFile(first / receipt["package_filename"]) as package:
            names = package.namelist()
        self.assertFalse(
            any(name.startswith("payload/themes/mrn-base-stack-child/") for name in names)
        )
        self.assertFalse(
            any(
                name.startswith("payload/plugins/mrn-stack-deployment-agent/")
                for name in names
            )
        )

    def test_generated_package_matches_dashboard_and_child_contracts(self):
        agent_root_value = os.environ.get("MRN_STACK_AGENT_ROOT")
        operations_root_value = os.environ.get("MRN_MAINWP_OPERATIONS_ROOT")
        if not agent_root_value and not operations_root_value:
            self.skipTest("standalone validator roots were not supplied")
        self.assertTrue(
            agent_root_value and operations_root_value,
            "both standalone validator roots are required",
        )

        agent_root = Path(agent_root_value).resolve()
        operations_root = Path(operations_root_value).resolve()
        validators = (
            (agent_root, Path("tests/regression.php")),
            (operations_root, Path("tests/stack-deployments-regression.php")),
        )
        for root, test_path in validators:
            self.assertTrue((root / test_path).is_file(), f"missing validator: {root}")

        output = self.root / "cross-repo-contract"
        builder.build_release(
            self.lock_path,
            self.artifacts,
            output,
            "rollout-contract-001",
        )
        environment = os.environ.copy()
        environment["MRN_STACK_CONTRACT_DIR"] = str(output)
        for root, test_path in validators:
            result = subprocess.run(
                ["php", str(test_path)],
                cwd=root,
                env=environment,
                check=False,
                capture_output=True,
                text=True,
                timeout=30,
            )
            self.assertEqual(
                0,
                result.returncode,
                f"{root.name} rejected the generated contract:\n"
                f"{result.stdout}{result.stderr}",
            )

    def test_refuses_agent_artifact_drift(self):
        agent_file = (
            self.artifacts
            / "plugins/mrn-stack-deployment-agent/mrn-stack-deployment-agent.php"
        )
        agent_file.write_text("<?php // drift\n", encoding="utf-8")
        with self.assertRaisesRegex(builder.BuildError, "deployment agent"):
            builder.build_release(
                self.lock_path,
                self.artifacts,
                self.root / "output",
                "rollout-fixture-002",
            )

    def test_refuses_optional_component_in_full_stack_projection(self):
        self.lock["components"][0]["required"] = False
        self.lock_path.write_text(json.dumps(self.lock) + "\n", encoding="utf-8")
        with self.assertRaisesRegex(builder.BuildError, "optional components"):
            builder.build_release(
                self.lock_path,
                self.artifacts,
                self.root / "optional-output",
                "rollout-fixture-003",
            )

    def test_refuses_agent_without_schema_two_support(self):
        agent = next(
            component
            for component in self.lock["components"]
            if component["slug"] == "mrn-stack-deployment-agent"
        )
        agent["version"] = "0.1.8"
        self.lock_path.write_text(json.dumps(self.lock) + "\n", encoding="utf-8")
        with self.assertRaisesRegex(builder.BuildError, "does not support schema 2"):
            builder.build_release(
                self.lock_path,
                self.artifacts,
                self.root / "old-agent-output",
                "rollout-fixture-004",
            )


if __name__ == "__main__":
    unittest.main()

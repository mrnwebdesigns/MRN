#!/usr/bin/env python3
"""Regression coverage for the deterministic MainWP MU package builder."""

from __future__ import annotations

import importlib.util
import json
import subprocess
import tempfile
import unittest
import zipfile
from pathlib import Path


SCRIPT = Path(__file__).with_name("build-mainwp-mu-release.py")
SPEC = importlib.util.spec_from_file_location("mrn_mu_builder", SCRIPT)
BUILDER = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(BUILDER)


class MainwpMuBuilderTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name)
        loader = self.root / "stack/mu-plugins/mrn-loader.php"
        component = self.root / "mu-plugins/mrn-example/mrn-example.php"
        wrapper = self.root / "stack/mu-plugins/mrn-example.php"
        loader.parent.mkdir(parents=True)
        component.parent.mkdir(parents=True)
        loader.write_text("<?php\n/* Version: 1.0.0 */\n", encoding="utf-8")
        component.write_text("<?php\n/* Version: 1.0.0 */\n", encoding="utf-8")
        wrapper.write_text("<?php\nrequire __DIR__ . '/mrn-example/mrn-example.php';\n", encoding="utf-8")

        loader_hash, loader_count = BUILDER.tree_sha256(loader)
        component_hash, component_count = BUILDER.tree_sha256(component.parent)
        self.lock_path = self.root / "stack/manifests/stack-release.lock.json"
        self.lock_path.parent.mkdir(parents=True)
        self.lock_path.write_text(
            json.dumps(
                {
                    "schema_version": 1,
                    "hash_algorithm": "sha256-tree-v1",
                    "release_id": "release-2026-08-23-r1",
                    "components": [
                        {
                            "slug": "mrn-loader",
                            "runtime_type": "mu-loader",
                            "deployed_path": "mu-plugins/mrn-loader.php",
                            "sha256": loader_hash,
                            "file_count": loader_count,
                            "source": {
                                "repository": "MRN",
                                "path": "stack/mu-plugins/mrn-loader.php",
                            },
                        },
                        {
                            "slug": "mrn-example",
                            "runtime_type": "mu-component",
                            "deployed_path": "mu-plugins/mrn-example",
                            "sha256": component_hash,
                            "file_count": component_count,
                            "source": {
                                "repository": "MRN",
                                "path": "mu-plugins/mrn-example",
                            },
                        },
                        {
                            "slug": "mrn-standard-plugin",
                            "runtime_type": "standard-plugin",
                            "deployed_path": "plugins/mrn-standard-plugin",
                        },
                    ],
                },
                sort_keys=True,
            )
            + "\n",
            encoding="utf-8",
        )
        self.output = self.root / "release"

    def tearDown(self):
        self.temporary.cleanup()

    def run_builder(self):
        return subprocess.run(
            [
                "python3",
                str(SCRIPT),
                "--repo-root",
                str(self.root),
                "--lock",
                str(self.lock_path),
                "--output-dir",
                str(self.output),
                "--rollout-id",
                "canary-2026-08-23-r1",
            ],
            capture_output=True,
            text=True,
            check=False,
        )

    def test_build_is_exact_and_reproducible(self):
        first = self.run_builder()
        self.assertEqual(0, first.returncode, first.stderr)
        package = self.output / "mrn-mu-release-canary-2026-08-23-r1.zip"
        first_hash = BUILDER.file_sha256(package)

        plan = json.loads((self.output / "plan.json").read_text(encoding="utf-8"))
        self.assertEqual(
            ["mrn-example", "mrn-loader", "mrn-stack-release-lock"],
            [component["slug"] for component in plan["components"]],
        )
        self.assertEqual(
            ["mu-plugins/mrn-example.php"],
            plan["components"][0]["legacy_paths"],
        )
        with zipfile.ZipFile(package) as archive:
            self.assertEqual(
                {
                    "plan.json",
                    "payload/mu-plugins/mrn-example/mrn-example.php",
                    "payload/mu-plugins/mrn-loader.php",
                    "payload/mu-plugins/mrn-stack-release.lock.json",
                },
                set(archive.namelist()),
            )

        second = self.run_builder()
        self.assertEqual(0, second.returncode, second.stderr)
        self.assertEqual(first_hash, BUILDER.file_sha256(package))

    def test_source_drift_fails_closed(self):
        source = self.root / "mu-plugins/mrn-example/mrn-example.php"
        source.write_text("<?php\n/* drift */\n", encoding="utf-8")
        result = self.run_builder()
        self.assertNotEqual(0, result.returncode)
        self.assertIn("does not match the release lock", result.stderr)


if __name__ == "__main__":
    unittest.main()

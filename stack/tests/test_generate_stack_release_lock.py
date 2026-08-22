import importlib.util
import json
import os
import subprocess
import tempfile
import unittest
from pathlib import Path


SCRIPT_PATH = Path(__file__).parents[1] / "scripts" / "generate-stack-release-lock.py"
SPEC = importlib.util.spec_from_file_location("generate_stack_release_lock", SCRIPT_PATH)
release_lock = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(release_lock)


class ReleaseLockTests(unittest.TestCase):
    def test_tree_hash_is_stable_across_creation_order(self):
        with tempfile.TemporaryDirectory() as first, tempfile.TemporaryDirectory() as second:
            first_path = Path(first)
            second_path = Path(second)
            (first_path / "b.txt").write_text("second\n", encoding="utf-8")
            (first_path / "a.txt").write_text("first\n", encoding="utf-8")
            (second_path / "a.txt").write_text("first\n", encoding="utf-8")
            (second_path / "b.txt").write_text("second\n", encoding="utf-8")

            self.assertEqual(
                release_lock.tree_sha256(first_path),
                release_lock.tree_sha256(second_path),
            )

    def test_tree_hash_ignores_packaging_noise(self):
        with tempfile.TemporaryDirectory() as root:
            root_path = Path(root)
            (root_path / "plugin.php").write_text("runtime\n", encoding="utf-8")
            baseline = release_lock.tree_sha256(root_path)
            (root_path / ".DS_Store").write_text("noise", encoding="utf-8")
            (root_path / "node_modules").mkdir()
            (root_path / "node_modules" / "dependency.js").write_text(
                "noise", encoding="utf-8"
            )

            self.assertEqual(baseline, release_lock.tree_sha256(root_path))

    def test_tree_hash_rejects_symlinks(self):
        with tempfile.TemporaryDirectory() as root:
            root_path = Path(root)
            target = root_path / "target.php"
            target.write_text("runtime\n", encoding="utf-8")
            os.symlink(target, root_path / "linked.php")

            with self.assertRaisesRegex(release_lock.ReleaseLockError, "symlinks"):
                release_lock.tree_sha256(root_path)

    def test_validate_lock_rejects_duplicate_components(self):
        source = {"repository": "MRN", "git_commit": "a" * 40, "path": "."}
        component = {
            "slug": "mrn-loader",
            "name": "MRN Loader",
            "version": "1.0.0",
            "runtime_type": "mu-loader",
            "required": True,
            "deployed_path": "mu-plugins/mrn-loader.php",
            "source": source,
            "sha256": "b" * 64,
            "file_count": 1,
        }
        payload = {
            "schema_version": 1,
            "release_id": "fixture",
            "released_at": "2026-08-22T00:00:00Z",
            "source": source,
            "stack_version": "fixture",
            "hash_algorithm": "sha256-tree-v1",
            "compatibility": {
                "minimum_loader_version": "1.0.0",
                "runtime_report_schema_version": 1,
            },
            "components": [component, dict(component)],
            "themes": [
                {
                    "slug": "fixture",
                    "verification_mode": "exact",
                    "deployment_role": "parent-template",
                }
            ],
        }

        with self.assertRaisesRegex(release_lock.ReleaseLockError, "duplicate"):
            release_lock.validate_lock(payload)

    def test_validate_lock_requires_shared_runtime(self):
        payload = {
            "schema_version": 1,
            "release_id": "fixture",
            "released_at": "2026-08-22T00:00:00Z",
            "source": {"repository": "MRN", "git_commit": "a" * 40},
            "stack_version": "fixture",
            "hash_algorithm": "sha256-tree-v1",
            "compatibility": {
                "minimum_loader_version": "1.0.0",
                "runtime_report_schema_version": 1,
            },
            "components": [
                {
                    "slug": "mrn-loader",
                    "runtime_type": "mu-loader",
                    "deployed_path": "mu-plugins/mrn-loader.php",
                }
            ],
            "themes": [
                {
                    "slug": "mrn-base-stack",
                    "verification_mode": "exact",
                    "deployment_role": "parent-template",
                }
            ],
        }

        with self.assertRaisesRegex(release_lock.ReleaseLockError, "shared-runtime"):
            release_lock.validate_lock(payload)

    def test_header_version_parser_reads_wordpress_header(self):
        with tempfile.TemporaryDirectory() as root:
            path = Path(root) / "plugin.php"
            path.write_text("<?php\n/**\n * Version: 1.2.3\n */\n", encoding="utf-8")

            self.assertEqual("1.2.3", release_lock.read_header_version(path))

    def test_active_manifest_theme_is_site_derived(self):
        with tempfile.TemporaryDirectory() as root:
            manifest = Path(root) / "themes.txt"
            manifest.write_text(
                "/packages/mrn-base-stack.zip\n"
                "/packages/mrn-base-stack-child.zip|active\n",
                encoding="utf-8",
            )

            themes = release_lock.parse_theme_manifest(manifest)

            self.assertFalse(themes[0]["active"])
            self.assertTrue(themes[1]["active"])


if __name__ == "__main__":
    unittest.main()

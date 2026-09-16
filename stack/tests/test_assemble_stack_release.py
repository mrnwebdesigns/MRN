import importlib.util
import json
import os
import subprocess
import tempfile
import unittest
from unittest import mock
from pathlib import Path


SCRIPT_PATH = Path(__file__).parents[1] / "scripts" / "assemble-stack-release.py"
SPEC = importlib.util.spec_from_file_location("assemble_stack_release", SCRIPT_PATH)
assembler = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(assembler)


class AssembleStackReleaseTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name)
        self.repo = self.root / "MRN"
        self.repo.mkdir()
        subprocess.run(["git", "init", "-q", self.repo], check=True)
        subprocess.run(
            ["git", "-C", self.repo, "config", "user.email", "test@example.com"],
            check=True,
        )
        subprocess.run(
            ["git", "-C", self.repo, "config", "user.name", "Test"], check=True
        )
        loader = self.repo / "stack/mu-plugins/mrn-loader.php"
        loader.parent.mkdir(parents=True)
        loader.write_text("<?php /* Version: 1.0.0 */\n", encoding="utf-8")
        theme = self.repo / "stack/themes/mrn-base-stack"
        theme.mkdir(parents=True)
        (theme / "style.css").write_text("/* Version: 1.0.0 */\n", encoding="utf-8")
        shared = self.repo / "shared"
        shared.mkdir()
        (shared / "mrn-shared-runtime.php").write_text(
            "<?php /* Version: 1.0.0 */\n", encoding="utf-8"
        )
        subprocess.run(["git", "-C", self.repo, "add", "."], check=True)
        subprocess.run(["git", "-C", self.repo, "commit", "-qm", "fixture"], check=True)
        self.commit = subprocess.run(
            ["git", "-C", self.repo, "rev-parse", "HEAD"],
            check=True,
            capture_output=True,
            text=True,
        ).stdout.strip()

        loader_hash, loader_count = assembler.release_lock.tree_sha256(loader)
        theme_hash, theme_count = assembler.release_lock.tree_sha256(theme)
        shared_hash, shared_count = assembler.release_lock.tree_sha256(shared)
        self.lock = {
            "schema_version": 1,
            "release_id": "fixture",
            "released_at": "2026-08-22T00:00:00Z",
            "source": {"repository": "MRN", "git_commit": self.commit},
            "stack_version": "fixture",
            "hash_algorithm": "sha256-tree-v1",
            "compatibility": {
                "minimum_loader_version": "1.0.0",
                "runtime_report_schema_version": 1,
            },
            "components": [
                {
                    "slug": "mrn-loader",
                    "name": "MRN Loader",
                    "version": "1.0.0",
                    "runtime_type": "mu-loader",
                    "required": True,
                    "deployed_path": "mu-plugins/mrn-loader.php",
                    "source": {
                        "repository": "MRN",
                        "git_commit": self.commit,
                        "path": "stack/mu-plugins/mrn-loader.php",
                    },
                    "sha256": loader_hash,
                    "file_count": loader_count,
                },
                {
                    "slug": "mrn-shared-runtime",
                    "name": "MRN Shared Runtime",
                    "version": "1.0.0",
                    "runtime_type": "shared-runtime",
                    "required": True,
                    "deployed_path": "shared",
                    "source": {
                        "repository": "MRN",
                        "git_commit": self.commit,
                        "path": "shared",
                    },
                    "sha256": shared_hash,
                    "file_count": shared_count,
                },
            ],
            "themes": [
                {
                    "slug": "mrn-base-stack",
                    "version": "1.0.0",
                    "active": False,
                    "verification_mode": "exact",
                    "deployment_role": "parent-template",
                    "deployed_path": "themes/mrn-base-stack",
                    "source": {
                        "repository": "MRN",
                        "git_commit": self.commit,
                        "path": "stack/themes/mrn-base-stack",
                    },
                    "sha256": theme_hash,
                    "file_count": theme_count,
                }
            ],
        }
        self.lock_path = self.root / "lock.json"
        self.lock_path.write_text(json.dumps(self.lock) + "\n", encoding="utf-8")

    def tearDown(self):
        self.temporary.cleanup()

    def test_assembles_only_locked_paths_and_embeds_release_lock(self):
        output = self.root / "release"

        metadata = assembler.assemble(
            self.lock_path, self.repo, self.root / "plugins", output
        )

        self.assertEqual("fixture", metadata["release_id"])
        self.assertTrue((output / "mu-plugins/mrn-loader.php").is_file())
        self.assertTrue((output / "themes/mrn-base-stack/style.css").is_file())
        self.assertTrue((output / "shared/mrn-shared-runtime.php").is_file())
        self.assertTrue((output / "mu-plugins/mrn-stack-release.lock.json").is_file())

    def test_source_change_after_locked_commit_is_rejected(self):
        loader = self.repo / "stack/mu-plugins/mrn-loader.php"
        loader.write_text("<?php /* Version: 2.0.0 */\n", encoding="utf-8")
        subprocess.run(["git", "-C", self.repo, "add", "."], check=True)
        subprocess.run(["git", "-C", self.repo, "commit", "-qm", "change"], check=True)

        with self.assertRaisesRegex(assembler.AssemblyError, "changed after locked"):
            assembler.assemble(
                self.lock_path,
                self.repo,
                self.root / "plugins",
                self.root / "release",
            )

    def test_dirty_locked_source_is_rejected(self):
        loader = self.repo / "stack/mu-plugins/mrn-loader.php"
        loader.write_text("dirty\n", encoding="utf-8")

        with self.assertRaisesRegex(assembler.AssemblyError, "dirty"):
            assembler.assemble(
                self.lock_path,
                self.repo,
                self.root / "plugins",
                self.root / "release",
            )

    def test_cli_default_standalone_root_is_independent_of_repo_location(self):
        expected = Path.home() / "Development/MRN-plugins"

        with mock.patch.dict(os.environ, {}, clear=True), mock.patch.object(
            assembler, "assemble", return_value={"release_id": "fixture"}
        ) as assemble:
            result = assembler.main(
                [
                    "--release-lock",
                    str(self.lock_path),
                    "--repo-root",
                    str(self.repo),
                    "--output",
                    str(self.root / "release"),
                ]
            )

        self.assertEqual(0, result)
        self.assertEqual(expected, assemble.call_args.args[2])

    def test_external_plugin_assembly_honors_export_ignore(self):
        plugins_root = self.root / "plugins"
        plugin = plugins_root / "mrn-fixture-plugin"
        plugin.mkdir(parents=True)
        subprocess.run(["git", "init", "-q", str(plugin)], check=True)
        subprocess.run(
            ["git", "-C", str(plugin), "config", "user.email", "test@example.com"],
            check=True,
        )
        subprocess.run(
            ["git", "-C", str(plugin), "config", "user.name", "Test"], check=True
        )
        (plugin / "mrn-fixture-plugin.php").write_text(
            "<?php /* Version: 1.0.0 */\n", encoding="utf-8"
        )
        (plugin / "repo-only.txt").write_text("not deployed\n", encoding="utf-8")
        (plugin / ".gitattributes").write_text(
            ".gitattributes export-ignore\nrepo-only.txt export-ignore\n",
            encoding="utf-8",
        )
        subprocess.run(["git", "-C", str(plugin), "add", "."], check=True)
        subprocess.run(
            ["git", "-C", str(plugin), "commit", "-qm", "fixture plugin"], check=True
        )
        commit = subprocess.run(
            ["git", "-C", str(plugin), "rev-parse", "HEAD"],
            check=True,
            capture_output=True,
            text=True,
        ).stdout.strip()
        archive_files = assembler.release_lock.git_archive_files(plugin, commit)
        plugin_hash, plugin_count = assembler.release_lock.bytes_tree_sha256(archive_files)
        self.lock["components"].append(
            {
                "slug": "mrn-fixture-plugin",
                "name": "Fixture Plugin",
                "version": "1.0.0",
                "runtime_type": "standard-plugin",
                "required": True,
                "deployed_path": "plugins/mrn-fixture-plugin",
                "source": {
                    "repository": "mrn-fixture-plugin",
                    "git_commit": commit,
                    "path": ".",
                },
                "sha256": plugin_hash,
                "file_count": plugin_count,
            }
        )
        self.lock_path.write_text(json.dumps(self.lock) + "\n", encoding="utf-8")
        output = self.root / "release-with-plugin"

        assembler.assemble(self.lock_path, self.repo, plugins_root, output)

        deployed = output / "plugins/mrn-fixture-plugin"
        self.assertTrue((deployed / "mrn-fixture-plugin.php").is_file())
        self.assertFalse((deployed / "repo-only.txt").exists())
        self.assertFalse((deployed / ".gitattributes").exists())


if __name__ == "__main__":
    unittest.main()

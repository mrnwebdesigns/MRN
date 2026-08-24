import importlib.util
import json
import subprocess
import tempfile
import unittest
from pathlib import Path


SCRIPT_PATH = Path(__file__).parents[1] / "scripts" / "qa-stack-promotion.py"
SPEC = importlib.util.spec_from_file_location("qa_stack_promotion", SCRIPT_PATH)
promotion = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(promotion)


def run(*command, cwd, check=True):
    result = subprocess.run(
        [str(item) for item in command],
        cwd=cwd,
        capture_output=True,
        text=True,
        check=False,
    )
    if check and result.returncode != 0:
        raise AssertionError(result.stderr or result.stdout)
    return result


def component(slug, version, source_path, digest, source_commit, runtime_type):
    deployed_path = (
        "shared"
        if runtime_type == "shared-runtime"
        else f"mu-plugins/{slug}.php"
        if runtime_type == "mu-loader"
        else f"mu-plugins/{slug}"
    )
    return {
        "slug": slug,
        "name": slug,
        "version": version,
        "runtime_type": runtime_type,
        "required": True,
        "deployed_path": deployed_path,
        "source": {
            "repository": "MRN",
            "git_commit": source_commit,
            "path": source_path,
        },
        "sha256": digest,
        "file_count": 1,
    }


def release_payload(release_id, source_commit, example_version, example_digest):
    return {
        "schema_version": 1,
        "release_id": release_id,
        "released_at": "2026-08-24T00:00:00Z",
        "source": {"repository": "MRN", "git_commit": source_commit},
        "stack_version": release_id,
        "hash_algorithm": "sha256-tree-v1",
        "compatibility": {
            "minimum_loader_version": "1.0.0",
            "runtime_report_schema_version": 1,
        },
        "components": [
            component(
                "example",
                example_version,
                "mu-plugins/example",
                example_digest,
                source_commit,
                "mu-component",
            ),
            component(
                "mrn-loader",
                "1.0.0",
                "stack/mu-plugins/mrn-loader.php",
                "b" * 64,
                source_commit,
                "mu-loader",
            ),
            component(
                "mrn-shared-runtime",
                "1.0.0",
                "shared",
                "c" * 64,
                source_commit,
                "shared-runtime",
            ),
        ],
        "themes": [
            {
                "slug": "base-theme",
                "version": "1.0.0",
                "active": False,
                "verification_mode": "exact",
                "deployment_role": "parent-template",
                "deployed_path": "themes/base-theme",
                "source": {
                    "repository": "MRN",
                    "git_commit": source_commit,
                    "path": "stack/themes/base-theme",
                },
                "sha256": "d" * 64,
                "file_count": 1,
            }
        ],
    }


class GitFixture:
    def __init__(self, root):
        self.root = Path(root) / "repo"
        self.origin = Path(root) / "origin.git"
        self.root.mkdir()
        run("git", "init", "--initial-branch=main", cwd=self.root)
        run("git", "config", "user.email", "qa@example.com", cwd=self.root)
        run("git", "config", "user.name", "QA Fixture", cwd=self.root)
        run("git", "init", "--bare", self.origin, cwd=self.root)
        run("git", "remote", "add", "origin", self.origin, cwd=self.root)
        self._write_initial_source()
        run("git", "add", ".", cwd=self.root)
        run("git", "commit", "-m", "Initial release source", cwd=self.root)
        self.source_commit = run("git", "rev-parse", "HEAD", cwd=self.root).stdout.strip()
        self.baseline = release_payload(
            "release-1", self.source_commit, "1.0.0", "a" * 64
        )
        self.lock_path.write_text(
            json.dumps(self.baseline, indent=2) + "\n", encoding="utf-8"
        )
        run("git", "add", self.lock_path, cwd=self.root)
        run("git", "commit", "-m", "Lock initial release", cwd=self.root)
        run("git", "tag", "baseline-lock", cwd=self.root)
        run("git", "push", "-u", "origin", "main", "--tags", cwd=self.root)

    @property
    def lock_path(self):
        return self.root / "stack/manifests/stack-release.lock.json"

    @property
    def catalog_path(self):
        return self.root / "stack/manifests/component-catalog.json"

    def _write_initial_source(self):
        files = {
            "mu-plugins/example/example.php": "<?php\n/** Version: 1.0.0 */\n",
            "shared/runtime.php": "<?php\n",
            "stack/mu-plugins/mrn-loader.php": "<?php\n/** Version: 1.0.0 */\n",
            "stack/themes/base-theme/style.css": "/* Version: 1.0.0 */\n",
            "stack/STACK_VERSION.md": (
                "# Stack Version\n- Stack release: `release-1`\n"
                "- Release date: `2026-08-24`\n"
            ),
            "stack/CHANGELOG.md": "# Stack Changelog\n\n## release-1\n- Initial.\n",
        }
        for relative, content in files.items():
            path = self.root / relative
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(content, encoding="utf-8")
        catalog = {
            "components": [
                {
                    "slug": "example",
                    "target_tier": "platform-required",
                    "runtime_type": "mu-component",
                    "source": {"repository": "MRN", "path": "mu-plugins/example"},
                },
                {
                    "slug": "optional-example",
                    "target_tier": "optional-shared",
                    "runtime_type": "standard-plugin",
                    "source": {"repository": "MRN", "path": "plugins/optional-example"},
                },
            ]
        }
        self.catalog_path.parent.mkdir(parents=True, exist_ok=True)
        self.catalog_path.write_text(json.dumps(catalog) + "\n", encoding="utf-8")

    def add_unreleased_change(self):
        (self.root / "mu-plugins/example/example.php").write_text(
            "<?php\n/** Version: 1.1.0 */\n", encoding="utf-8"
        )
        (self.root / "stack/STACK_VERSION.md").write_text(
            "# Stack Version\n- Stack release: `release-2`\n"
            "- Release date: `2026-08-24`\n",
            encoding="utf-8",
        )
        (self.root / "stack/CHANGELOG.md").write_text(
            "# Stack Changelog\n\n## release-2\n- Update.\n\n## release-1\n- Initial.\n",
            encoding="utf-8",
        )
        run("git", "add", ".", cwd=self.root)
        run("git", "commit", "-m", "Change required component", cwd=self.root)
        self.candidate_source = run("git", "rev-parse", "HEAD", cwd=self.root).stdout.strip()
        run("git", "push", "origin", "main", cwd=self.root)

    def add_candidate_lock(self, version="1.1.0"):
        candidate = release_payload(
            "release-2", self.candidate_source, version, "e" * 64
        )
        self.lock_path.write_text(
            json.dumps(candidate, indent=2) + "\n", encoding="utf-8"
        )
        run("git", "add", self.lock_path, cwd=self.root)
        run("git", "commit", "-m", "Lock candidate release", cwd=self.root)


class PromotionTests(unittest.TestCase):
    def test_change_classification_separates_release_units(self):
        catalog = {
            "components": [
                {
                    "slug": "required",
                    "target_tier": "platform-required",
                    "runtime_type": "mu-component",
                    "source": {"repository": "MRN", "path": "mu-plugins/required"},
                },
                {
                    "slug": "optional",
                    "target_tier": "optional-shared",
                    "runtime_type": "standard-plugin",
                    "source": {"repository": "MRN", "path": "plugins/optional"},
                },
            ]
        }
        inventory = promotion.classify_changes(
            [
                "mu-plugins/required/main.php",
                "plugins/optional/main.php",
                "plugins/unknown/main.php",
                "stack/scripts/deploy-stack-release-to-site.py",
                "docs/notes.md",
            ],
            catalog,
            {"themes": []},
        )

        self.assertEqual(["required"], [item["slug"] for item in inventory["required_components"]])
        self.assertEqual(
            ["optional"],
            [item["slug"] for item in inventory["independent_release_units"]],
        )
        self.assertEqual(["plugins/unknown/main.php"], inventory["untracked_deployables"])
        self.assertEqual(
            ["stack/scripts/deploy-stack-release-to-site.py"],
            inventory["deployment_contracts"],
        )

    def test_external_inventory_detects_default_branch_drift(self):
        with tempfile.TemporaryDirectory() as root:
            standalone = Path(root) / "standalone"
            repository = standalone / "required-external"
            origin = Path(root) / "required-external.git"
            repository.mkdir(parents=True)
            run("git", "init", "--initial-branch=main", cwd=repository)
            run("git", "config", "user.email", "qa@example.com", cwd=repository)
            run("git", "config", "user.name", "QA Fixture", cwd=repository)
            (repository / "plugin.php").write_text("<?php\n", encoding="utf-8")
            run("git", "add", ".", cwd=repository)
            run("git", "commit", "-m", "Initial", cwd=repository)
            run("git", "init", "--bare", origin, cwd=repository)
            run("git", "remote", "add", "origin", origin, cwd=repository)
            run("git", "push", "-u", "origin", "main", cwd=repository)

            inventory = promotion.external_source_inventory(
                {
                    "components": [
                        {
                            "slug": "required-external",
                            "source": {
                                "repository": "required-external",
                                "git_commit": "0" * 40,
                            },
                        }
                    ]
                },
                standalone,
            )

            self.assertEqual("drift", inventory[0]["status"])
            self.assertEqual(
                run("git", "rev-parse", "HEAD", cwd=repository).stdout.strip(),
                inventory[0]["default_commit"],
            )

    def test_audit_blocks_required_changes_after_current_lock(self):
        with tempfile.TemporaryDirectory() as root:
            fixture = GitFixture(root)
            fixture.add_unreleased_change()
            result = run(
                "python3",
                SCRIPT_PATH,
                "--repo-root",
                fixture.root,
                "--lock",
                fixture.lock_path,
                "--catalog",
                fixture.catalog_path,
                cwd=fixture.root,
                check=False,
            )

            self.assertEqual(1, result.returncode)
            report = json.loads(result.stdout)
            self.assertEqual("blocked", report["status"])
            self.assertEqual(
                ["example"],
                [item["slug"] for item in report["inventory"]["required_components"]],
            )

    def test_candidate_passes_with_versioned_locked_change_from_origin_main(self):
        with tempfile.TemporaryDirectory() as root:
            fixture = GitFixture(root)
            fixture.add_unreleased_change()
            fixture.add_candidate_lock()
            result = run(
                "python3",
                SCRIPT_PATH,
                "--repo-root",
                fixture.root,
                "--lock",
                fixture.lock_path,
                "--catalog",
                fixture.catalog_path,
                "--mode",
                "candidate",
                cwd=fixture.root,
                check=False,
            )

            self.assertEqual(0, result.returncode, result.stderr)
            report = json.loads(result.stdout)
            self.assertEqual("pass", report["status"])
            self.assertEqual("release-2", report["candidate"]["release_id"])

    def test_candidate_blocks_changed_hash_without_version_bump(self):
        with tempfile.TemporaryDirectory() as root:
            fixture = GitFixture(root)
            fixture.add_unreleased_change()
            fixture.add_candidate_lock(version="1.0.0")
            result = run(
                "python3",
                SCRIPT_PATH,
                "--repo-root",
                fixture.root,
                "--lock",
                fixture.lock_path,
                "--catalog",
                fixture.catalog_path,
                "--mode",
                "candidate",
                "--baseline-ref",
                "baseline-lock",
                cwd=fixture.root,
                check=False,
            )

            self.assertEqual(1, result.returncode)
            self.assertIn("no version bump", result.stderr)


if __name__ == "__main__":
    unittest.main()

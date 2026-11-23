import datetime as dt
import importlib.util
import io
import json
import os
import shlex
import subprocess
import tempfile
import unittest
from contextlib import redirect_stderr, redirect_stdout
from pathlib import Path
from types import SimpleNamespace


SCRIPT_PATH = Path(__file__).parents[1] / "scripts" / "stack-first-adoption.py"
SPEC = importlib.util.spec_from_file_location("stack_first_adoption", SCRIPT_PATH)
adopt = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(adopt)
release_lock = adopt.release_lock

FIXED_NOW = dt.datetime(2026, 11, 22, 12, 0, tzinfo=dt.timezone.utc)
SITE_URL = "https://example.mrndev.io"
LOGIN = "example@site-owner"

PAYLOAD = {
    "mu-plugins/mrn-loader.php": (
        "<?php\n/**\n * Plugin Name: MRN Loader\n */\n"
        "$e = WP_CONTENT_DIR . '/mu-plugins/mrn-comp/mrn-comp.php';\n"
    ),
    "mu-plugins/mrn-comp.php": "<?php\n/* Plugin Name: MRN Comp Loader */\nrequire_once __DIR__ . '/mrn-comp/mrn-comp.php';\n",
    "mu-plugins/mrn-comp/mrn-comp.php": "<?php\n/* Plugin Name: MRN Comp */\nfunction mrn_comp_boot() {}\n",
    "mu-plugins/mrn-comp/assets/app.css": "body{}\n",
    "plugins/mrn-required/mrn-required.php": "<?php\n/**\n * Plugin Name: MRN Required\n */\nfunction mrn_required_boot() {}\n",
    "shared/mrn-shared-runtime.php": "<?php\n",
    "themes/mrn-base-stack/style.css": "/* Theme Name: MRN Base Stack */\n",
    "themes/mrn-base-stack/functions.php": "<?php\nfunction mrn_base_stack_setup() {}\n",
    "themes/mrn-base-stack-child/style.css": "/* Template: mrn-base-stack */\n",
}

LEGACY = {
    "wp-content/themes/legacy-parent/style.css": "/* Theme Name: Legacy */\n",
    "wp-content/themes/legacy-parent/functions.php": "<?php\nfunction mrn_base_stack_setup() {}\n",
    "wp-content/themes/legacy-child/style.css": "/* Template: legacy-parent */\n",
    "wp-content/mu-plugins/mrn-loader.php": (
        "<?php\n/* Plugin Name: MRN Loader (MU) */\n"
        "$e = array(WP_CONTENT_DIR . '/mu-plugins/mrn-comp/mrn-comp.php',"
        " WP_CONTENT_DIR . '/mu-plugins/mrn-retired/mrn-retired.php');\n"
    ),
    "wp-content/mu-plugins/mrn-comp/mrn-comp.php": "<?php\n/* Plugin Name: MRN Comp */\nfunction mrn_comp_boot() {}\n",
    "wp-content/mu-plugins/mrn-comp/old-file.php": "<?php\n",
    "wp-content/mu-plugins/mrn-retired/mrn-retired.php": "<?php\n",
    "wp-content/plugins/mrn-required/mrn-required.php": "<?php\n/**\n * Plugin Name: MRN Required\n */\n",
    "wp-content/shared/mrn-shared-runtime.php": "<?php\n",
}


def write_tree(root, files):
    for relative, contents in files.items():
        path = root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(contents, encoding="utf-8")


class FakeTarget:
    """Runs the remote shell locally against a temp site root; stubs wp/preflight."""

    def __init__(self, base):
        self.site = base / "site"
        self.home = base / "home"
        self.site.mkdir()
        self.home.mkdir()
        self.state = {
            "home_url": SITE_URL,
            "environment_type": "development",
            "template": "legacy-parent",
            "stylesheet": "legacy-child",
            "active_plugins": ["mrn-required/mrn-required.php"],
            "is_multisite": False,
        }
        self.calls = []
        self.backups = []
        self.backup_ok = True
        self.load_ok = True

    def __call__(self, command):
        command = [str(item) for item in command]
        self.calls.append(command)
        if command[0].endswith("preflight-live-site-deploy.sh"):
            out = (
                "SITE_HOSTNAME=example.mrndev.io\nSITE_USER=example\n"
                f"SITE_ROOT={self.site}\nSSH_ALIAS=site-owner\nSSH_LOGIN={LOGIN}\n"
            )
            if "--with-db-backup" in command:
                label = command[command.index("--backup-label") + 1]
                self.backups.append(label)
                if self.backup_ok:
                    out += f"BACKUP_LABEL={label}\n"
            return SimpleNamespace(stdout=out)
        if command[0] == "ssh":
            assert command[1] == LOGIN
            if command[2].startswith("wp "):
                return SimpleNamespace(stdout=self.wp(shlex.split(command[2])[2:]))
            result = subprocess.run(
                ["bash", "-c", command[2]],
                capture_output=True,
                text=True,
                env={**os.environ, "HOME": str(self.home)},
            )
            if result.returncode:
                raise adopt.deploy.DeployError(result.stderr or "remote failed")
            return SimpleNamespace(stdout=result.stdout)
        if command[0] == "rsync":
            local = [item.split(":", 1)[1] if item.startswith(LOGIN + ":") else item for item in command]
            subprocess.run(local, check=True, capture_output=True)
            return SimpleNamespace(stdout="")
        raise AssertionError(f"unexpected command {command}")

    def wp(self, arguments):
        if arguments[0] == "eval" and adopt.STATE_MARKER in arguments[1]:
            return adopt.STATE_MARKER + json.dumps(self.state) + "\n"
        if arguments[0] == "eval" and adopt.LOAD_MARKER in arguments[1]:
            if not self.load_ok:
                raise adopt.deploy.DeployError("PHP Fatal error")
            return adopt.LOAD_MARKER
        raise AssertionError(f"unexpected wp command {arguments}")


class StackFirstAdoptionTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.base = Path(self.temporary.name).resolve()
        self.artifacts = self.base / "payload"
        write_tree(self.artifacts, PAYLOAD)

        def entry(slug, runtime_type, path):
            digest, count = release_lock.tree_sha256(self.artifacts / path)
            return {
                "slug": slug, "name": slug, "version": "1.0.0", "runtime_type": runtime_type,
                "required": True, "deployed_path": path,
                "source": {"repository": "MRN", "git_commit": "a" * 40, "path": path},
                "sha256": digest, "file_count": count,
            }

        def theme(slug, mode, role):
            path = f"themes/{slug}"
            digest, count = release_lock.tree_sha256(self.artifacts / path)
            return {
                "slug": slug, "version": "1.0.0", "active": mode == "site-derived",
                "verification_mode": mode, "deployment_role": role, "deployed_path": path,
                "source": {"repository": "MRN", "git_commit": "a" * 40, "path": path},
                "sha256": digest, "file_count": count,
            }

        lock = {
            "schema_version": 1, "release_id": "fixture-release",
            "released_at": "2026-11-21T00:00:00Z",
            "source": {"repository": "MRN", "git_commit": "a" * 40},
            "stack_version": "fixture-release", "hash_algorithm": "sha256-tree-v1",
            "compatibility": {"minimum_loader_version": "1.0.0", "runtime_report_schema_version": 1},
            "components": [
                entry("mrn-loader", "mu-loader", "mu-plugins/mrn-loader.php"),
                entry("mrn-comp-loader", "mu-loader", "mu-plugins/mrn-comp.php"),
                entry("mrn-comp", "mu-component", "mu-plugins/mrn-comp"),
                entry("mrn-required", "standard-plugin", "plugins/mrn-required"),
                entry("mrn-shared-runtime", "shared-runtime", "shared"),
            ],
            "themes": [
                theme("mrn-base-stack", "exact", "parent-template"),
                theme("mrn-base-stack-child", "site-derived", "active-stylesheet-template"),
            ],
        }
        self.lock_path = self.base / "lock.json"
        self.lock_path.write_text(json.dumps(lock) + "\n", encoding="utf-8")
        (self.artifacts / "mu-plugins/mrn-stack-release.lock.json").write_bytes(self.lock_path.read_bytes())
        self.target = FakeTarget(self.base)
        write_tree(self.target.site, LEGACY)
        self.journal = self.base / "receipts" / "journal.json"
        self.page = (200, '<link href="/wp-content/themes/legacy-child/style.css">')

    def tearDown(self):
        self.temporary.cleanup()

    def site_args(self, command, *extra):
        return [
            command, "--release-lock", str(self.lock_path), "--artifact-root", str(self.artifacts),
            "--site-url", SITE_URL, "--expected-environment", "development",
            "--expected-template", "legacy-parent", "--expected-stylesheet", "legacy-child",
            "--expected-site-root", str(self.target.site), "--discovery-ssh-host", "fixture", *extra,
        ]

    def run_main(self, argv):
        out, err = io.StringIO(), io.StringIO()
        with redirect_stdout(out), redirect_stderr(err):
            code = adopt.main(argv, runner=self.target, now=FIXED_NOW, fetch=lambda url: self.page)
        return code, out.getvalue(), err.getvalue()

    def plan(self):
        code, out, err = self.run_main(self.site_args("plan"))
        self.assertEqual(0, code, err)
        return json.loads(out[: out.rindex("}") + 1])

    def apply(self, plan_hash):
        return self.run_main(self.site_args("apply", "--plan-hash", plan_hash, "--receipt-out", str(self.journal)))

    def tree(self, relative):
        path = self.target.site / relative
        return release_lock.tree_sha256(path)[0] if path.exists() else None

    def writes(self):
        return [c for c in self.target.calls if c[0] == "rsync" or "tar -czf" in " ".join(c)]

    def test_remote_tree_hash_matches_release_lock_algorithm(self):
        ctx = {"ssh_login": LOGIN, "site_root": str(self.artifacts)}
        trees = adopt.read_trees(ctx, ["themes/mrn-base-stack", "mu-plugins/mrn-loader.php", "missing"], self.target)
        self.assertEqual(release_lock.tree_sha256(self.artifacts / "themes/mrn-base-stack")[0], trees["themes/mrn-base-stack"]["sha256"])
        self.assertEqual(release_lock.tree_sha256(self.artifacts / "mu-plugins/mrn-loader.php")[0], trees["mu-plugins/mrn-loader.php"]["sha256"])
        self.assertEqual("absent", trees["missing"]["type"])

    def test_plan_is_read_only_and_reports_states_and_collisions(self):
        before = {p: p.read_bytes() for p in self.target.site.rglob("*") if p.is_file()}
        plan = self.plan()
        after = {p: p.read_bytes() for p in self.target.site.rglob("*") if p.is_file()}
        self.assertEqual(before, after)
        self.assertEqual([], self.writes())

        rows = {row["target"]: row for row in plan["operations"]}
        self.assertEqual("add", rows["wp-content/themes/mrn-base-stack"]["action"])
        self.assertEqual("replace", rows["wp-content/mu-plugins/mrn-comp"]["action"])
        self.assertEqual(["old-file.php"], rows["wp-content/mu-plugins/mrn-comp"]["files_removed"])
        self.assertEqual("skip", rows["wp-content/shared"]["action"])
        self.assertEqual("add", rows["wp-content/mu-plugins/mrn-stack-release.lock.json"]["action"])
        self.assertNotIn("wp-content/themes/legacy-parent", rows)
        self.assertNotIn("wp-content/themes/legacy-child", rows)
        self.assertNotIn("wp-content/themes/mrn-base-stack-child", rows)
        self.assertEqual("not-installed", plan["child_theme"]["action"])
        kinds = {finding["kind"] for finding in plan["collisions"]}
        self.assertIn("stops_loading", kinds)
        # The inactive new parent declares the same function as the legacy parent; not a collision.
        self.assertFalse(any("mrn_base_stack_setup" in f["detail"] for f in plan["collisions"]))
        self.assertTrue(plan["apply_allowed"], plan["blocking"])
        self.assertEqual(
            ["switch template legacy-parent -> mrn-base-stack through the site child deploy"],
            plan["remaining_activation_steps"],
        )
        self.assertEqual(plan["plan_hash"], self.plan()["plan_hash"])

    def test_plan_refuses_wrong_identity(self):
        for key, value in (("home_url", "https://other.mrndev.io"), ("template", "other"),
                           ("stylesheet", "legacy-parent"), ("environment_type", "production")):
            with self.subTest(key=key):
                original = self.target.state[key]
                self.target.state[key] = value
                code, _out, err = self.run_main(self.site_args("plan"))
                self.target.state[key] = original
                self.assertEqual(1, code)
                self.assertIn("identity refused", err)

    def test_plan_blocks_different_parent_and_loaded_collisions(self):
        write_tree(self.target.site, {
            "wp-content/themes/mrn-base-stack/style.css": "/* stale */\n",
            "wp-content/mu-plugins/legacy-comp.php": "<?php\nrequire WP_CONTENT_DIR . '/mu-plugins/mrn-comp/mrn-comp.php';\n",
            "wp-content/themes/legacy-child/functions.php": "<?php\nfunction mrn_comp_boot() {}\n",
            "wp-content/plugins/mrn-comp/mrn-comp.php": "<?php\n",
        })
        self.target.state["active_plugins"].append("mrn-comp/mrn-comp.php")
        plan = self.plan()
        self.assertFalse(plan["apply_allowed"])
        blocking = " | ".join(plan["blocking"])
        self.assertIn("wp-content/themes/mrn-base-stack exists and differs", blocking)
        self.assertIn("legacy-comp.php also loads mrn-comp without require_once", blocking)
        self.assertIn("function mrn_comp_boot() declared by mrn-comp and wp-content/themes/legacy-child/functions.php", blocking)
        self.assertIn("standard copy is active", blocking)
        code, _out, err = self.apply(plan["plan_hash"])
        self.assertEqual(1, code)
        self.assertIn("blocking findings", err)
        self.assertEqual([], self.target.backups)
        self.assertEqual([], self.writes())

    def test_apply_refuses_changed_plan_and_missing_backup_without_writing(self):
        plan = self.plan()
        code, _out, err = self.apply("0" * 64)
        self.assertEqual(1, code)
        self.assertIn("Plan changed", err)
        self.target.backup_ok = False
        code, _out, err = self.apply(plan["plan_hash"])
        self.assertEqual(1, code)
        self.assertIn("backup receipt", err)
        self.assertEqual([], self.writes())
        self.assertFalse(self.journal.exists())

    def test_apply_installs_new_parent_preserves_legacy_and_rollback_restores(self):
        legacy_parent = self.tree("wp-content/themes/legacy-parent")
        legacy_child = self.tree("wp-content/themes/legacy-child")
        prior_comp = self.tree("wp-content/mu-plugins/mrn-comp")
        prior_loader = self.tree("wp-content/mu-plugins/mrn-loader.php")
        plan = self.plan()

        code, out, err = self.apply(plan["plan_hash"])
        self.assertEqual(0, code, err)
        journal = json.loads(self.journal.read_text())
        self.assertEqual("applied-verified", journal["status"])
        self.assertEqual(1, len(self.target.backups))
        self.assertEqual(journal["db_backup_label"], self.target.backups[0])
        backup_dir = Path(journal["file_backup_dir"])
        self.assertTrue(str(backup_dir).startswith(str(self.target.home / ".mrn-deploy-backups")))
        self.assertFalse(str(backup_dir).startswith(str(self.target.site)))
        self.assertTrue((backup_dir / "journal.json").is_file())
        self.assertEqual(0o700, backup_dir.stat().st_mode & 0o777)

        self.assertEqual(legacy_parent, self.tree("wp-content/themes/legacy-parent"))
        self.assertEqual(legacy_child, self.tree("wp-content/themes/legacy-child"))
        self.assertFalse((self.target.site / "wp-content/themes/mrn-base-stack-child").exists())
        for row in plan["operations"]:
            self.assertEqual(row["expected_sha256"], self.tree(row["target"]), row["target"])
        joined = [" ".join(c) for c in self.target.calls]
        self.assertFalse(any("plugin activate" in c or "option update" in c or "theme activate" in c for c in joined))

        code, out, err = self.run_main(self.site_args("verify"))
        self.assertEqual(0, code, err)
        self.page = (200, '<link href="/wp-content/themes/mrn-base-stack/style.css">')
        code, _out, err = self.run_main(self.site_args("verify"))
        self.assertEqual(1, code)
        self.assertIn("legacy parent", err)

        code, out, err = self.run_main(["rollback", "--journal", str(self.journal), "--confirm", "ROLLBACK example.mrndev.io fixture-release"])
        self.assertEqual(0, code, err)
        self.assertEqual(prior_comp, self.tree("wp-content/mu-plugins/mrn-comp"))
        self.assertEqual(prior_loader, self.tree("wp-content/mu-plugins/mrn-loader.php"))
        self.assertIsNone(self.tree("wp-content/themes/mrn-base-stack"))
        self.assertIsNone(self.tree("wp-content/mu-plugins/mrn-stack-release.lock.json"))
        self.assertEqual(2, len(self.target.backups))
        self.assertEqual("verified-restored", json.loads(self.journal.read_text())["rollback"]["status"])
        code, _out, err = self.run_main(["rollback", "--journal", str(self.journal), "--confirm", "ROLLBACK example.mrndev.io fixture-release"])
        self.assertEqual(1, code)
        self.assertIn("already records", err)

    def test_rollback_refuses_when_target_changed_after_apply(self):
        plan = self.plan()
        self.assertEqual(0, self.apply(plan["plan_hash"])[0])
        code, _out, err = self.run_main(["rollback", "--journal", str(self.journal), "--confirm", "ROLLBACK other fixture-release"])
        self.assertEqual(1, code)
        self.assertIn("requires --confirm", err)
        (self.target.site / "wp-content/mu-plugins/mrn-comp/hotfix.php").write_text("<?php\n")
        code, _out, err = self.run_main(["rollback", "--journal", str(self.journal), "--confirm", "ROLLBACK example.mrndev.io fixture-release"])
        self.assertEqual(1, code)
        self.assertIn("changed since apply", err)
        self.assertEqual(1, len(self.target.backups))
        self.assertTrue((self.target.site / "wp-content/themes/mrn-base-stack").is_dir())

    def test_failed_verification_marks_journal_for_rollback(self):
        plan = self.plan()
        self.target.load_ok = False
        code, _out, err = self.apply(plan["plan_hash"])
        self.assertEqual(1, code)
        self.assertIn("rollback is available", err)
        self.assertEqual("apply-failed", json.loads(self.journal.read_text())["status"])
        code, _out, err = self.run_main(["rollback", "--journal", str(self.journal), "--confirm", "ROLLBACK example.mrndev.io fixture-release"])
        self.assertEqual(0, code, err)
        self.assertIsNone(self.tree("wp-content/themes/mrn-base-stack"))


if __name__ == "__main__":
    unittest.main()

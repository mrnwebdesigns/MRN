"""Local-only target, immutable payload, preservation and recovery contracts."""
import copy
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch


SCRIPT = Path(__file__).resolve().parents[1] / "scripts/update-local-stack.py"
SPEC = importlib.util.spec_from_file_location("update_local_stack", SCRIPT)
adapter = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(adapter)


class LocalUpdateTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name).resolve() / "demo"
        self.public = self.root / "public"
        self.content = self.public / "wp-content"
        self.artifacts = self.root.parent / "payload"
        for root in (self.content, self.artifacts):
            (root / "shared").mkdir(parents=True)
            (root / "shared/runtime.php").write_text("<?php // old" if root == self.content else "<?php // new")
            (root / "themes/mrn-base-stack").mkdir(parents=True)
            (root / "themes/mrn-base-stack/style.css").write_text("Version: 1.0" if root == self.content else "Version: 2.0")
        child = self.content / "themes/client-child"
        child.mkdir()
        (child / "functions.php").write_text("<?php // preserved private child loader")
        (child / "style.css").write_text("Template: mrn-base-stack")
        (self.content / "mu-plugins").mkdir()
        self.bootstrap = self.content / adapter.PARENT_BOOTSTRAP
        self.bootstrap.write_text("MRN_Component_Release_Runtime::boot($root); MRN component release storage is unavailable.")
        self.manifest = self.root / ".mrn-site.json"
        self.manifest_value = {"slug": "demo", "runtime": "local-vm-openlitespeed",
                               "localUrl": "https://demo.localhost", "localRoot": str(self.root),
                               "publicPath": str(self.public), "phpVersion": "8.4", "dbPassword": "must-not-leak"}
        self.manifest.write_text(json.dumps(self.manifest_value))
        self.lock = {"schema_version": 1, "release_id": "fixture-2", "released_at": "2026-10-07T00:00:00Z",
                     "source": {}, "stack_version": "fixture-2", "hash_algorithm": "sha256-tree-v1",
                     "compatibility": {}, "components": [self.entry("shared", "mrn-shared-runtime", "shared-runtime")],
                     "themes": [self.entry("themes/mrn-base-stack", "mrn-base-stack", None)]}
        self.lock_file = self.root.parent / "release.lock.json"
        self.save_lock()
        self.site = adapter.load_site(self.manifest)
        self.identity = {"home": "https://demo.localhost", "siteurl": "https://demo.localhost",
                         "template": "mrn-base-stack", "stylesheet": "client-child",
                         "child_directory": "/private/child-release/theme", "child_manifest": "a" * 64,
                         "parent_directory": "/private/parent-release/component/mrn-base-stack",
                         "parent_selection": {"components": {"mrn-base-stack": {}}},
                         "local_guard": True, "installed": [], "active": []}
        self.inspector = patch.object(adapter, "inspect_site", side_effect=self.inspect)
        self.inspector.start()
        self.wp = patch.object(adapter, "wp", return_value="ok")
        self.wp.start()

    def tearDown(self):
        self.inspector.stop()
        self.wp.stop()
        self.temporary.cleanup()

    def entry(self, path, slug, kind):
        checksum, count = adapter.release_lock.tree_sha256(self.artifacts / path)
        result = {"slug": slug, "deployed_path": path, "sha256": checksum, "file_count": count, "version": "2.0"}
        if kind:
            result["runtime_type"] = kind
        else:
            result.update(verification_mode="exact", deployment_role="parent-template")
        return result

    def save_lock(self):
        self.lock_file.write_text(json.dumps(self.lock))
        self.lock_checksum = adapter.digest(self.lock_file.read_bytes())

    def inspect(self, _site):
        identity = copy.deepcopy(self.identity)
        selected = self.content / adapter.LOCK_PATH
        current = selected.exists() and adapter.digest(selected.read_bytes()) == self.lock_checksum
        if current and not self.bootstrap.exists():
            identity["parent_directory"] = "/srv/mrn-sites/demo/public/wp-content/themes/mrn-base-stack"
            identity["parent_selection"] = None
        return {"identity": identity, "report": {
            "generated_at_utc": "variable-time", "release_lock": {"sha256": self.lock_checksum if current else "old",
                                                                   "release_id": "fixture-2" if current else "fixture-1"},
            "themes": [{"slug": "mrn-base-stack", "matches_release": current}],
            "missing_required": [], "drifted_required": [], "legacy_flat_collisions": []}}

    def plan(self):
        return adapter.preflight(self.site, self.lock_file, self.lock_checksum, self.artifacts, True)

    def execute(self):
        plan = self.plan()
        return adapter.apply(plan, adapter.digest(adapter.canonical(plan)))

    def test_remote_manifest_rejected_without_runtime_calls(self):
        self.manifest_value["localUrl"] = "https://demo.mrndev.io"
        self.manifest.write_text(json.dumps(self.manifest_value))
        with self.assertRaisesRegex(ValueError, "localhost"):
            adapter.load_site(self.manifest)

    def test_alias_and_missing_standard_plugin_refused(self):
        (self.artifacts / "shared/alias.php").symlink_to(self.artifacts / "shared/runtime.php")
        with self.assertRaisesRegex(ValueError, "physical"):
            self.plan()
        (self.artifacts / "shared/alias.php").unlink()
        plugin = self.artifacts / "plugins/mrn-new"
        plugin.mkdir(parents=True)
        (plugin / "mrn-new.php").write_text("<?php")
        self.lock["components"].append(self.entry("plugins/mrn-new", "mrn-new", "standard-plugin"))
        self.save_lock()
        with self.assertRaisesRegex(ValueError, "missing standard plugin"):
            self.plan()

    def test_lock_payload_plan_and_runtime_safety_binding(self):
        with self.assertRaisesRegex(ValueError, "trusted checksum"):
            adapter.preflight(self.site, self.lock_file, "0" * 64, self.artifacts, True)
        (self.artifacts / "shared/runtime.php").write_text("tampered")
        with self.assertRaisesRegex(ValueError, "locked component"):
            self.plan()
        (self.artifacts / "shared/runtime.php").write_text("<?php // new")
        with self.assertRaisesRegex(ValueError, "reviewed checksum"):
            adapter.apply(self.plan(), "0" * 64)
        self.identity["local_guard"] = False
        with self.assertRaisesRegex(ValueError, "safety guard"):
            self.plan()

    def test_parent_detachment_must_be_explicit_and_parent_only(self):
        with self.assertRaisesRegex(ValueError, "Explicit parent-only"):
            adapter.preflight(self.site, self.lock_file, self.lock_checksum, self.artifacts)
        self.identity["parent_selection"]["components"]["mrn-plugin"] = {}
        with self.assertRaisesRegex(ValueError, "Explicit parent-only"):
            self.plan()

    def test_apply_and_code_rollback_preserve_child_plugins_and_private_storage(self):
        child_before = adapter.inventory(self.content / "themes/client-child")
        preserved = self.root / "private-parent-release"
        preserved.mkdir()
        (preserved / "code.php").write_text("retained generation")
        unrelated = self.content / "plugins/another-plugin"
        unrelated.mkdir(parents=True)
        (unrelated / "plugin.php").write_text("unrelated code")
        before = adapter.inventory(self.content)
        result = self.execute()
        self.assertEqual(result["status"], "verified-current")
        self.assertFalse(self.bootstrap.exists())
        self.assertEqual(adapter.inventory(self.content / "themes/client-child"), child_before)
        self.assertEqual((preserved / "code.php").read_text(), "retained generation")
        self.assertEqual((unrelated / "plugin.php").read_text(), "unrelated code")
        adapter.restore(Path(result["journal"]))
        self.assertEqual(adapter.inventory(self.content), before)

    def test_changed_baseline_and_staged_source_refused(self):
        plan = self.plan()
        (self.content / "shared/runtime.php").write_text("owner edit")
        with self.assertRaisesRegex(ValueError, "before-state changed"):
            adapter.apply(plan, adapter.digest(adapter.canonical(plan)))
        (self.content / "shared/runtime.php").write_text("<?php // old")
        (self.artifacts / "shared/runtime.php").write_text("artifact race")
        with self.assertRaisesRegex(RuntimeError, "rolled back"):
            adapter.apply(plan, adapter.digest(adapter.canonical(plan)))
        self.assertEqual((self.content / "shared/runtime.php").read_text(), "<?php // old")

    def test_incomplete_apply_is_recoverable_and_blocks_new_updates(self):
        plan = self.plan()
        real_replace = adapter.os.replace
        def interrupted(source, target):
            real_replace(source, target)
            if Path(target).name == "0" and Path(target).parent.name == "before":
                raise SystemExit("interrupted after the original component move")
        with patch.object(adapter.os, "replace", side_effect=interrupted):
            with self.assertRaises(SystemExit):
                adapter.apply(plan, adapter.digest(adapter.canonical(plan)))
        journal = next((self.root / "output/local-fleet-updates").glob("*/journal.json"))
        with self.assertRaisesRegex(ValueError, "incomplete local update"):
            adapter.apply(plan, adapter.digest(adapter.canonical(plan)))
        adapter.restore(journal)
        self.assertEqual((self.content / "shared/runtime.php").read_text(), "<?php // old")
        self.assertEqual(json.loads(journal.read_text())["status"], "rolled-back")

    def test_rollback_refuses_unrelated_edits_before_restoring_any_slot(self):
        result = self.execute()
        (self.content / "shared/runtime.php").write_text("later owner edit")
        with self.assertRaisesRegex(ValueError, "unrelated component edit"):
            adapter.restore(Path(result["journal"]))
        self.assertEqual((self.content / "shared/runtime.php").read_text(), "later owner edit")
        self.assertFalse(self.bootstrap.exists())

    def test_failed_runtime_readback_restores_original_code(self):
        before = adapter.inventory(self.content)
        original_inspect = self.inspect
        def missing_runtime(site):
            result = original_inspect(site)
            if result["report"]["release_lock"]["release_id"] == "fixture-2":
                result["report"]["missing_required"] = ["mrn-loader"]
            return result
        with patch.object(adapter, "inspect_site", side_effect=missing_runtime):
            with self.assertRaisesRegex(RuntimeError, "rolled back"):
                self.execute()
        self.assertEqual(adapter.inventory(self.content), before)

    def test_changed_recovery_plan_is_refused_without_writes(self):
        result = self.execute()
        path = Path(result["journal"])
        journal = json.loads(path.read_text())
        journal["plan"]["release_id"] = "unreviewed"
        path.write_text(json.dumps(journal))
        before = adapter.inventory(self.content)
        with self.assertRaisesRegex(ValueError, "checksum differs"):
            adapter.restore(path)
        self.assertEqual(adapter.inventory(self.content), before)

    def test_unmanaged_dependencies_are_preserved_without_following_links(self):
        dependencies = self.content / "themes/mrn-base-stack/node_modules"
        dependencies.mkdir()
        (dependencies / "private-tool").symlink_to("/outside/development-tool")
        injected = self.artifacts / "themes/mrn-base-stack/node_modules"
        injected.mkdir()
        (injected / "untrusted.php").write_text("not part of the signed payload")
        result = self.execute()
        self.assertTrue((dependencies / "private-tool").is_symlink())
        self.assertFalse((dependencies / "untrusted.php").exists())
        adapter.restore(Path(result["journal"]))
        self.assertEqual((dependencies / "private-tool").readlink(), Path("/outside/development-tool"))

    def test_missing_before_state_blocks_all_recovery_writes(self):
        result = self.execute()
        journal = Path(result["journal"])
        missing = journal.parent / "before/0"
        import shutil
        shutil.rmtree(missing)
        before = adapter.inventory(self.content)
        with self.assertRaisesRegex(ValueError, "before-state is missing"):
            adapter.restore(journal)
        self.assertEqual(adapter.inventory(self.content), before)


if __name__ == "__main__":
    unittest.main()

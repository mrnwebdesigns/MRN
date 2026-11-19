"""Contract coverage for the required PreviewShare Stack integration."""
import json
from pathlib import Path
import unittest


ROOT = Path(__file__).parents[1]


class PreviewShareContractTests(unittest.TestCase):
    def setUp(self):
        self.manifest = (ROOT / "manifests/plugins.txt").read_text(encoding="utf-8")
        self.lock = json.loads(
            (ROOT / "manifests/bootstrap-packages.lock.json").read_text(encoding="utf-8")
        )
        self.bootstrap = (ROOT / "scripts/site-bootstrap.sh").read_text(encoding="utf-8")
        self.configure = (ROOT / "scripts/configure-previewshare.php").read_text(encoding="utf-8")
        self.verify = (ROOT / "scripts/verify-previewshare.php").read_text(encoding="utf-8")
        self.rollout_qa = (ROOT / "scripts/qa-rollout-contract.sh").read_text(encoding="utf-8")

    def test_wordpress_org_package_is_manifested_and_checksum_locked(self):
        self.assertIn("previewshare|1.1.0", self.manifest)
        entry = next(item for item in self.lock["plugins"] if item["slug"] == "previewshare")
        self.assertEqual(36, self.lock["plain_plugin_count"])
        self.assertEqual(37, len(self.lock["plugins"]))
        self.assertEqual("1.1.0", entry["version"])
        self.assertEqual("previewshare/previewshare.php", entry["main_file"])
        self.assertEqual("wordpress.org", entry["source"]["type"])
        self.assertEqual(
            "https://downloads.wordpress.org/plugin/previewshare.1.1.0.zip",
            entry["source"]["url"],
        )

    def test_bootstrap_configures_previewshare_after_activation(self):
        install_index = self.bootstrap.index("install_plugins")
        config_index = self.bootstrap.index("configure_previewshare")
        self.assertGreater(config_index, install_index)
        self.assertIn("STACK_PREVIEWSHARE_POST_TYPES:-post,page", self.bootstrap)
        self.assertIn("STACK_PREVIEWSHARE_DEFAULT_TTL_HOURS:-6", self.bootstrap)

    def test_configuration_and_verification_reject_unsafe_types(self):
        for source in (self.configure, self.verify):
            self.assertIn("attachment", source)
            self.assertIn("revision", source)
            self.assertIn("is_post_type_viewable", source)
        self.assertIn("$post_type->public", self.verify)
        self.assertIn("previewshare_default_ttl_hours", self.configure)
        self.assertIn("previewshare_post_types", self.configure)
        self.assertIn("filter_preview_robots", self.verify)

    def test_release_contract_checks_remote_helper_parity_and_child_runtime(self):
        self.assertIn("REMOTE_PREVIEWSHARE_CONFIG", self.rollout_qa)
        self.assertIn("REMOTE_PREVIEWSHARE_VERIFY", self.rollout_qa)
        self.assertIn("PreviewShare is active and matches", self.rollout_qa)


if __name__ == "__main__":
    unittest.main()

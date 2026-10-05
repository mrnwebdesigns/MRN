from pathlib import Path
import os
import subprocess
import unittest


ROOT = Path(__file__).parents[1]


class SiteBootstrapContractTests(unittest.TestCase):
    def setUp(self):
        self.bootstrap = (ROOT / "scripts/site-bootstrap.sh").read_text(encoding="utf-8")
        self.importer = (
            ROOT / "configs/importers/stack-export-importer.sh"
        ).read_text(encoding="utf-8")
        self.deployer = (
            ROOT / "scripts/deploy-feature-stack-and-default-configs.sh"
        ).read_text(encoding="utf-8")
        self.rollout_qa = (ROOT / "scripts/qa-rollout-contract.sh").read_text(
            encoding="utf-8"
        )

    def test_existing_marker_reconciles_all_managed_credentials(self):
        marker_block = self.bootstrap.split(
            'if [[ -f "${SITE_PATH}/${MARKER_NAME}" ]]', 1
        )[1].split("send_slack_notification", 1)[0]

        self.assertIn("reconcile_managed_credentials", marker_block)
        self.assertNotIn("reconcile_recaptcha_enterprise_constants\n", marker_block)

    def test_stack_bootstrap_requires_uptime_and_recaptcha_delivery(self):
        self.assertIn("validate_managed_credential_sources", self.bootstrap)
        self.assertIn("MRN_UPTIME_ROBOT_API_KEY", self.bootstrap)
        self.assertIn("verify_managed_credential_delivery", self.bootstrap)
        self.assertIn("bootstrap_wpforms_recaptcha", self.bootstrap)
        self.assertIn('array("unchanged", "reused", "created")', self.bootstrap)

    def test_sendgrid_is_absent_even_with_old_opt_in_environment(self):
        manifest = (ROOT / "manifests/plugins.txt").read_text(encoding="utf-8")
        self.assertNotIn("mrn-sendgrid-provisioning.zip", manifest)
        self.assertNotIn("sendgrid", self.bootstrap.lower())
        defaults = self.bootstrap.split("apply_wp_defaults() {", 1)[1].split(
            "reconcile_development_environment_policy() {", 1
        )[0]
        services = self.bootstrap.split("provision_external_services() {", 1)[1].split(
            "ensure_updraft_local_retention_schedule() {", 1
        )[0]
        # Execute the actual functions with a WP recorder, never a site or provider.
        fixture = """
set -eu
run_wp() { printf 'WP %s\\n' "$*"; }
run_wp_config_set_quiet() { printf 'CONFIG %s\\n' "$*"; }
add_warning() { printf 'WARNING %s\\n' "$*"; }
bootstrap_flag_enabled() { test "$1" = 1; }
SITE_PROFILE=stack
AUTO_PROVISION_UPTIME_ROBOT=0
RECAPTCHA_ENTERPRISE_PROJECT_ID=''
RECAPTCHA_ENTERPRISE_SERVICE_ACCOUNT_EMAIL=''
RECAPTCHA_ENTERPRISE_PRIVATE_KEY=''
RECAPTCHA_ENTERPRISE_ALLOWED_DOMAINS=''
RECAPTCHA_ENTERPRISE_DEFAULT_INTEGRATION_TYPE=''
"""
        fixture += "apply_wp_defaults() {" + defaults
        fixture += "provision_external_services() {" + services
        fixture += "apply_wp_defaults\nprovision_external_services\n"
        environment = os.environ.copy()
        environment["STACK_BOOTSTRAP_SENDGRID_AUTO_PROVISION"] = "1"
        environment["MRN_SENDGRID_MANAGEMENT_API_KEY"] = "retired-provider-fixture"
        result = subprocess.run(["bash", "--noprofile", "--norc"], input=fixture,
                                text=True, capture_output=True, env=environment, check=True)
        self.assertNotIn("sendgrid", result.stdout.lower())
        self.assertNotIn("retired-provider-fixture", result.stdout)
        self.assertIn("WP option update blog_public 0", result.stdout)
        self.assertIn("UptimeRobot provisioning deferred", result.stdout)

    def test_bootstrap_invokes_importer_in_strict_mode(self):
        self.assertIn("STACK_IMPORTER_STRICT=1", self.bootstrap)
        self.assertIn('if [[ "${IMPORT_STRICT}" == "1" ]]', self.importer)
        self.assertIn("exit 1", self.importer)

    def test_feature_deploy_publishes_and_verifies_bootstrap_sources(self):
        self.assertIn("LOCAL_SITE_BOOTSTRAP", self.deployer)
        self.assertIn("LOCAL_STACK_EXPORT_IMPORTER", self.deployer)
        self.assertIn("verify_remote_file_sha256", self.deployer)
        self.assertIn("--bootstrap-contract-only", self.deployer)
        self.assertIn('SSH_HOST="mrndev-stack-manager"', self.deployer)
        self.assertNotIn("mrndev-stack-manager@167.99.54.77", self.deployer)
        self.assertIn('if [[ "${BOOTSTRAP_CONTRACT_ONLY}" -eq 0 ]]', self.deployer)
        self.assertNotIn("LIVE_SITE_SSH_LOGIN", self.deployer)
        self.assertIn("REMOTE_SITE_BOOTSTRAP", self.rollout_qa)
        self.assertIn("verify_remote_file_sha256", self.rollout_qa)


if __name__ == "__main__":
    unittest.main()

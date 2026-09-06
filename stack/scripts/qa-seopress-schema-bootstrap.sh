#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
BRIDGE_ROOT="${REPO_ROOT}/mu-plugins/mrn-schema-bridge"
BRIDGE_MAIN="${BRIDGE_ROOT}/mrn-schema-bridge.php"
BRIDGE_TEST="${BRIDGE_ROOT}/tests/contract-regression.php"
BOOTSTRAP="${REPO_ROOT}/stack/scripts/site-bootstrap.sh"

fail() {
	echo "FAIL: $*" >&2
	exit 1
}

pass() {
	echo "PASS: $*"
}

[[ -f "${BRIDGE_MAIN}" ]] || fail "Schema Bridge source not found: ${BRIDGE_MAIN}"
[[ -f "${BRIDGE_TEST}" ]] || fail "Schema Bridge contract test not found: ${BRIDGE_TEST}"
[[ -f "${BOOTSTRAP}" ]] || fail "Stack bootstrap not found: ${BOOTSTRAP}"

grep -Fq 'function mrn_schema_bridge_provision_seopress_article_templates()' "${BRIDGE_MAIN}" \
	|| fail "Schema Bridge is missing automatic Article template provisioning"
grep -Fq 'function mrn_schema_bridge_sync_seopress_identity_from_business_information()' "${BRIDGE_MAIN}" \
	|| fail "Schema Bridge is missing Business Information identity synchronization"
pass "Schema Bridge exposes the new-site SEOPress provisioning APIs"

provision_line="$(grep -n '^  provision_seopress_schema_defaults$' "${BOOTSTRAP}" | cut -d: -f1)"
policy_line="$(grep -n '^  reconcile_development_environment_policy$' "${BOOTSTRAP}" | cut -d: -f1)"

[[ -n "${provision_line}" ]] || fail "Bootstrap does not invoke SEOPress schema provisioning"
[[ -n "${policy_line}" ]] || fail "Bootstrap development policy invocation was not found"
(( provision_line < policy_line )) || fail "SEOPress schema provisioning must run before development policy deactivates SEOPress"
pass "Bootstrap provisions SEOPress schema before development deactivation"

php "${BRIDGE_TEST}"
pass "Schema Bridge provider and provisioning contracts"

echo "SEOPress schema bootstrap QA passed."

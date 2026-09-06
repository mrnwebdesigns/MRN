#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STACK_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
POLICY_FILE="${STACK_ROOT}/CLOUDFLARE_SECURITY_POLICY.md"
PROFILE_DIR="${STACK_ROOT}/configs/cloudflare"

fail() {
	echo "FAIL: $*" >&2
	exit 1
}

pass() {
	echo "PASS: $*"
}

command -v jq >/dev/null 2>&1 || fail "Required command not found: jq"
[[ -f "${POLICY_FILE}" ]] || fail "Policy file not found: ${POLICY_FILE}"

validate_profile() {
	local environment="$1"
	local profile_file="${PROFILE_DIR}/security-${environment}.json"

	[[ -f "${profile_file}" ]] || fail "Profile file not found: ${profile_file}"
	jq -e --arg environment "${environment}" '
		.schema_version == 1 and
		.profile == $environment and
		.enforcement == "policy_intent_not_api_payload" and
		(.purpose | type == "string" and length > 0) and
		.zone.ssl == "full_strict" and
		.zone.always_https == true and
		.zone.min_tls_version == "1.2" and
		.zone.tls_1_3 == true and
		.zone.managed_waf == "enable_available_managed_rulesets_for_plan" and
		(.zone.bot_protection | type == "string" and length > 0) and
		(.zone.dnssec | type == "string" and length > 0) and
		.dns.preserve_existing_records == true and
		(.dns.never_proxy_types | index("MX") != null) and
		(.dns.never_proxy_types | index("TXT") != null) and
		(.dns.never_proxy_types | index("CAA") != null) and
		(.launch_requirements | type == "array" and length > 0) and
		if $environment == "development" then
			.zone.bot_protection == "off_unless_available_and_non_disruptive" and
			.zone.dnssec == "recommended_after_nameserver_cutover_when_registrar_supports_it" and
			.rules.wp_admin == "do_not_block_without_verified_admin_access_plan"
		else
			.zone.bot_protection == "enable_plan_appropriate_mode_after_integration_verification" and
			.zone.dnssec == "required_when_registrar_supports_it" and
			.rules.wp_admin == "challenge_or_restrict_only_with_verified_admin_access_plan"
		end
	' "${profile_file}" >/dev/null || fail "Invalid ${environment} profile: ${profile_file}"

	if jq -e '.zone | has("waf_managed_rules") or has("bot_management")' "${profile_file}" >/dev/null; then
		fail "${environment} profile uses deprecated non-plan-aware WAF or bot keys"
	fi

	pass "${environment} Cloudflare security profile"
}

validate_profile development
validate_profile production

grep -Fq 'security-development.json' "${POLICY_FILE}" || fail "Policy does not reference the development profile"
grep -Fq 'security-production.json' "${POLICY_FILE}" || fail "Policy does not reference the production profile"
grep -Fq 'not blindly executable API payloads' "${POLICY_FILE}" || fail "Policy is missing the non-executable profile safety boundary"

pass "Cloudflare policy references and safety boundary"
echo "Cloudflare security policy QA passed."

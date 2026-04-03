#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  qa-security.sh [theme-dir]

Default:
  /Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack

Environment:
  MRN_QA_INCLUDE_DEV_AUDIT=1   Also audit npm/composer dev dependencies.
EOF
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
	usage
	exit 0
fi

if ! command -v php >/dev/null 2>&1; then
	echo "Required command not found: php" >&2
	exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
THEME_DIR="${1:-${REPO_ROOT}/stack/themes/mrn-base-stack}"
RISK_SCAN_SCRIPT="${SCRIPT_DIR}/qa-risk-scan.sh"
INCLUDE_DEV_AUDIT="${MRN_QA_INCLUDE_DEV_AUDIT:-0}"
STATUS=0

if [[ ! -d "${THEME_DIR}" ]]; then
	echo "Theme directory not found: ${THEME_DIR}" >&2
	exit 1
fi

echo "Security QA target: ${THEME_DIR}"

echo
echo "1. Risk scan"
if ! "${RISK_SCAN_SCRIPT}" "${THEME_DIR}"; then
	STATUS=1
fi

echo
echo "2. Focused WordPress security sniffs"
if [[ -x "${THEME_DIR}/vendor/bin/phpcs" && -f "${THEME_DIR}/phpcs.xml.dist" ]]; then
	if ! "${THEME_DIR}/vendor/bin/phpcs" \
		--standard="${THEME_DIR}/phpcs.xml.dist" \
		--sniffs=WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput,WordPress.Security.EscapeOutput,WordPress.DB.PreparedSQL \
		"${THEME_DIR}"; then
		STATUS=1
	fi
else
	echo "Skipping focused PHPCS security sniffs; install Composer dev tools first."
fi

echo
echo "3. Lightweight secret scan"
if command -v rg >/dev/null 2>&1; then
	SECRET_SCAN_ARGS=(
		-n
		--hidden
		--glob '!vendor/**'
		--glob '!node_modules/**'
		--glob '!test-results/**'
		--glob '!playwright-report/**'
		--glob '!.git/**'
	)
	SECRET_PATTERNS=(
		'-----BEGIN (RSA|OPENSSH|EC|DSA) PRIVATE KEY-----'
		'ghp_[A-Za-z0-9]{36,}'
		'github_pat_[A-Za-z0-9_]{20,}'
		'xox[baprs]-[A-Za-z0-9-]{10,}'
		'AIza[0-9A-Za-z_-]{35}'
		'AKIA[0-9A-Z]{16}'
		'aws_secret_access_key'
	)
	SECRET_FINDINGS=0

	for secret_pattern in "${SECRET_PATTERNS[@]}"; do
		set +e
		rg "${SECRET_SCAN_ARGS[@]}" -e "${secret_pattern}" "${THEME_DIR}"
		rg_exit_code=$?
		set -e

		if [[ "${rg_exit_code}" -eq 0 ]]; then
			SECRET_FINDINGS=1
		elif [[ "${rg_exit_code}" -gt 1 ]]; then
			echo "Secret scan failed for pattern: ${secret_pattern}" >&2
			STATUS=1
			SECRET_FINDINGS=1
		fi
	done

	if [[ "${SECRET_FINDINGS}" -eq 0 ]]; then
		echo "No secret-like patterns found."
	else
		echo "Potential secret-like material found above." >&2
		STATUS=1
	fi
else
	echo "Skipping secret scan; rg is not installed."
fi

echo
echo "4. Runtime dependency audit"
if [[ -f "${THEME_DIR}/package-lock.json" ]] && command -v npm >/dev/null 2>&1; then
	if ! (
		cd "${THEME_DIR}" &&
		npm audit --omit=dev --omit=optional --audit-level=high
	); then
		STATUS=1
	fi
else
	echo "Skipping npm runtime audit; install Node dependencies first."
fi

if [[ -f "${THEME_DIR}/composer.lock" ]] && command -v composer >/dev/null 2>&1; then
	if ! composer audit --no-dev --working-dir="${THEME_DIR}"; then
		STATUS=1
	fi
else
	echo "Skipping Composer runtime audit; composer is not installed on PATH."
fi

if [[ "${INCLUDE_DEV_AUDIT}" == "1" ]]; then
	echo
	echo "5. Dev dependency audit"
	if [[ -f "${THEME_DIR}/package-lock.json" ]] && command -v npm >/dev/null 2>&1; then
		if ! (
			cd "${THEME_DIR}" &&
			npm audit --audit-level=high
		); then
			STATUS=1
		fi
	else
		echo "Skipping npm dev audit; install Node dependencies first."
	fi

	if [[ -f "${THEME_DIR}/composer.lock" ]] && command -v composer >/dev/null 2>&1; then
		if ! composer audit --working-dir="${THEME_DIR}"; then
			STATUS=1
		fi
	else
		echo "Skipping Composer dev audit; composer is not installed on PATH."
	fi
else
	echo
	echo "5. Dev dependency audit"
	echo "Skipped by default. Set MRN_QA_INCLUDE_DEV_AUDIT=1 to audit local dev tooling too."
fi

echo
if [[ "${STATUS}" -eq 0 ]]; then
	echo "Security QA completed."
else
	echo "Security QA completed with findings." >&2
fi

exit "${STATUS}"

#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  qa-theme.sh [theme-dir]

Default:
  /Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack
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

if ! command -v find >/dev/null 2>&1; then
	echo "Required command not found: find" >&2
	exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
THEME_DIR="${1:-${REPO_ROOT}/stack/themes/mrn-base-stack}"
RISK_SCAN_SCRIPT="${SCRIPT_DIR}/qa-risk-scan.sh"
STATUS=0

if [[ ! -d "${THEME_DIR}" ]]; then
	echo "Theme directory not found: ${THEME_DIR}" >&2
	exit 1
fi

echo "Theme QA target: ${THEME_DIR}"

echo
echo "1. PHP syntax"
while IFS= read -r -d '' php_file; do
	php -l "${php_file}"
done < <(find "${THEME_DIR}" -type f -name '*.php' ! -path '*/vendor/*' -print0 | sort -z)

echo
echo "2. Git diff whitespace"
if ! git -C "${REPO_ROOT}" diff --check; then
	STATUS=1
fi

echo
echo "3. Risk scan"
if ! "${RISK_SCAN_SCRIPT}" "${THEME_DIR}"; then
	STATUS=1
fi

echo
echo "4. JavaScript syntax"
if command -v node >/dev/null 2>&1; then
	while IFS= read -r -d '' js_file; do
		node --check "${js_file}"
	done < <(find "${THEME_DIR}/js" -type f -name '*.js' ! -path '*/vendor/*' ! -name '*.min.js' -print0 2>/dev/null | sort -z)
else
	echo "Skipping JS syntax; node is not installed."
fi

echo
echo "5. Parallel lint"
if [[ -x "${THEME_DIR}/vendor/bin/parallel-lint" ]]; then
	if ! "${THEME_DIR}/vendor/bin/parallel-lint" --exclude "${THEME_DIR}/vendor" "${THEME_DIR}"; then
		STATUS=1
	fi
else
	echo "Skipping parallel lint; install Composer dev tools first."
fi

echo
echo "6. PHPCS"
if [[ -x "${THEME_DIR}/vendor/bin/phpcs" && -f "${THEME_DIR}/phpcs.xml.dist" ]]; then
	if ! "${THEME_DIR}/vendor/bin/phpcs" \
		-d memory_limit="${MRN_QA_PHPCS_MEMORY_LIMIT:-2G}" \
		--parallel="${MRN_QA_PHPCS_PARALLEL:-1}" \
		--standard="${THEME_DIR}/phpcs.xml.dist" \
		"${THEME_DIR}"; then
		STATUS=1
	fi
else
	echo "Skipping PHPCS; install Composer dev tools first."
fi

echo
echo "7. PHPStan (shared MRN config)"
if [[ -x "${REPO_ROOT}/vendor/bin/phpstan" && -f "${REPO_ROOT}/phpstan.neon.dist" ]]; then
	if ! php "${REPO_ROOT}/vendor/bin/phpstan" analyse \
		--configuration="${REPO_ROOT}/phpstan.neon.dist" \
		--memory-limit=2G \
		--no-progress \
		"${THEME_DIR}"; then
		STATUS=1
	fi
else
	echo "Skipping PHPStan; install root Composer dev tools first."
fi

echo
if [[ "${STATUS}" -eq 0 ]]; then
	echo "Theme QA completed."
else
	echo "Theme QA completed with findings." >&2
fi

exit "${STATUS}"

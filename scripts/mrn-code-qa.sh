#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STACK_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
PHPCS_BIN="${STACK_ROOT}/vendor/bin/phpcs"
PHPCS_STANDARD="${MRN_PHPCS_STANDARD:-${STACK_ROOT}/phpcs.xml.dist}"
SEMGREP_CONFIG="${MRN_SEMGREP_CONFIG:-${STACK_ROOT}/semgrep/security-audit.yml}"
PHPSTAN_RUNNER="${STACK_ROOT}/scripts/mrn-phpstan.sh"

usage() {
	cat <<USAGE
Usage:
  scripts/mrn-code-qa.sh <path> [more-paths...]

Runs:
1) PHP lint on *.php files
2) PHPCS (repo standard)
3) PHPStan (shared MRN config)
4) Semgrep (repo security config)
USAGE
}

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
	usage
	exit 0
fi

if [[ "$#" -eq 0 ]]; then
	usage >&2
	exit 1
fi

TARGETS=("$@")
STATUS=0

PHP_FILES=()
while IFS= read -r -d '' file; do
	PHP_FILES+=("${file}")
done < <(find "${TARGETS[@]}" -type f -name '*.php' -print0 2>/dev/null || true)

echo "[1/4] PHP lint"
if [[ "${#PHP_FILES[@]}" -eq 0 ]]; then
	echo "No PHP files found in targets."
else
	for php_file in "${PHP_FILES[@]}"; do
		if ! php -l "${php_file}" >/dev/null; then
			STATUS=1
		fi
	done
	echo "PHP lint complete (${#PHP_FILES[@]} files)."
fi

echo
echo "[2/4] PHPCS"
if [[ -x "${PHPCS_BIN}" && -f "${PHPCS_STANDARD}" && "${#PHP_FILES[@]}" -gt 0 ]]; then
	if ! "${PHPCS_BIN}" --standard="${PHPCS_STANDARD}" "${PHP_FILES[@]}"; then
		STATUS=1
	fi
else
	echo "Skipped: PHPCS binary/standard missing or no PHP files."
	echo "  bin=${PHPCS_BIN}"
	echo "  standard=${PHPCS_STANDARD}"
fi

echo
echo "[3/4] PHPStan"
if [[ -x "${PHPSTAN_RUNNER}" ]]; then
	if ! "${PHPSTAN_RUNNER}" "${TARGETS[@]}"; then
		STATUS=1
	fi
else
	echo "Skipped: PHPStan runner missing (${PHPSTAN_RUNNER})."
fi

echo
echo "[4/4] Semgrep"
if [[ -f "${SEMGREP_CONFIG}" ]] && command -v semgrep >/dev/null 2>&1; then
	SEMGREP_HOME="${STACK_ROOT}/.tmp/semgrep-home"
	mkdir -p "${SEMGREP_HOME}"
	if ! HOME="${SEMGREP_HOME}" SEMGREP_SEND_METRICS=off semgrep scan --config "${SEMGREP_CONFIG}" --error --no-git-ignore "${TARGETS[@]}"; then
		STATUS=1
	fi
else
	echo "Skipped: semgrep not installed or config missing (${SEMGREP_CONFIG})."
fi

echo
if [[ "${STATUS}" -eq 0 ]]; then
	echo "mrn-code-qa: PASS"
else
	echo "mrn-code-qa: FAIL" >&2
fi

exit "${STATUS}"

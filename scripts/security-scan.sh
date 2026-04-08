#!/usr/bin/env bash
set -uo pipefail

usage() {
	cat <<'EOF'
Usage:
  scripts/security-scan.sh [path]

Default targets:
  plugins
  mu-plugins
  stack/themes

Examples:
  scripts/security-scan.sh
  scripts/security-scan.sh plugins/mrn-editor-tools
  scripts/security-scan.sh stack/themes/mrn-base-stack

Notes:
  - Scans the source-of-truth repo paths, not Local's internal site folders.
  - Works whether those directories are symlinked into a Local site or not.
EOF
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
PHPCS_CONFIG="${REPO_ROOT}/phpcs.xml.dist"
PHPSTAN_CONFIG="${REPO_ROOT}/phpstan.neon.dist"
SEMGREP_CONFIG="${REPO_ROOT}/semgrep/security-audit.yml"
SEMGREP_HOME="${REPO_ROOT}/.tmp/semgrep-home"
PHPSTAN_TMP_DIR="${REPO_ROOT}/.tmp/phpstan"

mkdir -p "${SEMGREP_HOME}" "${PHPSTAN_TMP_DIR}"

resolve_path() {
	local raw_path="$1"
	local candidate=""

	if [[ -e "${raw_path}" ]]; then
		candidate="${raw_path}"
	elif [[ -e "${REPO_ROOT}/${raw_path}" ]]; then
		candidate="${REPO_ROOT}/${raw_path}"
	else
		return 1
	fi

	(
		cd "$(dirname "${candidate}")" >/dev/null 2>&1 &&
		printf '%s/%s\n' "$(pwd -P)" "$(basename "${candidate}")"
	)
}

ensure_repo_path() {
	local target="$1"

	case "${target}" in
		"${REPO_ROOT}"|"${REPO_ROOT}/"*)
			return 0
			;;
		*)
			return 1
			;;
	esac
}

collect_abspath_entrypoints() {
	local target=""

	for target in "$@"; do
		case "${target}" in
			"${REPO_ROOT}/plugins"|"${REPO_ROOT}/mu-plugins")
				find "${target}" -mindepth 2 -maxdepth 2 -type f -name '*.php'
				;;
			"${REPO_ROOT}/plugins/"*|"${REPO_ROOT}/mu-plugins/"*)
				find "${target}" -mindepth 1 -maxdepth 1 -type f -name '*.php'
				;;
			*)
				continue
				;;
		esac
	done | sort -u
}

run_abspath_guard_check() {
	local file=""
	local guard_missing=0
	local found_entrypoints=0

	while IFS= read -r file; do
		found_entrypoints=1

		if ! head -n 40 "${file}" | rg -q "defined\s*\(\s*['\"]ABSPATH['\"]\s*\)"; then
			if [[ "${guard_missing}" -eq 0 ]]; then
				echo "Missing ABSPATH guard in top-level plugin or MU plugin PHP files:"
			fi

			echo "  - ${file#${REPO_ROOT}/}"
			guard_missing=1
		fi
	done < <(collect_abspath_entrypoints "$@")

	if [[ "${found_entrypoints}" -eq 0 ]]; then
		echo "No top-level plugin or MU plugin entry files found for ABSPATH guard review."
		return 0
	fi

	if [[ "${guard_missing}" -eq 0 ]]; then
		echo "Top-level plugin/theme PHP files include an ABSPATH guard."
		return 0
	fi

	return 1
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
	usage
	exit 0
fi

if ! command -v php >/dev/null 2>&1; then
	echo "Required command not found: php" >&2
	exit 1
fi

if ! command -v semgrep >/dev/null 2>&1; then
	echo "Required command not found: semgrep" >&2
	exit 1
fi

if [[ ! -x "${REPO_ROOT}/vendor/bin/phpcs" || ! -x "${REPO_ROOT}/vendor/bin/phpstan" ]]; then
	echo "Composer dev tools are not installed yet. Run 'composer install' from ${REPO_ROOT} first." >&2
	exit 1
fi

TARGETS=()

if [[ $# -gt 1 ]]; then
	usage >&2
	exit 1
elif [[ $# -eq 1 ]]; then
	RESOLVED_TARGET="$(resolve_path "$1")" || {
		echo "Path not found: $1" >&2
		exit 1
	}

	if ! ensure_repo_path "${RESOLVED_TARGET}"; then
		echo "Refusing to scan outside the repo: ${RESOLVED_TARGET}" >&2
		exit 1
	fi

	TARGETS=("${RESOLVED_TARGET}")
else
	TARGETS=(
		"${REPO_ROOT}/plugins"
		"${REPO_ROOT}/mu-plugins"
		"${REPO_ROOT}/stack/themes"
	)
fi

echo "Security scan targets:"
for target in "${TARGETS[@]}"; do
	echo "  - ${target#${REPO_ROOT}/}"
done

STATUS=0
PHPCS_RESULT="PASS"
PHPSTAN_RESULT="PASS"
SEMGREP_RESULT="PASS"
ABSPATH_RESULT="PASS"

echo
echo "1. PHPCS (WordPress security-focused sniffs)"
if ! php "${REPO_ROOT}/vendor/bin/phpcs" --standard="${PHPCS_CONFIG}" "${TARGETS[@]}"; then
	PHPCS_RESULT="FAIL"
	STATUS=1
fi

echo
echo "2. PHPStan"
if ! php "${REPO_ROOT}/vendor/bin/phpstan" analyse --configuration="${PHPSTAN_CONFIG}" --memory-limit=2G "${TARGETS[@]}"; then
	PHPSTAN_RESULT="FAIL"
	STATUS=1
fi

echo
echo "3. Semgrep"
if ! HOME="${SEMGREP_HOME}" SEMGREP_SEND_METRICS=off semgrep scan --config "${SEMGREP_CONFIG}" --error --no-git-ignore "${TARGETS[@]}"; then
	SEMGREP_RESULT="FAIL"
	STATUS=1
fi

echo
echo "4. ABSPATH guard spot-check"
if ! run_abspath_guard_check "${TARGETS[@]}"; then
	ABSPATH_RESULT="FAIL"
	STATUS=1
fi

echo
echo "Summary"
echo "  PHPCS: ${PHPCS_RESULT}"
echo "  PHPStan: ${PHPSTAN_RESULT}"
echo "  Semgrep: ${SEMGREP_RESULT}"
echo "  ABSPATH guard check: ${ABSPATH_RESULT}"

exit "${STATUS}"

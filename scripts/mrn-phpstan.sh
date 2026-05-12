#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_STACK_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
STACK_ROOT="${MRN_STACK_ROOT:-${DEFAULT_STACK_ROOT}}"
PHPSTAN_BIN="${STACK_ROOT}/vendor/bin/phpstan"
PHPSTAN_CONFIG="${MRN_PHPSTAN_CONFIG:-${STACK_ROOT}/phpstan.neon.dist}"
DEFAULT_MEMORY_LIMIT="${MRN_PHPSTAN_MEMORY_LIMIT:-2G}"

usage() {
	cat <<USAGE
Usage:
  scripts/mrn-phpstan.sh <path> [more-paths...]
  scripts/mrn-phpstan.sh --memory-limit=1G <path>
  scripts/mrn-phpstan.sh --level=3 <path>
  scripts/mrn-phpstan.sh --help

Notes:
- Uses: ${PHPSTAN_BIN}
- Config: ${PHPSTAN_CONFIG}
- Override stack root with MRN_STACK_ROOT.
- Override config with MRN_PHPSTAN_CONFIG.
USAGE
}

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
	usage
	exit 0
fi

if [[ ! -x "${PHPSTAN_BIN}" ]]; then
	echo "mrn-phpstan: PHPStan binary not found at ${PHPSTAN_BIN}" >&2
	echo "Run: cd ${STACK_ROOT} && composer install" >&2
	exit 1
fi

if [[ ! -f "${PHPSTAN_CONFIG}" ]]; then
	echo "mrn-phpstan: config not found at ${PHPSTAN_CONFIG}" >&2
	exit 1
fi

if [[ "$#" -eq 0 ]]; then
	usage >&2
	exit 1
fi

MEMORY_LIMIT="${DEFAULT_MEMORY_LIMIT}"
LEVEL_OVERRIDE=""
ARGS=()

for arg in "$@"; do
	if [[ "$arg" == --memory-limit=* ]]; then
		MEMORY_LIMIT="${arg#--memory-limit=}"
	elif [[ "$arg" == --level=* ]]; then
		LEVEL_OVERRIDE="${arg#--level=}"
	else
		ARGS+=("$arg")
	fi
done

if [[ "${#ARGS[@]}" -eq 0 ]]; then
	usage >&2
	exit 1
fi

CMD=(
	php "${PHPSTAN_BIN}" analyse
	--configuration="${PHPSTAN_CONFIG}"
	--memory-limit="${MEMORY_LIMIT}"
	--no-progress
)

if [[ -n "${LEVEL_OVERRIDE}" ]]; then
	CMD+=("--level=${LEVEL_OVERRIDE}")
fi

CMD+=("${ARGS[@]}")
"${CMD[@]}"

#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STACK_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLAYWRIGHT_SMOKE_SCRIPT="${STACK_ROOT}/stack/scripts/qa-playwright-local-stack-site.sh"
DEFAULT_LOCAL_SITE_ROOT="/Users/khofmeyer/Local Sites"
DEFAULT_STACK_SITE_PATH="${DEFAULT_LOCAL_SITE_ROOT}/mrn-plugin-stack/app/public"

usage() {
	cat <<USAGE
Usage:
  scripts/mrn-smoke.sh [site-path]
  scripts/mrn-smoke.sh --site-path /Users/.../Local Sites/<site>/app/public
  scripts/mrn-smoke.sh --scope public
  scripts/mrn-smoke.sh --scope full --site-path /Users/.../Local Sites/<site>/app/public
  scripts/mrn-smoke.sh --scope public --require-structure 1
  scripts/mrn-smoke.sh --help

Options:
  --site-path <path>   Explicit Local WordPress path to test.
  --scope <value>      Smoke scope: full (default) or public.
  --require-structure  Optional: 1 or 0. Overrides public-page structure checks.

Auto-detection order:
  1) --site-path
  2) MRN_SITE_PATH
  3) If current folder is a site repo at /Users/khofmeyer/Development/MRN-sites/<slug>, use /Users/khofmeyer/Local Sites/<slug>/app/public
  4) If current folder is inside /Users/khofmeyer/Local Sites/<name>/app/public, use that path
  5) Fallback to /Users/khofmeyer/Local Sites/mrn-plugin-stack/app/public

Environment passthrough:
  MRN_WP_ADMIN_USER
  MRN_WP_ADMIN_PASS
  MRN_MOTION_TARGET_CASES
USAGE
}

resolve_path() {
	local candidate="$1"
	(
		cd "$(dirname "${candidate}")" >/dev/null 2>&1
		printf '%s/%s\n' "$(pwd -P)" "$(basename "${candidate}")"
	)
}

detect_site_path_from_cwd() {
	local current_dir="$1"
	local slug=""
	local local_candidate=""
	local local_root="${DEFAULT_LOCAL_SITE_ROOT}"

	if [[ "${current_dir}" == /Users/khofmeyer/Development/MRN-sites/* ]]; then
		slug="${current_dir#/Users/khofmeyer/Development/MRN-sites/}"
		slug="${slug%%/*}"
		local_candidate="${local_root}/${slug}/app/public"
		if [[ -d "${local_candidate}" ]]; then
			printf '%s\n' "${local_candidate}"
			return 0
		fi
	fi

	if [[ "${current_dir}" == ${local_root}/*/app/public* ]]; then
		slug="${current_dir#${local_root}/}"
		slug="${slug%%/*}"
		local_candidate="${local_root}/${slug}/app/public"
		if [[ -d "${local_candidate}" ]]; then
			printf '%s\n' "${local_candidate}"
			return 0
		fi
	fi

	return 1
}

SITE_PATH=""
SMOKE_SCOPE="${MRN_SMOKE_SCOPE:-full}"
SMOKE_REQUIRE_STRUCTURE="${MRN_SMOKE_REQUIRE_STRUCTURE:-}"

while [[ $# -gt 0 ]]; do
	case "$1" in
		-h|--help)
			usage
			exit 0
			;;
		--site-path)
			if [[ $# -lt 2 ]]; then
				echo "--site-path requires a value" >&2
				exit 1
			fi
			SITE_PATH="$2"
			shift 2
			;;
		--scope)
			if [[ $# -lt 2 ]]; then
				echo "--scope requires a value" >&2
				exit 1
			fi
			SMOKE_SCOPE="$2"
			shift 2
			;;
		--scope=*)
			SMOKE_SCOPE="${1#--scope=}"
			shift
			;;
		--require-structure)
			if [[ $# -lt 2 ]]; then
				echo "--require-structure requires a value (0 or 1)" >&2
				exit 1
			fi
			SMOKE_REQUIRE_STRUCTURE="$2"
			shift 2
			;;
		--require-structure=*)
			SMOKE_REQUIRE_STRUCTURE="${1#--require-structure=}"
			shift
			;;
		--site-path=*)
			SITE_PATH="${1#--site-path=}"
			shift
			;;
		*)
			if [[ -z "${SITE_PATH}" ]]; then
				SITE_PATH="$1"
				shift
			else
				echo "Unexpected argument: $1" >&2
				exit 1
			fi
			;;
	esac
done

case "${SMOKE_SCOPE}" in
	full|public)
		;;
	*)
		echo "Invalid scope: ${SMOKE_SCOPE}" >&2
		echo "Valid values: full, public" >&2
		exit 1
		;;
esac

if [[ -n "${SMOKE_REQUIRE_STRUCTURE}" && "${SMOKE_REQUIRE_STRUCTURE}" != "0" && "${SMOKE_REQUIRE_STRUCTURE}" != "1" ]]; then
	echo "Invalid --require-structure value: ${SMOKE_REQUIRE_STRUCTURE}" >&2
	echo "Valid values: 0, 1" >&2
	exit 1
fi

if [[ -z "${SITE_PATH}" && -n "${MRN_SITE_PATH:-}" ]]; then
	SITE_PATH="${MRN_SITE_PATH}"
fi

if [[ -z "${SITE_PATH}" ]]; then
	if detected="$(detect_site_path_from_cwd "$(pwd -P)")"; then
		SITE_PATH="${detected}"
	fi
fi

if [[ -z "${SITE_PATH}" && -d "${DEFAULT_STACK_SITE_PATH}" ]]; then
	SITE_PATH="${DEFAULT_STACK_SITE_PATH}"
fi

if [[ -z "${SITE_PATH}" ]]; then
	echo "Could not auto-detect a Local site path." >&2
	echo "Pass one explicitly, for example:" >&2
	echo "  /Users/khofmeyer/Development/MRN/scripts/mrn-smoke.sh --site-path '/Users/khofmeyer/Local Sites/freedomhouse/app/public'" >&2
	exit 1
fi

if [[ ! -d "${SITE_PATH}" ]]; then
	echo "Site path not found: ${SITE_PATH}" >&2
	exit 1
fi

if [[ ! -f "${SITE_PATH}/wp-config.php" ]]; then
	echo "Path does not look like a WordPress root (missing wp-config.php): ${SITE_PATH}" >&2
	exit 1
fi

if [[ ! -x "${PLAYWRIGHT_SMOKE_SCRIPT}" ]]; then
	echo "Smoke script not found or not executable: ${PLAYWRIGHT_SMOKE_SCRIPT}" >&2
	exit 1
fi

SITE_PATH="$(resolve_path "${SITE_PATH}")"

echo "MRN smoke runner"
echo "  Scope: ${SMOKE_SCOPE}"
if [[ -n "${SMOKE_REQUIRE_STRUCTURE}" ]]; then
	echo "  Structure checks override: ${SMOKE_REQUIRE_STRUCTURE}"
fi
echo "  Site path: ${SITE_PATH}"
echo "  Harness: ${PLAYWRIGHT_SMOKE_SCRIPT}"

env \
	MRN_SMOKE_SCOPE="${SMOKE_SCOPE}" \
	MRN_SMOKE_REQUIRE_STRUCTURE="${SMOKE_REQUIRE_STRUCTURE}" \
	"${PLAYWRIGHT_SMOKE_SCRIPT}" "${SITE_PATH}"

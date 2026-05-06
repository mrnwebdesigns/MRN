#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
NEW_SCRIPT="${REPO_ROOT}/local/scripts/nightly-pull-mrndev-sites.sh"

[[ -x "${NEW_SCRIPT}" ]] || {
	echo "FAIL: Missing nightly Local workflow helper: ${NEW_SCRIPT}" >&2
	exit 1
}

exec "${NEW_SCRIPT}" "$@"

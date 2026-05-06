#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
NEW_SCRIPT="${REPO_ROOT}/local/scripts/local-env-workflow.sh"

[[ -x "${NEW_SCRIPT}" ]] || {
	echo "FAIL: Missing Local workflow helper: ${NEW_SCRIPT}" >&2
	exit 1
}

exec "${NEW_SCRIPT}" "$@"

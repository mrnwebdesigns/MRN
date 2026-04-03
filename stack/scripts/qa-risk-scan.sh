#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  qa-risk-scan.sh [path ...]

Examples:
  qa-risk-scan.sh
  qa-risk-scan.sh /path/to/theme /path/to/plugin.php
EOF
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
	usage
	exit 0
fi

if ! command -v rg >/dev/null 2>&1; then
	echo "Required command not found: rg" >&2
	exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

if [[ "$#" -eq 0 ]]; then
	set -- "${REPO_ROOT}"
fi

PATTERN='\beval\s*\(|\bbase64_decode\s*\(|\bexec\s*\(|\bshell_exec\s*\(|\bsystem\s*\(|\bpassthru\s*\(|\bproc_open\s*\(|\bpopen\s*\('

echo "Risk scan targets:"
for target in "$@"; do
	echo "  - ${target}"
done

if rg -n -S \
	--glob '!**/vendor/**' \
	--glob '!**/node_modules/**' \
	--glob '!**/.git/**' \
	--glob '!**/*.min.js' \
	"${PATTERN}" \
	"$@"
then
	echo
	echo "Risk scan found one or more high-risk patterns." >&2
	exit 1
fi

echo "Risk scan passed."

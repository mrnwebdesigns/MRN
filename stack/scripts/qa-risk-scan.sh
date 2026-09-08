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

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

if [[ "$#" -eq 0 ]]; then
	set -- "${REPO_ROOT}"
fi

PATTERN='\beval\s*\(|\bexec\s*\(|\bshell_exec\s*\(|\bsystem\s*\(|\bpassthru\s*\(|\bproc_open\s*\(|\bpopen\s*\('
GREP_PATTERN='(^|[^[:alnum:]_])(eval|exec|shell_exec|system|passthru|proc_open|popen)[[:space:]]*\('

echo "Risk scan targets:"
for target in "$@"; do
	echo "  - ${target}"
done

MATCH_FOUND=1

if command -v rg >/dev/null 2>&1; then
	if rg -n -S \
		--glob '!**/vendor/**' \
		--glob '!**/node_modules/**' \
		--glob '!**/.git/**' \
		--glob '!**/*.min.js' \
		--glob '!**/semgrep/**' \
		--glob '!**/phpcs.xml.dist' \
		"${PATTERN}" \
		"$@"; then
		MATCH_FOUND=0
	fi
elif command -v grep >/dev/null 2>&1 && command -v find >/dev/null 2>&1; then
	while IFS= read -r -d '' file; do
		if grep -EnI "${GREP_PATTERN}" "${file}"; then
			MATCH_FOUND=0
		fi
	done < <(
		find "$@" -type f \
			! -path '*/vendor/*' \
			! -path '*/node_modules/*' \
			! -path '*/.git/*' \
			! -name '*.min.js' \
			! -path '*/semgrep/*' \
			! -name 'phpcs.xml.dist' \
			-print0
	)
else
	echo "Required scanner not found: install rg, or both grep and find." >&2
	exit 1
fi

if [[ "${MATCH_FOUND}" -eq 0 ]]; then
	echo
	echo "Risk scan found one or more high-risk patterns." >&2
	exit 1
fi

echo "Risk scan passed."

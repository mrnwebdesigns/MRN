#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  qa-page-speed.sh <base-url> [path ...]

Examples:
  qa-page-speed.sh https://default-configs.mrndev.io / /sample-page/
  qa-page-speed.sh http://mrn-plugin-stack.local / /qa-hero/
EOF
}

if [[ $# -lt 1 || "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
	usage
	exit $([[ $# -lt 1 ]] && echo 1 || echo 0)
fi

if ! command -v curl >/dev/null 2>&1; then
	echo "Required command not found: curl" >&2
	exit 1
fi

BASE_URL="${1%/}"
shift

if [[ "$#" -eq 0 ]]; then
	set -- "/"
fi

printf "%-40s %-6s %-10s %-10s %-10s %-12s\n" "URL" "Code" "TTFB" "Total" "Bytes" "Bytes/sec"

for path in "$@"; do
	url="${BASE_URL}${path}"
	output="$(
		curl -L -sS -o /dev/null \
			-w '%{http_code} %{time_starttransfer} %{time_total} %{size_download} %{speed_download}' \
			"${url}"
	)"
	read -r http_code time_starttransfer time_total size_download speed_download <<<"${output}"
	printf "%-40s %-6s %-10s %-10s %-10s %-12s\n" "${url}" "${http_code}" "${time_starttransfer}" "${time_total}" "${size_download}" "${speed_download}"
done

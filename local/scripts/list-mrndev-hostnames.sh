#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  list-mrndev-hostnames.sh \
    [--discovery-ssh-host <ssh-host>] \
    [--cache-file <path>] \
    [--cache-ttl <seconds>] \
    [--refresh]

Description:
  Prints discovered *.mrndev.io hostnames from the dev server, one per line.
  Uses a short-lived cache for fast autocomplete.
EOF
}

DISCOVERY_SSH_HOST="${MRN_DISCOVERY_SSH_HOST:-mrndev}"
CACHE_FILE="${MRN_MRNDEV_HOSTS_CACHE_FILE:-$HOME/.cache/mrn/mrndev-hosts.txt}"
CACHE_TTL="${MRN_MRNDEV_HOSTS_CACHE_TTL:-300}"
REFRESH=0

while [[ $# -gt 0 ]]; do
	case "$1" in
		--discovery-ssh-host)
			DISCOVERY_SSH_HOST="${2:-}"
			shift 2
			;;
		--cache-file)
			CACHE_FILE="${2:-}"
			shift 2
			;;
		--cache-ttl)
			CACHE_TTL="${2:-}"
			shift 2
			;;
		--refresh)
			REFRESH=1
			shift
			;;
		-h|--help)
			usage
			exit 0
			;;
		*)
			echo "Unknown argument: $1" >&2
			usage >&2
			exit 1
			;;
	esac
done

for required in date find sed sort ssh tr; do
	command -v "${required}" >/dev/null 2>&1 || exit 0
done

file_mtime_epoch() {
	local path="$1"

	if stat -f %m "${path}" >/dev/null 2>&1; then
		stat -f %m "${path}"
		return 0
	fi

	if stat -c %Y "${path}" >/dev/null 2>&1; then
		stat -c %Y "${path}"
		return 0
	fi

	return 1
}

emit_cache_if_fresh() {
	local now
	local mtime
	local age

	[[ -f "${CACHE_FILE}" ]] || return 1
	[[ "${REFRESH}" -eq 0 ]] || return 1

	now="$(date +%s)"
	mtime="$(file_mtime_epoch "${CACHE_FILE}" || true)"
	[[ -n "${mtime}" ]] || return 1

	age=$((now - mtime))
	if [[ "${age}" -le "${CACHE_TTL}" ]]; then
		cat "${CACHE_FILE}"
		return 0
	fi

	return 1
}

discover_hosts_live() {
	local remote_script

	remote_script=$(cat <<'EOF'
set -euo pipefail
find /home -mindepth 3 -maxdepth 3 -type d -path "/home/*/htdocs/*.mrndev.io" 2>/dev/null \
	| sed -E 's#.*/htdocs/([^/]+)$#\1#' \
	| sort -u
EOF
)

	ssh \
		-o BatchMode=yes \
		-o ConnectTimeout=3 \
		"${DISCOVERY_SSH_HOST}" \
		"bash -s" <<< "${remote_script}" \
		| tr -d '\r' \
		| sed '/^[[:space:]]*$/d' \
		| sort -u
}

if emit_cache_if_fresh; then
	exit 0
fi

HOSTS="$(discover_hosts_live 2>/dev/null || true)"
if [[ -n "${HOSTS}" ]]; then
	mkdir -p "$(dirname "${CACHE_FILE}")"
	printf '%s\n' "${HOSTS}" > "${CACHE_FILE}"
	printf '%s\n' "${HOSTS}"
	exit 0
fi

if [[ -f "${CACHE_FILE}" ]]; then
	cat "${CACHE_FILE}"
	exit 0
fi

exit 0

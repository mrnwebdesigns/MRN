#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  resolve-live-site-owner.sh <site-hostname> [--ssh-host <ssh-host>]

Examples:
  resolve-live-site-owner.sh mrnwebdesigns.mrndev.io
  resolve-live-site-owner.sh default-configs.mrndev.io --ssh-host mrndev-kyle

Output:
  SITE_HOSTNAME=<site-hostname>
  SITE_USER=<site-owner-user>
  SITE_ROOT=<absolute-live-site-root>
  SSH_ALIAS=mrndev-site-owner
  SSH_LOGIN=<site-owner-user>@mrndev-site-owner
  SSH_VERIFY=ssh -l <site-owner-user> mrndev-site-owner 'whoami && pwd'

Notes:
  - This helper is read-only.
  - It uses the fallback/admin SSH path only to discover the live site owner.
  - Live file writes should still run as the returned site owner user via the site-owner SSH alias.
EOF
}

fail() {
	echo "FAIL: $*" >&2
	exit 1
}

SSH_HOST="mrndev"
SSH_ALIAS="mrndev-site-owner"
SITE_HOSTNAME=""

while [[ $# -gt 0 ]]; do
	case "$1" in
		--ssh-host)
			SSH_HOST="${2:-}"
			shift 2
			;;
		-h|--help)
			usage
			exit 0
			;;
		-*)
			echo "Unknown argument: $1" >&2
			usage >&2
			exit 1
			;;
		*)
			if [[ -n "${SITE_HOSTNAME}" ]]; then
				fail "Only one site hostname may be provided."
			fi
			SITE_HOSTNAME="$1"
			shift
			;;
	esac
done

if [[ -z "${SITE_HOSTNAME}" ]]; then
	usage >&2
	exit 1
fi

for required in ssh sort; do
	command -v "${required}" >/dev/null 2>&1 || fail "Required command not found: ${required}"
done

REMOTE_SCRIPT=$(cat <<'EOF'
set -euo pipefail

site_hostname="$1"

find /home -mindepth 3 -maxdepth 3 -type d -path "/home/*/htdocs/${site_hostname}" 2>/dev/null | sort
EOF
)

MATCHES="$(
	ssh "${SSH_HOST}" "bash -s -- '${SITE_HOSTNAME}'" <<<"${REMOTE_SCRIPT}" | tr -d '\r' || true
)"

if [[ -z "${MATCHES}" ]]; then
	fail "Could not resolve a live site root for ${SITE_HOSTNAME} via ${SSH_HOST}."
fi

MATCH_COUNT="$(printf '%s\n' "${MATCHES}" | awk 'NF { count++ } END { print count + 0 }')"

if [[ "${MATCH_COUNT}" -gt 1 ]]; then
	echo "FAIL: Multiple live site roots matched ${SITE_HOSTNAME}:" >&2
	printf '  %s\n' "${MATCHES}" >&2
	exit 1
fi

SITE_ROOT="${MATCHES}"
SITE_USER="$(printf '%s\n' "${SITE_ROOT}" | awk -F/ '{print $3}')"

if [[ -z "${SITE_USER}" || -z "${SITE_ROOT}" ]]; then
	fail "Resolved site details were incomplete for ${SITE_HOSTNAME}."
fi

printf 'SITE_HOSTNAME=%s\n' "${SITE_HOSTNAME}"
printf 'SITE_USER=%s\n' "${SITE_USER}"
printf 'SITE_ROOT=%s\n' "${SITE_ROOT}"
printf 'SSH_ALIAS=%s\n' "${SSH_ALIAS}"
printf 'SSH_LOGIN=%s@%s\n' "${SITE_USER}" "${SSH_ALIAS}"
printf "SSH_VERIFY=ssh -l %s %s 'whoami && pwd'\n" "${SITE_USER}" "${SSH_ALIAS}"

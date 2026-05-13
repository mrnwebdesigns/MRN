#!/usr/bin/env bash
set -euo pipefail

# Run as root on the server. Mirrors selected root-owned config files into a
# stack-manager-readable export tree for Git snapshot pulls.

EXPORT_ROOT="${EXPORT_ROOT:-/home/mrndev-stack-manager/stack/server-config-export}"
EXPORT_OWNER="${EXPORT_OWNER:-mrndev-stack-manager:mrndev-stack-manager}"

copy_export() {
	local src="$1"
	local rel="$2"
	local dst="${EXPORT_ROOT}/${rel}"

	if [[ ! -f "${src}" ]]; then
		return 0
	fi

	mkdir -p "$(dirname "${dst}")"
	cp "${src}" "${dst}"
	chown "${EXPORT_OWNER}" "${dst}"
	chmod 644 "${dst}"
}

copy_export "/usr/local/sbin/stack-load-alerts.sh" "usr/local/sbin/stack-load-alerts.sh"

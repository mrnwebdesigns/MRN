#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  remediate-site-owner-readiness.sh \
    --site-hostname <site-hostname> [--site-hostname <site-hostname> ...] \
    [--discovery-ssh-host <ssh-host>] \
    [--site-owner-key-file <path>]

Description:
  Remediates site-owner readiness for stack-packaged plugin rollouts by:
  1) ensuring canonical site-owner SSH key is present in /home/<site-user>/.ssh/authorized_keys
  2) normalizing mrn-config-helper plugin ownership/mode to <site-user>:<site-user> + 755/644

Notes:
  - This script requires privileged sudo on the discovery host.
  - It is intended for ops/admin use when parity audits show:
      - site_owner_ssh_ok=no
      - site_owner_can_write=no
EOF
}

DISCOVERY_SSH_HOST="mrndev"
SITE_OWNER_KEY_FILE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/configs/site-owner-authorized-key.pub"
declare -a SITE_HOSTNAMES=()
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

while [[ $# -gt 0 ]]; do
	case "$1" in
		--site-hostname)
			SITE_HOSTNAMES+=("${2:-}")
			shift 2
			;;
		--discovery-ssh-host)
			DISCOVERY_SSH_HOST="${2:-}"
			shift 2
			;;
		--site-owner-key-file)
			SITE_OWNER_KEY_FILE="${2:-}"
			shift 2
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

if [[ "${#SITE_HOSTNAMES[@]}" -eq 0 ]]; then
	echo "At least one --site-hostname is required." >&2
	usage >&2
	exit 1
fi

if [[ ! -f "$SITE_OWNER_KEY_FILE" ]]; then
	echo "Site owner key file not found: $SITE_OWNER_KEY_FILE" >&2
	exit 1
fi

SITE_OWNER_KEY="$(tr -d '\r\n' < "$SITE_OWNER_KEY_FILE")"
if [[ -z "$SITE_OWNER_KEY" ]]; then
	echo "Site owner key file is empty: $SITE_OWNER_KEY_FILE" >&2
	exit 1
fi

for required in ssh grep sed tr; do
	command -v "$required" >/dev/null 2>&1 || {
		echo "Required command not found: $required" >&2
		exit 1
	}
done

echo "site,site_user,site_root,ssh_key_remediated,plugin_owner_remediated"

for SITE_HOSTNAME in "${SITE_HOSTNAMES[@]}"; do
	if [[ -z "$SITE_HOSTNAME" ]]; then
		continue
	fi

	RESOLVE_OUTPUT="$("${SCRIPT_DIR}/resolve-live-site-owner.sh" "$SITE_HOSTNAME" --ssh-host "$DISCOVERY_SSH_HOST")"
	SITE_USER="$(printf '%s\n' "$RESOLVE_OUTPUT" | awk -F= '/^SITE_USER=/{print $2}')"
	SITE_ROOT="$(printf '%s\n' "$RESOLVE_OUTPUT" | awk -F= '/^SITE_ROOT=/{print $2}')"

	if [[ -z "$SITE_USER" || -z "$SITE_ROOT" ]]; then
		echo "Failed resolving site owner/root for $SITE_HOSTNAME" >&2
		exit 1
	fi

	REMOTE_SCRIPT=$(cat <<'EOS'
set -euo pipefail
SITE_USER="$1"
SITE_ROOT="$2"
SITE_OWNER_KEY="$3"

HOME_DIR="/home/${SITE_USER}"
SSH_DIR="${HOME_DIR}/.ssh"
AUTH_KEYS="${SSH_DIR}/authorized_keys"
PLUGIN_DIR="${SITE_ROOT}/wp-content/plugins/mrn-config-helper"

sudo -n chmod g-w,o-w "${HOME_DIR}"
sudo -n install -d -m 700 -o "${SITE_USER}" -g "${SITE_USER}" "${SSH_DIR}"
sudo -n touch "${AUTH_KEYS}"
sudo -n chown "${SITE_USER}:${SITE_USER}" "${AUTH_KEYS}"
sudo -n chmod 600 "${AUTH_KEYS}"

if ! sudo -n grep -Fqx "${SITE_OWNER_KEY}" "${AUTH_KEYS}"; then
	printf '%s\n' "${SITE_OWNER_KEY}" | sudo -n tee -a "${AUTH_KEYS}" >/dev/null
fi

if [[ -d "${PLUGIN_DIR}" ]]; then
	sudo -n chown -R "${SITE_USER}:${SITE_USER}" "${PLUGIN_DIR}"
	sudo -n find "${PLUGIN_DIR}" -type d -exec chmod 755 {} +
	sudo -n find "${PLUGIN_DIR}" -type f -exec chmod 644 {} +
fi
EOS
)

	ssh "$DISCOVERY_SSH_HOST" "bash -s -- '$SITE_USER' '$SITE_ROOT' '$SITE_OWNER_KEY'" <<<"$REMOTE_SCRIPT"

	echo "${SITE_HOSTNAME},${SITE_USER},${SITE_ROOT},yes,yes"
done

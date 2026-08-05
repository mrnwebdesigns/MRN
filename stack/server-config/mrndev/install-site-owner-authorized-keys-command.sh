#!/usr/bin/env bash
set -euo pipefail

KEY_SOURCE="/home/mrndev-stack-manager/stack/configs/site-owner-authorized-key.pub"
COMMAND_PATH="/usr/local/sbin/mrn-site-owner-authorized-keys"
SSHD_CONFIG_PATH="/etc/ssh/sshd_config.d/98-mrn-site-owner-cloudpanel.conf"

if [[ "${EUID}" -ne 0 ]]; then
	echo "Run as root." >&2
	exit 1
fi

if [[ ! -s "${KEY_SOURCE}" ]]; then
	echo "Missing shared site-owner public key: ${KEY_SOURCE}" >&2
	exit 1
fi

install -d -m 755 /usr/local/sbin
cat > "${COMMAND_PATH}" <<'SCRIPT'
#!/usr/bin/env bash
set -euo pipefail

USER_NAME="${1:-}"
CLOUDPANEL_DB="/home/clp/htdocs/app/data/db.sq3"
SITE_OWNER_KEY="/home/mrndev-stack-manager/stack/configs/site-owner-authorized-key.pub"

case "${USER_NAME}" in
	""|root|clp|kyle|mrn-ops|mrndev-stack-manager)
		exit 0
		;;
esac

if [[ ! "${USER_NAME}" =~ ^[a-z][-a-z0-9_]{2,31}$ ]]; then
	exit 0
fi

if [[ ! -r "${CLOUDPANEL_DB}" || ! -s "${SITE_OWNER_KEY}" ]]; then
	exit 0
fi

if ! getent passwd "${USER_NAME}" >/dev/null 2>&1; then
	exit 0
fi

MATCH="$(
	sqlite3 "${CLOUDPANEL_DB}" \
		"SELECT 1 FROM site WHERE user = '${USER_NAME}' LIMIT 1;" 2>/dev/null || true
)"

if [[ "${MATCH}" != "1" ]]; then
	MATCH="$(
		sqlite3 "${CLOUDPANEL_DB}" \
			"SELECT 1 FROM ssh_user WHERE user_name = '${USER_NAME}' LIMIT 1;" 2>/dev/null || true
	)"
fi

if [[ "${MATCH}" == "1" ]]; then
	cat "${SITE_OWNER_KEY}"
fi
SCRIPT

chown root:root "${COMMAND_PATH}"
chmod 755 "${COMMAND_PATH}"

cat > "${SSHD_CONFIG_PATH}" <<EOF
# MRN CloudPanel site-owner SSH.
# Allows the shared MRN site-owner key for Linux users that CloudPanel knows as
# site owners or CloudPanel SSH users. Existing per-user authorized_keys remain
# valid via sshd's default AuthorizedKeysFile behavior.
AuthorizedKeysCommand ${COMMAND_PATH} %u
AuthorizedKeysCommandUser root
EOF

chown root:root "${SSHD_CONFIG_PATH}"
chmod 644 "${SSHD_CONFIG_PATH}"

sshd -t
systemctl reload ssh

echo "Installed MRN CloudPanel site-owner AuthorizedKeysCommand."

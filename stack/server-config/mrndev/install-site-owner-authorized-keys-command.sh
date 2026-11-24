#!/usr/bin/env bash
set -euo pipefail

KEY_SOURCE="/home/mrndev-stack-manager/stack/configs/site-owner-authorized-key.pub"
COMMAND_PATH="/usr/local/sbin/mrn-site-owner-authorized-keys"
SSHD_CONFIG_PATH="/etc/ssh/sshd_config.d/98-mrn-site-owner-cloudpanel.conf"
DEPLOY_KEY_DIR="/etc/ssh/mrn-site-deploy-keys"

if [[ "${EUID}" -ne 0 ]]; then
	echo "Run as root." >&2
	exit 1
fi

if [[ ! -s "${KEY_SOURCE}" ]]; then
	echo "Missing shared site-owner public key: ${KEY_SOURCE}" >&2
	exit 1
fi

install -d -m 755 /usr/local/sbin
install -d -o root -g root -m 755 "${DEPLOY_KEY_DIR}"
cat > "${COMMAND_PATH}" <<'SCRIPT'
#!/usr/bin/env bash
set -euo pipefail

# Prints extra authorized keys for a CloudPanel user. sshd runs it as root.
# 1) The shared MRN site-owner key, for CloudPanel site owners and SSH users.
# 2) A site's own deploy key (for example the GitHub Actions key), for site owners
#    only, from a root-owned file /etc/ssh/mrn-site-deploy-keys/<site-user>.pub.
# Keys served here keep working when CloudPanel leaves the site home group-writable
# for its extra SSH users, which makes sshd ignore ~/.ssh/authorized_keys.

USER_NAME="${1:-}"
CLOUDPANEL_DB="/home/clp/htdocs/app/data/db.sq3"
SITE_OWNER_KEY="/home/mrndev-stack-manager/stack/configs/site-owner-authorized-key.pub"
DEPLOY_KEY_DIR="/etc/ssh/mrn-site-deploy-keys"

case "${USER_NAME}" in
	""|root|clp|kyle|mrn-ops|mrndev-stack-manager)
		exit 0
		;;
esac

if [[ ! "${USER_NAME}" =~ ^[a-z][-a-z0-9_]{2,31}$ ]]; then
	exit 0
fi

if [[ ! -r "${CLOUDPANEL_DB}" ]]; then
	exit 0
fi

if ! getent passwd "${USER_NAME}" >/dev/null 2>&1; then
	exit 0
fi

cloudpanel_has() {
	[[ "$(sqlite3 "${CLOUDPANEL_DB}" "$1" 2>/dev/null || true)" == "1" ]]
}

# Root-owned (owned by this process's user), a regular file, not a symlink, and
# not writable by group or others.
trusted_key_file() {
	local path="$1"
	[[ -f "${path}" && ! -L "${path}" && -O "${path}" && -s "${path}" ]] || return 1
	not_shared_writable "${path}"
}

IS_SITE_OWNER=0
if cloudpanel_has "SELECT 1 FROM site WHERE user = '${USER_NAME}' LIMIT 1;"; then
	IS_SITE_OWNER=1
fi

if [[ "${IS_SITE_OWNER}" == "1" ]] || cloudpanel_has "SELECT 1 FROM ssh_user WHERE user_name = '${USER_NAME}' LIMIT 1;"; then
	if [[ -s "${SITE_OWNER_KEY}" ]]; then
		cat "${SITE_OWNER_KEY}"
	fi
fi

not_shared_writable() {
	[[ -z "$(find "$1" -maxdepth 0 \( -perm -g+w -o -perm -o+w \) -print 2>/dev/null)" ]]
}

DEPLOY_KEY="${DEPLOY_KEY_DIR}/${USER_NAME}.pub"
if [[ "${IS_SITE_OWNER}" == "1" && -d "${DEPLOY_KEY_DIR}" && ! -L "${DEPLOY_KEY_DIR}" && -O "${DEPLOY_KEY_DIR}" ]] \
	&& not_shared_writable "${DEPLOY_KEY_DIR}" && trusted_key_file "${DEPLOY_KEY}"; then
	cat "${DEPLOY_KEY}"
fi
SCRIPT

chown root:root "${COMMAND_PATH}"
chmod 755 "${COMMAND_PATH}"

cat > "${SSHD_CONFIG_PATH}" <<EOF
# MRN CloudPanel site-owner SSH.
# Allows the shared MRN site-owner key for Linux users that CloudPanel knows as
# site owners or CloudPanel SSH users, and each site owner's deploy key from
# ${DEPLOY_KEY_DIR}/<site-user>.pub. Existing per-user authorized_keys remain
# valid via sshd's default AuthorizedKeysFile behavior.
AuthorizedKeysCommand ${COMMAND_PATH} %u
AuthorizedKeysCommandUser root
EOF

chown root:root "${SSHD_CONFIG_PATH}"
chmod 644 "${SSHD_CONFIG_PATH}"

sshd -t
systemctl reload ssh

echo "Installed MRN CloudPanel site-owner AuthorizedKeysCommand."

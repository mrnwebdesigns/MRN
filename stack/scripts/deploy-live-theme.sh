#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  deploy-live-theme.sh \
    [--site-hostname <site-hostname>] \
    --site-user <site-user> \
    --site-path <absolute-site-root> \
    --theme-src <local-theme-dir> \
    --remote-theme-path <absolute-live-theme-dir> \
    [--ssh-host <ssh-host>] \
    [--discovery-ssh-host <ssh-host>] \
    [--direct-ssh] \
    [--preserve-theme-name <theme-name>] \
    [--preserve-text-domain <text-domain>]

Example:
  deploy-live-theme.sh \
    --ssh-host mrndev-ops \
    --site-user mrndev-default-configs-stack \
    --site-path /home/mrndev-default-configs-stack/htdocs/default-configs.mrndev.io \
    --theme-src /Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack \
    --remote-theme-path /home/mrndev-default-configs-stack/htdocs/default-configs.mrndev.io/wp-content/themes/default-configs \
    --preserve-theme-name "default configs" \
    --preserve-text-domain default-configs

Direct site-owner example:
  deploy-live-theme.sh \
    --ssh-host mrndev-default-configs-stack@167.99.54.77 \
    --site-user mrndev-default-configs-stack \
    --site-path /home/mrndev-default-configs-stack/htdocs/default-configs.mrndev.io \
    --theme-src /Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack \
    --remote-theme-path /home/mrndev-default-configs-stack/htdocs/default-configs.mrndev.io/wp-content/themes/default-configs \
    --direct-ssh \
    --preserve-theme-name "default configs" \
    --preserve-text-domain default-configs
EOF
}

SSH_HOST="mrndev-ops"
DISCOVERY_SSH_HOST=""
SITE_HOSTNAME=""
SITE_USER=""
SITE_PATH=""
THEME_SRC=""
REMOTE_THEME_PATH=""
PRESERVE_THEME_NAME=""
PRESERVE_TEXT_DOMAIN=""
DIRECT_SSH=0
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

while [[ $# -gt 0 ]]; do
	case "$1" in
		--site-hostname)
			SITE_HOSTNAME="${2:-}"
			shift 2
			;;
		--ssh-host)
			SSH_HOST="${2:-}"
			shift 2
			;;
		--discovery-ssh-host)
			DISCOVERY_SSH_HOST="${2:-}"
			shift 2
			;;
		--site-user)
			SITE_USER="${2:-}"
			shift 2
			;;
		--site-path)
			SITE_PATH="${2:-}"
			shift 2
			;;
		--theme-src)
			THEME_SRC="${2:-}"
			shift 2
			;;
		--remote-theme-path)
			REMOTE_THEME_PATH="${2:-}"
			shift 2
			;;
		--direct-ssh)
			DIRECT_SSH=1
			shift
			;;
		--preserve-theme-name)
			PRESERVE_THEME_NAME="${2:-}"
			shift 2
			;;
		--preserve-text-domain)
			PRESERVE_TEXT_DOMAIN="${2:-}"
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

if [[ -z "$SITE_USER" || -z "$SITE_PATH" || -z "$THEME_SRC" || -z "$REMOTE_THEME_PATH" ]]; then
	usage >&2
	exit 1
fi

if [[ -n "${SITE_HOSTNAME}" ]]; then
	PREP_DISCOVERY_HOST="${DISCOVERY_SSH_HOST:-${SSH_HOST}}"
	PREP_OUTPUT="$("${SCRIPT_DIR}/preflight-live-site-deploy.sh" --site-hostname "${SITE_HOSTNAME}" --discovery-ssh-host "${PREP_DISCOVERY_HOST}")"
	RESOLVED_SITE_USER=""
	RESOLVED_SITE_ROOT=""
	RESOLVED_SSH_LOGIN=""

	while IFS='=' read -r key value; do
		case "${key}" in
			SITE_USER) RESOLVED_SITE_USER="${value}" ;;
			SITE_ROOT) RESOLVED_SITE_ROOT="${value}" ;;
			SSH_LOGIN) RESOLVED_SSH_LOGIN="${value}" ;;
		esac
	done <<< "${PREP_OUTPUT}"

	if [[ -z "${RESOLVED_SITE_USER}" || -z "${RESOLVED_SITE_ROOT}" || -z "${RESOLVED_SSH_LOGIN}" ]]; then
		echo "Live-site preflight did not return complete details for ${SITE_HOSTNAME}." >&2
		exit 1
	fi

	if [[ "${SITE_USER}" != "${RESOLVED_SITE_USER}" ]]; then
		echo "Resolved site owner (${RESOLVED_SITE_USER}) does not match --site-user (${SITE_USER})." >&2
		exit 1
	fi

	if [[ "${SITE_PATH}" != "${RESOLVED_SITE_ROOT}" ]]; then
		echo "Resolved site path (${RESOLVED_SITE_ROOT}) does not match --site-path (${SITE_PATH})." >&2
		exit 1
	fi

	SSH_HOST="${RESOLVED_SSH_LOGIN}"
	DIRECT_SSH=1
fi

if [[ ! -d "$THEME_SRC" ]]; then
	echo "Theme source directory not found: $THEME_SRC" >&2
	exit 1
fi

for required in rsync ssh; do
	if ! command -v "$required" >/dev/null 2>&1; then
		echo "Required command not found: $required" >&2
		exit 1
	fi
done

RSYNC_EXCLUDES=(
	--exclude=.git
	--exclude=.DS_Store
	--exclude=node_modules
	--exclude=vendor
	--exclude=sass
	--exclude=package-lock.json
	--exclude=package.json
	--exclude=composer.lock
	--exclude=composer.json
	--exclude=README.md
	--exclude=.gitignore
	--exclude=.gitattributes
	--exclude=.github
	--exclude=.travis.yml
	--exclude=phpcs.xml.dist
	--exclude=.stylelintrc.json
	--exclude=.eslintrc
	--exclude=style.css.map
	--exclude=yarn.lock
)

echo "Syncing theme to live site as ${SITE_USER} via ${SSH_HOST}..."

RSYNC_ARGS=(
	-rlt
	--delete
	--omit-dir-times
	"${RSYNC_EXCLUDES[@]}"
	"${THEME_SRC}/"
	"${SSH_HOST}:${REMOTE_THEME_PATH}/"
)

if [[ "${DIRECT_SSH}" -eq 1 ]]; then
	rsync "${RSYNC_ARGS[@]}"
else
	rsync \
		--rsync-path="sudo -n -u ${SITE_USER} rsync" \
		"${RSYNC_ARGS[@]}"
fi

if [[ -n "$PRESERVE_THEME_NAME" || -n "$PRESERVE_TEXT_DOMAIN" ]]; then
	REMOTE_STYLE="${REMOTE_THEME_PATH}/style.css"
	REMOTE_PATCH="perl -0pi -e '"

	if [[ -n "$PRESERVE_THEME_NAME" ]]; then
		REMOTE_PATCH+="s/^Theme Name:\\s*.*\$/Theme Name: ${PRESERVE_THEME_NAME}/m;"
	fi

	if [[ -n "$PRESERVE_TEXT_DOMAIN" ]]; then
		REMOTE_PATCH+="s/^Text Domain:\\s*.*\$/Text Domain: ${PRESERVE_TEXT_DOMAIN}/m;"
	fi

	REMOTE_PATCH+="' '${REMOTE_STYLE}'"

	if [[ "${DIRECT_SSH}" -eq 1 ]]; then
		ssh "$SSH_HOST" "${REMOTE_PATCH}"
	else
		ssh "$SSH_HOST" "sudo -n -u ${SITE_USER} ${REMOTE_PATCH}"
	fi
fi

if [[ "${DIRECT_SSH}" -eq 1 ]]; then
	ssh "$SSH_HOST" "find '${REMOTE_THEME_PATH}' -type d -exec chmod 755 {} +"
	ssh "$SSH_HOST" "find '${REMOTE_THEME_PATH}' -type f -exec chmod 644 {} +"
else
	ssh "$SSH_HOST" "sudo -n -u ${SITE_USER} find '${REMOTE_THEME_PATH}' -type d -exec chmod 755 {} +"
	ssh "$SSH_HOST" "sudo -n -u ${SITE_USER} find '${REMOTE_THEME_PATH}' -type f -exec chmod 644 {} +"
fi

ssh "$SSH_HOST" "stat -c '%U:%G %a %n' '${REMOTE_THEME_PATH}/style.css'"

echo "Live theme deploy completed."

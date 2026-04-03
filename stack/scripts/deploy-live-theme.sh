#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  deploy-live-theme.sh \
    --site-user <site-user> \
    --site-path <absolute-site-root> \
    --theme-src <local-theme-dir> \
    --remote-theme-path <absolute-live-theme-dir> \
    [--ssh-host <ssh-host>] \
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
SITE_USER=""
SITE_PATH=""
THEME_SRC=""
REMOTE_THEME_PATH=""
PRESERVE_THEME_NAME=""
PRESERVE_TEXT_DOMAIN=""
DIRECT_SSH=0

while [[ $# -gt 0 ]]; do
	case "$1" in
		--ssh-host)
			SSH_HOST="${2:-}"
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

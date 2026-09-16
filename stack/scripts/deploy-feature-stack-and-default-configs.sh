#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  deploy-feature-stack-and-default-configs.sh [--ssh-host <ssh-host>] [--bootstrap-contract-only] [--dry-run]

Description:
  Sync the canonical stack theme, stack MU plugin source, and stack MU loader
  wrappers to the stack server source-of-truth paths
  (/home/mrndev-stack-manager/stack on the target host). This is the tree
  site-bootstrap.sh reads MU-plugin source from when provisioning a new
  CloudPanel site, so this sync is what actually gets new sites onto current
  stack code.

Notes:
  - This is the canonical feature-deploy helper for stack theme and stack MU work.
  - Standard plugins still follow their own plugin release flow.
  - --bootstrap-contract-only publishes only site-bootstrap.sh, its importer,
    and the importer manifest. It does not touch release-managed runtime code.
  - The default target is the configured mrndev-stack-manager SSH alias, which
    pins the manager identity and prevents SSH agent identity exhaustion.
  - This script no longer syncs a live reference site. It previously also
    synced to default-configs.mrndev.io, but that site no longer exists on
    this host (confirmed 2026-08-20: absent from the full /home account
    listing under mrndev-stack-manager). If a live reference site is needed
    again, use deploy-live-theme.sh against a specific site once one exists.
EOF
}

SSH_HOST="mrndev-stack-manager"
DRY_RUN=0
BOOTSTRAP_CONTRACT_ONLY=0
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

while [[ $# -gt 0 ]]; do
	case "$1" in
		--ssh-host)
			SSH_HOST="${2:-}"
			shift 2
			;;
		--dry-run)
			DRY_RUN=1
			shift
			;;
		--bootstrap-contract-only)
			BOOTSTRAP_CONTRACT_ONLY=1
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

STACK_ROOT_REMOTE="/home/mrndev-stack-manager/stack"
LOCAL_THEME_DIR="${REPO_ROOT}/stack/themes/mrn-base-stack"
LOCAL_STACK_MU_DIR="${REPO_ROOT}/stack/mu-plugins"
LOCAL_STACK_RELEASE_LOCK="${REPO_ROOT}/stack/manifests/stack-release.lock.json"
LOCAL_SITE_BOOTSTRAP="${REPO_ROOT}/stack/scripts/site-bootstrap.sh"
LOCAL_STACK_EXPORT_IMPORTER="${REPO_ROOT}/stack/configs/importers/stack-export-importer.sh"
LOCAL_IMPORTERS_MANIFEST="${REPO_ROOT}/stack/manifests/importers.txt"
LOCAL_MU_SOURCE_ROOT="${REPO_ROOT}/mu-plugins"
LOCAL_SHARED_DIR="${REPO_ROOT}/shared"

MU_PLUGIN_DIRS=(
	"mrn-active-style-guide"
	"mrn-admin-data-post-types"
	"mrn-admin-ui-css"
	"mrn-dashboard-support"
	"mrn-disable-comments"
	"mrn-editor-lockdown"
	"mrn-environment-runtime"
	"mrn-public-security-hardening"
	"mrn-schema-bridge"
	"mrn-shared-assets"
	"mrn-site-colors"
	"mrn-updraft-local-retention"
)

THEME_EXCLUDES=(
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
	--exclude=playwright-report
	--exclude=test-results
)

COMMON_DIR_EXCLUDES=(
	--exclude=.git
	--exclude=.gitignore
	--exclude=.DS_Store
)

RSYNC_FLAGS=(
	-rlt
	--delete
	--omit-dir-times
)

if [[ "${DRY_RUN}" -eq 1 ]]; then
	RSYNC_FLAGS+=(--dry-run --itemize-changes)
fi

for required in python3 rsync ssh; do
	if ! command -v "${required}" >/dev/null 2>&1; then
		echo "Required command not found: ${required}" >&2
		exit 1
	fi
done

for required_file in "${LOCAL_SITE_BOOTSTRAP}" "${LOCAL_STACK_EXPORT_IMPORTER}" "${LOCAL_IMPORTERS_MANIFEST}"; do
	if [[ ! -f "${required_file}" ]]; then
		echo "Required bootstrap source not found: ${required_file}" >&2
		exit 1
	fi
done

if [[ "${BOOTSTRAP_CONTRACT_ONLY}" -eq 0 ]]; then
	if [[ ! -d "${LOCAL_THEME_DIR}" ]]; then
		echo "Theme source directory not found: ${LOCAL_THEME_DIR}" >&2
		exit 1
	fi

	if [[ ! -d "${LOCAL_SHARED_DIR}" ]]; then
		echo "Shared source directory not found: ${LOCAL_SHARED_DIR}" >&2
		exit 1
	fi

	if [[ ! -f "${LOCAL_STACK_RELEASE_LOCK}" ]]; then
		echo "Stack release lock not found: ${LOCAL_STACK_RELEASE_LOCK}" >&2
		exit 1
	fi

	python3 "${REPO_ROOT}/stack/scripts/generate-stack-release-lock.py" \
		--check "${LOCAL_STACK_RELEASE_LOCK}" >/dev/null

	for slug in "${MU_PLUGIN_DIRS[@]}"; do
		if [[ ! -d "${LOCAL_MU_SOURCE_ROOT}/${slug}" ]]; then
			echo "MU plugin source directory not found: ${LOCAL_MU_SOURCE_ROOT}/${slug}" >&2
			exit 1
		fi
	done
fi

run_rsync() {
	local source="$1"
	local destination="$2"
	shift 2
	echo "Syncing ${source} -> ${destination}"
	rsync "${RSYNC_FLAGS[@]}" "$@" "${source}" "${destination}"
}

run_remote() {
	local remote_host="$1"
	local command="$2"
	ssh "${remote_host}" "${command}"
}

verify_remote_file_sha256() {
	local local_file="$1"
	local remote_file="$2"
	local local_sha remote_sha

	local_sha="$(python3 -c 'import hashlib,sys; print(hashlib.sha256(open(sys.argv[1], "rb").read()).hexdigest())' "${local_file}")"
	remote_sha="$(run_remote "${SSH_HOST}" "sha256sum '${remote_file}' | cut -d ' ' -f1" | tr -d '\r\n')"
	if [[ "${local_sha}" != "${remote_sha}" ]]; then
		echo "ERROR: Remote source hash mismatch: ${remote_file}" >&2
		return 1
	fi
	echo "Verified source parity: ${remote_file}"
}

normalize_remote_tree_permissions() {
	local remote_host="$1"
	local path="$2"
	local label="$3"
	local user_filter="${4:-}"
	local find_prefix="find '${path}'"

	if [[ -n "${user_filter}" ]]; then
		find_prefix+=" -user '${user_filter}'"
	fi

	echo "Normalizing ${label} permissions..."
	run_remote "${remote_host}" "${find_prefix} -type d -exec chmod 755 {} +"
	run_remote "${remote_host}" "${find_prefix} -type f -not -path '*/.git/*' -exec chmod 644 {} +"
}

verify_remote_tree_file_modes() {
	local remote_host="$1"
	local path="$2"
	local label="$3"
	local user_filter="${4:-}"
	local find_prefix="find '${path}'"
	local out_of_spec=""

	if [[ -n "${user_filter}" ]]; then
		find_prefix+=" -user '${user_filter}'"
	fi

	out_of_spec="$(run_remote "${remote_host}" "${find_prefix} -type f -not -path '*/.git/*' ! -perm 644 -print | head -n 20" | tr -d '\r')"
	if [[ -n "${out_of_spec}" ]]; then
		echo "ERROR: ${label} still has files that are not mode 644 after normalization." >&2
		echo "${out_of_spec}" >&2
		return 1
	fi
}

echo "Deploying stack feature surfaces to ${SSH_HOST}..."

if [[ "${BOOTSTRAP_CONTRACT_ONLY}" -eq 0 ]]; then
	run_rsync \
		"${LOCAL_THEME_DIR}/" \
		"${SSH_HOST}:${STACK_ROOT_REMOTE}/themes/mrn-base-stack/" \
		"${THEME_EXCLUDES[@]}"

	run_rsync \
		"${LOCAL_SHARED_DIR}/" \
		"${SSH_HOST}:${STACK_ROOT_REMOTE}/shared/" \
		"${COMMON_DIR_EXCLUDES[@]}"

	for slug in "${MU_PLUGIN_DIRS[@]}"; do
		run_rsync \
			"${LOCAL_MU_SOURCE_ROOT}/${slug}/" \
			"${SSH_HOST}:${STACK_ROOT_REMOTE}/mu-plugins/${slug}/" \
			"${COMMON_DIR_EXCLUDES[@]}"
	done

	for wrapper in "${LOCAL_STACK_MU_DIR}"/mrn-*.php; do
		[[ -f "${wrapper}" ]] || continue
		run_rsync \
			"${wrapper}" \
			"${SSH_HOST}:${STACK_ROOT_REMOTE}/mu-plugins/$(basename "${wrapper}")"
	done

	run_rsync \
		"${LOCAL_STACK_RELEASE_LOCK}" \
		"${SSH_HOST}:${STACK_ROOT_REMOTE}/mu-plugins/mrn-stack-release.lock.json"
fi

run_rsync \
	"${LOCAL_SITE_BOOTSTRAP}" \
	"${SSH_HOST}:${STACK_ROOT_REMOTE}/scripts/site-bootstrap.sh"
run_rsync \
	"${LOCAL_STACK_EXPORT_IMPORTER}" \
	"${SSH_HOST}:${STACK_ROOT_REMOTE}/configs/importers/stack-export-importer.sh"
run_rsync \
	"${LOCAL_IMPORTERS_MANIFEST}" \
	"${SSH_HOST}:${STACK_ROOT_REMOTE}/manifests/importers.txt"

if [[ "${DRY_RUN}" -eq 0 ]]; then
	run_remote "${SSH_HOST}" "chmod 750 '${STACK_ROOT_REMOTE}/scripts/site-bootstrap.sh' '${STACK_ROOT_REMOTE}/configs/importers/stack-export-importer.sh' && chmod 640 '${STACK_ROOT_REMOTE}/manifests/importers.txt'"
	verify_remote_file_sha256 "${LOCAL_SITE_BOOTSTRAP}" "${STACK_ROOT_REMOTE}/scripts/site-bootstrap.sh"
	verify_remote_file_sha256 "${LOCAL_STACK_EXPORT_IMPORTER}" "${STACK_ROOT_REMOTE}/configs/importers/stack-export-importer.sh"
	verify_remote_file_sha256 "${LOCAL_IMPORTERS_MANIFEST}" "${STACK_ROOT_REMOTE}/manifests/importers.txt"
	if [[ "${BOOTSTRAP_CONTRACT_ONLY}" -eq 0 ]]; then
		normalize_remote_tree_permissions "${SSH_HOST}" "${STACK_ROOT_REMOTE}/themes/mrn-base-stack" "stack theme"
		normalize_remote_tree_permissions "${SSH_HOST}" "${STACK_ROOT_REMOTE}/shared" "stack shared runtime"
		normalize_remote_tree_permissions "${SSH_HOST}" "${STACK_ROOT_REMOTE}/mu-plugins" "stack mu-plugins"

		verify_remote_tree_file_modes "${SSH_HOST}" "${STACK_ROOT_REMOTE}/themes/mrn-base-stack" "stack theme"
		verify_remote_tree_file_modes "${SSH_HOST}" "${STACK_ROOT_REMOTE}/shared" "stack shared runtime"
		verify_remote_tree_file_modes "${SSH_HOST}" "${STACK_ROOT_REMOTE}/mu-plugins" "stack mu-plugins"
	fi
fi

echo "Stack source-of-truth sync completed."

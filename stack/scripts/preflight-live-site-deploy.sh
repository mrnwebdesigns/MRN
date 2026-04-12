#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  preflight-live-site-deploy.sh \
    --site-hostname <site-hostname> \
    [--discovery-ssh-host <ssh-host>] \
    [--backup-label <label>] \
    [--skip-backup]

Description:
  Resolve the live site owner, verify the direct site-owner SSH path, normalize
  malformed Updraft placeholder settings, and run a pre-deploy Updraft backup.

Output:
  Prints shell-friendly key=value lines for:
  - SITE_HOSTNAME
  - SITE_USER
  - SITE_ROOT
  - SSH_ALIAS
  - SSH_LOGIN
  - BACKUP_LABEL

Notes:
  - Human-readable progress is written to stderr.
  - Use the printed SSH_LOGIN for direct site-owner deploy writes.
  - Backup is skipped when --skip-backup is provided.
EOF
}

fail() {
	echo "FAIL: $*" >&2
	exit 1
}

note() {
	echo "$*" >&2
}

SITE_HOSTNAME=""
DISCOVERY_SSH_HOST="mrndev"
BACKUP_LABEL=""
SKIP_BACKUP=0
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

while [[ $# -gt 0 ]]; do
	case "$1" in
		--site-hostname)
			SITE_HOSTNAME="${2:-}"
			shift 2
			;;
		--discovery-ssh-host)
			DISCOVERY_SSH_HOST="${2:-}"
			shift 2
			;;
		--backup-label)
			BACKUP_LABEL="${2:-}"
			shift 2
			;;
		--skip-backup)
			SKIP_BACKUP=1
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

[[ -n "${SITE_HOSTNAME}" ]] || fail "--site-hostname is required."

for required in base64 date grep sed ssh tr; do
	command -v "${required}" >/dev/null 2>&1 || fail "Required command not found: ${required}"
done

sanitize_label() {
	printf '%s' "$1" | tr -c '[:alnum:]._- ' '-' | tr ' ' '-'
}

has_backup_warning() {
	local text="$1"
	printf '%s' "${text}" | grep -Eiq 'methods/0\.php|storage method not found:[[:space:]]*0|wp_mail_failed|recipient email'
}

run_site_ssh() {
	local command="$1"
	ssh -l "${SITE_USER}" "${SSH_ALIAS}" "${command}"
}

run_site_wp() {
	local wp_command="$1"
	run_site_ssh "wp --path='${SITE_ROOT}' ${wp_command}"
}

run_site_php() {
	local code="$1"
	local code_b64 wrapper

	code_b64="$(printf '%s' "${code}" | base64 | tr -d '\n')"
	wrapper="\$code = base64_decode(\"${code_b64}\", true); if (\$code === false) { fwrite(STDERR, \"Invalid base64 PHP payload.\\n\"); exit(1); } eval(\$code);"
	run_site_wp "eval '${wrapper}'"
}

read_kv_output() {
	local raw="$1"
	while IFS='=' read -r key value; do
		case "${key}" in
			SITE_HOSTNAME) SITE_HOSTNAME="${value}" ;;
			SITE_USER) SITE_USER="${value}" ;;
			SITE_ROOT) SITE_ROOT="${value}" ;;
			SSH_ALIAS) SSH_ALIAS="${value}" ;;
			SSH_LOGIN) SSH_LOGIN="${value}" ;;
		esac
	done <<< "${raw}"
}

SITE_USER=""
SITE_ROOT=""
SSH_ALIAS=""
SSH_LOGIN=""

RESOLVE_OUTPUT="$("${SCRIPT_DIR}/resolve-live-site-owner.sh" "${SITE_HOSTNAME}" --ssh-host "${DISCOVERY_SSH_HOST}")" || fail "Unable to resolve live site owner for ${SITE_HOSTNAME}."
read_kv_output "${RESOLVE_OUTPUT}"

[[ -n "${SITE_USER}" && -n "${SITE_ROOT}" && -n "${SSH_ALIAS}" && -n "${SSH_LOGIN}" ]] || fail "Resolved live-site details were incomplete."

note "Resolved ${SITE_HOSTNAME} to ${SITE_USER} (${SITE_ROOT}) via ${SSH_ALIAS}."

VERIFY_OUTPUT="$(ssh -l "${SITE_USER}" "${SSH_ALIAS}" 'whoami && pwd' 2>&1)" || fail "Site-owner SSH verify failed for ${SITE_USER}@${SSH_ALIAS}: ${VERIFY_OUTPUT}"

VERIFY_USER="$(printf '%s\n' "${VERIFY_OUTPUT}" | sed -n '1p' | tr -d '\r')"
[[ "${VERIFY_USER}" == "${SITE_USER}" ]] || fail "Site-owner SSH verify returned unexpected user '${VERIFY_USER}' for ${SITE_USER}@${SSH_ALIAS}."

UPDRAFT_STATE_CODE=$(cat <<'PHP'
$keys = [
    'updraft_service',
    'updraft_email',
    'updraft_report_warningsonly',
    'updraft_report_wholebackup',
    'updraft_report_dbbackup',
];
$out = [];
foreach ($keys as $key) {
    $out[$key] = get_option($key, null);
}
echo wp_json_encode($out);
PHP
)

UPDRAFT_NORMALIZE_CODE=$(cat <<'PHP'
$keys = [
    'updraft_service',
    'updraft_email',
    'updraft_report_warningsonly',
    'updraft_report_wholebackup',
    'updraft_report_dbbackup',
];
foreach ($keys as $key) {
    $value = get_option($key, null);
    if ($value === null) {
        continue;
    }
    if (is_array($value)) {
        $filtered = array_values(array_filter($value, static function ($item) {
            return $item !== '0' && $item !== '' && $item !== 0 && $item !== null;
        }));
        update_option($key, $filtered);
        continue;
    }
    if ($value === '0' || $value === '' || $value === 0) {
        delete_option($key);
    }
}
$out = [];
foreach ($keys as $key) {
    $out[$key] = get_option($key, null);
}
echo wp_json_encode($out);
PHP
)

BEFORE_STATE="$(run_site_php "${UPDRAFT_STATE_CODE}" | tr -d '\r')"
AFTER_STATE="$(run_site_php "${UPDRAFT_NORMALIZE_CODE}" | tr -d '\r')"

if [[ "${BEFORE_STATE}" != "${AFTER_STATE}" ]]; then
	note "Normalized Updraft placeholder values for ${SITE_HOSTNAME}."
fi

if [[ "${SKIP_BACKUP}" -eq 0 ]]; then
	if [[ -z "${BACKUP_LABEL}" ]]; then
		BACKUP_LABEL="predeploy-$(sanitize_label "${SITE_HOSTNAME}")-$(date +%Y%m%d%H%M%S)"
	fi

	note "Starting Updraft backup on ${SITE_HOSTNAME} as ${SITE_USER}."
	BACKUP_OUTPUT="$(run_site_wp "updraftplus backup --include-files='plugins,themes,uploads,others' --send-to-cloud --always-keep --label='${BACKUP_LABEL}'" 2>&1)" || fail "Updraft backup command failed for ${SITE_HOSTNAME}: ${BACKUP_OUTPUT}"

	if has_backup_warning "${BACKUP_OUTPUT}"; then
		fail "Updraft backup output still contains configuration warnings for ${SITE_HOSTNAME}: ${BACKUP_OUTPUT}"
	fi

	BACKUP_JOB_ID="$(printf '%s\n' "${BACKUP_OUTPUT}" | grep -Eo 'backup_progress[[:space:]]+[[:alnum:]]+' | awk '{print $2}' | tail -n 1)"
	if [[ -z "${BACKUP_JOB_ID}" ]]; then
		BACKUP_JOB_ID="$(printf '%s\n' "${BACKUP_OUTPUT}" | grep -Eo 'job id:[[:space:]]+[[:alnum:]]+' | awk '{print $3}' | tail -n 1)"
	fi

	if [[ -n "${BACKUP_JOB_ID}" ]]; then
		for attempt in 1 2 3 4 5; do
			sleep 2
			PROGRESS_OUTPUT="$(run_site_wp "updraftplus backup_progress ${BACKUP_JOB_ID}" 2>&1 || true)"
			if has_backup_warning "${PROGRESS_OUTPUT}"; then
				fail "Updraft backup progress log contains warnings for ${SITE_HOSTNAME}: ${PROGRESS_OUTPUT}"
			fi
		done
	fi

	note "Updraft backup started cleanly for ${SITE_HOSTNAME} (${BACKUP_LABEL})."
fi

printf 'SITE_HOSTNAME=%s\n' "${SITE_HOSTNAME}"
printf 'SITE_USER=%s\n' "${SITE_USER}"
printf 'SITE_ROOT=%s\n' "${SITE_ROOT}"
printf 'SSH_ALIAS=%s\n' "${SSH_ALIAS}"
printf 'SSH_LOGIN=%s\n' "${SSH_LOGIN}"
printf 'BACKUP_LABEL=%s\n' "${BACKUP_LABEL}"

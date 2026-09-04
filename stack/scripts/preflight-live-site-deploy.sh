#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  preflight-live-site-deploy.sh \
    --site-hostname <site-hostname> \
    [--discovery-ssh-host <ssh-host>] \
    [--with-db-backup] \
    [--backup-label <label>] \
    [--skip-backup]

Description:
  Resolve the live site owner, verify the direct site-owner SSH path, detect
  malformed Updraft placeholder settings, and verify deploy readiness. Every
  real deployment must provide --with-db-backup; --skip-backup is only for a
  dry run or genuinely read-only readiness check.

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
  - --with-db-backup creates and verifies a database-only remote backup.
  - --skip-backup remains accepted only for dry-run/readiness callers.
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
RUN_DB_BACKUP=0
BACKUP_MODE_SET=0
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
		--with-db-backup)
			RUN_DB_BACKUP=1
			BACKUP_MODE_SET=1
			shift
			;;
		--skip-backup)
			RUN_DB_BACKUP=0
			BACKUP_MODE_SET=1
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
[[ "${BACKUP_MODE_SET}" -eq 1 ]] || fail "Choose --with-db-backup for a deployment or --skip-backup for a dry-run/read-only readiness check."

for required in date grep sed ssh tr; do
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
	local tmp_file
	local status=0

	tmp_file="$(run_site_ssh "mktemp /tmp/mrn-preflight-live-site-deploy.XXXXXX.php" | tr -d '\r')"
	[[ -n "${tmp_file}" ]] || fail "Unable to create remote temporary PHP file."

	# Avoid dynamic eval by uploading the payload to a temp file and running wp eval-file.
	run_site_ssh "cat > '${tmp_file}' <<'PHP'
<?php
${code}
PHP"

	run_site_wp "eval-file '${tmp_file}'" || status=$?
	run_site_ssh "rm -f '${tmp_file}'" >/dev/null 2>&1 || true

	return "${status}"
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

UPDRAFT_NORMALIZED_STATE_CODE=$(cat <<'PHP'
$keys = [
    'updraft_service',
    'updraft_email',
    'updraft_report_warningsonly',
    'updraft_report_wholebackup',
    'updraft_report_dbbackup',
];
$out = [];
foreach ($keys as $key) {
    $value = get_option($key, null);
    if ($value === null) {
        $out[$key] = null;
        continue;
    }
    if (is_array($value)) {
        $out[$key] = array_values(array_filter($value, static function ($item) {
            return $item !== '0' && $item !== '' && $item !== 0 && $item !== null;
        }));
        continue;
    }
    if ($value === '0' || $value === '' || $value === 0) {
        $out[$key] = null;
        continue;
    }
    $out[$key] = $value;
}
echo wp_json_encode($out);
PHP
)

BEFORE_STATE="$(run_site_php "${UPDRAFT_STATE_CODE}" | tr -d '\r')"
NORMALIZED_STATE="$(run_site_php "${UPDRAFT_NORMALIZED_STATE_CODE}" | tr -d '\r')"

if [[ "${BEFORE_STATE}" != "${NORMALIZED_STATE}" ]]; then
	fail "Updraft settings contain malformed placeholder values for ${SITE_HOSTNAME}; preflight is read-only and will not change them before a verified backup. Resolve the settings through an approved recovery path, then rerun preflight."
fi

if [[ "${RUN_DB_BACKUP}" -eq 1 ]]; then
	if [[ -z "${BACKUP_LABEL}" ]]; then
		BACKUP_LABEL="predeploy-$(sanitize_label "${SITE_HOSTNAME}")-$(date +%Y%m%d%H%M%S)"
	fi
	BACKUP_LABEL="$(sanitize_label "${BACKUP_LABEL}")"

	note "Starting database-only Updraft backup on ${SITE_HOSTNAME} as ${SITE_USER}."
	BACKUP_OUTPUT=""
	if ! BACKUP_OUTPUT="$(run_site_wp "updraftplus backup --include-files= --send-to-cloud --label='${BACKUP_LABEL}'" 2>&1)"; then
		if ! printf '%s' "${BACKUP_OUTPUT}" | grep -Eiq "not a registered subcommand|unknown command|command .* not found"; then
			fail "Updraft backup command failed for ${SITE_HOSTNAME}: ${BACKUP_OUTPUT}"
		fi

		note "UpdraftPlus CLI backup command is unavailable; using the installed plugin API fallback."
		INTERNAL_BACKUP_OUTPUT="$(run_site_php "global \$updraftplus;
if ( ! is_object( \$updraftplus ) || ! is_callable( array( \$updraftplus, 'backupnow_database' ) ) ) {
	echo wp_json_encode( array( 'result' => false, 'error' => 'UpdraftPlus database backup API is unavailable.' ) );
	exit( 1 );
}

	\$result = \$updraftplus->backupnow_database(
		array(
			'nocloud' => false,
			'label'   => '${BACKUP_LABEL}',
		)
	);

echo wp_json_encode(
	array(
			'result'      => \$result,
			'job_id'      => \$updraftplus->nonce,
			'last_backup' => get_option( 'updraft_last_backup', null ),
	)
);" 2>&1)" || fail "UpdraftPlus API backup fallback failed for ${SITE_HOSTNAME}: ${INTERNAL_BACKUP_OUTPUT}"

		if ! printf '%s' "${INTERNAL_BACKUP_OUTPUT}" | grep -Eiq '"result":true'; then
			fail "UpdraftPlus API backup fallback did not start successfully for ${SITE_HOSTNAME}: ${INTERNAL_BACKUP_OUTPUT}"
		fi

		if ! printf '%s' "${INTERNAL_BACKUP_OUTPUT}" | grep -Eiq '"success":1'; then
			fail "UpdraftPlus API backup fallback did not verify a successful database backup for ${SITE_HOSTNAME}: ${INTERNAL_BACKUP_OUTPUT}"
		fi

		BACKUP_JOB_ID="$(printf '%s\n' "${INTERNAL_BACKUP_OUTPUT}" | grep -Eo '"job_id":"[0-9a-f]+"' | sed 's/.*"job_id":"//; s/"$//' | tail -n 1)"
		[[ -n "${BACKUP_JOB_ID}" ]] || fail "UpdraftPlus API backup fallback did not return a job ID for ${SITE_HOSTNAME}: ${INTERNAL_BACKUP_OUTPUT}"
		note "Updraft database backup completed cleanly via the plugin API for ${SITE_HOSTNAME} (${BACKUP_LABEL}, job ${BACKUP_JOB_ID})."
		printf 'SITE_HOSTNAME=%s\n' "${SITE_HOSTNAME}"
		printf 'SITE_USER=%s\n' "${SITE_USER}"
		printf 'SITE_ROOT=%s\n' "${SITE_ROOT}"
		printf 'SSH_ALIAS=%s\n' "${SSH_ALIAS}"
		printf 'SSH_LOGIN=%s\n' "${SSH_LOGIN}"
		printf 'BACKUP_LABEL=%s\n' "${BACKUP_LABEL}"
		exit 0
	fi

	if has_backup_warning "${BACKUP_OUTPUT}"; then
		fail "Updraft backup output still contains configuration warnings for ${SITE_HOSTNAME}: ${BACKUP_OUTPUT}"
	fi

	BACKUP_JOB_ID="$(printf '%s\n' "${BACKUP_OUTPUT}" | grep -Eo 'backup_progress[[:space:]]+[[:alnum:]]+' | awk '{print $2}' | tail -n 1)"
	if [[ -z "${BACKUP_JOB_ID}" ]]; then
		BACKUP_JOB_ID="$(printf '%s\n' "${BACKUP_OUTPUT}" | grep -Eo 'job id:[[:space:]]+[[:alnum:]]+' | awk '{print $3}' | tail -n 1)"
	fi

	[[ -n "${BACKUP_JOB_ID}" ]] || fail "Updraft backup command did not return a job ID for ${SITE_HOSTNAME}: ${BACKUP_OUTPUT}"

	BACKUP_COMPLETE=0
	for (( attempt=1; attempt<=60; attempt++ )); do
		sleep 5
		PROGRESS_OUTPUT="$(run_site_wp "updraftplus backup_progress ${BACKUP_JOB_ID}" 2>&1 || true)"
		if has_backup_warning "${PROGRESS_OUTPUT}"; then
			fail "Updraft backup progress log contains warnings for ${SITE_HOSTNAME}: ${PROGRESS_OUTPUT}"
		fi
		if printf '%s' "${PROGRESS_OUTPUT}" | grep -Eiq 'backup succeeded and is now complete|backup.*completed successfully'; then
			BACKUP_COMPLETE=1
			break
		fi
		if printf '%s' "${PROGRESS_OUTPUT}" | grep -Eiq 'backup failed|errors? occurred|apparently unsuccessfully'; then
			fail "Updraft backup failed for ${SITE_HOSTNAME}: ${PROGRESS_OUTPUT}"
		fi
	done

	[[ "${BACKUP_COMPLETE}" -eq 1 ]] || fail "Updraft backup did not complete within 300 seconds for ${SITE_HOSTNAME} (job ${BACKUP_JOB_ID})."
	note "Updraft database backup completed cleanly for ${SITE_HOSTNAME} (${BACKUP_LABEL}, job ${BACKUP_JOB_ID})."
fi

printf 'SITE_HOSTNAME=%s\n' "${SITE_HOSTNAME}"
printf 'SITE_USER=%s\n' "${SITE_USER}"
printf 'SITE_ROOT=%s\n' "${SITE_ROOT}"
printf 'SSH_ALIAS=%s\n' "${SSH_ALIAS}"
printf 'SSH_LOGIN=%s\n' "${SSH_LOGIN}"
printf 'BACKUP_LABEL=%s\n' "${BACKUP_LABEL}"

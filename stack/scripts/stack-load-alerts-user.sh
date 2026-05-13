#!/usr/bin/env bash
set -euo pipefail

STACK_ROOT="${STACK_ROOT:-/home/mrndev-stack-manager/stack}"
RUNTIME_DIR="${STACK_RUNTIME_DIR:-${STACK_ROOT}/runtime}"
STATE_FILE="${STACK_LOAD_ALERT_STATE_FILE:-${RUNTIME_DIR}/stack-load-alerts-user.state}"
LOCK_FILE="${STACK_LOAD_ALERT_LOCK_FILE:-${RUNTIME_DIR}/stack-load-alerts-user.lock}"

SLACK_WEBHOOK_URL="${STACK_SLACK_WEBHOOK_URL:-}"
SLACK_WEBHOOK_URL_FILE="${STACK_SLACK_WEBHOOK_URL_FILE:-${STACK_ROOT}/secrets/slack-webhook-url.txt}"
SLACK_CHANNEL="${STACK_SLACK_CHANNEL:-}"
SLACK_USERNAME="${STACK_SLACK_USERNAME:-MRN Load Alerts}"
SLACK_ICON_EMOJI="${STACK_SLACK_ICON_EMOJI:-:rotating_light:}"

WARN_LOAD_PER_CORE="${WARN_LOAD_PER_CORE:-1.05}"
CRIT_LOAD_PER_CORE="${CRIT_LOAD_PER_CORE:-1.25}"
WARN_MEM_AVAIL_PCT="${WARN_MEM_AVAIL_PCT:-30}"
CRIT_MEM_AVAIL_PCT="${CRIT_MEM_AVAIL_PCT:-20}"
WARN_SWAP_USED_PCT="${WARN_SWAP_USED_PCT:-97}"
CRIT_SWAP_USED_PCT="${CRIT_SWAP_USED_PCT:-100}"
WARN_SWAP_ACTIVITY_KBPS="${WARN_SWAP_ACTIVITY_KBPS:-64}"
CRIT_SWAP_ACTIVITY_KBPS="${CRIT_SWAP_ACTIVITY_KBPS:-256}"
WARN_PHP_WORKERS="${WARN_PHP_WORKERS:-16}"
CRIT_PHP_WORKERS="${CRIT_PHP_WORKERS:-20}"
WARN_CONSECUTIVE_REQUIRED="${WARN_CONSECUTIVE_REQUIRED:-2}"
CRIT_CONSECUTIVE_REQUIRED="${CRIT_CONSECUTIVE_REQUIRED:-2}"
OK_CONSECUTIVE_REQUIRED="${OK_CONSECUTIVE_REQUIRED:-3}"
NON_OK_REMINDER_MINUTES="${NON_OK_REMINDER_MINUTES:-30}"

usage() {
	cat <<'USAGE'
Usage:
  stack-load-alerts-user.sh --run
  stack-load-alerts-user.sh --test
  stack-load-alerts-user.sh --status
USAGE
}

log() {
	printf '%s\n' "$*"
}

cmp_ge() {
	local left="$1"
	local right="$2"
	awk -v a="${left}" -v b="${right}" 'BEGIN { exit ((a + 0) >= (b + 0) ? 0 : 1) }'
}

ensure_runtime() {
	mkdir -p "${RUNTIME_DIR}"
}

load_webhook() {
	if [[ -z "${SLACK_WEBHOOK_URL}" && -f "${SLACK_WEBHOOK_URL_FILE}" ]]; then
		SLACK_WEBHOOK_URL="$(tr -d '\r\n' < "${SLACK_WEBHOOK_URL_FILE}")"
	fi
}

send_slack() {
	local title="$1"
	local body="$2"
	local color="$3"

	if [[ -z "${SLACK_WEBHOOK_URL}" ]]; then
		log "WARN: Slack webhook URL is empty; notification skipped."
		return 1
	fi

	python3 - "${SLACK_WEBHOOK_URL}" "${title}" "${body}" "${color}" "${SLACK_USERNAME}" "${SLACK_ICON_EMOJI}" "${SLACK_CHANNEL}" <<'PY'
import json
import sys
from urllib.request import Request, urlopen

webhook, title, body, color, username, icon_emoji, channel = sys.argv[1:]

payload = {
    "username": username,
    "icon_emoji": icon_emoji,
    "attachments": [
        {
            "color": color,
            "title": title,
            "text": body,
        }
    ],
}
if channel:
    payload["channel"] = channel

req = Request(
    webhook,
    data=json.dumps(payload).encode("utf-8"),
    headers={"Content-Type": "application/json"},
    method="POST",
)

with urlopen(req, timeout=10) as resp:
    body_bytes = resp.read()
    text = body_bytes.decode("utf-8", "replace").strip()
    print(text or "ok")
PY
}

read_state() {
	STATE_STATUS="UNKNOWN"
	STATE_LAST_NOTIFY_EPOCH="0"
	STATE_LAST_NOTIFY_STATUS="UNKNOWN"
	STATE_LAST_CANDIDATE="UNKNOWN"
	STATE_LAST_CANDIDATE_STREAK="0"

	if [[ ! -f "${STATE_FILE}" ]]; then
		return 0
	fi

	STATE_STATUS="$(awk -F= '/^status=/{print $2}' "${STATE_FILE}" | tail -n 1)"
	STATE_LAST_NOTIFY_EPOCH="$(awk -F= '/^last_notify_epoch=/{print $2}' "${STATE_FILE}" | tail -n 1)"
	STATE_LAST_NOTIFY_STATUS="$(awk -F= '/^last_notify_status=/{print $2}' "${STATE_FILE}" | tail -n 1)"
	STATE_LAST_CANDIDATE="$(awk -F= '/^last_candidate=/{print $2}' "${STATE_FILE}" | tail -n 1)"
	STATE_LAST_CANDIDATE_STREAK="$(awk -F= '/^last_candidate_streak=/{print $2}' "${STATE_FILE}" | tail -n 1)"

	[[ -n "${STATE_STATUS}" ]] || STATE_STATUS="UNKNOWN"
	[[ "${STATE_LAST_NOTIFY_EPOCH}" =~ ^[0-9]+$ ]] || STATE_LAST_NOTIFY_EPOCH="0"
	[[ -n "${STATE_LAST_NOTIFY_STATUS}" ]] || STATE_LAST_NOTIFY_STATUS="UNKNOWN"
	[[ -n "${STATE_LAST_CANDIDATE}" ]] || STATE_LAST_CANDIDATE="UNKNOWN"
	[[ "${STATE_LAST_CANDIDATE_STREAK}" =~ ^[0-9]+$ ]] || STATE_LAST_CANDIDATE_STREAK="0"
}

write_state() {
	local status="$1"
	local last_notify_epoch="$2"
	local last_notify_status="$3"
	local last_candidate="$4"
	local last_candidate_streak="$5"
	cat > "${STATE_FILE}" <<EOF
status=${status}
last_notify_epoch=${last_notify_epoch}
last_notify_status=${last_notify_status}
last_candidate=${last_candidate}
last_candidate_streak=${last_candidate_streak}
EOF
}

required_consecutive() {
	case "$1" in
		OK) echo "${OK_CONSECUTIVE_REQUIRED}" ;;
		WARN) echo "${WARN_CONSECUTIVE_REQUIRED}" ;;
		CRIT) echo "${CRIT_CONSECUTIVE_REQUIRED}" ;;
		*) echo 1 ;;
	esac
}

collect_metrics() {
	local load1 cores

	load1="$(awk '{print $1}' /proc/loadavg)"
	cores="$(nproc 2>/dev/null || echo 1)"
	if [[ -z "${cores}" || "${cores}" -lt 1 ]]; then
		cores=1
	fi

	LOAD1="${load1}"
	CORES="${cores}"
	LOAD_PER_CORE="$(awk -v loadv="${LOAD1}" -v cores="${CORES}" 'BEGIN { printf "%.2f", (loadv + 0) / (cores + 0) }')"

	MEM_AVAIL_PCT="$(free | awk '/^Mem:/ { if ($2 == 0) { print 100 } else { printf "%.0f", ($7 / $2) * 100 } }')"
	SWAP_USED_PCT="$(free | awk '/^Swap:/ { if ($2 == 0) { print 0 } else { printf "%.0f", ($3 / $2) * 100 } }')"
	read -r SWAP_IN_KBPS SWAP_OUT_KBPS < <(vmstat 1 2 | tail -n 1 | awk '{print $7, $8}')
	[[ "${SWAP_IN_KBPS}" =~ ^[0-9]+$ ]] || SWAP_IN_KBPS=0
	[[ "${SWAP_OUT_KBPS}" =~ ^[0-9]+$ ]] || SWAP_OUT_KBPS=0
	if [[ "${SWAP_IN_KBPS}" -gt "${SWAP_OUT_KBPS}" ]]; then
		SWAP_ACTIVITY_KBPS="${SWAP_IN_KBPS}"
	else
		SWAP_ACTIVITY_KBPS="${SWAP_OUT_KBPS}"
	fi

	PHP_WORKERS="$(ps -eo args= | awk '/^php-fpm: pool / { c++ } END { print c + 0 }')"

	if systemctl is-active --quiet mysql || systemctl is-active --quiet mariadb; then
		MYSQL_STATUS="up"
	else
		MYSQL_STATUS="down"
	fi

	PHP_HOT_POOLS="$(ps -eo pcpu=,args= --sort=-pcpu | awk '/php-fpm: pool / {gsub(/^ +/, "", $0); print; c++; if (c == 3) exit }' | paste -sd ';' -)"
}

severity_rank() {
	case "$1" in
		OK) echo 0 ;;
		WARN) echo 1 ;;
		CRIT) echo 2 ;;
		*) echo 0 ;;
	esac
}

evaluate_status() {
	CANDIDATE_STATUS="OK"
	CANDIDATE_REASONS=()

	if cmp_ge "${LOAD_PER_CORE}" "${CRIT_LOAD_PER_CORE}"; then
		CANDIDATE_STATUS="CRIT"
		CANDIDATE_REASONS+=("load/core=${LOAD_PER_CORE} >= ${CRIT_LOAD_PER_CORE}")
	elif cmp_ge "${LOAD_PER_CORE}" "${WARN_LOAD_PER_CORE}"; then
		CANDIDATE_STATUS="WARN"
		CANDIDATE_REASONS+=("load/core=${LOAD_PER_CORE} >= ${WARN_LOAD_PER_CORE}")
	fi

	if cmp_ge "${CRIT_MEM_AVAIL_PCT}" "${MEM_AVAIL_PCT}"; then
		CANDIDATE_STATUS="CRIT"
		CANDIDATE_REASONS+=("mem_avail=${MEM_AVAIL_PCT}% <= ${CRIT_MEM_AVAIL_PCT}%")
	elif cmp_ge "${WARN_MEM_AVAIL_PCT}" "${MEM_AVAIL_PCT}"; then
		if [[ "$(severity_rank "${CANDIDATE_STATUS}")" -lt 1 ]]; then
			CANDIDATE_STATUS="WARN"
		fi
		CANDIDATE_REASONS+=("mem_avail=${MEM_AVAIL_PCT}% <= ${WARN_MEM_AVAIL_PCT}%")
	fi

	if cmp_ge "${SWAP_USED_PCT}" "${CRIT_SWAP_USED_PCT}" \
		&& cmp_ge "${CRIT_MEM_AVAIL_PCT}" "${MEM_AVAIL_PCT}" \
		&& cmp_ge "${SWAP_ACTIVITY_KBPS}" "${CRIT_SWAP_ACTIVITY_KBPS}"; then
		CANDIDATE_STATUS="CRIT"
		CANDIDATE_REASONS+=("swap_pressure=crit (swap_used=${SWAP_USED_PCT}%, mem_avail=${MEM_AVAIL_PCT}%, swap_io=${SWAP_ACTIVITY_KBPS}kB/s)")
	elif cmp_ge "${SWAP_USED_PCT}" "${WARN_SWAP_USED_PCT}" \
		&& cmp_ge "${WARN_MEM_AVAIL_PCT}" "${MEM_AVAIL_PCT}" \
		&& cmp_ge "${SWAP_ACTIVITY_KBPS}" "${WARN_SWAP_ACTIVITY_KBPS}"; then
		if [[ "$(severity_rank "${CANDIDATE_STATUS}")" -lt 1 ]]; then
			CANDIDATE_STATUS="WARN"
		fi
		CANDIDATE_REASONS+=("swap_pressure=warn (swap_used=${SWAP_USED_PCT}%, mem_avail=${MEM_AVAIL_PCT}%, swap_io=${SWAP_ACTIVITY_KBPS}kB/s)")
	fi

	if cmp_ge "${PHP_WORKERS}" "${CRIT_PHP_WORKERS}"; then
		CANDIDATE_STATUS="CRIT"
		CANDIDATE_REASONS+=("php_workers=${PHP_WORKERS} >= ${CRIT_PHP_WORKERS}")
	elif cmp_ge "${PHP_WORKERS}" "${WARN_PHP_WORKERS}"; then
		if [[ "$(severity_rank "${CANDIDATE_STATUS}")" -lt 1 ]]; then
			CANDIDATE_STATUS="WARN"
		fi
		CANDIDATE_REASONS+=("php_workers=${PHP_WORKERS} >= ${WARN_PHP_WORKERS}")
	fi

	if [[ "${MYSQL_STATUS}" != "up" ]]; then
		CANDIDATE_STATUS="CRIT"
		CANDIDATE_REASONS+=("mysql=${MYSQL_STATUS}")
	fi

	if [[ "${#CANDIDATE_REASONS[@]}" -eq 0 ]]; then
		CANDIDATE_REASON_TEXT="none"
	else
		CANDIDATE_REASON_TEXT="$(IFS='; '; echo "${CANDIDATE_REASONS[*]}")"
	fi
}

build_message() {
	local mode="$1"
	local reason_text="$2"
	cat <<EOF
Host: ${HOST}
Mode: ${mode}
Current status: ${CURRENT_STATUS}
Previous status: ${STATE_STATUS}
Reason: ${reason_text}

load/core: ${LOAD_PER_CORE} (load1=${LOAD1}, cores=${CORES})
mem_avail: ${MEM_AVAIL_PCT}%
swap_used: ${SWAP_USED_PCT}%
swap_io: in=${SWAP_IN_KBPS}kB/s out=${SWAP_OUT_KBPS}kB/s (max=${SWAP_ACTIVITY_KBPS}kB/s)
php_workers: ${PHP_WORKERS}
mysql: ${MYSQL_STATUS}

top_php_fpm:
${PHP_HOT_POOLS:-none}
EOF
}

run_check() {
	local now_epoch notify=0 notify_title="" notify_body="" notify_color=""
	local candidate_streak required_streak transition_held

	HOST="$(hostname -s)"
	now_epoch="$(date +%s)"

	collect_metrics
	read_state
	evaluate_status

	if [[ "${CANDIDATE_STATUS}" == "${STATE_LAST_CANDIDATE}" ]]; then
		candidate_streak="$(( STATE_LAST_CANDIDATE_STREAK + 1 ))"
	else
		candidate_streak=1
	fi

	required_streak="$(required_consecutive "${CANDIDATE_STATUS}")"
	transition_held=0
	if [[ "${CANDIDATE_STATUS}" != "${STATE_STATUS}" && "${candidate_streak}" -lt "${required_streak}" ]]; then
		transition_held=1
		CURRENT_STATUS="${STATE_STATUS}"
	else
		CURRENT_STATUS="${CANDIDATE_STATUS}"
	fi

	log "ts=$(date -u +%Y-%m-%dT%H:%M:%SZ) host=${HOST} status=${STATE_STATUS} candidate=${CANDIDATE_STATUS} candidate_streak=${candidate_streak}/${required_streak} effective=${CURRENT_STATUS} transition_held=${transition_held} load/core=${LOAD_PER_CORE} mem_avail=${MEM_AVAIL_PCT}% swap_used=${SWAP_USED_PCT}% swap_io=${SWAP_ACTIVITY_KBPS}kB/s php_workers=${PHP_WORKERS} mysql=${MYSQL_STATUS}"

	if [[ "${CURRENT_STATUS}" != "${STATE_STATUS}" ]]; then
		notify=1
		if [[ "${CURRENT_STATUS}" == "OK" ]]; then
			notify_title="MRN Load RECOVERY: ${HOST}"
			notify_color="#1f883d"
		elif [[ "${CURRENT_STATUS}" == "WARN" ]]; then
			notify_title="MRN Load WARNING: ${HOST}"
			notify_color="#d29922"
		else
			notify_title="MRN Load CRITICAL: ${HOST}"
			notify_color="#d1242f"
		fi
		notify_body="$(build_message "state-change" "${CANDIDATE_REASON_TEXT}")"
	elif [[ "${CURRENT_STATUS}" != "OK" ]]; then
		local reminder_seconds elapsed
		reminder_seconds="$(( NON_OK_REMINDER_MINUTES * 60 ))"
		elapsed="$(( now_epoch - STATE_LAST_NOTIFY_EPOCH ))"
		if [[ "${STATE_LAST_NOTIFY_STATUS}" != "${CURRENT_STATUS}" || "${elapsed}" -ge "${reminder_seconds}" ]]; then
			notify=1
			if [[ "${CURRENT_STATUS}" == "WARN" ]]; then
				notify_title="MRN Load WARNING Reminder: ${HOST}"
				notify_color="#d29922"
			else
				notify_title="MRN Load CRITICAL Reminder: ${HOST}"
				notify_color="#d1242f"
			fi
			notify_body="$(build_message "reminder" "${CANDIDATE_REASON_TEXT}")"
		fi
	fi

	if [[ "${notify}" -eq 1 ]]; then
		if send_slack "${notify_title}" "${notify_body}" "${notify_color}" >/dev/null; then
			STATE_LAST_NOTIFY_EPOCH="${now_epoch}"
			STATE_LAST_NOTIFY_STATUS="${CURRENT_STATUS}"
			log "notify=sent status=${CURRENT_STATUS}"
		else
			log "notify=failed status=${CURRENT_STATUS}"
		fi
	fi

	write_state \
		"${CURRENT_STATUS}" \
		"${STATE_LAST_NOTIFY_EPOCH:-0}" \
		"${STATE_LAST_NOTIFY_STATUS:-UNKNOWN}" \
		"${CANDIDATE_STATUS}" \
		"${candidate_streak}"
}

run_test() {
	HOST="$(hostname -s)"
	collect_metrics
	read_state
	evaluate_status
	CURRENT_STATUS="${CANDIDATE_STATUS}"
	local body
	body="$(build_message "manual-test" "forced test notification")"
	send_slack "MRN Load Alert TEST: ${HOST}" "${body}" "#1f6feb" >/dev/null
	log "notify=test-sent host=${HOST}"
}

print_status() {
	local candidate_streak required_streak
	collect_metrics
	read_state
	evaluate_status
	if [[ "${CANDIDATE_STATUS}" == "${STATE_LAST_CANDIDATE}" ]]; then
		candidate_streak="$(( STATE_LAST_CANDIDATE_STREAK + 1 ))"
	else
		candidate_streak=1
	fi
	required_streak="$(required_consecutive "${CANDIDATE_STATUS}")"
	log "host=$(hostname -s) status=${STATE_STATUS} candidate=${CANDIDATE_STATUS} candidate_streak=${candidate_streak}/${required_streak} reason=${CANDIDATE_REASON_TEXT}"
	log "load/core=${LOAD_PER_CORE} load1=${LOAD1} cores=${CORES} mem_avail=${MEM_AVAIL_PCT}% swap_used=${SWAP_USED_PCT}% swap_io=${SWAP_ACTIVITY_KBPS}kB/s php_workers=${PHP_WORKERS} mysql=${MYSQL_STATUS}"
}

main() {
	local mode="${1:-}"
	if [[ -z "${mode}" ]]; then
		usage
		exit 1
	fi

	ensure_runtime
	load_webhook

	exec 9>"${LOCK_FILE}"
	if ! flock -n 9; then
		log "skip=locked"
		exit 0
	fi

	case "${mode}" in
		--run)
			run_check
			;;
		--test)
			run_test
			;;
		--status)
			print_status
			;;
		-h|--help)
			usage
			;;
		*)
			usage
			exit 1
			;;
	esac
}

main "$@"

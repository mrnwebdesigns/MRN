#!/usr/bin/env bash
set -euo pipefail

MODE="${1:---run}"
if [[ "$MODE" != "--run" && "$MODE" != "--dry-run" && "$MODE" != "--status" ]]; then
  echo "Usage: $0 [--run|--dry-run|--status]" >&2
  exit 64
fi

STACK_ROOT="${STACK_ROOT:-/home/mrndev-stack-manager/stack}"
SLACK_WEBHOOK_URL="${STACK_SLACK_WEBHOOK_URL:-${BOOTSTRAP_SLACK_WEBHOOK_URL:-}}"
SLACK_WEBHOOK_URL_FILE="${STACK_SLACK_WEBHOOK_URL_FILE:-${STACK_ROOT}/secrets/slack-webhook-url.txt}"
SLACK_CHANNEL="${STACK_SLACK_CHANNEL:-}"
SLACK_USERNAME="${STACK_SLACK_USERNAME:-MRN Stack Monitor}"
SLACK_ICON_EMOJI="${STACK_SLACK_ICON_EMOJI:-:bar_chart:}"

CONFIG_FILE="${STACK_LOAD_ALERTS_CONFIG:-/etc/default/stack-load-alerts}"
STATE_DIR="${STACK_LOAD_ALERTS_STATE_DIR:-/var/lib/stack-load-alerts}"
STATE_FILE="${STACK_LOAD_ALERTS_STATE_FILE:-${STATE_DIR}/state.env}"

WARN_LOAD_PER_CORE="${WARN_LOAD_PER_CORE:-1.25}"
CRIT_LOAD_PER_CORE="${CRIT_LOAD_PER_CORE:-1.75}"
WARN_MEM_AVAIL_PCT="${WARN_MEM_AVAIL_PCT:-20}"
CRIT_MEM_AVAIL_PCT="${CRIT_MEM_AVAIL_PCT:-12}"
WARN_SWAP_USED_PCT="${WARN_SWAP_USED_PCT:-70}"
CRIT_SWAP_USED_PCT="${CRIT_SWAP_USED_PCT:-85}"
WARN_PHP_WORKERS="${WARN_PHP_WORKERS:-14}"
CRIT_PHP_WORKERS="${CRIT_PHP_WORKERS:-20}"
WARN_STREAK_REQUIRED="${WARN_STREAK_REQUIRED:-3}"
CRIT_STREAK_REQUIRED="${CRIT_STREAK_REQUIRED:-2}"
RECOVERY_STREAK_REQUIRED="${RECOVERY_STREAK_REQUIRED:-2}"
ALERT_COOLDOWN_SEC="${ALERT_COOLDOWN_SEC:-900}"

if [[ -f "$CONFIG_FILE" ]]; then
  # shellcheck disable=SC1090
  source "$CONFIG_FILE"
fi

if [[ -z "$SLACK_WEBHOOK_URL" && -f "$SLACK_WEBHOOK_URL_FILE" ]]; then
  SLACK_WEBHOOK_URL="$(tr -d '\r\n' < "$SLACK_WEBHOOK_URL_FILE")"
fi

mkdir -p "$STATE_DIR"
chmod 750 "$STATE_DIR" || true

json_escape() {
  local value="${1:-}"
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  value="${value//$'\n'/\\n}"
  value="${value//$'\r'/\\r}"
  value="${value//$'\t'/\\t}"
  printf '%s' "$value"
}

float_ge() {
  awk -v a="$1" -v b="$2" 'BEGIN{exit !(a+0 >= b+0)}'
}

send_slack_notification() {
  local title="$1"
  local body="$2"
  local color="${3:-#1f6feb}"
  local channel_field payload

  [[ -n "$SLACK_WEBHOOK_URL" ]] || return 0
  command -v curl >/dev/null 2>&1 || return 0

  if [[ -n "$SLACK_CHANNEL" ]]; then
    channel_field="\"channel\":\"$(json_escape "$SLACK_CHANNEL")\","
  else
    channel_field=""
  fi

  payload="$(cat <<EOF
{
  ${channel_field}
  \"username\":\"$(json_escape "$SLACK_USERNAME")\",
  \"icon_emoji\":\"$(json_escape "$SLACK_ICON_EMOJI")\",
  \"attachments\":[
    {
      \"color\":\"$(json_escape "$color")\",
      \"title\":\"$(json_escape "$title")\",
      \"text\":\"$(json_escape "$body")\",
      \"mrkdwn_in\":[\"text\"],
      \"footer\":\"MRN Stack Monitor\",
      \"ts\":$(date +%s)
    }
  ]
}
EOF
)"

  curl -sS -X POST -H 'Content-type: application/json' --data "$payload" "$SLACK_WEBHOOK_URL" >/dev/null || true
}

if [[ -f "$STATE_FILE" ]]; then
  # shellcheck disable=SC1090
  source "$STATE_FILE"
fi

LAST_STATUS="${LAST_STATUS:-OK}"
WARN_STREAK="${WARN_STREAK:-0}"
CRIT_STREAK="${CRIT_STREAK:-0}"
OK_STREAK="${OK_STREAK:-0}"
LAST_ALERT_TS="${LAST_ALERT_TS:-0}"

host="$(hostname -s)"
read -r load1 _ < /proc/loadavg
core_count="$(nproc 2>/dev/null || echo 1)"
if [[ -z "$core_count" || "$core_count" -lt 1 ]]; then
  core_count=1
fi
load_per_core="$(awk -v l="$load1" -v c="$core_count" 'BEGIN{printf "%.2f", (c>0 ? l/c : l)}')"

read -r mem_total_kb mem_avail_kb swap_total_kb swap_free_kb < <(
  awk '
    /^MemTotal:/ {mt=$2}
    /^MemAvailable:/ {ma=$2}
    /^SwapTotal:/ {st=$2}
    /^SwapFree:/ {sf=$2}
    END {print mt, ma, st, sf}
  ' /proc/meminfo
)

if [[ -z "$mem_total_kb" || "$mem_total_kb" -le 0 ]]; then
  mem_total_kb=1
fi
mem_avail_pct="$(awk -v ma="$mem_avail_kb" -v mt="$mem_total_kb" 'BEGIN{printf "%.0f", (ma*100)/mt}')"

if [[ -z "$swap_total_kb" || "$swap_total_kb" -le 0 ]]; then
  swap_used_pct=0
else
  swap_used_pct="$(awk -v st="$swap_total_kb" -v sf="$swap_free_kb" 'BEGIN{used=st-sf; if (used<0) used=0; printf "%.0f", (used*100)/st}')"
fi

php_worker_count="$(ps -C php-fpm8.3 -o cmd= 2>/dev/null | grep -c 'php-fpm: pool ' || true)"
php_pool_summary_tsv="$(ps -C php-fpm8.3 -o pcpu=,cmd= 2>/dev/null \
  | awk '/pool / {cpu=$1; sub(/^ +/,"",cpu); sub(/.*pool /,"",$0); pool=$0; gsub(/^[[:space:]]+|[[:space:]]+$/, "", pool); usage[pool]+=cpu; workers[pool]++} END {for (p in usage) printf "%.1f\t%d\t%s\n", usage[p], workers[p], p}' \
  | sort -nr | head -n 5 || true)"

php_top_pools="n/a"
suspect_site="n/a"
suspect_detail="n/a"

if [[ -n "$php_pool_summary_tsv" ]]; then
  php_top_pools="$(printf '%s\n' "$php_pool_summary_tsv" | head -n 3 | awk '{printf "%s(%.1f%%,w=%s) ", $3, $1, $2}')"
  suspect_site="$(printf '%s\n' "$php_pool_summary_tsv" | head -n 1 | awk '{print $3}')"
  suspect_cpu="$(printf '%s\n' "$php_pool_summary_tsv" | head -n 1 | awk '{print $1}')"
  suspect_workers="$(printf '%s\n' "$php_pool_summary_tsv" | head -n 1 | awk '{print $2}')"
  suspect_detail="${suspect_site}(cpu=${suspect_cpu}%,workers=${suspect_workers})"
fi

if mysqladmin ping -s >/dev/null 2>&1; then
  mysql_status="up"
else
  mysql_status="down"
fi

crit_reasons=()
warn_reasons=()

if float_ge "$load_per_core" "$CRIT_LOAD_PER_CORE"; then
  crit_reasons+=("load/core ${load_per_core} >= ${CRIT_LOAD_PER_CORE}")
elif float_ge "$load_per_core" "$WARN_LOAD_PER_CORE"; then
  warn_reasons+=("load/core ${load_per_core} >= ${WARN_LOAD_PER_CORE}")
fi

if (( mem_avail_pct <= CRIT_MEM_AVAIL_PCT )); then
  crit_reasons+=("mem available ${mem_avail_pct}% <= ${CRIT_MEM_AVAIL_PCT}%")
elif (( mem_avail_pct <= WARN_MEM_AVAIL_PCT )); then
  warn_reasons+=("mem available ${mem_avail_pct}% <= ${WARN_MEM_AVAIL_PCT}%")
fi

if (( mem_avail_pct <= WARN_MEM_AVAIL_PCT )); then
  if (( swap_used_pct >= CRIT_SWAP_USED_PCT )); then
    crit_reasons+=("swap used ${swap_used_pct}% >= ${CRIT_SWAP_USED_PCT}% while mem available <= ${WARN_MEM_AVAIL_PCT}%")
  elif (( swap_used_pct >= WARN_SWAP_USED_PCT )); then
    warn_reasons+=("swap used ${swap_used_pct}% >= ${WARN_SWAP_USED_PCT}% while mem available <= ${WARN_MEM_AVAIL_PCT}%")
  fi
fi

if (( php_worker_count >= CRIT_PHP_WORKERS )); then
  crit_reasons+=("php workers ${php_worker_count} >= ${CRIT_PHP_WORKERS}")
elif (( php_worker_count >= WARN_PHP_WORKERS )); then
  warn_reasons+=("php workers ${php_worker_count} >= ${WARN_PHP_WORKERS}")
fi

if [[ "$mysql_status" == "down" ]]; then
  crit_reasons+=("mysql ping failed")
fi

candidate_status="OK"
if (( ${#crit_reasons[@]} > 0 )); then
  candidate_status="CRIT"
elif (( ${#warn_reasons[@]} > 0 )); then
  candidate_status="WARN"
fi

case "$candidate_status" in
  CRIT)
    CRIT_STREAK=$((CRIT_STREAK + 1))
    WARN_STREAK=0
    OK_STREAK=0
    ;;
  WARN)
    WARN_STREAK=$((WARN_STREAK + 1))
    CRIT_STREAK=0
    OK_STREAK=0
    ;;
  OK)
    OK_STREAK=$((OK_STREAK + 1))
    WARN_STREAK=0
    CRIT_STREAK=0
    ;;
esac

new_status="$LAST_STATUS"
case "$LAST_STATUS" in
  OK)
    if [[ "$candidate_status" == "CRIT" && "$CRIT_STREAK" -ge "$CRIT_STREAK_REQUIRED" ]]; then
      new_status="CRIT"
    elif [[ "$candidate_status" == "WARN" && "$WARN_STREAK" -ge "$WARN_STREAK_REQUIRED" ]]; then
      new_status="WARN"
    fi
    ;;
  WARN)
    if [[ "$candidate_status" == "CRIT" && "$CRIT_STREAK" -ge "$CRIT_STREAK_REQUIRED" ]]; then
      new_status="CRIT"
    elif [[ "$candidate_status" == "OK" && "$OK_STREAK" -ge "$RECOVERY_STREAK_REQUIRED" ]]; then
      new_status="OK"
    fi
    ;;
  CRIT)
    if [[ "$candidate_status" == "OK" && "$OK_STREAK" -ge "$RECOVERY_STREAK_REQUIRED" ]]; then
      new_status="OK"
    elif [[ "$candidate_status" == "WARN" && "$WARN_STREAK" -ge "$WARN_STREAK_REQUIRED" ]]; then
      new_status="WARN"
    fi
    ;;
  *)
    new_status="OK"
    ;;
esac

reason_text=""
if [[ "$candidate_status" == "CRIT" ]]; then
  reason_text="${crit_reasons[*]}"
elif [[ "$candidate_status" == "WARN" ]]; then
  reason_text="${warn_reasons[*]}"
else
  reason_text="within thresholds"
fi

status_line="host=${host} status=${new_status} candidate=${candidate_status} load/core=${load_per_core} mem_avail=${mem_avail_pct}% swap_used=${swap_used_pct}% php_workers=${php_worker_count} mysql=${mysql_status}"

if [[ "$MODE" == "--status" ]]; then
  echo "$status_line"
  echo "reasons: $reason_text"
  echo "suspect_site: $suspect_detail"
  echo "php_top_pools: $php_top_pools"
  exit 0
fi

now_ts="$(date +%s)"
should_alert=0
if [[ "$new_status" != "$LAST_STATUS" ]]; then
  should_alert=1
elif [[ "$new_status" != "OK" && $((now_ts - LAST_ALERT_TS)) -ge "$ALERT_COOLDOWN_SEC" ]]; then
  should_alert=1
fi

if [[ "$MODE" == "--dry-run" ]]; then
  should_alert=0
fi

if [[ "$should_alert" -eq 1 ]]; then
  title="MRN Stack Load ${new_status}: ${host}"
  color="#1f883d"
  case "$new_status" in
    WARN) color="#d29922" ;;
    CRIT) color="#d1242f" ;;
  esac

  body=$(cat <<EOF
Host: ${host}
Transition: ${LAST_STATUS} -> ${new_status}
Candidate: ${candidate_status}
Reason: ${reason_text}
Load/Core: ${load_per_core}
Memory Available: ${mem_avail_pct}%
Swap Used: ${swap_used_pct}%
PHP Workers: ${php_worker_count}
MySQL: ${mysql_status}
Likely Suspect Site: ${suspect_detail}
Top PHP Pools (CPU): ${php_top_pools}
EOF
)
  send_slack_notification "$title" "$body" "$color"
  LAST_ALERT_TS="$now_ts"
fi

cat > "$STATE_FILE" <<EOF
LAST_STATUS=${new_status}
WARN_STREAK=${WARN_STREAK}
CRIT_STREAK=${CRIT_STREAK}
OK_STREAK=${OK_STREAK}
LAST_ALERT_TS=${LAST_ALERT_TS}
LAST_CHECK_TS=${now_ts}
LAST_LOAD_PER_CORE=${load_per_core}
LAST_MEM_AVAIL_PCT=${mem_avail_pct}
LAST_SWAP_USED_PCT=${swap_used_pct}
LAST_PHP_WORKERS=${php_worker_count}
LAST_MYSQL_STATUS=${mysql_status}
LAST_SUSPECT_SITE=${suspect_site}
EOF
chmod 640 "$STATE_FILE" || true

if [[ "$MODE" != "--status" ]]; then
  echo "$status_line"
fi

#!/usr/bin/env bash

set -euo pipefail

mrn_timestamp() {
  date '+%Y-%m-%d %H:%M:%S'
}

log_info() {
  printf '[%s] [INFO] %s\n' "$(mrn_timestamp)" "$*" >&2
}

log_warn() {
  printf '[%s] [WARN] %s\n' "$(mrn_timestamp)" "$*" >&2
}

log_error() {
  printf '[%s] [ERROR] %s\n' "$(mrn_timestamp)" "$*" >&2
}

command_exists() {
  command -v "$1" >/dev/null 2>&1
}

abspath() {
  local target="$1"
  if [[ -d "${target}" ]]; then
    (cd "${target}" && pwd -P)
  else
    (cd "$(dirname "${target}")" && printf '%s/%s\n' "$(pwd -P)" "$(basename "${target}")")
  fi
}

first_non_empty() {
  local value
  for value in "$@"; do
    if [[ -n "${value}" ]]; then
      printf '%s\n' "${value}"
      return 0
    fi
  done
  printf '%s\n' ""
  return 0
}

lowercase() {
  printf '%s' "$1" | tr '[:upper:]' '[:lower:]'
}

trim() {
  local value="$1"
  value="${value#${value%%[![:space:]]*}}"
  value="${value%${value##*[![:space:]]}}"
  printf '%s' "${value}"
}

markdown_escape() {
  local value="$1"
  value="${value//|/\\|}"
  value="${value//$'\n'/ }"
  printf '%s' "${value}"
}

ensure_dir() {
  local path="$1"
  mkdir -p "${path}"
}

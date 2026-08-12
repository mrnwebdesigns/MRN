#!/usr/bin/env bash

set -euo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

detect_repo_root() {
  local start_path="$1"
  if git -C "${start_path}" rev-parse --show-toplevel >/dev/null 2>&1; then
    git -C "${start_path}" rev-parse --show-toplevel
  else
    printf '%s\n' "$(abspath "${start_path}")"
  fi
}

detect_nested_git_repos() {
  local root="$1"
  find "${root}" -type d -name .git -not -path "${root}/.git" -prune 2>/dev/null | sed 's|/\.git$||' | sort -u
}

repo_is_git() {
  local repo="$1"
  git -C "${repo}" rev-parse --is-inside-work-tree >/dev/null 2>&1
}

repo_branch() {
  local repo="$1"
  if repo_is_git "${repo}"; then
    git -C "${repo}" rev-parse --abbrev-ref HEAD 2>/dev/null || printf 'detached\n'
  else
    printf 'n/a\n'
  fi
}

repo_sha() {
  local repo="$1"
  if repo_is_git "${repo}"; then
    git -C "${repo}" rev-parse HEAD 2>/dev/null || printf 'unknown\n'
  else
    printf 'n/a\n'
  fi
}

repo_tag_exact() {
  local repo="$1"
  if repo_is_git "${repo}"; then
    git -C "${repo}" describe --tags --exact-match 2>/dev/null || true
  fi
}

repo_dirty_state() {
  local repo="$1"
  if ! repo_is_git "${repo}"; then
    printf 'non-git\n'
    return 0
  fi

  if [[ -n "$(git -C "${repo}" status --porcelain 2>/dev/null)" ]]; then
    printf 'dirty\n'
  else
    printf 'clean\n'
  fi
}

detect_site_slug_from_repo_root() {
  local repo_root="$1"
  basename "${repo_root}"
}

detect_site_path() {
  local repo_root="$1"
  local local_sites_root="$2"
  local slug
  slug="$(detect_site_slug_from_repo_root "${repo_root}")"
  local candidate="${local_sites_root}/${slug}/app/public"
  if [[ -d "${candidate}" ]]; then
    printf '%s\n' "${candidate}"
  fi
}

detect_site_url() {
  local site_path="$1"
  if [[ -z "${site_path}" || ! -d "${site_path}" ]]; then
    return 1
  fi

  if command_exists wp; then
    wp --path="${site_path}" option get home 2>/dev/null || true
    return 0
  fi

  local local_wp="/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp"
  if [[ -x "${local_wp}" ]]; then
    "${local_wp}" --path="${site_path}" option get home 2>/dev/null || true
    return 0
  fi

  return 1
}

read_required_discovery_files() {
  local repo_root="$1"
  local files=()
  files+=("${repo_root}/AGENTS.md")
  files+=("${repo_root}/STACK_BASELINE.md")
  files+=("${repo_root}/stack.lock")
  printf '%s\n' "${files[@]}"
}

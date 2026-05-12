#!/usr/bin/env bash

set -euo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

declare -a REPORT_TOOL_ROWS=()
REPORT_OVERALL_PASS_FAIL="Fail/Blocked"
REPORT_OVERALL_NOTES="No checks executed"
REPORT_OVERALL_SCOPE="all in-scope repos"

report_reset() {
  REPORT_TOOL_ROWS=()
  REPORT_OVERALL_PASS_FAIL="Fail/Blocked"
  REPORT_OVERALL_NOTES="No checks executed"
  REPORT_OVERALL_SCOPE="all in-scope repos"
}

report_add_tool_row() {
  local tool="$1"
  local ran_skipped="$2"
  local pass_fail="$3"
  local repo_path="$4"
  local notes="$5"
  REPORT_TOOL_ROWS+=("${tool}"$'\t'"${ran_skipped}"$'\t'"${pass_fail}"$'\t'"${repo_path}"$'\t'"${notes}")
}

report_set_overall() {
  REPORT_OVERALL_PASS_FAIL="$1"
  REPORT_OVERALL_SCOPE="$2"
  REPORT_OVERALL_NOTES="$3"
}

report_emit_tool_table() {
  echo "| Tool | Ran/Skipped | Pass/Fail | Repo/Path | Notes |"
  echo "| --- | --- | --- | --- | --- |"

  local row tool ran pass path notes
  for row in "${REPORT_TOOL_ROWS[@]}"; do
    IFS=$'\t' read -r tool ran pass path notes <<<"${row}"
    echo "| $(markdown_escape "${tool}") | $(markdown_escape "${ran}") | $(markdown_escape "${pass}") | $(markdown_escape "${path}") | $(markdown_escape "${notes}") |"
  done

  echo "| **OVERALL** | Ran | ${REPORT_OVERALL_PASS_FAIL} | $(markdown_escape "${REPORT_OVERALL_SCOPE}") | $(markdown_escape "${REPORT_OVERALL_NOTES}") |"
}

report_emit_markdown() {
  local output_file="${1:-}"

  local final_result
  if [[ "${REPORT_OVERALL_PASS_FAIL}" == "Pass" ]]; then
    final_result="**Release QA Result: 100% SUCCESS**"
  else
    final_result="**Release QA Result: NOT 100%**"
  fi

  if [[ -n "${output_file}" ]]; then
    ensure_dir "$(dirname "${output_file}")"
    exec >"${output_file}"
  fi

  echo "1) Changes Summary By Repo"
  echo "${SECTION_CHANGES_SUMMARY:-No data}"
  echo
  echo "2) Release Readiness Summary"
  echo "${SECTION_RELEASE_READINESS:-No data}"
  echo
  echo "3) Tool Execution Report"
  report_emit_tool_table
  echo
  echo "${final_result}"
  echo
  echo "4) Verification Steps"
  echo "${SECTION_VERIFICATION_STEPS:-No data}"
  echo
  echo "5) Missing Release Items"
  echo "${SECTION_MISSING_ITEMS:-No data}"
  echo
  echo "6) Rollout Risks"
  echo "${SECTION_ROLLOUT_RISKS:-No data}"
  echo
  echo "7) Security Concerns"
  echo "${SECTION_SECURITY_CONCERNS:-No data}"
  echo
  echo "8) Accessibility Concerns"
  echo "${SECTION_ACCESSIBILITY_CONCERNS:-No data}"
  echo
  echo "9) Performance Concerns"
  echo "${SECTION_PERFORMANCE_CONCERNS:-No data}"
  echo
  echo "10) Classification:"
  echo "- release blockers"
  echo "${SECTION_CLASSIFICATION_BLOCKERS:-- none}"
  echo "- should-fix-before-release"
  echo "${SECTION_CLASSIFICATION_SHOULD_FIX:-- none}"
  echo "- follow-up items"
  echo "${SECTION_CLASSIFICATION_FOLLOW_UP:-- none}"
  echo
  echo "11) Cross-Repo Coordination Risks"
  echo "${SECTION_CROSS_REPO_RISKS:-No data}"
}

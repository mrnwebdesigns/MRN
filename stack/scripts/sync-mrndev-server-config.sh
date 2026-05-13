#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  sync-mrndev-server-config.sh [--pull] [--push] [--ssh-host <ssh-host>] [--dry-run]

Description:
  Sync tracked server config files for mrndev using:
    stack/server-config/mrndev/manifest.txt

Modes:
  --pull   Pull remote files into local repo snapshots (default)
  --push   Push local files to remote for entries marked rw

Notes:
  - Manifest format: local_path|remote_path|access_mode|file_mode
  - access_mode=rw: pull + push
  - access_mode=ro: pull only (push skipped)
EOF
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
MANIFEST="${REPO_ROOT}/stack/server-config/mrndev/manifest.txt"

SSH_HOST="mrndev-stack-manager@167.99.54.77"
MODE="pull"
DRY_RUN=0

while [[ $# -gt 0 ]]; do
	case "$1" in
		--pull)
			MODE="pull"
			shift
			;;
		--push)
			MODE="push"
			shift
			;;
		--ssh-host)
			SSH_HOST="${2:-}"
			shift 2
			;;
		--dry-run)
			DRY_RUN=1
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

[[ -f "${MANIFEST}" ]] || {
	echo "Manifest not found: ${MANIFEST}" >&2
	exit 1
}

for required in ssh; do
	command -v "${required}" >/dev/null 2>&1 || {
		echo "Required command not found: ${required}" >&2
		exit 1
	}
done

if [[ "${MODE}" == "push" ]]; then
	command -v scp >/dev/null 2>&1 || {
		echo "Required command not found: scp" >&2
		exit 1
	}
fi

pull_file() {
	local local_rel="$1"
	local remote_path="$2"
	local file_mode="$3"
	local local_abs="${REPO_ROOT}/${local_rel}"
	local local_dir tmp_file

	local_dir="$(dirname "${local_abs}")"
	tmp_file="${local_abs}.tmp.$$"

	mkdir -p "${local_dir}"

	if [[ "${DRY_RUN}" -eq 1 ]]; then
		echo "PULL ${remote_path} -> ${local_rel} (mode ${file_mode})"
		return 0
	fi

	if ssh -n "${SSH_HOST}" "cat '${remote_path}' 2>/dev/null" > "${tmp_file}"; then
		mv "${tmp_file}" "${local_abs}"
		chmod "${file_mode}" "${local_abs}"
		echo "Pulled ${remote_path} -> ${local_rel}"
	else
		rm -f "${tmp_file}"
		return 1
	fi
}

push_file() {
	local local_rel="$1"
	local remote_path="$2"
	local mode="$3"
	local local_abs="${REPO_ROOT}/${local_rel}"
	local remote_tmp

	[[ -f "${local_abs}" ]] || {
		echo "ERROR: Local file missing for push: ${local_rel}" >&2
		return 1
	}

	remote_tmp="/tmp/$(basename "${remote_path}").$$"

	if [[ "${DRY_RUN}" -eq 1 ]]; then
		echo "PUSH ${local_rel} -> ${remote_path} (mode ${mode})"
		return 0
	fi

	scp "${local_abs}" "${SSH_HOST}:${remote_tmp}" >/dev/null
	ssh -n "${SSH_HOST}" "mkdir -p '$(dirname "${remote_path}")' && cp '${remote_tmp}' '${remote_path}' && chmod ${mode} '${remote_path}' && rm -f '${remote_tmp}'"
	echo "Pushed ${local_rel} -> ${remote_path}"
}

processed=0
skipped=0
failed=0

while IFS='|' read -r local_rel remote_path access_mode file_mode; do
	# Trim whitespace.
	local_rel="${local_rel#"${local_rel%%[![:space:]]*}"}"
	local_rel="${local_rel%"${local_rel##*[![:space:]]}"}"
	remote_path="${remote_path#"${remote_path%%[![:space:]]*}"}"
	remote_path="${remote_path%"${remote_path##*[![:space:]]}"}"
	access_mode="${access_mode#"${access_mode%%[![:space:]]*}"}"
	access_mode="${access_mode%"${access_mode##*[![:space:]]}"}"
	file_mode="${file_mode#"${file_mode%%[![:space:]]*}"}"
	file_mode="${file_mode%"${file_mode##*[![:space:]]}"}"

	[[ -z "${local_rel}" || "${local_rel}" == \#* ]] && continue
	[[ -n "${remote_path}" ]] || {
		echo "ERROR: Empty remote path for ${local_rel}" >&2
		failed=$(( failed + 1 ))
		continue
	}
	[[ "${access_mode}" == "rw" || "${access_mode}" == "ro" ]] || {
		echo "ERROR: Invalid mode '${access_mode}' for ${local_rel}" >&2
		failed=$(( failed + 1 ))
		continue
	}
	if [[ -z "${file_mode}" ]]; then
		if [[ "${access_mode}" == "rw" ]]; then
			file_mode="750"
		else
			file_mode="644"
		fi
	fi
	[[ "${file_mode}" =~ ^[0-7]{3,4}$ ]] || {
		echo "ERROR: Invalid file mode '${file_mode}' for ${local_rel}" >&2
		failed=$(( failed + 1 ))
		continue
	}

	processed=$(( processed + 1 ))

	if [[ "${MODE}" == "pull" ]]; then
		if ! pull_file "${local_rel}" "${remote_path}" "${file_mode}"; then
			if [[ "${access_mode}" == "ro" ]]; then
				echo "WARN: Pull skipped for ro entry (permission or availability): ${remote_path}" >&2
				skipped=$(( skipped + 1 ))
			else
				echo "ERROR: Failed to pull rw entry: ${remote_path}" >&2
				failed=$(( failed + 1 ))
			fi
		fi
		continue
	fi

	# Push mode.
	if [[ "${access_mode}" == "ro" ]]; then
		echo "Skip push (ro): ${local_rel}"
		skipped=$(( skipped + 1 ))
		continue
	fi

	if ! push_file "${local_rel}" "${remote_path}" "${file_mode}"; then
		failed=$(( failed + 1 ))
	fi
done < "${MANIFEST}"

echo "Done mode=${MODE} processed=${processed} skipped=${skipped} failed=${failed}"
[[ "${failed}" -eq 0 ]]

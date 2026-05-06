#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  audit-config-helper-parity.sh [--discovery-ssh-host <ssh-host>] [--site-owner-host <ssh-host>] [--quiet]

Description:
  Audits mrn-config-helper parity across /home/*/htdocs/*.mrndev.io sites.
  Outputs CSV rows with version parity and site-owner update readiness.

Columns:
  site,user,plugin_present,installed_version,package_version,version_parity,file_owner,site_owner_ssh_ok,site_owner_can_write

Exit codes:
  0 = all installed copies match package version and are site-owner writable with working site-owner SSH
  1 = one or more rows failed parity/readiness checks
EOF
}

DISCOVERY_SSH_HOST="mrndev"
SITE_OWNER_HOST="mrndev-site-owner"
QUIET=0

while [[ $# -gt 0 ]]; do
	case "$1" in
		--discovery-ssh-host)
			DISCOVERY_SSH_HOST="${2:-}"
			shift 2
			;;
		--site-owner-host)
			SITE_OWNER_HOST="${2:-}"
			shift 2
			;;
		--quiet)
			QUIET=1
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

for required in ssh awk sed grep sort unzip; do
	command -v "$required" >/dev/null 2>&1 || {
		echo "Required command not found: $required" >&2
		exit 1
	}
done

REMOTE_PACKAGE="/home/mrndev-stack-manager/stack/packages/mrn-config-helper.zip"
REMOTE_PACKAGE_FILE="mrn-config-helper/mrn-config-helper.php"

PACKAGE_VERSION="$(
	ssh -n "$DISCOVERY_SSH_HOST" "unzip -p '$REMOTE_PACKAGE' '$REMOTE_PACKAGE_FILE' | grep -m1 '^ \\* Version:' | sed 's/^ \\* Version:[[:space:]]*//'" \
		| tr -d '\r'
)"

if [[ -z "$PACKAGE_VERSION" ]]; then
	echo "Could not resolve package version from $REMOTE_PACKAGE" >&2
	exit 1
fi

SITE_ROOTS="$(
	ssh -n "$DISCOVERY_SSH_HOST" "find /home -mindepth 3 -maxdepth 3 -type d -path '/home/*/htdocs/*.mrndev.io' 2>/dev/null | sort" \
		| tr -d '\r'
)"

if [[ -z "$SITE_ROOTS" ]]; then
	echo "No site roots discovered on $DISCOVERY_SSH_HOST." >&2
	exit 1
fi

if [[ "$QUIET" -ne 1 ]]; then
	echo "Package version: $PACKAGE_VERSION" >&2
fi

echo "site,user,plugin_present,installed_version,package_version,version_parity,file_owner,site_owner_ssh_ok,site_owner_can_write"

FAILURES=0

while IFS= read -r SITE_ROOT; do
	[[ -n "$SITE_ROOT" ]] || continue

	SITE="$(basename "$SITE_ROOT")"
	SITE_USER="$(awk -F/ '{print $3}' <<<"$SITE_ROOT")"
	PLUGIN_FILE="$SITE_ROOT/wp-content/plugins/mrn-config-helper/mrn-config-helper.php"

	PLUGIN_PRESENT="no"
	INSTALLED_VERSION="missing"
	VERSION_PARITY="n/a"
	FILE_OWNER="missing:missing"
	SITE_OWNER_SSH_OK="no"
	SITE_OWNER_CAN_WRITE="no"

	if ssh -n "$DISCOVERY_SSH_HOST" "test -f '$PLUGIN_FILE'"; then
		PLUGIN_PRESENT="yes"
		INSTALLED_VERSION="$(
			ssh -n "$DISCOVERY_SSH_HOST" "grep -m1 '^ \\* Version:' '$PLUGIN_FILE' | sed 's/^ \\* Version:[[:space:]]*//'" \
				| tr -d '\r'
		)"
		FILE_OWNER="$(
			ssh -n "$DISCOVERY_SSH_HOST" "stat -c '%U:%G' '$PLUGIN_FILE'" \
				| tr -d '\r'
		)"
		if [[ "$INSTALLED_VERSION" == "$PACKAGE_VERSION" ]]; then
			VERSION_PARITY="match"
		else
			VERSION_PARITY="mismatch"
		fi

		if ssh -n -o BatchMode=yes -o ConnectTimeout=8 -l "$SITE_USER" "$SITE_OWNER_HOST" "test -d '$SITE_ROOT'" >/dev/null 2>&1; then
			SITE_OWNER_SSH_OK="yes"
			if ssh -n -o BatchMode=yes -o ConnectTimeout=8 -l "$SITE_USER" "$SITE_OWNER_HOST" "test -w '$PLUGIN_FILE'" >/dev/null 2>&1; then
				SITE_OWNER_CAN_WRITE="yes"
			else
				SITE_OWNER_CAN_WRITE="no"
			fi
		else
			SITE_OWNER_SSH_OK="no"
			SITE_OWNER_CAN_WRITE="no"
		fi

		if [[ "$VERSION_PARITY" != "match" || "$SITE_OWNER_SSH_OK" != "yes" || "$SITE_OWNER_CAN_WRITE" != "yes" ]]; then
			FAILURES=$((FAILURES + 1))
		fi
	fi

	echo "${SITE},${SITE_USER},${PLUGIN_PRESENT},${INSTALLED_VERSION},${PACKAGE_VERSION},${VERSION_PARITY},${FILE_OWNER},${SITE_OWNER_SSH_OK},${SITE_OWNER_CAN_WRITE}"
done <<<"$SITE_ROOTS"

if [[ "$FAILURES" -gt 0 ]]; then
	if [[ "$QUIET" -ne 1 ]]; then
		echo "Parity/readiness failures: $FAILURES" >&2
	fi
	exit 1
fi

if [[ "$QUIET" -ne 1 ]]; then
	echo "All installed mrn-config-helper copies are in parity and site-owner writable." >&2
fi

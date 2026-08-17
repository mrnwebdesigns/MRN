#!/usr/bin/env bash
set -euo pipefail

DOMAIN=""
SITE_USER=""
EMAIL="${MRN_SSL_EMAIL:-wordpress_admin@mrnwebdesigns.com}"
REMOTE_HOST="${MRN_SSL_REMOTE_HOST:-mrndev-ops}"
REMOTE_USER="${MRN_SSL_REMOTE_USER:-mrn-ops}"
INSTALL_SSH="${MRN_SSL_INSTALL_SSH:-}"
SUDO_IMPORT="${MRN_SSL_SUDO_IMPORT:-0}"
export OP_ACCOUNT="mrnwebdesigns.1password.com"
OP_VAULT="${MRN_SSL_OP_VAULT:-Production Hub}"
OP_ITEM="${MRN_SSL_OP_ITEM:-Production Hub - Cloudflare}"
CERTBOT_VENV="${MRN_SSL_CERTBOT_VENV:-/tmp/mrn-certbot-venv}"
CERTBOT_PROPAGATION_SECONDS="${MRN_SSL_CERTBOT_PROPAGATION_SECONDS:-30}"
WORK_ROOT=""
EXECUTE=0
KEEP_WORKSPACE=0

usage() {
  cat <<'USAGE'
Usage:
  cloudpanel-dns01-ssl.sh --domain <fqdn> --site-user <cloudpanel-user> --execute

Issues a Let's Encrypt certificate with Cloudflare DNS-01 and stages the
certificate files on the CloudPanel server for import.

By default, this does not require root SSH. It prints the root-side CloudPanel
import command after staging the files. If root/passwordless-sudo SSH is
available, pass --install-ssh <ssh-target> to run the import automatically.

Options:
  --domain <fqdn>            Required, for example platform.mrndev.io.
  --site-user <user>         Required CloudPanel site owner, for path context.
  --email <email>            ACME account email.
  --remote-host <ssh-alias>  SSH alias used for staging files. Default: mrndev-ops.
  --remote-user <user>       Remote staging user's home. Default: mrn-ops.
  --install-ssh <ssh-target> Optional root-capable SSH target for import.
  --sudo-import              Run the import through sudo -n on --install-ssh.
  --work-root <path>         Local certbot workspace. Default: /tmp/mrn-cloudpanel-ssl-...
  --keep-workspace           Keep local issued cert files after completion.
  --execute                  Required for live ACME/DNS/server writes.

Cloudflare token source:
  1. CLOUDFLARE_API_TOKEN in the environment.
  2. 1Password field CLOUDFLARE_API_TOKEN from Production Hub - Cloudflare.
USAGE
}

die() {
  printf 'Error: %s\n' "$*" >&2
  exit 1
}

quote() {
  printf '%q' "$1"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain)
      DOMAIN="${2:-}"
      shift 2
      ;;
    --site-user)
      SITE_USER="${2:-}"
      shift 2
      ;;
    --email)
      EMAIL="${2:-}"
      shift 2
      ;;
    --remote-host)
      REMOTE_HOST="${2:-}"
      shift 2
      ;;
    --remote-user)
      REMOTE_USER="${2:-}"
      shift 2
      ;;
    --install-ssh)
      INSTALL_SSH="${2:-}"
      shift 2
      ;;
    --sudo-import)
      SUDO_IMPORT=1
      shift
      ;;
    --work-root)
      WORK_ROOT="${2:-}"
      shift 2
      ;;
    --keep-workspace)
      KEEP_WORKSPACE=1
      shift
      ;;
    --execute)
      EXECUTE=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      die "Unknown argument: $1"
      ;;
  esac
done

[[ -n "${DOMAIN}" ]] || die "Missing --domain."
[[ -n "${SITE_USER}" ]] || die "Missing --site-user."

if [[ "${EXECUTE}" -ne 1 ]]; then
  usage
  printf '\nDry run only. Add --execute to issue and stage a live certificate for %s.\n' "${DOMAIN}"
  exit 0
fi

command -v ssh >/dev/null 2>&1 || die "ssh is required."
command -v scp >/dev/null 2>&1 || die "scp is required."
command -v python3 >/dev/null 2>&1 || die "python3 is required."

if [[ -z "${WORK_ROOT}" ]]; then
  WORK_ROOT="/tmp/mrn-cloudpanel-ssl-${DOMAIN//[^A-Za-z0-9_.-]/-}-$(date +%s)"
fi

mkdir -p "${WORK_ROOT}"
chmod 700 "${WORK_ROOT}"

cleanup() {
  rm -f "${WORK_ROOT}/cloudflare.ini"
  if [[ "${KEEP_WORKSPACE}" -ne 1 ]]; then
    rm -rf "${WORK_ROOT}"
  fi
}
trap cleanup EXIT

if [[ ! -x "${CERTBOT_VENV}/bin/certbot" ]]; then
  python3 -m venv "${CERTBOT_VENV}"
  "${CERTBOT_VENV}/bin/python" -m pip install --upgrade pip >/dev/null
  "${CERTBOT_VENV}/bin/python" -m pip install certbot certbot-dns-cloudflare >/dev/null
fi

CF_TOKEN="${CLOUDFLARE_API_TOKEN:-}"
if [[ -z "${CF_TOKEN}" ]]; then
  command -v op >/dev/null 2>&1 || die "op CLI is required when CLOUDFLARE_API_TOKEN is not set."
  CF_TOKEN="$(
    op read "op://${OP_VAULT}/${OP_ITEM}/CLOUDFLARE_API_TOKEN"
  )"
fi
[[ -n "${CF_TOKEN}" ]] || die "Cloudflare API token is empty."

CF_CREDS="${WORK_ROOT}/cloudflare.ini"
install -m 600 /dev/null "${CF_CREDS}"
printf 'dns_cloudflare_api_token = %s\n' "${CF_TOKEN}" > "${CF_CREDS}"

CERTBOT_CONFIG="${WORK_ROOT}/config"
CERTBOT_WORK="${WORK_ROOT}/work"
CERTBOT_LOGS="${WORK_ROOT}/logs"
mkdir -p "${CERTBOT_CONFIG}" "${CERTBOT_WORK}" "${CERTBOT_LOGS}"

"${CERTBOT_VENV}/bin/certbot" certonly \
  --dns-cloudflare \
  --dns-cloudflare-credentials "${CF_CREDS}" \
  --dns-cloudflare-propagation-seconds "${CERTBOT_PROPAGATION_SECONDS}" \
  --non-interactive \
  --agree-tos \
  --email "${EMAIL}" \
  --config-dir "${CERTBOT_CONFIG}" \
  --work-dir "${CERTBOT_WORK}" \
  --logs-dir "${CERTBOT_LOGS}" \
  -d "${DOMAIN}"

LIVE_DIR="${CERTBOT_CONFIG}/live/${DOMAIN}"
[[ -f "${LIVE_DIR}/privkey.pem" ]] || die "Certbot did not produce privkey.pem."
[[ -f "${LIVE_DIR}/cert.pem" ]] || die "Certbot did not produce cert.pem."
[[ -f "${LIVE_DIR}/chain.pem" ]] || die "Certbot did not produce chain.pem."

REMOTE_DIR="/home/${REMOTE_USER}/tmp/mrn-ssl-import-${DOMAIN}-$(date +%s)"
ssh -o BatchMode=yes -o ConnectTimeout=8 "${REMOTE_HOST}" \
  "mkdir -p $(quote "${REMOTE_DIR}") && chmod 700 $(quote "${REMOTE_DIR}")"
scp -q -o BatchMode=yes -o ConnectTimeout=8 \
  "${LIVE_DIR}/privkey.pem" \
  "${LIVE_DIR}/cert.pem" \
  "${LIVE_DIR}/chain.pem" \
  "${REMOTE_HOST}:${REMOTE_DIR}/"
ssh -o BatchMode=yes -o ConnectTimeout=8 "${REMOTE_HOST}" \
  "chmod 600 $(quote "${REMOTE_DIR}")/*.pem"

if [[ "${SUDO_IMPORT}" -eq 1 ]]; then
  IMPORT_CMD="sudo -n /usr/bin/php /home/clp/htdocs/app/files/bin/console site:install:certificate --domainName=$(quote "${DOMAIN}") --privateKey=$(quote "${REMOTE_DIR}/privkey.pem") --certificate=$(quote "${REMOTE_DIR}/cert.pem") --certificateChain=$(quote "${REMOTE_DIR}/chain.pem") --no-interaction"
else
  IMPORT_CMD="cd /home/clp/htdocs/app/files && php bin/console site:install:certificate --domainName=$(quote "${DOMAIN}") --privateKey=$(quote "${REMOTE_DIR}/privkey.pem") --certificate=$(quote "${REMOTE_DIR}/cert.pem") --certificateChain=$(quote "${REMOTE_DIR}/chain.pem") --no-interaction"
fi
CLEANUP_CMD="rm -rf $(quote "${REMOTE_DIR}")"

if [[ -n "${INSTALL_SSH}" ]]; then
  ssh -o BatchMode=yes -o ConnectTimeout=8 "${INSTALL_SSH}" "${IMPORT_CMD}"
  ssh -o BatchMode=yes -o ConnectTimeout=8 "${REMOTE_HOST}" "${CLEANUP_CMD}"
  printf 'Installed certificate for %s via %s.\n' "${DOMAIN}" "${INSTALL_SSH}"
else
  cat <<EOF
Certificate issued and staged for ${DOMAIN}.

Remote staged directory:
  ${REMOTE_HOST}:${REMOTE_DIR}

Run this on a root-capable CloudPanel shell to import it:
  ${IMPORT_CMD}

Then clean up:
  ${CLEANUP_CMD}

Verify:
  curl -I https://${DOMAIN}/
EOF
fi

#!/bin/sh
# Install as /etc/letsencrypt/renewal-hooks/deploy/mrn-operations-nginx.
# certonly/webroot renewals must load the renewed certificate into Nginx.
set -eu
if [ "${RENEWED_LINEAGE:-}" = /etc/letsencrypt/live/operations.mrnwebdesigns.com ]; then
    /usr/sbin/nginx -t
    /usr/bin/systemctl reload nginx
fi

#!/bin/sh
set -e

# Construit les directives CORS Mercure à partir de MERCURE_CORS_ORIGINS
# (origines séparées par des espaces, sans slash final).
# Exemple Dokploy :
#   MERCURE_CORS_ORIGINS=https://admin.cabinetdentaireousmanesow.cloud https://admin.mondentiste-mali.com
if [ -n "${MERCURE_CORS_ORIGINS:-}" ]; then
    _directives=""
    for _origin in $MERCURE_CORS_ORIGINS; do
        _directives="${_directives}cors_origins ${_origin}
"
    done
    if [ -n "${MERCURE_EXTRA_DIRECTIVES:-}" ]; then
        _directives="${_directives}${MERCURE_EXTRA_DIRECTIVES}
"
    fi
    export MERCURE_EXTRA_DIRECTIVES="$_directives"
fi

# Derrière le proxy Dokploy / Traefik (réseau Docker)
if [ -z "${TRUSTED_PROXIES:-}" ]; then
    export TRUSTED_PROXIES="private_ranges"
fi

exec /usr/bin/caddy run --config /etc/caddy/Caddyfile

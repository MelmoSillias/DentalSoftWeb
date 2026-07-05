#!/bin/sh
set -e

# Aligné sur l'exemple officiel dunglas/mercure (docker-compose) :
# https://github.com/dunglas/mercure
#
# MERCURE_EXTRA_DIRECTIVES est injecté dans le bloc `mercure { }` du Caddyfile.
# Chaque ligne = une directive (ex. cors_origins, anonymous, demo).

if [ -z "${SERVER_NAME:-}" ]; then
    export SERVER_NAME=":80"
fi

_directives=""

# Origines autorisées (séparées par des espaces, SANS slash final).
# Ex. MERCURE_CORS_ORIGINS="https://admin.cabinetdentaireousmanesow.cloud https://admin.mondentiste-mali.com"
if [ -n "${MERCURE_CORS_ORIGINS:-}" ]; then
    _line="cors_origins"
    for _origin in $MERCURE_CORS_ORIGINS; do
        _line="${_line} ${_origin}"
    done
    _directives="${_directives}${_line}
"
fi

# Directives supplémentaires (format multiligne Dokploy / docker-compose).
if [ -n "${MERCURE_EXTRA_DIRECTIVES:-}" ]; then
    _directives="${_directives}${MERCURE_EXTRA_DIRECTIVES}
"
fi

if [ -n "$_directives" ]; then
    export MERCURE_EXTRA_DIRECTIVES="$_directives"
    echo "[mercure] MERCURE_EXTRA_DIRECTIVES configuré :"
    printf '%s' "$_directives" | sed 's/^/[mercure]   /'
else
    echo "[mercure] ATTENTION: aucune directive CORS — définir MERCURE_CORS_ORIGINS ou MERCURE_EXTRA_DIRECTIVES" >&2
fi

if [ -z "${TRUSTED_PROXIES:-}" ]; then
    export TRUSTED_PROXIES="private_ranges"
fi

exec /usr/bin/caddy run --config /etc/caddy/Caddyfile

#!/bin/sh
set -eu

: "${MERCURE_PUBLISHER_JWT_KEY:?MERCURE_PUBLISHER_JWT_KEY requis}"
: "${MERCURE_SUBSCRIBER_JWT_KEY:?MERCURE_SUBSCRIBER_JWT_KEY requis}"

# L'ancienne variable CORS reste utilisable avec les directives libres.
if [ -n "${MERCURE_CORS_ORIGINS:-}" ]; then
    export MERCURE_EXTRA_DIRECTIVES="cors_origins ${MERCURE_CORS_ORIGINS}
${MERCURE_EXTRA_DIRECTIVES:-}"
fi

exec /usr/bin/caddy run --config /etc/caddy/Caddyfile

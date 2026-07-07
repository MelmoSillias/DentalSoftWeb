#!/bin/sh
set -e

mkdir -p var/cache var/log var/backups public/uploads
chown -R www-data:www-data var public/uploads 2>/dev/null || chmod -R 777 var public/uploads

if [ "${APP_ENV:-prod}" = "prod" ]; then
    if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
        echo "[entrypoint] doctrine:migrations:migrate"
        php bin/console doctrine:migrations:migrate --no-interaction --env=prod \
            || echo "[entrypoint] migrate failed (verifier DATABASE_URL)"
    fi

    php bin/console cache:clear --env=prod --no-warmup || echo "[entrypoint] cache:clear failed (non bloquant)"
    php bin/console cache:warmup --env=prod || echo "[entrypoint] cache:warmup failed (non bloquant)"
fi

# Worker Messenger (Dokploy worker-cdos / worker-mondentiste) : poll SMS + consume async
if [ "${WORKER_MODE:-0}" = "1" ]; then
    _poll_interval="${SMS_POLL_INTERVAL:-60}"
    _dispatch_limit="${SMS_DISPATCH_LIMIT:-20}"
    _time_limit="${MESSENGER_TIME_LIMIT:-3600}"
    _memory_limit="${MESSENGER_MEMORY_LIMIT:-128M}"

    echo "[entrypoint] WORKER_MODE=1 — dispatch SMS toutes les ${_poll_interval}s + messenger:consume async"

    (
        while true; do
            php bin/console app:sms:dispatch-queue --limit="${_dispatch_limit}" --env="${APP_ENV:-prod}" \
                || echo "[worker] app:sms:dispatch-queue failed (non bloquant)"
            sleep "${_poll_interval}"
        done
    ) &

    exec php bin/console messenger:consume async \
        --time-limit="${_time_limit}" \
        --memory-limit="${_memory_limit}" \
        -vv
fi

exec "$@"

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

_poll_interval="${SMS_POLL_INTERVAL:-60}"
_dispatch_limit="${SMS_DISPATCH_LIMIT:-20}"
_time_limit="${MESSENGER_TIME_LIMIT:-3600}"
_memory_limit="${MESSENGER_MEMORY_LIMIT:-128M}"
_app_env="${APP_ENV:-prod}"

SMS_PID=""
MSG_PID=""
MAIN_PID=""

# Poll SMS + consumer Messenger (mails / messages async).
# $1 = process-queue (embarqué) | dispatch-queue (worker exclusif)
start_background_workers() {
    _sms_cmd="${1:-process-queue}"

    (
        while true; do
            if [ "${_sms_cmd}" = "dispatch-queue" ]; then
                php bin/console app:sms:dispatch-queue --limit="${_dispatch_limit}" --env="${_app_env}" \
                    || echo "[worker] app:sms:dispatch-queue failed (non bloquant)"
            else
                php bin/console app:sms:process-queue --limit="${_dispatch_limit}" --env="${_app_env}" \
                    || echo "[worker] app:sms:process-queue failed (non bloquant)"
            fi
            sleep "${_poll_interval}"
        done
    ) &
    SMS_PID=$!

    (
        while true; do
            php bin/console messenger:consume async \
                --time-limit="${_time_limit}" \
                --memory-limit="${_memory_limit}" \
                -vv \
                || echo "[worker] messenger:consume exited (relance dans 2s)"
            sleep 2
        done
    ) &
    MSG_PID=$!
}

stop_background_workers() {
    for _pid in "${SMS_PID}" "${MSG_PID}"; do
        if [ -n "${_pid}" ] && kill -0 "${_pid}" 2>/dev/null; then
            kill "${_pid}" 2>/dev/null || true
        fi
    done
    wait 2>/dev/null || true
}

# Mode worker exclusif (conteneur dédié, sans HTTP) — optionnel / scaling.
if [ "${WORKER_MODE:-0}" = "1" ]; then
    echo "[entrypoint] WORKER_MODE=1 — dispatch SMS toutes les ${_poll_interval}s + messenger:consume async"
    start_background_workers "dispatch-queue"

    _shutdown_worker() {
        echo "[entrypoint] signal reçu — arrêt worker"
        stop_background_workers
        exit 0
    }
    trap '_shutdown_worker' TERM INT
    wait
    exit 0
fi

# Mode API : FrankenPHP + workers embarqués (défaut — un seul conteneur Dokploy).
if [ "${ENABLE_EMBEDDED_WORKER:-1}" != "0" ]; then
    echo "[entrypoint] ENABLE_EMBEDDED_WORKER=1 — process-queue SMS + messenger:consume en arrière-plan"
    start_background_workers "process-queue"

    _shutdown_api() {
        echo "[entrypoint] signal reçu — arrêt FrankenPHP + workers embarqués"
        if [ -n "${MAIN_PID}" ] && kill -0 "${MAIN_PID}" 2>/dev/null; then
            kill "${MAIN_PID}" 2>/dev/null || true
        fi
        stop_background_workers
        exit 0
    }
    trap '_shutdown_api' TERM INT

    "$@" &
    MAIN_PID=$!

    set +e
    wait "${MAIN_PID}"
    _exit=$?
    set -e

    stop_background_workers
    exit "${_exit}"
fi

exec "$@"

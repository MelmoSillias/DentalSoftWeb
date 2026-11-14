#!/bin/sh
set -e

# Construire le DSN au runtime : aucun secret dans l'image et mots de passe URL-encodés.
# DATABASE_URL reste utilisable par les anciens déploiements hors Compose.
if [ -n "${DB_HOST:-}" ]; then
    export DATABASE_URL="$(php /app/docker/database-url.php)"
fi

mkdir -p var/cache var/log var/backups var/uploads public/uploads public/upload_files public/assurances config/jwt
chown www-data:www-data var var/cache var/log var/backups var/uploads public/uploads public/upload_files public/assurances config/jwt

# Générer une seule fois dans le volume. Une passphrase changée échoue explicitement.
if [ ! -s config/jwt/private.pem ] && [ ! -s config/jwt/public.pem ]; then
    : "${JWT_PASSPHRASE:?JWT_PASSPHRASE requis pour générer les clés}"
    echo "[entrypoint] Initialisation des clés JWT persistantes"
    (
        umask 077
        openssl genpkey -algorithm RSA -out config/jwt/private.pem.tmp \
            -pkeyopt rsa_keygen_bits:4096 -aes-256-cbc -pass env:JWT_PASSPHRASE
        openssl pkey -in config/jwt/private.pem.tmp -passin env:JWT_PASSPHRASE \
            -pubout -out config/jwt/public.pem.tmp
        mv config/jwt/private.pem.tmp config/jwt/private.pem
        mv config/jwt/public.pem.tmp config/jwt/public.pem
    )
fi
openssl pkey -in config/jwt/private.pem -passin env:JWT_PASSPHRASE -pubout \
    | cmp - config/jwt/public.pem
chown www-data:www-data config/jwt/*.pem
chmod 640 config/jwt/*.pem

if [ "${APP_ENV:-prod}" = "prod" ]; then
    if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
        echo "[entrypoint] doctrine:migrations:migrate"
        php bin/console doctrine:migrations:migrate --no-interaction --env=prod
    fi

    # Nécessaire dès le premier boot avec auto_setup=0 (y compris la file failed).
    if [ "${ENABLE_EMBEDDED_WORKER:-1}" != "0" ] || [ "${WORKER_MODE:-0}" = "1" ]; then
        php bin/console messenger:setup-transports --no-interaction --env=prod
    fi

    # Échec bloquant : avec set -e, un warmup raté empêche FrankenPHP de démarrer.
    echo "[entrypoint] cache:clear + cache:warmup + ensure-proxies"
    php bin/console cache:clear --env=prod --no-warmup
    php bin/console cache:warmup --env=prod
    php bin/console app:doctrine:ensure-proxies --env=prod
    chown -R www-data:www-data var/cache var/log
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

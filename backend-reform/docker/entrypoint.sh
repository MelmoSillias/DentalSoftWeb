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

exec "$@"

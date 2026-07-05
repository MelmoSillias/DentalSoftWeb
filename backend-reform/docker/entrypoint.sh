#!/bin/sh

mkdir -p var/cache var/log var/backups public/uploads
chown -R www-data:www-data var public/uploads 2>/dev/null || chmod -R 777 var public/uploads

if [ "${APP_ENV:-prod}" = "prod" ]; then
    php bin/console cache:clear --env=prod --no-warmup || echo "[entrypoint] cache:clear failed (non bloquant)"
    php bin/console cache:warmup --env=prod || echo "[entrypoint] cache:warmup failed (non bloquant)"
fi

exec "$@"

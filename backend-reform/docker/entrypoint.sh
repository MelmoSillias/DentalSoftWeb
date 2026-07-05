#!/bin/sh
set -e

mkdir -p var/cache var/log var/backups public/uploads
chmod -R 775 var public/uploads 2>/dev/null || true

if [ "${APP_ENV:-prod}" = "prod" ]; then
    php bin/console cache:clear --env=prod --no-warmup
    php bin/console cache:warmup --env=prod
fi

exec "$@"

ARG FRANKENPHP_IMAGE=dunglas/frankenphp:1.13.4-php8.2-bookworm
FROM ${FRANKENPHP_IMAGE}

# Mêmes versions PHP minimales que pour l'image de la stack.
RUN php -r '$minimums = [802 => "8.2.35", 803 => "8.3.36", 804 => "8.4.27", 805 => "8.5.12"]; $branch = PHP_MAJOR_VERSION * 100 + PHP_MINOR_VERSION; if (!isset($minimums[$branch]) || version_compare(PHP_VERSION, $minimums[$branch], "<")) { fwrite(STDERR, "PHP non valide pour cette image de production\n"); exit(1); }'

RUN apt-get update && apt-get install -y --no-install-recommends \
    mariadb-client \
    procps \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    apcu \
    intl \
    opcache \
    pdo_mysql \
    pcntl \
    zip

ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APP_INTERNET_FEATURES_ENABLED=1 \
    CORS_ALLOW_ORIGIN=a^ \
    JWT_SECRET_KEY=/app/config/jwt/private.pem \
    JWT_PUBLIC_KEY=/app/config/jwt/public.pem \
    MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
COPY docker/doctrine.dokploy.yaml config/packages/doctrine_dokploy.yaml
COPY docker/framework.dokploy.yaml config/packages/framework_dokploy.yaml
RUN touch .env \
    && composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts \
    && test -f vendor/autoload_runtime.php \
    && composer check-platform-reqs --no-dev

COPY docker/Caddyfile.dokploy /etc/caddy/Caddyfile
COPY docker/php.ini /usr/local/etc/php/conf.d/dentalsoft.ini
COPY docker/entrypoint.dokploy.sh /entrypoint.sh
COPY docker/healthcheck.sh /healthcheck.sh
RUN sed -i 's/\r$//' /entrypoint.sh /healthcheck.sh \
    && chmod +x /entrypoint.sh /healthcheck.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=5 CMD ["/healthcheck.sh"]

ENTRYPOINT ["/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

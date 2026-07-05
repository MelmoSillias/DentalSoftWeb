FROM dunglas/frankenphp:1-php8.2-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    mariadb-client \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    apcu \
    intl \
    opcache \
    pdo_mysql \
    zip

ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Contexte monorepo : Dokploy build depuis la racine avec -f backend-reform/Dockerfile
COPY backend-reform/composer.json backend-reform/composer.lock backend-reform/symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

COPY backend-reform/ .
RUN composer dump-autoload --optimize --classmap-authoritative

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/php.ini /usr/local/etc/php/conf.d/dentalsoft.ini
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

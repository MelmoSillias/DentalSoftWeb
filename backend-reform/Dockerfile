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

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative

ARG JWT_PASSPHRASE
# openssl only: Symfony console boots Doctrine and needs DATABASE_URL (unavailable at image build on Dokploy).
RUN mkdir -p config/jwt \
    && if [ -n "$JWT_PASSPHRASE" ]; then export JWT_PASSPHRASE="$JWT_PASSPHRASE"; \
       else export JWT_PASSPHRASE="$(grep '^JWT_PASSPHRASE=' .env | cut -d= -f2- | tr -d '"')"; fi \
    && openssl genpkey -algorithm RSA -out config/jwt/private.pem \
         -pkeyopt rsa_keygen_bits:4096 -aes-256-cbc -pass pass:"$JWT_PASSPHRASE" \
    && openssl pkey -in config/jwt/private.pem -passin pass:"$JWT_PASSPHRASE" \
         -pubout -out config/jwt/public.pem

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/php.ini /usr/local/etc/php/conf.d/dentalsoft.ini
COPY docker/entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

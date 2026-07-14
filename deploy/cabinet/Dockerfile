# =============================================================================
# DentalSoft — image all-in-one par cabinet (admin Vue + API FrankenPHP)
# Build context: racine du monorepo DentalSoftWeb
# MariaDB reste un service Compose séparé (même déploiement Dokploy).
# =============================================================================

# --- 1) Build admin Vue ---
FROM node:22-alpine AS frontend-build

ARG CABINET=cdos
ARG BUILD_ENV=prod

WORKDIR /frontend

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ .

# Same-origin: appels API relatifs (/api) — un seul domaine public
ENV VITE_SAME_ORIGIN=1

RUN npm run build:cabinet -- --cabinet=${CABINET} --env=${BUILD_ENV}

# --- 2) API FrankenPHP + SPA embarquée ---
FROM dunglas/frankenphp:1-php8.2-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    mariadb-client \
    procps \
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

COPY backend-reform/composer.json backend-reform/composer.lock backend-reform/symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

COPY backend-reform/ .
RUN composer dump-autoload --optimize --classmap-authoritative

ARG JWT_PASSPHRASE
RUN mkdir -p config/jwt \
    && if [ -n "$JWT_PASSPHRASE" ]; then export JWT_PASSPHRASE="$JWT_PASSPHRASE"; \
       else export JWT_PASSPHRASE="$(grep '^JWT_PASSPHRASE=' .env | cut -d= -f2- | tr -d '"')"; fi \
    && openssl genpkey -algorithm RSA -out config/jwt/private.pem \
         -pkeyopt rsa_keygen_bits:4096 -aes-256-cbc -pass pass:"$JWT_PASSPHRASE" \
    && openssl pkey -in config/jwt/private.pem -passin pass:"$JWT_PASSPHRASE" \
         -pubout -out config/jwt/public.pem

COPY --from=frontend-build /frontend/dist /srv/admin

COPY deploy/cabinet/Caddyfile /etc/caddy/Caddyfile
COPY backend-reform/docker/php.ini /usr/local/etc/php/conf.d/dentalsoft.ini
COPY backend-reform/docker/entrypoint.sh /entrypoint.sh
COPY backend-reform/docker/healthcheck.sh /healthcheck.sh
RUN sed -i 's/\r$//' /entrypoint.sh /healthcheck.sh \
    && chmod +x /entrypoint.sh /healthcheck.sh

ENV ENABLE_EMBEDDED_WORKER=1

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=120s --retries=5 CMD ["/healthcheck.sh"]

ENTRYPOINT ["/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

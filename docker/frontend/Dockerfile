# Capture avant la copie des sources applicatives : .git ne rejoint jamais nginx.
FROM node:22-alpine AS release-metadata
RUN apk add --no-cache git
WORKDIR /source
COPY . .
ARG FRONTEND_DIR=frontend
ARG PWA_RELEASE_METADATA
ARG PWA_RELEASE_REVISION
ARG PWA_BUILD_ID
RUN if [ "${FRONTEND_DIR}" = frontend ]; then \
        sh docker/frontend/prepare-pwa-history.sh \
        && node frontend/scripts/pwa-release.mjs --output /release.json; \
    else echo null > /release.json; fi

FROM node:22-alpine AS build
ARG FRONTEND_DIR=frontend
ARG CABINET=default
WORKDIR /app/${FRONTEND_DIR}
COPY ${FRONTEND_DIR}/package.json ${FRONTEND_DIR}/package-lock.json ./
RUN npm ci --no-audit --no-fund
# Le portail patient réutilise le logo du cabinet admin si nécessaire.
COPY frontend/cabinet-configs /app/frontend/cabinet-configs
COPY ${FRONTEND_DIR}/ ./
COPY --from=release-metadata /release.json /pwa-release.json
ENV PWA_RELEASE_FILE=/pwa-release.json
# Les URLs des anciens cabinets ne doivent pas court-circuiter le proxy interne.
ENV VITE_DOKPLOY=true
RUN npm run build:cabinet -- --cabinet=${CABINET} --env=prod

FROM nginx:stable-alpine
RUN sed -i 's/^worker_processes[[:space:]].*;/worker_processes 2;/' /etc/nginx/nginx.conf
ARG FRONTEND_DIR=frontend
COPY --from=build /app/${FRONTEND_DIR}/dist /usr/share/nginx/html
COPY docker/frontend/default.conf /etc/nginx/templates/default.conf.template
COPY docker/frontend/proxy.conf /etc/nginx/dentalsoft-proxy.conf
EXPOSE 80

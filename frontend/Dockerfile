FROM node:22-alpine AS release-metadata
RUN apk add --no-cache git
WORKDIR /source
COPY . .
ARG FRONTEND_DIR=frontend
ARG PWA_RELEASE_METADATA
ARG PWA_RELEASE_REVISION
ARG PWA_BUILD_ID
RUN sh docker/frontend/prepare-pwa-history.sh \
    && node "${FRONTEND_DIR}/scripts/pwa-release.mjs" --output /release.json

FROM node:22-alpine AS build
ARG FRONTEND_DIR=frontend
ARG CABINET=default
ARG BUILD_ENV=prod
WORKDIR /app/${FRONTEND_DIR}
COPY ${FRONTEND_DIR}/package.json ${FRONTEND_DIR}/package-lock.json ./
RUN npm ci --no-audit --no-fund
# Le portail patient peut réutiliser le logo du cabinet admin.
COPY frontend/cabinet-configs /app/frontend/cabinet-configs
COPY ${FRONTEND_DIR}/ ./
COPY --from=release-metadata /release.json /pwa-release.json
ENV PWA_RELEASE_FILE=/pwa-release.json
# Sans VITE_DOKPLOY : chaque cabinet garde l'URL publique de son API.
RUN npm run build:cabinet -- --cabinet="${CABINET}" --env="${BUILD_ENV}"

FROM nginx:stable-alpine
RUN sed -i 's/^worker_processes[[:space:]].*;/worker_processes 2;/' /etc/nginx/nginx.conf
ARG FRONTEND_DIR=frontend
COPY --from=build /app/${FRONTEND_DIR}/dist /usr/share/nginx/html
COPY frontend/nginx/default.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD ["wget", "-q", "--spider", "http://127.0.0.1/healthz"]

FROM node:22-alpine AS build
ARG FRONTEND_DIR=frontend
ARG CABINET=default
WORKDIR /app/${FRONTEND_DIR}
COPY ${FRONTEND_DIR}/package.json ${FRONTEND_DIR}/package-lock.json ./
RUN npm ci --no-audit --no-fund
# Le portail patient réutilise le logo du cabinet admin si nécessaire.
COPY frontend/cabinet-configs /app/frontend/cabinet-configs
COPY ${FRONTEND_DIR}/ ./
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

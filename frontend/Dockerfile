# --- Étape 1 : Build de l'application ---
FROM node:22-alpine AS build-stage
WORKDIR /app

ARG CABINET=default
ARG BUILD_ENV=prod

COPY frontend/package*.json ./
RUN npm ci

COPY frontend/ .
RUN npm run build:cabinet -- --cabinet=${CABINET} --env=${BUILD_ENV}

FROM nginx:stable-alpine AS production-stage

COPY --from=build-stage /app/dist /usr/share/nginx/html
COPY --from=build-stage /app/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]

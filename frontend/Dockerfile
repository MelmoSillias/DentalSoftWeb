# --- Étape 1 : Build de l'application ---
FROM node:22-alpine AS build-stage
WORKDIR /app

# On ne copie que les fichiers de dépendances pour mettre en cache le 'npm install'
COPY package*.json ./
RUN npm install

# Copie du code source et build
COPY . .
RUN npm run build

# --- Étape 2 : Serveur final (Nginx) ---
FROM nginx:stable-alpine AS production-stage

# On copie le résultat du build vers le dossier public de Nginx
COPY --from=build-stage /app/dist /usr/share/nginx/html

# Copie de ta config personnalisée (voir section précédente)
COPY ../docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
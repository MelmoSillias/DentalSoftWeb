# Déploiement Dokploy — DentalSoft (Admin + API)

Guide de configuration pour déployer les admins Vue et les APIs Symfony depuis le monorepo `DentalSoftWeb`.
Les Dockerfiles ne sont utilisés qu'en production sur le serveur — le développement local reste inchangé (Symfony CLI, `npm run dev`, MariaDB local).

---

## Applications Dokploy (7 déploiements minimum)

| Application | Root directory | Domaine | Spécificité |
|-------------|----------------|---------|-------------|
| Admin CDOS | `frontend/` | `admin.cabinetdentaireousmanesow.cloud` | Build arg `CABINET=cdos` |
| Admin Mondentiste | `frontend/` | `admin.mondentiste-mali.com` | Build arg `CABINET=mondentiste` |
| API CDOS | `backend-reform/` | `api.cabinetdentaireousmanesow.cloud` | Variables env cdos |
| **Worker CDOS** | `backend-reform/` | *(aucun — interne)* | `WORKER_MODE=1`, même image/env que API CDOS |
| API Mondentiste | `backend-reform/` | `api.mondentiste-mali.com` | Variables env mondentiste |
| **Worker Mondentiste** | `backend-reform/` | *(aucun — interne)* | `WORKER_MODE=1`, même image/env que API Mondentiste |
| **Mercure Hub** | `mercure-prod/` | `mercure.cabinetdentaireousmanesow.cloud` | Hub temps réel partagé (SSE) |

---

## Frontend — configuration Dokploy

### Build

- **Dockerfile path :** `frontend/Dockerfile`
- **Build Path / Root Directory :** `.` (racine du monorepo) **ou** `frontend`
- **Docker Context Path :** `.`
- **Build argument :** `CABINET` = `cdos` ou `mondentiste`
- **Port exposé :** 80

> Le Dockerfile est écrit pour un build depuis la **racine du monorepo** (`frontend/package.json`, etc.).
> Si tu mets **Build Path** = `frontend`, utilise plutôt `Dockerfile` comme chemin et adapte si besoin.

Dokploy → Service → Build :

| Champ | Valeur (monorepo racine) |
|-------|--------------------------|
| Build Path | `.` ou vide |
| Dockerfile Path | `frontend/Dockerfile` |
| Docker Context Path | `.` |

Dokploy → Application → Build → Build Args :

```
CABINET=cdos
```

ou

```
CABINET=mondentiste
```

Aucune variable d'environnement runtime n'est nécessaire : l'URL API, le branding et la config PWA sont intégrés au build via `cabinet-configs/<id>/config.json`.

### Domaine et SSL

Configurer le domaine dans Dokploy ; Let's Encrypt est généré automatiquement.

---

## MariaDB dans Dokploy (recommandé)

Créer la base **dans le même projet** `DentalSoft` que l'API. Les services du même projet communiquent via le réseau interne Docker (`dokploy-network`).

### Étape 1 — Créer MariaDB CDOS

1. Projet **DentalSoft** → **Add Service** → **MariaDB** (pas Application)
2. Remplir :

| Champ | Exemple |
|-------|---------|
| Name | `mariadb-cdos` |
| Database Name | `dentalsoft_cdos` |
| Database User | `dentalsoft` |
| Database Password | mot de passe fort (éviter les caractères problématiques dans l'URL : `@`, `#`, `%`) |
| Root Password | mot de passe root |
| Version | `11` ou `10.11` |

3. **Deploy** et attendre le statut **Running**
4. Noter le **Internal Host** affiché par Dokploy (ex. `mariadb-cdos-a1b2c3` ou le nom `appName`)

> Ne pas exposer MariaDB sur Internet (pas de port externe) sauf pour import temporaire.

### Étape 2 — Importer les données existantes

Depuis le serveur Ubuntu, dumper l'ancienne base :

```bash
mysqldump -u root -p <ancienne_db_cdos> > /tmp/cdos-backup.sql
```

Importer dans MariaDB Dokploy (remplacer le host par l'internal host) :

```bash
# Depuis un conteneur temporaire sur le même réseau, ou via l'outil Import de Dokploy si disponible
docker exec -i <container_mariadb_cdos> mariadb -u dentalsoft -p<password> dentalsoft_cdos < /tmp/cdos-backup.sql
```

Ou via phpMyAdmin / Adminer si tu déploies un service d'admin temporaire.

### Étape 3 — Mettre à jour `DATABASE_URL` sur `api-cdos`

Dans **Environment** du service `api-cdos` :

```env
DATABASE_URL=mysql://dentalsoft:TON_MOT_DE_PASSE@mariadb-cdos-a1b2c3:3306/dentalsoft_cdos?charset=utf8mb4
```

Remplace `mariadb-cdos-a1b2c3` par le **Internal Host** exact affiché dans Dokploy.

**Règles importantes :**
- Host = **Internal Host** Dokploy, **jamais** `localhost` ni `127.0.0.1`
- Port = `3306`
- Si le mot de passe contient des caractères spéciaux, les encoder en URL (`@` → `%40`, `#` → `%23`)

### Étape 4 — Redeploy l'API

1. **Save** les variables
2. **Redeploy** `api-cdos` (et `worker-cdos` avec les mêmes variables)
3. Tester : `GET /api/health` puis login

### Mondentiste — 2e base (plus tard)

Même procédure : **Add Service** → **MariaDB** → `mariadb-mondentiste`, puis `DATABASE_URL` sur `api-mondentiste` avec son internal host.

Tu peux aussi utiliser **une seule** instance MariaDB avec **deux bases** (`dentalsoft_cdos` + `dentalsoft_mondentiste`) — une seule instance à maintenir.

---

## Mercure Hub — déploiement Dokploy (obligatoire pour le temps réel)

Le backend **publie** sur Mercure ; le navigateur **s'abonne** via SSE. Sans ce service, les notifications et le Focus temps réel ne fonctionnent pas (aucune erreur bloquante au login, mais pas de push).

### Étape 1 — Créer le service Mercure

1. Projet **DentalSoft** → **Add Service** → **Application** (Docker)
2. Configuration build :

| Champ | Valeur |
|-------|--------|
| **Build Path** | `mercure-prod` |
| **Dockerfile Path** | `Dockerfile` |
| **Docker Context Path** | `mercure-prod` |
| Port exposé | `80` |
| Domaine | `mercure.cabinetdentaireousmanesow.cloud` (HTTPS Let's Encrypt) |

> Le Dockerfile (`mercure-prod/Dockerfile`) part de `dunglas/mercure:v0.16.2` et injecte les directives CORS via `MERCURE_CORS_ORIGINS` (équivalent à `MERCURE_EXTRA_DIRECTIVES` de l'exemple officiel).

### Étape 2 — Variables d'environnement du hub Mercure

Générer un secret fort (32+ caractères) et **le réutiliser identiquement** sur les 2 APIs :

```env
SERVER_NAME=:80
MERCURE_PUBLISHER_JWT_KEY=<meme-secret-que-MERCURE_JWT_SECRET-api>
MERCURE_SUBSCRIBER_JWT_KEY=<meme-secret-que-MERCURE_JWT_SECRET-api>
MERCURE_CORS_ORIGINS=https://admin.cabinetdentaireousmanesow.cloud https://admin.mondentiste-mali.com
TRUSTED_PROXIES=private_ranges
```

**Équivalent docker-compose officiel dunglas/mercure** (si vous n'utilisez pas `mercure-prod/entrypoint.sh`) :

```env
MERCURE_EXTRA_DIRECTIVES=cors_origins https://admin.cabinetdentaireousmanesow.cloud
cors_origins https://admin.mondentiste-mali.com
```

**Règles critiques :**
- `MERCURE_CORS_ORIGINS` : origines **sans slash final**
- Le secret JWT doit être **strictement identique** entre hub Mercure et chaque API (`MERCURE_JWT_SECRET`)
- Ne pas exposer Mercure sans HTTPS en prod (le admin est en HTTPS → mixed content bloqué)

### Étape 3 — Variables Mercure sur chaque API

Remplacer les placeholders par les valeurs réelles :

```env
# Publication interne (réseau Docker Dokploy — PAS localhost, PAS l'URL publique)
MERCURE_URL=http://<internal-host-mercure>:80/.well-known/mercure

# URL lue par le navigateur (HTTPS, domaine public du hub)
MERCURE_PUBLIC_URL=https://mercure.cabinetdentaireousmanesow.cloud/.well-known/mercure

MERCURE_JWT_SECRET=<meme-secret-que-MERCURE_PUBLISHER_JWT_KEY>
MERCURE_TOPIC_NAMESPACE=dentalsoft-cdos
```

Pour Mondentiste, seul `MERCURE_TOPIC_NAMESPACE` change :

```env
MERCURE_TOPIC_NAMESPACE=dentalsoft-mondentiste
```

`MERCURE_URL`, `MERCURE_PUBLIC_URL` et `MERCURE_JWT_SECRET` restent identiques si un seul hub est partagé.

### Étape 4 — Vérification après deploy

1. **Hub accessible** : `curl -I https://mercure.cabinetdentaireousmanesow.cloud/.well-known/mercure` → réponse HTTP (400 sans topic = hub OK)
2. **API → hub** : `GET https://api.cabinetdentaireousmanesow.cloud/api/health/mercure`
   - `status: "ok"` → l'API atteint le hub en interne
   - `status: "unreachable"` → `MERCURE_URL` incorrect (souvent `localhost` ou mauvais internal host)
   - `status: "warning"` → `MERCURE_PUBLIC_URL` n'est pas en HTTPS
3. **Login admin** : la réponse `/api/me` ou login doit contenir `mercure.publicUrl`, `mercure.topic`, `mercure.token`
4. **Navigateur** (F12 → Réseau) : requête SSE vers `mercure.../.well-known/mercure?topic=...` en statut **200** et type `text/event-stream`
5. **CORS** (depuis votre machine) :

```bash
curl -sI -X OPTIONS "https://mercure.cabinetdentaireousmanesow.cloud/.well-known/mercure" \
  -H "Origin: https://admin.cabinetdentaireousmanesow.cloud" \
  -H "Access-Control-Request-Method: GET" \
  -H "Access-Control-Request-Headers: authorization"
```

→ doit contenir `Access-Control-Allow-Origin: https://admin.cabinetdentaireousmanesow.cloud`

### Dépannage fréquent Dokploy

| Symptôme | Cause probable | Correction |
|----------|----------------|------------|
| CORS dans la console | Origines admin absentes du hub | Ajouter les 2 domaines admin dans `MERCURE_CORS_ORIGINS` sur le service Mercure |
| `Connection failed` / SSE coupée | Proxy Traefik bufferise SSE | Sur le domaine Mercure : désactiver le buffering (header `X-Accel-Buffering: no`) |
| `401` sur subscribe | JWT secret différent API ↔ hub | Aligner `MERCURE_JWT_SECRET` et `MERCURE_PUBLISHER/SUBSCRIBER_JWT_KEY` |
| Publish OK, subscribe KO | `MERCURE_PUBLIC_URL` ≠ domaine réel du hub | Utiliser exactement l'URL HTTPS du domaine Mercure Dokploy |
| `/api/health/mercure` unreachable | `MERCURE_URL` pointe vers localhost | Utiliser l'**Internal Host** Dokploy du service Mercure (`http://mercure-xxx:80/...`) |
| Notifications silencieuses | `notificationsEnabled: false` sur l'utilisateur | Activer dans le profil utilisateur |

### Proxy SSE (Traefik / Dokploy)

Si la connexion SSE se coupe après quelques secondes, ajouter sur le routeur du domaine Mercure (labels ou interface avancée Dokploy) :

```
X-Accel-Buffering: no
Cache-Control: no-cache
```

---

## Backend — variables d'environnement

Définir **uniquement dans Dokploy** (jamais commitées). `.env.local` reste sur la machine de développement.

### API CDOS (`api.cabinetdentaireousmanesow.cloud`)

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<secret-unique-cdos>

DATABASE_URL=mysql://dentalsoft:<password>@<internal-host-mariadb>:3306/dentalsoft_cdos?charset=utf8mb4

MERCURE_URL=http://<internal-host-mercure-dokploy>:80/.well-known/mercure
MERCURE_PUBLIC_URL=https://mercure.cabinetdentaireousmanesow.cloud/.well-known/mercure
MERCURE_JWT_SECRET=<secret-partage-avec-conteneur-mercure>
MERCURE_TOPIC_NAMESPACE=dentalsoft-cdos

CORS_ALLOW_ORIGIN='^https?://admin\.cabinetdentaireousmanesow\.cloud$'

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=<passphrase-cdos>

MAILER_DSN=<smtp-prod>
MESSENGER_TRANSPORT_DSN=doctrine://default?queue_name=default
RUN_MIGRATIONS=1
```

> `RUN_MIGRATIONS=1` : applique les migrations au démarrage. Mettre `0` une fois la base provisionnée si tu ne veux plus migrate à chaque restart.

### Provisionnement base CDOS (ordre)

1. MariaDB Dokploy vide + `DATABASE_URL` correcte
2. Deploy `api-cdos` → migrations créent le schéma
3. Export données seules depuis l'ancienne base :

```bash
mysqldump -u root -p --no-create-info --skip-triggers --single-transaction \
  <ancienne_db> > /tmp/cdos-data-only.sql
```

4. Import dans MariaDB Dokploy :

```bash
docker exec -i <conteneur_mariadb> mariadb -u dentalsoft -p dentalsoft_cdos < /tmp/cdos-data-only.sql
```

### API Mondentiste (`api.mondentiste-mali.com`)

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<secret-unique-mondentiste>

DATABASE_URL=mysql://dentalsoft:<password>@<internal-host-mariadb>:3306/dentalsoft_mondentiste?charset=utf8mb4

MERCURE_URL=http://<internal-host-mercure-dokploy>:80/.well-known/mercure
MERCURE_PUBLIC_URL=https://mercure.cabinetdentaireousmanesow.cloud/.well-known/mercure
MERCURE_JWT_SECRET=<secret-partage-avec-conteneur-mercure>
MERCURE_TOPIC_NAMESPACE=dentalsoft-mondentiste

CORS_ALLOW_ORIGIN='^https?://admin\.mondentiste-mali\.com$'

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=<passphrase-mondentiste>

MAILER_DSN=<smtp-prod>
MESSENGER_TRANSPORT_DSN=doctrine://default?queue_name=default
```

### Notes importantes

- **DATABASE_URL :** utiliser le **Internal Host** du service MariaDB Dokploy (même projet), port `3306`. Ne jamais utiliser `localhost`.
- **MERCURE_TOPIC_NAMESPACE :** doit être unique par instance si les deux APIs partagent le même hub Mercure.
- **JWT :** les clés sont dans `config/jwt/` du dépôt. À terme, les injecter via secrets Dokploy plutôt que de les garder dans l'image.
- **Migrations :** le dossier `migrations/` est versionné dans Git. Au démarrage, `doctrine:migrations:migrate` s'exécute (`RUN_MIGRATIONS=1` par défaut). Voir [migrations/README.md](../backend-reform/migrations/README.md).

### Backend — volumes Dokploy

| Volume hôte | Montage conteneur | Instance |
|-------------|-------------------|----------|
| `/srv/data/uploads-cdos` | `/app/public/uploads` | API CDOS |
| `/srv/data/backups-cdos` | `/app/var/backups` | API CDOS |
| `/srv/data/uploads-mondentiste` | `/app/public/uploads` | API Mondentiste |
| `/srv/data/backups-mondentiste` | `/app/var/backups` | API Mondentiste |

### Backend — build Dokploy

| Champ | Valeur |
|-------|--------|
| **Build Path** | `backend-reform` |
| **Dockerfile Path** | `Dockerfile` |
| **Docker Context Path** | `.` |
| Port exposé | `80` |
| Health check | Utiliser le **HEALTHCHECK Docker intégré** (script `/healthcheck.sh`) |

> Contrairement au frontend (build depuis la racine), l'API se build **depuis le dossier `backend-reform/`**.

### Backend — healthcheck recommandé (API + worker)

Le backend embarque un healthcheck Docker natif dans l'image:

- Script: `/healthcheck.sh`
- Dockerfile: `HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=5 CMD ["/healthcheck.sh"]`

Comportement:

- **API (`WORKER_MODE=0`)**:
  - vérifie `http://127.0.0.1/health` (ou `HEALTHCHECK_URL` si défini)
  - exige HTTP `2xx` + payload contenant `"status":"ok"`
- **Worker (`WORKER_MODE=1`)**:
  - ne fait **pas** de check HTTP
  - vérifie la présence du process `messenger:consume` (`pgrep` / scan `/proc`)

Réglage Dokploy recommandé:

- **Ne pas** configurer de healthcheck HTTP (`/health`, `/api/health`) sur le worker — il n'écoute pas HTTP, Dokploy le marquera unhealthy et recrée le conteneur en boucle.
- Désactiver le healthcheck UI Dokploy sur le worker, ou laisser uniquement le HEALTHCHECK de l'image (`/healthcheck.sh`).
- Vérifier que `WORKER_MODE=1` est bien dans les variables d'environnement du service worker.
- Garder un `start period` ≥ 90s pour éviter les faux négatifs pendant `migrations`/`cache:warmup`.

---

## Préparation serveur Ubuntu (`/srv/data/`)

Exécuter sur le serveur **avant** la première bascule DNS.

### 1. Créer les répertoires persistants

```bash
sudo mkdir -p /srv/data/uploads-cdos
sudo mkdir -p /srv/data/uploads-mondentiste
sudo mkdir -p /srv/data/backups-cdos
sudo mkdir -p /srv/data/backups-mondentiste
sudo mkdir -p /srv/data/mercure
sudo chown -R 33:33 /srv/data/uploads-cdos /srv/data/uploads-mondentiste
sudo chown -R 33:33 /srv/data/backups-cdos /srv/data/backups-mondentiste
```

> UID 33 = `www-data`, utilisé par FrankenPHP dans le conteneur.

### 2. Copier les uploads existants depuis Apache

Adapter les chemins selon l'installation actuelle sous `/var/www/` :

```bash
# CDOS — adapter le chemin source
sudo rsync -av /var/www/apicdos/public/uploads/ /srv/data/uploads-cdos/

# Mondentiste — adapter le chemin source
sudo rsync -av /var/www/api.mondentiste/public/uploads/ /srv/data/uploads-mondentiste/
```

### 3. Autoriser MariaDB depuis Docker

MariaDB reste sur Ubuntu (pas dans Dokploy ce soir). Depuis le conteneur, `localhost` pointe vers le conteneur lui-même, pas l'hôte.

**Option A — IP du serveur :**

```env
DATABASE_URL=mysql://user:pass@192.168.x.x:3306/dbname?charset=utf8mb4
```

**Option B — `host.docker.internal` (si supporté par Dokploy) :**

```env
DATABASE_URL=mysql://user:pass@host.docker.internal:3306/dbname?charset=utf8mb4
```

**Côté MariaDB**, autoriser l'accès depuis le réseau Docker :

```sql
CREATE USER 'dentalsoft'@'172.%.%.%' IDENTIFIED BY '<password>';
GRANT ALL PRIVILEGES ON db_cdos.* TO 'dentalsoft'@'172.%.%.%';
GRANT ALL PRIVILEGES ON db_mondentiste.* TO 'dentalsoft'@'172.%.%.%';
FLUSH PRIVILEGES;
```

Vérifier aussi `bind-address` dans `/etc/mysql/mariadb.conf.d/50-server.cnf` (écouter sur `0.0.0.0` ou l'IP du serveur si nécessaire).

### 4. Sauvegardes avant migration

```bash
# Dump bases
mysqldump -u root -p db_cdos > /srv/data/backups-cdos/pre-migration-$(date +%Y%m%d).sql
mysqldump -u root -p db_mondentiste > /srv/data/backups-mondentiste/pre-migration-$(date +%Y%m%d).sql

# Archive uploads
tar -czf /srv/data/backups-cdos/uploads-$(date +%Y%m%d).tar.gz -C /srv/data/uploads-cdos .
tar -czf /srv/data/backups-mondentiste/uploads-$(date +%Y%m%d).tar.gz -C /srv/data/uploads-mondentiste .
```

---

## Workers Messenger (traitement SMS async)

L'API met les SMS en file (`sms_queue`) puis déclenche le traitement via Messenger (`ProcessSmsQueueMessage` → transport `async` Doctrine). **Sans worker, les SMS restent en attente** (y compris les rappels RDV programmés).

Le worker intégré (`WORKER_MODE=1` dans `docker/entrypoint.sh`) fait deux choses en parallèle :

1. **Poll SMS** — toutes les 60 s (configurable), exécute `app:sms:dispatch-queue` pour enfiler un traitement
2. **Consume Messenger** — `messenger:consume async` traite les messages (SMS, emails async, etc.)

### Étape 1 — Créer le worker CDOS

1. Projet **DentalSoft** → **Add Service** → **Application** (Docker)
2. Même build que `api-cdos` :

| Champ | Valeur |
|-------|--------|
| **Name** | `worker-cdos` |
| **Build Path** | `backend-reform` |
| **Dockerfile Path** | `Dockerfile` |
| **Docker Context Path** | `.` |
| Port exposé | *(aucun)* |
| Domaine | *(aucun)* |

3. **Environment** — copier **toutes** les variables de `api-cdos`, puis ajouter :

```env
WORKER_MODE=1
RUN_MIGRATIONS=0
SMS_POLL_INTERVAL=60
SMS_DISPATCH_LIMIT=20
MESSENGER_TIME_LIMIT=3600
MESSENGER_MEMORY_LIMIT=128M
```

> `RUN_MIGRATIONS=0` évite de lancer les migrations en double (déjà faites par l'API au démarrage).

4. **Deploy** — le conteneur doit rester **Running** en permanence (pas de health check HTTP).

### Étape 2 — Worker Mondentiste

Même procédure avec `worker-mondentiste` et les variables env de `api-mondentiste` + `WORKER_MODE=1`.

### Vérification

Depuis le conteneur worker (logs Dokploy ou `docker exec`) :

```bash
# Doit afficher « ProcessSmsQueueMessage dispatché » toutes les ~60 s
php bin/console app:sms:dispatch-queue --limit=5

# Traitement synchrone de test (sans passer par Messenger)
php bin/console app:sms:process-queue --limit=5
```

Côté admin → **Paramètres SMS → File SMS** : les entrées `pending` passent à `sent` après le poll.

### Variables worker optionnelles

| Variable | Défaut | Rôle |
|----------|--------|------|
| `SMS_POLL_INTERVAL` | `60` | Secondes entre deux dispatch de la file SMS |
| `SMS_DISPATCH_LIMIT` | `20` | SMS max traités par cycle |
| `MESSENGER_TIME_LIMIT` | `3600` | Redémarrage auto du consumer après 1 h |
| `MESSENGER_MEMORY_LIMIT` | `128M` | Limite mémoire du consumer |

### Cron optionnel — nettoyage notifications

Dans Dokploy, planifier sur chaque backend :

```bash
php bin/console app:notifications:cleanup
```

Fréquence recommandée : quotidienne (ex. `0 3 * * *`).

---

## Migration domaine par domaine — ordre et rollback

Apache reste actif tant que le nouveau service n'est pas validé. Bascule DNS/proxy uniquement après tests.

### Ordre recommandé

| Étape | Action | Validation avant bascule |
|-------|--------|--------------------------|
| 1 | Installer Dokploy sur Ubuntu | Interface accessible |
| 2 | Déployer Admin CDOS (`CABINET=cdos`) | SPA charge, login vers API Apache actuelle |
| 3 | Déployer Admin Mondentiste (`CABINET=mondentiste`) | Idem |
| 4 | Déployer API CDOS + worker | Checklist backend ci-dessous |
| 5 | Bascule `api.cabinetdentaireousmanesow.cloud` | Rollback = réactiver vhost Apache |
| 6 | Déployer API Mondentiste + worker | Checklist backend |
| 7 | Bascule `api.mondentiste-mali.com` | Rollback = réactiver vhost Apache |
| 8 | Bascule admins (si pas fait aux étapes 2-3) | Rollback = réactiver vhost Apache admin |

### Checklist validation backend (avant chaque bascule API)

- [ ] `GET /api/health` → `{"status":"ok"}`
- [ ] `GET /api/health/mercure` → `status: "ok"`
- [ ] `POST /api/login` → JWT + URL Mercure dans la réponse
- [ ] Upload fichier patient (test 5–50 Mo)
- [ ] Génération PDF / rapport
- [ ] Envoi email test
- [ ] Notification temps réel (Mercure)
- [ ] SMS (worker Messenger actif)

### Checklist validation frontend (avant bascule admin)

- [ ] Page de login s'affiche avec le bon branding cabinet
- [ ] Navigation SPA (refresh sur une route profonde)
- [ ] Connexion et appels API fonctionnels
- [ ] PWA / manifest accessibles (HTTPS requis)

### Rollback rapide par domaine

En cas de problème après bascule :

1. **DNS / proxy :** remettre le vhost Apache sur le domaine concerné (Apache n'a pas été supprimé).
2. **Données :** les uploads restent dans `/srv/data/uploads-*` ; Apache peut être reconfiguré pour les servir si besoin.
3. **Base :** restaurer depuis le dump `pre-migration-*.sql` si une migration a altéré des données.

### Test avant bascule DNS (optionnel)

Tester via fichier `hosts` local ou sous-domaine de test Dokploy avant de pointer le domaine de production.

---

## Développement local (inchangé)

```bash
# Backend
cd backend-reform
symfony serve --port=8010

# Frontend CDOS
cd frontend
npm run dev:cabinet -- --cabinet=cdos
```

Les Dockerfiles ne sont jamais exécutés sur la machine de développement Windows.

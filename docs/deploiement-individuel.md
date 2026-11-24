# Déploiement Dokploy — services individuels DentalSoft

Guide pour déployer séparément l'interface cabinet, le portail patient, l'API Symfony, MariaDB et Mercure, sans passer au [Compose complet](dokploy-env.template.md).
Les Dockerfiles ne sont utilisés qu'en production sur le serveur — le développement local reste inchangé (Symfony CLI, `npm run dev`, MariaDB local).

---

## Services Dokploy

| Service | Contexte Docker | Dockerfile (par rapport au contexte) | Domaine / accès |
|---------|-----------------|------------------------------------|-----------------|
| Interface cabinet | `.` | `frontend/Dockerfile` | Domaine admin du cabinet |
| Portail patient | `.` | `frontend/Dockerfile` (`FRONTEND_DIR=patient-frontend`) | Domaine patient du cabinet |
| API | `api/` | `Dockerfile` | Domaine API du cabinet |
| MariaDB | — | Service MariaDB natif Dokploy | Hôte interne distinct, sans port public |
| Mercure | `mercure-prod/` | `Dockerfile` | Domaine HTTPS du hub (éventuellement partagé) |

Choisir un `CABINET` par frontend, par exemple `cdos` ou `mondentiste`. Chaque cabinet conserve sa base, ses volumes API et ses domaines.

> Les services `worker-*` séparés sont **optionnels** (scaling). Par défaut, FrankenPHP + poll SMS + `messenger:consume` tournent dans le **même** conteneur API (`ENABLE_EMBEDDED_WORKER=1`).

---

## Frontend — configuration Dokploy

### Build

Les deux applications utilisent `frontend/Dockerfile` avec le **contexte à la racine du dépôt**. Elles exposent le port `80` et fournissent un contrôle de santé HTTP sur `/healthz`.

Dokploy → Service → Build :

| Champ | Valeur (monorepo racine) |
|-------|--------------------------|
| Build Path | `.` ou vide |
| Dockerfile Path | `frontend/Dockerfile` |
| Docker Context Path | `.` |

Dans **Build Args**, renseigner `CABINET=cdos` (ou `CABINET=mondentiste`). Pour le portail patient uniquement, ajouter `FRONTEND_DIR=patient-frontend` ; l'interface cabinet garde la valeur par défaut `frontend`.

```sh
docker build -f frontend/Dockerfile --build-arg CABINET=cdos -t dentalsoft-admin-cdos .
docker build -f frontend/Dockerfile --build-arg FRONTEND_DIR=patient-frontend --build-arg CABINET=cdos -t dentalsoft-patient-cdos .
```

Aucune variable runtime nginx n'est nécessaire : le build conserve les URLs d'API publiques de `frontend/cabinet-configs/<id>/config.prod.json` pour l'admin et `patient-frontend/cabinet-configs/<id>/config.json` pour le portail. Ne pas activer `VITE_DOKPLOY` dans ce mode. Ajouter les deux origines HTTPS à `CORS_ALLOW_ORIGIN` côté API.

Le build capture l'historique des releases PWA. Si Dokploy ne fournit pas `.git` ou si le builder ne peut pas compléter un clone superficiel, fournir `PWA_RELEASE_METADATA` et `PWA_RELEASE_REVISION` comme [arguments de build](pwa-required-update.md#git-absent-du-contexte-dokploy-archive-ci).

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
2. **Redeploy** `api-cdos`
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
| **Docker Context Path** | `.` (relatif à `mercure-prod`) |
| Port exposé | `80` |
| Domaine | `mercure.cabinetdentaireousmanesow.cloud` (HTTPS Let's Encrypt) |

Le Dockerfile utilise `dunglas/mercure:v1.1.5` avec la compatibilité de protocole 0.x et un contrôle de disponibilité interne du transport. Conserver les montages `/data` et `/config`. Avant de remplacer un hub `v0.16.2`, sauvegarder ces volumes et vérifier la reprise sur une copie : le nouveau Caddyfile utilise `/data/mercure.db`. Conserver l'ancien volume intact pour le retour arrière si l'ancien chemin ou format diffère.

### Étape 2 — Variables d'environnement du hub Mercure

Générer un secret fort (32+ caractères) et **le réutiliser identiquement** sur les 2 APIs :

```env
SERVER_NAME=:80
MERCURE_PUBLISHER_JWT_KEY=<meme-secret-que-MERCURE_JWT_SECRET-api>
MERCURE_SUBSCRIBER_JWT_KEY=<meme-secret-que-MERCURE_JWT_SECRET-api>
MERCURE_CORS_ORIGINS=https://admin.cabinetdentaireousmanesow.cloud https://admin.mondentiste-mali.com
TRUSTED_PROXIES=private_ranges
```

Ajouter à `MERCURE_CORS_ORIGINS` toute autre origine qui s'abonne au hub. `MERCURE_EXTRA_DIRECTIVES` reste disponible pour les directives supplémentaires ; ne pas y redéclarer `cors_origins` si `MERCURE_CORS_ORIGINS` est déjà défini.

Pour le Compose local `mercure-prod/docker-compose.yml`, fournir les deux clés JWT dans l'environnement de la commande ou via `--env-file`. Le port publié est `127.0.0.1:3000` par défaut (`MERCURE_PORT` permet de changer le port). Sur Dokploy, utiliser le domaine HTTPS plutôt qu'un port hôte public.

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

# Exemple avec patient.<domaine> : adapter au domaine réellement configuré.
CORS_ALLOW_ORIGIN='^https://(admin|patient)\.cabinetdentaireousmanesow\.cloud$'
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

# Exemple avec patient.<domaine> : adapter au domaine réellement configuré.
CORS_ALLOW_ORIGIN='^https://(admin|patient)\.mondentiste-mali\.com$'
JWT_PASSPHRASE=<passphrase-mondentiste>

MAILER_DSN=<smtp-prod>
MESSENGER_TRANSPORT_DSN=doctrine://default?queue_name=default
```

### Notes importantes

- **DATABASE_URL :** utiliser l'hôte interne du service MariaDB Dokploy (même projet), port `3306`, et encoder les caractères spéciaux des identifiants dans l'URL. Ne jamais utiliser `localhost`. Si l'API est derrière un proxy, renseigner `TRUSTED_PROXIES` avec les adresses ou plages réseau des seuls proxys de confiance.
- **MERCURE_TOPIC_NAMESPACE :** doit être unique par instance si les deux APIs partagent le même hub Mercure.
- **JWT :** `JWT_PASSPHRASE` est une variable **runtime**, plus un argument Docker. L'API réutilise les clés de `/app/config/jwt` ou génère une paire au démarrage si le montage est vide. Une paire incomplète ou une passphrase incorrecte empêche le démarrage.
- **Environnement :** aucun fichier `.env*` du dépôt n'est embarqué. Vérifier les valeurs Dokploy avant le premier redéploiement ; conserver notamment `APP_SECRET`, la passphrase et le secret Mercure existants.
- **Migrations :** le dossier `migrations/` est versionné dans Git. Au démarrage, `doctrine:migrations:migrate` s'exécute (`RUN_MIGRATIONS=1` par défaut). Voir [migrations/README.md](../api/migrations/README.md).

### Backend — volumes Dokploy

**Avant le premier redéploiement**, sauvegarder les répertoires du conteneur actuel, y compris ceux qui ne sont pas montés, puis restaurer les données dans les montages. Copier les deux clés JWT existantes dans le nouveau montage avec la **même** passphrase pour préserver les sessions ; n'utiliser un volume vide que pour une installation neuve ou une rotation voulue.

| Données par cabinet | Montage conteneur |
|---------------------|-------------------|
| Clés JWT | `/app/config/jwt` |
| Uploads publics | `/app/public/uploads` |
| Fichiers joints publics | `/app/public/upload_files` |
| Fichiers d'assurances (y compris les logos si un montage hôte masque ceux de l'image) | `/app/public/assurances` |
| Uploads privés | `/app/var/uploads` |
| Sauvegardes | `/app/var/backups` |

Garder les volumes existants, par exemple `/srv/data/uploads-cdos` et `/srv/data/backups-cdos`, et ajouter les montages manquants séparément pour chaque cabinet.

### Backend — build Dokploy

| Champ | Valeur |
|-------|--------|
| **Build Path** | `api` |
| **Dockerfile Path** | `Dockerfile` |
| **Docker Context Path** | `.` |
| Port exposé | `80` |
| Health check | Utiliser le **HEALTHCHECK Docker intégré** (script `/healthcheck.sh`) |

> Contrairement au frontend (build depuis la racine), l'API se build **depuis le dossier `api/`**.

### Backend — healthcheck recommandé

Le backend embarque un healthcheck Docker natif dans l'image:

- Script: `/healthcheck.sh`
- Dockerfile: `HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=5 CMD ["/healthcheck.sh"]`

Comportement:

- **API (défaut, y compris worker embarqué)** :
  - vérifie `http://127.0.0.1/health` (ou `HEALTHCHECK_URL` si défini)
  - exige HTTP `2xx` + payload contenant `"status":"ok"`
  - **ne** dépend **pas** du process Messenger (évite les redémarrages si le consumer se relance)
- **Worker exclusif (`WORKER_MODE=1`, optionnel)** :
  - ne fait **pas** de check HTTP
  - vérifie la présence du process `messenger:consume` (`pgrep` / scan `/proc`)

Réglage Dokploy recommandé:

- Sur l’API : healthcheck HTTP `/health` **ou** s’appuyer sur le HEALTHCHECK de l’image.
- **Ne jamais** mettre `WORKER_MODE=1` sur le service API (sinon plus de FrankenPHP → 502).
- Si tu crées un worker dédié : **pas** de healthcheck HTTP Dokploy sur ce service.
- Garder un `start period` ≥ 90s pour `migrations` / `cache:warmup`.

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

## Worker Messenger embarqué (SMS + async) — recommandé

L'API met les SMS en file (`sms_queue`). Le traitement automatique tourne **dans le même conteneur** que FrankenPHP (pas de second service Dokploy).

Au démarrage ([entrypoint partagé](../api/docker/entrypoint.dokploy.sh)), si `WORKER_MODE` est absent/`0` et `ENABLE_EMBEDDED_WORKER=1` (défaut) :

1. **Poll SMS** — toutes les 60 s : `app:sms:process-queue` (traitement direct des SMS dus)
2. **Consume Messenger** — `messenger:consume async` (mails / autres messages async)
3. **FrankenPHP** — HTTP sur le port 80 (PID principal)

### Configuration API (aucun service worker à créer)

Sur `api-cdos` / `api-mondentiste` :

```env
# Obligatoire : ne PAS mettre WORKER_MODE=1 (sinon plus d'HTTP → 502)
# WORKER_MODE=0

# Défaut = 1 (peut être omis). Mettre 0 uniquement pour désactiver les boucles embarquées.
ENABLE_EMBEDDED_WORKER=1

SMS_POLL_INTERVAL=60
SMS_DISPATCH_LIMIT=20
MESSENGER_TIME_LIMIT=3600
MESSENGER_MEMORY_LIMIT=128M
```

**Redeploy** l’API. Dans les logs tu dois voir :

```text
[entrypoint] ENABLE_EMBEDDED_WORKER=1 — process-queue SMS + messenger:consume en arrière-plan
```

puis le démarrage FrankenPHP / Caddy.

### Vérification

```bash
# Depuis le conteneur API
php bin/console app:sms:process-queue --limit=5 --env=prod
```

Admin → **Paramètres SMS → File SMS** : les `pending` dus passent à `sent` après le poll (~60 s).

Pas besoin de Schedule Dokploy pour les SMS si le worker embarqué tourne.

### Variables optionnelles

| Variable | Défaut | Rôle |
|----------|--------|------|
| `ENABLE_EMBEDDED_WORKER` | `1` | Worker SMS + Messenger dans le conteneur API |
| `SMS_POLL_INTERVAL` | `60` | Secondes entre deux traitements de la file SMS |
| `SMS_DISPATCH_LIMIT` | `20` | SMS max traités par cycle |
| `MESSENGER_TIME_LIMIT` | `3600` | Redémarrage auto du consumer après 1 h |
| `MESSENGER_MEMORY_LIMIT` | `128M` | Limite mémoire du consumer |

### Worker dédié (optionnel — scaling)

Uniquement si la charge le justifie : second service avec la **même image**, env de l’API + :

```env
WORKER_MODE=1
RUN_MIGRATIONS=0
ENABLE_EMBEDDED_WORKER=0
```

Dans ce cas, mettre `ENABLE_EMBEDDED_WORKER=0` sur l’API pour éviter un double traitement SMS. Démarrer l'API en premier et réutiliser son montage JWT sur le worker, sans générer une seconde paire. Pas de domaine / pas de healthcheck HTTP sur le worker.

### Cron optionnel — nettoyage notifications

Dans Dokploy Schedule sur chaque API :

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
| 4 | Déployer API CDOS (worker embarqué) | Checklist backend ci-dessous |
| 5 | Bascule `api.cabinetdentaireousmanesow.cloud` | Rollback = réactiver vhost Apache |
| 6 | Déployer API Mondentiste (worker embarqué) | Checklist backend |
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
- [ ] SMS (log `ENABLE_EMBEDDED_WORKER=1` + file SMS qui avance)

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
cd api
symfony serve --port=8010

# Frontend CDOS
cd frontend
npm run dev:cabinet -- --cabinet=cdos
```

Les Dockerfiles ne sont jamais exécutés sur la machine de développement Windows.

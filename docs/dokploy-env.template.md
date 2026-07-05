# Déploiement Dokploy — DentalSoft (Admin + API)

Guide de configuration pour déployer les admins Vue et les APIs Symfony depuis le monorepo `DentalSoftWeb`.
Les Dockerfiles ne sont utilisés qu'en production sur le serveur — le développement local reste inchangé (Symfony CLI, `npm run dev`, MariaDB local).

---

## Applications Dokploy (4 déploiements, 2 images)

| Application | Root directory | Domaine | Spécificité |
|-------------|----------------|---------|-------------|
| Admin CDOS | `frontend/` | `admin.cabinetdentaireousmanesow.cloud` | Build arg `CABINET=cdos` |
| Admin Mondentiste | `frontend/` | `admin.mondentiste-mali.com` | Build arg `CABINET=mondentiste` |
| API CDOS | `backend-reform/` | `api.cabinetdentaireousmanesow.cloud` | Variables env cdos |
| API Mondentiste | `backend-reform/` | `api.mondentiste-mali.com` | Variables env mondentiste |

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

## Backend — variables d'environnement

Définir **uniquement dans Dokploy** (jamais commitées). `.env.local` reste sur la machine de développement.

### API CDOS (`api.cabinetdentaireousmanesow.cloud`)

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<secret-unique-cdos>

DATABASE_URL=mysql://<user>:<password>@<IP_SERVEUR_UBUNTU>:3306/<db_cdos>?charset=utf8mb4

MERCURE_URL=http://<mercure-host>:80/.well-known/mercure
MERCURE_PUBLIC_URL=https://<mercure-public-domain>/.well-known/mercure
MERCURE_JWT_SECRET=<secret-partage-avec-conteneur-mercure>
MERCURE_TOPIC_NAMESPACE=dentalsoft-cdos

CORS_ALLOW_ORIGIN='^https?://admin\.cabinetdentaireousmanesow\.cloud$'

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=<passphrase-cdos>

MAILER_DSN=<smtp-prod>
MESSENGER_TRANSPORT_DSN=doctrine://default?queue_name=default
```

### API Mondentiste (`api.mondentiste-mali.com`)

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<secret-unique-mondentiste>

DATABASE_URL=mysql://<user>:<password>@<IP_SERVEUR_UBUNTU>:3306/<db_mondentiste>?charset=utf8mb4

MERCURE_URL=http://<mercure-host>:80/.well-known/mercure
MERCURE_PUBLIC_URL=https://<mercure-public-domain>/.well-known/mercure
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

- **DATABASE_URL :** ne pas utiliser `localhost` depuis le conteneur Docker. Utiliser l'IP du serveur Ubuntu ou `host.docker.internal`.
- **MERCURE_TOPIC_NAMESPACE :** doit être unique par instance si les deux APIs partagent le même hub Mercure.
- **JWT :** les clés sont dans `config/jwt/` du dépôt. À terme, les injecter via secrets Dokploy plutôt que de les garder dans l'image.
- **Migrations :** le dossier `migrations/` est gitignoré. Sur une base existante, ne pas lancer `doctrine:migrations:migrate` sans vérification préalable.

### Backend — volumes Dokploy

| Volume hôte | Montage conteneur | Instance |
|-------------|-------------------|----------|
| `/srv/data/uploads-cdos` | `/app/public/uploads` | API CDOS |
| `/srv/data/backups-cdos` | `/app/var/backups` | API CDOS |
| `/srv/data/uploads-mondentiste` | `/app/public/uploads` | API Mondentiste |
| `/srv/data/backups-mondentiste` | `/app/var/backups` | API Mondentiste |

### Backend — build Dokploy

- **Dockerfile path :** `backend-reform/Dockerfile`
- **Port exposé :** 80
- **Health check :** `GET /api/health` → `{"status":"ok"}`

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

## Workers Messenger (SMS async)

L'API utilise un transport Doctrine async (`config/packages/messenger.yaml`) pour la file SMS. Sans worker, les SMS ne seront pas traités.

### Configuration Dokploy — 2 services worker supplémentaires

Créer un service Dokploy **par backend**, avec la **même image** que l'API mais une commande différente :

| Service | Image source | Commande |
|---------|--------------|----------|
| Worker CDOS | Même build que API CDOS | `php bin/console messenger:consume async --time-limit=3600 -vv` |
| Worker Mondentiste | Même build que API Mondentiste | `php bin/console messenger:consume async --time-limit=3600 -vv` |

**Variables d'environnement :** identiques à l'API correspondante (`DATABASE_URL`, `APP_SECRET`, etc.).

**Pas de port exposé** — service interne uniquement.

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

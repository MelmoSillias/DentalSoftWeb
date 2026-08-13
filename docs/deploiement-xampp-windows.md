# Déploiement XAMPP Windows (LAN sans Internet)

Guide pour installer DentalSoft sur **un PC Windows** avec **XAMPP**, accessible depuis les postes du cabinet via des **noms locaux** (`hosts`), **sans port 8010** et **sans Internet public**.

DentalSoft fonctionne en **HTTP (port 80)** ou **HTTPS (port 443)**. Choisissez **un seul mode** pour tout le déploiement (Apache, CORS, build frontend).

| Mode | Avantages | Inconvénients |
|------|-----------|---------------|
| **HTTP** (recommandé pour démarrer) | Aucun certificat, aucun avertissement navigateur | Pas de chiffrement LAN ; PWA « installer l'app » limitée |
| **HTTPS** | Chiffrement LAN, PWA plus fiable | Certificat à générer / approuver sur chaque poste |

> **Règle importante** : le protocole du build frontend (`http://` ou `https://` dans `viteApiPrefix`) doit correspondre à Apache et à `CORS_ALLOW_ORIGIN`.

---

## Architecture cible (exemple cabinet MAF)

| Nom local | Rôle |
|-----------|------|
| `dentalsoft.local` | Frontend Vue (build `dist/`) |
| `apidentalsoft.local` | API Symfony + fichiers uploadés |

Les noms exacts viennent du fichier `frontend/cabinet-configs/<cabinet>/config.xampp-lan*.json`. Pour le cabinet **default**, l'API est `api.dentalsoft.local`.

### HTTP (port 80)

| URL | DocumentRoot |
|-----|--------------|
| `http://dentalsoft.local` | `C:\xampp\htdocs\dentalsoft\` |
| `http://apidentalsoft.local` | `C:\xampp\htdocs\dentalsoft-api\public\` |

Build frontend :

```json
"viteApiPrefix": "http://apidentalsoft.local/api",
"viteFilePrefix": "http://apidentalsoft.local"
```

```powershell
npm run build:cabinet -- --cabinet=maf --env=xampp-lan-http
```

### HTTPS (port 443)

| URL | DocumentRoot |
|-----|--------------|
| `https://dentalsoft.local` | `C:\xampp\htdocs\dentalsoft\` |
| `https://apidentalsoft.local` | `C:\xampp\htdocs\dentalsoft-api\public\` |
| *(optionnel)* `http://…` port 80 | Redirection vers HTTPS |

Build frontend :

```json
"viteApiPrefix": "https://apidentalsoft.local/api",
"viteFilePrefix": "https://apidentalsoft.local"
```

```powershell
npm run build:cabinet -- --cabinet=maf --env=xampp-lan
```

MySQL reste sur **3306 localhost uniquement** (non exposé au LAN).

```text
Poste réception ──► dentalsoft.local ──► htdocs/dentalsoft/
                 └──► apidentalsoft.local ──► htdocs/dentalsoft-api/public/
```

---

## Phase 0 — Préparation (machine avec Internet, une fois)

```powershell
cd backend-reform
composer install --no-dev --optimize-autoloader
php bin/console lexik:jwt:generate-keypair

cd ..\frontend
npm ci
# Choisir HTTP ou HTTPS (voir ci-dessus)
npm run build:cabinet -- --cabinet=maf --env=xampp-lan-http
# ou : npm run build:cabinet -- --cabinet=maf --env=xampp-lan
```

Copier sur le PC cabinet :

```text
DentalSoftDeploy/
  backend-reform/          → sera déployé dans htdocs/dentalsoft-api/
  frontend/dist/           → contenu de htdocs/dentalsoft/
  docs/
  scripts/xampp/
```

---

## Phase 1 — XAMPP

1. Installer XAMPP 8.2+ dans `C:\xampp`
2. Dans `C:\xampp\php\php.ini`, activer : `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `mysqli`, `pdo_mysql`, `openssl`, `sodium`, `zip`
3. Dans `C:\xampp\apache\conf\httpd.conf`, vérifier :

```apache
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
Include conf/extra/httpd-vhosts.conf
```

Pour **HTTPS uniquement**, ajouter aussi :

```apache
LoadModule ssl_module modules/mod_ssl.so
Include conf/extra/httpd-ssl.conf
```

4. Redémarrer Apache depuis le panneau XAMPP

---

## Phase 2 — Arborescence dans `htdocs`

```text
C:\xampp\htdocs\
  dentalsoft\                 ← copier le contenu de frontend/dist/
  dentalsoft-api\             ← copier tout backend-reform/
    public\                   ← point d'entrée Apache API
    var\
    config\
    vendor\
    ...
C:\DentalSoft\backups\        ← sauvegardes (hors htdocs)
```

Droits d'écriture Windows sur :

- `C:\xampp\htdocs\dentalsoft-api\var\`
- `C:\xampp\htdocs\dentalsoft-api\public\upload_files\`

---

## Phase 3 — DNS local (`hosts`)

### PC serveur (XAMPP)

Éditer **en administrateur** : `C:\Windows\System32\drivers\etc\hosts`

Exemple **MAF** :

```text
127.0.0.1       dentalsoft.local
127.0.0.1       apidentalsoft.local
```

Exemple **default** :

```text
127.0.0.1       dentalsoft.local
127.0.0.1       api.dentalsoft.local
```

### Postes du LAN (réception, médecin, etc.)

Remplacer `192.168.1.50` par l'**IP fixe** du PC XAMPP :

```text
192.168.1.50    dentalsoft.local
192.168.1.50    apidentalsoft.local
```

> Les postes n'ont pas besoin de connaître l'IP dans le build frontend : seuls les noms `*.local` suffisent.

Modèle : `scripts/xampp/hosts-lan.example.txt`

---

## Phase 4 — Virtual hosts Apache

Fichier commun SPA : `C:\xampp\htdocs\dentalsoft\.htaccess`

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>
```

Éditer `C:\xampp\apache\conf\extra\httpd-vhosts.conf` :

### Option A — HTTP uniquement (port 80)

Aucun certificat requis. Ne pas activer `mod_ssl` si vous n'utilisez que HTTP.

```apache
# Frontend — http://dentalsoft.local
<VirtualHost *:80>
    ServerName dentalsoft.local
    DocumentRoot "C:/xampp/htdocs/dentalsoft"

    <Directory "C:/xampp/htdocs/dentalsoft">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        FallbackResource /index.html
    </Directory>
</VirtualHost>

# API Symfony — http://apidentalsoft.local
<VirtualHost *:80>
    ServerName apidentalsoft.local
    DocumentRoot "C:/xampp/htdocs/dentalsoft-api/public"

    <Directory "C:/xampp/htdocs/dentalsoft-api/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <Location />
        Require ip 127.0.0.1 192.168.0.0/16 10.0.0.0/8
    </Location>
</VirtualHost>
```

Pare-feu : ouvrir **TCP 80** entrant (profil Privé).

### Option B — HTTPS (port 443)

#### B.1 — Certificat

**mkcert (recommandé)** — sur une machine avec Internet :

```powershell
mkcert -install
mkcert dentalsoft.local apidentalsoft.local
```

Copier vers XAMPP :

```text
C:\xampp\apache\conf\ssl.crt\dentalsoft.local.pem
C:\xampp\apache\conf\ssl.key\dentalsoft.local-key.pem
```

Installer la CA mkcert sur chaque poste client (`mkcert -install`).

**OpenSSL offline** :

```powershell
scripts\xampp\generate-ssl-openssl.bat
```

(Adapter les SAN dans le script si vous utilisez `api.dentalsoft.local` au lieu de `apidentalsoft.local`.)

#### B.2 — Virtual hosts HTTPS

```apache
# Redirection HTTP → HTTPS (optionnel)
<VirtualHost *:80>
    ServerName dentalsoft.local
    ServerAlias apidentalsoft.local
    Redirect permanent / https://%{SERVER_NAME}%{REQUEST_URI}
</VirtualHost>

# Frontend — https://dentalsoft.local
<VirtualHost *:443>
    ServerName dentalsoft.local
    DocumentRoot "C:/xampp/htdocs/dentalsoft"

    SSLEngine on
    SSLCertificateFile "conf/ssl.crt/dentalsoft.local.pem"
    SSLCertificateKeyFile "conf/ssl.key/dentalsoft.local-key.pem"

    <Directory "C:/xampp/htdocs/dentalsoft">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        FallbackResource /index.html
    </Directory>
</VirtualHost>

# API Symfony — https://apidentalsoft.local
<VirtualHost *:443>
    ServerName apidentalsoft.local
    DocumentRoot "C:/xampp/htdocs/dentalsoft-api/public"

    SSLEngine on
    SSLCertificateFile "conf/ssl.crt/dentalsoft.local.pem"
    SSLCertificateKeyFile "conf/ssl.key/dentalsoft.local-key.pem"

    <Directory "C:/xampp/htdocs/dentalsoft-api/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <Location />
        Require ip 127.0.0.1 192.168.0.0/16 10.0.0.0/8
    </Location>
</VirtualHost>
```

Dans `httpd-ssl.conf`, vérifier que `Listen 443` est actif.

Pare-feu : ouvrir **TCP 443** entrant (et **80** si redirect).

Redémarrer Apache.

---

## Phase 5 — MySQL

```sql
CREATE DATABASE dentalsoft CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dentalsoft'@'localhost' IDENTIFIED BY 'MotDePasseFort!';
GRANT ALL ON dentalsoft.* TO 'dentalsoft'@'localhost';
FLUSH PRIVILEGES;
```

```powershell
cd C:\xampp\htdocs\dentalsoft-api
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

---

## Phase 6 — Backend `.env.local`

Créer `C:\xampp\htdocs\dentalsoft-api\.env.local` :

### HTTP

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<secret-unique-32-caracteres>
APP_INTERNET_FEATURES_ENABLED=0
ENABLE_EMBEDDED_WORKER=0

DATABASE_URL="mysql://dentalsoft:MotDePasseFort!@127.0.0.1:3306/dentalsoft?charset=utf8mb4"

CORS_ALLOW_ORIGIN='^http://(dentalsoft\.local|apidentalsoft\.local)$'

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=<passphrase-jwt>
```

### HTTPS

Même contenu, avec CORS HTTPS :

```env
CORS_ALLOW_ORIGIN='^https://(dentalsoft\.local|apidentalsoft\.local)$'
```

### HTTP + HTTPS (les deux en parallèle, rare)

```env
CORS_ALLOW_ORIGIN='^https?://(dentalsoft\.local|apidentalsoft\.local)$'
```

```powershell
cd C:\xampp\htdocs\dentalsoft-api
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod
```

---

## Phase 7 — Build frontend (cabinet)

| Mode | Fichier config MAF | Commande |
|------|-------------------|----------|
| HTTP | `config.xampp-lan-http.json` | `npm run build:cabinet -- --cabinet=maf --env=xampp-lan-http` |
| HTTPS | `config.xampp-lan.json` | `npm run build:cabinet -- --cabinet=maf --env=xampp-lan` |

```powershell
cd frontend
npm run build:cabinet -- --cabinet=maf --env=xampp-lan-http
xcopy /E /I /Y dist C:\xampp\htdocs\dentalsoft
```

---

## Phase 8 — Partage LAN et sécurité

1. **IP fixe** sur le PC XAMPP (ex. `192.168.1.50`)
2. **Pare-feu Windows** (profil **Privé** uniquement) :
   - TCP **80** (HTTP) ou **443** (HTTPS) entrant
   - **Ne pas** ouvrir 3306
3. **Accès utilisateurs** : `http://dentalsoft.local/` ou `https://dentalsoft.local/` selon le mode
4. **Approbation appareils** : activer la validation des postes dans Paramètres → Appareils
5. **Sauvegardes** : `scripts\xampp\backup.bat`

---

## Vérification

### HTTP

| Test | URL | Attendu |
|------|-----|---------|
| Frontend | `http://dentalsoft.local/` | Page de login |
| API | `http://apidentalsoft.local/api/health` | `{"status":"ok"}` |
| Features | `http://apidentalsoft.local/api/health/features` | `internetFeaturesEnabled: false` |
| LAN | Même URL depuis un autre PC | OK si `hosts` configuré |
| CORS | Login depuis le frontend | Pas d'erreur console |

### HTTPS

| Test | URL | Attendu |
|------|-----|---------|
| Frontend | `https://dentalsoft.local/` | Page de login |
| API | `https://apidentalsoft.local/api/health` | `{"status":"ok"}` |
| Redirect | `http://dentalsoft.local/` | Redirige vers HTTPS (si vhost redirect actif) |
| Certificat | Navigateur | Pas d'avertissement si mkcert installé |

---

## Scripts utiles

| Script | Rôle |
|--------|------|
| `scripts/xampp/warmup-cache.bat` | Vide / réchauffe le cache Symfony |
| `scripts/xampp/backup.bat` | Dump MySQL + copie `upload_files` |
| `scripts/xampp/generate-ssl-openssl.bat` | Certificat auto-signé (HTTPS uniquement) |
| `scripts/xampp/hosts-lan.example.txt` | Modèle entrées `hosts` |

---

## Dépannage

| Symptôme | Cause probable | Solution |
|----------|----------------|----------|
| CORS bloqué | `CORS_ALLOW_ORIGIN` ne correspond pas au protocole | HTTP → `^http://…` ; HTTPS → `^https://…` |
| Mixed content | Build en `https://` mais site servi en HTTP (ou l'inverse) | Rebuild avec le bon `--env` |
| `NET::ERR_CERT_AUTHORITY_INVALID` | Certificat auto-signé (HTTPS) | mkcert `-install` ou importer le certificat |
| API 404 | `mod_rewrite` off | `AllowOverride All` sur `public/` |
| Frontend OK, API KO sur LAN | `hosts` manquant sur le poste client | Ajouter les 2 lignes `hosts` |
| SMS visible | Mode local non activé | `APP_INTERNET_FEATURES_ENABLED=0` + `internetFeaturesEnabled: false` |

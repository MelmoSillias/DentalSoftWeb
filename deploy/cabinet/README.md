# Déploiement all-in-one par cabinet

Un seul déploiement Dokploy (Docker Compose) par cabinet :

| Service | Contenu |
|---------|---------|
| `app` | Admin Vue + API FrankenPHP + worker SMS/Messenger embarqué |
| `db` | MariaDB 11 |

Ce n’est **pas** un seul process (MariaDB reste un conteneur voisin). C’est **un seul stack Compose** = un seul déploiement Dokploy, une seule image app à builder.

## Architecture

```text
https://cabinet.example.com
        │
        ▼
   [app :80 Caddy]
     ├─ /api/*, /health  → Symfony / FrankenPHP
     ├─ /uploads/*       → fichiers
     └─ /*               → SPA admin (/srv/admin)
        │
        └── DATABASE_URL → [db :3306]
```

Le front est compilé avec `VITE_SAME_ORIGIN=1` → appels relatifs `/api` (pas de sous-domaine API séparé).

## Dokploy — création

1. Projet → **Add Service** → **Docker Compose**
2. Repo monorepo `DentalSoftWeb`
3. **Compose file** : `deploy/cabinet/docker-compose.yml`
4. Variables d’environnement (voir `.env.example`) — obligatoire :
   - `CABINET` = `cdos` ou `mondentiste`
   - `MYSQL_*`, `APP_SECRET`, `JWT_PASSPHRASE`
5. Domaine public sur le service / port **80** de `app`
6. Deploy

### Exemple CDOS

```env
CABINET=cdos
MYSQL_DATABASE=dentalsoft_cdos
MERCURE_TOPIC_NAMESPACE=dentalsoft-cdos
```

### Exemple Mondentiste

Même compose, autre application Dokploy (ou autre env) :

```env
CABINET=mondentiste
MYSQL_DATABASE=dentalsoft_mondentiste
MERCURE_TOPIC_NAMESPACE=dentalsoft-mondentiste
```

## Vérifications

- `GET https://<domaine>/health` → `{"status":"ok"}`
- `GET https://<domaine>/` → page login admin
- `POST https://<domaine>/api/login` → JWT
- Logs app : `ENABLE_EMBEDDED_WORKER=1` + poll SMS

## Limites

- Mercure reste un hub **partagé** externe (recommandé).
- Ressources : 1 image app + 1 MariaDB par cabinet (moins que admin + api + db + worker séparés).
- Les Dockerfiles historiques `frontend/Dockerfile` et `backend-reform/Dockerfile` restent valides pour le mode multi-services.

## Test local (optionnel)

```bash
cd deploy/cabinet
cp .env.example .env
# éditer .env
docker compose up --build
```

Ouvrir http://localhost/

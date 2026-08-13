# Déploiement local sans Internet

Ce guide décrit l'installation de DentalSoft staff en **réseau local (LAN)** sans accès Internet public.

## Principe

| Couche | Flag | Effet |
|--------|------|-------|
| Backend | `APP_INTERNET_FEATURES_ENABLED=0` | Bloque SMS et appels sortants côté API |
| Frontend (build) | `"internetFeaturesEnabled": false` | Masque l'UI SMS et services externes |

Le backend est la **source de vérité** pour la sécurité. Le frontend lit aussi `/api/settings/general/public` après connexion.

## Fonctionnalités bloquées

- Module SMS (envoi, file, test, solde provider)
- SMS automatisés (création patient, RDV, reçus caisse)
- Paramètres API SMS
- Rapport d'erreurs client (prod)

## Fonctionnalités conservées

Patients, consultations, caisse, agenda (sans SMS), rapports, administration, impressions, uploads, auth, export BDD.

Les QR codes portail sont générés **localement** (plus de dépendance à `api.qrserver.com`).

## Développement local

Backend `.env.local` :

```env
APP_INTERNET_FEATURES_ENABLED=0
ENABLE_EMBEDDED_WORKER=0
```

Frontend :

```bash
cd frontend
npm run dev:local-offline
```

## Build production LAN (XAMPP)

Configurer les noms locaux dans `hosts`, puis choisir **HTTP** ou **HTTPS** :

| Mode | Commande build MAF |
|------|-------------------|
| HTTP (sans certificat) | `npm run build:cabinet -- --cabinet=maf --env=xampp-lan-http` |
| HTTPS | `npm run build:cabinet -- --cabinet=maf --env=xampp-lan` |

Déployer `dist/` dans `C:\xampp\htdocs\dentalsoft\`.

Voir [deploiement-xampp-windows.md](./deploiement-xampp-windows.md) pour virtual hosts, CORS et `.env.local`.

## Vérification

- `http://apidentalsoft.local/api/health/features` (HTTP) ou `https://…` (HTTPS) → `internetFeaturesEnabled: false`
- Bandeau « Mode local » visible dans l'app
- Menu « API SMS » absent

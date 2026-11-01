# API Portail Patient

Ce document decrit les endpoints du portail patient exposes par le backend.

- Base URL API: `/api`
- Prefixe portail patient: `/api/portal-patient/me`
- Format: `application/json`

## Authentification

Les endpoints du portail patient sont proteges et necessitent un token JWT.

### 1) Connexion

- Methode: `POST`
- URL: `/api/login_check`
- Body JSON:

```json
{
  "username": "identifiant_patient",
  "password": "mot_de_passe"
}
```

- Reponse typique:

```json
{
  "token": "<jwt_token>",
  "refresh_token": "<refresh_token_optionnel>"
}
```

### 2) Appels authentifies

Ajouter le header HTTP suivant sur les endpoints proteges:

```http
Authorization: Bearer <jwt_token>
```

### 3) Verification utilisateur courant

- Methode: `GET`
- URL: `/api/me`
- Usage: recuperer les informations du compte connecte.

## Regles d acces

- Controle d acces: `ROLE_PATIENT`
- Prefixe de controleur: `/api/portal-patient/me`
- Si token invalide ou absent: `401`/`403` selon la couche de securite
- Si aucun dossier patient n est relie au compte: `404`

Le patient connecte est resolu via:

1. Relation directe `user -> portalPatient` (prioritaire)
2. Fallback via identifiant portail (repository patient)

## Endpoints Portail Patient

## 1) Dashboard

- Methode: `GET`
- URL: `/api/portal-patient/me/dashboard`
- Description: retourne les compteurs principaux du patient.

Reponse `200`:

```json
{
  "patient": {
    "id": 12,
    "nom": "Doe",
    "prenom": "Jane",
    "numCarnet": "C-0012",
    "telephone": "0600000000",
    "email": "jane@example.com"
  },
  "stats": {
    "consultations": 8,
    "rdvs": 4,
    "devisFactures": 5,
    "paiements": 6
  }
}
```

## 2) Consultations

- Methode: `GET`
- URL: `/api/portal-patient/me/consultations`
- Description: liste des consultations du patient, triees par date desc.

Reponse `200`:

```json
{
  "patient": {
    "id": 12,
    "nom": "Doe",
    "prenom": "Jane",
    "numCarnet": "C-0012",
    "telephone": "0600000000",
    "email": "jane@example.com"
  },
  "total": 2,
  "items": [
    {
      "id": 44,
      "date": "2026-04-20T10:45:00+00:00",
      "type": "controle",
      "statut": "cloturee",
      "noteSeance": "RAS",
      "medecin": {
        "id": 3,
        "nom": "Medecin Exemple"
      }
    }
  ]
}
```

## 3) Devis et factures

- Methode: `GET`
- URL: `/api/portal-patient/me/devis-factures`
- Description: liste des devis/factures lies au patient.

Reponse `200`:

```json
{
  "patient": {
    "id": 12,
    "nom": "Doe",
    "prenom": "Jane",
    "numCarnet": "C-0012",
    "telephone": "0600000000",
    "email": "jane@example.com"
  },
  "total": 1,
  "items": [
    {
      "id": 55,
      "date": "2026-04-19T09:00:00+00:00",
      "montant": 12000,
      "reste": 2000,
      "statut": "partiellement_paye",
      "type": 1,
      "consultationId": 44,
      "isFacture": true
    }
  ]
}
```

## 4) Paiements

- Methode: `GET`
- URL: `/api/portal-patient/me/paiements`
- Description: liste des transactions de paiement du patient.

Reponse `200`:

```json
{
  "patient": {
    "id": 12,
    "nom": "Doe",
    "prenom": "Jane",
    "numCarnet": "C-0012",
    "telephone": "0600000000",
    "email": "jane@example.com"
  },
  "total": 1,
  "items": [
    {
      "id": 77,
      "date": "2026-04-20T12:00:00+00:00",
      "montant": 10000,
      "type": "encaissement",
      "description": "Paiement consultation",
      "validationStatus": "validated",
      "validated": true,
      "modePaiement": "Especes",
      "consultationId": 44,
      "devisId": 55,
      "recu": {
        "label": "Recu #77",
        "printDataUrl": "/api/portal-patient/me/paiements/77/recu"
      }
    }
  ]
}
```

## 5) Detail recu de paiement

- Methode: `GET`
- URL: `/api/portal-patient/me/paiements/{id}/recu`
- Description: retourne les informations de recu pour une transaction appartenant au patient.

Reponse `200`:

```json
{
  "transaction": {
    "id": 77,
    "date": "2026-04-20 12:00:00",
    "montant": 10000,
    "type": "encaissement",
    "modePaiement": "Especes",
    "description": "Paiement consultation"
  },
  "patient": {
    "id": 12,
    "nom": "Doe",
    "prenom": "Jane",
    "numCarnet": "C-0012",
    "telephone": "0600000000",
    "email": "jane@example.com"
  }
}
```

Erreurs:

- `404` si le recu n existe pas ou n appartient pas au patient.

## 6) Rendez-vous

- Methode: `GET`
- URL: `/api/portal-patient/me/rdvs`
- Description: liste des rendez-vous du patient.

Reponse `200`:

```json
{
  "patient": {
    "id": 12,
    "nom": "Doe",
    "prenom": "Jane",
    "numCarnet": "C-0012",
    "telephone": "0600000000",
    "email": "jane@example.com"
  },
  "total": 1,
  "items": [
    {
      "id": 10,
      "dateRdv": "2026-04-25T14:00:00+00:00",
      "dateCreation": "2026-04-20T09:00:00+00:00",
      "statut": "confirme",
      "description": "Controle post-traitement",
      "duree": 30,
      "medecin": {
        "id": 3,
        "nom": "Medecin Exemple"
      }
    }
  ]
}
```

## 7) Notifications (liste)

- Methode: `GET`
- URL: `/api/portal-patient/me/notifications`
- Description: retourne les 50 dernieres notifications du compte.

Reponse `200`:

```json
{
  "total": 2,
  "items": [
    {
      "id": 901,
      "message": "Votre rendez-vous a ete confirme",
      "type": "appointment",
      "priority": "normal",
      "status": "vu",
      "date": "2026-04-20T08:15:00+00:00",
      "link": "/patient/rdv/10"
    }
  ]
}
```

## 8) Notifications Mercure (subscription)

- Methode: `GET`
- URL: `/api/portal-patient/me/notifications/mercure`
- Description: retourne les donnees necessaires a la souscription Mercure cote frontend.

Reponse `200` (exemple, structure variable selon config Mercure):

```json
{
  "hubUrl": "https://mercure.example/.well-known/mercure",
  "topics": [
    "https://dentalsoft.local/users/42/notifications"
  ],
  "token": "<mercure_jwt>"
}
```

Erreurs:

- `400` si la souscription Mercure ne peut pas etre generee.

## Reponses d erreur communes

Exemples de payload d erreur renvoyes par le controleur:

```json
{
  "error": "Patient introuvable pour ce compte"
}
```

```json
{
  "error": "Utilisateur non authentifie"
}
```

```json
{
  "error": "Recu introuvable"
}
```

## Exemple cURL

api de connexion: **api.cabinetdentaireousmanesow.cloud**

username: de **patient1** à **patient5**
password: **123**

```bash
curl -X POST http://localhost:8000/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"username":"identifiant_patient","password":"mot_de_passe"}'
```

Lecture du dashboard:

```bash
curl http://localhost:8000/api/portal-patient/me/dashboard \
  -H "Authorization: Bearer <jwt_token>"
```

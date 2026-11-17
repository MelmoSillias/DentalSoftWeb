# DentalSoftWeb

- [Extraction des architectures DentalSoftWeb et Medinote](docs/architecture/01-analyse-existant.md).
- [Architecture du backend modulaire](docs/architecture/02-backend-cible.md), [migration des routes](docs/architecture/03-routes-api.md) et [authentification](docs/architecture/04-authentification.md).
- [Analyse pour la refonte du frontend](docs/architecture/05-refonte-frontend.md).
- [Interface cabinet modulaire](frontend/README.md), désormais dans `frontend/` ; [recette de migration](frontend/RECETTE.md).
- [Portail patient modulaire](patient-frontend/README.md), refondu dans son dossier existant.
- [Publication PWA et mises à jour obligatoires](docs/pwa-required-update.md).
- [Déploiement Dokploy complet](docs/dokploy-env.template.md) : cinq services dans
  [un seul Compose](docker-compose.dokploy.yml), routage interne et persistance.
- [Variables à copier dans Dokploy](.env.dokploy.example).
- [Déploiements individuels conservés](docs/deploiement-individuel.md).
- Applications : [API Symfony](api/), [interface cabinet](frontend/),
  [portail patient](patient-frontend/).
- [Archives et anciens déploiements](archive/README.md).

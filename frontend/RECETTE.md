# Recette du frontend cabinet

## Périmètre livré

`frontend/` contient l'application cabinet organisée par domaines, sans réécriture des règles métier. Les URLs, noms de routes, rôles et écrans sont conservés. Le portail patient reste une application distincte dans `../patient-frontend`.

- `src/app/` : bootstrap, routeur, shell, navigation, tableaux de bord et tours.
- `src/domains/` : auth, patient, clinical-record, care-delivery, scheduling, billing, workforce, inventory, communication, reporting, focus, settings et docs.
- `src/shared/` : transport HTTP, primitives UI, utilitaires, impression et temps réel.
- `architecture-map.json` : correspondance des anciens emplacements avec l'architecture actuelle.
- `architecture-debt.json` : exceptions historiques à résorber, sans nouvelle dépendance autorisée.
- `scripts/migrate-architecture.py` : outil ponctuel déjà exécuté, refusant de se relancer après création du mapping. Python n'est pas nécessaire pour développer, tester ou construire l'application.

Le déplacement architectural n'a pas activé le refresh token ni modifié les endpoints d'authentification, les calculs financiers, la paie ou les mécanismes de mutation. Les couplages historiques de `shared` restent suivis explicitement. La PWA prend en charge les mises à jour obligatoires et facultatives.

## Validation automatisée

Depuis `frontend` :

```sh
npm ci
npm run validate
npm run test:pwa:browser
```

- `validate` contrôle l'architecture, l'isolation vis-à-vis du portail patient, les tests, le lint et le build.
- La PWA est activée par défaut au build ; `VITE_ENABLE_PWA=false` permet de la désactiver explicitement.
- Le test de rapport attend le libellé actuel « Total encaissé » et couvre la séparation soins/prestations et l'exclusion des remboursements assurance.
- Les tests HTTP couvrent les réponses tardives, l'invalidation de session et le verrou PWA sans déconnexion ni fausse erreur réseau.
- Le scénario navigateur construit deux versions et vérifie deux onglets, le blocage obligatoire, l'installation au clic malgré la coupure réseau après téléchargement, un seul rechargement par onglet et la conservation du stockage.
- L'avertissement d'architecture sur l'import historique absent de `FilesOptions.vue`, écran non routé, reste suivi dans la dette.

Ces contrôles ne remplacent pas une recette fonctionnelle avec l'API de recette. `lint` utilise `--fix` et le build régénère configuration et assets : leurs écritures doivent être autorisées.

## Préparer l'environnement de recette

- [ ] API indépendante, base anonymisée, fichiers de test, fournisseurs SMS/mail/push de test.
- [ ] Confirmer les URLs API/fichiers réellement appelées, y compris après sélection du cabinet. Le build `default` conserve des URLs localhost ; vérifier la variante de livraison et le mode Dokploy.
- [ ] Origine et profil navigateur dédiés ; ne pas tester sur l'origine de production.
- [ ] API CORS autorisant explicitement l'origine de recette (`http://localhost:5181`, puis `http://localhost:4181`).
- [ ] Comptes de test pour chaque rôle ; ne jamais recopier les jetons de production.
- [ ] Vérifier les variantes cabinet, hors Internet et LAN séparément.

## Parcours fonctionnels

Pour chaque ligne, noter cabinet, rôle, navigateur, résultat attendu/observé et preuve de test.

- [ ] Login, restauration de session, mot de passe, déconnexion pendant une requête, 401 et appareil refusé.
- [ ] Aucun dossier, cache privé ou abonnement de l'utilisateur précédent après changement de compte.
- [ ] Navigation par rôle, accès direct aux URLs existantes, rechargement des liens profonds.
- [ ] Patient : recherche, création, dossier, antécédents, allergies, pièces jointes et uploads.
- [ ] Consultation, actes, prescriptions, devis, facture, paiement et reçu, avec et sans assurance.
- [ ] Double clic paiement, réponse perdue, clé d'idempotence réutilisée, absence de double débit.
- [ ] Agenda médecin, salles, rendez-vous, changements de date et fuseau.
- [ ] Stock : entrées/sorties, refus du négatif et historique.
- [ ] RH, paie mensuelle/journalière, paiements partiels, primes et INPS.
- [ ] Impression A4/ticket, en-têtes cabinet, exports et rapprochement des totaux.
- [ ] Notifications, liens, Mercure, reconnexion et poste Focus.
- [ ] Mode local sans Internet, retour du réseau et absence de rejeu aveugle des mutations.
- [ ] PWA sur une origine dédiée : mise à jour facultative différable, mise à jour obligatoire bloquante, opt-out de build explicite.
- [ ] Portail patient : non-régression avec l'API de recette, sans changement de son application.

## Autorisation de livraison

La validation automatisée ne vaut pas approbation métier. Avant tout déploiement, fermer les anomalies, obtenir la validation de recette et préparer un changement explicite et réversible. Le contexte Docker racine et la documentation d'architecture du dépôt sont gérés séparément de ce dossier.

# DentalSoft — frontend cabinet

`frontend/` est l'application cabinet. Le portail patient reste dans `../patient-frontend` et possède ses propres dépendances, fichiers générés et builds. Ne pas partager de `node_modules` ni créer de liens symboliques, jonctions ou liens physiques entre les applications. Les archives et anciens dossiers de travail ne sont pas des dépendances de l'application.

## Démarrage (Windows, shell sh)

Depuis `frontend` avec une version de Node compatible avec Vite 7 (Node 22.12+ recommandé) :

```sh
npm ci
npm run check:isolation
npm run dev
```

Le serveur dev écoute sur **http://localhost:5181**, avec `strictPort: true` : il échoue si le port est occupé, sans basculer ailleurs. HMR utilise ce même serveur/port. `npm run preview` écoute sur **http://localhost:4181**, également strict, après un build.

Pour le développement et la recette, utiliser une origine et un profil navigateur dédiés, jamais l'origine de production. Les ports séparent les origines en local mais les cookies peuvent être partagés entre ports : prévoir aussi des comptes de test.

## API : vérifier la cible avant toute connexion

**Une origine frontend séparée ne protège pas les données de production.** En développement et en recette, utiliser une API et une base locales/de staging, avec fichiers et fournisseurs SMS/mail/push de test. Les données médicales réelles et les secrets n'ont pas leur place dans le dépôt ou dans les variables `VITE_*` (publiques dans le navigateur).

`npm run dev` sélectionne `cabinet-configs/default/config.dev.json`. La priorité réelle de `src/config.js` est :

1. `VITE_DOKPLOY=true` force l'API de la même origine (`/api`) pour le déploiement derrière le reverse proxy.
2. Les valeurs `viteApiPrefix` / `viteFilePrefix` du cabinet généré priment sur `.env`.
3. Les variables `VITE_API_PREFIX` / `VITE_FILE_PREFIX`, puis les valeurs localhost de repli, ne servent qu'en l'absence des valeurs cabinet.

Modifier seulement `.env.local` ne suffit donc pas à rediriger un cabinet déjà configuré. Vérifier le cabinet sélectionné, `src/generated/cabinet-config.generated.js` et les requêtes réellement envoyées dans l'onglet Réseau.

`npm run build` sélectionne `default/config.prod.json`, dont les URLs API/fichiers restent configurées vers localhost:8000. Pour la livraison, sélectionner la variante cabinet appropriée ou le mode `VITE_DOKPLOY=true` prévu par le déploiement. Les variantes cabinet/LAN conservent leurs endpoints : les vérifier avant utilisation. Aucun script ici ne migre la base ni ne déploie automatiquement.

## PWA et service workers

La PWA est **activée par défaut au build de production**, sans variable supplémentaire. `VITE_ENABLE_PWA=false` est l'opt-out explicite ; il désactive la génération du service worker et le démarrage des contrôles de mise à jour. Le serveur de développement n'active pas la PWA.

Les mises à jour obligatoires bloquent les écrans métier et les nouvelles requêtes HTTP jusqu'à l'installation d'une version admissible. L'installation reste déclenchée par l'utilisateur, sans effacement des données locales. Voir [README-PWA.md](README-PWA.md) pour les métadonnées de livraison et les tests navigateur.

La désactivation n'efface pas un service worker précédemment installé. Sur l'origine **de test uniquement**, le désinscrire et effacer ses caches dans les outils navigateur si nécessaire. Ne pas désinscrire le service worker de production pendant la recette.

## Architecture et configuration cabinet

- `src/app/` : entrée, `App.vue`, routeur, layouts et navigation.
- `src/domains/` : écrans, composants, services et règles métier par domaine.
- `src/shared/` : HTTP canonique, primitives UI, utilitaires, impression et temps réel.

L'auto-import Components parcourt `src/shared/ui`, `src/domains` et `src/app/layout`, avec le resolver PrimeVue. L'alias `@` reste ancré sur `src`. `src/assets`, `src/generated`, `src/config.js` et `src/cabinetConfig.js` conservent leur rôle.

Le workflow multi-cabinet reste :

- `cabinet-configs/<cabinet-id>/config.<env>.json` (ou `config.json` en repli) : configuration et branding.
- `cabinet-configs/<cabinet-id>/public/` : assets du cabinet.
- `src/generated/cabinet-config.generated.js` : module généré consommé par l'application et Vite.
- `npm run dev:cabinet -- --cabinet=<cabinet-id>` et `npm run build:cabinet -- --cabinet=<cabinet-id>` : sélection explicite ; consulter les scripts pour les options d'environnement.

Les scripts dev/build régénèrent la configuration et synchronisent `public` et `src/assets` dans `frontend`. Ces chemins doivent rester locaux à cette application.

## Contrôles

```sh
npm run validate
npm run test:pwa:browser
```

- `validate` exécute, dans l'ordre, `check:architecture`, `check:isolation`, `test`, `lint` et `build`.
- `check:architecture` vérifie les imports locaux statiques/lazy, interdit les imports hors de `src`, le retour aux anciens dossiers techniques et toute nouvelle dépendance de `shared` vers `app`/les domaines. `architecture-debt.json` suit les 26 couplages historiques et l'import manquant de l'écran non routé `FilesOptions.vue`.
- `check:isolation` ne démarre aucun serveur. Il contrôle les ports/HMR résolus par Vite, les noms de package/lock et l'absence de références au portail patient, aux archives ou à l'ancien dossier de travail dans les manifestes et la configuration Vite/alias. Il rejette les liens sortants et les liens physiques avec les fichiers homologues du portail patient. Le Vite utilisé doit être installé dans `frontend/node_modules`.
- Le parcours contrôle les liens avant d'exclure le contenu de `.git`, `node_modules`, `dist`, `.vite` et `coverage`. Ce contrôle ne remplace pas l'audit des endpoints API ni le contrôle d'architecture des imports source.
- `test` exécute `node --test tests/*.test.mjs scripts/tests/*.test.mjs`. Les tests d'isolation peuvent se lancer séparément avec `node --test scripts/tests/check-isolation.test.mjs`.
- **`lint` utilise `--fix` et peut modifier les fichiers.** Le build régénère aussi la configuration et les assets. Lancer `validate` lorsque ces écritures sont autorisées.

Les variantes local-offline et XAMPP restent disponibles. Leurs endpoints doivent être vérifiés avant usage. La recette fonctionnelle est détaillée dans [RECETTE.md](RECETTE.md).

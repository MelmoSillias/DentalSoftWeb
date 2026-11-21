# DentalSoft — portail patient

Application Vue 3 / Pinia / PrimeVue, indépendante du frontend cabinet. Refonte réalisée **dans ce dossier uniquement**, selon le modèle `frontend-refonte` et `../docs/architecture/{03-routes-api,04-authentification,05-refonte-frontend}.md`. Aucun import runtime de code cabinet. Le protocole PWA obligatoire de `frontend-refonte` est repris localement ; seul `vite-plugin-pwa` est ajouté, avec correctifs compatibles des dépendances. Docker, déploiement et autres frontends restent indépendants.

## Démarrer et construire

Depuis `patient-frontend`, avec Node compatible avec le Vite verrouillé (Node 22.12+ recommandé) :

```sh
npm ci
npm run dev
npm run build
npm run preview
```

La configuration et le branding multi-cabinet existants sont conservés :

```sh
npm run dev:cabinet -- --cabinet=default
npm run build:cabinet -- --cabinet=default
npm run select-cabinet -- --cabinet=default
```

`select-cabinet` et les variantes cabinet écrivent la configuration générée et les assets dans **ce dossier**. Le build simple ne change pas de cabinet. `src/assets`, `src/generated`, `src/cabinetConfig.js`, `src/style.css` et les configurations cabinet conservent leur emplacement.

Le transport utilise, dans cet ordre : `VITE_DOKPLOY=true` → même origine ; sinon `VITE_API_BASE_URL` → URL configurée ; sinon `http://localhost:8010`. L'URL doit être une origine **sans suffixe `/api`**. Les scripts cabinet injectent `apiBaseUrl` dans `VITE_API_BASE_URL` ; importer uniquement le branding généré ne redirige pas le transport.

**Tester sur une API/base de test uniquement.** Vérifier l'URL réelle dans l'onglet Réseau avant toute mutation. Les builds et tests Node ci-dessous n'appellent aucune API métier réelle. Les variables `VITE_*` sont publiques : aucun secret ne doit y figurer.

## Architecture

```text
src/
  app/
    main.js                 Bootstrap, Pinia, PrimeVue, injection de session HTTP
    App.vue                 Shell, toast, thème, verrou PWA et vues par session
    router/                 Routes existantes et garde de navigation testable
    layout/                 En-tête, navigation mobile, sidebar
  domains/
    auth/
      index.js              Entrée publique : store et formulaire mot de passe
      api.js                Login, refresh, logout, identité, mot de passe
      session.js            Cycle de session testable sans Vue ni navigateur
      store.js              Adaptation réactive Pinia et stockage navigateur
      useAuthForm.js
      views/                Connexion et changement de mot de passe
    patient-portal/
      index.js              Entrée publique : services et réglages du portail
      api.js                Routes canoniques ; DTO métier préservés
      stores/               Réglages publics, chargement concurrent partagé
      views/                Dashboard, consultations, RDV, paiements, documents,
                            profil, avis anonyme, fermeture du portail
  shared/
    api/                    Transport injectable, adaptateur réseau/toasts
    composables/            Réseau, thème, métadonnées et usePwaUpdate
    lib/                    Validation des releases et verrou HTTP irréversible
    ui/                     Composants visuels et PwaUpdateBanner
```

Règles : `app` assemble les domaines et `shared` ; un domaine ne dépend pas de `app` ; `shared` ne dépend ni des domaines ni de `app`. Les imports entre domaines passent par leurs `index.js`. Le bootstrap peut importer leurs vues pour composer les routes. Les vues utilisent les services métier, sans construire d'URL API ni appeler `fetch`. Le transport ne connaît ni Pinia ni le routeur : sa politique de session est injectée au démarrage. L'alias `@` reste `src`.

Les parcours et URLs publiques sont conservés : `/login`, `/dashboard`, `/consultations`, `/rendez-vous`, `/paiements`, `/documents`, `/profil`, `/avis-anonyme`, `/portail-ferme`, ainsi que la redirection `/` → dashboard. Recherche, filtres, détails de consultation/documents, avis après consultation, avis anonyme, branding, thème, navigation responsive et bannière réseau restent en place. Le formulaire de changement du mot de passe est ajouté au profil ; pas de refonte visuelle générale.

Les données privées et détails chargés restent locaux aux vues. Une nouvelle version de session remonte ces vues ; les réponses HTTP tardives d'une ancienne session sont rejetées avant livraison. Aucun store de données médicales partagé ni cache persistant privé n'est ajouté.

## Contrats API et authentification

- `POST /api/patient-portal/auth/login` reçoit `{ email, password }` ; `email` accepte l'identifiant historique.
- La réponse doit contenir `access_token` (ou `token`) **et** `refresh_token`.
- `GET /api/me` vérifie l'identité avant publication/restauration de la session. Le JWT doit annoncer `realm=patient` et les rôles serveur doivent être patients, sans rôle personnel. Le décodage local ne valide pas une signature : l'API reste l'autorité de sécurité.
- `POST /api/patient-portal/auth/refresh` et `/logout` reçoivent seulement `{ refresh_token }`, sans Bearer. Le comportement d'appareil existant par User-Agent/Accept-Language est conservé, sans inventer une nouvelle identité d'appareil.
- Une seule rotation est partagée entre lectures concurrentes en échec 401. Un GET peut être rejoué **une seule fois**, sur la même URL. Aucun refresh sur 403, ni aucune relance automatique de mutation. Une mutation refusée en 401 termine la session et doit être ressaisie après reconnexion.
- Un 401 définitif ou un refresh 401/403 efface la session. Un refus métier/appareil 403 d'une lecture ne provoque pas de logout. Une panne réseau/429/5xx pendant le renouvellement ne détruit pas la session active.
- Logout : effacement local immédiat, puis tentative de révocation serveur. Un échec réseau ne bloque jamais la déconnexion ; dans ce cas, la révocation distante n'est **pas garantie**. Le JWT déjà émis reste valable jusqu'à expiration selon le contrat backend.
- `PATCH /api/me/password` reçoit `{ oldPassword, newPassword }`. Après succès : effacement des credentials locaux, tentative de révocation et retour à la connexion. Le backend invalide également les JWT/refresh des autres sessions ; elles sont rejetées à leur prochaine requête. Pas de polling ajouté.
- Les appels métier utilisent `/api/patient-portal/me/{dashboard,consultations,appointments,payments,billing-documents,profile,reviews}` et leurs sous-ressources canoniques. L'avis anonyme utilise `/api/public/review-submissions`. Les corps et réponses historiques ne sont pas retraduits arbitrairement. Le service de reçu utilise `/payments/{id}/receipt` ; aucun nouveau parcours d'impression n'est inventé.

### Stockage et migration volontaire

La paire est enregistrée sous `patient_portal_session_v2` dans **sessionStorage** : rechargement dans le même onglet possible, pas de persistance volontaire après fermeture de celui-ci. Le navigateur peut toutefois restaurer son stockage de session, et un onglet dupliqué peut en hériter : cela ne constitue pas une isolation de sécurité entre onglets. Les fronts cabinet et patient n'utilisent pas les mêmes clés. L'identité n'est pas restaurée depuis un cache local.

Les anciennes clés localStorage `patient_portal_token` / `patient_portal_user` ne sont plus acceptées et sont retirées à l'effacement de session. Les anciens JWT sans realm et les anciennes sessions sans refresh imposent une reconnexion. Le thème et la préférence de sidebar restent inchangés dans localStorage.

**Limite de sécurité assumée :** sessionStorage est accessible à JavaScript et donc aux XSS. Aucun cookie HttpOnly/CSRF n'est ajouté sans contrat backend. Une rotation est sérialisée dans l'onglet, pas entre onglets dupliqués. Si deux copies présentent le même refresh consommable, l'une peut devoir se reconnecter. Un stockage HttpOnly ou une coordination inter-onglets est une évolution distincte.

## PWA et mises à jour obligatoires

### Build et confidentialité

La PWA est **active par défaut au build de production**, en mode **prompt**, avec `vite-plugin-pwa`. `npm run dev` n'enregistre pas de service worker. Seule la valeur exacte **`VITE_ENABLE_PWA=false`** désactive la génération et le runtime PWA ; `VITE_ENABLE_PWA=true` reste accepté. Cette option ne désinscrit pas un ancien SW déjà installé : nettoyer uniquement l'origine de test dans les outils navigateur si nécessaire. Utiliser HTTPS en déploiement, ou localhost pour les tests.

`plugins/patientPwa.mjs` définit un manifest portant le `displayName`, le `brandName` et le logo du cabinet sélectionné. Un objet optionnel `pwa` dans la configuration cabinet permet de préciser `name`, `shortName`, `description`, `themeColor`, `backgroundColor`, `startUrl`, `display`, `icons`. Le logo de repli est un PNG 512×512 ; toute icône déclarée doit réellement exister dans `public` avec ses dimensions correctes. Les ressources déclarées sont limitées aux images publiques, jamais aux URLs d'API/uploads. Les assets cabinet restent synchronisés par le workflow existant.

Le SW précache **uniquement le shell statique** : `index.html`, JS/CSS, polices et images compilées, favicon et icônes déclarées. **Aucun runtime cache**, aucune réponse API, donnée de profil, consultation, paiement ou fichier médical mis en cache par la PWA. Le transport API utilise aussi `cache: 'no-store'` pour éviter le cache HTTP navigateur. Le fallback de navigation exclut notamment `/api`, `/uploads`, `/upload_files`, `/assurances`, `/health`, `/files`, `/receipts`, `/prints`, `/.well-known`, les PDF/DICOM/ZIP et les métadonnées de release. Les routes UI comme `/documents` gardent leur fallback SPA. Le shell peut démarrer hors ligne, mais cela **ne fournit pas de dossier patient hors ligne**.

`release.json` et `pwa-release-worker.js` sont **exclus du précache**. Le second est importé par le worker pour répondre à `GET_PWA_RELEASE` avec l'identité exacte du build installé. Aucun handler de notification cabinet, Mercure ou Web Push n'est importé.

### Protocole de release et contrat parent/Docker

Les fichiers sont autonomes dans ce dossier :

- `scripts/pwa-release.mjs` : capture fiable, compatible avec l'appel du parent pour construire l'image patient ;
- `plugins/injectBuildVersion.mjs` : injection `VITE_APP_RELEASE`, `VITE_APP_BUILD_ID`, meta HTML `app-version`, génération de `release.json` et du répondeur worker ;
- `src/shared/lib/pwaRelease.mjs` : schéma et calcul du seuil, identiques au protocole frontend ;
- `src/shared/composables/usePwaUpdate.js`, `src/shared/lib/pwaUpdateLock.mjs` : orchestration et verrou ;
- `src/shared/ui/PwaUpdateBanner.vue` : bannière facultative ou écran obligatoire adapté au portail.

Les trailers de l'historique **first-parent complet** définissent la politique :

```text
PWA-Update: required
```

`optional` ou l'absence de trailer n'élèvent pas le seuil. Une release facultative **après** une obligatoire conserve le minimum requis. La comparaison utilise révision, génération et lignée, jamais une date de build. Un build de la même révision conserve sa génération mais possède un `buildId` distinct. Une politique invalide, un historique superficiel/manquant ou une révision attendue incohérente font échouer le build plutôt que fabriquer une version fiable en apparence.

Depuis `patient-frontend` :

```sh
node scripts/pwa-release.mjs
node scripts/pwa-release.mjs --base64
# Alias pratique (sortie npm supplémentaire) :
npm run pwa:release
```

Le Dockerfile partagé capture les métadonnées du portail depuis `.git` dans une étape intermédiaire. Si `.git` est absent du contexte Docker, capturer les métadonnées depuis le checkout complet à déployer avec `patient-frontend/scripts/pwa-release.mjs --base64`. Le build accepte :

- `PWA_RELEASE_METADATA` : JSON de capture encodé en base64, utilisable sans `.git` ;
- `PWA_RELEASE_REVISION` : révision source attendue, vérifiée si fournie ;
- `PWA_RELEASE_FILE` : chemin vers une capture JSON, prioritaire dans le plugin de build, également validée ;
- `PWA_BUILD_ID` : identifiant de build optionnel lors de la capture depuis l'historique.

Sans capture fournie, le build lit l'historique complet disponible. Le mode PWA désactivé continue d'émettre les métadonnées de build, sans SW ni manifest.

Côté serveur parent : publier `release.json`, `sw.js` et `pwa-release-worker.js` à leurs vraies URLs, sans fallback HTML ni cache longue durée (recommandation : `Cache-Control: no-store` pour ces fichiers et `index.html`). Les assets hashés peuvent être immutables. Déployer le shell, ses assets et les métadonnées de façon cohérente, conserver les assets nécessaires aux anciens onglets et utiliser une origine patient distincte du cabinet : SW, CacheStorage et stockage navigateur sont liés à l'origine/scope.

### Comportement runtime

Au démarrage, le bootstrap restaure l'obligation connue **avant les gardes du routeur**. Il vérifie ensuite `release.json` avec `no-store`, le worker via `updateViaCache: 'none'`, toutes les 60 secondes et au focus/retour réseau/retour visible. La clé `dentalsoft-patient-pwa-release-v1:<BASE_URL>` et un BroadcastChannel propagent le seuil entre onglets ; les événements `storage` servent de repli.

- **Facultative** : bannière « Actualiser » / « Plus tard », sans activation automatique.
- **Obligatoire** : démontage de toute l'UI du portail (y compris layout, connexion et toasts) ; seul l'écran de mise à jour reste visible. Impossible de masquer l'obligation.
- **Transport verrouillé** : tous les appels API, publics/auth inclus, échouent avec `PWA_UPDATE_REQUIRED`. Le contrôle est répété avant retry et après réception pour ne pas livrer de réponse sensible tardive. Les requêtes déjà envoyées ne peuvent pas être annulées rétroactivement côté serveur.
- Le verrou est irréversible **pour le document courant**, même hors ligne ou après une réponse facultative plus ancienne. Seul un nouveau document éligible le lève. Le stockage d'authentification et les préférences ne sont pas effacés.
- Le bouton d'installation n'est proposé que pour un worker installé/actif dont l'identité satisfait le minimum. `SKIP_WAITING` est envoyé **uniquement sur clic**, après revérification. Les autres onglets contrôlés rechargent une fois au changement de contrôleur ; pas de boucle/fallback de reload sur timeout.
- Une erreur réseau garde le verrou et affiche une action de retry. Un worker éligible déjà installé peut être activé malgré une panne réseau. Sans worker éligible, attendre le retour réseau ; aucune purge automatique des caches/du stockage pour contourner le problème.

## Tests et contrôles

```sh
npm run check:architecture
npm test
npm run build
# Les trois, dans cet ordre :
npm run validate
```

Aucun runner supplémentaire : tests natifs `node:test`, imports réels des modules de session, transport, services et garde, API simulée, aucune donnée médicale réelle. Les anciens tests d'authentification basés sur l'évaluation du texte du store ont été remplacés par des tests du véritable module de session.

- `scripts/tests/auth.test.mjs` : login/profil vérifié, rôles mixtes/personnel, realm, absence de paire, restauration, rotation unique, logout et courses login/hydrate/refresh, échec du stockage, mot de passe et invalidation.
- `tests/client.test.mjs` : retry GET borné, mutations non rejouées, 403, second 401, réponse tardive, 204, refus des URLs externes et des credentials d'une ancienne session, intégration de trois lectures concurrentes avec une seule rotation réelle du module de session.
- `tests/contracts.test.mjs` : méthodes, URLs canoniques, Bearer, corps login/refresh/logout/password/avis, services de listes/détails/reçu et réglages publics.
- `tests/navigation.test.mjs` : garde, lien profond conservé, accès aux écrans privés, portail fermé et avis anonyme public, attente de restauration et partage du chargement des réglages.
- `tests/architecture.test.mjs` : projet réel et contre-exemples temporaires prouvant les interdictions, modules `.mjs` inclus.
- `tests/pwaRelease.test.mjs` : minimum persistant, capture full-history/métadonnées Docker, validation, fetch `no-store`, verrou et plugin d'émission.
- `tests/pwaRuntime.test.mjs` : runtime dans un harness VM avec navigateur simulé, activations sur clic, obligations persistées/inter-onglets, offline/retry, minimum renforcé et absence de boucle de reload.
- `tests/pwaPrivacy.test.mjs` : configuration sans cache métier, denylist, branding, verrou réel au transport pour toutes méthodes/routes, retry, réponses en vol et conservation de l'authentification.

`check:architecture` contrôle les imports locaux statiques et lazy à chaîne littérale, leur résolution, les frontières et l'absence des anciens dossiers techniques. Il refuse les appels HTTP dans les vues/composants. C'est un contrôle statique pragmatique, pas un parseur JavaScript complet : imports calculés, dépendances cachées, CSS et comportement visuel ne sont pas certifiés.

### Test PWA dans un vrai navigateur

```sh
npm run test:pwa:browser
```

Ce test opt-in n'est pas inclus dans `validate`. Il utilise Chrome/Edge installé et le CDP/WebSocket natif de Node (Node 22.12+), sans Playwright ni nouvelle dépendance. Pour un autre emplacement, définir `PWA_BROWSER_EXECUTABLE` sur le chemin absolu de Chrome/Edge. Un checkout avec historique complet est nécessaire pour créer les deux releases de test.

Le test construit deux fixtures A/B dans `node_modules/.cache`, lance un serveur local éphémère avec **API simulée**, un profil navigateur neuf et deux onglets. Il vérifie le SW réel, le manifest, l'absence d'API/uploads/release dans CacheStorage, le démontage UI obligatoire, l'absence d'activation avant clic, l'installation du worker B déjà téléchargé pendant une panne réseau, exactement un reload par onglet et la conservation de sentinelles localStorage/sessionStorage. Il arrête serveur/navigateur et supprime ses fixtures ; aucun profil utilisateur ni compte médical réel n'est utilisé.

## Validation réalisée et reste à recetter

`npm ci`, `npm run validate` (contrôle d'architecture, **53 tests Node réussis**, build Vite/PWA) et `npm run test:pwa:browser` exécutés avec succès sous Windows, Node 26.13.1 / npm 11.20.0 et Chrome headless. Le build avec `VITE_ENABLE_PWA=false` a également été vérifié : ni SW ni manifest générés.

Les anciennes alertes élevées sont corrigées avec **Vite 8.2.6** (correctif de 8.2.2) et **source-map-js 1.2.2**. `vite-plugin-pwa` **2.0.0** déclare Vite 8 compatible. `npm audit --omit=dev` : **0 vulnérabilité**. L'audit complet conserve **6 alertes transitives de développement (2 low, 4 moderate)** via Workbox / node-resolve / deepmerge / glob / minimatch, sans alerte high/critical. La correction automatique proposée rétrograderait le plugin vers 1.1.0 : elle n'est pas appliquée. Aucun `--force`, aucune migration majeure de dépendance existante. Ne pas exposer le serveur de développement à un réseau non fiable.

Restent à effectuer avec un navigateur et une API de test : recette responsive/thèmes, comptes réels patient/personnel/mixte, appareil refusé, expiration et rotation réelles, changement de mot de passe sur plusieurs sessions, logout hors ligne, fermeture du portail, consultations et détails, documents, paiements, avis après consultation/anonyme et retour via lien profond. Les tests Node n'attestent pas ces intégrations ni la conformité visuelle. Le portail existant n'implémente pas de flux Mercure/Web Push ; aucun abonnement ni parcours temps réel supplémentaire n'est ajouté. Docker, déploiement et autres frontends restent hors périmètre. La recette PWA automatisée ci-dessous utilise uniquement une origine locale éphémère, une API simulée et un profil Chrome neuf, pas un compte patient réel.

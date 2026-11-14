# PWA — frontend

La PWA est activée par défaut au build de production, sans configuration supplémentaire. Seule la valeur explicite `VITE_ENABLE_PWA=false` désactive la génération du service worker et le démarrage des contrôles de mise à jour. Le serveur de développement n'active pas la PWA. Les ports restent `5181` (dev) et `4181` (preview). Le service worker est testé sur un build de production, sur HTTPS ou localhost.

## Mises à jour

- `src/shared/lib/pwaRelease.mjs` valide les métadonnées et compare les générations/révisions, sans dépendre des dates de build.
- `src/shared/composables/usePwaUpdate.js` vérifie `release.json` au démarrage, toutes les minutes et au retour au premier plan/en ligne. L'obligation est persistée et propagée entre onglets.
- Une mise à jour facultative peut être différée. Une mise à jour obligatoire démonte les écrans métier et verrouille les nouvelles requêtes du client canonique `src/shared/api/http.js`, sans effacer la session ni les données locales.
- L'installation requiert un clic et un worker installé dont l'identité satisfait le minimum requis. Un échec reste réessayable ; aucune actualisation forcée par temporisation. Les autres onglets se rechargent une seule fois après activation.
- `release.json` n'est jamais précaché ni servi par le fallback SPA. Nginx désactive son cache et revalide les scripts du service worker.

## Métadonnées de livraison

Le plugin `plugins/injectBuildVersion.mjs` émet `release.json` et `pwa-release-worker.js`. Le script `scripts/pwa-release.mjs` lit l'historique Git complet en lecture seule, sur la première chaîne de parents. Le trailer `PWA-Update: required` relève le minimum ; `optional` (ou l'absence de trailer) conserve le minimum précédent.

```sh
npm run release:pwa
npm run test:pwa
npm run test:pwa:browser
```

Pour un build sans historique Git (Docker notamment), fournir `PWA_RELEASE_METADATA` (JSON encodé en base64) et éventuellement `PWA_RELEASE_REVISION` pour vérifier le hash source. `PWA_RELEASE_FILE` permet également au plugin de lire un fichier JSON préparé. Les historiques superficiels ou absents sans métadonnées fiables font échouer le build.

Le `Dockerfile` local accepte ces arguments. La PWA ne nécessite pas d'argument d'activation, mais les métadonnées fiables restent indispensables pour un build sans historique Git. L'orchestration Docker et le Dockerfile à la racine du dépôt restent gérés séparément.

## Tests navigateur

Depuis `frontend`, `npm run test:pwa:browser` construit deux vrais bundles de production sans option d'activation PWA, puis utilise Chrome/Edge via CDP, sans dépendance supplémentaire. Définir `PWA_BROWSER_EXECUTABLE` si le navigateur n'est pas à un emplacement Windows standard. Node doit fournir le WebSocket natif.

Le scénario vérifie deux onglets, le blocage obligatoire, l'absence de rechargement avant clic, une installation déjà téléchargée malgré la coupure réseau, un seul rechargement par onglet et la conservation de `localStorage` / `sessionStorage`. Les builds et profils temporaires sont créés dans `node_modules/.cache` de `frontend` puis supprimés.

## Authentification

Le portage conserve le contrat existant : `login_check` reçoit `username` / `password`, `me` fournit `user.roles`, et `PATCH me/change-password` reçoit `oldPassword` / `newPassword`. Le backend invalide les JWT après modification des credentials, des rôles ou de l'état du compte ; le client canonique traite le `401` et ignore les réponses tardives d'une ancienne session. Le verrou PWA ne doit pas être interprété comme une erreur réseau ou une expiration de session.

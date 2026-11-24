# Mise à jour PWA obligatoire

Le protocole ne dépend pas du backend : aucun endpoint API ni changement de schéma. La version d’adoption publiée sur `master` équipe l’interface cabinet historique. La branche de refonte reprend ce protocole dans `frontend/` et l’ajoute au portail `patient-frontend/`, avec une PWA active par défaut dans les deux builds. Le portail patient historique ne possédait pas de PWA : sa première adoption intervient donc avec la refonte.

## Activer une publication obligatoire

Ajouter un trailer Git à la fin du message du **commit déployé** (séparé du corps par une ligne vide) :

```text
Corriger la compatibilité des encaissements

PWA-Update: required
```

Sans trailer, ou avec `PWA-Update: optional`, la publication est facultative pour les clients qui satisfont déjà le minimum. Ce paramètre n'est pas un fichier de configuration permanent ni une variable à laisser activée dans Dokploy.

Le build parcourt l'historique **first-parent complet** de HEAD, du plus ancien au plus récent. La génération est la position du commit dans cet historique ; le dernier trailer `required` fixe le minimum. Exemple : A optional → B required → C optional : A est bloqué face à C, B et C ne le sont pas. Un nouveau `required` relève le seuil. Un trailer invalide ou multiple fait échouer la capture. Le texte `PWA-Update: required` au milieu d'un paragraphe n'est pas un trailer.

Pour un merge, mettre le trailer sur le commit de merge déployé, pas seulement sur un commit d'une branche secondaire. Un cherry-pick doit conserver le trailer sur la branche finale. Les hashes/générations sont recalculés sur cette branche : ne pas transporter les métadonnées d'une autre branche.

## Identité et artefacts

`frontend/scripts/pwa-release.mjs` produit des métadonnées de schéma 1 :

- `revision` : hash Git complet de HEAD ;
- `generation` et `lineage` : position first-parent et hash racine ;
- `buildId` : hash Git + UUID de build (ou suffixe explicite `PWA_BUILD_ID`) ;
- `policy` : choix du commit courant uniquement ;
- `minimum` : hash/génération de la dernière obligation, ou `null`.

L'identité est injectée dans le bundle, l'entrée HTML et le répondeur `pwa-release-worker.js` du SW. Deux reconstructions du même commit sont distinguables, mais satisfont le même minimum. Le suffixe peut être fixé pour un build reproductible, à condition d'être unique par ensemble réel d'artefacts/configuration. Le protocole est basé sur les commits, pas sur l'horloge, la version npm ou l'heure du navigateur.

`dist/release.json` est émis au build, **hors précache Workbox**, sans runtime cache ni fallback SPA. Le répondeur SW est également hors précache, importé par le SW. Modifier le seul JSON sur le serveur ne modifie donc pas le SW et ne provoque pas son installation ; cela peut toutefois bloquer les clients sans leur donner de version installable. Publier les artefacts ensemble, ne pas éditer le JSON à la main.

Les deux configurations nginx servent `/release.json` avec `Cache-Control: no-store, no-cache, must-revalidate`, sans ETag/304, et un vrai 404 si absent. Le client utilise `fetch(..., {cache: 'no-store'})`, valide le schéma, impose un délai de 10 secondes et traite les erreurs/HTML/503 comme des échecs réessayables. Configurer de même tout CDN/reverse proxy extérieur : aucune règle « cache everything » sur cette URL, `sw.js`, `pwa-release-worker.js` ou `index.html`.

## Build local et Docker / Dokploy

### Checkout complet disponible

```sh
npm --prefix frontend ci
npm --prefix frontend run build
```

`docker/frontend/Dockerfile` capture les métadonnées dans une étape intermédiaire `release-metadata` pour les deux frontends, **avant** de copier les sources vers l'étape applicative. Son dockerignore autorise le `.git` racine si présent. Il faut un contexte à la racine du dépôt et Git disponible dans cette étape (installé par l'image). Si le clone est superficiel (notamment avec Dokploy), l'étape complète automatiquement son historique depuis `origin` avant la capture, sans déplacer `HEAD` ni modifier les sources. `.git` n'est jamais copié dans nginx. Les métadonnées sont transférées dans `/pwa-release.json` puis lues via `PWA_RELEASE_FILE` au build Vite.

```sh
docker build -f docker/frontend/Dockerfile --build-arg CABINET=default -t dentalsoft-frontend .
docker build -f docker/frontend/Dockerfile --build-arg FRONTEND_DIR=patient-frontend --build-arg CABINET=orodent -t dentalsoft-frontendpatient .
```

Le contexte `.git` peut être volumineux et contenir des informations privées : n'exposer ni contexte ni cache intermédiaire publiquement. Un contexte BuildKit Git peut retirer `.git` ; dans ce cas utiliser la méthode suivante, ou configurer sa conservation. Un worktree avec un fichier `.git` pointant hors contexte ne fonctionne pas automatiquement.

### `.git` absent du contexte (Dokploy, archive, CI)

Capturer **depuis le checkout complet exact qui sera construit**, avant l'envoi du contexte :

```sh
node frontend/scripts/pwa-release.mjs --base64
```

La sortie est une ligne Base64 de JSON, sans secret. La fournir à l'argument Docker `PWA_RELEASE_METADATA` (pas à une variable runtime nginx). `PWA_RELEASE_REVISION` peut être fourni avec le hash Git complet attendu : toute différence fait échouer la capture. L'argument prend priorité sur Git ; les métadonnées externes sont validées structurellement mais la provenance de l'archive doit être garantie par la CI.

Pour automatiser sans substitutions propres au shell, ce script Node utilise le checkout courant et transmet lui-même les arguments Docker :

```sh
node frontend/scripts/build-pwa-docker.mjs --build-arg CABINET=default -t dentalsoft-frontend .
```

Il sélectionne `docker/frontend/Dockerfile`, capture avant le lancement de Docker et ajoute `PWA_RELEASE_METADATA` et `PWA_RELEASE_REVISION`. Dans Dokploy : utiliser ce script dans la préparation/build personnalisé avec accès Git, ou capturer dans la CI puis renseigner les arguments de build. Une variable d'environnement Dokploy n'est pas automatiquement un `ARG` Docker : vérifier la configuration effective du builder. Le Compose transmet `PWA_RELEASE_METADATA` et `PWA_RELEASE_REVISION` aux deux builds. Ces valeurs doivent être régénérées pour chaque checkout déployé ; ne pas laisser une ancienne capture dans les variables Dokploy.

`frontend/Dockerfile` (déploiement individuel, contexte racine lui aussi) utilise la même capture intermédiaire et accepte les mêmes arguments pour les deux frontends. Ne pas fournir uniquement le hash courant ou le trailer courant : cela ne suffit pas à reconstruire les obligations précédentes.

### Historique shallow et sécurité du déploiement

Une capture Git shallow **échoue volontairement**, même si le commit courant n'a pas de trailer. Ne jamais convertir cet échec en `minimum: null`, ni ignorer l'erreur avec une valeur optional. Le Dockerfile Dokploy exécute `docker/frontend/prepare-pwa-history.sh` avant la capture : en l'absence de `PWA_RELEASE_METADATA`, un clone superficiel est complété avec `git fetch --unshallow --no-tags origin`. Aucun `pull`, checkout ou changement de `HEAD` n'est effectué, même si la branche distante a avancé depuis le clonage. Un checkout complet ou des métadonnées externes ne déclenchent pas de fetch.

Cette préparation nécessite un accès réseau à `origin` depuis le builder. Pour un dépôt privé dont les identifiants Dokploy ne sont pas disponibles dans Docker, compléter l'historique dans la préparation CI/Dokploy et fournir `PWA_RELEASE_METADATA` pour le checkout exact (avec `PWA_RELEASE_REVISION`), sans copier de secrets dans le contexte. Un échec de fetch ou un historique encore incomplet arrête le build. Le script de capture lui-même reste sans accès réseau et refuse toujours l'historique shallow.

Déployer une histoire linéaire first-parent, sans réécriture/rebase des commits déjà publiés. La comparaison par génération n'est pas une preuve d'ascendance sur des branches divergentes partageant la même racine. Un changement de racine avec un seuil non nul est bloquant par prudence. Ne pas alterner plusieurs histoires sur une même origine PWA. Les métadonnées ne sont pas signées : elles font confiance au serveur HTTPS et au pipeline.

Un rollback sous un seuil déjà annoncé peut laisser les clients bloqués : publier de préférence un correctif en avant avec une nouvelle identité conservant le seuil, plutôt que rétrograder le JSON ou l'histoire. Les métadonnées externes et le contenu doivent toujours correspondre ; ne jamais réutiliser la sortie Base64 d'un précédent déploiement.

## Comportement utilisateur et plusieurs onglets

- Vérification au démarrage en production, retour au focus/visibilité, retour réseau et toutes les 60 secondes ; les appels simultanés sont coalescés.
- Dès qu'un minimum incompatible est reçu, l'application routée est démontée et remplacée par une page simple utilisant les variables de thème PrimeVue. Aucun bouton de fermeture. Les nouveaux transports du client HTTP central sont suspendus, y compris une écriture qui attendait encore le calcul de son identité. Les requêtes déjà parties ne peuvent pas être annulées rétroactivement.
- Le bouton **Installer la mise à jour** n'est proposé qu'après identification d'un SW installé/en attente (ou déjà actif dans un autre onglet), dont la génération satisfait le seuil connu. Aucun `SKIP_WAITING` pendant `installing`. Un ancien worker en attente sous le minimum ne devient pas activable.
- Une obligation reste verrouillée dans le document même après une erreur réseau ou une réponse obsolète. Bouton Réessayer et contrôles automatiques continuent ; une erreur d'activation ne déclenche pas de rechargement de secours. Timeout d'activation de 15 secondes puis nouvelle tentative possible. Un worker compatible déjà installé reste activable si `registration.update()` échoue hors ligne. Chaque clic revérifie l'identité du worker et le seuil le plus fort reçu, même si celui-ci vient de changer.
- Un clic active le worker installé. Son changement de contrôleur recharge chaque ancien document une seule fois ; la première prise de contrôle d'un document du même build ne recharge pas. L'activation dans un onglet actualise donc les autres onglets, y compris ceux ayant fermé le bandeau facultatif.
- Les instructions validées circulent via BroadcastChannel et l'événement storage (secours). Avant toute écriture, le seuil en mémoire est fusionné avec celui du stockage ; un événement storage obsolète est réparé pour ne pas abaisser une obligation connue. Le dernier seuil est conservé sous une clé dédiée `dentalsoft-pwa-release-v1:<base-url>` pour résister à un redémarrage hors ligne. Si le stockage est interdit, le verrou reste en mémoire et les contrôles réseau fonctionnent, mais la persistance entre documents n'est pas garantie.
- Aucun `localStorage.clear`, suppression IndexedDB, déconnexion ou effacement des données utilisateur. Les caches Workbox continuent leur cycle normal. **Un formulaire non enregistré en mémoire peut être perdu** lors du blocage/démontage ou de l'actualisation multi-onglets : annoncer les mises à jour obligatoires aux équipes.

Le verrou HTTP couvre `src/shared/api/http.js` côté cabinet et le transport partagé côté patient (sur la version historique d’adoption : `src/service/http.js`), pas des transports autonomes/fetch métier contournant ces clients, ni une opération serveur déjà envoyée. Ce mécanisme frontend est une barrière UX/compatibilité, pas une règle d'autorisation backend opposable à un client modifié.

## Première adoption et hors ligne

Les clients antérieurs à cette fonctionnalité ne connaissent ni le JSON ni le blocage. Ils doivent charger cette première version par leur ancien mécanisme de mise à jour (bandeau ou fermeture/réouverture) avant de pouvoir respecter les prochains seuils. On ne peut pas imposer rétroactivement une fonctionnalité absente de leur JavaScript. Prévoir une première publication d'adoption et vérifier sa diffusion avant de compter sur une obligation.

Sans réseau et sans seuil déjà connu, l'application reste utilisable : elle ne peut pas deviner une obligation distante. Une obligation déjà mémorisée reste bloquante hors ligne, avec réessai, mais ne peut pas télécharger les assets manquants. HTTPS ou localhost et un navigateur compatible service workers sont nécessaires à l'installation ; à défaut une obligation reste bloquante avec erreur, sans boucle de reload.

## Validation et recette

```sh
npm --prefix frontend run test:pwa
node --test frontend/tests/mutationGuard.test.mjs
npm --prefix frontend run build
```

Les tests Node couvrent historique/required→optional, shallow/absence de Git, arguments externes, validation/fetch no-store, répondeur SW et runtime avec workers/réseau simulés (retries, dismissal, worker trop ancien ou pas installé, seuil relevé pendant un échec réseau, coalescence, réparation du stockage obsolète, activation multi-onglets sans boucle).

Une recette navigateur automatisée est également disponible, sans dépendance npm supplémentaire :

```sh
npm --prefix frontend run test:pwa:browser
```

Elle utilise Node 22+ (WebSocket natif) et Chrome ou Edge déjà installé. Sous Windows, les emplacements standards sont détectés ; ailleurs, définir `PWA_BROWSER_EXECUTABLE` avec le chemin absolu du binaire Chromium/Chrome/Edge. Le test réalise deux builds Vite de production avec des identités de fixture (sans créer de commits Git), démarre un serveur localhost temporaire et lance un profil navigateur isolé sous `frontend/node_modules/.cache/`. Il vérifie deux onglets réellement contrôlés par Workbox : blocage non fermable, aucun reload avant clic, erreur réseau réessayable, activation hors ligne d'un SW déjà installé, exactement un reload par onglet et conservation de sentinelles localStorage/sessionStorage. Serveur, navigateur et fichiers temporaires sont supprimés en fin de test. Compter environ une minute ; aucun backend n'est nécessaire. Les CDN de polices/styles de la page sont bloqués dans ce profil pour ne pas dépendre de leurs délais réseau.

Recette navigateur sur HTTPS, avec un profil de test et deux onglets :

1. Installer A (adoption), déployer B avec le trailer required ; vérifier le blocage au focus, puis le bouton après installation du SW.
2. Couper le réseau pendant le téléchargement : UI bloquée, erreur/réessai, aucun reload répété. Revenir en ligne et installer.
3. Vérifier qu'un clic actualise les deux onglets et conserve session/données enregistrées.
4. Déployer C optional, laisser un client A : C conserve le minimum B ; un client B n'est pas bloqué.
5. Vérifier dans Network `release.json` non caché et dans `dist/sw.js` son absence du tableau précaché. Vérifier les en-têtes avec `curl -I https://votre-origine/release.json`, y compris après passage CDN.
6. Vérifier qu'un builder shallow échoue et qu'une capture Base64 issue du checkout complet permet un build sans `.git`.

Les tests automatisés ne remplacent pas la vérification des en-têtes nginx/CDN, la recette sur les appareils réellement utilisés ni un build d'image Docker sur l'infrastructure cible.

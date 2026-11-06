# Audit et durcissement de l'authentification — 6 novembre 2026

## Périmètre et constat principal

Revue des connexions patient et personnel, des contrôles Symfony/JWT, de la validation
des jetons et de la gestion des sessions dans les deux interfaces. Vérifications automatisées
locales ; cet audit ne constitue pas un test d'intrusion de l'infrastructure déployée.

Le portail appelait la connexion commune : un compte administrateur recevait un JWT
valide, puis le navigateur refusait son rôle. Le correctif vérifie désormais l'éligibilité
côté serveur, après vérification du mot de passe et avant émission du JWT.

## Correctifs

- Connexion patient dédiée : `POST /api/login_patient_check`. Compte actif, rôle
  `ROLE_PATIENT`, aucun rôle du personnel. Les comptes mixtes personnel/patient sont refusés.
- Connexion du personnel conservée : `POST /api/login_check`. Rôles admis :
  `ROLE_ADMIN`, `ROLE_MEDECIN`, `ROLE_RECEPTION`, `ROLE_RECEPTIONNISTE`, `ROLE_SECRETAIRE`.
  Les comptes uniquement patients et les comptes sans rôle du personnel sont refusés.
- Réponse identique pour compte inconnu, mauvais mot de passe, compte désactivé et
  mauvais espace : HTTP 401, `{"code":401,"message":"Identifiants incorrects."}`.
  Aucun JWT ni cookie de session n'est émis pour ces refus.
- Limiteur officiel Symfony sur chaque connexion : cinq échecs par identifiant/IP
  et 25 par IP sur cinq minutes ; HTTP 429 en cas de limitation. Les limites des deux
  points de connexion sont indépendantes et ne dépendent pas de l'existence du compte.
- Routes de connexion limitées à POST et expressions de pare-feu bornées au chemin exact.
- `GET /api/token/validate` exige une authentification et ne répond plus HTTP 200
  en l'absence d'utilisateur. L'administration vérifie aussi le statut du corps de réponse.
- Les comptes désactivés restent bloqués lorsqu'ils présentent un JWT déjà émis.
- Réponses de connexion, de profil courant et de validation des jetons non stockables
  par les caches HTTP (`private, no-store`).
- Lecture et journalisation d'activité tolérant l'absence de session sur l'API stateless.
- Restauration patient vérifiée auprès du serveur : les rôles et l'identité en stockage
  navigateur ne servent plus de preuve d'authentification. Un cache JSON corrompu ne bloque
  plus la navigation. Aucun jeton n'est conservé lors d'une connexion partielle ou refusée.
- Réponses tardives de connexion/profil et erreurs 401 protégées contre la restauration
  d'une session après déconnexion ou la déconnexion d'une nouvelle session.

## Dépendances

L'audit Composer a identifié 20 avis sur 12 paquets Symfony existants, dont des avis
concernant les contrôles d'accès HTTP, les chemins et les vérifications après authentification.
Les 12 paquets ont reçu leurs correctifs dans la branche 7.4 : version 7.4.23,
sauf `symfony/mime` en 7.4.22. Ajout de `symfony/rate-limiter` et de sa dépendance
`symfony/options-resolver`. Aucun autre paquet existant n'a changé de version.
L'audit Composer exécuté après résolution ne signale plus d'avis connu.

## Vérifications

- Suite backend complète finale : 81 tests, 406 assertions réussies.
- Dont 20 tests d'authentification, 110 assertions réussies.
  Les tests HTTP utilisent le noyau Symfony et les vrais pare-feu ; fournisseur utilisateur
  et création/parsing des JWT sont simulés pour ne pas dépendre d'une base ou de clés privées.
- Portail : neuf tests de session réussis et compilation de production réussie.
- Administration : huit tests ciblés d'authentification réussis et compilation de production réussie.
- Suite JavaScript administration étendue : un échec préexistant dans
  [report-overview.test.mjs](../frontend/scripts/tests/report-overview.test.mjs), qui attend
  le libellé « Paiements patients » alors que [reportOverview.js](../frontend/src/utils/reportOverview.js)
  expose « Total encaissé ». Les deux fichiers sont identiques à HEAD et hors du périmètre modifié.
- Validation du conteneur Symfony et syntaxe YAML réussies.

## Déploiement et limites

Publier le backend et le portail mis à jour dans la même livraison : l'ancien portail
utilise encore la connexion du personnel, qui refuse désormais les comptes patients.
Exécuter `composer install` depuis le verrou mis à jour, vider/réchauffer le cache Symfony
et reconstruire les deux interfaces. Aucune migration de base n'est requise.

Le limiteur utilise le cache Symfony. Sur plusieurs instances, son stockage et ses verrous
doivent être partagés pour une limite globale. La configuration des reverse proxies de
production doit permettre d'obtenir l'IP réelle sans accepter de faux en-têtes clients.

Les JWT restent stockés dans le navigateur et expirent selon la configuration Lexik.
La déconnexion locale et un changement de mot de passe ne révoquent pas à eux seuls
un JWT copié auparavant ; la désactivation du compte est vérifiée côté serveur.
Une révocation persistante et une migration vers des cookies HttpOnly exigent un chantier
supplémentaire sur le cycle des jetons et la protection CSRF. Aucun de ces mécanismes
n'est présenté comme couvert par cette livraison.

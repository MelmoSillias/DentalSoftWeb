# Authentification et alignement avec Medinote

## Contrats HTTP

| Usage | Cabinet | Patient |
| --- | --- | --- |
| Connexion JSON | POST `/api/auth/login` | POST `/api/patient-portal/auth/login` |
| Renouvellement | POST `/api/auth/refresh` | POST `/api/patient-portal/auth/refresh` |
| Déconnexion | POST `/api/auth/logout` | POST `/api/patient-portal/auth/logout` |
| Profil existant | GET `/api/me` | GET `/api/patient-portal/me/profile` |

Les nouvelles connexions acceptent le contrat de Medinote :

```json
{"email":"personne@example.test","password":"mot-de-passe"}
```

Le champ `email` accepte aussi le username existant. Un email d'employé ou de patient ne permet la connexion que s'il désigne **un seul compte utilisateur lié**. Un email partagé ou entrant en collision avec le username d'un autre utilisateur est refusé. Aucun email n'est attribué automatiquement et les identifiants actuels ne sont pas renommés. Les routes historiques POST `/api/login_check` et `/api/login_patient_check` continuent d'accepter `username` / `password`.

Réponse commune :

```json
{
  "token":"jwt-compatible-clients-actuels",
  "access_token":"meme-jwt",
  "refresh_token":"secret-aleatoire-64-caracteres-hexadecimaux",
  "token_type":"Bearer"
}
```

Le JWT s'envoie dans `Authorization: Bearer <access_token>`. Le refresh et le logout reçoivent uniquement `{"refresh_token":"..."}`. Le refresh retourne une nouvelle paire ; le logout révoque ce refresh et retourne 204. L'alias POST `/api/token/refresh` suit le contrat cabinet de Medinote.

Les en-têtes `X-Device-Id`, `X-Device-Name` et `X-Device-Type` restent supportés. Il faut conserver le même identifiant d'appareil entre login et refresh. Sans `X-Device-Id`, le repli historique dépend du User-Agent et d'Accept-Language ; ce repli n'est pas une preuve matérielle d'identité.

## Cycle de vie

Le JWT expire après une heure ; le refresh après 30 jours. Le secret refresh contient 32 octets aléatoires et seul son SHA-256 est stocké. La table associe le secret haché à l'utilisateur, l'espace, l'empreinte d'appareil, l'empreinte du hash de mot de passe, l'expiration et la révocation.

Le renouvellement vérifie l'utilisateur actif, ses rôles actuels, le mot de passe, l'appareil approuvé et l'espace demandé. La consommation conditionnelle et la création du successeur partagent une transaction : un lecteur concurrent ne peut pas réutiliser l'ancien secret, et une erreur de signature annule la consommation. Une reconnexion crée une session distincte. La révocation d'un refresh ne révoque pas les autres appareils.

La version des credentials inclut les rôles triés : une modification de droits invalide également les sessions existantes. Planifier quotidiennement `php bin/console app:auth:purge-refresh-tokens` pour supprimer les lignes expirées ; cette commande n'interrompt pas les sessions actives.

La déconnexion révoque le refresh présenté. Le JWT déjà délivré reste valide jusqu'à expiration, sauf changement du mot de passe, désactivation ou changement d'espace. Une révocation instantanée de tous les JWT demanderait un registre de sessions/jti supplémentaire. Les anciens frontends effectuent encore une déconnexion locale : ils devront appeler le nouvel endpoint lors de leur migration.

Un changement de mot de passe invalide immédiatement les JWT et refresh existants par comparaison de l'empreinte de credentials. Les modifications de rôles sont revérifiées en base : les droits du JWT ne remplacent pas les droits actuels. Les appareils non approuvés conservent le parcours existant : login possible, accès métier refusé jusqu'à approbation ; aucun refresh métier n'est accordé à un appareil refusé.

## Espaces d'accès

Les JWT portent `realm=app` pour le cabinet et `realm=patient` pour le portail. Les comptes cumulant des rôles cabinet et patient sont refusés. Les routes patient requièrent le rôle patient et le realm patient ; les routes métier cabinet requièrent le realm app et leurs autorisations existantes. Les endpoints personnels communs (`/api/me`, notifications, validation de session) restent accessibles selon les règles existantes aux deux populations.

Les JWT antérieurs à cette refonte, sans realm et version de credentials, sont refusés : une reconnexion est nécessaire au déploiement. Le contrôle s'applique aussi aux URLs historiques, ce qui évite qu'elles contournent la séparation des espaces.

## Limitation, erreurs et confidentialité

Les connexions partagent une limitation de cinq tentatives en cinq minutes par identifiant et adresse IP, avec un plafond de vingt-cinq par IP, et un message d'échec générique. Les URLs historiques et canoniques utilisent le même limiteur. Refresh et logout partagent un quota de trente requêtes par minute et par adresse IP. 400 indique un JSON/champ invalide, 401 un secret invalide/expiré/révoqué ou un espace/appareil différent, 403 un appareil refusé, 429 une limite atteinte. Les sessions ne sont ni mises en cache ni enregistrées dans les receipts d'idempotence.

Le refresh est un secret porteur : ne pas le journaliser ou l'envoyer à un autre domaine. Les clients actuels stockent le JWT dans localStorage ; la prochaine phase doit traiter explicitement le stockage du refresh et l'exposition XSS, ou adopter un cookie HttpOnly avec une politique CSRF adaptée. Aucun cookie de session n'est ajouté implicitement.

## Équivalence et différences avec Medinote

Repris : contrat email/password, Bearer JWT, `access_token` / `refresh_token`, secret haché, expiration et rotation, compte actif, séparation des espaces, ports et implémentations de sécurité.

Adapté : compatibilité `token` / username, espace patient supplémentaire, contrôles d'appareils, atomicité de rotation, invalidation après changement de credentials et rejet des emails ambigus.

Non transposé : comptes `Account`, filtre tenant, opérateurs et realm plateforme, abonnements, UUID et catalogue AccessAudit. DentalSoftWeb reste déployé par cabinet avec ses rôles existants. Une isolation multi-compte demanderait de rattacher et filtrer toutes les données métier ; un changement d'authentification seul ne peut pas la garantir.

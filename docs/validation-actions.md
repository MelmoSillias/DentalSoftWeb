# Protection des validations

Les écritures du client HTTP cabinet (POST, PUT, PATCH, DELETE) partagent une
requête en cas d'appels simultanés identiques. Les boutons de validation affichent
leur spinner pendant l'action et empêchent une seconde validation. Aucun dialogue
d'attente ni blocage global de la navigation n'est ajouté. Les écritures disposent de
60 secondes par défaut (les uploads gardent leur délai étendu). Les lectures restent libres.

Chaque écriture envoie `Idempotency-Key`. Après une erreur réseau, un timeout ou
une erreur serveur, une relance identique réutilise cette clé pendant 24 heures.
Avec HTTPS, la clé est conservée dans sessionStorage pour survivre au rechargement
du même onglet. Seule une empreinte du contenu est stockée, jamais le formulaire
ou le jeton. Sur HTTP sans Web Crypto, la protection des relances reste en mémoire;
les fichiers sont regroupés uniquement tant que la même sélection reste en mémoire.
Après un succès ou un refus métier définitif,
une nouvelle validation constitue une nouvelle action.

Le serveur protège les écritures authentifiées portant cette clé. Un verrou
InnoDB sérialise deux requêtes concurrentes et la réponse réussie est enregistrée
dans la même transaction que les écritures métier. Une relance renvoie la réponse
enregistrée sans réexécuter le contrôleur. Une même clé avec un autre contenu est
refusée (409). Les erreurs annulent la transaction et autorisent une nouvelle
tentative. Les clés sont isolées par utilisateur; les contrôles d'accès restent
exécutés avant toute restitution.

## Déploiement

Appliquer la migration avant de déployer les clients :

```sh
cd backend-reform
php bin/console doctrine:migrations:migrate --no-interaction
```

Les reçus contiennent des réponses métier : appliquer les mêmes règles de
protection que pour la base principale. Programmer quotidiennement la purge :

```sql
DELETE FROM api_mutation_receipt WHERE expires_at < UNIX_TIMESTAMP();
```

## Limites

Les exports en streaming, la maintenance de base et le nettoyage du mode test
ne sont pas conservés comme reçus. Les effets externes (SMS, e-mails, services
tiers) ne sont pas annulables par une transaction SQL. Deux actions distinctes,
deux onglets avec des clés différentes ou un autre client sans clé nécessitent
toujours les contraintes métier habituelles. Aucune relance automatique n'est
effectuée après une erreur réseau : le message invite à vérifier les données.

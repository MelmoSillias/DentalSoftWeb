# Séparation des responsabilités applicatives

Cette étape approfondit le monolithe modulaire sans modifier les routes, les formats de réponse, les tables ou les invariants financiers. Elle extrait du code effectif des services historiques, sans traits ni héritage destinés à masquer leur taille.

## Organisation à suivre

```text
<Contexte>/
  Domain/
    Entity/          Modèle et invariants métier
    Repository/      Contrats de persistance
    Exception/       Violations d'invariants du domaine
  Application/
    Query/           Données d'une demande de lecture
    Command/         Données d'une demande de mutation, lorsque nécessaire
    Handler/         Exécution des lectures et coordination des projections
    UseCase/         Scénarios de mutation et limites de persistance
    ReadModel/       Assemblage des données nécessaires aux projections
    Mapper/          Projection pure de données déjà chargées
    Policy/          Règles de décision réutilisables
    Exception/       Rejets attendus d'un scénario applicatif
    Port/            Contrats applicatifs vers les systèmes externes
    Service/         Collaborateurs ciblés et façades de transition
  Infrastructure/    Adaptateurs Doctrine, cache et intégrations
  Presentation/      Adaptation HTTP et contrôleurs
```

Créer uniquement les sous-sections nécessaires : `Command/` est une convention disponible, pas une obligation de créer un DTO pour chaque tableau historique. Les queries introduites sont des objets de données, les handlers sont des services. Il n'y a ni nouveau bus obligatoire, ni traitement asynchrone implicite. Les commandes console restent dans `Infrastructure/Console`.

### Règles pratiques

- Un contrôleur adapte la requête HTTP et appelle un point d'entrée applicatif ; il ne porte pas les règles métier.
- Un handler de lecture ne modifie pas l'état métier. Un cas d'usage de mutation explicite la validation, les changements, les écritures et les effets secondaires.
- Un mapper reçoit les données dont il a besoin ; il n'interroge pas un repository. Un provider de lecture peut charger et assembler ces données avant leur projection.
- Les règles de décision partagées sont extraites dans des policies. Les invariants intrinsèques aux entités doivent rester dans le domaine ; une policy d'autorisation applicative n'est pas artificiellement déplacée dans `Domain`.
- Les erreurs attendues peuvent être des exceptions typées. Elles ne contiennent pas de code HTTP ; l'adaptateur de compatibilité traduit les rejets en réponses historiques. Les erreurs inattendues ne sont pas absorbées par un `catch` général.
- Les collaborateurs sont injectés par Symfony. Une façade ne construit pas ses handlers, mappers ou cas d'usage et ces derniers ne dépendent pas en retour de la façade.
- Préférer les ports existants à une nouvelle interface générique. Les QueryBuilder doivent rester dans les adaptateurs de persistance ; les couplages historiques conservés doivent être identifiés plutôt que dissimulés.
- Préserver les limites de transaction/flush, l'ordre des validations, la portée médecin, les clés et la durée des caches, ainsi que l'ordre des notifications. Une extraction ne doit pas réécrire ces comportements.

`api/config/services.yaml` exclut `Application/Query/`, `Application/Command/` et `Application/Exception/` de la découverte automatique, comme les DTO et messages existants. Les handlers, cas d'usage, providers, mappers et policies restent autowirés.

## Extractions réalisées

| Contexte | Lectures et projections | Mutations et règles |
| --- | --- | --- |
| Patient | `PatientCollectionQuery`, handlers de collections et d'aperçu, `PatientSummaryProvider`, mappers de résumé et de compte portail | Gestion des comptes portail et policy de périmètre médecin |
| CareDelivery | Query/handler de prescription, mappers de prescription et de facturation | `SaveOrdonnance`, policy de modification des factures |
| Billing | Handlers des comptes, transactions et tableaux croisés, mapper de transaction | Gestion des transactions financières, policies de type et de modification manuelle |
| Inventory | Query d'historique, handler de consommables, mapper de réponses, requête Doctrine `findHistory` derrière le port de repository | Un cas d'usage par création, modification, suppression, retrait et réapprovisionnement ; notifications dédiées et rejets typés |

Les points d'entrée restent `PatientService`, `ConsultationService`, `FinanceService` et `ConsommableService`. Leurs opérations publiques et les réponses consommées par les contrôleurs restent compatibles. Leurs constructeurs évoluent : les instanciations manuelles doivent injecter les nouveaux collaborateurs ; les appels Symfony sont résolus par autowiring.

Le module Inventory est désormais une façade légère. Dans les autres modules, les façades contiennent encore des opérations historiques : elles ne sont pas présentées comme une migration DDD terminée.

## Limites et suite de la migration

- **Patient** : inscriptions et mises à jour, dossier, fichiers, rendez-vous/rappels et orchestration clinique/financière restent à extraire. Voir aussi le [détail du module](../../api/src/Patient/README.md).
- **CareDelivery** : cycle de vie des consultations, requêtes de réception, autorisations et orchestration des mutations de facture restent en partie dans le service.
- **Billing** : charges fixes et modes de paiement restent dans `FinanceService`. Les autres services de facturation et d'assurance ne sont pas découpés dans cette étape. Le cas d'usage de transactions regroupe encore plusieurs mutations financières cohérentes.
- Les extractions Patient et Billing utilisent encore certains repositories Doctrine concrets et des associations intercontextes existantes. La séparation par fichiers ne suffit pas à rendre toutes les couches indépendantes de l'infrastructure.
- Les contextes ClinicalRecord, Scheduling, Communication, IdentityAccess, Settings et Reporting ne font pas l'objet d'un découpage supplémentaire ici. Leurs services volumineux sont des candidats pour des extractions ultérieures par scénario, et non pour un déplacement massif de méthodes sans tests.
- Aucun changement de sécurité, d'encaissement, de paie ou de comportement de validation n'est introduit volontairement. Les comportements historiques risqués ne sont pas corrigés implicitement par ce refactoring.

## Vérification

Depuis `api/` :

```sh
php vendor/phpunit/phpunit/phpunit
php bin/console lint:container --env=test
php bin/console lint:container --env=dev
php bin/console lint:yaml config
```

Les tests couvrent les contrats des façades et les nouveaux composants : projections, périmètres médecin, caches, prescriptions, protections des transactions, erreurs de stock, persistance et notifications. Des tests d'architecture contrôlent également la séparation des mappers et l'absence de dépendance des composants extraits vers les façades.

Les tests unitaires avec doubles de repositories ne valident pas le SQL en production. Les tests d'intégration optionnels ignorés nécessitent toujours leur activation et leur environnement dédié.

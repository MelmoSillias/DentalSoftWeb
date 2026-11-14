# Extraction du tableau de bord dans Focus

## Responsabilités séparées

[DashboardService](../../api/src/Focus/Application/Service/DashboardService.php) prépare les projections `cards`, `carousels` et `tabs` pour l'administration, les médecins et la réception. Il conserve l'assemblage des réponses, les agrégations, les montants calculés par les entités et les règles de présentation. Il ne construit plus de requêtes, n'importe plus Doctrine ou une implémentation de repository, et ne connaît plus le cache Symfony ni le service Reporting concret.

Un tableau de bord est une projection de lecture transversale. Aucun agrégat métier artificiel n'est créé pour porter ces résultats.

| Port Application | Adaptateur Infrastructure | Responsabilité |
| --- | --- | --- |
| [DashboardQueriesInterface](../../api/src/Focus/Application/Port/DashboardQueriesInterface.php) | [DoctrineDashboardQueries](../../api/src/Focus/Infrastructure/Persistence/Doctrine/DoctrineDashboardQueries.php) | Lectures patients, consultations, rendez-vous, factures, paiements, transactions et actes |
| [DashboardReportingInterface](../../api/src/Focus/Application/Port/DashboardReportingInterface.php) | [ReportingDashboardProvider](../../api/src/Focus/Infrastructure/Reporting/ReportingDashboardProvider.php) | Contrat limité avec Reporting : recettes et rapports médecins |
| [DashboardCacheInterface](../../api/src/Focus/Application/Port/DashboardCacheInterface.php) | [SymfonyDashboardCache](../../api/src/Focus/Infrastructure/Cache/SymfonyDashboardCache.php) | Cache des projections et expiration |
| [DashboardClockInterface](../../api/src/Focus/Application/Port/DashboardClockInterface.php) | [SystemDashboardClock](../../api/src/Focus/Infrastructure/Clock/SystemDashboardClock.php) | Heure courante, notamment pour les temps d'attente |

Les ports sont liés aux adaptateurs dans [services.yaml](../../api/config/services.yaml). Le port de lecture expose des nombres, des lignes de projection ou des entités métier ; aucun QueryBuilder ou EntityManager n'est retourné. Les helpers de construction de requêtes restent privés dans l'adaptateur Doctrine. Il ne réalise aucune écriture.

## Contrats préservés

L'URL `GET /api/dashboard/{role}/{type}`, les contrôles d'accès du [contrôleur](../../api/src/Focus/Presentation/Api/Controller/DashboardController.php) et les clés JSON restent identiques. Les tableaux de bord médecin appliquent leur périmètre à toutes les lectures concernées. Les compteurs de consultations payantes distinguent les tickets et les factures d'assurance payantes ; une facture d'actes seule ne rend pas une consultation payante.

Les calculs financiers continuent d'utiliser les méthodes des entités et Reporting. Les périmètres historiques et limites des listes sont conservés : leur harmonisation serait une modification fonctionnelle distincte de l'extraction d'architecture.

## Cache et temps

L'expiration reste de 120 secondes. Les clés distinguent le rôle, l'identité du médecin lorsqu'elle s'applique et les deux bornes temporelles complètes, avec microsecondes et décalage horaire. Deux périodes d'une même journée ne partagent donc plus une clé comme c'était le cas lorsque seul `Ymd` était utilisé.

L'horloge injectable permet de contrôler le temps d'attente sans modifier le système ou rendre les tests dépendants de l'heure réelle. Le cache et l'horloge sont des détails d'infrastructure ; leurs contrats appartiennent à Application.

## Vérification

[DashboardServiceTest](../../api/tests/Focus/DashboardServiceTest.php) utilise un schéma Doctrine dans une base SQLite en mémoire et une horloge fixe. Les quatre scénarios couvrent :

1. Les cartes, leurs montants, les patients supprimés et le périmètre médecin.
2. Les neuf projections de rôles : payloads, graphiques, listes, paiements et rapports.
3. L'isolation du cache entre deux périodes d'une même journée.
4. Le caractère payant d'une consultation assurée et la distinction avec une facture d'actes.

Résultat ciblé : quatre tests et 33 assertions réussis. Une comparaison temporaire avec le service avant extraction a également vérifié l'identité stricte de 12 réponses complètes : les trois sections administration, réception et deux médecins. Le code temporaire de comparaison n'est pas livré avec l'application.

Cette extraction fournit un exemple pour les autres services historiques qui construisent encore leurs requêtes directement. Leurs règles métier et transactions doivent être vérifiées séparément, plutôt que déplacées aveuglément.

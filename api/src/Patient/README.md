# Architecture du module Patient

Le module regroupe les données et les opérations de gestion des patients. Sa
couche application sépare progressivement les lectures, les projections de
réponse et les cas d’usage, tout en conservant `PatientService` comme point
d’entrée pour les contrôleurs et les autres modules.

## Organisation

| Composant | Responsabilité |
| --- | --- |
| `Application/Service/PatientService.php` | Façade de compatibilité et orchestration des opérations qui n’ont pas encore été extraites. |
| `Application/Query/PatientCollectionQuery.php` | Requête de lecture immuable : utilisateur, périmètre médecin, pagination, recherche et tri. |
| `Application/Handler/PatientCollectionHandler.php` | Lecture des collections actives ou supprimées, pagination et cache des collections actives paginées. |
| `Application/Handler/PatientOverviewHandler.php` | Calcul des indicateurs de synthèse et répartition des sources de référencement. |
| `Application/Policy/PatientReadScopePolicy.php` | Application du paramétrage du périmètre médecin et résolution de l’employé associé à l’utilisateur. |
| `Application/ReadModel/PatientSummaryProvider.php` | Assemblage des résumés : lecture des soldes impayés, sélection de la dernière consultation clôturée et orchestration de la projection. |
| `Application/Mapper/PatientSummaryMapper.php` | Projection pure des résumés à partir du patient, de la consultation et du solde fournis explicitement ; projections utilitaires de la photo, des préférences SMS et de l’assurance. |
| `Application/Mapper/PortalAccountMapper.php` | Projection des informations du compte portail. |
| `Application/UseCase/ManagePatientPortalAccounts.php` | Consultation, création unitaire ou en lot, réinitialisation du mot de passe et activation des comptes portail. |
| `Domain/` | Entités, énumérations et contrats de dépôt du module. |
| `Infrastructure/Persistence/Doctrine/` | Dépôts de persistance Doctrine. |
| `Presentation/Api/Controller/` | Adaptation des requêtes HTTP et des réponses de l’application. |

## Injection des dépendances

`PatientService` reçoit ses handlers, ses mappers, `PatientSummaryProvider` et le
cas d’usage des comptes portail comme dépendances obligatoires de son constructeur. Il ne les construit
pas lui-même. Symfony assemble ces services par autowiring ; aucun accès au
conteneur ni mécanisme de repli n’est nécessaire dans la façade.

Les handlers de lecture reçoivent directement `PatientReadScopePolicy`.
`PatientCollectionHandler` reçoit `PatientSummaryProvider` pour assembler les
résumés des collections. Le provider dépend des dépôts de consultations et de
factures ainsi que du mapper. Il porte les lectures et la règle de repli vers
la consultation clôturée référencée par le patient.

`PatientSummaryMapper` ne possède aucune dépendance de dépôt et ne sélectionne
pas de consultation. Sa méthode `formatPatientSummary()` exige trois entrées :
le patient, la consultation sélectionnée (ou `null`) et le montant des impayés.
Une consultation explicitement absente reste absente dans la réponse, même si
le patient référence une consultation. Le provider, et non le mapper, décide de
la sélection et du montant. La façade réutilise directement les projections
utilitaires pures pour ses réponses de détail et de dossier.

`ManagePatientPortalAccounts` reçoit `PortalAccountMapper`, les dépôts, le
gestionnaire d’entités, le hasher et le cache dont il a besoin. Les composants
extraits ne dépendent pas de `PatientService`.

`PatientCollectionQuery` est un objet de données créé pour chaque opération de
lecture, et non un service. Les répertoires `Application/Query/` sont exclus de
la découverte automatique des services dans la configuration globale.

## Contrats et comportements

Les signatures des opérations publiques de `PatientService` et leurs formats de
réponse restent compatibles avec les appelants existants. Cette compatibilité ne
s’étend pas à son constructeur : une instanciation manuelle doit fournir les
collaborateurs applicatifs requis.

- Les réponses conservent leurs clés, messages et champs de statut. Les échecs
  attendus des opérations extraites restent représentés par les tableaux de
  réponse existants.
- Les collections actives paginées utilisent un cache de 60 secondes, avec des
  clés distinctes pour le périmètre global et chaque médecin. La liste des
  patients supprimés n’utilise pas ce cache.
- Pour une collection non vide, le provider charge les impayés en une seule
  requête de dépôt et conserve les clés et l’ordre des patients lors de
  l’assemblage. Un solde absent vaut zéro. Une collection vide ne déclenche
  aucune lecture de solde ni de consultation.
- La sélection de la dernière consultation reste effectuée pour chaque patient.
  La référence portée par le patient sert de repli uniquement si elle est
  clôturée. Une lecture de consultation seule, notamment pour les détails,
  ne charge pas les soldes.
- Le paramétrage autorisant les médecins à créer patients et consultations peut
  élargir leur périmètre de lecture. La policy est partagée entre les listes et
  les indicateurs de synthèse.
- `ensurePatientPortalAccount()` prépare un compte sans effectuer de `flush`,
  afin de participer à la création d’un patient. Les opérations autonomes du
  cas d’usage conservent leurs propres frontières de persistance.

## Périmètre restant dans la façade

La séparation est progressive. L’inscription et la modification des patients,
la suppression et la restauration, l’orchestration des consultations, les
rendez-vous et rappels, la recherche, les fichiers et photos, les modifications
du dossier médical et de l’assurance, les antécédents et allergies, les
notifications et les projections d’impression restent dans `PatientService`.
Les composants extraits utilisent encore les adaptateurs de dépôt existants.

La création des comptes portail conserve le mot de passe initial `123` et son
champ de réponse historique. Une évolution de cette politique de sécurité
nécessite un changement fonctionnel distinct. La vérification des collisions
d’identifiants reste fondée sur une boucle de recherche en dépôt ; elle ne
constitue pas, à elle seule, une garantie contre les créations concurrentes.

## Tests et vérifications

Depuis le répertoire `api/` :

```sh
php vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Patient
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
php bin/console lint:container --env=dev
php bin/console lint:container --env=test
```

`PatientServiceRegressionTest` injecte les composants applicatifs réels dans la
façade et remplace leurs dépendances de persistance par des doubles de test. Il
couvre notamment la pagination, le cache, les restrictions médecin, les
indicateurs, la corbeille et les opérations sur les comptes portail.

`PatientSummaryMapperTest` vérifie les projections de réponse, les préférences
SMS, l’assurance et le respect des données explicitement fournies, sans dépôt
ni double de persistance.

`PatientSummaryProviderTest` vérifie séparément le chargement des impayés en lot,
la conservation des clés, les soldes absents, la sélection des consultations et
les règles de repli. Le provider est construit avec le mapper réel et des
doubles de dépôt.

Les tests unitaires du module utilisent un cache en mémoire ; ils ne vérifient
pas l’exécution du SQL sur une base de données. La validation du conteneur vérifie
séparément la cohérence de l’injection Symfony.

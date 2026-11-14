# Comparaison rapide avant bascule

Validation locale le 14 novembre 2026, avec PHP 8.2.12 et PHPUnit 9.6.36.
Référence historique : `0a33f416674e0f31975ab52a7c4303a913fb36c4`.
L’authentification a volontairement changé et n’est pas soumise à une exigence d’équivalence.

| Suite | Tests | Assertions | Ignorés | Erreurs / échecs |
| --- | ---: | ---: | ---: | ---: |
| `backend-reform` historique, hors auth | 111 | 526 | 2 | 0 / 0 |
| `api` complète | 326 | 5090 | 2 | 0 / 0 |

Les 111 cas historiques hors auth sont conservés, avec les mêmes nombres d’assertions par classe. Les copies de validation utilisent les mêmes dépendances Composer, des bases SQLite en mémoire et des transports neutralisés ; aucune base de cabinet n’a été utilisée. Les deux tests ignorés nécessitent explicitement `MUTATION_TEST_MYSQL=1` et vérifient l’idempotence transactionnelle MySQL. Ils restent à exécuter dans un environnement MySQL isolé.

Commande PHPUnit dans chaque copie isolée :

```sh
php vendor/phpunit/phpunit/phpunit -c phpunit.validation.xml --do-not-cache-result --verbose --log-junit results.xml
```

Les artefacts détaillés locaux, ignorés par Git, sont dans `archive/local/test-comparison-20261114/`.

## Frontends

- Cabinet : `npm run validate` (architecture, isolation, tests, lint et build PWA), puis `npm run test:pwa:browser`.
- Patient : `npm run validate` (architecture, tests et build PWA), puis `npm run test:pwa:browser`.
- Les recettes Chrome PWA utilisent deux onglets et vérifient le blocage, l’activation sur clic d’un worker téléchargé même hors ligne, un rechargement par onglet et la conservation du stockage.

Ces vérifications ne prouvent pas l’équivalence de toutes les données métier. La recette avec API et données de test représentatives, les tests MySQL ignorés, le build des images et la vérification des en-têtes nginx/CDN restent nécessaires avant une généralisation. Docker n’est pas installé sur le poste de validation.

# API DentalSoftWeb

Monolithe modulaire Symfony 7.4 / PHP 8.2, inspiré de l'architecture de Medinote. Voir l'[extraction des architectures](../docs/architecture/01-analyse-existant.md), le [modèle backend](../docs/architecture/02-backend-cible.md), la [migration des routes](../docs/architecture/03-routes-api.md), l'[authentification](../docs/architecture/04-authentification.md) et l'[analyse frontend](../docs/architecture/05-refonte-frontend.md).

Exemple d'extraction complète d'un service de lecture : [tableau de bord Focus](../docs/architecture/06-focus-dashboard.md).

[Résultats de validation et mise en service](../docs/architecture/07-validation.md).

[Découpage applicatif DDD : queries, handlers, cas d’usage et exceptions](../docs/architecture/08-separation-responsabilites.md).

Depuis ce dossier :

```powershell
composer install
php bin/console lexik:jwt:generate-keypair --skip-if-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console lint:container
php bin/console doctrine:schema:validate --skip-sync
php vendor/phpunit/phpunit/phpunit
```

Configurer les secrets, la connexion MariaDB et Mercure dans un fichier `.env.local` non versionné. Réutiliser les clés, volumes et données de l'instance existante. Ne pas charger les fixtures sur une base de cabinet existante.

Les anciennes URLs sont conservées pendant la transition ; les nouvelles ressources sont déclarées dans [resources.yaml](config/routes/resources.yaml). Le [registre JSON](docs/route-migration.json) permet de préparer les clients. Les JWT antérieurs à la refonte nécessitent une reconnexion. La migration ajoute une table de sessions renouvelables sans renommer les tables métier.

# Extraction des architectures — DentalSoftWeb et Medinote

Analyse du 14 novembre 2026, à partir des sources locales des deux dépôts.

Révisions de référence : DentalSoftWeb `0a33f416674e0f31975ab52a7c4303a913fb36c4` ; Medinote `97fe72874e77c47ae319042c02fb476d5ff7a3e6`.

## DentalSoftWeb avant refonte

Un monolithe Symfony 7.4 / PHP 8.2, Doctrine ORM 3 et MariaDB, dans `backend-reform/`, expose une API consommée par deux applications Vue 3 / Pinia : le cabinet (`frontend/`) et le patient (`patient-frontend/`). Mercure, Messenger, SMS et Web Push complètent le backend. Le déploiement Dokploy isole chaque cabinet dans une stack, avec ses propres données et clés JWT.

Les contextes métier existent déjà : Patient, ClinicalRecord, CareDelivery, Scheduling, Billing, Inventory, IdentityAccess, Communication, Settings, Reporting et Focus. Leur découpage technique est horizontal : Controller, Service, Entity et Repository. Les services combinent orchestration métier, requêtes Doctrine, accès HTTP et intégrations. Les relations Doctrine traversent les contextes : déplacer les fichiers ne supprime pas ce couplage.

Les routes mélangent français et anglais, singulier et pluriel, ressources et verbes (`/patient/add`, `/patient/{id}/update`, `/rdv/create`). Des mutations utilisent POST même lorsqu'elles modifient une ressource. Les représentations JSON et codes d'erreur varient selon les fonctionnalités. Les contrôles d'accès sont répartis entre firewalls, voters, contrôleurs et services.

L'identité persistée est le `username`. Les employés peuvent avoir un email, mais celui-ci ne constitue pas un identifiant unique de connexion. Le login cabinet et le login patient ont des vérifications de rôles distinctes. Les utilisateurs désactivés sont refusés. Les JWT sont utilisés avec une liste d'appareils approuvés ; les deux clients lisent le champ `token`. Aucun renouvellement persistant n'existait.

Le frontend cabinet est organisé en `views`, `components`, `service`, `stores`, `composables`, `utils` et `router`. Le portail patient possède sa propre authentification et son propre client HTTP. Les écrans, le temps réel, le stockage local et les effets de session sont liés aux stores globaux.

## Modèle extrait de Medinote

Sources examinées : `docs/architecture.md`, `api/config/{services,routes}.yaml`, `api/config/packages/security.yaml`, les contextes de `api/src/`, `frontend/src/domains/`, `frontend/src/lib/axios.js` et les stores d'authentification.

Medinote utilise un monolithe modulaire Symfony avec quatre couches dans chaque contexte :

| Couche | Responsabilités observées |
| --- | --- |
| Domain | Entités, objets valeur, événements, contrats de repositories |
| Application | Cas d'utilisation, handlers de commandes/requêtes, DTO et services d'orchestration |
| Infrastructure | Implémentations Doctrine, intégrations, stockage et sécurité technique |
| Presentation | Contrôleurs HTTP et adaptation de requêtes/réponses |

Les interfaces de repositories sont liées aux implémentations Doctrine dans le conteneur Symfony. Un SharedKernel contient les primitives partagées. Le domaine conserve des attributs ORM : il s'agit donc d'un DDD pragmatique, pas d'un domaine complètement indépendant de Doctrine.

L'authentification Medinote repose sur Lexik JWT, une connexion JSON par `email`, un contrôle des comptes actifs et des refresh tokens aléatoires, stockés sous forme de SHA-256, expirables et révocables. Le renouvellement consomme l'ancien token et produit une nouvelle paire. Les claims JWT séparent `realm=app` et `realm=platform`. Un compte `Account` et un filtre Doctrine isolent les tenants ; les opérateurs plateforme ont des utilisateurs distincts. Les permissions fines sont gérées par AccessAudit. Le frontend utilise `domains/<métier>/{views,components,services,stores,composables}`, un transport partagé, un shell et une configuration centrale.

Points à ne pas reproduire aveuglément : la rotation de refresh doit être atomique ; l'utilisateur actif et son espace doivent être revérifiés au renouvellement ; un email d'employé n'est pas automatiquement une identité de connexion ; la présence d'un claim ne crée pas une isolation multi-tenant ; les routes de Medinote ne sont pas toutes des ressources REST (`login_check` reste un endpoint technique).

## Décisions pour DentalSoftWeb

1. Déplacer le backend dans `api/`, avec une seule application Symfony et une seule base par instance.
2. Conserver les contextes dentaires et les contrats de données existants ; appliquer les quatre couches et introduire des ports de persistance pour les cas d'utilisation extraits.
3. Ajouter des routes canoniques orientées ressources et conserver temporairement les anciennes routes sur les mêmes contrôleurs. La sécurité doit être identique pour les deux chemins.
4. Aligner le cycle JWT / refresh / révocation et distinguer les espaces cabinet et patient, en conservant les règles d'appareils de DentalSoftWeb.
5. Documenter séparément l'isolation multi-compte et les différences de modèle d'identité. Ne pas présenter une instance cabinet comme une plateforme SaaS multi-tenant.
6. Préparer la migration du frontend par domaines sans déplacer ses écrans pendant cette phase.

Suite : [architecture backend](02-backend-cible.md), [migration des routes](03-routes-api.md), [authentification](04-authentification.md), [analyse frontend](05-refonte-frontend.md).

# Analyse documentée pour la prochaine refonte frontend

Cette phase prépare la refonte ; les écrans et stores des deux applications ne sont pas déplacés.

## Modèle observé et écarts

Medinote regroupe le code fonctionnel dans `src/domains/<métier>/` : vues, composants, services, stores, composables et utilitaires locaux. Son shell, le routeur, le client Axios et la configuration restent transversaux. Le domaine auth centralise la session ; le domaine access gère les permissions. Les composants de tableaux et CRUD génériques ne doivent accueillir que les comportements réellement communs.

DentalSoftWeb cabinet utilise encore des répertoires techniques globaux. [auth.js](../../frontend/src/stores/auth.js) coordonne les tokens, les appareils, les notifications et le temps réel ; [http.js](../../frontend/src/service/http.js) combine transport, gestion des erreurs et garde d'idempotence. Les vues patients, soins, caisse, agenda, RH et rapports appellent des services partagés. Le portail possède [son store](../../patient-frontend/src/stores/auth.js) et un transport distinct. Les deux clients attendent `token` et ne consomment pas encore le refresh. Le mode hors Internet, le réseau local, l'impression, Mercure, Web Push et les pièces jointes sont des contraintes de migration à conserver.

## Architecture cible

```text
frontend/src/
  app/                    Bootstrap, router, shell, configuration, providers
  domains/
    auth/                 Login, profil, session, appareils côté utilisateur
    patient/              Liste, identité, dossier, allergies, antécédents
    clinical-record/      Entretien, examens, bilans, documents médicaux
    care-delivery/        Consultations, actes, prescriptions et soins
    scheduling/           Agenda, rendez-vous, salles, congés
    billing/              Caisse, devis, factures, paiements, assurances
    workforce/            Employés, paie et INPS
    inventory/            Consommables et stocks
    communication/        Notifications, SMS, messagerie, modération
    reporting/            Rapports et graphiques
    focus/                Poste de réception et projection temps réel
    settings/             Réglages et maintenance
    docs/                 Manuel et documentation d'utilisation
  shared/
    api/                  Transport, problèmes HTTP, idempotence
    realtime/             Connexion Mercure, reconnexion, Web Push
    ui/                   Primitives visuelles et tableaux sans métier
    print/                Socle d'impression
    lib/                  Dates et autres fonctions sans dépendance métier
```

Chaque domaine contient `views`, `components`, `services`, `stores`, `composables` et éventuellement `model` seulement s'ils sont utiles. Ne pas créer des dossiers vides ni imposer une traduction complète des noms métier internes. Les URLs publiques de navigation et les noms de routes Vue peuvent être conservés pendant le déplacement.

Le portail patient garde son application et sa livraison propres. Il peut reprendre le même découpage (`auth`, `patient-portal`, `communication`, `shared`) sans importer directement les stores cabinet. Une bibliothèque commune ne se justifie qu'après identification de contrats effectivement partagés.

## Correspondance de migration

| Sources actuelles cabinet | Cible | Vigilance |
| --- | --- | --- |
| `views/patients`, store patient, services patient | `domains/patient` | Dossier, pièces jointes et liens profonds |
| `views/consultations`, formulaires cliniques | `domains/care-delivery` et `domains/clinical-record` | Frontière dossier médical / acte de soin |
| `views/caisse`, services factures/assurances | `domains/billing` | Arrondis, reliquats, idempotence, impression |
| `views/agenda`, services RDV/salles/congés | `domains/scheduling` | Dates, fuseau, droits médecin |
| Vues RH et paie sous administration | `domains/workforce` | Règles INPS et informations sensibles |
| Vues consommables | `domains/inventory` | Mouvements et stock négatif |
| `views/vitrine`, notifications et SMS | `domains/communication` | Voters, confidentialité et push |
| `views/rapport`, utilitaires de rapports | `domains/reporting` | Précision des totaux et export |
| `views/focus`, DTO client et publications | `domains/focus` | Rafraîchissement, sélection et reconnexion |
| `views/manual` | `domains/docs` | Ancres et aides contextuelles |
| Store auth et écrans de connexion/profil | `domains/auth` | Course login/logout et blocage appareil |
| Transport HTTP, mutations et réseau | `shared/api`, `shared/lib` | Ne jamais rejouer aveuglément une mutation |

## Contrats entre domaines

Les vues et composants ne construisent plus directement les URLs d'API : ils appellent un service de leur domaine. Les services adaptent les DTO historiques vers les modèles d'écran ; ils utilisent le [registre des routes backend](../../api/docs/route-migration.json). Un store appartient à un domaine, et n'est pas importé par le transport HTTP. Le transport reçoit les credentials par un provider injecté ; les événements de session sont orchestrés par le bootstrap pour éviter le cycle store auth → HTTP → store auth.

Les imports entre domaines passent par des points d'entrée explicites, pas par leurs composants internes. Les éléments `shared` restent sans métier dentaire. Les autorisations UI servent à guider l'utilisateur ; le backend reste responsable de l'autorisation réelle. Les rôles DentalSoftWeb ne doivent pas être remplacés par le catalogue Medinote sans une migration métier dédiée.

## Session cible

1. Lire la paire `access_token` / `refresh_token` et vérifier le profil avant d'afficher une session restaurée.
2. Mettre en place un renouvellement unique partagé entre requêtes simultanées. Une seule rotation doit être envoyée ; les autres requêtes attendent son résultat.
3. Conserver une version de session pour empêcher qu'un refresh, login ou chargement du profil terminé tardivement restaure une session après logout.
4. Au logout, révoquer le refresh et effacer identité, caches privés, abonnements Mercure et Web Push. Une erreur réseau ne doit pas bloquer l'effacement local.
5. Ne pas traiter un refus d'appareil comme une expiration de JWT. Ne pas retenter le refresh sur 403 ni en boucle sur 401.
6. Réserver le retry automatique aux lectures. Une mutation ne peut être relancée qu'avec son corps, sa clé d'idempotence et son URL d'origine conservés, après vérification du contrat backend.

Le choix de stockage des refresh est à traiter avant activation : un cookie HttpOnly implique CORS/credentials et CSRF ; un stockage accessible à JavaScript implique une exposition XSS. Les fronts cabinet et patient ne partagent jamais leurs jetons ni leurs clés de stockage.

## Ordre d'exécution proposé

1. Cartographier les appels HTTP et les imports, figer les parcours critiques et ajouter des règles ESLint de dépendances.
2. Extraire le transport et la session, adopter les endpoints auth canoniques et tester les courses de session, l'expiration et la révocation.
3. Migrer un domaine pilote à faible couplage (Inventory), puis Patient et Scheduling. Conserver temporairement des réexports pour les imports non migrés.
4. Migrer ClinicalRecord et CareDelivery, puis Billing et Workforce avec vérification des invariants financiers et des impressions.
5. Migrer Communication, Focus et Reporting, puis le portail patient, en vérifiant les abonnements et les liens de notifications.
6. Retirer les réexports et anciennes routes API après observation et annonce de leur retrait.

## Critères d'acceptation

Build et lint des deux applications ; vérification des connexions cabinet/patient, restauration, changement de mot de passe, expiration, logout concurrent et appareils refusés ; parcours patient → consultation → facture → paiement → reçu ; dossier médical et uploads ; agenda médecin ; paie/INPS ; mode local sans Internet ; impression ; notifications et reconnexion Mercure ; absence de données du précédent utilisateur après changement de session. La migration ne doit pas modifier les calculs de caisse ou de paie par effet de déplacement.

# Feuille de route : déploiement individuel vers Docker Compose

Objectif : migrer **un cabinet à la fois**, avec sa base complète, ses fichiers et ses secrets, vers [la stack Compose](../docker-compose.dokploy.yml). Conserver les anciennes installations et leurs données pour permettre un retour arrière.

Ce document est adapté au code du dépôt. Les noms des conteneurs, les montages et la version de la base réellement en production restent à relever sur le serveur. Aucune migration serveur n'a été exécutée pour préparer cette feuille de route.

## 1. Stratégie et conditions de réussite

Préparer une nouvelle stack avec des volumes distincts, répéter la restauration, puis prévoir une fenêtre de maintenance pour une dernière sauvegarde cohérente base + fichiers et la bascule des domaines. Les anciens services restent disponibles pour restauration, mais sans recevoir de nouvelles écritures après la bascule.

Ne pas réutiliser le SQL allégé précédemment : les données de `notification`, `sms_log`, `sms_queue` et `user_device_access_log` en ont été retirées. Pour conserver toutes les données, refaire un **export intégral de la base de production**, sans nettoyage ni procédure de suppression globale ajoutée. Ne pas importer ce SQL allégé sur la production existante.

La copie initiale sert à répéter la migration. Seule la copie finale, prise après suspension des écritures, sert à la bascule. Une sauvegarde SQL seule ne contient pas les fichiers joints.

## 2. Inventaire de la source — avant tout changement

Compléter cette fiche pour chaque cabinet, sans y inscrire les valeurs des secrets :

| Élément | Valeur à relever |
|---|---|
| Cabinet / branding | Identifiant existant et configuration patient correspondante |
| Code actuellement déployé | Commit ou image exacte, version PHP |
| Base | Hôte, nom, moteur et version exacte, taille, moteurs des tables |
| Applications | API, admin, portail patient et éventuels clients externes |
| Tâches | Workers embarqués ou séparés, cron, sauvegardes, intégrations entrantes |
| Fichiers | Chemins dans l'API, montages réels, tailles et nombre de fichiers |
| Secrets | Emplacement sécurisé de l'environnement, des clés JWT et VAPID |
| Domaines | Domaines admin, patient, API et Mercure, routage Dokploy actuel |
| Messagerie | Transport Messenger réel : Doctrine, Redis, RabbitMQ ou autre |
| Dépendances externes | SMTP, SMS, stockage externe, webhooks, sauvegardes distantes |

Examiner les fichiers **dans le conteneur API existant**, même si aucun volume n'est déclaré : des pièces jointes ou des clés peuvent être uniquement dans sa couche inscriptible. Les extraire avant tout remplacement ou suppression de ce conteneur.

Identifier également les chemins personnalisés et les liens symboliques. Les exemples de l'ancien guide ne prouvent pas que les montages du serveur sont identiques.

**Validation :** chaque donnée persistante a une source identifiée, une destination et une sauvegarde prévue.

## 3. Correspondance des données et des volumes

Les chemins de destination ci-dessous sont ceux du conteneur API de la nouvelle stack, et non des chemins à créer sur la machine locale.

| Données source | Destination | Volume Compose / nom effectif |
|---|---|---|
| Base complète du cabinet | Base du service `db` | `db_data` → `dentalsoft-${INSTANCE_ID}-db` |
| `public/uploads/` | `/app/public/uploads/` | `uploads` → `dentalsoft-${INSTANCE_ID}-uploads` |
| `public/upload_files/` | `/app/public/upload_files/` | `upload_files` → `dentalsoft-${INSTANCE_ID}-upload-files` |
| `public/assurances/` | `/app/public/assurances/` | `assurance_files` → `dentalsoft-${INSTANCE_ID}-assurances` |
| `var/uploads/` | `/app/var/uploads/` | `private_uploads` → `dentalsoft-${INSTANCE_ID}-private-uploads` |
| `var/backups/` | `/app/var/backups/` | `backups` → `dentalsoft-${INSTANCE_ID}-backups` |
| Clés JWT effectivement utilisées | `/app/config/jwt/private.pem` et `public.pem` | `jwt_keys` → `dentalsoft-${INSTANCE_ID}-jwt` |
| Historique du hub Mercure | À inventorier et archiver séparément | Nouvelle destination : `mercure_data` → `dentalsoft-${INSTANCE_ID}-mercure` |

Conserver les arborescences et noms de fichiers. Vérifier aussi les chemins stockés dans la base : si certains sont des URL absolues vers l'ancienne API, prévoir leur maintien ou une conversion ciblée, testée et sauvegardée. Éviter un remplacement global dans le SQL.

Pour MariaDB, privilégier un export/import logique. Ne pas recopier à chaud le répertoire physique de l'ancienne base vers le nouveau volume, particulièrement si les versions diffèrent.

Si Mercure est partagé entre plusieurs cabinets, ne pas recopier son stockage partagé dans la stack d'un seul cabinet. Conserver son archive et l'ancien hub pour les autres cabinets. Un nouveau hub peut démarrer sans reprendre les événements passés : cette différence doit être acceptée explicitement si leur historique doit aussi être disponible en ligne. Les notifications applicatives stockées en base sont, elles, conservées par l'export intégral.

Tout stockage métier découvert hors de ces chemins doit recevoir un montage persistant supplémentaire avant la migration.

## 4. Préparer la configuration cible

Partir du [modèle d'environnement](../.env.dokploy.example), mais appliquer ces règles de **migration**, différentes d'une installation vierge :

| Variable / secret | Action |
|---|---|
| `INSTANCE_ID` | Choisir un ID unique et stable, par exemple `cdos-prod-compose`. Ne pas reprendre celui d'une démo contenant déjà des données. Le changer ensuite sélectionne d'autres volumes. |
| `CABINET` | Reprendre le branding existant, disponible dans les deux frontends. |
| `ADMIN_URL`, `PATIENT_URL` | Origines HTTPS sans slash final ni chemin. Utiliser des domaines de validation pendant la répétition, puis les domaines définitifs lors de la bascule. |
| `APP_SECRET` | **Conserver exactement l'ancien.** Le chiffrement des identifiants SMS en dépend dans [CryptoService](../backend-reform/src/Communication/Service/CryptoService.php). |
| Clés JWT + `JWT_PASSPHRASE` | Copier la paire réellement utilisée et reprendre sa passphrase. Ne pas laisser générer une nouvelle paire par inadvertance. |
| `VAPID_*` | Conserver les clés et le sujet pour les abonnements Web Push existants. Les permissions navigateur restent liées au domaine. |
| `MERCURE_JWT_SECRET` | Reprendre le secret du cabinet pour la bascule ; API et nouveau hub doivent correspondre. |
| `MERCURE_TOPIC_NAMESPACE` | Reprendre le namespace existant du cabinet. Le changement d'URL du hub peut néanmoins changer les topics et nécessite une reconnexion des clients. |
| `DB_NAME`, `DB_USER` | Choisir les valeurs cibles et importer explicitement dans cette base. |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | Des nouveaux mots de passe sont possibles pour la nouvelle base ; conserver ceux de la source pour le retour arrière. |
| `RUN_MIGRATIONS` | Mettre `0` pendant l'import et l'examen des migrations. |
| `ENABLE_EMBEDDED_WORKER` | Mettre `0` pendant la restauration et les validations sans envoi. |

L'[entrypoint Compose](../backend-reform/docker/entrypoint.dokploy.sh) peut générer les clés JWT et lancer les migrations et les workers au démarrage. **Ne pas démarrer l'API avant d'avoir restauré les données et la paire JWT.** Si la stack a déjà démarré, arrêter l'API avant de restaurer les deux clés ensemble et les fichiers.

Une variable ajoutée dans l'environnement Dokploy n'est pas automatiquement transmise à tous les conteneurs : vérifier la section `environment` de chaque service. Le Compose actuel ne transmet notamment pas `MAILER_DSN`. Si la production utilise le mail ou d'autres variables absentes, compléter le Compose avant la bascule. Ne pas committer les valeurs des secrets.

Le transport Messenger cible est Doctrine par défaut. Si la source utilise un autre transport, définir une stratégie de transfert ou de drainage de ses messages : son contenu ne sera pas inclus dans le dump MariaDB.

**Validation :** configuration complète, secrets récupérés, volumes distincts et aucun envoi réel possible depuis la copie de répétition.

## 5. Faire une sauvegarde complète et répéter la restauration

1. Sauvegarder l'environnement, les clés et les réglages Dokploy dans un emplacement protégé.
2. Exporter toute la base, schéma et données, y compris `doctrine_migration_versions`, les journaux, les notifications et les files d'attente. Inclure les routines, événements et triggers s'il en existe ; relever leurs comptes `DEFINER` et les droits nécessaires à la restauration.
3. Archiver chacun des répertoires du tableau, ainsi que les éventuels stockages supplémentaires. Copier les archives et le SQL hors du serveur de production.
4. Produire un manifeste des fichiers : chemin relatif, taille et empreinte SHA-256. Conserver aussi le nombre de lignes par table et les totaux métier de référence.
5. Construire les images de la nouvelle stack à l'avance, avec la correction des URL `/api/api` incluse.
6. Initialiser uniquement la base et Mercure cibles ; laisser API et frontends arrêtés. Préparer le démarrage sélectif des services dans la méthode d'exploitation retenue avant le jour de la migration.
7. Importer le dump complet dans la base cible dédiée. Contrôler l'absence d'erreurs et la présence des tables et données attendues. Aucun nettoyage préalable global de la base source n'est nécessaire.
8. Restaurer les fichiers et la paire JWT dans les volumes cibles, avant démarrage de l'API. Vérifier lecture et écriture par l'utilisateur `www-data`, y compris dans les sous-dossiers ; conserver des droits restreints sur les clés.
9. Examiner les migrations en attente sur la copie et leur SQL. Sauvegarder cette copie avant de les appliquer ; traiter toute suppression ou transformation de données avant de poursuivre. Ne pas utiliser de fixtures ni de mise à jour forcée du schéma pour initialiser les données.
10. Démarrer l'API et les frontends avec les workers désactivés. Protéger les domaines de validation et bloquer aussi les envois ou intégrations déclenchables directement par une requête HTTP : désactiver les workers seuls ne suffit pas.
11. Exécuter les contrôles de la section 7 et mesurer la durée réelle de l'import, de la copie et des migrations.

Pour les tables transactionnelles, `--single-transaction` est une option de sauvegarde cohérente ; elle ne couvre pas de la même manière les tables non transactionnelles et suppose l'absence de modifications de schéma pendant le dump. La sauvegarde finale sera donc réalisée après suspension de toutes les écritures. Utiliser un client MariaDB compatible avec le dump et vérifier son code de sortie. Voir la [documentation de mariadb-dump](https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump).

**Validation :** une restauration complète a réussi sur des volumes séparés. La durée de maintenance est estimée à partir de cette répétition, avec marge ; elle ne doit pas être promise avant mesure.

## 6. Bascule finale — fenêtre de maintenance

1. Informer le cabinet et demander la fermeture des sessions. Préparer les règles de maintenance et le retour aux anciens domaines.
2. Bloquer les écritures sur toutes les entrées de l'ancien système : admin, portail patient, API publique, intégrations et accès directs. Un simple écran de maintenance sur l'admin ne suffit pas.
3. Arrêter les workers, tâches planifiées et traitements embarqués de l'ancien cabinet. Si les workers sont embarqués dans l'API, arrêter cette API après la mise en maintenance ; laisser la base disponible pour l'export. Attendre la fin des traitements en cours et relever les envois dont le résultat est incertain pour éviter les doublons.
4. Faire le dernier export intégral de la base et la dernière copie de tous les fichiers, pendant que la source ne reçoit plus aucune écriture. Sauvegarder le manifeste final et l'heure de gel. Conserver aussi les fichiers supprimés depuis la copie initiale dans l'archive de référence.
5. Arrêter l'API cible de répétition et remplacer ses données par cette sauvegarde finale dans une base cible propre. Écarter les données créées lors des essais. Restaurer les dossiers à l'identique de l'instant final, pas par une fusion approximative laissant des fichiers d'essai.
6. Vérifier les données et fichiers avant migration, puis appliquer uniquement les migrations déjà répétées. Documenter leurs effets attendus sur les comptages et les données.
7. Renseigner les domaines définitifs dans l'environnement cible ; garder les workers désactivés. Démarrer les services et vérifier les healthchecks.
8. Dans Dokploy, retirer les associations des domaines admin et patient aux anciens services, puis les associer respectivement à `frontend:80` et `frontendpatient:80`, chemin `/`, sans suppression du préfixe de chemin. Éviter deux routes concurrentes pour un même domaine.
9. Si le serveur ne change pas, la bascule se fait généralement au niveau du proxy Dokploy. Si le serveur change, préparer aussi le DNS et empêcher les anciennes adresses de continuer à accepter des écritures pendant sa propagation.
10. Tester par les domaines définitifs, accepter la mise à jour PWA et recharger les clients. Vérifier qu'aucun navigateur ne continue à utiliser l'ancienne API via une ancienne version en cache.
11. Examiner les messages en attente importés et leurs statuts ; ne pas réinitialiser globalement les statuts d'envoi. Réactiver **un seul ensemble** de workers sur la nouvelle stack, puis remettre les intégrations et tâches nécessaires.
12. Lever la maintenance une fois les contrôles validés. Noter l'heure de la première écriture réelle sur la nouvelle stack : elle change la procédure de retour arrière.

Les anciens services du cabinet restent arrêtés ou isolés. Ne pas arrêter un hub ou une base partagés dont d'autres cabinets dépendent.

## 7. Contrôles avant réouverture

| Contrôle | Résultat attendu |
|---|---|
| Import SQL | Aucun échec ; inventaire complet des tables et autres objets applicatifs |
| Lignes par table | Identiques à l'instant du gel avant migration ; écarts après migration expliqués par le SQL validé |
| Finances | Mêmes totaux de factures, paiements, soldes et dernières opérations |
| Dossiers patients | Patients, consultations, traitements, prescriptions et rendez-vous récents et anciens consultables |
| Fichiers | Même manifeste de chemins, tailles et SHA-256 pour les fichiers restaurés |
| Accès aux pièces | Photos, documents, fichiers d'assurance et pièces privées lisibles depuis l'application avec les bons droits |
| Identités | Connexion admin et patient, rôles et appareils autorisés conservés |
| Secrets SMS | Paramètres fournisseurs déchiffrables avec l'ancien `APP_SECRET` |
| Routage | Appels sur un seul `/api`, sans 404 systématiques ni appel involontaire à l'ancienne API |
| Temps réel | Flux SSE et notification reçue entre deux sessions |
| SMS / mail / push | Tests limités à des destinataires de validation, puis activation maîtrisée des traitements |
| Persistance | Après un redéploiement contrôlé de la cible, données et fichier de test toujours présents |
| Reprise des sauvegardes | Sauvegarde de la nouvelle stack créée, exportée hors serveur et restaurable |

Un hash d'archive valide son transfert ; le manifeste des fichiers et la restauration testée valident davantage la reprise. Ne pas se limiter à la taille du SQL ou à un login réussi.

## 8. Retour arrière

**Avant toute écriture réelle sur la nouvelle stack :** arrêter ses workers et bloquer ses accès, rétablir les domaines et l'environnement de l'ancienne installation, puis remettre en service ses applications et ses workers. La source est restée intacte et figée à l'instant de bascule.

**Après des écritures réelles sur la nouvelle stack :** remettre les deux côtés en maintenance, arrêter les traitements, sauvegarder immédiatement la nouvelle base et les nouveaux fichiers, puis préparer leur reprise. Un simple changement de domaines ferait perdre les nouvelles consultations, paiements ou pièces jointes. Tester la compatibilité de l'ancien code avec le nouveau schéma ; si elle n'est pas garantie, prévoir une reprise des écarts ou corriger sur la nouvelle stack. Ne pas supposer que les migrations peuvent être annulées sans perte.

Conserver les anciens volumes, images, configurations et archives pendant une période convenue avec le cabinet, par exemple 7 à 14 jours après validation, et jusqu'à confirmation d'une sauvegarde restaurable de la nouvelle stack. Leur suppression constitue une opération séparée.

## 9. Après la migration

- Surveiller erreurs applicatives, espace disque, redémarrages, RAM, files SMS et Messenger, sauvegardes et accès aux fichiers.
- Garder l'ancien endpoint API fermé aux écritures tant que d'anciens clients peuvent encore l'appeler. Relever les éventuelles intégrations à migrer avant sa suppression définitive.
- Programmer la sauvegarde de la base, de tous les volumes métier, des clés et de l'environnement. Les volumes persistent au remplacement des conteneurs mais ne remplacent pas une sauvegarde ; voir la [documentation Docker sur les volumes](https://docs.docker.com/engine/storage/volumes/).
- Conserver un compte rendu : commit déployé, `INSTANCE_ID`, heure de gel, sauvegardes utilisées, résultats de validation, migrations appliquées et responsable de réouverture.
- Répéter la procédure pour le cabinet suivant après validation du premier.

## Ordre de réalisation

| Étape | Sortie attendue avant la suivante |
|---|---|
| Inventaire | Sources, secrets, dépendances et volumes identifiés |
| Sauvegarde | Base intégrale + fichiers + configuration copiés hors production |
| Répétition | Restauration vérifiée, migrations testées et durée mesurée |
| Préparation de bascule | Domaines, maintenance, destinataires de test et retour arrière prêts |
| Gel et copie finale | Toutes les écritures arrêtées, base et fichiers au même instant |
| Validation et réouverture | Contrôles réussis, routage basculé et un seul ensemble de workers actif |
| Stabilisation | Sauvegarde cible restaurable et décision de retrait de l'ancienne installation |

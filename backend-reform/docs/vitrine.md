# Vitrine

Le groupe apparaît après Accueil et regroupe Messagerie, Les avis patients et Portail Patient. Les pages et leurs API sont réservées aux comptes actifs ayant `ROLE_ADMIN`, `ROLE_RECEPTION` ou `ROLE_RECEPTIONNISTE`. Les médecins et patients ne peuvent pas accéder à la gestion de la Vitrine. L’ancienne URL des avis redirige vers `/vitrine/avis-patients`.

La réception peut consulter et synchroniser les messages, répondre par email/SMS, modérer les avis et gérer les portails. **Seul un administrateur peut configurer ou remplacer les identifiants mail.** Les notifications de nouveaux mails et nouveaux avis sont adressées uniquement aux admins et à la réception actifs ayant activé leurs notifications.

## Installation

1. Appliquer la migration avec `php bin/console doctrine:migrations:migrate`.
2. Activer l’extension PHP IMAP pour le PHP web **et** CLI, avec `mbstring` et OpenSSL. Le serveur IMAP doit être accessible sur le port 993 avec un certificat SSL valide. Définir côté déploiement `VITRINE_IMAP_ALLOWED_HOSTS=imap.fournisseur.fr` (plusieurs noms exacts séparés par des virgules). La liste est vide par défaut et interdit toute connexion tant qu’elle n’est pas configurée. Les IP littérales, destinations privées/réservées et options IMAP injectées sont refusées. Autoriser uniquement des domaines maîtrisés ou des fournisseurs de confiance, puis limiter les sorties réseau aux serveurs approuvés : la bibliothèque IMAP résout elle-même le DNS lors de la connexion.
3. Avec un compte admin, connecter dans Messagerie le serveur IMAP, l’adresse de contact et un mot de passe d’application dédié. La connexion est testée avant enregistrement. Le mot de passe est chiffré en AES-256-GCM, authentifié et lié au serveur/identifiant/email ; il n’est jamais renvoyé au navigateur. Une modification de ces paramètres exige de le ressaisir. Après cette mise à jour de sécurité, **ressaisir aussi les anciens identifiants chiffrés au format CBC** : ils ne sont plus réutilisés. Conserver un `APP_SECRET` de production fort et confidentiel ; sa rotation nécessite de reconnecter la boîte. Utiliser HTTPS, `APP_ENV=prod` et `APP_DEBUG=0` ; ne pas journaliser les corps des requêtes à cet endpoint au niveau du proxy ou de l’APM. Le profiler Symfony est désactivé pour les requêtes de gestion Vitrine.
4. Configurer le transport de `mail()` dans PHP / le MTA du serveur (ou le relais SMTP de PHP sous Windows). La connexion IMAP concerne la réception ; elle ne configure pas le transport sortant. Configurer un relais authentifié avec chiffrement TLS obligatoire : l’application ne peut pas le garantir avec `mail()`. Autoriser l’adresse du cabinet comme expéditeur et configurer SPF/DKIM chez le fournisseur.
5. Planifier `php bin/console app:vitrine:sync-mailbox` chaque minute, avec le même environnement et les mêmes droits que l’application. Un verrou local empêche le chevauchement de la commande et de la synchronisation manuelle ; exécuter cette tâche sur une seule instance en déploiement multi-serveur.
6. Garder les workers Messenger, Mercure, les clés VAPID et le traitement de la file SMS existants actifs. L’utilisateur doit activer les notifications et autoriser le push dans son navigateur.

La synchronisation importe les objets contenant « Nouvelle demande de rendez-vous », indépendamment de la casse, avec dédoublonnage par serveur/compte/UIDVALIDITY/UID IMAP. Le destinataire d’une réponse provient de `Reply-To`, puis de `From`. Le formulaire du site doit donc renseigner `Reply-To` avec l’email du patient. Cette adresse et l’objet ne prouvent pas l’identité de l’expéditeur : les réponses sont toujours déclenchées par un opérateur. Les versions texte et HTML des corps MIME sont converties en texte et affichées via interpolation Vue, sans HTML actif ni images distantes ; les pièces jointes ne sont pas importées.

Les messages supérieurs à 2 Mio sont ignorés avant téléchargement du corps. Chaque passage examine jusqu’à 50 nouveaux messages correspondant à l’objet, avec un budget de 45 secondes vérifié entre les messages et des délais réseau de 10/15 secondes. Les messages ignorés sont dépassés par un curseur UID persistant ; les passages suivants poursuivent l’import. Le parcours MIME est borné à 12 niveaux et 50 enfants par conteneur, les objets et corps à 500 et 100 000 caractères. La liste affiche les 500 demandes les plus récentes. Contrôler aussi la taille et l’antispam chez le fournisseur IMAP ; le budget applicatif ne remplace pas les délais du serveur web.

Chaque import appelle le service de notifications existant pour les admins et la réception, avec un lien vers le message : persistance, publication Mercure et mise en file du Web Push. La notification ne contient pas le corps ni l’adresse du patient. La page recharge sa liste à réception de l’événement Mercure. Le bouton Synchroniser permet aussi un import immédiat.

Les API appliquent des quotas communs par boîte : 5 essais de connexion/minute, 2 synchronisations manuelles/minute et 20 réponses email/SMS/minute. Elles répondent `429` avec `Retry-After` en cas de dépassement. Les avis anonymes sont limités à 5/heure/IP pour limiter le spam de notifications. Utiliser un cache partagé pour les quotas en déploiement multi-instance et configurer correctement les proxies de confiance pour l’IP client. Les données de gestion portent `Cache-Control: private, no-store`.

Les emails apparaissent comme acceptés par le transport, ce qui ne confirme pas leur livraison. Les SMS (160 caractères maximum) apparaissent en file d’envoi et leur identifiant de file est conservé dans l’historique. Le téléphone est saisi par l’opérateur ; il n’est pas déduit du texte libre reçu.

## Avis

Les catégories du filtre correspondent aux niveaux de satisfaction : insatisfait (1–2), neutre (3), satisfait (4), très satisfait (5). Elles se combinent avec la recherche et les filtres de visibilité/anonymat.

Tout nouvel avis (anonyme, patient ou consultation) notifie les admins et la réception via Mercure et Web Push, avec un lien vers `/vitrine/avis-patients`, sans identité ni texte d’avis. Modifier la visibilité ou supprimer un avis n’envoie pas de nouvelle notification. Un avis anonyme reste masqué à sa création, même si le client transmet `isPublished=true` ; la publication se fait par la modération. Les avis identifiés conservent leur publication initiale existante.

`GET /api/appreciations/public/export?limit=50` fournit le même JSON que l’API publique existante : uniquement les avis publiés, sans email, identifiant patient ni consultation. Les avis anonymes n’exposent aucun nom. Cette URL peut être appelée par le serveur du site web ; pour un appel direct depuis un navigateur, configurer l’origine du site dans CORS. La visibilité est modifiée avec les endpoints de modération existants.

## Portails

« Portails créés » liste les patients disposant d’un compte, y compris les comptes désactivés. « Tous les patients » permet de rechercher un patient sans compte et de créer son accès. La recherche porte sur le nom, prénom, carnet et identifiant et retourne au maximum 100 résultats.

Ouvrir le portail crée ou réactive le compte ; cette action ne connecte pas l’opérateur sous l’identité du patient. La création réutilise le mécanisme existant et son mot de passe initial. La réinitialisation depuis Vitrine génère un nouveau mot de passe aléatoire, affiché une fois dans une boîte de dialogue avec une réponse HTTP `no-store`. Seul son hash est stocké. Le renommage vérifie la syntaxe et l’unicité de l’identifiant.

## Vérification externe

Après configuration, envoyer une demande depuis une boîte de test, lancer deux synchronisations et vérifier un seul message importé, puis la cloche Mercure et le push dans un navigateur abonné. Tester une réponse email et un SMS avec des destinataires de test autorisés. Ces vérifications nécessitent des identifiants IMAP et un transport sortant réels.

Vérifier aussi qu’un compte réception accède aux trois pages et aux avis, qu’un médecin reçoit `403` sur les API de gestion et qu’un compte réception reçoit `403` lors d’une tentative de modification des identifiants mail. Déposer chacun des trois types d’avis et vérifier les notifications uniquement côté réception/admin. Consulter le [rapport d’audit mail](./audit-securite-mail.md) pour les risques résiduels et les tests réalisés.

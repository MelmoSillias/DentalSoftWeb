/**
 * Fiches de référence du chapitre 4, une par écran.
 * `audience` : profils concernés (admin, accueil, medecin), utilisé par le filtre de lecture.
 * `actions` : [action, effet, qui] ; `events` : [quand, alors].
 */
export const pageDocs = [
    {
        id: 'page-connexion',
        title: 'Connexion et appareil en attente',
        menu: 'Écran public, avant le menu',
        route: '/auth/login',
        roles: 'Tout utilisateur',
        audience: ['admin', 'accueil', 'medecin'],
        purpose:
            'Porte d’entrée du cabinet. L’utilisateur saisit son identifiant et son mot de passe dans le formulaire « Connexion ». Si le poste n’est pas encore approuvé, l’application s’arrête sur un écran d’attente au lieu d’afficher les données des patients.',
        figure: {
            label: 'Connexion',
            caption: 'Écran de connexion du cabinet.',
            markers: [
                { x: 25, y: 40, text: 'Nom du cabinet et « Votre plateforme de gestion pour cabinet dentaire. »' },
                { x: 72, y: 38, text: 'Nom d’utilisateur et Mot de passe.' },
                { x: 72, y: 66, text: 'Bouton Se connecter.' }
            ]
        },
        actions: [
            ['Se connecter', 'Vérifie le compte, puis ouvre le tableau de bord ou l’accueil en cartes selon le réglage du poste.', 'Tous'],
            ['Verifier l\'autorisation', 'Redemande au serveur si l’administrateur a approuvé ce poste.', 'Poste en attente'],
            ['Se deconnecter', 'Quitte l’écran d’attente et revient à la connexion.', 'Poste en attente']
        ],
        events: [
            ['Identifiants incorrects', '« Nom d\'utilisateur ou mot de passe incorrect. »'],
            ['Serveur injoignable', '« Impossible de se connecter au serveur... » : vérifier le réseau ou le serveur du cabinet.'],
            ['Poste inconnu, approbation manuelle', 'Écran « Appareil non autorise » : « Cet appareil n\'est pas encore autorise. Veuillez attendre qu\'un administrateur valide votre demande. »'],
            ['Poste refusé', '« Cet appareil a ete refuse. Contactez un administrateur. » ; le bouton de vérification disparaît.'],
            ['Session expirée', 'Retour automatique à la connexion.']
        ],
        exceptions: [
            'Il n’y a ni « Mot de passe oublié » ni « Rester connecté » : en cas d’oubli, l’administrateur pose un nouveau mot de passe depuis Utilisateurs.',
            'Un appareil approuvé est ensuite ouvert à tous les comptes, pas seulement à celui qui a fait la demande.',
            'Sans approbation automatique, seul le tout premier appareil est accepté d’office ; les suivants attendent l’administrateur.'
        ],
        see: ['mr-postes', 'page-parametres']
    },
    {
        id: 'page-accueil',
        title: 'Tableau de bord et accueil en cartes',
        menu: 'Accueil › Dashboard',
        route: '/dashboard · /accueil',
        roles: 'Administrateur, accueil, médecin',
        audience: ['admin', 'accueil', 'medecin'],
        purpose:
            'Deux portes d’entrée possibles après la connexion. La page « Accueil » en cartes reprend le menu du rôle sous forme de vignettes. Le tableau de bord (« Voici votre tableau de bord pour aujourd\'hui ») résume l’activité et regroupe ce qui attend une action.',
        figure: {
            label: 'Tableau de bord',
            caption: 'Aperçu rapide, éléments en attente et centre d’alertes.',
            markers: [
                { x: 85, y: 10, text: 'Filtre Date (et Periode pour l’administrateur et le médecin).' },
                { x: 30, y: 30, text: 'Aperçu rapide : cartes chiffrées avec leur lien.' },
                { x: 30, y: 72, text: 'Panneau En attente : Rendez-vous, Consultations, Factures…' },
                { x: 80, y: 72, text: 'Centre d’alertes et Tout lire.' }
            ]
        },
        actions: [
            ['Carte de l’accueil en cartes', 'Ouvre la page correspondante.', 'Selon le menu du rôle'],
            ['Carte de l’Aperçu rapide', 'Raccourci vers la page concernée (par exemple « Voir la caisse »).', 'Admin, accueil, médecin'],
            ['Voir, Ouvrir, Caisse (panneau En attente)', 'Voir ouvre un rendez-vous, Ouvrir une consultation, Caisse une facture.', 'Selon l’onglet'],
            ['Tout lire', 'Après confirmation, marque toutes les notifications comme lues.', 'Tous'],
            ['Imprimer', 'Dialogue « Choix d\'impression » : Liste des médecins (résumé) ou Liste des soins détaillée.', 'Accueil']
        ],
        events: [
            ['Changement de date', 'Les indicateurs du jour se recalculent.'],
            ['Échec de chargement', 'Bandeau « Chargement interrompu » et bouton Réessayer ; le reste du menu reste utilisable.']
        ],
        exceptions: [
            'Onglets du panneau En attente : Rendez-vous, Consultations et Factures pour tous ; Paiements en plus pour l’accueil ; Actes en plus pour le médecin.',
            'L’accueil n’a que le sélecteur de date ; l’administrateur et le médecin ont aussi un sélecteur de période pour le carrousel de graphiques.',
            'La carte « Montant facturé » du médecin renvoie vers la Caisse, qui ne lui est pas ouverte : elle affiche « Accès refusé ».',
            'Le choix entre tableau de bord et accueil en cartes se fait par poste, dans Paramètres généraux › Apparence.'
        ],
        see: ['navigation']
    },
    {
        id: 'page-agenda',
        title: 'Agenda des rendez-vous',
        menu: 'Agenda › Rendez-Vous',
        route: '/agenda/rendez-vous',
        roles: 'Administrateur, accueil, médecin (son planning)',
        audience: ['admin', 'accueil', 'medecin'],
        purpose:
            'Planning du cabinet. La « Vue hebdomadaire » s’ouvre en premier ; la « Vue journalière » découpe la journée par médecin, entre l’heure d’ouverture et l’heure de fermeture du cabinet. Un médecin ne voit que son propre planning.',
        figure: {
            label: 'Agenda hebdomadaire',
            caption: 'Rendez-vous de la semaine, colorés selon leur statut.',
            markers: [
                { x: 15, y: 10, text: 'Onglets Vue hebdomadaire et Vue journalière, navigation dans les dates.' },
                { x: 88, y: 10, text: 'Bouton + : nouveau rendez-vous.' },
                { x: 55, y: 55, text: 'Rendez-vous : clic droit pour Valider, Reporter, Annuler ou les rappels SMS.' }
            ]
        },
        actions: [
            ['Créneau « Disponible » ou +', 'Ouvre « Nouveau rendez-vous » avec l’heure et le médecin du créneau.', 'Admin, accueil, médecin'],
            ['Valider', 'Dialogue « Valider le rendez-vous » avec la question « Créer une consultation maintenant ? » (Oui / Non).', 'Rendez-vous En attente'],
            ['Reporter', 'Dialogue « Reporter le rendez-vous » : Médecin, Nouvelle date, Durée. Le statut devient Reporté.', 'Rendez-vous En attente'],
            ['Annuler', 'Confirmation « Confirmer l\'annulation de ce rendez-vous ? ». Le statut devient Annulé ; le patient n’est pas supprimé.', 'Rendez-vous En attente'],
            ['Envoyer rappel SMS', 'Place un SMS de rappel dans la file d’envoi.', 'Vue hebdomadaire, Internet actif'],
            ['Programmer rappel auto', 'Programme un rappel 72h, 48h, 24h, 12h ou 2h avant.', 'Vue hebdomadaire, Internet actif']
        ],
        events: [
            ['Création', '« Rendez-vous créé. » ; statut En attente.'],
            ['Validation avec Oui', 'La consultation est créée et le patient entre dans la file.'],
            ['Validation avec Non', '« Le rendez-vous sera validé sans consultation ni attribution de médecin. »'],
            ['Patient, médecin, date ou durée manquants', 'La création est refusée. La durée doit être positive.']
        ],
        exceptions: [
            'Statuts : En attente, Validé, Reporté, Annulé. Valider, Reporter et Annuler ne sont proposés que pour un rendez-vous En attente.',
            'Pour un médecin, le champ Médecin est verrouillé sur lui-même.',
            'La réponse proposée par défaut à « Créer une consultation maintenant ? » dépend d’un réglage des Paramètres généraux (Non par défaut).'
        ],
        see: ['wf-rdv', 'page-evenements']
    },
    {
        id: 'page-evenements',
        title: 'Événements du cabinet',
        menu: 'Agenda › Evenements',
        route: '/agenda/evenements',
        roles: 'Administrateur',
        audience: ['admin'],
        purpose: 'Page « Gestion des Événements ». Bloque une plage du calendrier pour tout le cabinet : fermeture, formation, jour férié. Ce n’est pas un rendez-vous patient.',
        figure: { label: 'Événement de fermeture', caption: 'Liste des événements et dialogue « Ajouter un événement ».' },
        actions: [
            ['Nouvel Événement', 'Ouvre « Ajouter un événement » : Date de début, Date de fin, Titre, Description.', 'Admin'],
            ['Enregistrer', 'Crée l’événement : « Événement ajouté avec succès. »', 'Admin'],
            ['Clic droit › Valider', 'Après confirmation, l’événement est marqué « Confirmé » : « Événement validé avec succès. »', 'Admin'],
            ['Clic droit › Supprimer', 'Retire l’événement après confirmation : « Événement supprimé avec succès. »', 'Admin']
        ],
        events: [
            ['Champ obligatoire vide', 'Message sous le champ, par exemple « Date de début obligatoire ».'],
            ['Validation', 'L’événement reste visible comme plage confirmée.']
        ],
        exceptions: ['L’accueil et le médecin ne voient pas cette page ; ils continuent de poser des rendez-vous dans Rendez-Vous.'],
        see: ['mr-fermetures']
    },
    {
        id: 'page-patients',
        title: 'Liste des patients',
        menu: 'Patients › Liste',
        route: '/patients/liste',
        roles: 'Administrateur, accueil, médecin (ses patients)',
        audience: ['admin', 'accueil', 'medecin'],
        purpose:
            'Registre du cabinet (« Gestion des Patients »). On y cherche un patient, on crée son dossier, on lance une consultation, un rendez-vous ou un service cabinet. Un médecin ne voit que ses patients, et le téléphone peut lui être affiché « Masqué par l\'administrateur ».',
        figure: {
            label: 'Liste des patients',
            caption: 'Recherche, boutons d’en-tête et actions de ligne.',
            markers: [
                { x: 25, y: 12, text: 'Rechercher un patient : nom, prénom, téléphone, adresse.' },
                { x: 80, y: 12, text: 'Corbeille, Nouveau rendez-vous, Nouvelle consultation, Ajouter un patient, Exporter.' },
                { x: 80, y: 52, text: 'Actions de la ligne : consultation, modification, menu d’actions.' },
                { x: 15, y: 66, text: 'Icône portefeuille : reliquat ; ligne teintée de rouge : dernière consultation urgente.' }
            ]
        },
        actions: [
            ['Ajouter un patient', 'Ouvre le formulaire ; Créer, puis Confirmer, enregistre le patient.', 'Admin, accueil, médecin'],
            ['Rechercher un patient', 'Filtre la liste après une courte pause de saisie.', 'Tous'],
            ['Nouvelle consultation', 'Vérifie d’abord qu’aucune consultation n’est déjà ouverte pour ce patient.', 'Admin, accueil'],
            ['Modifier', 'Rouvre le formulaire « Modifier le patient », bouton Mettre à jour.', 'Tous'],
            ['Voir dossier médical', 'Ouvre le dossier du patient (menu d’actions de la ligne).', 'Tous'],
            ['Nouveau rendez-vous, Service cabinet', 'Ouvre le dialogue correspondant avec ce patient.', 'Tous'],
            ['Supprimer (corbeille)', 'Après confirmation : « Patient déplacé dans la corbeille. » Il n’est pas effacé.', 'Tous'],
            ['Corbeille › Restaurer', '« Patient restauré avec succès. »', 'Tous'],
            ['Exporter', 'Imprime la page affichée : nom, prénom, téléphone, âge, sexe.', 'Tous']
        ],
        events: [
            ['Patient enregistré', '« Patient sauvegardé. » La nouvelle ligne clignote brièvement en vert.'],
            ['Consultation déjà ouverte, sans fiche', 'Dialogue « Consultation en cours » avec Compris et Annuler la consultation (« La consultation en cours a été supprimée. »).'],
            ['Consultation déjà ouverte, avec fiche', '« Cette consultation est liée à une fiche : elle ne peut pas être supprimée. » Seul Compris est proposé.'],
            ['Recherche sans résultat', 'Message dédié et bouton Réinitialiser la recherche.']
        ],
        exceptions: [
            'Le logiciel ne détecte pas les doublons : cherchez toujours avant de créer.',
            'Saisir l’âge calcule une date de naissance au 1er janvier de l’année correspondante.',
            'L’onglet « Informations assurances » n’apparaît que si au moins une assurance est active.',
            'Le médecin n’a pas les boutons Nouvelle consultation.'
        ],
        tech: ['Les astérisques de Nom et Téléphone sont indicatifs : le formulaire laisse partir un champ vide et c’est le serveur qui refuse l’enregistrement.'],
        see: ['wf-patient', 'page-dossier']
    },
    {
        id: 'page-dossier',
        title: 'Dossier patient',
        menu: 'Patients › Dossier',
        route: '/patients/dossier/:id',
        roles: 'Administrateur, accueil, médecin',
        audience: ['admin', 'accueil', 'medecin'],
        purpose:
            'Vue complète d’une personne : identité, documents, historique clinique, rendez-vous et finances. Deux présentations existent, « Affichage classique » ou « Affichage en onglets », mémorisées sur le poste. Sans patient choisi, la page affiche « Aucune sélection ».',
        figure: {
            label: 'Dossier patient',
            caption: 'En-tête d’identité et sections du dossier.',
            markers: [
                { x: 20, y: 15, text: 'Identité, photo et coordonnées.' },
                { x: 85, y: 15, text: 'Imprimer, Modifier, RDV.' },
                { x: 30, y: 55, text: 'Antécédents et allergies (bouton Ajouter).' },
                { x: 72, y: 55, text: 'Activité et finances : factures et paiements.' },
                { x: 92, y: 88, text: 'Bascule entre affichage classique et en onglets.' }
            ]
        },
        actions: [
            ['Sélectionner un patient', 'Charge le dossier ; la recherche porte sur le nom et le téléphone.', 'Tous'],
            ['Imprimer', 'Dialogue « Choisir les sections a imprimer », avec l’option Imprimer les champs vides.', 'Tous'],
            ['Modifier', 'Rouvre le formulaire patient.', 'Tous'],
            ['RDV', 'Ouvre « Nouveau rendez-vous » avec ce patient déjà choisi.', 'Tous'],
            ['Ajouter (antécédent, allergie)', 'Enregistre le type et la description.', 'Admin, médecin'],
            ['Compte espace patient › Créer', 'Crée le compte du portail patient, avec le mot de passe par défaut 123.', 'Tous'],
            ['Clic droit sur une facture', 'Payer ou Valider, Voir, Imprimer.', 'Admin, accueil']
        ],
        events: [
            ['Photo changée', '« Photo mise à jour. »'],
            ['Compte portail créé', '« Compte créé (identifiant) - mot de passe par défaut: 123 »'],
            ['Sortie d’une fiche modifiée non enregistrée', 'Avertissement : les modifications seront perdues.'],
            ['Dossier masqué aux médecins', '« Dossier patient masqué » : « L\'accès au dossier patient est restreint pour votre profil. »']
        ],
        exceptions: [
            'L’accueil voit la liste des consultations du patient, pas le contenu clinique des fiches.',
            'Le portail peut être activé ou désactivé depuis le même bloc.'
        ],
        warning: 'Le compte du portail est créé avec le mot de passe 123. Communiquez-le au patient et demandez-lui de le changer dès sa première connexion.',
        see: ['page-patients', 'page-fiche']
    },
    {
        id: 'page-file',
        title: 'File d’attente des consultations',
        menu: 'Consultations › File d\'attente',
        route: '/consultations/cards',
        roles: 'Administrateur, médecin',
        audience: ['admin', 'medecin'],
        purpose:
            'Les consultations ouvertes, de la plus ancienne à la plus récente, en vue « Cartes » ou « File d’attente ». La première position est en rouge, les deux suivantes en ambre, les autres en vert. L’accueil suit la même file dans le Mode Focus.',
        figure: {
            label: 'File d’attente',
            caption: 'Cartes classées par ancienneté.',
            markers: [
                { x: 18, y: 40, text: 'Carte en position 1 : la plus ancienne.' },
                { x: 55, y: 40, text: 'Actions rapides : Clôture rapide, fiche, Service cabinet, Annuler.' },
                { x: 88, y: 10, text: 'Bascule Cartes / File d’attente.' }
            ]
        },
        actions: [
            ['Double-clic sur une carte', 'Ouvre la fiche médicale de la consultation.', 'Admin, médecin'],
            ['Clôture rapide', 'Formulaire court : Médecin, Aide(s) soignant(e)s, Salle, Soins réalisés, puis Clôturer.', 'Admin, médecin'],
            ['Ouvrir fiche médicale du patient', 'Ouvre la fiche liée tant que la consultation n’est pas clôturée.', 'Admin, médecin'],
            ['Service cabinet', 'Enregistre une prestation du catalogue cabinet.', 'Admin, médecin'],
            ['Annuler', 'Confirmation « Annuler cette consultation en cours ? ». Si une fiche est liée, réservé à l’administrateur.', 'Selon le cas']
        ],
        events: [
            ['Clôture rapide effectuée', '« Clôture rapide effectuée. » La consultation quitte la file et reste dans l’historique.'],
            ['Annulation', '« Consultation supprimée. »'],
            ['File vide', '« Toutes les consultations ont été traitées ou clôturées. » et bouton Créer une consultation.']
        ],
        exceptions: ['La clôture rapide ne gère pas les ordonnances : pour prescrire, ouvrez la fiche complète.'],
        warning: 'Annuler une consultation la supprime définitivement.',
        see: ['wf-cloture', 'page-fiche']
    },
    {
        id: 'page-historique',
        title: 'Historique des consultations',
        menu: 'Consultations › Historique',
        route: '/consultations/table',
        roles: 'Administrateur, accueil, médecin',
        audience: ['admin', 'accueil', 'medecin'],
        purpose: 'Journal des consultations d’une date. On y retrouve une consultation En cours ou Clôturée, on en consulte le détail, on ouvre le dossier ou on corrige une facture encore modifiable.',
        figure: {
            label: 'Historique des consultations',
            caption: 'Tableau filtrable par date et par recherche.',
            markers: [
                { x: 25, y: 12, text: 'Rechercher une consultation : patient, médecin, statut.' },
                { x: 70, y: 12, text: 'Date de consultation et Exporter.' },
                { x: 85, y: 55, text: 'Actions : Voir détails, Ouvrir dossier, Éditer facture, Annuler, Actions rapides.' }
            ]
        },
        actions: [
            ['Nouvelle consultation', 'Même dialogue que depuis la liste des patients.', 'Admin, accueil'],
            ['Rechercher une consultation', 'Filtre par patient, médecin ou statut.', 'Tous'],
            ['Date de consultation', 'Recharge le jour choisi.', 'Tous'],
            ['Voir détails', 'Ouvre le détail de la consultation, en lecture.', 'Tous'],
            ['Ouvrir dossier', 'Ouvre le dossier du patient, sauf si le dossier est masqué aux médecins.', 'Tous'],
            ['Actions rapides › Clôture rapide', 'Proposée tant que la consultation est en cours.', 'Selon le réglage'],
            ['Éditer facture', 'Ouvre la facture d’une consultation clôturée encore modifiable.', 'Admin, ou accueil si autorisé'],
            ['Annuler', 'Pour une consultation en cours : « Annuler cette consultation ? Cette action est irréversible. »', 'Tous'],
            ['Exporter', 'Imprime la liste chargée.', 'Tous']
        ],
        events: [
            ['Consultation signalée urgente', 'La mention « Urgent » accompagne le statut.'],
            ['Aucune consultation ce jour-là', '« Aucune consultation n\'a été enregistrée pour cette période. »']
        ],
        exceptions: ['Si une fiche médicale est déjà liée, l’annulation peut être refusée : faites alors clôturer la consultation par le médecin.'],
        see: ['page-file', 'page-caisse']
    },
    {
        id: 'page-fiche',
        title: 'Fiche médicale',
        menu: 'Ouverte depuis la file d’attente (double-clic) ou le Mode Focus',
        route: '/consultations/form',
        roles: 'Administrateur, médecin',
        audience: ['admin', 'medecin'],
        purpose:
            'Dossier clinique de la consultation ouverte. Chaque section se sauvegarde séparément. La clôture fige la séance : la fiche passe ensuite en lecture seule. Deux formes existent selon le réglage du cabinet : détaillée ou simplifiée.',
        figure: {
            label: 'Fiche médicale',
            caption: 'Sections de la fiche et barre d’actions.',
            markers: [
                { x: 15, y: 15, text: 'Sections de la fiche, avec leur état : Modifie, Sauvegarde, Lecture seule.' },
                { x: 80, y: 12, text: 'Enregistrer tout, Auto-sauvegarde, Imprimer fiche.' },
                { x: 50, y: 50, text: 'Section ouverte et son bouton Sauvegarder.' },
                { x: 88, y: 80, text: 'Clôturer.' }
            ]
        },
        actions: [
            ['Sauvegarder (section)', 'Écrit la section ; son état passe de « Modifie » à « Sauvegarde ».', 'Admin, médecin'],
            ['Enregistrer tout', 'Écrit toutes les sections modifiées. Inactif s’il n’y a rien à écrire.', 'Admin, médecin'],
            ['Auto-sauvegarde', 'Désactivée par défaut. Activée, elle enregistre en silence 7 secondes après la dernière saisie.', 'Admin, médecin'],
            ['Soins réalisés', 'Pose des actes du catalogue sur la séance, avec leur quantité.', 'Admin, médecin'],
            ['Nouvelle ordonnance', 'Ajoute une prescription ; Voir, Modifier, Imprimer.', 'Admin, médecin'],
            ['Imprimer le devis', 'Refusé tant que le devis n’est pas sauvegardé.', 'Admin, médecin'],
            ['Imprimer fiche', 'Imprime les sections remplies.', 'Admin, médecin'],
            ['Clôturer', '« Cloturer definitivement cette consultation ? » ; exige un médecin et des sections sauvegardées.', 'Admin, médecin']
        ],
        events: [
            ['Médecin absent à la clôture', '« Veuillez sélectionner un médecin avant de sauvegarder ou clôturer. »'],
            ['Section modifiée non sauvegardée', '« Toutes les sections doivent être sauvegardées avant la clôture. »'],
            ['Devis imprimé avant sauvegarde', '« Ce devis doit etre sauvegarde avant impression. »'],
            ['Clôture réussie', 'Retour à l’Historique ; la consultation y apparaît « Clôturée ».'],
            ['Ouverture d’une consultation déjà close', '« Cette consultation est déjà clôturée. »']
        ],
        exceptions: [
            'Forme détaillée : Informations patient, Questionnaire médical, Examens, Images & documents, Bilans, Plan de traitement, Devis, Seances, Consultation en cours.',
            'Forme simplifiée : Informations patient, Synthèse clinique, Devis, Seances, Consultation en cours.',
            'Types de consultation (dans Consultation en cours) : Première Consultation, Contrôle ou prévention, Suivi de traitement, Urgence Dentaire, Autre.',
            'Les séances passées sont en lecture seule : ce sont les consultations déjà clôturées.'
        ],
        warning: 'La clôture est définitive : vérifiez les soins réalisés, ils déterminent la facture et la part du médecin.',
        see: ['wf-cloture', 'fo-dentiste']
    },
    {
        id: 'page-services',
        title: 'Services cabinets',
        menu: 'Consultations › Services cabinets',
        route: '/consultations/services-cabinets',
        roles: 'Administrateur, accueil, médecin',
        audience: ['admin', 'accueil', 'medecin'],
        purpose: 'Registre des prestations facturées au patient mais exclues de la rémunération du médecin : radiographie, produit, acte du cabinet. Les tarifs se préparent dans le catalogue des Paramètres généraux.',
        figure: { label: 'Services cabinets', caption: 'Liste des services, statut de paiement et actions.' },
        actions: [
            ['Nouveau service', 'Patient, service du catalogue, quantité, prix, date, note.', 'Tous'],
            ['Payer', 'Ouvre l’encaissement tant qu’il reste un solde.', 'Tous'],
            ['Modifier', 'Corrige le service.', 'Tous'],
            ['Supprimer', 'Après « Oui, supprimer », uniquement s’il n’existe aucun paiement.', 'Tous'],
            ['Exporter', 'Imprime la liste filtrée.', 'Tous']
        ],
        events: [
            ['Paiement partiel', 'Statut Partiel ; le bouton Payer reste.'],
            ['Solde atteint', 'Statut Payé.'],
            ['Suppression avec paiement', 'Refusée.'],
            ['Montant nul', 'Enregistrement refusé.']
        ],
        exceptions: [
            'Statuts : Payé, Partiel, Impayé, Annulé.',
            'Le même dialogue s’ouvre depuis la liste des patients, la file d’attente et le Mode Focus.',
            'Une facture de service cabinet ne se réinitialise pas et ne s’envoie pas par SMS.'
        ],
        see: ['mr-tarifs']
    },
    {
        id: 'page-caisse',
        title: 'Caisse',
        menu: 'Caisse › Encaissements',
        route: '/caisse',
        roles: 'Administrateur, accueil',
        audience: ['admin', 'accueil'],
        purpose:
            'Suivi des factures, des paiements et des dossiers d’assurance. Quatre onglets : Vue d\'ensemble, Factures, Paiements, Assurances. L’accueil arrive sur la journée ; l’administrateur sur le mois en cours.',
        figure: {
            label: 'Caisse, vue d’ensemble',
            caption: 'Onglets, période et liste des factures.',
            markers: [
                { x: 20, y: 10, text: 'Onglets Vue d’ensemble, Factures, Paiements, Assurances.' },
                { x: 85, y: 10, text: 'Période et filtres Toutes, Impayées (période), Toutes les impayées.' },
                { x: 35, y: 50, text: 'Factures et leur statut.' },
                { x: 88, y: 60, text: 'Régler ou Valider, Modifier, Aperçu, Envoyer facture par SMS.' }
            ]
        },
        actions: [
            ['Régler', 'Ouvre « Régler la facture », montant prérempli au reste ; plusieurs factures impayées s’ouvrent en onglets.', 'Admin, accueil'],
            ['Valider', 'Pour une facture à 0 : « Facture vide validée », sans paiement.', 'Admin, accueil'],
            ['Modifier', 'Change les lignes d’une facture sans aucun paiement. Refusé sur assurance et service cabinet.', 'Admin ; accueil si autorisé'],
            ['Aperçu', 'Affiche puis imprime la facture.', 'Admin, accueil'],
            ['Reçu', 'Onglet Paiements : imprime le reçu 80 mm du paiement.', 'Admin, accueil'],
            ['Envoyer facture par SMS', '« Facture ajoutée à la file SMS. » Indisponible pour assurance et service cabinet.', 'Internet actif'],
            ['Créer un lot', 'Regroupe des factures assurance : Nom, Début, Fin.', 'Admin, accueil'],
            ['Envoyer, Confirmer, Rembourser, Imprimer', 'Fait avancer le lot ; Rembourser demande un mode et un montant ; Imprimer sort le bordereau.', 'Admin, accueil'],
            ['Réinitialiser', 'Dans le dialogue de paiement : efface les paiements de la facture.', 'Admin']
        ],
        events: [
            ['Paiement enregistré', '« Paiement enregistré » ; la notification propose Imprimer le reçu pendant 10 secondes.'],
            ['Paiement partiel', 'Le dialogue reste ouvert ; le reste diminue.'],
            ['Solde atteint avec d’autres factures impayées', 'Le dialogue passe à la facture suivante.'],
            ['Montant supérieur au reste', 'Refusé.'],
            ['Facture réinitialisée', '« La facture a été réinitialisée. »'],
            ['Facture déposée sur un lot non ouvert', '« Seul un lot ouvert accepte de nouvelles factures. »']
        ],
        exceptions: [
            'Statuts de facture : Payé, Validée, Vide non validé, Impayé, Partiellement payé.',
            'Statuts de lot : Ouvert, Envoyé, Confirmé, Partiellement remboursé, Remboursé. La vue Affectation permet de glisser les factures vers un lot ouvert.',
            '« Toutes les impayées » ignore la période et montre tous les restes du cabinet.',
            'Une facture partiellement payée ne se modifie plus.'
        ],
        warning: 'Réinitialiser efface tous les paiements de la facture. Notez-les avant pour pouvoir les ressaisir.',
        see: ['wf-paiement', 'sc-assure']
    },
    {
        id: 'page-rapports',
        title: 'Rapports',
        menu: 'Rapports › Statistiques',
        route: '/rapports',
        roles: 'Administrateur, accueil, médecin',
        audience: ['admin', 'accueil', 'medecin'],
        purpose: 'Lecture de l’activité, en lecture seule. Le contenu dépend du rôle. Pour corriger un chiffre, il faut revenir à la source : caisse, fiche ou paie.',
        figure: { label: 'Rapports', caption: 'Onglets du rapport et choix de période.' },
        actions: [
            ['Période (administrateur, médecin)', 'Sélecteur de plage de dates et bouton Rafraîchir.', 'Admin, médecin'],
            ['Date (accueil)', 'Choix d’un jour.', 'Accueil'],
            ['Onglets administrateur', 'Vue d\'ensemble, Activité, Finances (tableau croisé financier), Soins, Médecins.', 'Admin'],
            ['Onglets médecin', 'Synthèse, Activité, Actes, Profil : uniquement ses propres chiffres.', 'Médecin'],
            ['Onglets accueil', 'Stats du jour, Par médecin.', 'Accueil'],
            ['Imprimer', 'Sur les tableaux par médecin et les statistiques du jour.', 'Admin, accueil']
        ],
        events: [
            ['Période sans activité', 'Les indicateurs restent à zéro ; ce n’est pas une erreur.'],
            ['Profil sans rapport', '« Aucun tableau de bord disponible pour ce profil. »']
        ],
        exceptions: ['Le médecin n’a pas de bouton Imprimer. Les finances et la masse salariale ne concernent que l’administrateur.'],
        see: ['sc-fin-de-mois']
    },
    {
        id: 'page-rh',
        title: 'Gestion RH et fiche employé',
        menu: 'Administration › Gestion RH',
        route: '/administration/gestionrh',
        roles: 'Administrateur',
        audience: ['admin'],
        purpose:
            'Employés, paie et congés, en trois onglets : Employes, Gestion de la paie, Gestion des conges. Créer un employé ne crée pas son accès : le compte de connexion se fait ensuite dans Utilisateurs.',
        figure: {
            label: 'Gestion RH',
            caption: 'Onglets et liste des employés.',
            markers: [
                { x: 22, y: 10, text: 'Onglets Employes, Gestion de la paie, Gestion des conges.' },
                { x: 86, y: 10, text: 'Nouvel employe, Ajouter un paiement ou Ajouter un conge, selon l’onglet.' },
                { x: 88, y: 50, text: 'Œil : fiche « Détails employé ».' }
            ]
        },
        actions: [
            ['Nouvel employe', 'Dialogue « Ajouter un employé » : identité, poste, contrat, rémunération, jours travaillés, documents.', 'Admin'],
            ['Enregistrer', '« Confirmer la creation ? » puis « Employe cree. »', 'Admin'],
            ['Œil', 'Ouvre « Détails employé » ; Enregistrer met à jour (« Employe mis a jour. »).', 'Admin'],
            ['Ajouter un paiement', 'Dialogue « Paiement de salaire » sur un mode de paiement du cabinet : « Paiement enregistre. »', 'Admin'],
            ['Imprimer le bulletin', 'Imprime le bulletin de la ligne de paie.', 'Admin'],
            ['Ajouter un conge', 'Dialogue « Nouveau conge » : type (Vacances par défaut), début, fin, puis Creer.', 'Admin']
        ],
        events: [
            ['Médecin rémunéré au pourcentage', 'Le taux s’applique aux actes facturés. 35 % proposé, 100 % au plus.'],
            ['Période déjà soldée', '« Cette période est déjà entièrement réglée. » Aucun second versement.'],
            ['Aucun mode de paiement choisi', '« Sélectionnez un mode de règlement. »'],
            ['Contrat CDI', 'La durée du contrat est désactivée ; sinon 3 mois par défaut.']
        ],
        exceptions: [
            'Jours travaillés proposés : du lundi au samedi.',
            'Le type de poste est en lecture seule dans la fiche détaillée.',
            'Supprimer un employé retire sa fiche RH ; les consultations déjà signées restent dans l’historique.'
        ],
        see: ['mr-employes', 'mr-comptes']
    },
    {
        id: 'page-utilisateurs',
        title: 'Utilisateurs',
        menu: 'Administration › Utilisateurs',
        route: '/administration/utilisateurs',
        roles: 'Administrateur',
        audience: ['admin'],
        purpose: 'Comptes de connexion du personnel et du portail patient. Aucun mot de passe n’est saisi à la création : il se pose ensuite avec l’icône de clé.',
        figure: {
            label: 'Utilisateurs',
            caption: 'Liste des comptes, filtre Employes / Patients et actions.',
            markers: [
                { x: 20, y: 10, text: 'Filtre Employes / Patients.' },
                { x: 86, y: 10, text: 'Nouvel utilisateur.' },
                { x: 85, y: 50, text: 'Clé : réinitialiser le mot de passe.' }
            ]
        },
        actions: [
            ['Nouvel utilisateur', 'Dialogue « Ajouter un utilisateur » : Nom d\'utilisateur, Role, employé ou patient associé.', 'Admin'],
            ['Onglet User / Employe (Staff)', 'L’Employe associe propose le rôle : Admin, Médecin, ou Réceptionniste (réceptionniste et aide-soignante).', 'Admin'],
            ['Onglet User / Patient', 'Crée un compte de portail ; le rôle est forcé à Patient.', 'Admin'],
            ['Clé', '« Réinitialiser le mot de passe » : Nouveau mot de passe, puis Réinitialiser.', 'Admin'],
            ['Supprimer', 'Retire le compte ; la fiche employé ou le dossier patient restent.', 'Admin']
        ],
        events: [
            ['Compte créé', '« Utilisateur créé. » Il ne peut pas se connecter tant que le mot de passe n’est pas posé.'],
            ['Mot de passe posé', '« Mot de passe réinitialisé. »'],
            ['Rôle Médecin', 'La personne apparaît dans les listes de médecins.']
        ],
        exceptions: ['Pour un poste « Autre », aucun rôle n’est proposé : il faut le choisir.', 'Le rôle Réceptionniste ouvre tout l’accueil, y compris la Caisse.'],
        see: ['mr-comptes']
    },
    {
        id: 'page-finances',
        title: 'Finances',
        menu: 'Administration › Finances',
        route: '/administration/finances',
        roles: 'Administrateur',
        audience: ['admin'],
        purpose: 'L’argent du cabinet, distinct de la caisse patients, en cinq onglets : Transactions, Mode de paiement, Assurances, Charges fixes, Graphiques.',
        figure: { label: 'Finances', caption: 'Onglets Transactions, Mode de paiement, Assurances, Charges fixes, Graphiques.' },
        actions: [
            ['Ajouter (mode de paiement)', '« Ajouter un mode de paiement » : Libelle, Type de mode, Notes ; « Mode créé. »', 'Admin'],
            ['Activer / Désactiver (mode)', 'Icônes de la ligne, après confirmation ; l’historique est conservé.', 'Admin'],
            ['Activer (assurance)', 'Rend l’organisme disponible dans le formulaire patient.', 'Admin'],
            ['Nouvelle transaction', 'Revenu ou Dépense : motif, montant, date, mode.', 'Admin'],
            ['Valider / Rejeter', '« Transaction validée. » ou « Transaction rejetée. »', 'Admin'],
            ['Charges fixes', 'Ajouter une ligne, Enregistrer, Ajouter en dépense, Transaction globale.', 'Admin']
        ],
        events: [
            ['Mode actif', 'Il apparaît dans l’encaissement et la paie.'],
            ['Mode protégé', '« Ce mode ne peut pas être désactivé. »'],
            ['Assurance désactivée', 'Elle disparaît des nouveaux dossiers ; les patients rattachés la conservent.'],
            ['Transaction validée', 'Elle entre dans les tableaux et les rapports.']
        ],
        exceptions: ['La liste des assurances est fournie avec le logiciel : on active, on ne crée pas.', 'Les motifs de revenus et de dépenses se définissent dans Paramètres généraux › Caisse & finances.'],
        see: ['mr-paiements', 'mr-assurances']
    },
    {
        id: 'page-salles',
        title: 'Salles',
        menu: 'Administration › Salles',
        route: '/administration/salles',
        roles: 'Administrateur',
        audience: ['admin'],
        purpose: 'Page « Gestion des salles ». Lieux de soins proposés dans la fiche médicale et la clôture rapide.',
        figure: { label: 'Salles', caption: 'Cartes des salles et bouton Ajouter une salle.' },
        actions: [
            ['Ajouter une salle', 'Nom obligatoire, Description facultative ; Ajouter puis « Oui, ajouter ».', 'Admin'],
            ['Modifier', '« Modifier la salle » ; Enregistrer puis « Oui, modifier ».', 'Admin'],
            ['Supprimer', '« Supprimer cette salle ? Cette action est irréversible. »', 'Admin'],
            ['Exporter', 'Imprime la liste.', 'Admin']
        ],
        events: [
            ['Salle ajoutée', '« La salle a été créée. » Elle devient sélectionnable à la clôture.'],
            ['Salle supprimée', 'Les consultations passées gardent le nom déjà enregistré.']
        ],
        exceptions: ['Le statut disponible ou occupé est calculé automatiquement.'],
        see: ['mr-salles']
    },
    {
        id: 'page-consommables',
        title: 'Consommables',
        menu: 'Administration › Consommables',
        route: '/administration/consommables',
        roles: 'Administrateur',
        audience: ['admin'],
        purpose: 'Stock du cabinet, en deux vues : Liste et Variations (historique des mouvements). Chaque produit a une « Valeur de Stock Bas » qui déclenche l’alerte.',
        figure: { label: 'Consommables', caption: 'Cartes produits avec leur statut de stock.' },
        actions: [
            ['Nouveau Consommable', 'Nom du consommable, Fournisseur, Quantité, Valeur de Stock Bas.', 'Admin'],
            ['Ajouter stock / Retirer stock', 'Quantité, employé, description ; le mouvement est historisé.', 'Admin'],
            ['Modifier, Détails', 'Corrige la fiche ou affiche ses mouvements.', 'Admin'],
            ['Supprimer', '« Supprimer le consommable "nom" ? »', 'Admin'],
            ['Variations › Filtrer', 'Limite l’historique à un produit et à une période.', 'Admin']
        ],
        events: [
            ['Quantité au-dessus du seuil', 'Statut EN STOCK.'],
            ['Quantité au seuil ou en dessous', 'Statut STOCK BAS.'],
            ['Quantité à zéro', 'Statut RUPTURE DE STOCK.']
        ],
        exceptions: ['Le stock ne diminue pas automatiquement quand un soin est posé : les sorties se saisissent à la main.'],
        see: []
    },
    {
        id: 'page-notifications',
        title: 'Notifications internes',
        menu: 'Administration › Notifications',
        route: '/administration/notifications',
        roles: 'Administrateur',
        audience: ['admin'],
        purpose: 'Page « Envoyer une notification ». Message aux comptes du cabinet, avec une priorité. Ce n’est pas un SMS patient.',
        figure: { label: 'Envoi de notification', caption: 'Destinataires, priorité et message.' },
        actions: [
            ['Cocher des destinataires', 'Construit la liste ; « Sélection rapide par type » coche un groupe d’employés.', 'Admin'],
            ['Priorité', 'Faible, Normal ou Haute.', 'Admin'],
            ['Lien associé (optionnel)', 'Ouvre la page indiquée depuis la notification.', 'Admin'],
            ['Envoyer', '« Envoyer à N destinataire(s) ? » puis Envoyer.', 'Admin']
        ],
        events: [
            ['Envoi réussi', 'Chaque destinataire voit la notification dans la cloche de la barre du haut.'],
            ['Message ou destinataires manquants', '« Renseignez un message et sélectionnez au moins un destinataire. »']
        ],
        exceptions: ['Le compteur indique 1000 caractères comme repère ; restez concis.'],
        see: ['page-profil']
    },
    {
        id: 'page-avis',
        title: 'Avis et retours patients',
        menu: 'Administration › Avis & retours patients',
        route: '/administration/avis-retours-patients',
        roles: 'Administrateur',
        audience: ['admin'],
        purpose: 'Modération des avis laissés par les patients, depuis le portail ou un QR code anonyme. Indicateurs : Total avis, Avis anonymes, Avis publiés, Note moyenne.',
        figure: { label: 'Avis patients', caption: 'Indicateurs, filtres et actions de modération.' },
        actions: [
            ['Publier', '« Publier cet avis sur l\'API publique ? » ; l’avis devient visible à l’extérieur.', 'Admin'],
            ['Masquer', '« Masquer cet avis de l\'API publique ? » ; l’avis reste dans la liste.', 'Admin'],
            ['Supprimer', '« Supprimer définitivement cet avis ? Cette action est irréversible. »', 'Admin'],
            ['Filtres', 'Tous, Anonymes, Identifiés, Publiés, Non publiés, et recherche.', 'Admin']
        ],
        events: [
            ['Publication', '« L\'avis est visible via l\'API publique. »'],
            ['Masquage', '« L\'avis n\'est plus visible via l\'API publique. »'],
            ['Aucun avis pour le filtre', '« Aucun avis ne correspond au filtre courant. »']
        ],
        exceptions: ['Les indicateurs portent sur tous les avis, publiés ou non, quel que soit le filtre affiché.'],
        see: []
    },
    {
        id: 'page-parametres',
        title: 'Paramètres généraux',
        menu: 'Paramètres › Paramètres généraux',
        route: '/parametres/general-options',
        roles: 'Administrateur ; onglet Apparence seul pour l’accueil',
        audience: ['admin'],
        purpose:
            'Réglages du cabinet et du poste, en onglets : Cabinet, Portail patient, Administration (administrateur), et Apparence. Chaque bloc a son propre bouton d’enregistrement.',
        figure: {
            label: 'Paramètres généraux',
            caption: 'Onglets et blocs de réglages.',
            markers: [
                { x: 20, y: 10, text: 'Onglets de la page.' },
                { x: 30, y: 50, text: 'Bloc de réglages.' },
                { x: 85, y: 75, text: 'Bouton Enregistrer du bloc.' }
            ]
        },
        actions: [
            ['Apparence', 'Thème, couleurs, police, navigation Classique (sidebar) ou Accueil cartes ; bouton Sauvegarder. Effet sur ce poste.', 'Admin, accueil'],
            ['Cabinet', 'Identité & SMS, Consultations & réception, Horaires d\'ouverture, Interface médecin, Fiche clinique, Caisse & finances, Catalogue des soins.', 'Admin'],
            ['Portail patient', 'Activation du portail, création automatique des comptes, QR code, Créer les comptes manquants.', 'Admin'],
            ['Appareils autorisés', 'Approuver, Refuser, Renommer, Supprimer ; Approbation automatique ; Journal d\'accès.', 'Admin'],
            ['Maintenance', 'Activer le mode test global (Appliquer), Nettoyer les tests, Créer sauvegarde/export.', 'Admin']
        ],
        events: [
            ['Médecin requis à la création', 'Le champ Médecin devient obligatoire à la création de consultation.'],
            ['Formulaire simplifié de fiche consultation', 'La fiche se réduit à la synthèse clinique, au devis, aux séances et à la consultation en cours.'],
            ['Approbation automatique', 'Les nouveaux postes entrent sans écran d’attente.'],
            ['Désactivation du mode test', 'Choix entre Supprimer les données de test et Conserver les données.']
        ],
        exceptions: [
            'Une catégorie de soins ne se supprime que lorsqu’elle ne contient plus aucun acte.',
            'Le logo de l’écran de connexion fait partie de l’installation ; il ne se change pas depuis cette page.',
            'Le masquage du dossier ou des téléphones ne s’applique pas à un médecin qui est aussi administrateur.'
        ],
        warning: '« Reset complet base » efface les données du cabinet et ne conserve que le compte initial. Il n’est jamais nécessaire en exploitation normale : faites plutôt une sauvegarde.',
        see: ['mr-cabinet', 'mr-tarifs', 'mr-postes']
    },
    {
        id: 'page-sms',
        title: 'API SMS',
        menu: 'Paramètres › API SMS',
        route: '/administration/api-sms',
        roles: 'Administrateur, si les fonctions Internet sont actives',
        audience: ['admin'],
        purpose: 'Fournisseur d’envoi, file des SMS, journaux et modèles. Six onglets : Aperçu, Configuration & Test, File SMS, Logs, Templates, Envoi Manuel. Sans Internet, l’entrée de menu disparaît.',
        figure: { label: 'Configuration SMS', caption: 'Onglets de configuration, file, journaux et modèles.' },
        actions: [
            ['Sauvegarder', 'Enregistre le fournisseur (Orange ou AfrikSms), l’activation et les exceptions.', 'Admin'],
            ['Test connexion, Envoyer SMS test', 'Vérifie le fournisseur avant d’activer les rappels.', 'Admin'],
            ['Programmer', 'Place un SMS dans la file : Sans répétition, Tous les jours x3, Toutes les semaines x4.', 'Admin'],
            ['Annuler, Renvoyer, Reprogrammer', 'Agit sur un SMS en attente ou en échec.', 'Admin'],
            ['Traiter file', 'Envoie les SMS arrivés à échéance.', 'Admin'],
            ['Templates', 'Modèles avec variables ; Prévisualiser, puis Sauvegarder templates.', 'Admin'],
            ['Envoi Manuel', 'Message libre à un numéro, éventuellement à partir d’un modèle.', 'Admin']
        ],
        events: [
            ['File traitée', 'Statut Envoyé, Livré, Échec ou En attente dans les Logs.'],
            ['Patient désabonné ou numéro blacklisté', 'Les envois automatiques ne partent pas, sauf exception cochée (bypass).']
        ],
        exceptions: ['Le nom affiché dans les messages est le « Nom du centre (SMS) » des Paramètres généraux.'],
        tech: ['L’envoi effectif dépend aussi d’un service du serveur. Un test réussi ne garantit pas les rappels automatiques si ce service est arrêté.'],
        see: ['mr-cabinet']
    },
    {
        id: 'page-profil',
        title: 'Mon profil',
        menu: 'Menu du compte › Mon profil',
        route: '/profile',
        roles: 'Tout utilisateur connecté',
        audience: ['admin', 'accueil', 'medecin'],
        purpose: 'Informations personnelles, mot de passe et notifications. Le rôle affiché vient du compte et ne se modifie pas ici.',
        figure: { label: 'Mon profil', caption: 'Informations, mot de passe, notifications et raccourcis.' },
        actions: [
            ['Enregistrer', '« Confirmer la mise à jour du profil ? » puis Mettre à jour : « Profil mis à jour ».', 'Tous'],
            ['Mettre à jour (mot de passe)', 'Exige l’ancien mot de passe, le nouveau et la confirmation.', 'Tous'],
            ['Tout lire', 'Marque les notifications comme lues, après confirmation.', 'Tous'],
            ['Activees / Desactivees', 'Coupe ou rétablit la réception des notifications pour ce compte.', 'Tous']
        ],
        events: [
            ['Mot de passe changé', '« Mot de passe mis à jour »'],
            ['Ancien mot de passe incorrect', '« Mot de passe incorrect ou invalide. » Aucun changement.'],
            ['Profil mis à jour', 'Le nom affiché dans la barre du haut change immédiatement.']
        ],
        exceptions: ['Les raccourcis suivent le rôle : un médecin n’y trouve pas la Caisse.', 'Les notifications du navigateur demandent aussi l’autorisation du navigateur.'],
        see: ['mr-comptes']
    },
    {
        id: 'page-manuel',
        title: 'Manuel d’utilisation',
        menu: 'Documentation › Manuel d\'utilisation, ou Menu du compte › Manuel d\'utilisation',
        route: '/manual',
        roles: 'Tout utilisateur connecté',
        audience: ['admin', 'accueil', 'medecin'],
        purpose: 'Le présent document. Il s’adapte au profil du lecteur et s’imprime ou s’exporte en PDF.',
        figure: {
            label: 'Manuel d’utilisation',
            caption: 'Sommaire, filtre de profil et bouton d’impression.',
            markers: [
                { x: 12, y: 18, text: 'Filtre « Lire en tant que ».' },
                { x: 12, y: 50, text: 'Sommaire : la section en cours de lecture est surlignée.' },
                { x: 12, y: 80, text: 'Imprimer ou exporter en PDF.' }
            ]
        },
        actions: [
            ['Lire en tant que', 'Masque les sections qui ne concernent pas le profil choisi, à l’écran comme à l’impression.', 'Tous'],
            ['Sommaire', 'Fait défiler jusqu’au chapitre ou à la section choisie.', 'Tous'],
            ['Imprimer ou exporter en PDF', 'Ouvre l’impression du navigateur : couverture, sommaire, un chapitre par page, pied de page numéroté.', 'Tous']
        ],
        events: [['Ouverture du manuel', 'Le profil de lecture est présélectionné d’après votre rôle ; l’administrateur voit tout le manuel.']],
        exceptions: ['Pour un PDF propre, choisissez « Enregistrer au format PDF » et décochez les en-têtes et pieds de page du navigateur.'],
        see: ['conventions']
    }
];

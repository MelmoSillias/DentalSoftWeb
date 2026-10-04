/**
 * Scénarios du chapitre 5, du plus simple au plus délicat.
 * `audience` : profils concernés (admin, accueil, medecin), utilisé par le filtre de lecture.
 */
export const scenarios = [
    {
        id: 'sc-journee-accueil',
        title: 'Une matinée à l’accueil',
        level: 'Simple',
        audience: ['admin', 'accueil'],
        profile: { name: 'Fatou, réceptionniste', icon: 'pi pi-user', context: 'Travaille toute la journée dans le Mode Focus, mode Reception.' },
        situation:
            'Le cabinet ouvre à 8 h. Trois patients ont un rendez-vous ce matin, deux autres viendront sans. L’imprimante ticket est allumée, le Dr Diarra arrive à 8 h 15.',
        steps: [
            { text: 'Ouvrir le Mode Focus et vérifier que « Temps réel » est allumé. La file doit être vide, ou ne contenir que des consultations déjà créées.' },
            { text: 'Passer en mode Rendez-vous pour repérer les créneaux du matin, puis revenir en mode Reception.' },
            { text: 'À l’arrivée du premier patient, le rechercher par son téléphone. S’il est nouveau, « Ajouter », puis « Nouvelle consultation » en activant « Consultation payante ».', result: 'Le patient entre dans la file ; le ticket est proposé.' },
            { text: 'Pour un patient attendu, valider son rendez-vous et répondre Oui à « Créer une consultation maintenant ? ».' },
            { text: 'À la sortie du fauteuil, « Clôture rapide » si la visite est simple ; sinon, attendre que le médecin clôture depuis le mode Dentiste.' },
            { text: 'Ouvrir la ligne, « Régler », remettre le reçu.', critical: true, result: 'La ligne quitte la liste des factures à régler.' }
        ],
        decisions: [
            {
                question: 'Le patient a un reliquat d’une visite précédente.',
                options: [
                    { when: 'Il veut tout régler', then: 'Encaissez chaque onglet du dialogue de paiement, l’un après l’autre.' },
                    { when: 'Il règle seulement aujourd’hui', then: 'Encaissez l’onglet du jour et fermez le dialogue ; le reliquat restera signalé.' }
                ]
            }
        ],
        result: 'À midi, la file ne contient plus que les patients encore au cabinet. Le bouton Encaissements affiche un total qui correspond au contenu du tiroir.',
        variants: [
            ['Le patient part sans payer', 'Ne touchez pas à la facture. Elle reste Impayé, l’icône reliquat la signalera à la prochaine visite et la caisse la retrouvera avec le filtre des impayées.'],
            ['L’imprimante ne répond pas', 'Encaissez quand même ; réimprimez le reçu plus tard depuis l’icône reçu du paiement validé.']
        ]
    },
    {
        id: 'sc-consultation-medecin',
        title: 'La consultation complète du médecin',
        level: 'Simple',
        audience: ['admin', 'medecin'],
        profile: { name: 'Dr Diarra, chirurgien-dentiste', icon: 'pi pi-heart', context: 'Mode Focus, mode Dentiste.' },
        situation:
            'Awa Koné, nouvelle patiente, attend dans la file. Le Dr Diarra veut noter une allergie, faire l’examen, établir un devis en deux séances et prescrire un antalgique.',
        steps: [
            { text: 'Sélectionner sa ligne dans la file. La fiche s’ouvre au centre.' },
            { text: 'Dans le dossier à gauche, ajouter l’allergie signalée.' },
            { text: 'Remplir Questionnaire médical, Examens et Plan de traitement, en sauvegardant chaque section.' },
            { text: 'Établir le devis, le sauvegarder, puis l’imprimer pour le remettre à la patiente.', result: 'Le devis imprimé est un document d’information ; ce n’est pas la facture.' },
            { text: 'Dans Consultation en cours : salle, type « Première Consultation », soins réalisés, puis « Nouvelle ordonnance ».' },
            { text: 'Clôturer et confirmer « Cloturer definitivement cette consultation ? ».', critical: true, result: '« Consultation cloturee » : la fiche passe en lecture seule ; l’accueil voit la facture des soins.' }
        ],
        decisions: [
            {
                question: 'La clôture est refusée.',
                options: [
                    { when: 'Médecin manquant', then: 'Sélectionnez le médecin dans la consultation en cours, puis recommencez.' },
                    { when: 'Section non sauvegardée', then: 'Repérez la section marquée « Modifie », sauvegardez-la, puis recommencez.' }
                ]
            }
        ],
        result: 'L’historique affiche la consultation « Clôturée ». La facture des actes réalisés attend l’encaissement à l’accueil ; le devis reste une proposition pour les séances suivantes.',
        variants: [['Le cabinet utilise le formulaire simplifié', 'Une seule « Synthèse clinique » remplace questionnaire, examens, images, bilans et plan. Devis, séances et consultation en cours restent séparés ; la clôture obéit aux mêmes règles.']]
    },
    {
        id: 'sc-assure',
        title: 'Un patient assuré',
        level: 'Intermédiaire',
        audience: ['admin', 'accueil'],
        profile: { name: 'Accueil et administrateur', icon: 'pi pi-shield', context: 'L’accueil gère la part patient ; l’administrateur ou l’accueil gère le lot d’assurance dans la Caisse.' },
        situation:
            'Moussa Traoré est couvert à 80 % par un organisme activé. Il règle sa part aujourd’hui ; la part de l’assurance sera regroupée avec les autres factures de la semaine.',
        steps: [
            { text: 'Dans le dossier patient, onglet assurance : cocher « Patient assuré », choisir l’organisme, le taux et les identifiants demandés.' },
            { text: 'Créer la consultation en activant « Consultation payante ». Le bandeau « Patient assuré — organisme (couverture 80 %) » s’affiche ; le mode de paiement n’est pas exigé à ce stade.' },
            { text: 'Après la clôture, « Régler » : le dialogue montre l’assurance, le taux et la part assurance ; le montant proposé est la part patient.', critical: true },
            { text: 'En fin de semaine, Caisse › Assurances : ouvrir l’organisme, « Créer un lot » sur la période, puis y déposer les factures.' },
            { text: '« Envoyer » le lot avec le bordereau imprimé. Au retour de l’organisme, « Confirmer », puis « Rembourser » avec le mode et le montant reçus.' }
        ],
        decisions: [
            {
                question: 'L’organisme ne rembourse qu’une partie du lot.',
                options: [
                    { when: 'Paiement partiel reçu', then: 'Saisissez le montant reçu : le lot passe à Partiellement remboursé et reste suivi.' },
                    { when: 'Rejet d’une facture', then: 'Traitez la facture concernée avec l’administrateur avant de solder le lot.' }
                ]
            }
        ],
        figure: { label: 'Lot d’assurance', caption: 'Factures sans lot d’un côté, lots ouverts de l’autre.' },
        result: 'La part patient est payée le jour même. Le lot passe successivement par Ouvert, Envoyé, Confirmé, puis Remboursé.',
        variants: [['Le patient n’a pas sa carte', 'Décidez avant de créer la consultation : soit il présente ses justificatifs plus tard et la consultation attend, soit il est traité comme non assuré et règle la totalité. Corriger une facture déjà encaissée demande l’administrateur.']],
        callouts: [{ type: 'warn', text: 'On ne dépose une facture que dans un lot Ouvert. Une facture assurance ne se modifie pas comme une facture classique.' }]
    },
    {
        id: 'sc-reliquat',
        title: 'Reliquat et paiement échelonné',
        level: 'Intermédiaire',
        audience: ['admin', 'accueil'],
        profile: { name: 'Accueil', icon: 'pi pi-wallet', context: 'Mode Focus ou Caisse.' },
        situation: 'Le même patient revient. Son ancienne facture laisse un reste de 15 000 F. Aujourd’hui, il ne peut verser que 5 000 F.',
        steps: [
            { text: 'Repérer l’icône portefeuille rouge sur sa ligne (« Reliquat : 15 000 FCFA ») ; dans le Mode Focus, le détail affiche aussi « Reliquats antérieurs ».' },
            { text: '« Régler » ouvre toutes ses factures impayées, une par onglet ; le pied du dialogue indique le « Reliquat total ».' },
            { text: 'Sur l’onglet de l’ancienne facture, saisir 5 000. « Reste après paiement » affiche ce qui restera dû.' },
            { text: 'Confirmer le paiement et imprimer le reçu des 5 000 F.', critical: true, result: 'La facture passe à Partiellement payé ; le dialogue reste ouvert avec le nouveau reste.' }
        ],
        decisions: [],
        result: 'Le reçu ne couvre que le versement du jour. Le reliquat reste visible à la prochaine visite et dans la Caisse avec le filtre des impayées.',
        variants: [
            ['Il solde tout', 'Après le dernier franc, le dialogue passe à l’onglet suivant, ou se ferme s’il n’en reste plus.'],
            ['Montant supérieur au reste', 'Le logiciel refuse : on ne peut pas encaisser plus que ce qui est dû.']
        ]
    },
    {
        id: 'sc-urgence',
        title: 'Une urgence en plein planning',
        level: 'Intermédiaire',
        audience: ['admin', 'accueil', 'medecin'],
        profile: { name: 'Accueil puis médecin', icon: 'pi pi-exclamation-circle', context: 'Mode Focus.' },
        situation: 'Un patient connu arrive sans rendez-vous, la joue enflée. Le planning de l’après-midi est complet.',
        steps: [
            { text: 'Le retrouver par son téléphone dans le Mode Focus. Ne pas créer de second dossier.' },
            { text: 'Ouvrir la consultation tout de suite, sans passer par l’agenda.' },
            { text: 'Prévenir le médecin de vive voix : la file est classée par ancienneté, pas par gravité.' },
            { text: 'Le médecin choisit le type « Urgence Dentaire » dans Consultation en cours, soigne, puis clôture.', critical: true }
        ],
        decisions: [
            {
                question: 'Le logiciel indique qu’une consultation est déjà ouverte pour ce patient.',
                options: [
                    { when: 'Ouverte ce matin', then: 'Continuez-la : un patient n’a qu’une consultation ouverte à la fois.' },
                    { when: 'Oubli d’une visite passée', then: 'Faites-la clôturer ou annuler (si aucune fiche n’y est liée), puis créez la nouvelle.' }
                ]
            }
        ],
        result: 'Le patient est soigné entre deux rendez-vous et la visite est tracée avec le type « Urgence Dentaire » dans sa fiche.',
        variants: [['Le médecin est absent', 'Ne créez pas la consultation au nom d’un autre médecin sans son accord ; orientez le patient et notez l’appel.']]
    },
    {
        id: 'sc-correction',
        title: 'Corriger une erreur de saisie',
        level: 'Avancé',
        audience: ['admin', 'accueil'],
        profile: { name: 'Accueil, avec l’administrateur', icon: 'pi pi-undo', context: 'Caisse ou Mode Focus.' },
        situation: 'Un soin a été facturé deux fois par erreur, et le patient a déjà versé un acompte de 10 000 F.',
        steps: [
            { text: 'Ouvrir la facture et constater que « Modifier » n’est pas proposé : un paiement existe déjà.' },
            { text: 'Appeler l’administrateur. Ne pas encaisser le solde erroné.' },
            { text: 'L’administrateur ouvre « Régler » et clique « Réinitialiser » : l’acompte enregistré est effacé. Dans le Mode Focus, ce bouton agit sans demander de confirmation.', critical: true },
            { text: 'Corriger la facture avec « Modifier ».' },
            { text: 'Ré-encaisser immédiatement l’acompte de 10 000 F avec le même mode et l’heure réelle du versement, puis réimprimer le reçu.', critical: true }
        ],
        decisions: [
            {
                question: 'Faut-il réinitialiser ?',
                options: [
                    { when: 'Erreur de montant', then: 'Oui, puis ressaisie immédiate des paiements réellement reçus.' },
                    { when: 'Patient qui ne paie pas', then: 'Non. Un impayé n’est pas une erreur : la facture reste en reliquat.' }
                ]
            }
        ],
        result: 'La facture est juste, les paiements reflètent l’argent réellement reçu et le patient repart avec un reçu exact.',
        variants: [['Aucun paiement n’a encore été fait', 'Modifier suffit (administrateur, ou accueil si le réglage l’autorise). Aucune réinitialisation n’est nécessaire.']],
        callouts: [{ type: 'critical', text: 'Réinitialiser efface tous les paiements de la facture. Notez-les avant, pour les ressaisir à l’identique.' }]
    },
    {
        id: 'sc-fin-de-mois',
        title: 'La fin de mois de l’administrateur',
        level: 'Avancé',
        audience: ['admin'],
        profile: { name: 'Administrateur', icon: 'pi pi-briefcase', context: 'Caisse, Gestion RH, Finances, Rapports, Paramètres.' },
        situation: 'Dernier jour du mois : il faut contrôler les impayés, envoyer les lots d’assurance, payer les salaires et sortir le rapport.',
        steps: [
            { text: 'Caisse, période du mois, filtre « Toutes les impayées » pour voir aussi les restes antérieurs. Relancer ou encaisser.' },
            { text: 'Onglet Assurances : envoyer les lots encore ouverts et imprimer leurs bordereaux.' },
            { text: 'Gestion RH, onglet Gestion de la paie : pour chaque employé, « Ajouter un paiement » sur le mois, puis « Imprimer le bulletin ». Pour un médecin au pourcentage, contrôler la base calculée.', critical: true },
            { text: 'Finances : « Ajouter en dépense » les charges fixes et valider les transactions en attente.' },
            { text: 'Rapports : choisir la période du mois, vérifier les onglets, imprimer le tableau de l’onglet Médecins.' },
            { text: 'Paramètres généraux, onglet Administration : « Créer sauvegarde/export » et télécharger le fichier.' }
        ],
        decisions: [
            {
                question: 'Une facture du mois reste impayée.',
                options: [
                    { when: 'Paiement attendu', then: 'Laissez-la en reliquat et relancez le patient.' },
                    { when: 'Erreur de facturation', then: 'Appliquez le scénario 5.6 avant de payer le médecin, pour que sa base soit juste.' }
                ]
            }
        ],
        result: 'Le rapport du mois, le tiroir et la paie racontent la même histoire, parce que factures, lots et transactions ont été traités avant l’impression.',
        variants: [['Un salaire du mois a déjà été versé', '« Cette période est déjà entièrement réglée. » : le logiciel bloque un second versement.']],
        callouts: [{ type: 'critical', text: '« Reset complet base » n’est pas une clôture de mois : il efface les données du cabinet. La clôture normale, c’est la sauvegarde.' }]
    },
    {
        id: 'sc-nouvelle-recrue',
        title: 'Accueillir une nouvelle recrue',
        level: 'Simple',
        audience: ['admin'],
        profile: { name: 'Administrateur', icon: 'pi pi-user-plus', context: 'Gestion RH, Utilisateurs, ce manuel.' },
        situation: 'Une nouvelle réceptionniste commence lundi. Elle doit pouvoir travailler dès la première heure.',
        steps: [
            { text: 'Gestion RH : créer sa fiche avec le poste « Réceptionniste », son contrat et ses jours travaillés.' },
            { text: 'Utilisateurs : créer son compte en la choisissant comme Employe associe ; le rôle Réceptionniste est proposé.', result: 'Elle aura accès au Mode Focus, à l’Agenda, aux Patients, à l’Historique, aux Services cabinets, à la Caisse et aux Rapports.' },
            { text: 'Poser son mot de passe avec la clé, et le lui remettre en main propre.', critical: true },
            { text: 'Ouvrir ce manuel, choisir « Lire en tant que : Accueil », puis « Imprimer ou exporter en PDF ».', result: 'Un PDF limité aux sections qui la concernent.' },
            { text: 'Le premier jour, lui faire dérouler le scénario 5.1 à côté d’un collègue.' }
        ],
        decisions: [
            {
                question: 'Peut-elle corriger une facture avant encaissement ?',
                options: [
                    { when: 'Pas encore', then: 'Laissez décoché « Modification de facture par les secrétaires » : elle signale les erreurs à l’administrateur.' },
                    { when: 'Oui', then: 'Cochez ce réglage dans Paramètres généraux › Caisse & finances. Elle pourra modifier une facture tant qu’aucun paiement n’y est enregistré.' }
                ]
            }
        ],
        result: 'Lundi matin, elle se connecte sur un poste déjà autorisé, change son mot de passe dans Mon profil et accueille son premier patient. L’aide guidée de chaque page complète le manuel.',
        variants: [['Elle utilise un nouvel ordinateur', 'Approuvez le poste dans Appareils autorisés lors de sa première connexion.']]
    }
];

# Parkour Guided Tour DentalSoft

## 1. Objet

Ce document sert de source de verite pour le guided tour overlay declenche par un nouveau bouton Aide dans le header.

Le tour doit etre resolu a partir de deux clefs:

- route.name
- role utilisateur

Pour les pages a onglets ou a sous-vues majeures, la cle technique recommandee est:

- route.name + ':' + sousVue

Exemples utiles:

- caisse:overview
- caisse:factures
- caisse:paiements
- administration-finances:tables
- administration-finances:charts

## 2. Regles globales d'implementation

- Le bouton Aide doit etre ajoute dans AppTopbar, idealement avant la cloche des notifications.
- Le bouton doit etre visible sur les pages sous AppLayout.
- La page login n'a pas de header applicatif: si un tour est souhaite, il faut un CTA local dans la carte de connexion.
- Ne pas ancrer le tour sur les classes PrimeVue uniquement. Ajouter des attributs data-tour stables.
- Convention d'ancrage recommandee: page.zone ou page.sousVue.zone.
- Afficher 4 a 6 steps sur les pages simples.
- Afficher 6 a 8 steps sur les pages denses comme dossier patient ou fiche de consultation.
- Si une zone n'existe pas, si la table est vide ou si un dialogue n'est pas ouvert, le step doit etre saute automatiquement.
- Si une page depend d'un contexte charge, ne pas lancer le tour detaille tant que la ressource n'est pas disponible.
- Le footer du composant de tour doit contenir Precedent, Suivant, Ignorer, Terminer et un compteur x / n.
- Memoriser l'etat vu par route et par role. Ne pas partager un meme etat entre admin, medecin et reception.

## 3. Jeu de donnees de demo recommande pour les captures

- Un patient complet avec antecedents, allergies, fiche medicale, rendez-vous, paiements et factures.
- Une semaine d'agenda avec rendez-vous en attente, valides, reportes et annules.
- Des consultations ouvertes, cloturees et une fiche legacy encore accessible.
- Des factures impayees, partiellement payees, vides a valider et plusieurs paiements par mode.
- Des transactions financieres validees, en attente et rejetees.
- Des consommables en stock, en stock bas et en rupture.
- Des salles disponibles et occupees.
- Des utilisateurs de plusieurs types et des employes avec conges et documents.

## 4. Pages hors perimetre ou a traiter a part

- landing: pas prioritaire pour un tour metier.
- accessDenied: pas de tour necessaire.
- error: pas de tour necessaire.
- notfound: pas de tour necessaire.

## 5. Tours par page

### Dashboard

- Cle: dashboard
- Roles: ROLE_ADMIN, ROLE_RECEPTION, ROLE_MEDECIN
- Capture recommandee: oui, une capture par role avec donnees chargees. Noms proposes: dashboard-admin-overview, dashboard-reception-overview, dashboard-medecin-overview.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | dashboard.header | Tableau de bord du jour | Cette page centralise les indicateurs utiles des la connexion. Le contenu s'adapte a votre role et a la periode choisie. |
| 2 | dashboard.filters | Filtrer les donnees | Choisissez une date unique ou une plage de dates. Toutes les cartes, tableaux et rapports se recalculent sur ce perimetre. |
| 3 | dashboard.quick-stats | Lire les indicateurs cles | Ces cartes donnent une vue rapide sur les patients, consultations, rendez-vous et montants suivis dans le cabinet. |
| 4 | dashboard.main-report | Approfondir l'analyse | Le bloc principal affiche soit un carrousel analytique, soit un tableau reception par medecin selon votre profil. |
| 5 | dashboard.tabs-panel | Suivi operationnel | Le panneau lateral regroupe les details complementaires que vous devez surveiller au quotidien. |
| 6 | dashboard.notifications | Alertes recentes | Les notifications de profil vous aident a prioriser les actions a faire sans quitter le tableau de bord. |

Notes role:

- Admin: mettre l'accent sur les slides finances et rapports par medecin.
- Medecin: mettre l'accent sur les montants generes, consultations par jour et raccourcis agenda/patients.
- Reception: mettre l'accent sur les rapports periodiques par medecin et le suivi journalier.

### Agenda - Rendez-vous

- Cle: agenda-rendezvous
- Roles: ROLE_ADMIN, ROLE_RECEPTION, ROLE_MEDECIN
- Capture recommandee: oui, avec une semaine contenant au moins 4 rendez-vous et plusieurs statuts. Nom propose: agenda-rendezvous-week.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | agenda-rdv.header | Gestion des rendez-vous | Cette page sert a planifier et suivre les rendez-vous du cabinet sur une vue jour ou semaine. |
| 2 | agenda-rdv.legend | Comprendre les statuts | La legende rappelle les couleurs utilisees pour distinguer les rendez-vous en attente, valides, reportes ou annules. |
| 3 | agenda-rdv.tabs | Changer de vue | Basculez entre la vue hebdomadaire et la vue journaliere selon le niveau de detail souhaite. |
| 4 | agenda-rdv.calendar | Interagir avec le planning | Cliquez sur un creneau libre pour creer un rendez-vous, ou sur un rendez-vous existant pour le valider, le reporter ou l'annuler. |
| 5 | agenda-rdv.dialogs | Actions rapides | Les dialogues associes permettent de creer, valider, reporter, annuler et programmer des rappels SMS. |
| 6 | agenda-rdv.scope | Portee medecin | Si vous etes medecin, la vue est verrouillee sur votre agenda. Les autres profils peuvent naviguer entre plusieurs medecins. |

### Agenda - Evenements

- Cle: agenda-evenements
- Roles: ROLE_ADMIN
- Capture recommandee: oui, avec des evenements valides et non valides. Nom propose: agenda-evenements-month.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | agenda-events.header | Agenda des evenements | Cette page gere les evenements generaux du cabinet en dehors des rendez-vous patients. |
| 2 | agenda-events.create | Ajouter un evenement | Utilisez ce bouton pour declarer un nouvel evenement dans le calendrier. |
| 3 | agenda-events.calendar | Naviguer dans le calendrier | Le calendrier permet de changer de mois, de passer a une vue semaine et de visualiser rapidement les evenements a venir. |
| 4 | agenda-events.status | Lire l'etat des evenements | Les couleurs du calendrier distinguent les evenements valides des evenements encore en attente. |
| 5 | agenda-events.actions | Ouvrir les actions contextuelles | Un clic droit sur un evenement ouvre les actions de validation ou suppression. |

### Patients - Liste

- Cle: patients-liste
- Roles: ROLE_ADMIN, ROLE_RECEPTION, ROLE_MEDECIN
- Capture recommandee: oui, avec au moins 5 patients et une derniere consultation visible. Nom propose: patients-liste-table.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | patients-list.toolbar | Actions principales | Depuis cette barre, vous pouvez ajouter un patient, creer un rendez-vous et, selon votre role, ouvrir une nouvelle consultation. |
| 2 | patients-list.search | Rechercher un patient | La recherche filtre la liste en direct pour retrouver rapidement un dossier ou un contact. |
| 3 | patients-list.table | Parcourir la base patient | Le tableau affiche les informations utiles du patient, y compris la derniere consultation quand elle existe. |
| 4 | patients-list.row-actions | Acceder aux actions de ligne | Chaque patient propose des actions rapides vers le dossier, le rendez-vous, la consultation ou la modification. |
| 5 | patients-list.active-consult | Eviter les doublons | Si une consultation est deja ouverte, un avertissement bloque la creation d'une nouvelle consultation et propose de reprendre le flux correct. |
| 6 | patients-list.stats | Lire le resume | Les cartes en bas donnent un resume rapide du volume de patients et de l'activite du jour. |

Note role:

- Medecin: ne pas presenter le bouton de creation globale de consultation s'il n'est pas visible.

### Patients - Dossier patient

- Cle: patients-dossier
- Roles: ROLE_ADMIN, ROLE_RECEPTION, ROLE_MEDECIN
- Capture recommandee: oui, avec un patient complet. Nom propose: patients-dossier-complet.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | patients-dossier.selector | Changer de patient | Ce selecteur permet d'ouvrir un autre dossier sans repasser par la liste des patients. |
| 2 | patients-dossier.info-card | Resume du patient | La carte de gauche centralise les informations personnelles, les antecedents, allergies et actions rapides. |
| 3 | patients-dossier.actions | Lancer une action metier | Depuis le dossier, vous pouvez imprimer, modifier le patient, ajouter un rendez-vous ou ouvrir une consultation. |
| 4 | patients-dossier.medical | Suivi clinique | La colonne centrale affiche soit les fiches medicales, soit la liste des consultations selon votre role. |
| 5 | patients-dossier.finance | Rendez-vous, paiements et factures | Cette zone relie l'historique de rendez-vous, les paiements et les factures pour une vue complete du dossier. |
| 6 | patients-dossier.dialogs | Completer le dossier | Les dialogues servent a enrichir le dossier: ajout d'allergie, antecedent, impression ou edition. |

Note de contexte:

- Si aucun patient n'est charge, lancer un tour reduit a 2 steps: selecteur de patient puis explication de chargement du dossier.

### Consultations - File d'attente

- Cle: consultations-cards
- Roles: ROLE_ADMIN, ROLE_MEDECIN
- Capture recommandee: oui, avec au moins 3 consultations ouvertes. Nom propose: consultations-cards-queue.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | consultations-cards.counter | Taille de la file | Cette carte indique combien de consultations sont encore ouvertes ou en attente de prise en charge. |
| 2 | consultations-cards.refresh | Mettre la file a jour | Utilisez ce bouton pour rafraichir la liste sans quitter la page. |
| 3 | consultations-cards.list | Prioriser les patients | Les cartes sont triees par anciennete pour vous aider a traiter d'abord les attentes les plus longues. |
| 4 | consultations-cards.waiting | Lire l'urgence | Les indicateurs visuels mettent en avant le temps d'attente et la priorite de chaque consultation. |
| 5 | consultations-cards.actions | Continuer ou creer une fiche | Selon le contexte, vous pouvez reprendre la derniere fiche, ouvrir la fiche liee ou en creer une nouvelle. |
| 6 | consultations-cards.cancel | Annuler proprement | L'annulation est reservee aux cas ou la consultation doit etre retiree de la file. |

### Consultations - Historique / Table

- Cle: consultations-table
- Roles: ROLE_ADMIN, ROLE_RECEPTION, ROLE_MEDECIN
- Capture recommandee: oui, avec consultations ouvertes et cloturees. Nom propose: consultations-table-day.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | consultations-table.header | Historique des consultations | Cette page liste les consultations du jour ou de la date choisie, avec leurs statuts et actions. |
| 2 | consultations-table.filters | Filtrer la journee | Combinez la recherche et le filtre de date pour isoler rapidement un patient, un medecin ou un statut. |
| 3 | consultations-table.table | Lire la liste | Le tableau affiche patient, medecin, date de creation et statut de chaque consultation. |
| 4 | consultations-table.status | Reconnaitre l'etat | Les badges distinguent les consultations en cours, cloturees et les cas urgents. |
| 5 | consultations-table.actions | Ouvrir les actions utiles | Depuis une ligne, vous pouvez ouvrir le dossier, afficher les details, gerer la facture, continuer la fiche ou annuler la consultation. |
| 6 | consultations-table.dialogs | Dialogues metier | Les modales associees servent a creer une consultation, modifier une facture ou afficher le detail complet. |

Note role:

- Medecin: retirer le step sur le bouton Nouvelle consultation si le bouton n'est pas visible.

### Consultations - Fiche moderne

- Cle: consultations-form
- Roles: ROLE_ADMIN, ROLE_MEDECIN
- Capture recommandee: non obligatoire. La page est trop contextuelle pour une capture unique fiable. Si une capture est prise, utiliser une fiche de demo complete et la nommer consultations-form-complete.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | consultations-form.header | Fiche medicale | Cette page regroupe tout le suivi de la consultation en cours pour un patient donne. |
| 2 | consultations-form.save-indicator | Suivi des sauvegardes | Cet indicateur montre les sections modifiees, l'etat d'auto-sauvegarde et permet de forcer un enregistrement global. |
| 3 | consultations-form.switcher | Naviguer entre les sections | Utilisez l'affichage Onglets ou Sidebar pour passer rapidement d'une partie clinique a une autre. |
| 4 | consultations-form.patient-info | Relire le contexte patient | La section Informations patient resume les donnees de base, allergies et antecedents utiles avant toute saisie. |
| 5 | consultations-form.section-body | Saisir la fiche section par section | Entretien, examens, documents, bilans, plan de traitement et devis se remplissent independamment mais restent lies a la meme consultation. |
| 6 | consultations-form.consultation | Finaliser la consultation | La section Consultation en cours gere les actes, les soignants, la salle, l'ordonnance et la cloture definitive. |

### Consultations - Fiche legacy

- Cle: consultations-form-legacy
- Roles: ROLE_ADMIN, ROLE_MEDECIN
- Capture recommandee: non. Meme logique que la fiche moderne, mais sur l'ancien modele de saisie.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | consultations-form-legacy.header | Fiche legacy | Cette vue conserve l'ancien parcours de saisie pour certaines consultations ou anciennes fiches. |
| 2 | consultations-form-legacy.save-indicator | Suivi des modifications | Le bandeau de sauvegarde montre ce qui a ete modifie et ce qui doit encore etre enregistre. |
| 3 | consultations-form-legacy.switcher | Changer de section | Passez des infos patient au motif, aux examens, aux traitements, au devis et a la consultation en cours. |
| 4 | consultations-form-legacy.motif | Documenter le motif | Commencez par le motif et l'histoire de la maladie avant de remplir les examens et traitements. |
| 5 | consultations-form-legacy.traitements | Ajouter les traitements et documents | Cette zone regroupe la prise en charge, les documents et les pieces utiles au suivi. |
| 6 | consultations-form-legacy.consultation | Cloturer l'ancien flux | La section finale sert a completer la consultation, ajouter une ordonnance et fermer proprement le dossier. |

### Caisse - Vue d'ensemble

- Cle: caisse:overview
- Roles: ROLE_ADMIN, ROLE_RECEPTION
- Capture recommandee: oui, avec factures et paiements. Nom propose: caisse-overview.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | caisse.tabs | Choisir la sous-vue | Les onglets separent la vue d'ensemble, les factures et les paiements selon le besoin du moment. |
| 2 | caisse-overview.stats | Lire les chiffres du jour | Les cartes de synthese donnent le volume visible de factures, le restant du et la recette sur la periode. |
| 3 | caisse-overview.factures | Gerer les factures impayees | Ce bloc permet de filtrer les factures, de les regler, de les modifier ou de les previsualiser. |
| 4 | caisse-overview.payment-dialog | Enregistrer un paiement | La modale de reglement gere le montant patient, le mode de paiement, les assurances et le reste a payer. |
| 5 | caisse-overview.payments | Suivre les encaissements | La seconde zone resume les paiements deja enregistres et permet d'imprimer ou d'envoyer les recus. |

### Caisse - Factures

- Cle: caisse:factures
- Roles: ROLE_ADMIN, ROLE_RECEPTION
- Capture recommandee: oui, avec factures impayees et partiellement payees. Nom propose: caisse-factures.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | caisse-factures.filters | Filtrer les factures | Recherchez, changez la periode et limitez l'affichage aux factures impayees si besoin. |
| 2 | caisse-factures.cards | Lire les cartes facture | Chaque carte montre le patient, le montant, le reste et le statut de paiement. |
| 3 | caisse-factures.actions | Agir sur une facture | Depuis une carte, vous pouvez regler, valider une facture vide, modifier, previsualiser ou envoyer la facture par SMS. |
| 4 | caisse-factures.preview | Verifier avant impression | L'aperçu detaille la facture et permet une verification avant impression ou envoi. |
| 5 | caisse-factures.modify | Corriger les lignes facture | La modale de modification sert a ajuster les soins, quantites et montants avant validation. |

### Caisse - Paiements

- Cle: caisse:paiements
- Roles: ROLE_ADMIN, ROLE_RECEPTION
- Capture recommandee: oui, avec plusieurs modes de paiement. Nom propose: caisse-paiements.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | caisse-paiements.filters | Filtrer la periode | Choisissez la plage de dates et la recherche libre pour limiter les paiements affiches. |
| 2 | caisse-paiements.totals | Lire les totaux | Cette synthese donne le nombre de paiements visibles et le montant total encaissé sur la periode. |
| 3 | caisse-paiements.accordion | Explorer par mode de paiement | Les paiements sont regroupes par mode pour faciliter le controle de caisse et les rapprochements. |
| 4 | caisse-paiements.row-actions | Imprimer et envoyer | Chaque ligne permet d'imprimer un paiement ou un ticket et d'envoyer le recu par SMS. |

### Rapports - Admin

- Cle: rapports:admin
- Roles: ROLE_ADMIN
- Capture recommandee: oui. Nom propose: rapports-admin.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | rapports-admin.range | Choisir la periode | Selectionnez une plage de dates pour recalculer l'ensemble des statistiques du cabinet. |
| 2 | rapports-admin.global | Lire la synthese globale | Cette section resume l'activite generale du cabinet sur la periode. |
| 3 | rapports-admin.non-periodic | Surveiller les fondamentaux | Les details non periodiques couvrent la repartition du personnel, les consommables critiques et les patients globaux. |
| 4 | rapports-admin.periodic | Lire l'activite sur la periode | Cette zone detaille patients, consultations, rendez-vous, usage des salles et equilibres de paiement. |
| 5 | rapports-admin.acts | Analyser les actes | Cette section met en avant les actes realises et les volumes utiles a l'analyse metier. |
| 6 | rapports-admin.doctors | Comparer les medecins | Le tableau final consolide les performances par medecin sur la meme periode. |

### Rapports - Medecin

- Cle: rapports:medecin
- Roles: ROLE_MEDECIN
- Capture recommandee: oui. Nom propose: rapports-medecin.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | rapports-medecin.range | Choisir la periode | Le rapport se recalculera sur la plage choisie pour analyser votre activite recente. |
| 2 | rapports-medecin.global | Vue d'ensemble personnelle | Cette synthese resume votre volume d'activite et vos principaux indicateurs cliniques. |
| 3 | rapports-medecin.quick | Lire les indicateurs rapides | Les quick stats isolent les informations les plus utiles pour la pratique quotidienne. |
| 4 | rapports-medecin.periodic | Detail sur la periode | Cette partie detaille consultations, paiements et autres resultats lies a la periode selectionnee. |
| 5 | rapports-medecin.acts | Revoir les actes et paiements | Utilisez cette section pour relire les actes realises et les flux associes. |
| 6 | rapports-medecin.profile | Profil professionnel | Cette derniere zone relie vos statistiques au profil du praticien connecte. |

### Rapports - Reception

- Cle: rapports:reception
- Roles: ROLE_RECEPTION
- Capture recommandee: oui. Nom propose: rapports-reception.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | rapports-reception.date | Choisir la journee | Le rapport reception est journalier. Changez de date pour revoir une autre journee d'accueil. |
| 2 | rapports-reception.daily | Lire les stats du jour | Cette carte resume l'activite reception du jour selectionne. |
| 3 | rapports-reception.doctors | Voir les rapports par medecin | Le tableau par medecin permet de comparer rapidement l'activite du jour entre praticiens. |
| 4 | rapports-reception.print | Imprimer le recapitulatif | Utilisez l'impression pour sortir un resume journalier reception. |

### Administration - Consommables

- Cle: administration-consommables
- Roles: ROLE_ADMIN
- Capture recommandee: oui, avec au moins un article en stock bas et un en rupture. Nom propose: admin-consommables.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | admin-consumables.header | Gestion du stock | Cette page sert a suivre les consommables, leur disponibilite et leurs mouvements. |
| 2 | admin-consumables.mode | Changer de mode d'affichage | Basculez entre la liste des consommables et l'historique des variations de stock. |
| 3 | admin-consumables.stats | Prioriser les alertes | Les cartes mettent en avant le total, le stock suffisant, le stock faible et les ruptures. |
| 4 | admin-consumables.list | Gerer les articles | La liste permet de consulter, modifier, supprimer et ouvrir les actions d'ajout ou retrait de stock. |
| 5 | admin-consumables.variations | Auditer les mouvements | En mode Variations, suivez les entrees et sorties de stock sur une periode donnee. |

### Administration - Salles

- Cle: administration-salles
- Roles: ROLE_ADMIN
- Capture recommandee: oui. Nom propose: admin-salles.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | admin-salles.header | Gestion des salles | Cette page gere les espaces de consultation et de traitement du cabinet. |
| 2 | admin-salles.table | Consulter les salles | Le tableau centralise le nom, le type, le statut et les actions de chaque salle. |
| 3 | admin-salles.actions | Modifier ou supprimer | Depuis une ligne, vous pouvez editer les informations de la salle ou la supprimer. |
| 4 | admin-salles.stats | Lire l'occupation | Les cartes de synthese montrent combien de salles sont disponibles, occupees et reparties par type. |
| 5 | admin-salles.dialogs | Ajouter une salle | Les dialogues servent a creer ou modifier une salle sans quitter la liste. |

### Administration - Finances / Tableaux

- Cle: administration-finances:tables
- Roles: ROLE_ADMIN
- Capture recommandee: oui, avec transactions en attente et modes de paiement configures. Nom propose: admin-finances-tables.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | admin-finances.header | Tableau de bord financier | Cette page consolide les transactions, validations manuelles et modes de paiement du cabinet. |
| 2 | admin-finances.kpi | Lire les KPI | Les cartes du haut isolent capital total, transactions validees, flux en attente et nombre de modes actifs. |
| 3 | admin-finances.transactions | Filtrer l'historique | Utilisez la periode, le statut et la recherche pour cibler les transactions a controler. |
| 4 | admin-finances.validation | Valider ou rejeter | Les actions de ligne servent a confirmer ou rejeter une transaction encore en attente. |
| 5 | admin-finances.methods | Gerer les modes de paiement | Les modes sont regroupes par famille, avec un traitement distinct pour les assurances et leur taux de prise en charge. |
| 6 | admin-finances.dialogs | Creer ou modifier | Les formulaires de transaction et de mode servent a faire vivre le referentiel financier sans quitter la page. |

### Administration - Finances / Graphiques

- Cle: administration-finances:charts
- Roles: ROLE_ADMIN
- Capture recommandee: oui. Nom propose: admin-finances-charts.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | admin-finances.tabs | Passer aux graphiques | Cet onglet transforme les tableaux financiers en visualisations de pilotage. |
| 2 | admin-finances.monthly-flow | Lire le flux mensuel | Ce graphe compare les entrees, les depenses et le resultat net sur l'annee choisie. |
| 3 | admin-finances.distribution | Repartition des encaissements | Ce donut montre la part de chaque mode de paiement dans les encaissements. |
| 4 | admin-finances.accounts | Suivre capital et comptes | Les graphiques par compte mettent en avant le capital disponible et le solde par mode. |
| 5 | admin-finances.status | Voir les statuts de validation | Ce graphe offre une vision immediate des flux valides, rejetes et en attente. |
| 6 | admin-finances.evolution | Lire l'evolution du capital | La courbe finale montre la croissance ou l'erosion du capital dans le temps. |

### Administration - Utilisateurs

- Cle: administration-utilisateurs
- Roles: ROLE_ADMIN
- Capture recommandee: oui. Nom propose: admin-utilisateurs.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | admin-users.header | Gestion des comptes | Cette page sert a creer, modifier et securiser les comptes utilisateurs du systeme. |
| 2 | admin-users.search | Rechercher un compte | Recherchez par identifiant, employe lie ou type d'utilisateur. |
| 3 | admin-users.grouping | Regrouper par type | Activez ce mode pour relire les comptes par categorie metier. |
| 4 | admin-users.table | Lire la liste | Le tableau affiche le nom d'utilisateur, le type et les actions disponibles. |
| 5 | admin-users.actions | Securiser un acces | Vous pouvez editer un compte, reinitialiser son mot de passe ou le supprimer. |
| 6 | admin-users.form | Creer ou modifier | Le formulaire associe permet de renseigner les donnees du compte et le profil employe lie. |

### Administration - Gestion RH

- Cle: administration-gestionrh
- Roles: ROLE_ADMIN
- Capture recommandee: oui. Nom propose: admin-rh.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | admin-rh.header | Gestion RH | Cette page centralise les collaborateurs du cabinet et leurs informations principales. |
| 2 | admin-rh.filters | Filtrer les employes | Utilisez la recherche et le filtre par type pour reduire la liste aux profils utiles. |
| 3 | admin-rh.table | Lire la liste RH | Le tableau groupe les employes par type et affiche les actions de gestion sur chaque ligne. |
| 4 | admin-rh.actions | Ouvrir les details | Depuis une ligne, vous pouvez modifier, supprimer ou ouvrir la fiche detaillee de l'employe. |
| 5 | admin-rh.form | Ajouter un employe | Le formulaire sert a creer ou mettre a jour un collaborateur sans quitter la vue RH. |

### Administration - Detail employe

- Cle: administration-employee-details
- Roles: ROLE_ADMIN
- Capture recommandee: oui, avec un employe ayant conges et documents. Nom propose: admin-employee-details.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | admin-employee.header | Fiche RH detaillee | Cette page rassemble toutes les informations administratives et contractuelles d'un employe. |
| 2 | admin-employee.personal | Informations personnelles | Commencez ici pour relire ou corriger l'identite, les contacts et les donnees de base. |
| 3 | admin-employee.hr | Informations RH | Cette zone gere salaire, type de contrat, duree et jours travailles. |
| 4 | admin-employee.files | Documents administratifs | Ajoutez ou telechargez les pieces necessaires au suivi administratif de l'employe. |
| 5 | admin-employee.leaves | Conges par annee | Le panneau lateral permet de relire les conges enregistres et leur duree. |
| 6 | admin-employee.save | Enregistrer les changements | Utilisez le bouton Enregistrer pour valider les modifications faites sur la fiche. |

### Administration - Notifications

- Cle: administration-notifications
- Roles: ROLE_ADMIN
- Capture recommandee: oui. Nom propose: admin-notifications.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | admin-notifications.header | Envoi de notifications | Cette page permet de preparer et envoyer une notification ciblee aux utilisateurs internes. |
| 2 | admin-notifications.users | Selection des destinataires | Recherchez un utilisateur ou cochez plusieurs profils dans la liste de gauche. |
| 3 | admin-notifications.types | Selection rapide par type | Les boutons de type ajoutent en une action tous les utilisateurs d'une meme categorie. |
| 4 | admin-notifications.compose | Rediger le message | A droite, choisissez la priorite, saisissez le message, ajoutez un lien et verifiez l'aperçu. |
| 5 | admin-notifications.selected | Verifier les destinataires | Le panneau des destinataires selectionnes sert a controler la cible avant l'envoi. |
| 6 | admin-notifications.send | Confirmer l'envoi | Quand le contenu et les destinataires sont corrects, lancez l'envoi depuis le header de page ou le panneau lateral. |

### Profil

- Cle: profile
- Roles: tous les roles connectes
- Capture recommandee: oui. Nom propose: profile-overview.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | profile.stats | Resume personnel | Les cartes du haut resument votre activite, vos alertes et vos notifications non lues. |
| 2 | profile.info | Gerer vos informations | Cette zone permet de mettre a jour votre profil et de changer votre mot de passe. |
| 3 | profile.links | Revenir aux modules utiles | Les raccourcis mènent directement aux pages les plus pertinentes pour votre role. |
| 4 | profile.activity | Relire votre activite | Le bloc Activite aide a suivre ce qui s'est passe recemment sur votre compte ou votre espace de travail. |
| 5 | profile.notifications | Traiter les notifications | Filtrez, marquez comme lues ou videz vos notifications depuis ce panneau. |

### Parametres - Apparence et SMS

- Cle: settings-apparence
- Roles: ROLE_ADMIN, ROLE_SECRETAIRE, ROLE_TOPO
- Capture recommandee: oui, deux captures si possible. Noms proposes: settings-appearance et settings-sms.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | settings.sidebar | Naviguer entre les sections | Le menu lateral permet d'aller directement a l'apparence ou a la configuration SMS. |
| 2 | settings.appearance | Personnaliser l'interface | Ce panneau pilote theme, couleurs, surfaces, preset et typographie de l'application. |
| 3 | settings.appearance-save | Sauvegarder l'apparence | Les modifications visuelles doivent etre confirmees ici pour etre conservees. |
| 4 | settings.sms-config | Configurer l'API SMS | Cette section gere l'activation, les credentials, les tests de connexion et l'envoi de SMS de test. |
| 5 | settings.sms-monitoring | Suivre l'activite SMS | Les cartes, logs et indicateurs servent a suivre la consommation et l'etat de la file SMS. |
| 6 | settings.sms-templates | Maintenir les templates | Editez les messages types, testez leur rendu avec variables et gerez un envoi manuel si besoin. |

Note produit:

- Si les profils non admin ne doivent pas voir la partie SMS, il faut masquer la section cote UI ou reduire le tour a la seule apparence.

### Manuel utilisateur

- Cle: manual
- Roles: ROLE_ADMIN
- Capture recommandee: non
- Etat actuel: la vue est vide.

Decision recommandee:

- Soit ne pas afficher le bouton Aide sur cette page tant qu'elle reste vide.
- Soit afficher un tour en un seul step: Cette page servira de manuel integre quand son contenu sera disponible.

### Login

- Cle: login
- Roles: public
- Capture recommandee: oui. Nom propose: login-form.
- Note technique: pas de declenchement via le header. Prevoir un lien local de type Besoin d'aide si le tour doit etre disponible ici.

| # | Ancre | Titre | Texte overlay |
|---|---|---|---|
| 1 | login.username | Saisir votre identifiant | Renseignez ici le nom d'utilisateur fourni pour acceder a l'application. |
| 2 | login.password | Saisir votre mot de passe | Entrez votre mot de passe puis utilisez l'icone d'affichage si vous souhaitez le verifier. |
| 3 | login.remember | Rester reconnu | Cochez cette option si vous voulez conserver plus facilement votre session sur ce poste. |
| 4 | login.submit | Ouvrir votre espace | Cliquez sur Se connecter pour lancer l'authentification et acceder au tableau de bord. |
| 5 | login.error | Comprendre l'erreur | Si la connexion echoue, le message affiche ici explique le probleme rencontre. |

## 6. Ordre de priorite recommande pour l'implementation

Priorite 1:

- dashboard
- agenda-rendezvous
- patients-liste
- patients-dossier
- consultations-table
- consultations-form
- caisse:overview
- rapports selon role

Priorite 2:

- consultations-cards
- administration-finances:tables
- administration-utilisateurs
- administration-gestionrh
- administration-notifications
- profile
- settings-apparence

Priorite 3:

- agenda-evenements
- administration-consommables
- administration-salles
- administration-employee-details
- caisse:factures
- caisse:paiements
- consultations-form-legacy
- login
- manual

## 7. Recommandations de captures

- Toujours utiliser un jeu de donnees de demo stable pour que les overlays restent credibles d'une demo a l'autre.
- Eviter les captures sur les vues trop contextuelles si leur structure change selon le patient ou la consultation.
- Pour les pages avec modales critiques, prevoir une capture page et une capture modale si la modale fait partie du tour.
- Pour la caisse, prendre au minimum une capture avec assurance activee dans la modale de paiement.
- Pour l'agenda, prendre une capture en vue semaine plutot qu'en vue jour, plus parlante pour une premiere decouverte.

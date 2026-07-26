import { flushUi, normalizeTourSteps } from './shared/tourHelpers';

const GROUP = 'patients-liste';

async function switchFormTabStep(ctx, tab) {
    await ctx.switchPatientFormTab?.(tab);
    await flushUi();
}

function buildPatientFormTabSteps(ctx, { afterLeaveClose = true } = {}) {
    const steps = [
        {
            group: GROUP,
            target: '[data-tour="patients-form.tabs"]',
            title: 'Onglets du formulaire',
            content: 'Le formulaire patient est organise en trois parties : informations personnelles, parametres SMS et informations assurances (si des assureurs sont actifs dans le cabinet).'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-form.panel-personal"]',
            title: 'Informations personnelles',
            content: 'Renseignez le nom et le telephone (obligatoires), le prenom, l adresse, la profession, la date de naissance ou l age, le sexe, le lieu de naissance et la source de referencement.',
            beforeEnter: async () => switchFormTabStep(ctx, 'personal')
        },
        {
            group: GROUP,
            target: '[data-tour="patients-form.panel-personal"]',
            title: 'Contact d urgence',
            content: 'En bas de l onglet identite, enregistrez une personne a contacter en cas d urgence : nom, telephone et lien de parente.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-form.tab-sms"]',
            title: 'Onglet Parametres SMS',
            content: 'Passez a cet onglet pour controler les messages automatiques envoyes a ce patient.',
            beforeEnter: async () => switchFormTabStep(ctx, 'sms')
        },
        {
            group: GROUP,
            target: '[data-tour="patients-form.panel-sms"]',
            title: 'Preferences SMS',
            content: 'Autorisez ou bloquez les envois : SMS apres creation du dossier, recus de paiement, tickets, factures et rappels de rendez-vous. Les options desabonne et numero blackliste suspendent tout envoi automatique.'
        }
    ];

    if (ctx.hasInsuranceTab) {
        steps.push(
            {
                group: GROUP,
                target: '[data-tour="patients-form.tab-insurance"]',
                title: 'Onglet Informations assurances',
                content: 'Si le patient est couvert par une assurance, configurez son profil ici pour pre-remplir les consultations et factures.',
                beforeEnter: async () => switchFormTabStep(ctx, 'insurance')
            },
            {
                group: GROUP,
                target: '[data-tour="patients-form.panel-insurance"]',
                title: 'Profil assurance',
                content: 'Cochez Patient assure, choisissez l organisme et le taux de couverture. Les champs affiches s adaptent au formulaire specifique de l assureur selectionne (SBN, Bleues, Sunu, Lafia, Saham, MSH, etc.).'
            }
        );
    }

    steps.push({
        group: GROUP,
        target: '[data-tour="patients-form.actions"]',
        title: 'Confirmer l enregistrement',
        content: 'Cliquez sur Creer ou Mettre a jour. Une confirmation est demandee avant la sauvegarde definitive du dossier.',
        ...(afterLeaveClose ? {
            afterLeave: async () => {
                ctx.closeAllDialogs();
                await flushUi();
            }
        } : {})
    });

    return steps;
}

export const patientsListeRegistry = {
    routeName: GROUP,
    tasks: [
        {
            id: 'overview',
            label: 'Presentation de la page',
            icon: 'pi pi-compass',
            mockScenario: 'static'
        },
        {
            id: 'search-patient',
            label: 'Rechercher un patient',
            icon: 'pi pi-search',
            mockScenario: 'static',
            variants: [
                { id: 'default', label: 'Liste avec patients', mockScenario: 'static' },
                { id: 'empty', label: 'Liste vide', mockScenario: 'empty' }
            ]
        },
        {
            id: 'add-patient',
            label: 'Ajouter un patient',
            icon: 'pi pi-user-plus',
            mockScenario: 'static'
        },
        {
            id: 'edit-patient',
            label: 'Modifier un patient',
            icon: 'pi pi-pencil',
            mockScenario: 'static'
        },
        {
            id: 'schedule-rdv',
            label: 'Planifier un RDV',
            icon: 'pi pi-calendar-plus',
            mockScenario: 'static'
        },
        {
            id: 'create-consultation',
            label: 'Creer une consultation',
            icon: 'pi pi-plus-circle',
            roles: ['admin', 'reception'],
            mockScenario: 'clean-patient',
            variants: [
                { id: 'normal', label: 'Cas normal — patient disponible', mockScenario: 'clean-patient' },
                { id: 'blocked-no-fiche', label: 'Blocage — consultation sans fiche', mockScenario: 'active-no-fiche' },
                { id: 'blocked-with-fiche', label: 'Blocage — consultation avec fiche', mockScenario: 'active-with-fiche' }
            ]
        },
        {
            id: 'open-dossier',
            label: 'Ouvrir un dossier',
            icon: 'pi pi-folder-open',
            mockScenario: 'static'
        },
        {
            id: 'manage-trash',
            label: 'Gerer la corbeille',
            icon: 'pi pi-trash',
            mockScenario: 'static'
        },
        {
            id: 'export-list',
            label: 'Exporter la liste',
            icon: 'pi pi-download',
            mockScenario: 'static'
        }
    ],
    buildSteps(taskId, variantId, ctx) {
        switch (taskId) {
            case 'search-patient':
                return buildSearchPatientSteps(ctx, variantId);
            case 'add-patient':
                return buildAddPatientSteps(ctx);
            case 'edit-patient':
                return buildEditPatientSteps(ctx);
            case 'schedule-rdv':
                return buildScheduleRdvSteps(ctx);
            case 'create-consultation':
                return buildCreateConsultationSteps(ctx, variantId);
            case 'open-dossier':
                return buildOpenDossierSteps(ctx);
            case 'manage-trash':
                return buildManageTrashSteps(ctx);
            case 'export-list':
                return buildExportListSteps(ctx);
            case 'overview':
            default:
                return buildOverviewSteps(ctx);
        }
    }
};

function buildOverviewSteps(ctx) {
    const steps = [
        {
            group: GROUP,
            target: '[data-tour="patients-list.header"]',
            title: 'Gestion des patients',
            content: 'Point d entree du cabinet : recherche, creation de dossiers, planification de rendez-vous, consultations et acces aux fiches medicales.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-list.toolbar"]',
            title: 'Barre d actions principales',
            content: ctx.isMedecin
                ? 'Corbeille, nouveau rendez-vous et ajout de patient. La creation de consultation est reservee aux profils reception et administration.'
                : 'Corbeille, rendez-vous, nouvelle consultation et ajout de patient — toutes les actions d accueil depuis un seul endroit.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-list.search"]',
            title: 'Recherche instantanee',
            content: 'Filtrez par nom, prenom, telephone ou adresse. La liste se met a jour automatiquement sans recharger la page.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-list.table"]',
            title: 'Tableau des patients',
            content: 'Chaque ligne affiche l identite, le sexe, l age, le telephone, l adresse, la derniere consultation et un badge assurance si le patient est couvert. Colonnes triables et pagination.'
        }
    ];

    if (ctx.hasPatients) {
        steps.push({
            group: GROUP,
            target: '[data-tour="patients-list.row-actions"]',
            title: 'Actions par ligne',
            content: 'Ouvrir le dossier, lancer une consultation, planifier un RDV, modifier le patient ou le deplacer vers la corbeille via l icone poubelle.'
        });
    }

    steps.push(
        {
            group: GROUP,
            target: '[data-tour="patients-list.stats"]',
            title: 'Indicateurs de synthese',
            content: 'Total patients, consultations du jour, rendez-vous a venir et nouveaux dossiers du mois pour suivre l activite du cabinet.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-list.referrals"]',
            title: 'Referencement patients',
            content: 'Graphique et repartition indiquant comment les patients ont connu le cabinet (reseaux sociaux, bouche a oreille, medecin, publicite, etc.). Renseigne lors de la creation du dossier.'
        }
    );

    return normalizeTourSteps(steps);
}

function buildSearchPatientSteps(ctx, variantId) {
    if (variantId === 'empty' || !ctx.hasPatients) {
        return normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="patients-list.search"]',
                title: 'Lancer une recherche',
                content: 'Tapez quelques caracteres pour filtrer des qu au moins un patient est enregistre dans le cabinet.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-list.table"]',
                title: 'Aucun resultat',
                content: 'Si aucun patient ne correspond, un message invite a elargir la recherche ou a reinitialiser le filtre.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-list.add-patient-button"]',
                title: 'Premier patient',
                content: 'Sans dossier enregistre, commencez par Ajouter un patient pour peupler la liste.'
            }
        ]);
    }

    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="patients-list.search"]',
            title: 'Filtrer la liste',
            content: 'Recherche multi-criteres sur nom, prenom, telephone et adresse. Les resultats se mettent a jour en direct.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-list.table"]',
            title: 'Lire les resultats',
            content: 'Avatar, sexe, age, telephone, adresse, badge assurance et derniere consultation pour chaque patient trouve.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-list.table"]',
            title: 'Pagination',
            content: 'Le pied de tableau indique le nombre de patients retrouves et permet de changer le nombre de lignes par page.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-list.row-actions"]',
            title: 'Agir sur un resultat',
            content: 'Identifiez le patient puis utilisez les icones de la ligne pour ouvrir le dossier, demarrer une consultation ou planifier un RDV.'
        }
    ]);
}

function buildAddPatientSteps(ctx) {
    const formSteps = buildPatientFormTabSteps(ctx);
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="patients-list.add-patient-button"]',
            title: 'Creer un dossier',
            content: 'Ouvre le formulaire de creation. Un numero de dossier est attribue automatiquement a l enregistrement.'
        },
        ...formSteps.map((step, index) => ({
            ...step,
            ...(index === 0 ? {
                beforeEnter: async () => {
                    await ctx.openCreatePatientDialog();
                    await flushUi();
                    await switchFormTabStep(ctx, 'personal');
                }
            } : {})
        }))
    ]);
}

function buildEditPatientSteps(ctx) {
    const formSteps = buildPatientFormTabSteps(ctx);
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="patients-list.row-actions"]',
            title: 'Acceder a la modification',
            content: 'L icone crayon ouvre le formulaire en mode edition avec les donnees deja enregistrees, y compris les onglets SMS et assurance.'
        },
        ...formSteps.map((step, index) => ({
            ...step,
            ...(index === 0 ? {
                beforeEnter: async () => {
                    await ctx.openEditPatientDialog?.();
                    await flushUi();
                    await switchFormTabStep(ctx, 'personal');
                }
            } : {})
        }))
    ]);
}

function buildScheduleRdvSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="patients-list.rdv-button"]',
            title: 'Planifier depuis la liste',
            content: 'Le bouton Nouveau rendez-vous ouvre le formulaire sans quitter la page. Le patient sera selectionne dans le dialogue.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-list.row-actions"]',
            title: 'Planifier pour un patient precis',
            content: 'L icone calendrier sur une ligne preselectionne le patient et accelere la prise de rendez-vous.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-form-rdv.details"]',
            title: 'Detail du rendez-vous',
            content: 'Selectionnez le patient (si non preselectionne), le medecin, la duree, le motif, la date/heure et des notes internes.',
            beforeEnter: async () => {
                await ctx.openRendezVousDialog();
                await flushUi();
            }
        },
        {
            group: GROUP,
            target: '[data-tour="patients-form-rdv.sms-reminder"]',
            title: 'Rappel SMS automatique',
            content: 'Programmez un rappel SMS : desactive, 1 semaine, 5 jours, 3 jours, 2 jours ou 1 jour avant le RDV. Definissez aussi une repetition (quotidienne, hebdomadaire) si la premiere echeance est future.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-form-rdv.actions"]',
            title: 'Valider le rendez-vous',
            content: 'Confirmez la creation. Le RDV apparait dans l agenda et les SMS prevus sont mis en file d envoi si le patient l autorise.',
            afterLeave: async () => {
                ctx.closeAllDialogs();
                await flushUi();
            }
        }
    ]);
}

function buildCreateConsultationSteps(ctx, variantId) {
    if (variantId === 'blocked-no-fiche' || variantId === 'blocked-with-fiche') {
        return normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="patients-list.consultation-button"]',
                title: 'Protection anti-doublon',
                content: 'Une consultation deja ouverte bloque la creation d un second dossier pour eviter les incoherences de facturation et de suivi clinique.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-list.row-actions"]',
                title: 'Depuis une ligne',
                content: 'Le controle s applique aussi via l icone stethoscope : le patient doit etre libre ou la consultation en cours doit etre traitee.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-list.dialog.active-warning"]',
                title: variantId === 'blocked-with-fiche' ? 'Fiche deja saisie' : 'Annulation possible',
                content: variantId === 'blocked-with-fiche'
                    ? 'Consultation liee a une fiche medicale : suppression impossible ici. Poursuivez ou cloturez la fiche dans le module Consultations.'
                    : 'Sans fiche saisie, la consultation ouverte par erreur peut etre annulee directement depuis ce dialogue.',
                beforeEnter: async () => {
                    await ctx.openDuplicateConsultationDialog(variantId);
                    await flushUi();
                },
                afterLeave: async () => {
                    ctx.closeAllDialogs();
                    await flushUi();
                }
            }
        ]);
    }

    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="patients-list.consultation-button"]',
            title: 'Demarrer une consultation',
            content: 'Flux de prise en charge depuis la reception (profils admin et reception uniquement sur cette page).'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-form-consultation.patient"]',
            title: 'Selectionner le patient',
            content: 'Choisissez le patient dans la liste ou confirmez le patient preselectionne depuis la barre d outils ou une ligne.',
            beforeEnter: async () => {
                await ctx.openConsultationDialog();
                await flushUi();
            }
        },
        {
            group: GROUP,
            target: '[data-tour="patients-form-consultation.schedule"]',
            title: 'Medecin et horaire',
            content: 'Indiquez le medecin traitant (obligatoire selon la politique du cabinet) et la date/heure de la consultation.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-form-consultation.insurance"], [data-tour="patients-form-consultation.payment"]',
            title: 'Assurance et reglement',
            content: 'Si le patient est assure, un bandeau vert affiche l organisme et le taux de couverture. Ensuite, activez Consultation payante et choisissez le mode de paiement pour la part patient restante.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-form-consultation.actions"]',
            title: 'Creer la consultation',
            content: 'Validez pour ouvrir la consultation en attente de fiche. Un ticket peut etre imprime immediatement si un paiement est enregistre.',
            afterLeave: async () => {
                ctx.closeAllDialogs();
                await flushUi();
            }
        }
    ]);
}

function buildOpenDossierSteps(ctx) {
    const steps = [
        {
            group: GROUP,
            target: '[data-tour="patients-list.row-actions"]',
            title: 'Ouvrir le dossier medical',
            content: 'L icone oeil ouvre la fiche complete : identite, antecedents, allergies, ordonnances, fiches medicales, RDV, paiements et fichiers administratifs.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-list.table"]',
            title: 'Derniere consultation',
            content: 'La colonne indique date, motif et statut (en cours, normal, urgent). Le badge vert Assure signale un profil assurance actif.'
        }
    ];

    if (ctx.hasPatients) {
        steps.push({
            group: GROUP,
            target: '[data-tour="patients-list.row-actions"]',
            title: 'Contenu du dossier',
            content: 'Dans le dossier : suivi clinique, historique financier (RDV, paiements, factures), espace patient en ligne et documents archives.'
        });
    }

    return normalizeTourSteps(steps);
}

function buildManageTrashSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="patients-list.trash-button"]',
            title: 'Ouvrir la corbeille',
            content: 'Les patients supprimes ne sont pas effaces definitivement : ils sont deplaces ici et peuvent etre restaures.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-list.dialog.trash"]',
            title: 'Rechercher dans la corbeille',
            content: 'Filtrez par nom, prenom ou telephone pour retrouver un dossier supprime par erreur.',
            beforeEnter: async () => {
                await ctx.openTrashDialog?.();
                await flushUi();
            }
        },
        {
            group: GROUP,
            target: '[data-tour="patients-list.dialog.trash"]',
            title: 'Restaurer un patient',
            content: 'Cliquez sur Restaurer pour remettre le dossier dans la liste active avec toutes ses donnees conservees.',
            afterLeave: async () => {
                ctx.closeAllDialogs();
                await flushUi();
            }
        }
    ]);
}

function buildExportListSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="patients-list.search"]',
            title: 'Filtrer avant export',
            content: 'Seuls les patients actuellement visibles (apres recherche) seront inclus dans l export.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-list.table"]',
            title: 'Contenu exporte',
            content: 'Identite, coordonnees et derniere consultation pour chaque patient de la liste filtree.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-list.export"]',
            title: 'Lancer l export',
            content: 'Genere un document imprimable de la liste courante pour un controle administratif ou un etat des dossiers.'
        }
    ]);
}

export function buildPatientsListTourSteps(taskId, variantId, ctx) {
    return patientsListeRegistry.buildSteps(taskId, variantId, ctx);
}

export function createPatientsListTour(ctx) {
    return buildPatientsListTourSteps('overview', null, ctx);
}

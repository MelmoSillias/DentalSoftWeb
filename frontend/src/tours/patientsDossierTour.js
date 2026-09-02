import { flushUi, openDialogStep, normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'patients-dossier';

async function switchFormTabStep(ctx, tab) {
    await ctx.switchPatientFormTab?.(tab);
    await flushUi();
}

function buildDossierPatientFormTabSteps(ctx) {
    const steps = [
        {
            group: GROUP,
            target: '[data-tour="patients-form.tabs"]',
            title: 'Onglets du formulaire',
            content: 'Comme a la creation, le formulaire comprend les onglets identite, SMS et assurance pour une mise a jour complete du dossier administratif.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-form.panel-personal"]',
            title: 'Informations personnelles',
            content: 'Modifiez l identite, les coordonnees, la profession, la date de naissance, le referencement et le contact d urgence.',
            beforeEnter: async () => switchFormTabStep(ctx, 'personal')
        },
        {
            group: GROUP,
            target: '[data-tour="patients-form.tab-sms"]',
            title: 'Onglet Parametres SMS',
            content: 'Ajustez les autorisations d envoi SMS pour ce patient.',
            beforeEnter: async () => switchFormTabStep(ctx, 'sms')
        },
        {
            group: GROUP,
            target: '[data-tour="patients-form.panel-sms"]',
            title: 'Preferences SMS',
            content: 'Recus, tickets, factures, rappels RDV, desabonnement et blacklist : chaque option controle un type de message automatique.'
        }
    ];

    if (ctx.hasInsuranceTab) {
        steps.push(
            {
                group: GROUP,
                target: '[data-tour="patients-form.tab-insurance"]',
                title: 'Onglet Informations assurances',
                content: 'Mettez a jour le profil assurance du patient pour les prochaines consultations et factures.',
                beforeEnter: async () => switchFormTabStep(ctx, 'insurance')
            },
            {
                group: GROUP,
                target: '[data-tour="patients-form.panel-insurance"]',
                title: 'Profil assurance',
                content: 'Activez Patient assure, selectionnez l organisme, le taux de couverture et completez les champs specifiques a l assureur.'
            }
        );
    }

    steps.push({
        group: GROUP,
        target: '[data-tour="patients-form.actions"]',
        title: 'Enregistrer les modifications',
        content: 'Confirmez la mise a jour. La carte d identite du dossier se recharge avec les nouvelles informations.'
    });

    return steps;
}

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'switch-patient', label: 'Changer de patient', icon: 'pi pi-users', mockScenario: 'static' },
    { id: 'manage-antecedents', label: 'Gerer antecedents/allergies', icon: 'pi pi-heart', mockScenario: 'static' },
    { id: 'portal-account', label: 'Compte espace patient', icon: 'pi pi-globe', mockScenario: 'static' },
    { id: 'view-fiches', label: 'Consulter fiches medicales', icon: 'pi pi-folder-open', mockScenario: 'static', roles: ['admin', 'medecin'] },
    { id: 'consultations-history', label: 'Historique consultations', icon: 'pi pi-history', mockScenario: 'static', roles: ['reception'] },
    {
        id: 'create-consultation',
        label: 'Creer une consultation',
        icon: 'pi pi-plus-circle',
        roles: ['admin'],
        mockScenario: 'clean-patient',
        variants: [
            { id: 'normal', label: 'Cas normal', mockScenario: 'clean-patient' },
            { id: 'blocked-no-fiche', label: 'Blocage sans fiche', mockScenario: 'active-no-fiche' },
            { id: 'blocked-with-fiche', label: 'Blocage avec fiche', mockScenario: 'active-with-fiche' }
        ]
    },
    { id: 'edit-patient', label: 'Modifier le patient', icon: 'pi pi-pencil', mockScenario: 'static' },
    { id: 'schedule-rdv', label: 'Planifier un RDV', icon: 'pi pi-calendar-plus', mockScenario: 'static' },
    { id: 'manage-archive', label: 'Fichiers administratifs', icon: 'pi pi-folder-open', mockScenario: 'static' },
    { id: 'print-dossier', label: 'Imprimer le dossier', icon: 'pi pi-print', mockScenario: 'static', roles: ['admin', 'medecin'] }
];

function buildOverviewSteps(ctx) {
    const steps = [
        {
            group: GROUP,
            target: '[data-tour="patients-dossier.selector"]',
            title: 'Navigation entre patients',
            content: 'Selecteur avec recherche par nom ou telephone pour basculer d un dossier a un autre sans retourner a la liste.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-dossier.layout-toggle"]',
            title: 'Mode d affichage',
            content: 'Bascule entre vue colonnes (identite a gauche, suivi a droite) et vue onglets (sections regroupees).'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-dossier.identity"]',
            title: 'Photo et identite',
            content: 'Photo du patient (modifiable), nom complet et numero de dossier. Point de reference visuel avant toute action.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-dossier.personal-details"]',
            title: 'Donnees civiles',
            content: 'Date et lieu de naissance, age, sexe, groupe sanguin, profession et source de referencement.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-dossier.contact"]',
            title: 'Coordonnees',
            content: 'Telephone, e-mail et adresse du patient. Le telephone peut etre masque pour certains profils medecin selon la politique du cabinet.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-dossier.insurance"]',
            title: 'Assurance du patient',
            content: ctx.hasInsuranceProfile
                ? 'Organisme assureur, taux de couverture et donnees specifiques au contrat (societe, numero assure, beneficiaire, etc.).'
                : 'Si le patient n est pas assure, cette section indique Patient non assure. Le profil se configure dans le formulaire de modification (onglet Assurance).'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-dossier.antecedents"], [data-tour="patients-dossier.allergies"]',
            title: 'Alertes medicales',
            content: 'Antecedents et allergies visibles immediatement. Boutons Ajouter pour consigner de nouvelles informations critiques.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-dossier.ordonnances"]',
            title: 'Ordonnances',
            content: 'Liste des ordonnances du patient avec actions Voir, Modifier et Imprimer pour chaque prescription.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-dossier.emergency-contact"]',
            title: 'Contact d urgence',
            content: 'Personne a joindre en cas d urgence : nom, telephone et lien de parente.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-dossier.portal-account"]',
            title: 'Espace patient en ligne',
            content: 'Creation du compte portail, reinitialisation du mot de passe et activation/desactivation de l acces en ligne.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-dossier.actions"]',
            title: 'Actions du dossier',
            content: 'Imprimer une fiche, modifier le patient, planifier un RDV ou demarrer une consultation selon votre profil.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-dossier.medical"]',
            title: 'Suivi clinique',
            content: ctx.isReception ? 'Historique des consultations avec filtres et export (profil reception).' : 'Fiches medicales avec historique, contenu detaille, navigation interne et impression (profils medecin et admin).'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-dossier.finance-tabs"]',
            title: 'Historique administratif',
            content: 'Onglets RDV, Paiements, Factures (avec filtre impayees), Actes medicaux et eventuellement Consultations pour le suivi financier et administratif du patient.'
        },
        {
            group: GROUP,
            target: '[data-tour="patients-dossier.archive-files"]',
            title: 'Fichiers administratifs',
            content: 'Documents administratifs (PDF, images) independants des fiches medicales : pieces justificatives, consentements, courriers.'
        }
    ];

    if (!ctx.isReception) {
        steps.splice(11, 0, {
            group: GROUP,
            target: '[data-tour="patients-dossier.main-tabs"]',
            title: 'Vue en onglets',
            content: 'En mode onglets, les sections Identite, Medical, Finances et Archives sont regroupees pour une navigation simplifiee sur petit ecran.'
        });
    }

    return normalizeTourSteps(steps);
}

export const patientsDossierRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'switch-patient': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.selector"]',
                title: 'Selectionner un autre patient',
                content: 'Tapez quelques lettres pour filtrer. Chaque option affiche nom et telephone pour eviter les confusions entre homonymes.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.selector"]',
                title: 'Rechargement du dossier',
                content: 'A la selection, tout le dossier se recharge : identite, antecedents, assurance, fiches, RDV et paiements.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.identity"]',
                title: 'Verifier le contexte',
                content: 'Confirmez visuellement le nom et le numero de dossier avant toute action medicale ou administrative.'
            }
        ]),
    'manage-antecedents': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.antecedents"]',
                title: 'Antecedents medicaux',
                content: 'Pathologies, traitements chroniques et interventions passees. Chaque entree : type, description et date. Suppression possible via l icone poubelle.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.allergies"]',
                title: 'Allergies connues',
                content: 'Allergies medicamenteuses ou alimentaires avec description. Information critique avant toute prescription.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.emergency-contact"]',
                title: 'Contact d urgence',
                content: 'Nom, lien de parente et telephone d une personne a contacter en cas d urgence.'
            }
        ]),
    'portal-account': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.portal-account"]',
                title: 'Creer le compte portail',
                content: 'Genere l acces en ligne du patient pour consulter rendez-vous et documents depuis le portail web.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.portal-account"]',
                title: 'Reinitialiser le mot de passe',
                content: 'En cas d oubli, declenchez un reset. Le patient recoit les instructions de connexion.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.portal-account"]',
                title: 'Activer ou desactiver l acces',
                content: 'Suspendez temporairement l acces sans supprimer le compte (dossier archive, demande du patient, etc.).'
            }
        ]),
    'view-fiches': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.fiches-toolbar"]',
                title: 'Historique des fiches',
                content: 'Liste des fiches par date. Selectionnez une fiche pour afficher entretien, examens, bilans, plan de traitement et seances.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.fiches-preview"]',
                title: 'Lecture de la fiche',
                content: 'Contenu detaille de la fiche selectionnee : diagnostic, actes, notes de seance et documents associes.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.fiches-jump"]',
                title: 'Navigation interne',
                content: 'Raccourcis pour sauter a une section (entretien, examens, bilans, devis) dans les dossiers longs.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.fiches-expand"]',
                title: 'Vue agrandie et impression',
                content: 'Ouvrez la fiche en plein ecran ou lancez l impression unitaire depuis la barre d outils.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.fiches-new-consultation"]',
                title: 'Nouvelle consultation',
                content: 'Depuis la barre des fiches, demarrez une nouvelle prise en charge pour ce patient si aucune consultation n est deja active.'
            }
        ]),
    'consultations-history': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.consultations-toolbar"]',
                title: 'Historique du patient',
                content: 'Recense toutes les consultations passees : medecin, date et statut de facturation.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.consultations-filter"]',
                title: 'Filtrer les consultations',
                content: 'Restreignez par periode ou statut pour retrouver une consultation precise ou preparer un suivi de facturation.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.consultations-table"]',
                title: 'Tableau et export',
                content: 'Detail de chaque passage et possibilite d exporter la liste pour un controle administratif.'
            }
        ]),
    'create-consultation': (ctx, variantId) => {
        if (variantId === 'blocked-no-fiche' || variantId === 'blocked-with-fiche') {
            return normalizeTourSteps([
                {
                    group: GROUP,
                    target: '[data-tour="patients-dossier.actions"]',
                    title: 'Lancer une consultation',
                    content: 'Le systeme verifie qu aucune consultation n est deja active pour ce patient avant d ouvrir le formulaire.'
                },
                {
                    group: GROUP,
                    target: '[data-tour="patients-dossier.medical"]',
                    title: 'Consultation en cours',
                    content: 'Une consultation ouverte apparait dans le suivi clinique. Une seconde ouverture est bloquee.'
                },
                {
                    group: GROUP,
                    target: '[data-tour="patients-dossier.dialog.active-warning"]',
                    title: variantId === 'blocked-with-fiche' ? 'Fiche deja saisie' : 'Annulation possible',
                    content:
                        variantId === 'blocked-with-fiche'
                            ? 'Consultation avec fiche medicale : suppression impossible ici. Cloturez la fiche dans Consultations.'
                            : 'Sans fiche saisie, annulez la consultation ouverte par erreur directement depuis ce dialogue.',
                    beforeEnter: async () => {
                        await ctx.openDuplicateConsultationDialog?.(variantId);
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
                target: '[data-tour="patients-dossier.actions"]',
                title: 'Demarrer depuis le dossier',
                content: 'Patient deja preselectionne : ideal pour eviter toute erreur d identification a l accueil.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-form-consultation.schedule"]',
                title: 'Medecin et horaire',
                content: 'Choisissez le medecin et la date/heure de consultation.',
                beforeEnter: async () => openDialogStep(ctx.openConsultationDialog, ctx.closeAllDialogs)
            },
            {
                group: GROUP,
                target: '[data-tour="patients-form-consultation.insurance"], [data-tour="patients-form-consultation.payment"]',
                title: 'Assurance et reglement',
                content: 'Bandeau vert si le patient est assure. Sinon, passez directement au reglement : consultation payante et mode de paiement patient.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-form-consultation.actions"]',
                title: 'Valider',
                content: 'La consultation apparait en attente de fiche dans le module Consultations.',
                afterLeave: async () => {
                    ctx.closeAllDialogs();
                    await flushUi();
                }
            }
        ]);
    },
    'edit-patient': (ctx) => {
        const formSteps = buildDossierPatientFormTabSteps(ctx);
        return normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.actions"]',
                title: 'Modifier le dossier',
                content: 'Ouvre le formulaire administratif complet avec les trois onglets : identite, SMS et assurance.'
            },
            ...formSteps.map((step, index) => ({
                ...step,
                ...(index === 0
                    ? {
                          beforeEnter: async () => {
                              await ctx.openEditPatientDialog?.();
                              await flushUi();
                              await switchFormTabStep(ctx, 'personal');
                          }
                      }
                    : {}),
                ...(index === formSteps.length - 1
                    ? {
                          afterLeave: async () => {
                              ctx.closeAllDialogs();
                              await flushUi();
                          }
                      }
                    : {})
            }))
        ]);
    },
    'schedule-rdv': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.actions"]',
                title: 'Planifier un passage',
                content: 'Patient du dossier courant deja preselectionne dans le formulaire de rendez-vous.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-form-rdv.details"]',
                title: 'Detail du rendez-vous',
                content: 'Medecin, duree, motif, date/heure et notes internes pour preparer l accueil.',
                beforeEnter: async () => openDialogStep(ctx.openRdvDialog, ctx.closeAllDialogs)
            },
            {
                group: GROUP,
                target: '[data-tour="patients-form-rdv.sms-reminder"]',
                title: 'Rappel SMS',
                content: 'Programmez un rappel (1 jour avant par defaut) et une repetition eventuelle. Respecte les preferences SMS du patient.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.finance-tabs"]',
                title: 'Suivi dans le dossier',
                content: 'Le RDV apparait dans l onglet Rendez-vous de la section historique administratif, avec le statut du SMS programme.'
            }
        ]),
    'manage-archive': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.archive-toolbar"]',
                title: 'Deposer un document',
                content: 'Ajoutez des fichiers administratifs (PDF, images, documents) independants des fiches medicales.'
            },
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.archive-table"]',
                title: 'Consulter et gerer',
                content: 'Liste des fichiers avec actions Voir, Telecharger et Supprimer pour chaque document archive.'
            }
        ]),
    'print-dossier': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="patients-dossier.actions"]',
                title: 'Imprimer une fiche',
                content: ctx.hasFiches ? 'Prepare un document a partir des fiches medicales du patient.' : 'Sans fiche medicale, l impression n est pas disponible. Completez une fiche dans Consultations d abord.'
            },
            ...(ctx.hasFiches
                ? [
                      {
                          group: GROUP,
                          target: '[data-tour="patients-dossier.dialog.print"]',
                          title: 'Choisir les sections',
                          content: 'Questionnaire medical, examen, images, plan de traitement, bilan dentaire, seances. Option pour inclure les champs vides.',
                          beforeEnter: async () => openDialogStep(ctx.openPrintDialog, ctx.closeAllDialogs)
                      },
                      {
                          group: GROUP,
                          target: '[data-tour="patients-dossier.dialog.print"]',
                          title: 'Generer le document',
                          content: 'Lance l impression ou l apercu PDF avec l identite du patient et les sections selectionnees.',
                          afterLeave: async () => {
                              ctx.closeAllDialogs();
                              await flushUi();
                          }
                      }
                  ]
                : [])
        ])
});

export function buildPatientsDossierTourSteps(taskId, variantId, ctx) {
    return patientsDossierRegistry.buildSteps(taskId, variantId, ctx);
}

export function createPatientsDossierTour(ctx) {
    return buildPatientsDossierTourSteps('overview', null, ctx);
}

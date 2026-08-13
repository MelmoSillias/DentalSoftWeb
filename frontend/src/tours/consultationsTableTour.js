import { flushUi, openDialogStep, normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'consultations-table';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'filter-date', label: 'Filtrer par date', icon: 'pi pi-filter', mockScenario: 'static' },
    { id: 'continue-open', label: 'Poursuivre une consultation', icon: 'pi pi-play', mockScenario: 'static' },
    { id: 'view-details', label: 'Voir les details', icon: 'pi pi-eye', mockScenario: 'static' },
    { id: 'quick-cloture', label: 'Cloture rapide', icon: 'pi pi-check-circle', mockScenario: 'static' },
    { id: 'modify-facture', label: 'Modifier une facture', icon: 'pi pi-file-edit', roles: ['admin'], mockScenario: 'static' },
    { id: 'urgent-case', label: 'Cas urgent', icon: 'pi pi-exclamation-triangle', mockScenario: 'static' }
];

function buildOverviewSteps(ctx) {
    const steps = [
        {
            group: GROUP,
            target: '[data-tour="consultations-table.header"]',
            title: 'Historique des consultations',
            content: 'Cette vue liste les consultations de la date choisie avec leur statut et leurs actions metier.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-table.stats"]',
            title: 'Synthese du jour',
            content: 'Les cartes resument le volume total, les consultations cloturees et celles encore en cours.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-table.filters"]',
            title: 'Filtres de la journee',
            content: 'Combinez la recherche et le filtre de date pour isoler rapidement un patient ou une situation.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-table.table"]',
            title: 'Tableau des consultations',
            content: 'Le tableau centralise patient, medecin, date de creation et statut pour une lecture rapide.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-table.status"]',
            title: 'Lecture du statut',
            content: 'Les badges distinguent les consultations en cours et cloturees, avec indication urgente si presente.'
        }
    ];

    if (ctx.hasConsultations) {
        steps.push({
            group: GROUP,
            target: '[data-tour="consultations-table.actions"]',
            title: 'Actions par ligne',
            content: 'Depuis une ligne, vous pouvez ouvrir le dossier, voir les details, gerer la facture ou lancer des actions rapides.'
        });
    }

    return normalizeTourSteps(steps);
}

export const consultationsTableRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'filter-date': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="consultations-table.filters"]',
            title: 'Filtres de la journee',
            content: 'La recherche et le selecteur de date permettent d isoler les consultations d une journee precise.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-table.table"]',
            title: 'Resultats filtres',
            content: 'Le tableau se met a jour selon les criteres choisis pour afficher uniquement les lignes correspondantes.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-table.stats"]',
            title: 'Synthese recalculee',
            content: 'Les cartes du bas s adaptent aux consultations visibles pour la date selectionnee.'
        }
    ]),
    'continue-open': (ctx) => {
        const steps = [];

        if (ctx.hasRepriseCase || ctx.hasLinkedCase || ctx.hasFreshCase) {
            steps.push({
                group: GROUP,
                target: '[data-tour="consultations-table.actions"]',
                title: 'Poursuivre une consultation',
                content: 'Les actions rapides lancent une cloture apres liaison automatique a la derniere fiche medicale du patient.'
            });
        }

        if (ctx.hasOpenConsultation) {
            steps.push({
                group: GROUP,
                target: '[data-tour="consultations-table.dialog.quick"]',
                title: 'Cloture rapide',
                content: 'Le dialogue prepare la cloture en liant automatiquement la derniere fiche medicale.',
                beforeEnter: async () => openDialogStep(ctx.openQuickDialog, ctx.closeAllDialogs)
            });
        }

        return normalizeTourSteps(steps.length ? steps : [
            {
                group: GROUP,
                target: '[data-tour="consultations-table.actions"]',
                title: 'Actions par ligne',
                content: 'Utilisez les actions de ligne pour reprendre une consultation encore ouverte.'
            }
        ]);
    },
    'view-details': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="consultations-table.actions"]',
            title: 'Ouvrir les details',
            content: 'Depuis une ligne, l action details permet une relecture rapide de la consultation.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-table.dialog.details"]',
            title: 'Dialogue de details',
            content: 'Ce dialogue centralise la consultation, la note de seance et les actes enregistres.',
            beforeEnter: async () => openDialogStep(ctx.openDetailsDialog, ctx.closeAllDialogs)
        }
    ]),
    'quick-cloture': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="consultations-table.actions"]',
            title: 'Actions rapides',
            content: 'Depuis une consultation ouverte, lancez une cloture rapide. La derniere fiche medicale est liee automatiquement.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-table.dialog.quick"]',
            title: 'Dialogue de cloture',
            content: 'Ce dialogue prepare la cloture apres liaison automatique a la derniere fiche medicale.',
            beforeEnter: async () => openDialogStep(ctx.openQuickDialog, ctx.closeAllDialogs)
        }
    ]),
    'modify-facture': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="consultations-table.case-closed"]',
            title: 'Consultation cloturee',
            content: 'Une consultation cloturee reste visible et peut faire l objet d ajustements de facture.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-table.actions"]',
            title: 'Gerer la facture',
            content: 'Depuis une ligne cloturee, ouvrez la gestion de facture si votre profil le permet.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-table.dialog.facture"]',
            title: 'Dialogue de facture',
            content: 'Revoyez les lignes facturees et corrigez une facture de consultation deja cloturee.',
            beforeEnter: async () => openDialogStep(ctx.openFactureDialog, ctx.closeAllDialogs)
        }
    ]),
    'urgent-case': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="consultations-table.case-urgent"]',
            title: 'Indicateur d urgence',
            content: 'L indicateur urgent permet de reperer immediatement les consultations prioritaires.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-table.status"]',
            title: 'Badge de statut',
            content: 'Le badge de statut confirme visuellement l urgence associee a la consultation.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-table.actions"]',
            title: 'Traiter en priorite',
            content: 'Depuis la ligne urgente, ouvrez le dossier ou les details pour accelerer la prise en charge.'
        }
    ])
});

export function buildConsultationsTableTourSteps(taskId, variantId, ctx) {
    return consultationsTableRegistry.buildSteps(taskId, variantId, ctx);
}

export function createConsultationsTableTour(ctx) {
    return buildConsultationsTableTourSteps('overview', null, ctx);
}

import { openDialogStep, normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'agenda-rendezvous';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'create-rdv', label: 'Creer un rendez-vous', icon: 'pi pi-calendar-plus', mockScenario: 'static' },
    { id: 'edit-rdv', label: 'Modifier un rendez-vous', icon: 'pi pi-pencil', mockScenario: 'static' },
    { id: 'filter-calendar', label: 'Filtrer le calendrier', icon: 'pi pi-filter', mockScenario: 'static' }
];

function buildOverviewSteps(ctx) {
    const steps = [
        {
            group: GROUP,
            target: '[data-tour="agenda-rdv.header"]',
            title: 'Gestion des rendez-vous',
            content: 'Cette page sert a planifier et suivre les rendez-vous du cabinet sur une vue semaine ou jour.'
        },
        {
            group: GROUP,
            target: '[data-tour="agenda-rdv.legend"]',
            title: 'Comprendre les statuts',
            content: 'La legende rappelle les couleurs utilisees pour distinguer les rendez-vous en attente, valides, reportes ou annules.'
        },
        {
            group: GROUP,
            target: '[data-tour="agenda-rdv.tabs"]',
            title: 'Changer de vue',
            content: 'Basculez entre la vue hebdomadaire et la vue journaliere selon le niveau de detail souhaite.'
        },
        {
            group: GROUP,
            target: '[data-tour="agenda-rdv.calendar"]',
            title: 'Interagir avec le planning',
            content: 'Cliquez sur un creneau libre pour creer un rendez-vous, ou sur un rendez-vous existant pour lancer les actions metier.'
        }
    ];

    if (ctx.isMedecin) {
        steps.push({
            group: GROUP,
            target: '[data-tour="agenda-rdv.scope"]',
            title: 'Portee medecin',
            content: 'Si vous etes medecin, la vue est verrouillee sur votre agenda et le choix du praticien est restreint.'
        });
    }

    return normalizeTourSteps(steps);
}

export const agendaRendezvousRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'create-rdv': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="agenda-rdv.calendar"]',
            title: 'Choisir un creneau',
            content: 'Cliquez sur un creneau libre du calendrier pour demarrer la creation d un rendez-vous.'
        },
        {
            group: GROUP,
            target: '[data-tour="agenda-rdv.dialogs"]',
            title: 'Formulaire de creation',
            content: 'Le dialogue permet de renseigner le patient, le medecin, la date et les options de rappel SMS.',
            beforeEnter: async () => openDialogStep(
                () => ctx.openCreateDialog?.(),
                ctx.closeAllDialogs
            )
        }
    ]),
    'edit-rdv': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="agenda-rdv.calendar"]',
            title: 'Selectionner un rendez-vous',
            content: 'Cliquez sur un rendez-vous existant pour ouvrir les actions de modification, validation ou report.'
        },
        {
            group: GROUP,
            target: '[data-tour="agenda-rdv.dialogs"]',
            title: 'Actions sur le rendez-vous',
            content: 'Depuis le dialogue, vous pouvez valider, reporter, annuler ou reprogrammer le rendez-vous selectionne.',
            beforeEnter: async () => openDialogStep(
                () => ctx.openEditDialog?.(),
                ctx.closeAllDialogs
            )
        }
    ]),
    'filter-calendar': (ctx) => {
        const steps = [
            {
                group: GROUP,
                target: '[data-tour="agenda-rdv.tabs"]',
                title: 'Changer de vue',
                content: 'Basculez entre la vue semaine et la vue jour pour ajuster le niveau de detail affiche.'
            }
        ];

        if (ctx.isMedecin) {
            steps.push({
                group: GROUP,
                target: '[data-tour="agenda-rdv.scope"]',
                title: 'Filtrer par medecin',
                content: 'En tant que medecin, le calendrier est automatiquement limite a votre propre agenda.'
            });
        } else {
            steps.push({
                group: GROUP,
                target: '[data-tour="agenda-rdv.scope"]',
                title: 'Filtrer par medecin',
                content: 'Selectionnez un praticien pour restreindre le calendrier aux rendez-vous qui le concernent.'
            });
        }

        steps.push({
            group: GROUP,
            target: '[data-tour="agenda-rdv.legend"]',
            title: 'Lire les statuts',
            content: 'La legende aide a distinguer rapidement les rendez-vous selon leur etat dans le planning.'
        });

        return normalizeTourSteps(steps);
    }
});

export function buildAgendaRendezvousTourSteps(taskId, variantId, ctx) {
    return agendaRendezvousRegistry.buildSteps(taskId, variantId, ctx);
}

export function createAgendaRendezvousTour(ctx) {
    return buildAgendaRendezvousTourSteps('overview', null, ctx);
}

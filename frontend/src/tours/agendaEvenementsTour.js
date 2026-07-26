import { openDialogStep, normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'agenda-evenements';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'create-event', label: 'Creer un evenement', icon: 'pi pi-plus-circle', mockScenario: 'static' },
    { id: 'manage-events', label: 'Gerer les evenements', icon: 'pi pi-list', mockScenario: 'static' }
];

function buildOverviewSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="agenda-events.header"]',
            title: 'Agenda des evenements',
            content: 'Cette page gere les evenements generaux du cabinet en dehors des rendez-vous patients.'
        },
        {
            group: GROUP,
            target: '[data-tour="agenda-events.create"]',
            title: 'Ajouter un evenement',
            content: 'Ce bouton ouvre le formulaire pour declarer un nouvel evenement dans le calendrier.'
        },
        {
            group: GROUP,
            target: '[data-tour="agenda-events.calendar"]',
            title: 'Naviguer dans le calendrier',
            content: 'Le calendrier permet de changer de mois, de passer en vue semaine et de visualiser les evenements a venir.'
        },
        {
            group: GROUP,
            target: '[data-tour="agenda-events.status"]',
            title: 'Lire l etat des evenements',
            content: 'Les couleurs du calendrier distinguent les evenements valides des evenements encore en attente.'
        }
    ]);
}

export const agendaEvenementsRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'create-event': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="agenda-events.create"]',
            title: 'Nouvel evenement',
            content: 'Cliquez sur ce bouton pour ouvrir le formulaire de creation.'
        },
        {
            group: GROUP,
            target: '[data-tour="agenda-events.actions"]',
            title: 'Formulaire evenement',
            content: 'Renseignez le titre, la date, la duree et les participants avant validation.',
            beforeEnter: async () => openDialogStep(
                () => ctx.openCreateDialog?.(),
                ctx.closeAllDialogs
            )
        }
    ]),
    'manage-events': (ctx) => {
        const steps = [
            {
                group: GROUP,
                target: '[data-tour="agenda-events.calendar"]',
                title: 'Reperer un evenement',
                content: 'Les evenements apparaissent directement dans le calendrier selon leur statut.'
            }
        ];

        if (ctx.hasEvents !== false) {
            steps.push({
                group: GROUP,
                target: '[data-tour="agenda-events.actions"]',
                title: 'Ouvrir les actions contextuelles',
                content: 'Un clic droit sur un evenement ouvre les actions de validation ou suppression.',
                beforeEnter: async () => openDialogStep(
                    () => ctx.openActionsDialog?.(),
                    ctx.closeAllDialogs
                )
            });
        } else {
            steps.push({
                group: GROUP,
                target: '[data-tour="agenda-events.status"]',
                title: 'Liste vide',
                content: 'Quand aucun evenement n est planifie, commencez par en creer un depuis le bouton dedie.'
            });
        }

        return normalizeTourSteps(steps);
    }
});

export function buildAgendaEvenementsTourSteps(taskId, variantId, ctx) {
    return agendaEvenementsRegistry.buildSteps(taskId, variantId, ctx);
}

export function createAgendaEvenementsTour(ctx) {
    return buildAgendaEvenementsTourSteps('overview', null, ctx);
}

import { flushUi, openDialogStep, normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'administration-salles';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'add-salle', label: 'Ajouter une salle', icon: 'pi pi-plus', mockScenario: 'static' },
    { id: 'edit-salle', label: 'Modifier une salle', icon: 'pi pi-pencil', mockScenario: 'static' }
];

function buildOverviewSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="admin-salles.header"]',
            title: 'Gestion des salles',
            content: 'Cette page gere les espaces de consultation et de traitement du cabinet.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-salles.table"]',
            title: 'Consulter les salles',
            content: 'Le tableau centralise le nom, le type, le statut et les actions de chaque salle.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-salles.actions"]',
            title: 'Modifier ou supprimer',
            content: 'Depuis une ligne, vous pouvez editer les informations de la salle ou la supprimer.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-salles.stats"]',
            title: 'Lire l occupation',
            content: 'Les cartes de synthese montrent combien de salles sont disponibles, occupees et reparties par type.'
        }
    ]);
}

export const administrationSallesRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'add-salle': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="admin-salles.header"]',
                title: 'Ajouter une salle',
                content: 'Depuis la page de gestion, ouvrez le formulaire pour creer un nouvel espace.'
            },
            {
                group: GROUP,
                target: '[data-tour="admin-salles.dialog.add"]',
                title: 'Formulaire de creation',
                content: 'Le dialogue permet de renseigner le nom, le type et la description de la nouvelle salle.',
                beforeEnter: async () => openDialogStep(ctx.openAddDialog, ctx.closeAllDialogs)
            }
        ]),
    'edit-salle': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="admin-salles.actions"]',
                title: 'Modifier une salle',
                content: 'Utilisez l action edition sur une ligne pour ajuster les informations d une salle existante.'
            },
            {
                group: GROUP,
                target: '[data-tour="admin-salles.dialog.edit"]',
                title: 'Formulaire d edition',
                content: 'Le dialogue d edition permet d ajuster le nom ou la description sans quitter le tableau.',
                beforeEnter: async () => openDialogStep(ctx.openEditDialog, ctx.closeAllDialogs)
            }
        ])
});

export function buildAdministrationSallesTourSteps(taskId, variantId, ctx) {
    return administrationSallesRegistry.buildSteps(taskId, variantId, ctx);
}

export function createAdministrationSallesTour(ctx) {
    return buildAdministrationSallesTourSteps('overview', null, ctx);
}

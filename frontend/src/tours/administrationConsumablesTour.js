import { flushUi, openDialogStep, normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'administration-consommables';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'manage-list', label: 'Gerer la liste', icon: 'pi pi-list', mockScenario: 'static' },
    { id: 'audit-variations', label: 'Auditer les variations', icon: 'pi pi-history', mockScenario: 'static' },
    { id: 'add-consumable', label: 'Ajouter un consommable', icon: 'pi pi-plus', mockScenario: 'static' },
    { id: 'stock-movement', label: 'Mouvement de stock', icon: 'pi pi-arrows-h', mockScenario: 'static' }
];

function buildOverviewSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="admin-consumables.header"]',
            title: 'Gestion du stock',
            content: 'Cette page sert a suivre les consommables, leur disponibilite et leurs mouvements.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-consumables.mode"]',
            title: 'Changer de mode d affichage',
            content: 'Basculez entre la liste des consommables et l historique des variations de stock.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-consumables.stats"]',
            title: 'Prioriser les alertes',
            content: 'Les cartes mettent en avant le total, le stock suffisant, le stock faible et les ruptures.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-consumables.list"]',
            title: 'Liste des articles',
            content: 'La liste permet de consulter, modifier, supprimer et ouvrir les actions de stock.',
            beforeEnter: async () => {
                ctx.setMode('list');
                await flushUi();
            }
        }
    ]);
}

export const administrationConsumablesRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'manage-list': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="admin-consumables.mode"]',
                title: 'Mode liste',
                content: 'Assurez-vous d etre en mode liste pour gerer les articles du stock.'
            },
            {
                group: GROUP,
                target: '[data-tour="admin-consumables.list"]',
                title: 'Gerer les articles',
                content: 'La liste permet de consulter, modifier, supprimer et ouvrir les actions d ajout ou retrait de stock.',
                beforeEnter: async () => {
                    ctx.setMode('list');
                    await flushUi();
                }
            },
            {
                group: GROUP,
                target: '[data-tour="admin-consumables.dialog.details"]',
                title: 'Detail d un article',
                content: 'Le dialogue de details permet de verifier fournisseur, stock courant et seuil bas.',
                beforeEnter: async () => {
                    ctx.setMode('list');
                    await openDialogStep(ctx.openDetailsDialog, ctx.closeAllDialogs);
                }
            }
        ]),
    'audit-variations': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="admin-consumables.mode"]',
                title: 'Mode variations',
                content: 'Basculez vers le mode Variations pour auditer les mouvements de stock.'
            },
            {
                group: GROUP,
                target: '[data-tour="admin-consumables.variations"]',
                title: 'Historique des mouvements',
                content: 'Suivez les entrees et sorties de stock sur une periode donnee.',
                beforeEnter: async () => {
                    ctx.setMode('vars');
                    await flushUi();
                }
            }
        ]),
    'add-consumable': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="admin-consumables.list"]',
                title: 'Liste des consommables',
                content: 'Depuis la liste, ouvrez le formulaire pour ajouter un nouvel article.',
                beforeEnter: async () => {
                    ctx.setMode('list');
                    await flushUi();
                }
            },
            {
                group: GROUP,
                target: '[data-tour="admin-consumables.dialog.create"]',
                title: 'Ajouter un consommable',
                content: 'Le dialogue permet de creer ou modifier un consommable sans quitter la page.',
                beforeEnter: async () => {
                    ctx.setMode('list');
                    await openDialogStep(ctx.openCreateDialog, ctx.closeAllDialogs);
                }
            }
        ]),
    'stock-movement': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="admin-consumables.list"]',
                title: 'Selectionner un article',
                content: 'Choisissez un consommable dans la liste pour enregistrer un mouvement de stock.',
                beforeEnter: async () => {
                    ctx.setMode('list');
                    await flushUi();
                }
            },
            {
                group: GROUP,
                target: '[data-tour="admin-consumables.dialog.stock"]',
                title: 'Ajouter ou retirer du stock',
                content: 'Enregistrez une entree ou une sortie avec quantite, employe et description.',
                beforeEnter: async () => {
                    ctx.setMode('list');
                    await openDialogStep(() => ctx.openStockDialog('withdraw'), ctx.closeAllDialogs);
                }
            }
        ])
});

export function buildAdministrationConsumablesTourSteps(taskId, variantId, ctx) {
    return administrationConsumablesRegistry.buildSteps(taskId, variantId, ctx);
}

export function createAdministrationConsumablesTour(ctx) {
    return buildAdministrationConsumablesTourSteps('overview', null, ctx);
}

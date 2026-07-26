import { flushUi, openDialogStep, normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'administration-gestionrh';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'add-employee', label: 'Ajouter un employe', icon: 'pi pi-user-plus', mockScenario: 'static' },
    { id: 'manage-contracts', label: 'Gerer les contrats', icon: 'pi pi-briefcase', mockScenario: 'static' }
];

function buildOverviewSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="admin-rh.header"]',
            title: 'Pilotage RH',
            content: 'Cette page centralise la gestion RH du cabinet: liste des employes, creation et actions courantes.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-rh.table"]',
            title: 'Liste des employes',
            content: 'Le tableau affiche identite, fonction, type, telephone et date d embauche pour chaque employe.',
            beforeEnter: async () => {
                await ctx.expandGroups?.();
                await flushUi();
            }
        },
        {
            group: GROUP,
            target: '[data-tour="admin-rh.actions"]',
            title: 'Actions par ligne',
            content: 'Chaque ligne donne acces a la fiche detaillee, a la modification RH ou a la suppression.',
            beforeEnter: async () => {
                await ctx.expandGroups?.();
                await flushUi();
            }
        },
        {
            group: GROUP,
            target: '[data-tour="admin-rh.stats"]',
            title: 'Indicateurs rapides',
            content: 'Les cartes de synthese fournissent une lecture rapide des employes, salaires et conges enregistres.'
        }
    ]);
}

export const administrationGestionrhRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'add-employee': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="admin-rh.header"]',
            title: 'Ajouter un employe',
            content: 'Depuis la page RH, ouvrez le formulaire pour creer un nouveau collaborateur.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-rh.dialog.form"]',
            title: 'Formulaire RH',
            content: 'Renseignez donnees personnelles, informations contractuelles, salaire et documents administratifs.',
            beforeEnter: async () => {
                await ctx.expandGroups?.();
                await openDialogStep(ctx.openCreateDialog, ctx.closeAllDialogs);
            }
        }
    ]),
    'manage-contracts': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="admin-rh.actions"]',
            title: 'Modifier un employe',
            content: 'Utilisez l action edition sur une ligne pour mettre a jour le contrat ou le salaire.',
            beforeEnter: async () => {
                await ctx.expandGroups?.();
                await flushUi();
            }
        },
        {
            group: GROUP,
            target: '[data-tour="admin-rh.dialog.form"]',
            title: 'Formulaire de modification',
            content: 'La modale permet de corriger un contrat, ajuster un salaire ou completer les informations RH.',
            beforeEnter: async () => {
                await ctx.expandGroups?.();
                await openDialogStep(ctx.openEditDialog, ctx.closeAllDialogs);
            }
        }
    ])
});

export function buildAdministrationGestionRHTourSteps(taskId, variantId, ctx) {
    return administrationGestionrhRegistry.buildSteps(taskId, variantId, ctx);
}

export function createAdministrationGestionRHTour(ctx) {
    return buildAdministrationGestionRHTourSteps('overview', null, ctx);
}

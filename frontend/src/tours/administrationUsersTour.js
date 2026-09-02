import { flushUi, openDialogStep, normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'administration-utilisateurs';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'create-user', label: 'Creer un utilisateur', icon: 'pi pi-user-plus', mockScenario: 'static' },
    { id: 'edit-roles', label: 'Modifier les roles', icon: 'pi pi-shield', mockScenario: 'static' }
];

function buildOverviewSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="admin-users.header"]',
            title: 'Gestion des comptes',
            content: 'Cette page centralise la creation, la modification et la securisation des utilisateurs applicatifs.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-users.grouping"]',
            title: 'Regrouper par type',
            content: 'Activez le regroupement pour analyser rapidement les comptes par profil metier.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-users.search"]',
            title: 'Recherche globale',
            content: 'Le filtre recherche interroge simultanement nom utilisateur, employe lie et type de compte.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-users.table"]',
            title: 'Table des utilisateurs',
            content: 'La table affiche les comptes existants et permet un tri multi-colonnes pour les controles administratifs.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-users.actions"]',
            title: 'Actions critiques',
            content: 'Chaque ligne propose edition, reinitialisation mot de passe et suppression selon les besoins.'
        }
    ]);
}

export const administrationUtilisateursRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'create-user': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="admin-users.header"]',
                title: 'Creer un compte',
                content: 'Depuis la barre principale, ouvrez le formulaire de creation d utilisateur.'
            },
            {
                group: GROUP,
                target: '[data-tour="admin-users.dialog.create"]',
                title: 'Formulaire de creation',
                content: 'Renseignez le compte et rattachez-le a un employe existant avec le profil adapte.',
                beforeEnter: async () => openDialogStep(ctx.openCreateDialog, ctx.closeAllDialogs)
            }
        ]),
    'edit-roles': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="admin-users.actions"]',
                title: 'Modifier un compte',
                content: 'Utilisez l action edition sur une ligne pour ajuster le profil et les roles d un utilisateur.'
            },
            {
                group: GROUP,
                target: '[data-tour="admin-users.dialog.create"]',
                title: 'Formulaire d edition',
                content: 'Le meme formulaire s ouvre en mode edition pour modifier le type de compte et les droits associes.',
                beforeEnter: async () => openDialogStep(ctx.openEditDialog || ctx.openCreateDialog, ctx.closeAllDialogs)
            },
            ...(ctx.hasUsers
                ? [
                      {
                          group: GROUP,
                          target: '[data-tour="admin-users.dialog.reset"]',
                          title: 'Securiser l acces',
                          content: 'Vous pouvez aussi reinitialiser le mot de passe temporaire d un utilisateur depuis cette page.',
                          beforeEnter: async () => openDialogStep(ctx.openResetDialog, ctx.closeAllDialogs)
                      }
                  ]
                : [])
        ])
});

export function buildAdministrationUsersTourSteps(taskId, variantId, ctx) {
    return administrationUtilisateursRegistry.buildSteps(taskId, variantId, ctx);
}

export function createAdministrationUsersTour(ctx) {
    return buildAdministrationUsersTourSteps('overview', null, ctx);
}

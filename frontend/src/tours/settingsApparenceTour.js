import { flushUi, normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'settings-apparence';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'customize-appearance', label: 'Personnaliser l apparence', icon: 'pi pi-palette', mockScenario: 'static' },
    { id: 'configure-cabinet', label: 'Configurer le cabinet', icon: 'pi pi-building', mockScenario: 'static', roles: ['admin'] },
    { id: 'configure-portal', label: 'Configurer le portail', icon: 'pi pi-globe', mockScenario: 'static', roles: ['admin'] },
    { id: 'system-administration', label: 'Administration systeme', icon: 'pi pi-cog', mockScenario: 'static', roles: ['admin'] }
];

async function navigateToSection(ctx, category, section) {
    await ctx.navigateToSection?.(category, section);
    await flushUi();
}

function buildOverviewSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="settings-appearance.main"]',
            title: 'Parametres d apparence',
            content: 'Cette page centralise la personnalisation de l interface et les reglages generaux du cabinet.'
        },
        {
            group: GROUP,
            target: '[data-tour="settings-appearance.navigation"]',
            title: 'Navigation par section',
            content: 'Utilisez la colonne laterale pour naviguer rapidement vers chaque zone de configuration.'
        },
        {
            group: GROUP,
            target: '[data-tour="settings-appearance.theme"]',
            title: 'Theme et apparence',
            content: 'Ce bloc presente la synthese du theme, du preset, de la police et de la palette active.'
        }
    ]);
}

export const settingsApparenceRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'customize-appearance': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="settings-appearance.theme"]',
            title: 'Theme et apparence',
            content: 'Ce bloc presente la synthese du theme, du preset, de la police et de la palette active.',
            beforeEnter: async () => navigateToSection(ctx, 'appearance', 'overview')
        },
        {
            group: GROUP,
            target: '[data-tour="settings-appearance.primary"]',
            title: 'Couleurs principales',
            content: 'Choisissez ici le preset de composants, la couleur principale et la palette de surface appliquee a l interface.',
            beforeEnter: async () => navigateToSection(ctx, 'appearance', 'colors')
        },
        {
            group: GROUP,
            target: '[data-tour="settings-appearance.font-family"]',
            title: 'Typographie',
            content: 'Ajustez la police et la taille de texte pour harmoniser la lisibilite de l application.',
            beforeEnter: async () => navigateToSection(ctx, 'appearance', 'typography')
        },
        {
            group: GROUP,
            target: '[data-tour="settings-appearance.layout"]',
            title: 'Disposition du menu',
            content: 'Configurez le mode de navigation lateral ou statique selon vos preferences.',
            beforeEnter: async () => navigateToSection(ctx, 'appearance', 'layout')
        }
    ]),
    'configure-cabinet': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="settings-cabinet.consultations"]',
            title: 'Politique de consultation',
            content: 'Definissez les regles de creation de consultation, les droits reception et le prix par defaut.',
            beforeEnter: async () => navigateToSection(ctx, 'cabinet', 'consultations')
        },
        {
            group: GROUP,
            target: '[data-tour="settings-cabinet.opening-hours"]',
            title: 'Horaires d ouverture',
            content: 'Renseignez les heures d ouverture et de fermeture du cabinet.',
            beforeEnter: async () => navigateToSection(ctx, 'cabinet', 'opening-hours')
        },
        {
            group: GROUP,
            target: '[data-tour="settings-cabinet.billing"]',
            title: 'Facturation',
            content: 'Ajustez les regles de facturation et les options liees aux paiements.',
            beforeEnter: async () => navigateToSection(ctx, 'cabinet', 'billing')
        }
    ]),
    'configure-portal': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="settings-portal.portal-settings"]',
            title: 'Portail patient',
            content: 'Configurez l URL du portail, les QR codes et les options d acces patient.',
            beforeEnter: async () => navigateToSection(ctx, 'portal', 'portal-settings')
        }
    ]),
    'system-administration': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="settings-administration.devices"]',
            title: 'Appareils approuves',
            content: 'Gerez les appareils autorises a se connecter au cabinet et consultez les journaux d acces.',
            beforeEnter: async () => navigateToSection(ctx, 'administration', 'devices')
        },
        {
            group: GROUP,
            target: '[data-tour="settings-administration.system-maintenance"]',
            title: 'Maintenance systeme',
            content: 'Exportez, sauvegardez ou reinitialisez la base de donnees depuis cette zone reservee aux administrateurs.',
            beforeEnter: async () => navigateToSection(ctx, 'administration', 'system-maintenance')
        }
    ])
});

export function buildSettingsApparenceTourSteps(taskId, variantId, ctx) {
    return settingsApparenceRegistry.buildSteps(taskId, variantId, ctx);
}

export function createSettingsApparenceTour(ctx) {
    return buildSettingsApparenceTourSteps('overview', null, ctx);
}

import { flushUi, normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'administration-api-sms';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'configured' },
    {
        id: 'configure-provider',
        label: 'Configurer le provider',
        icon: 'pi pi-cog',
        mockScenario: 'configured',
        variants: [
            { id: 'orange', label: 'Provider Orange', mockScenario: 'configured' },
            { id: 'afrik', label: 'Provider Afrik', mockScenario: 'configured' }
        ]
    },
    {
        id: 'manage-queue',
        label: 'Gerer la file SMS',
        icon: 'pi pi-list',
        mockScenario: 'queue-pending',
        variants: [
            { id: 'pending', label: 'Messages en attente', mockScenario: 'queue-pending' },
            { id: 'empty', label: 'File vide', mockScenario: 'queue-empty' }
        ]
    },
    { id: 'manage-templates', label: 'Gerer les templates', icon: 'pi pi-file-edit', mockScenario: 'configured' },
    { id: 'manual-send', label: 'Envoi manuel', icon: 'pi pi-send', mockScenario: 'configured' }
];

function buildOverviewSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="sms-settings.overview"]',
            title: 'Apercu SMS',
            content: 'Cette page centralise la configuration SMS, la file d envoi, les templates et l envoi manuel.'
        },
        {
            group: GROUP,
            target: '[data-tour="sms-settings.status"]',
            title: 'Statut automation',
            content: 'Le bandeau indique si l automation SMS est operationnelle selon le provider configure.'
        },
        {
            group: GROUP,
            target: '[data-tour="sms-settings.tabs"]',
            title: 'Navigation par onglets',
            content: 'Basculez entre apercu, configuration, file, logs, templates et envoi manuel.'
        }
    ]);
}

export const smsSettingsRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'configure-provider': (ctx, variantId) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="sms-settings.config"]',
            title: 'Configuration provider',
            content: variantId === 'afrik'
                ? 'Renseignez les identifiants Afrik SMS puis testez la connexion.'
                : 'Renseignez les identifiants Orange SMS puis testez la connexion.',
            beforeEnter: async () => {
                await ctx.switchTab?.('config');
                await flushUi();
            }
        },
        {
            group: GROUP,
            target: '[data-tour="sms-settings.test-connection"]',
            title: 'Tester la connexion',
            content: 'Verifiez que le provider repond avant d enregistrer la configuration.'
        },
        {
            group: GROUP,
            target: '[data-tour="sms-settings.save-config"]',
            title: 'Enregistrer',
            content: 'Sauvegardez la configuration active pour le cabinet.'
        }
    ]),
    'manage-queue': (ctx, variantId) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="sms-settings.queue"]',
            title: 'File SMS',
            content: variantId === 'empty'
                ? 'Quand la file est vide, aucun message n est en attente d envoi.'
                : 'Les messages en attente peuvent etre relances, reprogrammes ou annules.',
            beforeEnter: async () => {
                await ctx.switchTab?.('queue');
                await flushUi();
            }
        },
        {
            group: GROUP,
            target: '[data-tour="sms-settings.queue-actions"]',
            title: 'Actions file',
            content: 'Utilisez retry, annulation ou reprogrammation selon le statut du message.'
        }
    ]),
    'manage-templates': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="sms-settings.templates"]',
            title: 'Templates SMS',
            content: 'Creez et modifiez les modeles reutilisables pour l automation.',
            beforeEnter: async () => {
                await ctx.switchTab?.('templates');
                await flushUi();
            }
        },
        {
            group: GROUP,
            target: '[data-tour="sms-settings.template-preview"]',
            title: 'Apercu template',
            content: 'Previsualisez le rendu du message avant enregistrement.'
        }
    ]),
    'manual-send': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="sms-settings.manual-send"]',
            title: 'Envoi manuel',
            content: 'Envoyez un SMS ponctuel a un patient ou un numero externe.',
            beforeEnter: async () => {
                await ctx.switchTab?.('manual');
                await flushUi();
            }
        }
    ])
});

export function buildSmsSettingsTourSteps(taskId, variantId, ctx) {
    return smsSettingsRegistry.buildSteps(taskId, variantId, ctx);
}

export function createSmsSettingsTour(ctx) {
    return buildSmsSettingsTourSteps('overview', null, ctx);
}

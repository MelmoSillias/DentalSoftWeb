import { flushUi, openDialogStep, normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'administration-finances';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'manage-transactions', label: 'Gerer les transactions', icon: 'pi pi-wallet', mockScenario: 'static' },
    { id: 'manage-payment-modes', label: 'Gerer les modes de paiement', icon: 'pi pi-credit-card', mockScenario: 'static' },
    { id: 'analyze-charts', label: 'Analyser les graphiques', icon: 'pi pi-chart-bar', mockScenario: 'static' }
];

export function resolveAdministrationFinancesTourGroup(activeTab) {
    return GROUP;
}

function buildOverviewSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="admin-finances.header"]',
            title: 'Tableau de bord financier',
            content: 'Cette page centralise la supervision financiere du cabinet: transactions, comptes et modes de paiement.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-finances.kpi"]',
            title: 'Lire les KPI',
            content: 'Les cartes synthetisent le capital disponible, les transactions validees, les flux en attente et les modes actifs.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-finances.tabs"]',
            title: 'Organisation par onglets',
            content: 'Transactions pour le suivi quotidien, Mode de paiement pour les comptes, Graphiques pour l analyse globale.'
        }
    ]);
}

export const administrationFinancesRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'manage-transactions': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="admin-finances.transactions"]',
            title: 'Filtrer l historique',
            content: 'Isolez les transactions a verifier en combinant periode, statut de validation et recherche libre.',
            beforeEnter: async () => {
                ctx.closeAllDialogs();
                await ctx.switchTab('transactions');
                await flushUi();
            }
        },
        {
            group: GROUP,
            target: '[data-tour="admin-finances.validation"]',
            title: 'Valider ou rejeter',
            content: 'Validez une transaction en attente ou rejetez-la si elle doit etre corrigee.',
            beforeEnter: async () => {
                ctx.closeAllDialogs();
                await ctx.switchTab('transactions');
                await flushUi();
            }
        },
        {
            group: GROUP,
            target: '[data-tour="admin-finances.dialog.transaction"]',
            title: 'Nouvelle transaction',
            content: 'Le formulaire enregistre une entree ou une sortie avec compte, montant, date et motif.',
            beforeEnter: async () => {
                ctx.closeAllDialogs();
                await ctx.switchTab('transactions');
                await ctx.openTransactionDialog();
                await flushUi();
            }
        }
    ]),
    'manage-payment-modes': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="admin-finances.methods"]',
            title: 'Modes de paiement',
            content: 'Ce tableau regroupe les comptes de paiement par famille, modes classiques et assurances.',
            beforeEnter: async () => {
                ctx.closeAllDialogs();
                await ctx.switchTab('payment-methods');
                await flushUi();
            }
        },
        {
            group: GROUP,
            target: '[data-tour="admin-finances.method-actions"]',
            title: 'Actions sur un mode',
            content: 'Chaque mode peut etre modifie, active ou desactive selon vos besoins.',
            beforeEnter: async () => {
                ctx.closeAllDialogs();
                await ctx.switchTab('payment-methods');
                await flushUi();
            }
        },
        {
            group: GROUP,
            target: '[data-tour="admin-finances.dialog.mode"]',
            title: 'Configurer un mode',
            content: 'Le formulaire permet d ajouter ou modifier un compte, y compris une assurance avec taux de couverture.',
            beforeEnter: async () => {
                ctx.closeAllDialogs();
                await ctx.switchTab('payment-methods');
                await ctx.openModeDialog();
                await flushUi();
            }
        }
    ]),
    'analyze-charts': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="admin-finances.tabs"]',
            title: 'Onglet graphiques',
            content: 'Basculez vers les graphiques pour acceder a l analyse financiere globale.',
            beforeEnter: async () => {
                ctx.closeAllDialogs();
                await ctx.switchTab('charts');
                await flushUi();
            }
        },
        {
            group: GROUP,
            target: '[data-tour="admin-finances.monthly-flow"]',
            title: 'Flux mensuel global',
            content: 'Ce graphique compare entrees, depenses et resultat net sur l annee choisie.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-finances.distribution"]',
            title: 'Repartition des encaissements',
            content: 'Ce donut montre la part de chaque mode de paiement dans les encaissements.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-finances.evolution"]',
            title: 'Evolution du capital',
            content: 'Le graphique final suit la progression cumulee du capital sur l annee.'
        }
    ])
});

export function buildAdministrationFinancesTourSteps(taskId, variantId, ctx) {
    return administrationFinancesRegistry.buildSteps(taskId, variantId, ctx);
}

export function createAdministrationFinancesTour(ctx) {
    return buildAdministrationFinancesTourSteps('overview', null, ctx);
}

import { normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'rapports';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'financial-report', label: 'Rapport financier', icon: 'pi pi-chart-line', mockScenario: 'static', roles: ['admin'] },
    { id: 'clinical-report', label: 'Rapport clinique', icon: 'pi pi-id-card', mockScenario: 'static', roles: ['medecin'] },
    { id: 'reception-report', label: 'Rapport reception', icon: 'pi pi-users', mockScenario: 'static', roles: ['reception'] },
    { id: 'export-report', label: 'Exporter le rapport', icon: 'pi pi-download', mockScenario: 'static' }
];

export function resolveRapportsTourGroup(role) {
    if (role === 'medecin') return 'rapports:medecin';
    if (role === 'reception') return 'rapports:reception';
    return 'rapports:admin';
}

function resolveStepGroup(ctx) {
    return resolveRapportsTourGroup(ctx.role);
}

function buildOverviewSteps(ctx) {
    const stepGroup = resolveStepGroup(ctx);
    const role = ctx.role;

    if (role === 'medecin') {
        return normalizeTourSteps([
            {
                group: stepGroup,
                target: '[data-tour="rapports-medecin.range"]',
                title: 'Choisir la periode',
                content: 'Le rapport se recalculera sur la plage choisie pour analyser votre activite recente.'
            },
            {
                group: stepGroup,
                target: '[data-tour="rapports-medecin.global"]',
                title: 'Vue d ensemble personnelle',
                content: 'Cette synthese resume votre volume d activite et vos principaux indicateurs cliniques.'
            }
        ]);
    }

    if (role === 'reception') {
        return normalizeTourSteps([
            {
                group: stepGroup,
                target: '[data-tour="rapports-reception.date"]',
                title: 'Choisir la journee',
                content: 'Le rapport reception est journalier. Changez de date pour revoir une autre journee d accueil.'
            },
            {
                group: stepGroup,
                target: '[data-tour="rapports-reception.daily"]',
                title: 'Lire les stats du jour',
                content: 'Cette carte resume l activite reception du jour selectionne.'
            }
        ]);
    }

    return normalizeTourSteps([
        {
            group: stepGroup,
            target: '[data-tour="rapports-admin.range"]',
            title: 'Choisir la periode',
            content: 'Selectionnez une plage de dates pour recalculer l ensemble des statistiques du cabinet.'
        },
        {
            group: stepGroup,
            target: '[data-tour="rapports-admin.global"]',
            title: 'Lire la synthese globale',
            content: 'Cette section resume l activite generale du cabinet sur la periode.'
        }
    ]);
}

function buildFinancialReportSteps(ctx) {
    const stepGroup = resolveStepGroup(ctx);

    return normalizeTourSteps([
        {
            group: stepGroup,
            target: '[data-tour="rapports-admin.range"]',
            title: 'Choisir la periode',
            content: 'Selectionnez une plage de dates pour recalculer l ensemble des statistiques du cabinet.'
        },
        {
            group: stepGroup,
            target: '[data-tour="rapports-admin.global"]',
            title: 'Lire la synthese globale',
            content: 'Cette section resume l activite generale du cabinet sur la periode.'
        },
        {
            group: stepGroup,
            target: '[data-tour="rapports-admin.non-periodic"]',
            title: 'Surveiller les fondamentaux',
            content: 'Les details non periodiques couvrent la repartition du personnel, les consommables critiques et les patients globaux.'
        },
        {
            group: stepGroup,
            target: '[data-tour="rapports-admin.periodic"]',
            title: 'Lire l activite sur la periode',
            content: 'Cette zone detaille patients, consultations, rendez-vous, usage des salles et equilibres de paiement.'
        },
        {
            group: stepGroup,
            target: '[data-tour="rapports-admin.acts"]',
            title: 'Analyser les actes',
            content: 'Cette section met en avant les actes realises et les volumes utiles a l analyse metier.'
        },
        {
            group: stepGroup,
            target: '[data-tour="rapports-admin.doctors"]',
            title: 'Comparer les medecins',
            content: 'Le tableau final consolide les performances par medecin sur la meme periode.'
        }
    ]);
}

function buildClinicalReportSteps(ctx) {
    const stepGroup = resolveStepGroup(ctx);

    return normalizeTourSteps([
        {
            group: stepGroup,
            target: '[data-tour="rapports-medecin.range"]',
            title: 'Choisir la periode',
            content: 'Le rapport se recalculera sur la plage choisie pour analyser votre activite recente.'
        },
        {
            group: stepGroup,
            target: '[data-tour="rapports-medecin.global"]',
            title: 'Vue d ensemble personnelle',
            content: 'Cette synthese resume votre volume d activite et vos principaux indicateurs cliniques.'
        },
        {
            group: stepGroup,
            target: '[data-tour="rapports-medecin.quick"]',
            title: 'Lire les indicateurs rapides',
            content: 'Les quick stats isolent les informations les plus utiles pour la pratique quotidienne.'
        },
        {
            group: stepGroup,
            target: '[data-tour="rapports-medecin.periodic"]',
            title: 'Detail sur la periode',
            content: 'Cette partie detaille consultations, paiements et autres resultats lies a la periode selectionnee.'
        },
        {
            group: stepGroup,
            target: '[data-tour="rapports-medecin.acts"]',
            title: 'Revoir les actes et paiements',
            content: 'Utilisez cette section pour relire les actes realises et les flux associes.'
        },
        {
            group: stepGroup,
            target: '[data-tour="rapports-medecin.profile"]',
            title: 'Profil professionnel',
            content: 'Cette derniere zone relie vos statistiques au profil du praticien connecte.'
        }
    ]);
}

function buildReceptionReportSteps(ctx) {
    const stepGroup = resolveStepGroup(ctx);

    return normalizeTourSteps([
        {
            group: stepGroup,
            target: '[data-tour="rapports-reception.date"]',
            title: 'Choisir la journee',
            content: 'Le rapport reception est journalier. Changez de date pour revoir une autre journee d accueil.'
        },
        {
            group: stepGroup,
            target: '[data-tour="rapports-reception.daily"]',
            title: 'Lire les stats du jour',
            content: 'Cette carte resume l activite reception du jour selectionne.'
        },
        {
            group: stepGroup,
            target: '[data-tour="rapports-reception.doctors"]',
            title: 'Voir les rapports par medecin',
            content: 'Le tableau par medecin permet de comparer rapidement l activite du jour entre praticiens.'
        }
    ]);
}

function buildExportReportSteps(ctx) {
    const stepGroup = resolveStepGroup(ctx);
    const role = ctx.role;

    if (role === 'reception') {
        return normalizeTourSteps([
            {
                group: stepGroup,
                target: '[data-tour="rapports-reception.print"]',
                title: 'Imprimer le recapitulatif',
                content: 'Utilisez l impression pour sortir un resume journalier reception.'
            }
        ]);
    }

    if (role === 'medecin') {
        return normalizeTourSteps([
            {
                group: stepGroup,
                target: '[data-tour="rapports-medecin.acts"]',
                title: 'Consulter les resultats',
                content: 'Verifiez les actes et paiements avant export ou impression depuis votre rapport personnel.'
            },
            {
                group: stepGroup,
                target: '[data-tour="rapports-medecin.profile"]',
                title: 'Profil et synthese',
                content: 'La section profil consolide les chiffres utiles pour un export ou une relecture rapide.'
            }
        ]);
    }

    return normalizeTourSteps([
        {
            group: stepGroup,
            target: '[data-tour="rapports-admin.doctors"]',
            title: 'Tableau comparatif',
            content: 'Le tableau par medecin peut servir de base a une impression ou un export manuel du rapport.'
        },
        {
            group: stepGroup,
            target: '[data-tour="rapports-admin.global"]',
            title: 'Synthese exportable',
            content: 'La synthese globale regroupe les indicateurs principaux du cabinet sur la periode choisie.'
        }
    ]);
}

export const rapportsRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'financial-report': buildFinancialReportSteps,
    'clinical-report': buildClinicalReportSteps,
    'reception-report': buildReceptionReportSteps,
    'export-report': buildExportReportSteps
});

export function buildRapportsTourSteps(taskId, variantId, ctx) {
    return rapportsRegistry.buildSteps(taskId, variantId, ctx);
}

export function createRapportsTour(ctx) {
    return buildRapportsTourSteps('overview', null, ctx);
}

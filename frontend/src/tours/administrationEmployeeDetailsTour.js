import { normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'administration-employee-details';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'edit-info', label: 'Modifier les informations', icon: 'pi pi-pencil', mockScenario: 'static' },
    { id: 'manage-documents', label: 'Gerer les documents', icon: 'pi pi-file', mockScenario: 'static' }
];

function buildOverviewSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="admin-employee-details.header"]',
            title: 'Fiche employe',
            content: 'Cette page detaille la fiche RH complete d un employe avec edition directe des informations principales.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-employee-details.personal"]',
            title: 'Informations personnelles',
            content: 'Identite, contacts et date d embauche forment la base RH fiable de l employe.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-employee-details.rh"]',
            title: 'Parametres RH',
            content: 'Cette section gere type de salaire, valeur, contrat et jours travailles.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-employee-details.documents"]',
            title: 'Documents administratifs',
            content: 'Ajoutez et telechargez les pieces administratives liees a l employe depuis ce bloc.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-employee-details.summary"]',
            title: 'Cartes de synthese',
            content: 'Les cartes de droite affichent un resume rapide du profil, du type et de la remuneration actuelle.'
        }
    ]);
}

export const administrationEmployeeDetailsRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'edit-info': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="admin-employee-details.personal"]',
                title: 'Informations personnelles',
                content: 'Modifiez identite, contacts et date d embauche directement dans ce bloc.'
            },
            {
                group: GROUP,
                target: '[data-tour="admin-employee-details.rh"]',
                title: 'Parametres RH',
                content: 'Ajustez type de salaire, valeur, contrat et jours travailles selon l evolution du poste.'
            },
            {
                group: GROUP,
                target: '[data-tour="admin-employee-details.conges"]',
                title: 'Suivi des conges',
                content: 'Visualisez l historique des conges avec total annuel pour completer le suivi RH.'
            }
        ]),
    'manage-documents': (ctx) =>
        normalizeTourSteps([
            {
                group: GROUP,
                target: '[data-tour="admin-employee-details.documents"]',
                title: 'Documents administratifs',
                content: 'Ce bloc centralise l ajout et le telechargement des pieces liees a l employe.'
            },
            {
                group: GROUP,
                target: '[data-tour="admin-employee-details.summary"]',
                title: 'Verifier le profil',
                content: 'Les cartes de synthese permettent de confirmer le contexte avant d archiver un document.'
            }
        ])
});

export function buildAdministrationEmployeeDetailsTourSteps(taskId, variantId, ctx) {
    return administrationEmployeeDetailsRegistry.buildSteps(taskId, variantId, ctx);
}

export function createAdministrationEmployeeDetailsTour(ctx = {}) {
    return buildAdministrationEmployeeDetailsTourSteps('overview', null, ctx);
}

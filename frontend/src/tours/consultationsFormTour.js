import { flushUi, openDialogStep, normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'consultations-form';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'fill-entretien', label: 'Remplir l entretien', icon: 'pi pi-comments', mockScenario: 'static' },
    { id: 'fill-examens', label: 'Saisir les examens', icon: 'pi pi-search', mockScenario: 'static' },
    { id: 'manage-ordonnance', label: 'Gerer l ordonnance', icon: 'pi pi-file', mockScenario: 'static' },
    { id: 'treatment-plan', label: 'Plan de traitement', icon: 'pi pi-list-check', mockScenario: 'static' },
    { id: 'close-fiche', label: 'Cloturer la fiche', icon: 'pi pi-check-circle', mockScenario: 'static' }
];

function buildOverviewSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="consultations-form.header"]',
            title: 'Fiche medicale patient',
            content: 'Cette vue centralise toute la prise en charge: evaluation clinique, examens, documents, devis, seances et cloture.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-form.navigation"]',
            title: 'Navigation rapide',
            content: 'Le bouton Retour vous ramene a la page precedente sans perdre le contexte.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-form.display-mode"]',
            title: 'Mode d affichage',
            content: 'Basculez entre vue onglets et vue sidebar selon votre confort de lecture.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-form.save-indicator"]',
            title: 'Suivi de sauvegarde',
            content: 'L indicateur affiche les sections modifiees, l etat de sauvegarde et permet une sauvegarde globale.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-form.switcher"]',
            title: 'Parcours section par section',
            content: 'Le switcher structure la fiche en blocs metier pour avancer dans le bon ordre clinique.'
        }
    ]);
}

export const consultationsFormRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'fill-entretien': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="consultations-form.switcher"]',
            title: 'Acceder a l entretien',
            content: 'Selectionnez la section entretien dans le switcher pour saisir le questionnaire medical.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-form.section.entretien"]',
            title: 'Questionnaire medical',
            content: 'Saisissez le motif, l histoire de la plainte et les informations contextuelles qui orientent les examens.',
            beforeEnter: async () => {
                ctx.setSection('entretien');
                await flushUi();
            }
        }
    ]),
    'fill-examens': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="consultations-form.switcher"]',
            title: 'Acceder aux examens',
            content: 'Basculez vers la section examens pour documenter les constatations cliniques.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-form.section.examens"]',
            title: 'Examens cliniques',
            content: 'Documentez les constatations, observations et resultats cliniques qui soutiennent le diagnostic.',
            beforeEnter: async () => {
                ctx.setSection('examens');
                await flushUi();
            }
        }
    ]),
    'manage-ordonnance': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="consultations-form.section.consult"]',
            title: 'Section consultation',
            content: 'La section consultation regroupe les actes de seance et les ordonnances.',
            beforeEnter: async () => {
                ctx.setSection('consult');
                await flushUi();
            }
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-form.dialogs"]',
            title: 'Dialogue ordonnance',
            content: 'La modale ordonnance permet de completer rapidement la prescription sans quitter la fiche.',
            beforeEnter: async () => {
                ctx.setSection('consult');
                ctx.openOrdonnanceDialog();
                await flushUi();
            },
            afterLeave: async () => {
                ctx.closeAllDialogs();
                await flushUi();
            }
        }
    ]),
    'treatment-plan': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="consultations-form.switcher"]',
            title: 'Plan de traitement',
            content: 'Accedez au bloc plan de traitement depuis le switcher de sections.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-form.section.plan-traitement"]',
            title: 'Definir la strategie',
            content: 'Definissez ici la strategie therapeutique, les etapes et les priorites pour la suite de la prise en charge.',
            beforeEnter: async () => {
                ctx.setSection('plan-traitement');
                await flushUi();
            }
        }
    ]),
    'close-fiche': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="consultations-form.save-indicator"]',
            title: 'Verifier la sauvegarde',
            content: 'Assurez-vous que toutes les sections modifiees sont bien enregistrees avant la cloture.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-form.section.consult"]',
            title: 'Finaliser la consultation',
            content: 'Finalisez les actes de seance, les intervenants et les ordonnances puis lancez la cloture.',
            beforeEnter: async () => {
                ctx.setSection('consult');
                await flushUi();
            }
        }
    ])
});

export function buildConsultationsFormTourSteps(taskId, variantId, ctx) {
    return consultationsFormRegistry.buildSteps(taskId, variantId, ctx);
}

export function createConsultationsFormTour(ctx) {
    return buildConsultationsFormTourSteps('overview', null, ctx);
}

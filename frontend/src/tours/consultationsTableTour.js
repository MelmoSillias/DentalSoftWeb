import { nextTick } from 'vue';
import { getTourGuideClient } from './tourGuideClient';

function wait(ms = 120) {
    return new Promise((resolve) => {
        window.setTimeout(resolve, ms);
    });
}

async function refreshTourLayout() {
    const tg = getTourGuideClient();

    if (!tg?.isVisible) {
        return;
    }

    await tg.updatePositions().catch(() => undefined);
}

async function flushUi() {
    await nextTick();
    await wait();
    await refreshTourLayout();
}

export function createConsultationsTableTour({
    hasConsultations,
    hasOpenConsultation,
    isMedecin,
    openCreateConsultationDialog,
    openQuickDialog,
    closeAllDialogs
}) {
    const steps = [
        {
            group: 'consultations-table',
            order: 10,
            target: '[data-tour="consultations-table.header"]',
            title: 'Historique des consultations',
            content: 'Cette vue liste les consultations de la date choisie avec leur statut et leurs actions metier.'
        },
        {
            group: 'consultations-table',
            order: 20,
            target: '[data-tour="consultations-table.filters"]',
            title: 'Filtres de la journee',
            content: 'Combinez la recherche et le filtre de date pour isoler rapidement un patient, un medecin ou une situation precise.'
        },
        {
            group: 'consultations-table',
            order: 30,
            target: '[data-tour="consultations-table.table"]',
            title: 'Tableau des consultations',
            content: 'Le tableau centralise patient, medecin, date de creation et statut pour une lecture rapide de l activite.'
        },
        {
            group: 'consultations-table',
            order: 40,
            target: '[data-tour="consultations-table.status"]',
            title: 'Lecture du statut',
            content: 'Les badges distinguent les consultations en cours et cloturees, avec indication urgente quand presente.'
        }
    ];

    if (hasConsultations) {
        steps.push({
            group: 'consultations-table',
            order: 50,
            target: '[data-tour="consultations-table.actions"]',
            title: 'Actions par ligne',
            content: 'Depuis une ligne, vous pouvez ouvrir le dossier, voir les details, gerer la facture, annuler ou lancer des actions rapides.'
        });
    }

    if (!isMedecin) {
        steps.push(
            {
                group: 'consultations-table',
                order: 60,
                target: '[data-tour="consultations-table.create-button"]',
                title: 'Nouvelle consultation',
                content: 'Les profils autorises peuvent ouvrir une nouvelle consultation directement depuis cette page.'
            },
            {
                group: 'consultations-table',
                order: 70,
                target: '[data-tour="consultations-table.dialog.create"]',
                title: 'Dialogue de creation',
                content: 'Ce formulaire sert a creer une consultation sans quitter l historique.',
                beforeEnter: async () => {
                    openCreateConsultationDialog();
                    await flushUi();
                },
                afterLeave: async () => {
                    closeAllDialogs();
                    await flushUi();
                }
            }
        );
    }

    if (hasOpenConsultation) {
        steps.push({
            group: 'consultations-table',
            order: 80,
            title: 'Dialogue metier rapide',
            content: 'Les actions rapides ouvrent un dialogue pour continuer ou creer une fiche selon le contexte.',
            beforeEnter: async () => {
                openQuickDialog();
                await flushUi();
            },
            afterLeave: async () => {
                closeAllDialogs();
                await flushUi();
            }
        });
    }

    return steps;
}
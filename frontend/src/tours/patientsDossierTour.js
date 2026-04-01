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

export function createPatientsDossierTour({
    hasPatientContext,
    isMedecin,
    openEditPatientDialog,
    closeAllDialogs
}) {
    if (!hasPatientContext) {
        return [
            {
                group: 'patients-dossier',
                order: 10,
                target: '[data-tour="patients-dossier.selector"]',
                title: 'Selection du patient',
                content: 'Choisissez un patient dans ce selecteur pour charger son dossier complet sans revenir a la liste.'
            },
            {
                group: 'patients-dossier',
                order: 20,
                target: '[data-tour="patients-dossier.medical"]',
                title: 'Chargement du dossier',
                content: 'Une fois le patient selectionne, les informations cliniques, rendez-vous, paiements et factures seront disponibles dans cette zone.'
            }
        ];
    }

    const steps = [
        {
            group: 'patients-dossier',
            order: 10,
            target: '[data-tour="patients-dossier.selector"]',
            title: 'Changer de patient',
            content: 'Ce selecteur permet d ouvrir un autre dossier patient directement depuis cette page.'
        },
        {
            group: 'patients-dossier',
            order: 20,
            target: '[data-tour="patients-dossier.info-card"]',
            title: 'Resume du patient',
            content: 'La carte de gauche centralise les informations personnelles, antecedents, allergies et contact d urgence.'
        },
        {
            group: 'patients-dossier',
            order: 30,
            target: '[data-tour="patients-dossier.actions"]',
            title: 'Actions du dossier',
            content: 'Les actions rapides permettent d imprimer, modifier le patient et lancer un rendez-vous depuis le dossier.'
        },
        {
            group: 'patients-dossier',
            order: 40,
            target: '[data-tour="patients-dossier.medical"]',
            title: 'Suivi clinique',
            content: 'Cette zone affiche les fiches medicales ou la liste des consultations selon votre role.'
        },
        {
            group: 'patients-dossier',
            order: 50,
            target: '[data-tour="patients-dossier.finance"]',
            title: 'Rendez-vous, paiements et factures',
            content: 'Cette section relie l historique des rendez-vous et les donnees financieres du patient.'
        }
    ];

    if (!isMedecin) {
        steps.push({
            group: 'patients-dossier',
            order: 60,
            target: '[data-tour="patients-dossier.dialogs"]',
            title: 'Dialogues metier',
            content: 'Les dialogues du dossier servent a modifier le patient, ajouter des informations et completer le suivi sans quitter la page.',
            beforeEnter: async () => {
                openEditPatientDialog();
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
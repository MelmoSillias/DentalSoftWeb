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

export function createAgendaRendezvousTour({ isMedecin, openCreateDialog, closeAllDialogs }) {
    const steps = [
        {
            group: 'agenda-rendezvous',
            order: 10,
            target: '[data-tour="agenda-rdv.header"]',
            title: 'Gestion des rendez-vous',
            content: 'Cette page sert a planifier et suivre les rendez-vous du cabinet sur une vue semaine ou jour.'
        },
        {
            group: 'agenda-rendezvous',
            order: 20,
            target: '[data-tour="agenda-rdv.legend"]',
            title: 'Comprendre les statuts',
            content: 'La legende rappelle les couleurs utilisees pour distinguer les rendez-vous en attente, valides, reportes ou annules.'
        },
        {
            group: 'agenda-rendezvous',
            order: 30,
            target: '[data-tour="agenda-rdv.tabs"]',
            title: 'Changer de vue',
            content: 'Basculez entre la vue hebdomadaire et la vue journaliere selon le niveau de detail souhaite.'
        },
        {
            group: 'agenda-rendezvous',
            order: 40,
            target: '[data-tour="agenda-rdv.calendar"]',
            title: 'Interagir avec le planning',
            content: 'Cliquez sur un creneau libre pour creer un rendez-vous, ou sur un rendez-vous existant pour lancer les actions metier.'
        },
        {
            group: 'agenda-rendezvous',
            order: 50,
            target: '[data-tour="agenda-rdv.dialogs"]',
            title: 'Actions rapides',
            content: 'Les dialogues associes permettent de creer, valider, reporter, annuler et programmer des rappels SMS.',
            beforeEnter: async () => {
                openCreateDialog();
                await flushUi();
            },
            afterLeave: async () => {
                closeAllDialogs();
                await flushUi();
            }
        }
    ];

    if (isMedecin) {
        steps.push({
            group: 'agenda-rendezvous',
            order: 60,
            target: '[data-tour="agenda-rdv.scope"]',
            title: 'Portee medecin',
            content: 'Si vous etes medecin, la vue est verrouillee sur votre agenda et le choix du praticien est restreint.'
        });
    }

    return steps;
}
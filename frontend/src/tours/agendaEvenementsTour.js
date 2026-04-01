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

export function createAgendaEvenementsTour({ hasEvents, openActionsDialog, closeAllDialogs }) {
    const steps = [
        {
            group: 'agenda-evenements',
            order: 10,
            target: '[data-tour="agenda-events.header"]',
            title: 'Agenda des evenements',
            content: 'Cette page gere les evenements generaux du cabinet en dehors des rendez-vous patients.'
        },
        {
            group: 'agenda-evenements',
            order: 20,
            target: '[data-tour="agenda-events.create"]',
            title: 'Ajouter un evenement',
            content: 'Ce bouton ouvre le formulaire pour declarer un nouvel evenement dans le calendrier.'
        },
        {
            group: 'agenda-evenements',
            order: 30,
            target: '[data-tour="agenda-events.calendar"]',
            title: 'Naviguer dans le calendrier',
            content: 'Le calendrier permet de changer de mois, de passer en vue semaine et de visualiser les evenements a venir.'
        },
        {
            group: 'agenda-evenements',
            order: 40,
            target: '[data-tour="agenda-events.status"]',
            title: 'Lire l etat des evenements',
            content: 'Les couleurs du calendrier distinguent les evenements valides des evenements encore en attente.'
        }
    ];

    if (hasEvents) {
        steps.push({
            group: 'agenda-evenements',
            order: 50,
            target: '[data-tour="agenda-events.actions"]',
            title: 'Ouvrir les actions contextuelles',
            content: 'Un clic droit sur un evenement ouvre les actions de validation ou suppression.',
            beforeEnter: async () => {
                openActionsDialog();
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
import { nextTick } from 'vue';
import { getTourGuideClient } from './tourGuideClient';

function wait(ms = 120) {
    return new Promise((resolve) => {
        window.setTimeout(resolve, ms);
    });
}

async function refreshTourLayout() {
    const tg = getTourGuideClient();

    if (!tg?.isVisible) return;
    await tg.updatePositions().catch(() => undefined);
}

async function flushUi() {
    await nextTick();
    await wait();
    await refreshTourLayout();
}

export function createAdministrationSallesTour({ openAddDialog, closeAllDialogs }) {
    return [
        {
            group: 'administration-salles',
            order: 10,
            target: '[data-tour="admin-salles.header"]',
            title: 'Gestion des salles',
            content: 'Cette page gere les espaces de consultation et de traitement du cabinet.'
        },
        {
            group: 'administration-salles',
            order: 20,
            target: '[data-tour="admin-salles.table"]',
            title: 'Consulter les salles',
            content: 'Le tableau centralise le nom, le type, le statut et les actions de chaque salle.'
        },
        {
            group: 'administration-salles',
            order: 30,
            target: '[data-tour="admin-salles.actions"]',
            title: 'Modifier ou supprimer',
            content: 'Depuis une ligne, vous pouvez editer les informations de la salle ou la supprimer.'
        },
        {
            group: 'administration-salles',
            order: 40,
            target: '[data-tour="admin-salles.stats"]',
            title: 'Lire l occupation',
            content: 'Les cartes de synthese montrent combien de salles sont disponibles, occupees et reparties par type.'
        },
        {
            group: 'administration-salles',
            order: 50,
            target: '[data-tour="admin-salles.dialogs"]',
            title: 'Ajouter une salle',
            content: 'Les dialogues servent a creer ou modifier une salle sans quitter la liste.',
            beforeEnter: async () => {
                openAddDialog();
                await flushUi();
            },
            afterLeave: async () => {
                closeAllDialogs();
                await flushUi();
            }
        }
    ];
}

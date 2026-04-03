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

async function openDialogStep(openDialog, closeAllDialogs) {
    closeAllDialogs();
    await flushUi();
    await openDialog();
    await flushUi();
}

export function createAdministrationConsumablesTour({ setMode, openCreateDialog, openStockDialog, openDetailsDialog, closeAllDialogs }) {
    return [
        {
            group: 'administration-consommables',
            order: 10,
            target: '[data-tour="admin-consumables.header"]',
            title: 'Gestion du stock',
            content: 'Cette page sert a suivre les consommables, leur disponibilite et leurs mouvements.'
        },
        {
            group: 'administration-consommables',
            order: 20,
            target: '[data-tour="admin-consumables.mode"]',
            title: 'Changer de mode d affichage',
            content: 'Basculez entre la liste des consommables et l historique des variations de stock.'
        },
        {
            group: 'administration-consommables',
            order: 30,
            target: '[data-tour="admin-consumables.stats"]',
            title: 'Prioriser les alertes',
            content: 'Les cartes mettent en avant le total, le stock suffisant, le stock faible et les ruptures.'
        },
        {
            group: 'administration-consommables',
            order: 40,
            target: '[data-tour="admin-consumables.list"]',
            title: 'Gerer les articles',
            content: 'La liste permet de consulter, modifier, supprimer et ouvrir les actions d ajout ou retrait de stock.',
            beforeEnter: async () => {
                setMode('list');
                await flushUi();
            }
        },
        {
            group: 'administration-consommables',
            order: 50,
            target: '[data-tour="admin-consumables.variations"]',
            title: 'Auditer les mouvements',
            content: 'En mode Variations, suivez les entrees et sorties de stock sur une periode donnee.',
            beforeEnter: async () => {
                setMode('vars');
                await flushUi();
            }
        },
        {
            group: 'administration-consommables',
            order: 60,
            target: '[data-tour="admin-consumables.dialog.create"]',
            title: 'Ajouter un consommable',
            content: 'Les dialogues permettent de creer ou modifier un consommable sans quitter la page.',
            beforeEnter: async () => {
                setMode('list');
                await openDialogStep(openCreateDialog, closeAllDialogs);
            },
        },
        {
            group: 'administration-consommables',
            order: 70,
            target: '[data-tour="admin-consumables.dialog.stock"]',
            title: 'Ajouter ou retirer du stock',
            content: 'Le dialogue de mouvement permet d enregistrer une entree ou une sortie de stock avec quantite, employe et description.',
            beforeEnter: async () => {
                setMode('list');
                await openDialogStep(() => openStockDialog('withdraw'), closeAllDialogs);
            }
        },
        {
            group: 'administration-consommables',
            order: 80,
            target: '[data-tour="admin-consumables.dialog.details"]',
            title: 'Consulter le detail d un article',
            content: 'Le dialogue de details sert a verifier rapidement le fournisseur, le stock courant et le seuil bas d un consommable.',
            beforeEnter: async () => {
                setMode('list');
                await openDialogStep(openDetailsDialog, closeAllDialogs);
            }
        }
    ];
}

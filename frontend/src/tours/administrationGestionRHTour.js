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

export function createAdministrationGestionRHTour({ hasEmployees, openCreateDialog, openEditDialog, closeAllDialogs }) {
    const steps = [
        {
            group: 'administration-gestionrh',
            order: 10,
            target: '[data-tour="admin-rh.header"]',
            title: 'Pilotage RH',
            content: 'Cette vue regroupe les employes du cabinet avec recherche, edition et suivi operationnel.'
        },
        {
            group: 'administration-gestionrh',
            order: 20,
            target: '[data-tour="admin-rh.filters"]',
            title: 'Filtres de recherche',
            content: 'Combinez texte et type d employe pour isoler rapidement les profils a administrer.'
        },
        {
            group: 'administration-gestionrh',
            order: 30,
            target: '[data-tour="admin-rh.table"]',
            title: 'Table employes',
            content: 'La table affiche les informations RH utiles et permet tri, pagination et regroupement par type.'
        },
        {
            group: 'administration-gestionrh',
            order: 40,
            target: '[data-tour="admin-rh.actions"]',
            title: 'Actions par ligne',
            content: 'Depuis chaque employe vous pouvez ouvrir les details, modifier la fiche ou supprimer le profil.'
        },
        {
            group: 'administration-gestionrh',
            order: 50,
            target: '[data-tour="admin-rh.stats"]',
            title: 'Indicateurs rapides',
            content: 'Les cartes de synthese donnent une lecture immediate de la volumetrie RH.'
        },
        {
            group: 'administration-gestionrh',
            order: 60,
            target: '[data-tour="admin-rh.dialogs"]',
            title: 'Ajouter un employe',
            content: 'Le formulaire RH sert a creer un nouveau collaborateur sans quitter la liste.',
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

    if (hasEmployees) {
        steps.push({
            group: 'administration-gestionrh',
            order: 70,
            target: '[data-tour="admin-rh.dialogs"]',
            title: 'Modifier un employe',
            content: 'La meme modale permet de corriger les informations RH existantes.',
            beforeEnter: async () => {
                openEditDialog();
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

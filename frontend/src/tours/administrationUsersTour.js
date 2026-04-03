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

export function createAdministrationUsersTour({ hasUsers, openCreateDialog, openResetDialog, closeAllDialogs }) {
    const steps = [
        {
            group: 'administration-utilisateurs',
            order: 10,
            target: '[data-tour="admin-users.header"]',
            title: 'Gestion des comptes',
            content: 'Cette page centralise la creation, la modification et la securisation des utilisateurs applicatifs.'
        },
        {
            group: 'administration-utilisateurs',
            order: 20,
            target: '[data-tour="admin-users.grouping"]',
            title: 'Regrouper par type',
            content: 'Activez le regroupement pour analyser rapidement les comptes par profil metier.'
        },
        {
            group: 'administration-utilisateurs',
            order: 30,
            target: '[data-tour="admin-users.search"]',
            title: 'Recherche globale',
            content: 'Le filtre recherche interroge simultanement nom utilisateur, employe lie et type de compte.'
        },
        {
            group: 'administration-utilisateurs',
            order: 40,
            target: '[data-tour="admin-users.table"]',
            title: 'Table des utilisateurs',
            content: 'La table affiche les comptes existants et permet un tri multi-colonnes pour les controles administratifs.'
        },
        {
            group: 'administration-utilisateurs',
            order: 50,
            target: '[data-tour="admin-users.actions"]',
            title: 'Actions critiques',
            content: 'Chaque ligne propose edition, reinitialisation mot de passe et suppression selon les besoins.'
        },
        {
            group: 'administration-utilisateurs',
            order: 60,
            target: '[data-tour="admin-users.dialog.create"]',
            title: 'Creer un utilisateur',
            content: 'Le formulaire permet de creer un compte et de le rattacher a un employe existant.',
            beforeEnter: async () => {
                await openDialogStep(openCreateDialog, closeAllDialogs);
            }
        }
    ];

    if (hasUsers) {
        steps.push({
            group: 'administration-utilisateurs',
            order: 70,
            target: '[data-tour="admin-users.dialog.reset"]',
            title: 'Reinitialiser un mot de passe',
            content: 'Utilisez cette modale pour appliquer un nouveau mot de passe temporaire a un utilisateur.',
            beforeEnter: async () => {
                await openDialogStep(openResetDialog, closeAllDialogs);
            }
        });
    }

    return steps;
}

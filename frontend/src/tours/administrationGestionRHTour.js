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

export function createAdministrationGestionRHTour({ hasEmployees, openCreateDialog, openEditDialog, expandGroups, closeAllDialogs }) {
    const steps = [
        {
            group: 'administration-gestionrh',
            order: 10,
            target: '[data-tour="admin-rh.header"]',
            title: 'Pilotage RH',
            content: 'Cette page centralise la gestion RH du cabinet. Vous y retrouvez la liste des employes, l acces au formulaire de creation et les actions d administration courantes sur chaque fiche.'
        },
        {
            group: 'administration-gestionrh',
            order: 20,
            target: '[data-tour="admin-rh.filters"]',
            title: 'Filtres de recherche',
            content: 'La zone de filtres combine une recherche texte et un filtre par type. Elle sert a retrouver rapidement un medecin, un receptionniste, un administrateur ou tout autre profil RH sans parcourir toute la table.'
        },
        {
            group: 'administration-gestionrh',
            order: 30,
            target: '[data-tour="admin-rh.table"]',
            title: 'Table employes',
            content: 'La table affiche les informations RH utiles pour chaque employe: identite, fonction, telephone et date d embauche. Pendant ce tour, les groupes par type sont volontairement deroules pour rendre la lecture plus claire.',
            beforeEnter: async () => {
                await expandGroups();
            }
        },
        {
            group: 'administration-gestionrh',
            order: 40,
            target: '[data-tour="admin-rh.actions"]',
            title: 'Actions par ligne',
            content: 'Chaque ligne donne acces aux actions metier principales: ouvrir la fiche detaillee dans un nouvel onglet, modifier les informations RH directement depuis la liste, ou supprimer un profil si necessaire.',
            beforeEnter: async () => {
                await expandGroups();
            }
        },
        {
            group: 'administration-gestionrh',
            order: 50,
            target: '[data-tour="admin-rh.stats"]',
            title: 'Indicateurs rapides',
            content: 'Les cartes de synthese fournissent une lecture rapide de la volumetrie RH. Elles servent surtout de repere global avant d entrer dans le detail d une fiche employe.'
        },
        {
            group: 'administration-gestionrh',
            order: 60,
            target: '[data-tour="admin-rh.dialog.form"]',
            title: 'Ajouter un employe',
            content: 'Le formulaire RH permet de creer un collaborateur sans quitter la liste. Il regroupe les donnees personnelles, les informations contractuelles, la configuration du salaire et les documents administratifs.',
            beforeEnter: async () => {
                await expandGroups();
                await openDialogStep(openCreateDialog, closeAllDialogs);
            }
        }
    ];

    if (hasEmployees) {
        steps.push({
            group: 'administration-gestionrh',
            order: 70,
            target: '[data-tour="admin-rh.dialog.form"]',
            title: 'Modifier un employe',
            content: 'La meme modale sert ensuite a mettre a jour une fiche existante. C est le point d entree principal pour corriger un contrat, ajuster un salaire ou completer les informations d un employe deja cree.',
            beforeEnter: async () => {
                await expandGroups();
                await openDialogStep(openEditDialog, closeAllDialogs);
            }
        });
    }

    return steps;
}

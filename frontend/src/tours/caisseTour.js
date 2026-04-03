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

export function resolveCaisseTourGroup(activeView) {
    return 'caisse';
}

export function createCaisseTour({
    canOpenPaymentDialog,
    canOpenPreviewDialog,
    canOpenModifyDialog,
    openPaymentDialog,
    openPreviewDialog,
    openModifyDialog,
    switchView,
    closeAllDialogs
}) {
    const steps = [
        {
            group: 'caisse',
            order: 10,
            target: '[data-tour="caisse.tabs"]',
            title: 'Choisir la sous-vue',
            content: 'Les onglets separent la vue d ensemble, les factures et les paiements selon le besoin du moment.'
        },
        {
            group: 'caisse',
            order: 20,
            target: '[data-tour="caisse-overview.stats"]',
            title: 'Lire les chiffres du jour',
            content: 'Les cartes de synthese donnent le volume visible de factures, le restant du et la recette sur la periode.'
        },
        {
            group: 'caisse',
            order: 30,
            target: '[data-tour="caisse-overview.factures"]',
            title: 'Gerer les factures impayees',
            content: 'Ce bloc permet de filtrer les factures, de les regler, de les modifier ou de les previsualiser.'
        },
        {
            group: 'caisse',
            order: 40,
            target: '[data-tour="caisse-overview.payments"]',
            title: 'Suivre les encaissements',
            content: 'La seconde zone resume les paiements deja enregistres et permet d imprimer ou d envoyer les recus.'
        }
    ];

    if (canOpenPaymentDialog) {
        steps.push({
            group: 'caisse',
            order: 50,
            target: '[data-tour="caisse-overview.payment-dialog"]',
            title: 'Enregistrer un paiement',
            content: 'La modale de reglement gere le montant patient, le mode de paiement, les assurances et le reste a payer.',
            beforeEnter: async () => {
                await openPaymentDialog();
                await flushUi();
            },
        });
    }

    steps.push(
        {
            group: 'caisse',
            order: 60,
            target: '[data-tour="caisse.tabs"]',
            title: 'Basculer vers les factures',
            content: 'Le tour passe maintenant sur la vue Factures pour montrer les cas de facture impayee, partielle, vide et reglee.',
            beforeEnter: async () => {
                await closeAllDialogs();
                await switchView('factures');
                await flushUi();
            }
        },
        {
            group: 'caisse',
            order: 70,
            target: '[data-tour="caisse-factures.filters"]',
            title: 'Filtrer les factures',
            content: 'Recherchez, changez la periode et limitez l affichage aux factures impayees si besoin.'
        },
        {
            group: 'caisse',
            order: 80,
            target: '[data-tour="caisse-factures.cards"]',
            title: 'Lire les cartes facture',
            content: 'Chaque carte montre le patient, le montant, le reste et le statut de paiement pour couvrir plusieurs cas metier dans la meme vue.'
        },
        {
            group: 'caisse',
            order: 90,
            target: '[data-tour="caisse-factures.actions"]',
            title: 'Agir sur une facture',
            content: 'Depuis une carte, vous pouvez regler, valider une facture vide, modifier, previsualiser ou envoyer la facture par SMS.'
        }
    );

    if (canOpenPreviewDialog) {
        steps.push({
            group: 'caisse',
            order: 100,
            target: '[data-tour="caisse-factures.preview"]',
            title: 'Verifier avant impression',
            content: 'L apercu detaille la facture et permet une verification avant impression ou envoi.',
            beforeEnter: async () => {
                await openPreviewDialog();
                await flushUi();
            }
        });
    }

    if (canOpenModifyDialog) {
        steps.push({
            group: 'caisse',
            order: 110,
            target: '[data-tour="caisse-factures.modify"]',
            title: 'Corriger les lignes facture',
            content: 'La modale de modification sert a ajuster les soins, quantites et montants avant validation.',
            beforeEnter: async () => {
                await openModifyDialog();
                await flushUi();
            }
        });
    }

    steps.push(
        {
            group: 'caisse',
            order: 120,
            target: '[data-tour="caisse.tabs"]',
            title: 'Basculer vers les paiements',
            content: 'Le tour termine sur la vue Paiements pour montrer le controle des encaissements et les actions disponibles sur chaque reglement.',
            beforeEnter: async () => {
                await closeAllDialogs();
                await switchView('paiements');
                await flushUi();
            }
        },
        {
            group: 'caisse',
            order: 130,
            target: '[data-tour="caisse-paiements.filters"]',
            title: 'Filtrer la periode',
            content: 'Choisissez la plage de dates et la recherche libre pour limiter les paiements affiches.'
        },
        {
            group: 'caisse',
            order: 140,
            target: '[data-tour="caisse-paiements.totals"]',
            title: 'Lire les totaux',
            content: 'Cette synthese donne le nombre de paiements visibles et le montant total encaisse sur la periode.'
        },
        {
            group: 'caisse',
            order: 150,
            target: '[data-tour="caisse-paiements.accordion"]',
            title: 'Explorer par mode de paiement',
            content: 'Les paiements sont regroupes par mode pour faciliter le controle de caisse et les rapprochements.'
        },
        {
            group: 'caisse',
            order: 160,
            target: '[data-tour="caisse-paiements.row-actions"]',
            title: 'Imprimer et envoyer',
            content: 'Chaque ligne permet d imprimer un paiement ou un ticket et d envoyer le recu par SMS.'
        }
    );

    return steps;
}
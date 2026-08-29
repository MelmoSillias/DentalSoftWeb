import { flushUi, openDialogStep, normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'caisse';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    {
        id: 'register-payment',
        label: 'Enregistrer un paiement',
        icon: 'pi pi-wallet',
        mockScenario: 'static',
        variants: [
            { id: 'classic', label: 'Paiement classique', mockScenario: 'static' },
            { id: 'insurance-active', label: 'Assurance active', mockScenario: 'static' },
            { id: 'insurance-disabled', label: 'Assurance desactivee', mockScenario: 'static' }
        ]
    },
    { id: 'validate-empty-invoice', label: 'Valider une facture vide', icon: 'pi pi-check-circle', mockScenario: 'static' },
    { id: 'manage-factures', label: 'Gerer les factures', icon: 'pi pi-file', mockScenario: 'static' },
    { id: 'preview-facture', label: 'Previsualiser une facture', icon: 'pi pi-eye', mockScenario: 'static' },
    { id: 'modify-facture', label: 'Modifier une facture', icon: 'pi pi-pencil', mockScenario: 'static' },
    { id: 'track-payments', label: 'Suivre les paiements', icon: 'pi pi-money-bill', mockScenario: 'static' },
    { id: 'manage-assurances', label: 'Gerer les assurances', icon: 'pi pi-shield', mockScenario: 'static' }
];

export function resolveCaisseTourGroup(activeView) {
    return GROUP;
}

async function switchViewStep(ctx, view) {
    await ctx.closeAllDialogs?.();
    await ctx.switchView?.(view);
    await flushUi();
}

function buildOverviewSteps(ctx) {
    return normalizeTourSteps([
        { group: GROUP, target: '[data-tour="caisse.tabs"]', title: 'Choisir la sous-vue', content: 'Les onglets separent la vue d ensemble, les factures, les paiements et les assurances.' },
        { group: GROUP, target: '[data-tour="caisse-overview.stats"]', title: 'Lire les chiffres du jour', content: 'Les cartes de synthese donnent le volume visible de factures, le restant du et les encaissements sur la periode.' },
        { group: GROUP, target: '[data-tour="caisse-overview.factures"]', title: 'Gerer les factures impayees', content: 'Ce bloc permet de filtrer les factures, de les regler, de les modifier ou de les previsualiser.' },
        { group: GROUP, target: '[data-tour="caisse-overview.payments"]', title: 'Suivre les encaissements', content: 'La seconde zone resume les paiements deja enregistres et permet d imprimer ou d envoyer les recus.' }
    ]);
}

function buildRegisterPaymentSteps(ctx, variantId) {
    const insuranceActive = variantId === 'insurance-active';
    const insuranceDisabled = variantId === 'insurance-disabled';

    const steps = [
        {
            group: GROUP,
            target: '[data-tour="caisse-overview.factures"], [data-tour="caisse-factures.actions"]',
            title: 'Selectionner une facture',
            content: 'Choisissez une facture impayee ou partiellement reglee pour ouvrir le reglement.',
            beforeEnter: async () => switchViewStep(ctx, 'overview')
        }
    ];

    if (ctx.canOpenPaymentDialog !== false) {
        steps.push({
            group: GROUP,
            target: '[data-tour="caisse-overview.payment-dialog"]',
            title: insuranceActive
                ? 'Regler avec assurance'
                : insuranceDisabled
                  ? 'Reglement sans assurance'
                  : 'Enregistrer un paiement',
            content: insuranceActive
                ? 'La modale affiche le detail assurance (taux, part couverte) et permet de regler uniquement la part patient restante.'
                : insuranceDisabled
                  ? 'Pour une facture classique, seul le reglement patient est propose : montant, mode de paiement et reste a payer.'
                  : 'La modale de reglement gere le montant patient, le mode de paiement et le reste a payer.',
            beforeEnter: async () => openDialogStep(
                () => ctx.openPaymentDialog?.(variantId),
                ctx.closeAllDialogs
            )
        });
    }

    return normalizeTourSteps(steps);
}

function buildValidateEmptyInvoiceSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="caisse.tabs"]',
            title: 'Basculer vers les factures',
            content: 'Les factures vides non validees sont visibles dans la vue Factures ou depuis la vue d ensemble.',
            beforeEnter: async () => switchViewStep(ctx, 'factures')
        },
        {
            group: GROUP,
            target: '[data-tour="caisse-factures.cards"]',
            title: 'Reperer une facture vide',
            content: 'Une facture a zero franc avec le statut vide non valide doit etre confirmee avant archivage.'
        },
        {
            group: GROUP,
            target: '[data-tour="caisse-factures.actions"]',
            title: 'Lancer la validation',
            content: 'Utilisez l action de validation sur la carte concernee.'
        },
        {
            group: GROUP,
            target: '[data-tour="caisse-factures.validate"]',
            title: 'Confirmer la facture vide',
            content: 'Le dialogue de validation confirme qu aucun reglement n est attendu pour cette facture.',
            beforeEnter: async () => openDialogStep(
                () => ctx.openValidateDialog?.(),
                ctx.closeAllDialogs
            )
        }
    ]);
}

function buildManageFacturesSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="caisse.tabs"]',
            title: 'Vue Factures',
            content: 'Cette vue concentre le suivi des factures impayees, partielles, vides et reglees.',
            beforeEnter: async () => switchViewStep(ctx, 'factures')
        },
        {
            group: GROUP,
            target: '[data-tour="caisse-factures.filters"]',
            title: 'Filtrer les factures',
            content: 'Recherchez, changez la periode et limitez l affichage aux factures impayees si besoin.'
        },
        {
            group: GROUP,
            target: '[data-tour="caisse-factures.cards"]',
            title: 'Lire les cartes facture',
            content: 'Chaque carte montre le patient, le montant, le reste et le statut de paiement.'
        },
        {
            group: GROUP,
            target: '[data-tour="caisse-factures.actions"]',
            title: 'Agir sur une facture',
            content: 'Depuis une carte, vous pouvez regler, valider une facture vide, modifier, previsualiser ou envoyer la facture par SMS.'
        }
    ]);
}

function buildPreviewFactureSteps(ctx) {
    const steps = [
        {
            group: GROUP,
            target: '[data-tour="caisse-factures.actions"]',
            title: 'Ouvrir l apercu',
            content: 'L action de previsualisation permet de verifier le detail avant impression ou envoi.',
            beforeEnter: async () => switchViewStep(ctx, 'factures')
        }
    ];

    if (ctx.canOpenPreviewDialog !== false) {
        steps.push({
            group: GROUP,
            target: '[data-tour="caisse-factures.preview"]',
            title: 'Verifier avant impression',
            content: 'L apercu detaille la facture, les lignes de soins et l historique des paiements.',
            beforeEnter: async () => openDialogStep(
                () => ctx.openPreviewDialog?.(),
                ctx.closeAllDialogs
            )
        });
    }

    return normalizeTourSteps(steps);
}

function buildModifyFactureSteps(ctx) {
    const steps = [
        {
            group: GROUP,
            target: '[data-tour="caisse-factures.actions"]',
            title: 'Modifier une facture',
            content: 'Seules les factures sans paiement et encore modifiables exposent l action de correction.',
            beforeEnter: async () => switchViewStep(ctx, 'factures')
        }
    ];

    if (ctx.canOpenModifyDialog !== false) {
        steps.push({
            group: GROUP,
            target: '[data-tour="caisse-factures.modify"]',
            title: 'Corriger les lignes facture',
            content: 'La modale de modification sert a ajuster les soins, quantites et montants avant validation.',
            beforeEnter: async () => openDialogStep(
                () => ctx.openModifyDialog?.(),
                ctx.closeAllDialogs
            )
        });
    }

    return normalizeTourSteps(steps);
}

function buildTrackPaymentsSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="caisse.tabs"]',
            title: 'Basculer vers les paiements',
            content: 'La vue Paiements regroupe tous les encaissements de la periode.',
            beforeEnter: async () => switchViewStep(ctx, 'paiements')
        },
        {
            group: GROUP,
            target: '[data-tour="caisse-paiements.filters"]',
            title: 'Filtrer la periode',
            content: 'Choisissez la plage de dates et la recherche libre pour limiter les paiements affiches.'
        },
        {
            group: GROUP,
            target: '[data-tour="caisse-paiements.totals"]',
            title: 'Lire les totaux',
            content: 'Cette synthese donne le nombre de paiements visibles et le montant total encaisse sur la periode.'
        },
        {
            group: GROUP,
            target: '[data-tour="caisse-paiements.accordion"]',
            title: 'Explorer par mode de paiement',
            content: 'Les paiements sont regroupes par mode pour faciliter le controle de caisse et les rapprochements.'
        },
        {
            group: GROUP,
            target: '[data-tour="caisse-paiements.row-actions"]',
            title: 'Imprimer et envoyer',
            content: 'Chaque ligne permet d imprimer un paiement ou un ticket et d envoyer le recu par SMS.'
        }
    ]);
}

function buildManageAssurancesSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="caisse.tabs"]',
            title: 'Onglet Assurances',
            content: 'Cette vue suit les dossiers assurance, les lots et les remboursements par organisme.',
            beforeEnter: async () => switchViewStep(ctx, 'assurances')
        },
        {
            group: GROUP,
            target: '[data-tour="caisse-assurances.dashboard"]',
            title: 'Tableau de bord assureurs',
            content: 'Chaque carte resume les factures sans lot, ouvertes, envoyees, confirmees et remboursees par assurance.'
        },
        {
            group: GROUP,
            target: '[data-tour="caisse-assurances.lots"]',
            title: 'Gerer les lots',
            content: 'Depuis un assureur, ouvrez ses lots pour creer, envoyer, confirmer ou rembourser les prises en charge.',
            beforeEnter: async () => {
                await ctx.openAssuranceLots?.();
                await flushUi();
            },
            afterLeave: async () => {
                await ctx.closeAssuranceLots?.();
                await flushUi();
            }
        }
    ]);
}

export const caisseRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'register-payment': buildRegisterPaymentSteps,
    'validate-empty-invoice': buildValidateEmptyInvoiceSteps,
    'manage-factures': buildManageFacturesSteps,
    'preview-facture': buildPreviewFactureSteps,
    'modify-facture': buildModifyFactureSteps,
    'track-payments': buildTrackPaymentsSteps,
    'manage-assurances': buildManageAssurancesSteps
});

export function buildCaisseTourSteps(taskId, variantId, ctx) {
    return caisseRegistry.buildSteps(taskId, variantId, ctx);
}

export function createCaisseTour(ctx) {
    return buildCaisseTourSteps('overview', null, ctx);
}

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

async function openDialogStep(openDialog, closeAllDialogs) {
    closeAllDialogs();
    await flushUi();
    await openDialog();
    await flushUi();
}

export function createConsultationsTableTour({
    hasConsultations,
    hasOpenConsultation,
    hasRepriseCase,
    hasLinkedCase,
    hasFreshCase,
    hasClosedCase,
    hasUrgentCase,
    isAdmin,
    isMedecin,
    openCreateConsultationDialog,
    openQuickDialog,
    openDetailsDialog,
    openFactureDialog,
    closeAllDialogs
}) {
    const steps = [
        {
            group: 'consultations-table',
            order: 10,
            target: '[data-tour="consultations-table.header"]',
            title: 'Historique des consultations',
            content: 'Cette vue liste les consultations de la date choisie avec leur statut et leurs actions metier.'
        },
        {
            group: 'consultations-table',
            order: 20,
            target: '[data-tour="consultations-table.stats"]',
            title: 'Synthese du jour',
            content: 'Les cartes du bas resument le volume total, les consultations cloturees et celles encore en cours pour la date affichee.'
        },
        {
            group: 'consultations-table',
            order: 30,
            target: '[data-tour="consultations-table.filters"]',
            title: 'Filtres de la journee',
            content: 'Combinez la recherche et le filtre de date pour isoler rapidement un patient, un medecin ou une situation precise.'
        },
        {
            group: 'consultations-table',
            order: 40,
            target: '[data-tour="consultations-table.table"]',
            title: 'Tableau des consultations',
            content: 'Le tableau centralise patient, medecin, date de creation et statut pour une lecture rapide de l activite.'
        }
    ];

    if (hasRepriseCase) {
        steps.push({
            group: 'consultations-table',
            order: 50,
            target: '[data-tour="consultations-table.case-last-fiche"]',
            title: 'Cas reprise de derniere fiche',
            content: 'Cette ligne illustre une consultation encore ouverte mais non reliee a une fiche active, avec une derniere fiche deja existante a reprendre.'
        });
    }

    if (hasLinkedCase) {
        steps.push({
            group: 'consultations-table',
            order: 60,
            target: '[data-tour="consultations-table.case-linked"]',
            title: 'Cas fiche deja liee',
            content: 'Ici, la consultation est deja rattachee a une fiche active. Le bon chemin est de poursuivre cette fiche plutot que d en ouvrir une nouvelle.'
        });
    }

    if (hasFreshCase) {
        steps.push({
            group: 'consultations-table',
            order: 70,
            target: '[data-tour="consultations-table.case-new"]',
            title: 'Cas nouvelle fiche',
            content: 'Cette ligne represente un patient sans fiche precedente reutilisable. Une nouvelle fiche peut alors etre creee proprement.'
        });
    }

    if (hasClosedCase) {
        steps.push({
            group: 'consultations-table',
            order: 80,
            target: '[data-tour="consultations-table.case-closed"]',
            title: 'Cas consultation cloturee',
            content: 'Une consultation cloturee reste visible dans l historique, peut etre revue dans ses details et, pour les profils autorises, sa facture peut encore etre ajustee.'
        });
    }

    if (hasUrgentCase) {
        steps.push({
            group: 'consultations-table',
            order: 90,
            target: '[data-tour="consultations-table.case-urgent"]',
            title: 'Indicateur d urgence',
            content: 'L indicateur urgent permet de reperer immediatement les consultations qui demandent une attention prioritaire dans la journee.'
        });
    }

    steps.push(
        {
            group: 'consultations-table',
            order: 100,
            target: '[data-tour="consultations-table.status"]',
            title: 'Lecture du statut',
            content: 'Les badges distinguent les consultations en cours et cloturees, avec indication urgente quand presente.'
        }
    );

    if (hasConsultations) {
        steps.push({
            group: 'consultations-table',
            order: 110,
            target: '[data-tour="consultations-table.actions"]',
            title: 'Actions par ligne',
            content: 'Depuis une ligne, vous pouvez ouvrir le dossier, voir les details, gerer la facture, annuler ou lancer des actions rapides.'
        });

        steps.push({
            group: 'consultations-table',
            order: 120,
            target: '[data-tour="consultations-table.dialog.details"]',
            title: 'Dialogue de details',
            content: 'Ce dialogue centralise la consultation, la note de seance et les actes deja enregistres pour une relecture rapide.',
            beforeEnter: async () => {
                await openDialogStep(openDetailsDialog, closeAllDialogs);
            }
        });

        if (isAdmin && hasClosedCase) {
            steps.push({
                group: 'consultations-table',
                order: 130,
                target: '[data-tour="consultations-table.dialog.facture"]',
                title: 'Dialogue de facture',
                content: 'Le dialogue de facture permet de revoir les lignes facturees et de corriger une facture d une consultation deja cloturee.',
                beforeEnter: async () => {
                    await openDialogStep(openFactureDialog, closeAllDialogs);
                }
            });
        }
    }

    if (!isMedecin) {
        steps.push({
            group: 'consultations-table',
            order: 140,
            target: '[data-tour="consultations-table.dialog.create"]',
            title: 'Dialogue de creation',
            content: 'Ce formulaire sert a creer une consultation sans quitter l historique de la journee.',
            beforeEnter: async () => {
                await openDialogStep(openCreateConsultationDialog, closeAllDialogs);
            }
        });
    }

    if (hasOpenConsultation) {
        steps.push({
            group: 'consultations-table',
            order: 150,
            target: '[data-tour="consultations-table.dialog.quick"]',
            title: 'Dialogue metier rapide',
            content: 'Les actions rapides ouvrent un dialogue pour continuer ou creer une fiche selon le contexte.',
            beforeEnter: async () => {
                await openDialogStep(openQuickDialog, closeAllDialogs);
            }
        });
    }

    return steps;
}
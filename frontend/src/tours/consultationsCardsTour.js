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

export function createConsultationsCardsTour({
    hasConsultations,
    isMedecin,
    openCreateConsultationDialog,
    openQuickDialog,
    closeAllDialogs,
    firstConsultationHasContinueAction,
    firstConsultationHasNewFicheAction,
    firstConsultationCanCancel,
    hasLinkedCase,
    hasFreshCase,
    canOpenCreateDialog
}) {
    const steps = [
        {
            group: 'consultations-cards',
            order: 10,
            target: '[data-tour="consultations-cards.stats"]',
            title: 'File d attente',
            content: 'Cette carte de synthese vous donne instantanement le nombre de consultations encore ouvertes. C est le premier indicateur a regarder pour evaluer la charge immediate du cabinet.'
        },
        {
            group: 'consultations-cards',
            order: 20,
            target: '[data-tour="consultations-cards.header"]',
            title: 'Vue globale des consultations en cours',
            content: 'Cette page regroupe toutes les consultations non cloturees. Elle sert a prioriser les patients, reprendre une fiche existante ou lancer une action rapide de prise en charge.'
        },
        {
            group: 'consultations-cards',
            order: 30,
            target: '[data-tour="consultations-cards.refresh"]',
            title: 'Rafraichissement manuel',
            content: 'Le bouton Rafraichir relance le chargement de la file d attente. Utilisez-le quand une consultation vient d etre creee, annulee ou cloturee depuis une autre page.'
        }
    ];

    if (!hasConsultations) {
        steps.push({
            group: 'consultations-cards',
            order: 40,
            target: '[data-tour="consultations-cards.empty-state"]',
            title: 'Aucune consultation en cours',
            content: 'Quand la file est vide, cela signifie que toutes les consultations ont deja ete traitees ou cloturees. Cette page devient alors un bon point de controle avant de creer une nouvelle consultation.'
        });

        if (!isMedecin) {
            steps.push(
                {
                    group: 'consultations-cards',
                    order: 50,
                    target: '[data-tour="consultations-cards.empty-create-button"]',
                    title: 'Relancer l activite',
                    content: 'Ce bouton est propose quand la file est vide pour creer rapidement une nouvelle consultation depuis cette page.'
                },
                {
                    group: 'consultations-cards',
                    order: 60,
                    title: 'Formulaire de creation',
                    content: 'Le formulaire de consultation permet d ouvrir une nouvelle prise en charge sans quitter la file d attente.',
                    beforeEnter: async () => {
                        openCreateConsultationDialog();
                        await flushUi();
                    },
                    afterLeave: async () => {
                        closeAllDialogs();
                        await flushUi();
                    }
                }
            );
        }

        return steps;
    }

    steps.push(
        {
            group: 'consultations-cards',
            order: 40,
            target: '[data-tour="consultations-cards.case-last-fiche"]',
            title: 'Cas reprise de fiche',
            content: 'La premiere carte montre le cas d une consultation non liee a une fiche active, mais pour laquelle une derniere fiche existe deja et peut etre reprise.'
        },
        {
            group: 'consultations-cards',
            order: 50,
            target: '[data-tour="consultations-cards.patient-block"]',
            title: 'Identite et contexte de prise en charge',
            content: 'En haut de la carte, vous retrouvez le nom du patient, son telephone, le medecin rattache et un badge de statut si disponible. Cette zone sert a identifier rapidement qui doit etre pris en charge.'
        },
        {
            group: 'consultations-cards',
            order: 60,
            target: '[data-tour="consultations-cards.timeline"]',
            title: 'Horodatage de la consultation',
            content: 'La section temporelle montre l heure d ouverture exacte et l anciennete de la consultation. C est un repere essentiel pour arbitrer la priorite de traitement.'
        },
        {
            group: 'consultations-cards',
            order: 70,
            target: '[data-tour="consultations-cards.progress"]',
            title: 'Indicateur visuel d attente',
            content: 'La barre de progression transforme le temps d attente en repere visuel. Plus la barre est avancee et plus la consultation est ancienne dans la file.'
        },
        {
            group: 'consultations-cards',
            order: 80,
            target: '[data-tour="consultations-cards.quick-actions"]',
            title: 'Actions rapides',
            content: 'Le menu Actions rapides regroupe les chemins intelligents pour reprendre la bonne fiche selon le contexte: derniere fiche, fiche liee ou nouvelle fiche.'
        }
    );

    if (hasLinkedCase) {
        steps.push({
            group: 'consultations-cards',
            order: 90,
            target: '[data-tour="consultations-cards.case-linked"]',
            title: 'Cas fiche deja liee',
            content: 'Cette carte represente une consultation deja rattachee a une fiche active. Le bon reflexe est alors de continuer cette fiche plutot que d en ouvrir une nouvelle.'
        });
    }

    if (hasFreshCase) {
        steps.push({
            group: 'consultations-cards',
            order: 100,
            target: '[data-tour="consultations-cards.case-new"]',
            title: 'Cas nouvelle fiche',
            content: 'Cette carte illustre un patient sans fiche precedente exploitable. Dans ce cas, la creation d une nouvelle fiche est le chemin attendu.'
        });
    }

    if (firstConsultationHasContinueAction) {
        steps.push({
            group: 'consultations-cards',
            order: 110,
            target: '[data-tour="consultations-cards.continue-action"]',
            title: 'Continuer la prise en charge',
            content: 'Ce bouton reprend la consultation ou la derniere fiche deja existante pour eviter de ressaisir inutilement le contexte clinique.'
        });
    }

    if (firstConsultationHasNewFicheAction) {
        steps.push({
            group: 'consultations-cards',
            order: 120,
            target: '[data-tour="consultations-cards.new-fiche-action"]',
            title: 'Ouvrir une nouvelle fiche',
            content: 'Cette action cree une nouvelle fiche quand la consultation n est pas encore liee a une fiche active. Elle est utile pour repartir sur une nouvelle saisie propre.'
        });
    }

    if (firstConsultationCanCancel) {
        steps.push({
            group: 'consultations-cards',
            order: 130,
            target: '[data-tour="consultations-cards.cancel-action"]',
            title: 'Annuler une consultation ouverte',
            content: 'L annulation retire la consultation de la file. Elle doit etre reservee aux cas ou l ouverture est erronée ou n a plus lieu d etre.'
        });
    }

    steps.push({
        group: 'consultations-cards',
        order: 140,
        target: '[data-tour="consultations-cards.dialog.quick"]',
        title: 'Cloturation rapide',
        content: 'Le dialogue de cloturation rapide sert a terminer une consultation sans passer par toute la fiche, en validant les informations cliniques minimales et la cloture.',
        beforeEnter: async () => {
            await openDialogStep(openQuickDialog, closeAllDialogs);
        }
    });

    if (!isMedecin && canOpenCreateDialog) {
        steps.push({
            group: 'consultations-cards',
            order: 150,
            target: '[data-tour="consultations-cards.dialog.create"]',
            title: 'Dialogue de creation',
            content: 'Ce formulaire ouvre une nouvelle consultation et, apres enregistrement, la carte apparaitra dans la file pour etre prise en charge.',
            beforeEnter: async () => {
                await openDialogStep(openCreateConsultationDialog, closeAllDialogs);
            }
        });
    }

    return steps;
}

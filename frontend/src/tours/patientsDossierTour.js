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

export function createPatientsDossierTour({
    isMedecin,
    isReception,
    hasFiches,
    openEditPatientDialog,
    openRdvDialog,
    openConsultationDialog,
    openPrintDialog,
    closeAllDialogs
}) {
    const steps = [
        {
            group: 'patients-dossier',
            order: 10,
            target: '[data-tour="patients-dossier.selector"]',
            title: 'Changer de patient',
            content: 'Le tour charge son patient fictif automatiquement, et ce selecteur permet ensuite de basculer rapidement vers un autre dossier.'
        },
        {
            group: 'patients-dossier',
            order: 20,
            target: '[data-tour="patients-dossier.info-card"]',
            title: 'Vue synthese du patient',
            content: 'La colonne de gauche regroupe l identite du patient, ses coordonnees, ses informations utiles et les acces rapides au dossier.'
        },
        {
            group: 'patients-dossier',
            order: 30,
            target: '[data-tour="patients-dossier.antecedents"]',
            title: 'Antecedents et allergies',
            content: 'Ces zones servent a consigner les alertes medicales importantes, y compris le contact d urgence utile en cas de besoin.'
        },
        {
            group: 'patients-dossier',
            order: 40,
            target: '[data-tour="patients-dossier.actions"]',
            title: 'Actions du dossier',
            content: 'Les actions rapides permettent d imprimer, modifier le patient et lancer un rendez-vous depuis le dossier.'
        },
        {
            group: 'patients-dossier',
            order: 50,
            target: '[data-tour="patients-dossier.medical"]',
            title: 'Suivi clinique',
            content: 'Cette zone regroupe la partie clinique du dossier avec les fiches medicales ou la liste des consultations selon votre role.'
        },
        {
            group: 'patients-dossier',
            order: 60,
            target: '[data-tour="patients-dossier.finance"]',
            title: 'Historique administratif',
            content: 'Cette section relie les rendez-vous, les paiements, les factures et, selon le role, le recapitulatif des consultations.'
        }
    ];

    if (isReception) {
        steps.push(
            {
                group: 'patients-dossier',
                order: 70,
                target: '[data-tour="patients-dossier.consultations-table"]',
                title: 'Historique des consultations',
                content: 'Le tableau reception permet de filtrer, exporter et relire rapidement les consultations du patient.'
            }
        );
    } else {
        steps.push(
            {
                group: 'patients-dossier',
                order: 70,
                target: '[data-tour="patients-dossier.fiches-toolbar"]',
                title: 'Barre des fiches',
                content: 'La barre des fiches indique l historique disponible et donne acces aux actions principales, notamment la lecture, la consultation et l impression.'
            },
            {
                group: 'patients-dossier',
                order: 80,
                target: '[data-tour="patients-dossier.fiches-preview"]',
                title: 'Lecture des fiches',
                content: 'La fiche medicale concentre les details cliniques du patient et le selecteur du bas permet de naviguer dans l historique.'
            }
        );
    }

    if (!isMedecin && !isReception) {
        steps.push({
            group: 'patients-dossier',
            order: 90,
            target: '[data-tour="patients-dossier.dialog.consultation"]',
            title: 'Dialogue de consultation',
            content: 'Le dialogue de consultation ouvre le demarrage d une nouvelle prise en charge a partir du dossier du patient.',
            beforeEnter: async () => {
                await openDialogStep(openConsultationDialog, closeAllDialogs);
            }
        });
    }

    steps.push(
        {
            group: 'patients-dossier',
            order: 100,
            target: '[data-tour="patients-dossier.dialog.edit"]',
            title: 'Dialogue de modification',
            content: 'Le dialogue de modification permet d ajuster les informations administratives du patient sans quitter le dossier.',
            beforeEnter: async () => {
                await openDialogStep(openEditPatientDialog, closeAllDialogs);
            }
        },
        {
            group: 'patients-dossier',
            order: 110,
            target: '[data-tour="patients-dossier.dialog.rdv"]',
            title: 'Dialogue de rendez-vous',
            content: 'Ce formulaire permet de planifier un prochain passage en gardant le contexte du dossier courant.',
            beforeEnter: async () => {
                await openDialogStep(openRdvDialog, closeAllDialogs);
            }
        }
    );

    if (!isReception && hasFiches) {
        steps.push({
            group: 'patients-dossier',
            order: 120,
            target: '[data-tour="patients-dossier.dialog.print"]',
            title: 'Preparation de l impression',
            content: 'Avant impression, ce dialogue permet de choisir les sections de fiche a inclure dans le document final.',
            beforeEnter: async () => {
                await openDialogStep(openPrintDialog, closeAllDialogs);
            }
        });
    }

    return steps;
}
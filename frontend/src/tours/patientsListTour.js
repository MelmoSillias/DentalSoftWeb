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

export function createPatientsListTour({
    hasPatients,
    isMedecin,
    openCreatePatientDialog,
    openRendezVousDialog,
    openConsultationDialog,
    openDuplicateConsultationDialog,
    closeAllDialogs
}) {
    const steps = [
        {
            group: 'patients-liste',
            order: 10,
            target: '[data-tour="patients-list.header"]',
            title: 'Gestion des patients',
            content: 'Cette page centralise la recherche, la creation et l acces rapide aux dossiers patients.'
        },
        {
            group: 'patients-liste',
            order: 20,
            target: '[data-tour="patients-list.toolbar"]',
            title: 'Actions principales',
            content: 'Depuis cette barre, vous pouvez ajouter un patient, planifier un rendez-vous et, selon votre role, ouvrir une consultation.'
        },
        {
            group: 'patients-liste',
            order: 30,
            target: '[data-tour="patients-list.search"]',
            title: 'Recherche patient',
            content: 'La recherche filtre la liste en direct pour retrouver un patient par nom, prenom, telephone ou adresse.'
        },
        {
            group: 'patients-liste',
            order: 40,
            target: '[data-tour="patients-list.table"]',
            title: 'Liste des patients',
            content: 'Le tableau donne acces au tri, a la pagination et a la derniere consultation visible pour chaque patient.'
        }
    ];

    if (hasPatients) {
        steps.push({
            group: 'patients-liste',
            order: 50,
            target: '[data-tour="patients-list.row-actions"]',
            title: 'Actions par patient',
            content: 'Sur la premiere ligne visible, vous retrouvez l ouverture du dossier, la consultation, le rendez-vous et la modification du patient.'
        });
    }

    steps.push(
        {
            group: 'patients-liste',
            order: 60,
            target: '[data-tour="patients-list.export"]',
            title: 'Exporter la liste',
            content: 'Le footer permet d exporter rapidement la liste des patients actuellement affiches.'
        },
        {
            group: 'patients-liste',
            order: 70,
            target: '[data-tour="patients-list.stats"]',
            title: 'Resume visuel',
            content: 'Ces cartes donnent un resume rapide du volume de patients et des indicateurs cles de la page.'
        },
        {
            group: 'patients-liste',
            order: 80,
            target: '[data-tour="patients-list.add-patient-button"]',
            title: 'Ajouter un patient',
            content: 'Ce bouton ouvre le formulaire de creation. Le meme formulaire est ensuite reutilise pour la modification depuis l action crayon.'
        },
        {
            group: 'patients-liste',
            order: 90,
            title: 'Formulaire patient',
            content: 'Le formulaire patient s ouvre pour creer un nouveau dossier ou modifier un dossier existant sans quitter la liste.',
            beforeEnter: async () => {
                openCreatePatientDialog();
                await flushUi();
            },
            afterLeave: async () => {
                closeAllDialogs();
                await flushUi();
            }
        },
        {
            group: 'patients-liste',
            order: 100,
            target: '[data-tour="patients-list.rdv-button"]',
            title: 'Nouveau rendez-vous',
            content: 'Depuis la barre principale, vous pouvez lancer un rendez-vous sans quitter la liste.'
        },
        {
            group: 'patients-liste',
            order: 110,
            title: 'Formulaire de rendez-vous',
            content: 'Le dialogue permet de planifier un nouveau rendez-vous pour un patient existant ou depuis le flux de creation.',
            beforeEnter: async () => {
                openRendezVousDialog();
                await flushUi();
            },
            afterLeave: async () => {
                closeAllDialogs();
                await flushUi();
            }
        }
    );

    if (!isMedecin) {
        steps.push(
            {
                group: 'patients-liste',
                order: 120,
                target: '[data-tour="patients-list.consultation-button"]',
                title: 'Nouvelle consultation',
                content: 'Ce bouton ouvre le flux de consultation directement depuis la page patients.'
            },
            {
                group: 'patients-liste',
                order: 130,
                title: 'Formulaire de consultation',
                content: 'Le dialogue de consultation permet de demarrer rapidement une prise en charge depuis la liste des patients.',
                beforeEnter: async () => {
                    openConsultationDialog();
                    await flushUi();
                },
                afterLeave: async () => {
                    closeAllDialogs();
                    await flushUi();
                }
            },
            {
                group: 'patients-liste',
                order: 140,
                title: 'Protection contre les doublons',
                content: 'Si une consultation est deja en cours pour un patient, la page affiche un avertissement au lieu de creer un doublon.',
                beforeEnter: async () => {
                    openDuplicateConsultationDialog();
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

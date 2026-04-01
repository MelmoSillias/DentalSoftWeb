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

export function createConsultationsFormTour({ setSection, openOrdonnanceDialog, closeAllDialogs }) {
    return [
        {
            group: 'consultations-form',
            order: 10,
            target: '[data-tour="consultations-form.header"]',
            title: 'Fiche medicale patient',
            content: 'Cette vue centralise toute la prise en charge: evaluation clinique, examens, documents, devis, seances et cloture de consultation.'
        },
        {
            group: 'consultations-form',
            order: 20,
            target: '[data-tour="consultations-form.navigation"]',
            title: 'Navigation rapide',
            content: 'Le bouton Retour vous ramene a la page precedente sans perdre le contexte. Utilisez-le apres verification des donnees sauvegardees.'
        },
        {
            group: 'consultations-form',
            order: 30,
            target: '[data-tour="consultations-form.display-mode"]',
            title: 'Mode d affichage',
            content: 'Basculez entre vue onglets et vue sidebar selon votre confort de lecture pendant la consultation.'
        },
        {
            group: 'consultations-form',
            order: 40,
            target: '[data-tour="consultations-form.save-indicator"]',
            title: 'Suivi de sauvegarde',
            content: 'L indicateur affiche les sections modifiees, l etat de sauvegarde et permet de lancer une sauvegarde globale a tout moment.'
        },
        {
            group: 'consultations-form',
            order: 50,
            target: '[data-tour="consultations-form.switcher"]',
            title: 'Parcours section par section',
            content: 'Le switcher structure la fiche en blocs metier pour avancer dans le bon ordre: infos, clinique, pieces justificatives, actes et cloture.'
        },
        {
            group: 'consultations-form',
            order: 60,
            target: '[data-tour="consultations-form.section.infos"]',
            title: 'Informations patient',
            content: 'Cette section consolide identite, contacts, antecedents et allergies. C est la base avant toute decision clinique.',
            beforeEnter: async () => {
                setSection('infos');
                await flushUi();
            }
        },
        {
            group: 'consultations-form',
            order: 70,
            target: '[data-tour="consultations-form.section.entretien"]',
            title: 'Entretien verbale',
            content: 'Saisissez le motif, l histoire de la plainte et les informations contextuelles qui orientent les examens.',
            beforeEnter: async () => {
                setSection('entretien');
                await flushUi();
            }
        },
        {
            group: 'consultations-form',
            order: 80,
            target: '[data-tour="consultations-form.section.examens"]',
            title: 'Examens cliniques',
            content: 'Documentez les constatations, observations et resultats cliniques qui soutiennent le diagnostic.',
            beforeEnter: async () => {
                setSection('examens');
                await flushUi();
            }
        },
        {
            group: 'consultations-form',
            order: 90,
            target: '[data-tour="consultations-form.section.documents"]',
            title: 'Images et documents',
            content: 'Ajoutez radios, photos ou fichiers utiles pour garder des preuves exploitables dans le suivi et la communication medicale.',
            beforeEnter: async () => {
                setSection('documents');
                await flushUi();
            }
        },
        {
            group: 'consultations-form',
            order: 100,
            target: '[data-tour="consultations-form.section.bilans"]',
            title: 'Bilans',
            content: 'Centralisez les bilans medicaux et dentaires pour disposer d une vue complete avant le plan de traitement.',
            beforeEnter: async () => {
                setSection('bilans');
                await flushUi();
            }
        },
        {
            group: 'consultations-form',
            order: 110,
            target: '[data-tour="consultations-form.section.plan-traitement"]',
            title: 'Plan de traitement',
            content: 'Definissez ici la strategie therapeutique, les etapes et les priorites pour la suite de la prise en charge.',
            beforeEnter: async () => {
                setSection('plan-traitement');
                await flushUi();
            }
        },
        {
            group: 'consultations-form',
            order: 120,
            target: '[data-tour="consultations-form.section.devis"]',
            title: 'Devis',
            content: 'Le devis transforme les actes prevus en chiffrage clair pour le patient et la partie caisse.',
            beforeEnter: async () => {
                setSection('devis');
                await flushUi();
            }
        },
        {
            group: 'consultations-form',
            order: 130,
            target: '[data-tour="consultations-form.section.seances"]',
            title: 'Seances',
            content: 'Cette partie recapitule les seances deja enregistrees pour suivre l avancement reel du traitement.',
            beforeEnter: async () => {
                setSection('seances');
                await flushUi();
            }
        },
        {
            group: 'consultations-form',
            order: 140,
            target: '[data-tour="consultations-form.section.consult"]',
            title: 'Consultation en cours',
            content: 'Finalisez ici les actes de seance, les intervenants, les ordonnances puis lancez la cloture une fois la verification terminee.',
            beforeEnter: async () => {
                setSection('consult');
                await flushUi();
            }
        },
        {
            group: 'consultations-form',
            order: 150,
            target: '[data-tour="consultations-form.dialogs"]',
            title: 'Dialogues metier',
            content: 'Les modales servent a completer rapidement antecedents, allergies et ordonnances sans quitter la fiche.',
            beforeEnter: async () => {
                setSection('consult');
                openOrdonnanceDialog();
                await flushUi();
            },
            afterLeave: async () => {
                closeAllDialogs();
                await flushUi();
            }
        }
    ];
}

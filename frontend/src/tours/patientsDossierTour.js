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

export function createPatientsDossierTour({
    hasPatientContext,
    isMedecin,
    isReception,
    hasFiches,
    openEditPatientDialog,
    openRdvDialog,
    openConsultationDialog,
    openPrintDialog,
    closeAllDialogs
}) {
    if (!hasPatientContext) {
        return [
            {
                group: 'patients-dossier',
                order: 10,
                target: '[data-tour="patients-dossier.selector"]',
                title: 'Selection du patient',
                content: 'Choisissez un patient dans ce selecteur pour charger son dossier complet sans revenir a la liste.'
            },
            {
                group: 'patients-dossier',
                order: 20,
                target: '[data-tour="patients-dossier.info-card"]',
                title: 'Resume du dossier',
                content: 'Quand un patient est charge, cette colonne affiche son identite, ses alertes medicales et ses actions rapides.'
            },
            {
                group: 'patients-dossier',
                order: 30,
                target: '[data-tour="patients-dossier.medical"]',
                title: 'Zone clinique',
                content: 'La zone de droite affiche les consultations ou les fiches medicales, puis l historique financier du patient.'
            }
        ];
    }

    const steps = [
        {
            group: 'patients-dossier',
            order: 10,
            target: '[data-tour="patients-dossier.selector"]',
            title: 'Changer de patient',
            content: 'Ce selecteur permet d ouvrir un autre dossier patient directement depuis cette page.'
        },
        {
            group: 'patients-dossier',
            order: 20,
            target: '[data-tour="patients-dossier.info-card"]',
            title: 'Resume du patient',
            content: 'La carte de gauche centralise les informations personnelles, antecedents, allergies et contact d urgence.'
        },
        {
            group: 'patients-dossier',
            order: 30,
            target: '[data-tour="patients-dossier.identity"]',
            title: 'Identite du patient',
            content: 'Vous retrouvez ici l identite visuelle du patient, son nom complet et son numero de dossier.'
        },
        {
            group: 'patients-dossier',
            order: 40,
            target: '[data-tour="patients-dossier.personal-details"]',
            title: 'Donnees personnelles',
            content: 'Date de naissance, lieu de naissance, age, sexe, groupe sanguin et profession sont regroupes dans ce bloc.'
        },
        {
            group: 'patients-dossier',
            order: 50,
            target: '[data-tour="patients-dossier.contact"]',
            title: 'Coordonnees',
            content: 'Telephone, email et adresse permettent de joindre rapidement le patient depuis son dossier.'
        },
        {
            group: 'patients-dossier',
            order: 60,
            target: '[data-tour="patients-dossier.antecedents"]',
            title: 'Antecedents medicaux',
            content: 'Cette section sert a consulter, ajouter ou supprimer les antecedents utiles a la prise en charge.'
        },
        {
            group: 'patients-dossier',
            order: 70,
            target: '[data-tour="patients-dossier.allergies"]',
            title: 'Allergies',
            content: 'Les allergies sont visibles ici pour securiser les prescriptions et les soins.'
        },
        {
            group: 'patients-dossier',
            order: 80,
            target: '[data-tour="patients-dossier.emergency-contact"]',
            title: 'Contact d urgence',
            content: 'Le contact d urgence centralise la personne a prevenir et son lien avec le patient.'
        },
        {
            group: 'patients-dossier',
            order: 90,
            target: '[data-tour="patients-dossier.actions"]',
            title: 'Actions du dossier',
            content: 'Les actions rapides permettent d imprimer, modifier le patient et lancer un rendez-vous depuis le dossier.'
        },
        {
            group: 'patients-dossier',
            order: 100,
            target: '[data-tour="patients-dossier.medical"]',
            title: 'Suivi clinique',
            content: 'Cette zone regroupe la partie clinique du dossier avec les fiches medicales ou la liste des consultations selon votre role.'
        },
        {
            group: 'patients-dossier',
            order: 110,
            target: '[data-tour="patients-dossier.finance"]',
            title: 'Rendez-vous, paiements et factures',
            content: 'Cette section relie l historique des rendez-vous et les donnees financieres du patient.'
        },
        {
            group: 'patients-dossier',
            order: 120,
            target: '[data-tour="patients-dossier.finance-tabs"]',
            title: 'Navigation financiere',
            content: 'Les onglets permettent d alterner entre rendez-vous, paiements, factures et consultations selon le role.'
        },
        {
            group: 'patients-dossier',
            order: 130,
            target: '[data-tour="patients-dossier.finance-content"]',
            title: 'Details financiers',
            content: 'Le contenu de l onglet actif affiche les montants, les statuts et les notes utiles au suivi administratif.'
        }
    ];

    if (isReception) {
        steps.push(
            {
                group: 'patients-dossier',
                order: 140,
                target: '[data-tour="patients-dossier.consultations-toolbar"]',
                title: 'Barre des consultations',
                content: 'Cette barre resument le volume des consultations et propose un export rapide.'
            },
            {
                group: 'patients-dossier',
                order: 150,
                target: '[data-tour="patients-dossier.consultations-filter"]',
                title: 'Recherche de consultation',
                content: 'Ce filtre permet de retrouver rapidement une consultation par date, medecin ou statut.'
            },
            {
                group: 'patients-dossier',
                order: 160,
                target: '[data-tour="patients-dossier.consultations-table"]',
                title: 'Table des consultations',
                content: 'Le tableau affiche l historique de consultation avec statut, medecin et montant facture.'
            }
        );
    } else {
        steps.push(
            {
                group: 'patients-dossier',
                order: 140,
                target: '[data-tour="patients-dossier.fiches-toolbar"]',
                title: 'Barre des fiches',
                content: 'Cette barre indique le nombre de fiches, permet d agrandir la vue et, selon le role, de lancer une nouvelle consultation.'
            },
            {
                group: 'patients-dossier',
                order: 150,
                target: '[data-tour="patients-dossier.fiches-preview"]',
                title: 'Lecture de la fiche',
                content: 'La fiche medicale affiche l entretien verbal, l examen, les documents, le plan de traitement, le bilan et les seances passees.'
            },
            {
                group: 'patients-dossier',
                order: 160,
                target: '[data-tour="patients-dossier.fiches-jump"]',
                title: 'Navigation entre fiches',
                content: 'Ce selecteur permet de passer rapidement d une fiche a une autre dans l historique du patient.'
            }
        );

        if (!isMedecin) {
            steps.push({
                group: 'patients-dossier',
                order: 170,
                target: '[data-tour="patients-dossier.fiches-new-consultation"]',
                title: 'Nouvelle consultation',
                content: 'Depuis cette action, vous pouvez lancer une nouvelle consultation directement depuis le dossier.'
            });
        }

        if (hasFiches) {
            steps.push({
                group: 'patients-dossier',
                order: 180,
                target: '[data-tour="patients-dossier.fiches-expand"]',
                title: 'Agrandir la fiche',
                content: 'Cette action ouvre une lecture plus confortable de la fiche pour la parcourir ou la preparer a l impression.'
            });
        }
    }

    steps.push(
        {
            group: 'patients-dossier',
            order: 190,
            target: '[data-tour="patients-dossier.dialog.edit"]',
            title: 'Dialogue de modification',
            content: 'Le dialogue de modification permet d ajuster les informations administratives du patient sans quitter le dossier.',
            beforeEnter: async () => {
                await openEditPatientDialog();
                await flushUi();
            },
            afterLeave: async () => {
                closeAllDialogs();
                await flushUi();
            }
        },
        {
            group: 'patients-dossier',
            order: 200,
            target: '[data-tour="patients-dossier.dialog.rdv"]',
            title: 'Dialogue de rendez-vous',
            content: 'Ce formulaire permet de planifier un prochain passage en gardant le contexte du dossier courant.',
            beforeEnter: async () => {
                await openRdvDialog();
                await flushUi();
            },
            afterLeave: async () => {
                closeAllDialogs();
                await flushUi();
            }
        }
    );

    if (!isMedecin && !isReception) {
        steps.push({
            group: 'patients-dossier',
            order: 210,
            target: '[data-tour="patients-dossier.dialog.consultation"]',
            title: 'Dialogue de consultation',
            content: 'Le dialogue de consultation ouvre le demarrage d une nouvelle prise en charge a partir du dossier du patient.',
            beforeEnter: async () => {
                await openConsultationDialog();
                await flushUi();
            },
            afterLeave: async () => {
                closeAllDialogs();
                await flushUi();
            }
        });
    }

    if (!isReception && hasFiches) {
        steps.push({
            group: 'patients-dossier',
            order: 220,
            target: '[data-tour="patients-dossier.dialog.print"]',
            title: 'Preparation de l impression',
            content: 'Avant impression, ce dialogue permet de choisir les sections de fiche a inclure dans le document final.',
            beforeEnter: async () => {
                await openPrintDialog();
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
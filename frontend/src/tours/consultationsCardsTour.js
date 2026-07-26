import { flushUi, openDialogStep, normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'consultations-cards';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'empty-queue', label: 'File vide', icon: 'pi pi-inbox', mockScenario: 'empty' },
    { id: 'prioritize-queue', label: 'Prioriser la file', icon: 'pi pi-sort-amount-down', mockScenario: 'static' },
    { id: 'continue-fiche', label: 'Continuer une fiche', icon: 'pi pi-play', mockScenario: 'static' },
    { id: 'new-fiche', label: 'Nouvelle fiche', icon: 'pi pi-file-plus', mockScenario: 'static' },
    { id: 'quick-cloture', label: 'Cloture rapide', icon: 'pi pi-check-circle', mockScenario: 'static' },
    { id: 'cancel-consultation', label: 'Annuler une consultation', icon: 'pi pi-times-circle', mockScenario: 'static' },
    { id: 'create-consultation', label: 'Creer une consultation', icon: 'pi pi-plus-circle', roles: ['admin', 'reception'], mockScenario: 'static' }
];

function buildOverviewSteps(ctx) {
    const steps = [
        {
            group: GROUP,
            target: '[data-tour="consultations-cards.stats"]',
            title: 'File d attente',
            content: 'Cette carte de synthese vous donne instantanement le nombre de consultations encore ouvertes.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-cards.header"]',
            title: 'Vue globale des consultations en cours',
            content: 'Cette page regroupe toutes les consultations non cloturees pour prioriser les patients et reprendre une fiche existante.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-cards.refresh"]',
            title: 'Rafraichissement manuel',
            content: 'Le bouton Rafraichir relance le chargement de la file d attente apres une creation, annulation ou cloture.'
        }
    ];

    if (!ctx.hasConsultations) {
        steps.push({
            group: GROUP,
            target: '[data-tour="consultations-cards.empty-state"]',
            title: 'Aucune consultation en cours',
            content: 'Quand la file est vide, toutes les consultations ont deja ete traitees ou cloturees.'
        });
        return normalizeTourSteps(steps);
    }

    steps.push(
        {
            group: GROUP,
            target: '[data-tour="consultations-cards.patient-block"]',
            title: 'Identite et contexte de prise en charge',
            content: 'En haut de la carte, vous retrouvez le nom du patient, son telephone, le medecin rattache et un badge de statut.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-cards.timeline"]',
            title: 'Horodatage de la consultation',
            content: 'La section temporelle montre l heure d ouverture exacte et l anciennete de la consultation.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-cards.progress"]',
            title: 'Indicateur visuel d attente',
            content: 'La barre de progression transforme le temps d attente en repere visuel pour arbitrer la priorite.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-cards.quick-actions"]',
            title: 'Actions rapides',
            content: 'Le menu Actions rapides regroupe les chemins pour reprendre la bonne fiche selon le contexte.'
        }
    );

    return normalizeTourSteps(steps);
}

export const consultationsCardsRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'empty-queue': (ctx) => {
        const steps = [
            {
                group: GROUP,
                target: '[data-tour="consultations-cards.empty-state"]',
                title: 'File d attente vide',
                content: 'Quand aucune consultation n est ouverte, cette zone confirme que la file est libre.'
            }
        ];

        if (!ctx.isMedecin) {
            steps.push(
                {
                    group: GROUP,
                    target: '[data-tour="consultations-cards.empty-create-button"]',
                    title: 'Relancer l activite',
                    content: 'Ce bouton permet de creer rapidement une nouvelle consultation depuis cette page.'
                },
                {
                    group: GROUP,
                    target: '[data-tour="consultations-cards.dialog.create"]',
                    title: 'Formulaire de creation',
                    content: 'Le formulaire ouvre une nouvelle prise en charge sans quitter la file d attente.',
                    beforeEnter: async () => openDialogStep(ctx.openCreateConsultationDialog, ctx.closeAllDialogs)
                }
            );
        }

        return normalizeTourSteps(steps);
    },
    'prioritize-queue': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="consultations-cards.stats"]',
            title: 'Volume en attente',
            content: 'Commencez par le nombre total de consultations ouvertes pour evaluer la charge immediate.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-cards.timeline"]',
            title: 'Anciennete',
            content: 'Comparez l heure d ouverture et l anciennete pour identifier les consultations les plus urgentes.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-cards.progress"]',
            title: 'Repere visuel',
            content: 'La barre de progression aide a reperer rapidement les consultations les plus anciennes dans la file.'
        }
    ]),
    'continue-fiche': (ctx) => {
        const steps = [
            {
                group: GROUP,
                target: '[data-tour="consultations-cards.quick-actions"]',
                title: 'Actions rapides',
                content: 'Le menu propose de reprendre la derniere fiche, une fiche liee ou une nouvelle fiche selon le contexte.'
            }
        ];

        if (ctx.hasLinkedCase) {
            steps.unshift({
                group: GROUP,
                target: '[data-tour="consultations-cards.case-linked"]',
                title: 'Fiche deja liee',
                content: 'Quand une fiche active existe, le bon reflexe est de continuer cette fiche.'
            });
        } else if (ctx.hasConsultations) {
            steps.unshift({
                group: GROUP,
                target: '[data-tour="consultations-cards.case-last-fiche"]',
                title: 'Reprise de derniere fiche',
                content: 'Cette carte montre une consultation avec une derniere fiche existante a reprendre.'
            });
        }

        if (ctx.firstConsultationHasContinueAction) {
            steps.push({
                group: GROUP,
                target: '[data-tour="consultations-cards.continue-action"]',
                title: 'Continuer la prise en charge',
                content: 'Ce bouton reprend la consultation ou la derniere fiche existante pour eviter une ressaisie inutile.'
            });
        }

        return normalizeTourSteps(steps);
    },
    'new-fiche': (ctx) => {
        const steps = [];

        if (ctx.hasFreshCase) {
            steps.push({
                group: GROUP,
                target: '[data-tour="consultations-cards.case-new"]',
                title: 'Cas nouvelle fiche',
                content: 'Ce patient n a pas de fiche precedente exploitable. Une nouvelle fiche est le chemin attendu.'
            });
        }

        steps.push(
            {
                group: GROUP,
                target: '[data-tour="consultations-cards.quick-actions"]',
                title: 'Menu Actions rapides',
                content: 'Ouvrez le menu pour choisir la creation d une nouvelle fiche quand aucune fiche active n existe.'
            }
        );

        if (ctx.firstConsultationHasNewFicheAction) {
            steps.push({
                group: GROUP,
                target: '[data-tour="consultations-cards.new-fiche-action"]',
                title: 'Ouvrir une nouvelle fiche',
                content: 'Cette action cree une nouvelle fiche pour repartir sur une saisie propre.'
            });
        }

        return normalizeTourSteps(steps);
    },
    'quick-cloture': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="consultations-cards.quick-actions"]',
            title: 'Actions rapides',
            content: 'Depuis le menu, vous pouvez lancer une cloturation sans passer par toute la fiche.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-cards.dialog.quick"]',
            title: 'Cloturation rapide',
            content: 'Le dialogue permet de terminer une consultation en validant les informations cliniques minimales.',
            beforeEnter: async () => openDialogStep(ctx.openQuickDialog, ctx.closeAllDialogs)
        }
    ]),
    'cancel-consultation': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="consultations-cards.patient-block"]',
            title: 'Identifier la consultation',
            content: 'Reperez la consultation a annuler grace aux informations patient affichees sur la carte.'
        },
        ...(ctx.firstConsultationCanCancel ? [{
            group: GROUP,
            target: '[data-tour="consultations-cards.cancel-action"]',
            title: 'Annuler une consultation ouverte',
            content: 'L annulation retire la consultation de la file. Reservez-la aux ouvertures erronees ou sans suite.'
        }] : [])
    ]),
    'create-consultation': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="consultations-cards.header"]',
            title: 'Creer une consultation',
            content: 'Depuis la file d attente, vous pouvez ouvrir une nouvelle prise en charge sans changer de page.'
        },
        {
            group: GROUP,
            target: '[data-tour="consultations-cards.dialog.create"]',
            title: 'Dialogue de creation',
            content: 'Ce formulaire enregistre une nouvelle consultation qui apparaitra ensuite dans la file.',
            beforeEnter: async () => openDialogStep(ctx.openCreateConsultationDialog, ctx.closeAllDialogs)
        }
    ])
});

export function buildConsultationsCardsTourSteps(taskId, variantId, ctx) {
    return consultationsCardsRegistry.buildSteps(taskId, variantId, ctx);
}

export function createConsultationsCardsTour(ctx) {
    return buildConsultationsCardsTourSteps('overview', null, ctx);
}

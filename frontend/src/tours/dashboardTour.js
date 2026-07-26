import { normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'dashboard';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'filter-periods', label: 'Filtrer les periodes', icon: 'pi pi-calendar', mockScenario: 'static' },
    { id: 'read-indicators', label: 'Lire les indicateurs', icon: 'pi pi-chart-bar', mockScenario: 'static' }
];

function resolveRoleLabel(role) {
    if (role === 'medecin') return 'medecin';
    if (role === 'reception') return 'reception';
    return 'admin';
}

function getRoleOverviewContent(roleLabel) {
    if (roleLabel === 'admin') {
        return 'En tant qu administrateur, vous disposez d une vue transversale : activite clinique, encaissements, file d attente et alertes du cabinet.';
    }
    if (roleLabel === 'medecin') {
        return 'En tant que medecin, la page met en avant votre activite personnelle : patients vus, consultations en cours, rendez-vous et montants generes.';
    }
    return 'En tant qu accueil, la page concentre le suivi operationnel du jour : nouveaux patients, consultations, rendez-vous et encaissements en caisse.';
}

function getQuickStatsContent(roleLabel) {
    if (roleLabel === 'admin') {
        return 'Chaque carte resume un axe du cabinet : nouveaux patients, volume de consultations, rendez-vous du jour, encaissements et consultations encore en attente. Cliquez sur le lien en bas de carte pour ouvrir la page detaillee correspondante.';
    }
    if (roleLabel === 'medecin') {
        return 'Les cartes affichent vos patients consultes, les consultations en attente avec leur duree moyenne, vos rendez-vous, vos consultations payantes et le montant genere sur la periode. Utilisez les liens pour acceder directement a l agenda, a la file ou a la caisse.';
    }
    return 'Les cartes suivent l activite reception : nouveaux patients, consultations du jour, rendez-vous en attente ou annules, et montants encaisses ou impayes. Chaque carte propose un raccourci vers la page metier concernee.';
}

function getMainReportDescription(roleLabel) {
    if (roleLabel === 'admin') {
        return 'Le carrousel analytique enchaine plusieurs rapports : performance par medecin, actes realises, entrees financieres, sorties, equilibre net et evolution du capital. Naviguez avec les fleches ou changez la granularite (7 jours, mois, trimestre, annee) pour comparer les tendances.';
    }
    if (roleLabel === 'medecin') {
        return 'Le carrousel presente vos tendances personnelles : volume de consultations, repartition des actes et montants generes. Les mini-graphiques et objectifs vous aident a situer votre activite sur la periode selectionnee.';
    }
    return 'A la place du carrousel, la reception consulte un tableau de rapports periodiques par medecin. Il resume, pour la date choisie, le volume de consultations et les montants associes a chaque praticien.';
}

function getTabsPanelDescription(roleLabel) {
    if (roleLabel === 'admin') {
        return 'Le panneau « En attente » regroupe les elements a traiter : rendez-vous du jour, consultations ouvertes et factures impayees. Parcourez les onglets pour prioriser les actions sans quitter le dashboard.';
    }
    if (roleLabel === 'medecin') {
        return 'Le panneau lateral liste ce qui requiert votre attention : rendez-vous a venir, consultations en cours et factures en souffrance. L onglet Actes met en avant vos interventions les plus frequentes sur la periode.';
    }
    return 'Le panneau « En attente » centralise rendez-vous, consultations, factures impayees et paiements recents. Chaque ligne propose un bouton d acces rapide vers l agenda, la file ou la caisse.';
}

function getNotificationsDescription(roleLabel) {
    if (roleLabel === 'admin') {
        return 'Les notifications remontent les alertes transverses du cabinet : rappels systeme, echeances et evenements necessitant une decision. Filtrez par statut (toutes, lues, non lues) et marquez-les comme traitees pour garder une liste a jour.';
    }
    if (roleLabel === 'medecin') {
        return 'Les notifications signalent vos rappels cliniques et les suivis patients a ne pas oublier. Utilisez le filtre pour ne voir que les messages non lus, puis marquez-les comme lus une fois traites.';
    }
    return 'Les notifications vous indiquent les points reception a traiter en priorite dans la journee. Le compteur de non lus et les actions « tout marquer comme lu » facilitent le tri des messages entrants.';
}

function getFilterModeContent(roleLabel) {
    if (roleLabel === 'reception') {
        return 'Pour le role reception, seul le mode date est disponible : choisissez le jour a analyser. Toutes les cartes, tableaux et rapports se recalculent automatiquement sur cette journee.';
    }
    return 'Basculez entre « Date » (un jour precis) et « Periode » (plage personnalisee). En mode periode, selectionnez une date de debut et de fin : l ensemble du dashboard — cartes, carrousel, panneau lateral et notifications metier — se met a jour sur ce intervalle.';
}

function buildOverviewSteps(ctx) {
    const roleLabel = resolveRoleLabel(ctx.role);

    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="dashboard.header"]',
            title: 'Accueil personnalise',
            content: `Le bandeau d en-tete affiche votre nom et un message de bienvenue. ${getRoleOverviewContent(roleLabel)} Le fil d Ariane sous l en-tete confirme que vous etes sur le tableau de bord principal.`
        },
        {
            group: GROUP,
            target: '[data-tour="dashboard.filters"]',
            title: 'Piloter la periode analysee',
            content: getFilterModeContent(roleLabel)
        },
        {
            group: GROUP,
            target: '[data-tour="dashboard.quick-stats"]',
            title: 'Indicateurs cles en un coup d oeil',
            content: getQuickStatsContent(roleLabel)
        },
        {
            group: GROUP,
            target: '[data-tour="dashboard.main-report"]',
            title: 'Analyse approfondie',
            content: getMainReportDescription(roleLabel)
        },
        {
            group: GROUP,
            target: '[data-tour="dashboard.tabs-panel"]',
            title: 'File d attente operationnelle',
            content: getTabsPanelDescription(roleLabel)
        },
        {
            group: GROUP,
            target: '[data-tour="dashboard.notifications"]',
            title: 'Alertes et rappels',
            content: getNotificationsDescription(roleLabel)
        }
    ]);
}

function buildFilterPeriodsSteps(ctx) {
    const roleLabel = resolveRoleLabel(ctx.role);

    const steps = [
        {
            group: GROUP,
            target: '[data-tour="dashboard.filters"]',
            title: 'Choisir le mode de filtre',
            content: getFilterModeContent(roleLabel)
        }
    ];

    if (roleLabel !== 'reception') {
        steps.push({
            group: GROUP,
            target: '[data-tour="dashboard.filters"]',
            title: 'Basculer date ou plage',
            content: 'Le selecteur « Date / Periode » determine le type de filtre actif. En mode Date, un seul calendrier suffit pour analyser une journee. En mode Periode, le selecteur de plage permet de couvrir une semaine, un mois ou toute intervalle personnalisee.'
        });
    }

    steps.push(
        {
            group: GROUP,
            target: '[data-tour="dashboard.quick-stats"]',
            title: 'Recalcul immediat des cartes',
            content: 'Des que la date ou la plage change, les valeurs des cartes (patients, consultations, rendez-vous, montants, attente) sont rechargees depuis l API. Verifiez que les chiffres correspondent bien a la periode affichee en haut des rapports.'
        },
        {
            group: GROUP,
            target: '[data-tour="dashboard.main-report"]',
            title: 'Impact sur les rapports',
            content: roleLabel === 'reception'
                ? 'Le tableau des rapports par medecin affiche la periode choisie dans son titre. Changez de date pour comparer l activite d un praticien d un jour a l autre.'
                : 'Le carrousel et ses graphiques se recalculent sur la meme plage que le filtre principal. Utilisez aussi le selecteur de granularite (7 jours, mois, trimestre, annee) pour ajuster le niveau de detail des courbes.'
        },
        {
            group: GROUP,
            target: '[data-tour="dashboard.tabs-panel"]',
            title: 'Impact sur le panneau lateral',
            content: 'Les listes « En attente » (rendez-vous, consultations, factures, paiements ou actes) sont filtrees sur la periode active. Un changement de date peut faire apparaitre ou disparaitre des elements selon leur echeance.'
        }
    );

    return normalizeTourSteps(steps);
}

function buildReadIndicatorsSteps(ctx) {
    const roleLabel = resolveRoleLabel(ctx.role);

    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="dashboard.quick-stats"]',
            title: 'Lire chaque carte de synthese',
            content: getQuickStatsContent(roleLabel)
        },
        {
            group: GROUP,
            target: '[data-tour="dashboard.quick-stats"]',
            title: 'Valeur principale et sous-texte',
            content: 'Le chiffre en grand est l indicateur principal (total, montant ou compteur). La ligne secondaire apporte un contexte utile : total cumule, part payante, duree d attente moyenne ou montant impaye. Ces deux niveaux evitent d ouvrir une page detaillee pour un premier diagnostic.'
        },
        {
            group: GROUP,
            target: '[data-tour="dashboard.main-report"]',
            title: 'Approfondir avec les rapports',
            content: getMainReportDescription(roleLabel)
        },
        {
            group: GROUP,
            target: '[data-tour="dashboard.main-report"]',
            title: 'Raccourcis et objectifs',
            content: roleLabel === 'admin'
                ? 'Chaque slide du carrousel combine un classement (top medecins ou modes de paiement), un graphique barres, un resume chiffre et parfois un objectif cible (ex. taux de consultations payantes). Les boutons d action en bas du slide ouvrent Patients, Rapports ou Finances.'
                : roleLabel === 'medecin'
                  ? 'Les slides mettent en avant vos consultations, vos actes et vos montants avec des graphiques comparatifs. Les raccourcis Agenda, Consultations et Patients restent accessibles depuis chaque slide.'
                  : 'Le tableau par medecin detaille le nombre de consultations et les montants associes. Utilisez-le pour equilibrer la charge entre praticiens ou reperer une activite atypique sur la journee selectionnee.'
        },
        {
            group: GROUP,
            target: '[data-tour="dashboard.tabs-panel"]',
            title: 'Prioriser les actions en attente',
            content: getTabsPanelDescription(roleLabel)
        },
        {
            group: GROUP,
            target: '[data-tour="dashboard.tabs-panel"]',
            title: 'Naviguer entre les onglets',
            content: roleLabel === 'medecin'
                ? 'Onglet Rendez-vous : prochains RDV avec patient, heure et motif. Onglet Consultations : file ouverte avec duree d attente. Onglet Factures : impayes a relancer. Onglet Actes : vos actes les plus realises sur la periode.'
                : roleLabel === 'reception'
                  ? 'Onglet Rendez-vous : accueil du jour. Onglet Consultations : patients en salle d attente. Onglet Factures : restes a payer. Onglet Paiements : encaissements recents a verifier ou a imprimer.'
                  : 'Onglet Rendez-vous : planning du jour. Onglet Consultations : urgences et attentes. Onglet Factures : creances du cabinet. Chaque ligne propose un lien direct vers la page metier pour traiter l element.'
        },
        {
            group: GROUP,
            target: '[data-tour="dashboard.notifications"]',
            title: 'Completer avec les alertes',
            content: getNotificationsDescription(roleLabel)
        }
    ]);
}

export const dashboardRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'filter-periods': buildFilterPeriodsSteps,
    'read-indicators': buildReadIndicatorsSteps
});

export function buildDashboardTourSteps(taskId, variantId, ctx) {
    return dashboardRegistry.buildSteps(taskId, variantId, ctx);
}

export function createDashboardTour(ctx) {
    return buildDashboardTourSteps('overview', null, ctx);
}

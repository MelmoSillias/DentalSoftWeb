export function createDashboardTour({ role }) {
    const roleLabel = role === 'medecin' ? 'medecin' : role === 'reception' ? 'reception' : 'admin';

    const roleOverview = roleLabel === 'admin'
        ? 'Le contenu met l accent sur les indicateurs globaux, finances et pilotage du cabinet.'
        : roleLabel === 'medecin'
          ? 'Le contenu met l accent sur votre activite clinique, vos consultations et vos raccourcis metier.'
          : 'Le contenu met l accent sur le suivi reception, les rapports journaliers et l operationnel des rendez-vous.';

    const mainReportDescription = roleLabel === 'admin'
        ? 'Le bloc principal affiche un carrousel analytique avec rapports par medecin, actes et suivi financier.'
        : roleLabel === 'medecin'
          ? 'Le bloc principal affiche vos tendances de consultations et montants sur la periode choisie.'
          : 'Le bloc principal affiche le rapport periodique par medecin pour le suivi reception.';

    const notificationsDescription = roleLabel === 'admin'
        ? 'Les notifications vous aident a suivre les alertes transverses du cabinet et les actions a prioriser.'
        : roleLabel === 'medecin'
          ? 'Les notifications vous aident a prioriser vos actions cliniques et suivis patients du jour.'
          : 'Les notifications vous signalent les points reception a traiter rapidement dans la journee.';

    return [
        {
            group: 'dashboard',
            order: 10,
            target: '[data-tour="dashboard.header"]',
            title: 'Tableau de bord du jour',
            content: `Cette page centralise les indicateurs utiles des la connexion. ${roleOverview}`
        },
        {
            group: 'dashboard',
            order: 20,
            target: '[data-tour="dashboard.filters"]',
            title: 'Filtrer les donnees',
            content: 'Choisissez une date unique ou une plage de dates. Toutes les cartes, tableaux et rapports se recalculent sur ce perimetre.'
        },
        {
            group: 'dashboard',
            order: 30,
            target: '[data-tour="dashboard.quick-stats"]',
            title: 'Lire les indicateurs cles',
            content: 'Ces cartes donnent une vue rapide sur les patients, consultations, rendez-vous et montants suivis dans le cabinet.'
        },
        {
            group: 'dashboard',
            order: 40,
            target: '[data-tour="dashboard.main-report"]',
            title: 'Approfondir l analyse',
            content: mainReportDescription
        },
        {
            group: 'dashboard',
            order: 50,
            target: '[data-tour="dashboard.tabs-panel"]',
            title: 'Suivi operationnel',
            content: 'Le panneau lateral regroupe les details complementaires a surveiller au quotidien selon votre role.'
        },
        {
            group: 'dashboard',
            order: 60,
            target: '[data-tour="dashboard.notifications"]',
            title: 'Alertes recentes',
            content: notificationsDescription
        }
    ];
}
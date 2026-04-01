export function resolveAdministrationFinancesTourGroup(activeTab) {
    return activeTab === 'charts' ? 'administration-finances:charts' : 'administration-finances:tables';
}

export function createAdministrationFinancesTour({ activeTab }) {
    if (activeTab === 'charts') {
        return [
            {
                group: 'administration-finances:charts',
                order: 10,
                target: '[data-tour="admin-finances.tabs"]',
                title: 'Passer aux graphiques',
                content: 'Cet onglet transforme les tableaux financiers en visualisations de pilotage.'
            },
            {
                group: 'administration-finances:charts',
                order: 20,
                target: '[data-tour="admin-finances.monthly-flow"]',
                title: 'Lire le flux mensuel',
                content: 'Ce graphe compare les entrees, les depenses et le resultat net sur l annee choisie.'
            },
            {
                group: 'administration-finances:charts',
                order: 30,
                target: '[data-tour="admin-finances.distribution"]',
                title: 'Repartition des encaissements',
                content: 'Ce donut montre la part de chaque mode de paiement dans les encaissements.'
            },
            {
                group: 'administration-finances:charts',
                order: 40,
                target: '[data-tour="admin-finances.accounts"]',
                title: 'Suivre capital et comptes',
                content: 'Les graphiques par compte mettent en avant le capital disponible et le solde par mode.'
            },
            {
                group: 'administration-finances:charts',
                order: 50,
                target: '[data-tour="admin-finances.status"]',
                title: 'Voir les statuts de validation',
                content: 'Ce graphe offre une vision immediate des flux valides, rejetes et en attente.'
            }
        ];
    }

    return [
        {
            group: 'administration-finances:tables',
            order: 10,
            target: '[data-tour="admin-finances.header"]',
            title: 'Tableau de bord financier',
            content: 'Cette page consolide les transactions, validations manuelles et modes de paiement du cabinet.'
        },
        {
            group: 'administration-finances:tables',
            order: 20,
            target: '[data-tour="admin-finances.kpi"]',
            title: 'Lire les KPI',
            content: 'Les cartes du haut isolent capital total, transactions validees, flux en attente et nombre de modes actifs.'
        },
        {
            group: 'administration-finances:tables',
            order: 30,
            target: '[data-tour="admin-finances.transactions"]',
            title: 'Filtrer l historique',
            content: 'Utilisez la periode, le statut et la recherche pour cibler les transactions a controler.'
        },
        {
            group: 'administration-finances:tables',
            order: 40,
            target: '[data-tour="admin-finances.validation"]',
            title: 'Valider ou rejeter',
            content: 'Les actions de ligne servent a confirmer ou rejeter une transaction encore en attente.'
        },
        {
            group: 'administration-finances:tables',
            order: 50,
            target: '[data-tour="admin-finances.methods"]',
            title: 'Gerer les modes de paiement',
            content: 'Les modes sont regroupes par famille, avec un traitement distinct pour les assurances et leur taux de prise en charge.'
        },
        {
            group: 'administration-finances:tables',
            order: 60,
            target: '[data-tour="admin-finances.dialogs"]',
            title: 'Creer ou modifier',
            content: 'Les formulaires de transaction et de mode servent a faire vivre le referentiel financier sans quitter la page.'
        }
    ];
}

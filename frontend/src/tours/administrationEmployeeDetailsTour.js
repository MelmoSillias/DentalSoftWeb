export function createAdministrationEmployeeDetailsTour() {
    return [
        {
            group: 'administration-employee-details',
            order: 10,
            target: '[data-tour="admin-employee-details.header"]',
            title: 'Fiche employe',
            content: 'Cette page detaille la fiche RH complete d un employe avec edition directe des informations principales.'
        },
        {
            group: 'administration-employee-details',
            order: 20,
            target: '[data-tour="admin-employee-details.personal"]',
            title: 'Informations personnelles',
            content: 'Renseignez identite, contacts et date d embauche pour garder une base RH fiable.'
        },
        {
            group: 'administration-employee-details',
            order: 30,
            target: '[data-tour="admin-employee-details.rh"]',
            title: 'Parametres RH',
            content: 'Cette section gere type de salaire, valeur, contrat et jours travailles.'
        },
        {
            group: 'administration-employee-details',
            order: 40,
            target: '[data-tour="admin-employee-details.documents"]',
            title: 'Documents administratifs',
            content: 'Ajoutez et telechargez les pieces administratives liees a l employe depuis ce bloc.'
        },
        {
            group: 'administration-employee-details',
            order: 50,
            target: '[data-tour="admin-employee-details.conges"]',
            title: 'Conges par annee',
            content: 'Visualisez l historique des conges avec total annuel pour faciliter le suivi RH.'
        },
        {
            group: 'administration-employee-details',
            order: 60,
            target: '[data-tour="admin-employee-details.summary"]',
            title: 'Cartes de synthese',
            content: 'Les cartes de droite affichent un resume rapide du profil, du type et de la remuneration actuelle.'
        }
    ];
}

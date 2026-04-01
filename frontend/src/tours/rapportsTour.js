export function resolveRapportsTourGroup(role) {
    if (role === 'medecin') return 'rapports:medecin';
    if (role === 'reception') return 'rapports:reception';
    return 'rapports:admin';
}

export function createRapportsTour({ role }) {
    if (role === 'medecin') {
        return [
            {
                group: 'rapports:medecin',
                order: 10,
                target: '[data-tour="rapports-medecin.range"]',
                title: 'Choisir la periode',
                content: 'Le rapport se recalculera sur la plage choisie pour analyser votre activite recente.'
            },
            {
                group: 'rapports:medecin',
                order: 20,
                target: '[data-tour="rapports-medecin.global"]',
                title: 'Vue d ensemble personnelle',
                content: 'Cette synthese resume votre volume d activite et vos principaux indicateurs cliniques.'
            },
            {
                group: 'rapports:medecin',
                order: 30,
                target: '[data-tour="rapports-medecin.quick"]',
                title: 'Lire les indicateurs rapides',
                content: 'Les quick stats isolent les informations les plus utiles pour la pratique quotidienne.'
            },
            {
                group: 'rapports:medecin',
                order: 40,
                target: '[data-tour="rapports-medecin.periodic"]',
                title: 'Detail sur la periode',
                content: 'Cette partie detaille consultations, paiements et autres resultats lies a la periode selectionnee.'
            },
            {
                group: 'rapports:medecin',
                order: 50,
                target: '[data-tour="rapports-medecin.acts"]',
                title: 'Revoir les actes et paiements',
                content: 'Utilisez cette section pour relire les actes realises et les flux associes.'
            },
            {
                group: 'rapports:medecin',
                order: 60,
                target: '[data-tour="rapports-medecin.profile"]',
                title: 'Profil professionnel',
                content: 'Cette derniere zone relie vos statistiques au profil du praticien connecte.'
            }
        ];
    }

    if (role === 'reception') {
        return [
            {
                group: 'rapports:reception',
                order: 10,
                target: '[data-tour="rapports-reception.date"]',
                title: 'Choisir la journee',
                content: 'Le rapport reception est journalier. Changez de date pour revoir une autre journee d accueil.'
            },
            {
                group: 'rapports:reception',
                order: 20,
                target: '[data-tour="rapports-reception.daily"]',
                title: 'Lire les stats du jour',
                content: 'Cette carte resume l activite reception du jour selectionne.'
            },
            {
                group: 'rapports:reception',
                order: 30,
                target: '[data-tour="rapports-reception.doctors"]',
                title: 'Voir les rapports par medecin',
                content: 'Le tableau par medecin permet de comparer rapidement l activite du jour entre praticiens.'
            },
            {
                group: 'rapports:reception',
                order: 40,
                target: '[data-tour="rapports-reception.print"]',
                title: 'Imprimer le recapitulatif',
                content: 'Utilisez l impression pour sortir un resume journalier reception.'
            }
        ];
    }

    return [
        {
            group: 'rapports:admin',
            order: 10,
            target: '[data-tour="rapports-admin.range"]',
            title: 'Choisir la periode',
            content: 'Selectionnez une plage de dates pour recalculer l ensemble des statistiques du cabinet.'
        },
        {
            group: 'rapports:admin',
            order: 20,
            target: '[data-tour="rapports-admin.global"]',
            title: 'Lire la synthese globale',
            content: 'Cette section resume l activite generale du cabinet sur la periode.'
        },
        {
            group: 'rapports:admin',
            order: 30,
            target: '[data-tour="rapports-admin.non-periodic"]',
            title: 'Surveiller les fondamentaux',
            content: 'Les details non periodiques couvrent la repartition du personnel, les consommables critiques et les patients globaux.'
        },
        {
            group: 'rapports:admin',
            order: 40,
            target: '[data-tour="rapports-admin.periodic"]',
            title: 'Lire l activite sur la periode',
            content: 'Cette zone detaille patients, consultations, rendez-vous, usage des salles et equilibres de paiement.'
        },
        {
            group: 'rapports:admin',
            order: 50,
            target: '[data-tour="rapports-admin.acts"]',
            title: 'Analyser les actes',
            content: 'Cette section met en avant les actes realises et les volumes utiles a l analyse metier.'
        },
        {
            group: 'rapports:admin',
            order: 60,
            target: '[data-tour="rapports-admin.doctors"]',
            title: 'Comparer les medecins',
            content: 'Le tableau final consolide les performances par medecin sur la meme periode.'
        }
    ];
}
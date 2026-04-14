export function createSettingsApparenceTour() {
    return [
        {
            group: 'settings-apparence',
            order: 10,
            target: '[data-tour="settings-appearance.main"]',
            title: 'Parametres d apparence',
            content: 'Cette page centralise la personnalisation de l interface et les reglages generaux du cabinet.'
        },
        {
            group: 'settings-apparence',
            order: 20,
            target: '[data-tour="settings-appearance.navigation"]',
            title: 'Navigation par section',
            content: 'Utilisez la colonne laterale pour naviguer rapidement vers chaque zone de configuration.'
        },
        {
            group: 'settings-apparence',
            order: 30,
            target: '[data-tour="settings-appearance.theme"]',
            title: 'Theme et apparence',
            content: 'Ce bloc presente la synthese du theme, du preset, de la police et de la palette active.'
        },
        {
            group: 'settings-apparence',
            order: 40,
            target: '[data-tour="settings-appearance.primary"]',
            title: 'Couleurs principales',
            content: 'Choisissez ici le preset de composants, la couleur principale et la palette de surface appliquee a l interface.'
        },
        {
            group: 'settings-apparence',
            order: 50,
            target: '[data-tour="settings-appearance.font-family"]',
            title: 'Typographie',
            content: 'Ajustez la police et la taille de texte pour harmoniser la lisibilite de l application.'
        }
    ];
}
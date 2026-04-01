export function createSettingsApparenceTour() {
    return [
        {
            group: 'settings-apparence',
            order: 10,
            target: '[data-tour="settings-appearance.main"]',
            title: 'Parametres d apparence',
            content: 'Cette page centralise la personnalisation de l interface et les reglages techniques lies au SMS.'
        },
        {
            group: 'settings-apparence',
            order: 20,
            target: '[data-tour="settings-appearance.navigation"]',
            title: 'Navigation par section',
            content: 'Utilisez la colonne de droite pour naviguer rapidement vers chaque zone de configuration.'
        },
        {
            group: 'settings-apparence',
            order: 30,
            target: '[data-tour="settings-appearance.theme"]',
            title: 'Theme et apparence',
            content: 'Ce bloc permet d ajuster le mode clair/sombre, les couleurs principales et la typographie.'
        },
        {
            group: 'settings-apparence',
            order: 40,
            target: '[data-tour="settings-appearance.sms-config"]',
            title: 'Configuration API SMS',
            content: 'Renseignez les identifiants API et testez la connexion avant de lancer les envois.'
        },
        {
            group: 'settings-apparence',
            order: 50,
            target: '[data-tour="settings-appearance.sms-templates"]',
            title: 'Templates et envois',
            content: 'Gerez les templates, previsualisez les variables et envoyez des SMS manuels si necessaire.'
        }
    ];
}
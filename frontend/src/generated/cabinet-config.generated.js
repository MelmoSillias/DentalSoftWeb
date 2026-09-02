const cabinetConfig = {
    id: 'default',
    displayName: 'Cabinet Demo',
    appTitle: 'DentalSoft - Cabinet Demo',
    brandName: 'DentalSoft',
    brandSubtitle: 'Cabinet Dentaire Demo',
    settingsTitle: 'Configuration du cabinet',
    settingsDescription: "Personnalisez l'apparence et les flux metier de DentalSoft",
    smsCabinetName: 'CABINET DEMO',
    smsTestMessage: 'Message de test CABINET DEMO.',
    reportCabinetName: 'CABINET DENTAIRE DEMO',
    cabinetPhone: '+223 XX XX XX XX / +223 XX XX XX XX',
    printProfile: {
        name: 'CABINET DENTAIRE DEMO',
        addressLines: ['BAMAKO, MALI, SIMCORP,', 'PORTE : 192; BKO MALI'],
        phones: ['82 81 90 79'],
        email: 'bamogomohamed90@gmail.com'
    },
    viteApiPrefix: 'https://testapi.mondentiste-mali.com/api',
    viteFilePrefix: 'https://testapi.mondentiste-mali.com',
    brandingAssets: {
        logo: 'logo.png',
        logoAlt: 'logo.jpeg',
        header: 'header.png',
        headerAlt: 'header.jpeg',
        headerLarge: 'header-big.jpeg',
        profile: 'profil.png',
        landingIllustration: 'landing-illustration.png',
        notificationSound: 'notification.mp3'
    },
    pwa: {
        name: 'DENTALSOFT - CABINET DEMO',
        shortName: 'DENTALSOFT',
        description: 'Application de gestion de cabinet dentaire - CABINET DEMO',
        themeColor: '#5ad6f5',
        backgroundColor: '#ffffff',
        startUrl: '/',
        display: 'standalone',
        includeAssets: ['favicon.ico', 'robots.txt', 'icons/*.svg'],
        icons: [
            {
                src: 'logo.png',
                sizes: '512x512',
                type: 'image/png'
            }
        ]
    }
};

export default cabinetConfig;

const cabinetConfig = {
    "id": "default",
    "displayName": "Cabinet Demo",
    "appTitle": "DentalSoft - Cabinet Demo",
    "brandName": "DentalSoft",
    "brandSubtitle": "Cabinet Dentaire Demo",
    "settingsTitle": "Configuration du cabinet",
    "settingsDescription": "Personnalisez l'apparence et les flux metier de DentalSoft",
    "smsCabinetName": "CABINET DEMO",
    "smsTestMessage": "Message de test CABINET DEMO.",
    "reportCabinetName": "CABINET DENTAIRE DEMO",
    "viteApiPrefix": "http://localhost:8000/api",
    "viteFilePrefix": "http://localhost:8000",
    "brandingAssets": {
        "logo": "logo.png",
        "logoAlt": "logo.jpeg",
        "header": "header.png",
        "headerAlt": "header.jpeg",
        "headerLarge": "header-big.jpeg",
        "profile": "profil.png",
        "landingIllustration": "landing-illustration.png",
        "notificationSound": "notification.mp3"
    },
    "pwa": {
        "name": "DENTALSOFT - CABINET DEMO",
        "shortName": "DENTALSOFT",
        "description": "Application de gestion de cabinet dentaire - CABINET DEMO",
        "themeColor": "#5ad6f5",
        "backgroundColor": "#ffffff",
        "startUrl": "/",
        "display": "standalone",
        "includeAssets": [
            "favicon.ico",
            "robots.txt",
            "icons/*.svg"
        ],
        "icons": [
            {
                "src": "logo.png",
                "sizes": "512x512",
                "type": "image/png"
            }
        ]
    }
};

export default cabinetConfig;

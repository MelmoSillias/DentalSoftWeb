const cabinetConfig = {
    "id": "mondentiste",
    "displayName": "Mon dentiste",
    "appTitle": "DentalSoft - Mon dentiste",
    "brandName": "DentalSoft",
    "brandSubtitle": "Mon dentiste",
    "settingsTitle": "Configuration du cabinet",
    "settingsDescription": "Personnalisez l'apparence et les flux metier de DentalSoft",
    "smsCabinetName": "Mon dentiste",
    "smsTestMessage": "Message de test Mon dentiste.",
    "reportCabinetName": "Mon dentiste",
    "cabinetPhone": "+223 71 26 30 71",
    "viteApiPrefix": "https://api.mondentiste-mali.com/api",
    "viteFilePrefix": "https://api.mondentiste-mali.com",
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
        "name": "DENTALSOFT - MON DENTISTE",
        "shortName": "DENTALSOFT",
        "description": "Application de gestion de cabinet dentaire - MON DENTISTE",
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

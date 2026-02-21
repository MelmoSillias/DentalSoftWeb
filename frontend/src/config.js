// Fichier de configuration frontend
// Exporte le préfixe de l'API. Peut être surchargé par la variable d'environnement Vite VITE_API_PREFIX
export const apiPrefix = import.meta.env.VITE_API_PREFIX || 'https://api.dentalsoft.test.cabinet-orodent.org/api';

// Préfixe pour les fichiers statiques (uploads). Peut être surchargé par VITE_FILE_PREFIX
export const filePrefix = import.meta.env.VITE_FILE_PREFIX || 'https://api.dentalsoft.test.cabinet-orodent.org';

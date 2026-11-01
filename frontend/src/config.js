// Fichier de configuration frontend
import cabinetConfig from '@/cabinetConfig';

const defaultApiPrefix = 'http://localhost:8000/api';
const defaultFilePrefix = 'http://localhost:8000';

// Préfixe API: VITE_API_PREFIX > config cabinet > fallback localhost
export const apiPrefix = import.meta.env.VITE_DOKPLOY === 'true'
    // URL absolue de la même origine : Axios ne rajoute pas sa baseURL aux
    // appels déjà préfixés, et new URL() fonctionne dans les tableaux de bord.
    ? `${window.location.origin}/api`
    : cabinetConfig.viteApiPrefix || import.meta.env.VITE_API_PREFIX || defaultApiPrefix;

// Préfixe fichiers: VITE_FILE_PREFIX > config cabinet > fallback localhost
export const filePrefix = import.meta.env.VITE_DOKPLOY === 'true'
    ? ''
    : cabinetConfig.viteFilePrefix || import.meta.env.VITE_FILE_PREFIX || defaultFilePrefix;

// Fichier de configuration frontend
import cabinetConfig from '@/cabinetConfig';

const defaultApiPrefix = 'http://localhost:8000/api';
const defaultFilePrefix = 'http://localhost:8000';

// Déploiement all-in-one (même domaine admin + API) : VITE_SAME_ORIGIN=1
const sameOrigin = import.meta.env.VITE_SAME_ORIGIN === '1';

// Préfixe API: same-origin > VITE_API_PREFIX > config cabinet > fallback localhost
export const apiPrefix = sameOrigin
	? '/api'
	: (import.meta.env.VITE_API_PREFIX
		|| cabinetConfig.viteApiPrefix
		|| defaultApiPrefix);

// Préfixe fichiers: same-origin > VITE_FILE_PREFIX > config cabinet > fallback localhost
export const filePrefix = sameOrigin
	? ''
	: (import.meta.env.VITE_FILE_PREFIX
		|| cabinetConfig.viteFilePrefix
		|| defaultFilePrefix);

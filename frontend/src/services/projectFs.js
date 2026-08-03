import { logAppError } from '@/utils/appLogger';
import * as fsService from '@/services/olders/fsService';
import { computeCalculations } from '@/services/olders/topoCalc';
import { exportTopoPDF } from '@/services/olders/topoExport';

const notify = (notifier, payload) => {
    if (typeof notifier === 'function' && payload) {
        notifier(payload);
    }
};

export const ensureFsRoot = async (notifier) => {
    try {
        const root = await fsService.getRootDirectoryHandle();
        if (!root) {
            notify(notifier, {
                severity: 'warn',
                summary: 'Dossier racine',
                detail: 'Définissez le dossier racine pour enregistrer les exports.',
                life: 3500
            });
            return false;
        }
        return true;
    } catch (e) {
        logAppError('FS root unavailable', e);
        notify(notifier, {
            severity: 'warn',
            summary: 'Dossier racine',
            detail: 'Impossible d’accéder au dossier racine.',
            life: 3500
        });
        return false;
    }
};

export const saveDefaultFiles = async (projectTitle, locality, geo, notifier) => {
    if (!geo) return;
    const ready = await ensureFsRoot(notifier);
    if (!ready) return;
    try {
        await fsService.createProjectFolder(projectTitle);
        const geoName = geo.parcelNumber || geo.title || `geo-${geo.id}`;
        await fsService.createGeoFolder(projectTitle, geoName);
        const calculations = computeCalculations(geo);
        const { blob, filename } = await exportTopoPDF({
            project: { title: projectTitle, locality },
            parcelNumber: geoName,
            sections: ['coord', 'retour', 'superficie'],
            mode: 'single',
            calculations,
            orientation: 'portrait',
            reference: geo.reference
        });
        const finalName = `defaut_${filename}`;
        await fsService.writeFileInGeo(projectTitle, geoName, finalName, blob, false);
        notify(notifier, {
            severity: 'success',
            summary: 'Export',
            detail: `PDF par défaut enregistré (${finalName}).`,
            life: 3000
        });
    } catch (e) {
        logAppError('Default export failed', e);
        notify(notifier, {
            severity: 'warn',
            summary: 'Export',
            detail: 'Impossible de générer les fichiers par défaut.',
            life: 3500
        });
    }
};

import { setConsultationFiche } from '@/services/consultations';
import { createPatientFiche, fetchLatestFiche, loadFicheMedicale } from '@/services/ficheMedicale';

const ficheLinkPromises = new Map();

export const resolveLatestFicheId = (consultation) => {
    if (!consultation) return null;
    const linked = Number(consultation.ficheId);
    if (Number.isFinite(linked) && linked > 0) return linked;
    const last = Number(consultation.lastFicheId);
    if (Number.isFinite(last) && last > 0) return last;
    return null;
};

export const resolvePatientId = (consultation) => {
    if (!consultation) return null;
    if (Number.isFinite(Number(consultation.patientId)) && Number(consultation.patientId) > 0) {
        return Number(consultation.patientId);
    }
    const patient = consultation.patient;
    if (patient && typeof patient === 'object' && Number.isFinite(Number(patient.id)) && Number(patient.id) > 0) {
        return Number(patient.id);
    }
    return null;
};

export const linkConsultationToLatestFiche = async (consultationId, token, preferredFicheId = null) => {
    if (!consultationId) return null;

    const consultKey = String(consultationId);
    if (ficheLinkPromises.has(consultKey)) {
        return ficheLinkPromises.get(consultKey);
    }

    const linkPromise = (async () => {
        const res = await setConsultationFiche(consultationId, preferredFicheId || null, token, {
            createNew: false,
            allowDuplicate: false
        });
        return res?.ficheId ?? res?.id ?? null;
    })();

    ficheLinkPromises.set(consultKey, linkPromise);
    try {
        return await linkPromise;
    } finally {
        ficheLinkPromises.delete(consultKey);
    }
};

export const loadFicheWithRecovery = async ({ ficheId = null, consultId = null, patientId = null, token } = {}) => {
    let resolvedFicheId = Number(ficheId) > 0 ? Number(ficheId) : null;

    const tryLoad = async (id) => {
        if (!id) return null;
        const data = await loadFicheMedicale(id, token);
        return { ficheId: id, data };
    };

    if (resolvedFicheId) {
        try {
            return await tryLoad(resolvedFicheId);
        } catch (error) {
            if (error?.response?.status !== 404) {
                throw error;
            }
        }
    }

    if (consultId) {
        resolvedFicheId = await linkConsultationToLatestFiche(consultId, token, null);
        if (resolvedFicheId) {
            try {
                return await tryLoad(resolvedFicheId);
            } catch (error) {
                if (error?.response?.status !== 404) {
                    throw error;
                }
            }
        }
    }

    if (patientId) {
        const latest = await fetchLatestFiche(patientId, token);
        resolvedFicheId = latest?.ficheId ?? latest?.fiche?.id ?? null;
        if (resolvedFicheId && consultId) {
            resolvedFicheId = (await linkConsultationToLatestFiche(consultId, token, resolvedFicheId)) || resolvedFicheId;
        }
        if (resolvedFicheId) {
            if (latest?.fiche && Number(latest.ficheId) === Number(resolvedFicheId)) {
                return { ficheId: resolvedFicheId, data: latest.fiche };
            }
            return await tryLoad(resolvedFicheId);
        }
    }

    throw new Error('Impossible de récupérer la fiche médicale du patient.');
};

export const openConsultationFiche = (consultation, router) => {
    if (!consultation?.id || !router) return;

    const query = {
        id: consultation.id,
        mode: 'continue'
    };
    const targetFicheId = resolveLatestFicheId(consultation);
    if (targetFicheId) {
        query.ficheId = targetFicheId;
    }

    router.push({ name: 'consultations-form', query });
};

export const createNewFicheForPatient = async (patientId, token) => {
    return createPatientFiche(patientId, token);
};

export const useFicheMedicaleAccess = () => ({
    resolveLatestFicheId,
    resolvePatientId,
    linkConsultationToLatestFiche,
    loadFicheWithRecovery,
    openConsultationFiche,
    createNewFicheForPatient
});

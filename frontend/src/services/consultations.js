import { apiPrefix } from '@/config';
import http from '@/service/http';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const normalizeConsultation = (raw = {}) => {
    const patient = raw.patient ?? null;
    const patientName = (raw.patientName ?? raw.patient_name ?? `${patient?.prenom ?? ''} ${patient?.nom ?? ''}`.trim()) || patient?.nom || '';

    const createdAt = raw.createdAt ?? raw.created_at ?? raw.date ?? raw.created_at_consultation ?? null;
    const hasFiche = raw.hasFiche ?? raw.has_fiche ?? Boolean(raw.fiche || raw.ficheId);

    const patientId = raw.patientId ?? raw.patient_id ?? patient?.id ?? null;
    const state = raw.state ?? raw.statut ?? raw.status ?? null;
    const factState = raw.factstate ?? raw.factState ?? raw.fact_state ?? null;
    const ficheId = raw.ficheId ?? raw.fiche_id ?? raw.fiche?.id ?? null;
    const ficheType = raw.ficheType ?? raw.fiche_type ?? (raw.ficheMedicale ? 'medicale' : raw.fiche ? 'observation' : null);
    const ficheVersion = raw.ficheVersion ?? raw.fiche_version ?? (ficheType === 'medicale' ? 2 : ficheType === 'observation' ? 1 : null);
    const lastFicheId = raw.lastFicheId ?? raw.last_fiche_id ?? null;
    const lastFicheType = raw.lastFicheType ?? raw.last_fiche_type ?? null;
    const lastFicheVersion = raw.lastFicheVersion ?? raw.last_fiche_version ?? (lastFicheType === 'medicale' ? 2 : lastFicheType === 'observation' ? 1 : null);

    return {
        id: raw.id,
        patient,
        patientName,
        patientPhone: patient?.telephone || patient?.phone || '',
        medecin: raw.medecin,
        createdAt,
        motif: raw.motif ?? raw.note ?? raw.noteSeance ?? '',
        statut: raw.statut ?? raw.status ?? '',
        hasFiche,
        ficheId: raw.ficheId ?? raw.fiche_id ?? raw.fiche?.id ?? null,
        fiche: raw.fiche ?? null,
        patientHasFiche: Boolean(patient?.hasFiche || patient?.fiche || patient?.ficheId),
        type: raw.type ?? null,
        patientId,
        state,
        factState,
        ficheId,
        ficheType,
        ficheVersion,
        lastFicheId,
        lastFicheType,
        lastFicheVersion
    };
};

export const fetchPendingConsultations = async (token) => {
    const res = await axios.get(`${apiPrefix}/consultations/pending`, { headers: authHeaders(token) });
    const data = Array.isArray(res.data) ? res.data : [];
    return data.map((c) => normalizeConsultation(c));
};

export const cancelConsultation = async (consultationId, token) => {
    await axios.delete(`${apiPrefix}/consultations/${consultationId}`, { headers: authHeaders(token) });
};

export const fetchConsultationsByDate = async (date, token) => {
    const res = await axios.get(`${apiPrefix}/consultations/day`, {
        headers: authHeaders(token),
        params: { date }
    });

    const payload = Array.isArray(res.data) ? res.data : Array.isArray(res.data?.data) ? res.data.data : [];
    return payload.map((c) => normalizeConsultation(c));
};

export const fetchConsultationDetails = async (consultationId, token) => {
    const headers = authHeaders(token);
    const baseUrl = apiPrefix.replace(/\/$/, '');
    const candidates = [
        `${baseUrl}/consultations/${consultationId}/details`,
        `${baseUrl}/admin/consultation/${consultationId}/details`,
        `${baseUrl.replace(/\/api$/, '')}/admin/consultation/${consultationId}/details.json`
    ];

    let lastError = null;
    for (const url of candidates) {
        try {
            const res = await axios.get(url, { headers });
            const payload = res.data ?? {};
            const data = payload.data ?? payload.consultation ?? payload;
            const actesSource = payload.actes ?? data.actes ?? [];
            const actes = Array.isArray(actesSource)
                ? actesSource.map((a) => ({
                      dent: a.dent ?? '',
                      type: a.type ?? a.designation ?? '',
                      description: a.description ?? a.designation ?? '',
                      quantite: Number(a.quantite ?? a.qty ?? a.qte ?? 0),
                      prix: Number(a.prix ?? a.montant ?? 0)
                  }))
                : [];

            return {
                id: data.id ?? consultationId,
                date: data.date ?? data.createdAt ?? data.created_at ?? null,
                patient: data.patient ?? data.patientName ?? data.patient_name ?? '',
                type: data.type ?? data.consultationType ?? data.consultation_type ?? '',
                medecin: data.medecin ?? '',
                medecinId: data.medecin?.id ?? data.medecinId ?? data.medecin_id ?? null,
                infirmier: data.infirmier ?? '',
                salle: data.salle ?? '',
                noteSeance: data.noteSeance ?? data.note ?? '',
                actes
            };
        } catch (error) {
            lastError = error;
        }
    }

    throw lastError ?? new Error('Impossible de charger les détails de la consultation');
};

export const verifyConsultationMedecinPassword = async (consultationId, password, token) => {
    const res = await axios.post(
        `${apiPrefix}/consultations/${consultationId}/verify-medecin-password`,
        { password },
        { headers: authHeaders(token) }
    );

    return Boolean(res.data?.valid);
};

export const fetchConsultationInvoice = async (consultationId, token) => {
    const res = await axios.get(`${apiPrefix}/consultations/${consultationId}/facture`, { headers: authHeaders(token) });
    const lines = Array.isArray(res.data) ? res.data : Array.isArray(res.data?.lignes) ? res.data.lignes : [];
    return lines.map((line, idx) => ({
        id: line.id || line.idLigne || idx,
        dent: line.dent ?? '',
        type: line.type ?? line.designation ?? '',
        prix: Number(line.prix ?? line.montant ?? 0),
        quantite: Number(line.quantite ?? line.qty ?? 1),
        description: line.description ?? line.designation ?? ''
    }));
};

export const updateConsultationInvoice = async (consultationId, lignes = [], token) => {
    const payload = {
        lignes: (lignes || []).map((l) => ({
            dent: l.dent || '',
            type: l.type || '',
            prix: Number(l.prix) || 0,
            quantite: Number(l.quantite) || 1,
            description: l.description || ''
        }))
    };

    const res = await axios.put(`${apiPrefix}/consultations/${consultationId}/facture/update`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const setConsultationFiche = async (consultationId, ficheId = null, token) => {
    if (!consultationId) {
        throw new Error('consultationId requis');
    }

    const suffix = ficheId ? `/${ficheId}` : '';
    try {
        const res = await axios.post(
            `${apiPrefix}/consultation/set_fiche${suffix}`,
            { consultationId },
            { headers: authHeaders(token) }
        );

        return res.data;
    } catch (err) {
        // Log full server response if present to help debugging
        try {
            // eslint-disable-next-line no-console
            console.error('setConsultationFiche error response:', err.response && err.response.data ? err.response.data : err);
        } catch (e) {
            // ignore
        }
        throw err;
    }
};

export const defaultSoinList = ['Consultation', 'Détartrage', 'Extraction', 'Remplissage', 'Composite', 'Amalgame', 'Traitement de canal', 'Traumatisme', 'Couronne', 'Blanchiment', 'Radio', 'Prothèse', 'Orthodontie', 'Chirurgie'];

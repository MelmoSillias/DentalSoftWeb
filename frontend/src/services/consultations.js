import { apiPrefix } from '@/config';
import {
    cancelConsultationTourMock,
    fetchConsultationDetailsTourMock,
    fetchConsultationInvoiceTourMock,
    fetchConsultationsByDateTourMock,
    fetchPendingConsultationsTourMock,
    isConsultationsTourMockEnabled,
    setConsultationFicheTourMock,
    updateConsultationInvoiceTourMock,
    verifyConsultationMedecinPasswordTourMock
} from '@/services/consultationsTourMock';
import http from '@/service/http';
import { is } from 'zod/v4/locales';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const normalizeDentList = (value) => {
    if (Array.isArray(value)) {
        return [...new Set(value.map((item) => String(item || '').trim()).filter(Boolean))];
    }

    if (typeof value === 'string') {
        return [...new Set(value.split(',').map((item) => item.trim()).filter(Boolean))];
    }

    if (typeof value === 'number') {
        return [String(value)];
    }

    return [];
};

const normalizeFocusPatient = (raw = {}) => ({
    id: raw.id,
    nom: raw.nom ?? '',
    prenom: raw.prenom ?? '',
    fullname: raw.fullname ?? `${raw.prenom ?? ''} ${raw.nom ?? ''}`.trim(),
    photo: raw.photo ?? raw.photoUrl ?? raw.photo_url ?? null,
    telephone: raw.telephone ?? '',
    createdAt: raw.createdAt ?? raw.created_at ?? raw.dateInscription ?? raw.date_inscription ?? null,
});

const normalizeFocusBilling = (raw = {}) => ({
    invoiceId: raw.invoiceId ?? raw.id ?? null,
    total: Number(raw.total ?? raw.montant ?? 0) || 0,
    remaining: Number(raw.remaining ?? raw.reste ?? 0) || 0,
    isPayante: Boolean(raw.isPayante ?? raw.payante ?? false),
    state: raw.state ?? { label: 'Aucune facture', severity: 'contrast' },
    lines: Array.isArray(raw.lines) ? raw.lines.map((line) => ({
        id: line.id,
        label: line.label ?? line.designation ?? 'Soin',
        quantity: Number(line.quantity ?? line.qte ?? 1) || 1,
        unitPrice: Number(line.unitPrice ?? line.montant ?? 0) || 0,
        total: Number(line.total ?? line.montantTotal ?? 0) || 0,
    })) : [],
    payments: Array.isArray(raw.payments) ? raw.payments.map((payment) => ({
        id: payment.id ?? payment.pId ?? null,
        invoiceId: payment.invoiceId ?? payment.devisId ?? raw.invoiceId ?? raw.id ?? null,
        montant: Number(payment.montant ?? 0) || 0,
        mode: payment.mode ?? null,
        date: payment.date ?? payment.createdAt ?? null,
        rolePaiement: payment.rolePaiement ?? null,
        type: payment.type ?? 'paiement',
        status: payment.status ?? 'validated',
    })) : [],
});

export const normalizeConsultation = (raw = {}) => {
    const patient = raw.patient
    const patient_photo = patient?.photo ?? patient?.photoUrl ?? patient?.patientPhoto ?? patient?.patient_photo ?? null;
    const patientName = (raw.patientName ?? raw.patient_name ?? `${patient?.prenom ?? ''} ${patient?.nom ?? ''}`.trim()) || patient?.nom || '';

    const createdAt = raw.createdAt ?? raw.created_at ?? raw.date ?? raw.created_at_consultation ?? null;
    const hasFiche = raw.hasFiche ?? raw.has_fiche ?? Boolean(raw.fiche || raw.ficheId);

    const patientId = raw.patientId ?? raw.patient_id ?? patient?.id ?? null;
    const state = raw.state ?? raw.statut ?? raw.status ?? null;
    const factState = raw.factstate ?? raw.factState ?? raw.fact_state ?? null;
    const factModifiable = raw.factModifiable ?? raw.fact_modifiable ?? false;
    const ficheId = raw.ficheId ?? raw.fiche_id ?? raw.fiche?.id ?? null;
    const lastFicheId = raw.lastFicheId ?? raw.last_fiche_id ?? null;
    const isPaid = raw.isPaid ?? raw.paid ?? raw.payee ?? false;
    const paymentId = raw.paymentId ?? raw.paiementId ?? raw.payment_id ?? raw.paiement_id ?? null;
    const paiementAmount = Number(raw.paiementAmount ?? raw.paymentAmount ?? raw.montantPaiement ?? raw.montant_paiement ?? 0) || 0;

    return {
        id: raw.id,
        patient,
        patientName,
        patientPhoto: raw.patientPhoto ?? raw.patient_photo ?? patient?.photo ?? null,
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
        isPaid,
        paymentId,
        paiementAmount,
        patientId,
        state,
        factState,
        factModifiable: Boolean(factModifiable),
        ficheId,
        lastFicheId,
    }
};

export const fetchPendingConsultations = async (token) => {
    if (isConsultationsTourMockEnabled()) {
        return fetchPendingConsultationsTourMock().map((c) => normalizeConsultation(c));
    }

    const res = await axios.get(`${apiPrefix}/consultations/pending`, { headers: authHeaders(token) });
    const data = Array.isArray(res.data) ? res.data : [];
    return data.map((c) => normalizeConsultation(c));
};

export const cancelConsultation = async (consultationId, token) => {
    if (isConsultationsTourMockEnabled()) {
        return cancelConsultationTourMock(consultationId);
    }

    await axios.delete(`${apiPrefix}/consultations/${consultationId}`, { headers: authHeaders(token) });
};

export const fetchConsultationsByDate = async (date, token) => {
    if (isConsultationsTourMockEnabled()) {
        return fetchConsultationsByDateTourMock(date).map((c) => normalizeConsultation(c));
    }

    const res = await axios.get(`${apiPrefix}/consultations/day`, {
        headers: authHeaders(token),
        params: { date }
    });

    const payload = Array.isArray(res.data) ? res.data : Array.isArray(res.data?.data) ? res.data.data : [];
    return payload.map((c) => normalizeConsultation(c));
};

export const fetchFocusReceptionData = async (date, token) => {
    if (isConsultationsTourMockEnabled()) {
        return {
            consultations: fetchConsultationsByDateTourMock(date).map((c) => normalizeConsultation(c)),
            recentPatients: [],
            billingByConsultation: {},
        };
    }

    const res = await axios.get(`${apiPrefix}/focus/reception`, {
        headers: authHeaders(token),
        params: { date }
    });

    const payload = res.data ?? {};
    const consultations = Array.isArray(payload.consultations) ? payload.consultations.map((c) => normalizeConsultation(c)) : [];
    const recentPatients = Array.isArray(payload.recentPatients) ? payload.recentPatients.map((patient) => normalizeFocusPatient(patient)) : [];
    const billingByConsultation = Object.fromEntries(
        Object.entries(payload.billingByConsultation ?? {}).map(([consultationId, billing]) => [Number(consultationId), normalizeFocusBilling(billing)])
    );

    return {
        consultations,
        recentPatients,
        billingByConsultation,
    };
};

export const fetchConsultationDetails = async (consultationId, token) => {
    if (isConsultationsTourMockEnabled()) {
        return fetchConsultationDetailsTourMock(consultationId);
    }

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
                      dent: normalizeDentList(a.dent ?? a.dents),
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
    if (isConsultationsTourMockEnabled()) {
        return verifyConsultationMedecinPasswordTourMock(consultationId, password);
    }

    const res = await axios.post(
        `${apiPrefix}/consultations/${consultationId}/verify-medecin-password`,
        { password },
        { headers: authHeaders(token) }
    );

    return Boolean(res.data?.valid);
};

export const normalizeInvoiceLine = (line = {}, idx = 0) => ({
    id: line.id || line.idLigne || idx,
    dent: normalizeDentList(line.dent ?? line.dents ?? ''),
    type: line.type ?? line.designation ?? '',
    prix: Number(line.prix ?? line.montant ?? 0),
    quantite: Number(line.quantite ?? line.qty ?? 1),
    description: line.description ?? line.designation ?? ''
});

export const parseConsultationInvoiceResponse = (data) => {
    const lines = Array.isArray(data) ? data : Array.isArray(data?.lignes) ? data.lignes : [];
    const dateFacture = data?.dateFacture ?? data?.date ?? null;
    const timeFacture = data?.timeFacture ?? data?.time ?? null;
    let date = typeof dateFacture === 'string' ? dateFacture.slice(0, 10) : '';
    let time = typeof timeFacture === 'string' ? timeFacture.slice(0, 5) : '';

    if (!date && typeof dateFacture === 'string' && dateFacture.includes(' ')) {
        const [parsedDate, parsedTime] = dateFacture.split(' ');
        date = parsedDate || '';
        time = (parsedTime || '').slice(0, 5);
    }

    return {
        lines: lines.map((line, idx) => normalizeInvoiceLine(line, idx)),
        date,
        time,
        modifiable: data?.modifiable !== false
    };
};

export const fetchConsultationInvoice = async (consultationId, token) => {
    if (isConsultationsTourMockEnabled()) {
        return fetchConsultationInvoiceTourMock(consultationId);
    }

    const res = await axios.get(`${apiPrefix}/consultations/${consultationId}/facture`, { headers: authHeaders(token) });
    return parseConsultationInvoiceResponse(res.data);
};

export const updateConsultationInvoice = async (consultationId, payload = {}, token) => {
    const lines = Array.isArray(payload) ? payload : payload?.lines ?? payload?.lignes ?? [];
    const date = Array.isArray(payload) ? null : payload?.date ?? null;
    const time = Array.isArray(payload) ? null : payload?.time ?? null;

    if (isConsultationsTourMockEnabled()) {
        return updateConsultationInvoiceTourMock(consultationId, lines, { date, time });
    }

    const requestPayload = {
        lignes: (lines || []).map((l) => ({
            dent: normalizeDentList(l.dent ?? l.dents ?? '').join(','),
            dents: normalizeDentList(l.dent ?? l.dents ?? ''),
            type: l.type || '',
            prix: Number(l.prix) || 0,
            quantite: Number(l.quantite) || 1,
            description: l.description || ''
        }))
    };

    if (date) {
        requestPayload.date = date;
    }
    if (time) {
        requestPayload.time = time;
    }

    const res = await axios.put(`${apiPrefix}/consultations/${consultationId}/facture/update`, requestPayload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const setConsultationFiche = async (consultationId, ficheId = null, token) => {
    if (!consultationId) {
        throw new Error('consultationId requis');
    }

    if (isConsultationsTourMockEnabled()) {
        return setConsultationFicheTourMock(consultationId, ficheId);
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

export const normalizeSoinList = (items) => {
    if (!Array.isArray(items)) {
        return [...defaultSoinList];
    }

    const unique = new Set();
    const clean = items
        .map((item) => String(item || '').trim())
        .filter((item) => item && !unique.has(item) && unique.add(item));

    return clean.length ? clean : [...defaultSoinList];
};

export const teethOptions = (() => {
    const options = [];
    [1, 2, 3, 4].forEach((quadrant) => {
        for (let i = 1; i <= 8; i += 1) {
            const value = `${quadrant}${i}`;
            options.push({ label: value, value });
        }
    });
    [5, 6, 7, 8].forEach((quadrant) => {
        for (let i = 1; i <= 5; i += 1) {
            const value = `${quadrant}${i}`;
            options.push({ label: value, value });
        }
    });
    return options;
})();

export const formatActeCurrency = (value) => new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
}).format(Number(value) || 0);

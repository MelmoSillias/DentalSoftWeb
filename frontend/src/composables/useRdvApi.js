import { logAppError } from '@/utils/appLogger';
import { computed, ref } from 'vue';
import { addMinutes, formatISO, isSameDay, isWithinInterval, parseISO, startOfDay } from '../utils/dateUtils';
import { fetchPatients, fetchMedecins } from '@/services/patients';
import { useAuthStore } from '@/stores/auth';
import http from '@/service/http';

// Petit utilitaire pour simuler un délai réseau
const wait = (ms = 350) => new Promise((resolve) => setTimeout(resolve, ms));

let patientsSeed = [];

const toIsoString = (value) => {
    if (!value) return null;
    if (value instanceof Date) return value.toISOString();
    if (typeof value === 'string') {
        if (value.includes('T')) return value;
        if (value.includes(' ')) return value.replace(' ', 'T');
    }
    return value;
};

const normalizeRdv = (raw = {}) => {
    const patientData = raw.patientData ?? (raw.patient && typeof raw.patient === 'object' ? raw.patient : null);
    const patientName =
        raw.patientName ||
        raw.patient ||
        patientData?.fullname ||
        patientData?.name ||
        `${patientData?.prenom ?? ''} ${patientData?.nom ?? ''}`.trim();
    const medecinName =
        raw.medecinName ||
        raw.medecin ||
        raw.medecin?.fullname ||
        raw.medecin?.name ||
        `${raw.medecin?.prenom ?? ''} ${raw.medecin?.nom ?? ''}`.trim();

    return {
        id: raw.id,
        patientId: raw.patient_id ?? raw.patientId ?? patientData?.id ?? null,
        patientName: patientName || 'Patient',
        patientPhoto: raw.patientPhoto ?? raw.patient_photo ?? patientData?.photo ?? null,
        patientData,
        medecinId: raw.medecin_id ?? raw.medecinId ?? raw.medecin?.id ?? null,
        medecinName: medecinName || 'Médecin',
        start: toIsoString(raw.start ?? raw.dateRdv ?? raw.date_rdv),
        end: toIsoString(raw.end ?? raw.endDate ?? raw.end_date),
        statut: raw.statut ?? raw.status ?? raw.etat ?? 0,
        description: raw.description ?? raw.motif ?? raw.note ?? '',
        smsReminder: raw.smsReminder ?? raw.sms_reminder ?? null
    };
};

const normalizeStats = (raw) => {
    const defaults = { pending: 0, validated: 0, postponed: 0, cancelled: 0 };
    if (!raw || typeof raw !== 'object') return defaults;

    return {
        pending: Number(raw.pending ?? 0) || 0,
        validated: Number(raw.validated ?? 0) || 0,
        postponed: Number(raw.postponed ?? 0) || 0,
        cancelled: Number(raw.cancelled ?? 0) || 0
    };
};

const extractFirstJsonObject = (value) => {
    if (typeof value !== 'string') return null;
    const source = value.trim();
    const start = source.indexOf('{');
    if (start === -1) return null;

    let depth = 0;
    let inString = false;
    let escaped = false;

    for (let i = start; i < source.length; i += 1) {
        const ch = source[i];

        if (inString) {
            if (escaped) {
                escaped = false;
            } else if (ch === '\\') {
                escaped = true;
            } else if (ch === '"') {
                inString = false;
            }
            continue;
        }

        if (ch === '"') {
            inString = true;
            continue;
        }

        if (ch === '{') {
            depth += 1;
        } else if (ch === '}') {
            depth -= 1;
            if (depth === 0) {
                return source.slice(start, i + 1);
            }
        }
    }

    return null;
};

const parseStatsPayload = (payload) => {
    if (payload && typeof payload === 'object') {
        return normalizeStats(payload);
    }

    if (typeof payload === 'string') {
        const firstJson = extractFirstJsonObject(payload);
        if (firstJson) {
            try {
                return normalizeStats(JSON.parse(firstJson));
            } catch (_) {
                return normalizeStats(null);
            }
        }
    }

    return normalizeStats(null);
};

function buildMockEvents(baseDate) {
    const day = startOfDay(baseDate);
    return [
        {
            id: 1,
            patientId: 101,
            patientName: 'Alice Dupont',
            medecinId: 1,
            medecinName: 'Dr. Martin',
            start: formatISO(addMinutes(day, 8 * 60 + 30)),
            end: formatISO(addMinutes(day, 9 * 60 + 15)),
            statut: 0,
            description: 'Contrôle annuel'
        },
        {
            id: 2,
            patientId: 102,
            patientName: 'Bruno Lefevre',
            medecinId: 2,
            medecinName: 'Dr. Lopez',
            start: formatISO(addMinutes(day, 10 * 60)),
            end: formatISO(addMinutes(day, 10 * 60 + 30)),
            statut: 1,
            description: 'Détartrage'
        },
        {
            id: 3,
            patientId: 103,
            patientName: 'Claire Moreau',
            medecinId: 3,
            medecinName: 'Dr. Kim',
            start: formatISO(addMinutes(day, 14 * 60 + 15)),
            end: formatISO(addMinutes(day, 15 * 60)),
            statut: -1,
            description: 'Consultation post-op'
        }
    ];
}

export function useRdvApi() {
    const medecins = ref([]);
    const rdvs = ref(buildMockEvents(new Date()));
    const loading = ref(false);
    const error = ref(null);

    // Charger medecins et patients depuis les APIs (utilise le token du store si présent)
    (async () => {
        try {
            const auth = useAuthStore();
            const token = auth?.token || localStorage.getItem('token');
            const [meds, pts] = await Promise.all([
                fetchMedecins(token),
                fetchPatients(token, { page: 1, limit: 20 })
            ]);
            medecins.value = Array.isArray(meds)
                ? meds.map((m) => ({
                      id: m.id,
                      name: m.name ?? m.fullName ?? m.fullname ?? `${m.prenom ?? ''} ${m.nom ?? ''}`.trim() ?? m.nom ?? 'Médecin'
                  }))
                : [];
            const items = Array.isArray(pts?.items) ? pts.items : [];
            patientsSeed = items.map((p) => ({ id: p.id, name: p.fullname ?? `${p.prenom ?? ''} ${p.nom ?? ''}`.trim() }));
        } catch (e) {
            // ne pas interrompre l'utilisation mock si l'API échoue
            logAppError('Erreur chargement medecins/patients:', e);
        }
    })();

    const stats = computed(() => {
        const count = { pending: 0, validated: 0, postponed: 0, cancelled: 0 };
        rdvs.value.forEach((rdv) => {
            if (rdv.statut === 0) count.pending += 1;
            else if (rdv.statut === 1) count.validated += 1;
            else if (rdv.statut === -1) count.postponed += 1;
            else if (rdv.statut === -2) count.cancelled += 1;
        });
        return count;
    });

    const getNextId = () => (rdvs.value.length ? Math.max(...rdvs.value.map((r) => r.id)) + 1 : 1);

    const fetchEvents = async (filters = {}) => {
        loading.value = true;
        error.value = null;
        try {
            const { start, end, medecinId, patientQuery } = filters;

            // L'API /rdvs exige start/end. Si indisponible, on évite l'appel.
            if (!start || !end) {
                return [];
            }

            const params = {
                start: start instanceof Date ? start.toISOString() : start,
                end: end instanceof Date ? end.toISOString() : end
            };

            if (medecinId) {
                params.medecin = medecinId;
            }

            const res = await http.get('rdvs', { params });
            const data = Array.isArray(res.data) ? res.data : [];
            let matches = data.map(normalizeRdv);

            if (patientQuery) {
                const query = patientQuery.toLowerCase();
                matches = matches.filter((rdv) => (rdv.patientName || '').toLowerCase().includes(query));
            }

            rdvs.value = matches;
            return matches;
        } catch (err) {
            error.value = 'Impossible de charger les rendez-vous.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const fetchEventsByDay = async (day, medecinId) => {
        const target = day instanceof Date ? day : new Date(day);
        const dateStr = target.toISOString().slice(0, 10);
        loading.value = true;
        error.value = null;
        try {
            const params = {};
            if (medecinId) params.medecin = medecinId;
            const res = await http.get(`rdvs/${dateStr}`, { params });
            const data = Array.isArray(res.data) ? res.data : [];
            const normalized = data.map(normalizeRdv);
            rdvs.value = normalized;
            return normalized;
        } catch (err) {
            error.value = 'Impossible de charger les rendez-vous.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const fetchStats = async (day, medecinId) => {
        const target = day instanceof Date ? day : new Date(day);
        const dateStr = target.toISOString().slice(0, 10);
        const params = { date: dateStr };
        if (medecinId) params.medecin = medecinId;
        const res = await http.get('rdv/stats', { params });
        return parseStatsPayload(res.data);
    };

    const searchPatients = async (query) => {
        await wait(150);
        const term = (query || '').trim();
        if (!term && patientsSeed.length) return patientsSeed;

        try {
            const res = await http.get('patients/search', { params: { q: term, limit: 20 } });
            const results = Array.isArray(res.data?.results) ? res.data.results : [];
            const mapped = results.map((p) => ({
                id: p.id,
                name: p.fullname || `${p.prenom ?? ''} ${p.nom ?? ''}`.trim() || p.nom || 'Patient'
            }));
            if (!term) patientsSeed = mapped;
            return mapped;
        } catch (err) {
            if (!term) return patientsSeed;
            return patientsSeed.filter((p) => p.name.toLowerCase().includes(term.toLowerCase()));
        }
    };

    const createRdv = async (payload) => {
        loading.value = true;
        try {
            const startDate = payload.start ? new Date(payload.start) : null;
            const endDate = payload.end ? new Date(payload.end) : null;
            const duration = payload.duration ?? (startDate && endDate ? Math.round((endDate - startDate) / 60000) : 30);

            const requestPayload = {
                patient_id: payload.patientId ?? payload.patient_id ?? payload.patient?.id ?? null,
                medecin_id: payload.medecinId ?? payload.medecin_id ?? payload.medecin?.id ?? null,
                date: startDate ? startDate.toISOString().slice(0, 10) : payload.date,
                time: startDate ? startDate.toISOString().slice(11, 16) : payload.time,
                description: payload.description ?? '',
                duration
            };

            const res = await http.post('rdv/create', requestPayload);
            const data = res.data || {};
            const entry = normalizeRdv(data.id || data.patient || data.medecin ? data : { ...payload, ...data, statut: 0 });

            if (!entry.id) {
                entry.id = getNextId();
            }

            rdvs.value = [...rdvs.value, entry];
            return entry;
        } finally {
            loading.value = false;
        }
    };

    const validateRdv = async (id, medecinId, options = {}) => {
        const payload = {
            create_consultation: options.createConsultation === true
        };
        if (medecinId != null && medecinId !== '') {
            payload.medecin = medecinId;
        }
        await http.post(`rdv/${id}/validate`, payload);
        rdvs.value = rdvs.value.map((rdv) =>
            rdv.id === id
                ? {
                      ...rdv,
                      medecinId: payload.medecin ?? rdv.medecinId,
                      statut: 1
                  }
                : rdv
        );
    };

    const cancelRdv = async (id) => {
        await http.post(`rdv/${id}/cancel`);
        rdvs.value = rdvs.value.map((rdv) => (rdv.id === id ? { ...rdv, statut: -2 } : rdv));
    };

    const reportRdv = async (id, payload) => {
        const startDate = payload.start ? new Date(payload.start) : null;
        const endDate = payload.end ? new Date(payload.end) : null;
        const duration = payload.duration ?? (startDate && endDate ? Math.round((endDate - startDate) / 60000) : 30);

        await http.post(`rdv/${id}/report`, {
            new_date: startDate ? startDate.toISOString().slice(0, 10) : payload.date,
            new_time: startDate ? startDate.toISOString().slice(11, 16) : payload.time,
            new_duration: duration,
            new_medecin: payload.medecinId ?? payload.medecin_id ?? payload.medecin?.id ?? null
        });

        rdvs.value = rdvs.value.map((rdv) =>
            rdv.id === id
                ? {
                      ...rdv,
                      medecinId: payload.medecinId || rdv.medecinId,
                      start: payload.start || rdv.start,
                      end: payload.end || rdv.end,
                      statut: -1
                  }
                : rdv
        );
    };

    return {
        loading,
        error,
        medecins,
        rdvs,
        stats,
        fetchEvents,
        fetchEventsByDay,
        fetchStats,
        searchPatients,
        createRdv,
        validateRdv,
        cancelRdv,
        reportRdv
    };
}

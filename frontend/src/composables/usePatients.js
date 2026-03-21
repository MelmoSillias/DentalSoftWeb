import { apiPrefix } from '@/config';
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth'; 
import http from '@/service/http';

const auth = useAuthStore();
const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

const patients = ref([]);
const totalRecords = ref(0);
const loading = ref(false);
const error = ref(null);
const patientDossier = ref(null);

export function usePatients() {

    function buildAuthHeaders(includeJson = false) {
        const token = auth?.token || localStorage.getItem('token') || localStorage.getItem('authToken');
        const headers = {};
        if (includeJson) headers['Content-Type'] = 'application/json';
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return headers;
    }


    const normalizePatient = (raw = {}) => ({
        id: raw.id,
        nom: raw.nom ?? '',
        prenom: raw.prenom ?? '',
        fullname: raw.fullname ?? '',
        age: raw.age ?? null,
        dateNaissance: raw.dateNaissance ?? raw.date_naissance ?? '',
        sexe: raw.sexe ?? '',
        telephone: raw.telephone ?? '',
        email: raw.email ?? '',
        adresse: raw.adresse ?? '',
        profession: raw.profession ?? '',
        lieuNaissance: raw.lieuNaissance ?? raw.lieu_naissance ?? '',
        groupeSanguin: raw.groupeSanguin ?? raw.groupe_sanguin ?? '',
        notes: raw.notes ?? '',
        contactUrgence: raw.contactUrgence ?? raw.contact_urgence ?? null,
        smsPreferences: raw.smsPreferences ?? {
            patientCreated: raw.smsPatientCreated ?? false,
            receipt: raw.smsReceipt ?? false,
            ticket: raw.smsTicket ?? false,
            invoice: raw.smsInvoice ?? false,
            appointmentReminder: raw.smsAppointmentReminder ?? false,
            unsubscribed: raw.smsUnsubscribed ?? false,
            blacklisted: raw.smsBlacklisted ?? false
        },
        derniereConsultation: raw.derniereConsultation ?? raw.derniere_consultation ?? null
    });

    const normalizePatientDossier = (raw = {}) => {
        const dossier = raw || {};
        const patient = dossier.patient || dossier.patientInfo || dossier.infoPatient || dossier || {};
        const nom = patient.nom ?? '';
        const prenom = patient.prenom ?? '';
        const initials = `${prenom?.[0] ?? ''}${nom?.[0] ?? ''}`.toUpperCase() || '--';
        const stats = dossier.stats || dossier.statistiques || {};
        const contactUrgence = dossier.contactUrgence || patient.contactUrgence || patient.contact_urgence || null;
        const allergies = Array.isArray(dossier.allergies) ? dossier.allergies : [];
        const antecedents = Array.isArray(dossier.antecedents) ? dossier.antecedents : [];
        const rdvs = Array.isArray(dossier.rdvs) ? dossier.rdvs : [];
        const fiches = (Array.isArray(dossier.fiches) ? dossier.fiches : [])
            .map((fiche) => {
                const dateCreation = fiche?.dateCreation ?? fiche?.createdAt ?? fiche?.date ?? null;
                return { ...fiche, dateCreation, version: 2 };
            })
            .sort((a, b) => {
                const aTime = a?.dateCreation ? new Date(a.dateCreation).getTime() : 0;
                const bTime = b?.dateCreation ? new Date(b.dateCreation).getTime() : 0;
                return bTime - aTime;
            });
        const paiements = Array.isArray(dossier.paiements) ? dossier.paiements : [];
        const factures = Array.isArray(dossier.factures) ? dossier.factures : [];

        return {
            id: patient.id ?? dossier.patientId ?? dossier.id ?? null,
            nom,
            prenom,
            initials,
            numeroDossier: patient.numeroDossier ?? dossier.numeroDossier ?? dossier.code ?? '--',
            dateNaissance: patient.dateNaissance ?? patient.date_naissance ?? dossier.dateNaissance ?? '--',
            age: patient.age ?? dossier.age ?? '--',
            sexe: patient.sexe ?? dossier.sexe ?? '--',
            groupeSanguin: patient.groupeSanguin ?? patient.groupe_sanguin ?? dossier.groupeSanguin ?? '--',
            telephone: patient.telephone ?? dossier.telephone ?? '--',
            email: patient.email ?? dossier.email ?? '--',
            profession: patient.profession ?? dossier.profession ?? '--',
            lieuNaissance: patient.lieuNaissance ?? dossier.lieuNaissance ?? dossier.lieu_naissance ?? '--',
            adresse: patient.adresse ?? dossier.adresse ?? '--',
            contactUrgence,
            smsPreferences: patient.smsPreferences ?? dossier.smsPreferences ?? {
                patientCreated: false,
                receipt: false,
                ticket: false,
                invoice: false,
                appointmentReminder: false,
                unsubscribed: false,
                blacklisted: false
            },
            allergies,
            antecedents,
            stats: {
                fiches: stats.fiches ?? stats.ficheCount ?? 0,
                rdv: stats.rdv ?? stats.rdvCount ?? 0,
                hospitalisations: stats.hospitalisations ?? stats.hosp ?? 0,
                urgences: stats.urgences ?? stats.urg ?? 0
            },
            rdvs,
            fiches,
            paiements,
            factures,
            raw: dossier
        };
    };

    const fetchPatients = async (token, { page = 1, limit = 10, q = '', sortField = null, sortOrder = null } = {}) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.get(`${apiPrefix}/patients`, {
                headers: buildAuthHeaders(true),
                params: { page, limit, q, sortField, sortOrder }
            });
            const data = res.data || {};
            const list = Array.isArray(data.items) ? data.items.map(normalizePatient) : [];
            patients.value = list;
            totalRecords.value = data.total ?? list.length;
            return { ...data, items: list };
        } catch (err) {
            error.value = err;
            return { items: [], total: 0, page, limit };
        } finally {
            loading.value = false;
        }
    };

    const fetchPatientById = async (patientId, token) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.get(`${apiPrefix}/patient/${patientId}`, {
                headers: buildAuthHeaders(true)
            });
            const data = res.data;
            const normalized = normalizePatient(data);
            return { ...data, ...normalized };
        } catch (err) {
            error.value = err;
            return null;
        } finally {
            loading.value = false;
        }
    };

    const createPatient = async (payload, token) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.post(`${apiPrefix}/patient/add`, payload, {
                headers: buildAuthHeaders(true)
            });
            const data = res.data;
            return normalizePatient(data);
        } catch (err) {
            error.value = err;
            return null;
        } finally {
            loading.value = false;
        }
    };

    const fetchPatientsByMedecin = async (token, { page = 1, limit = 10, q = '', sortField = null, sortOrder = null } = {}) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.get(`${apiPrefix}/patients/medecin`, {
                headers: buildAuthHeaders(true),
                params: { page, limit, q, sortField, sortOrder }
            });
            const data = res.data || {};
            const list = Array.isArray(data.items) ? data.items.map(normalizePatient) : [];
            patients.value = list;
            totalRecords.value = data.total ?? list.length;
            return { ...data, items: list };
        } catch (err) {
            error.value = err;
            return { items: [], total: 0, page, limit };
        } finally {
            loading.value = false;
        }
    };

    const updatePatient = async (patientId, payload, token) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.post(`${apiPrefix}/patient/${patientId}/update`, payload, {
                headers: buildAuthHeaders(true)
            });
            const data = res.data;
            return normalizePatient(data);
        } catch (err) {
            error.value = err;
            return null;
        } finally {
            loading.value = false;
        }
    };

    const searchPatients = async (query, token, limit = 20) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.get(`${apiPrefix}/patients/search`, {
                headers: buildAuthHeaders(true),
                params: { q: query, limit }
            });
            const results = Array.isArray(res.data?.results) ? res.data.results : [];
            const list = results.map(normalizePatient);
            patients.value = list;
            totalRecords.value = list.length;
            return list;
        } catch (err) {
            error.value = err;
            return [];
        } finally {
            loading.value = false;
        }
    };

    const fetchPatientDossier = async (patientId, token) => {
        loading.value = true;
        error.value = null;

        try {
            const res = await http.get(`${apiPrefix}/patient/${patientId}/dossier`, {
                headers: buildAuthHeaders(true)
            });
            const data = res.data;
            patientDossier.value = normalizePatientDossier(data);
            return patientDossier.value;
        } catch (err) {
            error.value = err;
            return null;
        } finally {
            loading.value = false;
        }
    };

    const fetchPatientConsultations = async (patientId, token) => {
        loading.value = true;
        error.value = null;

        try {
            const res = await http.get(`${apiPrefix}/patient/${patientId}/consultations`, {
                headers: buildAuthHeaders(true)
            });
            const data = res.data;
            return Array.isArray(data) ? data : [];
        } catch (err) {
            error.value = err;
            return [];
        } finally {
            loading.value = false;
        }
    };

    const updatePatientDossier = async (patientId, payload, token) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.put(`${apiPrefix}/patient/${patientId}/dossier/update`, payload, {
                headers: buildAuthHeaders(true)
            });
            return res.data;
        } catch (err) {
            error.value = err;
            return null;
        } finally {
            loading.value = false;
        }
    };

    const createConsultationForPatient = async (patientId, payload, token) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.post(`${apiPrefix}/patient/${patientId}/consultation/create`, payload, {
                headers: buildAuthHeaders(true)
            });
            return res.data;
        } catch (err) {
            error.value = err;
            return null;
        } finally {
            loading.value = false;
        }
    };

    const checkConsultationActive = async (patientId, token) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.get(`${apiPrefix}/patient/${patientId}/consultation-en-cours`, {
                headers: buildAuthHeaders(true)
            });
            return res.data;
        } catch (err) {
            error.value = err;
            return null;
        } finally {
            loading.value = false;
        }
    };

    const deleteConsultation = async (consultationId, token) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.delete(`${apiPrefix}/consultations/${consultationId}`, {
                headers: buildAuthHeaders(true)
            });
            return res.data;
        } catch (err) {
            error.value = err;
            return null;
        } finally {
            loading.value = false;
        }
    };

    const fetchMedecins = async (token) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.get(`${apiPrefix}/medecins`, {
                headers: buildAuthHeaders(true)
            });
            const data = res.data;
            return Array.isArray(data) ? data : [];
        } catch (err) {
            error.value = err;
            return [];
        } finally {
            loading.value = false;
        }
    };

    const fetchPaymentMethods = async (token) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.get(`${apiPrefix}/payment-methods`, {
                headers: buildAuthHeaders(true)
            });
            const data = res.data;
            return Array.isArray(data) ? data : [];
        } catch (err) {
            error.value = err;
            return [];
        } finally {
            loading.value = false;
        }
    };

    const createRdvForPatient = async (patientId, payload, token) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.post(
                `${apiPrefix}/patient/${patientId}/rdv/create`,
                { ...payload, patient_id: patientId },
                { headers: buildAuthHeaders(true) }
            );
            return res.data;
        } catch (err) {
            error.value = err;
            return null;
        } finally {
            loading.value = false;
        }
    };

    const printPatientInfosPerso = async (patientId, token) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.get(`${apiPrefix}/patient/${patientId}/dossier/print/infosperso`, {
                headers: buildAuthHeaders(true),
                responseType: 'blob'
            });
            return res.data;
        } catch (err) {
            error.value = err;
            return null;
        } finally {
            loading.value = false;
        }
    };

    const printPatientFiche = async (patientId, ficheId, token) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.get(`${apiPrefix}/patient/${patientId}/fiche/${ficheId}/print`, {
                headers: buildAuthHeaders(true),
                responseType: 'blob'
            });
            return res.data;
        } catch (err) {
            error.value = err;
            return null;
        } finally {
            loading.value = false;
        }
    };

    
    return {
        patients,
        patientDossier,
        totalRecords,
        loading,
        error,
        normalizePatient,
        normalizePatientDossier,
        fetchPatients,
        fetchPatientById,
        createPatient,
        fetchPatientsByMedecin,
        updatePatient,
        searchPatients,
        fetchPatientDossier,
        fetchPatientConsultations,
        updatePatientDossier,
        createConsultationForPatient,
        checkConsultationActive,
        deleteConsultation,
        fetchMedecins,
        fetchPaymentMethods,
        createRdvForPatient,
        printPatientInfosPerso,
        printPatientFiche
    };
}

import { apiPrefix } from '@/config';
import {
    createPatientPortalUser,
    fetchPatientPortalUser,
    resetPatientPortalPassword,
    togglePatientPortalUser
} from '@/services/patients';
import {
    addPatientAllergyTourMock,
    addPatientAntecedentTourMock,
    checkConsultationActiveTourMock,
    createConsultationForPatientTourMock,
    createPatientTourMock,
    createRdvForPatientTourMock,
    deletePatientAllergyTourMock,
    deletePatientAntecedentTourMock,
    deleteConsultationTourMock,
    fetchMedecinsTourMock,
    fetchPatientConsultationsTourMock,
    fetchPatientDossierTourMock,
    fetchPatientByIdTourMock,
    fetchPaymentMethodsTourMock,
    isPatientsTourMockEnabled,
    listPatientsTourMock,
    searchPatientsTourMock,
    updatePatientTourMock
} from '@/services/patientsTourMock';
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import http from '@/service/http';

const auth = useAuthStore();
const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

const defaultInsuranceFormData = () => ({
    societe: '',
    assureNom: '',
    assureNumero: '',
    beneficiaireNom: '',
    beneficiaireNumero: '',
    sexe: '',
    souscripteur: '',
    salarieNomPrenom: '',
    salarieMatricule: '',
    patientNomPrenom: '',
    patientMatricule: '',
    patientAge: '',
    patientSexe: ''
});

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

    const extractPatientPhoto = (raw = {}) => raw.photo ?? raw.photoUrl ?? raw.photo_url ?? raw.patientPhoto ?? raw.patient_photo ?? null;

    const resolveInsuranceProfile = (value = {}) => {
        const insuranceProfile = value?.insuranceProfile ?? value?.assuranceProfile ?? null;
        if (!insuranceProfile) {
            return null;
        }

        const assurance = insuranceProfile.assurance ?? value?.assurance ?? null;
        const assuranceCode = insuranceProfile.assuranceCode
            ?? insuranceProfile.assurance_code
            ?? insuranceProfile.code
            ?? assurance?.code
            ?? '';
        const assuranceId = insuranceProfile.assuranceId
            ?? insuranceProfile.assurance_id
            ?? assurance?.id
            ?? null;
        const assuranceName = insuranceProfile.assuranceName
            ?? insuranceProfile.assurance_name
            ?? assurance?.nom
            ?? assurance?.libelle
            ?? assuranceCode
            ?? 'Assurance';
        const rawFormData = insuranceProfile.formData ?? insuranceProfile.form_data ?? {};

        return {
            enabled: Boolean(insuranceProfile.enabled ?? assuranceCode ?? assuranceId),
            assuranceCode,
            assuranceId,
            coverageRate: Number(insuranceProfile.coverageRate ?? insuranceProfile.coverage_rate ?? 0) || 0,
            assurance: assurance || (assuranceCode || assuranceId ? {
                id: assuranceId,
                code: assuranceCode,
                nom: assuranceName
            } : null),
            formData: {
                ...defaultInsuranceFormData(),
                ...(rawFormData || {})
            }
        };
    };


    const normalizePatient = (raw = {}) => ({
        id: raw.id,
        nom: raw.nom ?? '',
        prenom: raw.prenom ?? '',
        fullname: raw.fullname ?? '',
        photo: extractPatientPhoto(raw),
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
        deletedAt: raw.deletedAt ?? raw.deleted_at ?? null,
        contactUrgence: raw.contactUrgence ?? raw.contact_urgence ?? null,
        insuranceProfile: resolveInsuranceProfile(raw),
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
        const insuranceProfile = resolveInsuranceProfile(patient) ?? resolveInsuranceProfile(dossier);
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
            photo: extractPatientPhoto(patient) ?? extractPatientPhoto(dossier),
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
            insuranceProfile,
            smsPreferences: patient.smsPreferences ?? dossier.smsPreferences ?? {
                patientCreated: false,
                receipt: false,
                ticket: false,
                invoice: false,
                appointmentReminder: false,
                unsubscribed: false,
                blacklisted: false
            },
            portalAccount: dossier.portalAccount ?? null,
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
            const data = isPatientsTourMockEnabled()
                ? listPatientsTourMock({ page, limit, q, sortField, sortOrder })
                : (await http.get(`${apiPrefix}/patients`, {
                    headers: buildAuthHeaders(true),
                    params: { page, limit, q, sortField, sortOrder }
                })).data || {};

            const list = Array.isArray(data.items) ? data.items.map(normalizePatient) : [];
            patients.value = list;
            totalRecords.value = data.total ?? list.length;
            return { ...data, items: list };
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const fetchPatientById = async (patientId, token) => {
        loading.value = true;
        error.value = null;
        try {
            const data = isPatientsTourMockEnabled()
                ? fetchPatientByIdTourMock(patientId)
                : (await http.get(`${apiPrefix}/patient/${patientId}`, {
                    headers: buildAuthHeaders(true)
                })).data;
            const normalized = normalizePatient(data);
            return { ...data, ...normalized };
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const createPatient = async (payload, token) => {
        loading.value = true;
        error.value = null;
        try {
            const data = isPatientsTourMockEnabled()
                ? createPatientTourMock(payload)
                : (await http.post(`${apiPrefix}/patient/add`, payload, {
                    headers: buildAuthHeaders(true)
                })).data;
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
            const data = isPatientsTourMockEnabled()
                ? listPatientsTourMock({ page, limit, q, sortField, sortOrder })
                : (await http.get(`${apiPrefix}/patients/medecin`, {
                    headers: buildAuthHeaders(true),
                    params: { page, limit, q, sortField, sortOrder }
                })).data || {};
            const list = Array.isArray(data.items) ? data.items.map(normalizePatient) : [];
            patients.value = list;
            totalRecords.value = data.total ?? list.length;
            return { ...data, items: list };
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const updatePatient = async (patientId, payload, token) => {
        loading.value = true;
        error.value = null;
        try {
            const isFormData = payload instanceof FormData;
            const data = isPatientsTourMockEnabled()
                ? updatePatientTourMock(patientId, payload)
                : (await http.post(`${apiPrefix}/patient/${patientId}/update`, payload, {
                    headers: isFormData ? buildAuthHeaders(false) : buildAuthHeaders(true)
                })).data;
            return normalizePatient(data.patient ?? data);
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
            let results = [];
            if (isPatientsTourMockEnabled()) {
                results = searchPatientsTourMock(query, limit);
            } else {
                const res = await http.get(`${apiPrefix}/patients/search`, {
                    headers: buildAuthHeaders(true),
                    params: { q: query, limit }
                });
                results = Array.isArray(res.data?.results) ? res.data.results : [];
            }
            const list = results.map(normalizePatient);
            patients.value = list;
            totalRecords.value = list.length;
            return list;
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const fetchPatientDossier = async (patientId, token) => {
        loading.value = true;
        error.value = null;

        try {
            const data = isPatientsTourMockEnabled()
                ? fetchPatientDossierTourMock(patientId)
                : (await http.get(`${apiPrefix}/patient/${patientId}/dossier`, {
                    headers: buildAuthHeaders(true)
                })).data;
            patientDossier.value = normalizePatientDossier(data);
            return patientDossier.value;
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const fetchPatientConsultations = async (patientId, token) => {
        loading.value = true;
        error.value = null;

        try {
            const data = isPatientsTourMockEnabled()
                ? fetchPatientConsultationsTourMock(patientId)
                : (await http.get(`${apiPrefix}/patient/${patientId}/consultations`, {
                    headers: buildAuthHeaders(true)
                })).data;
            return Array.isArray(data) ? data : [];
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const addPatientAntecedent = async (patientId, payload, token) => {
        loading.value = true;
        error.value = null;
        try {
            if (isPatientsTourMockEnabled()) {
                return addPatientAntecedentTourMock(patientId, payload);
            }

            const res = await http.post(`${apiPrefix}/patient/${patientId}/antecedents`, payload, {
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

    const deletePatientAntecedent = async (patientId, antecedentId, token) => {
        loading.value = true;
        error.value = null;
        try {
            if (isPatientsTourMockEnabled()) {
                return deletePatientAntecedentTourMock(patientId, antecedentId);
            }

            const res = await http.delete(`${apiPrefix}/patient/${patientId}/antecedents/${antecedentId}`, {
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

    const addPatientAllergy = async (patientId, payload, token) => {
        loading.value = true;
        error.value = null;
        try {
            if (isPatientsTourMockEnabled()) {
                return addPatientAllergyTourMock(patientId, payload);
            }

            const res = await http.post(`${apiPrefix}/patient/${patientId}/allergies`, payload, {
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

    const deletePatientAllergy = async (patientId, allergyId, token) => {
        loading.value = true;
        error.value = null;
        try {
            if (isPatientsTourMockEnabled()) {
                return deletePatientAllergyTourMock(patientId, allergyId);
            }

            const res = await http.delete(`${apiPrefix}/patient/${patientId}/allergies/${allergyId}`, {
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
            if (isPatientsTourMockEnabled()) {
                return createConsultationForPatientTourMock(patientId, payload);
            }

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
            if (isPatientsTourMockEnabled()) {
                return checkConsultationActiveTourMock(patientId);
            }

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
            if (isPatientsTourMockEnabled()) {
                return deleteConsultationTourMock(consultationId);
            }

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

    const deletePatient = async (patientId, token) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.delete(`${apiPrefix}/patient/${patientId}`, {
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

    const fetchPatientsTrash = async (token, { page = 1, limit = 10, q = '' } = {}) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.get(`${apiPrefix}/patients/trash`, {
                headers: buildAuthHeaders(true),
                params: { page, limit, q }
            });

            const data = res.data || {};
            const list = Array.isArray(data.items) ? data.items.map(normalizePatient) : [];

            return {
                ...data,
                items: list,
                total: data.total ?? list.length,
            };
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const restorePatient = async (patientId, token) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.patch(`${apiPrefix}/patient/${patientId}/restore`, {}, {
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
            const data = isPatientsTourMockEnabled()
                ? fetchMedecinsTourMock()
                : (await http.get(`${apiPrefix}/medecins`, {
                    headers: buildAuthHeaders(true)
                })).data;
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
            const data = isPatientsTourMockEnabled()
                ? fetchPaymentMethodsTourMock()
                : (await http.get(`${apiPrefix}/payment-methods`, {
                    headers: buildAuthHeaders(true)
                })).data;
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
            if (isPatientsTourMockEnabled()) {
                return createRdvForPatientTourMock(patientId, payload);
            }

            const res = await http.post(
                `${apiPrefix}/patient/${patientId}/rdv/create`,
                { ...payload, patient_id: patientId },
                {
                    headers: buildAuthHeaders(true)
                }
            );
            return res.data;
        } catch (err) {
            error.value = err;
            return null;
        } finally {
            loading.value = false;
        }
    };

    const fetchPortalAccount = async (patientId, token) => {
        loading.value = true;
        error.value = null;
        try {
            const data = await fetchPatientPortalUser(patientId, token || auth?.token);
            return data?.account ?? null;
        } catch (err) {
            error.value = err;
            return null;
        } finally {
            loading.value = false;
        }
    };

    const createPortalAccount = async (patientId, token) => {
        loading.value = true;
        error.value = null;
        try {
            const data = await createPatientPortalUser(patientId, token || auth?.token);
            return data?.account ?? null;
        } catch (err) {
            error.value = err;
            return null;
        } finally {
            loading.value = false;
        }
    };

    const resetPortalAccountPassword = async (patientId, token) => {
        loading.value = true;
        error.value = null;
        try {
            const data = await resetPatientPortalPassword(patientId, token || auth?.token);
            return data?.account ?? null;
        } catch (err) {
            error.value = err;
            return null;
        } finally {
            loading.value = false;
        }
    };

    const togglePortalAccountActive = async (patientId, active, token) => {
        loading.value = true;
        error.value = null;
        try {
            const data = await togglePatientPortalUser(patientId, active, token || auth?.token);
            return data?.account ?? null;
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
        addPatientAntecedent,
        deletePatientAntecedent,
        addPatientAllergy,
        deletePatientAllergy,
        updatePatientDossier,
        createConsultationForPatient,
        checkConsultationActive,
        deleteConsultation,
        deletePatient,
        fetchPatientsTrash,
        restorePatient,
        fetchMedecins,
        fetchPaymentMethods,
        createRdvForPatient,
        fetchPortalAccount,
        createPortalAccount,
        resetPortalAccountPassword,
        togglePortalAccountActive,
        printPatientInfosPerso,
        printPatientFiche
    };
}

import PrintDossierBody from '@/components/print/PrintDossierBody.vue';
import PrintFicheV2Body from '@/components/print/PrintFicheV2Body.vue';
import { usePrinter } from '@/composables/usePrinter';
import { usePatients } from '@/composables/usePatients';
import { computeAgeYears } from '@/utils/formuleDentaireLayout';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { addPatientAllergy, addPatientAntecedent, deletePatientAllergy, deletePatientAntecedent } from '@/services/patients';
import { fetchPatientDossierPrintData, fetchPatientFichePrintData } from '@/services/printService';
import { useAuthStore } from '@/stores/auth';
import { logAppError } from '@/utils/appLogger';
import { useToast } from 'primevue/usetoast';
import { computed, ref, unref } from 'vue';

export const DOSSIER_PRINT_SECTION_OPTIONS = [
    { key: 'entretien', label: 'Questionnaire médical' },
    { key: 'examens', label: 'Examen' },
    { key: 'images', label: 'Images et documents' },
    { key: 'plan', label: 'Plan de traitement' },
    { key: 'bilan', label: 'Bilan dentaire' },
    { key: 'seances', label: 'Seances passees' }
];

/**
 * Shared patient dossier load + action handlers (page + Focus dialog).
 * @param {object} [options]
 * @param {import('vue').MaybeRefOrGetter<number|null>} [options.patientId]
 * @param {() => number|null} [options.getPatientId] Override displayed patient id (e.g. guided tour)
 * @param {() => void} [options.onUpdated] Called after mutations that may need parent refresh
 */
export function usePatientDossier(options = {}) {
    const patientStore = usePatients();
    const toast = useToast();
    const { printComponent } = usePrinter();
    const auth = useAuthStore();
    const token = localStorage.getItem('token');

    const patient = ref(patientStore.normalizePatientDossier());
    const consultations = ref([]);
    const consultationsLoading = ref(false);
    const dossierLoading = ref(false);
    const loadErrorMessage = ref('');
    let dossierLoadSeq = 0;

    const showRdvDialog = ref(false);
    const showConsultationDialog = ref(false);
    const showEditDialog = ref(false);
    const showAntecedentDialog = ref(false);
    const showAllergyDialog = ref(false);
    const savingAntecedent = ref(false);
    const savingAllergy = ref(false);
    const showPrintDialog = ref(false);
    const selectedFicheForPrint = ref(null);
    const printIncludeEmpty = ref(false);
    const printSections = ref([]);
    const showActiveConsultWarn = ref(false);
    const activeConsultInfo = ref({ hasActive: false, consultationId: null, hasFiche: false });
    const patientEditFormRef = ref(null);

    const hidePatientDossierForMedecins = ref(false);
    const hidePatientPhoneForMedecins = ref(false);

    const printSectionOptions = DOSSIER_PRINT_SECTION_OPTIONS;

    const fiches = computed(() => patient.value.fiches || []);
    const patientAge = computed(() => computeAgeYears(patient.value?.dateNaissance || patient.value?.age));
    const rdvs = computed(() => patient.value.rdvs || []);
    const archiveFiles = computed(() => patient.value.archiveFiles || []);
    const paiements = computed(() => patient.value.paiements || []);
    const factures = computed(() => patient.value.factures || []);
    const isReception = computed(() => Boolean(auth.user?.roles?.includes('ROLE_RECEPTION')));
    const isMedecin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')));
    const isAdmin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_ADMIN')));
    const showConsultationsTab = computed(() => isAdmin.value || isMedecin.value);
    const isRestrictedMedecin = computed(() => isMedecin.value && !isAdmin.value);
    const dossierHiddenForMedecin = computed(() => isRestrictedMedecin.value && hidePatientDossierForMedecins.value);
    const shouldHidePatientPhoneForMedecin = computed(() => isRestrictedMedecin.value && hidePatientPhoneForMedecins.value);

    const hasOpenDialogs = computed(() => showRdvDialog.value || showConsultationDialog.value || showEditDialog.value || showAntecedentDialog.value || showAllergyDialog.value || showPrintDialog.value || showActiveConsultWarn.value);

    const resolvePatientId = () => {
        if (typeof options.getPatientId === 'function') {
            const override = options.getPatientId();
            if (override != null) return override;
        }
        const fromOption = unref(options.patientId);
        if (fromOption != null) return fromOption;
        return patient.value?.id ?? null;
    };

    const notifyUpdated = () => {
        if (typeof options.onUpdated === 'function') {
            options.onUpdated();
        }
    };

    const ensurePatientLists = () => {
        if (!Array.isArray(patient.value.antecedents)) patient.value.antecedents = [];
        if (!Array.isArray(patient.value.allergies)) patient.value.allergies = [];
        if (!Array.isArray(patient.value.archiveFiles)) patient.value.archiveFiles = [];
    };

    const loadDossier = async (patientId, { asPageLoad = false } = {}) => {
        if (!patientId) return false;
        try {
            const data = await patientStore.fetchPatientDossier(patientId);
            if (data) {
                patient.value = patientStore.normalizePatientDossier(data);
            }
            if (asPageLoad) {
                loadErrorMessage.value = '';
            }
            return true;
        } catch (error) {
            logAppError('Erreur lors du chargement du dossier patient', error);
            if (asPageLoad) {
                loadErrorMessage.value = 'Impossible de charger le dossier du patient.';
            }
            toast.add({
                severity: 'error',
                summary: 'Erreur',
                detail: 'Impossible de charger le dossier du patient. si le problème persiste, contactez le support.',
                life: 3000
            });
            return false;
        }
    };

    const loadConsultations = async (patientId, { asPageLoad = false } = {}) => {
        if (!patientId) return false;
        consultationsLoading.value = true;
        try {
            consultations.value = await patientStore.fetchPatientConsultations(patientId);
            if (asPageLoad) {
                loadErrorMessage.value = '';
            }
            return true;
        } catch (error) {
            logAppError('Erreur lors du chargement des consultations', error);
            consultations.value = [];
            if (asPageLoad) {
                loadErrorMessage.value = 'Impossible de charger les consultations du patient.';
            }
            toast.add({
                severity: 'error',
                summary: 'Erreur',
                detail: 'Impossible de charger les consultations du patient. si le problème persiste, contactez le support.',
                life: 3000
            });
            return false;
        } finally {
            consultationsLoading.value = false;
        }
    };

    const clearDossier = () => {
        dossierLoadSeq += 1;
        patient.value = patientStore.normalizePatientDossier();
        consultations.value = [];
        loadErrorMessage.value = '';
        dossierLoading.value = false;
        consultationsLoading.value = false;
    };

    const loadAll = async (patientId, { asPageLoad = false } = {}) => {
        if (!patientId) return false;
        const seq = ++dossierLoadSeq;
        dossierLoading.value = true;
        try {
            const dossierOk = await loadDossier(patientId, { asPageLoad });
            if (seq !== dossierLoadSeq) return false;
            const consultationsOk = await loadConsultations(patientId, { asPageLoad });
            if (seq !== dossierLoadSeq) return false;
            return dossierOk && consultationsOk;
        } finally {
            if (seq === dossierLoadSeq) {
                dossierLoading.value = false;
            }
        }
    };

    const retryLoadPage = async () => {
        loadErrorMessage.value = '';
        const patientId = resolvePatientId();
        if (!patientId) return;
        await loadAll(patientId, { asPageLoad: true });
    };

    const loadVisibilityPolicy = async () => {
        try {
            const settings = await fetchPublicGeneralSettings(token);
            hidePatientDossierForMedecins.value = settings?.hidePatientDossierForMedecins === true;
            hidePatientPhoneForMedecins.value = settings?.hidePatientPhoneForMedecins === true;
        } catch (error) {
            logAppError('Erreur chargement politique visibilité dossier', error);
            hidePatientDossierForMedecins.value = false;
            hidePatientPhoneForMedecins.value = false;
        }
    };

    const resetDialogs = () => {
        showRdvDialog.value = false;
        showConsultationDialog.value = false;
        showEditDialog.value = false;
        showAntecedentDialog.value = false;
        showAllergyDialog.value = false;
        showPrintDialog.value = false;
        showActiveConsultWarn.value = false;
    };

    const handleSaveAntecedent = async (payload) => {
        if (!patient.value?.id) return;
        savingAntecedent.value = true;
        try {
            const res = await addPatientAntecedent(patient.value.id, payload, token);
            if (res?.antecedent) {
                ensurePatientLists();
                patient.value.antecedents = [res.antecedent, ...patient.value.antecedents];
            }
            toast.add({ severity: 'success', summary: 'Antécédent ajouté', life: 2500 });
            showAntecedentDialog.value = false;
            notifyUpdated();
        } catch (error) {
            logAppError('Erreur ajout antécédent', error);
            toast.add({
                severity: 'error',
                summary: 'Erreur',
                detail: "Impossible d'ajouter l'antécédent. si le problème persiste, contactez le support.",
                life: 3000
            });
        } finally {
            savingAntecedent.value = false;
        }
    };

    const handleSaveAllergy = async (payload) => {
        if (!patient.value?.id) return;
        savingAllergy.value = true;
        try {
            const res = await addPatientAllergy(patient.value.id, payload, token);
            if (res?.allergy) {
                ensurePatientLists();
                patient.value.allergies = [res.allergy, ...patient.value.allergies];
            }
            toast.add({ severity: 'success', summary: 'Allergie ajoutée', life: 2500 });
            showAllergyDialog.value = false;
            notifyUpdated();
        } catch (error) {
            logAppError('Erreur ajout allergie', error);
            toast.add({
                severity: 'error',
                summary: 'Erreur',
                detail: "Impossible d'ajouter l'allergie. si le problème persiste, contactez le support.",
                life: 3000
            });
        } finally {
            savingAllergy.value = false;
        }
    };

    const handleDeleteAntecedent = async (item) => {
        if (!patient.value?.id || !item?.id) return;
        try {
            await deletePatientAntecedent(patient.value.id, item.id, token);
            ensurePatientLists();
            patient.value.antecedents = patient.value.antecedents.filter((a) => a.id !== item.id);
            toast.add({ severity: 'success', summary: 'Antécédent supprimé', life: 2000 });
            notifyUpdated();
        } catch (error) {
            logAppError('Erreur suppression antécédent', error);
            toast.add({
                severity: 'error',
                summary: 'Erreur',
                detail: 'Suppression impossible. si le problème persiste, contactez le support.',
                life: 3000
            });
        }
    };

    const handleDeleteAllergy = async (item) => {
        if (!patient.value?.id || !item?.id) return;
        try {
            await deletePatientAllergy(patient.value.id, item.id, token);
            ensurePatientLists();
            patient.value.allergies = patient.value.allergies.filter((a) => a.id !== item.id);
            toast.add({ severity: 'success', summary: 'Allergie supprimée', life: 2000 });
            notifyUpdated();
        } catch (error) {
            logAppError('Erreur suppression allergie', error);
            toast.add({
                severity: 'error',
                summary: 'Erreur',
                detail: 'Suppression impossible. si le problème persiste, contactez le support.',
                life: 3000
            });
        }
    };

    const handleCreatePortalAccount = async () => {
        if (!patient.value?.id) return;

        const account = await patientStore.createPortalAccount(patient.value.id, token);
        if (!account) {
            toast.add({ severity: 'error', summary: 'Compte patient', detail: 'Création impossible.', life: 3000 });
            return;
        }

        patient.value.portalAccount = account;
        toast.add({
            severity: 'success',
            summary: 'Compte patient',
            detail: `Compte créé (${account.username}) - mot de passe par défaut: 123`,
            life: 4500
        });
        notifyUpdated();
    };

    const handleResetPortalPassword = async () => {
        if (!patient.value?.id || !patient.value?.portalAccount) return;

        const account = await patientStore.resetPortalAccountPassword(patient.value.id, token);
        if (!account) {
            toast.add({ severity: 'error', summary: 'Compte patient', detail: 'Réinitialisation impossible.', life: 3000 });
            return;
        }

        patient.value.portalAccount = account;
        toast.add({ severity: 'success', summary: 'Compte patient', detail: 'Mot de passe réinitialisé à 123.', life: 3500 });
        notifyUpdated();
    };

    const handleTogglePortalActive = async (active) => {
        if (!patient.value?.id || !patient.value?.portalAccount) return;

        const account = await patientStore.togglePortalAccountActive(patient.value.id, Boolean(active), token);
        if (!account) {
            toast.add({ severity: 'error', summary: 'Compte patient', detail: 'Mise à jour du statut impossible.', life: 3000 });
            return;
        }

        patient.value.portalAccount = account;
        toast.add({
            severity: 'success',
            summary: 'Compte patient',
            detail: account.active ? 'Compte activé.' : 'Compte désactivé.',
            life: 3000
        });
        notifyUpdated();
    };

    const handlePhotoSelected = async (file) => {
        if (!patient.value?.id || !file) return;

        const formData = new FormData();
        formData.append('photo', file);
        const loadingToast = {
            severity: 'info',
            summary: 'Photo patient',
            detail: 'Upload en cours...',
            life: 0
        };

        try {
            toast.add(loadingToast);
            const updated = await patientStore.updatePatient(patient.value.id, formData, token);
            if (!updated) {
                throw new Error('patient_photo_update_failed');
            }

            patient.value = {
                ...patient.value,
                photo: updated.photo ?? patient.value.photo
            };

            toast.remove(loadingToast);
            toast.add({ severity: 'success', summary: 'Photo patient', detail: 'Photo mise à jour.', life: 2500 });
            notifyUpdated();
        } catch (error) {
            toast.remove(loadingToast);
            logAppError('Erreur mise à jour photo patient', error);
            toast.add({
                severity: 'error',
                summary: 'Erreur',
                detail: 'Impossible de mettre à jour la photo du patient.',
                life: 3000
            });
        }
    };

    const handleRdvSaved = async () => {
        showRdvDialog.value = false;
        await loadDossier(resolvePatientId());
        notifyUpdated();
    };

    const handleConsultationSaved = async () => {
        showConsultationDialog.value = false;
        await loadConsultations(resolvePatientId());
        notifyUpdated();
    };

    const handlePatientSaved = async () => {
        showEditDialog.value = false;
        await loadDossier(resolvePatientId());
        notifyUpdated();
    };

    const handleFicheUpdated = async () => {
        const patientId = resolvePatientId();
        if (!patientId) return;
        await loadDossier(patientId);
        notifyUpdated();
    };

    const handlePrintDossier = async () => {
        const patientId = resolvePatientId();
        if (!patientId) return;
        try {
            const res = await fetchPatientDossierPrintData(patientId, localStorage.getItem('token'));
            await printComponent(PrintDossierBody, { patient: res.patient });
        } catch (error) {
            logAppError('DossierPatient', error);
            toast.add({ severity: 'error', summary: 'Dossier', detail: 'Impression indisponible', life: 3500 });
        }
    };

    const handlePrintFiche = async (fiche) => {
        const ficheId = fiche?.id ?? null;
        if (!ficheId) return;
        selectedFicheForPrint.value = fiche;
        printSections.value = printSectionOptions.map((item) => item.key);
        printIncludeEmpty.value = false;
        showPrintDialog.value = true;
    };

    const submitPrint = async () => {
        const patientId = resolvePatientId();
        const ficheId = selectedFicheForPrint.value?.id ?? null;
        if (!patientId || !ficheId) return;
        try {
            const res = await fetchPatientFichePrintData(patientId, ficheId, localStorage.getItem('token'));
            await printComponent(PrintFicheV2Body, {
                patient: res.patient,
                fiche: res.fiche,
                sections: printSections.value,
                printEmpty: printIncludeEmpty.value
            });
            showPrintDialog.value = false;
        } catch (error) {
            logAppError('DossierPatient', error);
            toast.add({ severity: 'error', summary: 'Fiche', detail: 'Impression indisponible', life: 3500 });
        }
    };

    return {
        patientStore,
        token,
        patient,
        consultations,
        consultationsLoading,
        dossierLoading,
        loadErrorMessage,
        fiches,
        patientAge,
        rdvs,
        archiveFiles,
        paiements,
        factures,
        isReception,
        isMedecin,
        isAdmin,
        showConsultationsTab,
        hidePatientDossierForMedecins,
        hidePatientPhoneForMedecins,
        dossierHiddenForMedecin,
        shouldHidePatientPhoneForMedecin,
        hasOpenDialogs,
        showRdvDialog,
        showConsultationDialog,
        showEditDialog,
        showAntecedentDialog,
        showAllergyDialog,
        savingAntecedent,
        savingAllergy,
        showPrintDialog,
        selectedFicheForPrint,
        printIncludeEmpty,
        printSections,
        printSectionOptions,
        showActiveConsultWarn,
        activeConsultInfo,
        patientEditFormRef,
        resolvePatientId,
        loadDossier,
        loadConsultations,
        clearDossier,
        loadAll,
        retryLoadPage,
        loadVisibilityPolicy,
        resetDialogs,
        ensurePatientLists,
        handleSaveAntecedent,
        handleSaveAllergy,
        handleDeleteAntecedent,
        handleDeleteAllergy,
        handleCreatePortalAccount,
        handleResetPortalPassword,
        handleTogglePortalActive,
        handlePhotoSelected,
        handleRdvSaved,
        handleConsultationSaved,
        handlePatientSaved,
        handleFicheUpdated,
        handlePrintDossier,
        handlePrintFiche,
        submitPrint
    };
}

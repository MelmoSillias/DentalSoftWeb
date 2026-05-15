<script setup>
import {
    checkConsultationActive,
    createConsultationForPatient,
    searchPatients,
    normalizePatient
} from '@/services/patients';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { fetchTicketPrintData } from '@/services/printService';
import PrintTicketBody from '@/components/print/PrintTicketBody.vue';
import { usePrinter } from '@/composables/usePrinter';
import { useMedecinsStore } from '@/stores/medecins';
import { usePaymentMethodsStore } from '@/stores/paymentMethods';
import {
    getPaymentMethodDefinition,
    getDefaultClassicMethod
} from '@/utils/paymentMethodUtils';
import Button from 'primevue/button';
import ConfirmPopup from 'primevue/confirmpopup';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    patient: {
        type: Object,
        default: null
    },
    patientId: {
        type: [Number, String],
        default: null
    }
});

const emit = defineEmits(['saved', 'cancel']);

const confirmPopup = useConfirm();
const toast = useToast();
const token = localStorage.getItem('token');
const loading = ref(false);
const { printComponent } = usePrinter();
const medecinsStore = useMedecinsStore();
const paymentMethodsStore = usePaymentMethodsStore();
const consultationAmount = ref(5000);

const patients = ref([]);
const patientsLoading = ref(false);
const medecins = ref([]);
const paymentMethods = ref([]);
const selectedPatientId = ref(null);

const form = reactive({
    motif: '',
    notes: '',
    dateConsultation: new Date(),
    medecinId: null,
    payant: false,
    modePaiementId: null
});

const hasActiveConsultation = ref(false);
const checkingActive = ref(false);
const requireMedecinOnCreation = ref(true);
let patientSearchTimeout = null;

const isPatientPreselected = computed(() => Boolean(props.patient?.id || props.patientId));
const patientDisplayName = computed(() => {
    const p = props.patient;
    if (!p) return '';
    return p.fullname || `${p.prenom ?? ''} ${p.nom ?? ''}`.trim();
});

const loadPatients = async (query = '') => {
    patientsLoading.value = true;
    try {
        const data = await searchPatients(query, token, 20);
        patients.value = data.map((p) => normalizePatient(p));
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les patients.', life: 3000 });
    } finally {
        patientsLoading.value = false;
    }
};

const loadMedecins = async () => {
    try {
        medecins.value = await medecinsStore.load(token);
        if (!form.medecinId && medecins.value.length === 1) {
            form.medecinId = medecins.value[0]?.id ?? null;
        }
    } catch (error) {
        console.error('Erreur lors du chargement des medecins', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les médecins.', life: 3000 });
    }
};

const loadPaymentMethods = async () => {
    try {
        paymentMethods.value = await paymentMethodsStore.load(token);
    } catch (error) {
        console.error('Erreur lors du chargement des modes de paiement', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les modes de paiement.', life: 3000 });
    }
};

onMounted(() => {
    if (!isPatientPreselected.value) {
        loadPatients();
    }
    loadConsultationCreationPolicy();
    loadMedecins();
    loadPaymentMethods();
});

const loadConsultationCreationPolicy = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        requireMedecinOnCreation.value = settings?.requireMedecinOnConsultationCreation !== false;
        consultationAmount.value = Math.max(1, Number(settings?.consultationPrice || 5000));
    } catch (error) {
        console.error('Erreur lors du chargement de la politique consultation', error);
        requireMedecinOnCreation.value = true;
        consultationAmount.value = 5000;
    }
};

watch(
    () => [props.patient, props.patientId],
    () => {
        selectedPatientId.value = props.patient?.id ?? props.patientId ?? null;
    },
    { immediate: true }
);

const patientOptions = computed(() =>
    patients.value.map((p) => ({
        label: p.fullname || `${p.prenom ?? ''} ${p.nom ?? ''}`.trim() || p.nom || 'Patient',
        value: p.id
    }))
);

const handlePatientFilter = (event) => {
    const query = event?.value ?? event?.query ?? '';
    if (patientSearchTimeout) clearTimeout(patientSearchTimeout);
    patientSearchTimeout = setTimeout(() => {
        loadPatients(query);
    }, 250);
};

const medecinOptions = computed(() =>
    medecins.value.map((m) => ({
        label: m.fullName || `${m.prenom ?? ''} ${m.nom ?? ''}`.trim() || m.nom,
        value: m.id
    }))
);

const paymentMethodOptions = computed(() =>
    paymentMethods.value
        .filter((m) => m.actif !== false)
        .map((m) => ({
            label: `${m.libelle}${getPaymentMethodDefinition(m).label ? ` (${getPaymentMethodDefinition(m).label})` : ''}`,
            value: m.id
        }))
);

const requiresClassicPayment = computed(() => form.payant);

const resetForm = () => {
    form.notes = '';
    form.dateConsultation = new Date();
    form.medecinId = null;
    form.modePaiementId = null;
};

watch(
    () => form.payant,
    (isPayant) => {
        if (isPayant) {
            if (!form.modePaiementId) {
                form.modePaiementId = getDefaultClassicMethod(paymentMethods.value)?.id ?? null;
            }
            return;
        }

        form.modePaiementId = null;
    }
);

const formatDatePart = (dateValue) => {
    const d = dateValue ? new Date(dateValue) : null;
    if (!d || Number.isNaN(d.getTime())) return null;
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
};

const formatTimePart = (dateValue) => {
    const d = dateValue ? new Date(dateValue) : null;
    if (!d || Number.isNaN(d.getTime())) return null;
    const hh = String(d.getHours()).padStart(2, '0');
    const mi = String(d.getMinutes()).padStart(2, '0');
    return `${hh}:${mi}`;
};

const refreshActiveConsultationFlag = async (patientId) => {
    if (!patientId) {
        hasActiveConsultation.value = false;
        return;
    }
    checkingActive.value = true;
    try {
        const res = await checkConsultationActive(patientId, token);
        hasActiveConsultation.value = Boolean(res?.hasActive);
    } catch (error) {
        console.error('Erreur lors de la vérification des consultations actives', error);
        toast.add({ severity: 'warn', summary: 'Vérification', detail: 'Impossible de vérifier les consultations en cours.', life: 2500 });
        hasActiveConsultation.value = false;
    } finally {
        checkingActive.value = false;
    }
};

const printConsultationTicket = async (consultationId) => {
    if (!consultationId) return;
    try {
        const res = await fetchTicketPrintData(consultationId, token);
        await printComponent(
            PrintTicketBody,
            { paiement: res.paiement },
            { format: [226.77, 255.12], width: '80mm' }
        );
    } catch (error) {
        console.error('Erreur lors de l\'impression du ticket', error);
        toast.add({ severity: 'error', summary: 'Ticket', detail: 'Impression indisponible.', life: 3500 });
    }
};

watch(selectedPatientId, (patientId) => {
    refreshActiveConsultationFlag(patientId);
});

const saveConsultation = async () => {
    if (!selectedPatientId.value) {
        toast.add({ severity: 'warn', summary: 'Patient requis', detail: 'Sélectionnez un patient.', life: 2500 });
        return;
    }
    await refreshActiveConsultationFlag(selectedPatientId.value);

    if (hasActiveConsultation.value) {
        toast.add({ severity: 'warn', summary: 'Consultation active', detail: 'Une consultation est déjà en cours pour ce patient.', life: 3000 });
        return;
    }
    if (requireMedecinOnCreation.value && !form.medecinId) {
        toast.add({ severity: 'warn', summary: 'Médecin requis', detail: 'Sélectionnez un médecin.', life: 2500 });
        return;
    }
    if (requiresClassicPayment.value && !form.modePaiementId) {
        toast.add({ severity: 'warn', summary: 'Mode de paiement requis', detail: 'Choisissez un mode de paiement patient.', life: 2500 });
        return;
    }

    const consultationDate = formatDatePart(form.dateConsultation);
    const consultationTime = formatTimePart(form.dateConsultation);

    if (!consultationDate || !consultationTime) {
        toast.add({ severity: 'warn', summary: 'Date/heure requises', detail: 'Indiquez la date et l\'heure de consultation.', life: 2500 });
        return;
    }
    loading.value = true;
    try {
        const payload = {
            patient_id: selectedPatientId.value,
            medecin_id: form.medecinId || null,
            payant: form.payant ? 1 : 0,
            mode_paiement_id: requiresClassicPayment.value ? form.modePaiementId : null,
            consultation_date: consultationDate,
            consultation_time: consultationTime,
            consultation_amount: form.payant ? consultationAmount.value : 0,
            notes: form.notes || null
        };
        const saved = await createConsultationForPatient(selectedPatientId.value, payload, token);
        const consultationId = saved?.consultation_id ?? saved?.consultationId ?? null;
        if (form.payant) {
            toast.add({
                severity: 'success',
                summary: 'Succès',
                detail: 'Consultation créée.',
                life: 10000,
                data: consultationId
                    ? {
                        actionLabel: 'Imprimer le ticket',
                        action: () => printConsultationTicket(consultationId)
                    }
                    : undefined
            });
        } else {
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Consultation créée.', life: 2500 });
        }
        emit('saved', saved);
        resetForm();
    } catch (error) {
        console.error('Erreur lors de la création de la consultation', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de créer la consultation.', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const handleSubmit = (event) => {
    confirmPopup.require({
        group: 'create-consultation',
        target: event.currentTarget || event.target,
        message: 'Confirmer la création de la consultation ?',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: saveConsultation
    });
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <ConfirmPopup group="create-consultation" />
        <div v-if="checkingActive && !hasActiveConsultation" class="text-xs text-gray-500">
            Vérification des consultations en cours...
        </div>
        <div v-if="hasActiveConsultation" class="p-3 border border-amber-400 bg-amber-50 text-amber-800 rounded">
            Une consultation est déjà en cours pour ce patient. Clôturez-la avant d'en créer une nouvelle.
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-2 md:col-span-2" v-if="!isPatientPreselected">
                <label class="font-semibold">Patient</label>
                <Select v-model="selectedPatientId" :options="patientOptions  || []" optionLabel="label" optionValue="value"
                    placeholder="Choisir un patient" class="w-full" filter :loading="patientsLoading"
                    @filter="handlePatientFilter" />
            </div>
            <div v-else class="flex flex-col gap-2 md:col-span-2">
                <label class="font-semibold">Patient</label>
                <InputText :value="patientDisplayName" disabled />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-semibold">Médecin {{ requireMedecinOnCreation ? '' : '(optionnel)' }}</label>
                <Select v-model="form.medecinId" :options="medecinOptions  || []" optionLabel="label" optionValue="value"
                    placeholder="Choisir un médecin" class="w-full" />
            </div>

            <div class="flex flex-col gap-2">
                <label class="font-semibold">Date et heure</label>
                <DatePicker v-model="form.dateConsultation" showTime hourFormat="24" dateFormat="dd/mm/yy"
                    class="w-full" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-semibold">Consultation payante</label>
                <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="form.payant" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ form.payant ? `Payante (${consultationAmount.toLocaleString('fr-FR')})` : 'Gratuite' }}</span>
                </div>
            </div>
            <div class="flex flex-col gap-2" v-if="form.payant">
                <label class="font-semibold">Mode de paiement patient</label>
                <Select v-model="form.modePaiementId" :options="paymentMethodOptions  || []" optionLabel="label"
                    optionValue="value" placeholder="Choisir un mode de paiement" class="w-full" />
                <small class="text-gray-500 dark:text-gray-400">
                    Le mode de paiement reste requis pour les consultations payantes.
                </small>
            </div>
            <!-- <div class="md:col-span-2 flex flex-col gap-2">
                <label class="font-semibold">Notes</label>
                <Textarea v-model="form.notes" rows="3" auto-resize placeholder="Notes supplémentaires" />
            </div> -->
        </div>
        <div class="flex gap-2 justify-end">
            <Button type="button" label="Annuler" severity="secondary" @click="emit('cancel')" />
            <Button type="button" label="Créer" icon="pi pi-check" :loading="loading || checkingActive" :disabled="checkingActive || hasActiveConsultation" @click="handleSubmit" />
        </div>
    </div>
</template>

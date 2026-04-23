<script setup>
import { createRdvForPatient, fetchMedecins, normalizePatient, searchPatients } from '@/services/patients';
import { useAuthStore } from '@/stores/auth';
import Button from 'primevue/button';
import ConfirmPopup from 'primevue/confirmpopup';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
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
    },
    initialDate: {
        type: Date,
        default: () => new Date()
    },
    initialMedecinId: {
        type: [Number, String],
        default: null
    },
    lockedMedecinId: {
        type: [Number, String],
        default: null
    },
    medecinReadonly: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['saved', 'cancel']);

const confirmPopup = useConfirm();
const toast = useToast();
const token = localStorage.getItem('token');
const auth = useAuthStore();
const loading = ref(false);

const patients = ref([]);
const patientsLoading = ref(false);
const selectedPatientId = ref(null);
const medecins = ref([]);
const selectedMedecinId = ref(null);
const isMedecinUser = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')));

const form = reactive({
    motif: '',
    dateRdv: props.initialDate ? new Date(props.initialDate) : new Date(),
    notes: '',
    duration: 30
});

const isPatientPreselected = computed(() => Boolean(props.patient?.id || props.patientId));
const patientDisplayName = computed(() => {
    const p = props.patient;
    if (!p) return '';
    return p.fullname || `${p.prenom ?? ''} ${p.nom ?? ''}`.trim();
});
let patientSearchTimeout = null;

const loadPatients = async (query = '') => {
    patientsLoading.value = true;
    try {
        const data = await searchPatients(query, token, 20);
        patients.value = data.map((p) => normalizePatient(p));
    } catch (error) {
        console.error('Erreur lors du chargement des patients', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les patients.', life: 3000 });
    } finally {
        patientsLoading.value = false;
    }
};

const loadMedecins = async () => {
    try {
        const data = await fetchMedecins(token);
        medecins.value = Array.isArray(data) ? data : [];
    } catch (error) {
        console.error('Erreur lors du chargement des médecins', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les médecins.', life: 3000 });
    }
};

onMounted(() => {
    if (!isPatientPreselected.value) {
        loadPatients();
    }
    loadMedecins();
});

watch(
    () => [props.patient, props.patientId],
    () => {
        selectedPatientId.value = props.patient?.id ?? props.patientId ?? null;
    },
    { immediate: true }
);

watch(
    () => props.initialDate,
    (value) => {
        form.dateRdv = value ? new Date(value) : new Date();
    },
    { immediate: true }
);

watch(
    () => props.initialMedecinId,
    (value) => {
        if (value !== null && value !== undefined) {
            selectedMedecinId.value = value;
        }
    },
    { immediate: true }
);

watch(
    () => props.lockedMedecinId,
    (value) => {
        if (value !== null && value !== undefined) {
            selectedMedecinId.value = value;
        }
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
    {
        const options = medecins.value.map((m) => ({
            label: m.label || m.fullName || m.fullname || m.name || `${m.prenom ?? ''} ${m.nom ?? ''}`.trim() || m.nom || 'Médecin',
            value: m.id
        }));

        if (
            isMedecinUser.value
            && selectedMedecinId.value
            && !options.some((opt) => Number(opt.value) === Number(selectedMedecinId.value))
        ) {
            options.unshift({
                label: connectedMedecinDisplayName.value || `Médecin #${selectedMedecinId.value}`,
                value: selectedMedecinId.value
            });
        }

        return options;
    }
);

const normalizeText = (value) => String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();

const resolveConnectedMedecinId = () => {
    const user = auth.user || {};
    const directId = Number(user.medecinId ?? user.medecin_id ?? user.medecin?.id ?? Number.NaN);
    if (Number.isFinite(directId)) {
        return directId;
    }

    const fullName = [user.prenom, user.nom].filter(Boolean).join(' ').trim();
    const candidates = [fullName, user.name, user.fullName, user.username].filter(Boolean).map(normalizeText);
    if (!candidates.length) return null;

    const foundByName = (medecins.value || []).find((m) => {
        const label = normalizeText(m.label || m.fullName || m.fullname || m.name || `${m.prenom ?? ''} ${m.nom ?? ''}`.trim() || m.nom);
        return candidates.some((candidate) => candidate && (label === candidate || label.includes(candidate) || candidate.includes(label)));
    });

    return foundByName?.id ?? null;
};

const connectedMedecinDisplayName = computed(() => {
    const user = auth.user || {};
    const fullName = [user.prenom, user.nom].filter(Boolean).join(' ').trim();
    return fullName || user.fullName || user.name || user.username || '';
});

watch(
    () => [isMedecinUser.value, medecinOptions.value.length],
    () => {
        if (!isMedecinUser.value) return;
        const connectedId = resolveConnectedMedecinId();
        if (connectedId) {
            selectedMedecinId.value = connectedId;
        }
    },
    { immediate: true }
);

const isMedecinLocked = computed(() => isMedecinUser.value || props.medecinReadonly);

const saveRendezVous = async () => {
    if (!selectedPatientId.value) {
        toast.add({ severity: 'warn', summary: 'Patient requis', detail: 'Sélectionnez un patient.', life: 2500 });
        return;
    }
    if (!selectedMedecinId.value) {
        toast.add({ severity: 'warn', summary: 'Médecin requis', detail: 'Sélectionnez un médecin.', life: 2500 });
        return;
    }

    const dateObj = form.dateRdv ? new Date(form.dateRdv) : null;
    if (!dateObj || Number.isNaN(dateObj.getTime())) {
        toast.add({ severity: 'warn', summary: 'Date invalide', detail: 'Choisissez une date et une heure valides.', life: 2500 });
        return;
    }

    const pad = (value) => value.toString().padStart(2, '0');
    const dateStr = `${dateObj.getFullYear()}-${pad(dateObj.getMonth() + 1)}-${pad(dateObj.getDate())}`;
    const timeStr = `${pad(dateObj.getHours())}:${pad(dateObj.getMinutes())}`;

    const duration = Number(form.duration) || 0;
    if (duration <= 0) {
        toast.add({ severity: 'warn', summary: 'Durée invalide', detail: 'La durée doit être positive.', life: 2500 });
        return;
    }

    loading.value = true;
    try {
        const payload = {
            medecin_id: props.medecinReadonly ? (props.lockedMedecinId ?? selectedMedecinId.value) : selectedMedecinId.value,
            date: dateStr,
            time: timeStr,
            duration,
            description: form.motif,
            notes: form.notes
        };
        const saved = await createRdvForPatient(selectedPatientId.value, payload, token);
        toast.add({ severity: 'success', summary: 'Succès', detail: 'Rendez-vous créé.', life: 2500 });
        emit('saved', saved);
        form.motif = '';
        form.notes = '';
        form.dateRdv = props.initialDate ? new Date(props.initialDate) : new Date();
        form.duration = 30;
        if (!isPatientPreselected.value) {
            selectedPatientId.value = null;
        }
    } catch (error) {
        console.error('Erreur lors de la création du rendez-vous', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible de créer le rendez-vous.", life: 3000 });
    } finally {
        loading.value = false;
    }
};

const handleSubmit = (event) => {
    confirmPopup.require({
        target: event.currentTarget || event.target,
        message: 'Confirmer la création du rendez-vous ?',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: saveRendezVous
    });
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <ConfirmPopup />
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-2" v-if="!isPatientPreselected">
                <label class="font-semibold">Patient</label>
                <Select v-model="selectedPatientId" :options="patientOptions" optionLabel="label" optionValue="value"
                    placeholder="Choisir un patient" class="w-full" filter :loading="patientsLoading"
                    @filter="handlePatientFilter" />
            </div>
            <div v-else class="flex flex-col gap-2">
                <label class="font-semibold">Patient</label>
                <InputText :value="patientDisplayName" disabled />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-semibold">Médecin</label>
                <Select v-model="selectedMedecinId" :options="medecinOptions" optionLabel="label" optionValue="value"
                    placeholder="Choisir un médecin" class="w-full" :disabled="isMedecinLocked" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-semibold">Durée (minutes)</label>
                <InputNumber v-model="form.duration" :min="1" showButtons buttonLayout="horizontal"
                    decrementButtonClass="p-button-outlined" incrementButtonClass="p-button-outlined" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-semibold">Motif</label>
                <InputText v-model="form.motif" placeholder="Motif du rendez-vous" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-semibold">Date et heure</label>
                <DatePicker v-model="form.dateRdv" showTime hourFormat="24" dateFormat="dd/mm/yy" class="w-full" />
            </div>
            <div class="md:col-span-2 flex flex-col gap-2">
                <label class="font-semibold">Notes</label>
                <Textarea v-model="form.notes" rows="3" auto-resize placeholder="Notes supplémentaires" />
            </div>
        </div>
        <div class="flex gap-2 justify-end">
            <Button type="button" label="Annuler" severity="secondary" @click="emit('cancel')" />
            <Button type="button" label="Créer" icon="pi pi-check" :loading="loading" @click="handleSubmit" />
        </div>
    </div>
</template>

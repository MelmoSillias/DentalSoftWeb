<script setup>
import { createRdvForPatient, normalizePatient, searchPatients } from '@/services/patients';
import { useAuthStore } from '@/stores/auth';
import { useMedecinsStore } from '@/stores/medecins';
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
const medecinsStore = useMedecinsStore();
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

const smsReminder = reactive({
    timing: '1d',
    repeatInterval: 'none'
});

const smsReminderTimingOptions = [
    { label: 'Désactivé', value: 'disabled' },
    { label: '1 semaine avant', value: '7d' },
    { label: '5 jours avant', value: '5d' },
    { label: '3 jours avant', value: '3d' },
    { label: '2 jours avant', value: '2d' },
    { label: '1 jour avant', value: '1d' }
];

const smsReminderRepeatOptions = [
    { label: 'Sans répétition', value: 'none' },
    { label: 'Tous les jours', value: 'daily' },
    { label: 'Tous les 2 jours', value: 'every_2_days' },
    { label: 'Chaque semaine', value: 'weekly' }
];

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
        const data = await medecinsStore.load(token);
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

const isValidMedecinId = (value) => {
    const id = Number(value ?? Number.NaN);
    return Number.isFinite(id) && id > 0;
};

const applyMedecinSelection = () => {
    if (isValidMedecinId(props.initialMedecinId)) {
        selectedMedecinId.value = props.initialMedecinId;
        return;
    }
    if (isValidMedecinId(props.lockedMedecinId)) {
        selectedMedecinId.value = props.lockedMedecinId;
        return;
    }
    if (!isMedecinUser.value) return;
    const connectedId = resolveConnectedMedecinId();
    if (connectedId) {
        selectedMedecinId.value = connectedId;
    }
};

const patientOptions = computed(() =>
    patients.value.map((p) => ({
        label: p.fullname || `${p.prenom ?? ''} ${p.nom ?? ''}`.trim() || p.nom || 'Patient',
        value: p.id,
        phone: p.telephone || p.phone || '',
        searchText: [p.fullname, `${p.prenom ?? ''} ${p.nom ?? ''}`.trim(), p.nom, p.telephone, p.phone].filter(Boolean).join(' ')
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
    () => [props.initialMedecinId, props.lockedMedecinId, isMedecinUser.value, medecins.value.length],
    applyMedecinSelection,
    { immediate: true }
);

const isMedecinLocked = computed(() => isMedecinUser.value || props.medecinReadonly);

const smsReminderFirstAt = computed(() => {
    if (smsReminder.timing === 'disabled') {
        return null;
    }

    const dateObj = form.dateRdv ? new Date(form.dateRdv) : null;
    if (!dateObj || Number.isNaN(dateObj.getTime())) {
        return null;
    }

    const daysBefore = {
        '7d': 7,
        '5d': 5,
        '3d': 3,
        '2d': 2,
        '1d': 1
    }[smsReminder.timing] ?? 1;

    const sendAt = new Date(dateObj);
    sendAt.setDate(sendAt.getDate() - daysBefore);
    return sendAt;
});

const canRepeatSmsReminder = computed(() => {
    if (smsReminder.timing === 'disabled') {
        return false;
    }

    return smsReminderFirstAt.value instanceof Date && smsReminderFirstAt.value.getTime() > Date.now();
});

watch(canRepeatSmsReminder, (allowed) => {
    if (!allowed) {
        smsReminder.repeatInterval = 'none';
    }
}, { immediate: true });

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
            notes: form.notes,
            smsReminder: {
                timing: smsReminder.timing,
                repeatInterval: canRepeatSmsReminder.value ? smsReminder.repeatInterval : 'none'
            }
        };
        const saved = await createRdvForPatient(selectedPatientId.value, payload, token);
        toast.add({
            severity: 'success',
            summary: 'Succès',
            detail: saved?.sms_queued_count > 0
                ? `Rendez-vous créé. ${saved.sms_queued_count} SMS programmé(s).`
                : 'Rendez-vous créé.',
            life: 3000
        });
        emit('saved', saved);
        form.motif = '';
        form.notes = '';
        form.dateRdv = props.initialDate ? new Date(props.initialDate) : new Date();
        form.duration = 30;
        smsReminder.timing = '1d';
        smsReminder.repeatInterval = 'none';
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
                    :filterFields="['label', 'phone', 'searchText']"
                    @filter="handlePatientFilter"
                >
                    <template #option="{ option }">
                        <div class="flex flex-col">
                            <span class="font-medium">{{ option.label }}</span>
                            <small class="text-surface-500 dark:text-surface-400">{{ option.phone || 'Téléphone non renseigné' }}</small>
                        </div>
                    </template>
                </Select>
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
            <div class="md:col-span-2 rounded-xl border border-surface-200 bg-surface-50 p-4 dark:border-surface-700 dark:bg-surface-800/40">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <label class="font-semibold">Programmation SMS rapide</label>
                        <p class="text-sm text-surface-500 dark:text-surface-400">Rappel de rendez-vous par défaut à 1 jour avant. La répétition se coupe automatiquement si la première échéance est déjà passée.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Quand envoyer</label>
                        <Select v-model="smsReminder.timing" :options="smsReminderTimingOptions" optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Répétition</label>
                        <Select v-model="smsReminder.repeatInterval" :options="smsReminderRepeatOptions" optionLabel="label" optionValue="value" class="w-full" :disabled="!canRepeatSmsReminder" />
                    </div>
                </div>

                <p class="mt-3 text-sm text-surface-500 dark:text-surface-400">
                    <span v-if="smsReminder.timing === 'disabled'">Aucun rappel SMS ne sera programmé.</span>
                    <span v-else-if="smsReminderFirstAt">Première échéance prévue: {{ smsReminderFirstAt.toLocaleString('fr-FR') }}</span>
                    <span v-else>Aucune échéance calculable.</span>
                </p>
            </div>
        </div>
        <div class="flex gap-2 justify-end">
            <Button type="button" label="Annuler" severity="secondary" @click="emit('cancel')" />
            <Button type="button" label="Créer" icon="pi pi-check" :loading="loading" @click="handleSubmit" />
        </div>
    </div>
</template>

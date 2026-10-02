<script setup>
import DatePicker from 'primevue/datepicker';
import AppDialog from '@/components/layout/AppDialog.vue';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { createCabinetService, updateCabinetService } from '@/services/cabinetServices';
import { defaultServicesCabinetList, normalizeServicesCabinetList } from '@/services/consultations';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { normalizePatient, searchPatients } from '@/services/patients';
import { useAuthStore } from '@/stores/auth';
import { logAppError } from '@/utils/appLogger';

const visible = defineModel('visible', { type: Boolean, default: false });

const props = defineProps({
    patientId: { type: [Number, String], default: null },
    patientName: { type: String, default: '' },
    /** When set, dialog switches to edit mode */
    service: { type: Object, default: null },
    /** Allow picking a patient when creating without a preselected patient */
    allowPatientSelect: { type: Boolean, default: false }
});

const emit = defineEmits(['created', 'updated']);

const auth = useAuthStore();
const toast = useToast();
const catalog = ref(defaultServicesCabinetList.map((item) => ({ ...item })));
const saving = ref(false);
const form = ref(emptyForm());
const selectedPatientId = ref(null);
const patients = ref([]);
const patientsLoading = ref(false);
let patientSearchTimeout = null;

const isEdit = computed(() => Boolean(props.service?.id));
const amountLocked = computed(() => Boolean(props.service?.facture?.hasPayments));
const dialogTitle = computed(() => (isEdit.value ? 'Modifier le service cabinet' : 'Enregistrer un service cabinet'));
const confirmLabel = computed(() => (isEdit.value ? 'Enregistrer' : 'Enregistrer'));
const resolvedSubtitle = computed(() => {
    if (isEdit.value) {
        return props.service?.patientName || props.patientName || null;
    }
    return props.patientName || null;
});
const needsPatientSelect = computed(() => !isEdit.value && props.allowPatientSelect && !props.patientId);
const effectivePatientId = computed(() => {
    if (isEdit.value) return props.service?.patientId ?? props.patientId;
    return props.patientId ?? selectedPatientId.value;
});

const total = computed(() => Math.max(0, Number(form.value.quantite) || 0) * Math.max(0, Number(form.value.prix) || 0));

const patientOptions = computed(() =>
    patients.value.map((p) => ({
        label: p.fullname || `${p.prenom ?? ''} ${p.nom ?? ''}`.trim() || p.nom || 'Patient',
        value: p.id,
        phone: p.telephone || p.phone || '',
        searchText: [p.fullname, `${p.prenom ?? ''} ${p.nom ?? ''}`.trim(), p.nom, p.telephone, p.phone].filter(Boolean).join(' ')
    }))
);

function emptyForm() {
    const first = catalog.value[0];
    return {
        designation: first?.description || '',
        quantite: 1,
        prix: Number(first?.montant) || 0,
        date: new Date(),
        note: ''
    };
}

function formFromService(service) {
    return {
        designation: service?.designation || '',
        quantite: Number(service?.quantite) || 1,
        prix: Number(service?.prix) || 0,
        date: service?.date ? new Date(service.date) : new Date(),
        note: service?.note || ''
    };
}

const loadCatalog = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(auth.token);
        catalog.value = normalizeServicesCabinetList(settings?.servicesCabinetList);
    } catch (error) {
        logAppError('Services cabinet', error);
        catalog.value = defaultServicesCabinetList.map((item) => ({ ...item }));
    }
    if (!isEdit.value && !form.value.designation && catalog.value[0]) {
        form.value.designation = catalog.value[0].description;
        form.value.prix = Number(catalog.value[0].montant) || 0;
    }
};

const loadPatients = async (query = '') => {
    patientsLoading.value = true;
    try {
        const data = await searchPatients(query, auth.token, 20);
        patients.value = data.map((p) => normalizePatient(p));
    } catch (error) {
        logAppError('Services cabinet patients', error);
        patients.value = [];
    } finally {
        patientsLoading.value = false;
    }
};

const handlePatientFilter = (event) => {
    const query = event?.value ?? event?.query ?? '';
    if (patientSearchTimeout) clearTimeout(patientSearchTimeout);
    patientSearchTimeout = setTimeout(() => {
        loadPatients(query);
    }, 250);
};

watch(visible, (open) => {
    if (!open) {
        return;
    }
    if (isEdit.value) {
        form.value = formFromService(props.service);
    } else {
        form.value = emptyForm();
        selectedPatientId.value = props.patientId ? Number(props.patientId) : null;
        if (needsPatientSelect.value) {
            loadPatients();
        }
    }
    loadCatalog();
});

const onDesignationChange = (value) => {
    if (amountLocked.value) return;
    const match = catalog.value.find((item) => item.description === value);
    if (match) {
        form.value.prix = Number(match.montant) || 0;
    }
};

const formatDate = (value) => {
    const date = value instanceof Date ? value : new Date();
    const pad = (part) => String(part).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const submit = async () => {
    if (!effectivePatientId.value && !isEdit.value) {
        toast.add({ severity: 'warn', summary: 'Patient', detail: 'Sélectionnez un patient', life: 2500 });
        return;
    }
    if (!String(form.value.designation || '').trim()) {
        toast.add({ severity: 'warn', summary: 'Service', detail: 'Choisissez un service cabinet', life: 2500 });
        return;
    }
    if (total.value <= 0) {
        toast.add({ severity: 'warn', summary: 'Montant', detail: 'Le montant doit être supérieur à zéro', life: 2500 });
        return;
    }

    const payload = {
        designation: form.value.designation,
        quantite: form.value.quantite,
        prix: form.value.prix,
        date: formatDate(form.value.date),
        note: form.value.note
    };

    try {
        saving.value = true;
        if (isEdit.value) {
            const result = await updateCabinetService(props.service.id, payload, auth.token);
            toast.add({ severity: 'success', summary: 'Service cabinet', detail: 'Service modifié', life: 2500 });
            visible.value = false;
            emit('updated', result?.data || null);
            return;
        }

        const result = await createCabinetService(effectivePatientId.value, payload, auth.token);
        toast.add({ severity: 'success', summary: 'Service cabinet', detail: 'Service enregistré et facture créée', life: 2500 });
        visible.value = false;
        emit('created', result?.data || null);
    } catch (error) {
        logAppError('Services cabinet', error);
        toast.add({
            severity: 'error',
            summary: 'Service cabinet',
            detail: error?.response?.data?.error || (isEdit.value ? 'Modification impossible' : 'Enregistrement impossible'),
            life: 3500
        });
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <AppDialog
        v-model:visible="visible"
        :title="dialogTitle"
        :subtitle="resolvedSubtitle"
        icon="pi pi-briefcase"
        icon-tone="primary"
        size="md"
        :loading="saving"
        cancel-label="Annuler"
        :confirm-label="confirmLabel"
        confirm-icon="pi pi-check"
        @cancel="visible = false"
        @confirm="submit"
    >
        <div class="flex flex-col gap-4">
            <div v-if="needsPatientSelect" class="flex flex-col gap-1">
                <label class="text-sm font-medium">Patient <span class="text-red-500">*</span></label>
                <Select
                    v-model="selectedPatientId"
                    :options="patientOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Choisir un patient"
                    class="w-full"
                    filter
                    :loading="patientsLoading"
                    :filterFields="['label', 'phone', 'searchText']"
                    @filter="handlePatientFilter"
                >
                    <template #option="{ option }">
                        <div class="flex flex-col">
                            <span class="font-medium">{{ option.label }}</span>
                            <small class="text-surface-500">{{ option.phone || 'Téléphone non renseigné' }}</small>
                        </div>
                    </template>
                </Select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Service</label>
                <Select
                    v-model="form.designation"
                    :options="catalog"
                    optionLabel="description"
                    optionValue="description"
                    placeholder="Choisir un service"
                    class="w-full"
                    editable
                    @update:modelValue="onDesignationChange"
                />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">Quantité</label>
                    <InputNumber v-model="form.quantite" :min="1" :maxFractionDigits="0" class="w-full" inputClass="w-full" :disabled="amountLocked" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">Prix unitaire</label>
                    <InputNumber v-model="form.prix" mode="decimal" :min="0" :minFractionDigits="0" :maxFractionDigits="2" class="w-full" inputClass="w-full" :disabled="amountLocked" />
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Date</label>
                <DatePicker v-model="form.date" showTime hourFormat="24" dateFormat="dd/mm/yy" class="w-full" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Note</label>
                <Textarea v-model="form.note" rows="2" autoResize class="w-full" />
            </div>
            <p class="text-sm font-semibold text-surface-800 dark:text-surface-100">Total : {{ total.toLocaleString('fr-FR') }} FCFA</p>
        </div>
    </AppDialog>
</template>

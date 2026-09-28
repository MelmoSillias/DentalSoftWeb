<script setup>
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { createCabinetService } from '@/services/cabinetServices';
import { defaultServicesCabinetList, normalizeServicesCabinetList } from '@/services/consultations';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { useAuthStore } from '@/stores/auth';
import { logAppError } from '@/utils/appLogger';

const visible = defineModel('visible', { type: Boolean, default: false });

const props = defineProps({
    patientId: { type: [Number, String], default: null },
    patientName: { type: String, default: '' }
});

const emit = defineEmits(['created']);

const auth = useAuthStore();
const toast = useToast();
const catalog = ref(defaultServicesCabinetList.map((item) => ({ ...item })));
const saving = ref(false);
const form = ref(emptyForm());

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

const total = computed(() => Math.max(0, Number(form.value.quantite) || 0) * Math.max(0, Number(form.value.prix) || 0));

const loadCatalog = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(auth.token);
        catalog.value = normalizeServicesCabinetList(settings?.servicesCabinetList);
    } catch (error) {
        logAppError('Services cabinet', error);
        catalog.value = defaultServicesCabinetList.map((item) => ({ ...item }));
    }
    if (!form.value.designation && catalog.value[0]) {
        form.value.designation = catalog.value[0].description;
        form.value.prix = Number(catalog.value[0].montant) || 0;
    }
};

watch(visible, (open) => {
    if (!open) {
        return;
    }
    form.value = emptyForm();
    loadCatalog();
});

const onDesignationChange = (value) => {
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
    if (!props.patientId) {
        toast.add({ severity: 'warn', summary: 'Patient', detail: 'Patient introuvable', life: 2500 });
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

    try {
        saving.value = true;
        const result = await createCabinetService(
            props.patientId,
            {
                designation: form.value.designation,
                quantite: form.value.quantite,
                prix: form.value.prix,
                date: formatDate(form.value.date),
                note: form.value.note
            },
            auth.token
        );
        toast.add({ severity: 'success', summary: 'Service cabinet', detail: 'Service enregistré et facture créée', life: 2500 });
        visible.value = false;
        emit('created', result?.data || null);
    } catch (error) {
        logAppError('Services cabinet', error);
        toast.add({
            severity: 'error',
            summary: 'Service cabinet',
            detail: error?.response?.data?.error || 'Enregistrement impossible',
            life: 3500
        });
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Dialog v-model:visible="visible" modal header="Enregistrer un service cabinet" class="w-full max-w-lg">
        <p v-if="patientName" class="mb-4 text-sm text-surface-500">{{ patientName }}</p>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Service</label>
                <Select v-model="form.designation" :options="catalog" optionLabel="description" optionValue="description" placeholder="Choisir un service" class="w-full" @update:modelValue="onDesignationChange" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">Quantité</label>
                    <InputNumber v-model="form.quantite" :min="1" :maxFractionDigits="0" class="w-full" inputClass="w-full" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">Prix unitaire</label>
                    <InputNumber v-model="form.prix" mode="decimal" :min="0" :minFractionDigits="0" :maxFractionDigits="2" class="w-full" inputClass="w-full" />
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
        <template #footer>
            <Button label="Annuler" text severity="secondary" @click="visible = false" />
            <Button label="Enregistrer" icon="pi pi-check" :loading="saving" @click="submit" />
        </template>
    </Dialog>
</template>

<script setup>
import AppDialog from '@/components/layout/AppDialog.vue';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import { computed, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { payCabinetFacture } from '@/services/cabinetServices';
import { useAuthStore } from '@/stores/auth';
import { usePaymentMethodsStore } from '@/stores/paymentMethods';
import { getDefaultClassicMethod, getPaymentMethodDefinition } from '@/utils/paymentMethodUtils';
import { logAppError } from '@/utils/appLogger';

const visible = defineModel('visible', { type: Boolean, default: false });

const props = defineProps({
    service: { type: Object, default: null }
});

const emit = defineEmits(['paid']);

const auth = useAuthStore();
const toast = useToast();
const paymentMethodsStore = usePaymentMethodsStore();
const saving = ref(false);
const paymentMethods = ref([]);
const form = ref({
    modeId: null,
    montant: 0,
    date: new Date()
});

const facture = computed(() => props.service?.facture || null);
const reste = computed(() => Math.max(0, Number(facture.value?.reste ?? props.service?.montant) || 0));
const patientLabel = computed(() => props.service?.patientName || `${props.service?.patient?.prenom ?? ''} ${props.service?.patient?.nom ?? ''}`.trim() || '');
const subtitle = computed(() => {
    const parts = [patientLabel.value, props.service?.designation].filter(Boolean);
    return parts.join(' · ') || null;
});

const paymentOptions = computed(() =>
    (paymentMethods.value || [])
        .filter((m) => m.actif !== false)
        .map((m) => ({
            label: `${m.libelle}${getPaymentMethodDefinition(m).label ? ` (${getPaymentMethodDefinition(m).label})` : ''}`,
            value: m.id
        }))
);

const formatMoney = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const formatDate = (value) => {
    const date = value instanceof Date ? value : new Date();
    const pad = (part) => String(part).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

watch(visible, async (open) => {
    if (!open) return;
    form.value = {
        modeId: null,
        montant: reste.value,
        date: new Date()
    };
    try {
        paymentMethods.value = await paymentMethodsStore.load(auth.token);
        form.value.modeId = getDefaultClassicMethod(paymentMethods.value)?.id ?? paymentOptions.value[0]?.value ?? null;
    } catch (error) {
        logAppError('Services cabinet paiement', error);
        paymentMethods.value = [];
    }
});

const submit = async () => {
    if (!facture.value?.id) {
        toast.add({ severity: 'warn', summary: 'Paiement', detail: 'Facture introuvable', life: 2500 });
        return;
    }
    if (!form.value.modeId) {
        toast.add({ severity: 'warn', summary: 'Paiement', detail: 'Choisissez un mode de paiement', life: 2500 });
        return;
    }
    const montant = Number(form.value.montant) || 0;
    if (montant <= 0 || montant > reste.value + 0.001) {
        toast.add({ severity: 'warn', summary: 'Paiement', detail: 'Montant invalide', life: 2500 });
        return;
    }

    try {
        saving.value = true;
        await payCabinetFacture(
            facture.value.id,
            {
                modeId: form.value.modeId,
                montant,
                date: formatDate(form.value.date)
            },
            auth.token
        );
        toast.add({ severity: 'success', summary: 'Paiement', detail: 'Paiement enregistré', life: 2500 });
        visible.value = false;
        emit('paid');
    } catch (error) {
        logAppError('Services cabinet paiement', error);
        toast.add({
            severity: 'error',
            summary: 'Paiement',
            detail: error?.response?.data?.error || 'Paiement impossible',
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
        title="Payer le service cabinet"
        :subtitle="subtitle"
        icon="pi pi-wallet"
        icon-tone="success"
        size="md"
        :loading="saving"
        cancel-label="Annuler"
        confirm-label="Encaisser"
        confirm-icon="pi pi-check"
        :confirm-disabled="!form.modeId || !form.montant"
        @cancel="visible = false"
        @confirm="submit"
    >
        <div class="flex flex-col gap-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-blue-200 bg-blue-50/70 p-3 dark:border-blue-800 dark:bg-blue-950/20">
                    <p class="text-xs text-surface-500">Montant</p>
                    <p class="text-sm font-semibold">{{ formatMoney(facture?.montant ?? service?.montant) }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-800 dark:bg-emerald-950/20">
                    <p class="text-xs text-surface-500">Déjà payé</p>
                    <p class="text-sm font-semibold">{{ formatMoney(facture?.partPatientPayee) }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 dark:border-amber-800 dark:bg-amber-950/20">
                    <p class="text-xs text-surface-500">Reste</p>
                    <p class="text-sm font-semibold">{{ formatMoney(reste) }}</p>
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Mode de paiement</label>
                <Select v-model="form.modeId" :options="paymentOptions" optionLabel="label" optionValue="value" placeholder="Choisir un mode" class="w-full" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Montant</label>
                <InputNumber v-model="form.montant" mode="decimal" :min="0" :max="reste" :minFractionDigits="0" :maxFractionDigits="2" class="w-full" inputClass="w-full" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Date</label>
                <DatePicker v-model="form.date" showTime hourFormat="24" dateFormat="dd/mm/yy" class="w-full" />
            </div>
        </div>
    </AppDialog>
</template>

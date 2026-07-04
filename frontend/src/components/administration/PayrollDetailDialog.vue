<script setup>
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Tag from 'primevue/tag';
import { formatFrequenceLabel, formatPrimeTypeLabel, formatSalaryTypeLabel } from '@/utils/payrollUtils';

const props = defineProps({
    visible: Boolean,
    payment: {
        type: Object,
        default: null
    },
    paymentMethods: {
        type: Array,
        default: () => []
    },
    loading: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:visible', 'submit', 'print']);

const localVisible = ref(false);
const editMode = ref(false);
const form = ref({
    paidAmount: null,
    paidAt: null,
    paymentMethodId: null,
    primeAmount: null,
    note: ''
});

watch(() => props.visible, (val) => {
    localVisible.value = val;
    if (val) {
        editMode.value = false;
        hydrateForm();
    }
}, { immediate: true });

watch(localVisible, (val) => emit('update:visible', val));
watch(() => props.payment, () => hydrateForm());

const hydrateForm = () => {
    const payment = props.payment;
    form.value = {
        paidAmount: payment?.paidAmount ?? null,
        paidAt: payment?.paidAt ? new Date(payment.paidAt) : new Date(),
        paymentMethodId: payment?.paymentMethod?.id ?? null,
        primeAmount: payment?.primeAmount ?? null,
        note: payment?.note || ''
    };
};

const hasFixedPrime = computed(() => props.payment?.primeType === 'fixe');

const formatCurrency = (value) => {
    if (value === null || value === undefined) return '—';
    return `${Number(value).toLocaleString('fr-FR')} F CFA`;
};

const periodLabel = computed(() => {
    if (props.payment?.workedDay) {
        return new Date(props.payment.workedDay).toLocaleDateString('fr-FR');
    }
    const month = props.payment?.month;
    const year = props.payment?.year;
    if (!month || !year) return '—';
    return new Date(year, month - 1, 1).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
});

const close = () => {
    localVisible.value = false;
};

const submit = () => {
    const paidAt = form.value.paidAt instanceof Date
        ? form.value.paidAt.toISOString().slice(0, 10)
        : '';

    const payload = {
        paidAmount: Number(form.value.paidAmount),
        paidAt,
        paymentMethodId: form.value.paymentMethodId,
        note: form.value.note || ''
    };

    if (hasFixedPrime.value && form.value.primeAmount !== null) {
        payload.primeAmount = Number(form.value.primeAmount);
    }

    emit('submit', payload);
    editMode.value = false;
};
</script>

<template>
    <Dialog
        v-model:visible="localVisible"
        modal
        header="Détail du paiement"
        :style="{ width: '44rem', maxWidth: '95vw' }"
        @hide="close"
    >
        <div v-if="payment" class="space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold">{{ payment.employeeName }}</h3>
                    <p class="text-sm text-surface-500">{{ payment.employeeFonction || '—' }} · {{ periodLabel }}</p>
                </div>
                <Tag :value="formatFrequenceLabel(payment.frequenceSnapshot)" severity="info" />
            </div>

            <div class="grid grid-cols-2 gap-3 text-sm rounded-xl border border-surface-200 dark:border-surface-700 p-4 bg-surface-50/60 dark:bg-surface-900/30">
                <div>
                    <span class="text-surface-500">Type salaire</span>
                    <p class="font-semibold uppercase tracking-wide">{{ formatSalaryTypeLabel(payment.salaryType) }}</p>
                </div>
                <div>
                    <span class="text-surface-500">Prime</span>
                    <p class="font-semibold uppercase tracking-wide">{{ formatPrimeTypeLabel(payment.primeType) }}</p>
                </div>
                <div>
                    <span class="text-surface-500">Salaire base</span>
                    <p class="font-semibold">{{ formatCurrency(payment.baseSalaryAmount) }}</p>
                </div>
                <div>
                    <span class="text-surface-500">Montant prime</span>
                    <p class="font-semibold">{{ formatCurrency(payment.primeAmount) }}</p>
                </div>
                <div>
                    <span class="text-surface-500">Total calculé</span>
                    <p class="font-semibold">{{ formatCurrency(payment.calculatedAmount) }}</p>
                </div>
                <div>
                    <span class="text-surface-500">Montant versé</span>
                    <p class="font-semibold text-primary-600 dark:text-primary-300">{{ formatCurrency(payment.paidAmount) }}</p>
                </div>
            </div>

            <div v-if="!editMode" class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <span class="text-surface-500">Date de règlement</span>
                    <p class="font-medium">{{ payment.paidAt || '—' }}</p>
                </div>
                <div>
                    <span class="text-surface-500">Mode de règlement</span>
                    <p class="font-medium">{{ payment.paymentMethod?.libelle || '—' }}</p>
                </div>
                <div class="col-span-2">
                    <span class="text-surface-500">Note</span>
                    <p class="font-medium">{{ payment.note || '—' }}</p>
                </div>
            </div>

            <div v-else class="space-y-3">
                <div class="space-y-1">
                    <label class="text-sm font-medium">Montant versé</label>
                    <InputNumber v-model="form.paidAmount" class="w-full" :min="0" suffix=" F CFA" />
                </div>
                <div v-if="hasFixedPrime" class="space-y-1">
                    <label class="text-sm font-medium">Montant prime</label>
                    <InputNumber v-model="form.primeAmount" class="w-full" :min="0" suffix=" F CFA" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Mode de règlement</label>
                    <Select
                        v-model="form.paymentMethodId"
                        :options="paymentMethods"
                        optionLabel="libelle"
                        optionValue="id"
                        class="w-full"
                        placeholder="Sélectionnez un mode"
                    />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Date de règlement</label>
                    <DatePicker v-model="form.paidAt" dateFormat="yy-mm-dd" showIcon class="w-full" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Note</label>
                    <Textarea v-model="form.note" rows="3" class="w-full" autoResize />
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-between w-full">
                <Button label="Imprimer le bulletin" icon="pi pi-print" severity="secondary" outlined @click="emit('print', payment)" />
                <div class="flex gap-2">
                    <Button label="Fermer" text severity="secondary" @click="close" />
                    <Button
                        v-if="!editMode"
                        label="Modifier"
                        icon="pi pi-pencil"
                        severity="info"
                        @click="editMode = true"
                    />
                    <Button
                        v-else
                        label="Enregistrer"
                        icon="pi pi-check"
                        :loading="loading"
                        @click="submit"
                    />
                </div>
            </div>
        </template>
    </Dialog>
</template>

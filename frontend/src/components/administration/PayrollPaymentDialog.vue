<script setup>
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import SalaryPreviewPanel from '@/components/administration/SalaryPreviewPanel.vue';

const props = defineProps({
    visible: Boolean,
    employees: {
        type: Array,
        default: () => []
    },
    paymentMethods: {
        type: Array,
        default: () => []
    },
    context: {
        type: Object,
        default: null
    },
    contextLoading: {
        type: Boolean,
        default: false
    },
    loading: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:visible', 'request-context', 'submit']);

const localVisible = ref(false);
const monthModel = ref(new Date());
const dayModel = ref(new Date());
const primeAmountModel = ref(null);
const form = ref({
    employeeId: null,
    paidAmount: null,
    paidAt: new Date(),
    paymentMethodId: null,
    note: ''
});

const selectedEmployee = computed(() => (props.employees || []).find((employee) => employee.id === form.value.employeeId) || null);

const isJournalier = computed(() => {
    const freq = props.context?.employee?.frequencePaiement || selectedEmployee.value?.frequencePaiement;
    return freq === 'journalier';
});

const hasFixedPrime = computed(() => {
    const type = props.context?.employee?.typePrime;
    return type === 'fixe';
});

const selectedMonth = computed(() => (monthModel.value ? monthModel.value.getMonth() + 1 : null));
const selectedYear = computed(() => (monthModel.value ? monthModel.value.getFullYear() : null));
const selectedDay = computed(() => {
    if (!isJournalier.value || !dayModel.value) return null;
    return dayModel.value.toISOString().slice(0, 10);
});

const canSubmit = computed(() => {
    if (!form.value.employeeId || !selectedMonth.value || !selectedYear.value || !form.value.paidAmount) {
        return false;
    }
    if (!form.value.paymentMethodId) return false;
    if (isJournalier.value && !selectedDay.value) return false;
    if (props.context && props.context.canPay === false) return false;
    return true;
});

const submitTooltip = computed(() => {
    if (!form.value.paymentMethodId) {
        return 'Sélectionnez un mode de règlement.';
    }
    if (props.context?.canPay === false) {
        return props.context?.blockReason || 'Cette période est déjà entièrement réglée.';
    }
    if (isJournalier.value && !selectedDay.value) {
        return 'Sélectionnez le jour travaillé à payer.';
    }
    return '';
});

const effectiveRemaining = computed(() => {
    if (!props.context) return null;
    const base = Number(props.context.breakdown?.baseSalary ?? props.context.baseSalaryAmount ?? 0);
    const prime = Number(primeAmountModel.value ?? props.context.primeAmount ?? 0);
    const alreadyPaid = Number(props.context.breakdown?.alreadyPaid ?? 0);
    return Math.max(0, base + prime - alreadyPaid);
});

watch(
    () => props.visible,
    (val) => {
        localVisible.value = val;
        if (val) {
            const now = new Date();
            monthModel.value = now;
            dayModel.value = now;
            primeAmountModel.value = null;
            form.value = {
                employeeId: null,
                paidAmount: null,
                paidAt: now,
                paymentMethodId: props.paymentMethods?.[0]?.id ?? null,
                note: ''
            };
        }
    },
    { immediate: true }
);

watch(localVisible, (val) => emit('update:visible', val));

watch(
    () => props.paymentMethods,
    (methods) => {
        if (!form.value.paymentMethodId && methods?.length) {
            form.value.paymentMethodId = methods[0].id;
        }
    },
    { immediate: true }
);

watch(
    () => [form.value.employeeId, selectedMonth.value, selectedYear.value, selectedDay.value],
    ([employeeId, month, year, day]) => {
        if (!employeeId || !month || !year) return;
        const employee = (props.employees || []).find((item) => item.id === employeeId);
        if (employee?.frequencePaiement === 'journalier' && !day) return;
        emit('request-context', { employeeId, month, year, day: day || null });
    }
);

watch(
    () => props.context,
    (val) => {
        if (!val) return;
        primeAmountModel.value = Number(val?.primeAmount || 0);
        const remaining = effectiveRemaining.value ?? Number(val?.breakdown?.remaining || val?.calculatedAmount || 0);
        form.value.paidAmount = remaining > 0 ? remaining : null;
    }
);

watch(primeAmountModel, () => {
    if (!props.context) return;
    const remaining = effectiveRemaining.value;
    form.value.paidAmount = remaining > 0 ? remaining : null;
});

const close = () => {
    localVisible.value = false;
};

const submit = () => {
    if (!canSubmit.value) return;

    const paidAt = form.value.paidAt instanceof Date ? form.value.paidAt.toISOString().slice(0, 10) : '';

    const payload = {
        employeeId: form.value.employeeId,
        month: selectedMonth.value,
        year: selectedYear.value,
        paidAmount: Number(form.value.paidAmount),
        paidAt,
        paymentMethodId: form.value.paymentMethodId,
        note: form.value.note || ''
    };

    if (isJournalier.value && selectedDay.value) {
        payload.day = selectedDay.value;
    }

    if (hasFixedPrime.value && primeAmountModel.value !== null) {
        payload.primeAmount = Number(primeAmountModel.value);
    }

    emit('submit', payload);
};

const employeeOptions = computed(() =>
    (props.employees || []).map((employee) => ({
        label: `${employee.prenom || ''} ${employee.nom || ''}`.trim() || `Employe #${employee.id}`,
        value: employee.id
    }))
);
</script>

<template>
    <Dialog v-model:visible="localVisible" modal header="Paiement de salaire" :style="{ width: '52rem', maxWidth: '95vw' }" @hide="close">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="space-y-4">
                <div class="space-y-1">
                    <label class="text-sm font-medium">Employé <span class="text-red-500">*</span></label>
                    <Select v-model="form.employeeId" :options="employeeOptions" optionLabel="label" optionValue="value" class="w-full" placeholder="Sélectionnez un employé" />
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium">{{ isJournalier ? 'Mois de référence' : 'Mois de paie' }} <span class="text-red-500">*</span></label>
                    <DatePicker v-model="monthModel" view="month" dateFormat="mm/yy" showIcon class="w-full" />
                </div>

                <div v-if="isJournalier" class="space-y-1">
                    <label class="text-sm font-medium">Jour travaillé à payer <span class="text-red-500">*</span></label>
                    <DatePicker v-model="dayModel" dateFormat="yy-mm-dd" showIcon class="w-full" />
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium">Montant versé <span class="text-red-500">*</span></label>
                    <InputNumber v-model="form.paidAmount" class="w-full" :min="0" mode="decimal" :minFractionDigits="0" :maxFractionDigits="2" suffix=" F CFA" />
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium">Mode de règlement <span class="text-red-500">*</span></label>
                    <Select v-model="form.paymentMethodId" :options="paymentMethods" optionLabel="libelle" optionValue="id" class="w-full" placeholder="Sélectionnez un mode" />
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium">Date de règlement</label>
                    <DatePicker v-model="form.paidAt" dateFormat="yy-mm-dd" showIcon class="w-full" />
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium">Note (optionnel)</label>
                    <Textarea v-model="form.note" rows="3" class="w-full" autoResize />
                </div>
            </div>

            <SalaryPreviewPanel :context="context" :loading="contextLoading" v-model:prime-amount="primeAmountModel" :editable-prime="hasFixedPrime" />
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Annuler" text severity="secondary" @click="close" />
                <span v-tooltip.top="submitTooltip">
                    <Button label="Enregistrer" icon="pi pi-check" :loading="loading" :disabled="!canSubmit" @click="submit" />
                </span>
            </div>
        </template>
    </Dialog>
</template>

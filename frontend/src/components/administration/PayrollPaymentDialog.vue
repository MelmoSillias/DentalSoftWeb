<script setup>
import { computed, ref, watch } from 'vue';
import AppDialog from '@/components/layout/AppDialog.vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Message from 'primevue/message';
import SalaryPreviewPanel from '@/components/administration/SalaryPreviewPanel.vue';

const props = defineProps({
    visible: Boolean,
    employees: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    context: { type: Object, default: null },
    contextLoading: Boolean,
    contextError: { type: String, default: '' },
    loading: Boolean,
    initialEmployeeId: { type: Number, default: null },
    initialPeriod: { type: Date, default: null }
});
const emit = defineEmits(['update:visible', 'request-context', 'submit', 'open-daily']);
const localVisible = ref(false);
const monthModel = ref(new Date());
const dayModel = ref(new Date());
const primeAmountModel = ref(null);
const indemniteSpecialeModel = ref(0);
const inpsModel = ref(0);
const form = ref({ employeeId: null, paidAmount: null, paidAt: new Date(), paymentMethodId: null, note: '' });
const localDay = (date) => (date instanceof Date && !Number.isNaN(date.getTime()) ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}` : null);
const roundAmount = (value) => Math.round((Number(value) + Number.EPSILON) * 100) / 100;
const selectedEmployee = computed(() => props.employees.find((employee) => Number(employee.id) === Number(form.value.employeeId)) || null);
const isJournalier = computed(() => selectedEmployee.value?.frequencePaiement === 'journalier');
const selectedMonth = computed(() => monthModel.value?.getMonth() + 1 || null);
const selectedYear = computed(() => monthModel.value?.getFullYear() || null);
const selectedDay = computed(() => (isJournalier.value ? localDay(dayModel.value) : null));
const dayMatchesPeriod = computed(() => !isJournalier.value || (dayModel.value?.getMonth() + 1 === selectedMonth.value && dayModel.value?.getFullYear() === selectedYear.value));
const currentContext = computed(() => {
    const context = props.context;
    if (!localVisible.value || !form.value.employeeId || props.contextLoading || !context) return null;
    if (Number(context.employee?.id) !== Number(form.value.employeeId) || context.period?.month !== selectedMonth.value || context.period?.year !== selectedYear.value) return null;
    if ((context.period?.day || null) !== selectedDay.value) return null;
    return context;
});
const salaryLocked = computed(() => currentContext.value?.salaryLocked === true);
const hasFixedPrime = computed(() => currentContext.value?.typePrime === 'fixe' && !salaryLocked.value);
const effectiveNet = computed(() => {
    if (!currentContext.value) return 0;
    if (salaryLocked.value) return Number(currentContext.value.calculatedAmount);
    return roundAmount(Number(currentContext.value.baseSalaryAmount || 0) + Number(primeAmountModel.value || 0) + Number(indemniteSpecialeModel.value || 0) - Number(inpsModel.value || 0));
});
const effectiveRemaining = computed(() => Math.max(0, roundAmount(effectiveNet.value - Number(currentContext.value?.breakdown?.alreadyPaid || 0))));
const submitTooltip = computed(() => {
    if (!form.value.employeeId) return 'Sélectionnez un employé pour préparer son paiement.';
    if (props.contextLoading) return 'Chargement du salaire en cours.';
    if (!dayMatchesPeriod.value) return 'Le jour travaillé doit appartenir au mois sélectionné.';
    if (!currentContext.value) return props.contextError || 'Le calcul du salaire est indisponible.';
    if (effectiveNet.value < 0) return 'La retenue INPS ne peut pas dépasser le salaire brut.';
    if ([primeAmountModel.value, indemniteSpecialeModel.value, inpsModel.value].some((value) => !Number.isFinite(Number(value)) || Number(value) < 0)) return 'Les montants doivent être positifs ou nuls.';
    if (effectiveRemaining.value <= 0.01) return 'Cette période est déjà entièrement réglée.';
    if (!Number.isFinite(Number(form.value.paidAmount)) || Number(form.value.paidAmount) <= 0) return 'Saisissez un montant versé supérieur à zéro.';
    if (roundAmount(form.value.paidAmount) > effectiveRemaining.value) return 'Le montant versé dépasse le reste à payer.';
    if (!form.value.paymentMethodId) return 'Sélectionnez un mode de règlement.';
    if (!localDay(form.value.paidAt)) return 'Sélectionnez une date de règlement valide.';
    return '';
});
const canSubmit = computed(() => !props.loading && !submitTooltip.value);
const employeeOptions = computed(() => props.employees.map((employee) => ({ label: `${employee.prenom || ''} ${employee.nom || ''}`.trim() || `Employé #${employee.id}`, value: employee.id })));

watch(
    () => props.visible,
    (visible) => {
        localVisible.value = visible;
        if (!visible) return;
        const now = new Date();
        const period = props.initialPeriod ? new Date(props.initialPeriod) : now;
        monthModel.value = period;
        dayModel.value = new Date(period.getFullYear(), period.getMonth(), period.getFullYear() === now.getFullYear() && period.getMonth() === now.getMonth() ? now.getDate() : 1);
        primeAmountModel.value = null;
        indemniteSpecialeModel.value = 0;
        inpsModel.value = 0;
        form.value = { employeeId: props.initialEmployeeId, paidAmount: null, paidAt: now, paymentMethodId: props.paymentMethods[0]?.id ?? null, note: '' };
    },
    { immediate: true }
);
watch(localVisible, (visible) => emit('update:visible', visible));
watch(
    () => props.paymentMethods,
    (methods) => {
        if (!form.value.paymentMethodId) form.value.paymentMethodId = methods[0]?.id ?? null;
    }
);
watch([selectedMonth, selectedYear], ([month, year]) => {
    if (month && year && !dayMatchesPeriod.value) dayModel.value = new Date(year, month - 1, 1);
});
watch(
    () => [localVisible.value, form.value.employeeId, selectedMonth.value, selectedYear.value, selectedDay.value],
    ([visible, employeeId, month, year, day]) => {
        primeAmountModel.value = null;
        indemniteSpecialeModel.value = 0;
        inpsModel.value = 0;
        form.value.paidAmount = null;
        if (visible && employeeId && isJournalier.value) {
            emit('request-context', { employeeId: null, month, year, day: null });
            emit('open-daily', { id: employeeId, period: monthModel.value });
            return;
        }
        emit('request-context', { employeeId: visible && dayMatchesPeriod.value ? employeeId : null, month, year, day });
    }
);
watch(currentContext, (context) => {
    if (!context) return;
    primeAmountModel.value = Number(context.primeAmount || 0);
    indemniteSpecialeModel.value = Number(context.indemniteSpeciale || 0);
    inpsModel.value = Number(context.inps || 0);
    form.value.paidAmount = effectiveRemaining.value > 0 ? effectiveRemaining.value : null;
});
watch(effectiveRemaining, (remaining, previous) => {
    if (!currentContext.value) return;
    if (form.value.paidAmount === null || Number(form.value.paidAmount) === previous || Number(form.value.paidAmount) > remaining) {
        form.value.paidAmount = remaining > 0 ? remaining : null;
    }
});
const close = () => {
    localVisible.value = false;
};
const submit = () => {
    if (!canSubmit.value) return;
    const payload = {
        employeeId: form.value.employeeId,
        month: selectedMonth.value,
        year: selectedYear.value,
        paidAmount: roundAmount(form.value.paidAmount),
        paidAt: localDay(form.value.paidAt),
        paymentMethodId: form.value.paymentMethodId,
        note: form.value.note || '',
        indemniteSpeciale: Number(indemniteSpecialeModel.value || 0),
        inps: Number(inpsModel.value || 0)
    };
    if (isJournalier.value) payload.day = selectedDay.value;
    if (hasFixedPrime.value) payload.primeAmount = Number(primeAmountModel.value || 0);
    emit('submit', payload);
};
</script>

<template>
    <AppDialog
        v-model:visible="localVisible"
        title="Paiement de salaire"
        icon="pi pi-wallet"
        icon-tone="success"
        size="xl"
        :loading="loading"
        :confirm-disabled="!canSubmit"
        cancel-label="Annuler"
        confirm-label="Enregistrer"
        @cancel="close"
        @confirm="submit"
        @hide="close"
    >
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="space-y-4">
                <div class="space-y-1">
                    <label class="text-sm font-medium">Employé <span class="text-red-500">*</span></label>
                    <Select v-model="form.employeeId" :options="employeeOptions" optionLabel="label" optionValue="value" class="w-full" showClear filter :disabled="loading" placeholder="Sélectionnez un employé" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Mois de paie <span class="text-red-500">*</span></label>
                    <DatePicker v-model="monthModel" view="month" dateFormat="mm/yy" showIcon class="w-full" :disabled="loading" />
                </div>
                <div v-if="isJournalier" class="space-y-1">
                    <label class="text-sm font-medium">Jour travaillé à payer <span class="text-red-500">*</span></label>
                    <DatePicker v-model="dayModel" dateFormat="yy-mm-dd" showIcon class="w-full" :disabled="loading" />
                </div>
                <Message v-if="!form.employeeId" severity="info" :closable="false">Sélectionnez un employé pour afficher son salaire et saisir un paiement.</Message>
                <fieldset :disabled="!currentContext || loading" class="space-y-4 border-0 p-0 m-0 min-w-0" :class="{ 'opacity-50': !currentContext }">
                    <Message v-if="salaryLocked" severity="info" :closable="false">Le premier calcul de cette période est conservé. Vous pouvez verser tout ou partie du solde restant.</Message>
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Indemnité spéciale</label>
                        <InputNumber v-model="indemniteSpecialeModel" @input="indemniteSpecialeModel = $event.value" :disabled="salaryLocked || !currentContext || loading" class="w-full" :min="0" :maxFractionDigits="2" suffix=" F CFA" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium">INPS (retenue)</label>
                        <InputNumber v-model="inpsModel" @input="inpsModel = $event.value" :disabled="salaryLocked || !currentContext || loading" class="w-full" :min="0" :maxFractionDigits="2" suffix=" F CFA" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Montant versé <span class="text-red-500">*</span></label>
                        <InputNumber v-model="form.paidAmount" @input="form.paidAmount = $event.value" :disabled="!currentContext || loading" class="w-full" :min="0" :maxFractionDigits="2" suffix=" F CFA" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Mode de règlement <span class="text-red-500">*</span></label>
                        <Select v-model="form.paymentMethodId" :options="paymentMethods" optionLabel="libelle" optionValue="id" class="w-full" :disabled="!currentContext || loading" placeholder="Sélectionnez un mode" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Date de règlement</label>
                        <DatePicker v-model="form.paidAt" dateFormat="yy-mm-dd" showIcon class="w-full" :disabled="!currentContext || loading" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Note (optionnel)</label>
                        <Textarea v-model="form.note" rows="3" class="w-full" autoResize />
                    </div>
                </fieldset>
            </div>
            <div>
                <Message v-if="contextError && form.employeeId && !contextLoading && !currentContext" severity="error" :closable="false">{{ contextError }}</Message>
                <SalaryPreviewPanel
                    :context="currentContext"
                    :loading="contextLoading && !!form.employeeId"
                    v-model:prime-amount="primeAmountModel"
                    :editable-prime="hasFixedPrime"
                    :indemnite-speciale="indemniteSpecialeModel"
                    :inps="inpsModel"
                    :paid-amount="Number(form.paidAmount || 0)"
                />
            </div>
        </div>
        <template #footer>
            <div class="flex justify-end gap-2 w-full">
                <Button label="Annuler" text severity="secondary" :disabled="loading" @click="close" />
                <span v-tooltip.top="submitTooltip"><Button label="Enregistrer" icon="pi pi-check" :loading="loading" :disabled="!canSubmit" @click="submit" /></span>
            </div>
        </template>
    </AppDialog>
</template>

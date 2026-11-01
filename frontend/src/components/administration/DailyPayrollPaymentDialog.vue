<script setup>
import { computed, ref, watch } from 'vue';
import AppDialog from '@/components/layout/AppDialog.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Tag from 'primevue/tag';

const props = defineProps({
    visible: Boolean,
    employees: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    initialEmployeeId: { type: Number, default: null },
    initialPeriod: { type: Date, default: null },
    loadContext: { type: Function, required: true },
    refreshKey: { type: Number, default: 0 },
    loading: Boolean
});
const emit = defineEmits(['update:visible', 'submit', 'detail', 'print']);
const localVisible = ref(false);
const employeeId = ref(null);
const monthModel = computed(() => props.initialPeriod || new Date());
const periodLabel = computed(() => monthModel.value.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }));
const paymentMethodId = ref(null);
const paidAt = ref(new Date());
const context = ref(null);
const contextLoading = ref(false);
const error = ref('');
const pendingDay = ref(null);
const paymentRequests = new Map();
let requestId = 0;
const employeeOptions = computed(() =>
    props.employees.filter((employee) => employee.frequencePaiement === 'journalier').map((employee) => ({ label: employee.fullname || `${employee.prenom || ''} ${employee.nom || ''}`.trim(), value: employee.id }))
);
const month = computed(() => monthModel.value?.getMonth() + 1 || null);
const year = computed(() => monthModel.value?.getFullYear() || null);
const localDate = (date) => (date instanceof Date && !Number.isNaN(date.getTime()) ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}` : null);
const amount = (value) => `${Number(value || 0).toLocaleString('fr-FR')} F CFA`;
const date = (value) => new Date(`${value}T00:00:00`).toLocaleDateString('fr-FR');
const paymentReady = computed(() => !!employeeId.value && !!paymentMethodId.value && !!localDate(paidAt.value) && !!context.value && !contextLoading.value && !props.loading);
const loadDays = async () => {
    const currentRequest = ++requestId;
    context.value = null;
    error.value = '';
    contextLoading.value = false;
    if (!localVisible.value || !employeeId.value || !month.value || !year.value) return;
    contextLoading.value = true;
    try {
        const result = await props.loadContext(employeeId.value, { month: month.value, year: year.value });
        if (currentRequest === requestId) context.value = result;
    } catch (err) {
        if (currentRequest === requestId) error.value = err?.response?.data?.message || err?.message || 'Chargement des jours impossible.';
    } finally {
        if (currentRequest === requestId) contextLoading.value = false;
    }
};
watch(
    () => props.visible,
    (visible) => {
        localVisible.value = visible;
        if (visible) {
            employeeId.value = props.initialEmployeeId;
            paymentMethodId.value = props.paymentMethods[0]?.id ?? null;
            paidAt.value = new Date();
            pendingDay.value = null;
        }
    },
    { immediate: true }
);
watch(localVisible, (visible) => emit('update:visible', visible));
watch([localVisible, employeeId, month, year, () => props.refreshKey], loadDays, { immediate: true });
watch(
    () => props.paymentMethods,
    (methods) => {
        if (!paymentMethodId.value) paymentMethodId.value = methods[0]?.id ?? null;
    }
);
watch(
    () => props.loading,
    (loading) => {
        if (!loading) pendingDay.value = null;
    }
);
const payDay = (day) => {
    if (!paymentReady.value || pendingDay.value || !day.canPay || day.hasPayment) return;
    pendingDay.value = day.day;
    const key = `${employeeId.value}:${day.day}`;
    if (!paymentRequests.has(key)) paymentRequests.set(key, crypto.randomUUID());
    emit('submit', { requestId: paymentRequests.get(key), employeeId: employeeId.value, month: month.value, year: year.value, day: day.day, paidAmount: day.calculatedAmount, paidAt: localDate(paidAt.value), paymentMethodId: paymentMethodId.value });
};
watch(context, (value) => {
    for (const day of value?.days || []) {
        if (day.hasPayment) paymentRequests.delete(`${employeeId.value}:${day.day}`);
    }
});
</script>

<template>
    <AppDialog v-model:visible="localVisible" title="Paiement des jours d’activité" icon="pi pi-calendar" icon-tone="success" size="xl" :show-footer="true" :loading="loading">
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-2">Employé journalier</label
                    ><Select v-model="employeeId" :options="employeeOptions" optionLabel="label" optionValue="value" showClear filter :disabled="loading" class="w-full" placeholder="Sélectionnez un employé" />
                </div>
                <Message severity="info" :closable="false" class="self-end">
                    <div class="text-xs font-medium">Période sélectionnée sur la page</div>
                    <div class="font-semibold capitalize">{{ periodLabel }}</div>
                </Message>
                <div>
                    <label class="block text-sm font-medium mb-2">Mode de règlement</label
                    ><Select v-model="paymentMethodId" :options="paymentMethods" optionLabel="libelle" optionValue="id" :disabled="loading" class="w-full" placeholder="Sélectionnez un mode" />
                </div>
                <div><label class="block text-sm font-medium mb-2">Date de règlement</label><DatePicker v-model="paidAt" dateFormat="dd/mm/yy" showIcon :disabled="loading" class="w-full" /></div>
            </div>
            <Message v-if="!employeeId" severity="info" :closable="false">Sélectionnez un employé pour charger ses jours d’activité.</Message>
            <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
            <div v-if="context" class="flex flex-wrap items-center gap-3">
                <Tag :value="`${context.paidDays} / ${context.activityDays} jours payés`" :severity="context.activityDays > 0 && context.paidDays === context.activityDays ? 'success' : 'info'" />
                <span class="text-sm"
                    >Total du mois : <strong>{{ amount(context.calculatedAmount) }}</strong></span
                >
                <span class="text-sm"
                    >Reste à payer : <strong>{{ amount(context.breakdown.remaining) }}</strong></span
                >
            </div>
            <p v-if="employeeId" class="text-sm text-surface-500">Chaque jour est réglé intégralement en un seul paiement.</p>
            <DataTable v-if="employeeId" :value="context?.days || []" :loading="contextLoading" dataKey="day" scrollable scrollHeight="420px">
                <Column header="Jour"
                    ><template #body="{ data }">{{ date(data.day) }}</template></Column
                >
                <Column header="Revenu total"
                    ><template #body="{ data }">{{ amount(data.revenueTotal) }}</template></Column
                >
                <Column header="Salaire"
                    ><template #body="{ data }"
                        ><div>{{ data.salaryType === 'pourcentage' ? `${data.salaryValue} %` : 'Fixe' }}</div>
                        <div class="text-xs text-surface-500">{{ amount(data.baseSalaryAmount) }}</div></template
                    ></Column
                >
                <Column header="Prime"
                    ><template #body="{ data }"
                        ><div>{{ data.typePrime === 'actes' ? `${data.valeurPrime} %` : data.typePrime === 'fixe' ? 'Fixe' : '—' }}</div>
                        <div v-if="data.primeAmount" class="text-xs text-surface-500">{{ amount(data.primeAmount) }}</div></template
                    ></Column
                >
                <Column header="Net du jour"
                    ><template #body="{ data }"
                        ><strong>{{ amount(data.calculatedAmount) }}</strong></template
                    ></Column
                >
                <Column header="État"
                    ><template #body="{ data }"><Tag :value="data.isPaid ? 'Payé' : data.hasPayment ? 'Paiement partiel existant' : 'Non payé'" :severity="data.isPaid ? 'success' : data.hasPayment ? 'warn' : 'secondary'" /></template
                ></Column>
                <Column header="Actions"
                    ><template #body="{ data }">
                        <Button v-if="!data.hasPayment" label="Payer ce jour" icon="pi pi-wallet" size="small" :loading="loading && pendingDay === data.day" :disabled="!paymentReady || !data.canPay || !!pendingDay" @click="payDay(data)" />
                        <div v-else class="flex gap-1">
                            <Button icon="pi pi-eye" text rounded aria-label="Voir le paiement" @click="emit('detail', data.payments[0])" /><Button
                                icon="pi pi-print"
                                text
                                rounded
                                aria-label="Imprimer le bulletin"
                                @click="emit('print', data.payments[0])"
                            />
                        </div> </template
                ></Column>
                <template #empty>{{ contextLoading ? 'Chargement des jours…' : 'Aucun jour d’activité pour cette période.' }}</template>
            </DataTable>
        </div>
        <template #footer
            ><div class="flex justify-end w-full"><Button label="Fermer" severity="secondary" text :disabled="loading" @click="localVisible = false" /></div
        ></template>
    </AppDialog>
</template>

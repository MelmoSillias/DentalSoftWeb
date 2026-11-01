<script setup>
import { computed, ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { groupPayrollPayments } from '@/utils/payrollGroups';

const props = defineProps({ payments: { type: Array, default: () => [] }, loading: Boolean });
const emit = defineEmits(['detail', 'print', 'remove']);
const groups = computed(() => groupPayrollPayments(props.payments));
const expanded = ref({});
const money = (value) => `${Number(value || 0).toLocaleString('fr-FR')} F CFA`;
const date = (value) => (value ? new Date(`${value}T12:00:00`).toLocaleDateString('fr-FR') : '—');
const period = (row) => (row.workedDay ? date(row.workedDay) : new Date(row.year, row.month - 1).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }));
</script>
<template>
    <DataTable v-model:expandedRows="expanded" :value="groups" :loading="loading" dataKey="id">
        <Column expander style="width: 3rem" />
        <Column field="employeeName" header="Employé"
            ><template #body="{ data }"
                ><div class="font-medium">{{ data.employeeName }}</div>
                <span class="text-xs text-surface-500">{{ data.payments.length }} paiement(s)</span></template
            ></Column
        >
        <Column header="Période"
            ><template #body="{ data }">{{ period(data) }}</template></Column
        >
        <Column header="Net à verser"
            ><template #body="{ data }">{{ money(data.net) }}</template></Column
        >
        <Column header="Total versé"
            ><template #body="{ data }"
                ><span class="font-semibold">{{ money(data.paid) }}</span></template
            ></Column
        >
        <Column header="Reste"
            ><template #body="{ data }">{{ money(data.remaining) }}</template></Column
        >
        <Column header="Actions"
            ><template #body="{ data }"
                ><template v-if="data.payments.length === 1"
                    ><Button icon="pi pi-eye" text rounded aria-label="Voir le paiement" @click="emit('detail', data.payments[0])" /><Button
                        icon="pi pi-print"
                        text
                        rounded
                        severity="secondary"
                        aria-label="Imprimer le bulletin"
                        @click="emit('print', data.payments[0])" /><Button
                        v-if="data.payments[0].status !== 'cancelled'"
                        icon="pi pi-ban"
                        text
                        rounded
                        severity="danger"
                        aria-label="Annuler une saisie erronée"
                        @click="(event) => emit('remove', event, data.payments[0])" /></template
                ><Button v-else label="Voir les paiements" text @click="expanded[data.id] = !expanded[data.id]" /></template
        ></Column>
        <template #expansion="{ data }"
            ><div class="p-4 bg-surface-50 dark:bg-surface-900 rounded-lg">
                <DataTable :value="data.payments" dataKey="id" size="small"
                    ><Column field="id" header="N°" /><Column header="Date"
                        ><template #body="{ data: payment }">{{ date(payment.paidAt) }}</template></Column
                    ><Column header="Montant"
                        ><template #body="{ data: payment }">{{ money(payment.paidAmount) }}</template></Column
                    ><Column header="Mode"
                        ><template #body="{ data: payment }">{{ payment.paymentMethod?.libelle }}</template></Column
                    ><Column header="Statut"
                        ><template #body="{ data: payment }"><Tag :value="payment.status === 'cancelled' ? 'Annulé' : 'Actif'" :severity="payment.status === 'cancelled' ? 'secondary' : 'success'" /></template></Column
                    ><Column header="Actions"
                        ><template #body="{ data: payment }"
                            ><Button icon="pi pi-eye" text rounded aria-label="Voir le paiement" @click="emit('detail', payment)" /><Button
                                icon="pi pi-print"
                                text
                                rounded
                                severity="secondary"
                                aria-label="Imprimer le bulletin"
                                @click="emit('print', payment)" /><Button
                                v-if="payment.status !== 'cancelled'"
                                icon="pi pi-ban"
                                text
                                rounded
                                severity="danger"
                                aria-label="Annuler une saisie erronée"
                                @click="(event) => emit('remove', event, payment)" /></template></Column
                ></DataTable></div
        ></template>
        <template #empty>Aucun paiement enregistré pour cette période.</template>
    </DataTable>
</template>

<script setup>
import { ref, watch } from 'vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import { formatFrequenceLabel, formatPrimeTypeLabel, formatSalaryTypeLabel } from '@/utils/payrollUtils';

const props = defineProps({ rows: { type: Array, default: () => [] }, loading: Boolean });
const emit = defineEmits(['pay', 'detail', 'print', 'remove']);
const expandedRows = ref({});
watch(
    () => props.rows,
    () => {
        expandedRows.value = {};
    }
);
const amount = (value) => `${Number(value || 0).toLocaleString('fr-FR')} F CFA`;
const date = (value) => (value ? new Date(value).toLocaleDateString('fr-FR') : '—');
const parameter = (value, percentage) => (percentage ? `${Number(value || 0)} %` : amount(value));
</script>

<template>
    <DataTable v-model:expandedRows="expandedRows" :value="rows" :loading="loading" dataKey="id" paginator :rows="10" :rowsPerPageOptions="[10, 25, 50]">
        <Column expander style="width: 3rem" />
        <Column field="employeeName" header="Employé">
            <template #body="{ data }">
                <div class="font-semibold">{{ data.employeeName }}</div>
                <div class="text-xs text-surface-500">{{ data.employeeFonction || '—' }}</div>
                <div v-if="data.context.period.day" class="text-xs text-surface-500">Jour à payer : {{ date(data.context.period.day) }}</div>
            </template>
        </Column>
        <Column header="Paramètres du salaire">
            <template #body="{ data }">
                <div>{{ formatSalaryTypeLabel(data.context.salaryType) }} · {{ parameter(data.context.salaryValue, data.context.salaryType === 'pourcentage') }}</div>
                <div class="text-xs text-surface-500">{{ formatFrequenceLabel(data.context.frequencePaiement) }}</div>
                <div v-if="data.context.typePrime !== 'aucune'" class="text-xs text-surface-500">Prime {{ formatPrimeTypeLabel(data.context.typePrime) }} · {{ parameter(data.context.valeurPrime, data.context.typePrime === 'actes') }}</div>
            </template>
        </Column>
        <Column header="Indemnité / INPS">
            <template #body="{ data }"
                ><div>{{ amount(data.context.indemniteSpeciale) }}</div>
                <div class="text-xs text-surface-500">INPS : {{ amount(data.context.inps) }}</div></template
            >
        </Column>
        <Column header="Net à payer">
            <template #body="{ data }"
                ><span class="font-semibold">{{ amount(data.context.calculatedAmount) }}</span></template
            >
        </Column>
        <Column header="Déjà versé">
            <template #body="{ data }">{{ amount(data.context.breakdown.alreadyPaid) }}</template>
        </Column>
        <Column header="Reste à payer">
            <template #body="{ data }"
                ><span class="font-semibold">{{ amount(data.context.breakdown.remaining) }}</span></template
            >
        </Column>
        <Column header="État">
            <template #body="{ data }"
                ><Tag :value="data.context.salaryLocked ? (data.context.canPay ? 'Partiellement réglé' : 'Réglé') : 'À calculer au premier paiement'" :severity="data.context.salaryLocked ? (data.context.canPay ? 'warn' : 'success') : 'info'"
            /></template>
        </Column>
        <Column header="Actions">
            <template #body="{ data }"><Button label="Payer" icon="pi pi-wallet" size="small" :disabled="data.context.salaryLocked && !data.context.canPay" @click="emit('pay', data)" /></template>
        </Column>
        <template #expansion="{ data }">
            <div class="p-4 rounded-lg bg-surface-50 dark:bg-surface-900/40">
                <h4 class="font-semibold mb-3">Paiements du mois — {{ data.employeeName }}</h4>
                <DataTable :value="data.payments" dataKey="id">
                    <Column header="Période"
                        ><template #body="{ data: payment }">{{ payment.workedDay ? date(payment.workedDay) : `${String(payment.month).padStart(2, '0')}/${payment.year}` }}</template></Column
                    >
                    <Column header="Salaire net"
                        ><template #body="{ data: payment }">{{ amount(payment.calculatedAmount) }}</template></Column
                    >
                    <Column header="Montant versé"
                        ><template #body="{ data: payment }">{{ amount(payment.paidAmount) }}</template></Column
                    >
                    <Column header="Mode"
                        ><template #body="{ data: payment }">{{ payment.paymentMethod?.libelle || '—' }}</template></Column
                    >
                    <Column header="Date"
                        ><template #body="{ data: payment }">{{ date(payment.paidAt) }}</template></Column
                    >
                    <Column header="Actions">
                        <template #body="{ data: payment }">
                            <div class="flex gap-2">
                                <Button icon="pi pi-eye" text rounded severity="info" aria-label="Voir le paiement" @click="emit('detail', payment)" />
                                <Button icon="pi pi-print" text rounded severity="secondary" aria-label="Imprimer le bulletin" @click="emit('print', payment)" />
                                <Button icon="pi pi-trash" text rounded severity="danger" aria-label="Supprimer le paiement" @click="(event) => emit('remove', event, payment)" />
                            </div>
                        </template>
                    </Column>
                    <template #empty>Aucun paiement enregistré pour ce mois.</template>
                </DataTable>
            </div>
        </template>
        <template #empty>Aucun employé trouvé pour cette sélection.</template>
    </DataTable>
</template>

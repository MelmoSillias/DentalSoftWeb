<script setup>
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import { formatFrequenceLabel, formatPrimeTypeLabel, formatSalaryTypeLabel } from '@/utils/payrollUtils';

const props = defineProps({ rows: { type: Array, default: () => [] }, loading: Boolean });
const emit = defineEmits(['pay', 'detail', 'print', 'remove']);
const expandedRows = ref({});
const groupedRows = computed(() => props.rows.map((row) => ({ ...row, frequencyOrder: row.context.frequencePaiement === 'mensuel' ? 0 : 1 })).sort((a, b) => a.frequencyOrder - b.frequencyOrder || a.employeeName.localeCompare(b.employeeName, 'fr')));
watch(
    () => props.rows,
    () => {
        expandedRows.value = {};
    }
);
const amount = (value) => `${Number(value || 0).toLocaleString('fr-FR')} F CFA`;
const date = (value) => (value ? new Date(value).toLocaleDateString('fr-FR') : '—');
const parameter = (value, percentage) => (percentage ? `${Number(value || 0)} %` : amount(value));
const columnCount = 9;
const hasCalculatedSalary = (row) => row.context.frequencePaiement === 'journalier' || row.context.salaryLocked === true;
const groupSummaries = computed(() => {
    const groups = {};
    for (const row of groupedRows.value) {
        const group = (groups[row.frequencyOrder] ??= { employees: 0, paidEmployees: 0, calculatedEmployees: 0, net: 0, paid: 0, remaining: 0, paidDays: 0, activityDays: 0 });
        group.employees++;
        const paid = Number(row.context.breakdown?.alreadyPaid || 0);
        if (paid > 0) group.paidEmployees++;
        group.paid += paid;
        if (hasCalculatedSalary(row)) {
            group.calculatedEmployees++;
            group.net += Number(row.context.calculatedAmount || 0);
            group.remaining += Number(row.context.breakdown?.remaining || 0);
        }
        group.paidDays += Number(row.context.paidDays || 0);
        group.activityDays += Number(row.context.activityDays || 0);
    }
    return groups;
});
</script>

<template>
    <DataTable
        v-model:expandedRows="expandedRows"
        :value="groupedRows"
        :loading="loading"
        dataKey="id"
        rowGroupMode="subheader"
        groupRowsBy="frequencyOrder"
        sortField="frequencyOrder"
        :sortOrder="1"
        :pt="{ rowGroupHeaderCell: { colspan: columnCount }, rowGroupFooterCell: { colspan: columnCount } }"
    >
        <template #groupheader="{ data }"
            ><div class="flex flex-wrap items-center justify-between gap-3 py-3 px-4 rounded-lg bg-primary-50 dark:bg-primary-950/40 border border-primary-100 dark:border-primary-900">
                <div class="flex items-center gap-3">
                    <i :class="['pi text-primary text-lg', data.frequencyOrder === 0 ? 'pi-calendar' : 'pi-sun']" aria-hidden="true" />
                    <div>
                        <div class="font-semibold">{{ data.frequencyOrder === 0 ? 'Employés mensuels' : 'Employés journaliers' }}</div>
                        <div class="text-xs text-surface-500">
                            {{ data.frequencyOrder === 0 ? 'Salaire calculé au premier paiement' : `${groupSummaries[data.frequencyOrder].paidDays} / ${groupSummaries[data.frequencyOrder].activityDays} jours payés` }}
                        </div>
                    </div>
                </div>
                <Tag
                    :value="`${groupSummaries[data.frequencyOrder].paidEmployees} / ${groupSummaries[data.frequencyOrder].employees} employés ayant reçu un paiement`"
                    :severity="groupSummaries[data.frequencyOrder].paidEmployees === groupSummaries[data.frequencyOrder].employees ? 'success' : 'info'"
                /></div
        ></template>
        <template #groupfooter="{ data }">
            <div class="flex flex-wrap items-center justify-between gap-4 p-4 rounded-lg bg-surface-100 dark:bg-surface-800">
                <div>
                    <div class="font-semibold">Total {{ data.frequencyOrder === 0 ? 'des mensuels' : 'des journaliers' }}</div>
                    <div v-if="data.frequencyOrder === 0" class="text-xs text-surface-500">{{ groupSummaries[data.frequencyOrder].calculatedEmployees }} / {{ groupSummaries[data.frequencyOrder].employees }} salaires calculés</div>
                </div>
                <div class="flex flex-wrap gap-x-8 gap-y-3 text-sm">
                    <div>
                        <div class="text-surface-500">Net à payer</div>
                        <strong>{{ groupSummaries[data.frequencyOrder].calculatedEmployees ? amount(groupSummaries[data.frequencyOrder].net) : '-' }}</strong>
                    </div>
                    <div>
                        <div class="text-surface-500">Déjà versé</div>
                        <strong class="text-green-600 dark:text-green-400">{{ amount(groupSummaries[data.frequencyOrder].paid) }}</strong>
                    </div>
                    <div>
                        <div class="text-surface-500">Reste à payer</div>
                        <strong>{{ groupSummaries[data.frequencyOrder].calculatedEmployees ? amount(groupSummaries[data.frequencyOrder].remaining) : '-' }}</strong>
                    </div>
                </div>
            </div>
        </template>
        <Column expander style="width: 3rem" />
        <Column field="employeeName" header="Employé">
            <template #body="{ data }">
                <div class="font-semibold">{{ data.employeeName }}</div>
                <div class="text-xs text-surface-500">{{ data.employeeFonction || '—' }}</div>
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
                ><span v-if="data.context.frequencePaiement === 'journalier'">—</span
                ><template v-else
                    ><div>{{ amount(data.context.indemniteSpeciale) }}</div>
                    <div class="text-xs text-surface-500">INPS : {{ amount(data.context.inps) }}</div></template
                ></template
            >
        </Column>
        <Column header="Net à payer">
            <template #body="{ data }"
                ><span class="font-semibold">{{ hasCalculatedSalary(data) ? amount(data.context.calculatedAmount) : '-' }}</span></template
            >
        </Column>
        <Column header="Déjà versé">
            <template #body="{ data }">{{ amount(data.context.breakdown.alreadyPaid) }}</template>
        </Column>
        <Column header="Reste à payer">
            <template #body="{ data }"
                ><span class="font-semibold">{{ hasCalculatedSalary(data) ? amount(data.context.breakdown.remaining) : '-' }}</span></template
            >
        </Column>
        <Column header="État">
            <template #body="{ data }">
                <Tag
                    v-if="data.context.frequencePaiement === 'journalier'"
                    :value="`${data.context.paidDays} / ${data.context.activityDays} jours payés`"
                    :severity="data.context.activityDays > 0 && data.context.paidDays === data.context.activityDays ? 'success' : 'info'" />
                <Tag v-else :value="data.context.salaryLocked ? (data.context.canPay ? 'Partiellement réglé' : 'Réglé') : 'À calculer au premier paiement'" :severity="data.context.salaryLocked ? (data.context.canPay ? 'warn' : 'success') : 'info'"
            /></template>
        </Column>
        <Column header="Actions">
            <template #body="{ data }"
                ><Button :label="data.context.frequencePaiement === 'journalier' ? 'Voir les jours' : 'Payer'" icon="pi pi-wallet" size="small" :disabled="data.context.salaryLocked && !data.context.canPay" @click="emit('pay', data)"
            /></template>
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

<script setup>
import { FilterMatchMode } from '@primevue/core/api';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';

const props = defineProps({
    consultations: {
        type: Array,
        default: () => []
    },
    loading: {
        type: Boolean,
        default: false
    }
});

const dt = ref(null);
const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});

const filterValue = computed({
    get: () => filters.value.global?.value ?? '',
    set: (value) => {
        filters.value = { ...filters.value, global: { ...filters.value.global, value } };
    }
});

const totalCountLabel = computed(() => `${props.consultations.length} consultation(s)`);

const exportCSV = () => {
    if (dt.value) {
        dt.value.exportCSV();
    }
};

const formatDate = (value) => {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
};

const consultationStatus = (consultation) =>
    consultation?.statut === 1 || consultation?.state === 1 ? 'Clôturée' : 'En cours';

const consultationSeverity = (consultation) =>
    consultationStatus(consultation) === 'Clôturée' ? 'success' : 'warning';

const consultationMontant = (consultation) =>
    Number(consultation?.factureMontant ?? consultation?.montant ?? 0);
</script>

<template>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm">
        <div class="px-5 md:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800" data-tour="patients-dossier.consultations-toolbar">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="space-y-1">
                    <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                        Consultations du patient
                    </h3>
                    <Tag :value="totalCountLabel" severity="info" class="px-3 py-1 rounded-full font-medium" />
                </div>
                <div class="flex items-center gap-2">
                    <Button icon="pi pi-download" label="Exporter" severity="secondary" text size="small" @click="exportCSV" />
                </div>
            </div>
        </div>

        <div class="px-5 md:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-surface-0/50 dark:bg-surface-800/30" data-tour="patients-dossier.consultations-filter">
            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">
                Rechercher une consultation
            </label>
            <span class="p-input-icon-left w-full">
                <i class="pi pi-search text-surface-400" />
                <InputText v-model="filterValue" placeholder="Date, statut, médecin..." class="w-full p-3.5 rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-700/50 focus:ring-2 focus:ring-primary-500/20 transition-all" />
            </span>
        </div>

        <div class="p-2" data-tour="patients-dossier.consultations-table">
            <DataTable
                ref="dt"
                :value="consultations"
                dataKey="id"
                :loading="loading"
                :filters="filters"
                :paginator="true"
                :rows="8"
                :rowsPerPageOptions="[5, 8, 15, 30]"
                class="rounded-none border-0"
            >
                <Column field="date" header="Date" sortable>
                    <template #body="{ data }">
                        <span class="text-surface-900 dark:text-surface-100">{{ formatDate(data.date) }}</span>
                    </template>
                </Column>
                <Column field="statut" header="Statut" sortable>
                    <template #body="{ data }">
                        <Tag :value="consultationStatus(data)" :severity="consultationSeverity(data)" class="px-3 py-1 rounded-full" />
                    </template>
                </Column>
                <Column field="medecin" header="Médecin" sortable>
                    <template #body="{ data }">
                        <span class="text-surface-700 dark:text-surface-300">{{ data.medecin || '—' }}</span>
                    </template>
                </Column>
                <Column field="factureMontant" header="Montant facture" sortable>
                    <template #body="{ data }">
                        <span class="font-semibold text-surface-900 dark:text-surface-100">{{ consultationMontant(data) }} F CFA</span>
                    </template>
                </Column>
                <template #empty>
                    <div class="text-center py-10 text-surface-500 dark:text-surface-400">Aucune consultation trouvée.</div>
                </template>
            </DataTable>
        </div>
    </div>
</template>

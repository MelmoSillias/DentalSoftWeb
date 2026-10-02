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

const consultationStatus = (consultation) => (consultation?.statut === 1 || consultation?.state === 1 ? 'Clôturée' : 'En cours');

const consultationSeverity = (consultation) => (consultationStatus(consultation) === 'Clôturée' ? 'success' : 'warning');

const consultationMontant = (consultation) => Number(consultation?.factureMontant ?? consultation?.montant ?? 0);
</script>

<template>
    <div class="page-section">
        <div class="page-section__header" data-tour="patients-dossier.consultations-toolbar">
            <div class="page-section__header-main">
                <h3 class="page-section__title">Consultations du patient</h3>
                <p class="page-section__subtitle">{{ totalCountLabel }}</p>
            </div>
            <div class="page-section__header-actions">
                <Button icon="pi pi-download" label="Exporter" severity="secondary" text size="small" @click="exportCSV" />
            </div>
        </div>

        <div class="px-3 py-3 border-b" style="border-color: color-mix(in srgb, var(--surface-border) 80%, transparent)" data-tour="patients-dossier.consultations-filter">
            <label class="block mb-1.5" style="font-size: var(--page-section-subtitle-size); font-weight: 500; color: var(--text-color)">Rechercher une consultation</label>
            <span class="p-input-icon-left w-full">
                <i class="pi pi-search" style="color: var(--text-color-secondary)" />
                <InputText
                    v-model="filterValue"
                    placeholder="Date, statut, médecin..."
                    class="w-full"
                />
            </span>
        </div>

        <div class="p-2" data-tour="patients-dossier.consultations-table">
            <DataTable ref="dt" :value="consultations" dataKey="id" :loading="loading" :filters="filters" :paginator="true" :rows="8" :rowsPerPageOptions="[5, 8, 15, 30]" class="rounded-none border-0">
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

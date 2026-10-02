<script setup>
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { computed, ref, watch } from 'vue';
import CabinetServiceDialog from '@/components/patients/CabinetServiceDialog.vue';
import { cancelCabinetService } from '@/services/cabinetServices';
import { useAuthStore } from '@/stores/auth';
import { useToast } from 'primevue/usetoast';
import { logAppError } from '@/utils/appLogger';

const props = defineProps({
    patientId: { type: [Number, String], default: null },
    patientName: { type: String, default: '' },
    services: { type: Array, default: () => [] },
    ficheId: { type: [Number, String], default: null },
    /** Compact mode for nested dossier tabs */
    compact: { type: Boolean, default: false }
});

const emit = defineEmits(['refresh']);

const auth = useAuthStore();
const toast = useToast();
const dialogVisible = ref(false);
const cancellingId = ref(null);
const searchQuery = ref('');
const statusFilter = ref(null);

const statusFilterOptions = [
    { label: 'Tous les statuts', value: null },
    { label: 'Payé', value: 'paye' },
    { label: 'Impayé', value: 'impaye' },
    { label: 'Partiel', value: 'partiel' },
    { label: 'Annulé', value: 'annule' }
];

const rows = computed(() => {
    const list = Array.isArray(props.services) ? props.services : [];
    if (props.ficheId == null || props.ficheId === '') {
        return list;
    }
    return list.filter((item) => Number(item?.ficheId) === Number(props.ficheId));
});

const statusKey = (service) => {
    if (service?.statut === 'annule') return 'annule';
    if (service?.facture?.isRegle) return 'paye';
    const reste = Number(service?.facture?.reste) || 0;
    const montant = Number(service?.facture?.montant ?? service?.montant) || 0;
    if (reste > 0 && reste < montant) return 'partiel';
    return 'impaye';
};

const statusLabel = (service) => {
    const key = statusKey(service);
    if (key === 'annule') return { label: 'Annulé', severity: 'secondary' };
    if (key === 'paye') return { label: 'Payé', severity: 'success' };
    if (key === 'partiel') return { label: 'Partiellement payé', severity: 'warning' };
    return { label: 'Impayé', severity: 'danger' };
};

const formatMoney = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const filteredRows = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    return rows.value.filter((service) => {
        if (statusFilter.value && statusKey(service) !== statusFilter.value) return false;
        if (!query) return true;
        const haystack = [service.designation, service.date, String(service.montant ?? '')].filter(Boolean).join(' ').toLowerCase();
        return haystack.includes(query);
    });
});

const kpi = computed(() => {
    const list = filteredRows.value;
    const active = list.filter((s) => statusKey(s) !== 'annule');
    const paid = active.filter((s) => statusKey(s) === 'paye').length;
    const unpaid = active.filter((s) => statusKey(s) === 'impaye' || statusKey(s) === 'partiel').length;
    const total = active.reduce((sum, s) => sum + (Number(s.montant) || 0), 0);
    const reste = active.reduce((sum, s) => sum + (Number(s.facture?.reste ?? (statusKey(s) === 'paye' ? 0 : s.montant)) || 0), 0);
    return { count: list.length, paid, unpaid, total, reste };
});

watch(
    () => props.services,
    () => {
        searchQuery.value = '';
        statusFilter.value = null;
    }
);

const cancelService = async (service) => {
    if (!service?.id) {
        return;
    }
    try {
        cancellingId.value = service.id;
        await cancelCabinetService(service.id, auth.token);
        toast.add({ severity: 'success', summary: 'Service cabinet', detail: 'Service annulé', life: 2500 });
        emit('refresh');
    } catch (error) {
        logAppError('Services cabinet', error);
        toast.add({
            severity: 'error',
            summary: 'Service cabinet',
            detail: error?.response?.data?.error || 'Annulation impossible',
            life: 3500
        });
    } finally {
        cancellingId.value = null;
    }
};
</script>

<template>
    <section :class="compact ? 'space-y-3' : 'page-section page-section--padded space-y-3'">
        <div v-if="!compact" class="mb-1 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="page-section__title">Services cabinet</h3>
                <p class="page-section__subtitle">Radiographie et autres prestations facturées hors consultation.</p>
            </div>
            <Button label="Enregistrer un service" icon="pi pi-plus" size="small" outlined @click="dialogVisible = true" />
        </div>

        <div :class="['page-kpi-grid', compact && 'page-kpi-grid--compact']">
            <div class="page-kpi-card border-blue-200/50 bg-gradient-to-br from-blue-50 to-blue-100/50 dark:border-blue-800/50 dark:from-blue-900/20 dark:to-blue-800/20">
                <div class="min-w-0">
                    <p class="page-kpi-label text-blue-700 dark:text-blue-300">Services</p>
                    <p class="page-kpi-value text-blue-900 dark:text-blue-100">{{ kpi.count }}</p>
                </div>
                <i class="pi pi-building page-kpi-icon text-blue-500"></i>
            </div>
            <div class="page-kpi-card border-emerald-200/50 bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:border-emerald-800/50 dark:from-emerald-900/20 dark:to-emerald-800/20">
                <div class="min-w-0">
                    <p class="page-kpi-label text-emerald-700 dark:text-emerald-300">Payés</p>
                    <p class="page-kpi-value text-emerald-900 dark:text-emerald-100">{{ kpi.paid }}</p>
                </div>
                <i class="pi pi-check-circle page-kpi-icon text-emerald-500"></i>
            </div>
            <div class="page-kpi-card border-amber-200/50 bg-gradient-to-br from-amber-50 to-amber-100/50 dark:border-amber-800/50 dark:from-amber-900/20 dark:to-amber-800/20">
                <div class="min-w-0">
                    <p class="page-kpi-label text-amber-700 dark:text-amber-300">À encaisser</p>
                    <p class="page-kpi-value text-amber-900 dark:text-amber-100">{{ kpi.unpaid }}</p>
                </div>
                <i class="pi pi-wallet page-kpi-icon text-amber-500"></i>
            </div>
            <div class="page-kpi-card border-violet-200/50 bg-gradient-to-br from-violet-50 to-violet-100/50 dark:border-violet-800/50 dark:from-violet-900/20 dark:to-violet-800/20">
                <div class="min-w-0">
                    <p class="page-kpi-label text-violet-700 dark:text-violet-300">Reste</p>
                    <p class="page-kpi-value truncate text-violet-900 dark:text-violet-100">{{ formatMoney(kpi.reste) }}</p>
                </div>
                <i class="pi pi-money-bill page-kpi-icon text-violet-500"></i>
            </div>
        </div>

        <div class="flex flex-wrap items-end justify-between gap-2">
            <div class="page-filters !justify-start !w-auto flex-1">
                <div class="page-filter-item max-w-xs">
                    <label>Recherche</label>
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="searchQuery" placeholder="Désignation…" class="w-full" />
                    </IconField>
                </div>
                <div class="page-filter-item">
                    <label>Statut</label>
                    <Select v-model="statusFilter" :options="statusFilterOptions" optionLabel="label" optionValue="value" class="w-full" />
                </div>
            </div>
            <Button v-if="compact" label="Ajouter" icon="pi pi-plus" size="small" outlined @click="dialogVisible = true" />
        </div>

        <div v-if="filteredRows.length" class="page-table-scroll">
            <DataTable :value="filteredRows" dataKey="id" paginator :rows="8" :rowsPerPageOptions="[5, 8, 15]" responsiveLayout="scroll" stripedRows size="small" class="text-sm">
                <Column field="date" header="Date" sortable style="min-width: 7rem">
                    <template #body="{ data }">
                        {{ data.date || '—' }}
                    </template>
                </Column>
                <Column field="designation" header="Prestation" sortable style="min-width: 10rem">
                    <template #body="{ data }">
                        <span class="font-medium">{{ data.designation || '—' }}</span>
                    </template>
                </Column>
                <Column field="montant" header="Montant" sortable style="min-width: 8rem">
                    <template #body="{ data }">
                        {{ formatMoney(data.montant) }}
                    </template>
                </Column>
                <Column header="Reste" style="min-width: 7rem">
                    <template #body="{ data }">
                        {{ formatMoney(data.facture?.reste ?? (statusKey(data) === 'paye' || statusKey(data) === 'annule' ? 0 : data.montant)) }}
                    </template>
                </Column>
                <Column header="Statut" style="min-width: 8rem">
                    <template #body="{ data }">
                        <Tag :value="statusLabel(data).label" :severity="statusLabel(data).severity" />
                    </template>
                </Column>
                <Column header="" style="width: 3.5rem">
                    <template #body="{ data }">
                        <Button
                            v-if="data.statut !== 'annule' && !data.facture?.hasPayments"
                            icon="pi pi-times"
                            text
                            rounded
                            severity="danger"
                            size="small"
                            v-tooltip.top="'Annuler'"
                            :loading="cancellingId === data.id"
                            @click="cancelService(data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </div>
        <div v-else class="dossier-state dossier-state--dashed py-8" style="box-shadow: none">
            <div class="dossier-state__icon">
                <i class="pi pi-building"></i>
            </div>
            <h4 class="dossier-state__title">{{ rows.length ? 'Aucun résultat' : 'Aucun service cabinet' }}</h4>
            <p class="dossier-state__text">
                {{ rows.length ? 'Aucun service ne correspond aux filtres.' : 'Aucune prestation cabinet enregistrée pour ce patient.' }}
            </p>
        </div>

        <CabinetServiceDialog v-model:visible="dialogVisible" :patient-id="patientId" :patient-name="patientName" @created="emit('refresh')" />
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import Button from 'primevue/button';
import Column from 'primevue/column';
import ConfirmPopup from 'primevue/confirmpopup';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';

import PanelDatePicker from '@/components/common/PanelDatePicker.vue';
import PageShell from '@/components/layout/PageShell.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import PageSection from '@/components/layout/PageSection.vue';
import AppDialog from '@/components/layout/AppDialog.vue';
import CabinetServiceDialog from '@/components/patients/CabinetServiceDialog.vue';
import CabinetServicePayDialog from '@/components/consultations/CabinetServicePayDialog.vue';
import PatientAvatar from '@/components/patients/PatientAvatar.vue';
import PrintDataTablePage from '@/components/print/PrintDataTablePage.vue';
import { usePrinter } from '@/composables/usePrinter';
import { cancelCabinetService, fetchCabinetService, fetchCabinetServices } from '@/services/cabinetServices';
import { defaultServicesCabinetList, normalizeServicesCabinetList } from '@/services/consultations';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { useAuthStore } from '@/stores/auth';
import { logAppError } from '@/utils/appLogger';
import { buildDefaultDatePeriods } from '@/utils/dateUtils';

const auth = useAuthStore();
const toast = useToast();
const confirm = useConfirm();
const { printComponent } = usePrinter();
const token = computed(() => auth.token || localStorage.getItem('token'));

const services = ref([]);
const loading = ref(false);
const loadErrorMessage = ref('');
const selectedPeriod = ref(buildDefaultDatePeriods().today);
const selectedDesignations = ref([]);
const serviceCatalog = ref(defaultServicesCabinetList.map((item) => ({ ...item })));
const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});

const createVisible = ref(false);
const editVisible = ref(false);
const payVisible = ref(false);
const detailsVisible = ref(false);
const detailsLoading = ref(false);
const selectedService = ref(null);
const detailService = ref(null);
const deletingId = ref(null);

const breadcrumbHome = { icon: 'pi pi-home', to: '/dashboard' };
const breadcrumbItems = [{ label: 'Consultations' }, { label: 'Services cabinets' }];

const formatDateToApi = (date) => {
    const d = new Date(date);
    if (Number.isNaN(d.getTime())) return '';
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
};

const formatDisplayDate = (date) => {
    const d = new Date(date);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const periodLabel = computed(() => {
    const range = selectedPeriod.value;
    if (!Array.isArray(range) || !range[0]) return null;
    const start = formatDisplayDate(range[0]);
    const end = range[1] ? formatDisplayDate(range[1]) : start;
    if (!start) return null;
    return start === end ? start : `${start} — ${end}`;
});

const headerSubtitle = computed(() => {
    if (!periodLabel.value) return 'Tous les services cabinets';
    return `Services du ${periodLabel.value}`;
});

const filteredServices = computed(() => {
    const selected = selectedDesignations.value || [];
    if (!selected.length) return services.value;
    const set = new Set(selected);
    return services.value.filter((s) => set.has(s.designation));
});

const totalCountLabel = computed(() => (filteredServices.value?.length ? `${filteredServices.value.length} service(s)` : ''));

const filterGlobalValue = computed({
    get: () => filters.value.global?.value ?? '',
    set: (val) => {
        filters.value = { ...filters.value, global: { ...filters.value.global, value: val } };
    }
});

const hasActiveFilters = computed(() => Boolean(String(filterGlobalValue.value || '').trim()) || (selectedDesignations.value?.length ?? 0) > 0);

const unpaidCount = computed(() => filteredServices.value.filter((s) => canPay(s)).length);
const paidCount = computed(() => filteredServices.value.filter((s) => isFullyPaid(s)).length);
const totalAmount = computed(() => filteredServices.value.reduce((sum, s) => sum + (Number(s.montant) || 0), 0));

const formatDateTime = (value) => {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
};

const formatMoney = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const patientLabel = (row) => {
    if (!row) return '';
    if (row.patientName) return row.patientName;
    const patient = row.patient || {};
    return patient.fullname || `${patient.prenom ?? ''} ${patient.nom ?? ''}`.trim() || '—';
};

const isFullyPaid = (service) => {
    if (service?.statut === 'annule') return false;
    if (service?.facture?.isRegle) return true;
    return Number(service?.facture?.reste ?? service?.montant) <= 0;
};

const canPay = (service) => {
    if (!service || service.statut === 'annule') return false;
    if (!service.facture?.id) return false;
    return Number(service.facture?.reste ?? service.montant) > 0;
};

const canDelete = (service) => {
    if (!service || service.statut === 'annule') return false;
    return !service.facture?.hasPayments;
};

const statusMeta = (service) => {
    if (service?.statut === 'annule') {
        return { label: 'Annulé', severity: 'secondary' };
    }
    if (isFullyPaid(service)) {
        return { label: 'Payé', severity: 'success' };
    }
    const reste = Number(service?.facture?.reste) || 0;
    const montant = Number(service?.facture?.montant ?? service?.montant) || 0;
    if (reste > 0 && reste < montant) {
        return { label: 'Partiel', severity: 'warning' };
    }
    return { label: 'Impayé', severity: 'danger' };
};

const loadCatalog = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token.value);
        serviceCatalog.value = normalizeServicesCabinetList(settings?.servicesCabinetList);
    } catch (error) {
        logAppError('Services cabinets catalogue', error);
        serviceCatalog.value = defaultServicesCabinetList.map((item) => ({ ...item }));
    }
};

const loadServices = async ({ asPageLoad = false } = {}) => {
    loading.value = true;
    try {
        const params = {};
        const range = selectedPeriod.value;
        if (Array.isArray(range) && range[0]) {
            params.start = formatDateToApi(range[0]);
            params.end = formatDateToApi(range[1] || range[0]);
        }
        services.value = await fetchCabinetServices(params, token.value);
        if (asPageLoad) loadErrorMessage.value = '';
        return true;
    } catch (error) {
        logAppError('Services cabinets', error);
        if (asPageLoad) {
            loadErrorMessage.value = 'Impossible de charger les services cabinets.';
        }
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les services cabinets.', life: 3000 });
        return false;
    } finally {
        loading.value = false;
    }
};

const retryLoadPage = () => loadServices({ asPageLoad: true });

const openCreate = () => {
    selectedService.value = null;
    createVisible.value = true;
};

const openEdit = (service) => {
    selectedService.value = service;
    editVisible.value = true;
};

const openPay = (service) => {
    selectedService.value = service;
    payVisible.value = true;
};

const openDetails = async (service) => {
    selectedService.value = service;
    detailsVisible.value = true;
    detailsLoading.value = true;
    detailService.value = service;
    try {
        detailService.value = (await fetchCabinetService(service.id, token.value)) || service;
    } catch (error) {
        logAppError('Services cabinets détails', error);
        toast.add({ severity: 'warn', summary: 'Détails', detail: 'Détails partiels affichés.', life: 2500 });
    } finally {
        detailsLoading.value = false;
    }
};

const handleDelete = (event, service) => {
    if (!canDelete(service)) return;
    confirm.require({
        target: event?.currentTarget,
        message: `Supprimer le service « ${service.designation} » pour ${patientLabel(service)} ?`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Oui, supprimer',
        rejectLabel: 'Annuler',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                deletingId.value = service.id;
                await cancelCabinetService(service.id, token.value);
                toast.add({ severity: 'success', summary: 'Service cabinet', detail: 'Service supprimé', life: 2500 });
                await loadServices();
            } catch (error) {
                logAppError('Services cabinets suppression', error);
                toast.add({
                    severity: 'error',
                    summary: 'Suppression',
                    detail: error?.response?.data?.error || 'Suppression impossible',
                    life: 3500
                });
            } finally {
                deletingId.value = null;
            }
        }
    });
};

const onServiceChanged = async () => {
    await loadServices();
};

const resetFilters = () => {
    filterGlobalValue.value = '';
    selectedDesignations.value = [];
    selectedPeriod.value = buildDefaultDatePeriods().today;
    loadServices();
};

const printServices = async () => {
    const rows = (filteredServices.value || []).map((s) => ({
        patient: patientLabel(s),
        designation: s.designation || '—',
        date: formatDateTime(s.date),
        montant: formatMoney(s.montant),
        statut: statusMeta(s).label
    }));

    await printComponent(PrintDataTablePage, {
        title: 'Services cabinets',
        subtitle: headerSubtitle.value,
        columns: [
            { key: 'patient', label: 'Patient' },
            { key: 'designation', label: 'Service' },
            { key: 'date', label: 'Date' },
            { key: 'montant', label: 'Montant' },
            { key: 'statut', label: 'Statut' }
        ],
        rows
    });
};

onMounted(async () => {
    await Promise.all([loadCatalog(), loadServices({ asPageLoad: true })]);
});
</script>

<template>
    <PageShell>
        <ConfirmPopup />

        <template #header>
            <PageHeader
                title="Services cabinets"
                :subtitle="headerSubtitle"
                icon="pi pi-building"
                :breadcrumb-items="breadcrumbItems"
                :breadcrumb-home="breadcrumbHome"
            >
                <template #actions>
                    <Button label="Nouveau service" icon="pi pi-plus" @click="openCreate" />
                </template>
            </PageHeader>
        </template>

        <div v-if="loadErrorMessage" class="mb-6 flex min-h-[320px] flex-col items-center justify-center gap-4 rounded-2xl border border-amber-200/70 bg-amber-50/70 p-8 dark:border-amber-800/70 dark:bg-amber-950/20">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                <i class="pi pi-exclamation-triangle text-2xl"></i>
            </div>
            <div class="text-center">
                <p class="text-lg font-semibold text-amber-800 dark:text-amber-200">Chargement interrompu</p>
                <p class="text-sm text-amber-700/90 dark:text-amber-300/90">{{ loadErrorMessage }}</p>
            </div>
            <Button icon="pi pi-refresh" label="Réessayer" severity="warning" @click="retryLoadPage" />
        </div>

        <template #toolbar v-if="!loadErrorMessage">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                <div class="w-full lg:flex-1">
                    <label class="mb-2 block text-sm font-medium text-surface-700 dark:text-surface-300">Rechercher</label>
                    <IconField class="p-input-icon-left w-full">
                        <InputIcon class="pi pi-search text-surface-400" />
                        <InputText
                            v-model="filterGlobalValue"
                            placeholder="Patient, service, statut..."
                            class="w-full rounded-xl border-surface-200 bg-surface-0 p-3.5 transition-all focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-700/50"
                        />
                    </IconField>
                </div>
                <div class="w-full lg:w-72">
                    <label class="mb-2 block text-sm font-medium text-surface-700 dark:text-surface-300">Service</label>
                    <MultiSelect
                        v-model="selectedDesignations"
                        :options="serviceCatalog"
                        optionLabel="description"
                        optionValue="description"
                        placeholder="Tous les services"
                        display="chip"
                        filter
                        showClear
                        class="w-full"
                        :maxSelectedLabels="2"
                    />
                </div>
                <div class="w-full lg:w-72">
                    <label class="mb-2 block text-sm font-medium text-surface-700 dark:text-surface-300">Période</label>
                    <PanelDatePicker
                        v-model="selectedPeriod"
                        dateFormat="dd/mm/yy"
                        showIcon
                        fluid
                        placeholder="Choisir période"
                        inputClass="rounded-xl p-3.5 border-surface-200 dark:border-surface-700"
                        @update:modelValue="loadServices()"
                    />
                </div>
                <div class="flex gap-2">
                    <Button icon="pi pi-filter-slash" label="Réinitialiser" severity="secondary" outlined @click="resetFilters" />
                    <Button icon="pi pi-refresh" severity="secondary" outlined :loading="loading" @click="loadServices()" />
                </div>
            </div>
        </template>

        <template v-if="!loadErrorMessage">
            <div class="page-kpi-grid mb-6">
                <div class="page-kpi-card border-blue-200/50 bg-gradient-to-br from-blue-50 to-blue-100/50 dark:border-blue-800/50 dark:from-blue-900/20 dark:to-blue-800/20">
                    <div>
                        <p class="page-kpi-label text-blue-700 dark:text-blue-300">Total</p>
                        <p class="page-kpi-value text-blue-900 dark:text-blue-100">{{ filteredServices.length }}</p>
                    </div>
                    <i class="pi pi-briefcase page-kpi-icon text-blue-500"></i>
                </div>
                <div class="page-kpi-card border-emerald-200/50 bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:border-emerald-800/50 dark:from-emerald-900/20 dark:to-emerald-800/20">
                    <div>
                        <p class="page-kpi-label text-emerald-700 dark:text-emerald-300">Payés</p>
                        <p class="page-kpi-value text-emerald-900 dark:text-emerald-100">{{ paidCount }}</p>
                    </div>
                    <i class="pi pi-check-circle page-kpi-icon text-emerald-500"></i>
                </div>
                <div class="page-kpi-card border-amber-200/50 bg-gradient-to-br from-amber-50 to-amber-100/50 dark:border-amber-800/50 dark:from-amber-900/20 dark:to-amber-800/20">
                    <div>
                        <p class="page-kpi-label text-amber-700 dark:text-amber-300">À encaisser</p>
                        <p class="page-kpi-value text-amber-900 dark:text-amber-100">{{ unpaidCount }}</p>
                    </div>
                    <i class="pi pi-wallet page-kpi-icon text-amber-500"></i>
                </div>
                <div class="page-kpi-card border-violet-200/50 bg-gradient-to-br from-violet-50 to-violet-100/50 dark:border-violet-800/50 dark:from-violet-900/20 dark:to-violet-800/20">
                    <div>
                        <p class="page-kpi-label text-violet-700 dark:text-violet-300">Montant</p>
                        <p class="page-kpi-value text-lg text-violet-900 dark:text-violet-100">{{ formatMoney(totalAmount) }}</p>
                    </div>
                    <i class="pi pi-money-bill page-kpi-icon text-violet-500"></i>
                </div>
            </div>

            <PageSection title="Liste des services" :subtitle="totalCountLabel">
                <template #headerActions>
                    <Button icon="pi pi-download" severity="secondary" text size="small" label="Exporter" @click="printServices" />
                </template>

                <div class="page-table-scroll">
                    <DataTable
                        v-model:filters="filters"
                        :value="filteredServices"
                        :loading="loading"
                        dataKey="id"
                        stripedRows
                        paginator
                        :rows="10"
                        :rowsPerPageOptions="[10, 25, 50]"
                        filterDisplay="menu"
                        :globalFilterFields="['patientName', 'designation', 'note', 'patient.nom', 'patient.prenom']"
                        class="text-sm"
                    >
                        <Column header="Patient" style="min-width: 14rem">
                            <template #body="{ data }">
                                <div class="flex items-center gap-3">
                                    <PatientAvatar :patient="data.patient ?? data" :initials="patientLabel(data)" size-class="w-10 h-10" text-class="font-semibold" />
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-surface-900 dark:text-surface-0 mb-1">{{ patientLabel(data) }}</p>
                                        <p class="truncate text-xs text-surface-500">{{ data.patient?.telephone || '—' }}</p>
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column field="designation" header="Service" style="min-width: 12rem">
                            <template #body="{ data }">
                                <div class="min-w-0">
                                    <p class="font-medium mb-1">{{ data.designation }}</p>
                                    <p v-if="data.note" class="truncate text-xs text-surface-500">{{ data.note }}</p>
                                </div>
                            </template>
                        </Column>

                        <Column header="Date" style="min-width: 9rem">
                            <template #body="{ data }">
                                {{ formatDateTime(data.date) }}
                            </template>
                        </Column>

                        <Column header="Montant" style="min-width: 8rem">
                            <template #body="{ data }">
                                <div>
                                    <p class="font-semibold">{{ formatMoney(data.montant) }}</p>
                                    <p v-if="canPay(data)" class="text-xs text-amber-600 dark:text-amber-400">Reste {{ formatMoney(data.facture?.reste) }}</p>
                                </div>
                            </template>
                        </Column>

                        <Column header="Statut" style="min-width: 8rem">
                            <template #body="{ data }">
                                <Tag :value="statusMeta(data).label" :severity="statusMeta(data).severity" />
                            </template>
                        </Column>

                        <Column header="Actions" style="min-width: 12rem">
                            <template #body="{ data }">
                                <div class="flex flex-wrap items-center gap-1">
                                    <Button icon="pi pi-eye" text rounded severity="secondary" v-tooltip.top="'Détails'" @click="openDetails(data)" />
                                    <Button
                                        v-if="canPay(data)"
                                        icon="pi pi-wallet"
                                        text
                                        rounded
                                        severity="success"
                                        v-tooltip.top="'Payer'"
                                        @click="openPay(data)"
                                    />
                                    <Button icon="pi pi-pencil" text rounded severity="info" v-tooltip.top="'Modifier'" @click="openEdit(data)" />
                                    <Button
                                        v-if="canDelete(data)"
                                        icon="pi pi-trash"
                                        text
                                        rounded
                                        severity="danger"
                                        v-tooltip.top="'Supprimer'"
                                        :loading="deletingId === data.id"
                                        @click="handleDelete($event, data)"
                                    />
                                </div>
                            </template>
                        </Column>

                        <template #empty>
                            <div class="text-center py-16">
                                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-surface-100 dark:bg-surface-800 mb-6">
                                    <i class="pi pi-building text-4xl text-surface-400"></i>
                                </div>
                                <h4 class="text-xl font-semibold text-surface-700 dark:text-surface-300 mb-3">Aucun service trouvé</h4>
                                <p class="text-surface-600 dark:text-surface-400 mb-8 max-w-md mx-auto">
                                    {{ hasActiveFilters ? 'Aucun résultat ne correspond à vos filtres.' : "Aucun service cabinet n'a été enregistré pour cette période." }}
                                </p>
                                <div class="flex flex-wrap gap-3 justify-center">
                                    <Button icon="pi pi-plus" label="Ajouter un service" class="bg-gradient-to-r from-primary-500 to-primary-600 border-0" @click="openCreate" />
                                    <Button v-if="hasActiveFilters" icon="pi pi-filter-slash" label="Réinitialiser les filtres" severity="secondary" outlined @click="resetFilters" />
                                </div>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </PageSection>
        </template>

        <template #dialogs>
            <CabinetServiceDialog v-model:visible="createVisible" allow-patient-select @created="onServiceChanged" />
            <CabinetServiceDialog v-model:visible="editVisible" :service="selectedService" @updated="onServiceChanged" />
            <CabinetServicePayDialog v-model:visible="payVisible" :service="selectedService" @paid="onServiceChanged" />

            <AppDialog
                v-model:visible="detailsVisible"
                title="Détails du service cabinet"
                :subtitle="patientLabel(detailService)"
                icon="pi pi-info-circle"
                icon-tone="info"
                size="md"
                :loading="detailsLoading"
                cancel-label="Fermer"
                :show-footer="true"
                @cancel="detailsVisible = false"
            >
                <div v-if="detailService" class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-surface-200 p-3 dark:border-surface-700">
                            <p class="text-xs text-surface-500">Service</p>
                            <p class="font-medium">{{ detailService.designation }}</p>
                        </div>
                        <div class="rounded-xl border border-surface-200 p-3 dark:border-surface-700">
                            <p class="text-xs text-surface-500">Statut</p>
                            <Tag :value="statusMeta(detailService).label" :severity="statusMeta(detailService).severity" />
                        </div>
                        <div class="rounded-xl border border-surface-200 p-3 dark:border-surface-700">
                            <p class="text-xs text-surface-500">Date</p>
                            <p class="font-medium">{{ formatDateTime(detailService.date) }}</p>
                        </div>
                        <div class="rounded-xl border border-surface-200 p-3 dark:border-surface-700">
                            <p class="text-xs text-surface-500">Quantité × Prix</p>
                            <p class="font-medium">{{ detailService.quantite }} × {{ formatMoney(detailService.prix) }}</p>
                        </div>
                        <div class="rounded-xl border border-surface-200 p-3 dark:border-surface-700">
                            <p class="text-xs text-surface-500">Montant</p>
                            <p class="font-semibold">{{ formatMoney(detailService.montant) }}</p>
                        </div>
                        <div class="rounded-xl border border-surface-200 p-3 dark:border-surface-700">
                            <p class="text-xs text-surface-500">Reste à payer</p>
                            <p class="font-semibold">{{ formatMoney(detailService.facture?.reste) }}</p>
                        </div>
                    </div>

                    <div v-if="detailService.note" class="rounded-xl border border-surface-200 p-3 dark:border-surface-700">
                        <p class="text-xs text-surface-500">Note</p>
                        <p class="text-sm">{{ detailService.note }}</p>
                    </div>

                    <div v-if="detailService.facture?.paiements?.length" class="rounded-xl border border-surface-200 p-3 dark:border-surface-700">
                        <p class="mb-2 text-sm font-semibold">Paiements</p>
                        <ul class="flex flex-col gap-2">
                            <li
                                v-for="paiement in detailService.facture.paiements"
                                :key="paiement.id"
                                class="flex items-center justify-between gap-3 rounded-lg bg-surface-50 px-3 py-2 dark:bg-surface-800/40"
                            >
                                <div>
                                    <p class="text-sm font-medium">{{ formatMoney(paiement.montant) }}</p>
                                    <p class="text-xs text-surface-500">{{ formatDateTime(paiement.date) }} · {{ paiement.mode || '—' }}</p>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <Button v-if="canPay(detailService)" label="Payer" icon="pi pi-wallet" severity="success" @click="detailsVisible = false; openPay(detailService)" />
                        <Button label="Modifier" icon="pi pi-pencil" severity="info" outlined @click="detailsVisible = false; openEdit(detailService)" />
                    </div>
                </div>
            </AppDialog>
        </template>
    </PageShell>
</template>

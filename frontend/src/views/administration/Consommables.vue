<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { activateAdminTourMock, deactivateAdminTourMock, resetAdminTourMockData } from '@/services/adminTourMock'
import { useConsumables } from '@/composables/useConsumables'
import { useStockVariations } from '@/composables/useStockVariations'
import ConsumableForm from '@/components/consumables/ConsumableForm.vue'
import AddRetireStockForm from '@/components/consumables/AddRetireStockForm.vue'
import { useEmployees } from '@/composables/useEmployees'
import { useGuidedTour } from '@/composables/useGuidedTour'
import PrintDataTablePage from '@/components/print/PrintDataTablePage.vue'
import { usePrinter } from '@/composables/usePrinter'
import PanelDatePicker from '@/components/common/PanelDatePicker.vue'
import { useToast } from 'primevue/usetoast'

const showForm = ref(false) 
const rowsPerPage = ref(10);
const showAddRetireForm = ref(false)
const addRetireFormType = ref("add")
const showDetails = ref(false)
const detailConsumable = ref(null)
const toast = useToast()
let guidedTourPageState = null
let guidedTourDemoActive = false
let guidedTourCleanupPromise = null

const editConsumable = ref(null)

const openEditForm = (consumable) => {
    editConsumable.value = consumable
    showForm.value = true
}

const toggleAddRetireForm = (value, consumable) => {
    addRetireFormType.value = value
    editConsumable.value = consumable
    showAddRetireForm.value = true 
}

const stockVariationsStore = useStockVariations();
const breadcrumbHome = { icon: 'pi pi-home', to: '/dashboard' };
const breadcrumbItems = [
    { label: 'Administration' },
    { label: 'Consommables', class: 'font-semibold' }
];
const consumablesStore = useConsumables();
const consumables = consumablesStore.consumables;
const { printComponent } = usePrinter();

const optionsMenu = [
    { label: 'Liste', value: 'list', icon: 'pi pi-list' },
    { label: 'Variations', value: 'vars', icon: 'pi pi-chart-bar' }
];

const currentYear = new Date().getFullYear();
const today = new Date();
const startOfYear = new Date(new Date().getFullYear(), 0, 1);

const menuValue = ref('list');
const hasOpenDialogs = computed(() => showForm.value || showAddRetireForm.value || showDetails.value)
const filters = ref({
    consumableId: null,
    period: [ startOfYear, today],
})

function getStatut(item) {
    const quantity = getQuantity(item);
    const lowValue = getLowValue(item);

    if (quantity > 0) {
        return quantity <= lowValue ? 'STOCK BAS' : 'EN STOCK';
    }
    return 'RUPTURE DE STOCK';
}

function getSeverity(item) {
    const quantity = getQuantity(item);
    const lowValue = getLowValue(item);

    if (quantity > 0) {
        return quantity <= lowValue ? 'warning' : 'success';
    }
    return 'danger';
}

function getQuantity(item) {
    const value = Number(item?.quantity ?? item?.quantite ?? 0);
    return Number.isFinite(value) ? value : 0;
}

function getLowValue(item) {
    const value = Number(item?.lowValue ?? item?.seuil ?? 0);
    return Number.isFinite(value) ? value : 0;
}

function getStockProgress(item) {
    const quantity = getQuantity(item);
    const lowValue = getLowValue(item);
    const denom = lowValue > 0 ? lowValue * 2 : 0;

    if (!denom) return quantity > 0 ? 100 : 0;
    return Math.min(100, Math.round((quantity / denom) * 100));
}

const getConsumablesList = () => {
    if (Array.isArray(consumables.value)) return consumables.value;
    if (Array.isArray(consumables)) return consumables;
    return [];
};

const printConsumables = async () => {
    const rows = getConsumablesList().map((item) => ({
        nom: item?.nom || '—',
        fournisseur: item?.fournisseur || '—',
        quantite: item?.quantity ?? item?.quantite ?? '—',
        seuil: item?.lowValue ?? item?.seuil ?? '—'
    }));

    await printComponent(PrintDataTablePage, {
        title: 'Liste des consommables',
        subtitle: `${rows.length} consommable(s)`,
        columns: [
            { key: 'nom', label: 'Nom' },
            { key: 'fournisseur', label: 'Fournisseur' },
            { key: 'quantite', label: 'Quantité', align: 'right' },
            { key: 'seuil', label: 'Seuil', align: 'right' }
        ],
        rows
    });
};

const printVariations = async () => {
    const rows = (stockVariationsStore.variations.value || []).map((item) => ({
        date: item?.date || '—',
        consommable: item?.consommable || '—',
        employe: item?.employe || '—',
        quantite: item?.quantiteUtilisee ?? item?.quantite ?? '—',
        type: item?.type || item?.mouvement || '—'
    }));

    await printComponent(PrintDataTablePage, {
        title: 'Mouvements des stocks',
        subtitle: `${rows.length} mouvement(s)`,
        columns: [
            { key: 'date', label: 'Date' },
            { key: 'consommable', label: 'Consommable' },
            { key: 'employe', label: 'Employé' },
            { key: 'quantite', label: 'Quantité', align: 'right' },
            { key: 'type', label: 'Type' }
        ],
        rows
    });
};

watch(filters, async (newF, oldF) => {
    stockVariationsStore.fetchStockVariations(newF.consumableId, newF.period[0], newF.period[1]);
}, { deep: true })

onMounted(() => {
    consumablesStore.fetchConsumables(); 
    stockVariationsStore.fetchStockVariations(filters.value.consumableId, filters.value.period[0], filters.value.period[1]);
});

onBeforeUnmount(() => {
    deactivateAdminTourMock();
    guidedTourDemoActive = false;
    resetTourDialogs();
});

const openDetails = async (consumable) => {
    const data = await consumablesStore.getConsumable(consumable?.id);
    detailConsumable.value = data ?? consumable ?? null;
    showDetails.value = true;
};

const confirmDelete = async (consumable) => {
    if (!consumable?.id) return;

    const ok = window.confirm(`Supprimer le consommable "${consumable.nom}" ?`);
    if (!ok) return;

    const result = await consumablesStore.deleteConsumable(consumable.id, consumable.deleteToken);
    if (result?.ok) {
        toast.add({ severity: 'success', summary: 'Supprimé', detail: 'Le consommable a été supprimé.', life: 3000 });
    } else {
        toast.add({ severity: 'error', summary: 'Erreur', detail: consumablesStore.error.value || 'Suppression impossible.', life: 4000 });
    }
};

const setTourMode = (value) => {
    menuValue.value = value;
};

const cloneValue = (value) => {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
};

const waitForTourUi = (ms = 180) => new Promise((resolve) => {
    window.setTimeout(resolve, ms);
});

const firstConsumable = computed(() => getConsumablesList()[0] || null);
const lowStockConsumable = computed(() => getConsumablesList().find((item) => getSeverity(item) === 'warning') || null);
const outOfStockConsumable = computed(() => getConsumablesList().find((item) => getSeverity(item) === 'danger') || null);

const resetTourDialogs = () => {
    showForm.value = false;
    showAddRetireForm.value = false;
    showDetails.value = false;
    editConsumable.value = null;
    detailConsumable.value = null;
};

const capturePageState = () => ({
    menuValue: menuValue.value,
    filters: cloneValue(filters.value),
    consumables: cloneValue(consumables.value),
    variations: cloneValue(stockVariationsStore.variations.value)
});

const restorePageState = async (state) => {
    if (!state) return;
    menuValue.value = state.menuValue || 'list';
    filters.value = cloneValue(state.filters) || {
        consumableId: null,
        period: [startOfYear, today]
    };
    consumables.value = cloneValue(state.consumables) || [];
    stockVariationsStore.variations.value = cloneValue(state.variations) || [];
    await nextTick();
};

const prepareGuidedTourDemo = async () => {
    guidedTourPageState = capturePageState();
    activateAdminTourMock();
    resetAdminTourMockData();
    guidedTourDemoActive = true;
    menuValue.value = 'list';
    filters.value = {
        consumableId: null,
        period: [startOfYear, today]
    };
    await consumablesStore.fetchConsumables();
    await stockVariationsStore.fetchStockVariations(filters.value.consumableId, filters.value.period[0], filters.value.period[1]);
    await nextTick();
};

const cleanupGuidedTourDemo = async () => {
    if (!guidedTourDemoActive) {
        resetTourDialogs();
        return;
    }

    if (guidedTourCleanupPromise) {
        return guidedTourCleanupPromise;
    }

    guidedTourCleanupPromise = (async () => {
        resetTourDialogs();
        deactivateAdminTourMock();
        guidedTourDemoActive = false;
        const stateToRestore = guidedTourPageState;
        guidedTourPageState = null;
        await restorePageState(stateToRestore);
    })().finally(() => {
        guidedTourCleanupPromise = null;
    });

    return guidedTourCleanupPromise;
};

const openTourCreateDialog = async () => {
    resetTourDialogs();
    await nextTick();
    await waitForTourUi();
    editConsumable.value = null;
    showForm.value = true;
    await nextTick();
};

const openTourStockDialog = async (mode = 'withdraw') => {
    const target = lowStockConsumable.value || outOfStockConsumable.value || firstConsumable.value;
    if (!target) return;
    resetTourDialogs();
    await nextTick();
    await waitForTourUi();
    addRetireFormType.value = mode === 'add' ? 'add' : 'withdraw';
    editConsumable.value = target;
    showAddRetireForm.value = true;
    await nextTick();
};

const openTourDetailsDialog = async () => {
    const target = outOfStockConsumable.value || lowStockConsumable.value || firstConsumable.value;
    if (!target) return;
    resetTourDialogs();
    await nextTick();
    await waitForTourUi();
    await openDetails(target);
    await nextTick();
};

useGuidedTour({
    routeName: 'administration-consommables',
    hasOpenDialogs: () => hasOpenDialogs.value,
    prepareDemo: prepareGuidedTourDemo,
    cleanupDemo: cleanupGuidedTourDemo,
    getStepContext: () => ({
        setMode: setTourMode,
        openCreateDialog: openTourCreateDialog,
        openStockDialog: openTourStockDialog,
        openDetailsDialog: openTourDetailsDialog,
        closeAllDialogs: resetTourDialogs
    }),
    dialogsMessage: 'Fermez les fenetres ouvertes avant de lancer le tour.',
    errorMessage: 'Impossible de lancer le tour des consommables.'
});

</script>
<template>
    <section
        class="min-h-screen p-4 md:p-6 lg:p-8 transition-colors duration-300">
        <AppToast />
        
        <!-- Header Section -->
        <div class="mb-6 md:mb-8" data-tour="admin-consumables.header">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                            <i class="pi pi-shopping-cart text-primary-600 dark:text-primary-400 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold text-surface-900 dark:text-surface-50 tracking-tight">
                                Gestion des Consommables
                            </h1>
                            <p class="text-surface-600 dark:text-surface-300 text-sm md:text-base mt-1">
                                Suivez et gérez votre stock de consommables
                            </p>
                        </div>
                    </div>
                </div>
                <Button 
                    label="Nouveau Consommable" 
                    icon="pi pi-plus" 
                    class="shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white px-6 py-3 rounded-xl font-medium"
                    @click="showForm = true" 
                />
            </div>
            
            <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl p-4 shadow-sm border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
                <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" />
            </div>
        </div>

        <!-- Mode Selection Card -->
        <div class="mb-6 md:mb-8" data-tour="admin-consumables.mode">
            <div class="card p-5 md:p-6 border-0 rounded-2xl bg-gradient-to-r from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 shadow-lg backdrop-blur-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 mb-2 flex items-center gap-2">
                            <i class="pi pi-sliders-h text-primary-500"></i>
                            Mode d'affichage
                        </h3>
                        <p class="text-sm text-surface-600 dark:text-surface-400">
                            Choisissez la vue qui vous convient
                        </p>
                    </div>
                    <div class="flex justify-end">
                        <div class="bg-surface-100 dark:bg-surface-700 p-1.5 rounded-xl inline-flex">
                            <SelectButton 
                                v-model="menuValue" 
                                :options="optionsMenu" 
                                optionLabel="label" 
                                optionValue="value" 
                                :allowEmpty="false"
                                class="rounded-lg"
                                :pt="{
                                    button: ({ context }) => ({
                                        class: [
                                            'px-5 py-2.5 font-medium transition-all duration-300',
                                            context.selected 
                                                ? 'bg-white dark:bg-surface-800 shadow-sm text-primary-600 dark:text-primary-400' 
                                                : 'text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-surface-300'
                                        ]
                                    })
                                }"
                            >
                                <template #option="slotProps">
                                    <div class="flex items-center gap-2">
                                        <i :class="slotProps.option.icon"></i>
                                        <span>{{ slotProps.option.label }}</span>
                                    </div>
                                </template>
                            </SelectButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 md:mb-8" data-tour="admin-consumables.stats">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20 rounded-2xl p-5 border border-blue-200/50 dark:border-blue-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-blue-700 dark:text-blue-300 font-medium">Total Consommables</p>
                        <p class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-2">{{ consumables.length }}</p>
                    </div>
                    <i class="pi pi-box text-2xl text-blue-500"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-green-50 to-green-100/50 dark:from-green-900/20 dark:to-green-800/20 rounded-2xl p-5 border border-green-200/50 dark:border-green-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-green-700 dark:text-green-300 font-medium">En stock suffisant</p>
                        <p class="text-2xl font-bold text-green-900 dark:text-green-100 mt-2">
                            {{ consumables.filter(c => getQuantity(c) > getLowValue(c)).length }}
                        </p>
                    </div>
                    <i class="pi pi-check-circle text-2xl text-green-500"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 rounded-2xl p-5 border border-amber-200/50 dark:border-amber-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-amber-700 dark:text-amber-300 font-medium">Stock faible</p>
                        <p class="text-2xl font-bold text-amber-900 dark:text-amber-100 mt-2">
                            {{ consumables.filter(c => getQuantity(c) <= getLowValue(c) && getQuantity(c) > 0).length }}
                        </p>
                    </div>
                    <i class="pi pi-exclamation-triangle text-2xl text-amber-500"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-red-50 to-red-100/50 dark:from-red-900/20 dark:to-red-800/20 rounded-2xl p-5 border border-red-200/50 dark:border-red-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-red-700 dark:text-red-300 font-medium">En rupture</p>
                        <p class="text-2xl font-bold text-red-900 dark:text-red-100 mt-2">
                            {{ consumables.filter(c => getQuantity(c) === 0).length }}
                        </p>
                    </div>
                    <i class="pi pi-times-circle text-2xl text-red-500"></i>
                </div>
            </div>
        </div>

        <!-- List View -->
        <div v-if="menuValue === 'list'" data-tour="admin-consumables.list" class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-xl overflow-hidden border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
            <!-- List Header -->
            <div class="px-5 md:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-1">
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                            Liste des Consommables
                        </h3>
                        <p class="text-sm text-surface-600 dark:text-surface-400">
                            {{ consumables.length }} consommable(s) au total
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button 
                            icon="pi pi-download" 
                            severity="secondary" 
                            text 
                            size="small"
                            label="Exporter"
                            class="text-surface-600 dark:text-surface-400 hover:text-primary-600 dark:hover:text-primary-400"
                            @click="printConsumables"
                        />
                    </div>
                </div>
            </div>

            <!-- Data View -->
            <DataView 
                :value="consumables" 
                layout="list" 
                :paginator="true"  
                :rows="5" 
                :rowsPerPageOptions="[5, 10, 20]"
                class="p-0"
                :pt="{
                    paginator: {
                        class: 'px-5 py-4 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800'
                    }
                }"
            >
                <template #list="slotProps">
                    <div class="flex flex-col divide-y divide-surface-100 dark:divide-surface-700/50">
                        <div 
                            v-for="(item, index) in slotProps.items" 
                            :key="index"
                            class="p-5 md:p-6 hover:bg-surface-50/50 dark:hover:bg-surface-700/30 transition-colors duration-300"
                        >
                            <div class="flex flex-col lg:flex-row gap-5">
                                <!-- Image Section -->
                                <div class="lg:w-48 relative flex-shrink-0">
                                    <div class="relative overflow-hidden rounded-xl border border-surface-200 dark:border-surface-700">
                                        <img 
                                            class="w-full h-48 object-cover transition-transform duration-500 hover:scale-105"
                                            :src="`https://thumbs.dreamstime.com/b/ic%C3%B4ne-de-ligne-noire-pour-le-panier-consommable-et-client-achat-209452627.jpg`"
                                            :alt="item.nom" 
                                        />
                                        <div class="absolute top-3 left-3">
                                            <Tag 
                                                :value="getStatut(item)" 
                                                :severity="getSeverity(item)"
                                                class="px-3 py-1.5 rounded-full font-medium shadow-lg backdrop-blur-sm"
                                            ></Tag>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Content Section -->
                                <div class="flex-1">
                                    <div class="flex flex-col md:flex-row justify-between gap-4">
                                        <div class="flex-1">
                                            <div class="flex items-start justify-between mb-3">
                                                <div>
                                                    <h4 class="text-xl font-semibold text-surface-900 dark:text-surface-100 mb-1">
                                                        {{ item.nom }}
                                                    </h4>
                                                    <div class="flex items-center gap-2 text-surface-600 dark:text-surface-400 text-sm mb-3">
                                                        <i class="pi pi-building text-xs"></i>
                                                        <span>{{ item.fournisseur }}</span>
                                                    </div>
                                                </div>
                                                
                                                <!-- Stock Indicator -->
                                                <div class="flex flex-col items-end">
                                                    <div class="bg-surface-100 dark:bg-surface-700 p-2 rounded-full">
                                                        <div class="flex items-center gap-2 px-3 py-1.5 bg-white dark:bg-surface-800 rounded-full shadow-sm">
                                                            <span class="text-surface-900 dark:text-surface-100 font-semibold text-lg">
                                                                {{ getQuantity(item) }}
                                                            </span>
                                                            <span class="text-surface-500 dark:text-surface-400 text-sm">pcs</span>
                                                        </div>
                                                    </div>
                                                    <div class="mt-2 flex items-center gap-1">
                                                        <i class="pi pi-star-fill text-yellow-500 text-sm"></i>
                                                        <span class="text-xs text-surface-500 dark:text-surface-400">
                                                            Seuil bas : {{ getLowValue(item) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Progress Bar -->
                                            <div class="mb-6">
                                                <div class="flex justify-between text-sm text-surface-600 dark:text-surface-400 mb-1">
                                                    <span>Niveau de stock</span>
                                                    <span>{{ getStockProgress(item) }}%</span>
                                                </div>
                                                <div class="h-2 bg-surface-200 dark:bg-surface-700 rounded-full overflow-hidden">
                                                    <div 
                                                        :class="[
                                                            'h-full rounded-full transition-all duration-1000',
                                                            getSeverity(item) === 'success' ? 'bg-green-500' :
                                                            getSeverity(item) === 'warning' ? 'bg-amber-500' :
                                                            'bg-red-500'
                                                        ]"
                                                        :style="{ width: `${getStockProgress(item)}%` }"
                                                    ></div>
                                                </div>
                                            </div>
                                            
                                            <!-- Action Buttons -->
                                            <div class="flex flex-wrap items-center gap-2">
                                                <Button 
                                                    icon="pi pi-plus" 
                                                    severity="success"
                                                    label="Ajouter stock" 
                                                    outlined
                                                    class="rounded-xl px-4"
                                                    @click="toggleAddRetireForm('add', item)"
                                                />
                                                <Button 
                                                    icon="pi pi-minus" 
                                                    severity="warn"
                                                    label="Retirer stock" 
                                                    outlined
                                                    class="rounded-xl px-4"
                                                    @click="toggleAddRetireForm('retire', item)"
                                                />
                                                <Button 
                                                    icon="pi pi-pencil" 
                                                    severity="secondary"
                                                    label="Modifier" 
                                                    outlined
                                                    class="rounded-xl px-4"
                                                    @click="openEditForm(item)"
                                                />
                                                <Button 
                                                    icon="pi pi-trash" 
                                                    severity="danger"
                                                    label="Supprimer" 
                                                    outlined
                                                    class="rounded-xl px-4"
                                                    @click="confirmDelete(item)"
                                                />
                                                <Button 
                                                    icon="pi pi-eye" 
                                                    severity="info"
                                                    label="Détails" 
                                                    text
                                                    class="rounded-xl px-4"
                                                    @click="openDetails(item)"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <template #empty>
                    <div class="text-center py-16">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-surface-100 dark:bg-surface-800 mb-6">
                            <i class="pi pi-box text-4xl text-surface-400"></i>
                        </div>
                        <h4 class="text-xl font-semibold text-surface-700 dark:text-surface-300 mb-3">
                            Aucun consommable trouvé
                        </h4>
                        <p class="text-surface-600 dark:text-surface-400 mb-8 max-w-md mx-auto">
                            Vous n'avez pas encore de consommables enregistrés. Commencez par en ajouter un.
                        </p>
                        <Button 
                            icon="pi pi-plus" 
                            label="Ajouter un consommable" 
                            @click="showForm = true"
                            class="bg-gradient-to-r from-primary-500 to-primary-600 border-0"
                        />
                    </div>
                </template>
            </DataView>
        </div>

        <!-- Variations View -->
        <div v-else-if="menuValue === 'vars'" data-tour="admin-consumables.variations" class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-xl overflow-hidden border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
            <!-- Table Header -->
            <div class="px-5 md:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-1">
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                            Mouvements des stocks
                        </h3>
                        <p class="text-sm text-surface-600 dark:text-surface-400">
                            Historique complet des entrées et sorties
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button 
                            icon="pi pi-download" 
                            severity="secondary" 
                            text 
                            size="small"
                            label="Exporter"
                            class="text-surface-600 dark:text-surface-400 hover:text-primary-600 dark:hover:text-primary-400"
                            @click="printVariations"
                        />
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">
                            Consommable
                        </label>
                        <Select 
                            v-model="filters.consumableId" 
                            :options="consumables" 
                            optionLabel="nom"
                            optionValue="id" 
                            placeholder="Tous les consommables" 
                            showClear
                            class="w-full rounded-xl border-surface-200 dark:border-surface-700 [&_.p-dropdown]:p-3.5"
                            filter 
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">
                            Période
                        </label>
                        <div class="flex gap-3">
                            <PanelDatePicker
                                v-model="filters.period"
                                showClear
                                :manualInput="false"
                                dateFormat="dd/mm/yy"
                                placeholder="Sélectionnez une période"
                                class="flex-1"
                                inputClass="rounded-xl border-surface-200 dark:border-surface-700"
                            />
                            <Button
                                icon="pi pi-filter"
                                severity="secondary"
                                outlined
                                label="Filtrer"
                                class="rounded-xl px-5"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <DataTable 
                :value="stockVariationsStore.variations.value" 
                stripedRows 
                :paginator="true" 
                autoLayout 
                :rows="rowsPerPage" 
                :rowsPerPageOptions="[10, 20, 30, 50]" 
                :loading="stockVariationsStore.loading.value"
                class="rounded-none border-0"
                :pt="{
                    table: 'rounded-none',
                    thead: 'bg-surface-50 dark:bg-surface-900/50',
                    headerCell: ({ state }) => ({
                        class: [
                            'py-4 px-5 text-left font-semibold text-surface-700 dark:text-surface-300',
                            'border-b border-surface-200 dark:border-surface-700',
                            'bg-gradient-to-b from-surface-50 to-surface-100/50 dark:from-surface-900/50 dark:to-surface-800',
                            state.sorted && 'bg-primary-50 dark:bg-primary-900/20'
                        ]
                    }),
                    bodyCell: {
                        class: 'py-4 px-5 border-b border-surface-100 dark:border-surface-800'
                    },
                    row: {
                        class: 'hover:bg-surface-50/50 dark:hover:bg-surface-700/30 transition-colors'
                    },
                    paginator: {
                        class: 'px-5 py-4 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800'
                    }
                }"
            >
                <Column field="date" header="Date" sortable>
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-calendar text-surface-400"></i>
                            <span class="font-medium">{{ data.date }}</span>
                        </div>
                    </template>
                </Column>

                <Column field="consommable" header="Consommable" sortable>
                    <template #body="{ data }">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                                <i class="pi pi-box text-primary-600 dark:text-primary-400 text-sm"></i>
                            </div>
                            <span class="font-medium text-surface-900 dark:text-surface-100">{{ data.consommable }}</span>
                        </div>
                    </template>
                </Column>
                
                <Column field="employe" header="Employé" sortable>
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white text-xs">
                                {{ data.employe?.charAt(0) || 'U' }}
                            </div>
                            <span>{{ data.employe }}</span>
                        </div>
                    </template>
                </Column>
                
                <Column field="quantiteUtilisee" header="Quantité" sortable>
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <i 
                                :class="[
                                    'pi',
                                    data.type === 'Ajout' ? 'pi-arrow-up-right text-green-500' : 'pi-arrow-down-left text-amber-500'
                                ]"
                            ></i>
                            <span :class="[
                                'font-bold',
                                data.type === 'Ajout' ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400'
                            ]">
                                {{ data.type === 'Ajout' ? '+' : '-' }}{{ data.quantiteUtilisee }}
                            </span>
                        </div>
                    </template>
                </Column>
                
                <Column field="type" header="Type" sortable>
                    <template #body="{ data }">
                        <Tag 
                            :value="data.type" 
                            :severity="data.type === 'Ajout' ? 'success' : 'warn'"
                            class="px-3 py-1.5 rounded-full font-medium"
                        ></Tag>
                    </template>
                </Column>
                
                <Column field="description" header="Description">
                    <template #body="{ data }">
                        <span class="text-surface-600 dark:text-surface-400">{{ data.description || '-' }}</span>
                    </template>
                </Column>

                <template #empty>
                    <div class="text-center py-16">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-surface-100 dark:bg-surface-800 mb-6">
                            <i class="pi pi-history text-4xl text-surface-400"></i>
                        </div>
                        <h4 class="text-xl font-semibold text-surface-700 dark:text-surface-300 mb-3">
                            Aucun mouvement trouvé
                        </h4>
                        <p class="text-surface-600 dark:text-surface-400 mb-8 max-w-md mx-auto">
                            Aucun mouvement de stock n'a été enregistré pour cette période.
                        </p>
                        <Button 
                            icon="pi pi-plus" 
                            label="Ajouter un mouvement" 
                            @click="showAddRetireForm = true"
                            severity="secondary"
                            outlined
                            class="rounded-xl"
                        />
                    </div>
                </template>

                <template #loading>
                    <div class="flex items-center justify-center py-16">
                        <div class="text-center">
                            <i class="pi pi-spin pi-spinner text-4xl text-primary-500 mb-4"></i>
                            <p class="text-surface-600 dark:text-surface-400">Chargement des mouvements...</p>
                        </div>
                    </div>
                </template>
            </DataTable>
        </div>
    </section>

    <!-- Dialogs -->
    <Dialog 
        v-model:visible="showForm" 
        modal 
        header="Nouveau consommable" 
        style="width: 40rem"
        :pt="{
            root: 'rounded-2xl overflow-hidden',
            header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b',
            content: 'p-0 mt-4'
        }"
    >
        <div data-tour="admin-consumables.dialog.create">
            <ConsumableForm @saved="showForm = false" @close="showForm = false" :consumable="editConsumable" />
        </div>
    </Dialog>

    <Dialog 
        v-model:visible="showAddRetireForm" 
        modal 
        style="width: 35rem"
        :pt="{
            root: 'rounded-2xl overflow-hidden',
            header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b',
            content: 'p-0 mt-4'
        }"
    >
        <template #header> 
            <div class="flex items-center gap-3">
                <div :class="[
                    'p-2 rounded-lg',
                    addRetireFormType === 'add' 
                        ? 'bg-green-100 dark:bg-green-900/30' 
                        : 'bg-amber-100 dark:bg-amber-900/30'
                ]">
                    <i :class="[
                        'pi',
                        addRetireFormType === 'add' ? 'pi-plus text-green-600 dark:text-green-400' : 'pi-minus text-amber-600 dark:text-amber-400'
                    ]"></i>
                </div>
                <div>
                    <h4 class="m-0 text-surface-900 dark:text-surface-100">
                        {{ addRetireFormType === 'add' ? 'Ajouter au stock' : 'Retirer du stock' }}
                    </h4>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        {{ editConsumable?.nom || 'Consommable' }}
                    </p>
                </div>
            </div>
        </template>
        <div data-tour="admin-consumables.dialog.stock">
            <AddRetireStockForm 
                @saved="showAddRetireForm = false" 
                @cancelled="showAddRetireForm = false" 
                :mode="addRetireFormType" 
                :consumable="editConsumable"
            />
        </div>
    </Dialog>

    <Dialog 
        v-model:visible="showDetails" 
        modal 
        header="Détails du consommable"
        style="width: 32rem"
        :pt="{
            root: 'rounded-2xl overflow-hidden',
            header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b',
            content: 'p-6'
        }"
    >
        <div v-if="detailConsumable" class="space-y-4" data-tour="admin-consumables.dialog.details">
            <div>
                <p class="text-xs text-surface-500 dark:text-surface-400">Nom</p>
                <p class="text-lg font-semibold text-surface-900 dark:text-surface-100">{{ detailConsumable.nom }}</p>
            </div>
            <div>
                <p class="text-xs text-surface-500 dark:text-surface-400">Fournisseur</p>
                <p class="text-surface-900 dark:text-surface-100">{{ detailConsumable.fournisseur || '-' }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-surface-500 dark:text-surface-400">Quantité</p>
                    <p class="text-surface-900 dark:text-surface-100">{{ getQuantity(detailConsumable) }}</p>
                </div>
                <div>
                    <p class="text-xs text-surface-500 dark:text-surface-400">Seuil bas</p>
                    <p class="text-surface-900 dark:text-surface-100">{{ getLowValue(detailConsumable) }}</p>
                </div>
            </div>
        </div>
        <div v-else class="text-surface-500 dark:text-surface-400">Aucun consommable sélectionné.</div>
    </Dialog>
</template>
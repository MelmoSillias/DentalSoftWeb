<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { activateAdminTourMock, deactivateAdminTourMock, resetAdminTourMockData } from '@/services/adminTourMock';
import { useSalles } from '@/composables/useSalles';
import PageShell from '@/components/layout/PageShell.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import PageSection from '@/components/layout/PageSection.vue';
import AddSalleDialog from '@/components/salles/AddSalleDialog.vue';
import EditSalleDialog from '@/components/salles/EditSalleDialog.vue';
import SallesTable from '@/components/salles/SallesTable.vue';
import PrintDataTablePage from '@/components/print/PrintDataTablePage.vue';
import { usePrinter } from '@/composables/usePrinter';
import { useGuidedTour } from '@/composables/useGuidedTour';
import Button from 'primevue/button';
import ConfirmPopup from 'primevue/confirmpopup';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';

const toast = useToast();
const confirm = useConfirm();
const { printComponent } = usePrinter();
const { salles, loading, fetchSalles, addSalle, editSalle, deleteSalle } = useSalles();

const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' });
const breadcrumbItems = ref([{ label: 'Administration' }, { label: 'Salles' }]);

const addDialogVisible = ref(false);
const editDialogVisible = ref(false);
const currentSalle = ref(null);
let guidedTourPageState = null;
let guidedTourDemoActive = false;
let guidedTourCleanupPromise = null;

const totalLabel = computed(() => (salles.value?.length ? `${salles.value.length} salle(s)` : ''));
const hasOpenDialogs = computed(() => addDialogVisible.value || editDialogVisible.value);
const firstSalle = computed(() => salles.value?.[0] || null);

onMounted(() => {
    fetchSalles();
});

onBeforeUnmount(() => {
    deactivateAdminTourMock();
    guidedTourDemoActive = false;
    resetTourDialogs();
});

const openAdd = () => {
    addDialogVisible.value = true;
};

const openEdit = (salle) => {
    currentSalle.value = salle;
    editDialogVisible.value = true;
};

const handleAddSubmit = ({ payload, event }) => {
    confirm.require({
        target: event?.currentTarget,
        message: "Confirmer l'ajout de cette salle ?",
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Oui, ajouter',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await addSalle(payload);
                toast.add({ severity: 'success', summary: 'Salle ajoutée', detail: 'La salle a été créée.', life: 2500 });
                addDialogVisible.value = false;
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: e.message || 'Ajout impossible.', life: 3000 });
            }
        }
    });
};

const handleEditSubmit = ({ payload, event }) => {
    if (!currentSalle.value?.id) return;
    confirm.require({
        target: event?.currentTarget,
        message: 'Confirmer la modification de cette salle ?',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Oui, modifier',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await editSalle(currentSalle.value.id, payload);
                toast.add({ severity: 'success', summary: 'Salle modifiée', detail: 'Modifications enregistrées.', life: 2500 });
                editDialogVisible.value = false;
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: e.message || 'Modification impossible.', life: 3000 });
            }
        }
    });
};

const handleDelete = ({ event, salle }) => {
    if (!salle?.id) return;
    confirm.require({
        target: event?.currentTarget,
        message: 'Supprimer cette salle ? Cette action est irréversible.',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Oui, supprimer',
        rejectLabel: 'Annuler',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await deleteSalle(salle.id);
                toast.add({ severity: 'success', summary: 'Salle supprimée', detail: 'La salle a été supprimée.', life: 2500 });
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: e.message || 'Suppression impossible.', life: 3000 });
            }
        }
    });
};

const availableSalles = computed(() => {
    return salles.value.filter((salle) => salle.statut === 'disponible').length;
});

const occupiedSalles = computed(() => {
    return salles.value.filter((salle) => salle.statut === 'occupé').length;
});

const uniqueTypes = computed(() => {
    const types = new Set(salles.value.map((salle) => salle.type));
    return types.size;
});

const printSalles = async () => {
    const rows = (salles.value || []).map((salle) => ({
        nom: salle?.nom || '—',
        description: salle?.description || '—'
    }));

    await printComponent(PrintDataTablePage, {
        title: 'Liste des salles',
        subtitle: `${rows.length} salle(s)`,
        columns: [
            { key: 'nom', label: 'Nom' },
            { key: 'description', label: 'Description' }
        ],
        rows
    });
};

const resetTourDialogs = () => {
    addDialogVisible.value = false;
    editDialogVisible.value = false;
    currentSalle.value = null;
};

const cloneValue = (value) => {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
};

const waitForTourUi = (ms = 180) =>
    new Promise((resolve) => {
        window.setTimeout(resolve, ms);
    });

const capturePageState = () => ({
    salles: cloneValue(salles.value)
});

const restorePageState = async (state) => {
    if (!state) return;
    salles.value = cloneValue(state.salles) || [];
};

const prepareGuidedTourDemo = async () => {
    guidedTourPageState = capturePageState();
    activateAdminTourMock();
    resetAdminTourMockData();
    guidedTourDemoActive = true;
    await fetchSalles();
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

const openTourAddDialog = async () => {
    resetTourDialogs();
    await waitForTourUi();
    openAdd();
};

const openTourEditDialog = async () => {
    if (!firstSalle.value) return;
    resetTourDialogs();
    await waitForTourUi();
    openEdit(firstSalle.value);
};

useGuidedTour({
    routeName: 'administration-salles',
    hasOpenDialogs: () => hasOpenDialogs.value,
    prepareDemo: prepareGuidedTourDemo,
    cleanupDemo: cleanupGuidedTourDemo,
    getStepContext: () => ({
        openAddDialog: openTourAddDialog,
        openEditDialog: openTourEditDialog,
        closeAllDialogs: resetTourDialogs
    }),
    dialogsMessage: 'Fermez les fenetres ouvertes avant de lancer le tour.',
    errorMessage: 'Impossible de lancer le tour des salles.'
});
</script>

<template>
    <PageShell>
        <ConfirmPopup />

        <template #header>
            <PageHeader
                title="Gestion des salles"
                subtitle="Gérez vos espaces de consultation et de traitement"
                icon="pi pi-building"
                tour-id="admin-salles.header"
                :breadcrumb-items="breadcrumbItems"
                :breadcrumb-home="breadcrumbHome"
            >
                <template #actions>
                    <Button label="Ajouter une salle" icon="pi pi-plus" @click="openAdd" />
                </template>
            </PageHeader>
        </template>

        <PageSection title="Liste des salles" :subtitle="`${salles.length} salle(s) disponible(s)`" tour-id="admin-salles.table">
            <template #headerActions>
                <Button icon="pi pi-download" severity="secondary" text size="small" label="Exporter" @click="printSalles" />
            </template>
            <div class="page-table-scroll" data-tour="admin-salles.actions">
                <SallesTable :salles="salles" :loading="loading" @edit="openEdit" @delete="handleDelete" @add="openAdd" />
            </div>
        </PageSection>

        <!-- Stats Cards -->
        <div class="page-kpi-grid" data-tour="admin-salles.stats">
            <div class="page-kpi-card bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20 border-blue-200/50 dark:border-blue-800/50">
                <div>
                    <p class="page-kpi-label text-blue-700 dark:text-blue-300">Total salles</p>
                    <p class="page-kpi-value text-blue-900 dark:text-blue-100">{{ salles.length }}</p>
                </div>
                <i class="pi pi-building page-kpi-icon text-blue-500"></i>
            </div>

            <div class="page-kpi-card bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:from-emerald-900/20 dark:to-emerald-800/20 border-emerald-200/50 dark:border-emerald-800/50">
                <div>
                    <p class="page-kpi-label text-emerald-700 dark:text-emerald-300">Salles disponibles</p>
                    <p class="page-kpi-value text-emerald-900 dark:text-emerald-100">{{ availableSalles }}</p>
                </div>
                <i class="pi pi-check-circle page-kpi-icon text-emerald-500"></i>
            </div>

            <div class="page-kpi-card bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 border-amber-200/50 dark:border-amber-800/50">
                <div>
                    <p class="page-kpi-label text-amber-700 dark:text-amber-300">Salles occupées</p>
                    <p class="page-kpi-value text-amber-900 dark:text-amber-100">{{ occupiedSalles }}</p>
                </div>
                <i class="pi pi-clock page-kpi-icon text-amber-500"></i>
            </div>

            <div class="page-kpi-card bg-gradient-to-br from-purple-50 to-purple-100/50 dark:from-purple-900/20 dark:to-purple-800/20 border-purple-200/50 dark:border-purple-800/50">
                <div>
                    <p class="page-kpi-label text-purple-700 dark:text-purple-300">Types de salles</p>
                    <p class="page-kpi-value text-purple-900 dark:text-purple-100">{{ uniqueTypes }}</p>
                </div>
                <i class="pi pi-tags page-kpi-icon text-purple-500"></i>
            </div>
        </div>

        <!-- Dialogs -->
        <AddSalleDialog :visible="addDialogVisible" :loading="loading" tourTarget="admin-salles.dialog.add" @update:visible="(value) => (addDialogVisible = value)" @submit="handleAddSubmit" />
        <EditSalleDialog :visible="editDialogVisible" :salle="currentSalle" :loading="loading" tourTarget="admin-salles.dialog.edit" @update:visible="(value) => (editDialogVisible = value)" @submit="handleEditSubmit" />
    </PageShell>
</template>

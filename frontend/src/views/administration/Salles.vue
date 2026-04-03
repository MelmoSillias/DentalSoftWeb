<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { activateAdminTourMock, deactivateAdminTourMock, resetAdminTourMockData } from '@/services/adminTourMock';
import { useSalles } from '@/composables/useSalles';
import AddSalleDialog from '@/components/salles/AddSalleDialog.vue';
import EditSalleDialog from '@/components/salles/EditSalleDialog.vue';
import SallesTable from '@/components/salles/SallesTable.vue'; 
import PrintDataTablePage from '@/components/print/PrintDataTablePage.vue';
import { usePrinter } from '@/composables/usePrinter';
import { GUIDED_TOUR_START_EVENT } from '@/tours';
import { createAdministrationSallesTour } from '@/tours/administrationSallesTour';
import { startTourGuide } from '@/tours/tourGuideClient';
import Button from 'primevue/button';
import ConfirmPopup from 'primevue/confirmpopup';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';

const toast = useToast();
const confirm = useConfirm();
const { printComponent } = usePrinter();
const { salles, loading, fetchSalles, addSalle, editSalle, deleteSalle } = useSalles();

const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/' });
const breadcrumbItems = ref([
  { label: 'Administration' },
  { label: 'Salles' }
]);

const addDialogVisible = ref(false);
const editDialogVisible = ref(false);
const currentSalle = ref(null);
const isGuidedTourStarting = ref(false);
let guidedTourPageState = null;
let guidedTourDemoActive = false;
let guidedTourCleanupPromise = null;

const totalLabel = computed(() => (salles.value?.length ? `${salles.value.length} salle(s)` : ''));
const hasOpenDialogs = computed(() => addDialogVisible.value || editDialogVisible.value);
const firstSalle = computed(() => salles.value?.[0] || null);

onMounted(() => {
  fetchSalles();
  window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
});

onBeforeUnmount(() => {
  window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
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
  return salles.value.filter(salle => salle.statut === 'disponible').length;
});

const occupiedSalles = computed(() => {
  return salles.value.filter(salle => salle.statut === 'occupé').length;
});

const uniqueTypes = computed(() => {
  const types = new Set(salles.value.map(salle => salle.type));
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

const waitForTourUi = (ms = 180) => new Promise((resolve) => {
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

const handleGuidedTourRequest = async (event) => {
  if (event?.detail?.routeName !== 'administration-salles' || isGuidedTourStarting.value) {
    return;
  }

  if (hasOpenDialogs.value) {
    toast.add({
      severity: 'warn',
      summary: 'Aide guidee',
      detail: 'Fermez les fenetres ouvertes avant de lancer le tour.',
      life: 3000
    });
    return;
  }

  isGuidedTourStarting.value = true;

  try {
    await cleanupGuidedTourDemo();
    await prepareGuidedTourDemo();
    const steps = createAdministrationSallesTour({
      openAddDialog: openTourAddDialog,
      openEditDialog: openTourEditDialog,
      closeAllDialogs: resetTourDialogs
    });
    await startTourGuide({
      group: 'administration-salles',
      steps,
      onAfterExit: cleanupGuidedTourDemo,
      onFinish: cleanupGuidedTourDemo
    });
  } catch (error) {
    console.error('Erreur lancement guided tour salles', error);
    await cleanupGuidedTourDemo();
    toast.add({
      severity: 'error',
      summary: 'Aide guidee',
      detail: 'Impossible de lancer le tour des salles.',
      life: 3000
    });
  } finally {
    isGuidedTourStarting.value = false;
  }
};
</script>

 <template>
  <section class="min-h-screen p-4 md:p-6 lg:p-8 transition-colors duration-300">
    <Toast />
    <ConfirmPopup />

    <!-- Header Section -->
    <div class="mb-6 md:mb-8" data-tour="admin-salles.header">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
        <div class="space-y-2">
          <div class="flex items-center gap-3">
            <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
              <i class="pi pi-building text-primary-600 dark:text-primary-400 text-xl"></i>
            </div>
            <div>
              <h1 class="text-3xl lg:text-4xl font-bold text-surface-900 dark:text-surface-50 tracking-tight">
                Gestion des salles
              </h1>
              <p class="text-surface-600 dark:text-surface-300 text-sm md:text-base">
                Gérez vos espaces de consultation et de traitement
              </p>
            </div>
          </div>
        </div>
        <Button 
          class="rounded-xl px-5 py-3 font-medium shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white"
          label="Ajouter une salle" 
          icon="pi pi-plus" 
          @click="openAdd" 
        />
      </div>
      
      <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl p-4 shadow-sm border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
        <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="text-sm" />
      </div>
    </div>

    <!-- Main Content Card -->
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-xl overflow-hidden border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm" data-tour="admin-salles.table">
      <!-- Table Header -->
      <div class="px-5 md:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="space-y-1">
            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100">
              Liste des salles
            </h3>
            <p class="text-sm text-surface-600 dark:text-surface-400">
              {{ salles.length }} salle(s) disponible(s)
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
              @click="printSalles"
            />
            <Button 
              icon="pi pi-filter" 
              severity="secondary" 
              text 
              size="small"
              label="Filtrer"
              class="text-surface-600 dark:text-surface-400"
            />
          </div>
        </div>
      </div>

      <!-- Table Content -->
      <div class="p-0" data-tour="admin-salles.actions">
        <SallesTable :salles="salles" :loading="loading" @edit="openEdit" @delete="handleDelete" />
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6" data-tour="admin-salles.stats">
      <div class="bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20 rounded-2xl p-5 border border-blue-200/50 dark:border-blue-800/50">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-blue-700 dark:text-blue-300 font-medium">Total salles</p>
            <p class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-2">{{ salles.length }}</p>
          </div>
          <i class="pi pi-building text-2xl text-blue-500"></i>
        </div>
      </div>
      
      <div class="bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:from-emerald-900/20 dark:to-emerald-800/20 rounded-2xl p-5 border border-emerald-200/50 dark:border-emerald-800/50">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-emerald-700 dark:text-emerald-300 font-medium">Salles disponibles</p>
            <p class="text-2xl font-bold text-emerald-900 dark:text-emerald-100 mt-2">
              {{ availableSalles }}
            </p>
          </div>
          <i class="pi pi-check-circle text-2xl text-emerald-500"></i>
        </div>
      </div>
      
      <div class="bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 rounded-2xl p-5 border border-amber-200/50 dark:border-amber-800/50">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-amber-700 dark:text-amber-300 font-medium">Salles occupées</p>
            <p class="text-2xl font-bold text-amber-900 dark:text-amber-100 mt-2">
              {{ occupiedSalles }}
            </p>
          </div>
          <i class="pi pi-clock text-2xl text-amber-500"></i>
        </div>
      </div>
      
      <div class="bg-gradient-to-br from-purple-50 to-purple-100/50 dark:from-purple-900/20 dark:to-purple-800/20 rounded-2xl p-5 border border-purple-200/50 dark:border-purple-800/50">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-purple-700 dark:text-purple-300 font-medium">Types de salles</p>
            <p class="text-2xl font-bold text-purple-900 dark:text-purple-100 mt-2">
              {{ uniqueTypes }}
            </p>
          </div>
          <i class="pi pi-tags text-2xl text-purple-500"></i>
        </div>
      </div>
    </div>

    <!-- Dialogs -->
    <AddSalleDialog
      :visible="addDialogVisible"
      :loading="loading"
      tourTarget="admin-salles.dialog.add"
      @update:visible="(value) => (addDialogVisible = value)"
      @submit="handleAddSubmit"
    />
    <EditSalleDialog
      :visible="editDialogVisible"
      :salle="currentSalle"
      :loading="loading"
      tourTarget="admin-salles.dialog.edit"
      @update:visible="(value) => (editDialogVisible = value)"
      @submit="handleEditSubmit"
    />
  </section>
</template>
 
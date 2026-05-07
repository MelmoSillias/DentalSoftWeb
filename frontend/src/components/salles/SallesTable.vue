<script setup>
import { computed, ref } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

const props = defineProps({
  salles: { type: Array, default: () => [] },
  loading: Boolean
});
const emit = defineEmits(['edit', 'delete', 'add']);

const getTypeSeverity = (type) => {
  const severities = {
    'Consultation': 'info',
    'Chirurgie': 'danger',
    'Radiologie': 'warning',
    'Urgence': 'help',
    'Salle d\'attente': 'success'
  };
  return severities[type] || 'info';
};

const getStatusColor = (statut) => {
  const colors = {
    'disponible': 'bg-emerald-500',
    'occupé': 'bg-amber-500',
    'maintenance': 'bg-red-500',
    'nettoyage': 'bg-blue-500'
  };
  return colors[statut] || 'bg-surface-400';
};

const getStatusTextColor = (statut) => {
  const colors = {
    'disponible': 'text-emerald-600 dark:text-emerald-400',
    'occupé': 'text-amber-600 dark:text-amber-400',
    'maintenance': 'text-red-600 dark:text-red-400',
    'nettoyage': 'text-blue-600 dark:text-blue-400'
  };
  return colors[statut] || 'text-surface-600 dark:text-surface-400';
};

const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
  nom: { value: null, matchMode: FilterMatchMode.CONTAINS },
  description: { value: null, matchMode: FilterMatchMode.CONTAINS }
});

const globalFilterValue = computed({
  get: () => filters.value.global?.value ?? '',
  set: (value) => {
    filters.value = {
      ...filters.value,
      global: { ...filters.value.global, value }
    };
  }
});

const totalLabel = computed(() => (props.salles?.length ? `${props.salles.length} salle(s)` : ''));

const emitDelete = (event, salle) => {
  emit('delete', { event, salle });
};

const emitAdd = (event) => {
  emit('add', { event });
};
</script>

<!-- SallesTable.vue -->
<template>
  <DataTable 
    :value="salles" 
    :loading="loading"
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
    <Column field="nom" header="Nom" :sortable="true">
      <template #body="{ data }">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-500/10">
            <i class="pi pi-building text-primary-500 text-sm"></i>
          </div>
          <div>
            <div class="font-medium text-surface-900 dark:text-surface-100">{{ data.nom }}</div>
            <div class="text-xs text-surface-500 dark:text-surface-400">ID: {{ data.id }}</div>
          </div>
        </div>
      </template>
    </Column>

    <Column field="description" header="Description" :sortable="true">
      <template #body="{ data }">
        <div class="flex items-center gap-3"> 
          <div>
            <div class="font-medium text-surface-900 dark:text-surface-100">{{ data.description }}</div>
          </div>
        </div>
      </template>
    </Column>
    
    <!-- <Column field="type" header="Type" :sortable="true">
      <template #body="{ data }">
        <Tag 
          :value="data.type" 
          :severity="getTypeSeverity(data.type)"
          class="px-3 py-1.5 rounded-full font-medium"
        />
      </template>
    </Column>
    
    <Column field="capacite" header="Capacité" :sortable="true">
      <template #body="{ data }">
        <div class="flex items-center gap-2">
          <i class="pi pi-users text-surface-400"></i>
          <span class="font-medium text-surface-900 dark:text-surface-100">{{ data.capacite }}</span>
          <span class="text-sm text-surface-500 dark:text-surface-400">personnes</span>
        </div>
      </template>
    </Column>
    
    <Column field="equipement" header="Équipement">
      <template #body="{ data }">
        <div v-if="data.equipement && data.equipement.length" class="flex flex-wrap gap-1">
          <span 
            v-for="(eq, index) in data.equipement.slice(0, 3)" 
            :key="index"
            class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-surface-100 dark:bg-surface-700 text-surface-600 dark:text-surface-400"
          >
            {{ eq }}
          </span>
          <span 
            v-if="data.equipement.length > 3"
            class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-surface-100 dark:bg-surface-700 text-surface-600 dark:text-surface-400"
          >
            +{{ data.equipement.length - 3 }}
          </span>
        </div>
        <span v-else class="text-surface-400 dark:text-surface-500">—</span>
      </template>
    </Column>
    
    <Column field="statut" header="Statut" :sortable="true">
      <template #body="{ data }">
        <div class="flex items-center gap-2">
          <div class="w-2 h-2 rounded-full" :class="getStatusColor(data.statut)"></div>
          <span class="font-medium" :class="getStatusTextColor(data.statut)">
            {{ data.statut }}
          </span>
        </div>
      </template>
    </Column> -->
    
    <Column header="Actions" style="min-width: 120px">
      <template #body="{ data }">
        <div class="flex items-center gap-2">
          <Button 
            icon="pi pi-eye" 
            severity="secondary" 
            text 
            rounded
            v-tooltip.top="'Voir détails'"
            class="hover:bg-surface-100 dark:hover:bg-surface-700"
          />
          <Button 
            icon="pi pi-pen-to-square" 
            severity="info" 
            text 
            rounded
            v-tooltip.top="'Modifier'"
            class="hover:bg-blue-50 dark:hover:bg-blue-900/20"
            @click="$emit('edit', data)" 
          />
          <Button 
            icon="pi pi-trash" 
            severity="danger" 
            text 
            rounded
            v-tooltip.top="'Supprimer'"
            class="hover:bg-red-50 dark:hover:bg-red-900/20"
            @click="$emit('delete', data)" 
          />
        </div>
      </template>
    </Column>

    <!-- Empty State -->
    <template #empty>
      <div class="text-center py-12">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-surface-100 dark:bg-surface-800 mb-4">
          <i class="pi pi-building text-3xl text-surface-400"></i>
        </div>
        <h4 class="text-lg font-semibold text-surface-700 dark:text-surface-300 mb-2">
          Aucune salle trouvée
        </h4>
        <p class="text-surface-600 dark:text-surface-400 mb-6 max-w-md mx-auto">
          Commencez par ajouter votre première salle pour gérer vos espaces de consultation.
        </p>
        <Button 
          label="Ajouter une salle" 
          icon="pi pi-plus"
          @click="emitAdd" 
          class="bg-gradient-to-r from-primary-500 to-primary-600 border-0 rounded-xl px-5 py-2.5"
        />
      </div>
    </template>

    <!-- Loading State -->
    <template #loading>
      <div class="flex items-center justify-center py-12">
        <div class="text-center">
          <i class="pi pi-spin pi-spinner text-4xl text-primary-500 mb-4"></i>
          <p class="text-surface-600 dark:text-surface-400">Chargement des salles...</p>
        </div>
      </div>
    </template>
  </DataTable>
</template> 
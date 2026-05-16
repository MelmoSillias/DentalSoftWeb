<script setup>
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';

const props = defineProps({
    claims: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    statusFilter: { type: String, default: 'all' },
    actionLoadingId: { type: Number, default: null },
    assuranceOptions: { type: Array, default: () => [] },
    insuranceRange: { type: Array, default: () => [] },
    insurancePatientFilter: { type: String, default: '' },
    insuranceAssuranceFilter: { type: String, default: 'all' }
});

const emit = defineEmits([
    'update:statusFilter',
    'update:insuranceRange',
    'update:insurancePatientFilter',
    'update:insuranceAssuranceFilter',
    'refresh',
    'validate-claim',
    'reject-claim',
    'recover-claim',
    'collect-patient-share'
]);

const search = ref('');

const statusOptions = [
    { label: 'Tous les statuts', value: 'all' },
    { label: 'En attente', value: 'pending' },
    { label: 'Validées', value: 'validated' },
    { label: 'Rejetées', value: 'rejected' },
    { label: 'Recouvrées', value: 'recouvre' }
];

const normalizeText = (value) => String(value ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');

const statusModel = computed({
    get: () => props.statusFilter,
    set: (value) => emit('update:statusFilter', value || 'all')
});

const insuranceStart = computed({
    get: () => props.insuranceRange?.[0] || null,
    set: (value) => emit('update:insuranceRange', [value || null, props.insuranceRange?.[1] || null])
});

const insuranceEnd = computed({
    get: () => props.insuranceRange?.[1] || null,
    set: (value) => emit('update:insuranceRange', [props.insuranceRange?.[0] || null, value || null])
});

const insurancePatientModel = computed({
    get: () => props.insurancePatientFilter || '',
    set: (value) => emit('update:insurancePatientFilter', value || '')
});

const insuranceAssuranceModel = computed({
    get: () => props.insuranceAssuranceFilter || 'all',
    set: (value) => emit('update:insuranceAssuranceFilter', value || 'all')
});

const filteredClaims = computed(() => {
    const query = normalizeText(search.value.trim());
    return (Array.isArray(props.claims) ? props.claims : []).filter((claim) => {
        if (!query) return true;

        return [
            claim?.patient,
            claim?.telephone,
            claim?.assurance?.nom,
            claim?.assurance?.code,
            claim?.insuranceStatus,
            claim?.dateFacture
        ].some((part) => normalizeText(part).includes(query));
    });
});

const statusTag = (status) => {
    if (status === 'validated') return { label: 'Validée', severity: 'success' };
    if (status === 'rejected') return { label: 'Rejetée', severity: 'danger' };
    if (status === 'recouvre') return { label: 'Recouvrée', severity: 'info' };
    return { label: 'En attente', severity: 'warning' };
};

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;
const canAct = (claimId) => props.actionLoadingId === null || props.actionLoadingId !== Number(claimId);
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Header & Filtres -->
    <div class="section-card p-5 bg-white dark:bg-surface-900 rounded-2xl shadow-sm border border-surface-200 dark:border-surface-700">
      <div class="flex flex-col gap-5">
        <!-- Titre + Rafraîchir -->
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <i class="pi pi-shield text-primary text-xl"></i>
            <div>
              <p class="text-xs font-semibold uppercase tracking-wider text-primary">Suivi assurances</p>
              <h2 class="text-xl font-bold text-surface-800 dark:text-surface-100">Gestion des créances</h2>
            </div>
          </div>
          <Button
            icon="pi pi-refresh"
            label="Rafraîchir"
            outlined
            rounded
            @click="emit('refresh')"
            class="!text-sm"
          />
        </div>

        <!-- Grille de filtres responsive -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
          <!-- Patient / Téléphone avec icône -->
          <div class="relative">
            <i class="pi pi-user absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-sm"></i>
            <InputText
              v-model="insurancePatientModel"
              placeholder="Patient ou téléphone"
              class="w-full pl-9"
            />
          </div>

          <!-- Assurance -->
          <Select
            v-model="insuranceAssuranceModel"
            :options="assuranceOptions || []"
            optionLabel="label"
            optionValue="value"
            placeholder="Toutes assurances"
            class="w-full"
            showClear
          />

          <!-- Date début -->
          <div class="relative">
            <i class="pi pi-calendar absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-sm"></i>
            <InputText
              type="date"
              class="w-full pl-9"
              :modelValue="insuranceStart"
              @update:modelValue="insuranceStart = $event"
            />
          </div>

          <!-- Date fin -->
          <div class="relative">
            <i class="pi pi-calendar absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-sm"></i>
            <InputText
              type="date"
              class="w-full pl-9"
              :modelValue="insuranceEnd"
              @update:modelValue="insuranceEnd = $event"
            />
          </div>

          <!-- Statut -->
          <Select
            v-model="statusModel"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Tous statuts"
            class="w-full"
            showClear
          />

          <!-- Recherche globale -->
          <div class="relative">
            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-sm"></i>
            <InputText
              v-model="search"
              placeholder="Recherche globale..."
              class="w-full pl-9"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Liste des créances -->
    <div class="section-card p-5 bg-white dark:bg-surface-900 rounded-2xl shadow-sm border border-surface-200 dark:border-surface-700">
      <!-- État chargement -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-12">
        <i class="pi pi-spin pi-spinner text-3xl text-primary"></i>
        <p class="mt-3 text-surface-500 text-sm">Chargement des créances assurances...</p>
      </div>

      <!-- État vide -->
      <div v-else-if="!filteredClaims.length" class="flex flex-col items-center justify-center py-12 text-center">
        <i class="pi pi-inbox text-5xl text-surface-300 dark:text-surface-600"></i>
        <p class="mt-3 text-surface-500 font-medium">Aucune créance trouvée</p>
        <p class="text-sm text-surface-400">Modifiez vos filtres ou rechargez la liste</p>
      </div>

      <!-- Grille de cartes -->
      <div v-else class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
        <article
          v-for="claim in filteredClaims"
          :key="claim.id"
          class="group relative rounded-2xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800/30 p-5 transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5"
        >
          <!-- Entête : patient + statut -->
          <div class="flex items-start justify-between gap-2">
            <div class="flex-1">
              <p class="text-base font-bold text-surface-800 dark:text-surface-100 truncate">
                {{ claim.patient || 'Patient inconnu' }}
              </p>
              <div class="flex items-center gap-1 mt-0.5">
                <i class="pi pi-phone text-surface-400 text-xs"></i>
                <p class="text-xs text-surface-500 truncate">
                  {{ claim.telephone || 'Téléphone indisponible' }}
                </p>
              </div>
            </div>
            <Tag
              :value="statusTag(claim.insuranceStatus).label"
              :severity="statusTag(claim.insuranceStatus).severity"
              rounded
              class="!px-2 !py-0.5 text-xs font-semibold"
            />
          </div>

          <!-- Infos assurance + date -->
          <div class="mt-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs">
            <div class="flex items-center gap-1.5 text-surface-600 dark:text-surface-400">
              <i class="pi pi-building text-primary"></i>
              <span>
                {{ claim?.assurance?.nom || '—' }}
                <span v-if="claim?.assurance?.code" class="font-mono">({{ claim.assurance.code }})</span>
              </span>
            </div>
            <div class="flex items-center gap-1.5 text-surface-500">
              <i class="pi pi-calendar-clock"></i>
              <span>{{ claim.dateFacture || 'Date inconnue' }}</span>
            </div>
          </div>

          <!-- Montants (grille 2x2) -->
          <div class="mt-5 grid grid-cols-2 gap-3 rounded-xl bg-surface-50 dark:bg-surface-800/50 p-3 text-sm">
            <div>
              <p class="text-xs text-surface-500 uppercase tracking-wide">Total</p>
              <p class="text-base font-bold text-surface-700 dark:text-surface-200">{{ formatFcfa(claim.montantTotal) }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-500 uppercase tracking-wide">Assurance</p>
              <p class="text-base font-bold text-primary">{{ formatFcfa(claim.montantAssurance) }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-500 uppercase tracking-wide">Patient</p>
              <p class="text-base font-bold text-orange-600 dark:text-orange-400">{{ formatFcfa(claim.montantPatient) }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-500 uppercase tracking-wide">Taux</p>
              <p class="text-base font-bold text-emerald-600 dark:text-emerald-400">{{ claim.tauxCouverture }}%</p>
            </div>
          </div>

          <!-- Détails du paiement patient -->
          <div class="mt-4 flex items-center justify-between border-t border-surface-100 dark:border-surface-700 pt-3 text-xs">
            <div class="flex items-center gap-1.5">
              <i class="pi pi-wallet text-surface-400"></i>
              <span class="text-surface-500">Payé :</span>
              <span class="font-medium text-surface-700 dark:text-surface-300">{{ formatFcfa(claim.patientPaidAmount) }}</span>
            </div>
            <div class="flex items-center gap-1.5">
              <i class="pi pi-clock text-red-400"></i>
              <span class="text-surface-500">Reste patient :</span>
              <span class="font-bold text-red-600 dark:text-red-400">{{ formatFcfa(claim.restePatient) }}</span>
            </div>
          </div>

          <!-- Actions (avec tooltips et état désactivé) -->
          <div class="mt-5 flex flex-wrap justify-end gap-2 border-t border-surface-100 dark:border-surface-700 pt-3">
            <Button
              v-if="claim.availableActions?.canValidate"
              size="small"
              icon="pi pi-check-circle"
              severity="success"
              rounded
              text
              :disabled="!canAct(claim.id)"
              @click="emit('validate-claim', claim)"
              v-tooltip.top="'Valider la créance'"
            />
            <Button
              v-if="claim.availableActions?.canReject"
              size="small"
              icon="pi pi-times-circle"
              severity="danger"
              rounded
              text
              :disabled="!canAct(claim.id)"
              @click="emit('reject-claim', claim)"
              v-tooltip.top="'Rejeter'"
            />
            <Button
              v-if="claim.availableActions?.canRecover"
              size="small"
              icon="pi pi-wallet"
              severity="info"
              rounded
              text
              :disabled="!canAct(claim.id)"
              @click="emit('recover-claim', claim)"
              v-tooltip.top="'Recouvrement assurance'"
            />
            <Button
              v-if="claim.availableActions?.canCollectPatient"
              size="small"
              icon="pi pi-credit-card"
              severity="secondary"
              rounded
              text
              :disabled="!canAct(claim.id)"
              @click="emit('collect-patient-share', claim)"
              v-tooltip.top="'Collecte patient'"
            />
          </div>
        </article>
      </div>
    </div>
  </div>
</template>

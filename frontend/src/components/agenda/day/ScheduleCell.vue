<script setup>
import Button from 'primevue/button';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import { computed } from 'vue';
import { useRdvStatus } from '@/composables/useRdvStatus';

const props = defineProps({
  slotLabel: { type: String, required: true },
  rdv: { type: Object, default: null },
  medecinName: { type: String, default: '' }
});

const emit = defineEmits(['create', 'validate', 'cancel', 'report']);
const { getLabel, getSeverity } = useRdvStatus();

const hasRdv = computed(() => !!props.rdv);

const patientLabel = computed(() => {
  if (!props.rdv) return '';
  return (
    props.rdv.patientName ||
    props.rdv.patient ||
    props.rdv.patient?.fullname ||
    props.rdv.patient?.name ||
    `${props.rdv.patient?.prenom ?? ''} ${props.rdv.patient?.nom ?? ''}`.trim() ||
    props.rdv.patient_nom ||
    props.rdv.patient_prenom ||
    'Patient inconnu'
  );
});

const medecinLabel = computed(() => {
  if (!props.rdv) return props.medecinName || '—';
  return (
    props.rdv.medecinName ||
    props.rdv.medecin ||
    props.rdv.medecin?.fullname ||
    props.rdv.medecin?.name ||
    `${props.rdv.medecin?.prenom ?? ''} ${props.rdv.medecin?.nom ?? ''}`.trim() ||
    props.medecinName ||
    'Médecin'
  );
});

const descriptionLabel = computed(() => {
  if (!props.rdv) return 'Créer un rendez-vous';
  return props.rdv.description || props.rdv.motif || props.rdv.note || 'Aucune description';
});

const statutValue = computed(() => props.rdv?.statut ?? props.rdv?.status ?? props.rdv?.etat ?? 0);
const smsReminder = computed(() => props.rdv?.smsReminder || null);
const smsSeverity = computed(() => {
  const status = String(smsReminder.value?.status || '').toLowerCase();
  if (status === 'sent') return 'success';
  if (status === 'failed') return 'danger';
  if (status === 'sending') return 'info';
  return smsReminder.value?.isAutomatic ? 'warning' : 'secondary';
});
const smsLabel = computed(() => smsReminder.value?.label ? `SMS: ${smsReminder.value.label}` : '');

const trigger = (eventName) => {
  emit(eventName, { rdv: props.rdv });
};
</script>

<template>
  <div class="group relative h-full min-h-[112px] xs:min-h-[128px] bg-surface-0 p-2 xs:p-3 shadow-sm transition-all duration-200 hover:shadow-md dark:bg-surface-900 dark:shadow-none dark:ring-1 dark:ring-surface-800/80">
    <!-- Header -->
    <div class="mb-1.5 xs:mb-2.5 flex items-center justify-between text-[10px] xs:text-xs font-medium text-surface-500 dark:text-surface-400">
      <span>{{ slotLabel }}</span>
      <Button
        v-if="!hasRdv"
        icon="pi pi-plus"
        rounded
        text
        severity="secondary"
        size="small"
        class="opacity-70 hover:opacity-100 transition-opacity"
        @click.stop="emit('create')"
      />
    </div>

    <!-- Contenu RDV -->
    <Card
      v-if="hasRdv"
      class="h-full border border-surface-200 bg-surface-50/80 shadow-sm transition-colors hover:border-primary-300 dark:border-surface-700 dark:bg-surface-800 dark:hover:border-primary-700/50"
    >
      <template #content>
        <div class="flex flex-col h-full">
          <div class="mb-1.5 xs:mb-2.5 flex items-start justify-between gap-2 xs:gap-3">
            <div class="min-w-0 flex-1">
              <div class="truncate font-semibold text-surface-900 dark:text-surface-0 text-sm xs:text-base leading-tight">
                {{ patientLabel }}
              </div>
              <div class="mt-0.5 truncate text-xs xs:text-sm text-surface-600 dark:text-surface-400">
                {{ medecinLabel }}
              </div>
            </div>
            <Tag
              :severity="getSeverity(statutValue)"
              :value="getLabel(statutValue)"
              class="text-[10px] xs:text-xs font-medium whitespace-nowrap"
            />
          </div>

          <p class="mb-3 xs:mb-4 line-clamp-2 text-xs xs:text-sm text-surface-700 dark:text-surface-300 leading-relaxed">
            {{ descriptionLabel }}
          </p>

          <Tag
            v-if="smsReminder"
            :severity="smsSeverity"
            :value="smsLabel"
            class="mb-3 w-fit text-[10px] xs:text-xs font-medium whitespace-nowrap"
          />

          <div v-if="statutValue === 0" class="mt-auto flex flex-wrap gap-1 xs:gap-2">
            <Button
              size="small"
              icon="pi pi-check"
              label="Valider"
              class="text-[10px] xs:text-xs"
              @click="trigger('validate')"
            />
            <Button
              size="small"
              icon="pi pi-calendar-minus"
              label="Reporter"
              severity="warning"
              text
              class="text-[10px] xs:text-xs"
              @click="trigger('report')"
            />
            <Button
              size="small"
              icon="pi pi-times"
              label="Annuler"
              severity="danger"
              text
              class="text-[10px] xs:text-xs"
              @click="trigger('cancel')"
            />
          </div>
        </div>
      </template>
    </Card>

    <!-- Placeholder quand pas de RDV -->
    <div
      v-else
      class="mt-2 xs:mt-3 flex h-16 xs:h-20 items-center justify-center rounded-lg border-2 border-dashed border-surface-300 bg-surface-50/50 text-xs xs:text-sm text-surface-400 transition-colors hover:border-surface-400 dark:border-surface-600 dark:bg-surface-800/40 dark:text-surface-500 dark:hover:border-surface-500"
    >
      Disponible
    </div>
  </div>
</template>


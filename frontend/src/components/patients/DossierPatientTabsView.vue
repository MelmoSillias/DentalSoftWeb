<script setup>
import ArchiveFilesSection from '@/components/patients/ArchiveFilesSection.vue';
import DossierPatientInfoCard from '@/components/patients/DossierPatientInfoCard.vue';
import FichesMedicalesSection from '@/components/patients/FichesMedicalesSection.vue';
import ListePatientConsultations from '@/components/patients/ListePatientConsultations.vue';
import RdvPaiementsSection from '@/components/patients/RdvPaiementsSection.vue';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import { computed, ref } from 'vue';
import { computeAgeYears } from '@/utils/formuleDentaireLayout';

const props = defineProps({
    patient: { type: Object, required: true },
    patientId: { type: Number, default: null },
    fiches: { type: Array, default: () => [] },
    consultations: { type: Array, default: () => [] },
    consultationsLoading: { type: Boolean, default: false },
    rdvs: { type: Array, default: () => [] },
    paiements: { type: Array, default: () => [] },
    factures: { type: Array, default: () => [] },
    archiveFiles: { type: Array, default: () => [] },
    isReception: { type: Boolean, default: false },
    isMedecin: { type: Boolean, default: false },
    showConsultationsTab: { type: Boolean, default: false },
    hidePhone: { type: Boolean, default: false }
});

const emit = defineEmits([
    'print-dossier',
    'edit',
    'new-rdv',
    'photo-selected',
    'add-antecedent',
    'add-allergy',
    'delete-antecedent',
    'delete-allergy',
    'create-portal-account',
    'reset-portal-password',
    'toggle-portal-active',
    'print-fiche',
    'new-consultation',
    'fiche-updated',
    'fiche-created',
    'refresh-archive',
    'refresh'
]);

const activeTab = ref('identite');

const patientAge = computed(() => computeAgeYears(props.patient?.dateNaissance || props.patient?.age));

const tabs = computed(() => [
    {
        id: 'identite',
        label: 'Identité & archives',
        icon: 'pi pi-user',
        badge: props.archiveFiles?.length || null
    },
    {
        id: 'clinique',
        label: 'Dossier clinique',
        icon: 'pi pi-folder-open',
        badge: props.isReception ? props.consultations?.length : props.fiches?.length
    },
    {
        id: 'activite',
        label: 'Activité & finances',
        icon: 'pi pi-chart-line',
        badge: props.rdvs?.length || null
    }
]);
</script>

<template>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm">
        <Tabs :value="activeTab" @update:value="activeTab = $event" class="dossier-tabs">
            <TabList
                class="flex flex-wrap gap-1 border-b border-surface-200/50 dark:border-surface-700/50 px-4 pt-4"
                data-tour="patients-dossier.main-tabs"
            >
                <Tab v-for="tab in tabs" :key="tab.id" :value="tab.id" class="flex-1 sm:flex-none">
                    <span class="flex items-center justify-center gap-2 px-2 py-1">
                        <i :class="tab.icon"></i>
                        <span class="hidden sm:inline">{{ tab.label }}</span>
                        <span
                            v-if="tab.badge"
                            class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/40 px-1.5 py-0.5 text-[10px] font-semibold text-primary-700 dark:text-primary-300"
                        >
                            {{ tab.badge }}
                        </span>
                    </span>
                </Tab>
            </TabList>

            <TabPanels class="p-4 md:p-6">
                <TabPanel value="identite">
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                        <div data-tour="patients-dossier.info-card">
                            <DossierPatientInfoCard
                                :patient="patient"
                                :hide-phone="hidePhone"
                                @print-dossier="emit('print-dossier')"
                                @edit="emit('edit')"
                                @new-rdv="emit('new-rdv')"
                                @photo-selected="(file) => emit('photo-selected', file)"
                                @add-antecedent="emit('add-antecedent')"
                                @add-allergy="emit('add-allergy')"
                                @delete-antecedent="(item) => emit('delete-antecedent', item)"
                                @delete-allergy="(item) => emit('delete-allergy', item)"
                                @create-portal-account="emit('create-portal-account')"
                                @reset-portal-password="emit('reset-portal-password')"
                                @toggle-portal-active="(active) => emit('toggle-portal-active', active)"
                            />
                        </div>
                        <div data-tour="patients-dossier.archive-files">
                            <ArchiveFilesSection
                                :patient-id="patientId"
                                :files="archiveFiles"
                                @refresh="emit('refresh-archive')"
                            />
                        </div>
                    </div>
                </TabPanel>

                <TabPanel value="clinique">
                    <div data-tour="patients-dossier.medical">
                        <ListePatientConsultations
                            v-if="isReception"
                            :consultations="consultations"
                            :loading="consultationsLoading"
                        />
                        <FichesMedicalesSection
                            v-else
                            :fiches="fiches"
                            :patient-id="patientId"
                            :patient-age="patientAge"
                            :can-create-consultation="!isMedecin"
                            @print-fiche="(fiche) => emit('print-fiche', fiche)"
                            @new-consultation="emit('new-consultation')"
                            @fiche-updated="emit('fiche-updated')"
                            @fiche-created="emit('fiche-created')"
                        />
                    </div>
                </TabPanel>

                <TabPanel value="activite">
                    <div data-tour="patients-dossier.finance">
                        <RdvPaiementsSection
                            :rdvs="rdvs"
                            :paiements="paiements"
                            :factures="factures"
                            :consultations="consultations"
                            :show-consultations="showConsultationsTab"
                            @refresh="emit('refresh')"
                        />
                    </div>
                </TabPanel>
            </TabPanels>
        </Tabs>
    </div>
</template>

<style scoped>
.dossier-tabs :deep(.p-tablist-tab-list) {
    background: transparent;
    border: none;
}

.dossier-tabs :deep(.p-tab) {
    border-radius: 0.75rem 0.75rem 0 0;
}
</style>

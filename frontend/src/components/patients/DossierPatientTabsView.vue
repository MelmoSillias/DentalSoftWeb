<script setup>
import ArchiveFilesSection from '@/components/patients/ArchiveFilesSection.vue';
import DossierPatientInfoCard from '@/components/patients/DossierPatientInfoCard.vue';
import FichesMedicalesSection from '@/components/patients/FichesMedicalesSection.vue';
import ListePatientConsultations from '@/components/patients/ListePatientConsultations.vue';
import PatientActiviteFinancesSection from '@/components/patients/PatientActiviteFinancesSection.vue';
import Button from 'primevue/button';
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
    servicesCabinet: { type: Array, default: () => [] },
    archiveFiles: { type: Array, default: () => [] },
    isReception: { type: Boolean, default: false },
    isMedecin: { type: Boolean, default: false },
    showConsultationsTab: { type: Boolean, default: false },
    hidePhone: { type: Boolean, default: false },
    /** Hide spine actions when parent already exposes them (e.g. dialog header) */
    hideSpineActions: { type: Boolean, default: false }
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

const spineMeta = computed(() => {
    const parts = [];
    if (props.patient?.numeroDossier) parts.push(props.patient.numeroDossier);
    if (patientAge.value != null && patientAge.value !== '') parts.push(`${patientAge.value} ans`);
    if (props.patient?.sexe) parts.push(props.patient.sexe);
    return parts.join(' · ');
});

const patientDisplayName = computed(() => `${props.patient?.nom || ''} ${props.patient?.prenom || ''}`.trim());

const activiteBadge = computed(() => {
    const total =
        (props.rdvs?.length || 0) +
        (props.paiements?.length || 0) +
        (props.factures?.length || 0) +
        (props.servicesCabinet?.length || 0);
    return total || null;
});

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
        badge: activiteBadge.value
    }
]);
</script>

<template>
    <div class="dossier-folder">
        <div class="dossier-folder__spine">
            <div class="dossier-folder__spine-main">
                <h2 class="dossier-folder__spine-name">{{ patient.nom }} {{ patient.prenom }}</h2>
                <p v-if="spineMeta" class="dossier-folder__spine-meta">{{ spineMeta }}</p>
            </div>
            <div v-if="!hideSpineActions" class="dossier-folder__spine-actions">
                <Button icon="pi pi-print" label="Imprimer" severity="secondary" outlined size="small" @click="emit('print-dossier')" />
                <Button icon="pi pi-pencil" label="Modifier" severity="secondary" outlined size="small" @click="emit('edit')" />
                <Button icon="pi pi-plus" label="RDV" size="small" @click="emit('new-rdv')" />
            </div>
        </div>

        <Tabs :value="activeTab" @update:value="activeTab = $event" class="dossier-folder__tabs">
            <TabList data-tour="patients-dossier.main-tabs">
                <Tab v-for="tab in tabs" :key="tab.id" :value="tab.id">
                    <span class="dossier-folder__tab-label">
                        <i :class="tab.icon"></i>
                        <span class="hidden sm:inline">{{ tab.label }}</span>
                        <span v-if="tab.badge" class="dossier-folder__tab-badge">{{ tab.badge }}</span>
                    </span>
                </Tab>
            </TabList>

            <TabPanels>
                <TabPanel value="identite">
                    <div class="dossier-folder__panel">
                        <div class="dossier-folder__grid dossier-folder__grid--split">
                            <div data-tour="patients-dossier.info-card">
                                <DossierPatientInfoCard
                                    :patient="patient"
                                    flat
                                    hide-actions
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
                                <ArchiveFilesSection :patient-id="patientId" :files="archiveFiles" @refresh="emit('refresh-archive')" />
                            </div>
                        </div>
                    </div>
                </TabPanel>

                <TabPanel value="clinique">
                    <div class="dossier-folder__panel" data-tour="patients-dossier.medical">
                        <ListePatientConsultations v-if="isReception" :consultations="consultations" :loading="consultationsLoading" />
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
                    <div class="dossier-folder__panel dossier-folder__panel--flush" data-tour="patients-dossier.finance">
                        <PatientActiviteFinancesSection
                            :rdvs="rdvs"
                            :paiements="paiements"
                            :factures="factures"
                            :consultations="consultations"
                            :services-cabinet="servicesCabinet"
                            :patient-id="patientId"
                            :patient-name="patientDisplayName"
                            :show-consultations="showConsultationsTab"
                            @refresh="emit('refresh')"
                        />
                    </div>
                </TabPanel>
            </TabPanels>
        </Tabs>
    </div>
</template>

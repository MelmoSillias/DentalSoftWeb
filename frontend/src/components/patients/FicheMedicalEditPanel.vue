<script setup>
import DevisForm from '@/components/consultations/DevisForm.vue';
import PastSessions from '@/components/consultations/PastSessions.vue';
import EntretienVerbalForm from '@/components/fiche-medicale/EntretienVerbalForm.vue';
import ExamensFicheForm from '@/components/fiche-medicale/ExamensFicheForm.vue';
import FicheBilansForm from '@/components/fiche-medicale/FicheBilansForm.vue';
import FicheDocumentsForm from '@/components/fiche-medicale/FicheDocumentsForm.vue';
import FichePlanTraitementForm from '@/components/fiche-medicale/FichePlanTraitementForm.vue';
import { useConsultationsForm } from '@/composables/useConsultationsForm';
import { defaultSoinList, normalizeSoinList } from '@/services/consultations';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import ProgressSpinner from 'primevue/progressspinner';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    ficheId: {
        type: Number,
        required: true
    }
});

const emit = defineEmits(['saved', 'dirty-change']);

const token = localStorage.getItem('token');
const ficheIdRef = ref(props.ficheId);
const consultIdRef = ref(null);
const mode = computed(() => 'continue');

const activeSection = ref(0);
const soinsList = ref(defaultSoinList);

const sections = [
    { key: 'entretien', title: 'Questionnaire médical', icon: 'pi pi-file-edit' },
    { key: 'examens', title: 'Examen', icon: 'pi pi-stethoscope' },
    { key: 'documents', title: 'Images et Docs', icon: 'pi pi-images' },
    { key: 'plan', title: 'Plan de traitement', icon: 'pi pi-sitemap' },
    { key: 'bilans', title: 'Bilan', icon: 'pi pi-clipboard' },
    { key: 'devis', title: 'Devis', icon: 'pi pi-file' },
    { key: 'seances', title: 'Séances passées', icon: 'pi pi-history' }
];

const {
    loading,
    data,
    saving,
    dirty,
    dirtySectionsList,
    documentsUploadProgress,
    loadData,
    watchSection,
    saveEntretienSection,
    saveExamensSection,
    saveBilansSection,
    savePlanTraitementSection,
    saveDocumentsSection,
    saveDevisSection
} = useConsultationsForm({ ficheId: ficheIdRef, consultId: consultIdRef, token, mode });

const ageNumber = computed(() => {
    const age = Number(data.patient?.age);
    if (Number.isFinite(age) && age > 0) return age;
    const dob = data.patient?.dateNaissance;
    if (!dob) return 0;
    const birth = new Date(dob);
    if (Number.isNaN(birth.getTime())) return 0;
    const today = new Date();
    let years = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) years -= 1;
    return Math.max(years, 0);
});

const saveAll = async () => {
    await Promise.all([
        saveEntretienSection(),
        saveExamensSection(),
        saveBilansSection(),
        savePlanTraitementSection(),
        saveDocumentsSection(),
        saveDevisSection()
    ]);
    emit('saved');
};

const saveCurrentSection = async () => {
    const handlers = [
        saveEntretienSection,
        saveExamensSection,
        saveDocumentsSection,
        savePlanTraitementSection,
        saveBilansSection,
        saveDevisSection,
        null
    ];
    const handler = handlers[activeSection.value];
    if (handler) {
        await handler();
        emit('saved');
    }
};

watch(dirtySectionsList, (list) => {
    emit('dirty-change', list.length > 0);
}, { immediate: true });

watch(() => props.ficheId, (next) => {
    ficheIdRef.value = next;
    loadData();
});

onMounted(async () => {
    watchSection(() => data.entretien, 'entretien', saveAll);
    watchSection(() => data.examens, 'examens', saveAll);
    watchSection(() => data.documents, 'documents', saveAll);
    watchSection(() => data.bilans, 'bilans', saveAll);
    watchSection(() => data.planTraitement, 'planTraitement', saveAll);
    watchSection(() => data.devis, 'devis', saveAll);

    try {
        const settings = await fetchPublicGeneralSettings();
        soinsList.value = normalizeSoinList(settings?.soins || defaultSoinList);
    } catch {
        soinsList.value = defaultSoinList;
    }

    await loadData();
});

defineExpose({
    hasDirtyChanges: () => dirtySectionsList.value.length > 0,
    saveAll
});
</script>

<template>
    <div class="min-h-[200px]">
        <div v-if="loading" class="flex items-center justify-center py-16">
            <ProgressSpinner style="width: 40px; height: 40px" />
        </div>

        <template v-else>
            <div class="sticky top-0 z-10 -mx-1 px-1 py-2 mb-4 bg-surface-0/95 dark:bg-surface-900/95 backdrop-blur-sm">
                <div class="flex flex-wrap gap-2 border-b border-surface-200/50 dark:border-surface-700/50 pb-4">
                    <button
                        v-for="(section, index) in sections"
                        :key="section.key"
                        type="button"
                        @click="activeSection = index"
                        :class="[
                            'px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300',
                            activeSection === index
                                ? 'bg-primary-500 text-white shadow-sm'
                                : 'text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-surface-100 hover:bg-surface-100 dark:hover:bg-surface-700'
                        ]"
                    >
                        <div class="flex items-center gap-2">
                            <i :class="section.icon"></i>
                            <span class="hidden sm:inline">{{ section.title }}</span>
                        </div>
                    </button>
                </div>
            </div>

            <div class="space-y-4">
                <EntretienVerbalForm
                    v-if="activeSection === 0"
                    v-model="data.entretien"
                    :saving="saving.entretien"
                    :patient-sex="data.patient?.sexe"
                    @save="saveCurrentSection"
                />

                <ExamensFicheForm
                    v-if="activeSection === 1"
                    v-model="data.examens"
                    :saving="saving.examens"
                    @save="saveCurrentSection"
                />

                <FicheDocumentsForm
                    v-if="activeSection === 2"
                    v-model="data.documents"
                    :saving="saving.documents"
                    :upload-progress="documentsUploadProgress"
                    @save="saveCurrentSection"
                />

                <FichePlanTraitementForm
                    v-if="activeSection === 3"
                    v-model="data.planTraitement"
                    :saving="saving.planTraitement"
                    @save="saveCurrentSection"
                />

                <FicheBilansForm
                    v-if="activeSection === 4"
                    v-model="data.bilans"
                    :saving="saving.bilans"
                    :patient-age="ageNumber"
                    @save="saveCurrentSection"
                />

                <DevisForm
                    v-if="activeSection === 5"
                    v-model="data.devis"
                    :saving="saving.devis"
                    :soins="soinsList"
                    @save="saveCurrentSection"
                />

                <div v-if="activeSection === 6">
                    <PastSessions :sessions="data.sessions" />
                    <p v-if="!data.sessions?.length" class="text-sm text-surface-500 dark:text-surface-400 mt-4 text-center">
                        Aucune séance précédente.
                    </p>
                </div>
            </div>
        </template>
    </div>
</template>

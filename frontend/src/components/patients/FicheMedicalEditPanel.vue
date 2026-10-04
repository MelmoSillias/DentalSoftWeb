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
    },
    tall: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['saved', 'dirty-change']);

const token = localStorage.getItem('token');
const ficheIdRef = ref(props.ficheId);
const consultIdRef = ref(null);
const mode = computed(() => 'continue');

const soinsList = ref(defaultSoinList);

const { loading, data, saving, dirtySectionsList, documentsUploadProgress, loadData, watchSection, saveEntretienSection, saveExamensSection, saveBilansSection, savePlanTraitementSection, saveDocumentsSection, saveDevisSection } =
    useConsultationsForm({ ficheId: ficheIdRef, consultId: consultIdRef, token, mode });

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
    await Promise.all([saveEntretienSection(), saveExamensSection(), saveBilansSection(), savePlanTraitementSection(), saveDocumentsSection(), saveDevisSection()]);
    emit('saved');
};

const saveAndNotify = async (saver) => {
    await saver();
    emit('saved');
};

watch(
    dirtySectionsList,
    (list) => {
        emit('dirty-change', list.length > 0);
    },
    { immediate: true }
);

watch(
    () => props.ficheId,
    (next) => {
        ficheIdRef.value = next;
        loadData();
    }
);

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
    <div class="medical-form-ui min-h-[200px]">
        <div v-if="loading" class="flex items-center justify-center py-16">
            <ProgressSpinner style="width: 40px; height: 40px" />
        </div>

        <template v-else>
            <div class="fiche-book" :class="{ 'fiche-book--tall': tall }">
                <div class="fiche-book__pages">
                    <EntretienVerbalForm v-model="data.entretien" layout="book" :saving="saving.entretien" :patient-sex="data.patient?.sexe" @save="() => saveAndNotify(saveEntretienSection)" />
                    <ExamensFicheForm v-model="data.examens" layout="book" :saving="saving.examens" @save="() => saveAndNotify(saveExamensSection)" />
                    <FicheDocumentsForm v-model="data.documents" layout="book" :saving="saving.documents" :upload-progress="documentsUploadProgress" @save="() => saveAndNotify(saveDocumentsSection)" />
                    <FichePlanTraitementForm v-model="data.planTraitement" layout="book" :saving="saving.planTraitement" @save="() => saveAndNotify(savePlanTraitementSection)" />
                    <FicheBilansForm v-model="data.bilans" layout="book" :saving="saving.bilans" :patient-age="ageNumber" @save="() => saveAndNotify(saveBilansSection)" />
                    <DevisForm v-model="data.devis" layout="book" :saving="saving.devis" :soins="soinsList" @save="() => saveAndNotify(saveDevisSection)" />
                    <PastSessions :sessions="data.sessions" layout="book" />
                </div>
            </div>
        </template>
    </div>
</template>

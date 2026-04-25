<script setup>
import FicheFormRenderer from '@/fiche-forms/FicheFormRenderer.vue';
import { DEFAULT_FORM_TEMPLATE_KEY } from '@/fiche-forms/shared/formTemplateResolver';
import { loadFicheMedicale } from '@/services/ficheMedicale';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import ProgressSpinner from 'primevue/progressspinner';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const token = localStorage.getItem('token');

const loadingTemplate = ref(false);
const resolvedTemplateKey = ref(DEFAULT_FORM_TEMPLATE_KEY);

const loadDefaultTemplateFromSettings = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        resolvedTemplateKey.value = settings?.defaultFormTemplate || DEFAULT_FORM_TEMPLATE_KEY;
    } catch (error) {
        console.error('Impossible de charger defaultFormTemplate, fallback v2.', error);
        resolvedTemplateKey.value = DEFAULT_FORM_TEMPLATE_KEY;
    }
};

const ficheId = computed(() => {
    const raw = Number(route.query.ficheId);
    return Number.isFinite(raw) && raw > 0 ? raw : null;
});

const resolveTemplate = async () => {
    if (!ficheId.value) {
        await loadDefaultTemplateFromSettings();
        return;
    }

    loadingTemplate.value = true;
    try {
        const fiche = await loadFicheMedicale(ficheId.value, token);
        resolvedTemplateKey.value = fiche?.formTemplateKey || DEFAULT_FORM_TEMPLATE_KEY;
    } catch (error) {
        console.error('Impossible de charger formTemplateKey, fallback settings.', error);
        await loadDefaultTemplateFromSettings();
    } finally {
        loadingTemplate.value = false;
    }
};

watch(
    () => [route.query.ficheId, route.query.id, route.query.mode],
    async () => {
        await resolveTemplate();
    },
    { immediate: true }
);
</script>

<template>
    <div v-if="loadingTemplate" class="flex min-h-[260px] items-center justify-center">
        <ProgressSpinner strokeWidth="4" />
    </div>
    <FicheFormRenderer v-else :form-template-key="resolvedTemplateKey" />
</template>

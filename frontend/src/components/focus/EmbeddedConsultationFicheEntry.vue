<script setup>
import EmbeddedConsultationFiche from '@/components/focus/EmbeddedConsultationFiche.vue';
import { DEFAULT_FORM_TEMPLATE_KEY, isV1Template } from '@/fiche-forms/shared/formTemplateResolver';
import { loadFicheMedicale } from '@/services/ficheMedicale';
import ProgressSpinner from 'primevue/progressspinner';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ConsultationForm from '@/views/consultations/ConsultationForm.vue';

const props = defineProps({
    consultationId: {
        type: Number,
        default: null
    },
    ficheId: {
        type: Number,
        default: null
    },
    mode: {
        type: String,
        default: 'continue'
    },
    readonly: {
        type: Boolean,
        default: false
    },
    choiceLabel: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['patient-loaded', 'closed']);

const token = localStorage.getItem('token');
const router = useRouter();
const route = useRoute();

const loadingTemplate = ref(false);
const formTemplateKey = ref(DEFAULT_FORM_TEMPLATE_KEY);
const isV1Form = computed(() => isV1Template(formTemplateKey.value));

const syncV1RouteQuery = async () => {
    if (!isV1Form.value) return;

    const nextQuery = {
        ...route.query,
        id: props.consultationId || undefined,
        ficheId: props.ficheId || undefined,
        mode: props.mode || 'continue'
    };

    const shouldReplace = String(route.query.id || '') !== String(nextQuery.id || '')
        || String(route.query.ficheId || '') !== String(nextQuery.ficheId || '')
        || String(route.query.mode || '') !== String(nextQuery.mode || '');

    if (!shouldReplace) return;

    await router.replace({
        name: route.name || 'focus-mode',
        query: nextQuery
    });
};

const resolveTemplateVersion = async () => {
    if (!props.ficheId) {
        formTemplateKey.value = DEFAULT_FORM_TEMPLATE_KEY;
        return;
    }

    loadingTemplate.value = true;
    try {
        const fiche = await loadFicheMedicale(props.ficheId, token);
        formTemplateKey.value = fiche?.formTemplateKey || DEFAULT_FORM_TEMPLATE_KEY;
    } catch (error) {
        console.error('Impossible de charger le template de fiche en mode focus.', error);
        formTemplateKey.value = DEFAULT_FORM_TEMPLATE_KEY;
    } finally {
        loadingTemplate.value = false;
    }
};

watch(
    () => [props.consultationId, props.ficheId, props.mode],
    async () => {
        await resolveTemplateVersion();
        await syncV1RouteQuery();
    },
    { immediate: true }
);
</script>

<template>
    <div v-if="loadingTemplate" class="flex min-h-[260px] items-center justify-center">
        <ProgressSpinner strokeWidth="4" />
    </div>

    <ConsultationForm v-else-if="isV1Form" />

    <EmbeddedConsultationFiche
        v-else
        :consultation-id="consultationId"
        :fiche-id="ficheId"
        :mode="mode"
        :readonly="readonly"
        :choice-label="choiceLabel"
        @patient-loaded="(payload) => emit('patient-loaded', payload)"
        @closed="emit('closed')"
    />
</template>

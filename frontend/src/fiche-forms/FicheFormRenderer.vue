<template>
    <component :is="resolvedComponent" />
</template>

<script setup>
import { computed } from 'vue';
import V1MainForm from '@/fiche-forms/v1/MainForm.vue';
import V2MainForm from '@/fiche-forms/v2/MainForm.vue';
import { resolveFormVersion } from '@/fiche-forms/shared/formTemplateResolver';

const props = defineProps({
    formTemplateKey: {
        type: String,
        default: null
    }
});

const resolvedComponent = computed(() => {
    const version = resolveFormVersion(props.formTemplateKey);
    return version === 'v1' ? V1MainForm : V2MainForm;
});
</script>

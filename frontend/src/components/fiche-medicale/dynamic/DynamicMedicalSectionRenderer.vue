<script setup>
import { computed } from 'vue';
import DynamicMedicalFieldRenderer from '@/components/fiche-medicale/dynamic/DynamicMedicalFieldRenderer.vue';
import { resolveMedicalSectionAdapter } from '@/components/fiche-medicale/dynamic/sectionAdapters';

const props = defineProps({
    section: {
        type: Object,
        required: true
    },
    modelValue: {
        type: [Object, Array, String, Number, Boolean],
        default: null
    },
    saving: {
        type: Boolean,
        default: false
    },
    patientAge: {
        type: Number,
        default: 0
    },
    soins: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['update:modelValue', 'save']);

const adapter = computed(() => resolveMedicalSectionAdapter(props.section?.componentKey || props.section?.code));
const adapterProps = computed(() => adapter.value?.buildProps?.({
    modelValue: props.modelValue,
    saving: props.saving,
    patientAge: props.patientAge,
    soins: props.soins
}) || {});
</script>

<template>
    <component
        :is="adapter.component"
        v-if="adapter"
        v-bind="adapterProps"
        @update:model-value="(value) => emit('update:modelValue', value)"
        @save="emit('save')"
    />

    <DynamicMedicalFieldRenderer
        v-else
        :fields="section.fields || []"
        :model-value="modelValue || {}"
        :saving="saving"
        @update:model-value="(value) => emit('update:modelValue', value)"
    />
</template>
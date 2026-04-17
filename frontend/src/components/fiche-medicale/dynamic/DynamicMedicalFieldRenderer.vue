<script setup>
import Checkbox from 'primevue/checkbox';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed } from 'vue';

const props = defineProps({
    fields: {
        type: Array,
        default: () => []
    },
    modelValue: {
        type: Object,
        default: () => ({})
    },
    saving: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue']);

const sortedFields = computed(() => [...(props.fields || [])].sort((left, right) => (left.sortOrder || 0) - (right.sortOrder || 0)));

const updateField = (fieldCode, value) => {
    emit('update:modelValue', {
        ...(props.modelValue || {}),
        [fieldCode]: value
    });
};

const parseJson = (value) => {
    if (!value) return null;
    try {
        return JSON.parse(value);
    } catch {
        return value;
    }
};
</script>

<template>
    <div class="grid grid-cols-1 gap-4">
        <div v-for="field in sortedFields" :key="field.code" class="space-y-2">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ field.label }}</label>

            <InputText
                v-if="field.fieldType === 'text'"
                :model-value="modelValue?.[field.code] ?? ''"
                :disabled="saving"
                class="w-full"
                @update:model-value="(value) => updateField(field.code, value)"
            />

            <Textarea
                v-else-if="field.fieldType === 'textarea'"
                :model-value="modelValue?.[field.code] ?? ''"
                :disabled="saving"
                rows="5"
                class="w-full"
                @update:model-value="(value) => updateField(field.code, value)"
            />

            <InputNumber
                v-else-if="field.fieldType === 'number'"
                :model-value="modelValue?.[field.code] ?? null"
                :disabled="saving"
                class="w-full"
                @update:model-value="(value) => updateField(field.code, value)"
            />

            <Select
                v-else-if="field.fieldType === 'select'"
                :model-value="modelValue?.[field.code] ?? null"
                :options="field.options?.choices || []"
                option-label="label"
                option-value="value"
                :disabled="saving"
                class="w-full"
                @update:model-value="(value) => updateField(field.code, value)"
            />

            <div v-else-if="field.fieldType === 'checkbox'" class="flex items-center gap-2">
                <Checkbox
                    binary
                    :model-value="Boolean(modelValue?.[field.code])"
                    :disabled="saving"
                    @update:model-value="(value) => updateField(field.code, value)"
                />
                <span class="text-sm text-surface-600 dark:text-surface-400">{{ field.label }}</span>
            </div>

            <Textarea
                v-else
                :model-value="JSON.stringify(modelValue?.[field.code] ?? null, null, 2)"
                :disabled="saving"
                rows="8"
                class="w-full font-mono text-xs"
                @update:model-value="(value) => updateField(field.code, parseJson(value))"
            />
        </div>
    </div>
</template>
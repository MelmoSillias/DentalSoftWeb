<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    loading: { type: Boolean, default: false }
});

const emit = defineEmits(['update:modelValue', 'save']);

const typeOptions = [
    { label: 'Personnel', value: 'Personnel' },
    { label: 'Familial', value: 'Familial' },
    { label: 'Médical', value: 'Médical' }
];

const form = reactive({
    type: '',
    description: ''
});

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
});

const resetForm = () => {
    form.type = '';
    form.description = '';
};

watch(
    () => props.modelValue,
    (val) => {
        if (val) resetForm();
    }
);

const submit = () => {
    emit('save', {
        type: form.type,
        description: form.description
    });
};
</script>

<template>
    <Dialog v-model:visible="visible" modal header="Ajouter un antécédent" :style="{ width: '32rem' }" :pt="{
        root: 'rounded-2xl',
        header: 'px-6 py-4 border-b border-surface-200 dark:border-surface-700',
        content: 'p-6'
    }">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label class="font-semibold">Type</label>
                <Select v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value"
                    placeholder="Sélectionner" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-semibold">Description</label>
                <Textarea v-model="form.description" rows="3" autoResize placeholder="Description" />
            </div>
        </div>
        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Annuler" severity="secondary" outlined @click="visible = false" />
                <Button label="Enregistrer" icon="pi pi-check" :loading="loading" @click="submit" />
            </div>
        </template>
    </Dialog>
</template>

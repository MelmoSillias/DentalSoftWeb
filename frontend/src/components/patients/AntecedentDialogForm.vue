<script setup>
import Button from 'primevue/button';
import AutoComplete from 'primevue/autocomplete';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    typeOptions: {
        type: Array,
        default: () => ['Personnel', 'Familial', 'Médical']
    }
});

const emit = defineEmits(['update:modelValue', 'save']);

const typeSuggestions = ref([]);

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

const searchTypeOptions = (event) => {
    const query = String(event?.query || '').toLowerCase().trim();
    typeSuggestions.value = query
        ? props.typeOptions.filter((item) => String(item).toLowerCase().includes(query))
        : props.typeOptions;
};

const submit = () => {
    emit('save', {
        type: form.type,
        description: form.description
    });
};
</script>

<template>
    <Dialog v-model:visible="visible" modal header="Ajouter un antécédent" :style="{ width: '32rem' }" :pt="{
        root: 'rounded-2xl overflow-hidden',
        header: 'px-6 py-4 border-b border-surface-200 dark:border-surface-700',
        content: 'p-6'
    }">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label class="font-semibold">Type</label>
                <AutoComplete
                    v-model="form.type"
                    :suggestions="typeSuggestions"
                    dropdown
                    placeholder="Saisir ou sélectionner"
                    @complete="searchTypeOptions"
                />
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

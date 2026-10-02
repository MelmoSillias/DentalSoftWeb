<script setup>
import Button from 'primevue/button';
import AutoComplete from 'primevue/autocomplete';
import AppDialog from '@/components/layout/AppDialog.vue';
import Textarea from 'primevue/textarea';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    typeOptions: {
        type: Array,
        default: () => ['Médicamenteuses', 'Alimentaires', 'Environnementales', 'Autres']
    }
});

const emit = defineEmits(['update:modelValue', 'save']);

const typeSuggestions = ref([]);

const form = reactive({
    libelle: '',
    description: ''
});

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
});

const resetForm = () => {
    form.libelle = '';
    form.description = '';
};

watch(
    () => props.modelValue,
    (val) => {
        if (val) resetForm();
    }
);

const searchTypeOptions = (event) => {
    const query = String(event?.query || '')
        .toLowerCase()
        .trim();
    typeSuggestions.value = query ? props.typeOptions.filter((item) => String(item).toLowerCase().includes(query)) : props.typeOptions;
};

const submit = () => {
    emit('save', {
        libelle: form.libelle,
        description: form.description
    });
};
</script>

<template>
    <AppDialog
        v-model:visible="visible"
        title="Ajouter une allergie"
        icon="pi pi-exclamation-circle"
        icon-tone="warning"
        size="sm"
        :loading="loading"
        cancel-label="Annuler"
        confirm-label="Enregistrer"
        confirm-icon="pi pi-check"
        @cancel="visible = false"
        @confirm="submit"
    >
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label class="font-semibold">Type</label>
                <AutoComplete v-model="form.libelle" :suggestions="typeSuggestions" dropdown placeholder="Saisir ou sélectionner" @complete="searchTypeOptions" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-semibold">Description</label>
                <Textarea v-model="form.description" rows="3" autoResize placeholder="Description" />
            </div>
        </div>
    </AppDialog>
</template>

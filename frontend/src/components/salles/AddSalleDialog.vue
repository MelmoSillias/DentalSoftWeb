<script setup>
import { ref, watch } from 'vue';
import AppDialog from '@/components/layout/AppDialog.vue';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';

const props = defineProps({
    visible: Boolean,
    loading: Boolean,
    tourTarget: {
        type: String,
        default: null
    }
});
const emit = defineEmits(['update:visible', 'submit']);

const form = ref({
    nom: '',
    description: ''
});

watch(
    () => props.visible,
    (value) => {
        if (value) {
            form.value = { nom: '', description: '' };
        }
    }
);

const close = () => emit('update:visible', false);

const submit = (event) => {
    emit('submit', { payload: { ...form.value }, event });
};
</script>

<template>
    <AppDialog
        :visible="visible"
        title="Ajouter une salle"
        icon="pi pi-building"
        icon-tone="primary"
        size="md"
        :loading="loading"
        cancel-label="Annuler"
        confirm-label="Ajouter"
        confirm-icon="pi pi-check"
        :data-tour="props.tourTarget || undefined"
        @update:visible="close"
        @cancel="close"
        @confirm="submit"
    >
        <form class="flex flex-col gap-4" @submit.prevent>
            <div class="grid grid-cols-1 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Nom <span class="text-red-500">*</span></label>
                    <InputText v-model="form.nom" placeholder="Ex: Salle A" required class="w-full border border-gray-200 rounded-md px-3 py-2 focus:ring-2 focus:ring-indigo-400" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Description</label>
                    <Textarea v-model="form.description" autoResize rows="3" placeholder="Description ou usage" class="w-full border border-gray-200 rounded-md px-3 py-2 focus:ring-2 focus:ring-indigo-400" />
                </div>
            </div>
        </form>
    </AppDialog>
</template>

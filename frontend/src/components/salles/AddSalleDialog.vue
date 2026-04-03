<script setup>
import { ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';

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
    <Dialog :visible="visible" modal header="Ajouter une salle" :style="{ width: '34rem' }" @update:visible="close">
        <form class="flex flex-col gap-4" :data-tour="props.tourTarget || null" @submit.prevent>
            <div class="grid grid-cols-1 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Nom</label>
                    <InputText v-model="form.nom" placeholder="Ex: Salle A" required
                        class="w-full border border-gray-200 rounded-md px-3 py-2 focus:ring-2 focus:ring-indigo-400" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Description</label>
                    <Textarea v-model="form.description" autoResize rows="3" placeholder="Description ou usage"
                        class="w-full border border-gray-200 rounded-md px-3 py-2 focus:ring-2 focus:ring-indigo-400" />
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <Button type="button" class="bg-gray-200 text-gray-700 hover:bg-gray-300 rounded-md px-4 py-2"
                    @click="close" label="Annuler" />
                <Button type="button" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-md px-4 py-2"
                    icon="pi pi-check" :loading="loading" @click="submit" label="Ajouter" />
            </div>
        </form>
    </Dialog>
</template>

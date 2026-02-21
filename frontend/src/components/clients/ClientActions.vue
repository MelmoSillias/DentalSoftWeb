<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import Button from 'primevue/button';
import ProjectCreateDialog from '@/components/projects/ProjectCreateDialog.vue';

const props = defineProps({
    client: { type: Object, required: true },
    compact: { type: Boolean, default: false }
});

const emit = defineEmits(['project-created']);

const router = useRouter();
const projectDialogVisible = ref(false);

const clientName = computed(() => props.client?.nom || props.client?.name || '');
const clientAddress = computed(() => props.client?.adresse || props.client?.address || '');

const goToQuote = () => {
    if (!props.client?.id) return;
    router.push({
        name: 'facturation-formulaire',
        query: {
            clientId: props.client.id,
            clientName: clientName.value,
            address: clientAddress.value
        }
    });
};

const openProjectModal = () => {
    projectDialogVisible.value = true;
};

const handleProjectCreated = () => {
    emit('project-created');
};
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Button :label="compact ? 'Devis' : 'Créer un devis'" icon="pi pi-file-edit" size="small" :disabled="!props.client?.id" @click="goToQuote" />
        <Button :label="compact ? 'Projet' : 'Nouveau projet'" icon="pi pi-plus" size="small" severity="success" :disabled="!props.client?.id" @click="openProjectModal" />
    </div>
    <ProjectCreateDialog v-model:visible="projectDialogVisible" :client-id="props.client?.id" @created="handleProjectCreated" />
</template>

<style scoped>
.flex {
    align-items: center;
}
</style>

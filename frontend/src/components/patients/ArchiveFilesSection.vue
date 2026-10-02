<script setup>
import { logAppError } from '@/utils/appLogger';
import { ref } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import AppDialog from '@/components/layout/AppDialog.vue';
import InputText from 'primevue/inputtext';
import FileUpload from 'primevue/fileupload';
import { addArchiveFile, deleteArchiveFile } from '@/services/patients';
import { filePrefix } from '@/config';

const props = defineProps({
    patientId: { type: Number, required: true },
    files: { type: Array, default: () => [] }
});

const emit = defineEmits(['refresh']);

const confirm = useConfirm();
const toast = useToast();
const loading = ref(false);
const showAddDialog = ref(false);
const newFileName = ref('');
const selectedFile = ref(null);
const uploading = ref(false);

const openAddDialog = () => {
    newFileName.value = '';
    selectedFile.value = null;
    showAddDialog.value = true;
};

const closeAddDialog = () => {
    showAddDialog.value = false;
};

const onFileSelect = (event) => {
    selectedFile.value = event.files[0];
};

const submitAdd = async () => {
    if (!props.patientId || !newFileName.value || !selectedFile.value) return;
    uploading.value = true;
    const formData = new FormData();
    formData.append('name', newFileName.value);
    formData.append('file', selectedFile.value);
    try {
        await addArchiveFile(props.patientId, formData, localStorage.getItem('token'));
        toast.add({ severity: 'success', summary: 'Ajouté', detail: 'Fichier ajouté', life: 2500 });
        emit('refresh');
        closeAddDialog();
    } catch (err) {
        logAppError('ArchiveFilesSection', err);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'ajouter le fichier", life: 3000 });
    } finally {
        uploading.value = false;
    }
};

const openFile = (url) => {
    window.open(filePrefix + url, '_blank');
};

const downloadFile = (url, filename) => {
    const link = document.createElement('a');
    link.href = filePrefix + url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const confirmDelete = (file) => {
    confirm.require({
        group: 'archive',
        header: 'Supprimer le fichier',
        message: `Voulez-vous vraiment supprimer "${file.nom}" ?`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Oui',
        rejectLabel: 'Non',
        accept: async () => {
            loading.value = true;
            try {
                await deleteArchiveFile(props.patientId, file.url, localStorage.getItem('token'));
                toast.add({ severity: 'success', summary: 'Supprimé', detail: 'Fichier supprimé', life: 2500 });
                emit('refresh');
            } catch (err) {
                logAppError('ArchiveFilesSection', err);
                toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression impossible', life: 3000 });
            } finally {
                loading.value = false;
            }
        }
    });
};

const isPdf = (url) => url.toLowerCase().endsWith('.pdf');
const isImage = (url) => /\.(jpg|jpeg|png|gif|webp)$/i.test(url);
</script>

<template>
    <div class="page-section">
        <div class="page-section__header" data-tour="patients-dossier.archive-toolbar">
            <div class="page-section__header-main">
                <h3 class="page-section__title">Fichiers administratifs</h3>
            </div>
            <div class="page-section__header-actions">
                <Button icon="pi pi-plus" label="Ajouter" size="small" outlined @click="openAddDialog" />
            </div>
        </div>

        <DataTable :value="files" class="p-3" responsiveLayout="scroll" dataKey="url" :loading="loading" data-tour="patients-dossier.archive-table">
            <Column field="nom" header="Nom du fichier">
                <template #body="{ data }">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-file-pdf" v-if="isPdf(data.url)"></i>
                        <i class="pi pi-image" v-else-if="isImage(data.url)"></i>
                        <i class="pi pi-file" v-else></i>
                        <span>{{ data.nom }}</span>
                    </div>
                </template>
            </Column>
            <Column header="Actions" :exportable="false" style="width: 120px">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button icon="pi pi-eye" text rounded severity="info" @click="openFile(data.url)" />
                        <Button icon="pi pi-download" text rounded severity="success" @click="downloadFile(data.url, data.nom)" />
                        <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmDelete(data)" />
                    </div>
                </template>
            </Column>
            <template #empty>
                <div class="text-center py-6" style="font-size: var(--page-section-subtitle-size); color: var(--text-color-secondary)">Aucun fichier administratif</div>
            </template>
        </DataTable>
    </div>

    <AppDialog
        v-model:visible="showAddDialog"
        title="Ajouter un fichier"
        icon="pi pi-file"
        icon-tone="primary"
        size="sm"
        :loading="uploading"
        :confirm-disabled="!newFileName || !selectedFile"
        cancel-label="Annuler"
        confirm-label="Ajouter"
        @cancel="closeAddDialog"
        @confirm="submitAdd"
    >
        <div class="flex flex-col gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Nom du fichier</label>
                <InputText v-model="newFileName" class="w-full" placeholder="ex: Bilan sanguin 2025" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Fichier</label>
                <FileUpload mode="basic" chooseLabel="Choisir" accept=".pdf,.jpg,.png,.doc,.docx" @select="onFileSelect" />
            </div>
            <div v-if="selectedFile" class="text-sm text-surface-600">Fichier sélectionné : {{ selectedFile.name }}</div>
        </div>
    </AppDialog>

    <ConfirmDialog group="archive" />
</template>

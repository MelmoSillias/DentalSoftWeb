<template>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm">
        <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                <i class="pi pi-folder-open text-primary-500"></i>
                Fichiers administratifs
            </h3>
            <Button icon="pi pi-plus" label="Ajouter" size="small" @click="openAddDialog" />
        </div>

        <DataTable :value="files" class="p-4" responsiveLayout="scroll" dataKey="url" :loading="loading">
            <Column field="nom" header="Nom du fichier">
                <template #body="{ data }">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-file-pdf" v-if="isPdf(data.url)"></i>
                        <i class="pi pi-file-image" v-else-if="isImage(data.url)"></i>
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
                <div class="text-center py-6 text-surface-500">Aucun fichier administratif</div>
            </template>
        </DataTable>
    </div>

    <Dialog v-model:visible="showAddDialog" modal header="Ajouter un fichier" :style="{ width: '30rem' }" :pt="{ root: 'rounded-2xl' }">
        <div class="flex flex-col gap-4 p-2">
            <div>
                <label class="block text-sm font-medium mb-1">Nom du fichier</label>
                <InputText v-model="newFileName" class="w-full" placeholder="ex: Bilan sanguin 2025" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Fichier</label>
                <FileUpload mode="basic" chooseLabel="Choisir" accept=".pdf,.jpg,.png,.doc,.docx" @select="onFileSelect" />
            </div>
            <div v-if="selectedFile" class="text-sm text-surface-600">
                Fichier sélectionné : {{ selectedFile.name }}
            </div>
        </div>
        <template #footer>
            <Button label="Annuler" severity="secondary" outlined @click="closeAddDialog" />
            <Button label="Ajouter" @click="submitAdd" :loading="uploading" :disabled="!newFileName || !selectedFile" />
        </template>
    </Dialog>

    <ConfirmDialog group="archive" />
</template>

<script setup>
import { ref } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
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
        console.error(err);
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
                console.error(err);
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

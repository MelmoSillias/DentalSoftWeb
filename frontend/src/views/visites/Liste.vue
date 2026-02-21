<script setup>
import { apiPrefix } from '@/config';
import { createVisit, deleteVisit, fetchAgences, fetchVisit, fetchVisits, updateVisit } from '@/services/olders/visiteService';
import { useAuthStore } from '@/stores/auth';
import Button from 'primevue/button';
import Column from 'primevue/column';
import ConfirmPopup from 'primevue/confirmpopup';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, reactive, ref } from 'vue';

const toast = useToast();
const confirm = useConfirm();
const auth = useAuthStore();

const isAdmin = computed(() => auth.user?.roles?.includes('ROLE_ADMIN'));
const isSecretaire = computed(() => auth.user?.roles?.includes('ROLE_SECRETAIRE'));

const visits = ref([]);
const loading = ref(false);
const agences = ref([]);
const filters = reactive({ agenceId: null, from: null, to: null });

const createDialogVisible = ref(false);
const editDialogVisible = ref(false);
const submitting = ref(false);
const currentVisitId = ref(null);

const form = reactive({
    dateDeVisite: null,
    nomPrenoms: '',
    telephone: '',
    motif: ''
});

const fileRows = ref([]);

const fileBaseUrl = apiPrefix.replace(/\/api\/?$/, '');

const statusSeverity = {
    saved: 'success',
    updated: 'warning',
    deleted: 'danger',
    new: 'info'
};

const statusLabel = {
    saved: 'Sauvegardé',
    updated: 'Modifié',
    deleted: 'Supprimé',
    new: 'Nouveau'
};

const resetForm = () => {
    Object.assign(form, {
        dateDeVisite: null,
        nomPrenoms: '',
        telephone: '',
        motif: ''
    });
    fileRows.value = [];
    currentVisitId.value = null;
};

const formatDateParam = (value) => {
    if (!value) return null;
    const d = value instanceof Date ? value : new Date(value);
    return Number.isNaN(d.getTime()) ? null : d.toISOString().slice(0, 10);
};

const loadAgences = async () => {
    try {
        agences.value = await fetchAgences();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Agences', detail: 'Chargement des agences impossible', life: 3500 });
    }
};

const loadVisits = async () => {
    loading.value = true;
    try {
        const params = {
            agenceId: filters.agenceId || undefined,
            from: formatDateParam(filters.from),
            to: formatDateParam(filters.to)
        };
        visits.value = await fetchVisits(params);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Registre', detail: 'Impossible de récupérer les visites', life: 3500 });
    } finally {
        loading.value = false;
    }
};

const newFileRow = (data = {}) => ({
    id: data.id ?? null,
    designation: data.designation ?? '',
    lien: data.lien ?? '',
    emplacement: data.emplacement ?? '',
    status: data.status ?? (data.id ? 'saved' : 'new'),
    file: data.file ?? null,
    fileField: data.fileField ?? `file_${Math.random().toString(36).slice(2, 9)}`
});

const addFileRow = () => {
    fileRows.value.push(newFileRow());
};

const markDeleteFile = (row) => {
    row.status = 'deleted';
};

const restoreFile = (row) => {
    if (row.status === 'deleted') {
        row.status = row.id ? 'saved' : 'new';
    }
};

const onFileSelected = (event, row) => {
    const file = event?.target?.files?.[0];
    if (!file) return;
    row.file = file;
    row.designation = row.designation || file.name;
    if (row.status === 'saved') {
        row.status = 'updated';
    }
};

const onEmplacementChange = (row) => {
    if (row.status === 'saved') {
        row.status = 'updated';
    }
};

const mapAction = (row) => {
    if (row.status === 'deleted') return 'delete';
    if (row.status === 'updated') return 'update';
    if (row.status === 'new') return 'add';
    return 'keep';
};

const validateForm = () => {
    if (!form.dateDeVisite || !form.nomPrenoms.trim() || !form.telephone.trim()) {
        toast.add({ severity: 'warn', summary: 'Formulaire', detail: 'Complétez la date, le nom & prénoms et le téléphone', life: 3000 });
        return false;
    }

    for (const row of fileRows.value) {
        if (row.status === 'deleted') continue;
        if (!row.emplacement || !row.emplacement.trim()) {
            toast.add({ severity: 'warn', summary: 'Fichiers', detail: 'Chaque fichier doit avoir un emplacement', life: 3000 });
            return false;
        }
        if (!row.designation || !row.designation.trim()) {
            toast.add({ severity: 'warn', summary: 'Fichiers', detail: 'Renseignez une désignation pour chaque entrée', life: 3000 });
            return false;
        }
    }

    return true;
};

const buildPayload = () => {
    const uploads = [];
    const fichiersJoints = fileRows.value.map((row) => {
        const action = mapAction(row);
        if (row.file && action !== 'delete') {
            uploads.push({ field: row.fileField, file: row.file });
        }

        return {
            id: row.id ?? undefined,
            designation: row.designation || row.file?.name || 'Document',
            lien: row.lien || null,
            emplacement: row.emplacement,
            action,
            fileField: row.file && action !== 'delete' ? row.fileField : null
        };
    });

    const payload = {
        dateDeVisite: formatDateParam(form.dateDeVisite),
        nomPrenoms: form.nomPrenoms.trim(),
        telephone: form.telephone.trim(),
        motif: form.motif.trim(),
        fichiersJoints
    };

    return { payload, uploads };
};

const submitCreate = async () => {
    if (!validateForm()) return;
    submitting.value = true;
    try {
        const { payload, uploads } = buildPayload();
        await createVisit(payload, uploads);
        toast.add({ severity: 'success', summary: 'Registre', detail: 'Visite ajoutée', life: 2500 });
        createDialogVisible.value = false;
        await loadVisits();
    } catch (e) {
        const message = e?.response?.data?.error || 'Création impossible';
        toast.add({ severity: 'error', summary: 'Registre', detail: message, life: 4000 });
    } finally {
        submitting.value = false;
    }
};

const submitUpdate = async () => {
    if (!validateForm()) return;
    submitting.value = true;
    try {
        const { payload, uploads } = buildPayload();
        await updateVisit(currentVisitId.value, payload, uploads);
        toast.add({ severity: 'success', summary: 'Registre', detail: 'Visite mise à jour', life: 2500 });
        editDialogVisible.value = false;
        await loadVisits();
    } catch (e) {
        const message = e?.response?.data?.error || 'Mise à jour impossible';
        toast.add({ severity: 'error', summary: 'Registre', detail: message, life: 4000 });
    } finally {
        submitting.value = false;
    }
};

const openCreateDialog = () => {
    resetForm();
    createDialogVisible.value = true;
};

const openEditDialog = async (visit) => {
    resetForm();
    editDialogVisible.value = true;
    try {
        const full = await fetchVisit(visit.id);
        currentVisitId.value = full.id;
        form.dateDeVisite = full.dateDeVisite ? new Date(full.dateDeVisite) : null;
        form.nomPrenoms = full.nomPrenoms || '';
        form.telephone = full.telephone || '';
        form.motif = full.motif || '';
        fileRows.value = (full.fichiersJoints || []).map((f) => newFileRow({ ...f, status: 'saved' }));
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Registre', detail: 'Impossible de charger la visite', life: 3500 });
        editDialogVisible.value = false;
    }
};

const buildFileUrl = (lien) => {
    if (!lien) return null;
    if (/^https?:\/\//i.test(lien)) return lien;
    if (lien.startsWith('/')) return `${fileBaseUrl}${lien}`;
    return `${fileBaseUrl}/${lien}`;
};

const openFile = (row) => {
    const url = buildFileUrl(row?.lien);
    if (!url) {
        toast.add({ severity: 'error', summary: 'Téléchargement', detail: 'Lien de fichier indisponible', life: 3000 });
        return;
    }
    const win = window.open(url, '_blank');
    if (!win) {
        toast.add({ severity: 'warn', summary: 'Téléchargement', detail: 'Téléchargement bloqué par le navigateur', life: 3000 });
    }
};

const confirmDelete = (event, visit) => {
    confirm.require({
        target: event.currentTarget,
        message: 'Supprimer cette visite ?',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => removeVisit(visit.id)
    });
};

const removeVisit = async (id) => {
    try {
        await deleteVisit(id);
        toast.add({ severity: 'success', summary: 'Registre', detail: 'Visite supprimée', life: 2500 });
        await loadVisits();
    } catch (e) {
        const message = e?.response?.data?.error || 'Suppression impossible';
        toast.add({ severity: 'error', summary: 'Registre', detail: message, life: 4000 });
    }
};

onMounted(async () => {
    await Promise.all([loadAgences(), loadVisits()]);
});

const isFilesSectionPristine = computed(() => fileRows.value.length === 0);
</script>

<template>
    <div class="page-shell">
        <div class="hero">
            <div>
                <p class="eyebrow">Registre</p>
                <h1>Registre de visite</h1>
                <p class="muted">Suivi des visiteurs avec pièces scannées</p>
            </div>
            <div class="hero-actions" v-if="isAdmin || isSecretaire">
                <Button label="Ajouter une visite" icon="pi pi-plus" @click="openCreateDialog" />
            </div>
        </div>

        <div class="card filters">
            <div class="filter-item">
                <label>Agence</label>
                <select v-model="filters.agenceId" class="p-inputtext">
                    <option :value="null">Toutes</option>
                    <option v-for="ag in agences" :key="ag.id" :value="ag.id">{{ ag.designation }} - {{ ag.localite }}
                    </option>
                </select>
            </div>
            <div class="filter-item">
                <label>Date de début</label>
                <DatePicker v-model="filters.from" dateFormat="yy-mm-dd" showIcon class="w-full"
                    @update:model-value="loadVisits" />
            </div>
            <div class="filter-item">
                <label>Date de fin</label>
                <DatePicker v-model="filters.to" dateFormat="yy-mm-dd" showIcon class="w-full"
                    @update:model-value="loadVisits" />
            </div>
            <div class="filter-actions">
                <Button label="Filtrer" icon="pi pi-filter" @click="loadVisits" />
                <Button label="Réinitialiser" icon="pi pi-refresh" severity="secondary" outlined
                    @click="() => { filters.agenceId = null; filters.from = null; filters.to = null; loadVisits(); }" />
            </div>
        </div>

        <div class="card">
            <DataTable :value="visits" :loading="loading" dataKey="id" class="visit-table" paginator :rows="10"
                responsiveLayout="scroll">
                <Column field="dateDeVisite" header="Date" sortable style="min-width: 130px"></Column>
                <Column header="Nom & Prénoms" style="min-width: 220px">
                    <template #body="{ data }">
                        <div class="cell-main">{{ data.nomPrenoms }}</div>
                        <div class="cell-sub">Ajouté le {{ data.dateCreation }}</div>
                    </template>
                </Column>
                <Column field="telephone" header="Téléphone" style="min-width: 140px"></Column>
                <Column header="Motif" style="min-width: 240px">
                    <template #body="{ data }">
                        <span class="muted">{{ data.motif }}</span>
                    </template>
                </Column>
                <Column header="Actions" style="min-width: 160px" bodyClass="text-right">
                    <template #body="{ data }">
                        <div class="row-actions">
                            <Button icon="pi pi-eye" label="Aperçu" size="small" text @click="openEditDialog(data)" />
                            <Button v-if="isAdmin" icon="pi pi-trash" label="Supprimer" size="small" text
                                severity="danger" @click="(event) => confirmDelete(event, data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog v-model:visible="createDialogVisible" header="Ajouter une visite" modal class="w-9/12"
            :closable="!submitting">
            <div class="dialog-content">
                <div class="form-grid">
                    <div>
                        <label>Date de visite</label>
                        <DatePicker v-model="form.dateDeVisite" dateFormat="yy-mm-dd" showIcon class="w-full" />
                    </div>
                    <div class="col-span-2">
                        <label>Nom & Prénoms</label>
                        <InputText v-model="form.nomPrenoms" class="w-full" placeholder="Nom & Prénoms" />
                    </div>
                    <div>
                        <label>Téléphone</label>
                        <InputText v-model="form.telephone" class="w-full" placeholder="Téléphone" />
                    </div>
                    <div class="col-span-2">
                        <label>Motif</label>
                        <Textarea v-model="form.motif" autoResize rows="3" class="w-full"
                            placeholder="Motif de la visite" />
                    </div>
                </div>

                <div class="files-panel">
                    <div class="files-header">
                        <div>
                            <h4>Fichiers joints</h4>
                            <p class="muted">Chaque fichier doit avoir un emplacement non vide.</p>
                        </div>
                        <Button icon="pi pi-plus" label="Ajouter un fichier" outlined size="small"
                            @click="addFileRow" />
                    </div>

                    <div v-if="isFilesSectionPristine" class="muted">Aucun fichier ajouté pour l'instant.</div>

                    <div v-else class="file-list">
                        <div v-for="row in fileRows" :key="row.fileField" class="file-row" :class="row.status">
                            <div class="file-row-main">
                                <div class="file-row-line">
                                    <label>Désignation</label>
                                    <InputText v-model="row.designation" class="w-full" placeholder="Désignation"
                                        @input="onEmplacementChange(row)" />
                                </div>
                                <div class="file-row-line">
                                    <label>Emplacement physique</label>
                                    <InputText v-model="row.emplacement" class="w-full"
                                        placeholder="Lieu de stockage (bureau)" @input="onEmplacementChange(row)" />
                                </div>
                                <div class="file-row-line file-upload">
                                    <input type="file" :id="row.fileField" class="file-input"
                                        @change="(e) => onFileSelected(e, row)" />
                                    <label class="file-label" :for="row.fileField">
                                        <i class="pi pi-upload" />
                                        <span>{{ row.file?.name || 'Sélectionner un fichier' }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="file-row-meta">
                                <Tag :value="statusLabel[row.status]" :severity="statusSeverity[row.status]" />
                                <div class="file-row-actions">
                                    <Button icon="pi pi-download" text size="small" :disabled="!row.lien"
                                        title="Télécharger" @click="openFile(row)" />
                                    <Button v-if="row.status !== 'deleted'" icon="pi pi-times" severity="danger" text
                                        @click="markDeleteFile(row)" title="Marquer pour suppression" />
                                    <Button v-else icon="pi pi-undo" severity="secondary" text @click="restoreFile(row)"
                                        title="Annuler la suppression" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button label="Annuler" icon="pi pi-times" text :disabled="submitting"
                    @click="createDialogVisible = false" />
                <Button label="Enregistrer" icon="pi pi-check" :loading="submitting" @click="submitCreate" />
            </template>
        </Dialog>

        <Dialog v-model:visible="editDialogVisible" header="Aperçu / Édition" modal class="w-9/12"
            :closable="!submitting">
            <div class="dialog-content">
                <Message severity="info" :closable="false" class="mb-3">Les modifications de fichiers s'appliquent après
                    sauvegarde.</Message>
                <div class="form-grid">
                    <div>
                        <label>Date de visite</label>
                        <DatePicker v-model="form.dateDeVisite" dateFormat="yy-mm-dd" showIcon class="w-full" />
                    </div>
                    <div>
                        <label>Nom & Prénoms</label>
                        <InputText v-model="form.nomPrenoms" class="w-full" />
                    </div>
                    <div>
                        <label>Téléphone</label>
                        <InputText v-model="form.telephone" class="w-full" />
                    </div>
                    <div class="col-span-2">
                        <label>Motif</label>
                        <Textarea v-model="form.motif" autoResize rows="3" class="w-full" />
                    </div>
                </div>

                <div class="files-panel">
                    <div class="files-header">
                        <div>
                            <h4>Fichiers joints</h4>
                            <p class="muted">Ajoutez, modifiez l'emplacement ou marquez pour suppression.</p>
                        </div>
                        <Button icon="pi pi-plus" label="Ajouter un fichier" outlined size="small"
                            @click="addFileRow" />
                    </div>
                    <div v-if="isFilesSectionPristine" class="muted">Aucun fichier listé.</div>
                    <div v-else class="file-list">
                        <div v-for="row in fileRows" :key="row.fileField" class="file-row" :class="row.status">
                            <div class="file-row-main">
                                <div class="file-row-line">
                                    <label>Désignation</label>
                                    <InputText v-model="row.designation" class="w-full"
                                        @input="onEmplacementChange(row)" />
                                </div>
                                <div class="file-row-line">
                                    <label>Emplacement physique</label>
                                    <InputText v-model="row.emplacement" class="w-full"
                                        @input="onEmplacementChange(row)" />
                                </div>
                                <div class="file-row-line file-upload">
                                    <input type="file" :id="row.fileField" class="file-input"
                                        @change="(e) => onFileSelected(e, row)" />
                                    <label class="file-label" :for="row.fileField">
                                        <i class="pi pi-upload" />
                                        <span>{{ row.file?.name || 'Remplacer le fichier' }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="file-row-meta">
                                <Tag :value="statusLabel[row.status]" :severity="statusSeverity[row.status]" />
                                <div class="file-row-actions">
                                    <Button icon="pi pi-download" text size="small" :disabled="!row.lien"
                                        title="Télécharger" @click="openFile(row)" />
                                    <Button v-if="row.status !== 'deleted'" icon="pi pi-times" severity="danger" text
                                        @click="markDeleteFile(row)" title="Marquer pour suppression" />
                                    <Button v-else icon="pi pi-undo" severity="secondary" text @click="restoreFile(row)"
                                        title="Annuler la suppression" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button label="Fermer" icon="pi pi-times" text :disabled="submitting"
                    @click="editDialogVisible = false" />
                <Button label="Sauvegarder" icon="pi pi-check" :loading="submitting" @click="submitUpdate" />
            </template>
        </Dialog>

        <ConfirmPopup />
        <Toast />
    </div>
</template>

<style scoped>
.page-shell {
    padding: 1.5rem;
    background: var(--surface-ground);
    min-height: 100vh;
}

.hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
    margin-bottom: 1.25rem;
}

.hero h1 {
    font-size: 1.6rem;
    margin: 0.15rem 0;
}

.hero .muted {
    color: #6b7280;
    margin: 0;
}

.hero .eyebrow {
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-size: 0.8rem;
    color: #94a3b8;
    margin: 0;
}

.hero-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.card {
    border-radius: 14px;
    padding: 1.25rem;
    background: var(--surface-card);
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.06);
    border: 1px solid var(--surface-border);
    margin-bottom: 1rem;
}

.filters {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 0.75rem;
    align-items: flex-end;
}

.filters label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.3rem;
    color: var(--text-color);
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.filter-item select.p-inputtext {
    width: 100%;
    padding: 0.65rem;
    border-radius: 10px;
    border: 1px solid var(--surface-border);
    background: var(--surface-section);
}

.visit-table :deep(.p-datatable-thead > tr > th) {
    background: var(--surface-section);
    color: var(--text-color);
    font-weight: 600;
}

.visit-table :deep(.p-datatable-tbody > tr:hover) {
    background: var(--surface-hover);
}

.cell-main {
    font-weight: 600;
    color: var(--text-color);
}

.cell-sub {
    font-size: 0.85rem;
    color: var(--text-color-secondary);
}

.row-actions {
    display: flex;
    gap: 0.25rem;
    justify-content: flex-end;
}

.dialog-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    max-height: 70vh;
    overflow-y: auto;
    padding-right: 0.25rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.75rem 1rem;
}

.form-grid label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.files-panel {
    border: 1px dashed var(--surface-border);
    border-radius: 12px;
    padding: 0.75rem;
    background: var(--surface-ground);
}

.files-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.file-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.file-row {
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    padding: 0.75rem;
    display: grid;
    grid-template-columns: 1fr 160px;
    gap: 0.75rem;
    background: var(--surface-card);
}

.file-row .file-row-line label {
    font-weight: 600;
    display: block;
    margin-bottom: 0.2rem;
}

.file-row-main {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.75rem;
}

.file-upload {
    grid-column: 1 / -1;
}

.file-input {
    display: none;
}

.file-label {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 0.9rem;
    border: 1px dashed var(--surface-border);
    border-radius: 10px;
    cursor: pointer;
    color: var(--text-color);
}

.file-row-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-end;
    justify-content: space-between;
}

.file-row-actions {
    display: flex;
    gap: 0.3rem;
}

.file-row.deleted {
    opacity: 0.6;
    background: #fef2f2;
}

.file-row.updated {
    border-color: var(--yellow-400);
}

.file-row.new {
    border-color: var(--blue-300);
}

.muted {
    color: var(--text-color-secondary);
}
</style>

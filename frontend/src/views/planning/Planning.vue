<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import http from '@/service/http';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import { apiPrefix } from '@/config';

const axios = http;

const toast = useToast();
const tasks = ref([]);
const users = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const dialogMode = ref('create'); // create | edit
const saving = ref(false);
const selectedTaskId = ref(null);

const statusOptions = [
    { label: 'A faire', value: 'todo', severity: 'secondary' },
    { label: 'En cours', value: 'in_progress', severity: 'info' },
    { label: 'Terminé', value: 'done', severity: 'success' },
    { label: 'Annulé', value: 'cancelled', severity: 'danger' }
];

const priorityOptions = [
    { label: 'Basse', value: 'low', severity: 'secondary' },
    { label: 'Normale', value: 'medium', severity: 'info' },
    { label: 'Haute', value: 'high', severity: 'warning' }
];

const filters = reactive({
    search: '',
    status: ['todo', 'in_progress'],
    priority: [],
    assignee: null,
    dueAfter: null,
    dueBefore: null
});

const form = reactive({
    title: '',
    description: '',
    status: 'todo',
    priority: 'medium',
    startAt: null,
    dueAt: null,
    assigneeId: null
});

const token = localStorage.getItem('token');

const statusSeverity = (value) => statusOptions.find((o) => o.value === value)?.severity || 'secondary';
const prioritySeverity = (value) => priorityOptions.find((o) => o.value === value)?.severity || 'secondary';
const statusLabel = (value) => statusOptions.find((o) => o.value === value)?.label || value;
const priorityLabel = (value) => priorityOptions.find((o) => o.value === value)?.label || value;
const assigneeFilterOptions = computed(() => [
    { label: 'Tous', value: null },
    ...users.value.map((u) => ({ label: u.fullName || u.username, value: u.id }))
]);
const assigneeFormOptions = computed(() => [
    { label: 'Non assigné', value: null },
    ...users.value.map((u) => ({ label: u.fullName || u.username, value: u.id }))
]);

const resetForm = () => {
    form.title = '';
    form.description = '';
    form.status = 'todo';
    form.priority = 'medium';
    form.startAt = null;
    form.dueAt = null;
    form.assigneeId = null;
};

const openCreate = () => {
    dialogMode.value = 'create';
    selectedTaskId.value = null;
    resetForm();
    dialogVisible.value = true;
};

const openEdit = (task) => {
    dialogMode.value = 'edit';
    selectedTaskId.value = task.id;
    form.title = task.title;
    form.description = task.description;
    form.status = task.status;
    form.priority = task.priority;
    form.startAt = task.startAt ? new Date(task.startAt) : null;
    form.dueAt = task.dueAt ? new Date(task.dueAt) : null;
    form.assigneeId = task.assignee?.id ?? null;
    dialogVisible.value = true;
};

const buildParams = () => {
    const params = {
        search: filters.search || undefined,
        status: filters.status?.join(',') || undefined,
        priority: filters.priority?.join(',') || undefined,
        assignee: filters.assignee ? Number(filters.assignee) : undefined,
        dueAfter: filters.dueAfter ? filters.dueAfter.toISOString() : undefined,
        dueBefore: filters.dueBefore ? filters.dueBefore.toISOString() : undefined
    };
    return params;
};

const fetchTasks = async () => {
    loading.value = true;
    try {
        const res = await axios.get(`${apiPrefix}/tasks`, {
            params: buildParams(),
            headers: { Authorization: `Bearer ${token}` }
        });
        tasks.value = res.data?.items || [];
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les tâches' });
    } finally {
        loading.value = false;
    }
};

const fetchUsers = async () => {
    try {
        const res = await axios.get(`${apiPrefix}/users`, {
            headers: { Authorization: `Bearer ${token}` }
        });
        users.value = res.data || [];
    } catch (err) {
        toast.add({ severity: 'warn', summary: 'Utilisateurs', detail: 'Impossible de charger la liste des utilisateurs' });
    }
};

const canEditTask = (task) => task.status !== 'done' && task.status !== 'cancelled';
const canCompleteTask = (task) => task.status !== 'done' && task.status !== 'cancelled';

const saveTask = async () => {
    if (!form.title?.trim()) {
        toast.add({ severity: 'warn', summary: 'Titre requis', detail: 'Le titre est obligatoire' });
        return;
    }
    saving.value = true;
    const payload = {
        title: form.title,
        description: form.description || null,
        status: form.status,
        priority: form.priority,
        startAt: form.startAt ? form.startAt.toISOString() : null,
        dueAt: form.dueAt ? form.dueAt.toISOString() : null,
        assigneeId: form.assigneeId ? Number(form.assigneeId) : null
    };
    try {
        if (dialogMode.value === 'edit' && selectedTaskId.value) {
            await axios.put(`${apiPrefix}/tasks/${selectedTaskId.value}`, payload, {
                headers: { Authorization: `Bearer ${token}` }
            });
            toast.add({ severity: 'success', summary: 'Tâche mise à jour' });
        } else {
            await axios.post(`${apiPrefix}/tasks`, payload, {
                headers: { Authorization: `Bearer ${token}` }
            });
            toast.add({ severity: 'success', summary: 'Tâche créée' });
        }
        dialogVisible.value = false;
        await fetchTasks();
    } catch (err) {
        const message = err.response?.data?.error || 'Action impossible';
        toast.add({ severity: 'error', summary: 'Erreur', detail: message });
    } finally {
        saving.value = false;
    }
};

const markDone = async (task) => {
    try {
        await axios.patch(`${apiPrefix}/tasks/${task.id}/status`, { status: 'done' }, {
            headers: { Authorization: `Bearer ${token}` }
        });
        toast.add({ severity: 'success', summary: 'Tâche terminée' });
        await fetchTasks();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de terminer la tâche' });
    }
};

const removeTask = async (task) => {
    if (!window.confirm('Supprimer cette tâche ?')) return;
    try {
        await axios.delete(`${apiPrefix}/tasks/${task.id}`, {
            headers: { Authorization: `Bearer ${token}` }
        });
        toast.add({ severity: 'success', summary: 'Tâche supprimée' });
        await fetchTasks();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression impossible' });
    }
};

const clearFilters = () => {
    filters.search = '';
    filters.status = ['todo', 'in_progress'];
    filters.priority = [];
    filters.assignee = null;
    filters.dueAfter = null;
    filters.dueBefore = null;
    fetchTasks();
};

const formatDate = (value) => {
    if (!value) return '-';
    const d = typeof value === 'string' ? new Date(value) : value;
    return d.toLocaleDateString();
};

onMounted(() => {
    fetchUsers();
    fetchTasks();
});

const filteredTasks = computed(() => tasks.value);
const taskStats = computed(() => {
    const total = tasks.value.length;
    let todo = 0;
    let inProgress = 0;
    let done = 0;
    tasks.value.forEach((t) => {
        if (t.status === 'done') done += 1;
        else if (t.status === 'in_progress') inProgress += 1;
        else todo += 1;
    });
    return { total, todo, inProgress, done };
});
</script>

<template>
    <div class="page-shell">
        <div class="hero">
            <div>
                <p class="eyebrow">Planning • Tâches</p>
                <h1>Suivi des tâches et actions</h1>
                <p class="muted">Crée, filtre et termine les tâches avec une vue claire.</p>
            </div>
            <div class="hero-actions">
                <Button label="Nouvelle tâche" icon="pi pi-plus" @click="openCreate" />
                <Button label="Actualiser" icon="pi pi-refresh" severity="secondary" outlined @click="fetchTasks" />
            </div>
        </div>

        <div class="stat-grid">
            <div class="stat-card total">
                <p class="label">Tâches</p>
                <p class="value">{{ taskStats.total }}</p>
            </div>
            <div class="stat-card ongoing">
                <p class="label">En cours</p>
                <p class="value">{{ taskStats.inProgress }}</p>
            </div>
            <div class="stat-card todo">
                <p class="label">À faire</p>
                <p class="value">{{ taskStats.todo }}</p>
            </div>
            <div class="stat-card done">
                <p class="label">Terminées</p>
                <p class="value">{{ taskStats.done }}</p>
            </div>
        </div>

        <div class="card filters-card">
            <div class="filters">
                <div class="filter-block wide">
                    <label class="block mb-2 text-500">Recherche</label>
                    <span class="p-input-icon-left w-full">
                        <i class="pi pi-search" />
                        <InputText v-model="filters.search" class="w-full" placeholder="Titre ou description" @keyup.enter="fetchTasks" />
                    </span>
                </div>
                <div class="filter-block">
                    <label class="block mb-2 text-500">Statuts</label>
                    <MultiSelect
                        v-model="filters.status"
                        :options="statusOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Tous les statuts"
                        display="chip"
                        class="w-full"
                        @change="fetchTasks"
                    />
                </div>
                <div class="filter-block">
                    <label class="block mb-2 text-500">Priorités</label>
                    <MultiSelect
                        v-model="filters.priority"
                        :options="priorityOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Toutes les priorités"
                        display="chip"
                        class="w-full"
                        @change="fetchTasks"
                    />
                </div>
                <div class="filter-block">
                    <label class="block mb-2 text-500">Utilisateur</label>
                    <Select
                        v-model="filters.assignee"
                        :options="assigneeFilterOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Tous les utilisateurs"
                        class="w-full"
                        showClear
                        @change="fetchTasks"
                    />
                </div>
                <div class="filter-block small">
                    <label class="block mb-2 text-500">Échéance ≥</label>
                    <DatePicker v-model="filters.dueAfter" dateFormat="dd/mm/yy" class="w-full" @update:model-value="fetchTasks" />
                </div>
                <div class="filter-block small">
                    <label class="block mb-2 text-500">Échéance ≤</label>
                    <DatePicker v-model="filters.dueBefore" dateFormat="dd/mm/yy" class="w-full" @update:model-value="fetchTasks" />
                </div>
                <div class="filter-actions">
                    <Button label="Réinitialiser" icon="pi pi-filter-slash" severity="secondary" outlined class="w-full" @click="clearFilters" />
                </div>
            </div>
        </div>

        <div class="card table-card">
            <DataTable
                :value="filteredTasks"
                :loading="loading"
                paginator
                :rows="10"
                row-hover
                responsive-layout="scroll"
                table-style="min-width: 920px"
                size="small"
                class="p-datatable-sm"
            >
                <Column field="title" header="Titre" style="min-width: 220px"></Column>
                <Column header="Priorité" style="min-width: 130px">
                    <template #body="slotProps">
                        <Tag :value="priorityLabel(slotProps.data.priority)" :severity="prioritySeverity(slotProps.data.priority)" />
                    </template>
                </Column>
                <Column header="Statut" style="min-width: 140px">
                    <template #body="slotProps">
                        <Tag :value="statusLabel(slotProps.data.status)" :severity="statusSeverity(slotProps.data.status)" />
                    </template>
                </Column>
                <Column header="Début" style="min-width: 140px">
                    <template #body="slotProps">{{ formatDate(slotProps.data.startAt) }}</template>
                </Column>
                <Column header="Échéance" style="min-width: 140px">
                    <template #body="slotProps">{{ formatDate(slotProps.data.dueAt) }}</template>
                </Column>
                <Column header="Assigné" style="min-width: 160px">
                    <template #body="slotProps">{{ slotProps.data.assignee?.username || '-' }}</template>
                </Column>
                <Column header="Actions" style="min-width: 200px" class="text-right">
                    <template #body="slotProps">
                        <div class="flex gap-2 justify-content-end">
                            <Button
                                v-if="canEditTask(slotProps.data)"
                                icon="pi pi-pencil"
                                rounded
                                text
                                @click="openEdit(slotProps.data)"
                            />
                            <Button
                                v-if="canCompleteTask(slotProps.data)"
                                icon="pi pi-check"
                                rounded
                                text
                                severity="success"
                                @click="markDone(slotProps.data)"
                            />
                            <Button icon="pi pi-trash" rounded text severity="danger" @click="removeTask(slotProps.data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </div>

    <Dialog
        v-model:visible="dialogVisible"
        :modal="true"
        :header="dialogMode === 'edit' ? 'Modifier la tâche' : 'Nouvelle tâche'"
        style="width: 720px"
        contentClass="task-modal"
    >
        <div class="modal-grid">
            <div class="field">
                <label class="block mb-2">Titre</label>
                <InputText v-model="form.title" class="w-full" maxlength="255" />
            </div>
            <div class="field">
                <label class="block mb-2">Description</label>
                <Textarea v-model="form.description" rows="4" auto-resize class="w-full" />
            </div>
            <div class="field two-cols">
                <div>
                    <label class="block mb-2">Statut</label>
                    <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full" />
                </div>
                <div>
                    <label class="block mb-2">Priorité</label>
                    <Select v-model="form.priority" :options="priorityOptions" optionLabel="label" optionValue="value" class="w-full" />
                </div>
            </div>
            <div class="field">
                <label class="block mb-2">Assigné à</label>
                <Select
                    v-model="form.assigneeId"
                    :options="assigneeFormOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Sélectionner un utilisateur"
                    class="w-full"
                    showClear
                />
            </div>
            <div class="field two-cols">
                <div>
                    <label class="block mb-2">Début</label>
                    <DatePicker v-model="form.startAt" showTime hourFormat="24" dateFormat="dd/mm/yy" class="w-full" />
                </div>
                <div>
                    <label class="block mb-2">Échéance</label>
                    <DatePicker v-model="form.dueAt" showTime hourFormat="24" dateFormat="dd/mm/yy" class="w-full" />
                </div>
            </div>
        </div>
        <template #footer>
            <div class="flex justify-content-end gap-2 w-full">
                <Button label="Annuler" severity="secondary" outlined @click="dialogVisible = false" />
                <Button :label="dialogMode === 'edit' ? 'Mettre à jour' : 'Créer'" :loading="saving" @click="saveTask" />
            </div>
        </template>
    </Dialog>
</template>

<style scoped>
.page-shell {
    padding: 1.5rem;
    background: var(--surface-ground);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
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
    gap: 0.5rem;
    flex-wrap: wrap;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.75rem;
}

.stat-card {
    background: var(--surface-card);
    color: var(--text-color);
    border-radius: 12px;
    padding: 0.9rem 1rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
    border: 1px solid var(--surface-border);
}

.stat-card .label {
    font-size: 0.85rem;
    color: var(--text-color-secondary);
    margin: 0;
}

.stat-card .value {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0.1rem 0 0;
    color: var(--primary-color);
}

.stat-card.todo .value {
    color: var(--orange-500, #f97316);
}

.stat-card.ongoing .value {
    color: var(--blue-500, #0ea5e9);
}

.stat-card.done .value {
    color: var(--green-500, #22c55e);
}

.card {
    border-radius: 14px;
    padding: 1.25rem;
    background: var(--surface-card);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--surface-border);
}

.filters-card {
    padding: 1rem;
}

.filters {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem 1rem;
    align-items: end;
}

.filter-block {
    display: flex;
    flex-direction: column;
}

.filter-block.wide {
    grid-column: span 2;
    min-width: 280px;
}

.filter-block.small {
    min-width: 170px;
}

.filter-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    min-width: 170px;
}

.table-card {
    overflow: hidden;
}

.task-modal {
    padding-top: 0.5rem;
}

.modal-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

.modal-grid .two-cols {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.75rem;
}

:deep(.p-datatable) {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}

:deep(.p-datatable .p-datatable-header) {
    padding: 0.5rem 1rem;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
    padding: 0.75rem 1rem;
    background: #f9fafb;
    color: #475569;
    font-weight: 600;
}

:deep(.p-datatable .p-datatable-tbody > tr > td) {
    padding: 0.6rem 1rem;
}

:deep(.p-multiselect) {
    width: 100%;
}

.text-500 {
    color: #6b7280;
}
</style>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import http from '@/service/http';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import Select from 'primevue/select';
import Drawer from 'primevue/drawer';
import { apiPrefix } from '@/config';

const axios = http;

const toast = useToast();
const token = localStorage.getItem('token');

const tasks = ref([]);
const users = ref([]);
const loading = ref(false);
const selectedTask = ref(null);
const detailOpen = ref(false);
const filters = reactive({ assignee: null });
const assigneeOptions = computed(() => [
    { label: 'Tous', value: null },
    ...users.value.map((u) => ({ label: u.fullName || u.username, value: u.id }))
]);

const statusMeta = {
    todo: { label: 'A faire', color: '#6b7280', severity: 'secondary' },
    in_progress: { label: 'En cours', color: '#0ea5e9', severity: 'info' },
    done: { label: 'Terminé', color: '#22c55e', severity: 'success' },
    cancelled: { label: 'Annulé', color: '#ef4444', severity: 'danger' }
};

const priorityMeta = {
    low: { label: 'Basse', severity: 'secondary' },
    medium: { label: 'Normale', severity: 'info' },
    high: { label: 'Haute', severity: 'warning' }
};

const parseDate = (value) => {
    if (!value) return null;
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? null : d;
};

const addHours = (date, hours) => new Date(date.getTime() + hours * 3600 * 1000);

const buildEvent = (task) => {
    const start = parseDate(task.startAt) || parseDate(task.dueAt) || parseDate(task.createdAt);
    if (!start) return null;
    const end = parseDate(task.dueAt) || addHours(start, 1);
    const meta = statusMeta[task.status] || statusMeta.todo;
    return {
        id: String(task.id),
        title: task.title,
        start,
        end,
        color: meta.color,
        textColor: '#ffffff',
        allDay: false
    };
};

const events = computed(() => tasks.value.map(buildEvent).filter(Boolean));

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay'
    },
    locale: 'fr',
    events: events.value,
    height: 'auto',
    eventClick: ({ event }) => {
        const task = tasks.value.find((t) => String(t.id) === String(event.id));
        selectedTask.value = task || null;
        detailOpen.value = !!task;
    }
}));

const fetchTasks = async () => {
    loading.value = true;
    try {
        const res = await axios.get(`${apiPrefix}/tasks`, {
            params: { limit: 300, assignee: filters.assignee ? Number(filters.assignee) : undefined },
            headers: { Authorization: `Bearer ${token}` }
        });
        tasks.value = res.data?.items || [];
        if (!selectedTask.value && tasks.value.length > 0) {
            selectedTask.value = tasks.value[0];
        }
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

const changeStatus = async (task, status) => {
    if (!task) return;
    try {
        await axios.patch(
            `${apiPrefix}/tasks/${task.id}/status`,
            { status },
            { headers: { Authorization: `Bearer ${token}` } }
        );
        toast.add({ severity: 'success', summary: 'Statut mis à jour' });
        await fetchTasks();
        selectedTask.value = tasks.value.find((t) => t.id === task.id) || null;
    } catch (err) {
        const message = err.response?.data?.error || 'Action impossible';
        toast.add({ severity: 'error', summary: 'Erreur', detail: message });
    }
};

const removeTask = async (task) => {
    if (!task) return;
    if (!window.confirm('Supprimer cette tâche ?')) return;
    try {
        await axios.delete(`${apiPrefix}/tasks/${task.id}`, {
            headers: { Authorization: `Bearer ${token}` }
        });
        toast.add({ severity: 'success', summary: 'Tâche supprimée' });
        selectedTask.value = null;
        await fetchTasks();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression impossible' });
    }
};

const formatDate = (value) => {
    if (!value) return '-';
    const d = typeof value === 'string' ? new Date(value) : value;
    return d.toLocaleString();
};

onMounted(() => {
    fetchUsers();
    fetchTasks();
});

const closeDetails = () => {
    detailOpen.value = false;
};

const statusLabel = (task) => statusMeta[task?.status]?.label || task?.status || '-';
const statusSeverity = (task) => statusMeta[task?.status]?.severity || 'secondary';
const priorityLabel = (task) => priorityMeta[task?.priority]?.label || task?.priority || '-';
const prioritySeverity = (task) => priorityMeta[task?.priority]?.severity || 'secondary';
const canSetInProgress = (task) => task?.status !== 'in_progress' && task?.status !== 'done' && task?.status !== 'cancelled';
const canSetDone = (task) => task?.status !== 'done';
const canCancel = (task) => task?.status !== 'cancelled' && task?.status !== 'done';
</script>

<template>
    <div class="agenda-page">
        <div class="card agenda-card">
            <div class="toolbar">
                <h3 class="m-0">Agenda des tâches</h3>
                <div class="flex gap-2 flex-wrap">
                    <Select
                        v-model="filters.assignee"
                        :options="assigneeOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Tous les utilisateurs"
                        class="w-14rem"
                        showClear
                        @change="fetchTasks"
                    />
                    <Button label="Rafraîchir" icon="pi pi-refresh" severity="secondary" outlined @click="fetchTasks" />
                </div>
            </div>

            <div class="calendar-shell">
                <FullCalendar :options="calendarOptions" />
            </div>
        </div>

        <Drawer v-model:visible="detailOpen" position="right" header="Détails de la tâche" dismissable modal class="task-drawer">
            <template v-if="selectedTask">
                <div class="flex align-items-center gap-2 mb-2">
                    <Tag :value="statusLabel(selectedTask)" :severity="statusSeverity(selectedTask)" />
                    <Tag :value="priorityLabel(selectedTask)" :severity="prioritySeverity(selectedTask)" />
                </div>
                <h4 class="m-0 mb-2">{{ selectedTask.title }}</h4>
                <p class="text-500 mb-3">{{ selectedTask.description || 'Aucune description' }}</p>

                <div class="info-line">
                    <span class="label">Début :</span>
                    <span>{{ formatDate(selectedTask.startAt) }}</span>
                </div>
                <div class="info-line">
                    <span class="label">Échéance :</span>
                    <span>{{ formatDate(selectedTask.dueAt) }}</span>
                </div>
                <div class="info-line">
                    <span class="label">Assigné :</span>
                    <span>{{ selectedTask.assignee?.username || '-' }}</span>
                </div>
                <div class="info-line">
                    <span class="label">Mise à jour :</span>
                    <span>{{ formatDate(selectedTask.updatedAt) }}</span>
                </div>

                <div class="flex gap-2 mt-3 flex-wrap">
                    <Button
                        v-if="canSetInProgress(selectedTask)"
                        label="En cours"
                        icon="pi pi-play"
                        severity="info"
                        outlined
                        @click="changeStatus(selectedTask, 'in_progress')"
                    />
                    <Button
                        v-if="canSetDone(selectedTask)"
                        label="Terminer"
                        icon="pi pi-check"
                        severity="success"
                        outlined
                        @click="changeStatus(selectedTask, 'done')"
                    />
                    <Button
                        v-if="canCancel(selectedTask)"
                        label="Annuler"
                        icon="pi pi-times"
                        severity="danger"
                        outlined
                        @click="changeStatus(selectedTask, 'cancelled')"
                    />
                    <Button label="Supprimer" icon="pi pi-trash" severity="danger" text @click="removeTask(selectedTask)" />
                </div>
            </template>
            <template v-else>
                <div class="text-500">Sélectionnez une tâche dans le calendrier.</div>
            </template>
        </Drawer>
    </div>
</template>

<style scoped>
.agenda-page {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.agenda-card {
    padding: 1.2rem;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    border-radius: 14px;
    box-shadow: 0 12px 26px rgba(0, 0, 0, 0.07);
}

.toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.calendar-shell {
    background: var(--surface-ground);
    border: 1px solid var(--surface-border);
    border-radius: 12px;
    padding: 0.5rem;
}

.eyebrow {
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-size: 0.78rem;
    color: var(--primary-color);
    margin: 0;
}

.info-line {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.4rem;
}

.info-line .label {
    color: var(--text-color-secondary);
    min-width: 90px;
    display: inline-block;
}

:deep(.fc) {
    --fc-border-color: var(--surface-border);
    --fc-page-bg-color: var(--surface-card);
    --fc-neutral-bg-color: var(--surface-ground);
    --fc-today-bg-color: rgba(14, 165, 233, 0.12);
    --fc-highlight-color: rgba(14, 165, 233, 0.15);
}

:deep(.fc .fc-toolbar) {
    gap: 0.5rem;
}

:deep(.fc .fc-button) {
    padding: 0.35rem 0.65rem;
    border-radius: 8px;
    background: var(--primary-color);
    border-color: var(--primary-color);
}

:deep(.fc .fc-button:hover) {
    background: var(--primary-600);
    border-color: var(--primary-600);
}

:deep(.fc .fc-button-primary:not(:disabled):active),
:deep(.fc .fc-button-primary:not(:disabled):focus) {
    background: var(--primary-600);
    border-color: var(--primary-600);
}

:deep(.fc .fc-daygrid-event),
:deep(.fc .fc-timegrid-event) {
    border: none;
    padding: 4px 8px;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
}

:deep(.fc .fc-daygrid-day-frame) {
    padding: 8px;
}

:deep(.fc .fc-col-header-cell-cushion) {
    padding: 6px 4px;
    font-weight: 600;
}

:deep(.fc .fc-toolbar-title) {
    font-size: 1.1rem;
    font-weight: 700;
}

@media (max-width: 768px) {
    .toolbar {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

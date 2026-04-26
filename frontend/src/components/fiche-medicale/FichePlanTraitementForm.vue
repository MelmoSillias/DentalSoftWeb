<script setup>
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import AutoComplete from 'primevue/autocomplete';
import Textarea from 'primevue/textarea';
import Timeline from 'primevue/timeline';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => []
    },
    saving: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue', 'save']);

const plans = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const typeSuggestions = ref([]);
const viewMode = ref('timeline');

const showDialog = ref(false);
const dialogMode = ref('add');
const editingIndex = ref(null);
const draftPlan = reactive({
    planIndex: null,
    type: '',
    dateSupposed: null,
    description: ''
});

const dialogTitle = computed(() => (dialogMode.value === 'edit' ? 'Modifier un plan' : 'Ajouter un plan'));

const resetDraft = () => {
    draftPlan.planIndex = null;
    draftPlan.type = '';
    draftPlan.dateSupposed = null;
    draftPlan.description = '';
};

const openAddDialog = () => {
    dialogMode.value = 'add';
    editingIndex.value = null;
    resetDraft();
    showDialog.value = true;
};

const openEditDialog = (plan, idx) => {
    dialogMode.value = 'edit';
    editingIndex.value = idx;
    draftPlan.planIndex = plan.planIndex ?? idx + 1;
    draftPlan.type = plan.type ?? '';
    draftPlan.dateSupposed = plan.dateSupposed ?? null;
    draftPlan.description = plan.description ?? '';
    showDialog.value = true;
};

const pushPlan = (payload) => {
    const list = plans.value || [];
    plans.value = [...list, payload];
};

const updatePlan = (idx, patch) => {
    const list = (plans.value || []).map((p, i) => (i === idx ? { ...p, ...patch } : p));
    plans.value = list;
};

const removePlan = (idx) => {
    plans.value = (plans.value || []).filter((_, i) => i !== idx);
};

const saveDraft = () => {
    const list = plans.value || [];
    const payload = {
        planIndex: draftPlan.planIndex ?? list.length + 1,
        type: String(draftPlan.type || '').trim(),
        dateSupposed: draftPlan.dateSupposed,
        description: draftPlan.description
    };

    if (dialogMode.value === 'edit' && editingIndex.value !== null) {
        updatePlan(editingIndex.value, payload);
    } else {
        pushPlan(payload);
    }

    showDialog.value = false;
    resetDraft();
};

const parseDate = (value) => {
    if (!value) return null;
    const date = value instanceof Date ? value : new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
};

const formatDate = (value) => {
    const date = parseDate(value);
    if (!date) return 'Date non definie';
    return date.toLocaleDateString('fr-FR');
};

const iconMap = {
    Urgence: { icon: 'pi pi-bolt', color: '#ef4444' },
    Dentaires: { icon: 'pi pi-tooth', color: '#0ea5e9' },
    Parodontaux: { icon: 'pi pi-heart', color: '#22c55e' },
    Orthodontiques: { icon: 'pi pi-sliders-h', color: '#f59e0b' },
    Autres: { icon: 'pi pi-briefcase', color: '#64748b' }
};

const availableTypeOptions = computed(() => {
    const list = plans.value || [];
    const existing = list.map((plan) => String(plan?.type || '').trim()).filter(Boolean);
    const current = String(draftPlan.type || '').trim();
    return Array.from(new Set([current, ...existing].filter(Boolean)));
});

const searchTypeOptions = (event) => {
    const query = String(event?.query || '').toLowerCase().trim();
    const options = availableTypeOptions.value;
    typeSuggestions.value = query ? options.filter((item) => item.toLowerCase().includes(query)) : options;
};

const sortedPlans = computed(() => {
    const list = plans.value || [];
    return [...list].sort((a, b) => {
        const da = parseDate(a.dateSupposed);
        const db = parseDate(b.dateSupposed);
        if (!da && !db) return 0;
        if (!da) return 1;
        if (!db) return -1;
        return da.getTime() - db.getTime();
    });
});

const timelineEvents = computed(() =>
    sortedPlans.value.map((plan, idx) => {
        const type = plan.type || 'Autres';
        const iconMeta = iconMap[type] || iconMap.Autres;
        return {
            status: plan.type || `Plan ${idx + 1}`,
            date: formatDate(plan.dateSupposed),
            icon: iconMeta.icon,
            color: iconMeta.color,
            description: plan.description || 'Aucune description.',
            planIndex: plan.planIndex ?? idx + 1,
            originalIndex: (plans.value || []).indexOf(plan)
        };
    })
);

const tablePlans = computed(() =>
    sortedPlans.value.map((plan, idx) => ({
        ...plan,
        status: plan.type || `Plan ${idx + 1}`,
        formattedDate: formatDate(plan.dateSupposed),
        descriptionDisplay: plan.description || 'Aucune description.',
        originalIndex: (plans.value || []).indexOf(plan)
    }))
);
</script>

<template>
    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-sitemap text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Plan de traitement</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Planifier les actes et priorites</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button icon="pi pi-plus" label="Ajouter" size="small" class="rounded-xl" @click="openAddDialog" />
                <Button
                    label="Sauvegarder"
                    icon="pi pi-save"
                    :loading="saving"
                    @click="emit('save')"
                    class="rounded-xl px-5 py-3 font-medium shadow-sm hover:shadow-md transition-all bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white"
                />
            </div>
        </div>

        <div class="space-y-4">
            <div v-if="!(plans && plans.length)" class="text-sm text-surface-500 dark:text-surface-400">
                Aucun plan de traitement ajoute.
            </div>

            <div v-else class="space-y-6">
                <div class="flex items-center justify-end">
                    <div class="inline-flex rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/40 p-1">
                        <Button
                            label="Timeline"
                            size="small"
                            :severity="viewMode === 'timeline' ? 'primary' : 'secondary'"
                            :outlined="viewMode !== 'timeline'"
                            @click="viewMode = 'timeline'"
                        />
                        <Button
                            label="Table"
                            size="small"
                            :severity="viewMode === 'table' ? 'primary' : 'secondary'"
                            :outlined="viewMode !== 'table'"
                            @click="viewMode = 'table'"
                        />
                    </div>
                </div>

                <Timeline v-if="viewMode === 'timeline'" :value="timelineEvents" align="alternate" class="customized-timeline">
                    <template #marker="slotProps">
                        <span
                            class="flex w-8 h-8 items-center justify-center text-white rounded-full z-10 shadow-sm"
                            :style="{ backgroundColor: slotProps.item.color }"
                        >
                            <i :class="slotProps.item.icon"></i>
                        </span>
                    </template>
                    <template #content="slotProps">
                        <Card class="mt-4">
                            <template #title>
                                {{ slotProps.item.status }}
                            </template>
                            <template #subtitle>
                                {{ slotProps.item.date }}
                            </template>
                            <template #content>
                                <p class="text-sm text-surface-600 dark:text-surface-300">
                                    {{ slotProps.item.description }}
                                </p>
                                <div class="mt-4 flex items-center gap-2">
                                    <Button label="Modifier" text @click="openEditDialog(sortedPlans[slotProps.index], slotProps.item.originalIndex)" />
                                    <Button
                                        label="Supprimer"
                                        text
                                        severity="danger"
                                        @click="removePlan(slotProps.item.originalIndex)"
                                    />
                                </div>
                            </template>
                        </Card>
                    </template>
                </Timeline>

                <div v-else class="rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
                    <div class="px-4 py-3 bg-surface-50 dark:bg-surface-800/40 border-b border-surface-200 dark:border-surface-700">
                        <h4 class="text-sm font-semibold text-surface-700 dark:text-surface-200">Vue table</h4>
                    </div>
                    <DataTable :value="tablePlans" dataKey="planIndex" class="rounded-none" size="small">
                        <Column field="status" header="Type" />
                        <Column field="formattedDate" header="Date prévue" />
                        <Column field="descriptionDisplay" header="Description" />
                        <Column header="Actions" style="width: 12rem">
                            <template #body="{ data }">
                                <div class="flex items-center gap-2">
                                    <Button label="Modifier" text size="small" @click="openEditDialog(data, data.originalIndex)" />
                                    <Button label="Supprimer" text severity="danger" size="small" @click="removePlan(data.originalIndex)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <Dialog v-model:visible="showDialog" modal :header="dialogTitle" class="w-full max-w-2xl">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Type</label>
                    <AutoComplete
                        :modelValue="draftPlan.type"
                        :suggestions="typeSuggestions"
                        dropdown
                        placeholder="Saisir ou choisir un type"
                        @complete="searchTypeOptions"
                        @update:modelValue="(v) => (draftPlan.type = v)"
                        class="w-full"
                    />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Date prevue</label>
                    <DatePicker
                        :modelValue="draftPlan.dateSupposed"
                        dateFormat="dd/mm/yy"
                        showIcon
                        inputClass="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 p-3"
                        @update:modelValue="(v) => (draftPlan.dateSupposed = v)"
                    />
                </div>
            </div>
            <div class="mt-4 space-y-2">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Description</label>
                <Textarea
                    :modelValue="draftPlan.description"
                    rows="4"
                    class="w-full"
                    @update:modelValue="(v) => (draftPlan.description = v)"
                />
            </div>
            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <Button label="Annuler" text @click="showDialog = false" />
                    <Button label="Enregistrer" icon="pi pi-check" @click="saveDraft" />
                </div>
            </template>
        </Dialog>
    </div>
</template>

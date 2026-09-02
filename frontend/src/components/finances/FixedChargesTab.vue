<script setup>
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';

const props = defineProps({
    items: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    loading: { type: Boolean, default: false },
    actionLoading: { type: Boolean, default: false }
});

const emit = defineEmits(['create', 'update', 'delete', 'create-expense', 'create-global-expense']);

const localRows = ref([]);

const mapIncomingRow = (row) => ({
    id: row?.id ?? null,
    designation: row?.designation || '',
    montant: Number(row?.montant || 0),
    isNew: false,
    isEditing: false,
    snapshot: null
});

watch(
    () => props.items,
    (items) => {
        localRows.value = (items || []).map(mapIncomingRow);
    },
    { immediate: true, deep: true }
);

const formatFcfa = (value) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(Number(value || 0));

const addRow = () => {
    localRows.value = [
        {
            id: null,
            designation: '',
            montant: 0,
            isNew: true,
            isEditing: true,
            snapshot: null
        },
        ...localRows.value
    ];
};

const startEdit = (row) => {
    row.snapshot = { designation: row.designation, montant: row.montant };
    row.isEditing = true;
};

const cancelEdit = (row) => {
    if (row.isNew) {
        localRows.value = localRows.value.filter((item) => item !== row);
        return;
    }

    row.designation = row.snapshot?.designation || row.designation;
    row.montant = row.snapshot?.montant ?? row.montant;
    row.isEditing = false;
    row.snapshot = null;
};

const saveRow = (row) => {
    const payload = {
        designation: String(row.designation || '').trim(),
        montant: Number(row.montant || 0)
    };

    if (!payload.designation || payload.montant <= 0) {
        return;
    }

    if (row.isNew) {
        emit('create', payload);
        return;
    }

    emit('update', { id: row.id, payload });
};

const removeRow = (row) => {
    if (row.isNew) {
        localRows.value = localRows.value.filter((item) => item !== row);
        return;
    }

    emit('delete', row);
};

const hasRows = computed(() => localRows.value.length > 0);
</script>

<template>
    <section class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-surface-200/70 bg-surface-0/80 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80">
            <div class="border-b border-surface-200/50 bg-gradient-to-r from-surface-50/50 to-surface-0/30 px-5 py-4 dark:border-surface-700/50 dark:from-surface-900/50 dark:to-surface-800/30 md:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100 md:text-xl">Charges fixes</h2>
                        <p class="text-sm text-surface-500 dark:text-surface-400">Ajoutez, modifiez et supprimez les charges fixes de l’entreprise.</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-950/40 dark:text-rose-200">Total : {{ formatFcfa(total) }}</div>
                        <Button v-if="hasRows && total > 0" icon="pi pi-minus-circle" label="Transaction globale" severity="warning" outlined @click="emit('create-global-expense')" />
                        <Button icon="pi pi-plus" label="Ajouter une ligne" @click="addRow" />
                    </div>
                </div>
            </div>

            <DataTable :value="localRows" dataKey="id" :loading="loading" responsiveLayout="scroll" stripedRows>
                <template #empty>
                    <div class="px-4 py-8 text-center text-surface-500 dark:text-surface-400">Aucune charge fixe enregistrée.</div>
                </template>

                <Column header="Désignation">
                    <template #body="{ data }">
                        <InputText v-if="data.isEditing" v-model="data.designation" class="w-full" placeholder="Ex: Loyer cabinet" />
                        <span v-else class="font-medium text-surface-900 dark:text-surface-100">{{ data.designation }}</span>
                    </template>
                </Column>

                <Column header="Montant" style="width: 220px">
                    <template #body="{ data }">
                        <InputNumber v-if="data.isEditing" v-model="data.montant" mode="decimal" locale="fr-FR" :minFractionDigits="0" class="w-full" />
                        <span v-else class="font-semibold text-rose-600 dark:text-rose-300">{{ formatFcfa(data.montant) }}</span>
                    </template>
                </Column>

                <Column header="Actions" style="width: 280px">
                    <template #body="{ data }">
                        <div class="flex flex-wrap gap-2">
                            <template v-if="data.isEditing">
                                <Button icon="pi pi-save" label="Enregistrer" size="small" :loading="actionLoading" @click="saveRow(data)" />
                                <Button icon="pi pi-times" label="Annuler" size="small" severity="secondary" text @click="cancelEdit(data)" />
                            </template>
                            <template v-else>
                                <Button icon="pi pi-pencil" label="Modifier" size="small" severity="info" text @click="startEdit(data)" />
                                <Button icon="pi pi-minus-circle" label="Ajouter en dépense" size="small" severity="warning" text @click="emit('create-expense', data)" />
                                <Button icon="pi pi-trash" label="Supprimer" size="small" severity="danger" text @click="removeRow(data)" />
                            </template>
                        </div>
                    </template>
                </Column>
            </DataTable>

            <div v-if="hasRows" class="border-t border-surface-200/60 px-5 py-3 text-sm text-surface-500 dark:border-surface-700/60 dark:text-surface-400 md:px-6">
                Utilisez “Ajouter en dépense” pour une ligne unitaire ou “Transaction globale” pour le total de toutes les charges fixes.
            </div>
        </section>
    </section>
</template>

<script setup>
import ActeLineCard from '@/components/consultations/ActeLineCard.vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import { computed, ref, watch } from 'vue';
import { defaultSoinList, formatActeCurrency, normalizeDentList, normalizeSoinList } from '@/services/consultations';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    lines: {
        type: Array,
        default: () => []
    },
    loading: {
        type: Boolean,
        default: false
    },
    saving: {
        type: Boolean,
        default: false
    },
    soins: {
        type: Array,
        default: () => defaultSoinList
    },
    formuleDentaire: {
        type: Object,
        default: () => ({})
    },
    tourTarget: {
        type: String,
        default: null
    },
    date: {
        type: String,
        default: ''
    },
    time: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:visible', 'save']);

const localLines = ref([]);
const lineSubtotals = ref([]);
const localDate = ref('');
const localTime = ref('');

const normalizeLine = (line = {}, idx = 0) => ({
    id: line.id ?? `${Date.now()}-${idx}-${Math.round(Math.random() * 1000)}`,
    dent: normalizeDentList(line.dent ?? line.dents ?? ''),
    type: line.type ?? '',
    prix: Number(line.prix ?? 0) || 0,
    quantite: Number(line.quantite ?? 1) || 1,
    description: line.description ?? ''
});

const syncLines = () => {
    const source = props.lines?.length ? props.lines : [normalizeLine()];
    localLines.value = source.map((line, idx) => normalizeLine(line, idx));
};

watch(
    () => props.lines,
    () => syncLines(),
    { immediate: true, deep: true }
);

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            syncLines();
            localDate.value = props.date || '';
            localTime.value = props.time || '';
        }
    }
);

watch(
    () => [props.date, props.time],
    ([date, time]) => {
        if (props.visible) {
            localDate.value = date || '';
            localTime.value = time || '';
        }
    }
);

const lineTotal = (line) => (Number(line.quantite) || 0) * (Number(line.prix) || 0);

watch(
    localLines,
    (lines) => {
        lineSubtotals.value = (lines || []).map((line) => lineTotal(line));
    },
    { deep: true, immediate: true }
);

const soinsList = computed(() => normalizeSoinList(props.soins));

const totalTtc = computed(() => localLines.value.reduce((sum, line) => sum + lineTotal(line), 0));

const addLine = () => {
    localLines.value = [...localLines.value, normalizeLine({}, localLines.value.length)];
};

const removeLine = (idx) => {
    if (localLines.value.length <= 1) {
        localLines.value = [normalizeLine()];
        return;
    }
    localLines.value = localLines.value.filter((_, index) => index !== idx);
};

const updateLine = (idx, patch) => {
    localLines.value = localLines.value.map((line, index) => {
        if (index !== idx) {
            return line;
        }

        const next = { ...line, ...patch };
        if (Object.prototype.hasOwnProperty.call(patch || {}, 'dent')) {
            next.dent = normalizeDentList(patch.dent);
        }
        return next;
    });
};

const handleSave = () => {
    emit('save', {
        lines: localLines.value.map((line, idx) => normalizeLine(line, idx)),
        date: localDate.value,
        time: localTime.value
    });
};

const handleHide = () => emit('update:visible', false);
</script>

<template>
    <Dialog :visible="visible" modal header="Modifier la facture" :style="{ width: '52rem', maxWidth: '98vw' }" @update:visible="handleHide">
        <div :data-tour="props.tourTarget || null">
            <div v-if="loading" class="flex min-h-[12rem] flex-col items-center justify-center gap-3 p-6 text-surface-600 dark:text-surface-300">
                <ProgressSpinner strokeWidth="4" style="width: 42px; height: 42px" />
                <p class="text-sm">Chargement des lignes de facture...</p>
            </div>

            <div v-else class="flex flex-col gap-4">
                <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800/40 p-4">
                    <p class="mb-3 text-sm font-semibold text-surface-900 dark:text-surface-100">Date de la facture</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-surface-500">Date</label>
                            <InputText v-model="localDate" type="date" class="w-full" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-surface-500">Heure</label>
                            <InputText v-model="localTime" type="time" class="w-full" />
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/10">
                                <i class="pi pi-file-edit text-amber-600 dark:text-amber-400"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-surface-900 dark:text-surface-100">Actes posés</p>
                                <p class="text-sm text-surface-500 dark:text-surface-400">Modifiez les soins facturés pour cette consultation</p>
                            </div>
                        </div>
                        <Button icon="pi pi-plus" label="Ajouter un soin" size="small" class="rounded-xl border-0 bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-2.5 text-white shadow-sm transition-all hover:shadow-md" @click="addLine" />
                    </div>
                </div>

                <div v-if="!localLines.length" class="rounded-xl border border-dashed border-surface-200 bg-surface-50/60 p-8 text-center dark:border-surface-700 dark:bg-surface-800/20">
                    <div class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800">
                        <i class="pi pi-inbox text-2xl text-surface-400"></i>
                    </div>
                    <p class="text-sm text-surface-600 dark:text-surface-400">Aucun acte. Ajoutez au moins une ligne pour enregistrer la facture.</p>
                </div>

                <div v-else class="space-y-4">
                    <ActeLineCard
                        v-for="(line, idx) in localLines"
                        :key="line.id"
                        :acte="line"
                        :index="idx"
                        :soins="soinsList"
                        :formule-dentaire="formuleDentaire"
                        :subtotal="lineSubtotals[idx] ?? lineTotal(line)"
                        @update="(patch) => updateLine(idx, patch)"
                        @remove="removeLine(idx)"
                    />
                </div>

                <div v-if="localLines.length" class="rounded-xl border border-surface-200 bg-gradient-to-r from-amber-50 to-orange-50 p-4 dark:border-surface-700 dark:from-amber-900/20 dark:to-orange-900/10">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-base font-semibold text-surface-900 dark:text-surface-100">Total TTC</span>
                        <span class="text-2xl font-bold text-amber-700 dark:text-amber-300">
                            {{ formatActeCurrency(totalTtc) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex w-full flex-wrap items-center justify-end gap-2">
                <Button label="Fermer" icon="pi pi-times" severity="secondary" text class="rounded-xl px-4" @click="handleHide" />
                <Button
                    label="Enregistrer"
                    icon="pi pi-save"
                    :loading="saving"
                    class="rounded-xl border-0 bg-gradient-to-r from-primary-500 to-primary-600 px-5 py-2.5 font-medium text-white shadow-sm transition-all hover:shadow-md"
                    @click="handleSave"
                />
            </div>
        </template>
    </Dialog>
</template>

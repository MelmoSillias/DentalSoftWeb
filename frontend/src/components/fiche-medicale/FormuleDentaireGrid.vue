<script setup>
import { computed } from 'vue';
import ToothSvg from '@/components/fiche-medicale/ToothSvg.vue';
import { hasToothData, toothSummary } from '@/utils/formuleDentaireLayout';

const props = defineProps({
    matrix: {
        type: Object,
        required: true
    },
    form: {
        type: Object,
        default: () => ({})
    },
    selectedTooth: {
        type: Number,
        default: null
    },
    mode: {
        type: String,
        default: 'readonly',
        validator: (value) => ['readonly', 'edit'].includes(value)
    }
});

const emit = defineEmits(['tooth-click']);

const formData = computed(() => props.form || {});

const gridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${props.matrix.gridColumns}, minmax(0, 1fr))`
}));

const toothState = (tooth) => {
    const entry = formData.value?.[tooth];
    if (entry?.estCausale) return 'causale';
    if (hasToothData(entry)) return 'data';
    return 'empty';
};

const toothColorClass = (tooth) => {
    switch (toothState(tooth)) {
        case 'causale':
            return 'text-red-500 dark:text-red-400';
        case 'data':
            return 'text-emerald-500 dark:text-emerald-400';
        default:
            return 'text-surface-300 dark:text-surface-600';
    }
};

const wrapperClass = (tooth) => {
    const active = props.mode === 'edit' && props.selectedTooth === tooth;
    const state = toothState(tooth);
    const hover = 'hover:bg-surface-100/70 dark:hover:bg-surface-800/60';
    let ring = '';
    if (active) {
        ring =
            state === 'causale'
                ? 'ring-2 ring-red-400 ring-offset-1 dark:ring-offset-surface-900'
                : state === 'data'
                  ? 'ring-2 ring-emerald-400 ring-offset-1 dark:ring-offset-surface-900'
                  : 'ring-2 ring-primary-400 ring-offset-1 dark:ring-offset-surface-900';
    }
    return `group relative flex items-center justify-center rounded-xl p-0.5 cursor-pointer transition-all duration-200 ${hover} ${ring}`;
};

const labelClass = (tooth) => {
    switch (toothState(tooth)) {
        case 'causale':
            return 'text-red-600 dark:text-red-300 font-bold';
        case 'data':
            return 'text-emerald-600 dark:text-emerald-300 font-bold';
        default:
            return 'text-surface-500 dark:text-surface-400 font-semibold';
    }
};

const onToothClick = (tooth) => {
    emit('tooth-click', tooth);
};

// Positionnement CSS grid : 5 lignes -> [1] dents sup, [2] num sup, [3] séparateur, [4] num inf, [5] dents inf
const teethRow = (role) => (role === 'upper' ? 1 : 5);
const labelRow = (role) => (role === 'upper' ? 2 : 4);

const toothCellStyle = (col, role) => ({ gridColumn: `${col} / span 1`, gridRow: teethRow(role) });
const labelCellStyle = (col, role) => ({ gridColumn: `${col} / span 1`, gridRow: labelRow(role) });
const midlineStyle = (role) => ({
    gridColumn: `${props.matrix.midlineColumn} / span 1`,
    gridRow: role === 'upper' ? '1 / span 2' : '4 / span 2'
});

const toothCells = (row) => row.cells.filter((cell) => cell.tooth);
const rightCells = (row) => toothCells(row).filter((cell) => cell.col < props.matrix.midlineColumn);
const leftCells = (row) => toothCells(row).filter((cell) => cell.col > props.matrix.midlineColumn);

// Rendu aplati en une seule boucle (évite les <template v-for> imbriqués)
const gridItems = computed(() => {
    const items = [];
    for (const row of props.matrix.rows) {
        items.push({ key: `mid-${row.role}`, kind: 'midline', role: row.role, style: midlineStyle(row.role) });
        for (const cell of toothCells(row)) {
            items.push({
                key: `tooth-${row.role}-${cell.tooth}`,
                kind: 'tooth',
                role: row.role,
                tooth: cell.tooth,
                style: toothCellStyle(cell.col, row.role)
            });
            items.push({
                key: `label-${row.role}-${cell.tooth}`,
                kind: 'label',
                role: row.role,
                tooth: cell.tooth,
                style: labelCellStyle(cell.col, row.role)
            });
        }
    }
    items.push({ key: 'divider', kind: 'divider', style: { gridColumn: '1 / -1', gridRow: 3 } });
    return items;
});
</script>

<template>
    <div class="formule-dentaire-chart w-full min-w-0">
        <!-- En-têtes quadrants (desktop) -->
        <div class="hidden sm:grid gap-x-1 mb-1 text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400" :style="gridStyle">
            <div :style="{ gridColumn: '1 / span 8' }" class="text-center">Droit</div>
            <div :style="{ gridColumn: '10 / span 8' }" class="text-center">Gauche</div>
        </div>

        <!-- Matrice anatomique (desktop) -->
        <div class="formule-dentaire-matrix hidden sm:grid gap-x-1 mx-auto max-w-3xl" :style="gridStyle">
            <div v-for="item in gridItems" :key="item.key" :style="item.style" class="min-w-0">
                <!-- Ligne médiane -->
                <div v-if="item.kind === 'midline'" class="flex h-full justify-center px-0.5">
                    <div class="w-px bg-surface-300/80 dark:bg-surface-600/80" />
                </div>

                <!-- Séparateur inter-arcades -->
                <div v-else-if="item.kind === 'divider'" class="flex items-center justify-center px-4">
                    <div class="w-full border-t border-dashed border-surface-300 dark:border-surface-600" />
                </div>

                <!-- Dent -->
                <div v-else-if="item.kind === 'tooth'" class="flex h-full" :class="item.role === 'upper' ? 'items-end' : 'items-start'">
                    <button type="button" class="mx-auto" :class="wrapperClass(item.tooth)" :title="`Dent ${item.tooth}${toothSummary(formData, item.tooth) ? ' — ' + toothSummary(formData, item.tooth) : ''}`" @click="onToothClick(item.tooth)">
                        <span class="block h-14 w-9 sm:h-[4.25rem] sm:w-11" :class="toothColorClass(item.tooth)">
                            <ToothSvg :tooth="item.tooth" />
                        </span>
                    </button>
                </div>

                <!-- Numéro -->
                <div v-else class="flex flex-col items-center justify-center leading-none py-0.5">
                    <span class="text-[11px] sm:text-xs" :class="labelClass(item.tooth)">{{ item.tooth }}</span>
                    <span v-if="toothSummary(formData, item.tooth)" class="text-[8px] sm:text-[9px] text-surface-400 dark:text-surface-500 truncate max-w-full px-0.5">
                        {{ toothSummary(formData, item.tooth) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Mobile : arcades empilées -->
        <div class="sm:hidden space-y-5">
            <div v-for="row in matrix.rows" :key="'mob-' + row.role" class="space-y-2">
                <div class="text-center text-[10px] font-semibold uppercase tracking-wider text-surface-500">
                    {{ row.role === 'upper' ? 'Arcade supérieure' : 'Arcade inférieure' }}
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <div class="text-[9px] text-center text-surface-400 mb-1 uppercase">Droit</div>
                        <div class="flex flex-wrap justify-center gap-1">
                            <button v-for="cell in rightCells(row)" :key="'mob-r-' + row.role + '-' + cell.tooth" type="button" :class="wrapperClass(cell.tooth)" @click="onToothClick(cell.tooth)">
                                <span class="flex flex-col items-center">
                                    <span class="block h-10 w-7" :class="toothColorClass(cell.tooth)">
                                        <ToothSvg :tooth="cell.tooth" />
                                    </span>
                                    <span class="text-[9px]" :class="labelClass(cell.tooth)">{{ cell.tooth }}</span>
                                </span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <div class="text-[9px] text-center text-surface-400 mb-1 uppercase">Gauche</div>
                        <div class="flex flex-wrap justify-center gap-1">
                            <button v-for="cell in leftCells(row)" :key="'mob-l-' + row.role + '-' + cell.tooth" type="button" :class="wrapperClass(cell.tooth)" @click="onToothClick(cell.tooth)">
                                <span class="flex flex-col items-center">
                                    <span class="block h-10 w-7" :class="toothColorClass(cell.tooth)">
                                        <ToothSvg :tooth="cell.tooth" />
                                    </span>
                                    <span class="text-[9px]" :class="labelClass(cell.tooth)">{{ cell.tooth }}</span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="row.role === 'upper'" class="border-t border-dashed border-surface-300 dark:border-surface-600" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.formule-dentaire-matrix {
    grid-template-rows: auto auto 0.75rem auto auto;
}
</style>

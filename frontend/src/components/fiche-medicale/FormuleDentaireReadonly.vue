<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({})
    }
});

const form = computed(() => props.modelValue || {});

const rows = [
    { left: [55, 54, 53, 52, 51], right: [61, 62, 63, 64, 65] },
    { left: [18, 17, 16, 15, 14, 13, 12, 11], right: [21, 22, 23, 24, 25, 26, 27, 28] },
    { left: [48, 47, 46, 45, 44, 43, 42, 41], right: [31, 32, 33, 34, 35, 36, 37, 38] },
    { left: [85, 84, 83, 82, 81], right: [71, 72, 73, 74, 75] }
];

const toothSummary = (tooth) => {
    const entry = form.value?.[tooth];
    if (!entry?.etat || entry.etat.length === 0) {
        return '';
    }
    return Array.isArray(entry.etat) ? entry.etat.join('-') : String(entry.etat);
};

const hasData = (entry) => {
    if (!entry) return false;
    if (entry.etat && entry.etat.length) return true;
    if (entry.estCausale) return true;
    if (entry.diagnosticSuppose) return true;
    if (entry.examensComplementaires && entry.examensComplementaires.length) return true;
    return Object.values(entry.siCausale || {}).some((value) => value);
};

const toothClasses = (tooth) => {
    const entry = form.value?.[tooth];
    if (entry?.estCausale) {
        return 'bg-red-100 text-red-700 border-red-200';
    }
    if (hasData(entry)) {
        return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    }
    return 'bg-white text-surface-600 border-surface-200';
};
</script>

<template>
    <div class="w-full min-w-0">
        <div class="sm:hidden space-y-4">
            <div v-for="(row, rowIndex) in rows" :key="'stack-' + rowIndex" class="space-y-2">
                <div class="flex flex-wrap justify-center gap-2">
                    <div
                        v-for="tooth in row.left"
                        :key="'stack-left-' + tooth"
                        class="h-10 w-10 rounded-xl border text-[10px] font-semibold tracking-tight flex flex-col items-center justify-center"
                        :class="toothClasses(tooth)"
                    >
                        <div class="text-[9px] leading-tight">Dent</div>
                        <div class="text-xs font-bold">{{ tooth }}</div>
                        <div class="text-[9px] leading-tight opacity-80">{{ toothSummary(tooth) || '---' }}</div>
                    </div>
                </div>
                <div class="flex flex-wrap justify-center gap-2">
                    <div
                        v-for="tooth in row.right"
                        :key="'stack-right-' + tooth"
                        class="h-10 w-10 rounded-xl border text-[10px] font-semibold tracking-tight"
                        :class="toothClasses(tooth)"
                    >
                        <div class="text-[9px] leading-tight">Dent</div>
                        <div class="text-xs font-bold">{{ tooth }}</div>
                        <div class="text-[9px] leading-tight opacity-80">{{ toothSummary(tooth) || '---' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full sm:min-w-[520px] border-collapse mx-auto">
                <tbody>
                    <tr v-for="(row, rowIndex) in rows" :key="rowIndex">
                        <template v-if="rowIndex === 0 || rowIndex === rows.length - 1">
                            <td class="w-6 sm:w-8"></td>
                            <td class="w-6 sm:w-8"></td>
                        </template>
                        <td v-for="tooth in row.left" :key="'left-' + tooth" class="p-1">
                            <div
                                class="h-12 w-12 sm:h-14 sm:w-14 rounded-xl border text-[11px] sm:text-xs font-semibold tracking-tight flex flex-col items-center justify-center"
                                :class="toothClasses(tooth)"
                            >
                                <div class="text-[10px] leading-tight">Dent</div>
                                <div class="text-sm font-bold">{{ tooth }}</div>
                                <div class="text-[10px] leading-tight opacity-80">{{ toothSummary(tooth) || '---' }}</div>
                            </div>
                        </td>
                        <template v-if="rowIndex === 0 || rowIndex === rows.length - 1">
                            <td class="w-6 sm:w-8"></td>
                            <td class="w-6 sm:w-8"></td>
                            <td class="w-6 sm:w-8"></td>
                        </template>
                        <template v-else>
                            <td class="w-6 sm:w-8"></td>
                        </template>
                        <td v-for="tooth in row.right" :key="'right-' + tooth" class="p-1">
                            <div
                                class="h-12 w-12 sm:h-14 sm:w-14 rounded-xl border text-[11px] sm:text-xs font-semibold tracking-tight flex flex-col items-center justify-center"
                                :class="toothClasses(tooth)"
                            >
                                <div class="text-[10px] leading-tight">Dent</div>
                                <div class="text-sm font-bold">{{ tooth }}</div>
                                <div class="text-[10px] leading-tight opacity-80">{{ toothSummary(tooth) || '---' }}</div>
                            </div>
                        </td>
                        <template v-if="rowIndex === 0 || rowIndex === rows.length - 1">
                            <td class="w-6 sm:w-8"></td>
                            <td class="w-6 sm:w-8"></td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

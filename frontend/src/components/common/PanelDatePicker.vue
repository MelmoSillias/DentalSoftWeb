<script setup>
import { computed, ref, watch } from 'vue';
import DatePicker from 'primevue/datepicker';
import Popover from 'primevue/popover';
import {
    buildDefaultDatePeriods,
    DEFAULT_PERIOD_LABELS,
    sameDayRange
} from '@/utils/dateUtils';

const props = defineProps({
    modelValue: {
        type: [Array, null],
        default: null
    },
    /** Record<string, [Date, Date] | null> — custom is always ensured */
    periods: {
        type: Object,
        default: null
    },
    periodLabels: {
        type: Object,
        default: null
    },
    dateFormat: {
        type: String,
        default: 'dd/mm/yy'
    },
    showIcon: {
        type: Boolean,
        default: true
    },
    placeholder: {
        type: String,
        default: 'Choisir période'
    },
    disabled: {
        type: Boolean,
        default: false
    },
    showClear: {
        type: Boolean,
        default: false
    },
    fluid: {
        type: Boolean,
        default: false
    },
    inputClass: {
        type: String,
        default: ''
    },
    manualInput: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue']);

const panelRef = ref(null);
const activeKey = ref('custom');
const draftRange = ref(normalizeRange(props.modelValue));

const resolvedPeriods = computed(() => {
    const base = props.periods && typeof props.periods === 'object'
        ? { ...props.periods }
        : buildDefaultDatePeriods();
    if (!Object.prototype.hasOwnProperty.call(base, 'custom')) {
        base.custom = null;
    }
    return base;
});

const resolvedLabels = computed(() => ({
    ...DEFAULT_PERIOD_LABELS,
    ...(props.periodLabels || {})
}));

const periodEntries = computed(() =>
    Object.keys(resolvedPeriods.value).map((key) => ({
        key,
        label: resolvedLabels.value[key] || key,
        range: resolvedPeriods.value[key]
    }))
);

const displayValue = computed(() => {
    const range = normalizeRange(props.modelValue);
    if (!range || !range[0]) return '';
    const start = formatDisplayDate(range[0]);
    if (!range[1]) return start;
    return `${start} — ${formatDisplayDate(range[1])}`;
});

function normalizeRange(value) {
    if (!Array.isArray(value) || !value.length) return null;
    const start = value[0] ? new Date(value[0]) : null;
    const end = value[1] ? new Date(value[1]) : null;
    if (!start || Number.isNaN(start.getTime())) return null;
    if (end && Number.isNaN(end.getTime())) return [start, null];
    return [start, end];
}

function formatDisplayDate(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
    const fmt = props.dateFormat || 'dd/mm/yy';
    const dd = String(date.getDate()).padStart(2, '0');
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const yyyy = String(date.getFullYear());
    const yy = yyyy.slice(-2);
    if (fmt.includes('yyyy')) {
        return fmt.replace('dd', dd).replace('mm', mm).replace('yyyy', yyyy);
    }
    return fmt.replace('dd', dd).replace('mm', mm).replace('yy', yy);
}

function matchPeriodKey(range) {
    const normalized = normalizeRange(range);
    if (!normalized || !normalized[0] || !normalized[1]) return 'custom';
    for (const [key, periodRange] of Object.entries(resolvedPeriods.value)) {
        if (key === 'custom' || !periodRange) continue;
        if (sameDayRange(normalized, periodRange)) return key;
    }
    return 'custom';
}

function selectPeriod(key) {
    activeKey.value = key;
    if (key === 'custom') return;
    const range = resolvedPeriods.value[key];
    if (!range) return;
    draftRange.value = [new Date(range[0]), new Date(range[1])];
    emit('update:modelValue', draftRange.value);
    panelRef.value?.hide?.();
}

function onCalendarUpdate(value) {
    draftRange.value = normalizeRange(value);
    activeKey.value = matchPeriodKey(draftRange.value);
    if (draftRange.value?.[0] && draftRange.value?.[1]) {
        emit('update:modelValue', draftRange.value);
    } else if (!draftRange.value) {
        emit('update:modelValue', null);
    }
}

function clearSelection(event) {
    event?.stopPropagation?.();
    draftRange.value = null;
    activeKey.value = 'custom';
    emit('update:modelValue', null);
}

function togglePanel(event) {
    if (props.disabled) return;
    // Refresh default periods relative to "now" when opening if using built-ins
    draftRange.value = normalizeRange(props.modelValue);
    activeKey.value = matchPeriodKey(draftRange.value);
    panelRef.value?.toggle?.(event);
}

watch(
    () => props.modelValue,
    (value) => {
        draftRange.value = normalizeRange(value);
        activeKey.value = matchPeriodKey(value);
    },
    { deep: true }
);

watch(
    () => props.periods,
    () => {
        activeKey.value = matchPeriodKey(props.modelValue);
    },
    { deep: true }
);
</script>

<template>
    <div class="panel-date-picker" :class="{ 'w-full': fluid, 'opacity-60 pointer-events-none': disabled }">
        <div
            class="panel-date-picker__trigger relative flex items-center"
            :class="fluid ? 'w-full' : ''"
            @click="togglePanel"
        >
            <input
                type="text"
                readonly
                :value="displayValue"
                :placeholder="placeholder"
                :disabled="disabled"
                class="p-inputtext p-component w-full cursor-pointer"
                :class="[
                    inputClass,
                    showIcon ? 'pr-10' : '',
                    showClear && displayValue ? 'pr-16' : ''
                ]"
            />
            <button
                v-if="showClear && displayValue"
                type="button"
                class="absolute right-8 top-1/2 -translate-y-1/2 flex h-6 w-6 items-center justify-center rounded-full text-surface-400 hover:bg-surface-100 hover:text-surface-600 dark:hover:bg-surface-700"
                aria-label="Effacer"
                @click="clearSelection"
            >
                <i class="pi pi-times text-xs" />
            </button>
            <i
                v-if="showIcon"
                class="pi pi-calendar absolute right-3 top-1/2 -translate-y-1/2 text-surface-400 pointer-events-none"
            />
        </div>

        <Popover ref="panelRef" class="panel-date-picker__popover">
            <div class="flex flex-col gap-3 sm:flex-row sm:gap-0">
                <aside class="flex shrink-0 flex-row gap-1 overflow-x-auto border-b border-surface-200 pb-2 dark:border-surface-700 sm:w-44 sm:flex-col sm:overflow-visible sm:border-b-0 sm:border-r sm:pb-0 sm:pr-3">
                    <button
                        v-for="entry in periodEntries"
                        :key="entry.key"
                        type="button"
                        class="whitespace-nowrap rounded-lg px-3 py-2 text-left text-sm transition-colors"
                        :class="
                            activeKey === entry.key
                                ? 'bg-primary-500 font-medium text-white'
                                : 'text-surface-700 hover:bg-surface-100 dark:text-surface-200 dark:hover:bg-surface-800'
                        "
                        @click="selectPeriod(entry.key)"
                    >
                        {{ entry.label }}
                    </button>
                </aside>

                <div class="sm:pl-3">
                    <DatePicker
                        :modelValue="draftRange"
                        selectionMode="range"
                        inline
                        :manualInput="manualInput"
                        :dateFormat="dateFormat"
                        @update:modelValue="onCalendarUpdate"
                    />
                </div>
            </div>
        </Popover>
    </div>
</template>

<style scoped>
.panel-date-picker__trigger input:disabled {
    cursor: not-allowed;
}
</style>

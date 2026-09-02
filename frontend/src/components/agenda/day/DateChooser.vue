<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: Date,
        required: true
    },
    windowDays: {
        type: Number,
        default: 7
    }
});

const emit = defineEmits(['update:modelValue']);

const ITEM_WIDTH = 88; // largeur d'une date (px)
const containerRef = ref(null);
const containerWidth = ref(0);

const updateWidth = () => {
    if (containerRef.value) {
        containerWidth.value = containerRef.value.clientWidth;
    }
};

let resizeObserver;
onMounted(() => {
    updateWidth();
    resizeObserver = new ResizeObserver(updateWidth);
    resizeObserver.observe(containerRef.value);
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
});

const visibleCount = computed(() => {
    const count = Math.floor(containerWidth.value / ITEM_WIDTH);
    return count % 2 === 0 ? count - 1 : count; // toujours impair
});

const dates = computed(() => {
    const result = [];
    const half = Math.floor(visibleCount.value / 2);

    for (let i = -half; i <= half; i++) {
        const d = new Date(props.modelValue);
        d.setDate(d.getDate() + i);

        result.push({
            key: d.toISOString(),
            date: d,
            label: d.toLocaleDateString('fr-FR', {
                weekday: 'short',
                day: '2-digit',
                month: 'short'
            })
        });
    }
    return result;
});

const isSelected = (d) => d.getFullYear() === props.modelValue.getFullYear() && d.getMonth() === props.modelValue.getMonth() && d.getDate() === props.modelValue.getDate();

/**
 * Actions
 */
const selectDate = (d) => emit('update:modelValue', d);

const prevDay = () => {
    const d = new Date(props.modelValue);
    d.setDate(d.getDate() - 1);
    emit('update:modelValue', d);
};

const nextDay = () => {
    const d = new Date(props.modelValue);
    d.setDate(d.getDate() + 1);
    emit('update:modelValue', d);
};
</script>

<template>
    <div ref="containerRef" class="w-full select-none rounded-xl border border-gray-200 bg-white p-2 xs:p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-1 xs:gap-2">
            <button @click="prevDay" class="flex h-8 xs:h-9 w-8 xs:w-9 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">‹</button>
            <div class="relative flex flex-1 justify-center overflow-hidden">
                <transition-group name="date-slide" tag="div" class="flex gap-1 xs:gap-2">
                    <button
                        v-for="item in dates"
                        :key="item.key"
                        @click="selectDate(item.date)"
                        class="flex h-9 xs:h-10 w-[72px] xs:w-[88px] flex-col items-center justify-center rounded-lg text-xs xs:text-sm transition-all duration-300 dark:text-gray-300"
                        :class="isSelected(item.date) ? 'bg-primary-600 text-white shadow-md' : 'text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800'"
                    >
                        {{ item.label }}
                    </button>
                </transition-group>
            </div>
            <button @click="nextDay" class="flex h-8 xs:h-9 w-8 xs:w-9 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">›</button>
        </div>
    </div>
</template>

<style scoped>
.date-slide-enter-active {
    transition:
        transform 0.25s cubic-bezier(0.22, 1, 0.36, 1),
        opacity 0.2s ease;
}

.date-slide-leave-active {
    position: absolute;
}

.date-slide-enter-from {
    opacity: 0;
    transform: translateX(24px);
}

.date-slide-leave-to {
    opacity: 0;
    transform: translateX(-24px);
}
</style>

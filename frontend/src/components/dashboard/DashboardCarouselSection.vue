<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Carousel from 'primevue/carousel';
import Select from 'primevue/select';

const props = defineProps({
    slides: { type: Array, default: () => [] },
    title: { type: String, default: 'Statistiques detaillees' },
    subtitle: { type: String, default: 'Analyse complete de votre activite' },
    periodOptions: { type: Array, default: () => [] },
    selectedPeriod: { type: String, default: 'month' },
    loading: { type: Boolean, default: false }
});

const emit = defineEmits(['update:selectedPeriod']);

const currentSlide = ref(0);

const hasSlides = computed(() => props.slides.length > 0);

const nextSlide = () => {
    if (!props.slides.length) return;
    currentSlide.value = (currentSlide.value + 1) % props.slides.length;
};

const prevSlide = () => {
    if (!props.slides.length) return;
    currentSlide.value = (currentSlide.value - 1 + props.slides.length) % props.slides.length;
};

const updatePeriod = (value) => {
    emit('update:selectedPeriod', value);
};
</script>

<template>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-xl overflow-hidden border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
        <div class="px-3 xs:px-4 sm:px-5 md:px-6 py-2 xs:py-3 sm:py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 xs:gap-3">
                <div class="space-y-0.5 xs:space-y-1">
                    <h3 class="text-sm xs:text-base sm:text-lg font-semibold text-surface-900 dark:text-surface-100">{{ title }}</h3>
                    <p class="text-[10px] xs:text-xs sm:text-sm text-surface-600 dark:text-surface-400">{{ subtitle }}</p>
                </div>
                <div class="flex items-center gap-1 xs:gap-2 mt-2 sm:mt-0 shrink-0">
                    <Button icon="pi pi-chevron-left" severity="secondary" text size="small" :disabled="!hasSlides" @click="prevSlide" class="text-surface-600 dark:text-surface-400 hover:text-primary-600 dark:hover:text-primary-400 p-1 xs:p-2" />
                    <Button icon="pi pi-chevron-right" severity="secondary" text size="small" :disabled="!hasSlides" @click="nextSlide" class="text-surface-600 dark:text-surface-400 hover:text-primary-600 dark:hover:text-primary-400 p-1 xs:p-2" />
                </div>
            </div>
        </div>

        <div class="p-3 xs:p-4 sm:p-5 md:p-6">
            <div v-if="loading" class="space-y-3 xs:space-y-4 animate-pulse">
                <div class="h-4 xs:h-5 w-32 xs:w-40 rounded bg-surface-200/80 dark:bg-surface-700/70"></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 xs:gap-6">
                    <div class="space-y-2 xs:space-y-3">
                        <div class="h-14 xs:h-16 rounded bg-surface-200/80 dark:bg-surface-700/70"></div>
                        <div class="h-14 xs:h-16 rounded bg-surface-200/80 dark:bg-surface-700/70"></div>
                        <div class="h-14 xs:h-16 rounded bg-surface-200/80 dark:bg-surface-700/70"></div>
                    </div>
                    <div class="h-64 xs:h-72 rounded bg-surface-200/80 dark:bg-surface-700/70"></div>
                </div>
            </div>
            <div v-else-if="!hasSlides" class="text-xs xs:text-sm text-surface-500">Aucune statistique disponible.</div>

            <Carousel v-else :value="slides" :numVisible="1" :numScroll="1" v-model:page="currentSlide" :showIndicators="true" :autoplayInterval="8000" circular class="dashboard-carousel">
                <template #item="slotProps">
                    <div class="p-0.5 xs:p-1">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 xs:gap-6">
                            <div class="space-y-4 xs:space-y-6">
                                <div>
                                    <h4 class="text-base xs:text-lg sm:text-xl font-semibold text-surface-900 dark:text-surface-100 mb-3 xs:mb-4">{{ slotProps.data.title }}</h4>
                                    <div class="space-y-3 xs:space-y-4">
                                        <div
                                            v-for="(stat, index) in slotProps.data.stats"
                                            :key="index"
                                            class="flex items-center justify-between p-2 xs:p-3 sm:p-4 rounded-lg xs:rounded-xl bg-surface-50/50 dark:bg-surface-700/30 hover:bg-surface-100/50 dark:hover:bg-surface-700/50 transition-colors"
                                        >
                                            <div class="flex items-center gap-2 xs:gap-3">
                                                <div :class="['p-1 xs:p-2 rounded-md xs:rounded-lg', stat.color]">
                                                    <i :class="[stat.icon, 'text-base xs:text-lg']"></i>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-surface-700 dark:text-surface-300 text-xs xs:text-sm sm:text-base">{{ stat.label }}</p>
                                                    <p class="text-[10px] xs:text-xs sm:text-sm text-surface-500 dark:text-surface-400">{{ stat.description }}</p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-lg xs:text-xl sm:text-2xl font-bold text-surface-900 dark:text-surface-100">{{ stat.value }}</p>
                                                <div v-if="stat.trend" class="flex items-center gap-0.5 xs:gap-1 mt-0.5 xs:mt-1">
                                                    <i :class="[stat.trendIcon, stat.trendColor]"></i>
                                                    <span :class="['text-[10px] xs:text-[11px] sm:text-xs', stat.trendColor]">{{ stat.trend }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="slotProps.data.actions?.length" class="pt-3 xs:pt-4 border-t border-surface-200/50 dark:border-surface-700/50">
                                    <h5 class="font-medium text-surface-700 dark:text-surface-300 mb-2 xs:mb-3 text-xs xs:text-sm">Actions rapides</h5>
                                    <div class="flex flex-wrap gap-1 xs:gap-2">
                                        <Button
                                            v-for="(action, index) in slotProps.data.actions"
                                            :key="index"
                                            :icon="action.icon"
                                            :label="action.label"
                                            :as="action.to ? 'router-link' : undefined"
                                            :to="action.to"
                                            severity="secondary"
                                            outlined
                                            size="small"
                                            class="rounded-lg xs:rounded-xl text-xs xs:text-sm"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gradient-to-br from-surface-50 to-surface-0 dark:from-surface-800 dark:to-surface-900 rounded-lg xs:rounded-xl p-3 xs:p-4 sm:p-5 border border-surface-200/50 dark:border-surface-700/50">
                                <div class="flex items-center justify-between mb-3 xs:mb-4">
                                    <h5 class="font-semibold text-surface-900 dark:text-surface-100 text-xs xs:text-sm sm:text-base">{{ slotProps.data.chart?.title || 'Evolution' }}</h5>
                                    <Select
                                        v-if="periodOptions.length"
                                        :modelValue="selectedPeriod"
                                        :options="periodOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        class="w-24 xs:w-28 sm:w-32 text-[10px] xs:text-xs sm:text-sm rounded-md xs:rounded-lg border-surface-200 dark:border-surface-700"
                                        size="small"
                                        @update:modelValue="updatePeriod"
                                    />
                                </div>

                                <div class="h-48 xs:h-56 sm:h-64 flex items-center justify-center">
                                    <div class="relative w-full h-full">
                                        <div class="absolute bottom-0 left-0 right-0 h-40 xs:h-48 bg-gradient-to-t from-primary-500/10 to-transparent rounded-md xs:rounded-lg"></div>
                                        <div class="absolute inset-0 flex items-end justify-between gap-1 xs:gap-2 px-2 xs:px-3 sm:px-4 pb-6 xs:pb-8">
                                            <div
                                                v-for="(bar, index) in slotProps.data.chart?.bars || []"
                                                :key="index"
                                                class="w-3 xs:w-4 sm:w-5 rounded-t-md xs:rounded-t-lg bg-gradient-to-t from-primary-500 to-primary-400"
                                                :style="{ height: `${bar.height}%` }"
                                            ></div>
                                        </div>
                                        <div class="absolute bottom-0 left-0 right-0 flex justify-between px-2 xs:px-3 sm:px-4 text-[9px] xs:text-[10px] sm:text-xs text-surface-500">
                                            <span v-for="(label, idx) in slotProps.data.chart?.labels || []" :key="idx" class="max-w-[32px] xs:max-w-[42px] truncate">
                                                {{ label }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 xs:mt-6 pt-3 xs:pt-4 border-t border-surface-200/50 dark:border-surface-700/50">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-[10px] xs:text-xs sm:text-sm text-surface-500 dark:text-surface-400">{{ slotProps.data.summary?.label || 'Moyenne' }}</p>
                                            <p class="text-lg xs:text-xl sm:text-2xl font-bold text-surface-900 dark:text-surface-100">{{ slotProps.data.summary?.value || '--' }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[10px] xs:text-xs sm:text-sm text-surface-500 dark:text-surface-400">{{ slotProps.data.target?.label || 'Objectif' }}</p>
                                            <p class="text-lg xs:text-xl sm:text-2xl font-bold text-green-600 dark:text-green-400">{{ slotProps.data.target?.value || '--' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </Carousel>
        </div>
    </div>
</template>

<style scoped>
@media (max-width: 639px) {
    .dashboard-carousel :deep(.p-carousel-prev-button),
    .dashboard-carousel :deep(.p-carousel-next-button) {
        display: none;
    }
}
</style>

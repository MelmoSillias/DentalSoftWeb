<script setup>
import Button from 'primevue/button';

const props = defineProps({
    cards: { type: Array, default: () => [] },
    title: { type: String, default: 'Apercu rapide' },
    loading: { type: Boolean, default: false }
});
</script>

<template>
    <div class="mb-6 md:mb-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base sm:text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                <i class="pi pi-chart-line text-primary-500 text-sm sm:text-base"></i>
                {{ title }}
            </h3>
            <Button
                icon="pi pi-ellipsis-h"
                severity="secondary"
                text
                size="small"
                class="text-surface-600 dark:text-surface-400"
            />
        </div>

        <div class="relative">
            <div class="flex gap-4 pb-4 overflow-x-auto scrollbar-hide">
                <div
                    v-if="loading"
                    v-for="idx in 4"
                    :key="`loading-${idx}`"
                    class="min-w-[240px] sm:min-w-[280px] rounded-2xl p-4 sm:p-5 border border-surface-200/60 dark:border-surface-700/60 bg-surface-50/70 dark:bg-surface-800/70 animate-pulse"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex-1 space-y-3">
                            <div class="h-3 w-24 rounded bg-surface-200/80 dark:bg-surface-700/70"></div>
                            <div class="h-7 w-16 rounded bg-surface-200/80 dark:bg-surface-700/70"></div>
                            <div class="h-3 w-32 rounded bg-surface-200/80 dark:bg-surface-700/70"></div>
                        </div>
                        <div class="h-12 w-12 rounded-xl bg-surface-200/80 dark:bg-surface-700/70"></div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-surface-200/60 dark:border-surface-700/60">
                        <div class="h-3 w-28 rounded bg-surface-200/80 dark:bg-surface-700/70"></div>
                    </div>
                </div>

                <div
                    v-else
                    v-for="card in cards"
                    :key="card.id"
                    :class="[
                        'min-w-[240px] sm:min-w-[280px] rounded-2xl p-4 sm:p-5 border hover:shadow-lg transition-all duration-300 cursor-pointer group',
                        card.background,
                        card.border
                    ]"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p :class="['text-xs sm:text-sm font-medium', card.text]">{{ card.title }}</p>
                            <p :class="['text-2xl sm:text-3xl font-bold mt-2', card.valueColor || card.text]">{{ card.value }}</p>
                            <div v-if="card.subValue" class="flex items-center gap-2 mt-2">
                                <i v-if="card.subIcon" :class="[card.subIcon, card.subIconColor || 'text-surface-400', 'text-xs']"></i>
                                <span :class="['text-[11px] sm:text-xs', card.subColor || 'text-surface-500']">{{ card.subValue }}</span>
                            </div>
                        </div>
                        <div :class="['p-2.5 sm:p-3 rounded-xl group-hover:scale-110 transition-transform duration-300', card.iconBg]">
                            <i :class="[card.icon, 'text-xl sm:text-2xl', card.iconColor]"></i>
                        </div>
                    </div>
                    <div v-if="card.link" :class="['mt-4 pt-3 border-t', card.borderLight || card.border]">
                        <RouterLink
                            :to="card.link"
                            :class="['text-xs sm:text-sm hover:underline flex items-center gap-2', card.linkColor || card.text]"
                        >
                            {{ card.linkLabel || 'Voir' }}
                            <i class="pi pi-arrow-right text-xs"></i>
                        </RouterLink>
                    </div>
                </div>
            </div>

            <div v-if="!loading && !cards.length" class="text-sm text-surface-500 py-2">
                Aucun indicateur disponible.
            </div>

            <div class="flex justify-center gap-1 mt-4" v-if="!loading && cards.length">
                <div
                    v-for="idx in Math.min(cards.length, 3)"
                    :key="idx"
                    :class="idx === 1 ? 'bg-primary-500' : 'bg-surface-300 dark:bg-surface-600'"
                    class="w-2 h-2 rounded-full"
                ></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>

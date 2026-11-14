<script setup>
import { ref } from 'vue';
import Card from 'primevue/card';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';
import Button from 'primevue/button';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            pending: 0,
            validated: 0,
            postponed: 0,
            cancelled: 0
        })
    },
    loading: {
        type: Boolean,
        default: false
    }
});

const expanded = ref(false);

const items = [
    { key: 'pending', label: 'En attente', severity: 'info', color: 'text-primary-600' },
    { key: 'validated', label: 'Validés', severity: 'success', color: 'text-green-600' },
    { key: 'postponed', label: 'Reportés', severity: 'warn', color: 'text-orange-500' },
    { key: 'cancelled', label: 'Annulés', severity: 'danger', color: 'text-red-500' }
];
</script>

<template>
    <Card class="dark:bg-surface-900">
        <!-- Header Accordion -->
        <template #title>
            <div class="flex cursor-pointer items-center justify-between" @click="expanded = !expanded">
                <span class="text-base xs:text-lg font-semibold text-surface-800 dark:text-surface-50"> Statistiques des rendez-vous </span>

                <Button :icon="expanded ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" text rounded severity="secondary" />
            </div>
        </template>

        <!-- Content -->
        <template #content>
            <transition name="accordion" mode="out-in">
                <!-- Vue détaillée -->
                <div v-if="expanded" key="expanded" class="grid grid-cols-1 gap-3 xs:gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card
                        v-for="item in items"
                        :key="item.key"
                        class="border-l-4 shadow-sm dark:bg-surface-800"
                        :class="{
                            'border-primary-500': item.severity === 'info',
                            'border-green-500': item.severity === 'success',
                            'border-orange-500': item.severity === 'warn',
                            'border-red-500': item.severity === 'danger'
                        }"
                    >
                        <template #content>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs xs:text-sm text-surface-600 dark:text-surface-300">
                                        {{ item.label }}
                                    </p>

                                    <Skeleton v-if="loading" width="3rem xs:4rem" height="2rem xs:2.5rem" borderRadius="8px" />
                                    <p v-else class="text-3xl xs:text-4xl font-bold" :class="item.color">
                                        {{ props.stats[item.key] ?? 0 }}
                                    </p>
                                </div>

                                <Tag :severity="item.severity" />
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Vue miniature -->
                <div v-else key="collapsed" class="grid grid-cols-2 gap-2 xs:gap-3 sm:grid-cols-4">
                    <div v-for="item in items" :key="item.key" class="flex items-center justify-between rounded-lg bg-surface-100 p-2 xs:p-3 shadow-sm dark:bg-surface-800">
                        <Skeleton v-if="loading" width="2rem xs:2.5rem" height="1.5rem xs:1.75rem" borderRadius="6px" />
                        <span v-else class="text-xl xs:text-2xl font-semibold" :class="item.color">
                            {{ props.stats[item.key] ?? 0 }}
                        </span>

                        <Tag :severity="item.severity" />
                    </div>
                </div>
            </transition>
        </template>
    </Card>
</template>

<style scoped>
.accordion-enter-active,
.accordion-leave-active {
    transition: all 0.25s cubic-bezier(0.22, 1, 0.36, 1);
}

.accordion-enter-from {
    opacity: 0;
    transform: translateY(-8px);
}

.accordion-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>

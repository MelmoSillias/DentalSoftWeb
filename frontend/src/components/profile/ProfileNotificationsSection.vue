<script setup>
import Button from 'primevue/button';
import SelectButton from 'primevue/selectbutton';
import ConfirmPopup from 'primevue/confirmpopup';
import { computed } from 'vue';
import { useConfirm } from 'primevue/useconfirm';

const props = defineProps({
    notifications: { type: Array, default: () => [] },
    unreadCount: { type: Number, default: 0 },
    loading: { type: Boolean, default: false },
    filter: { type: String, default: 'all' },
    notificationsEnabled: { type: Boolean, default: true }
});

const emit = defineEmits(['filter-change', 'mark-read', 'mark-all', 'notifications-enabled-change']);
const confirm = useConfirm();

const filterOptions = [
    { label: 'Toutes', value: 'all' },
    { label: 'Lues', value: 'read' },
    { label: 'Non lues', value: 'unread' }
];

const filteredItems = computed(() => props.notifications);

const markAll = (event) => {
    if (!props.notifications.length) return;
    confirm.require({
        target: event.currentTarget,
        message: 'Marquer toutes les notifications comme lues ?',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: () => emit('mark-all')
    });
};
</script>

<template>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-surface-200/50 dark:border-surface-700/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <p class="text-[11px] sm:text-xs uppercase tracking-wider text-surface-500">Centre d'alertes</p>
                <h3 class="text-base sm:text-lg font-semibold text-surface-900 dark:text-surface-100">Notifications</h3>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] sm:text-xs text-surface-500">{{ unreadCount }} non lues</span>
                <Button label="Tout lire" icon="pi pi-check" size="small" outlined :disabled="!notifications.length" @click="markAll" />
            </div>
        </div>
        <div class="p-4 sm:p-5 flex flex-col gap-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <span class="text-xs text-surface-600">Réception des notifications</span>
                <SelectButton
                    :modelValue="notificationsEnabled"
                    :options="[{ label: 'Activées', value: true }, { label: 'Désactivées', value: false }]"
                    optionLabel="label"
                    optionValue="value"
                    :allowEmpty="false"
                    @update:modelValue="emit('notifications-enabled-change', $event)"
                />
            </div>

            <SelectButton
                :options="filterOptions"
                optionLabel="label"
                optionValue="value"
                :modelValue="filter"
                class="w-full sm:w-auto"
                :disabled="!notificationsEnabled"
                @update:modelValue="emit('filter-change', $event)"
            />

            <div v-if="!notificationsEnabled" class="text-sm text-surface-500">Notifications désactivées pour ce compte.</div>

            <div v-else-if="loading" class="text-sm text-surface-500">Chargement...</div>

            <div v-else class="space-y-3">
                <div v-for="item in filteredItems" :key="item.id" class="p-3 sm:p-4 rounded-xl border border-surface-200/50 dark:border-surface-700/50 hover:border-primary-300/50 transition">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div>
                            <div class="font-medium text-surface-900 dark:text-surface-100 text-sm sm:text-base">{{ item.message }}</div>
                            <div class="text-[11px] sm:text-xs text-surface-500 mt-1">{{ item.createdAt || '--' }}</div>
                        </div>
                        <div class="flex items-center gap-2 sm:mt-0">
                            <span class="text-[11px] sm:text-xs px-2 py-1 rounded-full" :class="item.status === 'non_vu' ? 'bg-amber-100 text-amber-700' : 'bg-surface-100 text-surface-600'">
                                {{ item.status === 'non_vu' ? 'Non lu' : 'Lu' }}
                            </span>
                            <Button v-if="item.status === 'non_vu'" icon="pi pi-check" size="small" text @click="emit('mark-read', [item.id])" />
                        </div>
                    </div>
                    <div v-if="item.link" class="text-[11px] sm:text-xs text-primary-600 mt-2 break-all">
                        {{ item.link }}
                    </div>
                </div>

                <div v-if="!filteredItems.length" class="text-center text-surface-500">Aucune notification.</div>
            </div>
        </div>
    </div>
    <ConfirmPopup />
</template>

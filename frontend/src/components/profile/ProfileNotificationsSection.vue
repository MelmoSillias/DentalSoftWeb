<script setup>
import { ref, computed, watch } from 'vue';
import { formatDistanceToNow, parseISO } from 'date-fns';
import fr from 'date-fns/locale/fr';

// PrimeVue
import Button from 'primevue/button';
import SelectButton from 'primevue/selectbutton';
import ConfirmPopup from 'primevue/confirmpopup';
import Badge from 'primevue/badge';
import Avatar from 'primevue/avatar';
import Paginator from 'primevue/paginator';
import { useConfirm } from 'primevue/useconfirm';

const props = defineProps({
    notifications: { type: Array, default: () => [] },
    unreadCount: { type: Number, default: 0 },
    loading: { type: Boolean, default: false },
    filter: { type: String, default: 'all' },
    notificationsEnabled: { type: Boolean, default: true },
    desktopNotificationsEnabled: { type: Boolean, default: false },
    desktopNotificationsSupported: { type: Boolean, default: false },
    desktopPermission: { type: String, default: 'default' }
});

const emit = defineEmits(['filter-change', 'mark-read', 'mark-all', 'notifications-enabled-change', 'desktop-notifications-change']);

const confirm = useConfirm();

// Options de filtre
const filterOptions = [
    { label: 'Toutes', value: 'all' },
    { label: 'Lues', value: 'read' },
    { label: 'Non lues', value: 'unread' }
];

// Filtrage des notifications
const filteredItems = computed(() => {
    if (!props.notifications.length) return [];

    if (props.filter === 'read') {
        return props.notifications.filter((n) => n.status !== 'non_vu');
    }
    if (props.filter === 'unread') {
        return props.notifications.filter((n) => n.status === 'non_vu');
    }
    return props.notifications;
});

// Pagination
const rowsPerPageOptions = [
    { label: '5', value: 5 },
    { label: '10', value: 10 },
    { label: '20', value: 20 }
];
const currentPage = ref(1);
const rowsPerPage = ref(5);

const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value;
    return filteredItems.value.slice(start, start + rowsPerPage.value);
});

const onPageChange = (event) => {
    currentPage.value = Math.floor(event.first / event.rows) + 1;
    rowsPerPage.value = event.rows;
};

// Réinitialiser la pagination quand le filtre ou l'état change
watch([() => props.filter, () => props.notificationsEnabled], () => {
    currentPage.value = 1;
});

// Fonctions utilitaires
const formatRelativeDate = (dateString) => {
    if (!dateString) return 'Date inconnue';
    try {
        const date = parseISO(dateString);
        return formatDistanceToNow(date, { addSuffix: true, locale: fr });
    } catch {
        return dateString;
    }
};

const getNotificationIcon = (notif) => {
    if (notif.type === 'success') return 'pi pi-check-circle';
    if (notif.type === 'warning') return 'pi pi-exclamation-triangle';
    if (notif.type === 'error') return 'pi pi-times-circle';
    return 'pi pi-bell';
};

const shortenLink = (link) => {
    if (!link) return '';
    const maxLength = 40;
    return link.length > maxLength ? link.substring(0, maxLength) + 'â€¦' : link;
};

// Marquer tout comme lu
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
    <div class="overflow-hidden rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800/80 shadow-sm transition-all">
        <!-- En-tête -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 px-5 py-4 border-b border-surface-100 dark:border-surface-700/60">
            <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 shadow-sm">
                    <i class="pi pi-bell text-white text-sm"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-surface-400">Centre d'alertes</p>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-semibold text-surface-900 dark:text-surface-50">Notifications</h3>
                        <Badge v-if="unreadCount > 0" :value="unreadCount" severity="warn" />
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <span class="text-xs text-surface-400">{{ unreadCount }} non lue{{ unreadCount > 1 ? 's' : '' }}</span>
                <Button label="Tout lire" icon="pi pi-check-square" size="small" outlined severity="secondary" :disabled="!notifications.length" @click="markAll" />
            </div>
        </div>

        <!-- Corps -->
        <div class="p-5 space-y-4">
            <!-- Toggle + Filtres -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-surface-100 dark:border-surface-700/50 bg-surface-50/60 dark:bg-surface-700/20 px-4 py-3">
                <div class="flex items-center gap-2.5 text-sm text-surface-700 dark:text-surface-300">
                    <i class="pi pi-bell text-primary-500"></i>
                    <span>Reception des notifications</span>
                </div>
                <SelectButton
                    :modelValue="notificationsEnabled"
                    :options="[
                        { label: 'Activees', value: true },
                        { label: 'Desactivees', value: false }
                    ]"
                    optionLabel="label"
                    optionValue="value"
                    :allowEmpty="false"
                    @update:modelValue="emit('notifications-enabled-change', $event)"
                />
            </div>

            <div v-if="desktopNotificationsSupported" class="flex flex-col gap-3 rounded-xl border border-surface-100 dark:border-surface-700/50 bg-surface-50/60 dark:bg-surface-700/20 px-4 py-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 text-sm text-surface-700 dark:text-surface-300">
                        <i class="pi pi-desktop text-primary-500"></i>
                        <span>Notifications systeme</span>
                    </div>
                    <SelectButton
                        :modelValue="desktopNotificationsEnabled"
                        :options="[
                            { label: 'Activees', value: true },
                            { label: 'Desactivees', value: false }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        :allowEmpty="false"
                        :disabled="!notificationsEnabled || desktopPermission === 'denied'"
                        @update:modelValue="emit('desktop-notifications-change', $event)"
                    />
                </div>
                <p v-if="desktopPermission === 'denied'" class="text-xs text-amber-600 dark:text-amber-400">Autorisez les notifications dans les parametres de votre navigateur pour activer cette option.</p>
                <p v-else class="text-xs text-surface-400">Affiche une alerte OS quand l'onglet est en arriere-plan ou l'application est minimisee.</p>
            </div>

            <SelectButton :options="filterOptions" optionLabel="label" optionValue="value" :modelValue="filter" class="w-full sm:w-auto" :disabled="!notificationsEnabled" @update:modelValue="emit('filter-change', $event)" />

            <!-- Desactivees -->
            <div v-if="!notificationsEnabled" class="flex flex-col items-center justify-center py-10 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-700 mb-3">
                    <i class="pi pi-ban text-2xl text-surface-400 opacity-50"></i>
                </div>
                <p class="text-sm font-medium text-surface-500">Notifications desactivees</p>
                <p class="text-xs text-surface-400 mt-1">Activez-les pour recevoir des alertes</p>
            </div>

            <!-- Chargement -->
            <div v-else-if="loading" class="space-y-3">
                <div v-for="i in 3" :key="i" class="flex items-start gap-4 p-4 rounded-xl border border-surface-100 dark:border-surface-700 animate-pulse">
                    <div class="w-10 h-10 rounded-full bg-surface-200 dark:bg-surface-700 shrink-0"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-4 bg-surface-200 dark:bg-surface-700 rounded w-3/4"></div>
                        <div class="h-3 bg-surface-100 dark:bg-surface-800 rounded w-1/2"></div>
                    </div>
                </div>
            </div>

            <!-- Liste -->
            <div v-else>
                <TransitionGroup name="notif-list" tag="div" class="space-y-2.5">
                    <div
                        v-for="notif in paginatedItems"
                        :key="notif.id"
                        class="group flex items-start gap-3 rounded-xl border px-4 py-3 transition-all duration-200 hover:shadow-sm"
                        :class="[notif.status === 'non_vu' ? 'border-amber-200/70 bg-amber-50/40 dark:border-amber-800/40 dark:bg-amber-950/10' : 'border-surface-100 dark:border-surface-700/50 bg-surface-50/40 dark:bg-surface-700/20']"
                    >
                        <!-- Icone -->
                        <div class="relative mt-0.5 shrink-0">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full" :class="notif.status === 'non_vu' ? 'bg-amber-100 dark:bg-amber-900/40' : 'bg-surface-200 dark:bg-surface-700'">
                                <i :class="[getNotificationIcon(notif), 'text-sm', notif.status === 'non_vu' ? 'text-amber-600 dark:text-amber-400' : 'text-surface-400']"></i>
                            </div>
                            <span v-if="notif.status === 'non_vu'" class="absolute -top-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-amber-500 border-2 border-surface-0 dark:border-surface-800"></span>
                        </div>

                        <!-- Contenu -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-surface-800 dark:text-surface-100 break-words leading-snug">{{ notif.message }}</p>
                            <div class="flex flex-wrap items-center gap-x-3 mt-1.5">
                                <span class="text-xs text-surface-400 flex items-center gap-1">
                                    <i class="pi pi-clock text-[10px]"></i>
                                    {{ formatRelativeDate(notif.createdAt) }}
                                </span>
                                <a v-if="notif.link" :href="notif.link" target="_blank" rel="noopener noreferrer" class="text-xs text-primary-500 hover:underline truncate max-w-[180px] flex items-center gap-1">
                                    <i class="pi pi-link text-[10px]"></i>
                                    {{ shortenLink(notif.link) }}
                                </a>
                            </div>
                        </div>

                        <!-- Action -->
                        <div class="shrink-0 flex flex-col items-end gap-1.5">
                            <span
                                class="text-[10px] px-2 py-0.5 rounded-full font-medium"
                                :class="notif.status === 'non_vu' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' : 'bg-surface-100 text-surface-400 dark:bg-surface-700 dark:text-surface-500'"
                            >
                                {{ notif.status === 'non_vu' ? 'Non lue' : 'Lue' }}
                            </span>
                            <button
                                v-if="notif.status === 'non_vu'"
                                class="rounded-lg px-2 py-1 text-xs text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-900/20 transition-colors flex items-center gap-1"
                                @click="emit('mark-read', [notif.id])"
                            >
                                <i class="pi pi-check text-[10px]"></i> Lu
                            </button>
                        </div>
                    </div>
                </TransitionGroup>

                <!-- Pagination -->
                <div v-if="filteredItems.length" class="flex flex-col sm:flex-row items-center justify-between gap-4 mt-5 pt-4 border-t border-surface-100 dark:border-surface-700/50">
                    <div class="flex items-center gap-2 text-xs text-surface-500">
                        <span>Par page :</span>
                        <SelectButton v-model="rowsPerPage" :options="rowsPerPageOptions" optionLabel="label" optionValue="value" size="small" />
                    </div>
                    <Paginator
                        :rows="rowsPerPage"
                        :totalRecords="filteredItems.length"
                        :first="(currentPage - 1) * rowsPerPage"
                        @page="onPageChange"
                        template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink"
                        class="bg-transparent border-0 p-0"
                    />
                </div>

                <!-- Vide -->
                <div v-else class="flex flex-col items-center justify-center py-12 text-surface-400">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-700 mb-3">
                        <i class="pi pi-inbox text-2xl opacity-40"></i>
                    </div>
                    <p class="text-sm font-medium text-surface-500">Aucune notification</p>
                    <p class="text-xs text-surface-400 mt-1">Les nouvelles alertes apparaitront ici</p>
                </div>
            </div>
        </div>

        <ConfirmPopup />
    </div>
</template>

<style scoped>
.notif-list-enter-active,
.notif-list-leave-active {
    transition: all 0.25s ease;
}
.notif-list-enter-from {
    opacity: 0;
    transform: translateY(-8px);
}
.notif-list-leave-to {
    opacity: 0;
    transform: translateX(-12px);
}
</style>

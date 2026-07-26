<script setup>
import { ref, computed, nextTick, onBeforeUnmount, onMounted, watch } from 'vue';
import { activateAdminTourMock, deactivateAdminTourMock, resetAdminTourMockData } from '@/services/adminTourMock';
import Breadcrumb from 'primevue/breadcrumb';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext'; 
import Select from 'primevue/select';
import TextArea from 'primevue/textarea';
import Toast from 'primevue/toast';
import ConfirmPopup from 'primevue/confirmpopup';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { useUsers } from '@/composables/useUsers';
import { useNotifications } from '@/composables/useNotifications';
import { useGuidedTour } from '@/composables/useGuidedTour';

const toast = useToast();
const confirm = useConfirm();
let guidedTourPageState = null;
let guidedTourDemoActive = false;
let guidedTourCleanupPromise = null;

const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
const breadcrumbItems = [{ label: 'Administration' }, { label: 'Notifications' }];

const { users, fetchUsers, loading: usersLoading } = useUsers();
const { sendNotification, loading: sending, error: notifyError } = useNotifications();

const search = ref('');
const selectedSingleUser = ref(null); // radio selection
const selectedByType = ref([]); // types selected for bulk
const selectedRecipients = ref(new Set()); // set of user ids

const message = ref('');
const priority = ref('normal');
const link = ref('');

const types = computed(() => {
    const s = new Set();
    (users.value || []).forEach(u => { if (u.type) s.add(u.type); if (u.fonction) s.add(u.fonction); });
    return Array.from(s).sort();
});

const filteredUsers = computed(() => {
    const q = (search.value || '').toLowerCase().trim();
    return (users.value || []).filter(u => {
        const name = (u.username || '').toLowerCase();
        const emp = ((u.employee && (u.employee.nom || '') + ' ' + (u.employee.prenom || '')) || (u.employe && (u.employe.nom || '') + ' ' + (u.employe.prenom || '')) || '').toLowerCase();
        return !q || name.includes(q) || emp.includes(q);
    });
});

const recipientIds = computed(() => {
    const selected = selectedSingleUser.value ? [selectedSingleUser.value] : [];
    return Array.from(new Set([...selected, ...Array.from(selectedRecipients.value)]));
});

const selectedCount = computed(() => recipientIds.value.length);

const canSubmit = computed(() => Boolean(message.value?.trim()) && selectedCount.value > 0);
const hasPreview = computed(() => Boolean(message.value));

const cloneValue = (value) => {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
};

const load = async () => {
    try {
        await fetchUsers();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: e?.message || 'Impossible de charger les utilisateurs', life: 4000 });
    }
};

const capturePageState = () => ({
    users: cloneValue(users.value),
    search: search.value,
    selectedSingleUser: selectedSingleUser.value,
    selectedByType: cloneValue(selectedByType.value),
    selectedRecipients: Array.from(selectedRecipients.value),
    message: message.value,
    priority: priority.value,
    link: link.value
});

const restorePageState = async (state) => {
    if (!state) return;
    users.value = cloneValue(state.users) || [];
    search.value = state.search || '';
    selectedSingleUser.value = state.selectedSingleUser || null;
    selectedByType.value = cloneValue(state.selectedByType) || [];
    selectedRecipients.value = new Set(state.selectedRecipients || []);
    message.value = state.message || '';
    priority.value = state.priority || 'normal';
    link.value = state.link || '';
    await nextTick();
};

const prepareGuidedTourDemo = async () => {
    guidedTourPageState = capturePageState();
    activateAdminTourMock();
    resetAdminTourMockData();
    guidedTourDemoActive = true;
    await load();
    search.value = '';
    selectedSingleUser.value = 702;
    selectedByType.value = ['Réception'];
    selectedRecipients.value = new Set([701, 703, 704]);
    message.value = 'Le cabinet ferme exceptionnellement à 16h30. Merci de clôturer vos tâches prioritaires avant cette heure.';
    priority.value = 'high';
    link.value = 'https://dentalsoft.local/annonces/fermeture-anticipee';
    await nextTick();
};

const cleanupGuidedTourDemo = async () => {
    if (!guidedTourDemoActive) {
        return;
    }

    if (guidedTourCleanupPromise) {
        return guidedTourCleanupPromise;
    }

    guidedTourCleanupPromise = (async () => {
        deactivateAdminTourMock();
        guidedTourDemoActive = false;
        const stateToRestore = guidedTourPageState;
        guidedTourPageState = null;
        await restorePageState(stateToRestore);
    })().finally(() => {
        guidedTourCleanupPromise = null;
    });

    return guidedTourCleanupPromise;
};

const addTypeUsers = (type) => {
    const list = (users.value || []).filter(u => (u.type === type) || (u.fonction === type));
    list.forEach(u => selectedRecipients.value.add(u.id));
};

const removeRecipient = (id) => {
    selectedRecipients.value.delete(id);
    if (selectedSingleUser.value === id) selectedSingleUser.value = null;
};

const toggleRecipient = (id) => {
    if (selectedRecipients.value.has(id)) selectedRecipients.value.delete(id);
    else selectedRecipients.value.add(id);
};

const submit = (event) => {
    if (!canSubmit.value) {
        toast.add({ severity: 'warn', summary: 'Attention', detail: 'Renseignez un message et sélectionnez au moins un destinataire.', life: 3500 });
        return;
    }

    confirm.require({
        target: event?.currentTarget,
        message: `Envoyer à ${selectedCount.value} destinataire(s) ?`,
        icon: 'pi pi-envelope',
        acceptLabel: 'Envoyer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                const payload = {
                    recipients: recipientIds.value,
                    priority: priority.value,
                    message: message.value.trim(),
                    link: link.value || null
                };
                const res = await sendNotification(payload);
                toast.add({ severity: 'success', summary: 'Envoyé', detail: (res?.sent ?? 0) + ' notification(s) envoyée(s)', life: 4000 });
                // reset form
                message.value = '';
                link.value = '';
                selectedRecipients.value = new Set();
                selectedSingleUser.value = null;
            } catch (err) {
                const detail = err?.response?.data?.error || err?.message || 'Échec envoi';
                toast.add({ severity: 'error', summary: 'Erreur', detail, life: 5000 });
            }
        }
    });
};

 function getPriorityIcon(priority) {
            const icons = {
                'low': 'pi pi-flag',
                'normal': 'pi pi-flag-fill',
                'high': 'pi pi-exclamation-triangle'
            };
            return icons[priority] || 'pi pi-flag';
        }
        
        function getPriorityLabel(priority) {
            const labels = {
                'low': 'Faible',
                'normal': 'Normal',
                'high': 'Haute'
            };
            return labels[priority] || 'Normal';
        }
        
        function getInitials(id) {
            const user = users.value.find(u => u.id === id);
            if (!user) return '?';
            const nom = user.employee?.nom || user.employe?.nom || '';
            const prenom = user.employee?.prenom || user.employe?.prenom || '';
            if (nom || prenom) return `${nom.charAt(0)}${prenom.charAt(0)}`.toUpperCase();
            return user.username?.charAt(0).toUpperCase() || '?';
        }
        
        function getUserName(id) {
            const user = users.value.find(u => u.id === id);
            if (!user) return 'Utilisateur inconnu';
            return user.username || `Utilisateur ${id}`;
        }
        
        function clearSelection() {
            selectedSingleUser.value = null;
            selectedRecipients.value.clear();
        }

watch(selectedByType, (nv) => {
    // when types change, add corresponding users
    nv.forEach(t => addTypeUsers(t));
});

useGuidedTour({
    routeName: 'administration-notifications',
    prepareDemo: prepareGuidedTourDemo,
    cleanupDemo: cleanupGuidedTourDemo,
    errorMessage: 'Impossible de lancer le tour des notifications.'
});

onMounted(() => {
    load();
});

onBeforeUnmount(() => {
    deactivateAdminTourMock();
    guidedTourDemoActive = false;
});
</script>

<template>
    <section class="min-h-screen p-4 md:p-6 lg:p-8 transition-colors duration-300">
        <AppToast />
        <ConfirmPopup />
        
        <!-- Header -->
        <div class="mb-6 md:mb-8" data-tour="admin-notifications.header">
            <div class="mb-6">
                <div class="inline-flex items-center gap-3 mb-4 p-3 rounded-2xl bg-surface-0/80 dark:bg-surface-800/80 backdrop-blur-sm border border-surface-200/50 dark:border-surface-700/50">
                    <div class="p-2.5 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600">
                        <i class="pi pi-envelope text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-surface-900 dark:text-surface-50">Envoyer une notification</h1>
                        <p class="text-sm text-surface-600 dark:text-surface-300">Communiquez avec vos collaborateurs</p>
                    </div>
                </div>
                <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="text-sm" />
            </div>

            <!-- Action Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-gradient-to-r from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 shadow-sm border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm" data-tour="admin-notifications.action-bar">
                <div class="space-y-1">
                    <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Configuration de l'envoi</h2>
                    <p class="text-sm text-surface-600 dark:text-surface-300">
                        <span class="font-medium text-primary-600 dark:text-primary-400">{{ selectedCount }}</span> destinataire(s) sélectionné(s)
                    </p>
                </div>
                <Button 
                    :label="`Envoyer à ${selectedCount} destinataire(s)`" 
                    icon="pi pi-send" 
                    :loading="sending"
                    @click="submit"
                    data-tour="admin-notifications.send"
                    class="shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white px-6 py-3 rounded-xl font-medium min-w-[200px]"
                    :disabled="!canSubmit || sending"
                />
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Panel - User Selection -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Search Section -->
                <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm" data-tour="admin-notifications.users">
                    <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                            <i class="pi pi-users text-primary-500"></i>
                            Sélection des destinataires
                        </h3>
                    </div>
                    <div class="p-5">
                        <div class="relative mb-5">
                            <IconField>
                            <InputIcon class="pi pi-search" />
                            <InputText 
                                v-model="search" 
                                placeholder="Rechercher un utilisateur par nom, prénom ou identifiant..." 
                                class="w-full pl-11 pr-4 py-3.5 rounded-xl border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-700/50 focus:ring-2 focus:ring-primary-500/20 transition-all"
                            /></IconField>
                        </div>

                        <!-- User List -->
                        <div class="rounded-xl border border-surface-200/50 dark:border-surface-700/50 overflow-hidden">
                            <div class="max-h-[400px] overflow-y-auto">
                                <div 
                                    v-for="u in filteredUsers" 
                                    :key="u.id" 
                                    class="p-4 border-b border-surface-100 dark:border-surface-800 hover:bg-surface-50/50 dark:hover:bg-surface-700/30 transition-colors"
                                    :class="{
                                        'bg-primary-50/30 dark:bg-primary-900/20': selectedSingleUser === u.id || selectedRecipients.has(u.id)
                                    }"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="relative">
                                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-semibold">
                                                    {{ (u.employee?.nom || u.employe?.nom)?.charAt(0) || u.username?.charAt(0) }}
                                                </div>
                                                <div v-if="selectedSingleUser === u.id || selectedRecipients.has(u.id)" 
                                                    class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-primary-500 flex items-center justify-center">
                                                    <i class="pi pi-check text-white text-xs"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-semibold text-surface-900 dark:text-surface-100">{{ u.username }}</div>
                                                <div class="text-sm text-surface-600 dark:text-surface-400">
                                                    {{ (u.employee?.nom || u.employe?.nom) ? `${u.employee?.nom || u.employe?.nom} ${u.employee?.prenom || u.employe?.prenom}` : 'Utilisateur sans profil' }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <div class="flex items-center">
                                                <label class="relative inline-flex items-center cursor-pointer mr-4">
                                                    <input 
                                                        type="radio" 
                                                        :value="u.id" 
                                                        v-model="selectedSingleUser"
                                                        class="sr-only peer"
                                                    >
                                                    <div class="w-5 h-5 border-2 border-surface-300 dark:border-surface-600 rounded-full peer-checked:border-primary-500 flex items-center justify-center">
                                                        <div v-if="selectedSingleUser === u.id" class="w-2.5 h-2.5 rounded-full bg-primary-500"></div>
                                                    </div>
                                                </label>
                                            </div>
                                            <div class="flex items-center">
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input 
                                                        type="checkbox" 
                                                        :checked="selectedRecipients.has(u.id)" 
                                                        @change.prevent.stop="toggleRecipient(u.id)"
                                                        class="sr-only peer"
                                                    >
                                                    <div class="w-5 h-5 border-2 border-surface-300 dark:border-surface-600 rounded-md peer-checked:border-primary-500 peer-checked:bg-primary-500 flex items-center justify-center transition-colors">
                                                        <i v-if="selectedRecipients.has(u.id)" class="pi pi-check text-white text-xs"></i>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Selection by Type -->
                <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm" data-tour="admin-notifications.types">
                    <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                            <i class="pi pi-tags text-primary-500"></i>
                            Sélection rapide par type
                        </h3>
                    </div>
                    <div class="p-5">
                        <div class="flex flex-wrap gap-2 mb-4">
                            <Button 
                                v-for="t in types" 
                                :key="t" 
                                :label="t" 
                                @click="addTypeUsers(t)" 
                                icon="pi pi-plus"
                                severity="secondary"
                                outlined
                                class="rounded-xl border-surface-300 dark:border-surface-600 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
                            />
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-surface-600 dark:text-surface-400">
                                <i class="pi pi-info-circle text-primary-500 mr-2"></i>
                                {{ types.length }} type(s) détecté(s) dans la base
                            </span>
                            <Button 
                                label="Sélectionner tous" 
                                icon="pi pi-check-double" 
                                severity="secondary" 
                                text
                                size="small"
                                class="text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel - Message & Settings -->
            <div class="space-y-6">
                <!-- Message Content -->
                <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm" data-tour="admin-notifications.message">
                    <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                            <i class="pi pi-comment text-primary-500"></i>
                            Contenu du message
                        </h3>
                    </div>
                    <div class="p-5 space-y-5">
                        <!-- Priority -->
                        <div>
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2 flex items-center gap-2">
                                <i class="pi pi-flag text-surface-400"></i>
                                Priorité de la notification
                            </label>
                            <Select 
                                v-model="priority" 
                                :options="[
                                    {label:'Faible', value:'low', icon:'pi pi-flag'},
                                    {label:'Normal', value:'normal', icon:'pi pi-flag-fill'},
                                    {label:'Haute', value:'high', icon:'pi pi-exclamation-triangle'}
                                ]" 
                                optionLabel="label" 
                                optionValue="value"
                                class="w-full rounded-xl border-surface-200 dark:border-surface-700 [&_.p-dropdown]:p-3.5"
                                placeholder="Sélectionnez une priorité"
                            >
                                <template #value="slotProps">
                                    <div v-if="slotProps.value" class="flex items-center gap-2">
                                        <i :class="getPriorityIcon(slotProps.value)"></i>
                                        <span>{{ getPriorityLabel(slotProps.value) }}</span>
                                    </div>
                                </template>
                                <template #option="slotProps">
                                    <div class="flex items-center gap-2">
                                        <i :class="slotProps.option.icon"></i>
                                        <span>{{ slotProps.option.label }}</span>
                                    </div>
                                </template>
                            </Select>
                        </div>

                        <!-- Message -->
                        <div>
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2 flex items-center gap-2">
                                <i class="pi pi-file-edit text-surface-400"></i>
                                Message
                            </label>
                            <TextArea 
                                v-model="message" 
                                rows="8" 
                                placeholder="Rédigez votre message ici..." 
                                class="w-full p-3.5 rounded-xl border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-700/50 focus:ring-2 focus:ring-primary-500/20 transition-all resize-none"
                                :autoResize="true"
                            />
                            <div class="flex justify-between items-center mt-2 text-sm text-surface-500 dark:text-surface-400">
                                <span>
                                    <i class="pi pi-info-circle mr-1"></i>
                                    Le message sera envoyé à tous les destinataires sélectionnés
                                </span>
                                <span class="font-mono">{{ message.length }}/1000</span>
                            </div>
                        </div>

                        <!-- Link -->
                        <div>
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2 flex items-center gap-2">
                                <i class="pi pi-link text-surface-400"></i>
                                Lien associé (optionnel)
                            </label>
                            <InputText 
                                v-model="link" 
                                placeholder="https://example.com/page" 
                                class="w-full p-3.5 rounded-xl border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-700/50 focus:ring-2 focus:ring-primary-500/20 transition-all"
                            />
                        </div>

                        <!-- Preview -->
                        <div v-if="message" class="mt-6 pt-5 border-t border-surface-200/50 dark:border-surface-700/50" data-tour="admin-notifications.preview">
                            <h4 class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-3 flex items-center gap-2">
                                <i class="pi pi-eye text-surface-400"></i>
                                Aperçu
                            </h4>
                            <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/50 border border-surface-200/50 dark:border-surface-700/50">
                                <div class="flex items-start gap-3 mb-3">
                                    <div :class="[
                                        'p-2 rounded-lg',
                                        priority === 'high' ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300' :
                                        priority === 'low' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300' :
                                        'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300'
                                    ]">
                                        <i :class="getPriorityIcon(priority)"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium text-surface-900 dark:text-surface-100">Notification système</div>
                                        <div class="text-xs text-surface-500 dark:text-surface-400">À l'instant</div>
                                    </div>
                                </div>
                                <p class="text-surface-700 dark:text-surface-300 whitespace-pre-wrap">{{ message }}</p>
                                <div v-if="link" class="mt-3 pt-3 border-t border-surface-200/50 dark:border-surface-700/50">
                                    <a :href="link" target="_blank" class="inline-flex items-center gap-2 text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300">
                                        <i class="pi pi-external-link"></i>
                                        <span class="truncate">{{ link }}</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Selected Recipients -->
                <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm" data-tour="admin-notifications.recipients">
                    <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                            <i class="pi pi-check-circle text-primary-500"></i>
                            Destinataires sélectionnés
                            <span class="ml-2 px-2.5 py-0.5 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 text-sm font-medium">
                                {{ selectedCount }}
                            </span>
                        </h3>
                    </div>
                    <div class="p-5">
                        <div v-if="selectedCount === 0" class="text-center py-8">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-surface-100 dark:bg-surface-800 mb-4">
                                <i class="pi pi-user-plus text-2xl text-surface-400"></i>
                            </div>
                            <h4 class="text-lg font-semibold text-surface-700 dark:text-surface-300 mb-2">
                                Aucun destinataire
                            </h4>
                            <p class="text-surface-600 dark:text-surface-400 text-sm">
                                Sélectionnez des utilisateurs dans la liste pour envoyer une notification
                            </p>
                        </div>
                        
                        <div v-else class="space-y-3 max-h-[300px] overflow-y-auto pr-2">
                            <!-- Single User Selection -->
                            <div v-if="selectedSingleUser" class="group flex items-center justify-between p-3 rounded-xl bg-primary-50/30 dark:bg-primary-900/20 border border-primary-200/50 dark:border-primary-800/50">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-semibold">
                                        {{ getInitials(selectedSingleUser) }}
                                    </div>
                                    <div>
                                        <div class="font-medium text-surface-900 dark:text-surface-100">{{ getUserName(selectedSingleUser) }}</div>
                                        <div class="text-xs text-surface-600 dark:text-surface-400">Sélection individuelle</div>
                                    </div>
                                </div>
                                <button @click.prevent="selectedSingleUser = null" class="p-1.5 rounded-lg hover:bg-white dark:hover:bg-surface-700 transition-colors group-hover:opacity-100 opacity-0">
                                    <i class="pi pi-times text-surface-400 hover:text-red-500"></i>
                                </button>
                            </div>

                            <!-- Multiple Recipients -->
                            <div 
                                v-for="id in Array.from(selectedRecipients)" 
                                :key="id" 
                                class="group flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-surface-500 to-surface-600 flex items-center justify-center text-white font-semibold">
                                        {{ getInitials(id) }}
                                    </div>
                                    <div>
                                        <div class="font-medium text-surface-900 dark:text-surface-100">{{ getUserName(id) }}</div>
                                        <div class="text-xs text-surface-600 dark:text-surface-400">Utilisateur</div>
                                    </div>
                                </div>
                                <button @click.prevent="removeRecipient(id)" class="p-1.5 rounded-lg hover:bg-white dark:hover:bg-surface-700 transition-colors group-hover:opacity-100 opacity-0">
                                    <i class="pi pi-times text-surface-400 hover:text-red-500"></i>
                                </button>
                            </div>
                        </div>

                        <div v-if="selectedCount > 0" class="mt-4 pt-4 border-t border-surface-200/50 dark:border-surface-700/50">
                            <div class="flex justify-between items-center">
                                <Button 
                                    label="Tout effacer" 
                                    icon="pi pi-trash" 
                                    severity="danger" 
                                    outlined
                                    size="small"
                                    class="rounded-xl"
                                    @click="clearSelection"
                                />
                                <Button 
                                    label="Confirmer l'envoi" 
                                    icon="pi pi-send" 
                                    severity="primary"
                                    size="small"
                                    :loading="sending"
                                    :disabled="!canSubmit || sending"
                                    class="rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 border-0"
                                    @click="submit"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template> 

<style scoped>
/* petites adaptations visuelles si nécessaire */
</style>

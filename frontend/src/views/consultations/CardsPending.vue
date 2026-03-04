<script setup>
import { cancelConsultation, fetchPendingConsultations, normalizeConsultation } from '@/services/consultations';
import { useAuthStore } from '@/stores/auth';
import Button from 'primevue/button';
import ConfirmPopup from 'primevue/confirmpopup';
import { useConfirm } from 'primevue/useconfirm';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast'; 
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import '@/components/patients/FormCreateConsultation.vue';

const router = useRouter();
const toast = useToast();
const confirmPopup = useConfirm();
const token = localStorage.getItem('token');
const auth = useAuthStore();

const consultations = ref([]);
const loading = ref(false);
const canceling = ref({});
const openCreateConsultationDialog = ref(false);

const loadPending = async () => {
    loading.value = true;
    try {
        const data = await fetchPendingConsultations(token);
        consultations.value = data.map((c) => normalizeConsultation(c)); 
    } catch (error) {
        console.error('Erreur lors du chargement des consultations en cours', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les consultations en cours.', life: 3000 });
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadPending();
});

const sortedConsultations = computed(() => {
    return [...consultations.value].sort((a, b) => {
        const aTime = a.createdAt ? new Date(a.createdAt).getTime() : 0;
        const bTime = b.createdAt ? new Date(b.createdAt).getTime() : 0;
        return aTime - bTime;
    });
});

const formatDateTime = (value) => {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });
};

const formatSince = (value) => {
    if (!value) return '';
    const now = Date.now();
    const t = new Date(value).getTime();
    if (Number.isNaN(t)) return '';
    const diffMinutes = Math.max(0, Math.round((now - t) / 60000));
    if (diffMinutes < 60) return `${diffMinutes} min d'attente`;
    const hours = Math.floor(diffMinutes / 60);
    const mins = diffMinutes % 60;
    return `${hours} H ${mins.toString().padStart(2, '0')} d'attente`;
};

const cardTone = (index) => {
    if (index === 0) return 'accent-oldest';
    if (index === 1) return 'accent-old';
    return 'accent-default';
};

const goToConsultation = (consultation, mode = 'continue') => {
    const query = { id: consultation.id, mode };
    const linked = isLinked(consultation);
    const targetFicheId = linked
        ? consultation.ficheId
        : mode === 'continue'
          ? (consultation.lastFicheId || null)
          : null;

    if (targetFicheId) query.ficheId = targetFicheId;

    const ficheType = mode === 'new-fiche'
        ? 'medicale'
        : linked
          ? (consultation.ficheType || (consultation.ficheVersion === 1 ? 'observation' : 'medicale'))
          : (consultation.lastFicheType || (consultation.lastFicheVersion === 1 ? 'observation' : 'medicale'));

    const routeName = ficheType === 'observation' ? 'consultations-form-legacy' : 'consultations-form';
    router.push({ name: routeName, query });
};

const isLinked = (consultation) => Boolean(consultation.ficheId);
const patientHasFiche = (consultation) => Boolean(consultation.hasFiche || consultation.lastFicheId);
const isAdmin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_ADMIN')));
const isMedecin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')));

const medecinLabel = (consultation) => {
    const value = consultation?.medecin;
    if (!value) return '—';
    if (typeof value === 'string') return value;
    const fullName = `${value.prenom ?? ''} ${value.nom ?? ''}`.trim();
    return value.label || value.fullName || value.name || fullName || '—';
};

const showActions = {
    continue: (c) => isLinked(c) || (!isLinked(c) && patientHasFiche(c)),
    newFiche: (c) => !isLinked(c),
    cancel: (c) => (isLinked(c) ? isAdmin.value : true)
};

const continueLabel = (c) => (isLinked(c) ? 'Continuer la consultation' : 'Continuer dernière fiche');

const setCanceling = (id, value) => {
    canceling.value = { ...canceling.value, [id]: value };
};

const handleCancel = async (consultation) => {
    if (!consultation?.id) return;
    setCanceling(consultation.id, true);
    try {
        await cancelConsultation(consultation.id, token);
        toast.add({ severity: 'success', summary: 'Consultation annulée', detail: 'Consultation supprimée.', life: 2500 });
        await loadPending();
    } catch (error) {
        console.error('Annulation impossible', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible d\'annuler la consultation.', life: 3000 });
    } finally {
        setCanceling(consultation.id, false);
    }
};

const confirmAction = (event, message, accept) => {
    confirmPopup.require({
        target: event.currentTarget || event.target,
        message,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept
    });
};

const handleContinue = (event, consultation) => {
    if (!isLinked(consultation) && patientHasFiche(consultation)) {
        confirmAction(event, 'Une fiche existe déjà pour ce patient. Continuer la dernière fiche ?', () =>
            goToConsultation(consultation, 'continue')
        );
    } else {
        goToConsultation(consultation, 'continue');
    }
};

const handleNewFiche = (event, consultation) => {
    if (!isLinked(consultation) && patientHasFiche(consultation)) {
        confirmAction(event, 'Une fiche existe déjà pour ce patient. Créer une nouvelle fiche ?', () =>
            goToConsultation(consultation, 'new-fiche')
        );
    } else {
        goToConsultation(consultation, 'new-fiche');
    }
};

const handleCancelWithConfirm = (event, consultation) => {
    confirmAction(event, 'Annuler cette consultation en cours ?', () => handleCancel(consultation));
};

function getBorderColor(index) {
            if (index === 0) return 'emerald' // Plus ancien
            if (index < 3) return 'amber'    // Ancien
            return 'surface'                  // Récent
        }
        
        function getPriorityColor(index) {
            if (index === 0) return 'red'    // Plus ancien - urgent
            if (index < 3) return 'amber'    // Ancien - attention
            return 'green'                    // Récent - normal
        }
        
        function getProgressBarClass(index) {
            if (index === 0) return 'bg-gradient-to-r from-red-500 to-red-600'
            if (index < 3) return 'bg-gradient-to-r from-amber-500 to-amber-600'
            return 'bg-gradient-to-r from-green-500 to-green-600'
        }
        
        function getWaitTimePercentage(createdAt) {
            const created = new Date(createdAt)
            const now = new Date()
            const diffHours = (now - created) / (1000 * 60 * 60)
            
            // Plus de 1 heure = 100%
            if (diffHours >= 1) return 100
            // Entre 0 et 1 heure = proportionnel
            return Math.min(100, Math.round((diffHours / 1) * 100))
        }
</script>

<template>
    <div class="mb-6 md:mb-8">
        <!-- Stats Card -->
        <div class="bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 rounded-2xl p-5 border border-amber-200/50 dark:border-amber-800/50 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-amber-700 dark:text-amber-300 font-medium">Consultations en cours</p>
                    <p class="text-2xl font-bold text-amber-900 dark:text-amber-100 mt-2">
                        {{ sortedConsultations.length }}
                        <span class="text-base font-normal text-amber-600 dark:text-amber-400 ml-1">en attente</span>
                    </p>
                </div>
                <i class="fas fa-clock text-2xl text-amber-500 animate-pulse"></i>
            </div>
        </div>

        <!-- Main Card -->
        <div class="card p-5 md:p-6 border-0 rounded-2xl bg-gradient-to-r from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 shadow-xl backdrop-blur-sm">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                            <i class="fas fa-stethoscope text-primary-600 dark:text-primary-400 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl lg:text-3xl font-bold text-surface-900 dark:text-surface-50">
                                Consultations en cours
                            </h2>
                            <p class="text-surface-600 dark:text-surface-300 text-sm md:text-base">
                                Gestion des consultations ouvertes et en attente
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button 
                        icon="pi pi-refresh" 
                        label="Rafraîchir" 
                        :loading="loading" 
                        outlined
                        class="rounded-xl px-5 py-2.5 border-surface-300 dark:border-surface-600 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
                        @click="loadPending" 
                    />
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!loading && !sortedConsultations.length" 
                class="text-center py-16 rounded-xl border-2 border-dashed border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-50/50 to-surface-0/30 dark:from-surface-800/30 dark:to-surface-900/20">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-surface-100 dark:bg-surface-800 mb-6">
                    <i class="fas fa-check-circle text-4xl text-surface-400"></i>
                </div>
                <h4 class="text-xl font-semibold text-surface-700 dark:text-surface-300 mb-3">
                    Aucune consultation en cours
                </h4>
                <p class="text-surface-600 dark:text-surface-400 mb-8 max-w-md mx-auto">
                    Toutes les consultations ont été traitées ou clôturées.
                </p>
                <Button 
                    v-if="!isMedecin"
                    icon="fas fa-plus" 
                    label="Créer une consultation" 
                    severity="secondary"
                    @click="openCreateConsultationDialog = true"
                    outlined
                    class="rounded-xl"
                />
            </div>

            <!-- Consultations Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div 
                    v-for="(consultation, idx) in sortedConsultations" 
                    :key="consultation.id"
                    class="relative overflow-hidden rounded-2xl border transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 flex flex-col h-full group"
                    :class="[
                        'border-' + getBorderColor(idx) + '-200/50 dark:border-' + getBorderColor(idx) + '-800/50',
                        'bg-gradient-to-br from-white to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80'
                    ]"
                >
                    <!-- Priority Indicator -->
                    <div 
                        class="absolute top-0 left-0 w-2 h-full"
                        :class="'bg-gradient-to-b to-' + getPriorityColor(idx) + '-500 from-' + getPriorityColor(idx) + '-600'"
                    ></div>

                    <!-- Card Header -->
                    <div class="p-5 pt-6">
                        <div class="flex items-start justify-between gap-3 mb-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/30 dark:to-primary-800/30 flex items-center justify-center">
                                        <i class="fas fa-user-md text-primary-600 dark:text-primary-400"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h3 class="text-lg font-bold text-surface-900 dark:text-surface-100 truncate">
                                                {{ consultation.patient || 'Patient inconnu' }}
                                            </h3>
                                            <Tag 
                                                v-if="consultation.statut" 
                                                :value="consultation.statut" 
                                                severity="info"
                                                class="px-3 py-1 rounded-full font-medium"
                                            />
                                        </div>
                                        <div class="flex items-center gap-2 text-sm text-surface-500 dark:text-surface-400">
                                            <i class="pi pi-phone"></i>
                                            <span>{{ consultation.patientPhone || 'Téléphone non renseigné' }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-sm text-surface-500 dark:text-surface-400 mt-1">
                                            <i class="fas fa-user-md"></i>
                                            <span>{{ medecinLabel(consultation) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Timeline Info -->
                                <div class="space-y-3 mt-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                                <i class="pi pi-calendar text-blue-600 dark:text-blue-400 text-sm"></i>
                                            </div>
                                            <span class="text-sm font-medium text-surface-700 dark:text-surface-300">
                                                Ouverte le
                                            </span>
                                        </div>
                                        <span class="text-sm font-semibold text-surface-900 dark:text-surface-100">
                                            {{ formatDateTime(consultation.createdAt) }}
                                        </span>
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                                <i class="pi pi-clock text-amber-600 dark:text-amber-400 text-sm"></i>
                                            </div>
                                            <span class="text-sm font-medium text-surface-700 dark:text-surface-300">
                                                Ancienneté
                                            </span>
                                        </div>
                                        <span class="text-sm font-bold text-amber-600 dark:text-amber-400">
                                            {{ formatSince(consultation.createdAt) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Indicator -->
                        <div class="mt-4">
                            <div class="flex justify-between text-xs text-surface-500 dark:text-surface-400 mb-1">
                                <span>Temps d'attente</span>
                                <span>{{ getWaitTimePercentage(consultation.createdAt) }}%</span>
                            </div>
                            <div class="h-2 bg-surface-200 dark:bg-surface-700 rounded-full overflow-hidden">
                                <div 
                                    class="h-full rounded-full transition-all duration-1000"
                                    :class="getProgressBarClass(idx)"
                                    :style="{ width: getWaitTimePercentage(consultation.createdAt) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer - Actions -->
                    <div class="mt-auto p-4 border-t border-surface-100 dark:border-surface-700/50 bg-surface-50/50 dark:bg-surface-800/30">
                        <div class="flex flex-wrap gap-2">
                            <Button 
                                v-if="showActions.continue(consultation)" 
                                :label="continueLabel(consultation)" 
                                icon="pi pi-forward" 
                                severity="secondary"
                                size="small"
                                class="rounded-xl px-4 py-2 text-sm font-medium transition-all hover:scale-[1.02]"
                                @click="(e) => handleContinue(e, consultation)" 
                            />
                            <Button 
                                v-if="showActions.newFiche(consultation)" 
                                label="Nouvelle fiche" 
                                icon="pi pi-plus-circle" 
                                severity="success"
                                size="small"
                                class="rounded-xl px-4 py-2 text-sm font-medium transition-all hover:scale-[1.02]"
                                @click="(e) => handleNewFiche(e, consultation)" 
                            />
                            <Button 
                                v-if="showActions.cancel(consultation)" 
                                label="Annuler" 
                                icon="pi pi-times" 
                                severity="danger"
                                size="small"
                                outlined
                                :loading="canceling[consultation.id] === true"
                                class="rounded-xl px-4 py-2 text-sm font-medium transition-all hover:scale-[1.02]"
                                @click="(e) => handleCancelWithConfirm(e, consultation)" 
                            />
                        </div>
                    </div>

                    <!-- Hover Overlay -->
                    <div class="absolute inset-0 bg-gradient-to-t from-primary-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-16">
                <div class="text-center">
                    <i class="pi pi-spin pi-spinner text-4xl text-primary-500 mb-4"></i>
                    <p class="text-surface-600 dark:text-surface-400">Chargement des consultations...</p>
                </div>
            </div>
        </div>
    </div>

    <Dialog 
        v-if="!isMedecin"
        v-model:visible="openCreateConsultationDialog" 
        header="Créer une nouvelle consultation" 
        :modal="true" 
        :closable="true" 
        :dismissable-mask="true"
        :style="{ width: '50rem' }" :pt="{
            root: 'rounded-2xl',
            header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b',
            content: 'p-0 mt-4'
        }">
            <template #header>
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-green-100 dark:bg-green-900/30">
                        <i class="fas fa-stethoscope text-green-600 dark:text-green-400"></i>
                    </div>
                    <div>
                        <h4 class="m-0 text-surface-900 dark:text-surface-100">
                            Nouvelle consultation
                        </h4>
                        <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                            {{ consultationPatient?.fullname || consultationPatient?.nom || 'Nouveau patient' }}
                        </p>
                    </div>
                </div>
            </template> 
        
        <FormCreateConsultation @cancel="openCreateConsultationDialog = false"  @saved="() => { openCreateConsultationDialog = false; loadPending(); }" />
    </Dialog>

    <ConfirmPopup />
</template> 

<style scoped>
/* Animation pour les cartes */
@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 20px -10px rgba(16, 185, 129, 0.5); }
    50% { box-shadow: 0 0 30px -5px rgba(16, 185, 129, 0.8); }
}

/* Priorité haute - animation de pulsation */
.grid-cols-1 > div:first-child,
.grid-cols-2 > div:nth-child(-n+1),
.grid-cols-3 > div:nth-child(-n+1) {
    animation: pulse-glow 2s ease-in-out infinite;
}

/* Transition pour les cartes */
.card-transition-enter-active {
    transition: all 0.3s ease-out;
}

.card-transition-leave-active {
    transition: all 0.2s ease-in;
}

.card-transition-enter-from,
.card-transition-leave-to {
    opacity: 0;
    transform: scale(0.9) translateY(10px);
}
</style>

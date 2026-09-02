<script setup>
import PatientAvatar from '@/components/patients/PatientAvatar.vue';
import Drawer from 'primevue/drawer';
import { computed } from 'vue';

const props = defineProps({
    visible: { type: Boolean, default: false },
    rdv: { type: Object, default: null }
});

const emit = defineEmits(['close', 'update:visible']);

const syncVisible = (value) => {
    emit('update:visible', value);
    if (!value) emit('close');
};

const close = () => {
    syncVisible(false);
};

const title = computed(() => props.rdv?.patientName || 'Détails rendez-vous');
const smsReminder = computed(() => props.rdv?.smsReminder || null);

const statusClass = computed(() => {
    const s = (props.rdv?.statut || '').toString().toLowerCase();
    if (!s) return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100';
    if (s.includes('valid') || s.includes('valide') || s.includes('confirmed')) return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200';
    if (s.includes('annul') || s.includes('cancel')) return 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200';
    if (s.includes('attente') || s.includes('pending')) return 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200';
    return 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200';
});

const smsStatusClass = computed(() => {
    const status = String(smsReminder.value?.status || '').toLowerCase();
    if (status === 'sent') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200';
    if (status === 'failed') return 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200';
    if (status === 'sending') return 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200';
    if (smsReminder.value?.isAutomatic) return 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200';
    return 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-100';
});

const formattedStart = computed(() => (props.rdv?.start ? new Date(props.rdv.start).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—'));
const formattedEnd = computed(() => (props.rdv?.end ? new Date(props.rdv.end).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—'));
const formattedSmsSendAt = computed(() => (smsReminder.value?.sendAt ? new Date(smsReminder.value.sendAt).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—'));
const formattedSmsSentAt = computed(() => (smsReminder.value?.sentAt ? new Date(smsReminder.value.sentAt).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—'));
</script>

<template>
    <Drawer
        :visible="visible"
        position="right"
        :modal="true"
        :dismissableMask="true"
        :closeOnEscape="true"
        :style="{ width: '44rem', maxWidth: '94vw' }"
        :breakpoints="{ '1024px': '86vw', '640px': '96vw' }"
        class="details-drawer"
        @update:visible="syncVisible"
        @hide="close"
    >
        <div class="p-4 md:p-6">
            <div class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm dark:border-slate-700/80 dark:bg-slate-900/60">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <PatientAvatar
                            :patient="rdv?.patientData ?? rdv"
                            :photo="rdv?.patientPhoto || rdv?.patientData?.photo || ''"
                            :initials="rdv?.patientName || 'RD'"
                            size-class="h-12 w-12"
                            rounded-class="rounded-xl"
                            fallback-class="bg-sky-100 text-sky-700 dark:bg-sky-900 dark:text-sky-200"
                            text-class="font-semibold"
                        />
                        <div>
                            <h3 class="text-xl font-semibold text-slate-800 dark:text-slate-100 leading-tight">{{ title }}</h3>
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-300">{{ rdv?.medecinName || 'Médecin inconnu' }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <div :class="['px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap shadow-sm', statusClass]">{{ rdv?.statut || '—' }}</div>
                        <button class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700" @click="close">✕</button>
                    </div>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/70">
                    <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <i class="pi pi-clock text-[0.75rem]"></i>
                        Début
                    </div>
                    <div class="mt-2 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ formattedStart }}</div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/70">
                    <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <i class="pi pi-stopwatch text-[0.75rem]"></i>
                        Fin
                    </div>
                    <div class="mt-2 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ formattedEnd }}</div>
                </div>
            </div>

            <div class="mt-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/50">
                <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <i class="pi pi-user text-[0.75rem]"></i>
                    Patient
                </div>
                <div class="mt-2 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ rdv?.patientName || '—' }}</div>
            </div>

            <div class="mt-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/50">
                <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <i class="pi pi-file-edit text-[0.75rem]"></i>
                    Notes
                </div>
                <div class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700 dark:text-slate-200">{{ rdv?.description || 'Aucune note' }}</div>
            </div>

            <div class="mt-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/50">
                <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <i class="pi pi-send text-[0.75rem]"></i>
                    Rappel SMS
                </div>
                <div v-if="smsReminder" class="mt-3 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div :class="['px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap shadow-sm', smsStatusClass]">
                            {{ smsReminder.label || 'SMS' }}
                        </div>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ smsReminder.isAutomatic ? 'Programmation automatique' : 'Envoi manuel' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-800/70">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Programmé pour</div>
                            <div class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ formattedSmsSendAt }}</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-800/70">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Envoyé le</div>
                            <div class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ formattedSmsSentAt }}</div>
                        </div>
                    </div>
                    <div v-if="smsReminder.message" class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-sm leading-6 text-slate-700 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200">
                        {{ smsReminder.message }}
                    </div>
                    <div v-if="smsReminder.lastError" class="rounded-xl border border-rose-200 bg-rose-50/70 p-3 text-sm leading-6 text-rose-700 dark:border-rose-800 dark:bg-rose-950/30 dark:text-rose-200">
                        {{ smsReminder.lastError }}
                    </div>
                </div>
                <div v-else class="mt-2 text-sm text-slate-500 dark:text-slate-400">Aucun rappel SMS associé à ce rendez-vous.</div>
            </div>

            <div class="mt-6 flex flex-wrap gap-2 border-t border-slate-200 pt-4 dark:border-slate-700">
                <!-- <button class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-sky-700">
          <i class="pi pi-pencil text-xs"></i>
          Modifier
        </button> -->
                <button
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600"
                    @click="close"
                >
                    <i class="pi pi-times text-xs"></i>
                    Fermer
                </button>
            </div>
        </div>
    </Drawer>
</template>

<style scoped>
/* Largeur gérée directement via props du Drawer (:style / :breakpoints) */
</style>

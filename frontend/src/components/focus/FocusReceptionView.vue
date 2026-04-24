<script setup>
import PrintDevisBody from '@/components/print/PrintDevisBody.vue';
import PrintReceiptBody from '@/components/print/PrintReceiptBody.vue';
import PrintTicketBody from '@/components/print/PrintTicketBody.vue';
import { usePrinter } from '@/composables/usePrinter';
import { fetchInvoicePrintData, fetchReceiptPrintData, fetchTicketPrintData } from '@/services/printService';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';
import { computed, defineEmits, defineProps, toRefs } from 'vue';

const props = defineProps({
    consultations: {
        type: Array,
        default: () => []
    },
    recentPatients: {
        type: Array,
        default: () => []
    },
    billingByConsultation: {
        type: Object,
        default: () => ({})
    },
    loading: {
        type: Boolean,
        default: false
    },
    allowReceptionQuickClose: {
        type: Boolean,
        default: true
    },
    isAdmin: {
        type: Boolean,
        default: false
    },
    selectedConsultationId: {
        type: [Number, String, null],
        default: null
    }
});

const emit = defineEmits([
    'refresh',
    'select-consultation',
    'open-create-patient',
    'open-create-consultation',
    'open-caisse-pay',
    'open-caisse-validate',
    'open-caisse-modify',
    'open-caisse-preview',
    'send-invoice-sms',
    'select-medical-workspace',
    'open-details',
    'open-facture',
    'open-quick-dialog',
    'cancel-consultation'
]);

const { consultations, selectedConsultationId } = toRefs(props);
const token = localStorage.getItem('token');
const { printComponent } = usePrinter();

const showCompletedSecretary = defineModel('showCompletedSecretary', {
    type: Boolean,
    default: false
});

const parseDateTime = (value) => {
    if (!value) return null;
    if (value instanceof Date) return value;
    if (/^\d{2}\/\d{2}\/\d{4}\s\d{2}:\d{2}$/.test(String(value))) {
        const [datePart, timePart] = String(value).split(' ');
        const [day, month, year] = datePart.split('/').map(Number);
        const [hours, minutes] = timePart.split(':').map(Number);
        return new Date(year, month - 1, day, hours, minutes, 0, 0);
    }
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const formatTime = (value) => {
    const parsed = parseDateTime(value);
    return parsed ? parsed.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '--:--';
};

const formatDateTime = (value) => {
    const parsed = parseDateTime(value);
    return parsed ? parsed.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '--';
};

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const isSameCalendarDay = (left, right) => {
    const leftDate = parseDateTime(left);
    const rightDate = parseDateTime(right);
    if (!leftDate || !rightDate) return false;
    return leftDate.getFullYear() === rightDate.getFullYear()
        && leftDate.getMonth() === rightDate.getMonth()
        && leftDate.getDate() === rightDate.getDate();
};

const monthRange = () => {
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    return {
        start: start.toISOString().slice(0, 10),
        end: end.toISOString().slice(0, 10)
    };
};

const patientLabel = (consultation) => {
    if (!consultation) return 'Patient';
    if (typeof consultation.patientName === 'string' && consultation.patientName.trim()) return consultation.patientName;
    if (typeof consultation.patient === 'string' && consultation.patient.trim()) return consultation.patient;
    const patient = consultation.patient || {};
    return `${patient.prenom ?? ''} ${patient.nom ?? ''}`.trim() || patient.nom || 'Patient';
};

const patientCreatedAt = (patient) => patient?.createdAt
    || patient?.created_at
    || patient?.dateInscription
    || patient?.date_inscription
    || patient?.dateCreation
    || patient?.date_creation
    || null;

const patientDisplayName = (patient) => {
    const source = patient?.patient || patient || {};
    const fullName = `${source.prenom ?? ''} ${source.nom ?? ''}`.trim();
    return fullName || source.fullname || 'Patient';
};

const medecinLabel = (consultation) => {
    const medecin = consultation?.medecin;
    if (!medecin) return 'Non assigne';
    if (typeof medecin === 'string') return medecin;
    return medecin.label || medecin.fullName || medecin.name || `${medecin.prenom ?? ''} ${medecin.nom ?? ''}`.trim() || 'Non assigne';
};

const formatFactureState = (consultation) => {
    if (consultation?.factState === null || typeof consultation?.factState === 'undefined') {
        return { label: 'Aucune facture', severity: 'contrast' };
    }
    if (Number(consultation.factState) === 0) {
        return { label: 'Facture ouverte', severity: 'warn' };
    }
    return { label: 'Facture reglee', severity: 'success' };
};

const patientHistoryState = (consultation) => {
    if (consultation?.hasFiche || consultation?.lastFicheId || consultation?.ficheId) {
        return { label: 'Ancien patient', severity: 'info' };
    }
    return { label: 'Nouveau patient', severity: 'contrast' };
};

const consultationState = (consultation) => {
    if (Number(consultation?.state) === 1) {
        return { label: 'Terminee', severity: 'success' };
    }

    return consultation?.id === selectedConsultationId.value
        ? { label: 'Selectionnee', severity: 'info' }
        : { label: 'En attente', severity: 'warn' };
};

const queueItemClass = (consultation) => {
    if (Number(consultation.state) === 1) {
        return 'border-green-400/70 bg-green-50/70 dark:bg-green-950/20';
    }
    if (consultation.id === selectedConsultationId.value) {
        return 'border-primary-500 ring-2 ring-primary-400/60 ring-offset-2 ring-offset-surface-0 shadow-xl shadow-primary-500/20 bg-primary-50 dark:bg-primary-950/20 dark:ring-offset-surface-900';
    }
    return 'border-surface-200/70 bg-surface-0/90 dark:border-surface-700/70 dark:bg-surface-800/80';
};

const secretaryRows = computed(() => {
    const source = [...consultations.value].sort((left, right) => {
        const leftTime = parseDateTime(left.createdAt)?.getTime() || 0;
        const rightTime = parseDateTime(right.createdAt)?.getTime() || 0;
        return leftTime - rightTime;
    });

    if (showCompletedSecretary.value) {
        return source;
    }

    return source.filter((item) => Number(item.state) !== 1);
});

const currentConsultation = computed(() => secretaryRows.value.find((item) => item.id === selectedConsultationId.value) || null);

const latestPayment = computed(() => selectedInvoicePayments.value[0] || null);
const newPatients = computed(() => props.recentPatients || []);
const showRightPlaceholderSkeleton = computed(() => props.loading && !currentConsultation.value);

const currentBilling = computed(() => {
    if (!currentConsultation.value?.id) return null;
    return props.billingByConsultation?.[currentConsultation.value.id] || null;
});

const selectedInvoicePayments = computed(() => currentBilling.value?.payments || []);

const selectedInvoiceState = computed(() => currentBilling.value?.state || { label: 'Aucune facture', severity: 'contrast' });

const selectedInvoiceTotal = computed(() => Number(currentBilling.value?.total ?? 0) || 0);

const selectedInvoiceRemaining = computed(() => currentBilling.value ? (Number(currentBilling.value.remaining ?? 0) || 0) : null);

const consultationDetailsLines = computed(() => currentBilling.value?.lines || []);
const hasInvoiceContext = computed(() => Boolean(currentBilling.value?.invoiceId));
const isPaidInvoice = computed(() => hasInvoiceContext.value && selectedInvoiceRemaining.value === 0 && selectedInvoiceTotal.value > 0);
const isFreeInvoice = computed(() => hasInvoiceContext.value && selectedInvoiceRemaining.value === 0 && selectedInvoiceTotal.value === 0);
const canModifyInvoice = computed(() => hasInvoiceContext.value && selectedInvoiceTotal.value > 0 && selectedInvoiceRemaining.value === selectedInvoiceTotal.value);
const canPreviewInvoice = computed(() => hasInvoiceContext.value && !(selectedInvoiceTotal.value === 0 && selectedInvoiceRemaining.value === 0));

const selectConsultation = (consultationId) => {
    emit('select-consultation', consultationId);
};

const printInvoice = async () => {
    if (!currentBilling.value?.invoiceId) return;
    const response = await fetchInvoicePrintData(currentBilling.value.invoiceId, token);
    await printComponent(PrintDevisBody, { doc: response.doc, title: response.title || 'Facture' });
};

const printPaymentReceipt = async (paymentId) => {
    if (!paymentId) return;
    const response = await fetchReceiptPrintData(paymentId, token);
    await printComponent(
        PrintReceiptBody,
        { paiement: response.paiement },
        { format: [226.77, 255.12], width: '80mm' }
    );
};

const printPaymentTicket = async (paymentId) => {
    if (!paymentId) return;
    const response = await fetchTicketPrintData(paymentId, token);
    await printComponent(
        PrintTicketBody,
        { paiement: response.paiement },
        { format: [226.77, 255.12], width: '80mm' }
    );
};
</script>

<template>
    <div class="grid gap-4 xl:grid-cols-[280px_minmax(0,1fr)_340px]">
        <aside class="space-y-3">
    <div class="rounded-xl border-2 border-surface-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">
        <!-- En-tête -->
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400">
                    <i class="pi pi-users text-xs"></i>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-surface-900 dark:text-surface-50">Nouveaux patients</h3>
                    <p class="text-xs text-surface-500">{{ newPatients.length }} aujourd’hui</p>
                </div>
            </div>
            <button
                @click="emit('open-create-patient')"
                class="flex items-center gap-1 rounded-lg bg-primary-500 px-3 py-1.5 text-xs font-medium text-white transition-all hover:bg-primary-600 active:scale-95 shadow-sm"
            >
                <i class="pi pi-plus text-xs"></i>
                <span class="hidden sm:inline">Ajouter</span>
            </button>
        </div>

        <!-- Squelettes de chargement -->
        <div v-if="loading" class="space-y-2">
            <div v-for="i in 3" :key="i" class="flex items-center gap-3 rounded-xl bg-surface-50 p-3 dark:bg-surface-800/40">
                <Skeleton shape="circle" size="2.5rem" class="flex-shrink-0" />
                <div class="flex-1 space-y-2">
                    <Skeleton width="60%" height="0.75rem" />
                    <Skeleton width="80%" height="0.625rem" />
                </div>
            </div>
        </div>

        <!-- Liste des patients -->
        <div v-else-if="newPatients.length" class="space-y-2">
            <div
                v-for="patient in newPatients"
                :key="patient.id"
                class="group flex items-center gap-3 rounded-xl border border-surface-100 bg-surface-50/80 p-3 transition-all hover:border-purple-200 hover:bg-purple-50/50 hover:shadow-sm dark:border-surface-700 dark:bg-surface-800/60 dark:hover:border-purple-800 dark:hover:bg-purple-950/30"
            >
                <!-- Avatar avec initiales -->
                <div class="flex h-5 w-5 text-xs flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-purple-500 to-fuchsia-500 font-bold text-white shadow-sm">
                    {{ (patient.prenom?.[0] ?? '') + (patient.nom?.[0] ?? 'P') }}
                </div>

                <!-- Infos -->
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-surface-900 dark:text-surface-50">
                        {{ patientDisplayName(patient) }}
                    </p>
                    <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-surface-500 dark:text-surface-400">
                        <div class="flex items-center gap-1">
                            <i class="pi pi-clock text-[10px]"></i>
                            {{ formatDateTime(patientCreatedAt(patient)).split(' ')[0] }}
                        </div>
                        <div class="flex items-center gap-1 truncate">
                            <i class="pi pi-phone text-[10px]"></i>
                            {{ patient.telephone || 'Non renseigné' }}
                        </div>
                    </div>
                </div>

                <!-- Badge "Nouveau" -->
                <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-900/40 dark:text-purple-400">
                    <i class="pi pi-sparkles text-[10px]"></i> 
                </span>
            </div>
        </div>

        <!-- État vide -->
        <div v-else class="flex flex-col items-center justify-center py-8 text-center text-surface-400">
            <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800">
                <i class="pi pi-user-plus text-xl opacity-50"></i>
            </div>
            <p class="text-sm font-medium">Aucun nouveau patient</p>
            <p class="mt-1 text-xs">Les nouveaux arrivants s’afficheront ici</p>
        </div>
    </div>
</aside>

        <!-- Colonne Centre - Liste consultations -->
        <section>
            <div class="rounded-xl border-2 border-surface-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-surface-900 dark:text-surface-50">File d'attente</h3>
                        <p class="text-xs text-surface-500">{{ secretaryRows.length }} consultations</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="flex cursor-pointer items-center gap-1.5 text-xs text-surface-600 dark:text-surface-400">
                            <input type="checkbox" v-model="showCompletedSecretary" class="h-3.5 w-3.5 rounded border-surface-300 text-primary-600 focus:ring-primary-500 dark:border-surface-600" />
                            Terminées
                        </label>
                        <button @click="emit('open-create-consultation')"
                                class="flex items-center gap-1 rounded-md bg-primary-500 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-primary-600 shadow-sm">
                            <i class="pi pi-plus text-xs"></i> Nouvelle
                        </button>
                    </div>
                </div>

                <div v-if="loading" class="space-y-2">
                    <div v-for="item in 4" :key="item" class="rounded-lg bg-surface-50 p-3 dark:bg-surface-800/40">
                        <div class="flex gap-2 mb-2">
                            <Skeleton width="3rem" height="1.25rem" class="rounded-full" />
                            <Skeleton width="4rem" height="1.25rem" class="rounded-full" />
                        </div>
                        <Skeleton width="10rem" height="0.75rem" class="mb-1.5" />
                        <Skeleton width="8rem" height="0.625rem" />
                    </div>
                </div>

                <div v-else-if="secretaryRows.length" class="space-y-2">
                    <button
                        v-for="consultation in secretaryRows"
                        :key="consultation.id"
                        @click="selectConsultation(consultation.id)"
                        class="group relative w-full rounded-lg border-l-4 p-3 text-left transition-all duration-200 hover:scale-[1.02] hover:shadow-md"
                        :class="[
                            consultation.id === selectedConsultationId
                                ? 'bg-primary-50/90 border-l-primary-500 shadow-md ring-1 ring-primary-200 dark:bg-primary-950/30 dark:border-l-primary-400 dark:ring-primary-700'
                                : Number(consultation.state) === 1
                                    ? 'bg-green-50/50 border-l-green-400 opacity-75 dark:bg-green-950/10 dark:border-l-green-600'
                                    : 'bg-white border-l-amber-400 hover:bg-amber-50/30 dark:bg-surface-900 dark:border-l-amber-500 dark:hover:bg-amber-950/20',
                        ]"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-mono font-bold text-surface-600 dark:text-surface-300">
                                        {{ formatTime(consultation.createdAt) }}
                                    </span>
                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-50"></span>
                                    <!-- Badge état -->
                                    <span :class="[
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                        Number(consultation.state) === 1
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-400'
                                            : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-400'
                                    ]">
                                        {{ Number(consultation.state) === 1 ? 'Terminée' : 'En cours' }}
                                    </span>
                                    <!-- Type de patient -->
                                    <span :class="[
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                        consultation.hasFiche || consultation.lastFicheId
                                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-400'
                                            : 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-400'
                                    ]">
                                        {{ consultation.hasFiche || consultation.lastFicheId ? 'Ancien' : 'Nouveau' }}
                                    </span>
                                </div>
                                <p class="text-sm font-semibold text-surface-900 truncate dark:text-surface-50">
                                    {{ patientLabel(consultation) }}
                                </p>
                                <p class="text-xs text-surface-500 truncate mt-0.5">
                                    {{ medecinLabel(consultation) }} · {{ consultation.motif || '—' }}
                                </p>
                            </div>
                            <!-- État de la facture (badge coloré) -->
                            <div class="flex-shrink-0">
                                <span :class="[
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                    consultation?.factState === 1
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-400'
                                        : consultation?.factState === 0
                                            ? 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-400'
                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                                ]">
                                    {{ formatFactureState(consultation).label }}
                                </span>
                            </div>
                        </div>
                    </button>
                </div>

                <div v-else class="flex flex-col items-center py-10 text-center text-surface-500">
                    <i class="pi pi-calendar-times text-3xl mb-2 opacity-50"></i>
                    <p class="text-sm">Aucune consultation aujourd’hui</p>
                </div>
            </div>
        </section>

        <!-- Colonne Droite - Détails -->
        <aside>
            <div class="rounded-xl border-2 border-surface-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">
                <!-- en-tête -->
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-surface-900 dark:text-surface-50">Détails</h3>
                    <span v-if="currentConsultation" class="text-xs text-surface-500">Sélectionnée</span>
                </div>

                <div v-if="currentConsultation" class="space-y-4">
                    <!-- Résumé patient avec bordure latérale colorée -->
                    <div class="rounded-lg border-l-4 p-3"
                         :class="Number(currentConsultation.state) === 1 ? 'border-l-green-500 bg-green-50/50 dark:bg-green-950/10' : 'border-l-primary-500 bg-primary-50/50 dark:bg-primary-950/10'">
                        <p class="text-sm font-semibold text-surface-900 dark:text-surface-50">{{ patientLabel(currentConsultation) }}</p>
                        <p class="text-xs text-surface-600 dark:text-surface-400">{{ medecinLabel(currentConsultation) }} · {{ formatTime(currentConsultation.createdAt) }}</p>
                    </div>

                    <!-- Actions contextuelles -->
                    <div class="flex flex-wrap gap-2">
                        <button @click="emit('open-details', currentConsultation)"
                                class="rounded-md border border-surface-300 px-3 py-1.5 text-xs font-medium text-surface-600 transition hover:bg-surface-100 dark:border-surface-600 dark:text-surface-400 dark:hover:bg-surface-800">
                            <i class="pi pi-eye mr-1"></i>Détails
                        </button>
                        <button v-if="allowReceptionQuickClose && Number(currentConsultation.state) !== 1"
                                @click="emit('open-quick-dialog', currentConsultation)"
                                class="rounded-md bg-amber-500 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-amber-600 shadow-sm">
                            <i class="pi pi-bolt mr-1"></i>Clôture rapide
                        </button>
                        <button v-if="isAdmin && Number(currentConsultation.state) !== 1"
                                @click="emit('select-medical-workspace', currentConsultation, currentConsultation.hasFiche || currentConsultation.lastFicheId ? 'continue-last' : 'new-fiche')"
                                class="rounded-md border border-primary-500 px-3 py-1.5 text-xs font-medium text-primary-700 transition hover:bg-primary-50 dark:border-primary-400 dark:text-primary-400 dark:hover:bg-primary-950/20">
                            <i class="pi pi-arrow-right mr-1"></i>Médical
                        </button>
                    </div>

                    <!-- Bloc facture avec bordure latérale -->
                    <div class="rounded-lg border-l-4 p-3"
                         :class="isPaidInvoice ? 'border-l-green-500 bg-green-50/50 dark:bg-green-950/10' : isFreeInvoice ? 'border-l-blue-500 bg-blue-50/50 dark:bg-blue-950/10' : 'border-l-amber-500 bg-amber-50/50 dark:bg-amber-950/10'">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold text-surface-700 dark:text-surface-300">Facture</span>
                            <span :class="[
                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                isPaidInvoice ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-400' :
                                isFreeInvoice ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-400' :
                                'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-400'
                            ]">
                                {{ isPaidInvoice ? 'Réglée' : isFreeInvoice ? 'Gratuite' : 'En attente' }}
                            </span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-surface-500">Total</span>
                            <span class="font-bold text-surface-900 dark:text-white">{{ formatFcfa(selectedInvoiceTotal) }}</span>
                        </div>
                        <div class="flex justify-between text-xs mt-1">
                            <span class="text-surface-500">Reste</span>
                            <span class="font-bold" :class="selectedInvoiceRemaining > 0 ? 'text-red-600' : 'text-green-600'">
                                {{ selectedInvoiceRemaining === null ? '--' : formatFcfa(selectedInvoiceRemaining) }}
                            </span>
                        </div>
                        <div v-if="hasInvoiceContext" class="mt-3 flex flex-wrap gap-2 border-t border-surface-200 pt-3 dark:border-surface-700">
                            <button v-if="!isPaidInvoice && !isFreeInvoice" @click="emit('open-caisse-pay')"
                                    class="flex-1 rounded-md bg-green-500 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-green-600 shadow-sm">
                                Régler
                            </button>
                            <button v-if="isFreeInvoice" @click="emit('open-caisse-validate')"
                                    class="flex-1 rounded-md bg-blue-500 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-blue-600 shadow-sm">
                                Valider
                            </button>
                            <button @click="emit('open-caisse-preview')"
                                    class="rounded-md border border-surface-300 px-3 py-1.5 text-xs font-medium text-surface-600 transition hover:bg-surface-100 dark:border-surface-600 dark:text-surface-400">
                                Aperçu
                            </button>
                        </div>
                    </div>

                    <!-- Paiements (inchangé mais avec couleurs subtiles) -->
                    <div v-if="selectedInvoicePayments.length" class="space-y-2">
                        <p class="text-xs font-semibold text-surface-700 dark:text-surface-300">Paiements</p>
                        <div v-for="payment in selectedInvoicePayments" :key="payment.id"
                             class="rounded-md border border-surface-200 bg-surface-50 p-2 dark:border-surface-700 dark:bg-surface-800">
                            <!-- reste identique -->
                        </div>
                    </div>
                </div>

                <div v-else class="flex flex-col items-center py-12 text-center text-surface-400">
                    <i class="pi pi-click text-3xl mb-2"></i>
                    <p class="text-sm">Sélectionnez une consultation</p>
                </div>
            </div>
        </aside>
    </div>
</template>
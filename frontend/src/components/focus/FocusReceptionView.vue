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
import { computed, defineEmits, defineProps, ref, toRefs } from 'vue';

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

const newestFirstSecretary = ref(false);

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
        return newestFirstSecretary.value ? rightTime - leftTime : leftTime - rightTime;
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

const getConsultationBilling = (consultation) => {
    if (!consultation?.id) return null;
    return props.billingByConsultation?.[consultation.id] || null;
};

const isConsultationPayante = (consultation) => Number(getConsultationBilling(consultation)?.total ?? 0) > 0;

const getConsultationTicketPaymentId = (consultation) => {
    const payments = getConsultationBilling(consultation)?.payments || [];
    const validatedClient = payments.find((payment) => payment?.status === 'validated' && payment?.rolePaiement !== 'insurance');
    if (validatedClient?.id) return validatedClient.id;
    const validated = payments.find((payment) => payment?.status === 'validated');
    if (validated?.id) return validated.id;
    return payments[0]?.id || null;
};

const canPrintConsultationTicket = (consultation) => Boolean(getConsultationTicketPaymentId(consultation));

const printConsultationTicket = async (consultation) => {
    const paymentId = getConsultationTicketPaymentId(consultation);
    if (!paymentId) return;
    await printPaymentTicket(paymentId);
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

function formatPaymentMode(mode) {
    if (!mode) return '—'
    
    if (mode.toLowerCase().includes('esp')) return 'Espèces'
    if (mode.toLowerCase().includes('mobile')) return 'Mobile'
    if (mode.toLowerCase().includes('carte')) return 'Carte'
    
    return mode
}
</script>

<template>
    <div class="grid gap-4 xl:grid-cols-[350px_minmax(0,1fr)_400px]">
        <aside class="space-y-3 max-h-[75vh] overflow-y-auto">
            <div
                class="rounded-xl border-2 border-surface-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">
                <!-- En-tête -->
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400">
                            <i class="pi pi-users text-xs"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-surface-900 dark:text-surface-50">Nouveaux patients
                            </h3>
                            <p class="text-xs text-surface-500">{{ newPatients.length }} aujourd’hui</p>
                        </div>
                    </div>
                    <button @click="emit('open-create-patient')"
                        class="flex items-center gap-1 rounded-lg bg-primary-500 px-3 py-1.5 text-xs font-medium text-white transition-all hover:bg-primary-600 active:scale-95 shadow-sm">
                        <i class="pi pi-plus text-xs"></i>
                        <span class="hidden sm:inline">Ajouter</span>
                    </button>
                </div>

                <!-- Squelettes de chargement -->
                <div v-if="loading" class="space-y-2">
                    <div v-for="i in 3" :key="i"
                        class="flex items-center gap-3 rounded-xl bg-surface-50 p-3 dark:bg-surface-800/40">
                        <Skeleton shape="circle" size="2.5rem" class="flex-shrink-0" />
                        <div class="flex-1 space-y-2">
                            <Skeleton width="60%" height="0.75rem" />
                            <Skeleton width="80%" height="0.625rem" />
                        </div>
                    </div>
                </div>

                <!-- Liste des patients -->
                <div v-else-if="newPatients.length" class="space-y-2">
                    <div v-for="patient in newPatients" :key="patient.id"
                        class="group flex items-center gap-3 rounded-xl border border-surface-100 bg-surface-50/80 p-3 transition-all hover:border-purple-200 hover:bg-purple-50/50 hover:shadow-sm dark:border-surface-700 dark:bg-surface-800/60 dark:hover:border-purple-800 dark:hover:bg-purple-950/30">
                        <!-- Avatar avec initiales -->
                        <div
                            class="flex h-5 w-5 text-xs flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-purple-500 to-fuchsia-500 font-bold text-white shadow-sm">
                            {{ (patient.prenom?.[0] ?? '') + (patient.nom?.[0] ?? 'P') }}
                        </div>

                        <!-- Infos -->
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-surface-900 dark:text-surface-50">
                                {{ patientDisplayName(patient) }}
                            </p>
                            <div
                                class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-surface-500 dark:text-surface-400">
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
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-900/40 dark:text-purple-400">
                            <i class="pi pi-sparkles text-[10px]"></i>
                        </span>
                    </div>
                </div>

                <!-- État vide -->
                <div v-else class="flex flex-col items-center justify-center py-8 text-center text-surface-400">
                    <div
                        class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800">
                        <i class="pi pi-user-plus text-xl opacity-50"></i>
                    </div>
                    <p class="text-sm font-medium">Aucun nouveau patient</p>
                    <p class="mt-1 text-xs">Les nouveaux arrivants s’afficheront ici</p>
                </div>
            </div>
        </aside>

        <!-- Colonne Centre - Liste consultations -->
        <section class="max-h-[75vh] overflow-y-auto">
            <div class="rounded-xl border border-surface-200 bg-white dark:border-surface-700 dark:bg-surface-900">

                <!-- Header -->
                <div
                    class="flex items-center justify-between px-4 py-3 border-b border-surface-200 dark:border-surface-700">
                    <div>
                        <h3 class="text-sm font-semibold text-surface-900 dark:text-surface-50">
                            File d'attente
                        </h3>
                        <p class="text-xs text-surface-500">
                            {{ secretaryRows.length }} consultations
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <ToggleSwitch v-model="showCompletedSecretary" />
                        <Button
                            :icon="newestFirstSecretary ? 'pi pi-sort-amount-down' : 'pi pi-sort-amount-up'"
                            text
                            rounded
                            size="small"
                            :aria-label="newestFirstSecretary ? 'Plus récentes en haut' : 'Plus anciennes en haut'"
                            @click="newestFirstSecretary = !newestFirstSecretary"
                        />

                        <button @click="emit('open-create-consultation')"
                            class="flex items-center gap-1 rounded-md bg-primary-500 px-2.5 py-1 text-xs text-white shadow-sm">
                            <i class="pi pi-plus text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- CONTENT -->
                <div class="p-3">

                    <!-- Loading -->
                    <div v-if="loading" class="space-y-3">
                        <Skeleton v-for="i in 4" :key="i" height="60px" class="rounded-lg" />
                    </div>

                    <!-- Timeline -->
                    <div v-else-if="secretaryRows.length" class="relative">

                        <!-- Ligne verticale -->
                        <div class="absolute left-4 top-0 bottom-0 w-px bg-surface-200 dark:bg-surface-700"></div>

                        <div class="space-y-3">
                            <button v-for="(consultation, index) in secretaryRows" :key="consultation.id"
                                @click="selectConsultation(consultation.id)"
                                class="relative flex gap-3 w-full text-left group">

                                <!-- Index / Position -->
                                <div class="relative z-10 flex flex-col items-center">
                                    <div :class="[
                                        'w-7 h-7 flex items-center justify-center text-xs font-bold rounded-full border transition-all',

                                        consultation.id === selectedConsultationId
                                            ? 'bg-primary-500 text-white border-primary-500 scale-110 shadow'
                                            : Number(consultation.state) === 1
                                                ? 'bg-green-100 text-green-700 border-green-300'
                                                : 'bg-white dark:bg-surface-800 text-surface-500 border-surface-300'
                                    ]">
                                        {{ index + 1 }}
                                    </div>
                                </div>

                                <!-- Card -->
                                <div :class="[
                                    'flex-1 rounded-lg border px-3 py-2.5 transition-all',

                                    consultation.id === selectedConsultationId
                                        ? 'bg-primary-50 border-primary-300 shadow-sm dark:bg-primary-900/20'
                                        : Number(consultation.state) === 1
                                            ? 'bg-green-50/40 border-green-200 opacity-70 dark:bg-green-900/10'
                                            : 'bg-surface-50 border-surface-200 hover:shadow-sm dark:bg-surface-800'
                                ]">

                                    <!-- Ligne 1 -->
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="font-mono text-surface-500">
                                            {{ formatTime(consultation.createdAt) }}
                                        </span>

                                        <!-- Statut -->
                                        <span :class="[
                                            'px-2 py-0.5 rounded-full font-medium',
                                            Number(consultation.state) === 1
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-400'
                                                : 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-400'
                                        ]">
                                            {{ Number(consultation.state) === 1 ? 'Terminé' : 'En attente' }}
                                        </span>
                                    </div>

                                    <!-- Patient -->
                                    <p class="text-sm font-semibold text-surface-900 truncate dark:text-surface-50">
                                        {{ patientLabel(consultation) }}
                                    </p>

                                    <!-- Infos secondaires -->
                                    <p class="text-xs text-surface-500 truncate">
                                        {{ medecinLabel(consultation) }}
                                        <span v-if="consultation.motif"> · {{ consultation.motif }}</span>
                                    </p>

                                    <!-- Footer léger -->
                                    <div class="flex items-center justify-between mt-1 text-xs">

                                        <!-- Type patient -->
                                        <span :class="[
                                            consultation.hasFiche || consultation.lastFicheId
                                                ? 'text-blue-600 dark:text-blue-400'
                                                : 'text-purple-600 dark:text-purple-400'
                                        ]">
                                            {{ consultation.hasFiche || consultation.lastFicheId ? 'Ancien' : 'Nouveau'
                                            }}
                                        </span>

                                        <!-- Facture -->
                                        <div class="flex items-center gap-2">
                                            <span :class="[
                                                consultation?.factState === 1
                                                    ? 'text-emerald-600'
                                                    : consultation?.factState === 0
                                                        ? 'text-sky-600'
                                                        : 'text-gray-400'
                                            ]">
                                                {{ formatFactureState(consultation).label }}
                                            </span>

                                            <span
                                                v-if="isConsultationPayante(consultation)"
                                                class="inline-flex items-center gap-1 rounded-md border border-primary-200 bg-primary-50 px-2 py-0.5 text-[11px] font-medium text-primary-700"
                                                :class="!canPrintConsultationTicket(consultation) ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-primary-100'"
                                                role="button"
                                                tabindex="0"
                                                @click.stop="canPrintConsultationTicket(consultation) && printConsultationTicket(consultation)"
                                                @keydown.enter.stop.prevent="canPrintConsultationTicket(consultation) && printConsultationTicket(consultation)"
                                            >
                                                <i class="pi pi-print"></i>
                                                Ticket
                                            </span>
                                        </div>
                                    </div>

                                </div>
                            </button>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center py-10 text-center text-surface-500">
                        <i class="pi pi-calendar-times text-3xl mb-2 opacity-50"></i>
                        <p class="text-sm">Aucune consultation aujourd’hui</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Colonne Droite - Détails -->
        <aside class="max-h-[75vh] overflow-y-auto">
    <div class="rounded-xl border border-surface-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">

        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-surface-900 dark:text-surface-50">
                Détails
            </h3>
            <span v-if="currentConsultation" class="text-xs text-primary-500 font-medium">
                ● Actif
            </span>
        </div>

        <!-- CONTENT -->
        <div v-if="currentConsultation" class="space-y-4">

            <!-- PATIENT CARD -->
            <div class="rounded-xl border p-3 flex items-start gap-3"
                :class="Number(currentConsultation.state) === 1 
                    ? 'bg-green-50/50 border-green-200 dark:bg-green-950/10' 
                    : 'bg-primary-50/50 border-primary-200 dark:bg-primary-950/10'">

                <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold"
                    :class="Number(currentConsultation.state) === 1 ? 'bg-green-500' : 'bg-primary-500'">
                    {{ patientLabel(currentConsultation).charAt(0) }}
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-surface-900 truncate dark:text-surface-50">
                        {{ patientLabel(currentConsultation) }}
                    </p>
                    <p class="text-xs text-surface-500 truncate">
                        {{ medecinLabel(currentConsultation) }}
                    </p>
                    <p class="text-[11px] text-surface-400 mt-0.5">
                        {{ formatTime(currentConsultation.createdAt) }}
                    </p>
                </div>

                <!-- Etat -->
                <span :class="[
                    'text-xs px-2 py-0.5 rounded-full font-medium',
                    Number(currentConsultation.state) === 1
                        ? 'bg-green-100 text-green-700'
                        : 'bg-amber-100 text-amber-700'
                ]">
                    {{ Number(currentConsultation.state) === 1 ? 'Terminé' : 'En cours' }}
                </span>
            </div>

            <!-- ACTIONS -->
            <div class="flex gap-2 flex-wrap">

                <button @click="emit('open-details', currentConsultation)"
                    class="flex-1 rounded-md border border-surface-300 px-3 py-1.5 text-xs text-surface-600 hover:bg-surface-100 dark:border-surface-600 dark:text-surface-400">
                    <i class="pi pi-eye mr-1"></i>Détails
                </button>

                <button v-if="allowReceptionQuickClose && Number(currentConsultation.state) !== 1"
                    @click="emit('open-quick-dialog', currentConsultation)"
                    class="flex-1 rounded-md bg-amber-500 px-3 py-1.5 text-xs text-white hover:bg-amber-600">
                    <i class="pi pi-bolt mr-1"></i>Clôture
                </button>

                <button v-if="isAdmin && Number(currentConsultation.state) !== 1"
                    @click="emit('select-medical-workspace', currentConsultation, currentConsultation.hasFiche || currentConsultation.lastFicheId ? 'continue-last' : 'new-fiche')"
                    class="flex-1 rounded-md border border-primary-500 px-3 py-1.5 text-xs text-primary-600 hover:bg-primary-50">
                    <i class="pi pi-arrow-right mr-1"></i>Médical
                </button>

            </div>
 
            <div v-if="hasInvoiceContext"
                class="rounded-xl border p-3 bg-surface-50 dark:bg-surface-800 border-surface-200 dark:border-surface-700">
 
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-surface-700 dark:text-surface-300">
                        Facture
                    </span>

                    <span :class="[
                        'text-xs px-2 py-0.5 rounded-full font-medium',
                        isPaidInvoice ? 'bg-green-100 text-green-700' :
                        isFreeInvoice ? 'bg-blue-100 text-blue-700' :
                        'bg-amber-100 text-amber-700'
                    ]">
                        {{ isPaidInvoice ? 'Réglée' : isFreeInvoice ? 'Gratuite' : 'En attente' }}
                    </span>
                </div>
 
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between">
                        <span class="text-surface-500">Total</span>
                        <span class="font-semibold">{{ formatFcfa(selectedInvoiceTotal) }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-surface-500">Reste</span>
                        <span :class="selectedInvoiceRemaining > 0 ? 'text-red-600 font-semibold' : 'text-green-600 font-semibold'">
                            {{ selectedInvoiceRemaining === null ? '--' : formatFcfa(selectedInvoiceRemaining) }}
                        </span>
                    </div>
                </div> 
                <div class="flex gap-2 mt-3"> 
                    <button v-if="!isPaidInvoice && !isFreeInvoice"
                        @click="emit('open-caisse-pay')"
                        class="flex-1 rounded-md bg-green-500 px-3 py-1.5 text-xs text-white hover:bg-green-600">
                        Régler
                    </button>

                    <button v-if="isFreeInvoice"
                        @click="emit('open-caisse-validate')"
                        class="flex-1 rounded-md bg-blue-500 px-3 py-1.5 text-xs text-white hover:bg-blue-600">
                        Valider
                    </button>

                    <button @click="emit('open-caisse-preview')"
                        class="rounded-md border border-surface-300 px-3 py-1.5 text-xs text-surface-600 hover:bg-surface-100">
                        Aperçu
                    </button>
                </div>
            </div>
 
            <div v-if="selectedInvoicePayments.length" class="space-y-2">
                <p class="text-xs font-semibold text-surface-700 dark:text-surface-300">
                    Paiements
                </p>

                <div 
                    v-for="payment in selectedInvoicePayments" 
                    :key="payment.id"
                    class="flex items-center justify-between rounded-lg border px-3 py-2 text-xs
                        bg-surface-50 dark:bg-surface-800 border-surface-200 dark:border-surface-700"
                > 
                    <div class="flex items-center gap-3 min-w-0">
 
                        <div class="w-8 h-8 flex items-center justify-center rounded-full text-white text-xs font-bold"
                            :class="payment.status === 'validated' ? 'bg-green-500' : 'bg-amber-500'">
                            <i class="pi pi-wallet text-xs"></i>
                        </div>
                        
                        <div class="min-w-0">
                            <p class="font-semibold text-surface-900 dark:text-white truncate">
                                {{ formatFcfa(payment.montant) }}
                            </p>

                            <p class="text-[11px] text-surface-500 truncate">
                                {{ formatPaymentMode(payment.mode) }} · {{ payment.rolePaiement }}
                            </p>
                        </div>
                    </div>

                    <!-- RIGHT -->
                    <div class="text-right flex flex-col items-end">

                        <!-- Status -->
                        <span :class="[
                            'px-2 py-0.5 rounded-full text-[10px] font-medium',
                            payment.status === 'validated'
                                ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-400'
                                : 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-400'
                        ]">
                            {{ payment.status === 'validated' ? 'Validé' : 'En attente' }}
                        </span>

                        <!-- Time -->
                        <span class="text-[10px] text-surface-400 mt-1">
                            {{ formatTime(payment.date) }}
                        </span>

                        <Button 
                            v-if="payment.status === 'validated'"
                            @click="printPaymentReceipt(payment.id)"
                            icon="pi pi-receipt" size="small" class="mt-2"
                        /> 
                    </div>
                </div>
            </div>

        </div>

        <!-- EMPTY -->
        <div v-else class="flex flex-col items-center py-12 text-center text-surface-400">
            <i class="pi pi-click text-3xl mb-2"></i>
            <p class="text-sm">Sélectionnez une consultation</p>
        </div>

    </div>
</aside>
    </div>
</template>
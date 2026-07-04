<script setup>
import PrintDevisBody from '@/components/print/PrintDevisBody.vue';
import PrintReceiptBody from '@/components/print/PrintReceiptBody.vue';
import PrintTicketBody from '@/components/print/PrintTicketBody.vue';
import { usePrinter } from '@/composables/usePrinter';
import { fetchInvoicePrintData, fetchReceiptPrintData, fetchTicketPrintData } from '@/services/printService';
import { searchPatients } from '@/services/patients';
import Dialog from 'primevue/dialog';
import { computed, ref, toRefs } from 'vue';

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
    consultationToolbarLoading: {
        type: Boolean,
        default: false
    },
    consultationLoadingByPatient: {
        type: Object,
        default: () => ({})
    },
    allowReceptionQuickClose: {
        type: Boolean,
        default: true
    },
    allowReceptionInvoiceModification: {
        type: Boolean,
        default: false
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
    'open-create-consultation-for-patient',
    'open-edit-patient',
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

const searchMode = ref(false);
const patientSearchQuery = ref('');
const patientSearchResults = ref([]);
const patientSearchLoading = ref(false);
let searchDebounceTimer = null;

const toggleSearchMode = () => {
    searchMode.value = !searchMode.value;
    if (!searchMode.value) {
        patientSearchQuery.value = '';
        patientSearchResults.value = [];
    }
};

const performPatientSearch = async () => {
    const q = patientSearchQuery.value.trim();
    if (!q || q.length < 2) {
        patientSearchResults.value = [];
        return;
    }
    patientSearchLoading.value = true;
    try {
        patientSearchResults.value = await searchPatients(q, token, 15);
    } catch (_) {
        patientSearchResults.value = [];
    } finally {
        patientSearchLoading.value = false;
    }
};

const onPatientSearchInput = () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(performPatientSearch, 350);
};

const { consultations, selectedConsultationId } = toRefs(props);
const token = localStorage.getItem('token');
const { printComponent } = usePrinter();

const showCompletedSecretary = defineModel('showCompletedSecretary', {
    type: Boolean,
    default: false
});

const newestFirstSecretary = ref(false);
const showRevenueStatsModal = ref(false);

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
const isInsurancePayment = (payment) => {
    const role = String(payment?.rolePaiement || '').toLowerCase();
    const mode = String(payment?.mode || '').toLowerCase();

    return role === 'insurance' || mode.includes('assur');
};

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

const todayConsultations = computed(() => {
    const now = new Date();
    return (consultations.value || []).filter((consultation) => isSameCalendarDay(consultation?.createdAt, now));
});

const dailyRevenueStats = computed(() => {
    const invoiceMap = new Map();
    const paymentMap = new Map();
    let totalRevenue = 0;

    for (const consultation of todayConsultations.value) {
        totalRevenue += Number(consultation.paiementAmount ?? 0) || 0;
        const billing = props.billingByConsultation?.[consultation.id] || null;
        if (!billing) {
            continue;
        }

        const invoiceId = Number(billing.invoiceId ?? 0);
        if (invoiceId > 0 && !invoiceMap.has(invoiceId)) {
            invoiceMap.set(invoiceId, {
                id: invoiceId,
                total: Number(billing.total ?? 0) || 0,
                remaining: Number(billing.remaining ?? 0) || 0,
                state: billing.state || null
            });
        }

        const payments = Array.isArray(billing.payments) ? billing.payments : [];
        for (const payment of payments) {
            const paymentKey = payment?.id ?? `${consultation.id}-${payment?.invoiceId ?? 'x'}-${payment?.date ?? 'n/a'}-${payment?.montant ?? 0}-${payment?.mode ?? '—'}`;
            if (paymentMap.has(paymentKey)) {
                continue;
            }

            paymentMap.set(paymentKey, payment);
        }
    }

    const invoices = Array.from(invoiceMap.values());
    const payments = Array.from(paymentMap.values());
    totalRevenue = totalRevenue + payments.reduce((sum, payment) => sum + (Number(payment?.montant) || 0), 0);
    const totalUnpaid = invoices.reduce((sum, invoice) => sum + (Number(invoice?.remaining) || 0), 0);
    const paymentModeRows = Object.entries(
        payments.reduce((acc, payment) => {
            const mode = payment?.mode || 'Autre';
            acc[mode] = (acc[mode] || 0) + (Number(payment?.montant) || 0);
            return acc;
        }, {})
    )
        .map(([mode, amount]) => ({ mode, amount: Number(amount) || 0 }))
        .sort((left, right) => right.amount - left.amount);

    const statusCounts = {
        paid: 0,
        partial: 0,
        unpaid: 0,
        freeNotValidated: 0
    };

    invoices.forEach((invoice) => {
        const total = Number(invoice.total) || 0;
        const remaining = Number(invoice.remaining) || 0;
        const severity = invoice.state?.severity || null;

        if (remaining === 0 && total > 0) {
            statusCounts.paid += 1;
            return;
        }

        if (remaining === 0 && total === 0 && severity !== 'success') {
            statusCounts.freeNotValidated += 1;
            return;
        }

        if (remaining === total) {
            statusCounts.unpaid += 1;
            return;
        }

        statusCounts.partial += 1;
    });

    const insurancePayments = payments.filter((payment) => isInsurancePayment(payment));

    return {
        totalConsultations: todayConsultations.value.length,
        totalInvoices: invoices.length,
        totalRevenue: totalRevenue,
        totalUnpaid,
        totalPaymentsCount: payments.length,
        totalInsurance: insurancePayments.reduce((sum, payment) => sum + (Number(payment?.montant) || 0), 0),
        pendingInsurance: insurancePayments.filter((payment) => payment?.status === 'pending').length,
        paymentModeRows,
        statusCounts
    };
});

const revenueButtonLabel = computed(() => `Recette · ${formatFcfa(dailyRevenueStats.value.totalRevenue)}`);

const selectedInvoicePayments = computed(() => {
    const payments = currentBilling.value?.payments || [];
    const invoiceId = currentBilling.value?.invoiceId ?? null;

    if (!invoiceId) return [];

    return payments.filter((payment) => Number(payment?.invoiceId) === Number(invoiceId));
});

const selectedInvoiceState = computed(() => currentBilling.value?.state || { label: 'Aucune facture', severity: 'contrast' });

const selectedInvoiceTotal = computed(() => Number(currentBilling.value?.total ?? 0) || 0);

const selectedInvoiceRemaining = computed(() => currentBilling.value ? (Number(currentBilling.value.remaining ?? 0) || 0) : null);

const consultationDetailsLines = computed(() => currentBilling.value?.lines || []);
const hasInvoiceContext = computed(() => Boolean(currentBilling.value?.invoiceId));
const isPaidInvoice = computed(() => hasInvoiceContext.value && selectedInvoiceRemaining.value === 0 && selectedInvoiceTotal.value > 0);
const isFreeInvoice = computed(() => hasInvoiceContext.value && selectedInvoiceRemaining.value === 0 && selectedInvoiceTotal.value === 0);
const isValidatedFreeInvoice = computed(() => isFreeInvoice.value && selectedInvoiceState.value?.severity === 'success');
const canModifyInvoice = computed(() => {
    if (!props.allowReceptionInvoiceModification) return false;
    if (!hasInvoiceContext.value) return false;
    const payments = Array.isArray(currentBilling.value?.payments) ? currentBilling.value.payments : [];
    if (payments.length > 0) return false;
    return selectedInvoiceTotal.value > 0 && selectedInvoiceRemaining.value === selectedInvoiceTotal.value;
});
const canPreviewInvoice = computed(() => hasInvoiceContext.value && !(selectedInvoiceTotal.value === 0 && selectedInvoiceRemaining.value === 0));

const selectConsultation = (consultationId) => {
    emit('select-consultation', consultationId);
};

const getConsultationBilling = (consultation) => {
    if (!consultation?.id) return null;
    return props.billingByConsultation?.[consultation.id] || null;
};

const isConsultationPayante = (consultation) => {
    return consultation?.isPaid
};

const isConsultationCreateLoading = (patientId) => {
    if (patientId === undefined || patientId === null) return false;
    return Boolean(props.consultationLoadingByPatient?.[patientId]);
};

const getConsultationPaymentId = (consultation) => {
    if (!consultation) return null;

    if (consultation.paymentId ?? consultation.paiementId) {
        return consultation.paymentId ?? consultation.paiementId;
    }

    const billing = getConsultationBilling(consultation);
    const payments = Array.isArray(billing?.payments) ? billing.payments : [];
    return payments[0]?.id ?? null;
};

const printConsultationTicket = async (consultation) => {
    if (!consultation) return;
    await printPaymentTicket(getConsultationPaymentId(consultation));
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

const isInteractiveTarget = (target) => target instanceof Element
    && Boolean(target.closest('button, a, input, select, textarea, [role="button"], .p-button'));

const onQueueItemClick = (event, consultationId) => {
    if (isInteractiveTarget(event?.target)) return;
    selectConsultation(consultationId);
};

const handleCancelWithConfirm = (event, consultation) => {
    if (!consultation?.id) return;
    const sourceEvent = event?.originalEvent || event;
    const target = sourceEvent?.currentTarget
        || sourceEvent?.target?.closest?.('[data-cancel-consultation-id], .p-button, button')
        || sourceEvent?.target
        || null;
    emit('cancel-consultation', target, consultation);
};

</script>

<template>
    <div class="grid gap-5 xl:grid-cols-[360px_minmax(0,1fr)_420px]">
        <!-- Colonne Gauche - Nouveaux patients -->
        <aside class="space-y-3 max-h-[calc(100vh-180px)] overflow-y-auto scrollbar-thin">
            <div class="rounded-2xl border border-surface-200/60 bg-white/90 backdrop-blur-sm shadow-lg dark:border-surface-700/60 dark:bg-surface-900/90">
                <!-- En-tête -->
                <div class="border-b border-surface-200/60 px-4 py-3 dark:border-surface-700/60">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 shadow-md">
                                <i class="pi pi-users text-sm text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-surface-900 dark:text-surface-50">Nouveaux patients</h3>
                                <p class="text-xs text-surface-500">{{ newPatients.length }} aujourd'hui</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                @click="toggleSearchMode"
                                :class="[
                                    'flex h-8 w-8 items-center justify-center rounded-xl transition-all duration-200',
                                    searchMode
                                        ? 'bg-purple-500 text-white shadow-md'
                                        : 'bg-surface-100 text-surface-500 hover:bg-surface-200 dark:bg-surface-800 dark:hover:bg-surface-700'
                                ]"
                                :title="searchMode ? 'Fermer la recherche' : 'Rechercher un patient'"
                            >
                                <i :class="searchMode ? 'pi pi-times text-xs' : 'pi pi-search text-sm'"></i>
                            </button>
                            <button
                                @click="emit('open-create-patient')"
                                class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 px-3.5 py-1.5 text-xs font-medium text-white shadow-md transition-all hover:shadow-lg hover:scale-105 active:scale-95"
                            >
                                <i class="pi pi-plus text-xs"></i>
                                <span class="hidden sm:inline">Ajouter</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Recherche -->
                <div v-if="searchMode" class="p-4 space-y-3 border-b border-surface-200/60 dark:border-surface-700/60">
                    <div class="relative">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-sm"></i>
                        <input
                            v-model="patientSearchQuery"
                            type="text"
                            placeholder="Nom, prénom ou téléphone..."
                            class="w-full rounded-xl border-0 bg-surface-100/60 pl-9 pr-8 py-2.5 text-sm placeholder:text-surface-400 focus:ring-2 focus:ring-purple-500 dark:bg-surface-800/60"
                            @input="onPatientSearchInput"
                            autofocus
                        />
                        <button
                            v-if="patientSearchQuery"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600"
                            @click="patientSearchQuery = ''; patientSearchResults = []"
                        >
                            <i class="pi pi-times text-xs"></i>
                        </button>
                    </div>

                    <!-- Résultats recherche -->
                    <div class="space-y-2 max-h-[400px] overflow-y-auto">
                        <!-- Loading skeleton -->
                        <div v-if="patientSearchLoading" class="space-y-2">
                            <div v-for="i in 3" :key="`search-skeleton-${i}`" class="flex items-center gap-3 rounded-xl bg-surface-100/50 p-3 animate-pulse dark:bg-surface-800/30">
                                <div class="h-9 w-9 rounded-full bg-gradient-to-br from-purple-400 to-purple-500/50"></div>
                                <div class="flex-1 space-y-2">
                                    <div class="h-2.5 w-24 rounded bg-surface-300 dark:bg-surface-600"></div>
                                    <div class="h-2 w-32 rounded bg-surface-200 dark:bg-surface-700"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Résultats -->
                        <div v-else-if="patientSearchResults.length" class="space-y-2">
                            <div v-for="patient in patientSearchResults" :key="`search-${patient.id}`"
                                class="group flex items-center gap-3 rounded-xl bg-surface-50/50 p-3 transition-all hover:bg-purple-50/30 hover:shadow-md dark:bg-surface-800/30 dark:hover:bg-purple-900/20">
                                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-purple-500 to-purple-600 font-bold text-white shadow-md">
                                    {{ ((patient.prenom?.[0] ?? '') + (patient.nom?.[0] ?? 'P')).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-surface-900 dark:text-surface-50">
                                        {{ patientDisplayName(patient) }}
                                    </p>
                                    <div class="mt-0.5 flex items-center gap-1 truncate text-xs text-surface-400">
                                        <i class="pi pi-phone text-[10px]"></i>
                                        {{ patient.telephone || 'Non renseigné' }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 opacity-0 transition-opacity group-hover:opacity-100">
                                    <button
                                        :disabled="isConsultationCreateLoading(patient?.id)"
                                        :class="[
                                            'flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 transition-colors dark:bg-emerald-900/30 dark:text-emerald-400',
                                            isConsultationCreateLoading(patient?.id)
                                                ? 'cursor-not-allowed opacity-60'
                                                : 'hover:bg-emerald-200'
                                        ]"
                                        title="Nouvelle consultation"
                                        @click="emit('open-create-consultation-for-patient', patient)"
                                    >
                                        <i :class="isConsultationCreateLoading(patient?.id) ? 'pi pi-spin pi-spinner text-xs' : 'fas fa-stethoscope text-xs'"></i>
                                    </button>
                                    <button
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-600 transition-colors hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400"
                                        title="Modifier le patient"
                                        @click="emit('open-edit-patient', patient)"
                                    >
                                        <i class="pi pi-user-edit text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Aucun résultat -->
                        <div v-else-if="patientSearchQuery.length >= 2" class="flex flex-col items-center justify-center py-8 text-center">
                            <div class="mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800">
                                <i class="pi pi-search text-2xl text-surface-400"></i>
                            </div>
                            <p class="text-sm font-medium text-surface-600">Aucun patient trouvé</p>
                            <p class="mt-1 text-xs text-surface-400">Vérifiez l'orthographe ou essayez le téléphone</p>
                        </div>
                    </div>
                </div>

                <!-- Liste patients -->
                <div v-else class="p-3 space-y-2">
                    <!-- Skeleton loading -->
                    <div v-if="loading" class="space-y-2">
                        <div v-for="i in 3" :key="i" class="flex items-center gap-3 rounded-xl bg-surface-100/50 p-3 dark:bg-surface-800/30">
                            <div class="h-9 w-9 rounded-full bg-gradient-to-br from-purple-400 to-purple-500/50 animate-pulse"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-2.5 w-28 rounded bg-surface-300 animate-pulse dark:bg-surface-600"></div>
                                <div class="h-2 w-36 rounded bg-surface-200 animate-pulse dark:bg-surface-700"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Liste -->
                    <div v-else-if="newPatients.length" class="space-y-2">
                        <div v-for="patient in newPatients" :key="patient.id"
                            class="group flex items-center gap-3 rounded-xl bg-gradient-to-r from-transparent to-transparent p-3 transition-all hover:bg-purple-50/30 hover:shadow-md dark:hover:bg-purple-900/10">
                            <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-purple-500 to-fuchsia-500 font-bold text-white shadow-md">
                                {{ (patient.prenom?.[0] ?? '') + (patient.nom?.[0] ?? 'P') }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-surface-900 dark:text-surface-50">
                                    {{ patientDisplayName(patient) }}
                                </p>
                                <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-surface-400">
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
                            <div class="flex items-center gap-2 opacity-0 transition-opacity group-hover:opacity-100">
                                <button
                                    :disabled="isConsultationCreateLoading(patient?.id)"
                                    :class="[
                                        'flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 transition-colors dark:bg-emerald-900/30 dark:text-emerald-400',
                                        isConsultationCreateLoading(patient?.id)
                                            ? 'cursor-not-allowed opacity-60'
                                            : 'hover:bg-emerald-200'
                                    ]"
                                    title="Nouvelle consultation"
                                    @click="emit('open-create-consultation-for-patient', patient)"
                                >
                                    <i :class="isConsultationCreateLoading(patient?.id) ? 'pi pi-spin pi-spinner text-xs' : 'fas fa-stethoscope text-xs'"></i>
                                </button>
                                <button
                                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-600 transition-colors hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400"
                                    title="Modifier le patient"
                                    @click="emit('open-edit-patient', patient)"
                                >
                                    <i class="pi pi-user-edit text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- État vide -->
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-surface-100 to-surface-200 dark:from-surface-800 dark:to-surface-700">
                            <i class="pi pi-user-plus text-3xl text-surface-400"></i>
                        </div>
                        <p class="text-sm font-medium text-surface-600">Aucun nouveau patient</p>
                        <p class="mt-1 text-xs text-surface-400">Les nouveaux arrivants s'afficheront ici</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Colonne Centre - File d'attente -->
        <section class="max-h-[calc(100vh-180px)] overflow-y-auto scrollbar-thin">
            <div class="rounded-2xl border border-surface-200/60 bg-white/90 backdrop-blur-sm shadow-lg dark:border-surface-700/60 dark:bg-surface-900/90">
                <!-- Header -->
                <div class="border-b border-surface-200/60 px-4 py-3 dark:border-surface-700/60">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-surface-900 dark:text-surface-50">File d'attente</h3>
                            <p class="text-xs text-surface-500">{{ secretaryRows.length }} consultation(s)</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <ToggleSwitch v-model="showCompletedSecretary" />
                            <button
                                :class="[
                                    'flex h-8 w-8 items-center justify-center rounded-xl transition-all',
                                    newestFirstSecretary
                                        ? 'bg-primary-500 text-white shadow-md'
                                        : 'bg-surface-100 text-surface-500 hover:bg-surface-200'
                                ]"
                                :title="newestFirstSecretary ? 'Plus récentes en haut' : 'Plus anciennes en haut'"
                                @click="newestFirstSecretary = !newestFirstSecretary"
                            >
                                <i :class="newestFirstSecretary ? 'pi pi-arrow-down' : 'pi pi-arrow-up'" class="text-xs"></i>
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm transition-colors hover:bg-emerald-100 dark:border-emerald-800/60 dark:bg-emerald-950/30 dark:text-emerald-300 dark:hover:bg-emerald-900/30"
                                @click="showRevenueStatsModal = true"
                            >
                                {{ revenueButtonLabel }}
                            </button>
                            <button
                                @click="emit('open-create-consultation')"
                                :disabled="consultationToolbarLoading"
                                class="rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 p-1.5 text-white shadow-md transition-all hover:shadow-lg hover:scale-105 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <i :class="consultationToolbarLoading ? 'pi pi-spin pi-spinner text-xs' : 'pi pi-plus text-xs'"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <Dialog v-model:visible="showRevenueStatsModal" modal header="Statistiques de recette du jour" :style="{ width: 'min(960px, 96vw)' }">
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-2xl border border-surface-200 bg-surface-50 p-4 dark:border-surface-700 dark:bg-surface-800/40">
                                <span class="text-xs uppercase tracking-wide text-surface-500">Consultations</span>
                                <strong class="mt-2 block text-2xl text-surface-900 dark:text-surface-50">{{ dailyRevenueStats.totalConsultations }}</strong>
                            </div>
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800/60 dark:bg-emerald-950/20">
                                <span class="text-xs uppercase tracking-wide text-emerald-600 dark:text-emerald-300">Recette totale</span>
                                <strong class="mt-2 block text-2xl text-emerald-700 dark:text-emerald-200">{{ formatFcfa(dailyRevenueStats.totalRevenue) }}</strong>
                            </div>
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800/60 dark:bg-amber-950/20">
                                <span class="text-xs uppercase tracking-wide text-amber-600 dark:text-amber-300">Reste à payer</span>
                                <strong class="mt-2 block text-2xl text-amber-700 dark:text-amber-200">{{ formatFcfa(dailyRevenueStats.totalUnpaid) }}</strong>
                            </div>
                            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-800/60 dark:bg-sky-950/20">
                                <span class="text-xs uppercase tracking-wide text-sky-600 dark:text-sky-300">Paiements</span>
                                <strong class="mt-2 block text-2xl text-sky-700 dark:text-sky-200">{{ dailyRevenueStats.totalPaymentsCount }}</strong>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div class="rounded-2xl border border-surface-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900/50">
                                <h4 class="text-sm font-semibold text-surface-900 dark:text-surface-50">État des factures</h4>
                                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div class="rounded-xl bg-emerald-50 px-3 py-3 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-300">Payées: <strong>{{ dailyRevenueStats.statusCounts.paid }}</strong></div>
                                    <div class="rounded-xl bg-amber-50 px-3 py-3 text-amber-700 dark:bg-amber-950/20 dark:text-amber-300">Partielles: <strong>{{ dailyRevenueStats.statusCounts.partial }}</strong></div>
                                    <div class="rounded-xl bg-rose-50 px-3 py-3 text-rose-700 dark:bg-rose-950/20 dark:text-rose-300">Impayées: <strong>{{ dailyRevenueStats.statusCounts.unpaid }}</strong></div>
                                    <div class="rounded-xl bg-surface-100 px-3 py-3 text-surface-700 dark:bg-surface-800 dark:text-surface-300">Vides non validées: <strong>{{ dailyRevenueStats.statusCounts.freeNotValidated }}</strong></div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-surface-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900/50">
                                <h4 class="text-sm font-semibold text-surface-900 dark:text-surface-50">Assurances</h4>
                                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div class="rounded-xl bg-sky-50 px-3 py-3 text-sky-700 dark:bg-sky-950/20 dark:text-sky-300">Montant: <strong>{{ formatFcfa(dailyRevenueStats.totalInsurance) }}</strong></div>
                                    <div class="rounded-xl bg-amber-50 px-3 py-3 text-amber-700 dark:bg-amber-950/20 dark:text-amber-300">En attente: <strong>{{ dailyRevenueStats.pendingInsurance }}</strong></div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-surface-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900/50">
                            <h4 class="text-sm font-semibold text-surface-900 dark:text-surface-50">Recette par mode de paiement</h4>
                            <div v-if="dailyRevenueStats.paymentModeRows.length" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <div v-for="item in dailyRevenueStats.paymentModeRows" :key="item.mode" class="rounded-xl border border-surface-200 bg-surface-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800/40">
                                    <span class="text-xs uppercase tracking-wide text-surface-500">{{ item.mode }}</span>
                                    <strong class="mt-2 block text-lg text-surface-900 dark:text-surface-50">{{ formatFcfa(item.amount) }}</strong>
                                </div>
                            </div>
                            <div v-else class="mt-4 rounded-xl border border-dashed border-surface-300 px-4 py-5 text-sm text-surface-500 dark:border-surface-700 dark:text-surface-400">
                                Aucun paiement enregistré aujourd'hui.
                            </div>
                        </div>
                    </div>
                </Dialog>

                <!-- Contenu consultations -->
                <div class="p-4">
                    <div v-if="loading" class="space-y-3">
                        <div v-for="i in 4" :key="i" class="rounded-xl bg-surface-100/50 p-3 animate-pulse dark:bg-surface-800/30">
                            <div class="flex gap-3">
                                <div class="h-8 w-8 rounded-full bg-gradient-to-br from-surface-300 to-surface-400"></div>
                                <div class="flex-1 space-y-2">
                                    <div class="h-3 w-20 rounded bg-surface-300"></div>
                                    <div class="h-2.5 w-40 rounded bg-surface-200"></div>
                                    <div class="h-2 w-32 rounded bg-surface-200"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="secretaryRows.length" class="relative">
                        <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gradient-to-b from-primary-300 via-primary-200 to-transparent dark:from-primary-600 dark:via-primary-700"></div>

                        <div class="space-y-3">
                            <div v-for="(consultation, index) in secretaryRows" :key="consultation.id"
                                @click="selectConsultation(consultation.id)"
                                class="group relative flex gap-3 w-full cursor-pointer rounded-xl transition-all focus:outline-none focus:ring-2 focus:ring-primary-400">

                                <div class="relative z-10">
                                    <div :class="[
                                        'flex h-8 w-8 items-center justify-center rounded-full font-bold text-xs transition-all duration-200 shadow-md',
                                        consultation.id === selectedConsultationId
                                            ? 'bg-gradient-to-br from-primary-500 to-primary-600 text-white scale-110 ring-2 ring-primary-300 ring-offset-2'
                                            : Number(consultation.state) === 1
                                                ? 'bg-gradient-to-br from-emerald-500 to-emerald-600 text-white'
                                                : 'bg-gradient-to-br from-surface-400 to-surface-500 text-white dark:from-surface-600 dark:to-surface-700'
                                    ]">
                                        {{ index + 1 }}
                                    </div>
                                </div>

                                <div :class="[
                                    'flex-1 rounded-xl border p-3 transition-all duration-200',
                                    consultation.id === selectedConsultationId
                                        ? 'border-primary-200 bg-gradient-to-r from-primary-50 to-transparent shadow-md dark:border-primary-800 dark:from-primary-950/30'
                                        : Number(consultation.state) === 1
                                            ? 'border-emerald-200 bg-emerald-50/30 opacity-75 dark:border-emerald-800 dark:bg-emerald-950/20'
                                            : 'border-surface-200 bg-surface-50/30 hover:shadow-md hover:border-primary-200 dark:border-surface-700 dark:bg-surface-800/30'
                                ]">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="font-mono text-[11px] text-surface-400">
                                            {{ formatTime(consultation.createdAt) }}
                                        </span>
                                        <span :class="[
                                            'rounded-full px-2 py-0.5 text-[10px] font-medium',
                                            Number(consultation.state) === 1
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400'
                                                : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'
                                        ]">
                                            {{ Number(consultation.state) === 1 ? 'Terminé' : 'En attente' }}
                                        </span>
                                    </div>
                                    <p class="text-sm font-semibold text-surface-900 truncate dark:text-surface-50">
                                        {{ patientLabel(consultation) }}
                                    </p>
                                    <p class="text-xs text-surface-400 truncate mt-0.5">
                                        {{ medecinLabel(consultation) }}
                                        <span v-if="consultation.motif" class="text-surface-300">· {{ consultation.motif }}</span>
                                    </p>
                                    <div class="flex items-center justify-between mt-2">
                                        <button v-if="Number(consultation.state) === 0"
                                            @click="(e) => handleCancelWithConfirm(e, consultation)"
                                            class="rounded-lg bg-red-50 px-3 py-1 text-xs font-medium text-red-600 transition-colors hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400">
                                            Annuler
                                        </button>
                                        <div class="flex items-center gap-2">
                                            <span :class="[
                                                'text-xs font-medium',
                                                consultation?.factState === 1 ? 'text-emerald-600' :
                                                consultation?.factState === 0 ? 'text-sky-600' : 'text-surface-400'
                                            ]">
                                                {{ formatFactureState(consultation).label }}
                                            </span>
                                            <button v-if="isConsultationPayante(consultation)"
                                                @click.stop="printConsultationTicket(consultation)"
                                                class="flex items-center gap-1 rounded-lg bg-primary-50 px-2 py-1 text-[10px] font-medium text-primary-600 transition-colors hover:bg-primary-100 dark:bg-primary-900/30 dark:text-primary-400">
                                                <i class="pi pi-print text-xs"></i>
                                                Ticket
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                        <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-surface-100 to-surface-200 dark:from-surface-800 dark:to-surface-700">
                            <i class="pi pi-calendar-times text-3xl text-surface-400"></i>
                        </div>
                        <p class="text-sm font-medium text-surface-600">Aucune consultation</p>
                        <p class="mt-1 text-xs text-surface-400">Aujourd'hui</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Colonne Droite - Détails -->
        <aside class="max-h-[calc(100vh-180px)] overflow-y-auto scrollbar-thin">
            <div class="rounded-2xl border border-surface-200/60 bg-white/90 backdrop-blur-sm shadow-lg dark:border-surface-700/60 dark:bg-surface-900/90">
                <div class="border-b border-surface-200/60 px-4 py-3 dark:border-surface-700/60">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-surface-900 dark:text-surface-50">Détails</h3>
                        <span v-if="currentConsultation" class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-primary-500"></span>
                        </span>
                    </div>
                </div>

                <div v-if="currentConsultation" class="p-4 space-y-4">
                    <!-- Carte patient -->
                    <div :class="[
                        'rounded-xl border p-4 transition-all',
                        Number(currentConsultation.state) === 1
                            ? 'border-emerald-200 bg-gradient-to-br from-emerald-50 to-transparent dark:border-emerald-800 dark:from-emerald-950/30'
                            : 'border-primary-200 bg-gradient-to-br from-primary-50 to-transparent dark:border-primary-800 dark:from-primary-950/30'
                    ]">
                        <div class="flex items-start gap-3">
                            <div :class="[
                                'flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full text-white font-bold shadow-lg',
                                Number(currentConsultation.state) === 1 ? 'bg-gradient-to-br from-emerald-500 to-emerald-600' : 'bg-gradient-to-br from-primary-500 to-primary-600'
                            ]">
                                {{ patientLabel(currentConsultation).charAt(0).toUpperCase() }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-base font-semibold text-surface-900 truncate dark:text-surface-50">
                                    {{ patientLabel(currentConsultation) }}
                                </p>
                                <p class="text-xs text-surface-400 truncate mt-0.5">
                                    {{ medecinLabel(currentConsultation) }}
                                </p>
                                <p class="text-[11px] text-surface-400 mt-1">
                                    <i class="pi pi-clock mr-1"></i>{{ formatTime(currentConsultation.createdAt) }}
                                </p>
                            </div>
                            <span :class="[
                                'rounded-full px-2 py-1 text-[10px] font-medium',
                                Number(currentConsultation.state) === 1
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400'
                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'
                            ]">
                                {{ Number(currentConsultation.state) === 1 ? 'Terminé' : 'En cours' }}
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="grid grid-cols-2 gap-2">
                        <button @click="emit('open-details', currentConsultation)"
                            class="rounded-xl border border-surface-200 bg-surface-50 px-3 py-2 text-xs font-medium text-surface-600 transition-all hover:bg-surface-100 hover:shadow-sm dark:border-surface-700 dark:bg-surface-800/50">
                            <i class="pi pi-eye mr-1"></i>Détails
                        </button>
                        <button v-if="allowReceptionQuickClose && Number(currentConsultation.state) !== 1"
                            @click="emit('open-quick-dialog', currentConsultation)"
                            class="rounded-xl bg-amber-500 px-3 py-2 text-xs font-medium text-white shadow-md transition-all hover:bg-amber-600">
                            <i class="pi pi-bolt mr-1"></i>Clôture rapide
                        </button>
                        <button v-if="isAdmin && Number(currentConsultation.state) !== 1"
                            @click="emit('select-medical-workspace', currentConsultation, currentConsultation.hasFiche || currentConsultation.lastFicheId ? 'continue-last' : 'new-fiche')"
                            class="rounded-xl border border-primary-500 bg-primary-50 px-3 py-2 text-xs font-medium text-primary-600 transition-all hover:bg-primary-100 dark:bg-primary-950/30">
                            <i class="pi pi-arrow-right mr-1"></i>Espace médical
                        </button>
                    </div>

                    <!-- Facture -->
                    <div v-if="hasInvoiceContext" class="rounded-xl border border-surface-200 bg-surface-50/50 p-4 dark:border-surface-700 dark:bg-surface-800/30">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-semibold text-surface-700">Facture</span>
                            <span :class="[
                                'rounded-full px-2 py-1 text-[10px] font-medium',
                                isPaidInvoice ? 'bg-emerald-100 text-emerald-700' :
                                isValidatedFreeInvoice ? 'bg-emerald-100 text-emerald-700' :
                                isFreeInvoice ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'
                            ]">
                                {{ isPaidInvoice ? 'Réglée' : isValidatedFreeInvoice ? 'Validée' : isFreeInvoice ? 'Gratuite' : 'En attente' }}
                            </span>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-surface-500">Total</span>
                                <span class="font-bold text-surface-900">{{ formatFcfa(selectedInvoiceTotal) }}</span>
                            </div>
                            <div class="flex justify-between pt-1 border-t border-surface-200">
                                <span class="text-surface-500">Reste</span>
                                <span :class="selectedInvoiceRemaining > 0 ? 'text-red-600 font-bold' : 'text-emerald-600 font-bold'">
                                    {{ selectedInvoiceRemaining === null ? '--' : formatFcfa(selectedInvoiceRemaining) }}
                                </span>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-4">
                            <button v-if="!isPaidInvoice && !isFreeInvoice"
                                @click="emit('open-caisse-pay')"
                                class="rounded-xl bg-emerald-500 px-3 py-2 text-xs font-medium text-white shadow-md transition-all hover:bg-emerald-600 dark:bg-emerald-700 dark:hover:bg-emerald-800 dark:text-white">
                                <i class="pi pi-credit-card mr-1"></i>Régler
                            </button>
                            <button v-if="isFreeInvoice && !isValidatedFreeInvoice"
                                @click="emit('open-caisse-validate')"
                                class="rounded-xl bg-blue-500 px-3 py-2 text-xs font-medium text-white shadow-md transition-all hover:bg-blue-600 dark:bg-blue-700 dark:hover:bg-blue-800 dark:text-white">
                                <i class="pi pi-check mr-1"></i>Valider
                            </button>
                            <button v-if="canModifyInvoice"
                                @click="emit('open-caisse-modify')"
                                class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 transition-all hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                                <i class="pi pi-file-edit mr-1"></i>Modifier
                            </button>
                            <button @click="emit('open-caisse-preview')"
                                class="rounded-xl border border-surface-200 bg-white px-3 py-2 text-xs font-medium text-surface-600 transition-all hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-400">
                                <i class="pi pi-eye mr-1"></i>Aperçu
                            </button>
                        </div>
                    </div>

                    <!-- Paiements -->
                    <div v-if="selectedInvoicePayments.length" class="space-y-2">
                        <p class="text-sm font-semibold text-surface-700 dark:text-surface-400">Paiements effectués</p>
                        <div class="space-y-2">
                            <div v-for="payment in selectedInvoicePayments" :key="payment.id"
                                class="flex items-center justify-between rounded-xl border border-surface-200 bg-surface-50/50 p-3 dark:border-surface-700 dark:bg-surface-800/30">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div :class="[
                                        'flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-white',
                                        payment.status === 'validated' ? 'bg-gradient-to-br from-emerald-500 to-emerald-600' : 'bg-gradient-to-br from-amber-500 to-amber-600'
                                    ]">
                                        <i class="pi pi-wallet text-xs"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-surface-900 dark:text-surface-300">{{ formatFcfa(payment.montant) }}</p>
                                        <p class="text-[11px] text-surface-400 truncate">{{ formatPaymentMode(payment.mode) }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span :class="[
                                        'rounded-full px-2 py-0.5 text-[10px] font-medium',
                                        payment.status === 'validated' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'
                                    ]">
                                        {{ payment.status === 'validated' ? 'Validé' : 'En attente' }}
                                    </span>
                                    <div class="mt-1 flex items-center justify-end gap-1">
                                        <span class="text-[10px] text-surface-400">{{ formatTime(payment.date) }}</span>
                                        <button v-if="payment.status === 'validated'"
                                            @click="printPaymentReceipt(payment.id)"
                                            class="text-primary-500 hover:text-primary-600">
                                            <i class="pi pi-receipt text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-surface-100 to-surface-200 dark:from-surface-800 dark:to-surface-700">
                        <i class="pi pi-click text-3xl text-surface-400"></i>
                    </div>
                    <p class="text-sm font-medium text-surface-600">Aucune consultation sélectionnée</p>
                    <p class="mt-1 text-xs text-surface-400">Cliquez sur une consultation pour voir les détails</p>
                </div>
            </div>
        </aside>
    </div>
</template>

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 10px;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: rgba(156, 163, 175, 0.3);
    border-radius: 10px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: rgba(156, 163, 175, 0.5);
}
</style>

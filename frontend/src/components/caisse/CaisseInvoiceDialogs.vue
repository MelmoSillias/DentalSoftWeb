<script setup>
import ActeLineCard from '@/components/consultations/ActeLineCard.vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { computed } from 'vue';
import { normalizeDentList } from '@/services/consultations';

const props = defineProps({
    payDialogVisible: { type: Boolean, default: false },
    selectedFacture: { type: Object, default: null },
    paymentDialogTab: { type: String, default: 'client' },
    payForm: { type: Object, required: true },
    classicPaymentOptions: { type: Array, default: () => [] },
    assuranceOptions: { type: Array, default: () => [] },
    selectedAssurance: { type: Object, default: null },
    insuranceCoveredAmount: { type: Number, default: 0 },
    patientAlreadyPaidAmount: { type: Number, default: 0 },
    patientOutstandingAmount: { type: Number, default: 0 },
    invoiceHasInsurance: { type: Boolean, default: false },
    insuranceHelperMessage: { type: String, default: '' },
    insuranceSectionDisabledReason: { type: String, default: '' },
    insuranceStatusLabel: { type: String, default: '' },
    insuranceStatusSeverity: { type: String, default: 'info' },
    invoiceAllowsInsurance: { type: Boolean, default: false },
    requiresClassicPayment: { type: Boolean, default: false },
    maxClientPaymentAmount: { type: Number, default: 0 },
    remainingAfterPay: { type: Number, default: 0 },
    canResetInvoicePayments: { type: Boolean, default: false },
    payLoading: { type: Boolean, default: false },
    resetPaymentDialogVisible: { type: Boolean, default: false },
    resetPaymentsLoading: { type: Boolean, default: false },
    validateDialogVisible: { type: Boolean, default: false },
    validateLoading: { type: Boolean, default: false },
    factureDialogVisible: { type: Boolean, default: false },
    factureLines: { type: Array, default: () => [] },
    factureDate: { type: String, default: '' },
    factureTime: { type: String, default: '' },
    factureSaving: { type: Boolean, default: false },
    factureTotal: { type: Number, default: 0 },
    soinsList: { type: Array, default: () => [] },
    previewDialogVisible: { type: Boolean, default: false },
    previewLoading: { type: Boolean, default: false },
    previewData: { type: Object, default: null },
    previewDialogTab: { type: String, default: 'services' },
    previewPayments: { type: Array, default: () => [] },
    previewServicesTotal: { type: Number, default: 0 },
    formatFcfa: { type: Function, required: true },
    previewPaymentModeTag: { type: Function, required: true },
    previewPaymentRoleTag: { type: Function, required: true },
    showPrintInPreview: { type: Boolean, default: true }
});

const emit = defineEmits([
    'update:payDialogVisible',
    'update:paymentDialogTab',
    'update:resetPaymentDialogVisible',
    'update:validateDialogVisible',
    'update:factureDialogVisible',
    'update:factureDate',
    'update:factureTime',
    'update:previewDialogVisible',
    'update:previewDialogTab',
    'submit-payment',
    'confirm-reset',
    'confirm-validate',
    'save-facture',
    'print-invoice'
]);

const createEmptyLine = () => ({ dent: [], type: '', description: '', prix: 0, quantite: 1 });

const lineTotal = (line) => (Number(line?.quantite) || 0) * (Number(line?.prix) || 0);

const factureLineSubtotals = computed(() => (props.factureLines || []).map((line) => lineTotal(line)));

const addFactureLine = () => {
    props.factureLines.push(createEmptyLine());
};

const removeFactureLine = (index) => {
    props.factureLines.splice(index, 1);
    if (!props.factureLines.length) {
        props.factureLines.push(createEmptyLine());
    }
};

const updateFactureLine = (index, patch) => {
    const line = props.factureLines[index];
    if (!line) {
        return;
    }

    const next = { ...line, ...patch };
    if (Object.prototype.hasOwnProperty.call(patch || {}, 'dent')) {
        next.dent = normalizeDentList(patch.dent);
    }
    props.factureLines[index] = next;
};

const hasPreviewData = computed(() => Boolean(props.previewData));
</script>

<template>
    <div>
    <Dialog :visible="payDialogVisible" header="Régler la facture" :modal="true" :style="{ width: '760px' }" @update:visible="emit('update:payDialogVisible', $event)">
        <div class="flex flex-col gap-5">

            <div class="grid gap-3 grid-cols-1 md:grid-cols-3">
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/20 border border-blue-200 dark:border-blue-700/50 p-4 shadow-sm">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="pi pi-file-edit text-blue-500 text-xs"></i>
                        <p class="text-xs font-medium uppercase tracking-wider text-blue-600 dark:text-blue-400">{{ invoiceHasInsurance ? 'Part patient' : 'Total facture' }}</p>
                    </div>
                    <p class="text-lg font-bold text-blue-800 dark:text-blue-200">{{ formatFcfa(selectedFacture?.montant) }}</p>
                </div>
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/30 dark:to-emerald-800/20 border border-emerald-200 dark:border-emerald-700/50 p-4 shadow-sm">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="pi pi-check-circle text-emerald-500 text-xs"></i>
                        <p class="text-xs font-medium uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Déjà payé</p>
                    </div>
                    <p class="text-lg font-bold text-emerald-800 dark:text-emerald-200">{{ formatFcfa(patientAlreadyPaidAmount) }}</p>
                </div>
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900/30 dark:to-orange-800/20 border border-orange-200 dark:border-orange-700/50 p-4 shadow-sm">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="pi pi-clock text-orange-500 text-xs"></i>
                        <p class="text-xs font-medium uppercase tracking-wider text-orange-600 dark:text-orange-400">Reste patient</p>
                    </div>
                    <p class="text-lg font-bold text-orange-800 dark:text-orange-200">{{ formatFcfa(patientOutstandingAmount) }}</p>
                </div>
            </div>

            <div v-if="invoiceHasInsurance" class="rounded-2xl border border-sky-200 dark:border-sky-800 bg-sky-50/70 dark:bg-sky-950/20 p-4 grid gap-3 md:grid-cols-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-sky-700 dark:text-sky-300">Assurance</p>
                    <p class="font-semibold">{{ insuranceStatusLabel || selectedAssurance?.nom || '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-sky-700 dark:text-sky-300">Taux</p>
                    <p class="font-semibold">{{ Number(payForm?.insuranceRate || 0) }} %</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-sky-700 dark:text-sky-300">Part assurance</p>
                    <p class="font-semibold">{{ formatFcfa(insuranceCoveredAmount) }}</p>
                </div>
            </div>

            <div class="rounded-2xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 p-4 shadow-sm flex flex-col gap-3">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Mode de paiement patient</label>
                    <Select v-model="payForm.modeId" :options="classicPaymentOptions" optionLabel="label" optionValue="value" optionDisabled="disabled" placeholder="Sélectionner un mode" class="w-full mt-1" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</label>
                        <InputText v-model="payForm.date" type="date" class="w-full mt-1" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Heure</label>
                        <InputText v-model="payForm.time" type="time" class="w-full mt-1" />
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Montant patient</label>
                    <InputNumber v-model="payForm.montant" mode="decimal" locale="fr-FR" :min="0" class="w-full mt-1" :max="maxClientPaymentAmount" />
                    <div class="mt-2 flex items-center justify-between">
                        <p class="text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                            <i class="pi pi-wallet text-xs"></i>
                            Reste après paiement : <span class="font-semibold text-gray-600 dark:text-gray-300 ml-1">{{ formatFcfa(remainingAfterPay) }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex items-center justify-between w-full gap-2 pt-1">
                <Button v-if="canResetInvoicePayments" label="Réinitialiser" severity="danger" outlined icon="pi pi-refresh" @click="emit('confirm-reset')" class="text-sm" />
                <div class="flex items-center gap-2 ml-auto">
                    <Button label="Annuler" text icon="pi pi-times" @click="emit('update:payDialogVisible', false)" class="text-sm" />
                    <Button label="Confirmer le paiement" severity="success" icon="pi pi-check" @click="emit('submit-payment')" :loading="payLoading" class="text-sm font-semibold" />
                </div>
            </div>
        </template>
    </Dialog>

    <Dialog :visible="resetPaymentDialogVisible" header="Réinitialiser la facture" :modal="true" :style="{ width: '420px' }" @update:visible="emit('update:resetPaymentDialogVisible', $event)">
        <p class="dialog-note text-sm text-gray-700">Cette action supprimera tous les paiements et toutes les transactions liées à la facture pour la remettre à son état initial.</p>
        <template #footer>
            <Button label="Annuler" text @click="emit('update:resetPaymentDialogVisible', false)" />
            <Button label="Réinitialiser" severity="danger" icon="pi pi-refresh" @click="emit('confirm-reset')" :loading="resetPaymentsLoading" />
        </template>
    </Dialog>

    <Dialog :visible="validateDialogVisible" header="Valider la facture vide" :modal="true" :style="{ width: '420px' }" @update:visible="emit('update:validateDialogVisible', $event)">
        <p class="dialog-note text-sm text-gray-700">Confirmer que cette facture est vide et doit être marquée comme validée.</p>
        <template #footer>
            <Button label="Annuler" text @click="emit('update:validateDialogVisible', false)" />
            <Button label="Valider" severity="success" icon="pi pi-check" @click="emit('confirm-validate')" :loading="validateLoading" />
        </template>
    </Dialog>

    <Dialog :visible="factureDialogVisible" header="Modifier la facture" :modal="true" :style="{ width: '52rem', maxWidth: '98vw' }" @update:visible="emit('update:factureDialogVisible', $event)">
        <div class="flex flex-col gap-4">
            <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800/40 p-4">
                <p class="mb-3 text-sm font-semibold text-surface-900 dark:text-surface-100">Date de la facture</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-surface-500">Date</label>
                        <InputText :model-value="factureDate" type="date" class="w-full" @update:model-value="emit('update:factureDate', $event)" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-surface-500">Heure</label>
                        <InputText :model-value="factureTime" type="time" class="w-full" @update:model-value="emit('update:factureTime', $event)" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/10">
                            <i class="pi pi-file-edit text-amber-600 dark:text-amber-400"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-surface-900 dark:text-surface-100">Actes posés</p>
                            <p class="text-sm text-surface-500 dark:text-surface-400">Modifiez les soins facturés pour cette consultation</p>
                        </div>
                    </div>
                    <Button
                        icon="pi pi-plus"
                        label="Ajouter un soin"
                        size="small"
                        class="rounded-xl border-0 bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-2.5 text-white shadow-sm transition-all hover:shadow-md"
                        @click="addFactureLine"
                    />
                </div>
            </div>

            <div v-if="!factureLines.length" class="rounded-xl border border-dashed border-surface-200 bg-surface-50/60 p-8 text-center dark:border-surface-700 dark:bg-surface-800/20">
                <div class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800">
                    <i class="pi pi-inbox text-2xl text-surface-400"></i>
                </div>
                <p class="text-sm text-surface-600 dark:text-surface-400">Aucun acte. Ajoutez au moins une ligne pour enregistrer la facture.</p>
            </div>

            <div v-else class="space-y-4">
                <ActeLineCard
                    v-for="(line, idx) in factureLines"
                    :key="`${idx}-${line.type}-${(line.dent || []).join('-')}`"
                    :acte="line"
                    :index="idx"
                    :soins="soinsList"
                    :subtotal="factureLineSubtotals[idx] ?? lineTotal(line)"
                    @update="(patch) => updateFactureLine(idx, patch)"
                    @remove="removeFactureLine(idx)"
                />
            </div>

            <div v-if="factureLines.length" class="rounded-xl border border-surface-200 bg-gradient-to-r from-amber-50 to-orange-50 p-4 dark:border-surface-700 dark:from-amber-900/20 dark:to-orange-900/10">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-base font-semibold text-surface-900 dark:text-surface-100">Total TTC</span>
                    <span class="text-2xl font-bold text-amber-700 dark:text-amber-300">{{ formatFcfa(factureTotal) }}</span>
                </div>
            </div>
        </div>
        <template #footer>
            <div class="flex w-full flex-wrap items-center justify-end gap-2">
                <Button label="Annuler" icon="pi pi-times" severity="secondary" text class="rounded-xl px-4" @click="emit('update:factureDialogVisible', false)" />
                <Button
                    label="Enregistrer"
                    icon="pi pi-save"
                    :loading="factureSaving"
                    class="rounded-xl border-0 bg-gradient-to-r from-primary-500 to-primary-600 px-5 py-2.5 font-medium text-white shadow-sm transition-all hover:shadow-md"
                    @click="emit('save-facture')"
                />
            </div>
        </template>
    </Dialog>

    <Dialog :visible="previewDialogVisible" header="Détail de la facture" :modal="true" :style="{ width: '820px' }" @update:visible="emit('update:previewDialogVisible', $event)">
        <div>
            <div v-if="previewLoading" class="p-4 text-center text-gray-600 dark:text-gray-400">Chargement...</div>
            <div v-else-if="hasPreviewData" class="preview-dialog-content flex flex-col gap-3 ">
                <div class="preview-header-card flex items-center justify-between rounded-2xl border border-surface-200 bg-surface-50/80 p-4 dark:bg-surface-800 dark:border-surface-700">
                    <div>
                        <p class="text-lg font-semibold">Facture n° {{ String(previewData.id).padStart(4, '0') }}</p>
                        <p class="preview-subtext text-sm text-gray-600 dark:text-gray-400">Date : {{ previewData.date }}</p>
                        <p class="preview-subtext text-sm text-gray-600 dark:text-gray-400">Patient : {{ previewData.patient?.nom }} {{ previewData.patient?.prenom }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <Tag :value="'Reste ' + formatFcfa(previewData.reste)" severity="warning" />
                        <Tag v-if="previewData.insurance?.hasInsurance" value="Assurance" severity="info" icon="pi pi-shield" />
                    </div>
                </div>
                <Tabs :value="previewDialogTab" @update:value="emit('update:previewDialogTab', $event)">
                    <TabList>
                        <Tab value="services">Services effectués</Tab>
                        <Tab value="paiements">Détail des paiements</Tab>
                    </TabList>
                    <TabPanels class="mt-4">
                        <TabPanel value="services">
                            <div class="flex flex-col gap-4">
                                <div class="grid gap-3 md:grid-cols-3">
                                    <div class="preview-summary-card rounded-xl border border-surface-200 bg-surface-50/70 dark:bg-surface-800 dark:border-surface-700 p-3">
                                        <p class="preview-summary-label text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Actes enregistrés</p>
                                        <p class="mt-1 text-base font-semibold">{{ (previewData.contenus || []).length }}</p>
                                    </div>
                                    <div class="preview-summary-card rounded-xl border border-surface-200 bg-surface-50/70 dark:bg-surface-800 dark:border-surface-700 p-3">
                                        <p class="preview-summary-label text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Total des services</p>
                                        <p class="mt-1 text-base font-semibold">{{ formatFcfa(previewServicesTotal) }}</p>
                                    </div>
                                    <div class="preview-summary-card rounded-xl border border-surface-200 bg-surface-50/70 dark:bg-surface-800 dark:border-surface-700 p-3">
                                        <p class="preview-summary-label text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Montant facturé</p>
                                        <p class="mt-1 text-base font-semibold">{{ formatFcfa(previewData.montant) }}</p>
                                    </div>
                                </div>
                                <div class="preview-table-card overflow-hidden rounded-2xl border border-surface-200 bg-white/90 dark:bg-surface-800 dark:border-surface-700 shadow-sm">
                                    <table class="w-full text-sm">
                                        <thead class="preview-table-head bg-slate-50 text-slate-700 dark:bg-surface-800 dark:text-slate-400">
                                            <tr class="preview-table-head-row text-left border-b border-slate-200">
                                                <th class="px-4 py-3 font-medium">Désignation</th>
                                                <th class="px-4 py-3 font-medium">Qté</th>
                                                <th class="px-4 py-3 text-right font-medium">Prix</th>
                                                <th class="px-4 py-3 text-right font-medium">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(c, idx) in previewData.contenus || []" :key="idx" class="preview-table-row border-b border-slate-100 last:border-b-0">
                                                <td class="preview-table-strong px-4 py-3 font-medium text-slate-700 dark:text-slate-200">{{ c.designation }}</td>
                                                <td class="preview-table-muted px-4 py-3 text-slate-600 dark:text-slate-400">{{ c.qte }}</td>
                                                <td class="preview-table-muted px-4 py-3 text-right text-slate-600 dark:text-slate-400">{{ formatFcfa(c.montant) }}</td>
                                                <td class="preview-table-strong px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-200">{{ formatFcfa(c.total) }}</td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="preview-table-foot bg-slate-50/80 dark:bg-surface-800/80">
                                            <tr>
                                                <th colspan="3" class="preview-table-muted px-4 py-3 text-right font-medium text-slate-600 dark:text-slate-400">Total TTC</th>
                                                <th class="preview-table-emphasis px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-200">{{ formatFcfa(previewData.montant) }}</th>
                                            </tr>
                                            <tr>
                                                <th colspan="3" class="preview-table-muted px-4 py-3 text-right font-medium text-slate-600 dark:text-slate-400">Reste à payer</th>
                                                <th class="preview-table-warning px-4 py-3 text-right font-semibold text-amber-700 dark:text-amber-400">{{ formatFcfa(previewData.reste) }}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </TabPanel>
                        <TabPanel value="paiements">
                            <div class="flex flex-col gap-4">
                                <div v-if="previewData.insurance?.hasInsurance" class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                                    <div class="preview-summary-card rounded-xl border border-surface-200 bg-surface-50/70 p-3 dark:bg-surface-800/70 dark:border-surface-700">
                                        <p class="preview-summary-label text-xs uppercase tracking-wide text-gray-500">Assurance</p>
                                        <p class="mt-1 text-base font-semibold">{{ previewData.insurance?.assuranceNom || previewData.insurance?.insuranceModeLabel || '—' }}</p>
                                    </div>
                                    <div class="preview-summary-card rounded-xl border border-surface-200 bg-surface-50/70 p-3 dark:bg-surface-800/70 dark:border-surface-700">
                                        <p class="preview-summary-label text-xs uppercase tracking-wide text-gray-500">Taux</p>
                                        <p class="mt-1 text-base font-semibold">{{ Number(previewData.insurance?.tauxCouverture ?? previewData.insurance?.insuranceRate ?? 0) }} %</p>
                                    </div>
                                    <div class="preview-summary-card rounded-xl border border-surface-200 bg-surface-50/70 p-3 dark:bg-surface-800/70 dark:border-surface-700">
                                        <p class="preview-summary-label text-xs uppercase tracking-wide text-gray-500">Montant total</p>
                                        <p class="mt-1 text-base font-semibold">{{ formatFcfa(previewData.insurance?.montantTotal ?? previewData.montantTotal) }}</p>
                                    </div>
                                    <div class="preview-summary-card rounded-xl border border-surface-200 bg-surface-50/70 p-3 dark:bg-surface-800/70 dark:border-surface-700">
                                        <p class="preview-summary-label text-xs uppercase tracking-wide text-gray-500">Part assurance</p>
                                        <p class="mt-1 text-base font-semibold">{{ formatFcfa(previewData.insurance?.montantAssurance ?? previewData.insurance?.insuranceAmount) }}</p>
                                    </div>
                                    <div class="preview-summary-card rounded-xl border border-surface-200 bg-surface-50/70 p-3 dark:bg-surface-800/70 dark:border-surface-700">
                                        <p class="preview-summary-label text-xs uppercase tracking-wide text-gray-500">Part patient</p>
                                        <p class="mt-1 text-base font-semibold">{{ formatFcfa(previewData.insurance?.montantPatient ?? previewData.montant) }}</p>
                                    </div>
                                    <div class="preview-summary-card rounded-xl border border-surface-200 bg-surface-50/70 p-3 dark:bg-surface-800/70 dark:border-surface-700">
                                        <p class="preview-summary-label text-xs uppercase tracking-wide text-gray-500">Reste patient</p>
                                        <p class="mt-1 text-base font-semibold">{{ formatFcfa(previewData.insurance?.restePatient ?? previewData.reste) }}</p>
                                    </div>
                                </div>
                                <div v-else class="grid gap-3 md:grid-cols-3">
                                    <div class="preview-summary-card rounded-xl border border-surface-200 bg-surface-50/70 p-3 dark:bg-surface-800/70 dark:border-surface-700">
                                        <p class="preview-summary-label text-xs uppercase tracking-wide text-gray-500">Écritures</p>
                                        <p class="mt-1 text-base font-semibold">{{ previewPayments.length }}</p>
                                    </div>
                                </div>
                                <div v-if="previewPayments.length" class="flex flex-col gap-3">
                                    <div v-for="payment in previewPayments" :key="`${payment.sourceType}-${payment.id}`" class="preview-payment-card rounded-2xl border border-surface-200 bg-white/90 dark:bg-surface-800/80 p-4 shadow-sm dark:border-surface-700">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p class="preview-payment-amount font-semibold text-slate-800 dark:text-slate-200">{{ formatFcfa(payment.montant) }}</p>
                                                <p class="preview-payment-date text-sm text-slate-500 dark:text-slate-400">{{ payment.date || 'Date inconnue' }}</p>
                                                <p v-if="payment.description" class="preview-payment-description mt-1 text-sm text-slate-600 dark:text-slate-400">{{ payment.description }}</p>
                                            </div>
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <Tag :value="previewPaymentModeTag(payment).label" :severity="previewPaymentModeTag(payment).severity" />
                                                <Tag :value="previewPaymentRoleTag(payment).label" :severity="previewPaymentRoleTag(payment).severity" />
                                                <Tag v-if="payment.sourceType === 'transaction'" value="Transaction" severity="secondary" />
                                                <Tag v-else value="Paiement" severity="contrast" />
                                            </div>
                                        </div>
                                        <div class="preview-payment-meta mt-3 grid gap-3 text-sm text-slate-600 dark:text-slate-400 md:grid-cols-3">
                                            <div>
                                                <p class="preview-payment-meta-label text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Statut</p>
                                                <p class="mt-1 font-medium">{{ payment.status === 'pending' ? 'En attente' : 'Validé' }}</p>
                                            </div>
                                            <div>
                                                <p class="preview-payment-meta-label text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Mode</p>
                                                <p class="mt-1 font-medium">{{ payment.mode || '—' }}</p>
                                            </div>
                                            <div>
                                                <p class="preview-payment-meta-label text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Prise en charge</p>
                                                <p class="mt-1 font-medium">{{ Number(payment.insuranceRate || 0) > 0 ? `${Number(payment.insuranceRate).toLocaleString('fr-FR')} %` : '—' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="preview-empty-state rounded-2xl border border-dashed border-surface-200 bg-surface-50/60 p-6 text-center text-sm text-gray-500 dark:text-slate-400">
                                    Aucun paiement enregistré pour cette facture.
                                </div>
                            </div>
                        </TabPanel>
                    </TabPanels>
                </Tabs>
            </div>
        </div>
        <template #footer>
            <Button v-if="canResetInvoicePayments" label="Réinitialiser la facture" severity="danger" outlined icon="pi pi-refresh" @click="emit('confirm-reset')" />
            <Button label="Fermer" text @click="emit('update:previewDialogVisible', false)" />
            <Button v-if="showPrintInPreview" label="Imprimer" icon="pi pi-print" severity="info" @click="emit('print-invoice')" />
        </template>
    </Dialog>
    </div>
</template>

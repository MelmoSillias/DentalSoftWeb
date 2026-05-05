<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import { computed } from 'vue';

const props = defineProps({
    payDialogVisible: { type: Boolean, default: false },
    selectedDevis: { type: Object, default: null },
    paymentDialogTab: { type: String, default: 'client' },
    payForm: { type: Object, required: true },
    classicPaymentOptions: { type: Array, default: () => [] },
    insurancePaymentOptions: { type: Array, default: () => [] },
    selectedInsuranceMethod: { type: Object, default: null },
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
    'update:previewDialogVisible',
    'update:previewDialogTab',
    'submit-payment',
    'confirm-reset',
    'confirm-validate',
    'save-facture',
    'print-invoice'
]);

const createEmptyLine = () => ({ dent: '', type: '', description: '', prix: 0, quantite: 1 });

const addFactureLine = () => {
    props.factureLines.push(createEmptyLine());
};

const removeFactureLine = (index) => {
    props.factureLines.splice(index, 1);
    if (!props.factureLines.length) {
        props.factureLines.push(createEmptyLine());
    }
};

const hasPreviewData = computed(() => Boolean(props.previewData));
</script>

<template>
    <Dialog :visible="payDialogVisible" header="Régler la facture" :modal="true" :style="{ width: '760px' }" @update:visible="emit('update:payDialogVisible', $event)">
        <div class="flex flex-col gap-5">

            <!-- Summary Cards -->
            <div class="grid gap-3 grid-cols-2 md:grid-cols-4">
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/20 border border-blue-200 dark:border-blue-700/50 p-4 shadow-sm">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="pi pi-file-edit text-blue-500 text-xs"></i>
                        <p class="text-xs font-medium uppercase tracking-wider text-blue-600 dark:text-blue-400">Total facture</p>
                    </div>
                    <p class="text-lg font-bold text-blue-800 dark:text-blue-200">{{ formatFcfa(selectedDevis?.montant) }}</p>
                </div>
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/30 dark:to-purple-800/20 border border-purple-200 dark:border-purple-700/50 p-4 shadow-sm">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="pi pi-shield text-purple-500 text-xs"></i>
                        <p class="text-xs font-medium uppercase tracking-wider text-purple-600 dark:text-purple-400">Part assurance</p>
                    </div>
                    <p class="text-lg font-bold text-purple-800 dark:text-purple-200">{{ formatFcfa(insuranceCoveredAmount) }}</p>
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
                        <p class="text-xs font-medium uppercase tracking-wider text-orange-600 dark:text-orange-400">Reste à payer</p>
                    </div>
                    <p class="text-lg font-bold text-orange-800 dark:text-orange-200">{{ formatFcfa(patientOutstandingAmount) }}</p>
                </div>
            </div>

            <!-- Tabs -->
            <Tabs :value="paymentDialogTab" @update:value="emit('update:paymentDialogTab', $event)">
                <TabList class="rounded-xl overflow-hidden border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800">
                    <Tab value="client" class="flex-1 text-center">
                        <span class="flex items-center justify-center gap-2">
                            <i class="pi pi-user text-sm"></i>
                            <span class="font-medium">Paiement client</span>
                        </span>
                    </Tab>
                    <Tab value="assurance" class="flex-1 text-center">
                        <span class="flex items-center justify-center gap-2">
                            <i class="pi pi-shield text-sm"></i>
                            <span class="font-medium">Paiement assurance</span>
                            <i v-if="invoiceHasInsurance" class="pi pi-check-circle text-green-500 text-sm"></i>
                        </span>
                    </Tab>
                </TabList>

                <TabPanels class="mt-4 p-0">
                    <!-- Client Tab -->
                    <TabPanel value="client">
                        <div class="flex flex-col gap-4">
                            <!-- Synthèse -->
                            <div class="rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-50/80 dark:bg-surface-800/60 p-4 shadow-sm">
                                <div class="flex items-center gap-2 mb-3">
                                    <i class="pi pi-info-circle text-primary-500"></i>
                                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Synthèse de la facture</p>
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="rounded-xl bg-white dark:bg-surface-700 border border-surface-100 dark:border-surface-600 p-3">
                                        <p class="text-xs uppercase tracking-wider text-gray-400 dark:text-gray-500">Part assurance</p>
                                        <p class="mt-1 text-sm font-bold text-gray-800 dark:text-gray-100">{{ formatFcfa(insuranceCoveredAmount) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white dark:bg-surface-700 border border-surface-100 dark:border-surface-600 p-3">
                                        <p class="text-xs uppercase tracking-wider text-gray-400 dark:text-gray-500">Reste client à encaisser</p>
                                        <p class="mt-1 text-sm font-bold text-gray-800 dark:text-gray-100">{{ formatFcfa(patientOutstandingAmount) }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Mode de paiement -->
                            <div class="rounded-2xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 p-4 shadow-sm flex flex-col gap-3">
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Mode de paiement client</label>
                                    <Select v-model="payForm.modeId" :options="classicPaymentOptions" optionLabel="label" optionValue="value" optionDisabled="disabled" placeholder="Sélectionner un mode" class="w-full mt-1" />
                                    <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                                        <i class="pi pi-info-circle text-xs"></i>
                                        {{ requiresClassicPayment ? 'Choisissez le mode utilisé pour la tranche client en cours.' : 'Laissez le montant à 0 pour confirmer uniquement la partie assurance.' }}
                                    </p>
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
                                    <label class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Montant client</label>
                                    <InputNumber v-model="payForm.montant" mode="decimal" locale="fr-FR" :min="0" class="w-full mt-1" :max="maxClientPaymentAmount" />
                                    <div class="mt-2 flex items-center justify-between">
                                        <p class="text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                                            <i class="pi pi-wallet text-xs"></i>
                                            Reste après paiement : <span class="font-semibold text-gray-600 dark:text-gray-300 ml-1">{{ formatFcfa(remainingAfterPay) }}</span>
                                        </p>
                                        <p v-if="(payForm.insuranceEnabled || invoiceHasInsurance) && selectedInsuranceMethod" class="text-xs text-purple-500 dark:text-purple-400 flex items-center gap-1">
                                            <i class="pi pi-shield text-xs"></i>
                                            {{ selectedInsuranceMethod.libelle }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </TabPanel>

                    <!-- Assurance Tab -->
                    <TabPanel value="assurance">
                        <div class="flex flex-col gap-4">
                            <!-- État -->
                            <div class="flex items-center justify-between gap-3 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-50/80 dark:bg-surface-800/60 p-4 shadow-sm">
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-700">
                                        <i class="pi pi-shield text-primary-500"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">État de la prise en charge</p>
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ insuranceHelperMessage }}</p>
                                    </div>
                                </div>
                                <Tag :value="insuranceStatusLabel" :severity="insuranceStatusSeverity" class="shrink-0" />
                            </div>

                            <!-- Toggle assurance -->
                            <div v-if="invoiceAllowsInsurance" class="rounded-2xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 p-4 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Activer la prise en charge</p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ payForm.insuranceEnabled ? 'Facture marquée comme assurée' : 'Marquer cette facture comme assurée' }}</p>
                                    </div>
                                    <ToggleSwitch v-model="payForm.insuranceEnabled" />
                                </div>
                            </div>
                            <div v-else class="rounded-2xl border border-dashed border-surface-300 dark:border-surface-600 bg-surface-50/50 dark:bg-surface-800/40 p-4 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                <i class="pi pi-ban text-sm"></i>
                                {{ insuranceSectionDisabledReason }}
                            </div>

                            <!-- Détails assurance -->
                            <div class="rounded-2xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 p-4 shadow-sm flex flex-col gap-3">
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Assurance</label>
                                    <Select v-model="payForm.insuranceModeId" :options="insurancePaymentOptions" optionLabel="label" optionValue="value" placeholder="Sélectionner une assurance" class="w-full mt-1" :disabled="invoiceHasInsurance || !payForm.insuranceEnabled" />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Prise en charge (%)</label>
                                        <InputNumber v-model="payForm.insuranceRate" mode="decimal" locale="fr-FR" :min="0" :max="100" :minFractionDigits="0" :maxFractionDigits="2" inputClass="w-full" class="w-full mt-1" :disabled="invoiceHasInsurance || !payForm.insuranceEnabled" />
                                    </div>
                                    <div>
                                        <label class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Montant assurance</label>
                                        <InputNumber :modelValue="insuranceCoveredAmount" mode="decimal" locale="fr-FR" inputClass="w-full" class="w-full mt-1" disabled />
                                    </div>
                                </div>
                                <p v-if="invoiceHasInsurance && selectedInsuranceMethod" class="text-xs text-purple-500 dark:text-purple-400 flex items-center gap-1">
                                    <i class="pi pi-shield text-xs"></i>
                                    Assurance liée : <span class="font-semibold ml-1">{{ selectedInsuranceMethod.libelle }}</span>
                                </p>
                            </div>
                        </div>
                    </TabPanel>
                </TabPanels>
            </Tabs>
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

    <Dialog :visible="factureDialogVisible" header="Modifier la facture" :modal="true" :style="{ width: '720px' }" @update:visible="emit('update:factureDialogVisible', $event)">
        <div class="flex flex-col gap-3">
            <div v-for="(line, idx) in factureLines" :key="idx" class="border rounded p-3 flex flex-col gap-2">
                <div class="grid md:grid-cols-2 gap-2">
                    <InputText v-model="line.dent" placeholder="Dent" />
                    <Select v-model="line.type" :options="soinsList" placeholder="Acte / Soin" />
                </div>
                <InputText v-model="line.description" placeholder="Description" />
                <div class="grid grid-cols-2 gap-2">
                    <InputNumber v-model="line.prix" mode="decimal" locale="fr-FR" :min="0" class="w-full" placeholder="Prix" />
                    <InputNumber v-model="line.quantite" :min="1" class="w-full" placeholder="Quantité" />
                </div>
                <div class="flex justify-end">
                    <Button label="Supprimer" icon="pi pi-trash" text severity="danger" @click="removeFactureLine(idx)" />
                </div>
            </div>
            <Button label="Ajouter une ligne" icon="pi pi-plus" outlined @click="addFactureLine" />
            <div class="text-right font-semibold">Total TTC : {{ formatFcfa(factureTotal) }}</div>
        </div>
        <template #footer>
            <Button label="Annuler" text @click="emit('update:factureDialogVisible', false)" />
            <Button label="Enregistrer" severity="primary" icon="pi pi-save" :loading="factureSaving" @click="emit('save-facture')" />
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
                        <Tag v-if="previewData.insurance?.hasInsurance" :value="previewData.insurance?.insuranceStatus === 'pending' ? 'Assurance en attente' : 'Assurance liée'" :severity="previewData.insurance?.insuranceStatus === 'pending' ? 'warning' : 'info'" icon="pi pi-shield" />
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
                                <div class="grid gap-3 md:grid-cols-3">
                                    <div class="preview-summary-card rounded-xl border border-surface-200 bg-surface-50/70 p-3">
                                        <p class="preview-summary-label text-xs uppercase tracking-wide text-gray-500">Écritures</p>
                                        <p class="mt-1 text-base font-semibold">{{ previewPayments.length }}</p>
                                    </div>
                                    <div class="preview-summary-card rounded-xl border border-surface-200 bg-surface-50/70 p-3">
                                        <p class="preview-summary-label text-xs uppercase tracking-wide text-gray-500">Part assurance</p>
                                        <p class="mt-1 text-base font-semibold">{{ formatFcfa(previewData.insurance?.insuranceAmount) }}</p>
                                    </div>
                                    <div class="preview-summary-card rounded-xl border border-surface-200 bg-surface-50/70 p-3">
                                        <p class="preview-summary-label text-xs uppercase tracking-wide text-gray-500">Part client déjà réglée</p>
                                        <p class="mt-1 text-base font-semibold">{{ formatFcfa(previewData.insurance?.patientPaidAmount) }}</p>
                                    </div>
                                </div>
                                <div v-if="previewPayments.length" class="flex flex-col gap-3">
                                    <div v-for="payment in previewPayments" :key="`${payment.sourceType}-${payment.id}`" class="preview-payment-card rounded-2xl border border-surface-200 bg-white/90 dark:bg-surface-800/80 p-4 shadow-sm">
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
</template>
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
    <Dialog :visible="payDialogVisible" header="Régler la facture" :modal="true" :style="{ width: '720px' }" @update:visible="emit('update:payDialogVisible', $event)">
        <div class="flex flex-col gap-4">
            <div class="grid gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-surface-200 bg-surface-50/70 dark:bg-surface-800 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Montant total</p>
                    <p class="mt-1 text-base font-semibold">{{ formatFcfa(selectedDevis?.montant) }}</p>
                </div>
                <div class="rounded-xl border border-surface-200 bg-surface-50/70 dark:bg-surface-800 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Part assurance</p>
                    <p class="mt-1 text-base font-semibold">{{ formatFcfa(insuranceCoveredAmount) }}</p>
                </div>
                <div class="rounded-xl border border-surface-200 bg-surface-50/70 dark:bg-surface-800 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Déjà payé client</p>
                    <p class="mt-1 text-base font-semibold">{{ formatFcfa(patientAlreadyPaidAmount) }}</p>
                </div>
                <div class="rounded-xl border border-surface-200 bg-surface-50/70 dark:bg-surface-800 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Reste à payer</p>
                    <p class="mt-1 text-base font-semibold">{{ formatFcfa(patientOutstandingAmount) }}</p>
                </div>
            </div>

            <Tabs :value="paymentDialogTab" @update:value="emit('update:paymentDialogTab', $event)">
                <TabList>
                    <Tab value="client">Paiement client</Tab>
                    <Tab value="assurance">
                        <span class="flex items-center gap-2">
                            <span>Paiement assurance</span>
                            <i v-if="invoiceHasInsurance" class="pi pi-check-circle text-green-600"></i>
                        </span>
                    </Tab>
                </TabList>
                <TabPanels class="mt-4">
                    <TabPanel value="client">
                        <div class="flex flex-col gap-4">
                            <div class="rounded-xl border border-surface-200 bg-surface-50/70 p-4 dark:bg-surface-800">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Synthèse de la facture</p>
                                <div class="mt-3 grid gap-3 md:grid-cols-2">
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Part assurance</p>
                                        <p class="mt-1 text-sm font-semibold">{{ formatFcfa(insuranceCoveredAmount) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Reste client à encaisser</p>
                                        <p class="mt-1 text-sm font-semibold">{{ formatFcfa(patientOutstandingAmount) }}</p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm text-gray-600 dark:text-gray-400">Mode de paiement client</label>
                                <Select v-model="payForm.modeId" :options="classicPaymentOptions" optionLabel="label" optionValue="value" optionDisabled="disabled" placeholder="Sélectionner" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ requiresClassicPayment ? 'Choisissez le mode utilisé pour la tranche client en cours.' : 'Laissez le montant à 0 pour confirmer uniquement la partie assurance.' }}
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-sm text-gray-600 dark:text-gray-400">Date</label>
                                    <InputText v-model="payForm.date" type="date" class="w-full" />
                                </div>
                                <div>
                                    <label class="text-sm text-gray-600 dark:text-gray-400">Heure</label>
                                    <InputText v-model="payForm.time" type="time" class="w-full" />
                                </div>
                            </div>
                            <div>
                                <label class="text-sm text-gray-600 dark:text-gray-400">Montant client</label>
                                <InputNumber v-model="payForm.montant" mode="decimal" locale="fr-FR" :min="0" class="w-full" :max="maxClientPaymentAmount" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Reste après paiement : {{ formatFcfa(remainingAfterPay) }}</p>
                                <p v-if="(payForm.insuranceEnabled || invoiceHasInsurance) && selectedInsuranceMethod" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Assureur sélectionné : {{ selectedInsuranceMethod.libelle }}.
                                </p>
                            </div>
                        </div>
                    </TabPanel>
                    <TabPanel value="assurance">
                        <div class="flex flex-col gap-4">
                            <div class="flex items-start justify-between gap-3 rounded-xl border border-surface-200 bg-surface-50/70 p-4 dark:bg-surface-800">
                                <div>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">État de la prise en charge</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ insuranceHelperMessage }}</p>
                                </div>
                                <Tag :value="insuranceStatusLabel" :severity="insuranceStatusSeverity" />
                            </div>

                            <div v-if="invoiceAllowsInsurance" class="rounded-xl border border-surface-200 bg-surface-50/70 p-4 dark:bg-surface-800">
                                <div class="flex items-center gap-2">
                                    <ToggleSwitch v-model="payForm.insuranceEnabled" />
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ payForm.insuranceEnabled ? 'Facture marquée comme assurée' : 'Marquer cette facture comme assurée' }}</span>
                                </div>
                            </div>
                            <div v-else class="rounded-xl border border-dashed border-surface-200 bg-surface-50/50 dark:bg-surface-800 p-4 text-sm text-gray-600 dark:text-gray-400">
                                {{ insuranceSectionDisabledReason }}
                            </div>

                            <div class="grid grid-cols-1 gap-3 rounded-xl border border-surface-200 bg-surface-50/70 dark:bg-surface-800 p-4">
                                <div>
                                    <label class="text-sm text-gray-600 dark:text-gray-400">Assurance</label>
                                    <Select v-model="payForm.insuranceModeId" :options="insurancePaymentOptions" optionLabel="label" optionValue="value" placeholder="Sélectionner une assurance" :disabled="invoiceHasInsurance || !payForm.insuranceEnabled" />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-sm text-gray-600 dark:text-gray-400">Prise en charge (%)</label>
                                        <InputNumber v-model="payForm.insuranceRate" mode="decimal" locale="fr-FR" :min="0" :max="100" :minFractionDigits="0" :maxFractionDigits="2" inputClass="w-full" class="w-full" :disabled="invoiceHasInsurance || !payForm.insuranceEnabled" />
                                    </div>
                                    <div>
                                        <label class="text-sm text-gray-600 dark:text-gray-400">Montant assurance</label>
                                        <InputNumber :modelValue="insuranceCoveredAmount" mode="decimal" locale="fr-FR" inputClass="w-full" class="w-full" disabled />
                                    </div>
                                </div>
                                <p v-if="invoiceHasInsurance && selectedInsuranceMethod" class="text-xs text-gray-500">
                                    Assurance liée : {{ selectedInsuranceMethod.libelle }}.
                                </p>
                            </div>
                        </div>
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div>
        <template #footer>
            <Button v-if="canResetInvoicePayments" label="Réinitialiser la facture" severity="danger" outlined icon="pi pi-refresh" @click="emit('confirm-reset')" />
            <Button label="Annuler" text @click="emit('update:payDialogVisible', false)" />
            <Button label="Confirmer" severity="success" icon="pi pi-check" @click="emit('submit-payment')" :loading="payLoading" />
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
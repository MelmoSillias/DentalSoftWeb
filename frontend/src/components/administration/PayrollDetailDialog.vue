<script setup>
import { computed, ref, watch } from 'vue';
import AppDialog from '@/components/layout/AppDialog.vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Tag from 'primevue/tag';
import Message from 'primevue/message';
import Checkbox from 'primevue/checkbox';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import { formatFrequenceLabel, formatPrimeTypeLabel, formatSalaryTypeLabel } from '@/utils/payrollUtils';

const props = defineProps({
    visible: Boolean,
    initialCancel: Boolean,
    payment: {
        type: Object,
        default: null
    },
    paymentMethods: {
        type: Array,
        default: () => []
    },
    loading: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:visible', 'submit', 'print', 'cancel-payment']);

const localVisible = ref(false);
const editMode = ref(false);
const cancelMode = ref(false);
const inpsConfirmed = ref(false);
const entryErrorConfirmed = ref(false);
const form = ref({
    paidAmount: null,
    paidAt: null,
    paymentMethodId: null,
    note: ''
});

watch(
    () => props.visible,
    (val) => {
        localVisible.value = val;
        if (val) {
            editMode.value = props.initialCancel;
            cancelMode.value = props.initialCancel;
            hydrateForm();
        }
    },
    { immediate: true }
);

watch(localVisible, (val) => emit('update:visible', val));
watch(
    () => props.payment,
    () => hydrateForm()
);

const hydrateForm = () => {
    const payment = props.payment;
    form.value = {
        paidAmount: payment?.paidAmount ?? null,
        paidAt: payment?.paidAt ? new Date(payment.paidAt) : new Date(),
        paymentMethodId: payment?.paymentMethod?.id ?? null,
        note: payment?.note || '',
        baseSalaryAmount: payment?.baseSalaryAmount || 0,
        primeAmount: payment?.primeAmount || 0,
        indemniteSpeciale: payment?.indemniteSpeciale || 0,
        inps: payment?.inps || 0,
        reason: ''
    };
    inpsConfirmed.value = false;
    entryErrorConfirmed.value = false;
};

const activeOthers = computed(() => (props.payment?.payments || []).filter((p) => p.status === 'active' && p.id !== props.payment.id).reduce((sum, p) => sum + Number(p.paidAmount), 0));
const net = computed(() => Math.round((Number(form.value.baseSalaryAmount || 0) + Number(form.value.primeAmount || 0) + Number(form.value.indemniteSpeciale || 0) - Number(form.value.inps || 0)) * 100) / 100);
const maxAllowed = computed(() => Math.max(0, net.value - activeOthers.value));
const inpsChanged = computed(() => (cancelMode.value ? activeOthers.value === 0 && props.payment?.inps > 0 : Number(form.value.inps) !== Number(props.payment?.inps)));
const needsInpsConfirmation = computed(() => inpsChanged.value && props.payment?.inpsState?.remitted > 0);
const correctedDue = computed(() => (props.payment?.inpsState?.due || 0) - Number(props.payment?.inps || 0) + (cancelMode.value ? 0 : Number(form.value.inps || 0)));
const canSubmit = computed(() => {
    if (props.loading || props.payment?.status !== 'active' || props.payment?.inpsState?.closed || !form.value.reason?.trim() || (needsInpsConfirmation.value && !inpsConfirmed.value)) return false;
    if (cancelMode.value) return props.payment?.canCancel && entryErrorConfirmed.value;
    return (
        [form.value.baseSalaryAmount, form.value.primeAmount, form.value.indemniteSpeciale, form.value.inps].every((value) => Number.isFinite(Number(value)) && Number(value) >= 0) &&
        net.value >= 0 &&
        form.value.paidAmount > 0 &&
        form.value.paidAmount <= maxAllowed.value &&
        form.value.paymentMethodId &&
        form.value.paidAt instanceof Date &&
        !Number.isNaN(form.value.paidAt.getTime())
    );
});

const formatCurrency = (value) => {
    if (value === null || value === undefined) return '—';
    return `${Number(value).toLocaleString('fr-FR')} F CFA`;
};

const periodLabel = computed(() => {
    if (props.payment?.workedDay) {
        return new Date(props.payment.workedDay).toLocaleDateString('fr-FR');
    }
    const month = props.payment?.month;
    const year = props.payment?.year;
    if (!month || !year) return '—';
    return new Date(year, month - 1, 1).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
});

const close = () => {
    localVisible.value = false;
};

const submit = () => {
    if (!canSubmit.value) return;
    const date = form.value.paidAt;
    const paidAt = date instanceof Date ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}` : '';

    const payload = {
        paidAmount: Number(form.value.paidAmount),
        paidAt,
        paymentMethodId: form.value.paymentMethodId,
        note: form.value.note || '',
        reason: form.value.reason,
        version: props.payment.version,
        inpsFingerprint: needsInpsConfirmation.value && inpsConfirmed.value ? props.payment.inpsState.fingerprint : null,
        entryErrorConfirmed: entryErrorConfirmed.value
    };
    if (props.payment.canRecalculate && !cancelMode.value) Object.assign(payload, { baseSalaryAmount: form.value.baseSalaryAmount, primeAmount: form.value.primeAmount, indemniteSpeciale: form.value.indemniteSpeciale, inps: form.value.inps });

    emit(cancelMode.value ? 'cancel-payment' : 'submit', payload);
};
</script>

<template>
    <AppDialog v-model:visible="localVisible" :title="cancelMode ? 'Annuler une saisie de paiement' : 'Détail du paiement'" icon="pi pi-file" icon-tone="info" size="lg" :loading="loading" :closable="!loading" :dismissable-mask="!loading" :show-footer="true" @hide="close">
        <div v-if="payment" class="space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold">{{ payment.employeeName }}</h3>
                    <p class="text-sm text-surface-500">{{ payment.employeeFonction || '—' }} · {{ periodLabel }}</p>
                </div>
                <Tag :value="formatFrequenceLabel(payment.frequenceSnapshot)" severity="info" />
            </div>

            <div class="grid grid-cols-2 gap-3 text-sm rounded-xl border border-surface-200 dark:border-surface-700 p-4 bg-surface-50/60 dark:bg-surface-900/30">
                <div>
                    <span class="text-surface-500">Type salaire</span>
                    <p class="font-semibold uppercase tracking-wide">{{ formatSalaryTypeLabel(payment.salaryType) }}</p>
                </div>
                <div>
                    <span class="text-surface-500">Prime</span>
                    <p class="font-semibold uppercase tracking-wide">{{ formatPrimeTypeLabel(payment.primeType) }}</p>
                </div>
                <div>
                    <span class="text-surface-500">Salaire base</span>
                    <p class="font-semibold">{{ formatCurrency(payment.baseSalaryAmount) }}</p>
                </div>
                <div>
                    <span class="text-surface-500">Montant prime</span>
                    <p class="font-semibold">{{ formatCurrency(payment.primeAmount) }}</p>
                </div>
                <div>
                    <span class="text-surface-500">Total calculé</span>
                    <p class="font-semibold">{{ formatCurrency(payment.calculatedAmount) }}</p>
                </div>
                <div>
                    <span class="text-surface-500">Montant versé</span>
                    <p class="font-semibold text-primary-600 dark:text-primary-300">{{ formatCurrency(payment.paidAmount) }}</p>
                </div>
            </div>

            <div v-if="!editMode" class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <span class="text-surface-500">Date de règlement</span>
                    <p class="font-medium">{{ payment.paidAt || '—' }}</p>
                </div>
                <div>
                    <span class="text-surface-500">Mode de règlement</span>
                    <p class="font-medium">{{ payment.paymentMethod?.libelle || '—' }}</p>
                </div>
                <div class="col-span-2">
                    <span class="text-surface-500">Note</span>
                    <p class="font-medium">{{ payment.note || '—' }}</p>
                </div>
            </div>

            <Message v-if="payment.inpsState?.closed" severity="info" :closable="false">Le trimestre est clôturé. Les corrections sont bloquées.</Message>
            <Message v-if="payment.status === 'cancelled'" severity="warn" :closable="false">Ce paiement est annulé. Sa trace est conservée.</Message>
            <div v-if="editMode" class="space-y-3">
                <Message v-if="cancelMode" severity="warn" :closable="false">Cette action neutralise une saisie erronée et sa sortie de trésorerie. Si l’argent a réellement été versé, conservez le paiement.</Message>
                <Message v-if="cancelMode && !payment.canCancel" severity="error" :closable="false">Le premier paiement ne peut pas être annulé tant que d’autres paiements restent actifs sur cette période.</Message>
                <template v-if="!cancelMode">
                    <Message v-if="!payment.canRecalculate" severity="info" :closable="false">Le calcul du salaire est figé. Vous pouvez corriger le versement sans dépasser le net à payer.</Message>
                    <div v-if="payment.canRecalculate" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div><label class="block text-sm font-medium mb-1">Salaire de base</label><InputNumber v-model="form.baseSalaryAmount" :min="0" :maxFractionDigits="2" suffix=" F CFA" class="w-full" /></div>
                        <div><label class="block text-sm font-medium mb-1">Prime</label><InputNumber v-model="form.primeAmount" :disabled="payment.primeType !== 'fixe'" :min="0" :maxFractionDigits="2" suffix=" F CFA" class="w-full" /></div>
                        <div><label class="block text-sm font-medium mb-1">Indemnité spéciale</label><InputNumber v-model="form.indemniteSpeciale" :min="0" :maxFractionDigits="2" suffix=" F CFA" class="w-full" /></div>
                        <div><label class="block text-sm font-medium mb-1">Retenue INPS</label><InputNumber v-model="form.inps" :min="0" :maxFractionDigits="2" suffix=" F CFA" class="w-full" /></div>
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Montant versé</label>
                        <InputNumber v-model="form.paidAmount" class="w-full" :min="0" :max="maxAllowed" :maxFractionDigits="2" :disabled="payment.frequenceSnapshot === 'journalier'" suffix=" F CFA" />
                        <p class="text-xs text-surface-500">
                            Net : {{ formatCurrency(net) }} · Autres versements : {{ formatCurrency(activeOthers) }} · Maximum : {{ formatCurrency(maxAllowed) }} · Reste après correction :
                            {{ formatCurrency(maxAllowed - Number(form.paidAmount || 0)) }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Mode de règlement</label>
                        <Select v-model="form.paymentMethodId" :options="paymentMethods" optionLabel="libelle" optionValue="id" class="w-full" placeholder="Sélectionnez un mode" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Date de règlement</label>
                        <DatePicker v-model="form.paidAt" dateFormat="yy-mm-dd" showIcon class="w-full" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Note</label>
                        <Textarea v-model="form.note" rows="3" class="w-full" autoResize />
                    </div>
                </template>
                <div><label class="block text-sm font-medium mb-1">Motif obligatoire</label><Textarea v-model="form.reason" rows="2" class="w-full" /></div>
                <div v-if="cancelMode" class="flex items-center gap-2"><Checkbox v-model="entryErrorConfirmed" inputId="payroll-entry-error" binary /><label for="payroll-entry-error">Je confirme qu’il s’agit d’une erreur de saisie.</label></div>
                <Message v-if="needsInpsConfirmation" severity="warn" :closable="false">
                    <p>Des remises INPS existent sur ce trimestre. Retenue : {{ formatCurrency(payment.inps) }} → {{ formatCurrency(cancelMode ? 0 : form.inps) }}. Les remises réelles seront conservées.</p>
                    <p>
                        Total dû après correction : {{ formatCurrency(correctedDue) }} · Déjà remis : {{ formatCurrency(payment.inpsState.remitted) }} ·
                        {{ correctedDue >= payment.inpsState.remitted ? 'Reste à remettre' : 'Excédent à régulariser' }} : {{ formatCurrency(Math.abs(correctedDue - payment.inpsState.remitted)) }}.
                    </p>
                    <div class="flex items-center gap-2 mt-2"><Checkbox v-model="inpsConfirmed" inputId="payroll-inps-confirm" binary /><label for="payroll-inps-confirm">Je confirme cette régularisation INPS.</label></div>
                </Message>
            </div>
            <div>
                <h4 class="font-semibold mb-2">Paiements de la période</h4>
                <div class="page-table-scroll">
                    <DataTable :value="payment.payments || []" dataKey="id" size="small"
                        ><Column field="paidAt" header="Date" /><Column header="Montant"
                            ><template #body="{ data }">{{ formatCurrency(data.paidAmount) }}</template></Column
                        ><Column header="Mode"
                            ><template #body="{ data }">{{ data.paymentMethod?.libelle }}</template></Column
                        ><Column header="Statut"
                            ><template #body="{ data }"
                                ><Tag :value="data.status === 'cancelled' ? 'Annulé' : data.id === payment.id ? 'Paiement sélectionné' : 'Actif'" :severity="data.status === 'cancelled' ? 'secondary' : 'info'" /></template></Column
                    ></DataTable>
                </div>
            </div>
            <details v-if="payment.history?.length">
                <summary class="cursor-pointer font-medium">Historique des corrections</summary>
                <div v-for="(entry, index) in payment.history" :key="index" class="border-b border-surface-200 dark:border-surface-700 py-3 text-sm">
                    <p>{{ entry.at }} · {{ entry.actor }} · {{ entry.reason }}</p>
                    <p v-if="entry.before?.paidAmount !== undefined">
                        Versement : {{ formatCurrency(entry.before.paidAmount) }} → {{ formatCurrency(entry.after.paidAmount) }} · INPS : {{ formatCurrency(entry.before.inps) }} → {{ formatCurrency(entry.after.inps) }}
                    </p>
                </div>
            </details>
        </div>

        <template #footer>
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-between w-full">
                <Button label="Imprimer le bulletin" icon="pi pi-print" severity="secondary" outlined @click="emit('print', payment)" />
                <div class="flex flex-wrap gap-2">
                    <Button label="Fermer" text severity="secondary" :disabled="loading" @click="close" />
                    <Button
                        v-if="!editMode && payment?.status === 'active'"
                        label="Modifier"
                        icon="pi pi-pencil"
                        severity="info"
                        :disabled="payment?.inpsState?.closed"
                        @click="
                            editMode = true;
                            cancelMode = false;
                        "
                    />
                    <Button
                        v-if="!editMode && payment?.status === 'active'"
                        label="Annuler le paiement"
                        icon="pi pi-ban"
                        severity="danger"
                        outlined
                        :disabled="!payment?.canCancel || payment?.inpsState?.closed"
                        @click="
                            editMode = true;
                            cancelMode = true;
                        "
                    />
                    <Button v-if="editMode" :label="cancelMode ? 'Confirmer l’annulation' : 'Enregistrer la correction'" icon="pi pi-check" :severity="cancelMode ? 'danger' : 'primary'" :disabled="!canSubmit" :loading="loading" @click="submit" />
                </div>
            </div>
        </template>
    </AppDialog>
</template>

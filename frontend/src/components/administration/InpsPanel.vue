<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import ProgressBar from 'primevue/progressbar';
import Checkbox from 'primevue/checkbox';
import AppDialog from '@/components/layout/AppDialog.vue';
import PageSection from '@/components/layout/PageSection.vue';
import PrintDataTablePage from '@/components/print/PrintDataTablePage.vue';
import { usePrinter } from '@/composables/usePrinter';
import { useToast } from 'primevue/usetoast';
import { fetchInps, createInpsRemittance, cancelInpsRemittance, setInpsClosure, fetchPayrollPaymentMethods } from '@/services/rhManagementService';

const year = ref(new Date().getFullYear());
const quarter = ref(Math.ceil((new Date().getMonth() + 1) / 3));
const quarters = [1, 2, 3, 4].map((value) => ({ value, label: `T${value} · ${['Janvier–Mars', 'Avril–Juin', 'Juillet–Septembre', 'Octobre–Décembre'][value - 1]}` }));
const state = ref(null);
const error = ref('');
const loading = ref(false);
const saving = ref(false);
const dialog = ref(false);
const action = ref('remit');
const selected = ref(null);
const methods = ref([]);
const form = ref({});
const expanded = ref({});
const toast = useToast();
const { printComponent } = usePrinter();
const token = () => localStorage.getItem('token') || localStorage.getItem('authToken');
const money = (value) => `${Number(value || 0).toLocaleString('fr-FR')} F CFA`;
const months = computed(() => [0, 1, 2].map((offset) => ({ number: (quarter.value - 1) * 3 + offset + 1, label: new Date(year.value, (quarter.value - 1) * 3 + offset).toLocaleDateString('fr-FR', { month: 'long' }) })));
const progress = computed(() => (state.value?.due ? Math.min(100, Math.round((state.value.remitted / state.value.due) * 100)) : 0));
const status = computed(() =>
    state.value?.surplus > 0
        ? { label: 'Excédent à régulariser', severity: 'warn' }
        : state.value?.remaining === 0 && state.value?.due > 0
          ? { label: 'Remis', severity: 'success' }
          : state.value?.remitted > 0
            ? { label: 'Partiellement remis', severity: 'warn' }
            : state.value?.due > 0
              ? { label: 'À remettre', severity: 'info' }
              : { label: 'Aucune retenue', severity: 'secondary' }
);
const title = computed(() => ({ remit: 'Enregistrer une remise INPS', cancel: 'Annuler une remise saisie par erreur', closure: state.value?.closed ? 'Réouvrir le trimestre' : 'Clôturer le trimestre' })[action.value]);
const valid = computed(() =>
    action.value === 'remit'
        ? form.value.amount > 0 &&
          form.value.amount <= state.value?.remaining &&
          form.value.beneficiary?.trim() &&
          form.value.paymentMethodId &&
          form.value.paidAt instanceof Date &&
          !Number.isNaN(form.value.paidAt.getTime()) &&
          (!form.value.alreadyRecorded || form.value.existingTransactionId > 0)
        : form.value.reason?.trim() && (action.value !== 'cancel' || form.value.entryErrorConfirmed)
);
let request = 0;
async function load() {
    const id = ++request;
    loading.value = true;
    error.value = '';
    state.value = null;
    try {
        const data = await fetchInps({ year: year.value, quarter: quarter.value }, token());
        if (id === request) state.value = data;
    } catch (err) {
        if (id === request) error.value = err?.response?.data?.message || 'Chargement INPS impossible.';
    } finally {
        if (id === request) loading.value = false;
    }
}
async function open(mode, record = null) {
    action.value = mode;
    selected.value = record;
    form.value = { amount: state.value.remaining, paidAt: new Date(), beneficiary: '', reference: '', note: '', reason: '', entryErrorConfirmed: false, requestId: crypto.randomUUID() };
    try {
        if (mode === 'remit') methods.value = await fetchPayrollPaymentMethods(token());
        dialog.value = true;
    } catch {
        toast.add({ severity: 'error', summary: 'INPS', detail: 'Impossible de charger les modes de règlement.', life: 4000 });
    }
}
async function submit() {
    if (!valid.value || saving.value) return;
    saving.value = true;
    try {
        let result;
        const payload = { ...form.value, fingerprint: state.value.fingerprint };
        if (!form.value.alreadyRecorded) delete payload.existingTransactionId;
        if (action.value === 'remit') {
            const date = form.value.paidAt;
            payload.paidAt = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
            result = await createInpsRemittance({ ...payload, year: year.value, quarter: quarter.value }, token());
        } else if (action.value === 'cancel') result = await cancelInpsRemittance(selected.value.id, payload, token());
        else result = await setInpsClosure(year.value, quarter.value, { ...payload, closed: !state.value.closed }, token());
        state.value = result;
        dialog.value = false;
        toast.add({ severity: 'success', summary: 'INPS', detail: 'Opération enregistrée.', life: 3000 });
    } catch (err) {
        toast.add({ severity: 'error', summary: 'INPS', detail: err?.response?.data?.message || 'Enregistrement impossible. Actualisez la période avant de réessayer.', life: 6000 });
    } finally {
        saving.value = false;
    }
}
async function print(record = null) {
    try {
        await printComponent(
            PrintDataTablePage,
            record
                ? {
                      title: 'Bordereau de remise INPS',
                      subtitle: `${record.reference} · T${quarter.value} ${year.value} · ${record.status === 'cancelled' ? 'ANNULÉ' : 'Remis à la direction'}`,
                      columns: [
                          { key: 'label', label: 'Information' },
                          { key: 'value', label: 'Détail' }
                      ],
                      rows: [
                          { label: 'Date', value: record.paidAt },
                          { label: 'Bénéficiaire', value: record.beneficiary },
                          { label: 'Montant', value: money(record.amount) },
                          { label: 'Mode', value: record.paymentMethod },
                          { label: 'Observation', value: record.note || '—' },
                          { label: 'Signature du bénéficiaire', value: '' }
                      ]
                  }
                : {
                      title: 'État trimestriel INPS',
                      subtitle: `T${quarter.value} ${year.value} · Retenues : ${money(state.value.due)} · Remis : ${money(state.value.remitted)} · Reste : ${money(state.value.remaining)} · Excédent : ${money(state.value.surplus)}`,
                      columns: [{ key: 'name', label: 'Employé' }, ...months.value.map((month) => ({ key: `m${month.number}`, label: month.label, align: 'right' })), { key: 'total', label: 'Total', align: 'right' }],
                      rows: state.value.employees.map((employee) => ({
                          name: employee.name,
                          ...Object.fromEntries(months.value.map((month) => [`m${month.number}`, employee.months[month.number] === undefined ? 'Paie non enregistrée' : money(employee.months[month.number])])),
                          total: money(employee.total)
                      }))
                  }
        );
    } catch {
        toast.add({ severity: 'error', summary: 'Impression', detail: 'Impression impossible.', life: 4000 });
    }
}
watch([year, quarter], load);
onMounted(load);
</script>

<template>
    <div class="space-y-6" :aria-busy="loading">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="grid w-full grid-cols-1 gap-3 sm:w-auto sm:grid-cols-[minmax(8rem,auto)_minmax(10rem,1fr)_auto] sm:items-end">
                <div class="min-w-0"><label for="inps-year" class="mb-2 block text-sm font-medium">Année</label><InputNumber v-model="year" inputId="inps-year" :min="2000" :max="2100" :useGrouping="false" :disabled="dialog || saving" class="w-full sm:w-32" inputClass="w-full" /></div>
                <div class="min-w-0"><label for="inps-quarter" class="mb-2 block text-sm font-medium">Trimestre</label><Select v-model="quarter" inputId="inps-quarter" :options="quarters" optionLabel="label" optionValue="value" :disabled="dialog || saving" class="w-full" /></div>
                <Button class="justify-self-start sm:justify-self-auto" icon="pi pi-refresh" severity="secondary" outlined :loading="loading" :disabled="dialog || saving" aria-label="Actualiser l’INPS" @click="load" />
            </div>
            <div v-if="state" class="flex flex-wrap gap-2">
                <Button label="Imprimer" icon="pi pi-print" severity="secondary" outlined @click="print()" />
                <Button :label="state.closed ? 'Réouvrir' : 'Clôturer'" :icon="state.closed ? 'pi pi-lock-open' : 'pi pi-lock'" severity="secondary" outlined @click="open('closure')" />
                <Button label="Enregistrer une remise" icon="pi pi-wallet" :disabled="state.closed || state.remaining <= 0" @click="open('remit')" />
            </div>
        </div>
        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
        <p v-if="loading" class="text-surface-500">Chargement du suivi INPS…</p>
        <template v-if="state">
            <div class="page-kpi-grid">
                <article class="page-kpi-card bg-primary-50 dark:bg-primary-950/30">
                    <div>
                        <p class="page-kpi-label">Retenues du trimestre</p>
                        <p class="page-kpi-value">{{ money(state.due) }}</p>
                        <p class="text-xs text-surface-500 mt-1">Dues dès le premier paiement mensuel</p>
                    </div>
                    <i class="pi pi-users page-kpi-icon text-primary" />
                </article>
                <article class="page-kpi-card bg-emerald-50 dark:bg-emerald-950/30">
                    <div>
                        <p class="page-kpi-label">Remis à la direction</p>
                        <p class="page-kpi-value text-emerald-600">{{ money(state.remitted) }}</p>
                        <p class="text-xs text-surface-500 mt-1">Sorties de trésorerie confirmées</p>
                    </div>
                    <i class="pi pi-check-circle page-kpi-icon text-emerald-500" />
                </article>
                <article class="page-kpi-card bg-amber-50 dark:bg-amber-950/30">
                    <div>
                        <p class="page-kpi-label">Reste à remettre</p>
                        <p class="page-kpi-value text-amber-600">{{ money(state.remaining) }}</p>
                    </div>
                    <i class="pi pi-wallet page-kpi-icon text-amber-500" />
                </article>
                <article v-if="state.surplus > 0" class="page-kpi-card bg-amber-50 dark:bg-amber-950/30">
                    <div>
                        <p class="page-kpi-label">Excédent à régulariser</p>
                        <p class="page-kpi-value">{{ money(state.surplus) }}</p>
                    </div>
                    <i class="pi pi-exclamation-triangle page-kpi-icon text-amber-500" />
                </article>
            </div>
            <PageSection title="Avancement des remises">
                <div class="p-4">
                        <div class="flex flex-wrap justify-between gap-3 mb-3">
                            <Tag :value="status.label" :severity="status.severity" /><Tag :value="state.closed ? 'Trimestre clôturé' : 'Trimestre ouvert'" :severity="state.closed ? 'secondary' : 'info'" />
                        </div>
                        <ProgressBar :value="progress" />
                        <p class="text-sm text-surface-500 mt-3">Les remises sont conservées lorsqu’une paie est corrigée. Le solde est recalculé sur les retenues actives du trimestre.</p>
                        <details v-if="state.audit.length" class="text-sm mt-3">
                            <summary class="cursor-pointer">Historique des clôtures</summary>
                            <p v-for="(entry, index) in state.audit" :key="index" class="mt-2">{{ entry.at }} · {{ entry.actor }} · {{ entry.action === 'closed' ? 'Clôture' : 'Réouverture' }} · {{ entry.reason }}</p>
                        </details>
                        <Message v-if="state.surplus > 0" severity="warn" :closable="false" class="mt-3">{{ money(state.surplus) }} ont été remis en excédent après correction. Conservez la remise réelle ; sa régularisation doit faire l’objet d’un mouvement justifié.</Message>
                </div>
            </PageSection>
            <PageSection title="Retenues par employé" subtitle="Une retenue par employé et par mois, quel que soit le nombre de versements.">
                <div class="page-table-scroll">
                    <DataTable :value="state.employees" dataKey="id">
                        <Column field="name" header="Employé" />
                        <Column v-for="month in months" :key="month.number" :header="month.label"
                            ><template #body="{ data }"
                                ><span v-if="data.months[month.number] !== undefined">{{ money(data.months[month.number]) }}</span
                                ><span v-else class="text-xs text-surface-500">Paie non enregistrée</span></template
                            ></Column
                        >
                        <Column header="Total"
                            ><template #body="{ data }"
                                ><span class="font-semibold">{{ money(data.total) }}</span></template
                            ></Column
                        >
                        <template #empty>Aucune paie mensuelle enregistrée pour ce trimestre.</template>
                    </DataTable>
                </div>
            </PageSection>
            <PageSection title="Historique des remises">
                <div class="page-table-scroll">
                    <DataTable v-model:expandedRows="expanded" :value="state.remittances" dataKey="id">
                        <Column expander style="width: 3rem" /><Column field="paidAt" header="Date" /><Column field="beneficiary" header="Bénéficiaire" /><Column field="reference" header="Référence" />
                        <Column header="Montant"
                            ><template #body="{ data }">{{ money(data.amount) }}</template></Column
                        ><Column field="paymentMethod" header="Mode" />
                        <Column header="Statut"
                            ><template #body="{ data }"><Tag :value="data.status === 'cancelled' ? 'Annulée' : 'Remise confirmée'" :severity="data.status === 'cancelled' ? 'secondary' : 'success'" /></template
                        ></Column>
                        <Column header="Actions"
                            ><template #body="{ data }"
                                ><Button icon="pi pi-print" text rounded aria-label="Imprimer le bordereau" @click="print(data)" /><Button
                                    v-if="data.status !== 'cancelled' && !state.closed"
                                    icon="pi pi-ban"
                                    text
                                    rounded
                                    severity="danger"
                                    aria-label="Annuler une saisie erronée"
                                    @click="open('cancel', data)" /></template
                        ></Column>
                        <template #expansion="{ data }"
                            ><div class="p-4 space-y-2">
                                <p v-if="data.note">{{ data.note }}</p>
                                <p v-for="(entry, index) in data.audit" :key="index" class="text-sm text-surface-500">
                                    {{ entry.at }} · {{ entry.actor }} · {{ entry.action === 'created' ? 'Remise enregistrée' : 'Remise annulée' }} {{ entry.reason ? `· ${entry.reason}` : '' }}
                                </p>
                            </div></template
                        >
                        <template #empty>Aucune remise enregistrée.</template>
                    </DataTable>
                </div>
            </PageSection>
        </template>
        <AppDialog
            v-model:visible="dialog"
            :title="title"
            icon="pi pi-wallet"
            size="md"
            confirm-label="Confirmer"
            :loading="saving"
            :closable="!saving"
            :dismissable-mask="!saving"
            :confirm-disabled="!valid"
            @confirm="submit"
            @cancel="dialog = false"
        >
            <div class="space-y-4">
                <template v-if="action === 'remit'">
                    <Message severity="info" :closable="false">T{{ quarter }} {{ year }} · Reste à remettre : {{ money(state.remaining) }}</Message>
                    <div>
                        <label for="inps-amount" class="block text-sm font-medium mb-1">Montant remis</label
                        ><InputNumber v-model="form.amount" inputId="inps-amount" :min="0" :max="state.remaining" :maxFractionDigits="2" suffix=" F CFA" class="w-full" />
                    </div>
                    <div><label for="inps-date" class="block text-sm font-medium mb-1">Date de remise</label><DatePicker v-model="form.paidAt" inputId="inps-date" dateFormat="dd/mm/yy" showIcon class="w-full" /></div>
                    <div><label for="inps-beneficiary" class="block text-sm font-medium mb-1">Bénéficiaire à la direction</label><InputText v-model="form.beneficiary" id="inps-beneficiary" maxlength="255" class="w-full" /></div>
                    <div>
                        <label for="inps-mode" class="block text-sm font-medium mb-1">Mode de règlement</label><Select v-model="form.paymentMethodId" inputId="inps-mode" :options="methods" optionLabel="libelle" optionValue="id" class="w-full" />
                    </div>
                    <div class="flex items-center gap-2"><Checkbox v-model="form.alreadyRecorded" inputId="inps-already-recorded" binary /><label for="inps-already-recorded">La sortie est déjà enregistrée dans Finances</label></div>
                    <div v-if="form.alreadyRecorded">
                        <label for="inps-existing-transaction" class="block text-sm font-medium mb-1">N° de la transaction existante</label
                        ><InputNumber v-model="form.existingTransactionId" inputId="inps-existing-transaction" :min="1" :useGrouping="false" class="w-full" />
                        <p class="text-xs text-surface-500 mt-1">La dépense doit être manuelle et validée, avec les mêmes montant, date et mode. Elle sera liée à cette remise sans nouvelle sortie de trésorerie.</p>
                    </div>
                    <div><label for="inps-reference" class="block text-sm font-medium mb-1">Référence du reçu (facultatif)</label><InputText v-model="form.reference" id="inps-reference" maxlength="120" class="w-full" /></div>
                    <div><label for="inps-note" class="block text-sm font-medium mb-1">Observation</label><Textarea v-model="form.note" id="inps-note" rows="2" class="w-full" /></div>
                    <p class="font-medium">Après cette remise : {{ money(Math.max(0, state.remaining - (form.amount || 0))) }} à remettre.</p>
                </template>
                <template v-else>
                    <Message v-if="action === 'cancel'" severity="warn" :closable="false">Annuler cette saisie neutralisera la sortie de {{ money(selected.amount) }} en trésorerie. Une remise réellement effectuée doit être conservée.</Message>
                    <Message v-else severity="info" :closable="false">{{
                        state.closed
                            ? 'La réouverture autorisera à nouveau les corrections de paie et les remises sur ce trimestre.'
                            : 'La clôture bloquera les créations, corrections et annulations de paie et de remises du trimestre. Le trimestre doit être terminé et soldé.'
                    }}</Message>
                    <div><label for="inps-reason" class="block text-sm font-medium mb-1">Motif obligatoire</label><Textarea v-model="form.reason" id="inps-reason" rows="3" class="w-full" /></div>
                    <div v-if="action === 'cancel'" class="flex gap-2 items-center"><Checkbox v-model="form.entryErrorConfirmed" inputId="inps-error" binary /><label for="inps-error">Je confirme qu’il s’agit d’une erreur de saisie.</label></div>
                </template>
            </div>
        </AppDialog>
    </div>
</template>

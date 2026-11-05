<script setup>
import DatePicker from 'primevue/datepicker';
import AppDialog from '@/components/layout/AppDialog.vue';
import PrintDevisBody from '@/components/print/PrintDevisBody.vue';
import PrintFactureAssuranceBody from '@/components/print/PrintFactureAssuranceBody.vue';
import Checkbox from 'primevue/checkbox';
import ToggleSwitch from 'primevue/toggleswitch';
import { fetchFactureAssurancePrintData } from '@/services/printService';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { computed, nextTick, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { usePrinter } from '@/composables/usePrinter';
import { createCabinetService, fetchCabinetInvoicePrintData, fetchCabinetService, payCabinetServiceInvoice, updateCabinetService } from '@/services/cabinetServices';
import { usePaymentMethodsStore } from '@/stores/paymentMethods';
import { getDefaultClassicMethod, getPaymentMethodDefinition } from '@/utils/paymentMethodUtils';
import { defaultServicesCabinetList, normalizeServicesCabinetList } from '@/services/consultations';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { fetchPatientById, normalizePatient, searchPatients } from '@/services/patients';
import { useAuthStore } from '@/stores/auth';
import { logAppError } from '@/utils/appLogger';

const visible = defineModel('visible', { type: Boolean, default: false });

const props = defineProps({
    patientId: { type: [Number, String], default: null },
    patientName: { type: String, default: '' },
    /** When set, dialog switches to edit mode */
    service: { type: Object, default: null },
    /** Allow picking a patient when creating without a preselected patient */
    allowPatientSelect: { type: Boolean, default: false }
});

const emit = defineEmits(['created', 'updated']);

const auth = useAuthStore();
const paymentMethodsStore = usePaymentMethodsStore();
const payImmediately = ref(false);
const paymentModeId = ref(null);
const paymentMethods = ref([]);
const paymentMethodsLoading = ref(false);
const savedService = ref(null);
const paymentCompleted = ref(false);
const paymentOptions = computed(() => paymentMethods.value.filter((method) => method.actif !== false).map((method) => ({
    label: `${method.libelle}${getPaymentMethodDefinition(method).label ? ` (${getPaymentMethodDefinition(method).label})` : ''}`,
    value: method.id
})));
const toast = useToast();
const { printComponent } = usePrinter();
const catalog = ref(defaultServicesCabinetList.map((item) => ({ ...item })));
const saving = ref(false);
const printing = ref(false);
const saved = ref(false);
const savedFactureId = ref(null);
const savedServiceId = ref(null);
const savedInsured = ref(false);
const insuranceProfile = ref(null);
const insuranceLoading = ref(false);
const insuranceError = ref(false);
let insuranceRequest = 0;
const ignoreFormWatch = ref(false);
const form = ref(emptyForm());
const selectedPatientId = ref(null);
const patients = ref([]);
const patientsLoading = ref(false);
let patientSearchTimeout = null;

const isEdit = computed(() => Boolean(props.service?.id));
const amountLocked = computed(() => paymentCompleted.value || Boolean((savedService.value || props.service)?.facture?.hasPayments) || (savedService.value || props.service)?.canModify === false);
const dialogTitle = computed(() => (isEdit.value ? 'Modifier la prestation du cabinet' : 'Enregistrer une prestation du cabinet'));
const confirmLabel = computed(() => (isEdit.value ? 'Enregistrer' : 'Enregistrer'));
const resolvedSubtitle = computed(() => {
    if (isEdit.value) {
        return props.service?.patientName || props.patientName || null;
    }
    return props.patientName || null;
});
const needsPatientSelect = computed(() => !isEdit.value && props.allowPatientSelect && !props.patientId);
const effectivePatientId = computed(() => {
    if (isEdit.value) return props.service?.patientId ?? props.patientId;
    return props.patientId ?? selectedPatientId.value;
});

const total = computed(() => Math.max(0, Number(form.value.quantite) || 0) * Math.max(0, Number(form.value.prix) || 0));
const canPrint = computed(() => saved.value && Number(savedFactureId.value) > 0);
const coverageRate = computed(() => isEdit.value ? Number(props.service?.facture?.insurance?.tauxCouverture || 0) : Number(insuranceProfile.value?.coverageRate || 0));
const insuranceName = computed(() => isEdit.value ? props.service?.facture?.insurance?.assuranceNom : insuranceProfile.value?.assurance?.nom);
const canCover = computed(() => Boolean(insuranceProfile.value?.assurance?.actif && coverageRate.value > 0 && coverageRate.value <= 100));
const insurerAmount = computed(() => form.value.coveredByInsurance ? Math.round(total.value * coverageRate.value) / 100 : 0);
const patientAmount = computed(() => Math.round((total.value - insurerAmount.value) * 100) / 100);

watch(payImmediately, async (pay) => {
    if (!pay || paymentMethods.value.length) return;
    paymentMethodsLoading.value = true;
    try {
        paymentMethods.value = await paymentMethodsStore.load(auth.token);
        paymentModeId.value = getDefaultClassicMethod(paymentMethods.value)?.id ?? paymentOptions.value[0]?.value ?? null;
    } catch (error) {
        logAppError('Prestations du cabinet modes de paiement', error);
        toast.add({ severity: 'error', summary: 'Paiement', detail: 'Impossible de charger les moyens de paiement. Désactivez puis réactivez le bouton Payé pour réessayer.', life: 3500 });
    } finally {
        paymentMethodsLoading.value = false;
    }
});

watch([visible, effectivePatientId], async ([open, patientId]) => {
    const request = ++insuranceRequest;
    insuranceProfile.value = null;
    insuranceError.value = false;
    insuranceLoading.value = false;
    if (!open || isEdit.value || !patientId) return;
    form.value.coveredByInsurance = false;
    insuranceLoading.value = true;
    try {
        const patient = await fetchPatientById(patientId, auth.token);
        if (request === insuranceRequest) insuranceProfile.value = patient?.insuranceProfile ?? null;
    } catch (error) {
        if (request === insuranceRequest) insuranceError.value = true;
        logAppError('Assurance du patient', error);
    } finally {
        if (request === insuranceRequest) insuranceLoading.value = false;
    }
});

watch(
    form,
    () => {
        if (ignoreFormWatch.value) return;
        saved.value = false;
    },
    { deep: true }
);

watch(selectedPatientId, () => {
    if (ignoreFormWatch.value || isEdit.value) return;
    saved.value = false;
});

const patientOptions = computed(() =>
    patients.value.map((p) => ({
        label: p.fullname || `${p.prenom ?? ''} ${p.nom ?? ''}`.trim() || p.nom || 'Patient',
        value: p.id,
        phone: p.telephone || p.phone || '',
        searchText: [p.fullname, `${p.prenom ?? ''} ${p.nom ?? ''}`.trim(), p.nom, p.telephone, p.phone].filter(Boolean).join(' ')
    }))
);

function emptyForm() {
    const first = catalog.value[0];
    return {
        designation: first?.description || '',
        quantite: 1,
        prix: Number(first?.montant) || 0,
        date: new Date(),
        note: '',
        coveredByInsurance: false
    };
}

function formFromService(service) {
    return {
        designation: service?.designation || '',
        quantite: Number(service?.quantite) || 1,
        prix: Number(service?.prix) || 0,
        date: service?.date ? new Date(service.date) : new Date(),
        note: service?.note || '',
        coveredByInsurance: Boolean(service?.coveredByInsurance)
    };
}

const loadCatalog = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(auth.token);
        catalog.value = normalizeServicesCabinetList(settings?.servicesCabinetList);
    } catch (error) {
        logAppError('Prestations du cabinet', error);
        catalog.value = defaultServicesCabinetList.map((item) => ({ ...item }));
    }
    if (!isEdit.value && !form.value.designation && catalog.value[0]) {
        form.value.designation = catalog.value[0].description;
        form.value.prix = Number(catalog.value[0].montant) || 0;
    }
};

const loadPatients = async (query = '') => {
    patientsLoading.value = true;
    try {
        const data = await searchPatients(query, auth.token, 20);
        patients.value = data.map((p) => normalizePatient(p));
    } catch (error) {
        logAppError('Prestations du cabinet patients', error);
        patients.value = [];
    } finally {
        patientsLoading.value = false;
    }
};

const handlePatientFilter = (event) => {
    const query = event?.value ?? event?.query ?? '';
    if (patientSearchTimeout) clearTimeout(patientSearchTimeout);
    patientSearchTimeout = setTimeout(() => {
        loadPatients(query);
    }, 250);
};

const factureIdFrom = (service) => Number(service?.facture?.id) || null;

const applySavedState = async (service) => {
    const factureId = factureIdFrom(service);
    ignoreFormWatch.value = true;
    savedServiceId.value = Number(service?.id) || savedServiceId.value;
    savedService.value = service;
    savedInsured.value = Boolean(service?.coveredByInsurance);
    savedFactureId.value = factureId;
    saved.value = Boolean(factureId);
    await nextTick();
    ignoreFormWatch.value = false;
};

watch(visible, async (open) => {
    if (!open) {
        return;
    }
    ignoreFormWatch.value = true;
    payImmediately.value = false;
    paymentCompleted.value = false;
    savedService.value = null;
    saved.value = false;
    savedFactureId.value = null;
    savedInsured.value = Boolean(props.service?.coveredByInsurance);
    savedServiceId.value = isEdit.value ? Number(props.service.id) : null;
    if (isEdit.value) {
        form.value = formFromService(props.service);
        savedFactureId.value = factureIdFrom(props.service);
        saved.value = Boolean(savedFactureId.value);
    } else {
        form.value = emptyForm();
        selectedPatientId.value = props.patientId ? Number(props.patientId) : null;
        if (needsPatientSelect.value) {
            loadPatients();
        }
    }
    await nextTick();
    ignoreFormWatch.value = false;
    loadCatalog();
});

const onDesignationChange = (value) => {
    if (amountLocked.value) return;
    const match = catalog.value.find((item) => item.description === value);
    if (match) {
        form.value.prix = Number(match.montant) || 0;
    }
};

const formatDate = (value) => {
    const date = value instanceof Date ? value : new Date();
    const pad = (part) => String(part).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const submit = async () => {
    if (saving.value) return;
    if (insuranceLoading.value || insuranceError.value) {
        toast.add({ severity: 'warn', summary: 'Assurance', detail: 'Le profil assurance doit être chargé avant l’enregistrement. Fermez puis rouvrez le formulaire pour réessayer.', life: 3500 });
        return;
    }
    if (!effectivePatientId.value && !isEdit.value) {
        toast.add({ severity: 'warn', summary: 'Patient', detail: 'Sélectionnez un patient', life: 2500 });
        return;
    }
    if (!String(form.value.designation || '').trim()) {
        toast.add({ severity: 'warn', summary: 'Prestation', detail: 'Choisissez une prestation du cabinet', life: 2500 });
        return;
    }
    if (total.value <= 0) {
        toast.add({ severity: 'warn', summary: 'Montant', detail: 'Le montant doit être supérieur à zéro', life: 2500 });
        return;
    }
    if (!isEdit.value && payImmediately.value && patientAmount.value > 0 && !paymentCompleted.value && !paymentOptions.value.some((option) => option.value === paymentModeId.value)) {
        toast.add({ severity: 'warn', summary: 'Paiement', detail: 'Choisissez un moyen de paiement avant d’enregistrer.', life: 3000 });
        return;
    }

    const payload = {
        designation: form.value.designation,
        quantite: form.value.quantite,
        prix: form.value.prix,
        date: formatDate(form.value.date),
        note: form.value.note,
        coveredByInsurance: form.value.coveredByInsurance
    };

    try {
        saving.value = true;
        if (savedServiceId.value && isEdit.value) {
            const result = await updateCabinetService(savedServiceId.value, payload, auth.token);
            await applySavedState(result?.data);
            toast.add({ severity: 'success', summary: 'Prestation du cabinet', detail: 'Prestation modifiée', life: 2500 });
            emit('updated', result?.data || null);
            return;
        }

        let service;
        if (savedServiceId.value) {
            // Re-read the balance before retrying an interrupted payment.
            service = await fetchCabinetService(savedServiceId.value, auth.token);
            if (!service?.facture?.hasPayments && !paymentCompleted.value) {
                const result = await updateCabinetService(savedServiceId.value, payload, auth.token);
                service = result?.data;
            }
        } else {
            const result = await createCabinetService(effectivePatientId.value, payload, auth.token);
            service = result?.data;
        }
        await applySavedState(service);
        if (payImmediately.value && !paymentCompleted.value) {
            try {
                const invoice = service?.facture;
                if (!invoice?.id || invoice.reste == null) throw new Error('Facture ou solde patient indisponible');
                const remaining = Math.max(0, Number(invoice.reste) || 0);
                if (remaining > 0) {
                    await payCabinetServiceInvoice(invoice, { modeId: paymentModeId.value, montant: remaining, date: formatDate(new Date()) }, auth.token);
                    service = { ...service, facture: { ...invoice, reste: 0, isRegle: true, hasPayments: true, partPatientPayee: Number(invoice.partPatientPayee || 0) + remaining } };
                }
                paymentCompleted.value = true;
                await applySavedState(service);
            } catch (error) {
                logAppError('Prestations du cabinet paiement immédiat', error);
                toast.add({ severity: 'warn', summary: 'Prestation enregistrée', detail: `L’encaissement n’a pas pu être confirmé. ${error?.response?.data?.error || 'Réessayez ou utilisez l’action Encaisser.'}`, life: 6000 });
                emit('created', service);
                return;
            }
        }
        toast.add({ severity: 'success', summary: 'Prestation du cabinet', detail: paymentCompleted.value ? 'Prestation enregistrée, part patient réglée' : 'Prestation enregistrée et facture créée', life: 2500 });
        emit('created', service || null);
    } catch (error) {
        logAppError('Prestations du cabinet', error);
        toast.add({
            severity: 'error',
            summary: 'Prestation du cabinet',
            detail: error?.response?.data?.error || (isEdit.value ? 'Modification impossible' : 'Enregistrement impossible'),
            life: 3500
        });
    } finally {
        saving.value = false;
    }
};

const printInvoice = async () => {
    if (!canPrint.value) return;
    printing.value = true;
    try {
        const res = savedInsured.value
            ? await fetchFactureAssurancePrintData(savedFactureId.value, auth.token)
            : await fetchCabinetInvoicePrintData(savedFactureId.value, auth.token);
        await printComponent(savedInsured.value ? PrintFactureAssuranceBody : PrintDevisBody, { doc: res.doc, title: res.title || 'Facture de prestation du cabinet' });
    } catch (error) {
        logAppError('Prestations du cabinet', error);
        toast.add({ severity: 'error', summary: 'Impression', detail: 'Impression indisponible', life: 3000 });
    } finally {
        printing.value = false;
    }
};
</script>

<template>
    <AppDialog
        v-model:visible="visible"
        :title="dialogTitle"
        :subtitle="resolvedSubtitle"
        icon="pi pi-briefcase"
        icon-tone="primary"
        size="md"
        :loading="saving"
        cancel-label="Fermer"
        :confirm-label="confirmLabel"
        :confirm-disabled="insuranceLoading || insuranceError || paymentMethodsLoading || paymentCompleted"
        confirm-icon="pi pi-check"
        @cancel="visible = false"
        @confirm="submit"
    >
        <template #headerExtra>
            <Tag :value="saved ? 'Enregistré' : 'Non enregistré'" :severity="saved ? 'success' : 'warn'" />
        </template>
        <div class="flex flex-col gap-4">
            <div v-if="needsPatientSelect" class="flex flex-col gap-1">
                <label class="text-sm font-medium">Patient <span class="text-red-500">*</span></label>
                <Select
                    v-model="selectedPatientId"
                    :disabled="Boolean(savedServiceId)"
                    :options="patientOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Choisir un patient"
                    class="w-full"
                    filter
                    :loading="patientsLoading"
                    :filterFields="['label', 'phone', 'searchText']"
                    @filter="handlePatientFilter"
                >
                    <template #option="{ option }">
                        <div class="flex flex-col">
                            <span class="font-medium">{{ option.label }}</span>
                            <small class="text-surface-500">{{ option.phone || 'Téléphone non renseigné' }}</small>
                        </div>
                    </template>
                </Select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Prestation</label>
                <Select
                    v-model="form.designation"
                    :options="catalog"
                    optionLabel="description"
                    optionValue="description"
                    placeholder="Choisir une prestation"
                    class="w-full"
                    editable
                    :disabled="amountLocked"
                    @update:modelValue="onDesignationChange"
                />
            </div>
            <div class="rounded-lg border border-surface-200 p-3 space-y-2 dark:border-surface-700">
                <p v-if="insuranceLoading" class="text-sm">Chargement de l’assurance…</p>
                <p v-else-if="insuranceError" class="text-sm text-red-600">Impossible de charger le profil assurance. Rouvrez le formulaire pour réessayer.</p>
                <template v-else-if="canCover || form.coveredByInsurance">
                    <p class="text-sm font-medium">{{ insuranceName }} · Couverture {{ coverageRate }} %</p>
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="form.coveredByInsurance" inputId="cabinet-service-covered" binary :disabled="isEdit || Boolean(savedServiceId)" />
                        <label for="cabinet-service-covered" class="text-sm">Cette prestation est prise en charge par l’assurance</label>
                    </div>
                    <p class="text-xs text-surface-500">À confirmer selon la prise en charge de la prestation. Le taux est figé à l’enregistrement.</p>
                </template>
                <p v-else class="text-sm text-surface-500">Prestation à la charge du patient. Aucune couverture applicable sélectionnée.</p>
                <div class="flex justify-between text-sm"><span>Part patient</span><strong>{{ patientAmount.toLocaleString('fr-FR') }} FCFA</strong></div>
                <div class="flex justify-between text-sm"><span>Part assurance</span><strong>{{ insurerAmount.toLocaleString('fr-FR') }} FCFA</strong></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">Quantité</label>
                    <InputNumber v-model="form.quantite" :min="1" :maxFractionDigits="0" class="w-full" inputClass="w-full" :disabled="amountLocked" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">Prix unitaire</label>
                    <InputNumber v-model="form.prix" mode="decimal" :min="0" :minFractionDigits="0" :maxFractionDigits="2" class="w-full" inputClass="w-full" :disabled="amountLocked" />
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Date</label>
                <DatePicker v-model="form.date" showTime hourFormat="24" dateFormat="dd/mm/yy" class="w-full" :disabled="amountLocked" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Note</label>
                <Textarea v-model="form.note" rows="2" autoResize class="w-full" :disabled="amountLocked" />
            </div>
            <p class="text-sm font-semibold text-surface-800 dark:text-surface-100">Total : {{ total.toLocaleString('fr-FR') }} FCFA</p>
            <div v-if="!isEdit" class="space-y-3 rounded-lg border border-surface-200 p-3 dark:border-surface-700">
                <div class="flex items-center justify-between gap-3">
                    <label for="cabinet-service-paid" class="text-sm font-medium">Payé — encaisser à l’enregistrement</label>
                    <ToggleSwitch v-model="payImmediately" inputId="cabinet-service-paid" :disabled="saving || paymentCompleted" />
                </div>
                <p class="text-xs text-surface-500">Seule la part patient est encaissée : {{ patientAmount.toLocaleString('fr-FR') }} FCFA. La part assurance reste suivie séparément.</p>
                <div v-if="payImmediately && patientAmount > 0" class="space-y-1">
                    <label for="cabinet-service-payment-mode" class="text-sm font-medium">Moyen de paiement</label>
                    <Select v-model="paymentModeId" inputId="cabinet-service-payment-mode" :options="paymentOptions" optionLabel="label" optionValue="value" placeholder="Choisir un moyen de paiement" class="w-full" :loading="paymentMethodsLoading" :disabled="saving || paymentCompleted" />
                </div>
                <p v-if="payImmediately && patientAmount === 0" class="text-sm text-surface-500">Prise en charge à 100 % : aucun paiement patient à enregistrer.</p>
                <p v-if="paymentCompleted" class="text-sm font-medium text-emerald-600">Part patient réglée.</p>
            </div>
        </div>
        <template #footerStart>
            <Button v-if="canPrint" label="Imprimer" icon="pi pi-print" severity="secondary" outlined :loading="printing" @click="printInvoice" />
        </template>
    </AppDialog>
</template>

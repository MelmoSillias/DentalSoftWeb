<script setup>
import { logAppError } from '@/utils/appLogger';
import FormPatient from '@/components/patients/FormPatient.vue';
import AppDialog from '@/components/layout/AppDialog.vue';
import { createPatientWithConsultation } from '@/services/patients';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { useAuthStore } from '@/stores/auth';
import { useMedecinsStore } from '@/stores/medecins';
import { usePaymentMethodsStore } from '@/stores/paymentMethods';
import { getPaymentMethodDefinition } from '@/utils/paymentMethodUtils';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import RadioButton from 'primevue/radiobutton';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:visible', 'saved']);

const toast = useToast();
const auth = useAuthStore();
const medecinsStore = useMedecinsStore();
const paymentMethodsStore = usePaymentMethodsStore();
const token = localStorage.getItem('token');
const formRef = ref(null);
const saving = ref(false);
const payant = ref(false);
const medecinId = ref(null);
const modePaiementId = ref(null);
const consultationAmount = ref(5000);
const consultationPrices = ref([5000]);
const allowPriceEdit = ref(false);
const medecins = ref([]);
const paymentMethods = ref([]);

const isAdmin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_ADMIN')));
const isRestrictedMedecin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')) && !isAdmin.value);
const medecinOptions = computed(() =>
    medecins.value.map((item) => ({
        label: item.fullName || `${item.prenom ?? ''} ${item.nom ?? ''}`.trim() || item.nom,
        value: item.id
    }))
);
const paymentMethodOptions = computed(() =>
    paymentMethods.value
        .filter((item) => item.actif !== false)
        .map((item) => ({
            label: `${item.libelle}${getPaymentMethodDefinition(item).label ? ` (${getPaymentMethodDefinition(item).label})` : ''}`,
            value: item.id
        }))
);

const formatDatePart = (dateValue) => {
    const date = dateValue ? new Date(dateValue) : null;
    if (!date || Number.isNaN(date.getTime())) return null;
    const yyyy = date.getFullYear();
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const dd = String(date.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
};

const formatTimePart = (dateValue) => {
    const date = dateValue ? new Date(dateValue) : null;
    if (!date || Number.isNaN(date.getTime())) return null;
    return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
};

const loadOptions = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        const defaultPrice = Math.max(1, Number(settings?.consultationPrice || 5000));
        const prices = Array.isArray(settings?.consultationPrices) ? settings.consultationPrices.map((amount) => Number(amount)).filter((amount) => Number.isFinite(amount) && amount > 0) : [];
        consultationPrices.value = prices.length ? prices : [defaultPrice];
        consultationAmount.value = defaultPrice;
        allowPriceEdit.value = settings?.allowConsultationPriceEditOnCreation === true;
        medecins.value = await medecinsStore.load(token);
        paymentMethods.value = await paymentMethodsStore.load(token);
    } catch (error) {
        logAppError('Erreur chargement admission rapide', error);
    }
};

watch(
    () => props.visible,
    (open) => {
        if (!open) return;
        payant.value = false;
        medecinId.value = null;
        modePaiementId.value = null;
        loadOptions();
    }
);

const close = () => {
    emit('update:visible', false);
};

const onCollected = async (patient) => {
    if (!isRestrictedMedecin.value && !medecinId.value) {
        toast.add({ severity: 'warn', summary: 'Médecin requis', detail: 'Choisissez le médecin de la consultation.', life: 3000 });
        return;
    }

    const insured = Boolean(patient?.insuranceProfile?.enabled && (patient.insuranceProfile.assuranceCode || patient.insuranceProfile.assuranceId));
    if (payant.value && !insured && !modePaiementId.value) {
        toast.add({ severity: 'warn', summary: 'Mode de paiement requis', detail: 'Choisissez un mode de paiement patient.', life: 3000 });
        return;
    }

    const now = new Date();
    saving.value = true;
    try {
        const result = await createPatientWithConsultation(
            patient,
            {
                medecin_id: isRestrictedMedecin.value ? null : medecinId.value,
                payant: payant.value ? 1 : 0,
                mode_paiement_id: payant.value && !insured ? modePaiementId.value : null,
                consultation_date: formatDatePart(now),
                consultation_time: formatTimePart(now),
                consultation_amount: payant.value ? consultationAmount.value : 0
            },
            token
        );
        toast.add({ severity: 'success', summary: 'Admission', detail: 'Patient et consultation créés.', life: 2500 });
        emit('saved', result);
        close();
    } catch (error) {
        logAppError('Erreur admission rapide', error);
        toast.add({
            severity: 'error',
            summary: 'Erreur',
            detail: error?.response?.data?.message || error?.response?.data?.error || 'Impossible de créer le patient et la consultation.',
            life: 4000
        });
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <AppDialog
        :visible="visible"
        title="Nouveau patient et consultation"
        subtitle="Le patient et sa consultation sont enregistrés ensemble"
        icon="pi pi-user-plus"
        icon-tone="primary"
        size="lg"
        maximizable
        :loading="saving || Boolean(formRef?.loading)"
        cancel-label="Annuler"
        confirm-label="Créer"
        @update:visible="emit('update:visible', $event)"
        @confirm="formRef?.submit()"
        @cancel="close"
    >
        <div class="flex flex-col gap-6">
            <FormPatient ref="formRef" hide-actions collect-only @collected="onCollected" @cancel="close" />
            <div class="grid grid-cols-1 gap-4 border-t border-surface-200 pt-4 dark:border-surface-700 md:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">
                        Médecin
                        <span class="text-red-500">*</span>
                    </label>
                    <InputText v-if="isRestrictedMedecin" value="Médecin connecté" disabled />
                    <Select v-else v-model="medecinId" :options="medecinOptions" option-label="label" option-value="value" placeholder="Choisir un médecin" class="w-full" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Consultation payante</label>
                    <div class="flex items-center gap-2">
                        <ToggleSwitch v-model="payant" />
                        <span class="text-sm text-surface-600 dark:text-surface-300">
                            {{ payant ? `Payante (${Number(consultationAmount).toLocaleString('fr-FR')})` : 'Gratuite' }}
                        </span>
                    </div>
                </div>
                <div v-if="payant && allowPriceEdit" class="flex flex-col gap-2 md:col-span-2">
                    <label class="font-semibold">Prix de la consultation</label>
                    <div class="flex flex-wrap gap-3">
                        <div v-for="price in consultationPrices" :key="price" class="flex items-center gap-2">
                            <RadioButton v-model="consultationAmount" :input-id="`quick-admission-price-${price}`" name="quickAdmissionPrice" :value="price" />
                            <label :for="`quick-admission-price-${price}`" class="cursor-pointer text-sm">{{ Number(price).toLocaleString('fr-FR') }}</label>
                        </div>
                    </div>
                    <InputNumber v-model="consultationAmount" mode="decimal" :min="1" class="w-full" input-class="w-full" />
                </div>
                <div v-if="payant" class="flex flex-col gap-2 md:col-span-2">
                    <label class="font-semibold">Mode de paiement patient</label>
                    <Select v-model="modePaiementId" :options="paymentMethodOptions" option-label="label" option-value="value" placeholder="Choisir un mode de paiement" class="w-full" />
                    <small class="text-surface-500">Requis pour une consultation payante sans assurance.</small>
                </div>
            </div>
        </div>
    </AppDialog>
</template>

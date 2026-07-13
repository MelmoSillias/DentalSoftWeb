<script setup>
import { createPatient, updatePatient } from '@/services/patients';
import { useAssurancesStore } from '@/stores/assurances';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import ConfirmPopup from 'primevue/confirmpopup';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    patient: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['saved', 'cancel']);

const confirmPopup = useConfirm();
const toast = useToast();
const loading = ref(false);
const token = localStorage.getItem('token');
const assurancesStore = useAssurancesStore();
const assurances = ref([]);
const activeTab = ref('personal');

const sexes = [
    { label: 'Homme', value: 'Homme' },
    { label: 'Femme', value: 'Femme' }
];

const groups = [
    { label: 'A+', value: 'A+' },
    { label: 'A-', value: 'A-' },
    { label: 'B+', value: 'B+' },
    { label: 'B-', value: 'B-' },
    { label: 'AB+', value: 'AB+' },
    { label: 'AB-', value: 'AB-' },
    { label: 'O+', value: 'O+' },
    { label: 'O-', value: 'O-' }
];

const referralSources = [
    { label: 'Réseaux sociaux', value: 'Reseaux sociaux' },
    { label: 'Bouche à oreille', value: 'Bouche a oreille' },
    { label: 'Recommandation', value: 'Recommandation' },
    { label: 'Par un médecin', value: 'Par un medecin' },
    { label: 'Publicité', value: 'Publicite' },
    { label: 'Autres', value: 'Autres' }
];

const form = reactive({
    nom: '',
    prenom: '',
    telephone: '',
    email: '',
    adresse: '',
    profession: '',
    lieuNaissance: '',
    sexe: '',
    dateNaissance: '',
    referencement: '',
    groupeSanguin: '',
    notes: '',
    contactUrgence: {
        nom: '',
        telephone: '',
        lienParente: ''
    },
    smsPreferences: {
        patientCreated: false,
        receipt: false,
        ticket: false,
        invoice: false,
        appointmentReminder: false,
        unsubscribed: false,
        blacklisted: false
    },
    insuranceProfile: {
        enabled: false,
        assuranceCode: '',
        assuranceId: null,
        coverageRate: 0,
        formData: {
            societe: '',
            assureNom: '',
            assureNumero: '',
            beneficiaireNom: '',
            beneficiaireNumero: '',
            sexe: '',
            souscripteur: '',
            salarieNomPrenom: '',
            salarieMatricule: '',
            patientNomPrenom: '',
            patientMatricule: '',
            patientAge: '',
            patientSexe: '',
            carteNumero: '',
            numeroPolice: '',
            titulaireNomPrenoms: '',
            assurePrincipalNom: '',
            assurePrincipalTel: '',
            avenant: '',
            numeroAssure: '',
            assureNomPrenom: '',
            assureNomPrenoms: '',
            beneficiaireNomPrenoms: '',
            beneficiaireMatricule: '',
            identifiant: '',
            nomPrenoms: ''
        }
    }
});

const insuranceOptions = computed(() =>
    (assurances.value || [])
        .filter((item) => item?.actif !== false)
        .map((item) => ({
            label: item?.nom || item?.code || 'Assurance',
            value: item?.code || '',
            id: item?.id || null
        }))
);

const hasActiveInsurances = computed(() => insuranceOptions.value.length > 0);

const selectedInsurance = computed(() =>
    (assurances.value || []).find((item) => (item?.code || '') === form.insuranceProfile.assuranceCode) || null
);

const isSbn = computed(() => form.insuranceProfile.assuranceCode === 'SBN');
const isBleues = computed(() => form.insuranceProfile.assuranceCode === 'BLEUES');
const isSunu = computed(() => form.insuranceProfile.assuranceCode === 'SUNU');
const isLafia = computed(() => form.insuranceProfile.assuranceCode === 'LAFIA');
const isSaham = computed(() => form.insuranceProfile.assuranceCode === 'SAHAM');
const isMsh = computed(() => form.insuranceProfile.assuranceCode === 'MSH');

const defaultInsuranceFormData = () => ({
    societe: '',
    assureNom: '',
    assureNumero: '',
    beneficiaireNom: '',
    beneficiaireNumero: '',
    sexe: '',
    souscripteur: '',
    salarieNomPrenom: '',
    salarieMatricule: '',
    patientNomPrenom: '',
    patientMatricule: '',
    patientAge: '',
    patientSexe: '',
    carteNumero: '',
    numeroPolice: '',
    titulaireNomPrenoms: '',
    assurePrincipalNom: '',
    assurePrincipalTel: '',
    avenant: '',
    numeroAssure: '',
    assureNomPrenom: '',
    assureNomPrenoms: '',
    beneficiaireNomPrenoms: '',
    beneficiaireMatricule: '',
    identifiant: '',
    nomPrenoms: ''
});

const resolveInsuranceProfile = (value) => {
    const insuranceProfile = value?.insuranceProfile ?? value?.assuranceProfile ?? null;
    if (!insuranceProfile) {
        return null;
    }

    const assurance = insuranceProfile.assurance ?? value?.assurance ?? null;
    const assuranceCode = insuranceProfile.assuranceCode
        ?? insuranceProfile.assurance_code
        ?? assurance?.code
        ?? '';
    const assuranceId = insuranceProfile.assuranceId
        ?? insuranceProfile.assurance_id
        ?? assurance?.id
        ?? null;
    const rawFormData = insuranceProfile.formData ?? insuranceProfile.form_data ?? {};

    return {
        enabled: Boolean(insuranceProfile.enabled ?? assuranceCode ?? assuranceId),
        assuranceCode,
        assuranceId,
        coverageRate: Number(insuranceProfile.coverageRate ?? insuranceProfile.coverage_rate ?? 0) || 0,
        formData: {
            ...defaultInsuranceFormData(),
            ...(rawFormData || {})
        }
    };
};

const loadAssurances = async () => {
    try {
        assurances.value = await assurancesStore.load(token, { force: true });
    } catch (error) {
        console.error('Erreur chargement assurances', error);
        assurances.value = [];
    }
};

onMounted(() => {
    loadAssurances();
});

const isEdit = computed(() => Boolean(props.patient?.id));
const ageInput = ref('');

const formatDateInput = (value) => {
    if (!value) return '';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toISOString().slice(0, 10);
};

const calculateAgeFromDate = (value) => {
    if (!value) return '';
    const birthDate = new Date(value);
    if (Number.isNaN(birthDate.getTime())) return '';

    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    const dayDiff = today.getDate() - birthDate.getDate();

    if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
        age -= 1;
    }

    return age >= 0 ? String(age) : '';
};

const syncAgeFromDate = () => {
    ageInput.value = calculateAgeFromDate(form.dateNaissance);
};

const onDateNaissanceInput = (value) => {
    form.dateNaissance = value || '';
    syncAgeFromDate();
};

const onAgeInput = (value) => {
    const normalized = String(value ?? '').replace(/\D/g, '').slice(0, 3);
    ageInput.value = normalized;

    if (!normalized) {
        form.dateNaissance = '';
        return;
    }

    const age = Number(normalized);
    if (!Number.isFinite(age)) {
        return;
    }

    const currentYear = new Date().getFullYear();
    const year = Math.max(1900, currentYear - age);
    form.dateNaissance = `${year}-01-01`;
};

const resetForm = () => {
    form.nom = '';
    form.prenom = '';
    form.telephone = '';
    form.email = '';
    form.adresse = '';
    form.profession = '';
    form.lieuNaissance = '';
    form.sexe = '';
    form.dateNaissance = '';
    form.referencement = '';
    ageInput.value = '';
    form.groupeSanguin = '';
    form.notes = '';
    form.contactUrgence.nom = '';
    form.contactUrgence.telephone = '';
    form.contactUrgence.lienParente = '';
    form.smsPreferences.patientCreated = false;
    form.smsPreferences.receipt = false;
    form.smsPreferences.ticket = false;
    form.smsPreferences.invoice = false;
    form.smsPreferences.appointmentReminder = true;
    form.smsPreferences.unsubscribed = false;
    form.smsPreferences.blacklisted = false;
    form.insuranceProfile.enabled = false;
    form.insuranceProfile.assuranceCode = '';
    form.insuranceProfile.assuranceId = null;
    form.insuranceProfile.coverageRate = 0;
    form.insuranceProfile.formData = defaultInsuranceFormData();
};

watch(
    () => props.patient,
    (val) => {
        if (val) {
            form.nom = val.nom ?? '';
            form.prenom = val.prenom ?? '';
            form.telephone = val.telephone ?? '';
            form.email = val.email ?? '';
            form.adresse = val.adresse ?? '';
            form.profession = val.profession ?? '';
            form.lieuNaissance = val.lieuNaissance ?? val.lieu_naissance ?? '';
            form.sexe = val.sexe ?? '';
            form.dateNaissance = formatDateInput(val.dateNaissance ?? val.date_naissance ?? '');
            form.referencement = val.referencement ?? '';
            syncAgeFromDate();
            form.groupeSanguin = val.groupeSanguin ?? val.groupe_sanguin ?? '';
            form.notes = val.notes ?? '';
            const contactUrgence = val.contactUrgence ?? val.contact_urgence ?? {};
            form.contactUrgence.nom = contactUrgence.nom ?? '';
            form.contactUrgence.telephone = contactUrgence.telephone ?? '';
            form.contactUrgence.lienParente = contactUrgence.lienParente ?? '';
            const smsPreferences = val.smsPreferences ?? {};
            form.smsPreferences.patientCreated = Boolean(smsPreferences.patientCreated ?? false);
            form.smsPreferences.receipt = Boolean(smsPreferences.receipt ?? false);
            form.smsPreferences.ticket = Boolean(smsPreferences.ticket ?? false);
            form.smsPreferences.invoice = Boolean(smsPreferences.invoice ?? false);
            form.smsPreferences.appointmentReminder = Boolean(smsPreferences.appointmentReminder ?? false);
            form.smsPreferences.unsubscribed = Boolean(smsPreferences.unsubscribed ?? false);
            form.smsPreferences.blacklisted = Boolean(smsPreferences.blacklisted ?? false);

            const insuranceProfile = resolveInsuranceProfile(val);
            if (insuranceProfile) {
                form.insuranceProfile.enabled = insuranceProfile.enabled;
                form.insuranceProfile.assuranceCode = insuranceProfile.assuranceCode;
                form.insuranceProfile.assuranceId = insuranceProfile.assuranceId;
                form.insuranceProfile.coverageRate = insuranceProfile.coverageRate;
                form.insuranceProfile.formData = insuranceProfile.formData;
            } else {
                form.insuranceProfile.enabled = false;
                form.insuranceProfile.assuranceCode = '';
                form.insuranceProfile.assuranceId = null;
                form.insuranceProfile.coverageRate = 0;
                form.insuranceProfile.formData = defaultInsuranceFormData();
            }
        } else {
            resetForm();
        }
    },
    { immediate: true }
);

watch(
    () => form.insuranceProfile.assuranceCode,
    (code) => {
        const insurance = (assurances.value || []).find((item) => (item?.code || '') === code) || null;
        form.insuranceProfile.assuranceId = insurance?.id ?? null;
        if (!insurance) {
            return;
        }
    }
);

watch(
    () => [form.nom, form.prenom, form.sexe, ageInput.value, form.insuranceProfile.enabled, form.insuranceProfile.assuranceCode],
    () => {
        if (!form.insuranceProfile.enabled) {
            return;
        }

        const fullName = `${form.nom || ''} ${form.prenom || ''}`.trim();
        if (isSbn.value) {
            form.insuranceProfile.formData.assureNom = fullName;
            form.insuranceProfile.formData.sexe = form.sexe || '';
        }

        if (isBleues.value) {
            form.insuranceProfile.formData.patientNomPrenom = fullName;
            form.insuranceProfile.formData.patientAge = ageInput.value || '';
            form.insuranceProfile.formData.patientSexe = form.sexe || '';
        }

        if (isSunu.value) {
            form.insuranceProfile.formData.titulaireNomPrenoms = fullName;
        }

        if (isLafia.value) {
            form.insuranceProfile.formData.assureNomPrenom = fullName;
        }

        if (isSaham.value) {
            form.insuranceProfile.formData.beneficiaireNomPrenoms = fullName;
        }

        if (isMsh.value) {
            form.insuranceProfile.formData.nomPrenoms = fullName;
        }
    },
    { immediate: true }
);

const savePatient = async () => {
    loading.value = true;
    try {
        const contactUrgence = {
            nom: form.contactUrgence.nom?.trim() || '',
            telephone: form.contactUrgence.telephone?.trim() || '',
            lienParente: form.contactUrgence.lienParente?.trim() || ''
        };
        const hasContactUrgence = Object.values(contactUrgence).some((value) => Boolean(value));
        const payload = {
            nom: form.nom,
            prenom: form.prenom,
            telephone: form.telephone,
            email: form.email,
            adresse: form.adresse,
            profession: form.profession,
            lieuNaissance: form.lieuNaissance,
            sexe: form.sexe,
            dateNaissance: form.dateNaissance || null,
            referencement: form.referencement || '',
            groupeSanguin: form.groupeSanguin,
            notes: form.notes,
            contactUrgence: hasContactUrgence ? contactUrgence : null,
            smsPreferences: {
                patientCreated: form.smsPreferences.patientCreated,
                receipt: form.smsPreferences.receipt,
                ticket: form.smsPreferences.ticket,
                invoice: form.smsPreferences.invoice,
                appointmentReminder: form.smsPreferences.appointmentReminder,
                unsubscribed: form.smsPreferences.unsubscribed,
                blacklisted: form.smsPreferences.blacklisted
            },
            insuranceProfile: hasActiveInsurances.value ? {
                enabled: Boolean(form.insuranceProfile.enabled && form.insuranceProfile.assuranceCode),
                assuranceCode: form.insuranceProfile.assuranceCode || null,
                assuranceId: form.insuranceProfile.assuranceId,
                coverageRate: Number(form.insuranceProfile.coverageRate || 0),
                formData: { ...form.insuranceProfile.formData }
            } : null
        };
        const saved = isEdit.value && props.patient?.id
            ? await updatePatient(props.patient.id, payload, token)
            : await createPatient(payload, token);
        toast.add({ severity: 'success', summary: 'Succès', detail: 'Patient sauvegardé.', life: 2500 });
        emit('saved', saved);
        if (!isEdit.value) {
            resetForm();
        }
    } catch (error) {
        console.error('Erreur lors de la sauvegarde du patient', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de sauvegarder le patient.', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const handleSubmit = (event) => {
    confirmPopup.require({
        target: event.currentTarget || event.target,
        message: isEdit.value ? 'Confirmer la mise à jour du patient ?' : 'Confirmer la création du patient ?',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: savePatient
    });
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <ConfirmPopup />
        <Tabs :value="activeTab" @update:value="activeTab = $event">
            <TabList class="flex flex-wrap gap-2 border-b border-surface-200 dark:border-surface-700">
                <Tab value="personal">Informations personnelles</Tab>
                <Tab value="sms">Paramètres SMS</Tab>
                <Tab v-if="hasActiveInsurances" value="insurance">Informations assurances</Tab>
            </TabList>
            <TabPanels class="mt-4">
                <TabPanel value="personal">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label for="nom" class="font-semibold"><span class="text-red-500">*</span> Nom</label>
                            <InputText id="nom" v-model="form.nom" placeholder="Nom" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="prenom" class="font-semibold">Prénom</label>
                            <InputText id="prenom" v-model="form.prenom" placeholder="Prénom" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="telephone" class="font-semibold"><span class="text-red-500">*</span> Téléphone</label>
                            <InputText id="telephone" v-model="form.telephone" placeholder="Téléphone" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="adresse" class="font-semibold">Adresse</label>
                            <InputText id="adresse" v-model="form.adresse" placeholder="Adresse" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="lieu-naissance" class="font-semibold">Lieu de naissance</label>
                            <InputText id="lieu-naissance" v-model="form.lieuNaissance" placeholder="Lieu de naissance" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="profession" class="font-semibold">Profession</label>
                            <InputText id="profession" v-model="form.profession" placeholder="Profession" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="sexe" class="font-semibold">Sexe</label>
                            <Select id="sexe" v-model="form.sexe" :options="sexes" optionLabel="label" optionValue="value" placeholder="Choisir" class="w-full" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="date-naissance" class="font-semibold">Date de naissance</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <InputText id="date-naissance" :modelValue="form.dateNaissance" type="date" placeholder="Date de naissance" @update:modelValue="onDateNaissanceInput" />
                                <InputText id="age-patient" :modelValue="ageInput" type="number" min="0" max="130" placeholder="Âge" @update:modelValue="onAgeInput" />
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="referencement" class="font-semibold">Comment a-t-il connu le cabinet ?</label>
                            <Select id="referencement" v-model="form.referencement" :options="referralSources" optionLabel="label" optionValue="value" placeholder="Choisir" class="w-full" />
                        </div>
                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label class="font-semibold">Contact d'urgence</label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <InputText id="urgence-nom" v-model="form.contactUrgence.nom" placeholder="Nom" />
                                <InputText id="urgence-telephone" v-model="form.contactUrgence.telephone" placeholder="Téléphone" />
                                <InputText id="urgence-lien" v-model="form.contactUrgence.lienParente" placeholder="Lien de parenté" />
                            </div>
                        </div>
                    </div>
                </TabPanel>

                <TabPanel value="sms">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 rounded-xl border border-surface-200 p-4 dark:border-surface-700">
                        <div class="md:col-span-2">
                            <p class="text-sm text-surface-600 dark:text-surface-400">
                                Définissez les SMS autorisés pour ce patient et les exclusions d'envoi.
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <Checkbox inputId="sms-patient-created" v-model="form.smsPreferences.patientCreated" binary />
                            <label for="sms-patient-created">Créer un SMS après création</label>
                        </div>
                        <div class="flex items-center gap-3">
                            <Checkbox inputId="sms-receipt" v-model="form.smsPreferences.receipt" binary />
                            <label for="sms-receipt">Envoyer les reçus</label>
                        </div>
                        <div class="flex items-center gap-3">
                            <Checkbox inputId="sms-ticket" v-model="form.smsPreferences.ticket" binary />
                            <label for="sms-ticket">Envoyer les tickets</label>
                        </div>
                        <div class="flex items-center gap-3">
                            <Checkbox inputId="sms-invoice" v-model="form.smsPreferences.invoice" binary />
                            <label for="sms-invoice">Envoyer les factures</label>
                        </div>
                        <div class="flex items-center gap-3">
                            <Checkbox inputId="sms-appointment-reminder" v-model="form.smsPreferences.appointmentReminder" binary />
                            <label for="sms-appointment-reminder">Autoriser les rappels de rendez-vous</label>
                        </div>
                        <div class="flex items-center gap-3">
                            <Checkbox inputId="sms-unsubscribed" v-model="form.smsPreferences.unsubscribed" binary />
                            <label for="sms-unsubscribed">Patient désabonné</label>
                        </div>
                        <div class="flex items-center gap-3 md:col-span-2">
                            <Checkbox inputId="sms-blacklisted" v-model="form.smsPreferences.blacklisted" binary />
                            <label for="sms-blacklisted">Numéro blacklisté</label>
                        </div>
                    </div>
                </TabPanel>

                <TabPanel v-if="hasActiveInsurances" value="insurance">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 rounded-xl border border-surface-200 p-4 dark:border-surface-700">
                        <div class="md:col-span-2 flex items-center gap-3">
                            <Checkbox inputId="patient-insurance-enabled" v-model="form.insuranceProfile.enabled" binary />
                            <label for="patient-insurance-enabled" class="font-semibold">Patient assuré</label>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="font-semibold">Assurance</label>
                            <Select v-model="form.insuranceProfile.assuranceCode" :options="insuranceOptions" optionLabel="label" optionValue="value" placeholder="Choisir une assurance" class="w-full" :disabled="!form.insuranceProfile.enabled" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="font-semibold">Taux de couverture (%)</label>
                            <InputNumber v-model="form.insuranceProfile.coverageRate" mode="decimal" :min="0" :max="100" :minFractionDigits="0" :maxFractionDigits="2" class="w-full" inputClass="w-full" :disabled="!form.insuranceProfile.enabled" />
                        </div>

                        <template v-if="form.insuranceProfile.enabled && isSbn">
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Société</label>
                                <InputText v-model="form.insuranceProfile.formData.societe" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Nom de l'assuré</label>
                                <InputText v-model="form.insuranceProfile.formData.assureNom" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">N° de l'assuré</label>
                                <InputText v-model="form.insuranceProfile.formData.assureNumero" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Nom du bénéficiaire</label>
                                <InputText v-model="form.insuranceProfile.formData.beneficiaireNom" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">N° du bénéficiaire</label>
                                <InputText v-model="form.insuranceProfile.formData.beneficiaireNumero" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Sexe</label>
                                <InputText v-model="form.insuranceProfile.formData.sexe" />
                            </div>
                        </template>

                        <template v-if="form.insuranceProfile.enabled && isBleues">
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Souscripteur</label>
                                <InputText v-model="form.insuranceProfile.formData.souscripteur" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Salarié - Nom et prénom</label>
                                <InputText v-model="form.insuranceProfile.formData.salarieNomPrenom" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Salarié - Matricule</label>
                                <InputText v-model="form.insuranceProfile.formData.salarieMatricule" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Patient - Nom et prénom</label>
                                <InputText v-model="form.insuranceProfile.formData.patientNomPrenom" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Patient - Matricule</label>
                                <InputText v-model="form.insuranceProfile.formData.patientMatricule" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Patient - Age</label>
                                <InputText v-model="form.insuranceProfile.formData.patientAge" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Patient - Sexe</label>
                                <InputText v-model="form.insuranceProfile.formData.patientSexe" />
                            </div>
                        </template>

                        <template v-if="form.insuranceProfile.enabled && isSunu">
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Carte N°</label>
                                <InputText v-model="form.insuranceProfile.formData.carteNumero" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Société</label>
                                <InputText v-model="form.insuranceProfile.formData.societe" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">N° police</label>
                                <InputText v-model="form.insuranceProfile.formData.numeroPolice" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Titulaire - Nom et prénoms</label>
                                <InputText v-model="form.insuranceProfile.formData.titulaireNomPrenoms" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Assuré principal - Nom et prénoms</label>
                                <InputText v-model="form.insuranceProfile.formData.assurePrincipalNom" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Assuré principal - N° tel</label>
                                <InputText v-model="form.insuranceProfile.formData.assurePrincipalTel" />
                            </div>
                        </template>

                        <template v-if="form.insuranceProfile.enabled && isLafia">
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Police</label>
                                <InputText v-model="form.insuranceProfile.formData.numeroPolice" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Avenant</label>
                                <InputText v-model="form.insuranceProfile.formData.avenant" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">N° assuré</label>
                                <InputText v-model="form.insuranceProfile.formData.numeroAssure" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Souscripteur</label>
                                <InputText v-model="form.insuranceProfile.formData.souscripteur" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Nom et prénom</label>
                                <InputText v-model="form.insuranceProfile.formData.assureNomPrenom" />
                            </div>
                        </template>

                        <template v-if="form.insuranceProfile.enabled && isSaham">
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Assuré - Nom et prénoms</label>
                                <InputText v-model="form.insuranceProfile.formData.assureNomPrenoms" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Assuré - N°</label>
                                <InputText v-model="form.insuranceProfile.formData.assureNumero" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Bénéficiaire - Nom et prénoms</label>
                                <InputText v-model="form.insuranceProfile.formData.beneficiaireNomPrenoms" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Bénéficiaire - Matricule</label>
                                <InputText v-model="form.insuranceProfile.formData.beneficiaireMatricule" />
                            </div>
                        </template>

                        <template v-if="form.insuranceProfile.enabled && isMsh">
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Identifiant</label>
                                <InputText v-model="form.insuranceProfile.formData.identifiant" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-semibold">Nom et prénoms</label>
                                <InputText v-model="form.insuranceProfile.formData.nomPrenoms" />
                            </div>
                        </template>

                        <div v-if="form.insuranceProfile.enabled && selectedInsurance?.logoPath" class="md:col-span-2 text-sm text-gray-500">
                            Logo assurance: {{ selectedInsurance.logoPath }}
                        </div>
                    </div>
                </TabPanel>
            </TabPanels>
        </Tabs>
        <div class="flex gap-2 justify-end">
            <Button type="button" label="Annuler" severity="secondary" @click="emit('cancel')" />
            <Button type="button" :label="isEdit ? 'Mettre à jour' : 'Créer'" icon="pi pi-check" :loading="loading"
                @click="handleSubmit" />
        </div>
    </div>
</template>

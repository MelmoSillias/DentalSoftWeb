<script setup>
import { computed, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Calendar from 'primevue/calendar';
import MultiSelect from 'primevue/multiselect';
import FileUpload from 'primevue/fileupload';
import Divider from 'primevue/divider';
import Button from 'primevue/button';

const props = defineProps({
    visible: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    employee: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    tourTarget: { type: String, default: null }
});

const emit = defineEmits(['update:visible', 'submit', 'cancel']);

const typeOptions = [
    { label: 'Médecin', value: 'Medecin' },
    { label: 'Réceptionniste', value: 'Receptionniste' },
    { label: 'Admin', value: 'Admin' },
    { label: 'Autre', value: 'Autre' }
];

const typeContratOptions = [
    { label: 'CDI', value: 'CDI' },
    { label: 'CDD', value: 'CDD' },
    { label: 'Stage', value: 'Stage' },
    { label: 'Prestataire', value: 'Prestataire' }
];

const baseTypeSalaireOptions = [
    { label: 'Non défini', value: 'non_defini' },
    { label: 'Fixe', value: 'fixe' },
    { label: 'Pourcentage', value: 'pourcentage' }
];

const daysOptions = [
    { label: 'Lundi', value: 'Lundi' },
    { label: 'Mardi', value: 'Mardi' },
    { label: 'Mercredi', value: 'Mercredi' },
    { label: 'Jeudi', value: 'Jeudi' },
    { label: 'Vendredi', value: 'Vendredi' },
    { label: 'Samedi', value: 'Samedi' }
];

const form = ref({
    nom: '',
    prenom: '',
    telephone: '',
    email: '',
    fonction: '',
    type: 'Medecin',
    dateEmbauche: null,
    typeContrat: 'CDI',
    dureeContrat: null,
    typeSalaire: 'fixe',
    valeurSalaire: 100000,
    comingDays: []
});

const files = ref([]);

const dialogTitle = computed(() => (props.mode === 'edit' ? 'Modifier un employé' : 'Ajouter un employé'));

const hydrateForm = (employee) => {
    form.value = {
        nom: employee?.nom || '',
        prenom: employee?.prenom || '',
        telephone: employee?.telephone || '',
        email: employee?.email || employee?.mail || '',
        fonction: employee?.fonction || employee?.poste || '',
        type: employee?.type || 'Medecin',
        dateEmbauche: employee?.dateEmbauche ? new Date(employee.dateEmbauche) : null,
        typeContrat: (employee?.typeContrat === 'Freelance' ? 'Prestataire' : employee?.typeContrat) || 'CDI',
        dureeContrat: employee?.dureeContrat ?? null,
        typeSalaire: employee?.typeSalaire || 'fixe',
        valeurSalaire: employee?.valeurSalaire ?? null,
        comingDays: Array.isArray(employee?.comingDays)
            ? employee.comingDays.filter((day) => day !== 'Dimanche')
            : []
    };
    files.value = [];
};

const isMedecin = computed(() => form.value.type === 'Medecin');
const typeSalaireOptions = computed(() => {
    if (isMedecin.value) return baseTypeSalaireOptions;
    return baseTypeSalaireOptions.filter((option) => option.value !== 'pourcentage');
});

const isSalaireDisabled = computed(() => form.value.typeSalaire === 'non_defini');
const salaryMax = computed(() => (form.value.typeSalaire === 'pourcentage' ? 100 : null));
const salarySuffix = computed(() => (form.value.typeSalaire === 'pourcentage' ? '%' : 'F CFA'));

watch(
    () => form.value.type,
    (value) => {
        if (value !== 'Medecin' && form.value.typeSalaire === 'pourcentage') {
            form.value.typeSalaire = 'fixe';
        }
    }
);

watch(
    () => form.value.typeSalaire,
    (value) => {
        if (value === 'non_defini') {
            form.value.valeurSalaire = null;
            return;
        }

        if (value === 'pourcentage') {
            if (form.value.valeurSalaire === null || form.value.valeurSalaire === '') {
                form.value.valeurSalaire = 35;
            }
            if (form.value.valeurSalaire > 100) {
                form.value.valeurSalaire = 100;
            }
            return;
        }

        if (form.value.valeurSalaire === null || form.value.valeurSalaire === '') {
            form.value.valeurSalaire = 100000;
        }
    }
);

watch(
    () => form.value.typeContrat,
    (value) => {
        if (value === 'CDI') {
            form.value.dureeContrat = null;
            return;
        }

        if (!form.value.dureeContrat) {
            form.value.dureeContrat = 3;
        }
    }
);

watch(
    () => props.employee,
    (value) => hydrateForm(value),
    { immediate: true }
);

watch(
    () => props.visible,
    (visible) => {
        if (visible && props.mode === 'create') {
            hydrateForm({});
        }
    }
);

const onFilesSelect = (event) => {
    files.value = event.files || [];
};

const onFilesClear = () => {
    files.value = [];
};

const buildFormData = () => {
    const formData = new FormData();
    Object.entries(form.value).forEach(([key, value]) => {
        if (key === 'comingDays') {
            (value || []).forEach((day) => formData.append('comingDays[]', day));
            return;
        }
        if (value === null || value === undefined || value === '') return;
        if (value instanceof Date) {
            formData.append(key, value.toISOString().substring(0, 10));
            return;
        }
        formData.append(key, value);
    });

    files.value.forEach((fileItem) => {
        const file = fileItem?.file || fileItem;
        if (file) formData.append('administrativeFiles[]', file);
    });

    return formData;
};

const submitForm = (event) => {
    emit('submit', { formData: buildFormData(), mode: props.mode, id: props.employee?.id }, event);
};

const closeDialog = () => {
    emit('update:visible', false);
    emit('cancel');
};
</script>

<template>
    <Dialog :visible="visible" modal :style="{ width: '70vw', maxWidth: '960px' }" :header="dialogTitle"
        @update:visible="emit('update:visible', $event)">
        <div class="space-y-5" :data-tour="props.tourTarget || null">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-sm font-medium">Nom</label>
                    <InputText v-model="form.nom" placeholder="Nom" class="w-full" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Prénom</label>
                    <InputText v-model="form.prenom" placeholder="Prénom" class="w-full" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Téléphone</label>
                    <InputText v-model="form.telephone" placeholder="Téléphone" class="w-full" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Email</label>
                    <InputText v-model="form.email" placeholder="Email" class="w-full" />
                </div>
            </div>

            <Divider />

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="space-y-1 md:col-span-2">
                    <label class="text-sm font-medium">Fonction</label>
                    <InputText v-model="form.fonction" placeholder="Fonction" class="w-full" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Type</label>
                    <Select v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value"
                        class="w-full" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Date d'embauche</label>
                    <Calendar v-model="form.dateEmbauche" class="w-full" dateFormat="yy-mm-dd" showIcon
                        placeholder="YYYY-MM-DD" />
                </div>
            </div>

            <Divider />

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="space-y-1">
                    <label class="text-sm font-medium">Type de contrat</label>
                    <Select v-model="form.typeContrat" :options="typeContratOptions" optionLabel="label"
                        optionValue="value" class="w-full" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Durée du contrat (mois)</label>
                    <InputNumber v-model="form.dureeContrat" class="w-full" :min="1" :disabled="form.typeContrat === 'CDI'" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Type de salaire</label>
                    <Select v-model="form.typeSalaire" :options="typeSalaireOptions" optionLabel="label"
                        optionValue="value" class="w-full" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Valeur du salaire</label>
                    <InputNumber
                        v-model="form.valeurSalaire"
                        class="w-full"
                        :min="0"
                        :max="salaryMax"
                        :step="0.01"
                        :suffix="salarySuffix"
                        :disabled="isSalaireDisabled"
                    />
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-sm font-medium">Jours travaillés</label>
                <MultiSelect v-model="form.comingDays" :options="daysOptions" optionLabel="label"
                    optionValue="value" display="chip" class="w-full" placeholder="Sélectionner les jours" />
            </div>

            <Divider />

            <div class="space-y-1">
                <label class="text-sm font-medium">Documents administratifs</label>
                <FileUpload name="administrativeFiles[]" :multiple="true" :customUpload="true" :auto="false"
                    @select="onFilesSelect" @clear="onFilesClear" chooseLabel="Choisir" uploadLabel="Ajouter"
                    cancelLabel="Vider" />
            </div>
        </div>

        <template #footer>
            <div class="flex items-center justify-end gap-2">
                <Button label="Annuler" severity="secondary" text @click="closeDialog" />
                <Button label="Enregistrer" icon="pi pi-check" :loading="loading" @click="submitForm" />
            </div>
        </template>
    </Dialog>
</template>

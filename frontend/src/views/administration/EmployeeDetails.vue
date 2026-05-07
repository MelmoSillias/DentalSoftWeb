<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Breadcrumb from 'primevue/breadcrumb';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import ConfirmPopup from 'primevue/confirmpopup';
import Divider from 'primevue/divider';
import FileUpload from 'primevue/fileupload';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { filePrefix } from '@/config';
import { useEmployeeDetails } from '@/composables/useEmployeeDetails';
import { GUIDED_TOUR_START_EVENT } from '@/tours';
import { createAdministrationEmployeeDetailsTour } from '@/tours/administrationEmployeeDetailsTour';
import { startTourGuide } from '@/tours/tourGuideClient';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirm = useConfirm();

const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
const breadcrumbItems = [
    { label: 'Administration' },
    { label: 'Gestion RH', to: '/administration/gestionrh' },
    { label: 'Détails employé' }
];

const baseTypeSalaireOptions = [
    { label: 'Non défini', value: 'non_defini' },
    { label: 'Fixe', value: 'fixe' },
    { label: 'Pourcentage', value: 'pourcentage' }
];

const typeContratOptions = [
    { label: 'CDI', value: 'CDI' },
    { label: 'CDD', value: 'CDD' },
    { label: 'Stage', value: 'Stage' },
    { label: 'Prestataire', value: 'Prestataire' }
];

const daysOptions = [
    { label: 'Lundi', value: 'Lundi' },
    { label: 'Mardi', value: 'Mardi' },
    { label: 'Mercredi', value: 'Mercredi' },
    { label: 'Jeudi', value: 'Jeudi' },
    { label: 'Vendredi', value: 'Vendredi' },
    { label: 'Samedi', value: 'Samedi' }
];

const { employee, loading, error, fetchEmployee, updateEmployee } = useEmployeeDetails();

const files = ref([]);
const isGuidedTourStarting = ref(false);

const form = ref({
    nom: '',
    prenom: '',
    matricule: '',
    fonction: '',
    type: '',
    telephone: '',
    email: '',
    dateEmbauche: null,
    typeSalaire: 'fixe',
    valeurSalaire: 100000,
    typeContrat: 'CDI',
    dureeContrat: null,
    comingDays: []
});

const employeeId = computed(() => {
    const raw = Number(route.params.id);
    return Number.isNaN(raw) ? null : raw;
});

const fileBaseUrl = computed(() => filePrefix.replace(/\/$/, ''));

const hydrateForm = (data) => {
    form.value = {
        nom: data?.nom || '',
        prenom: data?.prenom || '',
        matricule: data?.matricule || '',
        fonction: data?.fonction || '',
        type: data?.type || '',
        telephone: data?.telephone || '',
        email: data?.email || '',
        dateEmbauche: data?.dateEmbauche ? new Date(data.dateEmbauche) : null,
        typeSalaire: data?.typeSalaire || 'fixe',
        valeurSalaire: data?.valeurSalaire ?? null,
        typeContrat: (data?.typeContrat === 'Freelance' ? 'Prestataire' : data?.typeContrat) || 'CDI',
        dureeContrat: data?.dureeContrat ?? null,
        comingDays: Array.isArray(data?.comingDays)
            ? data.comingDays.filter((day) => day !== 'Dimanche')
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

const loadEmployee = async () => {
    if (!employeeId.value) return;
    await fetchEmployee(employeeId.value);
    if (error.value) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: error.value, life: 4000 });
    }
};

const goBack = () => router.push({ name: 'administration-gestionrh' });

const resolveFileUrl = (filePath) => {
    if (!filePath) return '#';
    if (/^https?:\/\//i.test(filePath)) return filePath;
    return `${fileBaseUrl.value}${filePath.startsWith('/') ? '' : '/'}${filePath}`;
};

const formatDate = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('fr-FR');
};

const formatCurrency = (value) => {
    if (value === null || value === undefined) return '-';
    return `${new Intl.NumberFormat('fr-FR').format(value)} F CFA`;
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

const confirmSave = (event) => {
    confirm.require({
        target: event?.currentTarget,
        message: 'Confirmer la mise a jour des informations ?',
        icon: 'pi pi-check',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await updateEmployee(employeeId.value, buildFormData());
                toast.add({ severity: 'success', summary: 'Succès', detail: 'Employe mis a jour.', life: 3000 });
                await loadEmployee();
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Mise a jour impossible.', life: 4000 });
            }
        }
    });
};

const handleGuidedTourRequest = async (event) => {
    if (event?.detail?.routeName !== 'administration-employee-details' || isGuidedTourStarting.value) {
        return;
    }

    if (loading.value) {
        toast.add({
            severity: 'warn',
            summary: 'Aide guidee',
            detail: 'Attendez la fin du chargement de la fiche employe avant de lancer le tour.',
            life: 3000
        });
        return;
    }

    isGuidedTourStarting.value = true;

    try {
        await startTourGuide({
            group: 'administration-employee-details',
            steps: createAdministrationEmployeeDetailsTour()
        });
    } catch (error) {
        console.error('Erreur lancement guided tour details employe', error);
        toast.add({
            severity: 'error',
            summary: 'Aide guidee',
            detail: 'Impossible de lancer le tour de la fiche employe.',
            life: 3000
        });
    } finally {
        isGuidedTourStarting.value = false;
    }
};

const onFilesSelect = (event) => {
    files.value = event.files || [];
};

const onFilesClear = () => {
    files.value = [];
};

const congesByYear = computed(() => {
    const rows = Array.isArray(employee.value?.conges) ? employee.value.conges : [];
    const map = new Map();

    rows.forEach((conge) => {
        const year = conge?.startDate ? new Date(conge.startDate).getFullYear() : 'Inconnu';
        if (!map.has(year)) {
            map.set(year, []);
        }
        map.get(year).push(conge);
    });

    return Array.from(map.entries())
        .sort((a, b) => Number(b[0]) - Number(a[0]))
        .map(([year, items]) => {
            const totalDays = items.reduce((total, item) => {
                const start = item?.startDate ? new Date(item.startDate) : null;
                const end = item?.endDate ? new Date(item.endDate) : null;
                if (!start || !end || Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return total;
                const diff = Math.floor((end - start) / (1000 * 60 * 60 * 24)) + 1;
                return total + (diff > 0 ? diff : 0);
            }, 0);
            return { year, items, totalDays };
        });
});

const salaryPayments = computed(() => {
    const rows = Array.isArray(employee.value?.salaryPayments) ? [...employee.value.salaryPayments] : [];

    return rows.sort((left, right) => {
        const leftDate = String(left?.paidAt || '');
        const rightDate = String(right?.paidAt || '');
        if (leftDate === rightDate) {
            return Number(right?.id || 0) - Number(left?.id || 0);
        }
        return rightDate.localeCompare(leftDate);
    });
});

const monthYearLabel = (month, year) => {
    if (!month || !year) return '-';
    const date = new Date(Number(year), Number(month) - 1, 1);
    return Number.isNaN(date.getTime())
        ? `${month}/${year}`
        : date.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
};

const congeSeverity = (type) => {
    const map = {
        vacances: 'success',
        teletravail: 'info',
        arret: 'danger',
        deplacement: 'warning'
    };
    return map[String(type || '').toLowerCase()] || 'secondary';
};

const salaireCard = computed(() => {
    const typeSalaire = employee.value?.typeSalaire;
    const valeurSalaire = employee.value?.valeurSalaire ?? null;
    const salaireCalcule = employee.value?.salaireCalcule ?? null;

    if (typeSalaire === 'non_defini') {
        return {
            title: 'Salaire actuel',
            value: 'Non défini',
            sub: null
        };
    }

    if (typeSalaire === 'pourcentage' && employee.value?.type === 'Medecin') {
        return {
            title: 'Salaire actuel',
            value: formatCurrency(salaireCalcule),
            sub: `${valeurSalaire ?? 0}%`
        };
    }

    return {
        title: 'Salaire actuel',
        value: formatCurrency(valeurSalaire ?? salaireCalcule),
        sub: null
    };
});

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

watch(employee, (value) => {
    if (value) hydrateForm(value);
});

watch(employeeId, () => {
    loadEmployee();
});

onMounted(() => {
    loadEmployee();
    window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
});

onBeforeUnmount(() => {
    window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
});
</script>

<template>
    <section class="min-h-screen p-4 md:p-6 lg:p-8 transition-colors duration-300">
        <AppToast />
        <ConfirmPopup />

        <div data-tour="admin-employee-details.header" class="mb-6 md:mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                            <i class="pi pi-id-card text-primary-600 dark:text-primary-400 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold text-surface-900 dark:text-surface-50 tracking-tight">
                                Détails employé
                            </h1>
                            <p class="text-surface-600 dark:text-surface-300 text-sm md:text-base">
                                Consultez et mettez a jour les informations RH.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button icon="pi pi-arrow-left" severity="secondary" outlined label="Retour" @click="goBack" />
                    <Button
                        icon="pi pi-save"
                        label="Enregistrer"
                        class="shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white px-6 py-3 rounded-xl font-medium"
                        :loading="loading"
                        @click="confirmSave"
                    />
                </div>
            </div>

            <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl p-4 shadow-sm border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
                <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="text-sm" />
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div data-tour="admin-employee-details.personal" class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                            <i class="pi pi-user text-primary-500"></i>
                            Informations personnelles
                        </h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Nom</label>
                                <InputText v-model="form.nom" class="w-full" placeholder="Nom" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Prenom</label>
                                <InputText v-model="form.prenom" class="w-full" placeholder="Prenom" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Matricule</label>
                                <InputText v-model="form.matricule" class="w-full" readonly />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Fonction</label>
                                <InputText v-model="form.fonction" class="w-full" placeholder="Fonction" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Type</label>
                                <InputText v-model="form.type" class="w-full" readonly />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Date d'embauche</label>
                                <Calendar v-model="form.dateEmbauche" class="w-full" dateFormat="yy-mm-dd" showIcon readonlyInput />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Telephone</label>
                                <InputText v-model="form.telephone" class="w-full" placeholder="Telephone" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Email</label>
                                <InputText v-model="form.email" class="w-full" placeholder="Email" />
                            </div>
                        </div>
                    </div>
                </div>

                <div data-tour="admin-employee-details.rh" class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                            <i class="pi pi-briefcase text-primary-500"></i>
                            Informations RH
                        </h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Type de salaire</label>
                                <Select
                                    v-model="form.typeSalaire"
                                    :options="typeSalaireOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    class="w-full"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Valeur du salaire (F CFA ou %)</label>
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
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Type de contrat</label>
                                <Select
                                    v-model="form.typeContrat"
                                    :options="typeContratOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    class="w-full"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Duree de contrat (mois)</label>
                                <InputNumber v-model="form.dureeContrat" class="w-full" :min="1" :disabled="form.typeContrat === 'CDI'" />
                            </div>
                        </div>

                        <Divider />

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Jours travailles</label>
                            <SelectButton
                                v-model="form.comingDays"
                                :options="daysOptions"
                                optionLabel="label"
                                optionValue="value"
                                multiple
                                class="w-full flex flex-wrap gap-2"
                            />
                        </div>
                    </div>
                </div>

                <div data-tour="admin-employee-details.documents" class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                            <i class="pi pi-file text-primary-500"></i>
                            Documents administratifs
                        </h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Ajouter des fichiers</label>
                            <FileUpload
                                name="administrativeFiles[]"
                                :multiple="true"
                                :customUpload="true"
                                :auto="false"
                                @select="onFilesSelect"
                                @clear="onFilesClear"
                                chooseLabel="Choisir"
                                uploadLabel="Ajouter"
                                cancelLabel="Vider"
                            />
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-surface-800 dark:text-surface-200 mb-2">
                                Fichiers existants
                            </h4>
                            <div v-if="(employee?.administrativeFiles || []).length" class="flex flex-wrap gap-2">
                                <Button
                                    v-for="file in employee.administrativeFiles"
                                    :key="file"
                                    icon="pi pi-download"
                                    severity="secondary"
                                    outlined
                                    size="small"
                                    :label="file.split('/').pop()"
                                    @click="() => window.open(resolveFileUrl(file), '_blank')"
                                />
                            </div>
                            <div v-else class="text-sm text-surface-500 dark:text-surface-400">
                                Aucun fichier administratif.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6"> 
                <div data-tour="admin-employee-details.summary">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20 rounded-2xl p-5 border border-blue-200/50 dark:border-blue-800/50">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-blue-700 dark:text-blue-300 font-medium">Employe</p>
                                <p class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-2">
                                    {{ employee?.fullname || employee?.nom || '-' }}
                                </p>
                            </div>
                            <i class="pi pi-user text-2xl text-blue-500"></i>
                        </div>
                    </div>

                    <div class="bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:from-emerald-900/20 dark:to-emerald-800/20 rounded-2xl p-5 border border-emerald-200/50 dark:border-emerald-800/50 mt-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-emerald-700 dark:text-emerald-300 font-medium">Type</p>
                                <p class="text-2xl font-bold text-emerald-900 dark:text-emerald-100 mt-2">
                                    {{ employee?.type || '-' }}
                                </p>
                            </div>
                            <i class="pi pi-briefcase text-2xl text-emerald-500"></i>
                        </div>
                    </div>

                    <div class="bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 rounded-2xl p-5 border border-amber-200/50 dark:border-amber-800/50 mt-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-amber-700 dark:text-amber-300 font-medium">{{ salaireCard.title }}</p>
                                <p class="text-2xl font-bold text-amber-900 dark:text-amber-100 mt-2">
                                    {{ salaireCard.value }}
                                </p>
                                <p v-if="salaireCard.sub" class="text-xs text-amber-600 dark:text-amber-300 mt-1">
                                    {{ salaireCard.sub }}
                                </p>
                            </div>
                            <i class="pi pi-wallet text-2xl text-amber-500"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                            <i class="pi pi-calendar text-primary-500"></i>
                            Historique des paiements
                        </h3>
                    </div>
                    <div class="p-4 md:p-5">
                        <div v-if="salaryPayments.length" class="overflow-x-auto">
                            <table class="min-w-full text-sm border-separate border-spacing-0">
                                <thead>
                                    <tr class="text-left bg-surface-50 dark:bg-surface-900/50">
                                        <th class="px-3 py-2 border-b border-surface-200 dark:border-surface-700">Période</th>
                                        <th class="px-3 py-2 border-b border-surface-200 dark:border-surface-700">Montant calculé</th>
                                        <th class="px-3 py-2 border-b border-surface-200 dark:border-surface-700">Montant versé</th>
                                        <th class="px-3 py-2 border-b border-surface-200 dark:border-surface-700">Date</th>
                                        <th class="px-3 py-2 border-b border-surface-200 dark:border-surface-700">Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="payment in salaryPayments"
                                        :key="payment.id"
                                        class="odd:bg-surface-0 even:bg-surface-50/50 dark:odd:bg-surface-800 dark:even:bg-surface-900/40"
                                    >
                                        <td class="px-3 py-2 border-b border-surface-100 dark:border-surface-800">
                                            {{ monthYearLabel(payment.month, payment.year) }}
                                        </td>
                                        <td class="px-3 py-2 border-b border-surface-100 dark:border-surface-800">
                                            {{ formatCurrency(payment.calculatedAmount) }}
                                        </td>
                                        <td class="px-3 py-2 border-b border-surface-100 dark:border-surface-800 font-semibold text-primary-700 dark:text-primary-300">
                                            {{ formatCurrency(payment.paidAmount) }}
                                        </td>
                                        <td class="px-3 py-2 border-b border-surface-100 dark:border-surface-800">
                                            {{ formatDate(payment.paidAt) }}
                                        </td>
                                        <td class="px-3 py-2 border-b border-surface-100 dark:border-surface-800">
                                            {{ payment.note || '-' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun paiement enregistré pour cet employé.</div>
                    </div>
                </div>

                <div data-tour="admin-employee-details.conges" class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                            <i class="pi pi-calendar text-primary-500"></i>
                            Conges par annee
                        </h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div v-if="congesByYear.length" class="space-y-6">
                            <div v-for="group in congesByYear" :key="group.year" class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="text-sm font-semibold text-surface-900 dark:text-surface-100">
                                        {{ group.year }}
                                    </div>
                                    <div class="text-xs text-surface-500 dark:text-surface-400">
                                        {{ group.totalDays }} jour(s)
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div
                                        v-for="conge in group.items"
                                        :key="conge.id"
                                        class="flex items-center justify-between gap-3 rounded-xl border border-surface-200/50 dark:border-surface-700/50 p-3"
                                    >
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <Tag :value="conge.type" :severity="congeSeverity(conge.type)" />
                                                <span class="text-sm font-medium text-surface-800 dark:text-surface-200">
                                                    {{ formatDate(conge.startDate) }} - {{ formatDate(conge.endDate) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-xs text-surface-500 dark:text-surface-400">
                                            {{ conge.startDate && conge.endDate
                                                ? `${Math.max(1, Math.floor((new Date(conge.endDate) - new Date(conge.startDate)) / (1000 * 60 * 60 * 24)) + 1)} jour(s)`
                                                : '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-sm text-surface-500 dark:text-surface-400">
                            Aucun conge enregistre pour cet employe.
                        </div>
                    </div>
                </div>

                
            </div>
        </div>
    </section>
</template>

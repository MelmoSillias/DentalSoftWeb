<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageShell from '@/components/layout/PageShell.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import PageSection from '@/components/layout/PageSection.vue';
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
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { filePrefix } from '@/config';
import { useEmployeeDetails } from '@/composables/useEmployeeDetails';
import { useGuidedTour } from '@/composables/useGuidedTour';
import { formatEmployeeTypeLabel } from '@/utils/employeeTypeUtils';
import EmployeeSalarySection from '@/components/administration/EmployeeSalarySection.vue';
import StatsCardsGrid from '@/components/rapport/common/StatsCardsGrid.vue';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirm = useConfirm();

const breadcrumbHome = { icon: 'pi pi-home', to: '/dashboard' };
const breadcrumbItems = [{ label: 'Administration' }, { label: 'Gestion RH', to: '/administration/gestionrh' }, { label: 'Détails employé' }];

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
const activeTab = ref('personal');
const rhSection = ref(null);
const rhSectionOptions = [
    { label: 'Salaire', value: 'salary' },
    { label: 'Contrat', value: 'contract' },
    { label: 'Planning', value: 'schedule' }
];
const paymentYear = ref(null);
const paymentMonth = ref(null);
const paymentFrequency = ref(null);
const paymentSearch = ref('');
const paymentFrequencyOptions = [
    { label: 'Mensuel', value: 'mensuel' },
    { label: 'Journalier', value: 'journalier' }
];
const paymentMonthOptions = Array.from({ length: 12 }, (_, index) => ({
    label: new Date(2000, index, 1).toLocaleDateString('fr-FR', { month: 'long' }),
    value: index + 1
}));
const resetPaymentFilters = () => {
    paymentYear.value = null;
    paymentMonth.value = null;
    paymentFrequency.value = null;
    paymentSearch.value = '';
};
const tabs = computed(() => [
    { id: 'personal', label: 'Informations personnelles', icon: 'pi pi-user' },
    { id: 'rh', label: 'RH & contrat', icon: 'pi pi-briefcase' },
    { id: 'documents', label: 'Documents', icon: 'pi pi-folder-open', badge: employee.value?.administrativeFiles?.length },
    { id: 'payments', label: 'Paiements', icon: 'pi pi-wallet', badge: salaryPayments.value.length },
    { id: 'conges', label: 'Congés', icon: 'pi pi-calendar', badge: employee.value?.conges?.length }
]);

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
    frequencePaiement: 'mensuel',
    typePrime: 'aucune',
    valeurPrime: null,
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
        frequencePaiement: data?.frequencePaiement || 'mensuel',
        typePrime: data?.typePrime || 'aucune',
        valeurPrime: data?.valeurPrime ?? null,
        typeContrat: (data?.typeContrat === 'Freelance' ? 'Prestataire' : data?.typeContrat) || 'CDI',
        dureeContrat: data?.dureeContrat ?? null,
        comingDays: Array.isArray(data?.comingDays) ? data.comingDays.filter((day) => day !== 'Dimanche') : []
    };
    files.value = [];
};

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
            const days = value || [];
            if (days.length === 0) formData.append('comingDays[]', '');
            else days.forEach((day) => formData.append('comingDays[]', day));
            return;
        }
        if (key === 'fonction') {
            formData.append(key, value ?? '');
            return;
        }
        if (value instanceof Date) {
            formData.append(key, value.toISOString().substring(0, 10));
            return;
        }
        // Les champs vides font partie de la mise à jour et doivent effacer
        // les valeurs précédentes côté serveur.
        formData.append(key, value === null || value === undefined ? '' : value);
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

useGuidedTour({
    routeName: 'administration-employee-details',
    getStepContext: () => ({
        switchEmployeeTab: async (tab) => {
            activeTab.value = tab;
            if (tab === 'rh') rhSection.value = null;
            await nextTick();
        }
    }),
    isLoading: () => loading.value,
    loadingMessage: 'Attendez la fin du chargement de la fiche employe avant de lancer le tour.',
    errorMessage: 'Impossible de lancer le tour de la fiche employe.'
});

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

const paymentYearOptions = computed(() => [...new Set(salaryPayments.value.map((payment) => Number(payment.year)).filter(Boolean))].sort((a, b) => b - a));
const filteredPayments = computed(() => {
    const search = paymentSearch.value.trim().toLocaleLowerCase('fr');
    return salaryPayments.value.filter((payment) => {
        if (paymentYear.value && Number(payment.year) !== paymentYear.value) return false;
        if (paymentMonth.value && Number(payment.month) !== paymentMonth.value) return false;
        if (paymentFrequency.value && payment.frequenceSnapshot !== paymentFrequency.value) return false;
        return (
            !search ||
            [payment.note, payment.paymentMethod?.libelle, paymentPeriodLabel(payment)].some((value) =>
                String(value || '')
                    .toLocaleLowerCase('fr')
                    .includes(search)
            )
        );
    });
});
const paymentKpis = computed(() => {
    const rows = filteredPayments.value;
    const total = rows.reduce((sum, payment) => sum + Number(payment.paidAmount || 0), 0);
    return [
        { label: 'Total versé', value: formatCurrency(total), icon: 'pi pi-wallet', tone: 'emerald' },
        { label: 'Versements', value: rows.length, icon: 'pi pi-list', tone: 'blue' },
        { label: 'Versement moyen', value: rows.length ? formatCurrency(total / rows.length) : '—', icon: 'pi pi-chart-bar', tone: 'violet' },
        { label: 'Dernier versement', value: rows.length ? formatDate(rows[0].paidAt) : '—', icon: 'pi pi-calendar', tone: 'orange' }
    ];
});
const contractKpis = computed(() => [
    { label: 'Type de contrat', value: employee.value?.typeContrat === 'Freelance' ? 'Prestataire' : employee.value?.typeContrat || '—', icon: 'pi pi-file', tone: 'blue' },
    { label: 'Durée du contrat', value: employee.value?.typeContrat === 'CDI' ? 'Indéterminée' : employee.value?.dureeContrat ? `${employee.value.dureeContrat} mois` : 'Non renseignée', icon: 'pi pi-clock', tone: 'violet' },
    { label: "Date d'embauche", value: formatDate(employee.value?.dateEmbauche), icon: 'pi pi-calendar', tone: 'emerald' },
    { label: 'Jours travaillés / semaine', value: employee.value ? (employee.value.comingDays || []).filter((day) => day !== 'Dimanche').length : '—', icon: 'pi pi-briefcase', tone: 'orange' }
]);

const monthYearLabel = (month, year) => {
    if (!month || !year) return '-';
    const date = new Date(Number(year), Number(month) - 1, 1);
    return Number.isNaN(date.getTime()) ? `${month}/${year}` : date.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
};

const paymentPeriodLabel = (payment) => {
    if (payment?.workedDay) {
        return new Date(payment.workedDay).toLocaleDateString('fr-FR');
    }
    return monthYearLabel(payment?.month, payment?.year);
};

const frequenceLabel = (value) => (value === 'journalier' ? 'Journalier' : 'Mensuel');

const primeTypeLabel = (value) => {
    const map = { aucune: 'Aucune', fixe: 'Fixe', actes: '% actes' };
    return map[value] || value || '-';
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
    const frequence = employee.value?.frequencePaiement === 'journalier' ? 'Journalier' : 'Mensuel';
    const prime = employee.value?.typePrime && employee.value.typePrime !== 'aucune' ? primeTypeLabel(employee.value.typePrime) : null;

    if (typeSalaire === 'non_defini') {
        return {
            title: 'Salaire actuel',
            value: 'Non défini',
            sub: `${frequence}${prime ? ` · Prime ${prime}` : ''}`
        };
    }

    if (typeSalaire === 'pourcentage' && employee.value?.type === 'Medecin') {
        return {
            title: 'Salaire actuel',
            value: formatCurrency(salaireCalcule),
            sub: `${valeurSalaire ?? 0}% · ${frequence}${prime ? ` · Prime ${prime}` : ''}`
        };
    }

    return {
        title: 'Salaire actuel',
        value: formatCurrency(valeurSalaire ?? salaireCalcule),
        sub: `${frequence}${prime ? ` · Prime ${prime}` : ''}`
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
    activeTab.value = 'personal';
    rhSection.value = null;
    resetPaymentFilters();
    loadEmployee();
});

onMounted(() => {
    loadEmployee();
});
</script>

<template>
    <PageShell>
        <ConfirmPopup />

        <template #header>
            <PageHeader title="Détails employé" subtitle="Consultez et mettez a jour les informations RH." icon="pi pi-id-card" tour-id="admin-employee-details.header" :breadcrumb-items="breadcrumbItems" :breadcrumb-home="breadcrumbHome">
                <template #actions>
                    <Button icon="pi pi-arrow-left" severity="secondary" outlined label="Retour" @click="goBack" />
                    <Button icon="pi pi-save" label="Enregistrer" :loading="loading" @click="confirmSave" />
                </template>
            </PageHeader>
        </template>

        <div class="dossier-folder">
            <div class="dossier-folder__spine">
                <div class="dossier-folder__spine-main">
                    <h2 class="dossier-folder__spine-name">{{ [employee?.nom, employee?.prenom].filter(Boolean).join(' ') || 'Fiche employé' }}</h2>
                    <p class="dossier-folder__spine-meta">{{ [employee?.matricule, formatEmployeeTypeLabel(employee?.type)].filter(Boolean).join(' · ') }}</p>
                </div>
            </div>

            <Tabs v-model:value="activeTab" class="dossier-folder__tabs">
                <TabList data-tour="admin-employee-details.tabs">
                    <Tab v-for="tab in tabs" :key="tab.id" :value="tab.id" :aria-label="tab.label" v-tooltip.top="tab.label">
                        <span class="dossier-folder__tab-label">
                            <i :class="tab.icon" aria-hidden="true"></i>
                            <span class="hidden sm:inline">{{ tab.label }}</span>
                            <span v-if="tab.badge" class="dossier-folder__tab-badge">{{ tab.badge }}</span>
                        </span>
                    </Tab>
                </TabList>
                <TabPanels>
                    <TabPanel value="personal">
                        <div class="dossier-folder__panel">
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                <div class="lg:col-span-2 min-w-0">
                                    <PageSection title="Informations personnelles" tour-id="admin-employee-details.personal" padded>
                                        <div class="space-y-4">
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
                                                    <label class="text-sm font-medium">Description de fonction</label>
                                                    <InputText v-model="form.fonction" class="w-full" placeholder="Description de fonction (optionnel)" />
                                                </div>
                                                <div class="space-y-1">
                                                    <label class="text-sm font-medium">Type de poste</label>
                                                    <InputText :modelValue="formatEmployeeTypeLabel(form.type)" class="w-full" readonly />
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
                                    </PageSection>
                                </div>
                                <div class="min-w-0">
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
                                                        {{ formatEmployeeTypeLabel(employee?.type) || '-' }}
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
                                </div>
                            </div>
                        </div>
                    </TabPanel>
                    <TabPanel value="rh">
                        <div class="dossier-folder__panel space-y-5">
                            <StatsCardsGrid title="Synthèse du contrat" subtitle="Informations enregistrées de cet employé" :items="contractKpis" :loading="loading" />
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="space-y-1 w-full sm:w-72">
                                    <label for="employee-rh-section" class="text-sm font-medium">Informations à afficher</label>
                                    <Select v-model="rhSection" inputId="employee-rh-section" :options="rhSectionOptions" optionLabel="label" optionValue="value" placeholder="Toutes les informations" showClear class="w-full" />
                                </div>
                                <Button label="Réinitialiser" icon="pi pi-filter-slash" severity="secondary" outlined :disabled="!rhSection" @click="rhSection = null" />
                            </div>
                            <PageSection title="Informations RH" tour-id="admin-employee-details.rh" padded>
                                <div class="space-y-5">
                                    <EmployeeSalarySection v-show="!rhSection || rhSection === 'salary'" v-model:form="form" :employee-type="form.type" />

                                    <Divider v-if="!rhSection" />

                                    <div v-show="!rhSection || rhSection === 'contract'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="space-y-1">
                                            <label class="text-sm font-medium">Type de contrat</label>
                                            <Select v-model="form.typeContrat" :options="typeContratOptions" optionLabel="label" optionValue="value" class="w-full" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-sm font-medium">Duree de contrat (mois)</label>
                                            <InputNumber v-model="form.dureeContrat" class="w-full" :min="1" :disabled="form.typeContrat === 'CDI'" />
                                        </div>
                                    </div>

                                    <Divider v-if="!rhSection" />

                                    <div v-show="!rhSection || rhSection === 'schedule'" class="space-y-2">
                                        <label class="text-sm font-medium">Jours travailles</label>
                                        <SelectButton v-model="form.comingDays" :options="daysOptions" optionLabel="label" optionValue="value" multiple class="w-full flex flex-wrap gap-2" />
                                    </div>
                                </div>
                            </PageSection>
                        </div>
                    </TabPanel>
                    <TabPanel value="documents">
                        <div class="dossier-folder__panel">
                            <PageSection title="Documents administratifs" tour-id="admin-employee-details.documents" padded>
                                <div class="space-y-4">
                                    <div class="space-y-2">
                                        <FileUpload name="administrativeFiles[]" :multiple="true" :customUpload="true" :auto="false" @select="onFilesSelect" @clear="onFilesClear" chooseLabel="Choisir" uploadLabel="Ajouter" cancelLabel="Vider" />
                                    </div>

                                    <div>
                                        <h4 class="text-sm font-semibold text-surface-800 dark:text-surface-200 mb-2">Fichiers existants</h4>
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
                                        <div v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun fichier administratif.</div>
                                    </div>
                                </div>
                            </PageSection>
                        </div>
                    </TabPanel>
                    <TabPanel value="payments">
                        <div class="dossier-folder__panel space-y-5">
                            <StatsCardsGrid title="Synthèse des salaires versés" subtitle="Indicateurs calculés sur les versements correspondant aux filtres" :items="paymentKpis" :loading="loading" />
                            <PageSection title="Filtres des paiements" padded>
                                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                                    <div class="space-y-1">
                                        <label for="employee-payment-year" class="text-sm font-medium">Année de salaire</label>
                                        <Select v-model="paymentYear" inputId="employee-payment-year" :options="paymentYearOptions" placeholder="Toutes les années" showClear class="w-full" />
                                    </div>
                                    <div class="space-y-1">
                                        <label for="employee-payment-month" class="text-sm font-medium">Mois de salaire</label>
                                        <Select v-model="paymentMonth" inputId="employee-payment-month" :options="paymentMonthOptions" optionLabel="label" optionValue="value" placeholder="Tous les mois" showClear class="w-full" />
                                    </div>
                                    <div class="space-y-1">
                                        <label for="employee-payment-frequency" class="text-sm font-medium">Fréquence</label>
                                        <Select v-model="paymentFrequency" inputId="employee-payment-frequency" :options="paymentFrequencyOptions" optionLabel="label" optionValue="value" placeholder="Toutes les fréquences" showClear class="w-full" />
                                    </div>
                                    <div class="space-y-1">
                                        <label for="employee-payment-search" class="text-sm font-medium">Recherche</label>
                                        <InputText id="employee-payment-search" v-model="paymentSearch" placeholder="Note, période, mode de paiement" class="w-full" />
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                                    <span class="text-sm text-surface-500" role="status">{{ filteredPayments.length }} versement(s) sur {{ salaryPayments.length }}</span>
                                    <Button label="Réinitialiser" icon="pi pi-filter-slash" severity="secondary" outlined :disabled="!paymentYear && !paymentMonth && !paymentFrequency && !paymentSearch" @click="resetPaymentFilters" />
                                </div>
                            </PageSection>
                            <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden">
                                <div class="px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                                    <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                                        <i class="pi pi-calendar text-primary-500"></i>
                                        Historique des paiements
                                    </h3>
                                </div>
                                <div class="p-4 md:p-5">
                                    <div v-if="filteredPayments.length" class="overflow-x-auto">
                                        <table class="min-w-full text-sm border-separate border-spacing-0">
                                            <thead>
                                                <tr class="text-left bg-surface-50 dark:bg-surface-900/50">
                                                    <th class="px-3 py-2 border-b border-surface-200 dark:border-surface-700">Période</th>
                                                    <th class="px-3 py-2 border-b border-surface-200 dark:border-surface-700">Fréquence</th>
                                                    <th class="px-3 py-2 border-b border-surface-200 dark:border-surface-700">Salaire base</th>
                                                    <th class="px-3 py-2 border-b border-surface-200 dark:border-surface-700">Prime</th>
                                                    <th class="px-3 py-2 border-b border-surface-200 dark:border-surface-700">Total calculé</th>
                                                    <th class="px-3 py-2 border-b border-surface-200 dark:border-surface-700">Versé</th>
                                                    <th class="px-3 py-2 border-b border-surface-200 dark:border-surface-700">Date</th>
                                                    <th class="px-3 py-2 border-b border-surface-200 dark:border-surface-700">Note</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="payment in filteredPayments" :key="payment.id" class="odd:bg-surface-0 even:bg-surface-50/50 dark:odd:bg-surface-800 dark:even:bg-surface-900/40">
                                                    <td class="px-3 py-2 border-b border-surface-100 dark:border-surface-800">
                                                        {{ paymentPeriodLabel(payment) }}
                                                    </td>
                                                    <td class="px-3 py-2 border-b border-surface-100 dark:border-surface-800">
                                                        {{ frequenceLabel(payment.frequenceSnapshot) }}
                                                    </td>
                                                    <td class="px-3 py-2 border-b border-surface-100 dark:border-surface-800">
                                                        {{ formatCurrency(payment.baseSalaryAmount) }}
                                                    </td>
                                                    <td class="px-3 py-2 border-b border-surface-100 dark:border-surface-800">
                                                        {{ formatCurrency(payment.primeAmount) }}
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
                                    <div v-else class="text-sm text-surface-500 dark:text-surface-400">{{ salaryPayments.length ? 'Aucun versement ne correspond aux filtres sélectionnés.' : 'Aucun paiement enregistré pour cet employé.' }}</div>
                                </div>
                            </div>
                        </div>
                    </TabPanel>
                    <TabPanel value="conges">
                        <div class="dossier-folder__panel">
                            <PageSection title="Conges par annee" tour-id="admin-employee-details.conges" padded>
                                <div class="space-y-4">
                                    <div v-if="congesByYear.length" class="space-y-6">
                                        <div v-for="group in congesByYear" :key="group.year" class="space-y-3">
                                            <div class="flex items-center justify-between">
                                                <div class="text-sm font-semibold text-surface-900 dark:text-surface-100">
                                                    {{ group.year }}
                                                </div>
                                                <div class="text-xs text-surface-500 dark:text-surface-400">{{ group.totalDays }} jour(s)</div>
                                            </div>
                                            <div class="space-y-2">
                                                <div v-for="conge in group.items" :key="conge.id" class="flex items-center justify-between gap-3 rounded-xl border border-surface-200/50 dark:border-surface-700/50 p-3">
                                                    <div>
                                                        <div class="flex items-center gap-2">
                                                            <Tag :value="conge.type" :severity="congeSeverity(conge.type)" />
                                                            <span class="text-sm font-medium text-surface-800 dark:text-surface-200"> {{ formatDate(conge.startDate) }} - {{ formatDate(conge.endDate) }} </span>
                                                        </div>
                                                    </div>
                                                    <div class="text-xs text-surface-500 dark:text-surface-400">
                                                        {{ conge.startDate && conge.endDate ? `${Math.max(1, Math.floor((new Date(conge.endDate) - new Date(conge.startDate)) / (1000 * 60 * 60 * 24)) + 1)} jour(s)` : '-' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun conge enregistre pour cet employe.</div>
                                </div>
                            </PageSection>
                        </div>
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div>
    </PageShell>
</template>

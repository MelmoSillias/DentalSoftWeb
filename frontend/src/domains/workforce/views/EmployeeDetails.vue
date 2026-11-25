<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageShell from '@/app/components/layout/PageShell.vue';
import PageHeader from '@/app/components/layout/PageHeader.vue';
import PageSection from '@/app/components/layout/PageSection.vue';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import ConfirmPopup from 'primevue/confirmpopup';
import FileUpload from 'primevue/fileupload';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { filePrefix } from '@/config.js';
import { useEmployeeDetails } from '@/domains/workforce/composables/useEmployeeDetails.js';
import { useGuidedTour } from '@/shared/composables/useGuidedTour.js';
import { formatEmployeeTypeLabel } from '@/domains/workforce/model/employeeTypeUtils.js';
import EmployeeSalarySection from '@/domains/workforce/components/EmployeeSalarySection.vue';

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
const documentUpload = ref(null);
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
    documentUpload.value?.clear();
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
        { label: 'Total versé', value: formatCurrency(total), icon: 'pi pi-wallet' },
        { label: 'Versements', value: rows.length, icon: 'pi pi-list' },
        { label: 'Versement moyen', value: rows.length ? formatCurrency(total / rows.length) : '—', icon: 'pi pi-chart-bar' },
        { label: 'Dernier versement', value: rows.length ? formatDate(rows[0].paidAt) : '—', icon: 'pi pi-calendar' }
    ];
});
const contractKpis = computed(() => [
    { label: 'Type de contrat', value: employee.value?.typeContrat === 'Freelance' ? 'Prestataire' : employee.value?.typeContrat || '—', icon: 'pi pi-file' },
    { label: 'Durée du contrat', value: employee.value?.typeContrat === 'CDI' ? 'Indéterminée' : employee.value?.dureeContrat ? `${employee.value.dureeContrat} mois` : 'Non renseignée', icon: 'pi pi-clock' },
    { label: "Date d'embauche", value: formatDate(employee.value?.dateEmbauche), icon: 'pi pi-calendar' },
    { label: 'Jours travaillés / semaine', value: employee.value ? (employee.value.comingDays || []).filter((day) => day !== 'Dimanche').length : '—', icon: 'pi pi-briefcase' }
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

const salarySummary = computed(() => {
    const typeSalaire = employee.value?.typeSalaire;
    const valeurSalaire = employee.value?.valeurSalaire ?? null;
    const salaireCalcule = employee.value?.salaireCalcule ?? null;
    const frequence = employee.value?.frequencePaiement === 'journalier' ? 'Journalier' : 'Mensuel';
    const prime = employee.value?.typePrime && employee.value.typePrime !== 'aucune' ? primeTypeLabel(employee.value.typePrime) : null;

    if (typeSalaire === 'non_defini') {
        return {
            value: 'Non défini',
            sub: `${frequence}${prime ? ` · Prime ${prime}` : ''}`
        };
    }

    if (typeSalaire === 'pourcentage' && employee.value?.type === 'Medecin') {
        return {
            value: formatCurrency(salaireCalcule),
            sub: `${valeurSalaire ?? 0}% · ${frequence}${prime ? ` · Prime ${prime}` : ''}`
        };
    }

    return {
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
            <PageHeader title="Détails employé" subtitle="Consultez et mettez à jour les informations RH." icon="pi pi-id-card" tour-id="admin-employee-details.header" :breadcrumb-items="breadcrumbItems" :breadcrumb-home="breadcrumbHome">
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

            <Tabs v-model:value="activeTab" class="dossier-folder__tabs" scrollable>
                <TabList data-tour="admin-employee-details.tabs">
                    <Tab v-for="tab in tabs" :key="tab.id" :value="tab.id">
                        <span class="dossier-folder__tab-label">
                            <i :class="tab.icon" aria-hidden="true"></i>
                            <span>{{ tab.label }}</span>
                            <span v-if="tab.badge" class="dossier-folder__tab-badge">{{ tab.badge }}</span>
                        </span>
                    </Tab>
                </TabList>
                <TabPanels>
                    <TabPanel value="personal" class="grid grid-cols-1 gap-6 lg:grid-cols-[2fr_1fr]">
                        <PageSection title="Informations personnelles" tour-id="admin-employee-details.personal" class="employee-section" plain>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="space-y-1">
                                    <label for="employee-last-name" class="text-sm font-medium">Nom</label>
                                    <InputText id="employee-last-name" v-model="form.nom" class="w-full" />
                                </div>
                                <div class="space-y-1">
                                    <label for="employee-first-name" class="text-sm font-medium">Prénom</label>
                                    <InputText id="employee-first-name" v-model="form.prenom" class="w-full" />
                                </div>
                                <div class="space-y-1">
                                    <label for="employee-phone" class="text-sm font-medium">Téléphone</label>
                                    <InputText id="employee-phone" v-model="form.telephone" type="tel" class="w-full" />
                                </div>
                                <div class="space-y-1">
                                    <label for="employee-email" class="text-sm font-medium">Adresse e-mail</label>
                                    <InputText id="employee-email" v-model="form.email" class="w-full" />
                                </div>
                                <div class="space-y-1">
                                    <label for="employee-job" class="text-sm font-medium">Description de fonction</label>
                                    <InputText id="employee-job" v-model="form.fonction" class="w-full" placeholder="Facultatif" />
                                </div>
                                <div class="space-y-1">
                                    <label for="employee-hired-at" class="text-sm font-medium">Date d'embauche</label>
                                    <Calendar v-model="form.dateEmbauche" inputId="employee-hired-at" class="w-full" dateFormat="yy-mm-dd" showIcon readonlyInput />
                                </div>
                            </div>
                        </PageSection>
                        <PageSection title="Synthèse du profil" tour-id="admin-employee-details.summary" class="employee-section employee-profile-summary" plain>
                            <dl class="employee-metrics grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-1">
                                <div>
                                    <dt><i class="pi pi-id-card" aria-hidden="true"></i> Matricule</dt>
                                    <dd>{{ employee?.matricule || '—' }}</dd>
                                </div>
                                <div>
                                    <dt><i class="pi pi-briefcase" aria-hidden="true"></i> Type de poste</dt>
                                    <dd>{{ formatEmployeeTypeLabel(employee?.type) || '—' }}</dd>
                                </div>
                                <div>
                                    <dt><i class="pi pi-wallet" aria-hidden="true"></i> Salaire actuel</dt>
                                    <dd>{{ salarySummary.value }}</dd>
                                    <dd v-if="employee" class="employee-metrics__note">{{ salarySummary.sub }}</dd>
                                </div>
                            </dl>
                        </PageSection>
                    </TabPanel>
                    <TabPanel value="rh" class="space-y-6">
                        <PageSection title="Synthèse du contrat" subtitle="Informations enregistrées pour cet employé" class="employee-section" :aria-busy="loading" plain>
                            <dl class="employee-metrics employee-metrics--row">
                                <div v-for="item in contractKpis" :key="item.label">
                                    <dt><i :class="item.icon" aria-hidden="true"></i> {{ item.label }}</dt>
                                    <dd>
                                        <Skeleton v-if="loading" width="5rem" /><template v-else>{{ item.value }}</template>
                                    </dd>
                                </div>
                            </dl>
                        </PageSection>
                        <PageSection title="Informations RH" tour-id="admin-employee-details.rh" class="employee-section employee-section--separated" plain>
                            <div class="flex flex-wrap items-end gap-2 mb-5">
                                <div class="w-full space-y-1 sm:w-64">
                                    <label for="employee-rh-section" class="text-sm font-medium">Informations à afficher</label>
                                    <Select v-model="rhSection" inputId="employee-rh-section" :options="rhSectionOptions" optionLabel="label" optionValue="value" placeholder="Toutes les informations" showClear class="w-full" />
                                </div>
                                <Button label="Réinitialiser" icon="pi pi-filter-slash" severity="secondary" text :disabled="!rhSection" @click="rhSection = null" />
                            </div>
                            <div class="space-y-5">
                                <section v-show="!rhSection || rhSection === 'salary'" class="space-y-3">
                                    <h4 class="employee-subtitle">Rémunération</h4>
                                    <EmployeeSalarySection v-model:form="form" :employee-type="form.type" flat />
                                </section>
                                <section v-show="!rhSection || rhSection === 'contract'" class="space-y-3" :class="{ 'employee-section--separated': !rhSection }">
                                    <h4 class="employee-subtitle">Contrat</h4>
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="space-y-1">
                                            <label for="employee-contract-type" class="text-sm font-medium">Type de contrat</label>
                                            <Select v-model="form.typeContrat" inputId="employee-contract-type" :options="typeContratOptions" optionLabel="label" optionValue="value" class="w-full" />
                                        </div>
                                        <div class="space-y-1">
                                            <label for="employee-contract-duration" class="text-sm font-medium">Durée du contrat (mois)</label>
                                            <InputNumber v-model="form.dureeContrat" inputId="employee-contract-duration" class="w-full" :min="1" :disabled="form.typeContrat === 'CDI'" />
                                        </div>
                                    </div>
                                </section>
                                <section v-show="!rhSection || rhSection === 'schedule'" class="space-y-3" :class="{ 'employee-section--separated': !rhSection }">
                                    <h4 id="employee-workdays" class="employee-subtitle">Jours travaillés</h4>
                                    <SelectButton v-model="form.comingDays" :options="daysOptions" optionLabel="label" optionValue="value" aria-labelledby="employee-workdays" multiple class="flex flex-wrap gap-2" />
                                </section>
                            </div>
                        </PageSection>
                    </TabPanel>
                    <TabPanel value="documents">
                        <PageSection title="Documents administratifs" tour-id="admin-employee-details.documents" class="employee-section" plain>
                            <FileUpload
                                ref="documentUpload"
                                name="administrativeFiles[]"
                                multiple
                                customUpload
                                :showUploadButton="false"
                                chooseLabel="Choisir des fichiers"
                                cancelLabel="Vider la sélection"
                                :chooseButtonProps="{ size: 'small' }"
                                :cancelButtonProps="{ size: 'small', severity: 'secondary', variant: 'text' }"
                                :pt="{
                                    root: { class: '!border-0 !bg-transparent' },
                                    header: { class: '!p-0 flex-wrap' },
                                    content: { class: '!gap-0 !p-0' },
                                    file: { class: '!px-0 !rounded-none' },
                                    fileName: { class: 'break-all' },
                                    pcProgressbar: { root: { class: 'hidden' } }
                                }"
                                @select="onFilesSelect"
                                @remove="onFilesSelect"
                                @clear="onFilesClear"
                            >
                                <template #empty>
                                    <p class="employee-muted mt-3 text-sm">Vous pouvez aussi glisser-déposer les fichiers ici.</p>
                                </template>
                            </FileUpload>
                            <p class="employee-muted mt-2 text-sm">Pour ajouter les fichiers sélectionnés, cliquez sur « Enregistrer » en haut de la fiche.</p>

                            <section class="employee-section--separated mt-6">
                                <h4 class="employee-subtitle">Documents enregistrés</h4>
                                <ul v-if="employee?.administrativeFiles?.length" class="employee-list">
                                    <li v-for="file in employee.administrativeFiles" :key="file">
                                        <a :href="resolveFileUrl(file)" target="_blank" rel="noopener noreferrer" class="employee-document-link">
                                            <i class="pi pi-file shrink-0 text-primary" aria-hidden="true"></i>
                                            <span class="min-w-0 flex-1 break-all">{{ file.split('/').pop() }}</span>
                                            <span class="sr-only">(nouvel onglet)</span>
                                            <i class="pi pi-external-link shrink-0" aria-hidden="true"></i>
                                        </a>
                                    </li>
                                </ul>
                                <p v-else class="employee-empty"><i class="pi pi-folder-open" aria-hidden="true"></i> Aucun document administratif enregistré.</p>
                            </section>
                        </PageSection>
                    </TabPanel>
                    <TabPanel value="payments" class="space-y-6">
                        <PageSection title="Synthèse des salaires versés" subtitle="Indicateurs correspondant aux filtres actifs" class="employee-section" :aria-busy="loading" plain>
                            <dl class="employee-metrics employee-metrics--row">
                                <div v-for="item in paymentKpis" :key="item.label">
                                    <dt><i :class="item.icon" aria-hidden="true"></i> {{ item.label }}</dt>
                                    <dd>
                                        <Skeleton v-if="loading" width="5rem" /><template v-else>{{ item.value }}</template>
                                    </dd>
                                </div>
                            </dl>
                        </PageSection>
                        <PageSection title="Filtres des paiements" class="employee-section employee-section--separated" plain>
                            <template #headerActions>
                                <Button label="Réinitialiser" icon="pi pi-filter-slash" severity="secondary" text size="small" :disabled="!paymentYear && !paymentMonth && !paymentFrequency && !paymentSearch" @click="resetPaymentFilters" />
                            </template>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
                                    <InputText id="employee-payment-search" v-model="paymentSearch" placeholder="Note, période ou mode de paiement" class="w-full" />
                                </div>
                            </div>
                        </PageSection>
                        <PageSection class="employee-section employee-section--separated" :aria-busy="loading" plain>
                            <template #header>
                                <h3 id="employee-payments-history" class="page-section__title">Historique des paiements</h3>
                            </template>
                            <template #headerActions>
                                <span class="employee-muted text-sm tabular-nums" role="status">{{ filteredPayments.length }} {{ filteredPayments.length === 1 ? 'versement' : 'versements' }} sur {{ salaryPayments.length }}</span>
                            </template>
                            <div v-if="filteredPayments.length" class="employee-table-scroll" role="region" aria-labelledby="employee-payments-history" tabindex="0">
                                <table class="employee-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Période</th>
                                            <th scope="col">Fréquence</th>
                                            <th scope="col" class="employee-table__amount">Salaire de base</th>
                                            <th scope="col" class="employee-table__amount">Prime</th>
                                            <th scope="col" class="employee-table__amount">Total calculé</th>
                                            <th scope="col" class="employee-table__amount">Versé</th>
                                            <th scope="col">Date</th>
                                            <th scope="col">Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="payment in filteredPayments" :key="payment.id">
                                            <th scope="row" class="whitespace-nowrap font-medium">{{ paymentPeriodLabel(payment) }}</th>
                                            <td>{{ frequenceLabel(payment.frequenceSnapshot) }}</td>
                                            <td class="employee-table__amount">{{ formatCurrency(payment.baseSalaryAmount) }}</td>
                                            <td class="employee-table__amount">{{ formatCurrency(payment.primeAmount) }}</td>
                                            <td class="employee-table__amount">{{ formatCurrency(payment.calculatedAmount) }}</td>
                                            <td class="employee-table__amount font-semibold">{{ formatCurrency(payment.paidAmount) }}</td>
                                            <td class="whitespace-nowrap tabular-nums">{{ formatDate(payment.paidAt) }}</td>
                                            <td class="min-w-32 break-words">{{ payment.note || '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="employee-empty">
                                <i class="pi pi-wallet" aria-hidden="true"></i>
                                {{ loading ? 'Chargement des paiements…' : salaryPayments.length ? 'Aucun versement ne correspond aux filtres sélectionnés.' : 'Aucun paiement enregistré pour cet employé.' }}
                            </p>
                        </PageSection>
                    </TabPanel>
                    <TabPanel value="conges">
                        <PageSection title="Congés par année" subtitle="Historique des absences et total annuel" tour-id="admin-employee-details.conges" class="employee-section" :aria-busy="loading" plain>
                            <div v-if="congesByYear.length" class="space-y-5">
                                <section v-for="group in congesByYear" :key="group.year">
                                    <div class="employee-leave-heading">
                                        <h4 class="employee-subtitle"><i class="pi pi-calendar mr-1.5 text-primary" aria-hidden="true"></i> {{ group.year === 'Inconnu' ? 'Année non renseignée' : group.year }}</h4>
                                        <span class="employee-muted text-sm tabular-nums">{{ group.totalDays }} {{ group.totalDays === 1 ? 'jour' : 'jours' }} au total</span>
                                    </div>
                                    <ul class="employee-list">
                                        <li v-for="conge in group.items" :key="conge.id" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-3">
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                                <Tag :value="conge.type" :severity="congeSeverity(conge.type)" />
                                                <span class="text-sm font-medium tabular-nums">{{ formatDate(conge.startDate) }} – {{ formatDate(conge.endDate) }}</span>
                                            </div>
                                            <span class="employee-muted text-sm tabular-nums">
                                                {{ conge.startDate && conge.endDate ? `${Math.max(1, Math.floor((new Date(conge.endDate) - new Date(conge.startDate)) / (1000 * 60 * 60 * 24)) + 1)} jour(s)` : '—' }}
                                            </span>
                                        </li>
                                    </ul>
                                </section>
                            </div>
                            <p v-else class="employee-empty"><i class="pi pi-calendar" aria-hidden="true"></i> {{ loading ? 'Chargement des congés…' : 'Aucun congé enregistré pour cet employé.' }}</p>
                        </PageSection>
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div>
    </PageShell>
</template>

<style scoped>
.dossier-folder__tabs :deep(.p-tabpanels) {
    min-width: 0;
    padding: clamp(1rem, 2.5vw, 2rem);
}

.employee-section :deep(.page-section__header) {
    padding: 0;
    margin-bottom: 1rem;
    border: 0;
}

.employee-section :deep(.page-section__title),
.employee-section :deep(.page-section__subtitle) {
    white-space: normal;
}

.employee-section--separated,
.employee-profile-summary {
    border-top: 1px solid var(--surface-border);
    padding-top: 1rem;
}

.employee-subtitle {
    margin: 0;
    color: var(--text-color);
    font-size: 0.875rem;
    font-weight: 600;
}

.employee-metrics {
    margin: 0;
}

.employee-metrics--row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.employee-metrics dt {
    color: var(--text-color-secondary);
    font-size: 0.8125rem;
}

.employee-metrics dt i {
    margin-inline-end: 0.25rem;
    color: var(--primary-color);
}

.employee-metrics dd {
    margin: 0.3rem 0 0;
    color: var(--text-color);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
}

.employee-metrics--row dd {
    font-size: 1.125rem;
}

.employee-metrics__note,
.employee-muted {
    color: var(--text-color-secondary);
}

.employee-metrics dd.employee-metrics__note {
    color: var(--text-color-secondary);
    font-size: 0.8125rem;
    font-weight: 400;
}

.employee-list {
    list-style: none;
    margin: 0.5rem 0 0;
    padding: 0;
}

.employee-list > li + li {
    border-top: 1px solid var(--surface-border);
}

.employee-document-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0;
    color: var(--text-color);
    font-size: 0.875rem;
}

.employee-document-link:hover {
    text-decoration: underline;
}

.employee-empty {
    display: flex;
    align-items: baseline;
    gap: 0.625rem;
    margin: 0;
    padding: 0.75rem 0;
    color: var(--text-color-secondary);
    font-size: 0.875rem;
}

.employee-leave-heading {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.25rem 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--surface-border);
}

.employee-table-scroll {
    overflow-x: auto;
}

.employee-table-scroll:focus-visible,
.employee-document-link:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: -2px;
}

.employee-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    text-align: start;
}

.employee-table th,
.employee-table td {
    padding: 0.625rem 0.75rem;
    vertical-align: top;
    border-bottom: 1px solid var(--surface-border);
}

.employee-table thead th {
    background: color-mix(in srgb, var(--surface-card) 96%, var(--text-color));
    color: var(--text-color-secondary);
    font-weight: 600;
    white-space: nowrap;
}

.employee-table .employee-table__amount {
    text-align: end;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

@media (min-width: 768px) {
    .employee-metrics--row {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (min-width: 1024px) {
    .employee-profile-summary {
        border-top: 0;
        border-inline-start: 1px solid var(--surface-border);
        padding: 0 0 0 1.5rem;
    }
}
</style>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Breadcrumb from 'primevue/breadcrumb';
import DatePicker from 'primevue/datepicker';
import SelectButton from 'primevue/selectbutton';
import { useAuthStore } from '@/stores/auth';
import { useDashboards } from '@/composables/useDashboards';
import { useProfile } from '@/composables/useProfile';
import DashboardQuickStats from '@/components/dashboard/DashboardQuickStats.vue';
import DashboardCarouselSection from '@/components/dashboard/DashboardCarouselSection.vue';
import DashboardTabsPanel from '@/components/dashboard/DashboardTabsPanel.vue';
import ProfileNotificationsSection from '@/components/profile/ProfileNotificationsSection.vue';
import DoctorReportsTable from '@/components/rapport/common/DoctorReportsTable.vue';

const auth = useAuthStore();
const { cards, carousels, tabs, fetchDashboard, toIsoDate, loading } = useDashboards();
const {
    employee,
    notifications,
    unreadCount,
    loading: notificationsLoading,
    fetchProfile,
    fetchNotifications,
    markNotificationsRead,
    markAllNotificationsRead
} = useProfile();

const notificationsFilter = ref('all');

const filterMode = ref('date');
const selectedDate = ref(new Date());
const startOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
const selectedRange = ref([startOfMonth, new Date()]);

const selectedPeriod = ref('month');

const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
const breadcrumbItems = [{ label: 'Dashboard' }, { label: 'Tableau de bord' }];

const filterOptions = [
    { label: 'Date', value: 'date' },
    { label: 'Periode', value: 'range' }
];

const periodOptions = [
    { label: '7 jours', value: 'week' },
    { label: 'Mois', value: 'month' },
    { label: 'Trimestre', value: 'quarter' },
    { label: 'Annee', value: 'year' }
];

const role = computed(() => {
    const roles = auth.user?.roles || [];
    if (roles.includes('ROLE_ADMIN')) return 'admin';
    if (roles.includes('ROLE_MEDECIN')) return 'medecin';
    if (roles.includes('ROLE_RECEPTION') || roles.includes('ROLE_RECEPTIONNISTE')) return 'reception';
    return 'admin';
});

const userLabel = computed(() => {
    if (employee.value?.prenom || employee.value?.nom) {
        return `${employee.value.prenom || ''} ${employee.value.nom || ''}`.trim();
    }
    return auth.user?.username || 'Utilisateur';
});

const filterParams = computed(() => {
    if (filterMode.value === 'range') {
        const [start, end] = selectedRange.value || [];
        if (!start || !end) return {};
        return { from: toIsoDate(start), to: toIsoDate(end) };
    }
    return { date: toIsoDate(selectedDate.value) };
});

const periodLabel = computed(() => {
    if (filterMode.value === 'range') {
        const [start, end] = selectedRange.value || [];
        if (!start || !end) return 'Choisir periode';
        return `${start.toLocaleDateString('fr-FR')} - ${end.toLocaleDateString('fr-FR')}`;
    }
    return selectedDate.value?.toLocaleDateString('fr-FR') || 'Choisir date';
});

const quickActions = computed(() => {
    if (role.value === 'medecin') {
        return [
            { icon: 'pi pi-calendar', label: 'Agenda', to: '/agenda/rendez-vous' },
            { icon: 'pi pi-briefcase', label: 'Consultations', to: '/consultations/cards' },
            { icon: 'pi pi-users', label: 'Patients', to: '/patients/liste' }
        ];
    }
    if (role.value === 'reception') {
        return [
            { icon: 'pi pi-calendar', label: 'Agenda', to: '/agenda/rendez-vous' },
            { icon: 'pi pi-inbox', label: 'Caisse', to: '/caisse' },
            { icon: 'pi pi-users', label: 'Patients', to: '/patients/liste' }
        ];
    }
    return [
        { icon: 'pi pi-users', label: 'Patients', to: '/patients/liste' },
        { icon: 'pi pi-chart-bar', label: 'Rapports', to: '/rapports' },
        { icon: 'pi pi-wallet', label: 'Finances', to: '/administration/finances' }
    ];
});

const formatAmount = (value) => `${new Intl.NumberFormat('fr-FR').format(Number(value || 0))} Fcfa`;

const quickCards = computed(() => {
    if (!cards.value) return [];

    if (role.value === 'medecin') {
        return [
            {
                id: 'patients',
                title: 'Patients consultes',
                value: cards.value.patients?.new ?? 0,
                subValue: `Total: ${cards.value.patients?.total ?? 0}`,
                icon: 'pi pi-users',
                background: 'bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20',
                border: 'border-blue-200/50 dark:border-blue-800/50',
                text: 'text-blue-700 dark:text-blue-300',
                iconBg: 'bg-blue-100/50 dark:bg-blue-900/30',
                iconColor: 'text-blue-500',
                link: '/patients/liste',
                linkLabel: 'Voir les patients'
            },
            {
                id: 'pending-consults',
                title: 'Consultations en attente',
                value: cards.value.pendingConsultations?.total ?? 0,
                subValue: `Attente moyenne: ${cards.value.pendingConsultations?.avgWaitMinutes ?? 0} min`,
                icon: 'pi pi-heartbeat',
                background: 'bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20',
                border: 'border-amber-200/50 dark:border-amber-800/50',
                text: 'text-amber-700 dark:text-amber-300',
                iconBg: 'bg-amber-100/50 dark:bg-amber-900/30',
                iconColor: 'text-amber-500',
                link: '/consultations/cards',
                linkLabel: 'Voir les consultations'
            },
            {
                id: 'appointments',
                title: 'Rendez-vous',
                value: cards.value.appointments?.pending ?? 0,
                subValue: `Annules: ${cards.value.appointments?.cancelled ?? 0}`,
                icon: 'pi pi-calendar',
                background: 'bg-gradient-to-br from-green-50 to-green-100/50 dark:from-green-900/20 dark:to-green-800/20',
                border: 'border-green-200/50 dark:border-green-800/50',
                text: 'text-green-700 dark:text-green-300',
                iconBg: 'bg-green-100/50 dark:bg-green-900/30',
                iconColor: 'text-green-500',
                link: '/agenda/rendez-vous',
                linkLabel: "Ouvrir l'agenda"
            },
            {
                id: 'consultations',
                title: 'Consultations payantes',
                value: cards.value.consultations?.total ?? 0,
                subValue: `Payantes: ${cards.value.consultations?.paid ?? 0}`,
                icon: 'pi pi-briefcase',
                background: 'bg-gradient-to-br from-purple-50 to-purple-100/50 dark:from-purple-900/20 dark:to-purple-800/20',
                border: 'border-purple-200/50 dark:border-purple-800/50',
                text: 'text-purple-700 dark:text-purple-300',
                iconBg: 'bg-purple-100/50 dark:bg-purple-900/30',
                iconColor: 'text-purple-500',
                link: '/consultations/table',
                linkLabel: 'Voir les statistiques'
            },
            {
                id: 'revenue',
                title: 'Montant genere',
                value: formatAmount(cards.value.revenue?.total ?? 0),
                subValue: `Impayes: ${formatAmount(cards.value.revenue?.unpaid ?? 0)}`,
                icon: 'pi pi-euro',
                background: 'bg-gradient-to-br from-red-50 to-red-100/50 dark:from-red-900/20 dark:to-red-800/20',
                border: 'border-red-200/50 dark:border-red-800/50',
                text: 'text-red-700 dark:text-red-300',
                iconBg: 'bg-red-100/50 dark:bg-red-900/30',
                iconColor: 'text-red-500',
                link: '/caisse',
                linkLabel: 'Voir la caisse'
            }
        ];
    }

    if (role.value === 'reception') {
        return [
            {
                id: 'patients',
                title: 'Patients nouveaux',
                value: cards.value.patients?.new ?? 0,
                subValue: `Total: ${cards.value.patients?.total ?? 0}`,
                icon: 'pi pi-users',
                background: 'bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20',
                border: 'border-blue-200/50 dark:border-blue-800/50',
                text: 'text-blue-700 dark:text-blue-300',
                iconBg: 'bg-blue-100/50 dark:bg-blue-900/30',
                iconColor: 'text-blue-500',
                link: '/patients/liste',
                linkLabel: 'Voir les patients'
            },
            {
                id: 'consultations',
                title: 'Consultations',
                value: cards.value.consultations?.total ?? 0,
                subValue: `Payantes: ${cards.value.consultations?.paid ?? 0}`,
                icon: 'pi pi-briefcase',
                background: 'bg-gradient-to-br from-green-50 to-green-100/50 dark:from-green-900/20 dark:to-green-800/20',
                border: 'border-green-200/50 dark:border-green-800/50',
                text: 'text-green-700 dark:text-green-300',
                iconBg: 'bg-green-100/50 dark:bg-green-900/30',
                iconColor: 'text-green-500',
                link: '/consultations/table',
                linkLabel: 'Voir les consultations'
            },
            {
                id: 'appointments',
                title: 'Rendez-vous',
                value: cards.value.appointments?.pending ?? 0,
                subValue: `Annules: ${cards.value.appointments?.cancelled ?? 0}`,
                icon: 'pi pi-calendar',
                background: 'bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20',
                border: 'border-amber-200/50 dark:border-amber-800/50',
                text: 'text-amber-700 dark:text-amber-300',
                iconBg: 'bg-amber-100/50 dark:bg-amber-900/30',
                iconColor: 'text-amber-500',
                link: '/agenda/rendez-vous',
                linkLabel: "Ouvrir l'agenda"
            },
            {
                id: 'cash',
                title: 'Montant en caisse',
                value: formatAmount(cards.value.cash?.total ?? 0),
                subValue: `Impayes: ${formatAmount(cards.value.cash?.unpaid ?? 0)}`,
                icon: 'pi pi-wallet',
                background: 'bg-gradient-to-br from-red-50 to-red-100/50 dark:from-red-900/20 dark:to-red-800/20',
                border: 'border-red-200/50 dark:border-red-800/50',
                text: 'text-red-700 dark:text-red-300',
                iconBg: 'bg-red-100/50 dark:bg-red-900/30',
                iconColor: 'text-red-500',
                link: '/caisse',
                linkLabel: 'Voir la caisse'
            }
        ];
    }

    return [
        {
            id: 'patients',
            title: 'Patients nouveaux',
            value: cards.value.patients?.new ?? 0,
            subValue: `Total: ${cards.value.patients?.total ?? 0}`,
            icon: 'pi pi-users',
            background: 'bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20',
            border: 'border-blue-200/50 dark:border-blue-800/50',
            text: 'text-blue-700 dark:text-blue-300',
            iconBg: 'bg-blue-100/50 dark:bg-blue-900/30',
            iconColor: 'text-blue-500',
            link: '/patients/liste',
            linkLabel: 'Voir la liste complete'
        },
        {
            id: 'consultations',
            title: 'Consultations',
            value: cards.value.consultations?.total ?? 0,
            subValue: `Payantes: ${cards.value.consultations?.paid ?? 0}`,
            icon: 'pi pi-briefcase',
            background: 'bg-gradient-to-br from-green-50 to-green-100/50 dark:from-green-900/20 dark:to-green-800/20',
            border: 'border-green-200/50 dark:border-green-800/50',
            text: 'text-green-700 dark:text-green-300',
            iconBg: 'bg-green-100/50 dark:bg-green-900/30',
            iconColor: 'text-green-500',
            link: '/consultations/table',
            linkLabel: 'Voir les consultations'
        },
        {
            id: 'appointments',
            title: 'Rendez-vous',
            value: cards.value.appointments?.pending ?? 0,
            subValue: `Annules: ${cards.value.appointments?.cancelled ?? 0}`,
            icon: 'pi pi-calendar',
            background: 'bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20',
            border: 'border-amber-200/50 dark:border-amber-800/50',
            text: 'text-amber-700 dark:text-amber-300',
            iconBg: 'bg-amber-100/50 dark:bg-amber-900/30',
            iconColor: 'text-amber-500',
            link: '/agenda/rendez-vous',
            linkLabel: "Ouvrir l'agenda"
        },
        {
            id: 'cash',
            title: 'Montant en caisse',
            value: formatAmount(cards.value.cash?.total ?? 0),
            subValue: `Impayes: ${formatAmount(cards.value.cash?.unpaid ?? 0)}`,
            icon: 'pi pi-wallet',
            background: 'bg-gradient-to-br from-purple-50 to-purple-100/50 dark:from-purple-900/20 dark:to-purple-800/20',
            border: 'border-purple-200/50 dark:border-purple-800/50',
            text: 'text-purple-700 dark:text-purple-300',
            iconBg: 'bg-purple-100/50 dark:bg-purple-900/30',
            iconColor: 'text-purple-500',
            link: '/caisse',
            linkLabel: 'Voir la caisse'
        },
        {
            id: 'pending-consults',
            title: 'Consultations en attente',
            value: cards.value.pendingConsultations?.total ?? 0,
            subValue: `Attente moyenne: ${cards.value.pendingConsultations?.avgWaitMinutes ?? 0} min`,
            icon: 'pi pi-heartbeat',
            background: 'bg-gradient-to-br from-red-50 to-red-100/50 dark:from-red-900/20 dark:to-red-800/20',
            border: 'border-red-200/50 dark:border-red-800/50',
            text: 'text-red-700 dark:text-red-300',
            iconBg: 'bg-red-100/50 dark:bg-red-900/30',
            iconColor: 'text-red-500',
            link: '/consultations/cards',
            linkLabel: 'Voir les urgences'
        }
    ];
});

const buildBars = (values) => {
    const max = Math.max(...values, 1);
    return values.map((value) => ({ height: Math.round((value / max) * 100) }));
};

const adminSlides = computed(() => {
    const docConsults = carousels.value?.doctorConsultations || [];
    const docActs = carousels.value?.doctorActs || [];
    const financeEntries = carousels.value?.financeEntries || { byMode: [], byWeekday: [] };
    const financeOut = carousels.value?.financeOut?.daily || [];
    const capitalDaily = carousels.value?.capitalEvolution?.daily || [];

    const topConsults = docConsults.slice(0, 3);
    const consultLabels = docConsults.slice(0, 6).map((row) => (row.name || '--').split(' ')[0]);
    const consultRates = docConsults.slice(0, 6).map((row) => row.paidRate || 0);

    const topActs = docActs.slice(0, 3);
    const actsLabels = docActs.slice(0, 6).map((row) => (row.name || '--').split(' ')[0]);
    const actsValues = docActs.slice(0, 6).map((row) => row.acts || 0);

    const modeStats = financeEntries.byMode.slice(0, 3);
    const weekdayLabels = financeEntries.byWeekday.map((row) => row.label);
    const weekdayValues = financeEntries.byWeekday.map((row) => row.total || 0);

    const entriesTotal = financeOut.reduce((sum, row) => sum + Number(row.entries || 0), 0);
    const exitsTotal = financeOut.reduce((sum, row) => sum + Number(row.exits || 0), 0);
    const netValues = financeOut.slice(-6).map((row) => row.net || 0);
    const netLabels = financeOut.slice(-6).map((row) => row.date?.slice(5) || '--');

    const capitalValues = capitalDaily.slice(-6).map((row) => row.balance || 0);
    const capitalLabels = capitalDaily.slice(-6).map((row) => row.date?.slice(5) || '--');
    const lastBalance = capitalDaily.at(-1)?.balance || 0;

    return [
        {
            title: 'Rapport par medecin',
            stats: topConsults.map((row) => ({
                label: row.name,
                value: row.total,
                description: `Payantes: ${row.paid} (${row.paidRate}%)`,
                icon: 'pi pi-user',
                color: 'bg-blue-100 dark:bg-blue-900/30',
                trend: `${row.paidRate}%`,
                trendIcon: 'pi pi-chart-line',
                trendColor: 'text-blue-500'
            })),
            actions: quickActions.value,
            chart: {
                title: 'Taux payantes',
                labels: consultLabels,
                bars: buildBars(consultRates)
            },
            summary: { label: 'Payantes moy.', value: `${Math.round(consultRates.reduce((a, b) => a + b, 0) / (consultRates.length || 1))}%` },
            target: { label: 'Objectif', value: '95%' }
        },
        {
            title: 'Actes medicaux par medecin',
            stats: topActs.map((row) => ({
                label: row.name,
                value: row.acts,
                description: 'Actes realises',
                icon: 'pi pi-briefcase',
                color: 'bg-purple-100 dark:bg-purple-900/30'
            })),
            actions: quickActions.value,
            chart: {
                title: 'Volume actes',
                labels: actsLabels,
                bars: buildBars(actsValues)
            },
            summary: { label: 'Total actes', value: actsValues.reduce((a, b) => a + b, 0) },
            target: { label: 'Objectif', value: '120' }
        },
        {
            title: 'Entrees financieres',
            stats: modeStats.map((row) => ({
                label: row.label,
                value: formatAmount(row.total),
                description: 'Mode de paiement',
                icon: 'pi pi-wallet',
                color: 'bg-green-100 dark:bg-green-900/30'
            })),
            actions: quickActions.value,
            chart: {
                title: 'Entrees par jour',
                labels: weekdayLabels,
                bars: buildBars(weekdayValues)
            },
            summary: { label: 'Total entrees', value: formatAmount(entriesTotal) },
            target: { label: 'Cible', value: '---' }
        },
        {
            title: 'Sorties et equilibres',
            stats: [
                {
                    label: 'Entrees',
                    value: formatAmount(entriesTotal),
                    description: 'Sur la periode',
                    icon: 'pi pi-arrow-up',
                    color: 'bg-emerald-100 dark:bg-emerald-900/30'
                },
                {
                    label: 'Sorties',
                    value: formatAmount(exitsTotal),
                    description: 'Sur la periode',
                    icon: 'pi pi-arrow-down',
                    color: 'bg-red-100 dark:bg-red-900/30'
                }
            ],
            actions: quickActions.value,
            chart: {
                title: 'Net quotidien',
                labels: netLabels,
                bars: buildBars(netValues.map((value) => Math.abs(value)))
            },
            summary: { label: 'Net total', value: formatAmount(entriesTotal - exitsTotal) },
            target: { label: 'Objectif', value: '---' }
        },
        {
            title: 'Evolution du capital',
            stats: [
                {
                    label: 'Solde actuel',
                    value: formatAmount(lastBalance),
                    description: 'Capital cumule',
                    icon: 'pi pi-chart-line',
                    color: 'bg-amber-100 dark:bg-amber-900/30'
                }
            ],
            actions: quickActions.value,
            chart: {
                title: 'Capital',
                labels: capitalLabels,
                bars: buildBars(capitalValues)
            },
            summary: { label: 'Dernier solde', value: formatAmount(lastBalance) },
            target: { label: 'Objectif', value: '---' }
        }
    ];
});

const medecinSlides = computed(() => {
    const revenues = carousels.value?.revenuesByWeekday || [];
    const consultations = carousels.value?.consultationsByWeekday || [];

    const revenueLabels = revenues.map((row) => row.label);
    const revenueValues = revenues.map((row) => row.total || 0);
    const consultLabels = consultations.map((row) => row.label);
    const consultValues = consultations.map((row) => row.total || 0);

    return [
        {
            title: 'Montants generes par jour',
            stats: revenues.slice(0, 3).map((row) => ({
                label: row.label,
                value: formatAmount(row.total),
                description: 'Recettes',
                icon: 'pi pi-wallet',
                color: 'bg-purple-100 dark:bg-purple-900/30'
            })),
            actions: quickActions.value,
            chart: {
                title: 'Montants par jour',
                labels: revenueLabels,
                bars: buildBars(revenueValues)
            },
            summary: { label: 'Total', value: formatAmount(revenueValues.reduce((a, b) => a + b, 0)) },
            target: { label: 'Objectif', value: '---' }
        },
        {
            title: 'Consultations par jour',
            stats: consultations.slice(0, 3).map((row) => ({
                label: row.label,
                value: row.total,
                description: 'Consultations',
                icon: 'pi pi-briefcase',
                color: 'bg-blue-100 dark:bg-blue-900/30'
            })),
            actions: quickActions.value,
            chart: {
                title: 'Consultations par jour',
                labels: consultLabels,
                bars: buildBars(consultValues)
            },
            summary: { label: 'Total', value: consultValues.reduce((a, b) => a + b, 0) },
            target: { label: 'Objectif', value: '---' }
        }
    ];
});

const carouselSlides = computed(() => {
    if (role.value === 'medecin') return medecinSlides.value;
    if (role.value === 'reception') return [];
    return adminSlides.value;
});

const showReceptionReports = computed(() => role.value === 'reception');
const receptionReports = computed(() => carousels.value?.doctorReports || { kpi: {}, doctors: [] });

const handleFilterChange = async (filter) => {
    notificationsFilter.value = filter;
    await fetchNotifications(filter);
};

const handleMarkRead = async (ids) => {
    await markNotificationsRead(ids);
};

const handleMarkAll = async () => {
    await markAllNotificationsRead();
};

watch(
    () => [role.value, filterParams.value],
    async () => {
        if (!auth.token) return;

        const params = filterParams.value;
        if (!params.date && (!params.from || !params.to)) return;

        try {
            await fetchDashboard(role.value, params);
        } catch (_) {
            // Ignore transient errors during logout/unmount transitions
        }
    },
    { deep: true, immediate: true }
);

onMounted(async () => {
    await fetchProfile();
    await fetchNotifications(notificationsFilter.value);
});
</script>

<template>
    <section class="min-h-screen bg-gradient-to-br from-surface-50 to-surface-100/60 dark:from-surface-900 dark:to-surface-800/90 p-3 sm:p-4 md:p-6 lg:p-8 transition-colors duration-300">
        <div class="mb-6 md:mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                <div class="space-y-2 flex-1">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-xl bg-primary-500/10 dark:bg-primary-500/20 sm:p-2.5">
                            <i class="pi pi-home text-primary-600 dark:text-primary-400 text-lg sm:text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-surface-900 dark:text-surface-50 tracking-tight">
                                Bonjour, {{ userLabel }} 👋
                            </h1>
                            <p class="text-surface-600 dark:text-surface-300 text-xs sm:text-sm md:text-base mt-1">
                                Voici votre tableau de bord pour aujourd'hui
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2 sm:gap-3 w-full lg:w-auto">
                    <SelectButton
                        v-model="filterMode"
                        :options="filterOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full sm:w-auto"
                    />
                    <div class="relative w-full sm:w-auto" v-if="filterMode === 'date'">
                        <DatePicker
                            v-model="selectedDate"
                            showIcon
                            iconDisplay="input"
                            dateFormat="dd/mm/yy"
                            placeholder="Selectionner une date"
                            class="rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 [&_.p-datepicker]:p-3.5 w-full sm:w-56 lg:w-64"
                            :pt="{ input: 'pl-10 py-2.5 sm:py-3', icon: 'left-3 top-3 text-surface-400' }"
                        /> 
                    </div>
                    <div class="relative w-full sm:w-auto" v-else>
                        <DatePicker
                            v-model="selectedRange"
                            selectionMode="range"
                            showIcon
                            iconDisplay="input"
                            dateFormat="dd/mm/yy"
                            placeholder="Choisir periode"
                            class="rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 [&_.p-datepicker]:p-3.5 w-full sm:w-64 lg:w-72"
                            :pt="{ input: 'pl-10 py-2.5 sm:py-3', icon: 'left-3 top-3 text-surface-400' }"
                        /> 
                    </div>
                </div>
            </div>

            <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl p-3 sm:p-4 shadow-sm border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
                <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" />
            </div>
        </div>

        <DashboardQuickStats :cards="quickCards" :loading="loading" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 md:mb-8">
            <div class="lg:col-span-2">
                <div v-if="showReceptionReports">
                    <DoctorReportsTable
                        title="Rapports periodiques par medecin"
                        :data="receptionReports"
                        :loading="loading"
                        :period-label="periodLabel"
                        variant="reception"
                    />
                </div>
                <div v-else>
                    <DashboardCarouselSection
                        :slides="carouselSlides"
                        :selected-period="selectedPeriod"
                        :period-options="periodOptions"
                        :loading="loading"
                        @update:selectedPeriod="selectedPeriod = $event"
                    />
                </div>
            </div>

            <div class="lg:col-span-1">
                <DashboardTabsPanel :role="role" :tabs="tabs" :loading="loading" />
            </div>
        </div>

        <ProfileNotificationsSection
            :notifications="notifications"
            :unread-count="unreadCount"
            :loading="notificationsLoading"
            :filter="notificationsFilter"
            @filter-change="handleFilterChange"
            @mark-read="handleMarkRead"
            @mark-all="handleMarkAll"
        />
    </section>
</template>
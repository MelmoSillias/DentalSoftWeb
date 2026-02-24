import { computed, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useAuthStore } from '@/stores/auth';
import { apiPrefix } from '@/config';
import http from '@/service/http';

function toIsoDate(value) {
    if (!value) return null;
    if (typeof value === 'string') return value;
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatFcfa(amount) {
    const value = Number(amount || 0);
    return `${new Intl.NumberFormat('fr-FR').format(value)} Fcfa`;
}

function safeArray(value) {
    return Array.isArray(value) ? value : [];
}

export function useRapports() {
    const toast = useToast();
    const auth = useAuthStore();

    const adminLoading = ref(false);
    const adminError = ref(null);
    const medecinLoading = ref(false);
    const medecinError = ref(null);
    const receptionLoading = ref(false);
    const receptionError = ref(null);

    const adminGlobalStats = ref({
        patientsTotal: 0,
        capitalTotal: 0,
        capitalCash: 0,
        revenueTotal: 0,
        appointmentsTotal: 0,
        employeesTotal: 0,
        payrollFixed: 0,
        payrollFixedCount: 0,
        consultRoomsCount: 0,
        consumablesCount: 0,
        usersTotal: 0,
        usersAdmin: 0,
        usersReceptionist: 0,
        usersDoctor: 0
    });

    const adminEmployeeDistribution = ref([]);
    const adminLowStockConsumables = ref([]);
    const adminGlobalPatients = ref({
        total: 0,
        female: 0,
        male: 0,
        minors: 0,
        adults: 0,
        seniors: 0,
        averageAge: 0
    });
    const adminPeriodicPatients = ref({ newPatients: 0, returningPatients: 0 });
    const adminPeriodicConsultations = ref({
        total: 0,
        paid: 0,
        free: 0,
        totalAmount: 0,
        averageAmount: 0,
        topActs: []
    });
    const adminPeriodicAppointments = ref({
        scheduled: 0,
        confirmed: 0,
        pending: 0,
        postponed: 0,
        cancelled: 0,
        confirmationRate: 0,
        averageDelayDays: 0
    });
    const adminRoomUsage = ref({ usage: [], topRoom: '' });
    const adminPaymentBalances = ref([]);
    const adminPaymentFrequency = ref({ frequency: [], topMode: '' });
    const adminActsStats = ref([]);
    const adminDoctorReports = ref({
        kpi: { totalRevenue: 0, afterFees: 0, totalSalaries: 0 },
        doctors: []
    });

    const medecinData = ref({
        nom: '',
        prenom: '',
        matricule: '',
        fonction: '',
        telephone: '',
        email: '',
        type: '',
        dateEmbauche: null,
        typeSalaire: '',
        valeurSalaire: 0,
        typeContrat: '',
        dureeContrat: '',
        joursTravailles: [],
        stats: {
            patientsTotal: 0,
            totalConsultations: 0,
            consultationsEnAttente: 0,
            rdvJour: 0
        },
        period: {
            freeConsultations: 0,
            paidConsultations: 0,
            rdvPlanifies: 0,
            rdvEnAttente: 0,
            rdvValides: 0,
            rdvReportes: 0,
            rdvAnnules: 0,
            apportTotal: 0,
            paiements_period: []
        }
    });

    const receptionStats = ref({
        newPatients: 0,
        totalConsultations: 0,
        pendingConsultations: 0,
        totalAppointments: 0,
        absentAppointments: 0,
        paidInvoices: 0,
        cashRevenue: 0,
        totalRevenue: 0
    });

    const receptionDoctorReports = ref({
        kpi: { totalRevenue: 0, afterFees: 0, totalSalaries: 0 },
        doctors: []
    });

    const userRoles = computed(() => auth.user?.roles || []);

    function buildAuthHeaders(includeJson = false) {
        const token = auth.token || localStorage.getItem('token');
        const headers = {};
        if (includeJson) headers['Content-Type'] = 'application/json';
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return headers;
    }

    async function fetchJson(path, params = {}) {
        const url = new URL(`${apiPrefix}${path}`);
        Object.entries(params).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '') return;
            url.searchParams.append(key, value);
        });

        try {
            const response = await http.get(url.toString(), { headers: buildAuthHeaders() });
            return response.data;
        } catch (err) {
            const status = err?.response?.status;
            const body = err?.response?.data;
            const message = body?.message || body?.error || err?.message || `Erreur ${status || 'inconnue'}`;
            throw new Error(message);
        }
    }

    async function fetchAdminGlobalStats(from, to) {
        const data = await fetchJson('/report/global-stats', { from, to });
        adminGlobalStats.value = {
            patientsTotal: data.patientsTotal || 0,
            capitalTotal: data.capitalTotal || 0,
            capitalCash: data.inCash || 0,
            revenueTotal: data.revenueTotal || 0,
            appointmentsTotal: data.appointmentsTotal || 0,
            employeesTotal: data.employeesTotal || 0,
            payrollFixed: data.payrollFixed || 0,
            payrollFixedCount: data.payrollFixedCount || 0,
            consultRoomsCount: data.consultRoomsCount || 0,
            consumablesCount: data.consumablesCount || 0,
            usersTotal:
                (data.usersByRole?.administrateur || 0) +
                (data.usersByRole?.receptionniste || 0) +
                (data.usersByRole?.medecins || 0),
            usersAdmin: data.usersByRole?.administrateur || 0,
            usersReceptionist: data.usersByRole?.receptionniste || 0,
            usersDoctor: data.usersByRole?.medecins || 0
        };
    }

    async function fetchAdminEmployeeDistribution() {
        const data = await fetchJson('/report/nonperiodic/employees-distribution');
        adminEmployeeDistribution.value = Object.entries(data || {}).map(([key, value]) => ({
            label: key.charAt(0).toUpperCase() + key.slice(1),
            value
        }));
    }

    async function fetchAdminLowStockConsumables() {
        const items = await fetchJson('/report/nonperiodic/low-stock-consumables');
        adminLowStockConsumables.value = safeArray(items).map((item) => ({
            label: item.item,
            value: `${item.remaining} restants`
        }));
    }

    async function fetchAdminGlobalPatients() {
        const stats = await fetchJson('/report/global/patients');
        adminGlobalPatients.value = {
            total: stats.total || 0,
            female: stats.female || 0,
            male: stats.male || 0,
            minors: stats.minors || 0,
            adults: stats.adults || 0,
            seniors: stats.seniors || 0,
            averageAge: stats.averageAge || 0
        };
    }

    async function fetchAdminPeriodicPatients(from, to) {
        const data = await fetchJson('/report/periodic/patients', { from, to });
        adminPeriodicPatients.value = {
            newPatients: data.newPatients ?? 0,
            returningPatients: data.returningPatients ?? 0
        };
    }

    async function fetchAdminPeriodicConsultations(from, to) {
        const stats = await fetchJson('/report/periodic/consultations', { from, to });
        adminPeriodicConsultations.value = {
            total: stats.total || 0,
            paid: stats.paid || 0,
            free: stats.free || 0,
            totalAmount: stats.totalAmount || 0,
            averageAmount: stats.averageAmount || 0,
            topActs: safeArray(stats.topActs)
        };
    }

    async function fetchAdminPeriodicAppointments(from, to) {
        const stats = await fetchJson('/report/periodic/appointments', { from, to });
        adminPeriodicAppointments.value = {
            scheduled: stats.scheduled || 0,
            confirmed: stats.confirmed || 0,
            pending: stats.pending || 0,
            postponed: stats.postponed || 0,
            cancelled: stats.cancelled || 0,
            confirmationRate: stats.confirmationRate || 0,
            averageDelayDays: stats.averageDelayDays || 0
        };
    }

    async function fetchAdminRoomUsage(from, to) {
        const data = await fetchJson('/report/periodic/room-usage', { from, to });
        adminRoomUsage.value = {
            usage: safeArray(data.usage).filter((row) => row.room),
            topRoom: data.topRoom || ''
        };
    }

    async function fetchAdminPaymentBalances(from, to) {
        const data = await fetchJson('/report/periodic/payment-balances', { from, to });
        adminPaymentBalances.value = safeArray(data).map((item) => ({
            label: item.mode,
            value: formatFcfa(item.balance)
        }));
    }

    async function fetchAdminPaymentFrequency(from, to) {
        const data = await fetchJson('/report/periodic/payment-frequency', { from, to });
        adminPaymentFrequency.value = {
            frequency: safeArray(data.frequency).map((item) => ({
                label: item.mode,
                value: `${item.count} (${item.percent}%)`
            })),
            topMode: data.topMode || ''
        };
    }

    async function fetchAdminActsStats(from, to) {
        const data = await fetchJson('/report/periodic/acts-stats', { from, to });
        adminActsStats.value = Object.entries(data || {}).map(([label, value]) => ({
            label,
            value
        }));
    }

    async function fetchDoctorReports(from, to, target = adminDoctorReports) {
        const data = await fetchJson('/report/periodic/doctor-reports', { from, to });
        target.value = {
            kpi: {
                totalRevenue: data.kpi?.totalRevenue || 0,
                afterFees: data.kpi?.afterFees || 0,
                totalSalaries: data.kpi?.totalSalaries || 0
            },
            doctors: safeArray(data.doctors)
        };
    }

    async function fetchAdminRapport({ from, to, silent = false } = {}) {
        adminLoading.value = true;
        adminError.value = null;
        try {
            await Promise.all([
                fetchAdminGlobalStats(from, to),
                fetchAdminEmployeeDistribution(),
                fetchAdminLowStockConsumables(),
                fetchAdminGlobalPatients(),
                fetchAdminPeriodicPatients(from, to),
                fetchAdminPeriodicConsultations(from, to),
                fetchAdminPeriodicAppointments(from, to),
                fetchAdminRoomUsage(from, to),
                fetchAdminPaymentBalances(from, to),
                fetchAdminPaymentFrequency(from, to),
                fetchAdminActsStats(from, to),
                fetchDoctorReports(from, to, adminDoctorReports)
            ]);
            if (!silent) {
                toast.add({ severity: 'success', summary: 'Rapport admin', detail: 'Données mises à jour.', life: 2500 });
            }
        } catch (err) {
            adminError.value = err.message || 'Erreur de chargement';
            toast.add({ severity: 'error', summary: 'Rapport admin', detail: 'Erreur lors du chargement.', life: 3000 });
        } finally {
            adminLoading.value = false;
        }
    }

    async function fetchMedecinRapport({ from, to, silent = false } = {}) {
        medecinLoading.value = true;
        medecinError.value = null;
        try {
            const data = await fetchJson('/report/medecin', { from, to });
            const identity = data.identity || {};
            const fullName = data.fullName
                || identity.fullName
                || `${identity.prenom || ''} ${identity.nom || ''}`.trim();

            medecinData.value = {
                ...medecinData.value,
                ...identity,
                ...data,
                fullName,
                stats: {
                    patientsTotal: data.stats?.patientsTotal || 0,
                    totalConsultations: data.stats?.totalConsultations || 0,
                    consultationsEnAttente: data.stats?.consultationsEnAttente || 0,
                    rdvJour: data.stats?.rdvJour || 0
                },
                period: {
                    freeConsultations: data.period?.freeConsultations || 0,
                    paidConsultations: data.period?.paidConsultations || 0,
                    rdvPlanifies: data.period?.rdvPlanifies || 0,
                    rdvEnAttente: data.period?.rdvEnAttente || 0,
                    rdvValides: data.period?.rdvValides || 0,
                    rdvReportes: data.period?.rdvReportes || 0,
                    rdvAnnules: data.period?.rdvAnnules || 0,
                    apportTotal: data.period?.apportTotal || 0,
                    paiements_period: safeArray(data.period?.paiements_period)
                },
                joursTravailles: safeArray(identity.joursTravailles ?? data.joursTravailles)
            };
            if (!silent) {
                toast.add({ severity: 'success', summary: 'Rapport médecin', detail: 'Données mises à jour.', life: 2500 });
            }
        } catch (err) {
            medecinError.value = err.message || 'Erreur de chargement';
            toast.add({ severity: 'error', summary: 'Rapport médecin', detail: 'Erreur lors du chargement.', life: 3000 });
        } finally {
            medecinLoading.value = false;
        }
    }

    async function fetchReceptionRapport({ date, silent = false } = {}) {
        receptionLoading.value = true;
        receptionError.value = null;
        try {
            const stats = await fetchJson('/report/reception-stats', { date });
            receptionStats.value = {
                newPatients: stats.newPatients || 0,
                totalConsultations: stats.totalConsultations || 0,
                pendingConsultations: stats.pendingConsultations || 0,
                totalAppointments: stats.totalAppointments || 0,
                absentAppointments: stats.absentAppointments || 0,
                paidInvoices: stats.paidInvoices || 0,
                cashRevenue: stats.cashRevenue || 0,
                totalRevenue: stats.totalRevenue || 0
            };
            await fetchDoctorReports(date, date, receptionDoctorReports);
            if (!silent) {
                toast.add({ severity: 'success', summary: 'Rapport réception', detail: 'Données mises à jour.', life: 2500 });
            }
        } catch (err) {
            receptionError.value = err.message || 'Erreur de chargement';
            toast.add({ severity: 'error', summary: 'Rapport réception', detail: 'Erreur lors du chargement.', life: 3000 });
        } finally {
            receptionLoading.value = false;
        }
    }

    return {
        adminLoading,
        adminError,
        medecinLoading,
        medecinError,
        receptionLoading,
        receptionError,
        adminGlobalStats,
        adminEmployeeDistribution,
        adminLowStockConsumables,
        adminGlobalPatients,
        adminPeriodicPatients,
        adminPeriodicConsultations,
        adminPeriodicAppointments,
        adminRoomUsage,
        adminPaymentBalances,
        adminPaymentFrequency,
        adminActsStats,
        adminDoctorReports,
        medecinData,
        receptionStats,
        receptionDoctorReports,
        userRoles,
        formatFcfa,
        toIsoDate,
        fetchAdminRapport,
        fetchMedecinRapport,
        fetchReceptionRapport
    };
}

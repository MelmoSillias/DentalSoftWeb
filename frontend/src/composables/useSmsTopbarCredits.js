import { computed, ref } from 'vue';
import { fetchSmsProviderOverview, fetchSmsSettings } from '@/services/smsService';

const REFRESH_INTERVAL_MS = 5 * 60 * 1000;

const SMS_PROVIDER_OPTIONS = [
    { value: 'orange', label: 'Orange' },
    { value: 'afriksms', label: 'AfrikSms' }
];

const SMS_TOPBAR_ROLES = ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE'];

function canViewSmsTopbarCredits(roles) {
    return SMS_TOPBAR_ROLES.some((role) => (roles || []).includes(role));
}

function formatExpirationDate(value) {
    if (!value) {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function pickRecommendedContract(contracts) {
    if (!Array.isArray(contracts) || contracts.length === 0) {
        return null;
    }

    return contracts.find((item) => item.isRecommended) || contracts[0];
}

function normalizeProviderOverview(overview) {
    if (!overview || typeof overview !== 'object') {
        return { success: false, message: '', contracts: [] };
    }

    return {
        success: Boolean(overview.success),
        message: overview.message || '',
        contracts: Array.isArray(overview.contracts) ? overview.contracts : []
    };
}

const smsEnabled = ref(false);
const smsProvider = ref('orange');
const remainingUnits = ref(null);
const expirationDate = ref(null);
const overviewSuccess = ref(false);
const overviewMessage = ref('');
const loading = ref(false);
const lastUpdated = ref(null);

let refreshTimer = null;
let tokenGetter = () => null;
let rolesGetter = () => [];

function applyProviderOverview(overview) {
    const normalized = normalizeProviderOverview(overview);
    overviewSuccess.value = normalized.success;
    overviewMessage.value = normalized.message;
    const contract = pickRecommendedContract(normalized.contracts);
    remainingUnits.value = contract?.availableUnits ?? null;
    expirationDate.value = contract?.expirationDate ?? null;
    lastUpdated.value = new Date();
}

export function useSmsTopbarCredits(getToken, getRoles) {
    tokenGetter = getToken;
    rolesGetter = getRoles;

    const canViewCredits = computed(() => canViewSmsTopbarCredits(rolesGetter()));
    const canOpenSmsSettings = computed(() => (rolesGetter() || []).includes('ROLE_ADMIN'));

    const providerLabel = computed(() => {
        const match = SMS_PROVIDER_OPTIONS.find((item) => item.value === smsProvider.value);
        return match?.label || smsProvider.value || 'SMS';
    });

    const showInTopbar = computed(() => canViewCredits.value && smsEnabled.value);

    const displayUnits = computed(() => {
        if (loading.value && remainingUnits.value === null) {
            return '…';
        }

        if (!overviewSuccess.value || remainingUnits.value === null || remainingUnits.value === undefined) {
            return '—';
        }

        return String(remainingUnits.value);
    });

    const displayExpiration = computed(() => {
        if (loading.value && !expirationDate.value && remainingUnits.value === null) {
            return '…';
        }

        if (!overviewSuccess.value) {
            return '—';
        }

        return formatExpirationDate(expirationDate.value) || '—';
    });

    const refresh = async ({ silent = true } = {}) => {
        const token = tokenGetter();
        if (!token || !canViewSmsTopbarCredits(rolesGetter())) {
            return;
        }

        if (!silent) {
            loading.value = true;
        }

        try {
            const settings = await fetchSmsSettings(token);
            smsEnabled.value = Boolean(settings.enabled);
            smsProvider.value = settings.provider || 'orange';

            if (!smsEnabled.value) {
                remainingUnits.value = null;
                expirationDate.value = null;
                overviewSuccess.value = false;
                overviewMessage.value = '';
                return;
            }

            const overview = await fetchSmsProviderOverview(token);
            applyProviderOverview(overview);
        } catch {
            overviewSuccess.value = false;
            overviewMessage.value = 'Solde SMS indisponible';
            remainingUnits.value = null;
            expirationDate.value = null;
        } finally {
            loading.value = false;
        }
    };

    const startPolling = () => {
        stopPolling();
        if (!tokenGetter() || !canViewSmsTopbarCredits(rolesGetter())) {
            return;
        }

        refresh({ silent: false });
        refreshTimer = setInterval(() => refresh({ silent: true }), REFRESH_INTERVAL_MS);
    };

    const stopPolling = () => {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    };

    return {
        showInTopbar,
        canOpenSmsSettings,
        providerLabel,
        displayUnits,
        displayExpiration,
        overviewSuccess,
        loading,
        refresh,
        startPolling,
        stopPolling,
        applyProviderOverview
    };
}

export { applyProviderOverview };

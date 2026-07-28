import { computed, ref } from 'vue';
import { fetchSmsProviderOverview, fetchSmsSettings } from '@/services/smsService';
import { SMS_PROVIDER_OPTIONS } from '@/composables/useSmsAdminSettings';

const REFRESH_INTERVAL_MS = 5 * 60 * 1000;

const SMS_TOPBAR_ROLES = ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'];

function canViewSmsTopbarCredits(roles) {
    return SMS_TOPBAR_ROLES.some((role) => (roles || []).includes(role));
}

function pickRecommendedContract(contracts) {
    if (!Array.isArray(contracts) || contracts.length === 0) {
        return null;
    }

    return contracts.find((item) => item.isRecommended) || contracts[0];
}

export function useSmsTopbarCredits(getToken, getRoles) {
    const smsEnabled = ref(false);
    const smsProvider = ref('orange');
    const remainingUnits = ref(null);
    const overviewSuccess = ref(false);
    const overviewMessage = ref('');
    const loading = ref(false);
    const lastUpdated = ref(null);

    let refreshTimer = null;

    const canViewCredits = computed(() => canViewSmsTopbarCredits(getRoles()));
    const canOpenSmsSettings = computed(() => (getRoles() || []).includes('ROLE_ADMIN'));

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

    const titleHint = computed(() => {
        const parts = [`Solde ${providerLabel.value}`];
        if (remainingUnits.value !== null && remainingUnits.value !== undefined && overviewSuccess.value) {
            parts.push(`${remainingUnits.value} SMS restants`);
        } else if (overviewMessage.value) {
            parts.push(overviewMessage.value);
        }
        if (lastUpdated.value) {
            parts.push(`Mis à jour ${lastUpdated.value.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`);
        }
        parts.push('Actualisation automatique toutes les 5 min');
        return parts.join(' · ');
    });

    const refresh = async ({ silent = true } = {}) => {
        const token = getToken();
        if (!token || !canViewCredits.value) {
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
                overviewSuccess.value = false;
                overviewMessage.value = '';
                return;
            }

            const overview = await fetchSmsProviderOverview(token);
            overviewSuccess.value = Boolean(overview?.success);
            overviewMessage.value = overview?.message || '';
            const contract = pickRecommendedContract(overview?.contracts);
            remainingUnits.value = contract?.availableUnits ?? null;
            lastUpdated.value = new Date();
        } catch {
            overviewSuccess.value = false;
            overviewMessage.value = 'Solde SMS indisponible';
            remainingUnits.value = null;
        } finally {
            loading.value = false;
        }
    };

    const startPolling = () => {
        stopPolling();
        if (!getToken() || !canViewCredits.value) {
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
        overviewSuccess,
        loading,
        titleHint,
        refresh,
        startPolling,
        stopPolling
    };
}

import { computed, ref } from 'vue';
import cabinetConfig from '@/cabinetConfig';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';

const buildTimeEnabled = cabinetConfig.internetFeaturesEnabled !== false;
const runtimeEnabled = ref(null);

export function useInternetFeatures() {
    const isInternetFeaturesEnabled = computed(() => {
        if (!buildTimeEnabled) {
            return false;
        }

        if (runtimeEnabled.value === null) {
            return true;
        }

        return runtimeEnabled.value !== false;
    });

    const isLocalDeploymentMode = computed(() => !isInternetFeaturesEnabled.value);

    async function syncFromServer(token) {
        if (!token || !buildTimeEnabled) {
            runtimeEnabled.value = buildTimeEnabled;
            return;
        }

        try {
            const settings = await fetchPublicGeneralSettings(token);
            runtimeEnabled.value = settings?.internetFeaturesEnabled !== false;
        } catch {
            runtimeEnabled.value = buildTimeEnabled;
        }
    }

    function reset() {
        runtimeEnabled.value = null;
    }

    return {
        isInternetFeaturesEnabled,
        isLocalDeploymentMode,
        syncFromServer,
        reset,
    };
}

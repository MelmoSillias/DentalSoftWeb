import { computed, reactive, ref } from 'vue';
import { defineStore } from 'pinia';
import { fetchPublicPortalSettings } from '../services/patientPortal';

const defaultClosedMessage = 'Le portail patient est temporairement indisponible. Merci de contacter le cabinet pour toute assistance.';

export const usePortalSettingsStore = defineStore('portalSettings', () => {
    const loaded = ref(false);
    const loading = ref(false);

    const data = reactive({
        patientPortalEnabled: true,
        patientPortalClosedMessage: defaultClosedMessage,
        patientPortalBaseUrl: '',
        cabinetShowcaseWebsiteUrl: ''
    });

    const isPortalClosed = computed(() => data.patientPortalEnabled === false);

    async function load(force = false) {
        if (loading.value) return;
        if (loaded.value && !force) return;

        loading.value = true;
        try {
            const settings = await fetchPublicPortalSettings();
            data.patientPortalEnabled = settings?.patientPortalEnabled !== false;
            data.patientPortalClosedMessage = settings?.patientPortalClosedMessage || defaultClosedMessage;
            data.patientPortalBaseUrl = settings?.patientPortalBaseUrl || '';
            data.cabinetShowcaseWebsiteUrl = settings?.cabinetShowcaseWebsiteUrl || '';
        } catch (_error) {
            data.patientPortalEnabled = true;
            data.patientPortalClosedMessage = defaultClosedMessage;
            data.patientPortalBaseUrl = '';
            data.cabinetShowcaseWebsiteUrl = '';
        } finally {
            loaded.value = true;
            loading.value = false;
        }
    }

    return {
        loaded,
        loading,
        data,
        isPortalClosed,
        load
    };
});

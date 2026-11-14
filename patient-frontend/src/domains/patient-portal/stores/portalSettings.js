import { computed, reactive, ref } from 'vue';
import { defineStore } from 'pinia';
import { fetchPublicPortalSettings } from '../api.js';

const defaultClosedMessage = 'Le portail patient est temporairement indisponible. Merci de contacter le cabinet pour toute assistance.';

export const usePortalSettingsStore = defineStore('portalSettings', () => {
    const loaded = ref(false);
    const loading = ref(false);
    let pending;

    const data = reactive({
        patientPortalEnabled: true,
        patientPortalClosedMessage: defaultClosedMessage,
        patientPortalBaseUrl: '',
        cabinetShowcaseWebsiteUrl: ''
    });

    const isPortalClosed = computed(() => data.patientPortalEnabled === false);

    function load(force = false) {
        if (pending) return pending;
        if (loaded.value && !force) return Promise.resolve();

        loading.value = true;
        pending = (async () => {
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
            pending = null;
        }
        })();
        return pending;
    }

    return {
        loaded,
        loading,
        data,
        isPortalClosed,
        load
    };
});

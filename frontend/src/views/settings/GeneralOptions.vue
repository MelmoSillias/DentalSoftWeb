<script setup>
import { logAppError } from '@/utils/appLogger';

import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import Column from 'primevue/column';
import ConfirmPopup from 'primevue/confirmpopup';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import Tabs from 'primevue/tabs';
import Textarea from 'primevue/textarea';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import Divider from 'primevue/divider';
import Card from 'primevue/card';
import Badge from 'primevue/badge';
import InputNumber from 'primevue/inputnumber';
import DatePicker from 'primevue/datepicker';
import { useAuthStore } from '@/stores/auth';
import { usePrinter } from '@/composables/usePrinter';
import { useUiSettingsStore } from '@/stores/uiSettings';
import { useAppearanceSettings } from '@/composables/useAppearanceSettings';
import PrintPatientPortalQrPoster from '@/components/print/PrintPatientPortalQrPoster.vue';
import PrintPatientPortalQrSingle from '@/components/print/PrintPatientPortalQrSingle.vue';
import { useGuidedTour } from '@/composables/useGuidedTour';
import {
    cleanTestMode,
    approveDevice as approveDeviceApi,
    createMissingPatientPortalAccounts,
    deleteDevice as deleteDeviceApi,
    downloadDatabaseExport,
    exportDatabase,
    fetchApprovedDevices,
    fetchGeneralSettings,
    rejectDevice as rejectDeviceApi,
    renameDevice as renameDeviceApi,
    resetDatabase,
    saveGeneralSettings,
    toggleTestMode
} from '@/services/globalSettingsService';
import { buildPatientPortalQrPrintModel, getPatientPortalQrPrintEntry } from '@/services/printService';
import { getHttpErrorMessage } from '@/service/http';
import cabinetConfig from '@/cabinetConfig';

const router = useRouter();
const toast = useToast();
const confirm = useConfirm();
const { printComponent } = usePrinter();
const token = localStorage.getItem('token');
const auth = useAuthStore();
const uiSettings = useUiSettingsStore();
const activeCategory = ref('appearance');
const activeSubSection = ref('overview');
const settingsDisplayMode = ref('tabs');
const settingsDisplayModeOptions = [
    { label: 'Page', value: 'page', icon: 'pi pi-align-justify' },
    { label: 'Onglets', value: 'tabs', icon: 'pi pi-objects-column' }
];
const activeSettingsTab = ref('appearance');
let observer = null;

const {
    layoutConfig,
    isDarkTheme,
    preset,
    presetOptions,
    menuMode,
    menuModeOptions,
    themeOptions,
    fontFamilyOptions,
    fontSizeOptions,
    primaryColors,
    surfaces,
    themeMode,
    fontFamily,
    fontSize,
    updateColors,
    onPresetChange,
    onMenuModeChange
} = useAppearanceSettings();

// Loading states
const generalLoading = ref(false);
const generalSettingsLoaded = ref(false);
const loadErrorMessage = ref('');
const savingStates = reactive({
    consultationPolicy: false,
    openingHours: false,
    medecinPrivacy: false,
    clinicalForm: false,
    devicePolicy: false,
    billingPolicy: false,
    portalSettings: false,
    portalBulkCreate: false,
    soinsCatalog: false,
    cabinetIdentity: false,
    testMode: false,
    databaseExport: false,
    databaseReset: false
});

// Settings data
const devicePolicy = reactive({
    autoApproveDevices: true
});

const approvedDevices = ref([]);
const deviceAccessLogs = ref([]);
const deviceAccessLogsDialogVisible = ref(false);
const deviceRenameDialogVisible = ref(false);
const deviceBeingRenamed = ref(null);
const deviceRenameValue = ref('');
const deviceRenameSaving = ref(false);
const deviceStats = ref({ approved: 0, pending: 0, rejected: 0, total: 0 });
const devicesLoading = ref(false);

const consultationPolicy = reactive({
    requireMedecinOnConsultationCreation: true,
    allowReceptionConsultationQuickActions: true,
    showReceptionQuickCloseButton: true,
    allowReceptionBypassMedecinPasswordOnQuickClose: false,
    consultationPrice: 5000
});

const openingHours = reactive({
    openingTime: '08:00',
    closingTime: '18:00'
});

const parseTimeToDate = (value, fallback = '08:00') => {
    const raw = String(value || fallback);
    const match = raw.match(/^(\d{1,2}):(\d{2})/);
    const hours = match ? Number(match[1]) : 8;
    const minutes = match ? Number(match[2]) : 0;
    const date = new Date();
    date.setHours(hours, minutes, 0, 0);
    return date;
};

const formatTimeFromDate = (value, fallback = '08:00') => {
    if (!(value instanceof Date) || Number.isNaN(value.getTime())) return fallback;
    return `${String(value.getHours()).padStart(2, '0')}:${String(value.getMinutes()).padStart(2, '0')}`;
};

const openingTimeModel = computed({
    get: () => parseTimeToDate(openingHours.openingTime, '08:00'),
    set: (val) => {
        openingHours.openingTime = formatTimeFromDate(val, '08:00');
    }
});

const closingTimeModel = computed({
    get: () => parseTimeToDate(openingHours.closingTime, '18:00'),
    set: (val) => {
        openingHours.closingTime = formatTimeFromDate(val, '18:00');
    }
});

const medecinPrivacy = reactive({
    hidePatientDossierForMedecins: false,
    hidePatientPhoneForMedecins: false
});

const clinicalForm = reactive({
    ficheFormSimplifie: false,
    showDiagnosticPositifInConsultation: true,
    examensTypesText: 'Bacteriologique\nSerologique\nHistologique\nRadiologique\nAutre',
    traitementTypesText: 'Urgence\nDentaires\nParodontaux\nOrthodontiques\nAutres',
    allergyTypesText: 'Médicamenteuses\nAlimentaires\nEnvironnementales\nAutres',
    antecedentTypesText: 'Personnel\nFamilial\nMédical'
});

const billingPolicy = reactive({
    paiementDirectAssurance: false,
    allowReceptionInvoiceModification: false,
    allowConsultationPriceEditOnCreation: false
});

const transactionMotifs = reactive({
    revenueText: 'Paiement patient\nRemboursement assurance\nVente produit\nAutre',
    expenseText: 'Achat matériel\nFrais généraux\nPaiement salaire\nMaintenance\nAutre'
});

const soinsCatalog = reactive({
    text: 'Consultation\nDétartrage\nExtraction\nRemplissage\nComposite\nAmalgame\nTraitement de canal\nTraumatisme\nCouronne\nBlanchiment\nRadio\nProthèse\nOrthodontie\nChirurgie'
});

const portalPatientConfig = reactive({
    patientPortalEnabled: true,
    patientPortalClosedMessage: 'Le portail patient est temporairement indisponible. Merci de contacter le cabinet pour toute assistance.',
    patientPortalBaseUrl: '',
    cabinetShowcaseWebsiteUrl: '',
    autoCreatePortalAccountOnPatientCreation: false
});

const cabinetIdentity = reactive({
    smsCabinetName: cabinetConfig.smsCabinetName || cabinetConfig.displayName || 'Cabinet dentaire'
});

const testMode = reactive({
    enabled: false,
    snapshotCreatedAt: null,
    lastPurgeAt: null
});

const backupOptions = reactive({
    sql: true,
    zip: true,
    json: true
});

const backupDownloadMode = ref('primary');
const backupDownloadModeOptions = [
    { label: 'Principal uniquement', value: 'primary' },
    { label: 'Tous les formats', value: 'all' }
];

const ADMIN_CATEGORIES = ['cabinet', 'portal', 'administration'];

// Navigation structure
const navigation = {
    appearance: {
        label: 'Apparence',
        icon: 'pi pi-display',
        sections: [
            { id: 'overview', label: 'Aperçu', icon: 'pi pi-eye' },
            { id: 'theme', label: 'Thème', icon: 'pi pi-sun' },
            { id: 'colors', label: 'Couleurs', icon: 'pi pi-palette' },
            { id: 'typography', label: 'Typographie', icon: 'pi pi-at' },
            { id: 'layout', label: 'Disposition', icon: 'pi pi-th-large' }
        ]
    },
    cabinet: {
        label: 'Cabinet',
        icon: 'pi pi-briefcase',
        sections: [
            { id: 'identity', label: 'Identité & SMS', icon: 'pi pi-building' },
            { id: 'consultations', label: 'Consultations & réception', icon: 'pi pi-calendar' },
            { id: 'opening-hours', label: 'Horaires d\'ouverture', icon: 'pi pi-clock' },
            { id: 'medecin-privacy', label: 'Interface médecin', icon: 'pi pi-user' },
            { id: 'clinical-form', label: 'Fiche clinique', icon: 'pi pi-file' },
            { id: 'billing', label: 'Caisse & finances', icon: 'pi pi-wallet' },
            { id: 'soins-catalog', label: 'Catalogue des soins', icon: 'pi pi-heart' }
        ]
    },
    portal: {
        label: 'Portail patient',
        icon: 'pi pi-mobile',
        sections: [
            { id: 'portal-settings', label: 'Configuration', icon: 'pi pi-cog' }
        ]
    },
    administration: {
        label: 'Administration',
        icon: 'pi pi-shield',
        sections: [
            { id: 'devices', label: 'Appareils autorisés', icon: 'pi pi-desktop' },
            { id: 'system-maintenance', label: 'Maintenance système', icon: 'pi pi-database' }
        ]
    }
};

const canAccessWorkflowSettings = computed(() => (auth.user?.roles || []).includes('ROLE_ADMIN'));
const isSuperAdmin = computed(() => Number(auth.user?.id || 0) === 1);
const isSecurityDialogSubmitting = computed(() => savingStates.testMode || savingStates.databaseExport || savingStates.databaseReset);
const isResetDialogMode = computed(() => securityDialog.mode === 'db-reset');
const isDisablingTestMode = computed(() => securityDialog.mode === 'test-toggle' && !testMode.enabled && persistedTestModeEnabled.value);
const testModeDeleteOptions = [
    { label: 'Supprimer les données de test', value: true },
    { label: 'Conserver les données', value: false }
];
const persistedTestModeEnabled = ref(false);

const securityDialog = reactive({
    visible: false,
    mode: '',
    title: '',
    message: '',
    password: '',
    challenge: '',
    challengeInput: '',
    payload: {},
});

const visibleNavigation = computed(() => {
    if (canAccessWorkflowSettings.value) return navigation;
    return {
        appearance: navigation.appearance
    };
});
const tabModeSections = computed(() => {
    const sections = [
        {
            id: 'appearance',
            label: navigation.appearance.label,
            icon: navigation.appearance.icon
        }
    ];

    if (!canAccessWorkflowSettings.value) {
        return sections;
    }

    for (const categoryKey of ADMIN_CATEGORIES) {
        const category = navigation[categoryKey];
        for (const section of category.sections) {
            sections.push({
                id: `${categoryKey}:${section.id}`,
                label: section.label,
                icon: section.icon
            });
        }
    }

    return sections;
});
const isAppearanceVisible = computed(() => settingsDisplayMode.value === 'page' || activeSettingsTab.value === 'appearance');
const isCabinetVisible = computed(() => settingsDisplayMode.value === 'page' || activeSettingsTab.value.startsWith('cabinet:'));
const isPortalVisible = computed(() => settingsDisplayMode.value === 'page' || activeSettingsTab.value.startsWith('portal:'));
const isAdministrationVisible = computed(() => settingsDisplayMode.value === 'page' || activeSettingsTab.value.startsWith('administration:'));

const extractApiError = (error, fallback) => getHttpErrorMessage(error, fallback);

const normalizeLines = (value) => {
    const unique = new Set();
    return String(value || '')
        .split(/\r?\n/)
        .map((item) => item.trim())
        .filter((item) => item && !unique.has(item) && unique.add(item));
};

const selectSettingsTab = (tabId) => {
    const selected = tabModeSections.value.find((item) => item.id === tabId);
    if (!selected) return;

    activeSettingsTab.value = tabId;
    if (tabId === 'appearance') {
        activeCategory.value = 'appearance';
        activeSubSection.value = 'overview';
        return;
    }

    const colonIndex = tabId.indexOf(':');
    if (colonIndex === -1) return;

    activeCategory.value = tabId.slice(0, colonIndex);
    activeSubSection.value = tabId.slice(colonIndex + 1);
};

const isSectionVisible = (category, sectionId) => {
    if (settingsDisplayMode.value === 'page') return true;
    if (category === 'appearance') return activeSettingsTab.value === 'appearance';
    return activeSettingsTab.value === `${category}:${sectionId}`;
};

const scrollToSection = async (category, sectionId) => {
    if (settingsDisplayMode.value === 'tabs') {
        selectSettingsTab(category === 'appearance' ? 'appearance' : `${category}:${sectionId}`);
        return;
    }

    activeCategory.value = category;
    activeSubSection.value = sectionId;
    await nextTick();
    const element = document.getElementById(`${category}-${sectionId}`);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

// Setup intersection observer for scroll spy
const setupObserver = () => {
    if (settingsDisplayMode.value === 'tabs') {
        observer?.disconnect();
        return;
    }

    const sections = [];
    observer?.disconnect();
    for (const [category, data] of Object.entries(visibleNavigation.value)) {
        for (const section of data.sections) {
            sections.push({ id: `${category}-${section.id}`, category, sectionId: section.id });
        }
    }

    observer = new IntersectionObserver(
        (entries) => {
            const visibleSections = entries.filter(e => e.isIntersecting);
            if (visibleSections.length > 0) {
                const firstVisible = visibleSections[0];
                const [category, ...sectionParts] = firstVisible.target.id.split('-');
                const sectionId = sectionParts.join('-');
                activeCategory.value = category;
                activeSubSection.value = sectionId;
            }
        },
        { rootMargin: '-20% 0px -65% 0px', threshold: 0.3 }
    );

    sections.forEach(({ id }) => {
        const element = document.getElementById(id);
        if (element) observer.observe(element);
    });
};

const saveAppearance = () => {
    uiSettings.persistAppearance();
    uiSettings.persistLayout();
    toast.add({ severity: 'success', summary: 'Apparence', detail: 'Paramètres d\'apparence enregistrés', life: 2500 });
};

const loadGeneralSettings = async (force = false) => {
    if (!canAccessWorkflowSettings.value) {
        generalSettingsLoaded.value = true;
        return;
    }
    if (!force && generalSettingsLoaded.value) return;

    generalLoading.value = true;
    try {
        const settings = await fetchGeneralSettings(token);
        devicePolicy.autoApproveDevices = settings.autoApproveDevices !== false;
        consultationPolicy.requireMedecinOnConsultationCreation = settings.requireMedecinOnConsultationCreation !== false;
        consultationPolicy.allowReceptionConsultationQuickActions = settings.allowReceptionConsultationQuickActions !== false
            && settings.allowReceptionQuickCloseConsultation !== false;
        consultationPolicy.showReceptionQuickCloseButton = settings.showReceptionQuickCloseButton !== false;
        consultationPolicy.allowReceptionBypassMedecinPasswordOnQuickClose = consultationPolicy.showReceptionQuickCloseButton
            && settings.allowReceptionBypassMedecinPasswordOnQuickClose === true;
        consultationPolicy.consultationPrice = Number(settings.consultationPrice || 5000);
        openingHours.openingTime = settings.openingTime || '08:00';
        openingHours.closingTime = settings.closingTime || '18:00';
        medecinPrivacy.hidePatientDossierForMedecins = settings.hidePatientDossierForMedecins === true;
        medecinPrivacy.hidePatientPhoneForMedecins = settings.hidePatientPhoneForMedecins === true;
        clinicalForm.ficheFormSimplifie = settings.ficheFormSimplifie === true;
        clinicalForm.showDiagnosticPositifInConsultation = settings.showDiagnosticPositifInConsultation !== false;
        billingPolicy.paiementDirectAssurance = settings.paiementDirectAssurance === true;
        billingPolicy.allowReceptionInvoiceModification = settings.allowReceptionInvoiceModification === true;
        billingPolicy.allowConsultationPriceEditOnCreation = settings.allowConsultationPriceEditOnCreation === true;
        transactionMotifs.revenueText = (settings.transactionMotifs?.revenue || []).join('\n');
        transactionMotifs.expenseText = (settings.transactionMotifs?.expense || []).join('\n');
        soinsCatalog.text = (settings.soinsList || []).join('\n');
        clinicalForm.examensTypesText = (settings.examensTypes || []).join('\n');
        clinicalForm.traitementTypesText = (settings.traitementTypes || []).join('\n');
        clinicalForm.allergyTypesText = (settings.allergyTypes || []).join('\n');
        clinicalForm.antecedentTypesText = (settings.antecedentTypes || []).join('\n');
        portalPatientConfig.patientPortalEnabled = settings.patientPortalEnabled !== false;
        portalPatientConfig.patientPortalClosedMessage = settings.patientPortalClosedMessage || portalPatientConfig.patientPortalClosedMessage;
        portalPatientConfig.patientPortalBaseUrl = settings.patientPortalBaseUrl || '';
        portalPatientConfig.cabinetShowcaseWebsiteUrl = settings.cabinetShowcaseWebsiteUrl || '';
        cabinetIdentity.smsCabinetName = settings.smsCabinetName || cabinetConfig.smsCabinetName || cabinetConfig.displayName || 'Cabinet dentaire';
        portalPatientConfig.autoCreatePortalAccountOnPatientCreation = settings.autoCreatePortalAccountOnPatientCreation === true;
        testMode.enabled = settings.testModeEnabled === true;
        persistedTestModeEnabled.value = testMode.enabled;
        testMode.snapshotCreatedAt = settings.testModeSnapshotCreatedAt || null;
        testMode.lastPurgeAt = settings.testModeLastPurgeAt || null;
        generalSettingsLoaded.value = true;
        loadErrorMessage.value = '';
        await loadApprovedDevices();
    } catch (error) {
        logAppError('GeneralOptions', error);
        loadErrorMessage.value = extractApiError(error, 'Chargement impossible');
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Chargement impossible'), life: 3500 });
    } finally {
        generalLoading.value = false;
    }
};

const retryLoadSettings = async () => {
    await loadGeneralSettings(true);
};

const saveDevicePolicyAction = async () => {
    if (!canAccessWorkflowSettings.value) return;
    savingStates.devicePolicy = true;
    try {
        await saveGeneralSettings({
            autoApproveDevices: devicePolicy.autoApproveDevices
        }, token);
        toast.add({ severity: 'success', summary: 'Appareils autorisés', detail: 'Paramètres enregistrés', life: 2500 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Sauvegarde impossible'), life: 3500 });
    } finally {
        savingStates.devicePolicy = false;
    }
};

const loadApprovedDevices = async () => {
    if (!canAccessWorkflowSettings.value) return;
    devicesLoading.value = true;
    try {
        const payload = await fetchApprovedDevices(token);
        approvedDevices.value = Array.isArray(payload?.devices) ? payload.devices : [];
        deviceAccessLogs.value = Array.isArray(payload?.logs) ? payload.logs : [];
        deviceStats.value = payload?.stats || { approved: 0, pending: 0, rejected: 0, total: 0 };
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Chargement des appareils impossible'), life: 3500 });
    } finally {
        devicesLoading.value = false;
    }
};

const formatDeviceStatusLabel = (status) => {
    if (status === 'approved') return 'Approuvé';
    if (status === 'rejected') return 'Refusé';
    return 'En attente';
};

const formatDeviceStatusSeverity = (status) => {
    if (status === 'approved') return 'success';
    if (status === 'rejected') return 'danger';
    return 'warning';
};

const formatDeviceDate = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('fr-FR');
};

const formatDeviceTypeIcon = (deviceType) => {
    const type = String(deviceType || '').toLowerCase();
    if (type.includes('mobile') || type.includes('phone') || type.includes('tablet')) return 'pi pi-mobile';
    if (type.includes('desktop') || type.includes('pc')) return 'pi pi-desktop';
    return 'pi pi-server';
};

const getDeviceDisplayName = (device) => device?.displayName || device?.customName || device?.deviceName || 'Appareil inconnu';

const openDeviceRenameDialog = (device) => {
    deviceBeingRenamed.value = device;
    deviceRenameValue.value = getDeviceDisplayName(device);
    deviceRenameDialogVisible.value = true;
};

const closeDeviceRenameDialog = () => {
    deviceRenameDialogVisible.value = false;
    deviceBeingRenamed.value = null;
    deviceRenameValue.value = '';
};

const saveDeviceRename = async () => {
    const deviceId = deviceBeingRenamed.value?.id;
    const name = deviceRenameValue.value.trim();
    if (!deviceId || !name) {
        toast.add({ severity: 'warn', summary: 'Renommage', detail: 'Veuillez saisir un nom.', life: 3000 });
        return;
    }

    deviceRenameSaving.value = true;
    try {
        await renameDeviceApi(deviceId, name, token);
        await loadApprovedDevices();
        toast.add({ severity: 'success', summary: 'Appareils', detail: 'Nom mis à jour.', life: 2500 });
        closeDeviceRenameDialog();
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Renommage impossible'), life: 4500 });
    } finally {
        deviceRenameSaving.value = false;
    }
};

const openDeviceAccessLogsDialog = () => {
    deviceAccessLogsDialogVisible.value = true;
};

const confirmDeviceAction = (action, device, event) => {
    const deviceId = device?.id;
    if (!deviceId) return;

    const labels = {
        approve: 'approuver',
        reject: 'refuser',
        delete: 'supprimer'
    };

    confirm.require({
        target: event?.currentTarget,
        message: `Confirmer pour ${labels[action]} cet appareil pour tout le cabinet ?`,
        icon: 'pi pi-shield',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                if (action === 'approve') {
                    await approveDeviceApi(deviceId, token);
                } else if (action === 'reject') {
                    await rejectDeviceApi(deviceId, token);
                } else {
                    await deleteDeviceApi(deviceId, token);
                }

                await loadApprovedDevices();
                toast.add({ severity: 'success', summary: 'Appareils', detail: 'Mise à jour effectuée.', life: 2500 });
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Action impossible'), life: 4500 });
            }
        }
    });
};

const saveConsultationPolicyAction = async () => {
    if (!canAccessWorkflowSettings.value) return;
    savingStates.consultationPolicy = true;
    try {
        await saveGeneralSettings({
            requireMedecinOnConsultationCreation: consultationPolicy.requireMedecinOnConsultationCreation,
            allowReceptionConsultationQuickActions: consultationPolicy.allowReceptionConsultationQuickActions,
            allowReceptionQuickCloseConsultation: consultationPolicy.allowReceptionConsultationQuickActions,
            showReceptionQuickCloseButton: consultationPolicy.showReceptionQuickCloseButton,
            allowReceptionBypassMedecinPasswordOnQuickClose: consultationPolicy.showReceptionQuickCloseButton && consultationPolicy.allowReceptionBypassMedecinPasswordOnQuickClose,
            consultationPrice: Number(consultationPolicy.consultationPrice || 5000)
        }, token);
        toast.add({ severity: 'success', summary: 'Consultations & réception', detail: 'Paramètres enregistrés', life: 2500 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Sauvegarde impossible'), life: 3500 });
    } finally {
        savingStates.consultationPolicy = false;
    }
};

const saveOpeningHoursAction = async () => {
    if (!canAccessWorkflowSettings.value) return;
    savingStates.openingHours = true;
    try {
        const saved = await saveGeneralSettings({
            openingTime: openingHours.openingTime,
            closingTime: openingHours.closingTime
        }, token);
        openingHours.openingTime = saved.openingTime || openingHours.openingTime;
        openingHours.closingTime = saved.closingTime || openingHours.closingTime;
        toast.add({ severity: 'success', summary: 'Horaires d\'ouverture', detail: 'Horaires enregistrés', life: 2500 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Sauvegarde impossible'), life: 3500 });
    } finally {
        savingStates.openingHours = false;
    }
};

const saveCabinetIdentityAction = async () => {
    if (!canAccessWorkflowSettings.value) return;
    savingStates.cabinetIdentity = true;
    try {
        const saved = await saveGeneralSettings({
            smsCabinetName: String(cabinetIdentity.smsCabinetName || '').trim()
        }, token);
        cabinetIdentity.smsCabinetName = saved.smsCabinetName || cabinetIdentity.smsCabinetName;
        toast.add({ severity: 'success', summary: 'Identité & SMS', detail: 'Nom du centre enregistré', life: 2500 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Sauvegarde impossible'), life: 3500 });
    } finally {
        savingStates.cabinetIdentity = false;
    }
};

const saveMedecinPrivacyAction = async () => {
    if (!canAccessWorkflowSettings.value) return;
    savingStates.medecinPrivacy = true;
    try {
        await saveGeneralSettings({
            hidePatientDossierForMedecins: medecinPrivacy.hidePatientDossierForMedecins,
            hidePatientPhoneForMedecins: medecinPrivacy.hidePatientPhoneForMedecins
        }, token);
        toast.add({ severity: 'success', summary: 'Interface médecin', detail: 'Paramètres enregistrés', life: 2500 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Sauvegarde impossible'), life: 3500 });
    } finally {
        savingStates.medecinPrivacy = false;
    }
};

const saveClinicalFormAction = async () => {
    if (!canAccessWorkflowSettings.value) return;
    savingStates.clinicalForm = true;
    try {
        await saveGeneralSettings({
            ficheFormSimplifie: clinicalForm.ficheFormSimplifie,
            showDiagnosticPositifInConsultation: clinicalForm.showDiagnosticPositifInConsultation,
            examensTypes: normalizeLines(clinicalForm.examensTypesText),
            traitementTypes: normalizeLines(clinicalForm.traitementTypesText),
            allergyTypes: normalizeLines(clinicalForm.allergyTypesText),
            antecedentTypes: normalizeLines(clinicalForm.antecedentTypesText)
        }, token);
        toast.add({ severity: 'success', summary: 'Fiche clinique', detail: 'Paramètres enregistrés', life: 2500 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Sauvegarde impossible'), life: 3500 });
    } finally {
        savingStates.clinicalForm = false;
    }
};

const saveBillingPolicyAction = async () => {
    if (!canAccessWorkflowSettings.value) return;
    savingStates.billingPolicy = true;
    try {
        await saveGeneralSettings({
            paiementDirectAssurance: billingPolicy.paiementDirectAssurance,
            allowReceptionInvoiceModification: billingPolicy.allowReceptionInvoiceModification,
            allowConsultationPriceEditOnCreation: billingPolicy.allowConsultationPriceEditOnCreation,
            transactionMotifs: {
                revenue: normalizeLines(transactionMotifs.revenueText),
                expense: normalizeLines(transactionMotifs.expenseText)
            }
        }, token);
        toast.add({ severity: 'success', summary: 'Caisse & finances', detail: 'Paramètres enregistrés', life: 2500 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Sauvegarde impossible'), life: 3500 });
    } finally {
        savingStates.billingPolicy = false;
    }
};

const savePortalPatientSettingsAction = async () => {
    if (!canAccessWorkflowSettings.value) return;
    savingStates.portalSettings = true;
    try {
        await saveGeneralSettings({
            patientPortalEnabled: portalPatientConfig.patientPortalEnabled,
            patientPortalClosedMessage: String(portalPatientConfig.patientPortalClosedMessage || '').trim(),
            patientPortalBaseUrl: String(portalPatientConfig.patientPortalBaseUrl || '').trim(),
            cabinetShowcaseWebsiteUrl: String(portalPatientConfig.cabinetShowcaseWebsiteUrl || '').trim(),
            autoCreatePortalAccountOnPatientCreation: portalPatientConfig.autoCreatePortalAccountOnPatientCreation === true
        }, token);
        toast.add({ severity: 'success', summary: 'Portail patient', detail: 'Paramètres enregistrés', life: 2500 });
        await loadGeneralSettings(true);
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Sauvegarde impossible'), life: 3500 });
    } finally {
        savingStates.portalSettings = false;
    }
};

const createMissingPortalAccountsAction = async () => {
    if (!canAccessWorkflowSettings.value) return;
    savingStates.portalBulkCreate = true;
    try {
        const result = await createMissingPatientPortalAccounts(token);
        toast.add({
            severity: 'success',
            summary: 'Portail patient',
            detail: result?.message || `${result?.createdCount || 0} compte(s) créé(s).`,
            life: 3500
        });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Création de masse impossible'), life: 3500 });
    } finally {
        savingStates.portalBulkCreate = false;
    }
};

const openExternalUrl = (url) => {
    if (!url) return;
    window.open(url, '_blank', 'noopener,noreferrer');
};

const copyToClipboard = async (label, value) => {
    if (!value) {
        toast.add({ severity: 'warn', summary: 'Copie', detail: `${label} indisponible`, life: 2200 });
        return;
    }

    try {
        await navigator.clipboard.writeText(value);
        toast.add({ severity: 'success', summary: 'Copie', detail: `${label} copié`, life: 2200 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Copie', detail: 'Impossible de copier automatiquement', life: 2500 });
    }
};

const saveSoinsCatalogAction = async () => {
    if (!canAccessWorkflowSettings.value) return;
    savingStates.soinsCatalog = true;
    try {
        await saveGeneralSettings({ soinsList: normalizeLines(soinsCatalog.text) }, token);
        toast.add({ severity: 'success', summary: 'Catalogue des soins', detail: 'Paramètres enregistrés', life: 2500 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Sauvegarde impossible'), life: 3500 });
    } finally {
        savingStates.soinsCatalog = false;
    }
};

const buildLongResetChallenge = () => {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    const chunks = [];
    for (let i = 0; i < 6; i += 1) {
        let chunk = '';
        for (let j = 0; j < 5; j += 1) {
            chunk += alphabet[Math.floor(Math.random() * alphabet.length)];
        }
        chunks.push(chunk);
    }

    return chunks.join('-');
};

const openSecurityDialog = ({ mode, title, message, challenge = '', payload = {} }) => {
    securityDialog.mode = mode;
    securityDialog.title = title;
    securityDialog.message = message;
    securityDialog.password = '';
    securityDialog.challenge = challenge;
    securityDialog.challengeInput = '';
    securityDialog.payload = payload;
    securityDialog.visible = true;
};

const closeSecurityDialog = () => {
    const previousMode = securityDialog.mode;
    securityDialog.visible = false;
    securityDialog.mode = '';
    securityDialog.title = '';
    securityDialog.message = '';
    securityDialog.password = '';
    securityDialog.challenge = '';
    securityDialog.challengeInput = '';
    securityDialog.payload = {};

    if (previousMode === 'test-toggle') {
        testMode.enabled = persistedTestModeEnabled.value;
    }
};

const applyTestModeResponse = (response) => {
    testMode.enabled = response.testModeEnabled === true;
    persistedTestModeEnabled.value = testMode.enabled;
    testMode.snapshotCreatedAt = response.testModeSnapshotCreatedAt || null;
    testMode.lastPurgeAt = response.testModeLastPurgeAt || null;
};

const confirmSecurityDialog = async () => {
    const password = securityDialog.password.trim();
    if (!password) {
        toast.add({ severity: 'warn', summary: 'Confirmation', detail: 'Le mot de passe admin est requis.', life: 3000 });
        return;
    }

    if (securityDialog.mode === 'db-reset') {
        if (securityDialog.challengeInput.trim() !== securityDialog.challenge) {
            toast.add({ severity: 'error', summary: 'Sécurité reset', detail: 'La phrase de sécurité ne correspond pas.', life: 3500 });
            return;
        }
    }

    if (securityDialog.mode === 'test-toggle') {
        savingStates.testMode = true;
        try {
            const deleteTestData = isDisablingTestMode.value
                ? securityDialog.payload?.deleteTestData !== false
                : true;
            const response = await toggleTestMode({ enabled: testMode.enabled, password, deleteTestData }, token);
            applyTestModeResponse(response);
            toast.add({ severity: 'success', summary: 'Mode test', detail: response.message || 'Paramètre mis à jour', life: 3000 });
            closeSecurityDialog();
        } catch (error) {
            testMode.enabled = persistedTestModeEnabled.value;
            toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Action impossible'), life: 3500 });
        } finally {
            savingStates.testMode = false;
        }
        return;
    }

    if (securityDialog.mode === 'test-clean') {
        savingStates.testMode = true;
        try {
            const response = await cleanTestMode({ password }, token);
            applyTestModeResponse(response);
            toast.add({ severity: 'success', summary: 'Mode test', detail: response.message || 'Nettoyage effectué', life: 3000 });
            closeSecurityDialog();
        } catch (error) {
            toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Nettoyage impossible'), life: 3500 });
        } finally {
            savingStates.testMode = false;
        }
        return;
    }

    if (securityDialog.mode === 'db-export') {
        savingStates.databaseExport = true;
        try {
            const formats = securityDialog.payload?.formats || ['sql'];
            const response = await exportDatabase({ password, formats }, token);
            const sql = response?.files?.relativeSqlPath || 'n/a';
            const zip = response?.files?.relativeZipPath || 'n/a';
            const json = response?.files?.relativeJsonPath || 'n/a';

            const generatedFiles = [
                response?.files?.relativeSqlPath,
                response?.files?.relativeZipPath,
                response?.files?.relativeJsonPath,
            ].filter(Boolean);

            let filesToDownload = generatedFiles;
            if (backupDownloadMode.value === 'primary') {
                const preferredOrder = [
                    response?.files?.relativeZipPath,
                    response?.files?.relativeSqlPath,
                    response?.files?.relativeJsonPath,
                ].filter(Boolean);

                const primary = preferredOrder.find((path) => generatedFiles.includes(path));
                filesToDownload = primary ? [primary] : generatedFiles.slice(0, 1);
            }

            for (const file of filesToDownload) {
                const download = await downloadDatabaseExport({ file }, token);
                const blobUrl = window.URL.createObjectURL(download.blob);
                const link = document.createElement('a');
                link.href = blobUrl;
                link.download = download.filename || file.split('/').pop() || 'export.dat';
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(blobUrl);
            }

            toast.add({
                severity: 'success',
                summary: 'Sauvegarde créée',
                detail: `Fichiers téléchargés. SQL: ${sql} | ZIP: ${zip} | JSON: ${json}`,
                life: 6000
            });
            closeSecurityDialog();
        } catch (error) {
            toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Export impossible'), life: 3500 });
        } finally {
            savingStates.databaseExport = false;
        }
        return;
    }

    if (securityDialog.mode === 'db-reset') {
        savingStates.databaseReset = true;
        try {
            const response = await resetDatabase({ password }, token);
            toast.add({
                severity: 'success',
                summary: 'Réinitialisation terminée',
                detail: response?.message || 'Base réinitialisée avec sauvegarde préalable.',
                life: 5000
            });
            closeSecurityDialog();
        } catch (error) {
            toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Réinitialisation impossible'), life: 4000 });
        } finally {
            savingStates.databaseReset = false;
        }
    }
};

const saveTestModeAction = async () => {
    if (!canAccessWorkflowSettings.value) return;

    const isDisabling = persistedTestModeEnabled.value && !testMode.enabled;
    const isEnabling = !persistedTestModeEnabled.value && testMode.enabled;

    if (!isDisabling && !isEnabling) {
        toast.add({
            severity: 'info',
            summary: 'Mode test',
            detail: testMode.enabled ? 'Le mode test est déjà actif.' : 'Le mode test est déjà inactif.',
            life: 2500
        });
        return;
    }

    openSecurityDialog({
        mode: 'test-toggle',
        title: isDisabling ? 'Désactiver le mode test' : 'Activer le mode test',
        message: isDisabling
            ? 'Le mode test a été activé par erreur ? Choisissez si les données créées pendant les tests doivent être supprimées.'
            : 'Un snapshot de la base sera créé. Confirmez avec votre mot de passe admin.',
        payload: { deleteTestData: true }
    });
};

const cleanTestModeAction = async () => {
    if (!testMode.enabled) {
        toast.add({ severity: 'warn', summary: 'Mode test', detail: 'Le mode test doit être actif.', life: 2500 });
        return;
    }

    openSecurityDialog({
        mode: 'test-clean',
        title: 'Nettoyage des tests',
        message: 'Cette action restaure le snapshot du mode test puis regénère un nouveau snapshot. Confirmez avec votre mot de passe admin.'
    });
};

const exportDatabaseAction = async () => {
    const formats = [
        backupOptions.sql ? 'sql' : null,
        backupOptions.zip ? 'zip' : null,
        backupOptions.json ? 'json' : null,
    ].filter(Boolean);

    if (formats.length === 0) {
        toast.add({ severity: 'warn', summary: 'Export', detail: 'Sélectionnez au moins un format.', life: 2500 });
        return;
    }

    openSecurityDialog({
        mode: 'db-export',
        title: 'Confirmer export base de données',
        message: 'Entrez votre mot de passe admin pour lancer la sauvegarde/export.',
        payload: { formats }
    });
};

const resetDatabaseAction = async () => {
    if (!isSuperAdmin.value) {
        toast.add({ severity: 'error', summary: 'Accès refusé', detail: 'Action réservée au super-admin id=1.', life: 3000 });
        return;
    }

    openSecurityDialog({
        mode: 'db-reset',
        title: 'Réinitialisation complète - sécurité renforcée',
        message: 'Action irréversible: sauvegarde automatique puis suppression des données en conservant uniquement le super-admin id=1.',
        challenge: buildLongResetChallenge()
    });
};

const goToSmsPage = () => {
    router.push({ name: 'administration-api-sms' });
};

useGuidedTour({
    routeName: 'settings-apparence',
    isLoading: () => generalLoading.value && !generalSettingsLoaded.value,
    getStepContext: () => ({
        navigateToSection: async (category, sectionId) => {
            await scrollToSection(category, sectionId);
        }
    }),
    errorMessage: 'Impossible de lancer le tour guide sur les parametres.'
});

const currentThemeLabel = computed(() => themeOptions.value.find((option) => option.value === themeMode.value)?.label || 'Système');
const currentFontSizeLabel = computed(() => fontSizeOptions.value.find((option) => option.value === fontSize.value)?.label || 'Normal');
const currentSurfaceName = computed(() => layoutConfig.surface || (isDarkTheme.value ? 'zinc' : 'slate'));
const canAccessSmsSettings = computed(() => (auth.user?.roles || []).includes('ROLE_ADMIN'));
const normalizedPortalBaseUrl = computed(() => String(portalPatientConfig.patientPortalBaseUrl || '').replace(/\/$/, ''));
const portalLoginUrl = computed(() => normalizedPortalBaseUrl.value ? `${normalizedPortalBaseUrl.value}/login` : '');
const anonymousReviewUrl = computed(() => normalizedPortalBaseUrl.value ? `${normalizedPortalBaseUrl.value}/avis-anonyme` : '');
const normalizedShowcaseWebsiteUrl = computed(() => String(portalPatientConfig.cabinetShowcaseWebsiteUrl || '').trim());
const qrPortalLoginSrc = computed(() => {
    if (!portalLoginUrl.value) return '';
    return `https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=${encodeURIComponent(portalLoginUrl.value)}`;
});
const qrAnonymousReviewSrc = computed(() => {
    if (!anonymousReviewUrl.value) return '';
    return `https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=${encodeURIComponent(anonymousReviewUrl.value)}`;
});
const qrShowcaseWebsiteSrc = computed(() => {
    if (!normalizedShowcaseWebsiteUrl.value) return '';
    return `https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=${encodeURIComponent(normalizedShowcaseWebsiteUrl.value)}`;
});
const patientPortalQrPrintModel = computed(() => buildPatientPortalQrPrintModel({
    cabinetName: cabinetConfig.displayName,
    subtitle: 'Portail patient, avis anonymes et site vitrine',
    phone: cabinetConfig.cabinetPhone,
    portalLoginUrl: portalLoginUrl.value,
    anonymousReviewUrl: anonymousReviewUrl.value,
    showcaseWebsiteUrl: normalizedShowcaseWebsiteUrl.value
}));
const hasPrintablePortalQr = computed(() => {
    const entries = patientPortalQrPrintModel.value?.entries || {};
    return Object.values(entries).some((entry) => Boolean(entry?.url));
});

const printPatientPortalPoster = async () => {
    if (!hasPrintablePortalQr.value) {
        toast.add({ severity: 'warn', summary: 'Impression QR', detail: 'Aucune URL valide a imprimer.', life: 2600 });
        return;
    }
    await printComponent(
        PrintPatientPortalQrPoster,
        { data: patientPortalQrPrintModel.value },
        { title: 'Affiche QR portail patient', printDelay: 900 }
    );
};

const printPatientPortalSingleQr = async (entryKey) => {
    const entry = getPatientPortalQrPrintEntry(patientPortalQrPrintModel.value, entryKey);
    if (!entry || !entry.url) {
        toast.add({ severity: 'warn', summary: 'Impression QR', detail: 'URL indisponible pour ce QR code.', life: 2600 });
        return;
    }
    await printComponent(
        PrintPatientPortalQrSingle,
        { entry },
        { title: `QR ${entry.title}`, printDelay: 700 }
    );
};

watch(canAccessWorkflowSettings, async (allowed) => {
    if (!allowed && ADMIN_CATEGORIES.includes(activeCategory.value)) {
        activeCategory.value = 'appearance';
        activeSubSection.value = 'overview';
    }
    if (!allowed) {
        activeSettingsTab.value = 'appearance';
    }
    await nextTick();
    setupObserver();
}, { immediate: true });

watch(settingsDisplayMode, async () => {
    if (settingsDisplayMode.value === 'tabs') {
        if (ADMIN_CATEGORIES.includes(activeCategory.value) && canAccessWorkflowSettings.value) {
            selectSettingsTab(`${activeCategory.value}:${activeSubSection.value}`);
        } else {
            selectSettingsTab('appearance');
        }
    }
    await nextTick();
    setupObserver();
});

watch(
    () => consultationPolicy.showReceptionQuickCloseButton,
    (enabled) => {
        if (!enabled) {
            consultationPolicy.allowReceptionBypassMedecinPasswordOnQuickClose = false;
        }
    }
);

onMounted(async () => {
    if (canAccessWorkflowSettings.value) {
        await loadGeneralSettings(true);
    } else {
        generalSettingsLoaded.value = true;
    }
    setupObserver();
});

onBeforeUnmount(() => {
    observer?.disconnect();
});
</script>

<template>
    <div class="settings-container">
        <ConfirmPopup />
        <!-- Header -->
        <div class="settings-header">
            <div class="settings-header-content">
                <div class="settings-header-info">
                    <Badge value="Paramètres" class="settings-badge" />
                    <h1 class="settings-header-title">{{ cabinetConfig.settingsTitle }}</h1>
                    <p class="settings-header-description">
                        {{ cabinetConfig.settingsDescription }}
                    </p>
                </div>
                <div class="settings-header-actions">
                    <Button
                        v-if="canAccessSmsSettings"
                        label="API SMS"
                        icon="pi pi-send"
                        severity="secondary"
                        outlined
                        @click="goToSmsPage"
                    />
                </div>
            </div>
        </div>

        <div class="settings-body" data-tour="settings-appearance.main">
            <!-- Sidebar Navigation -->
            <aside v-if="settingsDisplayMode === 'page'" class="settings-sidebar" data-tour="settings-appearance.navigation">
                <div class="settings-nav-card">
                    <nav class="settings-nav">
						<div v-for="(category, key) in visibleNavigation" :key="key" class="settings-nav-group">
                            <div class="settings-nav-group-header">
                                <i :class="category.icon" class="settings-nav-icon"></i>
                                <span>{{ category.label }}</span>
                            </div>
                            <div class="settings-nav-items">
                                <button
                                    v-for="section in category.sections"
                                    :key="section.id"
                                    class="settings-nav-item"
                                    :class="{ active: activeCategory === key && activeSubSection === section.id }"
                                    @click="scrollToSection(key, section.id)"
                                >
                                    <i :class="section.icon" class="settings-nav-item-icon"></i>
                                    <span>{{ section.label }}</span>
                                </button>
                            </div>
                        </div>
                    </nav>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="settings-main">
                <div v-if="generalLoading && !generalSettingsLoaded" class="settings-loading">
                    <div v-for="i in 3" :key="i" class="settings-loading-card"></div>
                </div>

                <div v-else-if="loadErrorMessage" class="flex min-h-[320px] flex-col items-center justify-center gap-4 rounded-2xl border border-amber-200/70 bg-amber-50/70 p-8 dark:border-amber-800/70 dark:bg-amber-950/20">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                        <i class="pi pi-exclamation-triangle text-2xl"></i>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-semibold text-amber-800 dark:text-amber-200">Chargement interrompu</p>
                        <p class="text-sm text-amber-700/90 dark:text-amber-300/90">{{ loadErrorMessage }}</p>
                    </div>
                    <Button icon="pi pi-refresh" label="Réessayer" severity="warning" @click="retryLoadSettings" />
                </div>

                <div v-else class="settings-content">
                    <div v-if="settingsDisplayMode === 'tabs'" class="settings-tabs-nav settings-tabs-global">
                        <Tabs :value="activeSettingsTab" @update:value="selectSettingsTab">
                            <TabList class="settings-tab-list">
                                <Tab v-for="tabItem in tabModeSections" :key="tabItem.id" :value="tabItem.id">
                                    <span class="settings-tab-label">
                                        <i :class="tabItem.icon"></i>
                                        <span>{{ tabItem.label }}</span>
                                    </span>
                                </Tab>
                            </TabList>
                        </Tabs>
                    </div>

                    <!-- Appearance Section -->
                    <div v-show="isAppearanceVisible" class="settings-category">
                        <div class="settings-category-header">
                            <div class="settings-category-title">
                                <i class="pi pi-palette"></i>
                                <h2>Apparence</h2>
                            </div>
                            <Button label="Sauvegarder" icon="pi pi-save" outlined size="small" @click="saveAppearance" />
                        </div>

                        <!-- Overview -->
                        <div id="appearance-overview" class="settings-section" data-tour="settings-appearance.theme">
                            <div class="settings-section-header">
                                <h3>Aperçu visuel</h3>
                                <p class="settings-section-description">Vue d'ensemble de votre configuration actuelle</p>
                            </div>
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-icon"><i class="pi pi-sun"></i></div>
                                    <div class="stat-info">
                                        <span class="stat-label">Mode</span>
                                        <strong class="stat-value">{{ currentThemeLabel }}</strong>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon"><i class="pi-palette"></i></div>
                                    <div class="stat-info">
                                        <span class="stat-label">Preset</span>
                                        <strong class="stat-value">{{ preset }}</strong>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon"><i class="pi pi-font"></i></div>
                                    <div class="stat-info">
                                        <span class="stat-label">Typographie</span>
                                        <strong class="stat-value">{{ fontFamily }} • {{ currentFontSizeLabel }}</strong>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon"><i class="pi pi-palette"></i></div>
                                    <div class="stat-info">
                                        <span class="stat-label">Couleur surface</span>
                                        <strong class="stat-value">{{ currentSurfaceName }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Theme -->
                        <div id="appearance-theme" class="settings-section">
                            <div class="settings-section-header">
                                <h3>Mode d'affichage</h3>
                                <p class="settings-section-description">Choisissez entre mode clair, sombre ou automatique</p>
                            </div>
                            <div class="settings-card">
                                <SelectButton
                                    v-model="themeMode"
                                    :options="themeOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    :allowEmpty="false"
                                />
                            </div>
                        </div>

                        <!-- Colors -->
                        <div id="appearance-colors" class="settings-section" data-tour="settings-appearance.primary">
                            <div class="settings-section-header">
                                <h3>Couleurs</h3>
                                <p class="settings-section-description">Personnalisez la palette de couleurs de l'application</p>
                            </div>
                            <div class="settings-card">
                                <div class="color-section">
                                    <label class="color-label">Preset</label>
                                    <SelectButton
                                        v-model="preset"
                                        :options="presetOptions"
                                        :allowEmpty="false"
                                        @change="onPresetChange"
                                    />
                                </div>
                                <Divider />
                                <div class="color-section">
                                    <label class="color-label">Couleur principale</label>
                                    <div class="swatch-grid">
                                        <button
                                            v-for="primaryColor in primaryColors"
                                            :key="primaryColor.name"
                                            type="button"
                                            class="swatch"
                                            :class="{ active: layoutConfig.primary === primaryColor.name }"
                                            :title="primaryColor.name"
                                            :style="{ backgroundColor: primaryColor.palette['500'] }"
                                            @click="updateColors('primary', primaryColor)"
                                        />
                                    </div>
                                </div>
                                <Divider />
                                <div class="color-section">
                                    <label class="color-label">Couleur de surface</label>
                                    <div class="swatch-grid">
                                        <button
                                            v-for="surface in surfaces"
                                            :key="surface.name"
                                            type="button"
                                            class="swatch"
                                            :class="{ active: currentSurfaceName === surface.name }"
                                            :title="surface.name"
                                            :style="{ backgroundColor: surface.palette['500'] }"
                                            @click="updateColors('surface', surface)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Typography -->
                        <div id="appearance-typography" class="settings-section" data-tour="settings-appearance.font-family">
                            <div class="settings-section-header">
                                <h3>Typographie</h3>
                                <p class="settings-section-description">Police et taille du texte</p>
                            </div>
                            <div class="settings-card">
                                <div class="typography-controls">
                                    <div class="control-group">
                                        <label>Police</label>
                                        <Select v-model="fontFamily" :options="fontFamilyOptions" class="control-select" />
                                    </div>
                                    <div class="control-group">
                                        <label>Taille</label>
                                        <SelectButton v-model="fontSize" :options="fontSizeOptions" optionLabel="label" optionValue="value" :allowEmpty="false" />
                                    </div>
                                </div>
                                <Divider />
                                <div class="preview-area">
                                    <p class="preview-label">Aperçu</p>
                                    <p class="preview-title">DentalSoft Dashboard</p>
                                    <p class="preview-text">Suivi des rendez-vous, consultations, caisse et administration dans une interface cohérente et lisible.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Layout -->
                        <div id="appearance-layout" class="settings-section" data-tour="settings-appearance.layout">
                            <div class="settings-section-header">
                                <h3>Disposition</h3>
                                <p class="settings-section-description">Comportement du menu de navigation</p>
                            </div>
                            <div class="settings-card">
                                <SelectButton
                                    v-model="menuMode"
                                    :options="menuModeOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    :allowEmpty="false"
                                    @change="onMenuModeChange"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Cabinet -->
                    <div v-if="canAccessWorkflowSettings" v-show="isCabinetVisible" class="settings-category">
                        <div class="settings-category-header">
                            <div class="settings-category-title">
                                <i class="pi pi-briefcase"></i>
                                <h2>Cabinet</h2>
                            </div>
                        </div>

                        <!-- Identité & SMS -->
                        <div v-show="isSectionVisible('cabinet', 'identity')" id="cabinet-identity" class="settings-section" data-tour="settings-cabinet.identity">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Identité & SMS</h3>
                                    <p class="settings-section-description">Nom affiché dans les SMS automatiques (rappels RDV, reçus, factures, création patient)</p>
                                </div>
                                <Button
                                    label="Enregistrer"
                                    icon="pi pi-save"
                                    :loading="savingStates.cabinetIdentity"
                                    @click="saveCabinetIdentityAction"
                                />
                            </div>
                            <div class="settings-card">
                                <div class="field-group">
                                    <label>Nom du centre (SMS)</label>
                                    <InputText
                                        v-model="cabinetIdentity.smsCabinetName"
                                        class="w-full"
                                        placeholder="Ex: CENTRE DENTAIRE MASSAMAN"
                                    />
                                    <span class="field-helper">Utilisé tel quel dans les messages. Incluez « Cabinet », « Centre » ou autre selon votre enseigne.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Consultations & réception -->
                        <div v-show="isSectionVisible('cabinet', 'consultations')" id="cabinet-consultations" class="settings-section" data-tour="settings-cabinet.consultations">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Consultations & réception</h3>
                                    <p class="settings-section-description">Règles de création de consultation, actions rapides réception et tarif par défaut</p>
                                </div>
                                <Button
                                    label="Enregistrer"
                                    icon="pi pi-save"
                                    :loading="savingStates.consultationPolicy"
                                    @click="saveConsultationPolicyAction"
                                />
                            </div>
                            <div class="settings-card">
                                <div class="toggle-group">
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Médecin requis à la création</label>
                                            <span class="toggle-description">La consultation peut être créée sans médecin assigné si désactivé</span>
                                        </div>
                                        <ToggleSwitch v-model="consultationPolicy.requireMedecinOnConsultationCreation" />
                                    </div>
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Actions rapides pour réceptionniste</label>
                                            <span class="toggle-description">Active ou masque les boutons d'actions rapides de consultation côté réception</span>
                                        </div>
                                        <ToggleSwitch v-model="consultationPolicy.allowReceptionConsultationQuickActions" />
                                    </div>
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Afficher la clôturation rapide pour la réception</label>
                                            <span class="toggle-description">Affiche ou masque le bouton de clôture rapide pour les secrétaires dans focus et l'historique</span>
                                        </div>
                                        <ToggleSwitch v-model="consultationPolicy.showReceptionQuickCloseButton" />
                                    </div>
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Bypass code médecin en clôture rapide</label>
                                            <span class="toggle-description">Si activé, la réception peut clôturer rapidement sans validation du code médecin</span>
                                        </div>
                                        <ToggleSwitch v-model="consultationPolicy.allowReceptionBypassMedecinPasswordOnQuickClose" :disabled="!consultationPolicy.showReceptionQuickCloseButton" />
                                    </div>
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Prix consultation par défaut</label>
                                            <span class="toggle-description">Montant appliqué aux nouvelles consultations payantes</span>
                                        </div>
                                        <InputNumber
                                            v-model="consultationPolicy.consultationPrice"
                                            mode="decimal"
                                            locale="fr-FR"
                                            :min="1"
                                            :minFractionDigits="0"
                                            :maxFractionDigits="2"
                                            inputClass="w-40" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Horaires d'ouverture -->
                        <div v-show="isSectionVisible('cabinet', 'opening-hours')" id="cabinet-opening-hours" class="settings-section" data-tour="settings-cabinet.opening-hours">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Horaires d'ouverture</h3>
                                    <p class="settings-section-description">Plage horaire affichée dans l'agenda (vue hebdomadaire et journalière)</p>
                                </div>
                                <Button
                                    label="Enregistrer"
                                    icon="pi pi-save"
                                    :loading="savingStates.openingHours"
                                    @click="saveOpeningHoursAction"
                                />
                            </div>
                            <div class="settings-card">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="field-group">
                                        <label>Heure d'ouverture</label>
                                        <DatePicker
                                            v-model="openingTimeModel"
                                            timeOnly
                                            hourFormat="24"
                                            showIcon
                                            iconDisplay="input"
                                            class="w-full"
                                        />
                                    </div>
                                    <div class="field-group">
                                        <label>Heure de fermeture</label>
                                        <DatePicker
                                            v-model="closingTimeModel"
                                            timeOnly
                                            hourFormat="24"
                                            showIcon
                                            iconDisplay="input"
                                            class="w-full"
                                        />
                                    </div>
                                </div>
                                <span class="field-helper mt-2 block">L'heure d'ouverture doit être antérieure à l'heure de fermeture (défaut 08:00 – 18:00).</span>
                            </div>
                        </div>

                        <!-- Interface médecin -->
                        <div v-show="isSectionVisible('cabinet', 'medecin-privacy')" id="cabinet-medecin-privacy" class="settings-section">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Interface médecin</h3>
                                    <p class="settings-section-description">Contrôle de la visibilité des informations patient pour les médecins non-admin</p>
                                </div>
                                <Button
                                    label="Enregistrer"
                                    icon="pi pi-save"
                                    :loading="savingStates.medecinPrivacy"
                                    @click="saveMedecinPrivacyAction"
                                />
                            </div>
                            <div class="settings-card">
                                <div class="toggle-group">
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Masquer le dossier patient aux médecins</label>
                                            <span class="toggle-description">Le dossier patient et les redirections associées sont masqués aux médecins non-admin</span>
                                        </div>
                                        <ToggleSwitch v-model="medecinPrivacy.hidePatientDossierForMedecins" />
                                    </div>
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Masquer les numéros des patients aux médecins</label>
                                            <span class="toggle-description">Les numéros de téléphone patients sont masqués dans l'interface médecin</span>
                                        </div>
                                        <ToggleSwitch v-model="medecinPrivacy.hidePatientPhoneForMedecins" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fiche clinique -->
                        <div v-show="isSectionVisible('cabinet', 'clinical-form')" id="cabinet-clinical-form" class="settings-section">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Fiche clinique</h3>
                                    <p class="settings-section-description">Formulaire simplifié et listes de référence pour la synthèse clinique</p>
                                </div>
                                <Button
                                    label="Enregistrer"
                                    icon="pi pi-save"
                                    :loading="savingStates.clinicalForm"
                                    @click="saveClinicalFormAction"
                                />
                            </div>
                            <div class="settings-card">
                                <div class="toggle-group">
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Formulaire simplifié de fiche consultation</label>
                                            <span class="toggle-description">Active une vue condensée « Synthèse clinique » pour la fiche consultation</span>
                                        </div>
                                        <ToggleSwitch v-model="clinicalForm.ficheFormSimplifie" />
                                    </div>
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Diagnostic positif dans la consultation en cours</label>
                                            <span class="toggle-description">Affiche un raccourci vers le diagnostic positif à côté de la note de séance</span>
                                        </div>
                                        <ToggleSwitch v-model="clinicalForm.showDiagnosticPositifInConsultation" />
                                    </div>
                                </div>
                                <Divider />
                                <div class="two-columns">
                                    <div class="field-group">
                                        <label>Types d'examens (synthèse)</label>
                                        <Textarea
                                            v-model="clinicalForm.examensTypesText"
                                            rows="5"
                                            autoResize
                                            placeholder="Un type d'examen par ligne"
                                        />
                                    </div>
                                    <div class="field-group">
                                        <label>Types de traitements (synthèse)</label>
                                        <Textarea
                                            v-model="clinicalForm.traitementTypesText"
                                            rows="5"
                                            autoResize
                                            placeholder="Un type de traitement par ligne"
                                        />
                                    </div>
                                    <div class="field-group">
                                        <label>Types d'allergies</label>
                                        <Textarea
                                            v-model="clinicalForm.allergyTypesText"
                                            rows="4"
                                            autoResize
                                            placeholder="Un type d'allergie par ligne"
                                        />
                                    </div>
                                    <div class="field-group">
                                        <label>Types d'antécédents</label>
                                        <Textarea
                                            v-model="clinicalForm.antecedentTypesText"
                                            rows="4"
                                            autoResize
                                            placeholder="Un type d'antécédent par ligne"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Caisse & finances -->
                        <div v-show="isSectionVisible('cabinet', 'billing')" id="cabinet-billing" class="settings-section" data-tour="settings-cabinet.billing">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Caisse & finances</h3>
                                    <p class="settings-section-description">Comportement assurance côté caisse et motifs de transaction</p>
                                </div>
                                <Button
                                    label="Enregistrer"
                                    icon="pi pi-save"
                                    :loading="savingStates.billingPolicy"
                                    @click="saveBillingPolicyAction"
                                />
                            </div>
                            <div class="settings-card">
                                <div class="toggle-group">
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Paiement direct assurance</label>
                                            <span class="toggle-description">La part assurance crée un paiement immédiat</span>
                                        </div>
                                        <ToggleSwitch v-model="billingPolicy.paiementDirectAssurance" />
                                    </div>
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Modification de facture par les secrétaires</label>
                                            <span class="toggle-description">Autorise les réceptionnistes à modifier les factures sans paiement dans l'historique, la caisse et le mode focus</span>
                                        </div>
                                        <ToggleSwitch v-model="billingPolicy.allowReceptionInvoiceModification" />
                                    </div>
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Prix de consultation modifiable à la création</label>
                                            <span class="toggle-description">Affiche un champ pour modifier le prix de la consultation payante lors de sa création</span>
                                        </div>
                                        <ToggleSwitch v-model="billingPolicy.allowConsultationPriceEditOnCreation" />
                                    </div>
                                </div>
                                <Divider />
                                <div class="two-columns">
                                    <div class="field-group">
                                        <label>Motifs de revenus</label>
                                        <Textarea
                                            v-model="transactionMotifs.revenueText"
                                            rows="8"
                                            autoResize
                                            placeholder="Saisissez un motif par ligne"
                                        />
                                        <span class="field-helper">Un motif par ligne</span>
                                    </div>
                                    <div class="field-group">
                                        <label>Motifs de dépenses</label>
                                        <Textarea
                                            v-model="transactionMotifs.expenseText"
                                            rows="8"
                                            autoResize
                                            placeholder="Saisissez un motif par ligne"
                                        />
                                        <span class="field-helper">Un motif par ligne</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Catalogue des soins -->
                        <div v-show="isSectionVisible('cabinet', 'soins-catalog')" id="cabinet-soins-catalog" class="settings-section">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Catalogue des soins</h3>
                                    <p class="settings-section-description">Liste des actes proposés dans les consultations et la facturation</p>
                                </div>
                                <Button
                                    label="Enregistrer"
                                    icon="pi pi-save"
                                    :loading="savingStates.soinsCatalog"
                                    @click="saveSoinsCatalogAction"
                                />
                            </div>
                            <div class="settings-card">
                                <div class="field-group">
                                    <label>Soins proposés</label>
                                    <Textarea
                                        v-model="soinsCatalog.text"
                                        rows="12"
                                        autoResize
                                        placeholder="Saisissez un soin par ligne"
                                    />
                                    <span class="field-helper">Un soin par ligne. Utilisé dans les actes posés et la modification de facture.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Portail patient -->
                    <div v-if="canAccessWorkflowSettings" v-show="isPortalVisible" class="settings-category">
                        <div class="settings-category-header">
                            <div class="settings-category-title">
                                <i class="pi pi-mobile"></i>
                                <h2>Portail patient</h2>
                            </div>
                        </div>

                        <div v-show="isSectionVisible('portal', 'portal-settings')" id="portal-portal-settings" class="settings-section" data-tour="settings-portal.portal-settings">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Configuration portail patient</h3>
                                    <p class="settings-section-description">Activation, URLs, création de comptes et QR codes pour le portail patient</p>
                                </div>
                                <div class="settings-inline-actions">
                                    <Button
                                        label="Imprimer affiche QR"
                                        icon="pi pi-print"
                                        severity="secondary"
                                        outlined
                                        :disabled="!hasPrintablePortalQr"
                                        @click="printPatientPortalPoster"
                                    />
                                    <Button
                                        label="Enregistrer"
                                        icon="pi pi-save"
                                        :loading="savingStates.portalSettings"
                                        @click="savePortalPatientSettingsAction"
                                    />
                                </div>
                            </div>
                            <div class="settings-card">
                                <div class="toggle-group">
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Portail patient actif</label>
                                            <span class="toggle-description">Si désactivé, la connexion patient est bloquée et le message ci-dessous s'affiche</span>
                                        </div>
                                        <ToggleSwitch v-model="portalPatientConfig.patientPortalEnabled" />
                                    </div>
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Créer un compte automatiquement à la création client</label>
                                            <span class="toggle-description">Crée immédiatement un compte portail patient avec le mot de passe par défaut lors de l'ajout d'un nouveau patient.</span>
                                        </div>
                                        <ToggleSwitch v-model="portalPatientConfig.autoCreatePortalAccountOnPatientCreation" />
                                    </div>
                                </div>
                                <Divider />
                                <div class="two-columns">
                                    <div class="field-group">
                                        <label>Message de fermeture du portail</label>
                                        <Textarea
                                            v-model="portalPatientConfig.patientPortalClosedMessage"
                                            rows="4"
                                            autoResize
                                            placeholder="Message affiché au patient quand le portail est fermé"
                                        />
                                    </div>
                                    <div class="field-group">
                                        <label>Domaine du frontend patient</label>
                                        <InputText
                                            v-model="portalPatientConfig.patientPortalBaseUrl"
                                            placeholder="https://patient.votrecabinet.com"
                                        />
                                        <span class="field-helper">Utilisé pour générer les QR de connexion et d'avis anonymes.</span>
                                    </div>
                                    <div class="field-group">
                                        <label>URL site vitrine du cabinet</label>
                                        <InputText
                                            v-model="portalPatientConfig.cabinetShowcaseWebsiteUrl"
                                            placeholder="https://www.votrecabinet.com"
                                        />
                                        <div class="settings-inline-actions">
                                            <Button
                                                label="Ouvrir"
                                                icon="pi pi-external-link"
                                                text
                                                @click="openExternalUrl(portalPatientConfig.cabinetShowcaseWebsiteUrl)"
                                            />
                                            <Button
                                                label="Copier"
                                                icon="pi pi-copy"
                                                text
                                                @click="copyToClipboard('URL vitrine', portalPatientConfig.cabinetShowcaseWebsiteUrl)"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <Divider />
                                <div class="rounded-xl border border-surface-200 bg-surface-50 p-4 dark:border-surface-700 dark:bg-surface-900/40">
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                        <div>
                                            <label class="font-semibold text-surface-900 dark:text-surface-50">Créer les comptes patients manquants</label>
                                            <p class="mt-1 text-sm text-surface-600 dark:text-surface-300">Lance une création automatique pour tous les patients actifs qui n'ont pas encore de compte portail.</p>
                                        </div>
                                        <Button
                                            label="Créer les comptes manquants"
                                            icon="pi pi-users"
                                            severity="secondary"
                                            :loading="savingStates.portalBulkCreate"
                                            @click="createMissingPortalAccountsAction"
                                        />
                                    </div>
                                </div>
                                <Divider />
                                <div class="two-columns">
                                    <div class="field-group">
                                        <label>QR connexion patient</label>
                                        <div class="settings-inline-actions">
                                            <Button
                                                label="Copier URL"
                                                icon="pi pi-copy"
                                                size="small"
                                                text
                                                @click="copyToClipboard('URL connexion', portalLoginUrl)"
                                            />
                                            <Button
                                                label="Ouvrir"
                                                icon="pi pi-external-link"
                                                size="small"
                                                text
                                                @click="openExternalUrl(portalLoginUrl)"
                                            />
                                            <Button
                                                label="Imprimer"
                                                icon="pi pi-print"
                                                size="small"
                                                text
                                                :disabled="!portalLoginUrl"
                                                @click="printPatientPortalSingleQr('portal')"
                                            />
                                        </div>
                                        <p class="field-helper">{{ portalLoginUrl || 'Renseignez le domaine patient pour générer le QR.' }}</p>
                                        <img v-if="qrPortalLoginSrc" :src="qrPortalLoginSrc" alt="QR connexion patient" class="settings-qr" />
                                    </div>
                                    <div class="field-group">
                                        <label>QR avis anonyme</label>
                                        <div class="settings-inline-actions">
                                            <Button
                                                label="Copier URL"
                                                icon="pi pi-copy"
                                                size="small"
                                                text
                                                @click="copyToClipboard('URL avis anonyme', anonymousReviewUrl)"
                                            />
                                            <Button
                                                label="Ouvrir"
                                                icon="pi pi-external-link"
                                                size="small"
                                                text
                                                @click="openExternalUrl(anonymousReviewUrl)"
                                            />
                                            <Button
                                                label="Imprimer"
                                                icon="pi pi-print"
                                                size="small"
                                                text
                                                :disabled="!anonymousReviewUrl"
                                                @click="printPatientPortalSingleQr('review')"
                                            />
                                        </div>
                                        <p class="field-helper">{{ anonymousReviewUrl || 'Renseignez le domaine patient pour générer le QR.' }}</p>
                                        <img v-if="qrAnonymousReviewSrc" :src="qrAnonymousReviewSrc" alt="QR avis anonyme" class="settings-qr" />
                                    </div>
                                    <div class="field-group">
                                        <label>QR site vitrine</label>
                                        <div class="settings-inline-actions">
                                            <Button
                                                label="Copier URL"
                                                icon="pi pi-copy"
                                                size="small"
                                                text
                                                @click="copyToClipboard('URL vitrine', normalizedShowcaseWebsiteUrl)"
                                            />
                                            <Button
                                                label="Ouvrir"
                                                icon="pi pi-external-link"
                                                size="small"
                                                text
                                                @click="openExternalUrl(normalizedShowcaseWebsiteUrl)"
                                            />
                                            <Button
                                                label="Imprimer"
                                                icon="pi pi-print"
                                                size="small"
                                                text
                                                :disabled="!normalizedShowcaseWebsiteUrl"
                                                @click="printPatientPortalSingleQr('showcase')"
                                            />
                                        </div>
                                        <p class="field-helper">{{ normalizedShowcaseWebsiteUrl || "Renseignez l'URL du site vitrine pour générer le QR." }}</p>
                                        <img v-if="qrShowcaseWebsiteSrc" :src="qrShowcaseWebsiteSrc" alt="QR site vitrine" class="settings-qr" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Administration -->
                    <div v-if="canAccessWorkflowSettings" v-show="isAdministrationVisible" class="settings-category">
                        <div class="settings-category-header">
                            <div class="settings-category-title">
                                <i class="pi pi-shield"></i>
                                <h2>Administration</h2>
                            </div>
                        </div>

                        <!-- Appareils autorisés -->
                        <div v-show="isSectionVisible('administration', 'devices')" id="administration-devices" class="settings-section" data-tour="settings-administration.devices">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Appareils autorisés</h3>
                                    <p class="settings-section-description">Approbation automatique et gestion des appareils connectés au cabinet</p>
                                </div>
                                <Button
                                    label="Enregistrer"
                                    icon="pi pi-save"
                                    :loading="savingStates.devicePolicy"
                                    @click="saveDevicePolicyAction"
                                />
                            </div>
                            <div class="settings-card">
                                <div class="toggle-group">
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Approbation automatique</label>
                                            <span class="toggle-description">Active l'approbation automatique des nouveaux appareils. Sans cette option, seul le premier appareil est autorise automatiquement ; les suivants necessitent une validation manuelle.</span>
                                        </div>
                                        <ToggleSwitch v-model="devicePolicy.autoApproveDevices" />
                                    </div>
                                </div>
                            </div>

                            <div class="settings-card mt-4">
                                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                                    <div>
                                        <h4 class="text-base font-semibold m-0">Appareils du cabinet</h4>
                                        <p class="settings-section-description m-0 mt-1">
                                            Un appareil approuvé autorise la connexion de tous les comptes utilisateurs.
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Tag :value="`${deviceStats.approved || 0} approuvés`" severity="success" />
                                        <Tag v-if="(deviceStats.pending || 0) > 0" :value="`${deviceStats.pending} en attente`" severity="warning" />
                                        <Button
                                            label="Journal d'accès"
                                            icon="pi pi-history"
                                            severity="secondary"
                                            outlined
                                            :loading="devicesLoading"
                                            @click="openDeviceAccessLogsDialog"
                                        />
                                        <Button icon="pi pi-refresh" text severity="secondary" :loading="devicesLoading" @click="loadApprovedDevices" />
                                    </div>
                                </div>

                                <div v-if="devicesLoading && approvedDevices.length === 0" class="device-cards-grid">
                                    <div v-for="i in 3" :key="i" class="device-card device-card-skeleton"></div>
                                </div>
                                <div v-else-if="approvedDevices.length === 0" class="device-cards-empty">
                                    <i class="pi pi-desktop"></i>
                                    <p>Aucun appareil enregistré.</p>
                                </div>
                                <div v-else class="device-cards-grid">
                                    <article
                                        v-for="device in approvedDevices"
                                        :key="device.id"
                                        class="device-card"
                                        :class="`device-card--${device.status || 'pending'}`"
                                    >
                                        <div class="device-card-header">
                                            <div class="device-card-icon">
                                                <i :class="formatDeviceTypeIcon(device.deviceType)"></i>
                                            </div>
                                            <div class="device-card-title">
                                                <h5 class="m-0">{{ getDeviceDisplayName(device) }}</h5>
                                                <span v-if="device.customName && device.deviceName" class="device-card-subtitle">{{ device.deviceName }}</span>
                                                <span class="device-card-type">{{ device.deviceType || 'Type inconnu' }}</span>
                                            </div>
                                            <Tag
                                                :value="formatDeviceStatusLabel(device.status)"
                                                :severity="formatDeviceStatusSeverity(device.status)"
                                            />
                                        </div>
                                        <div class="device-card-body">
                                            <div class="device-card-meta">
                                                <span class="device-card-meta-label">IP</span>
                                                <span>{{ device.ipAddress || '-' }}</span>
                                            </div>
                                            <div class="device-card-meta">
                                                <span class="device-card-meta-label">Première connexion</span>
                                                <span>{{ device.requestedBy || '-' }}</span>
                                            </div>
                                            <div class="device-card-meta">
                                                <span class="device-card-meta-label">Dernière activité</span>
                                                <span>{{ formatDeviceDate(device.lastSeenAt) }}</span>
                                            </div>
                                        </div>
                                        <div class="device-card-actions">
                                            <Button
                                                label="Renommer"
                                                icon="pi pi-pencil"
                                                severity="secondary"
                                                size="small"
                                                outlined
                                                @click="openDeviceRenameDialog(device)"
                                            />
                                            <Button
                                                v-if="device.status === 'pending'"
                                                label="Approuver"
                                                icon="pi pi-check"
                                                severity="success"
                                                size="small"
                                                outlined
                                                @click="confirmDeviceAction('approve', device, $event)"
                                            />
                                            <Button
                                                v-if="device.status === 'pending'"
                                                label="Refuser"
                                                icon="pi pi-times"
                                                severity="warning"
                                                size="small"
                                                outlined
                                                @click="confirmDeviceAction('reject', device, $event)"
                                            />
                                            <Button
                                                v-if="device.status !== 'pending'"
                                                label="Supprimer"
                                                icon="pi pi-trash"
                                                severity="danger"
                                                size="small"
                                                text
                                                @click="confirmDeviceAction('delete', device, $event)"
                                            />
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </div>

                        <!-- Maintenance système -->
                        <div v-show="isSectionVisible('administration', 'system-maintenance')" id="administration-system-maintenance" class="settings-section" data-tour="settings-administration.system-maintenance">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Maintenance système</h3>
                                    <p class="settings-section-description">Mode test volatil, sauvegarde/export et réinitialisation de la base de données</p>
                                </div>
                            </div>

                            <div class="settings-card">
                                <div class="settings-section-header mb-0">
                                    <div>
                                        <h4 class="text-base font-semibold m-0">Mode test</h4>
                                        <p class="settings-section-description m-0 mt-1">Snapshot à l'activation, choix de suppression ou conservation des données à la désactivation</p>
                                    </div>
                                    <Button
                                        label="Appliquer"
                                        icon="pi pi-save"
                                        :loading="savingStates.testMode"
                                        @click="saveTestModeAction"
                                    />
                                </div>
                                <div class="toggle-group mt-4">
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Activer le mode test global</label>
                                            <span class="toggle-description">Toutes les écritures BD deviennent temporaires jusqu'à restauration snapshot</span>
                                        </div>
                                        <ToggleSwitch v-model="testMode.enabled" />
                                    </div>
                                </div>
                                <Divider />
                                <div class="two-columns">
                                    <div class="field-group">
                                        <label>Snapshot initial</label>
                                        <p class="field-helper">{{ testMode.snapshotCreatedAt || 'Aucun snapshot enregistré' }}</p>
                                    </div>
                                    <div class="field-group">
                                        <label>Dernier nettoyage</label>
                                        <p class="field-helper">{{ testMode.lastPurgeAt || 'Jamais' }}</p>
                                    </div>
                                </div>
                                <Divider />
                                <Button
                                    label="Nettoyer les tests"
                                    icon="pi pi-refresh"
                                    severity="warn"
                                    outlined
                                    :loading="savingStates.testMode"
                                    :disabled="!testMode.enabled"
                                    @click="cleanTestModeAction"
                                />
                            </div>

                            <div class="settings-card mt-4">
                                <div class="mb-4">
                                    <h4 class="text-base font-semibold m-0">Sauvegarde et reset base</h4>
                                    <p class="settings-section-description m-0 mt-1">Export SQL/ZIP/JSON avec confirmation admin, reset complet réservé au super-admin id=1</p>
                                </div>
                                <div class="two-columns">
                                    <div class="field-group">
                                        <label>Formats d'export</label>
                                        <div class="toggle-group">
                                            <div class="toggle-item">
                                                <div class="toggle-info">
                                                    <label>SQL dump</label>
                                                </div>
                                                <ToggleSwitch v-model="backupOptions.sql" />
                                            </div>
                                            <div class="toggle-item">
                                                <div class="toggle-info">
                                                    <label>ZIP + métadonnées</label>
                                                </div>
                                                <ToggleSwitch v-model="backupOptions.zip" />
                                            </div>
                                            <div class="toggle-item">
                                                <div class="toggle-info">
                                                    <label>JSON applicatif</label>
                                                </div>
                                                <ToggleSwitch v-model="backupOptions.json" />
                                            </div>
                                        </div>
                                        <label class="mt-3">Téléchargement</label>
                                        <SelectButton
                                            v-model="backupDownloadMode"
                                            :options="backupDownloadModeOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            :allowEmpty="false"
                                            class="mb-3"
                                        />
                                        <Button
                                            label="Créer sauvegarde/export"
                                            icon="pi pi-download"
                                            :loading="savingStates.databaseExport"
                                            @click="exportDatabaseAction"
                                        />
                                    </div>
                                    <div class="field-group">
                                        <label>Zone critique</label>
                                        <p class="field-helper">Réinitialise toutes les données applicatives, conserve uniquement l'utilisateur id=1.</p>
                                        <Button
                                            label="Reset complet base"
                                            icon="pi pi-exclamation-triangle"
                                            severity="danger"
                                            :loading="savingStates.databaseReset"
                                            :disabled="!isSuperAdmin"
                                            @click="resetDatabaseAction"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <div class="settings-display-mode-fab">
            <div class="settings-display-mode-label">Affichage sections</div>
            <SelectButton
                v-model="settingsDisplayMode"
                :options="settingsDisplayModeOptions"
                optionLabel="label"
                optionValue="value"
                :allowEmpty="false"
                aria-label="Mode visuel des sections"
            >
                <template #option="{ option }">
                    <span class="settings-display-mode-option">
                        <i :class="option.icon"></i>
                        <span>{{ option.label }}</span>
                    </span>
                </template>
            </SelectButton>
        </div>

        <Dialog
            :visible="deviceAccessLogsDialogVisible"
            header="Journal d'accès récent"
            :modal="true"
            :style="{ width: '56rem', maxWidth: '96vw' }"
            @update:visible="(value) => { deviceAccessLogsDialogVisible = value; }"
        >
            <p class="settings-section-description m-0 mb-4">
                Historique des tentatives de connexion et accès récents par appareil.
            </p>
            <DataTable :value="deviceAccessLogs" :loading="devicesLoading" size="small" responsiveLayout="scroll">
                <Column field="createdAt" header="Date">
                    <template #body="{ data }">
                        {{ formatDeviceDate(data.createdAt) }}
                    </template>
                </Column>
                <Column field="username" header="Utilisateur" />
                <Column field="deviceName" header="Appareil" />
                <Column field="status" header="État" />
                <Column field="path" header="Route" />
                <Column field="ipAddress" header="IP" />
                <template #empty>
                    <div class="text-sm text-surface-500 py-3">Aucun accès récent.</div>
                </template>
            </DataTable>
            <template #footer>
                <Button label="Fermer" icon="pi pi-times" severity="secondary" @click="deviceAccessLogsDialogVisible = false" />
            </template>
        </Dialog>

        <Dialog
            :visible="deviceRenameDialogVisible"
            header="Renommer l'appareil"
            :modal="true"
            :style="{ width: '28rem', maxWidth: '96vw' }"
            @update:visible="(value) => { if (!value) closeDeviceRenameDialog(); }"
        >
            <p class="settings-section-description m-0 mb-4">
                Choisissez un nom explicite (ex. « Accueil », « Salle 2 », « PC Dr Martin ») pour distinguer cet appareil.
            </p>
            <div class="field-group">
                <label for="device-rename-input">Nom affiché</label>
                <InputText
                    id="device-rename-input"
                    v-model="deviceRenameValue"
                    class="w-full"
                    maxlength="255"
                    placeholder="Nom de l'appareil"
                    @keyup.enter="saveDeviceRename"
                />
            </div>
            <template #footer>
                <Button label="Annuler" icon="pi pi-times" severity="secondary" text :disabled="deviceRenameSaving" @click="closeDeviceRenameDialog" />
                <Button label="Enregistrer" icon="pi pi-check" :loading="deviceRenameSaving" @click="saveDeviceRename" />
            </template>
        </Dialog>

        <Dialog
            :visible="securityDialog.visible"
            :header="securityDialog.title"
            :modal="true"
            :closable="!isSecurityDialogSubmitting"
            :dismissableMask="!isSecurityDialogSubmitting"
            :style="{ width: '38rem', maxWidth: '96vw' }"
            @update:visible="(value) => { securityDialog.visible = value; if (!value) closeSecurityDialog(); }"
            @hide="closeSecurityDialog"
        >
            <div class="space-y-4">
                <p class="text-sm text-color-secondary m-0">{{ securityDialog.message }}</p>

                <div v-if="isDisablingTestMode" class="space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950/20">
                    <p class="m-0 text-sm font-semibold text-amber-800 dark:text-amber-200">Que faire des données de test ?</p>
                    <SelectButton
                        v-model="securityDialog.payload.deleteTestData"
                        :options="testModeDeleteOptions"
                        optionLabel="label"
                        optionValue="value"
                        :allowEmpty="false"
                        :disabled="isSecurityDialogSubmitting"
                        class="w-full test-mode-delete-select"
                    />
                    <p v-if="securityDialog.payload.deleteTestData" class="m-0 text-xs text-amber-700/90 dark:text-amber-300/90">
                        La base sera restaurée à l'état du snapshot initial : toutes les données créées pendant le mode test seront supprimées.
                    </p>
                    <p v-else class="m-0 text-xs text-amber-700/90 dark:text-amber-300/90">
                        Seul le mode test sera désactivé. Les patients, consultations et factures créés pendant les tests seront conservés.
                    </p>
                </div>

                <div v-if="isResetDialogMode" class="space-y-2 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-950/20">
                    <p class="m-0 text-sm font-semibold text-red-700 dark:text-red-300">Check de sécurité renforcé requis</p>
                    <p class="m-0 text-xs text-red-700/90 dark:text-red-300/90">Recopiez exactement la phrase suivante pour autoriser la réinitialisation :</p>
                    <div class="rounded-md bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 px-3 py-2">
                        <span class="font-mono text-sm tracking-wide select-all">{{ securityDialog.challenge }}</span>
                    </div>
                    <InputText
                        v-model="securityDialog.challengeInput"
                        class="w-full"
                        placeholder="Répétez la phrase de sécurité"
                        :disabled="isSecurityDialogSubmitting"
                    />
                </div>

                <div class="space-y-2">
                    <label for="settings-admin-password" class="text-sm font-medium">Mot de passe admin</label>
                    <Password
                        id="settings-admin-password"
                        v-model="securityDialog.password"
                        :feedback="false"
                        toggleMask
                        fluid
                        :disabled="isSecurityDialogSubmitting"
                        inputClass="w-full"
                    />
                </div>
            </div>

            <template #footer>
                <Button
                    label="Annuler"
                    severity="secondary"
                    outlined
                    :disabled="isSecurityDialogSubmitting"
                    @click="closeSecurityDialog"
                />
                <Button
                    label="Confirmer"
                    icon="pi pi-check"
                    :loading="isSecurityDialogSubmitting"
                    @click="confirmSecurityDialog"
                />
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.settings-container {
    min-height: 100vh;
    background: var(--surface-ground);
}

/* Header */
.settings-header {
    background: var(--surface-card);
    border-bottom: 1px solid var(--surface-border);
    position: sticky;
    top: -1rem;
    z-index: 10;
    backdrop-filter: blur(10px);
    background: rgba(var(--surface-card-rgb), 0.9);
}

.settings-header-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.settings-header-info {
    flex: 1;
}

.settings-badge {
    background: var(--primary-color);
    color: white;
    margin-bottom: 0.75rem;
}

.settings-header-title {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    background: linear-gradient(135deg, var(--text-color), var(--primary-color));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.settings-header-description {
    color: var(--text-color-secondary);
    margin: 0;
    font-size: 0.95rem;
}

.settings-header-actions {
    display: flex;
    gap: 0.75rem;
}

/* Body Layout */
.settings-body {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    display: flex;
    gap: 2rem;
}

/* Sidebar */
.settings-sidebar {
    width: 280px;
    flex-shrink: 0;
    position: sticky;
    top: 10rem;
    height: fit-content;
}

.settings-nav-card {
    background: var(--surface-card);
    border-radius: 20px;
    border: 1px solid var(--surface-border);
    overflow: hidden;
}

.settings-nav {
    padding: 0.75rem;
}

.settings-nav-group {
    margin-bottom: 1rem;
}

.settings-nav-group-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 0.75rem 0.5rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-color-secondary);
}

.settings-nav-icon {
    font-size: 0.875rem;
}

.settings-nav-items {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.settings-nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.75rem;
    width: 100%;
    border: none;
    background: transparent;
    color: var(--text-color);
    border-radius: 12px;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.settings-nav-item:hover {
    background: var(--surface-hover);
}

.settings-nav-item.active {
    background: color-mix(in srgb, var(--primary-color), transparent 88%);
    color: var(--primary-color);
}

.settings-nav-item-icon {
    font-size: 1rem;
    width: 1.25rem;
}

/* Main Content */
.settings-main {
    flex: 1;
    min-width: 0;
}

.settings-loading {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.settings-loading-card {
    height: 200px;
    background: var(--surface-0);
    border-radius: 20px;
    border: 1px solid var(--surface-border);
    background: linear-gradient(90deg, var(--surface-100), var(--surface-50), var(--surface-100));
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.settings-category {
    margin-bottom: 2.5rem;
}

.settings-tabs-nav {
    margin-bottom: 1.25rem;
}

.settings-tabs-global {
    margin-bottom: 1.5rem;
}

.settings-tab-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.settings-tab-label {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.settings-category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--surface-border);
}

.settings-category-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.settings-category-title i {
    font-size: 1.5rem;
    color: var(--primary-color);
}

.settings-category-title h2 {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
}

/* Sections */
.settings-section {
    margin-bottom: 2rem;
    scroll-margin-top: 100px;
}

.settings-section-header {
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 1rem;
}

.settings-section-header h3 {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0 0 0.25rem 0;
}

.settings-section-description {
    color: var(--text-color-secondary);
    font-size: 0.875rem;
    margin: 0;
}

.settings-card {
    background: var(--surface-card);
    border-radius: 16px;
    border: 1px solid var(--surface-border);
    padding: 1.5rem;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1rem;
}

.stat-card {
    background: var(--surface-card);
    border-radius: 16px;
    border: 1px solid var(--surface-border);
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);
}

.stat-icon {
    width: 48px;
    height: 48px;
    background: color-mix(in srgb, var(--primary-color), transparent 88%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-icon i {
    font-size: 1.5rem;
    color: var(--primary-color);
}

.stat-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.stat-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-color-secondary);
}

.stat-value {
    font-size: 1rem;
    font-weight: 600;
}

/* Device cards */
.device-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}

.device-cards-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 2.5rem 1rem;
    border: 1px dashed var(--surface-border);
    border-radius: 16px;
    color: var(--text-color-secondary);
}

.device-cards-empty i {
    font-size: 2rem;
    opacity: 0.6;
}

.device-card {
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    border-radius: 16px;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.device-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
}

.device-card--approved {
    border-left: 3px solid var(--green-500, #22c55e);
}

.device-card--pending {
    border-left: 3px solid var(--orange-500, #f97316);
}

.device-card--rejected {
    border-left: 3px solid var(--red-500, #ef4444);
}

.device-card-skeleton {
    min-height: 180px;
    background: linear-gradient(90deg, var(--surface-100) 25%, var(--surface-200) 50%, var(--surface-100) 75%);
    background-size: 200% 100%;
    animation: device-card-shimmer 1.2s infinite;
}

@keyframes device-card-shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.device-card-header {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.device-card-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: color-mix(in srgb, var(--primary-color), transparent 88%);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.device-card-icon i {
    font-size: 1.25rem;
    color: var(--primary-color);
}

.device-card-title {
    flex: 1;
    min-width: 0;
}

.device-card-title h5 {
    font-size: 0.95rem;
    font-weight: 600;
    word-break: break-word;
}

.device-card-type {
    display: block;
    font-size: 0.8rem;
    color: var(--text-color-secondary);
    margin-top: 0.15rem;
}

.device-card-subtitle {
    display: block;
    font-size: 0.75rem;
    color: var(--text-color-secondary);
    opacity: 0.85;
    margin-top: 0.15rem;
    word-break: break-word;
}

.device-card-body {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.device-card-meta {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    font-size: 0.85rem;
}

.device-card-meta-label {
    color: var(--text-color-secondary);
    flex-shrink: 0;
}

.device-card-meta span:last-child {
    text-align: right;
    word-break: break-word;
}

.device-card-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: auto;
    padding-top: 0.25rem;
    border-top: 1px solid var(--surface-border);
}

/* Color Section */
.color-section {
    margin-bottom: 1rem;
}

.color-section:last-child {
    margin-bottom: 0;
}

.color-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.75rem;
}

.swatch-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.swatch {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    border: 2px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
}

.swatch:hover {
    transform: scale(1.05);
}

.swatch.active {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px var(--surface-0), 0 0 0 4px var(--primary-color);
}

/* Typography Controls */
.typography-controls {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.control-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.control-group label {
    font-size: 0.875rem;
    font-weight: 500;
}

.control-select {
    width: 100%;
}

/* Preview Area */
.preview-area {
    background: var(--surface-ground);
    border-radius: 12px;
    padding: 1rem;
}

.preview-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-color-secondary);
    margin: 0 0 0.5rem 0;
}

.preview-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
}

.preview-text {
    color: var(--text-color-secondary);
    margin: 0;
    font-size: 0.875rem;
    line-height: 1.5;
}

/* Toggle Group */
.toggle-group {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.toggle-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.toggle-info {
    flex: 1;
}

.toggle-info label {
    font-weight: 500;
    display: block;
    margin-bottom: 0.25rem;
}

.toggle-description {
    font-size: 0.75rem;
    color: var(--text-color-secondary);
}

/* Two Columns Layout */
.two-columns {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

/* Field Group */
.field-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.field-group label {
    font-weight: 500;
    font-size: 0.875rem;
}

.field-helper {
    font-size: 0.75rem;
    color: var(--text-color-secondary);
}

.settings-inline-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.settings-qr {
    width: min(260px, 100%);
    aspect-ratio: 1;
    object-fit: cover;
    border: 1px solid var(--surface-border);
    border-radius: 12px;
    background: var(--surface-0);
    padding: 0.5rem;
}

.settings-display-mode-fab {
    position: fixed;
    right: 1.5rem;
    bottom: 1.5rem;
    z-index: 30;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    background: color-mix(in srgb, var(--surface-card), transparent 6%);
    border: 1px solid var(--surface-border);
    border-radius: 14px;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.14);
    padding: 0.75rem;
    backdrop-filter: blur(8px);
}

.settings-display-mode-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-color-secondary);
}

.settings-display-mode-option {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

:deep(.test-mode-delete-select .p-togglebutton) {
    flex: 1 1 auto;
    white-space: normal;
    text-align: center;
    font-size: 0.8rem;
    line-height: 1.3;
}

/* Responsive */
@media (max-width: 1024px) {
    .settings-body {
        flex-direction: column;
    }

    .settings-sidebar {
        width: 100%;
        position: static;
    }

    .settings-nav-card {
        position: static;
    }

    .settings-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .settings-nav-group {
        flex: 1;
        min-width: 200px;
    }
}

@media (max-width: 768px) {
    .settings-header-content {
        flex-direction: column;
        padding: 1rem;
    }

    .settings-body {
        padding: 1rem;
    }

    .two-columns {
        grid-template-columns: 1fr;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .typography-controls {
        grid-template-columns: 1fr;
    }

    .toggle-item {
        flex-direction: column;
        align-items: flex-start;
    }

    .settings-display-mode-fab {
        left: 1rem;
        right: 1rem;
        bottom: 1rem;
    }
}
</style>

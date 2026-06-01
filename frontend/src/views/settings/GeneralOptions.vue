<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import Divider from 'primevue/divider';
import Card from 'primevue/card';
import Badge from 'primevue/badge';
import InputNumber from 'primevue/inputnumber';
import { useAuthStore } from '@/stores/auth';
import { useUiSettingsStore } from '@/stores/uiSettings';
import { useAppearanceSettings } from '@/composables/useAppearanceSettings';
import { GUIDED_TOUR_START_EVENT } from '@/tours';
import { createSettingsApparenceTour } from '@/tours/settingsApparenceTour';
import { startTourGuide } from '@/tours/tourGuideClient';
import {
    cleanTestMode,
    downloadDatabaseExport,
    exportDatabase,
    fetchGeneralSettings,
    resetDatabase,
    saveGeneralSettings,
    toggleTestMode
} from '@/services/globalSettingsService';
import { getHttpErrorMessage } from '@/service/http';
import cabinetConfig from '@/cabinetConfig';

const router = useRouter();
const toast = useToast();
const token = localStorage.getItem('token');
const auth = useAuthStore();
const uiSettings = useUiSettingsStore();
const isGuidedTourStarting = ref(false);
const activeCategory = ref('appearance');
const activeSubSection = ref('overview');
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
    consultationAccess: false,
    devicePolicy: false,
    billingPolicy: false,
    portalSettings: false,
    transactionMotifs: false,
    soinsCatalog: false,
    testMode: false,
    databaseExport: false,
    databaseReset: false
});

// Settings data
const devicePolicy = reactive({
    autoApproveDevices: true,
    requireMedecinOnConsultationCreation: true,
    paiementDirectAssurance: false
});

const billingPolicy = reactive({
    consultationPrice: 5000
});

const consultationAccess = reactive({
    allowReceptionConsultationQuickActions: true,
    showReceptionQuickCloseButton: true,
    allowReceptionBypassMedecinPasswordOnQuickClose: false,
    hidePatientDossierForMedecins: false,
    hidePatientPhoneForMedecins: false,
    ficheFormSimplifie: false
});

const transactionMotifs = reactive({
    revenueText: 'Paiement patient\nRemboursement assurance\nVente produit\nAutre',
    expenseText: 'Achat matériel\nFrais généraux\nPaiement salaire\nMaintenance\nAutre'
});

const soinsCatalog = reactive({
    text: 'Consultation\nDétartrage\nExtraction\nRemplissage\nComposite\nAmalgame\nTraitement de canal\nTraumatisme\nCouronne\nBlanchiment\nRadio\nProthèse\nOrthodontie\nChirurgie'
});

const ficheSimplifieCatalog = reactive({
    examensTypesText: 'Bacteriologique\nSerologique\nHistologique\nRadiologique\nAutre',
    traitementTypesText: 'Urgence\nDentaires\nParodontaux\nOrthodontiques\nAutres',
    allergyTypesText: 'Médicamenteuses\nAlimentaires\nEnvironnementales\nAutres',
    antecedentTypesText: 'Personnel\nFamilial\nMédical'
});

const portalPatientConfig = reactive({
    patientPortalEnabled: true,
    patientPortalClosedMessage: 'Le portail patient est temporairement indisponible. Merci de contacter le cabinet pour toute assistance.',
    patientPortalBaseUrl: '',
    cabinetShowcaseWebsiteUrl: ''
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
    workflow: {
        label: 'Flux métier',
        icon: 'pi pi-briefcase',
        sections: [
            { id: 'consultation-access', label: 'Consultation & Focus', icon: 'pi pi-lock' },
            { id: 'device-security', label: 'Sécurité appareils', icon: 'pi pi-shield' },
            { id: 'billing-rules', label: 'Règles facturation', icon: 'pi pi-wallet' },
            { id: 'patient-portal', label: 'Portail patient', icon: 'pi pi-mobile' },
            { id: 'test-mode', label: 'Mode test', icon: 'pi pi-flask' },
            { id: 'database-maintenance', label: 'Sauvegarde et reset', icon: 'pi pi-database' },
            { id: 'transaction-motifs', label: 'Motifs transaction', icon: 'pi pi-dollar' },
            { id: 'soins-list', label: 'Liste des soins', icon: 'pi pi-heart' }
        ]
    }
};

const canAccessWorkflowSettings = computed(() => (auth.user?.roles || []).includes('ROLE_ADMIN'));
const isSuperAdmin = computed(() => Number(auth.user?.id || 0) === 1);
const isSecurityDialogSubmitting = computed(() => savingStates.testMode || savingStates.databaseExport || savingStates.databaseReset);
const isResetDialogMode = computed(() => securityDialog.mode === 'db-reset');
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

const extractApiError = (error, fallback) => getHttpErrorMessage(error, fallback);

const normalizeLines = (value) => {
    const unique = new Set();
    return String(value || '')
        .split(/\r?\n/)
        .map((item) => item.trim())
        .filter((item) => item && !unique.has(item) && unique.add(item));
};

const scrollToSection = async (category, sectionId) => {
    if (!visibleNavigation.value?.[category]) return;
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
        devicePolicy.requireMedecinOnConsultationCreation = settings.requireMedecinOnConsultationCreation !== false;
        devicePolicy.paiementDirectAssurance = settings.paiementDirectAssurance === true;
        consultationAccess.allowReceptionConsultationQuickActions = settings.allowReceptionConsultationQuickActions !== false
            && settings.allowReceptionQuickCloseConsultation !== false;
        consultationAccess.showReceptionQuickCloseButton = settings.showReceptionQuickCloseButton !== false;
        consultationAccess.allowReceptionBypassMedecinPasswordOnQuickClose = consultationAccess.showReceptionQuickCloseButton
            && settings.allowReceptionBypassMedecinPasswordOnQuickClose === true;
        consultationAccess.hidePatientDossierForMedecins = settings.hidePatientDossierForMedecins === true;
        consultationAccess.hidePatientPhoneForMedecins = settings.hidePatientPhoneForMedecins === true;
        consultationAccess.ficheFormSimplifie = settings.ficheFormSimplifie === true;
        billingPolicy.consultationPrice = Number(settings.consultationPrice || 5000);
        transactionMotifs.revenueText = (settings.transactionMotifs?.revenue || []).join('\n');
        transactionMotifs.expenseText = (settings.transactionMotifs?.expense || []).join('\n');
        soinsCatalog.text = (settings.soinsList || []).join('\n');
        ficheSimplifieCatalog.examensTypesText = (settings.examensTypes || []).join('\n');
        ficheSimplifieCatalog.traitementTypesText = (settings.traitementTypes || []).join('\n');
        ficheSimplifieCatalog.allergyTypesText = (settings.allergyTypes || []).join('\n');
        ficheSimplifieCatalog.antecedentTypesText = (settings.antecedentTypes || []).join('\n');
        portalPatientConfig.patientPortalEnabled = settings.patientPortalEnabled !== false;
        portalPatientConfig.patientPortalClosedMessage = settings.patientPortalClosedMessage || portalPatientConfig.patientPortalClosedMessage;
        portalPatientConfig.patientPortalBaseUrl = settings.patientPortalBaseUrl || '';
        portalPatientConfig.cabinetShowcaseWebsiteUrl = settings.cabinetShowcaseWebsiteUrl || '';
        testMode.enabled = settings.testModeEnabled === true;
        persistedTestModeEnabled.value = testMode.enabled;
        testMode.snapshotCreatedAt = settings.testModeSnapshotCreatedAt || null;
        testMode.lastPurgeAt = settings.testModeLastPurgeAt || null;
        generalSettingsLoaded.value = true;
        loadErrorMessage.value = '';
    } catch (error) {
        console.error(error);
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
            autoApproveDevices: devicePolicy.autoApproveDevices,
            requireMedecinOnConsultationCreation: devicePolicy.requireMedecinOnConsultationCreation
        }, token);
        toast.add({ severity: 'success', summary: 'Sécurité appareils', detail: 'Paramètres enregistrés', life: 2500 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Sauvegarde impossible'), life: 3500 });
    } finally {
        savingStates.devicePolicy = false;
    }
};

const saveConsultationAccessAction = async () => {
    if (!canAccessWorkflowSettings.value) return;
    savingStates.consultationAccess = true;
    try {
        await saveGeneralSettings({
            allowReceptionConsultationQuickActions: consultationAccess.allowReceptionConsultationQuickActions,
            allowReceptionQuickCloseConsultation: consultationAccess.allowReceptionConsultationQuickActions,
            showReceptionQuickCloseButton: consultationAccess.showReceptionQuickCloseButton,
            allowReceptionBypassMedecinPasswordOnQuickClose: consultationAccess.showReceptionQuickCloseButton && consultationAccess.allowReceptionBypassMedecinPasswordOnQuickClose,
            hidePatientDossierForMedecins: consultationAccess.hidePatientDossierForMedecins,
            hidePatientPhoneForMedecins: consultationAccess.hidePatientPhoneForMedecins,
            ficheFormSimplifie: consultationAccess.ficheFormSimplifie,
            examensTypes: normalizeLines(ficheSimplifieCatalog.examensTypesText),
            traitementTypes: normalizeLines(ficheSimplifieCatalog.traitementTypesText),
            allergyTypes: normalizeLines(ficheSimplifieCatalog.allergyTypesText),
            antecedentTypes: normalizeLines(ficheSimplifieCatalog.antecedentTypesText)
        }, token);
        toast.add({ severity: 'success', summary: 'Consultation & Focus', detail: 'Paramètres enregistrés', life: 2500 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Sauvegarde impossible'), life: 3500 });
    } finally {
        savingStates.consultationAccess = false;
    }
};

const saveBillingPolicyAction = async () => {
    if (!canAccessWorkflowSettings.value) return;
    savingStates.billingPolicy = true;
    try {
        await saveGeneralSettings({
            paiementDirectAssurance: devicePolicy.paiementDirectAssurance,
            consultationPrice: Number(billingPolicy.consultationPrice || 5000)
        }, token);
        toast.add({ severity: 'success', summary: 'Règles facturation', detail: 'Paramètres enregistrés', life: 2500 });
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
            cabinetShowcaseWebsiteUrl: String(portalPatientConfig.cabinetShowcaseWebsiteUrl || '').trim()
        }, token);
        toast.add({ severity: 'success', summary: 'Portail patient', detail: 'Paramètres enregistrés', life: 2500 });
        await loadGeneralSettings(true);
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Sauvegarde impossible'), life: 3500 });
    } finally {
        savingStates.portalSettings = false;
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

const saveTransactionMotifsAction = async () => {
    if (!canAccessWorkflowSettings.value) return;
    savingStates.transactionMotifs = true;
    try {
        await saveGeneralSettings({
            transactionMotifs: {
                revenue: normalizeLines(transactionMotifs.revenueText),
                expense: normalizeLines(transactionMotifs.expenseText)
            }
        }, token);
        toast.add({ severity: 'success', summary: 'Motifs transaction', detail: 'Paramètres enregistrés', life: 2500 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Sauvegarde impossible'), life: 3500 });
    } finally {
        savingStates.transactionMotifs = false;
    }
};

const saveSoinsCatalogAction = async () => {
    if (!canAccessWorkflowSettings.value) return;
    savingStates.soinsCatalog = true;
    try {
        await saveGeneralSettings({ soinsList: normalizeLines(soinsCatalog.text) }, token);
        toast.add({ severity: 'success', summary: 'Liste des soins', detail: 'Paramètres enregistrés', life: 2500 });
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
            const response = await toggleTestMode({ enabled: testMode.enabled, password }, token);
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

    openSecurityDialog({
        mode: 'test-toggle',
        title: 'Confirmation mode test',
        message: 'Confirmez votre mot de passe admin pour appliquer ce changement de mode test.'
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

const handleGuidedTourRequest = async (event) => {
    if (event?.detail?.routeName !== 'settings-apparence' || isGuidedTourStarting.value) return;
    isGuidedTourStarting.value = true;
    try {
        await startTourGuide({ group: 'settings-apparence', steps: createSettingsApparenceTour() });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Aide guidée', detail: 'Impossible de lancer le tour', life: 3000 });
    } finally {
        isGuidedTourStarting.value = false;
    }
};

const currentThemeLabel = computed(() => themeOptions.value.find((option) => option.value === themeMode.value)?.label || 'Système');
const currentFontSizeLabel = computed(() => fontSizeOptions.value.find((option) => option.value === fontSize.value)?.label || 'Normal');
const currentSurfaceName = computed(() => layoutConfig.surface || (isDarkTheme.value ? 'zinc' : 'slate'));
const canAccessSmsSettings = computed(() => (auth.user?.roles || []).includes('ROLE_ADMIN'));
const normalizedPortalBaseUrl = computed(() => String(portalPatientConfig.patientPortalBaseUrl || '').replace(/\/$/, ''));
const portalLoginUrl = computed(() => normalizedPortalBaseUrl.value ? `${normalizedPortalBaseUrl.value}/login` : '');
const anonymousReviewUrl = computed(() => normalizedPortalBaseUrl.value ? `${normalizedPortalBaseUrl.value}/avis-anonyme` : '');
const qrPortalLoginSrc = computed(() => {
    if (!portalLoginUrl.value) return '';
    return `https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=${encodeURIComponent(portalLoginUrl.value)}`;
});
const qrAnonymousReviewSrc = computed(() => {
    if (!anonymousReviewUrl.value) return '';
    return `https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=${encodeURIComponent(anonymousReviewUrl.value)}`;
});

watch(canAccessWorkflowSettings, async (allowed) => {
    if (!allowed && activeCategory.value === 'workflow') {
        activeCategory.value = 'appearance';
        activeSubSection.value = 'overview';
    }
    await nextTick();
    setupObserver();
}, { immediate: true });

watch(
    () => consultationAccess.showReceptionQuickCloseButton,
    (enabled) => {
        if (!enabled) {
            consultationAccess.allowReceptionBypassMedecinPasswordOnQuickClose = false;
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
    window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
});
</script>

<template>
    <div class="settings-container">
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

        <div class="settings-body">
            <!-- Sidebar Navigation -->
            <aside class="settings-sidebar">
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
                    <!-- Appearance Section -->
                    <div class="settings-category">
                        <div class="settings-category-header">
                            <div class="settings-category-title">
                                <i class="pi pi-palette"></i>
                                <h2>Apparence</h2>
                            </div>
                            <Button label="Sauvegarder" icon="pi pi-save" outlined size="small" @click="saveAppearance" />
                        </div>

                        <!-- Overview -->
                        <div id="appearance-overview" class="settings-section">
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
                        <div id="appearance-colors" class="settings-section">
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
                        <div id="appearance-typography" class="settings-section">
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
                        <div id="appearance-layout" class="settings-section">
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

                    <!-- Workflow Section -->
                    <div v-if="canAccessWorkflowSettings" class="settings-category">
                        <div class="settings-category-header">
                            <div class="settings-category-title">
                                <i class="pi pi-briefcase"></i>
                                <h2>Flux métier</h2>
                            </div>
                        </div>

                        <!-- Consultation Access -->
                        <div id="workflow-consultation-access" class="settings-section">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Consultation & Focus</h3>
                                    <p class="settings-section-description">Gestion de la visibilité des actions et informations pour les médecins/réception</p>
                                </div>
                                <Button
                                    label="Enregistrer"
                                    icon="pi pi-save"
                                    :loading="savingStates.consultationAccess"
                                    @click="saveConsultationAccessAction"
                                />
                            </div>
                            <div class="settings-card">
                                <div class="toggle-group">
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Actions rapides pour réceptionniste</label>
                                            <span class="toggle-description">Active ou masque les boutons d'actions rapides de consultation côté réception</span>
                                        </div>
                                        <ToggleSwitch v-model="consultationAccess.allowReceptionConsultationQuickActions" />
                                    </div>
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Afficher la clôturation rapide pour la réception</label>
                                            <span class="toggle-description">Affiche ou masque le bouton de clôture rapide pour les secrétaires dans focus et l'historique</span>
                                        </div>
                                        <ToggleSwitch v-model="consultationAccess.showReceptionQuickCloseButton" />
                                    </div>
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Bypass code médecin en clôture rapide</label>
                                            <span class="toggle-description">Si activé, la réception peut clôturer rapidement sans validation du code médecin</span>
                                        </div>
                                        <ToggleSwitch v-model="consultationAccess.allowReceptionBypassMedecinPasswordOnQuickClose" :disabled="!consultationAccess.showReceptionQuickCloseButton" />
                                    </div>
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Masquer le dossier patient aux médecins</label>
                                            <span class="toggle-description">Le dossier patient et les redirections associées sont masqués aux médecins non-admin</span>
                                        </div>
                                        <ToggleSwitch v-model="consultationAccess.hidePatientDossierForMedecins" />
                                    </div>
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Masquer les numéros des patients aux médecins</label>
                                            <span class="toggle-description">Les numéros de téléphone patients sont masqués dans l'interface médecin</span>
                                        </div>
                                        <ToggleSwitch v-model="consultationAccess.hidePatientPhoneForMedecins" />
                                    </div>
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Formulaire simplifié de fiche consultation</label>
                                            <span class="toggle-description">Active une vue condensée "Synthèse clinique" pour la fiche consultation</span>
                                        </div>
                                        <ToggleSwitch v-model="consultationAccess.ficheFormSimplifie" />
                                    </div>
                                </div>
                                <Divider />
                                <div class="two-columns">
                                    <div class="field-group">
                                        <label>Types d'examens (synthèse)</label>
                                        <Textarea
                                            v-model="ficheSimplifieCatalog.examensTypesText"
                                            rows="5"
                                            autoResize
                                            placeholder="Un type d'examen par ligne"
                                        />
                                    </div>
                                    <div class="field-group">
                                        <label>Types de traitements (synthèse)</label>
                                        <Textarea
                                            v-model="ficheSimplifieCatalog.traitementTypesText"
                                            rows="5"
                                            autoResize
                                            placeholder="Un type de traitement par ligne"
                                        />
                                    </div>
                                    <div class="field-group">
                                        <label>Types d'allergies</label>
                                        <Textarea
                                            v-model="ficheSimplifieCatalog.allergyTypesText"
                                            rows="4"
                                            autoResize
                                            placeholder="Un type d'allergie par ligne"
                                        />
                                    </div>
                                    <div class="field-group">
                                        <label>Types d'antécédents</label>
                                        <Textarea
                                            v-model="ficheSimplifieCatalog.antecedentTypesText"
                                            rows="4"
                                            autoResize
                                            placeholder="Un type d'antécédent par ligne"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Device Security -->
                        <div id="workflow-device-security" class="settings-section">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Sécurité des appareils</h3>
                                    <p class="settings-section-description">Gestion des règles de sécurité et des appareils</p>
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
                                            <span class="toggle-description">Active l'approbation automatique des nouveaux appareils</span>
                                        </div>
                                        <ToggleSwitch v-model="devicePolicy.autoApproveDevices" />
                                    </div>
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Médecin requis à la création</label>
                                            <span class="toggle-description">La consultation peut être créée sans médecin assigné si désactivé</span>
                                        </div>
                                        <ToggleSwitch v-model="devicePolicy.requireMedecinOnConsultationCreation" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Billing Rules -->
                        <div id="workflow-billing-rules" class="settings-section">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Règles facturation</h3>
                                    <p class="settings-section-description">Comportement de la prise en charge assurance côté caisse</p>
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
                                        <ToggleSwitch v-model="devicePolicy.paiementDirectAssurance" />
                                    </div>
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Prix consultation</label>
                                            <span class="toggle-description">Montant par défaut appliqué aux nouvelles consultations payantes</span>
                                        </div>
                                        <InputNumber
                                            v-model="billingPolicy.consultationPrice"
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

                        <div id="workflow-patient-portal" class="settings-section">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Portail patient</h3>
                                    <p class="settings-section-description">Activation globale, message de fermeture, domaine frontend patient, URL vitrine et QR codes</p>
                                </div>
                                <Button
                                    label="Enregistrer"
                                    icon="pi pi-save"
                                    :loading="savingStates.portalSettings"
                                    @click="savePortalPatientSettingsAction"
                                />
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
                                        </div>
                                        <p class="field-helper">{{ anonymousReviewUrl || 'Renseignez le domaine patient pour générer le QR.' }}</p>
                                        <img v-if="qrAnonymousReviewSrc" :src="qrAnonymousReviewSrc" alt="QR avis anonyme" class="settings-qr" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Test Mode -->
                        <div id="workflow-test-mode" class="settings-section">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Mode test</h3>
                                    <p class="settings-section-description">Mode volatil global: snapshot à l'activation, restauration automatique à la désactivation</p>
                                </div>
                                <Button
                                    label="Appliquer"
                                    icon="pi pi-save"
                                    :loading="savingStates.testMode"
                                    @click="saveTestModeAction"
                                />
                            </div>
                            <div class="settings-card">
                                <div class="toggle-group">
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
                        </div>

                        <!-- Database Maintenance -->
                        <div id="workflow-database-maintenance" class="settings-section">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Sauvegarde et reset base</h3>
                                    <p class="settings-section-description">Export SQL/ZIP/JSON avec confirmation admin, et reset complet réservé au super-admin id=1</p>
                                </div>
                            </div>
                            <div class="settings-card">
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

                        <!-- Transaction Motifs -->
                        <div id="workflow-transaction-motifs" class="settings-section">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Motifs de transaction</h3>
                                    <p class="settings-section-description">Personnalisez les motifs disponibles pour les transactions</p>
                                </div>
                                <Button
                                    label="Enregistrer"
                                    icon="pi pi-save"
                                    :loading="savingStates.transactionMotifs"
                                    @click="saveTransactionMotifsAction"
                                />
                            </div>
                            <div class="settings-card">
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

                        <!-- Soins List -->
                        <div id="workflow-soins-list" class="settings-section">
                            <div class="settings-section-header">
                                <div>
                                    <h3>Liste des soins</h3>
                                    <p class="settings-section-description">Catalogue des soins disponibles dans l'application</p>
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
                </div>
            </main>
        </div>

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
}
</style>

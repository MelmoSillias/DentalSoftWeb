<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import Divider from 'primevue/divider';
import Card from 'primevue/card';
import Badge from 'primevue/badge';
import { useAuthStore } from '@/stores/auth';
import { useUiSettingsStore } from '@/stores/uiSettings';
import { useAppearanceSettings } from '@/composables/useAppearanceSettings';
import { GUIDED_TOUR_START_EVENT } from '@/tours';
import { createSettingsApparenceTour } from '@/tours/settingsApparenceTour';
import { startTourGuide } from '@/tours/tourGuideClient';
import { fetchGeneralSettings, saveGeneralSettings } from '@/services/globalSettingsService';

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
const savingStates = reactive({
    devicePolicy: false,
    transactionMotifs: false,
    soinsCatalog: false
});

// Settings data
const devicePolicy = reactive({
    autoApproveDevices: true,
    requireMedecinOnConsultationCreation: true,
    allowReceptionQuickCloseConsultation: true,
    paiementDirectAssurance: false
});

const transactionMotifs = reactive({
    revenueText: 'Paiement patient\nRemboursement assurance\nVente produit\nAutre',
    expenseText: 'Achat matériel\nFrais généraux\nPaiement salaire\nMaintenance\nAutre'
});

const soinsCatalog = reactive({
    text: 'Consultation\nDétartrage\nExtraction\nRemplissage\nComposite\nAmalgame\nTraitement de canal\nTraumatisme\nCouronne\nBlanchiment\nRadio\nProthèse\nOrthodontie\nChirurgie'
});

// Navigation structure
const navigation = {
    appearance: {
        label: 'Apparence',
        icon: 'pi-palette',
        sections: [
            { id: 'overview', label: 'Aperçu', icon: 'pi-chart-line' },
            { id: 'theme', label: 'Thème', icon: 'pi-sun' },
            { id: 'colors', label: 'Couleurs', icon: 'pi-palette' },
            { id: 'typography', label: 'Typographie', icon: 'pi-font' },
            { id: 'layout', label: 'Disposition', icon: 'pi-layout' }
        ]
    },
    workflow: {
        label: 'Flux métier',
        icon: 'pi-briefcase',
        sections: [
            { id: 'device-security', label: 'Sécurité appareils', icon: 'pi-shield' },
            { id: 'transaction-motifs', label: 'Motifs transaction', icon: 'pi-dollar' },
            { id: 'soins-list', label: 'Liste des soins', icon: 'pi-heart' }
        ]
    }
};

const extractApiError = (error, fallback) => {
    return error?.response?.data?.error
        || error?.response?.data?.message
        || error?.message
        || fallback;
};

const normalizeLines = (value) => {
    const unique = new Set();
    return String(value || '')
        .split(/\r?\n/)
        .map((item) => item.trim())
        .filter((item) => item && !unique.has(item) && unique.add(item));
};

const scrollToSection = async (category, sectionId) => {
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
    for (const [category, data] of Object.entries(navigation)) {
        for (const section of data.sections) {
            sections.push({ id: `${category}-${section.id}`, category, sectionId: section.id });
        }
    }

    observer = new IntersectionObserver(
        (entries) => {
            const visibleSections = entries.filter(e => e.isIntersecting);
            if (visibleSections.length > 0) {
                const firstVisible = visibleSections[0];
                const [category, sectionId] = firstVisible.target.id.split('-');
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
    if (!force && generalSettingsLoaded.value) return;

    generalLoading.value = true;
    try {
        const settings = await fetchGeneralSettings(token);
        devicePolicy.autoApproveDevices = settings.autoApproveDevices !== false;
        devicePolicy.requireMedecinOnConsultationCreation = settings.requireMedecinOnConsultationCreation !== false;
        devicePolicy.allowReceptionQuickCloseConsultation = settings.allowReceptionQuickCloseConsultation !== false;
        devicePolicy.paiementDirectAssurance = settings.paiementDirectAssurance === true;
        transactionMotifs.revenueText = (settings.transactionMotifs?.revenue || []).join('\n');
        transactionMotifs.expenseText = (settings.transactionMotifs?.expense || []).join('\n');
        soinsCatalog.text = (settings.soinsList || []).join('\n');
        generalSettingsLoaded.value = true;
    } catch (error) {
        console.error(error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Chargement impossible'), life: 3500 });
    } finally {
        generalLoading.value = false;
    }
};

const saveDevicePolicyAction = async () => {
    savingStates.devicePolicy = true;
    try {
        await saveGeneralSettings({
            autoApproveDevices: devicePolicy.autoApproveDevices,
            requireMedecinOnConsultationCreation: devicePolicy.requireMedecinOnConsultationCreation,
            allowReceptionQuickCloseConsultation: devicePolicy.allowReceptionQuickCloseConsultation,
            paiementDirectAssurance: devicePolicy.paiementDirectAssurance
        }, token);
        toast.add({ severity: 'success', summary: 'Sécurité appareils', detail: 'Paramètres enregistrés', life: 2500 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Sauvegarde impossible'), life: 3500 });
    } finally {
        savingStates.devicePolicy = false;
    }
};

const saveTransactionMotifsAction = async () => {
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

onMounted(async () => {
    await loadGeneralSettings(true);
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
                    <h1 class="settings-header-title">Configuration du cabinet</h1>
                    <p class="settings-header-description">
                        Personnalisez l'apparence et les flux métier de DentalSoft
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
                        <div v-for="(category, key) in navigation" :key="key" class="settings-nav-group">
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
                    <div class="settings-category">
                        <div class="settings-category-header">
                            <div class="settings-category-title">
                                <i class="pi pi-briefcase"></i>
                                <h2>Flux métier</h2>
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
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Clôture rapide par réceptionniste</label>
                                            <span class="toggle-description">Autorise l'option de clôture rapide côté réception</span>
                                        </div>
                                        <ToggleSwitch v-model="devicePolicy.allowReceptionQuickCloseConsultation" />
                                    </div>
                                    <Divider />
                                    <div class="toggle-item">
                                        <div class="toggle-info">
                                            <label>Paiement direct assurance</label>
                                            <span class="toggle-description">La part assurance crée un paiement immédiat</span>
                                        </div>
                                        <ToggleSwitch v-model="devicePolicy.paiementDirectAssurance" />
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
    </div>
</template>

<style scoped>
.settings-container {
    min-height: 100vh;
    background: var(--surface-ground);
}

/* Header */
.settings-header {
    background: var(--surface-0);
    border-bottom: 1px solid var(--surface-border);
    position: sticky;
    top: -1rem;
    z-index: 10;
    backdrop-filter: blur(10px);
    background: rgba(var(--surface-0-rgb), 0.9);
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
    background: var(--surface-0);
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
    background: var(--surface-0);
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
    background: var(--surface-0);
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
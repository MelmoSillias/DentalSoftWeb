<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import SelectButton from 'primevue/selectbutton';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import FormComponentRenderer from '@/components/forms/FormComponentRenderer.vue';
import { useFormBuilderStore } from '@/stores/formBuilder';
import { getPresetDefinitions } from '@/utils/formBuilderModel';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const store = useFormBuilderStore();

const activeTabId = ref(null);
const showJson = ref(false);
const builderMode = ref('layout');
const jsonDraft = ref('');
const savedSnapshot = ref('');
const presetDefinitions = getPresetDefinitions();
const builderModeOptions = [
    { label: 'Builder visuel', value: 'layout' },
    { label: 'Builder JSON', value: 'json' },
    { label: 'Builder presets', value: 'presets' }
];

const baseComponents = [
    { key: 'inputText', label: 'Input texte', icon: 'pi pi-pencil' },
    { key: 'inputNumber', label: 'Input numerique', icon: 'pi pi-hashtag' },
    { key: 'date', label: 'DatePicker', icon: 'pi pi-calendar' },
    { key: 'select', label: 'Select', icon: 'pi pi-list' },
    { key: 'textarea', label: 'Textarea', icon: 'pi pi-align-left' }
];

const presetItems = computed(() => Object.values(presetDefinitions));
const selectedTab = computed(() => store.tabs.find((tab) => String(tab.id) === String(activeTabId.value)) || store.tabs[0] || null);

const canAddField = computed(() => Boolean(store.selectedSection && store.selectedSection.locked !== true));
const presetInstances = computed(() => {
    return (store.tabs || []).flatMap((tab) => (tab.sections || [])
        .filter((section) => section.type === 'preset')
        .map((section) => ({
            tabId: tab.id,
            tabTitle: tab.title,
            sectionId: section.id,
            sectionTitle: section.title,
            presetKey: section.presetKey,
            enabled: section.enabled !== false
        })));
});

const copyJson = computed(() => JSON.stringify(store.definition, null, 2));
const hasUnsavedChanges = computed(() => JSON.stringify(store.definition) !== savedSnapshot.value);

const goBack = () => {
    if (hasUnsavedChanges.value && !window.confirm('Des modifications non sauvegardees existent. Quitter quand meme ?')) {
        return;
    }
    router.push({ name: 'settings-forms-list' });
};

const ensureActiveTab = () => {
    if (!store.tabs.length) {
        activeTabId.value = null;
        return;
    }

    if (!activeTabId.value || !store.tabs.some((tab) => String(tab.id) === String(activeTabId.value))) {
        activeTabId.value = store.tabs[0].id;
    }
};

const handleSelect = (payload) => {
    store.selectNode(payload);
    if (payload.tabId) {
        activeTabId.value = payload.tabId;
    }
};

const handleContextAction = (payload) => {
    if (!payload || !payload.action) return;

    if (payload.action === 'edit') {
        if (payload.nodeType === 'section') {
            store.selectNode({ nodeType: 'section', tabId: payload.tabId, sectionId: payload.sectionId, fieldId: null });
        }
        if (payload.nodeType === 'field') {
            store.selectNode({ nodeType: 'field', tabId: payload.tabId, sectionId: payload.sectionId, fieldId: payload.fieldId });
        }
        return;
    }

    if (payload.action === 'toggle' && payload.nodeType === 'section') {
        store.toggleSectionEnabled(payload.tabId, payload.sectionId);
        return;
    }

    if (payload.action === 'delete') {
        if (payload.nodeType === 'field') {
            store.removeField(payload.tabId, payload.sectionId, payload.fieldId);
        }
        if (payload.nodeType === 'section') {
            store.removeSection(payload.tabId, payload.sectionId);
        }
    }
};

const addBaseField = (fieldType) => {
    if (!store.selectedSection || store.selectedSection.locked) {
        toast.add({ severity: 'warn', summary: 'Selection', detail: 'Selectionnez une section simple pour ajouter un champ.', life: 2500 });
        return;
    }

    store.addField(store.selectedNode.tabId, store.selectedNode.sectionId, fieldType);
};

const onPresetDragStart = (event, presetKey) => {
    event.dataTransfer?.setData('application/x-preset', presetKey);
    event.dataTransfer.dropEffect = 'copy';
};

const onDropPreset = (event) => {
    event.preventDefault();
    const presetKey = event.dataTransfer?.getData('application/x-preset');

    if (!presetKey) return;
    if (!activeTabId.value) {
        toast.add({ severity: 'warn', summary: 'Onglet', detail: 'Ajoutez un onglet avant de deposer un preset.', life: 2500 });
        return;
    }

    store.addPresetSection(activeTabId.value, presetKey);
};

const save = async () => {
    try {
        await store.saveActiveForm();
        savedSnapshot.value = JSON.stringify(store.definition);
        jsonDraft.value = copyJson.value;
        toast.add({ severity: 'success', summary: 'Formulaire', detail: 'Sauvegarde reussie', life: 2500 });

        if (store.activeFormId) {
            router.replace({ name: 'settings-forms-builder', params: { formId: store.activeFormId } });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Sauvegarde impossible', life: 3500 });
    }
};

const setAsDefault = async () => {
    try {
        await store.setDefaultForm(store.definition.meta.code);
        toast.add({ severity: 'success', summary: 'Formulaire par defaut', detail: 'Configuration mise a jour', life: 2400 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Mise a jour impossible', life: 3500 });
    }
};

const syncSnapshot = () => {
    savedSnapshot.value = JSON.stringify(store.definition);
    jsonDraft.value = copyJson.value;
};

const applyJsonDraft = () => {
    try {
        const parsed = JSON.parse(jsonDraft.value || '{}');
        if (!parsed || typeof parsed !== 'object') {
            throw new Error('JSON invalide');
        }

        store.definition = parsed;
        ensureActiveTab();
        toast.add({ severity: 'success', summary: 'JSON', detail: 'Definition appliquee', life: 2200 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'JSON', detail: error?.message || 'JSON invalide', life: 3200 });
    }
};

const beforeUnloadHandler = (event) => {
    if (!hasUnsavedChanges.value) return;
    event.preventDefault();
    event.returnValue = '';
};

onBeforeRouteLeave((to, from, next) => {
    if (!hasUnsavedChanges.value) {
        next();
        return;
    }

    const ok = window.confirm('Des modifications non sauvegardees existent. Continuer ?');
    next(ok);
});

onMounted(async () => {
    await store.loadForms();

    const formId = route.params.formId;
    if (formId) {
        try {
            await store.loadFormById(formId);
        } catch (error) {
            toast.add({ severity: 'error', summary: 'Formulaire', detail: error?.message || 'Chargement impossible', life: 3200 });
            store.initializeNewForm();
        }
    } else {
        store.initializeNewForm();
    }

    ensureActiveTab();
    syncSnapshot();
    window.addEventListener('beforeunload', beforeUnloadHandler);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnloadHandler);
});
</script>

<template>
    <div class="builder-page">
        <header class="builder-header">
            <div class="header-text">
                <p class="header-kicker">SETTINGS / FORM BUILDER</p>
                <h1>{{ store.definition.meta.label || 'Nouveau formulaire' }}</h1>
                <p>Modeleur JSON centralise, sections verrouillees metier et rendu en temps reel.</p>
            </div>
            <div class="header-actions">
                <Button label="Retour liste" icon="pi pi-arrow-left" text @click="goBack" />
                <Button label="Definir par defaut" icon="pi pi-star" severity="secondary" outlined @click="setAsDefault" />
                <Button label="Sauvegarder" icon="pi pi-save" :loading="store.saving" @click="save" />
            </div>
        </header>

        <div class="builder-meta card-like">
            <div class="meta-grid">
                <div class="meta-item">
                    <label>Label</label>
                    <InputText :model-value="store.definition.meta.label" @update:model-value="store.setFormMeta({ label: $event })" />
                </div>
                <div class="meta-item">
                    <label>Code</label>
                    <InputText :model-value="store.definition.meta.code" @update:model-value="store.setFormMeta({ code: $event })" />
                </div>
                <div class="meta-item full">
                    <label>Description</label>
                    <Textarea :model-value="store.definition.meta.description" @update:model-value="store.setFormMeta({ description: $event })" rows="2" autoResize />
                </div>
                <div class="meta-item full">
                    <label>Mode de builder</label>
                    <SelectButton v-model="builderMode" :options="builderModeOptions" optionLabel="label" optionValue="value" />
                </div>
            </div>
        </div>

        <div class="builder-grid">
            <aside class="panel card-like left-panel" @dragover.prevent @drop="onDropPreset">
                <div class="panel-title-row">
                    <h3>Structure</h3>
                    <Button icon="pi pi-plus" text rounded aria-label="Add tab" @click="store.addTab(); ensureActiveTab()" />
                </div>

                <div class="tabs-list">
                    <button
                        v-for="tab in store.tabs"
                        :key="tab.id"
                        class="tab-chip"
                        :class="{ active: String(tab.id) === String(activeTabId) }"
                        @click="activeTabId = tab.id; store.selectNode({ nodeType: 'tab', tabId: tab.id })"
                    >
                        {{ tab.title }}
                    </button>
                </div>

                <div v-if="selectedTab" class="tab-actions-row">
                    <Button icon="pi pi-arrow-up" text rounded :disabled="selectedTab.configuration?.isSystem" @click="store.moveTab(selectedTab.id, 'up')" />
                    <Button icon="pi pi-arrow-down" text rounded :disabled="selectedTab.configuration?.isSystem" @click="store.moveTab(selectedTab.id, 'down')" />
                    <Button icon="pi pi-trash" text rounded severity="danger" :disabled="selectedTab.configuration?.isSystem" @click="store.removeTab(selectedTab.id); ensureActiveTab()" />
                    <Button icon="pi pi-list" text rounded @click="store.addSection(selectedTab.id)" />
                </div>

                <div class="section-tree" v-if="selectedTab">
                    <div class="section-node" v-for="section in selectedTab.sections" :key="section.id">
                        <div class="section-head" @click="store.selectNode({ nodeType: 'section', tabId: selectedTab.id, sectionId: section.id, fieldId: null })">
                            <span>{{ section.title }}</span>
                            <small>{{ section.type === 'preset' ? 'preset' : 'section' }}</small>
                        </div>
                        <div class="section-actions">
                            <Button icon="pi pi-arrow-up" text rounded @click="store.moveSection(selectedTab.id, section.id, 'up')" />
                            <Button icon="pi pi-arrow-down" text rounded @click="store.moveSection(selectedTab.id, section.id, 'down')" />
                            <Button icon="pi pi-power-off" text rounded @click="store.toggleSectionEnabled(selectedTab.id, section.id)" />
                            <Button icon="pi pi-trash" text rounded severity="danger" @click="store.removeSection(selectedTab.id, section.id)" />
                        </div>

                        <div v-if="section.type === 'section'" class="field-mini-list">
                            <button
                                v-for="field in section.fields"
                                :key="field.id"
                                class="field-mini"
                                @click="store.selectNode({ nodeType: 'field', tabId: selectedTab.id, sectionId: section.id, fieldId: field.id })"
                            >
                                {{ field.label }}
                            </button>
                        </div>
                    </div>
                    <div v-if="!selectedTab.sections.length" class="empty-note">Ajoutez une section ou deposez un preset metier.</div>
                </div>
            </aside>

            <main class="panel card-like center-panel" @dragover.prevent @drop="onDropPreset">
                <div class="panel-title-row">
                    <h3 v-if="builderMode === 'layout'">Apercu temps reel</h3>
                    <h3 v-else-if="builderMode === 'json'">Editeur JSON central</h3>
                    <h3 v-else>Gestionnaire presets verrouilles</h3>
                    <Button :label="showJson ? 'Masquer JSON' : 'Voir JSON'" text @click="showJson = !showJson" />
                </div>

                <FormComponentRenderer
                    v-if="builderMode === 'layout'"
                    :definition="store.definition"
                    :selected-node="store.selectedNode"
                    :active-tab-id="activeTabId"
                    @select="handleSelect"
                    @context-action="handleContextAction"
                />

                <div v-else-if="builderMode === 'json'" class="json-editor-wrap">
                    <Textarea v-model="jsonDraft" rows="18" autoResize class="json-editor" />
                    <div class="json-editor-actions">
                        <Button label="Recharger depuis etat courant" severity="secondary" outlined @click="jsonDraft = copyJson" />
                        <Button label="Appliquer JSON" icon="pi pi-check" @click="applyJsonDraft" />
                    </div>
                </div>

                <div v-else class="preset-manager">
                    <div v-for="instance in presetInstances" :key="`${instance.tabId}-${instance.sectionId}`" class="preset-row">
                        <div>
                            <strong>{{ instance.sectionTitle }}</strong>
                            <p>{{ instance.presetKey }} • {{ instance.tabTitle }}</p>
                        </div>
                        <div class="preset-row-actions">
                            <Button icon="pi pi-arrow-up" text rounded @click="store.moveSection(instance.tabId, instance.sectionId, 'up')" />
                            <Button icon="pi pi-arrow-down" text rounded @click="store.moveSection(instance.tabId, instance.sectionId, 'down')" />
                            <ToggleSwitch :model-value="instance.enabled" @update:model-value="store.toggleSectionEnabled(instance.tabId, instance.sectionId)" />
                        </div>
                    </div>
                    <div v-if="!presetInstances.length" class="empty-note">Aucun preset ajoute. Glissez-en depuis la palette de droite.</div>
                </div>

                <pre v-if="showJson" class="json-preview">{{ copyJson }}</pre>
            </main>

            <aside class="panel card-like right-panel">
                <h3>Composants & configuration</h3>

                <div class="palette-block" v-if="builderMode !== 'presets'">
                    <h4>Composants de base</h4>
                    <div class="palette-grid">
                        <button v-for="item in baseComponents" :key="item.key" class="palette-item" @click="addBaseField(item.key)">
                            <i :class="item.icon"></i>
                            <span>{{ item.label }}</span>
                        </button>
                    </div>
                </div>

                <div class="palette-block">
                    <h4>Composants predefinis (drag & drop)</h4>
                    <div class="palette-grid">
                        <button
                            v-for="preset in presetItems"
                            :key="preset.key"
                            class="palette-item preset"
                            draggable="true"
                            @dragstart="onPresetDragStart($event, preset.key)"
                            @click="activeTabId && store.addPresetSection(activeTabId, preset.key)"
                        >
                            <i class="pi pi-box"></i>
                            <span>{{ preset.title }}</span>
                        </button>
                    </div>
                    <small>Les presets sont verrouilles structurellement: deplacement/activation autorises, pas d'edition interne.</small>
                </div>

                <div class="config-block unsaved-warning" v-if="hasUnsavedChanges">
                    <h4>Etat</h4>
                    <p>Modifications non sauvegardees detectees.</p>
                </div>

                <div class="config-block" v-if="store.selectedNode.nodeType === 'tab' && store.selectedTab">
                    <h4>Proprietes onglet</h4>
                    <label>Titre</label>
                    <InputText :model-value="store.selectedTab.title" @update:model-value="store.updateSelectedTab({ title: $event })" />
                    <label>Code</label>
                    <InputText :model-value="store.selectedTab.code" @update:model-value="store.updateSelectedTab({ code: $event })" />
                </div>

                <div class="config-block" v-else-if="store.selectedNode.nodeType === 'section' && store.selectedSection">
                    <h4>Proprietes section</h4>
                    <label>Titre</label>
                    <InputText :model-value="store.selectedSection.title" @update:model-value="store.updateSelectedSection({ title: $event })" />
                    <label>Code</label>
                    <InputText :model-value="store.selectedSection.code" @update:model-value="store.updateSelectedSection({ code: $event })" />
                    <div class="toggle-line">
                        <span>Section active</span>
                        <ToggleSwitch :model-value="store.selectedSection.enabled !== false" @update:model-value="store.updateSelectedSection({ enabled: $event })" />
                    </div>
                    <small v-if="store.selectedSection.locked">Preset verrouille: structure interne non editable.</small>
                </div>

                <div class="config-block" v-else-if="store.selectedNode.nodeType === 'field' && store.selectedField">
                    <h4>Proprietes champ</h4>
                    <label>Label</label>
                    <InputText :model-value="store.selectedField.label" @update:model-value="store.updateSelectedField({ label: $event })" />
                    <label>Code</label>
                    <InputText :model-value="store.selectedField.code" @update:model-value="store.updateSelectedField({ code: $event })" />
                    <div class="toggle-line">
                        <span>Obligatoire</span>
                        <ToggleSwitch :model-value="store.selectedField.isRequired === true" @update:model-value="store.updateSelectedField({ isRequired: $event })" />
                    </div>
                    <div class="field-actions" v-if="canAddField">
                        <Button icon="pi pi-arrow-up" text rounded @click="store.moveField(store.selectedNode.tabId, store.selectedNode.sectionId, store.selectedNode.fieldId, 'up')" />
                        <Button icon="pi pi-arrow-down" text rounded @click="store.moveField(store.selectedNode.tabId, store.selectedNode.sectionId, store.selectedNode.fieldId, 'down')" />
                        <Button icon="pi pi-trash" text rounded severity="danger" @click="store.removeField(store.selectedNode.tabId, store.selectedNode.sectionId, store.selectedNode.fieldId)" />
                    </div>
                </div>

                <div class="config-block" v-else>
                    <h4>Selection</h4>
                    <p>Selectionnez un onglet, une section ou un champ pour editer ses proprietes.</p>
                </div>
            </aside>
        </div>
    </div>
</template>

<style scoped>
.builder-page {
    display: grid;
    gap: 1rem;
    padding-bottom: 1rem;
}

.builder-header {
    border: 1px solid var(--surface-border);
    border-radius: 18px;
    padding: 1rem;
    background: radial-gradient(circle at top right, color-mix(in srgb, var(--primary-color), white 90%), var(--surface-card));
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 1rem;
}

.header-kicker {
    margin: 0;
    font-size: 0.72rem;
    letter-spacing: 0.12em;
    color: var(--primary-color);
    font-weight: 700;
}

.header-text h1 {
    margin: 0.2rem 0 0;
    font-size: 1.55rem;
}

.header-text p {
    margin: 0.45rem 0 0;
    color: var(--text-color-secondary);
}

.header-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.card-like {
    border: 1px solid var(--surface-border);
    border-radius: 16px;
    background: var(--surface-card);
}

.builder-meta {
    padding: 0.8rem;
}

.meta-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.7rem;
}

.meta-item {
    display: grid;
    gap: 0.28rem;
}

.meta-item.full {
    grid-column: 1 / -1;
}

.meta-item label {
    font-size: 0.78rem;
    color: var(--text-color-secondary);
}

.builder-grid {
    display: grid;
    grid-template-columns: 280px minmax(0, 1fr) 330px;
    gap: 0.8rem;
    min-height: 560px;
}

.panel {
    padding: 0.8rem;
    display: grid;
    gap: 0.75rem;
    align-content: start;
}

.panel-title-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.4rem;
}

.panel h3 {
    margin: 0;
    font-size: 1rem;
}

.tabs-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.tab-chip {
    border: 1px solid var(--surface-border);
    background: var(--surface-0);
    border-radius: 999px;
    font-size: 0.78rem;
    padding: 0.28rem 0.65rem;
    cursor: pointer;
}

.tab-chip.active {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.tab-actions-row {
    display: flex;
    gap: 0.3rem;
}

.section-tree {
    display: grid;
    gap: 0.55rem;
}

.section-node {
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    padding: 0.5rem;
    display: grid;
    gap: 0.45rem;
}

.section-head {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 0.86rem;
}

.section-head small {
    color: var(--text-color-secondary);
}

.section-actions {
    display: flex;
    gap: 0.2rem;
}

.field-mini-list {
    display: grid;
    gap: 0.25rem;
}

.field-mini {
    text-align: left;
    border: 1px solid var(--surface-border);
    border-radius: 7px;
    padding: 0.25rem 0.4rem;
    background: var(--surface-0);
    font-size: 0.75rem;
    cursor: pointer;
}

.empty-note {
    border: 1px dashed var(--surface-border);
    border-radius: 10px;
    padding: 0.7rem;
    color: var(--text-color-secondary);
    font-size: 0.82rem;
}

.json-preview {
    margin: 0;
    max-height: 280px;
    overflow: auto;
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    padding: 0.7rem;
    background: color-mix(in srgb, var(--surface-0), black 3%);
    font-size: 0.76rem;
}

.json-editor-wrap {
    display: grid;
    gap: 0.6rem;
}

.json-editor {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}

.json-editor-actions {
    display: flex;
    gap: 0.45rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.preset-manager {
    display: grid;
    gap: 0.55rem;
}

.preset-row {
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    padding: 0.6rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.8rem;
}

.preset-row p {
    margin: 0.25rem 0 0;
    font-size: 0.78rem;
    color: var(--text-color-secondary);
}

.preset-row-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.palette-block,
.config-block {
    border: 1px solid var(--surface-border);
    border-radius: 12px;
    padding: 0.65rem;
    display: grid;
    gap: 0.45rem;
}

.palette-block h4,
.config-block h4 {
    margin: 0;
    font-size: 0.88rem;
}

.palette-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.4rem;
}

.palette-item {
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    background: var(--surface-0);
    color: var(--text-color);
    padding: 0.5rem;
    display: grid;
    justify-items: start;
    gap: 0.28rem;
    cursor: pointer;
    font-size: 0.78rem;
}

.palette-item.preset {
    border-style: dashed;
}

.config-block label {
    font-size: 0.75rem;
    color: var(--text-color-secondary);
}

.toggle-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
}

.field-actions {
    display: flex;
    gap: 0.25rem;
}

.unsaved-warning {
    border-color: color-mix(in srgb, #f97316, var(--surface-border) 35%);
    background: color-mix(in srgb, #f97316, transparent 95%);
}

@media (max-width: 1200px) {
    .builder-grid {
        grid-template-columns: 1fr;
    }

    .left-panel,
    .center-panel,
    .right-panel {
        min-height: auto;
    }
}
</style>

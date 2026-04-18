import { defineStore } from 'pinia';
import {
    createEmptyFormDefinition,
    createPresetSection,
    createSection,
    createSimpleField,
    createTab,
    enforceSystemTabOrder,
    isSystemTabCode,
    mapBackendFormToUnified,
    mapUnifiedToBackendPayload,
    slugify
} from '@/utils/formBuilderModel';
import {
    createFormulaire,
    deleteFormulaire,
    duplicateFormulaire,
    fetchDefaultMedicalForm,
    fetchFormulaireById,
    fetchFormulaires,
    publishFormulaire,
    setDefaultMedicalFormCode,
    updateFormulaire
} from '@/services/formulairesService';

const moveItem = (array, fromIndex, toIndex) => {
    if (!Array.isArray(array) || fromIndex === toIndex) return;
    if (fromIndex < 0 || toIndex < 0) return;
    if (fromIndex >= array.length || toIndex >= array.length) return;

    const next = [...array];
    const [item] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, item);

    next.forEach((entry, index) => {
        entry.sortOrder = (index + 1) * 10;
    });

    array.splice(0, array.length, ...next);
};

const tokenFromStorage = () => localStorage.getItem('token');

export const useFormBuilderStore = defineStore('formBuilder', {
    state: () => ({
        loading: false,
        saving: false,
        deleting: false,
        publishing: false,
        forms: [],
        activeFormId: null,
        defaultFormCode: 'fiche-medicale-standard',
        definition: createEmptyFormDefinition(),
        selectedNode: {
            nodeType: null,
            tabId: null,
            sectionId: null,
            fieldId: null
        }
    }),
    getters: {
        activeFormSummary(state) {
            return state.forms.find((item) => String(item.id) === String(state.activeFormId)) || null;
        },
        tabs(state) {
            return state.definition?.tabs || [];
        },
        selectedTab(state) {
            if (!state.selectedNode.tabId) return null;
            return (state.definition?.tabs || []).find((tab) => String(tab.id) === String(state.selectedNode.tabId)) || null;
        },
        selectedSection(state) {
            const tabs = state.definition?.tabs || [];
            for (const tab of tabs) {
                const section = (tab.sections || []).find((item) => String(item.id) === String(state.selectedNode.sectionId));
                if (section) return section;
            }
            return null;
        },
        selectedField(state) {
            const tabs = state.definition?.tabs || [];
            for (const tab of tabs) {
                for (const section of tab.sections || []) {
                    const field = (section.fields || []).find((item) => String(item.id) === String(state.selectedNode.fieldId));
                    if (field) return field;
                }
            }
            return null;
        }
    },
    actions: {
        resetSelection() {
            this.selectedNode = {
                nodeType: null,
                tabId: null,
                sectionId: null,
                fieldId: null
            };
        },
        selectNode(payload = {}) {
            this.selectedNode = {
                nodeType: payload.nodeType || null,
                tabId: payload.tabId || null,
                sectionId: payload.sectionId || null,
                fieldId: payload.fieldId || null
            };
        },
        normalizeSortOrder() {
            this.definition.tabs = enforceSystemTabOrder(this.definition?.tabs || []);

            (this.definition?.tabs || []).forEach((tab, tabIndex) => {
                tab.sortOrder = (tabIndex + 1) * 10;
                tab.configuration = {
                    ...(tab.configuration || {}),
                    isSystem: isSystemTabCode(tab.code),
                    locked: isSystemTabCode(tab.code) || tab.configuration?.locked === true
                };
                (tab.sections || []).forEach((section, sectionIndex) => {
                    section.sortOrder = (sectionIndex + 1) * 10;
                    (section.fields || []).forEach((field, fieldIndex) => {
                        field.sortOrder = (fieldIndex + 1) * 10;
                    });
                });
            });
        },
        async loadForms(force = false) {
            if (this.loading && !force) return;

            this.loading = true;
            try {
                const token = tokenFromStorage();
                const forms = await fetchFormulaires(token);
                this.forms = Array.isArray(forms) ? forms : [];

                const defaultForm = await fetchDefaultMedicalForm(token);
                if (defaultForm?.code) {
                    this.defaultFormCode = defaultForm.code;
                    const exists = this.forms.some((form) => String(form.id) === String(defaultForm.id));
                    if (!exists) {
                        this.forms = [defaultForm, ...this.forms];
                    }
                }

                if (!this.activeFormId && this.forms.length > 0) {
                    this.activeFormId = this.forms[0].id;
                }
            } finally {
                this.loading = false;
            }
        },
        async loadFormById(formId) {
            const token = tokenFromStorage();
            const source = await fetchFormulaireById(formId, token);

            if (!source) {
                throw new Error('Formulaire introuvable');
            }

            this.activeFormId = source.id;
            this.definition = mapBackendFormToUnified(source);
            this.normalizeSortOrder();

            const firstTab = this.definition.tabs[0] || null;
            const firstSection = firstTab?.sections?.[0] || null;
            const firstField = firstSection?.fields?.[0] || null;

            this.selectNode({
                nodeType: firstField ? 'field' : firstSection ? 'section' : firstTab ? 'tab' : null,
                tabId: firstTab?.id || null,
                sectionId: firstSection?.id || null,
                fieldId: firstField?.id || null
            });
        },
        initializeNewForm() {
            this.activeFormId = null;
            this.definition = createEmptyFormDefinition({
                code: `form-${Date.now()}`,
                label: 'Nouveau formulaire'
            });
            this.normalizeSortOrder();

            const firstTab = this.definition.tabs[0];
            this.selectNode({
                nodeType: 'tab',
                tabId: firstTab?.id || null,
                sectionId: null,
                fieldId: null
            });
        },
        setFormMeta(payload = {}) {
            this.definition.meta = {
                ...this.definition.meta,
                ...payload
            };
        },
        addTab(title = 'Nouvel onglet') {
            const tab = createTab({ title });
            this.definition.tabs.push(tab);
            this.normalizeSortOrder();
            this.selectNode({ nodeType: 'tab', tabId: tab.id, sectionId: null, fieldId: null });
        },
        removeTab(tabId) {
            const target = this.definition.tabs.find((tab) => String(tab.id) === String(tabId));
            if (target?.configuration?.isSystem || isSystemTabCode(target?.code)) {
                return;
            }

            this.definition.tabs = this.definition.tabs.filter((tab) => String(tab.id) !== String(tabId));
            this.normalizeSortOrder();
            this.resetSelection();
        },
        moveTab(tabId, direction = 'up') {
            const tabs = this.definition.tabs || [];
            const index = tabs.findIndex((tab) => String(tab.id) === String(tabId));
            if (index < 0) return;
            if (tabs[index]?.configuration?.isSystem || isSystemTabCode(tabs[index]?.code)) return;

            const toIndex = direction === 'up' ? index - 1 : index + 1;
            if (toIndex < 0 || toIndex >= tabs.length) return;
            if (tabs[toIndex]?.configuration?.isSystem || isSystemTabCode(tabs[toIndex]?.code)) return;
            moveItem(tabs, index, toIndex);
            this.normalizeSortOrder();
        },
        addSection(tabId, title = 'Nouvelle section') {
            const tab = this.definition.tabs.find((entry) => String(entry.id) === String(tabId));
            if (!tab) return;

            const section = createSection({ title });
            tab.sections.push(section);
            this.normalizeSortOrder();
            this.selectNode({ nodeType: 'section', tabId: tab.id, sectionId: section.id, fieldId: null });
        },
        addPresetSection(tabId, presetKey) {
            const tab = this.definition.tabs.find((entry) => String(entry.id) === String(tabId));
            if (!tab) return;

            const section = createPresetSection(presetKey);
            tab.sections.push(section);
            this.normalizeSortOrder();
            this.selectNode({ nodeType: 'section', tabId: tab.id, sectionId: section.id, fieldId: null });
        },
        removeSection(tabId, sectionId) {
            const tab = this.definition.tabs.find((entry) => String(entry.id) === String(tabId));
            if (!tab) return;

            tab.sections = tab.sections.filter((section) => String(section.id) !== String(sectionId));
            this.normalizeSortOrder();
            this.resetSelection();
        },
        toggleSectionEnabled(tabId, sectionId) {
            const tab = this.definition.tabs.find((entry) => String(entry.id) === String(tabId));
            if (!tab) return;

            const section = tab.sections.find((entry) => String(entry.id) === String(sectionId));
            if (!section) return;

            section.enabled = section.enabled === false;
        },
        moveSection(tabId, sectionId, direction = 'up') {
            const tab = this.definition.tabs.find((entry) => String(entry.id) === String(tabId));
            if (!tab) return;

            const index = tab.sections.findIndex((section) => String(section.id) === String(sectionId));
            if (index < 0) return;

            const toIndex = direction === 'up' ? index - 1 : index + 1;
            moveItem(tab.sections, index, toIndex);
        },
        addField(tabId, sectionId, fieldType = 'inputText') {
            const tab = this.definition.tabs.find((entry) => String(entry.id) === String(tabId));
            if (!tab) return;

            const section = tab.sections.find((entry) => String(entry.id) === String(sectionId));
            if (!section || section.locked) return;

            const field = createSimpleField({ fieldType, label: `Champ ${section.fields.length + 1}` });
            field.code = `${slugify(section.title)}__${slugify(field.label)}_${section.fields.length + 1}`;
            section.fields.push(field);
            this.normalizeSortOrder();
            this.selectNode({ nodeType: 'field', tabId: tab.id, sectionId: section.id, fieldId: field.id });
        },
        removeField(tabId, sectionId, fieldId) {
            const tab = this.definition.tabs.find((entry) => String(entry.id) === String(tabId));
            if (!tab) return;

            const section = tab.sections.find((entry) => String(entry.id) === String(sectionId));
            if (!section || section.locked) return;

            section.fields = section.fields.filter((field) => String(field.id) !== String(fieldId));
            this.normalizeSortOrder();
            this.resetSelection();
        },
        moveField(tabId, sectionId, fieldId, direction = 'up') {
            const tab = this.definition.tabs.find((entry) => String(entry.id) === String(tabId));
            if (!tab) return;

            const section = tab.sections.find((entry) => String(entry.id) === String(sectionId));
            if (!section || section.locked) return;

            const index = section.fields.findIndex((field) => String(field.id) === String(fieldId));
            if (index < 0) return;

            const toIndex = direction === 'up' ? index - 1 : index + 1;
            moveItem(section.fields, index, toIndex);
        },
        updateSelectedTab(payload = {}) {
            const tab = this.selectedTab;
            if (!tab) return;

            Object.assign(tab, payload);
            if (payload.title && !payload.code) {
                tab.code = slugify(payload.title);
            }
        },
        updateSelectedSection(payload = {}) {
            const section = this.selectedSection;
            if (!section) return;

            Object.assign(section, payload);
            if (payload.title && !payload.code) {
                section.code = slugify(payload.title);
            }
        },
        updateSelectedField(payload = {}) {
            const field = this.selectedField;
            if (!field) return;

            Object.assign(field, payload);
            if (payload.label && !payload.code) {
                field.code = slugify(payload.label);
            }
        },
        async saveActiveForm() {
            this.normalizeSortOrder();
            this.saving = true;

            try {
                const token = tokenFromStorage();
                const payload = mapUnifiedToBackendPayload(this.definition);

                let saved;
                if (this.activeFormId) {
                    saved = await updateFormulaire(this.activeFormId, payload, token);
                } else {
                    saved = await createFormulaire(payload, token);
                }

                if (saved?.id) {
                    this.activeFormId = saved.id;
                }

                const refreshed = await fetchFormulaires(token);
                this.forms = Array.isArray(refreshed) ? refreshed : this.forms;

                return saved;
            } finally {
                this.saving = false;
            }
        },
        async removeActiveForm() {
            if (!this.activeFormId) return;

            this.deleting = true;
            try {
                const token = tokenFromStorage();
                await deleteFormulaire(this.activeFormId, token);
                this.forms = this.forms.filter((item) => String(item.id) !== String(this.activeFormId));
                this.activeFormId = null;
                this.initializeNewForm();
            } finally {
                this.deleting = false;
            }
        },
        async duplicateForm(formId, label = null) {
            const token = tokenFromStorage();
            const duplicated = await duplicateFormulaire(formId, label, token);
            const refreshed = await fetchFormulaires(token);
            this.forms = Array.isArray(refreshed) ? refreshed : this.forms;
            return duplicated;
        },
        async publishForm(formId) {
            this.publishing = true;
            try {
                const token = tokenFromStorage();
                const published = await publishFormulaire(formId, token);
                const refreshed = await fetchFormulaires(token);
                this.forms = Array.isArray(refreshed) ? refreshed : this.forms;
                return published;
            } finally {
                this.publishing = false;
            }
        },
        async setDefaultForm(code) {
            const token = tokenFromStorage();
            await setDefaultMedicalFormCode(code, token);
            this.defaultFormCode = code;
        }
    }
});

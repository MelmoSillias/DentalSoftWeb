const SIMPLE_FIELD_TYPES = ['inputText', 'inputNumber', 'date', 'select', 'textarea'];

export const SYSTEM_TABS = [
    { code: 'informations-patient', title: 'Informations patient', locked: true, placement: 'first' },
    { code: 'seances-passees', title: 'Seances passees', locked: true, placement: 'before-last' },
    { code: 'consultation-en-cours', title: 'Consultation en cours', locked: true, placement: 'last' }
];

const SYSTEM_TAB_CODES = new Set(SYSTEM_TABS.map((entry) => entry.code));

const PRESET_DEFINITIONS = {
    entretienVerbal: {
        key: 'entretien-verbal',
        title: 'Entretien verbal',
        description: 'Cartes reutilisables pour anamnese et habitudes.',
        group: 'Entretien verbal',
        dataSchema: {
            motifConsultation: 'string',
            anamnese: 'string',
            habitudes: 'array'
        },
        fields: [
            { code: 'entretien__motif_consultation', label: 'Motif de consultation', fieldType: 'inputText', sortOrder: 10 },
            { code: 'entretien__anamnese', label: 'Anamnese', fieldType: 'textarea', sortOrder: 20 },
            { code: 'entretien__habitudes', label: 'Habitudes', fieldType: 'textarea', sortOrder: 30 }
        ]
    },
    exobuccalInspection: {
        key: 'exobuccal-inspection',
        title: 'Exobuccal - Inspection',
        description: 'Section reutilisable d inspection clinique.',
        group: 'Examen clinique',
        dataSchema: {
            notes: 'string',
            observations: 'array'
        },
        fields: [
            { code: 'exobuccal_inspection__notes', label: 'Notes inspection', fieldType: 'textarea', sortOrder: 10 }
        ]
    },
    exobuccalPalpation: {
        key: 'exobuccal-palpation',
        title: 'Exobuccal - Palpation',
        description: 'Section reutilisable de palpation clinique.',
        group: 'Examen clinique',
        dataSchema: {
            notes: 'string',
            triggerZones: 'array'
        },
        fields: [
            { code: 'exobuccal_palpation__notes', label: 'Notes palpation', fieldType: 'textarea', sortOrder: 10 }
        ]
    },
    chaineGanglionnaire: {
        key: 'chaine-ganglionnaire',
        title: 'Chaine ganglionnaire',
        description: 'Section reutilisable pour chaines ganglionnaires.',
        group: 'Examen clinique',
        dataSchema: {
            regions: 'array',
            observations: 'string'
        },
        fields: [
            { code: 'chaine_ganglionnaire__notes', label: 'Observation ganglionnaire', fieldType: 'textarea', sortOrder: 10 }
        ]
    },
    examenEndobuccal: {
        key: 'examen-endobuccal',
        title: 'Examen endobuccal',
        description: 'Section reutilisable de controle endobuccal.',
        group: 'Examen clinique',
        dataSchema: {
            boucheFermee: 'object',
            boucheOuverte: 'object'
        },
        fields: [
            { code: 'examen_endobuccal__bouche_fermee', label: 'Bouche fermee', fieldType: 'textarea', sortOrder: 10 },
            { code: 'examen_endobuccal__bouche_ouverte', label: 'Bouche ouverte', fieldType: 'textarea', sortOrder: 20 }
        ]
    },
    examensTissusMous: {
        key: 'examens-tissus-mous',
        title: 'Examens tissus mous',
        description: 'Section reutilisable tissus mous.',
        group: 'Examen clinique',
        dataSchema: {
            notes: 'string'
        },
        fields: [
            { code: 'examens_tissus_mous__notes', label: 'Notes tissus mous', fieldType: 'textarea', sortOrder: 10 }
        ]
    },
    examensTissusDurs: {
        key: 'examens-tissus-durs',
        title: 'Examens tissus durs',
        description: 'Section reutilisable tissus durs.',
        group: 'Examen clinique',
        dataSchema: {
            notes: 'string'
        },
        fields: [
            { code: 'examens_tissus_durs__notes', label: 'Notes tissus durs', fieldType: 'textarea', sortOrder: 10 }
        ]
    },
    examensComplementaires: {
        key: 'examens-complementaires',
        title: 'Examens complementaires',
        description: 'Section reutilisable pour examens complementaires.',
        group: 'Examen clinique',
        dataSchema: {
            bacterio: 'string',
            serologie: 'string',
            histologie: 'string'
        },
        fields: [
            { code: 'examens_complementaires__bacterio', label: 'Bacteriologie', fieldType: 'textarea', sortOrder: 10 },
            { code: 'examens_complementaires__serologie', label: 'Serologie', fieldType: 'textarea', sortOrder: 20 },
            { code: 'examens_complementaires__histologie', label: 'Histologie', fieldType: 'textarea', sortOrder: 30 }
        ]
    },
    ficheDocuments: {
        key: 'fiche-documents',
        title: 'Images et documents',
        description: 'Section reutilisable de documents et images.',
        group: 'Images Documents',
        dataSchema: {
            items: 'array',
            comments: 'string'
        },
        fields: [
            { code: 'documents__items', label: 'Elements', fieldType: 'textarea', sortOrder: 10 }
        ]
    },
    planTraitement: {
        key: 'plan-traitement',
        title: 'Plan de traitement',
        description: 'Section reutilisable de planification des soins.',
        group: 'Plan de traitement',
        dataSchema: {
            items: 'array'
        },
        fields: [
            { code: 'plan_traitement__items', label: 'Plan de traitement', fieldType: 'textarea', sortOrder: 10 }
        ]
    },
    formuleDentaire: {
        key: 'formule-dentaire',
        title: 'Formule dentaire',
        description: 'Section reutilisable de bilans dentaires.',
        group: 'Bilans',
        dataSchema: {
            odontogram: 'object',
            notes: 'string'
        },
        fields: [
            { code: 'bilans__formule_dentaire', label: 'Formule dentaire', fieldType: 'textarea', sortOrder: 10 }
        ]
    },
    ficheBilans: {
        key: 'fiche-bilans',
        title: 'Bilans reutilisables',
        description: 'Autres sections de bilans reutilisables.',
        group: 'Bilans',
        dataSchema: {
            bilanRadiographique: 'object',
            bilanSanguin: 'object',
            diagnostic: 'string'
        },
        fields: [
            { code: 'bilans__bilan_radiographique', label: 'Bilan radiographique', fieldType: 'textarea', sortOrder: 10 },
            { code: 'bilans__bilan_sanguin', label: 'Bilan sanguin', fieldType: 'textarea', sortOrder: 20 },
            { code: 'bilans__diagnostic_positif', label: 'Diagnostic positif', fieldType: 'textarea', sortOrder: 30 }
        ]
    },
    devis: {
        key: 'devis',
        title: 'Devis',
        description: 'Section reutilisable des devis.',
        group: 'Devis',
        dataSchema: {
            items: 'array',
            montant: 'number'
        },
        fields: [
            { code: 'devis__items', label: 'Items devis', fieldType: 'textarea', sortOrder: 10 }
        ]
    }
};

export const getPresetDefinitions = () => PRESET_DEFINITIONS;

const resolvePresetDefinition = (presetKey) => {
    if (!presetKey) return null;

    if (PRESET_DEFINITIONS[presetKey]) {
        return PRESET_DEFINITIONS[presetKey];
    }

    return Object.values(PRESET_DEFINITIONS).find((preset) => preset.key === presetKey) || null;
};

export const generateNodeId = (prefix = 'node') => `${prefix}_${Date.now()}_${Math.floor(Math.random() * 100000)}`;

export const slugify = (value = '') => {
    return String(value)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80) || generateNodeId('code');
};

export const createSimpleField = ({ label = 'Nouveau champ', fieldType = 'inputText' } = {}) => {
    const normalizedType = SIMPLE_FIELD_TYPES.includes(fieldType) ? fieldType : 'inputText';

    return {
        id: generateNodeId('field'),
        code: `${slugify(label)}__${Math.floor(Math.random() * 9999)}`,
        label,
        fieldType: normalizedType,
        rendererKey: null,
        sortOrder: 0,
        isRequired: false,
        isRepeated: false,
        defaultValue: normalizedType === 'inputNumber' ? 0 : '',
        options: normalizedType === 'select' ? [{ label: 'Option 1', value: 'option_1' }] : [],
        validationRules: {
            min: null,
            max: null,
            pattern: ''
        },
        conditions: [],
        configuration: {}
    };
};

export const createSection = ({ title = 'Nouvelle section' } = {}) => {
    return {
        id: generateNodeId('section'),
        code: slugify(title),
        title,
        type: 'section',
        presetKey: null,
        locked: false,
        enabled: true,
        sortOrder: 0,
        configuration: {
            layout: 'stacked'
        },
        fields: []
    };
};

export const createPresetSection = (presetKey) => {
    const preset = resolvePresetDefinition(presetKey);
    if (!preset) {
        throw new Error(`Preset introuvable: ${presetKey}`);
    }

    return {
        id: generateNodeId('section'),
        code: slugify(preset.title),
        title: preset.title,
        type: 'preset',
        presetKey: preset.key,
        locked: true,
        enabled: true,
        sortOrder: 0,
        configuration: {
            presetKey,
            dataSchema: preset.dataSchema
        },
        fields: preset.fields.map((field) => ({
            id: generateNodeId('field'),
            code: field.code,
            label: field.label,
            fieldType: field.fieldType,
            rendererKey: field.rendererKey || null,
            sortOrder: field.sortOrder || 0,
            isRequired: field.isRequired === true,
            isRepeated: field.isRepeated === true,
            defaultValue: field.defaultValue ?? null,
            options: field.options || [],
            validationRules: field.validationRules || {},
            conditions: field.conditions || [],
            configuration: field.configuration || {}
        }))
    };
};

export const createTab = ({ title = 'Nouvel onglet' } = {}) => {
    return {
        id: generateNodeId('tab'),
        code: slugify(title),
        title,
        sortOrder: 0,
        configuration: {
            layout: 'standard',
            locked: false,
            isSystem: false
        },
        sections: []
    };
};

const createSystemTab = ({ code, title, placement }) => {
    return {
        ...createTab({ title }),
        code,
        title,
        configuration: {
            layout: 'standard',
            locked: true,
            isSystem: true,
            placement
        }
    };
};

export const isSystemTabCode = (code) => SYSTEM_TAB_CODES.has(String(code || ''));

export const enforceSystemTabOrder = (tabs = []) => {
    const existing = Array.isArray(tabs) ? [...tabs] : [];
    const byCode = new Map(existing.map((tab) => [String(tab.code || ''), tab]));

    const first = SYSTEM_TABS[0];
    const beforeLast = SYSTEM_TABS[1];
    const last = SYSTEM_TABS[2];

    const firstTab = byCode.get(first.code) || createSystemTab(first);
    const beforeLastTab = byCode.get(beforeLast.code) || createSystemTab(beforeLast);
    const lastTab = byCode.get(last.code) || createSystemTab(last);

    firstTab.configuration = { ...(firstTab.configuration || {}), isSystem: true, locked: true, placement: 'first' };
    beforeLastTab.configuration = { ...(beforeLastTab.configuration || {}), isSystem: true, locked: true, placement: 'before-last' };
    lastTab.configuration = { ...(lastTab.configuration || {}), isSystem: true, locked: true, placement: 'last' };

    const dynamicTabs = existing.filter((tab) => !isSystemTabCode(tab.code));

    return [firstTab, ...dynamicTabs, beforeLastTab, lastTab];
};

export const createEmptyFormDefinition = ({
    code = 'fiche-medicale-standard',
    label = 'Fiche medicale standard',
    description = ''
} = {}) => {
    return {
        kind: 'medical-record',
        version: 1,
        meta: {
            code,
            label,
            description,
            status: 'draft'
        },
        configuration: {
            systemSections: ['infos', 'seances', 'consult'],
            transitionMode: 'double-read-double-write'
        },
        tabs: enforceSystemTabOrder([
            {
                ...createTab({ title: 'Entretien verbal' }),
                code: 'entretien-verbal',
                sections: [createPresetSection('entretien-verbal')]
            },
            {
                ...createTab({ title: 'Examen clinique' }),
                code: 'examen-clinique',
                sections: [
                    createPresetSection('exobuccal-inspection'),
                    createPresetSection('exobuccal-palpation'),
                    createPresetSection('chaine-ganglionnaire'),
                    createPresetSection('examen-endobuccal'),
                    createPresetSection('examens-tissus-mous'),
                    createPresetSection('examens-tissus-durs'),
                    createPresetSection('examens-complementaires')
                ]
            },
            {
                ...createTab({ title: 'Images Documents' }),
                code: 'images-documents',
                sections: [createPresetSection('fiche-documents')]
            },
            {
                ...createTab({ title: 'Plan de traitement' }),
                code: 'plan-traitement-tab',
                sections: [createPresetSection('plan-traitement')]
            },
            {
                ...createTab({ title: 'Bilans' }),
                code: 'bilans',
                sections: [createPresetSection('formule-dentaire'), createPresetSection('fiche-bilans')]
            },
            {
                ...createTab({ title: 'Devis' }),
                code: 'devis-tab',
                sections: [createPresetSection('devis')]
            }
        ])
    };
};

const normalizeFieldTypeFromBackend = (fieldType) => {
    if (fieldType === 'number') return 'inputNumber';
    if (fieldType === 'text') return 'inputText';
    if (fieldType === 'datetime' || fieldType === 'date') return 'date';
    if (fieldType === 'choice') return 'select';
    if (fieldType === 'textarea') return 'textarea';
    return fieldType || 'inputText';
};

const normalizeFieldTypeToBackend = (fieldType) => {
    if (fieldType === 'inputNumber') return 'number';
    if (fieldType === 'inputText') return 'text';
    if (fieldType === 'date') return 'date';
    if (fieldType === 'select') return 'choice';
    if (fieldType === 'textarea') return 'textarea';
    return fieldType || 'text';
};

export const mapBackendFormToUnified = (backendForm) => {
    const tabs = enforceSystemTabOrder((backendForm?.onglets || []).map((tab) => ({
        id: String(tab.id || generateNodeId('tab')),
        code: tab.code || slugify(tab.title || 'onglet'),
        title: tab.title || 'Onglet',
        sortOrder: tab.sortOrder || 0,
        configuration: {
            ...(tab.configuration || {}),
            isSystem: isSystemTabCode(tab.code),
            locked: isSystemTabCode(tab.code) || tab.configuration?.locked === true
        },
        sections: (tab.sections || []).map((section) => {
            const presetKey = section.componentKey || null;
            const isPreset = section.type === 'component' || Boolean(presetKey);

            return {
                id: String(section.id || generateNodeId('section')),
                code: section.code || slugify(section.title || 'section'),
                title: section.title || 'Section',
                type: isPreset ? 'preset' : 'section',
                presetKey,
                locked: isPreset,
                enabled: section.configuration?.enabled !== false,
                sortOrder: section.sortOrder || 0,
                configuration: section.configuration || {},
                fields: (section.fields || []).map((field) => ({
                    id: String(field.id || generateNodeId('field')),
                    code: field.code || slugify(field.label || 'champ'),
                    label: field.label || 'Champ',
                    fieldType: normalizeFieldTypeFromBackend(field.fieldType),
                    rendererKey: field.rendererKey || null,
                    sortOrder: field.sortOrder || 0,
                    isRequired: field.isRequired === true,
                    isRepeated: field.isRepeated === true,
                    defaultValue: field.defaultValue ?? null,
                    options: field.options || [],
                    validationRules: field.validationRules || {},
                    conditions: field.conditions || [],
                    configuration: field.configuration || {}
                }))
            };
        })
    })));

    return {
        kind: backendForm?.configuration?.kind || 'medical-record',
        version: Number(backendForm?.version || 1),
        meta: {
            id: backendForm?.id ?? null,
            code: backendForm?.code || 'formulaire',
            label: backendForm?.label || 'Formulaire',
            description: backendForm?.description || '',
            status: backendForm?.status || 'draft',
            publishedAt: backendForm?.publishedAt || null
        },
        configuration: backendForm?.configuration || {},
        tabs
    };
};

export const mapUnifiedToBackendPayload = (definition) => {
    return {
        code: definition?.meta?.code || 'formulaire',
        label: definition?.meta?.label || 'Formulaire',
        description: definition?.meta?.description || '',
        configuration: {
            ...(definition?.configuration || {}),
            kind: definition?.kind || 'medical-record'
        },
        onglets: (definition?.tabs || []).map((tab, tabIndex) => ({
            code: tab.code || slugify(tab.title || `onglet-${tabIndex + 1}`),
            title: tab.title || `Onglet ${tabIndex + 1}`,
            sortOrder: tab.sortOrder || (tabIndex + 1) * 10,
            configuration: tab.configuration || {},
            sections: (tab.sections || []).map((section, sectionIndex) => ({
                code: section.code || slugify(section.title || `section-${sectionIndex + 1}`),
                title: section.title || `Section ${sectionIndex + 1}`,
                type: section.type === 'preset' ? 'component' : 'custom',
                componentKey: section.type === 'preset' ? section.presetKey : null,
                sortOrder: section.sortOrder || (sectionIndex + 1) * 10,
                configuration: {
                    ...(section.configuration || {}),
                    enabled: section.enabled !== false
                },
                conditions: [],
                fields: (section.fields || []).map((field, fieldIndex) => ({
                    code: field.code || slugify(field.label || `champ-${fieldIndex + 1}`),
                    label: field.label || `Champ ${fieldIndex + 1}`,
                    fieldType: normalizeFieldTypeToBackend(field.fieldType),
                    rendererKey: field.rendererKey || null,
                    sortOrder: field.sortOrder || (fieldIndex + 1) * 10,
                    isRequired: field.isRequired === true,
                    isRepeated: field.isRepeated === true,
                    defaultValue: field.defaultValue ?? null,
                    options: field.options || [],
                    validationRules: field.validationRules || {},
                    conditions: field.conditions || []
                }))
            }))
        }))
    };
};

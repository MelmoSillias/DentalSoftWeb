<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import PresetConsultationEnCours from '@/components/forms/presets/PresetConsultationEnCours.vue';
import PresetChaineGanglionnaire from '@/components/forms/presets/PresetChaineGanglionnaire.vue';
import PresetDentalFormula from '@/components/forms/presets/PresetDentalFormula.vue';
import PresetDevis from '@/components/forms/presets/PresetDevis.vue';
import PresetDocumentsImages from '@/components/forms/presets/PresetDocumentsImages.vue';
import PresetEntretienVerbal from '@/components/forms/presets/PresetEntretienVerbal.vue';
import PresetExamenEndobuccal from '@/components/forms/presets/PresetExamenEndobuccal.vue';
import PresetExamensComplementaires from '@/components/forms/presets/PresetExamensComplementaires.vue';
import PresetExamensTissusDurs from '@/components/forms/presets/PresetExamensTissusDurs.vue';
import PresetExamensTissusMous from '@/components/forms/presets/PresetExamensTissusMous.vue';
import PresetExobuccalInspection from '@/components/forms/presets/PresetExobuccalInspection.vue';
import PresetExobuccalPalpation from '@/components/forms/presets/PresetExobuccalPalpation.vue';
import PresetFicheBilans from '@/components/forms/presets/PresetFicheBilans.vue';
import PresetInfosPatient from '@/components/forms/presets/PresetInfosPatient.vue';
import PresetSeancesPassees from '@/components/forms/presets/PresetSeancesPassees.vue';
import PresetDynamicTable from '@/components/forms/presets/PresetDynamicTable.vue';
import PresetTreatmentPlan from '@/components/forms/presets/PresetTreatmentPlan.vue';

const props = defineProps({
    definition: {
        type: Object,
        required: true
    },
    selectedNode: {
        type: Object,
        default: () => ({})
    },
    activeTabId: {
        type: [String, Number],
        default: null
    }
});

const emit = defineEmits(['select', 'context-action']);

const previewValues = reactive({});
const canvasRef = ref(null);
const sectionBodyRefs = new Map();

const interaction = reactive({
    active: false,
    mode: 'drag',
    nodeType: null,
    tabId: null,
    sectionId: null,
    fieldId: null,
    startX: 0,
    startY: 0,
    startBox: null,
    parentRect: null
});

const contextMenu = reactive({
    visible: false,
    x: 0,
    y: 0,
    nodeType: null,
    tabId: null,
    sectionId: null,
    fieldId: null,
    locked: false,
    enabled: true
});

const presetRenderers = {
    'informations-patient-core': PresetInfosPatient,
    'seances-passees-core': PresetSeancesPassees,
    'consultation-en-cours-core': PresetConsultationEnCours,
    'entretien-verbal': PresetEntretienVerbal,
    'exobuccal-inspection': PresetExobuccalInspection,
    'exobuccal-palpation': PresetExobuccalPalpation,
    'chaine-ganglionnaire': PresetChaineGanglionnaire,
    'examen-endobuccal': PresetExamenEndobuccal,
    'examens-tissus-mous': PresetExamensTissusMous,
    'examens-tissus-durs': PresetExamensTissusDurs,
    'examens-complementaires': PresetExamensComplementaires,
    'fiche-bilans': PresetFicheBilans,
    devis: PresetDevis,
    'formule-dentaire': PresetDentalFormula,
    'fiche-documents': PresetDocumentsImages,
    'plan-traitement': PresetTreatmentPlan,
    dentalFormula: PresetDentalFormula,
    dynamicTable: PresetDynamicTable,
    documentsImages: PresetDocumentsImages,
    treatmentPlan: PresetTreatmentPlan
};

const tabs = computed(() => props.definition?.tabs || []);

const effectiveActiveTabId = computed(() => {
    if (props.activeTabId) return props.activeTabId;
    return tabs.value[0]?.id || null;
});

const visibleTab = computed(() => {
    return tabs.value.find((tab) => String(tab.id) === String(effectiveActiveTabId.value)) || tabs.value[0] || null;
});

const visibleSections = computed(() => (visibleTab.value?.sections || []).filter((section) => section.enabled !== false));

const isSelected = ({ nodeType, tabId = null, sectionId = null, fieldId = null }) => {
    return String(props.selectedNode?.nodeType || '') === String(nodeType)
        && String(props.selectedNode?.tabId || '') === String(tabId || '')
        && String(props.selectedNode?.sectionId || '') === String(sectionId || '')
        && String(props.selectedNode?.fieldId || '') === String(fieldId || '');
};

const selectTab = (tabId) => {
    emit('select', { nodeType: 'tab', tabId, sectionId: null, fieldId: null });
};

const selectSection = (tabId, sectionId) => {
    emit('select', { nodeType: 'section', tabId, sectionId, fieldId: null });
};

const selectField = (tabId, sectionId, fieldId) => {
    emit('select', { nodeType: 'field', tabId, sectionId, fieldId });
};

const closeContextMenu = () => {
    contextMenu.visible = false;
};

const setSectionBodyRef = (sectionId, element) => {
    if (!sectionId) return;
    if (element) {
        sectionBodyRefs.set(String(sectionId), element);
    } else {
        sectionBodyRefs.delete(String(sectionId));
    }
};

const findSection = (tabId, sectionId) => {
    const tab = tabs.value.find((entry) => String(entry.id) === String(tabId));
    if (!tab) return null;
    return (tab.sections || []).find((entry) => String(entry.id) === String(sectionId)) || null;
};

const findField = (tabId, sectionId, fieldId) => {
    const section = findSection(tabId, sectionId);
    if (!section) return null;
    return (section.fields || []).find((entry) => String(entry.id) === String(fieldId)) || null;
};

const normalizePercentBox = (box, fallback = { x: 0, y: 0, w: 100, h: 30 }) => {
    const value = box || fallback;
    return {
        x: Number.isFinite(Number(value.x)) ? Number(value.x) : fallback.x,
        y: Number.isFinite(Number(value.y)) ? Number(value.y) : fallback.y,
        w: Number.isFinite(Number(value.w)) ? Number(value.w) : fallback.w,
        h: Number.isFinite(Number(value.h)) ? Number(value.h) : fallback.h
    };
};

const getSectionBox = (section, index) => {
    const total = Math.max(visibleSections.value.length, 1);
    const defaultHeight = Math.max(26, Math.floor(92 / total));
    const fallback = {
        x: 0,
        y: Math.min(index * (defaultHeight + 2), 90),
        w: 100,
        h: defaultHeight
    };
    const box = normalizePercentBox(section?.configuration?.box, fallback);
    return {
        x: Math.min(Math.max(box.x, 0), 95),
        y: Math.min(Math.max(box.y, 0), 95),
        w: Math.min(Math.max(box.w, 10), 100),
        h: Math.min(Math.max(box.h, 16), 100)
    };
};

const setSectionBox = (section, box) => {
    if (!section) return;
    section.configuration = section.configuration || {};
    section.configuration.box = normalizePercentBox(box);
};

const getFieldLayout = (section) => {
    section.configuration = section.configuration || {};
    section.configuration.fieldLayout = section.configuration.fieldLayout || {};
    return section.configuration.fieldLayout;
};

const getFieldBox = (section, field, index) => {
    const fields = section?.fields || [];
    const total = Math.max(fields.length, 1);
    const defaultHeight = Math.max(22, Math.floor(90 / total));
    const fallback = {
        x: 0,
        y: Math.min(index * (defaultHeight + 2), 92),
        w: 100,
        h: defaultHeight
    };
    const layout = getFieldLayout(section);
    const box = normalizePercentBox(layout[field.code], fallback);

    return {
        x: Math.min(Math.max(box.x, 0), 95),
        y: Math.min(Math.max(box.y, 0), 95),
        w: Math.min(Math.max(box.w, 15), 100),
        h: Math.min(Math.max(box.h, 18), 100)
    };
};

const setFieldBox = (section, fieldCode, box) => {
    const layout = getFieldLayout(section);
    layout[fieldCode] = normalizePercentBox(box);
};

const asPercent = (value, total) => {
    if (!total) return 0;
    return (value / total) * 100;
};

const clampPosition = (box, minW = 10, minH = 16) => {
    const next = { ...box };
    next.w = Math.min(Math.max(next.w, minW), 100);
    next.h = Math.min(Math.max(next.h, minH), 100);
    next.x = Math.min(Math.max(next.x, 0), 100 - next.w);
    next.y = Math.min(Math.max(next.y, 0), 100 - next.h);
    return next;
};

const startSectionInteraction = (event, section, sectionIndex, mode = 'drag') => {
    if (!visibleTab.value || section.locked) return;
    event.preventDefault();
    event.stopPropagation();
    closeContextMenu();
    selectSection(visibleTab.value.id, section.id);

    const parentRect = canvasRef.value?.getBoundingClientRect();
    if (!parentRect) return;

    interaction.active = true;
    interaction.mode = mode;
    interaction.nodeType = 'section';
    interaction.tabId = visibleTab.value.id;
    interaction.sectionId = section.id;
    interaction.fieldId = null;
    interaction.startX = event.clientX;
    interaction.startY = event.clientY;
    interaction.startBox = getSectionBox(section, sectionIndex);
    interaction.parentRect = parentRect;
};

const startFieldInteraction = (event, section, field, fieldIndex, mode = 'drag') => {
    if (!visibleTab.value || section.locked) return;
    event.preventDefault();
    event.stopPropagation();
    closeContextMenu();
    selectField(visibleTab.value.id, section.id, field.id);

    const container = sectionBodyRefs.get(String(section.id));
    const parentRect = container?.getBoundingClientRect();
    if (!parentRect) return;

    interaction.active = true;
    interaction.mode = mode;
    interaction.nodeType = 'field';
    interaction.tabId = visibleTab.value.id;
    interaction.sectionId = section.id;
    interaction.fieldId = field.id;
    interaction.startX = event.clientX;
    interaction.startY = event.clientY;
    interaction.startBox = getFieldBox(section, field, fieldIndex);
    interaction.parentRect = parentRect;
};

const onPointerMove = (event) => {
    if (!interaction.active) return;
    const section = findSection(interaction.tabId, interaction.sectionId);
    if (!section) return;

    const dxPercent = asPercent(event.clientX - interaction.startX, interaction.parentRect?.width || 1);
    const dyPercent = asPercent(event.clientY - interaction.startY, interaction.parentRect?.height || 1);

    if (interaction.nodeType === 'section') {
        const current = interaction.startBox || { x: 0, y: 0, w: 100, h: 30 };
        const next = interaction.mode === 'resize'
            ? clampPosition({ ...current, w: current.w + dxPercent, h: current.h + dyPercent }, 16, 20)
            : clampPosition({ ...current, x: current.x + dxPercent, y: current.y + dyPercent }, 16, 20);
        setSectionBox(section, next);
        return;
    }

    const field = findField(interaction.tabId, interaction.sectionId, interaction.fieldId);
    if (!field) return;

    const current = interaction.startBox || { x: 0, y: 0, w: 100, h: 30 };
    const next = interaction.mode === 'resize'
        ? clampPosition({ ...current, w: current.w + dxPercent, h: current.h + dyPercent }, 20, 18)
        : clampPosition({ ...current, x: current.x + dxPercent, y: current.y + dyPercent }, 20, 18);
    setFieldBox(section, field.code, next);
};

const stopInteraction = () => {
    interaction.active = false;
};

const openContextMenu = (event, payload) => {
    event.preventDefault();
    event.stopPropagation();

    contextMenu.visible = true;
    contextMenu.x = event.clientX;
    contextMenu.y = event.clientY;
    contextMenu.nodeType = payload.nodeType;
    contextMenu.tabId = payload.tabId;
    contextMenu.sectionId = payload.sectionId;
    contextMenu.fieldId = payload.fieldId || null;
    contextMenu.locked = payload.locked === true;
    contextMenu.enabled = payload.enabled !== false;

    if (payload.nodeType === 'section') {
        selectSection(payload.tabId, payload.sectionId);
    }
    if (payload.nodeType === 'field') {
        selectField(payload.tabId, payload.sectionId, payload.fieldId);
    }
};

const runContextAction = (action) => {
    if (!contextMenu.visible) return;
    emit('context-action', {
        action,
        nodeType: contextMenu.nodeType,
        tabId: contextMenu.tabId,
        sectionId: contextMenu.sectionId,
        fieldId: contextMenu.fieldId
    });
    closeContextMenu();
};

const sectionStyle = (section, sectionIndex) => {
    const box = getSectionBox(section, sectionIndex);
    return {
        left: `${box.x}%`,
        top: `${box.y}%`,
        width: `${box.w}%`,
        height: `${box.h}%`
    };
};

const fieldStyle = (section, field, fieldIndex) => {
    const box = getFieldBox(section, field, fieldIndex);
    return {
        left: `${box.x}%`,
        top: `${box.y}%`,
        width: `${box.w}%`,
        height: `${box.h}%`
    };
};

onMounted(() => {
    window.addEventListener('mousemove', onPointerMove);
    window.addEventListener('mouseup', stopInteraction);
    window.addEventListener('click', closeContextMenu);
});

onBeforeUnmount(() => {
    window.removeEventListener('mousemove', onPointerMove);
    window.removeEventListener('mouseup', stopInteraction);
    window.removeEventListener('click', closeContextMenu);
});

const resolveFieldOptions = (field) => {
    const options = Array.isArray(field?.options) ? field.options : [];
    return options.map((entry) => {
        if (typeof entry === 'object') {
            return {
                label: entry.label || entry.value || '',
                value: entry.value || entry.label || ''
            };
        }

        return { label: String(entry), value: String(entry) };
    });
};
</script>

<template>
    <div class="renderer-root">
        <div class="renderer-tabs">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                class="renderer-tab"
                :class="{ active: String(tab.id) === String(effectiveActiveTabId) }"
                @click="selectTab(tab.id)"
            >
                {{ tab.title }}
            </button>
        </div>

        <div v-if="!visibleTab" class="renderer-empty">
            Aucun onglet configure.
        </div>

        <div v-else class="renderer-canvas" ref="canvasRef">
            <div
                v-for="(section, sectionIndex) in visibleSections"
                :key="section.id"
                class="renderer-section"
                :class="{
                    selected: isSelected({ nodeType: 'section', tabId: visibleTab.id, sectionId: section.id }),
                    locked: section.locked === true
                }"
                @click.stop="selectSection(visibleTab.id, section.id)"
                @contextmenu="openContextMenu($event, { nodeType: 'section', tabId: visibleTab.id, sectionId: section.id, locked: section.locked, enabled: section.enabled })"
                :style="sectionStyle(section, sectionIndex)"
            >
                <div class="renderer-section-header" @mousedown.left="startSectionInteraction($event, section, sectionIndex, 'drag')">
                    <h4>{{ section.title }}</h4>
                    <span class="renderer-chip" :class="section.type === 'preset' ? 'preset' : 'base'">
                        {{ section.type === 'preset' ? 'Preset' : 'Section' }}
                    </span>
                </div>

                <button
                    v-if="section.locked !== true"
                    class="resize-handle section"
                    @mousedown.left="startSectionInteraction($event, section, sectionIndex, 'resize')"
                ></button>

                <div v-if="section.type === 'preset'" class="preset-content">
                    <component :is="presetRenderers[section.presetKey] || 'div'" :section="section">
                        <div class="preset-fallback">Renderer indisponible pour {{ section.presetKey }}</div>
                    </component>
                </div>

                <div v-else class="renderer-fields" :ref="(el) => setSectionBodyRef(section.id, el)">
                    <div
                        v-for="(field, fieldIndex) in section.fields"
                        :key="field.id"
                        class="renderer-field"
                        :class="{
                            selected: isSelected({ nodeType: 'field', tabId: visibleTab.id, sectionId: section.id, fieldId: field.id })
                        }"
                        @click.stop="selectField(visibleTab.id, section.id, field.id)"
                        @contextmenu="openContextMenu($event, { nodeType: 'field', tabId: visibleTab.id, sectionId: section.id, fieldId: field.id, locked: section.locked })"
                        :style="fieldStyle(section, field, fieldIndex)"
                    >
                        <div class="renderer-field-header" @mousedown.left="startFieldInteraction($event, section, field, fieldIndex, 'drag')">
                            <label class="renderer-label">{{ field.label }} <span v-if="field.isRequired">*</span></label>
                        </div>

                        <button
                            v-if="section.locked !== true"
                            class="resize-handle field"
                            @mousedown.left="startFieldInteraction($event, section, field, fieldIndex, 'resize')"
                        ></button>

                        <InputText v-if="field.fieldType === 'inputText'" v-model="previewValues[field.code]" />
                        <InputNumber v-else-if="field.fieldType === 'inputNumber'" v-model="previewValues[field.code]" class="w-full" />
                        <DatePicker v-else-if="field.fieldType === 'date'" v-model="previewValues[field.code]" showIcon fluid />
                        <Select
                            v-else-if="field.fieldType === 'select'"
                            v-model="previewValues[field.code]"
                            :options="resolveFieldOptions(field)"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Selectionner"
                        />
                        <Textarea v-else-if="field.fieldType === 'textarea'" v-model="previewValues[field.code]" rows="2" autoResize />
                        <InputText v-else v-model="previewValues[field.code]" />
                    </div>

                    <div v-if="!section.fields?.length" class="renderer-empty-fields">
                        Cette section ne contient pas encore de champ.
                    </div>
                </div>
            </div>

            <div v-if="!visibleSections.length" class="renderer-empty-fields canvas-empty">
                Aucune section dans cet onglet.
            </div>
        </div>

        <div
            v-if="contextMenu.visible"
            class="renderer-context-menu"
            :style="{ left: `${contextMenu.x}px`, top: `${contextMenu.y}px` }"
            @click.stop
        >
            <button class="ctx-item" @click="runContextAction('edit')">Modifier les proprietes</button>
            <button class="ctx-item" @click="runContextAction('toggle')" :disabled="contextMenu.locked">
                {{ contextMenu.enabled ? 'Desactiver' : 'Activer' }}
            </button>
            <button class="ctx-item danger" @click="runContextAction('delete')" :disabled="contextMenu.locked">Supprimer</button>
        </div>
    </div>
</template>

<style scoped>
.renderer-root {
    display: grid;
    gap: 0.9rem;
}

.renderer-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.renderer-tab {
    border: 1px solid var(--surface-border);
    border-radius: 999px;
    background: var(--surface-0);
    color: var(--text-color);
    padding: 0.3rem 0.8rem;
    font-size: 0.82rem;
    cursor: pointer;
}

.renderer-tab.active {
    background: var(--primary-color);
    color: #fff;
    border-color: var(--primary-color);
}

.renderer-canvas {
    position: relative;
    min-height: 700px;
    border: 1px solid var(--surface-border);
    border-radius: 16px;
    background:
        linear-gradient(90deg, color-mix(in srgb, var(--surface-border), transparent 70%) 1px, transparent 1px),
        linear-gradient(color-mix(in srgb, var(--surface-border), transparent 70%) 1px, transparent 1px),
        var(--surface-0);
    background-size: 24px 24px;
    overflow: hidden;
}

.renderer-section {
    position: absolute;
    border: 1px solid var(--surface-border);
    border-radius: 14px;
    background: var(--surface-0);
    padding: 0.8rem;
    cursor: default;
    min-height: 120px;
    overflow: hidden;
}

.renderer-section.locked {
    background: color-mix(in srgb, var(--surface-0), var(--primary-color) 3%);
}

.renderer-section.selected {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--primary-color), transparent 60%);
}

.renderer-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.5rem;
    cursor: move;
}

.renderer-section-header h4 {
    margin: 0;
    font-size: 1rem;
}

.renderer-chip {
    font-size: 0.72rem;
    border-radius: 999px;
    padding: 0.2rem 0.55rem;
}

.renderer-chip.preset {
    background: color-mix(in srgb, var(--primary-color), transparent 86%);
    color: var(--primary-color);
}

.renderer-chip.base {
    background: var(--surface-100);
    color: var(--text-color-secondary);
}

.renderer-fields {
    position: relative;
    border: 1px dashed color-mix(in srgb, var(--surface-border), transparent 30%);
    border-radius: 10px;
    min-height: 180px;
    height: calc(100% - 2.1rem);
}

.renderer-field {
    position: absolute;
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    padding: 0.55rem;
    background: var(--surface-0);
    min-height: 70px;
    overflow: hidden;
}

.renderer-field.selected {
    border-color: var(--primary-color);
}

.renderer-label {
    display: block;
    font-size: 0.78rem;
    margin-bottom: 0;
    color: var(--text-color-secondary);
}

.renderer-field-header {
    margin-bottom: 0.35rem;
    cursor: move;
}

.resize-handle {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 14px;
    height: 14px;
    border: none;
    border-top-left-radius: 8px;
    background: color-mix(in srgb, var(--primary-color), transparent 25%);
    cursor: nwse-resize;
}

.resize-handle.section {
    width: 16px;
    height: 16px;
}

.resize-handle.field {
    width: 12px;
    height: 12px;
}

.renderer-empty,
.renderer-empty-fields,
.preset-fallback {
    border: 1px dashed var(--surface-border);
    border-radius: 10px;
    padding: 0.75rem;
    font-size: 0.82rem;
    color: var(--text-color-secondary);
    background: var(--surface-0);
}

.canvas-empty {
    position: absolute;
    left: 1rem;
    right: 1rem;
    top: 1rem;
}

.renderer-context-menu {
    position: fixed;
    z-index: 50;
    display: grid;
    gap: 0.2rem;
    padding: 0.4rem;
    min-width: 180px;
    background: var(--surface-0);
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    box-shadow: 0 10px 22px rgba(0, 0, 0, 0.2);
}

.ctx-item {
    text-align: left;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: var(--text-color);
    font-size: 0.82rem;
    padding: 0.45rem 0.5rem;
    cursor: pointer;
}

.ctx-item:hover {
    background: color-mix(in srgb, var(--surface-border), transparent 50%);
}

.ctx-item:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.ctx-item.danger {
    color: #dc2626;
}
</style>

<script setup>
import Button from 'primevue/button';
import Select from 'primevue/select';
import { computed, ref, useId, watch } from 'vue';

const props = defineProps({
    sections: { type: Array, required: true },
    tall: { type: Boolean, default: false }
});
const activeSection = ref(props.sections[0]?.id);
const content = ref(null);
const instanceId = useId();
const currentIndex = computed(() => props.sections.findIndex((section) => section.id === activeSection.value));
const current = computed(() => props.sections[currentIndex.value]);

function navigate(id, focus = false) {
    activeSection.value = id;
    if (content.value) content.value.scrollTop = 0;
    if (focus) content.value?.focus();
}

watch(
    () => props.sections,
    (sections) => {
        if (!sections.some((section) => section.id === activeSection.value)) activeSection.value = sections[0]?.id;
    }
);
</script>

<template>
    <div class="medical-record" :class="{ 'medical-record--tall': tall }">
        <nav class="medical-record__nav" aria-label="Rubriques de la fiche médicale">
            <button v-for="section in sections" :key="section.id" type="button" :aria-pressed="activeSection === section.id" :aria-controls="`${instanceId}-content`" @click="navigate(section.id)">
                <span>{{ section.label }}</span>
            </button>
        </nav>
        <div class="medical-record__select">
            <label :for="instanceId + '-section'">Rubrique</label>
            <Select :inputId="instanceId + '-section'" :modelValue="activeSection" :options="sections" optionLabel="label" optionValue="id" aria-label="Rubrique de la fiche médicale" @update:modelValue="navigate" />
        </div>
        <div :id="`${instanceId}-content`" ref="content" class="medical-record__content" tabindex="-1" :aria-labelledby="`${instanceId}-title`">
            <header class="medical-record__heading">
                <h3 :id="`${instanceId}-title`">{{ current?.label }}</h3>
                <p>{{ current?.description }}</p>
            </header>
            <div v-for="section in sections" v-show="activeSection === section.id" :key="section.id" class="medical-record__section">
                <slot :name="section.id" />
            </div>
            <footer class="medical-record__pager">
                <Button label="Précédent" icon="pi pi-arrow-left" severity="secondary" text size="small" :disabled="currentIndex <= 0" @click="navigate(sections[currentIndex - 1].id, true)" />
                <Button label="Suivant" icon="pi pi-arrow-right" iconPos="right" severity="secondary" text size="small" :disabled="currentIndex >= sections.length - 1" @click="navigate(sections[currentIndex + 1].id, true)" />
            </footer>
        </div>
    </div>
</template>

<style scoped>
.medical-record {
    container: medical-record / inline-size;
    min-width: 0;
    font-family: var(--app-font-family);
    color: var(--text-color);
    background: var(--surface-card);
}
.medical-record__nav {
    display: flex;
    flex-wrap: wrap;
    gap: 0 1.25rem;
    border-bottom: 1px solid var(--surface-border);
}
.medical-record__nav button {
    padding: 0.625rem 0;
    border: 0;
    border-bottom: 2px solid transparent;
    border-radius: 0;
    background: transparent;
    color: var(--text-color-secondary);
    font-family: inherit;
    font-size: var(--page-section-subtitle-size);
    font-weight: 500;
    line-height: 1.4;
    cursor: pointer;
}
.medical-record__nav button:hover {
    color: var(--text-color);
}
.medical-record__nav button[aria-pressed='true'] {
    color: var(--text-color);
    border-bottom-color: var(--text-color);
    font-weight: 600;
}
.medical-record__nav button:focus-visible {
    outline: var(--p-focus-ring-width) var(--p-focus-ring-style) var(--p-focus-ring-color);
    outline-offset: var(--p-focus-ring-offset);
}
.medical-record__select {
    display: none;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0;
    border-bottom: 1px solid var(--surface-border);
    font-size: var(--page-section-subtitle-size);
}
.medical-record__select :deep(.p-select) {
    flex: 1;
    min-width: 0;
}
.medical-record__content {
    min-width: 0;
    padding: var(--page-content-gap) 0;
}
.medical-record--tall .medical-record__content {
    max-height: 65vh;
    overflow-y: auto;
    scrollbar-gutter: stable;
    padding-right: 0.5rem;
}
.medical-record__heading {
    margin-bottom: var(--page-content-gap);
}
.medical-record__heading h3 {
    margin: 0;
    font-size: var(--page-section-title-size);
    font-weight: 600;
    line-height: 1.3;
}
.medical-record__heading p {
    margin: 0.25rem 0 0;
    font-size: var(--page-section-subtitle-size);
    color: var(--text-color-secondary);
    line-height: 1.4;
}
.medical-record__pager {
    display: flex;
    justify-content: space-between;
    gap: var(--page-content-gap);
    padding-top: 0.625rem;
    margin-top: var(--page-content-gap);
    border-top: 1px solid var(--surface-border);
}
@container medical-record (max-width: 38rem) {
    .medical-record__nav {
        display: none;
    }
    .medical-record__select {
        display: flex;
    }
}
</style>

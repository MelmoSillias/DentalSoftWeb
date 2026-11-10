<script setup>
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
            <span class="medical-record__nav-title">Dans cette fiche</span>
            <button v-for="(section, index) in sections" :key="section.id" type="button" :aria-pressed="activeSection === section.id" :aria-controls="`${instanceId}-content`" @click="navigate(section.id)">
                <i :class="['pi', section.icon]" aria-hidden="true"></i>
                <span>{{ section.label }}</span>
                <span class="medical-record__number">{{ String(index + 1).padStart(2, '0') }}</span>
            </button>
        </nav>
        <div :id="`${instanceId}-content`" ref="content" class="medical-record__content" tabindex="-1" :aria-labelledby="`${instanceId}-title`">
            <header class="medical-record__heading">
                <span class="medical-record__eyebrow">Rubrique {{ currentIndex + 1 }} / {{ sections.length }}</span>
                <h3 :id="`${instanceId}-title`">{{ current?.label }}</h3>
                <p>{{ current?.description }}</p>
            </header>
            <div v-for="section in sections" v-show="activeSection === section.id" :key="section.id" class="medical-record__section">
                <slot :name="section.id" />
            </div>
            <footer class="medical-record__pager">
                <button type="button" :disabled="currentIndex <= 0" @click="navigate(sections[currentIndex - 1].id, true)"><i class="pi pi-arrow-left" aria-hidden="true"></i> Précédent</button>
                <button type="button" :disabled="currentIndex >= sections.length - 1" @click="navigate(sections[currentIndex + 1].id, true)">Suivant <i class="pi pi-arrow-right" aria-hidden="true"></i></button>
            </footer>
        </div>
    </div>
</template>

<style scoped>
.medical-record {
    display: grid;
    grid-template-columns: 13.5rem minmax(0, 1fr);
    border: 1px solid var(--surface-border);
    border-radius: 0.75rem;
    overflow: hidden;
    background: var(--surface-card);
    color: var(--text-color);
}
.medical-record__nav {
    padding: 1rem 0.65rem;
    background: var(--surface-ground);
    border-right: 1px solid var(--surface-border);
}
.medical-record__nav-title,
.medical-record__eyebrow {
    display: block;
    color: var(--text-color-secondary);
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 0.65rem;
}
.medical-record__nav-title {
    padding: 0 0.65rem;
}
.medical-record button {
    font: inherit;
    cursor: pointer;
    color: inherit;
    border-radius: 0.5rem;
}
.medical-record__nav button {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    width: 100%;
    text-align: left;
    padding: 0.8rem 0.65rem;
    margin-bottom: 0.25rem;
    background: transparent;
    border: 1px solid transparent;
    font-size: 0.9rem;
    line-height: 1.4;
}
.medical-record__nav button:hover {
    background: var(--surface-hover);
}
.medical-record__nav button[aria-pressed='true'] {
    background: var(--surface-card);
    color: var(--primary-color);
    border-color: var(--surface-border);
    font-weight: 700;
    box-shadow: inset 3px 0 var(--primary-color);
}
.medical-record button:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: -2px;
}
.medical-record__number {
    margin-left: auto;
    font-size: 0.75rem;
    opacity: 0.7;
}
.medical-record__content {
    min-width: 0;
    padding: 1.5rem;
    max-height: 42rem;
    overflow-y: auto;
}
.medical-record--tall .medical-record__content {
    max-height: 65vh;
}
.medical-record__heading {
    padding-bottom: 1.25rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid var(--surface-border);
}
.medical-record__heading h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
}
.medical-record__heading p {
    margin: 0.4rem 0 0;
    color: var(--text-color-secondary);
    line-height: 1.5;
}
.medical-record__pager {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding-top: 1.5rem;
    margin-top: 1.5rem;
    border-top: 1px solid var(--surface-border);
}
.medical-record__pager button {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 0.8rem;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
}
.medical-record__pager button:disabled {
    opacity: 0.4;
    cursor: default;
}
@media (max-width: 900px) {
    .medical-record {
        grid-template-columns: minmax(0, 1fr);
    }
    .medical-record__nav {
        display: flex;
        gap: 0.4rem;
        overflow-x: auto;
        padding: 0.65rem;
        border-right: 0;
        border-bottom: 1px solid var(--surface-border);
    }
    .medical-record__nav-title,
    .medical-record__number {
        display: none;
    }
    .medical-record__nav button {
        width: auto;
        flex-shrink: 0;
        margin: 0;
        white-space: nowrap;
    }
    .medical-record__content {
        padding: 1rem;
    }
}
</style>

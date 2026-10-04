<script setup>
import { computed } from 'vue';
import { findSection } from '../data/outline';
import { useManualAudience } from '../useManualAudience';
import ManualRoleBadge from './ManualRoleBadge.vue';

const props = defineProps({
    id: { type: String, required: true },
    /** Starts the section on a new printed page. */
    pageBreak: { type: Boolean, default: false }
});

const { isVisible } = useManualAudience();
const section = computed(() => findSection(props.id));
</script>

<template>
    <section v-if="section && isVisible(section.roles)" :id="section.id" class="m-section" :class="{ 'm-section--break': pageBreak }" data-manual-anchor>
        <header class="m-section__head">
            <h3>
                <span class="m-section__num">{{ section.number }}</span>
                {{ section.title }}
            </h3>
            <ManualRoleBadge :roles="section.roles" />
        </header>
        <slot :section="section" />
    </section>
</template>

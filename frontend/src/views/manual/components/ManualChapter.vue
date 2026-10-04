<script setup>
import { computed } from 'vue';
import { findChapter } from '../data/outline';
import { useManualAudience } from '../useManualAudience';
import ManualRoleBadge from './ManualRoleBadge.vue';

const props = defineProps({
    id: { type: String, required: true }
});

const { isVisible } = useManualAudience();
const chapter = computed(() => findChapter(props.id));
</script>

<template>
    <section v-if="chapter && isVisible(chapter.roles)" :id="chapter.id" class="m-chapter" data-manual-anchor>
        <header class="m-chapter__head">
            <p class="m-chapter__kicker">{{ chapter.kicker }}</p>
            <h2>{{ chapter.title }}</h2>
            <p v-if="chapter.goal" class="m-chapter__goal"><strong>Objectif.</strong> {{ chapter.goal }}</p>
            <ManualRoleBadge :roles="chapter.roles" />
        </header>
        <slot />
    </section>
</template>

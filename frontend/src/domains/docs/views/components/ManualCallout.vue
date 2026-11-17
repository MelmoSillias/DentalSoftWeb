<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, default: 'note', validator: (value) => ['tip', 'warn', 'note', 'critical'].includes(value) },
    title: { type: String, default: '' }
});

const DEFAULTS = {
    tip: { label: 'Conseil', icon: 'pi pi-lightbulb' },
    warn: { label: 'Attention', icon: 'pi pi-exclamation-triangle' },
    note: { label: 'Note', icon: 'pi pi-info-circle' },
    critical: { label: 'Action irréversible', icon: 'pi pi-ban' }
};

const meta = computed(() => DEFAULTS[props.type]);
</script>

<template>
    <aside class="m-callout" :class="`m-callout--${type}`" :role="type === 'critical' || type === 'warn' ? 'alert' : 'note'">
        <p class="m-callout__label"><i :class="meta.icon" aria-hidden="true"></i>{{ title || meta.label }}</p>
        <div class="m-callout__body"><slot /></div>
    </aside>
</template>

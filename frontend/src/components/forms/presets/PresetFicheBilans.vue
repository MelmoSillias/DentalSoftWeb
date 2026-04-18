<script setup>
import PresetTemplateBlock from '@/components/forms/presets/PresetTemplateBlock.vue';
</script>

<template>
    <PresetTemplateBlock
        title="Bilans reutilisables"
        icon="pi pi-chart-line"
        description="Sections bilans hors formule dentaire."
        :bullets="['Bilan radiographique', 'Bilan sanguin', 'Diagnostic positif']"
    />
</template>

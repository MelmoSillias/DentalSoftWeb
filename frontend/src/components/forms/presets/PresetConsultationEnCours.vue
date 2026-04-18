<script setup>
import PresetTemplateBlock from '@/components/forms/presets/PresetTemplateBlock.vue';
</script>

<template>
    <PresetTemplateBlock
        title="Consultation en cours"
        icon="pi pi-play-circle"
        description="Bloc systeme de saisie consultation active."
        :bullets="['Observations', 'Actes du jour', 'Validation']"
    />
</template>

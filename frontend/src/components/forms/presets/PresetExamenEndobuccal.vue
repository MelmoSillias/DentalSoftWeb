<script setup>
import PresetTemplateBlock from '@/components/forms/presets/PresetTemplateBlock.vue';
</script>

<template>
    <PresetTemplateBlock
        title="Examen endobuccal"
        icon="pi pi-eye"
        description="Section reutilisable pour examen bouche fermee/ouverte."
        :bullets="['Bouche fermee', 'Bouche ouverte', 'Occlusion']"
    />
</template>

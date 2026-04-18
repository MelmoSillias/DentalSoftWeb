<script setup>
import PresetTemplateBlock from '@/components/forms/presets/PresetTemplateBlock.vue';
</script>

<template>
    <PresetTemplateBlock
        title="Entretien verbal"
        icon="pi pi-comments"
        description="Cartes reutilisables d'anamnese et d'entretien patient."
        :bullets="['Motif de consultation', 'Anamnese', 'Habitudes']"
    />
</template>

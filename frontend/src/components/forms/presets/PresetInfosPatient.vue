<script setup>
import PresetTemplateBlock from '@/components/forms/presets/PresetTemplateBlock.vue';
</script>

<template>
    <PresetTemplateBlock
        title="Informations patient"
        icon="pi pi-user"
        description="Bloc systeme commun a tous les formulaires."
        :bullets="['Identite', 'Contact', 'Antecedents']"
    />
</template>

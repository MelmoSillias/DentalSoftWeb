<script setup>
import PresetTemplateBlock from '@/components/forms/presets/PresetTemplateBlock.vue';
</script>

<template>
    <PresetTemplateBlock
        title="Seances passees"
        icon="pi pi-history"
        description="Bloc systeme historique des seances."
        :bullets="['Date', 'Actes', 'Compte-rendu']"
    />
</template>

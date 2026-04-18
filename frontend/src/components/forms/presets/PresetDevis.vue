<script setup>
import PresetTemplateBlock from '@/components/forms/presets/PresetTemplateBlock.vue';
</script>

<template>
    <PresetTemplateBlock
        title="Devis"
        icon="pi pi-wallet"
        description="Section reutilisable pour generation de devis."
        :bullets="['Lignes de devis', 'Montants', 'Couverture assurance']"
    />
</template>

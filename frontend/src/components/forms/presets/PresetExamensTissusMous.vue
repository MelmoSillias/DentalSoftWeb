<script setup>
import PresetTemplateBlock from '@/components/forms/presets/PresetTemplateBlock.vue';
</script>

<template>
    <PresetTemplateBlock
        title="Examens tissus mous"
        icon="pi pi-circle-fill"
        description="Section reutilisable pour analyse tissus mous."
        :bullets="['Muqueuse', 'Langue', 'Palais']"
    />
</template>
